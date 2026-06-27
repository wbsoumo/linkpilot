<?php
// backend/api/extension/find_email.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Require Auth
$user = JWTHelper::requireAuth();
$userId = $user['id'];

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$linkedinUrl = trim($input['linkedin_url'] ?? '');
$name = trim($input['name'] ?? '');
$company = trim($input['company'] ?? '');
$jobTitle = trim($input['job_title'] ?? '');

if (empty($linkedinUrl) || empty($name)) {
    sendJsonResponse('error', 'LinkedIn URL and Name are required.', [], 400);
}

// Clean up LinkedIn URL (strip query parameters)
$linkedinUrl = explode('?', $linkedinUrl)[0];

$db = Database::getConnection();

// Helper to resolve company domain
function resolveCompanyDomain($companyName) {
    if (empty($companyName)) return '';
    
    // Clean name slightly (remove common suffixes)
    $cleanName = preg_replace('/\b(inc|corp|llc|ltd|co|gmbh|sa|pvt)\b.*?$/i', '', $companyName);
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $cleanName));

    $url = "https://autocomplete.clearbit.com/v1/companies/suggest?query=" . urlencode($cleanName);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot/1.0');
    $res = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return '';
    }
    
    curl_close($ch);
    
    if ($res) {
        $data = json_decode($res, true);
        if (!empty($data) && is_array($data) && isset($data[0]['domain'])) {
            return $data[0]['domain'];
        }
    }
    return '';
}

try {
    // 1. Check local cache
    $stmtCacheCheck = $db->prepare("SELECT * FROM email_cache WHERE linkedin_url = ? LIMIT 1");
    $stmtCacheCheck->execute([$linkedinUrl]);
    $cachedRow = $stmtCacheCheck->fetch();

    if ($cachedRow) {
        if ($cachedRow['status'] === 'found') {
            // Write search history entry with 0 credits
            $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmtHist->execute([$userId, $name, $company, $cachedRow['email'], $linkedinUrl, $cachedRow['provider']]);
            
            sendJsonResponse('success', 'Email found (from cache).', [
                'email' => $cachedRow['email'],
                'provider' => $cachedRow['provider'] . ' (cached)',
                'confidence_score' => $cachedRow['confidence_score']
            ]);
        } elseif ($cachedRow['status'] === 'not_found') {
            // Write search history entry with 0 credits
            $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, NULL, ?, 'none', 0)");
            $stmtHist->execute([$userId, $name, $company, $linkedinUrl]);
            
            sendJsonResponse('error', 'Email address not found (from cache).', [], 404);
        }
    }

    // 2. Not in cache: Perform lookup. First, verify credit balance.
    $stmtCredits = $db->prepare("SELECT remaining_credits FROM user_email_credits WHERE user_id = ?");
    $stmtCredits->execute([$userId]);
    $wallet = $stmtCredits->fetch();
    $creditsAvailable = $wallet ? (int)$wallet['remaining_credits'] : 0;

    if ($creditsAvailable < 1) {
        sendJsonResponse('error', 'Insufficient Email Finder credits. Please recharge your wallet.', [], 402);
    }

    // Fetch active email providers ordered by priority
    $stmtProviders = $db->query("SELECT * FROM email_provider_settings WHERE is_enabled = 1 ORDER BY priority ASC");
    $providers = $stmtProviders->fetchAll();

    if (empty($providers)) {
        sendJsonResponse('error', 'No email finder providers are currently enabled. Please contact support.', [], 503);
    }

    // Parse names
    $nameParts = explode(' ', trim($name));
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';

    // Resolve company domain
    $domain = resolveCompanyDomain($company);

    // Optimistically begin credit deduction transaction
    $db->beginTransaction();

    $stmtDeduct = $db->prepare("UPDATE user_email_credits SET remaining_credits = remaining_credits - 1, used_credits = used_credits + 1 WHERE user_id = ? AND remaining_credits >= 1");
    $stmtDeduct->execute([$userId]);

    if ($stmtDeduct->rowCount() === 0) {
        $db->rollBack();
        sendJsonResponse('error', 'Insufficient credits.', [], 402);
    }

    $emailFound = null;
    $successProvider = null;
    $confidenceScore = 0;
    $verificationStatus = 'unknown';

    foreach ($providers as $prov) {
        $decryptedKey = decryptData($prov['api_key']);
        $provName = strtolower($prov['provider_name']);
        
        $providerInstance = null;
        if ($provName === 'hunter') {
            require_once __DIR__ . '/../../providers/HunterProvider.php';
            $providerInstance = new HunterProvider($decryptedKey);
        } elseif ($provName === 'prospeo') {
            require_once __DIR__ . '/../../providers/ProspeoProvider.php';
            $providerInstance = new ProspeoProvider($decryptedKey);
        } elseif ($provName === 'apollo') {
            require_once __DIR__ . '/../../providers/ApolloProvider.php';
            $providerInstance = new ApolloProvider($decryptedKey);
        }
        
        if ($providerInstance) {
            try {
                $res = $providerInstance->findEmail($firstName, $lastName, $company, $domain, $jobTitle);
                if ($res && !empty($res['email'])) {
                    $emailFound = $res['email'];
                    $successProvider = $provName;
                    $confidenceScore = $res['score'];
                    $verificationStatus = $res['status'];
                    break;
                }
            } catch (Exception $ex) {
                error_log("Provider {$provName} failed: " . $ex->getMessage());
            }
        }
    }

    if ($emailFound) {
        // Successful lookup: commit deduction, cache the result, write logs
        $stmtCache = $db->prepare("INSERT INTO email_cache (name, company_name, domain, linkedin_url, email, confidence_score, provider, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'found') ON DUPLICATE KEY UPDATE email = VALUES(email), confidence_score = VALUES(confidence_score), provider = VALUES(provider), status = 'found'");
        $stmtCache->execute([$name, $company, $domain, $linkedinUrl, $emailFound, $confidenceScore, $successProvider]);

        $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmtHist->execute([$userId, $name, $company, $emailFound, $linkedinUrl, $successProvider]);

        $stmtTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, provider_used, status) VALUES (?, 'usage', 1, ?, 'success')");
        $stmtTx->execute([$userId, $successProvider]);

        $db->commit();

        sendJsonResponse('success', 'Email found successfully.', [
            'email' => $emailFound,
            'provider' => $successProvider,
            'confidence_score' => $confidenceScore
        ], 200);
    } else {
        // Unsuccessful lookup: roll back credit deduction, cache not_found status to prevent re-querying, write log
        $db->rollBack();

        $stmtCache = $db->prepare("INSERT INTO email_cache (name, company_name, domain, linkedin_url, email, status) VALUES (?, ?, ?, ?, NULL, 'not_found') ON DUPLICATE KEY UPDATE status = 'not_found'");
        $stmtCache->execute([$name, $company, $domain, $linkedinUrl]);

        $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, NULL, ?, 'none', 0)");
        $stmtHist->execute([$userId, $name, $company, $linkedinUrl]);

        sendJsonResponse('error', 'Email address not found.', [], 404);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse('error', 'Email Finder lookup failed: ' . $e->getMessage(), [], 500);
}
