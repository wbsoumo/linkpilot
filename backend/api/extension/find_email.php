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

function refundScrapeCredit($db, $userId) {
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE user_email_credits SET remaining_credits = remaining_credits + 1, used_credits = used_credits - 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stmtTx = $db->prepare("INSERT INTO email_credit_transactions (user_id, type, credits, provider_used, status) VALUES (?, 'refund', 1, 'linkedin_scraper', 'success')");
        $stmtTx->execute([$userId]);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
    }
}

function logScraperRequest($db, $userId, $url, $duration, $company, $hunterStatus, $credits, $errors) {
    try {
        $stmt = $db->prepare("INSERT INTO scraper_requests_log (user_id, linkedin_url, scraping_duration, company_found, hunter_lookup_status, credits_consumed, errors) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $url, $duration, $company, $hunterStatus, $credits, $errors]);
    } catch (Exception $e) {
        // Silently catch log database failures
    }
}

try {
    // 1. Check Scraper configuration
    $stmtScrap = $db->prepare("SELECT is_enabled, api_key, api_secret, priority FROM email_provider_settings WHERE provider_name = ? LIMIT 1");
    $stmtScrap->execute(['linkedin_scraper']);
    $scrapConf = $stmtScrap->fetch();
    
    $scraperEnabled = $scrapConf ? (int)$scrapConf['is_enabled'] : 0;
    $scraperUrl = $scrapConf ? $scrapConf['api_key'] : 'http://localhost:8000';
    $scraperTimeout = $scrapConf ? (int)$scrapConf['api_secret'] : 15;
    $scraperDebug = $scrapConf ? (int)$scrapConf['priority'] : 0;

    // 2. Check local cache (Valid for 24 hours)
    $stmtCacheCheck = $db->prepare("SELECT * FROM email_cache WHERE linkedin_url = ? LIMIT 1");
    $stmtCacheCheck->execute([$linkedinUrl]);
    $cachedRow = $stmtCacheCheck->fetch();

    if ($cachedRow) {
        $cacheAge = time() - strtotime($cachedRow['updated_at']);
        if ($cacheAge < 86400) {
            if ($cachedRow['status'] === 'found') {
                $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmtHist->execute([$userId, $name, $company, $cachedRow['email'], $linkedinUrl, $cachedRow['provider']]);
                
                sendJsonResponse('success', 'Email found (from cache).', [
                    'email' => $cachedRow['email'],
                    'provider' => $cachedRow['provider'] . ' (cached)',
                    'confidence_score' => $cachedRow['confidence_score']
                ]);
            } elseif ($cachedRow['status'] === 'not_found') {
                $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, NULL, ?, 'none', 0)");
                $stmtHist->execute([$userId, $name, $company, $linkedinUrl]);
                
                sendJsonResponse('error', 'Email address not found (from cache).', [], 404);
            }
        }
    }

    // 3. Credit checks and deductions are bypassed (Email Finder is free)
    $scrapDuration = 0;
    $scrapCreditsUsed = 0;
    $companyFound = $company;

    if ($scraperEnabled && !$cachedRow) {
        $scrapCreditsUsed = 0;

        // Perform HTTP Scrape POST Request to python service
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim($scraperUrl, '/') . '/scrape');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: linkpilot_local_scraper_secret_2026'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['linkedin_url' => $linkedinUrl]));
        curl_setopt($ch, CURLOPT_TIMEOUT, $scraperTimeout);

        $scrapStart = microtime(true);
        $res = curl_exec($ch);
        $scrapDuration = microtime(true) - $scrapStart;

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            logScraperRequest($db, $userId, $linkedinUrl, $scrapDuration, '', 'Scraping timeout/failed', 0, $err);
            sendJsonResponse('error', 'Scraping service unreachable: ' . $err, [], 504);
        }
        curl_close($ch);

        $scrapData = json_decode($res, true);
        if (!$scrapData || empty($scrapData['success'])) {
            $errMessage = $scrapData ? ($scrapData['message'] ?? 'Scraper error.') : 'Invalid response from scraper service.';
            logScraperRequest($db, $userId, $linkedinUrl, $scrapDuration, '', 'Scraping failed', 0, $errMessage);
            sendJsonResponse('error', 'LinkedIn Scraper failed: ' . $errMessage, [], 422);
        }

        // Successfully scraped! Update variables
        $name = $scrapData['name'];
        $company = $scrapData['company'];
        $jobTitle = $scrapData['designation'];
        $companyFound = $company;
    }

    // Now proceed to Domain Resolution
    $domain = isset($input['domain']) ? trim($input['domain']) : '';
    if (empty($domain)) {
        $domain = resolveCompanyDomain($company);
    }

    // Resolve Name parts
    $nameParts = explode(' ', trim($name));
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';

    // Fetch active email providers ordered by priority
    $stmtProviders = $db->query("SELECT * FROM email_provider_settings WHERE is_enabled = 1 AND provider_name != 'linkedin_scraper' ORDER BY priority ASC");
    $providers = $stmtProviders->fetchAll();

    if (empty($providers)) {
        sendJsonResponse('error', 'No email finder providers are currently enabled. Please contact support.', [], 503);
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
                    $confidenceScore = isset($res['score']) ? $res['score'] : 0;
                    $verificationStatus = isset($res['status']) ? $res['status'] : 'unknown';
                    break;
                }
            } catch (Exception $ex) {
                error_log("Provider {$provName} failed: " . $ex->getMessage());
            }
        }
    }

    if ($emailFound) {
        // Successful lookup
        $stmtCache = $db->prepare("INSERT INTO email_cache (name, company_name, domain, linkedin_url, email, confidence_score, provider, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'found') ON DUPLICATE KEY UPDATE name = VALUES(name), company_name = VALUES(company_name), domain = VALUES(domain), email = VALUES(email), confidence_score = VALUES(confidence_score), provider = VALUES(provider), status = 'found'");
        $stmtCache->execute([$name, $company, $domain, $linkedinUrl, $emailFound, $confidenceScore, $successProvider]);

        $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmtHist->execute([$userId, $name, $company, $emailFound, $linkedinUrl, $successProvider]);

        logScraperRequest($db, $userId, $linkedinUrl, $scrapDuration, $companyFound, 'success', 0, '');

        sendJsonResponse('success', 'Email found successfully.', [
            'email' => $emailFound,
            'provider' => $successProvider,
            'confidence_score' => $confidenceScore
        ], 200);
    } else {
        // Unsuccessful lookup
        $stmtCache = $db->prepare("INSERT INTO email_cache (name, company_name, domain, linkedin_url, email, status) VALUES (?, ?, ?, ?, NULL, 'not_found') ON DUPLICATE KEY UPDATE name = VALUES(name), company_name = VALUES(company_name), domain = VALUES(domain), status = 'not_found'");
        $stmtCache->execute([$name, $company, $domain, $linkedinUrl]);

        $stmtHist = $db->prepare("INSERT INTO email_search_history (user_id, name, company, email, linkedin_url, provider, credits_used) VALUES (?, ?, ?, NULL, ?, 'none', 0)");
        $stmtHist->execute([$userId, $name, $company, $linkedinUrl]);

        logScraperRequest($db, $userId, $linkedinUrl, $scrapDuration, $companyFound, 'not_found', 0, '');

        sendJsonResponse('error', 'Email address not found.', [], 404);
    }

} catch (Exception $e) {
    logScraperRequest($db, $userId, $linkedinUrl, 0, '', 'error', 0, $e->getMessage());
    sendJsonResponse('error', 'Email Finder lookup failed: ' . $e->getMessage(), [], 500);
}
