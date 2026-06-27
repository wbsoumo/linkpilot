<?php
// backend/api/auth/save_step.php

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$step = isset($input['step']) ? (int)$input['step'] : 0;

if ($step < 1 || $step > 3) {
    sendJsonResponse('error', 'Invalid step value.', [], 400);
}

$db = Database::getConnection();

try {
    if ($step === 1) {
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $phoneNumber = trim($input['phone_number'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($phoneNumber) || empty($password)) {
            sendJsonResponse('error', 'Name, email, phone number, and password are required.', [], 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse('error', 'Invalid email address.', [], 400);
        }
        
        if (!preg_match('/^[6-9]\d{9}$/', $phoneNumber)) {
            sendJsonResponse('error', 'Invalid phone number. Must be a 10-digit mobile number.', [], 400);
        }
        
        if (strlen($password) < 6) {
            sendJsonResponse('error', 'Password must be at least 6 characters long.', [], 400);
        }
        
        // 1. Check if email or phone number is already registered by a VERIFIED user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendJsonResponse('error', 'An account with this email address already exists.', [], 409);
        }
        
        $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ? AND is_verified = 1");
        $stmt->execute([$phoneNumber]);
        if ($stmt->fetch()) {
            sendJsonResponse('error', 'An account with this phone number already exists.', [], 409);
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // 2. Check if an unverified user exists with the same email or phone to overwrite
        $stmt = $db->prepare("SELECT id FROM users WHERE (email = ? OR phone_number = ?) AND is_verified = 0 LIMIT 1");
        $stmt->execute([$email, $phoneNumber]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $userId = $existing['id'];
            $stmtUpdate = $db->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, password = ? WHERE id = ?");
            $stmtUpdate->execute([$name, $email, $phoneNumber, $passwordHash, $userId]);
        } else {
            $stmtInsert = $db->prepare("INSERT INTO users (name, email, phone_number, password, is_verified) VALUES (?, ?, ?, ?, 0)");
            $stmtInsert->execute([$name, $email, $phoneNumber, $passwordHash]);
            $userId = $db->lastInsertId();
        }
        
        // Ensure user profile exists
        $stmtProfileCheck = $db->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
        $stmtProfileCheck->execute([$userId]);
        if (!$stmtProfileCheck->fetch()) {
            $stmtProfileInsert = $db->prepare("INSERT INTO user_profiles (user_id, user_type) VALUES (?, '')");
            $stmtProfileInsert->execute([$userId]);
        }
        
        sendJsonResponse('success', 'Step 1 registration details recorded.', ['user_id' => $userId]);
        
    } elseif ($step === 2) {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
        $userType = trim($input['user_type'] ?? '');
        $jobTitle = trim($input['job_title'] ?? '');
        $experienceYears = (int)($input['experience_years'] ?? 0);
        $skills = trim($input['skills'] ?? '');
        $companyName = trim($input['company_name'] ?? '');
        
        if ($userId <= 0) {
            sendJsonResponse('error', 'Invalid User ID.', [], 400);
        }
        
        if (empty($userType)) {
            sendJsonResponse('error', 'User type is required.', [], 400);
        }
        
        // Update user profile
        $stmtProfileCheck = $db->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
        $stmtProfileCheck->execute([$userId]);
        
        if ($stmtProfileCheck->fetch()) {
            $stmt = $db->prepare("UPDATE user_profiles SET user_type = ?, job_title = ?, experience_years = ?, skills = ?, company_name = ? WHERE user_id = ?");
            $stmt->execute([$userType, $jobTitle, $experienceYears, $skills, $companyName, $userId]);
        } else {
            $stmt = $db->prepare("INSERT INTO user_profiles (user_id, user_type, job_title, experience_years, skills, company_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $userType, $jobTitle, $experienceYears, $skills, $companyName]);
        }
        
        sendJsonResponse('success', 'Step 2 professional profile recorded.');
        
    } elseif ($step === 3) {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
        $website = trim($input['website'] ?? '');
        $portfolioUrl = trim($input['portfolio_url'] ?? '');
        $linkedinUrl = trim($input['linkedin_url'] ?? '');
        $aboutMe = trim($input['about_me'] ?? '');
        
        if ($userId <= 0) {
            sendJsonResponse('error', 'Invalid User ID.', [], 400);
        }
        
        // Update user profile
        $stmt = $db->prepare("UPDATE user_profiles SET website = ?, portfolio_url = ?, linkedin_url = ?, about_me = ? WHERE user_id = ?");
        $stmt->execute([$website, $portfolioUrl, $linkedinUrl, $aboutMe, $userId]);
        
        sendJsonResponse('success', 'Step 3 profile bio and links recorded.');
    }

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to save registration step: ' . $e->getMessage(), [], 500);
}
