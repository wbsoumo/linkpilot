<?php
// backend/api/crm/booking.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';
require_once __DIR__ . '/../../external_apps_helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// CORS Headers are already handled in config.php

try {
    $db = Database::getConnection();

    // -------------------------------------------------------------------------
    // PUBLIC ENDPOINTS (No authentication required)
    // -------------------------------------------------------------------------
    if ($action === 'public_profile') {
        $bookingId = trim($_GET['link_id'] ?? '');
        if (empty($bookingId)) {
            sendJsonResponse('error', 'Booking link ID is required.', [], 400);
        }

        // Fetch host profile
        $stmt = $db->prepare("
            SELECT p.*, u.name AS host_name, u.email AS host_email 
            FROM crm_booking_profiles p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.booking_id = ?
        ");
        $stmt->execute([$bookingId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            sendJsonResponse('error', 'Booking profile not found.', [], 404);
        }

        // Fetch availability ranges
        $stmtAvail = $db->prepare("
            SELECT day_of_week, start_time, end_time 
            FROM crm_booking_availability 
            WHERE user_id = ?
            ORDER BY day_of_week ASC, start_time ASC
        ");
        $stmtAvail->execute([$profile['user_id']]);
        $availability = $stmtAvail->fetchAll();

        sendJsonResponse('success', 'Public profile retrieved successfully', [
            'host_name' => $profile['host_name'],
            'timezone' => $profile['timezone'],
            'duration_minutes' => (int)$profile['duration_minutes'],
            'availability' => $availability
        ]);
    }

    elseif ($action === 'check_slots') {
        $bookingId = trim($_GET['link_id'] ?? '');
        $dateStr = trim($_GET['date'] ?? ''); // YYYY-MM-DD

        if (empty($bookingId) || empty($dateStr)) {
            sendJsonResponse('error', 'Booking link ID and Date are required.', [], 400);
        }

        // Verify date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $dateStr);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $dateStr) {
            sendJsonResponse('error', 'Invalid date format. Use YYYY-MM-DD.', [], 400);
        }

        // Fetch profile
        $stmt = $db->prepare("SELECT user_id, timezone, duration_minutes FROM crm_booking_profiles WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            sendJsonResponse('error', 'Booking profile not found.', [], 404);
        }

        $userId = $profile['user_id'];
        $timezone = new DateTimeZone($profile['timezone']);
        $duration = (int)$profile['duration_minutes'];

        // Get day of week (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
        $dayOfWeek = (int)$dateObj->format('w');

        // Fetch availability slots for this day of week
        $stmtAvail = $db->prepare("
            SELECT start_time, end_time 
            FROM crm_booking_availability 
            WHERE user_id = ? AND day_of_week = ?
        ");
        $stmtAvail->execute([$userId, $dayOfWeek]);
        $ranges = $stmtAvail->fetchAll();

        $slots = [];

        // Fetch existing meetings on this date
        $stmtMeetings = $db->prepare("
            SELECT start_time, end_time 
            FROM crm_meetings 
            WHERE user_id = ? AND status = 'scheduled' AND DATE(start_time) = ?
        ");
        $stmtMeetings->execute([$userId, $dateStr]);
        $existingMeetings = $stmtMeetings->fetchAll();

        // Get current time in host's timezone to filter past slots for today
        $now = new DateTime('now', $timezone);
        $isToday = ($dateStr === $now->format('Y-m-d'));

        foreach ($ranges as $range) {
            $startStr = $dateStr . ' ' . $range['start_time'];
            $endStr = $dateStr . ' ' . $range['end_time'];

            $currentSlotStart = new DateTime($startStr, $timezone);
            $rangeEnd = new DateTime($endStr, $timezone);

            while (true) {
                $currentSlotEnd = clone $currentSlotStart;
                $currentSlotEnd->modify('+' . $duration . ' minutes');

                if ($currentSlotEnd > $rangeEnd) {
                    break;
                }

                // Filter past slots if today
                if ($isToday && $currentSlotStart <= $now) {
                    $currentSlotStart = clone $currentSlotEnd;
                    continue;
                }

                // Check overlap with existing meetings
                $overlapping = false;
                foreach ($existingMeetings as $meeting) {
                    $mStart = new DateTime($meeting['start_time'], $timezone);
                    $mEnd = new DateTime($meeting['end_time'], $timezone);

                    // Interval overlap condition: !(slot_end <= meeting_start || slot_start >= meeting_end)
                    if (!($currentSlotEnd <= $mStart || $currentSlotStart >= $mEnd)) {
                        $overlapping = true;
                        break;
                    }
                }

                if (!$overlapping) {
                    $slots[] = $currentSlotStart->format('H:i');
                }

                $currentSlotStart = clone $currentSlotEnd;
            }
        }

        sendJsonResponse('success', 'Available slots retrieved', ['slots' => $slots]);
    }

    elseif ($action === 'schedule_event') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $bookingId = trim($input['link_id'] ?? '');
        $dateStr = trim($input['date'] ?? '');
        $timeStr = trim($input['time'] ?? ''); // HH:MM
        $guestName = trim($input['name'] ?? '');
        $guestEmail = trim($input['email'] ?? '');
        $remarks = trim($input['remarks'] ?? '');

        if (empty($bookingId) || empty($dateStr) || empty($timeStr) || empty($guestName) || empty($guestEmail)) {
            sendJsonResponse('error', 'All fields (link_id, date, time, name, email) are required.', [], 400);
        }

        // Fetch host profile
        $stmt = $db->prepare("SELECT user_id, duration_minutes, timezone FROM crm_booking_profiles WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            sendJsonResponse('error', 'Booking profile not found.', [], 404);
        }

        $hostUserId = $profile['user_id'];
        $duration = (int)$profile['duration_minutes'];

        // Compute start & end times
        $timezone = new DateTimeZone($profile['timezone']);
        $startTime = new DateTime($dateStr . ' ' . $timeStr, $timezone);
        $endTime = clone $startTime;
        $endTime->modify('+' . $duration . ' minutes');

        $startStr = $startTime->format('Y-m-d H:i:s');
        $endStr = $endTime->format('Y-m-d H:i:s');

        // Upsert contact in host's CRM contacts
        $stmtContact = $db->prepare("SELECT id FROM crm_contacts WHERE user_id = ? AND email = ?");
        $stmtContact->execute([$hostUserId, $guestEmail]);
        $contact = $stmtContact->fetch();

        if ($contact) {
            $contactId = $contact['id'];
        } else {
            $stmtInsertContact = $db->prepare("
                INSERT INTO crm_contacts (user_id, name, email, notes) 
                VALUES (?, ?, ?, 'Created automatically from booking page.')
            ");
            $stmtInsertContact->execute([$hostUserId, $guestName, $guestEmail]);
            $contactId = $db->lastInsertId();
        }

        // Determine meeting location: Google Meet if connected, else Zoom, else normal Meet
        $location = 'Google Meet';
        if (ExternalAppsHelper::isZoomConnected($hostUserId)) {
            $location = 'Zoom Meeting';
        }

        // Create scheduled meeting
        $title = "{$duration} Minute Meeting with {$guestName}";
        $description = "Scheduled via Booking Link.\n\nVisitor Message: " . $remarks;

        $stmtMeet = $db->prepare("
            INSERT INTO crm_meetings (user_id, contact_id, title, description, start_time, end_time, location, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
        ");
        $stmtMeet->execute([
            $hostUserId, $contactId, $title, $description, $startStr, $endStr, $location
        ]);
        $meetingId = $db->lastInsertId();

        // Sync to external apps if connected
        if ($location === 'Zoom Meeting') {
            try {
                $zoomData = ExternalAppsHelper::createZoomMeeting($hostUserId, $meetingId, $title, $description, $startStr, $endStr);
                if ($zoomData && isset($zoomData['join_url'])) {
                    $location = $zoomData['join_url'];
                    $stmtUpdate = $db->prepare("UPDATE crm_meetings SET location = ? WHERE id = ?");
                    $stmtUpdate->execute([$location, $meetingId]);
                }
            } catch (Exception $e) {
                error_log("Zoom Meeting sync failed for booking: " . $e->getMessage());
            }
        }

        try {
            if (ExternalAppsHelper::isGoogleConnected($hostUserId)) {
                ExternalAppsHelper::createGoogleCalendarEvent($hostUserId, $meetingId, $title, $description, $startStr, $endStr, $location, $contactId);
            }
        } catch (Exception $e) {
            error_log("Google Calendar Meeting sync failed for booking: " . $e->getMessage());
        }

        // Log to timeline
        $timelineStmt = $db->prepare("
            INSERT INTO crm_timeline (user_id, contact_id, activity_type, description) 
            VALUES (?, ?, 'Meeting Scheduled', ?)
        ");
        $timelineStmt->execute([
            $hostUserId, $contactId, "Meeting '{$title}' was booked by {$guestName} ({$guestEmail}) via booking link."
        ]);

        sendJsonResponse('success', 'Meeting scheduled successfully', [
            'meeting_id' => $meetingId,
            'title' => $title,
            'start_time' => $startStr,
            'end_time' => $endStr,
            'location' => $location
        ]);
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATED ENDPOINTS (Requires JWT authentication)
    // -------------------------------------------------------------------------
    else {
        $user = JWTHelper::requireAuth();
        $userId = $user['id'];

        if ($method === 'GET') {
            // Fetch booking profile
            $stmt = $db->prepare("SELECT * FROM crm_booking_profiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            $profile = $stmt->fetch();

            if (!$profile) {
                // Generate a random 16-digit numeric booking ID
                $bookingId = '';
                for ($i = 0; $i < 16; $i++) {
                    $bookingId .= rand(0, 9);
                }

                $stmtInsert = $db->prepare("
                    INSERT INTO crm_booking_profiles (user_id, booking_id, timezone, duration_minutes) 
                    VALUES (?, ?, 'Asia/Kolkata', 30)
                ");
                $stmtInsert->execute([$userId, $bookingId]);

                $profile = [
                    'user_id' => $userId,
                    'booking_id' => $bookingId,
                    'timezone' => 'Asia/Kolkata',
                    'duration_minutes' => 30
                ];

                // Create default availability (Mon-Fri 11:00am - 6:30pm)
                $stmtAvail = $db->prepare("
                    INSERT INTO crm_booking_availability (user_id, day_of_week, start_time, end_time) 
                    VALUES (?, ?, '11:00:00', '18:30:00')
                ");
                for ($d = 1; $d <= 5; $d++) {
                    $stmtAvail->execute([$userId, $d]);
                }
            }

            // Fetch availability
            $stmtAvailList = $db->prepare("
                SELECT day_of_week, start_time, end_time 
                FROM crm_booking_availability 
                WHERE user_id = ?
                ORDER BY day_of_week ASC, start_time ASC
            ");
            $stmtAvailList->execute([$userId]);
            $availability = $stmtAvailList->fetchAll();

            sendJsonResponse('success', 'Booking settings retrieved', [
                'profile' => $profile,
                'availability' => $availability
            ]);
        }

        elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $timezone = trim($input['timezone'] ?? 'Asia/Kolkata');
            $duration = (int)($input['duration_minutes'] ?? 30);
            $slots = $input['availability'] ?? []; // Array of {day_of_week: int, start_time: string, end_time: string}

            // Upsert profile details
            $stmtUpdate = $db->prepare("
                INSERT INTO crm_booking_profiles (user_id, booking_id, timezone, duration_minutes) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE timezone = VALUES(timezone), duration_minutes = VALUES(duration_minutes)
            ");
            // If profile does not exist yet (though GET handles it), generate ID
            $bookingId = '';
            for ($i = 0; $i < 16; $i++) {
                $bookingId .= rand(0, 9);
            }
            $stmtUpdate->execute([$userId, $bookingId, $timezone, $duration]);

            // Replace availability schedule
            $db->prepare("DELETE FROM crm_booking_availability WHERE user_id = ?")->execute([$userId]);

            if (!empty($slots)) {
                $stmtInsertAvail = $db->prepare("
                    INSERT INTO crm_booking_availability (user_id, day_of_week, start_time, end_time) 
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($slots as $slot) {
                    $day = (int)$slot['day_of_week'];
                    $start = trim($slot['start_time']);
                    $end = trim($slot['end_time']);
                    if ($day >= 0 && $day <= 6 && !empty($start) && !empty($end)) {
                        $stmtInsertAvail->execute([$userId, $day, $start, $end]);
                    }
                }
            }

            sendJsonResponse('success', 'Booking settings updated successfully');
        }

        else {
            sendJsonResponse('error', 'Method not allowed', [], 405);
        }
    }
} catch (Exception $e) {
    sendJsonResponse('error', 'Booking operation failed: ' . $e->getMessage(), [], 500);
}
