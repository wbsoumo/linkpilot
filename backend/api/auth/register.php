<?php
// backend/api/auth/register.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$name = trim($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';
$userType = trim($input['user_type'] ?? '');
$jobTitle = trim($input['job_title'] ?? '');
$experienceYears = (int)($input['experience_years'] ?? 0);
$skills = trim($input['skills'] ?? '');
$companyName = trim($input['company_name'] ?? '');
$website = trim($input['website'] ?? '');
$portfolioUrl = trim($input['portfolio_url'] ?? '');
$linkedinUrl = trim($input['linkedin_url'] ?? '');
$aboutMe = trim($input['about_me'] ?? '');
$companySize = trim($input['company_size'] ?? '');
$industry = trim($input['industry'] ?? '');
$location = trim($input['location'] ?? '');

// Validation
if (empty($name) || empty($email) || empty($password) || empty($userType)) {
    sendJsonResponse('error', 'Name, email, password, and user type are required.', [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid email address.', [], 400);
}

if ($password !== $confirmPassword) {
    sendJsonResponse('error', 'Passwords do not match.', [], 400);
}

if (strlen($password) < 6) {
    sendJsonResponse('error', 'Password must be at least 6 characters long.', [], 400);
}

$db = Database::getConnection();

try {
    // Check if email already exists and is verified
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse('error', 'An account with this email address already exists.', [], 409);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Lookup existing unverified user from previous steps
    $stmtUserLookup = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 0 LIMIT 1");
    $stmtUserLookup->execute([$email]);
    $existingUser = $stmtUserLookup->fetch();
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Determine user role (first verified user is admin, else user)
    $stmtCount = $db->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 1");
    $rowCount = $stmtCount->fetch();
    $role = ($rowCount['total'] == 0) ? 'admin' : 'user';
    
    if ($existingUser) {
        $userId = $existingUser['id'];
        $stmtUpdateUser = $db->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, is_verified = 1 WHERE id = ?");
        $stmtUpdateUser->execute([$name, $email, $passwordHash, $role, $userId]);
    } else {
        $stmtInsertUser = $db->prepare("INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, ?, 1)");
        $stmtInsertUser->execute([$name, $email, $passwordHash, $role]);
        $userId = $db->lastInsertId();
    }
    
    // Insert/Update User Profile
    $stmtProfileCheck = $db->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $stmtProfileCheck->execute([$userId]);
    if ($stmtProfileCheck->fetch()) {
        $stmtProfile = $db->prepare("UPDATE user_profiles SET user_type = ?, job_title = ?, experience_years = ?, skills = ?, company_name = ?, website = ?, portfolio_url = ?, linkedin_url = ?, about_me = ?, company_size = ?, industry = ?, location = ? WHERE user_id = ?");
        $stmtProfile->execute([
            $userType,
            $jobTitle,
            $experienceYears,
            $skills,
            $companyName,
            $website,
            $portfolioUrl,
            $linkedinUrl,
            $aboutMe,
            $companySize,
            $industry,
            $location,
            $userId
        ]);
    } else {
        $stmtProfile = $db->prepare("INSERT INTO user_profiles (user_id, user_type, job_title, experience_years, skills, company_name, website, portfolio_url, linkedin_url, about_me, company_size, industry, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtProfile->execute([
            $userId,
            $userType,
            $jobTitle,
            $experienceYears,
            $skills,
            $companyName,
            $website,
            $portfolioUrl,
            $linkedinUrl,
            $aboutMe,
            $companySize,
            $industry,
            $location
        ]);
    }
    
    // Insert Default Stats
    $stmtStatsCheck = $db->prepare("SELECT id FROM user_statistics WHERE user_id = ?");
    $stmtStatsCheck->execute([$userId]);
    if (!$stmtStatsCheck->fetch()) {
        $stmtStats = $db->prepare("INSERT INTO user_statistics (user_id) VALUES (?)");
        $stmtStats->execute([$userId]);
    }
    
    // Commit transaction
    $db->commit();
    
    // Log Activity
    logActivity($userId, "User registered successfully.");
    
    // Generate JWT Token
    $token = JWTHelper::generateToken([
        'id' => $userId,
        'email' => $email,
        'name' => $name,
        'role' => $role
    ]);
    
    sendJsonResponse('success', 'User registered successfully.', [
        'token' => $token,
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role
        ]
    ], 201);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Server error during registration: ' . $e->getMessage(), [], 500);
}
