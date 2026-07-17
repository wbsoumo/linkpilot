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

    // Self-healing database migrations for booking system (runs transparently on live server)
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `crm_booking_profiles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNIQUE NOT NULL,
            `booking_id` VARCHAR(16) UNIQUE NOT NULL,
            `timezone` VARCHAR(100) DEFAULT 'Asia/Kolkata',
            `duration_minutes` INT DEFAULT 30,
            `meeting_provider` VARCHAR(30) DEFAULT 'google',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_booking_profile_user_live` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Try adding meeting_provider column in case table already exists
        try {
            $db->exec("ALTER TABLE `crm_booking_profiles` ADD COLUMN `meeting_provider` VARCHAR(30) DEFAULT 'google' AFTER `duration_minutes`");
        } catch (Exception $colEx) {
            // Column already exists
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `crm_booking_availability` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `day_of_week` INT NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_booking_avail_user_live` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            UNIQUE KEY `idx_user_day_slot` (`user_id`, `day_of_week`, `start_time`, `end_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $migEx) {
        // Silently capture migration issues
    }

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
        $guests = $input['guests'] ?? [];

        if (empty($bookingId) || empty($dateStr) || empty($timeStr) || empty($guestName) || empty($guestEmail)) {
            sendJsonResponse('error', 'All fields (link_id, date, time, name, email) are required.', [], 400);
        }

        // Fetch host profile joining users table to get host name and email
        $stmt = $db->prepare("
            SELECT p.user_id, p.duration_minutes, p.timezone, p.meeting_provider, u.name AS host_name, u.email AS host_email 
            FROM crm_booking_profiles p
            JOIN users u ON p.user_id = u.id
            WHERE p.booking_id = ?
        ");
        $stmt->execute([$bookingId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            sendJsonResponse('error', 'Booking profile not found.', [], 404);
        }

        $hostUserId = $profile['user_id'];
        $duration = (int)$profile['duration_minutes'];
        $preferredProvider = trim($input['preferred_provider'] ?? '');
        $meetingProvider = !empty($preferredProvider) ? $preferredProvider : ($profile['meeting_provider'] ?? 'google');

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
        if ($meetingProvider === 'zoom') {
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
                
                // Fetch updated location in case Google Meet link was created
                $stmtGetLoc = $db->prepare("SELECT location FROM crm_meetings WHERE id = ?");
                $stmtGetLoc->execute([$meetingId]);
                $dbLoc = $stmtGetLoc->fetchColumn();
                if ($dbLoc) {
                    $location = $dbLoc;
                }
            }
        } catch (Exception $e) {
            error_log("Google Calendar Meeting sync failed for booking: " . $e->getMessage());
        }

        // -------------------------------------------------------------
        // ICS FILE GENERATION & EMAIL SENDING
        // -------------------------------------------------------------
        try {
            require_once __DIR__ . '/../../smtp_helper.php';

            // Generate ICS file content
            $hostName = $profile['host_name'] ?? 'Host';
            $hostEmail = $profile['host_email'] ?? '';
            
            $dtStart = date('Ymd\THis\Z', strtotime($startStr) - date('Z'));
            $dtEnd = date('Ymd\THis\Z', strtotime($endStr) - date('Z'));
            $dtStamp = date('Ymd\THis\Z');
            $uid = uniqid('linkpilot-', true) . '@linkpilot.work';

            $icsContent = "BEGIN:VCALENDAR\r\n";
            $icsContent .= "VERSION:2.0\r\n";
            $icsContent .= "PRODID:-//LinkPilot//Booking System//EN\r\n";
            $icsContent .= "CALSCALE:GREGORIAN\r\n";
            $icsContent .= "METHOD:REQUEST\r\n";
            $icsContent .= "BEGIN:VEVENT\r\n";
            $icsContent .= "UID:{$uid}\r\n";
            $icsContent .= "DTSTAMP:{$dtStamp}\r\n";
            $icsContent .= "DTSTART:{$dtStart}\r\n";
            $icsContent .= "DTEND:{$dtEnd}\r\n";
            $icsContent .= "SUMMARY:" . str_replace("\n", " ", $title) . "\r\n";
            $icsContent .= "DESCRIPTION:" . str_replace("\n", "\\n", $description) . "\r\n";
            $icsContent .= "LOCATION:" . str_replace("\n", " ", $location) . "\r\n";
            if (!empty($hostEmail)) {
                $icsContent .= "ORGANIZER;CN=\"{$hostName}\":MAILTO:{$hostEmail}\r\n";
            }
            $icsContent .= "STATUS:CONFIRMED\r\n";
            $icsContent .= "SEQUENCE:0\r\n";
            $icsContent .= "END:VEVENT\r\n";
            $icsContent .= "END:VCALENDAR\r\n";

            $tempDir = sys_get_temp_dir();
            $icsPath = $tempDir . '/invite-' . uniqid() . '.ics';
            file_put_contents($icsPath, $icsContent);

            $attachments = [
                [
                    'path' => $icsPath,
                    'name' => 'invite.ics'
                ]
            ];

            // Build list of CC emails (Host + additional guest emails)
            $ccEmails = [];
            if (!empty($hostEmail)) {
                $ccEmails[] = $hostEmail;
            }
            if (!empty($guests) && is_array($guests)) {
                foreach ($guests as $g) {
                    $g = trim($g);
                    if (filter_var($g, FILTER_VALIDATE_EMAIL) && strtolower($g) !== strtolower($guestEmail)) {
                        $ccEmails[] = $g;
                    }
                }
            }

            // Formatting email body template
            $startTimeObj = new DateTime($startStr);
            $endTimeObj = new DateTime($endStr);
            $dateTimeFormatted = $startTimeObj->format('l, d F Y \a\t h:i A') . ' - ' . $endTimeObj->format('h:i A');
            $locationLink = (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) 
                ? "<a href=\"{$location}\" style=\"color: #4f46e5; font-weight: bold; text-decoration: underline;\">Join Meeting</a>" 
                : htmlspecialchars($location);

            $subject = "Confirmed: Meeting with {$hostName} on " . $startTimeObj->format('M d, Y');
            $emailBody = "
                <div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155; line-height: 1.6; max-width: 600px; margin: 0 auto;\">
                    <h2 style=\"color: #1e1b4b; font-size: 20px; font-weight: 800; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;\">Meeting Confirmed</h2>
                    <p>Hello <strong>{$guestName}</strong>,</p>
                    <p>Your meeting with <strong>{$hostName}</strong> has been successfully scheduled. Below are the details:</p>
                    
                    <div style=\"background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 20px 0;\">
                        <table style=\"width: 100%; font-size: 13px;\">
                            <tr>
                                <td style=\"width: 100px; font-weight: bold; color: #64748b; padding-bottom: 8px;\">Meeting:</td>
                                <td style=\"color: #0f172a; padding-bottom: 8px; font-weight: bold;\">{$title}</td>
                            </tr>
                            <tr>
                                <td style=\"font-weight: bold; color: #64748b; padding-bottom: 8px;\">Date & Time:</td>
                                <td style=\"color: #0f172a; padding-bottom: 8px; font-weight: bold;\">{$dateTimeFormatted}</td>
                            </tr>
                            <tr>
                                <td style=\"font-weight: bold; color: #64748b; padding-bottom: 8px;\">Time Zone:</td>
                                <td style=\"color: #0f172a; padding-bottom: 8px;\">{$profile['timezone']}</td>
                            </tr>
                            <tr>
                                <td style=\"font-weight: bold; color: #64748b; padding-bottom: 8px;\">Location:</td>
                                <td style=\"color: #0f172a; padding-bottom: 8px;\">{$locationLink}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <p style=\"font-size: 12px; color: #64748b;\">We have attached an iCalendar (.ics) file to this message so you can add this appointment directly to your calendar.</p>
                    <p>Thank you for using our scheduling service!</p>
                    <p style=\"border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 12px; color: #94a3b8;\">Powered by LinkPilot AI CRM</p>
                </div>
            ";

            SMTPHelper::sendEmail($hostUserId, $guestEmail, $subject, $emailBody, $attachments, null, null, $ccEmails);
            
            // Delete temp ICS file
            @unlink($icsPath);
        } catch (Exception $mailEx) {
            error_log("Failed to send booking confirmation email: " . $mailEx->getMessage());
        }

        // Fetch contact phone if available
        $senderPhone = '';
        $stmtCPhone = $db->prepare("SELECT phone, whatsapp FROM crm_contacts WHERE id = ?");
        $stmtCPhone->execute([$contactId]);
        $cPhone = $stmtCPhone->fetch();
        if ($cPhone) {
            $senderPhone = !empty($cPhone['whatsapp']) ? $cPhone['whatsapp'] : (!empty($cPhone['phone']) ? $cPhone['phone'] : '');
        }

        // Trigger active visual workflows for meeting_scheduled
        try {
            require_once __DIR__ . '/../../workflow_runner.php';
            $stmtVisual = $db->prepare("SELECT * FROM automation_workflows WHERE user_id = ? AND trigger_type = 'meeting_scheduled' AND is_active = 1");
            $stmtVisual->execute([$hostUserId]);
            $visualWfs = $stmtVisual->fetchAll();
            
            $vContext = [
                'sender_email' => $guestEmail,
                'sender_name' => $guestName,
                'sender_phone' => $senderPhone,
                'guest_email' => $guestEmail,
                'guest_name' => $guestName,
                'meeting_title' => $title,
                'meeting_start' => $startStr,
                'meeting_end' => $endStr,
                'meeting_location' => $location,
                'time' => date('h:i A', strtotime($startStr)),
                'date' => date('l, d F Y', strtotime($startStr)),
                'contact_id' => $contactId
            ];
            
            foreach ($visualWfs as $vwf) {
                WorkflowRunner::execute($hostUserId, $vwf, $vContext);
            }
        } catch (Throwable $vEx) {
            error_log("Failed to trigger meeting_scheduled workflow: " . $vEx->getMessage());
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
                    INSERT INTO crm_booking_profiles (user_id, booking_id, timezone, duration_minutes, meeting_provider) 
                    VALUES (?, ?, 'Asia/Kolkata', 30, 'google')
                ");
                $stmtInsert->execute([$userId, $bookingId]);

                $profile = [
                    'user_id' => $userId,
                    'booking_id' => $bookingId,
                    'timezone' => 'Asia/Kolkata',
                    'duration_minutes' => 30,
                    'meeting_provider' => 'google'
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
            $provider = trim($input['meeting_provider'] ?? 'google');
            $slots = $input['availability'] ?? []; // Array of {day_of_week: int, start_time: string, end_time: string}

            // Upsert profile details
            $stmtUpdate = $db->prepare("
                INSERT INTO crm_booking_profiles (user_id, booking_id, timezone, duration_minutes, meeting_provider) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE timezone = VALUES(timezone), duration_minutes = VALUES(duration_minutes), meeting_provider = VALUES(meeting_provider)
            ");
            // If profile does not exist yet (though GET handles it), generate ID
            $bookingId = '';
            for ($i = 0; $i < 16; $i++) {
                $bookingId .= rand(0, 9);
            }
            $stmtUpdate->execute([$userId, $bookingId, $timezone, $duration, $provider]);

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
