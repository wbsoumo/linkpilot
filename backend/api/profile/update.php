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

$name = trim($input['name'] ?? '');
$userType = trim($input['user_type'] ?? '');
$jobTitle = trim($input['job_title'] ?? '');
$experienceYears = (int)($input['experience_years'] ?? 0);
$skills = trim($input['skills'] ?? '');
$companyName = trim($input['company_name'] ?? '');
$website = trim($input['website'] ?? '');
$portfolioUrl = trim($input['portfolio_url'] ?? '');
$linkedinUrl = trim($input['linkedin_url'] ?? '');
$aboutMe = trim($input['about_me'] ?? '');

// Validation
if (empty($name) || empty($userType)) {
    sendJsonResponse('error', 'Name and user type are required.', [], 400);
}

$db = Database::getConnection();

try {
    // Begin transaction
    $db->beginTransaction();
    
    // 1. Update Name in users
    $stmtUser = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
    $stmtUser->execute([$name, $userId]);
    
    // 2. Update Profile fields
    $stmtProfile = $db->prepare("
        INSERT INTO user_profiles (user_id, user_type, job_title, experience_years, skills, company_name, website, portfolio_url, linkedin_url, about_me) 
        VALUES (:user_id, :user_type, :job_title, :experience_years, :skills, :company_name, :website, :portfolio_url, :linkedin_url, :about_me)
        ON DUPLICATE KEY UPDATE 
            user_type = VALUES(user_type),
            job_title = VALUES(job_title),
            experience_years = VALUES(experience_years),
            skills = VALUES(skills),
            company_name = VALUES(company_name),
            website = VALUES(website),
            portfolio_url = VALUES(portfolio_url),
            linkedin_url = VALUES(linkedin_url),
            about_me = VALUES(about_me)
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
        'about_me' => $aboutMe
    ]);
    
    // Commit transaction
    $db->commit();
    
    logActivity($userId, "Updated profile information.");
    
    sendJsonResponse('success', 'Profile updated successfully.');
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Server error updating profile: ' . $e->getMessage(), [], 500);
}
