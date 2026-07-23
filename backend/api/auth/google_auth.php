<?php
// backend/api/auth/google_auth.php

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

$action = trim($input['action'] ?? '');
$idToken = trim($input['id_token'] ?? '');

if (empty($action) || empty($idToken)) {
    sendJsonResponse('error', 'Action and Google ID token are required.', [], 400);
}

if (!in_array($action, ['login', 'register'])) {
    sendJsonResponse('error', 'Invalid action value.', [], 400);
}

// Verify token using Google's tokeninfo API
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenInfo = json_decode($response, true);
if ($httpCode !== 200 || !isset($tokenInfo['email'])) {
    sendJsonResponse('error', 'Google token verification failed: ' . ($tokenInfo['error_description'] ?? 'invalid token'), [], 400);
}

$email = strtolower(trim($tokenInfo['email']));
$name = trim($tokenInfo['name'] ?? '');

$db = Database::getConnection();

try {
    if ($action === 'login') {
        // Check if user is verified and registered
        $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE email = ? AND is_verified = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Log in the user
            $token = JWTHelper::generateToken([
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role']
            ]);

            logActivity($user['id'], "User logged in via Google OAuth.");

            sendJsonResponse('success', 'User logged in successfully.', [
                'action' => 'login',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ], 200);
        } else {
            // User does not exist, return email & name to proceed to registration steps
            sendJsonResponse('success', 'User not registered yet. Please proceed to registration details.', [
                'action' => 'register',
                'email' => $email,
                'name' => $name
            ], 200);
        }

    } elseif ($action === 'register') {
        // Extra registration details
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

        // Validation (User type is required)
        if (empty($userType)) {
            sendJsonResponse('error', 'User type is required.', [], 400);
        }

        // Check if email already registered and verified
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 1 LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendJsonResponse('error', 'An account with this email address already exists.', [], 409);
        }

        // Generate a random password since they login using Google
        $randomPass = bin2hex(random_bytes(16));
        $passwordHash = password_hash($randomPass, PASSWORD_DEFAULT);

        // Determine user role (first verified user is admin, else user)
        $stmtCount = $db->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 1");
        $rowCount = $stmtCount->fetch();
        $role = ($rowCount['total'] == 0) ? 'admin' : 'user';

        // Begin Transaction
        $db->beginTransaction();

        // Check if there is an unverified user record to update, otherwise insert
        $stmtUserLookup = $db->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 0 LIMIT 1");
        $stmtUserLookup->execute([$email]);
        $existingUser = $stmtUserLookup->fetch();

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
                $userType, $jobTitle, $experienceYears, $skills, $companyName, $website, $portfolioUrl, $linkedinUrl, $aboutMe, $companySize, $industry, $location, $userId
            ]);
        } else {
            $stmtProfile = $db->prepare("INSERT INTO user_profiles (user_id, user_type, job_title, experience_years, skills, company_name, website, portfolio_url, linkedin_url, about_me, company_size, industry, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtProfile->execute([
                $userId, $userType, $jobTitle, $experienceYears, $skills, $companyName, $website, $portfolioUrl, $linkedinUrl, $aboutMe, $companySize, $industry, $location
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

        logActivity($userId, "User registered successfully via Google OAuth.");

        // Generate JWT Token
        $token = JWTHelper::generateToken([
            'id' => $userId,
            'email' => $email,
            'name' => $name,
            'role' => $role
        ]);

        sendJsonResponse('success', 'User registered successfully via Google OAuth.', [
            'action' => 'login',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => $role
            ]
        ], 201);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Server error during Google Authentication: ' . $e->getMessage(), [], 500);
}
