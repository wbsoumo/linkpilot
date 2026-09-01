<?php
// backend/api/profile/update.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$db = Database::getConnection();

try {
    // Fetch existing user/profile to merge inputs
    $stmtUserSel = $db->prepare("SELECT name, phone_number FROM users WHERE id = ?");
    $stmtUserSel->execute([$userId]);
    $existingUser = $stmtUserSel->fetch();

    $stmtSelect = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmtSelect->execute([$userId]);
    $existing = $stmtSelect->fetch();

    $name = isset($input['name']) ? trim($input['name']) : ($existingUser['name'] ?? '');
    $phoneNumber = isset($input['phone_number']) ? trim($input['phone_number']) : ($existingUser['phone_number'] ?? '');
    $userType = isset($input['user_type']) ? trim($input['user_type']) : ($existing['user_type'] ?? 'owner');
    $jobTitle = isset($input['job_title']) ? trim($input['job_title']) : ($existing['job_title'] ?? '');
    $experienceYears = isset($input['experience_years']) ? (int)$input['experience_years'] : ($existing['experience_years'] ?? 0);
    $skills = isset($input['skills']) ? trim($input['skills']) : ($existing['skills'] ?? '');
    $companyName = isset($input['company_name']) ? trim($input['company_name']) : ($existing['company_name'] ?? '');
    $website = isset($input['website']) ? trim($input['website']) : ($existing['website'] ?? '');
    $portfolioUrl = isset($input['portfolio_url']) ? trim($input['portfolio_url']) : ($existing['portfolio_url'] ?? '');
    $linkedinUrl = isset($input['linkedin_url']) ? trim($input['linkedin_url']) : ($existing['linkedin_url'] ?? '');
    $aboutMe = isset($input['about_me']) ? trim($input['about_me']) : ($existing['about_me'] ?? '');

    $businessAddress = isset($input['business_address']) ? trim($input['business_address']) : ($existing['business_address'] ?? null);
    $taxId = isset($input['tax_id']) ? trim($input['tax_id']) : ($existing['tax_id'] ?? null);
    $supportEmail = isset($input['support_email']) ? trim($input['support_email']) : ($existing['support_email'] ?? null);
    $currency = isset($input['currency']) ? trim($input['currency']) : ($existing['currency'] ?? 'INR');
    $timezone = isset($input['timezone']) ? trim($input['timezone']) : ($existing['timezone'] ?? 'Asia/Kolkata');
    $webhookUrl = isset($input['webhook_url']) ? trim($input['webhook_url']) : ($existing['webhook_url'] ?? null);
    
    $notificationLeads = isset($input['notification_leads']) ? (int)$input['notification_leads'] : (isset($existing['notification_leads']) ? (int)$existing['notification_leads'] : 1);
    $notificationTasks = isset($input['notification_tasks']) ? (int)$input['notification_tasks'] : (isset($existing['notification_tasks']) ? (int)$existing['notification_tasks'] : 1);
    $notificationDigest = isset($input['notification_digest']) ? (int)$input['notification_digest'] : (isset($existing['notification_digest']) ? (int)$existing['notification_digest'] : 0);
    $notificationErrors = isset($input['notification_errors']) ? (int)$input['notification_errors'] : (isset($existing['notification_errors']) ? (int)$existing['notification_errors'] : 1);
    
    $twoFactorEnabled = isset($input['two_factor_enabled']) ? (int)$input['two_factor_enabled'] : (isset($existing['two_factor_enabled']) ? (int)$existing['two_factor_enabled'] : 0);
    $emailOpenTracking = isset($input['email_open_tracking']) ? (int)$input['email_open_tracking'] : (isset($existing['email_open_tracking']) ? (int)$existing['email_open_tracking'] : 1);

    // Validation
    if (empty($name) || empty($userType)) {
        sendJsonResponse('error', 'Name and user type are required.', [], 400);
    }

    if (!empty($phoneNumber)) {
        if (!preg_match('/^[0-9]{10}$/', $phoneNumber)) {
            sendJsonResponse('error', 'Phone number must contain exactly 10 digits with no characters or special symbols.', [], 400);
        }
    }

    // Begin transaction
    $db->beginTransaction();
    
    // 1. Update Name and Phone in users
    $stmtUser = $db->prepare("UPDATE users SET name = ?, phone_number = ? WHERE id = ?");
    $stmtUser->execute([$name, $phoneNumber, $userId]);
    
    // 2. Update Profile fields
    $stmtProfile = $db->prepare("
        INSERT INTO user_profiles (
            user_id, user_type, job_title, experience_years, skills, company_name, website, portfolio_url, linkedin_url, about_me,
            business_address, tax_id, support_email, currency, timezone, webhook_url,
            notification_leads, notification_tasks, notification_digest, notification_errors, two_factor_enabled, email_open_tracking
        ) 
        VALUES (
            :user_id, :user_type, :job_title, :experience_years, :skills, :company_name, :website, :portfolio_url, :linkedin_url, :about_me,
            :business_address, :tax_id, :support_email, :currency, :timezone, :webhook_url,
            :notification_leads, :notification_tasks, :notification_digest, :notification_errors, :two_factor_enabled, :email_open_tracking
        )
        ON DUPLICATE KEY UPDATE 
            user_type = VALUES(user_type),
            job_title = VALUES(job_title),
            experience_years = VALUES(experience_years),
            skills = VALUES(skills),
            company_name = VALUES(company_name),
            website = VALUES(website),
            portfolio_url = VALUES(portfolio_url),
            linkedin_url = VALUES(linkedin_url),
            about_me = VALUES(about_me),
            business_address = VALUES(business_address),
            tax_id = VALUES(tax_id),
            support_email = VALUES(support_email),
            currency = VALUES(currency),
            timezone = VALUES(timezone),
            webhook_url = VALUES(webhook_url),
            notification_leads = VALUES(notification_leads),
            notification_tasks = VALUES(notification_tasks),
            notification_digest = VALUES(notification_digest),
            notification_errors = VALUES(notification_errors),
            two_factor_enabled = VALUES(two_factor_enabled),
            email_open_tracking = VALUES(email_open_tracking)
    ");
    
    $stmtProfile->execute([
        'user_id' => $userId,
        'user_type' => $userType,
        'job_title' => $jobTitle,
        'experience_years' => $experienceYears,
        'skills' => $skills,
        'company_name' => $companyName,
        'website' => $website,
        'portfolio_url' => $portfolioUrl,
        'linkedin_url' => $linkedinUrl,
        'about_me' => $aboutMe,
        'business_address' => $businessAddress,
        'tax_id' => $taxId,
        'support_email' => $supportEmail,
        'currency' => $currency,
        'timezone' => $timezone,
        'webhook_url' => $webhookUrl,
        'notification_leads' => $notificationLeads,
        'notification_tasks' => $notificationTasks,
        'notification_digest' => $notificationDigest,
        'notification_errors' => $notificationErrors,
        'two_factor_enabled' => $twoFactorEnabled,
        'email_open_tracking' => $emailOpenTracking
    ]);
    
    // Commit transaction
    $db->commit();
    
    logActivity($userId, "Updated profile information.");
    
    sendJsonResponse('success', 'Profile updated successfully.');
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $msg = $e->getMessage();
    if ($e instanceof PDOException || strpos($msg, 'SQLSTATE') !== false) {
        if (strpos($msg, '1062 Duplicate entry') !== false) {
            if (strpos($msg, 'phone_number') !== false) {
                $msg = 'This phone number is already associated with another account.';
            } else if (strpos($msg, 'email') !== false) {
                $msg = 'This email address is already associated with another account.';
            } else {
                $msg = 'A profile record with this unique detail already exists.';
            }
        } else {
            $msg = 'A database error occurred while updating profile.';
        }
    }
    sendJsonResponse('error', 'Server error updating profile: ' . $msg, [], 500);
}
