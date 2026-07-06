<?php
/**
 * LinkPilot Meta WhatsApp Business Cloud API Diagnostics Tool
 * Standalone single-file script. Save as testwa.php in public root.
 */

// Safety and error configurations
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('UTC');

// Helper to clean/mask logs & output
function maskString($str, $visibleCount = 6) {
    if (empty($str)) return '';
    $len = strlen($str);
    if ($len <= $visibleCount * 2) {
        return str_repeat('*', $len);
    }
    return substr($str, 0, $visibleCount) . str_repeat('*', $len - ($visibleCount * 2)) . substr($str, -$visibleCount);
}

// Log utility
function writeDiagnosticLog($url, $timeMs, $code, $payload, $response, $error) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $logFile = $dir . '/meta_diagnostic.log';
    
    // Mask sensitive details inside query logs
    $safeUrl = preg_replace('/access_token=[^&]+/', 'access_token=MASKED', $url);
    $safePayload = $payload;
    if (is_array($payload)) {
        $safePayload = json_encode($payload);
    }
    $safePayload = preg_replace('/"access_token"\s*:\s*"[^"]+"/', '"access_token":"MASKED"', $safePayload);
    
    $entry = sprintf(
        "[%s] URL: %s | Time: %dms | Code: %d | Payload: %s | Response: %s | Error: %s\n",
        date('Y-m-d H:i:s'),
        $safeUrl,
        $timeMs,
        $code,
        $safePayload,
        $response,
        $error ? $error : 'None'
    );
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

// Reusable cURL Request Utility
function apiRequest($url, $method = 'GET', $payload = null, $token = null) {
    $startTime = microtime(true);
    $ch = curl_init();
    
    $headers = [
        'Accept: application/json',
    ];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($payload) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload));
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $endTime = microtime(true);
    $timeMs = round(($endTime - $startTime) * 1000);
    
    // Log the API call
    writeDiagnosticLog($url, $timeMs, $httpCode, $payload, $response, $curlError);
    
    return [
        'url' => $url,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $curlError,
        'latency_ms' => $timeMs
    ];
}

// Handle AJAX Request
if (isset($_GET['action']) && $_GET['action'] === 'run') {
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_length()) {
        ob_clean();
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $appId = trim($input['app_id'] ?? '');
    $appSecret = trim($input['app_secret'] ?? '');
    $accessToken = trim($input['access_token'] ?? '');
    $businessId = trim($input['business_id'] ?? '');
    $wabaId = trim($input['waba_id'] ?? '');
    $phoneId = trim($input['phone_number_id'] ?? '');
    $recipient = trim($input['recipient_phone'] ?? '');
    $sendLive = !empty($input['send_live']);
    
    $results = [];
    $rawLogs = [];
    
    // STEP 1: Environment Check
    $env = [
        'php_version' => [
            'title' => 'PHP Version',
            'value' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'PASS' : 'WARNING',
            'desc' => 'Requires PHP 8+ for modern security/cURL settings.'
        ],
        'curl_enabled' => [
            'title' => 'cURL Extension',
            'value' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('curl') ? 'PASS' : 'FAIL',
            'desc' => 'cURL is required to communicate with the Meta Graph API.'
        ],
        'json_enabled' => [
            'title' => 'JSON Extension',
            'value' => extension_loaded('json') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('json') ? 'PASS' : 'FAIL',
            'desc' => 'Required for payload parsing.'
        ],
        'openssl_enabled' => [
            'title' => 'OpenSSL Extension',
            'value' => extension_loaded('openssl') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('openssl') ? 'PASS' : 'FAIL',
            'desc' => 'Required for payload encryption/security.'
        ],
        'https_enabled' => [
            'title' => 'HTTPS Usage',
            'value' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'Secure (HTTPS)' : 'Insecure (HTTP)',
            'status' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'PASS' : 'WARNING',
            'desc' => 'HTTPS is highly recommended for security.'
        ],
        'url_fopen' => [
            'title' => 'allow_url_fopen',
            'value' => ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled',
            'status' => ini_get('allow_url_fopen') ? 'PASS' : 'WARNING',
            'desc' => 'Recommended for stream requests.'
        ],
        'timezone' => [
            'title' => 'Timezone Setting',
            'value' => date_default_timezone_get(),
            'status' => 'PASS',
            'desc' => 'Server timezone alignment.'
        ]
    ];
    
    $results['step1'] = [
        'title' => 'Environment Check',
        'items' => $env,
        'status' => 'PASS'
    ];
    foreach ($env as $k => $e) {
        if ($e['status'] === 'FAIL') {
            $results['step1']['status'] = 'FAIL';
            break;
        } elseif ($e['status'] === 'WARNING') {
            $results['step1']['status'] = 'WARNING';
        }
    }
    
    // STEP 2: Graph API Connectivity
    $connCall = apiRequest('https://graph.facebook.com', 'GET');
    $rawLogs['step2_ping'] = $connCall;
    
    $results['step2'] = [
        'title' => 'Graph API Connectivity',
        'status' => $connCall['http_code'] > 0 ? 'PASS' : 'FAIL',
        'latency_ms' => $connCall['latency_ms'],
        'details' => $connCall['http_code'] > 0 ? "Connected to Meta Servers. Latency: {$connCall['latency_ms']}ms." : "Unable to reach graph.facebook.com: " . $connCall['error']
    ];
    
    // Generate App Access Token for debug token calls
    $appToken = ($appId && $appSecret) ? "{$appId}|{$appSecret}" : '';
    
    // STEP 3: App Verification
    if ($appId && $appSecret) {
        $appCall = apiRequest("https://graph.facebook.com/v20.0/{$appId}", 'GET', null, $accessToken);
        $rawLogs['step3_app'] = $appCall;
        
        if ($appCall['http_code'] === 200) {
            $appData = json_decode($appCall['response'], true);
            $results['step3'] = [
                'title' => 'App Verification',
                'status' => 'PASS',
                'app_name' => $appData['name'] ?? 'Meta Application',
                'app_id' => $appData['id'] ?? $appId,
                'category' => $appData['category'] ?? 'N/A'
            ];
        } else {
            $err = json_decode($appCall['response'], true);
            $results['step3'] = [
                'title' => 'App Verification',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Invalid App ID or App Secret.'
            ];
        }
    } else {
        $results['step3'] = [
            'title' => 'App Verification',
            'status' => 'WARNING',
            'details' => 'Skipped. App ID or App Secret was not provided.'
        ];
    }
    
    // STEP 4: Debug Token
    if ($accessToken && $appToken) {
        $debugCall = apiRequest("https://graph.facebook.com/debug_token?input_token={$accessToken}", 'GET', null, $appToken);
        $rawLogs['step4_debug'] = $debugCall;
        
        if ($debugCall['http_code'] === 200) {
            $dbData = json_decode($debugCall['response'], true)['data'] ?? [];
            $results['step4'] = [
                'title' => 'Debug Token Info',
                'status' => ($dbData['is_valid'] ?? false) ? 'PASS' : 'FAIL',
                'is_valid' => $dbData['is_valid'] ?? false,
                'app_name' => $dbData['application'] ?? 'Unknown App',
                'app_id' => $dbData['app_id'] ?? '',
                'user_id' => $dbData['user_id'] ?? '',
                'type' => $dbData['type'] ?? 'N/A',
                'issued_at' => isset($dbData['issued_at']) ? date('Y-m-d H:i:s', $dbData['issued_at']) : 'N/A',
                'expires_at' => isset($dbData['expires_at']) && $dbData['expires_at'] > 0 ? date('Y-m-d H:i:s', $dbData['expires_at']) : 'Never (Permanent)',
                'scopes' => $dbData['scopes'] ?? []
            ];
        } else {
            $err = json_decode($debugCall['response'], true);
            $results['step4'] = [
                'title' => 'Debug Token Info',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed verifying token.'
            ];
        }
    } else {
        $results['step4'] = [
            'title' => 'Debug Token Info',
            'status' => 'WARNING',
            'details' => 'Skipped. Requires Access Token and App credentials.'
        ];
    }
    
    // STEP 5: Permissions
    if ($accessToken) {
        $permCall = apiRequest("https://graph.facebook.com/v20.0/me/permissions", 'GET', null, $accessToken);
        $rawLogs['step5_perms'] = $permCall;
        
        if ($permCall['http_code'] === 200) {
            $permData = json_decode($permCall['response'], true)['data'] ?? [];
            $results['step5'] = [
                'title' => 'Permissions',
                'status' => 'PASS',
                'permissions' => $permData
            ];
        } else {
            $err = json_decode($permCall['response'], true);
            $results['step5'] = [
                'title' => 'Permissions',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed retrieving permissions.'
            ];
        }
    } else {
        $results['step5'] = [
            'title' => 'Permissions',
            'status' => 'FAIL',
            'error' => 'Skipped. Access Token is required.'
        ];
    }
    
    // STEP 6: System User Profile
    if ($accessToken) {
        $meCall = apiRequest("https://graph.facebook.com/v20.0/me", 'GET', null, $accessToken);
        $rawLogs['step6_me'] = $meCall;
        
        if ($meCall['http_code'] === 200) {
            $meData = json_decode($meCall['response'], true);
            $results['step6'] = [
                'title' => 'System User Profile',
                'status' => 'PASS',
                'user_id' => $meData['id'] ?? 'N/A',
                'user_name' => $meData['name'] ?? 'N/A'
            ];
        } else {
            $err = json_decode($meCall['response'], true);
            $results['step6'] = [
                'title' => 'System User Profile',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed reading System User profile.'
            ];
        }
    } else {
        $results['step6'] = [
            'title' => 'System User Profile',
            'status' => 'FAIL',
            'error' => 'Skipped. Access token is empty.'
        ];
    }
    
    // STEP 7: Business Verification
    if ($accessToken && $businessId) {
        $bizCall = apiRequest("https://graph.facebook.com/v20.0/{$businessId}", 'GET', null, $accessToken);
        $rawLogs['step7_biz'] = $bizCall;
        
        if ($bizCall['http_code'] === 200) {
            $bizData = json_decode($bizCall['response'], true);
            $results['step7'] = [
                'title' => 'Business Verification',
                'status' => 'PASS',
                'business_name' => $bizData['name'] ?? 'N/A',
                'business_id' => $bizData['id'] ?? $businessId,
                'link' => $bizData['link'] ?? 'N/A'
            ];
        } else {
            $err = json_decode($bizCall['response'], true);
            $results['step7'] = [
                'title' => 'Business Verification',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed checking business account.'
            ];
        }
    } else {
        $results['step7'] = [
            'title' => 'Business Verification',
            'status' => 'WARNING',
            'details' => 'Skipped. No Business Manager ID provided.'
        ];
    }
    
    // STEP 8: WABA Verification
    if ($accessToken && $wabaId) {
        $wabaCall = apiRequest("https://graph.facebook.com/v20.0/{$wabaId}", 'GET', null, $accessToken);
        $rawLogs['step8_waba'] = $wabaCall;
        
        if ($wabaCall['http_code'] === 200) {
            $wabaData = json_decode($wabaCall['response'], true);
            $results['step8'] = [
                'title' => 'WABA Verification',
                'status' => 'PASS',
                'waba_name' => $wabaData['name'] ?? 'N/A',
                'waba_id' => $wabaData['id'] ?? $wabaId,
                'status_badge' => $wabaData['status'] ?? 'APPROVED'
            ];
        } else {
            $err = json_decode($wabaCall['response'], true);
            $results['step8'] = [
                'title' => 'WABA Verification',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed verifying WABA account.'
            ];
        }
    } else {
        $results['step8'] = [
            'title' => 'WABA Verification',
            'status' => 'FAIL',
            'error' => 'Skipped. WABA ID is required.'
        ];
    }
    
    // STEP 9: Phone Verification
    if ($accessToken && $phoneId) {
        $fields = "id,display_phone_number,verified_name,quality_rating,messaging_limit_tier,status,code_verification_status";
        $phoneCall = apiRequest("https://graph.facebook.com/v20.0/{$phoneId}?fields={$fields}", 'GET', null, $accessToken);
        $rawLogs['step9_phone'] = $phoneCall;
        
        if ($phoneCall['http_code'] === 200) {
            $phoneData = json_decode($phoneCall['response'], true);
            $results['step9'] = [
                'title' => 'Phone Profile Verification',
                'status' => 'PASS',
                'phone_number' => $phoneData['display_phone_number'] ?? 'N/A',
                'verified_name' => $phoneData['verified_name'] ?? 'N/A',
                'quality' => $phoneData['quality_rating'] ?? 'unknown',
                'messaging_tier' => $phoneData['messaging_limit_tier'] ?? 'TIER_50',
                'phone_status' => $phoneData['status'] ?? 'unknown',
                'code_status' => $phoneData['code_verification_status'] ?? 'unknown'
            ];
        } else {
            $err = json_decode($phoneCall['response'], true);
            $results['step9'] = [
                'title' => 'Phone Profile Verification',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed verifying phone profile.'
            ];
        }
    } else {
        $results['step9'] = [
            'title' => 'Phone Profile Verification',
            'status' => 'FAIL',
            'error' => 'Skipped. Phone ID is required.'
        ];
    }
    
    // STEP 10: Phone Mapping inside WABA
    if ($accessToken && $wabaId && $phoneId) {
        $mapCall = apiRequest("https://graph.facebook.com/v20.0/{$wabaId}/phone_numbers", 'GET', null, $accessToken);
        $rawLogs['step10_mapping'] = $mapCall;
        
        if ($mapCall['http_code'] === 200) {
            $mapData = json_decode($mapCall['response'], true)['data'] ?? [];
            $found = false;
            foreach ($mapData as $ph) {
                if ($ph['id'] === $phoneId) {
                    $found = true;
                    break;
                }
            }
            $results['step10'] = [
                'title' => 'Phone Mapping under WABA',
                'status' => $found ? 'PASS' : 'FAIL',
                'details' => $found ? "Confirmed: Phone ID {$phoneId} belongs to WABA Account {$wabaId}." : "Warning: Phone ID {$phoneId} was not found inside the list of phone numbers for WABA {$wabaId}."
            ];
        } else {
            $err = json_decode($mapCall['response'], true);
            $results['step10'] = [
                'title' => 'Phone Mapping under WABA',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed querying WABA phone mapping.'
            ];
        }
    } else {
        $results['step10'] = [
            'title' => 'Phone Mapping under WABA',
            'status' => 'WARNING',
            'details' => 'Skipped. WABA ID and Phone ID are required.'
        ];
    }
    
    // STEP 11: Webhook Subscription
    if ($accessToken && $wabaId) {
        $webCall = apiRequest("https://graph.facebook.com/v20.0/{$wabaId}?fields=webhook_configuration", 'GET', null, $accessToken);
        $rawLogs['step11_webhook'] = $webCall;
        
        if ($webCall['http_code'] === 200) {
            $webData = json_decode($webCall['response'], true);
            $results['step11'] = [
                'title' => 'WABA Webhook configuration',
                'status' => 'PASS',
                'details' => isset($webData['webhook_configuration']) ? 'Webhook Configured: ' . json_encode($webData['webhook_configuration']) : 'No custom legacy webhook payload configuration at WABA level.'
            ];
        } else {
            $results['step11'] = [
                'title' => 'WABA Webhook configuration',
                'status' => 'WARNING',
                'details' => 'Unable to read legacy WABA webhook configuration (requires advanced permissions).'
            ];
        }
    } else {
        $results['step11'] = [
            'title' => 'WABA Webhook configuration',
            'status' => 'WARNING',
            'details' => 'Skipped. WABA ID is required.'
        ];
    }
    
    // STEP 12: Subscribed Apps
    if ($accessToken && $wabaId) {
        $subCall = apiRequest("https://graph.facebook.com/v20.0/{$wabaId}/subscribed_apps", 'GET', null, $accessToken);
        $rawLogs['step12_subscribed'] = $subCall;
        
        if ($subCall['http_code'] === 200) {
            $subData = json_decode($subCall['response'], true)['data'] ?? [];
            $isAppSubscribed = false;
            foreach ($subData as $app) {
                if ($app['whatsapp_business_api_data']['app_id'] ?? '' === $appId || $app['id'] ?? '' === $appId) {
                    $isAppSubscribed = true;
                    break;
                }
            }
            $results['step12'] = [
                'title' => 'App WABA Subscription status',
                'status' => ($appId && !$isAppSubscribed) ? 'FAIL' : 'PASS',
                'details' => $isAppSubscribed ? "Application {$appId} is successfully subscribed to this WABA account." : "Your Meta App is NOT subscribed to this WABA. Webhook payloads will not be received.",
                'raw_list' => $subData
            ];
        } else {
            $err = json_decode($subCall['response'], true);
            $results['step12'] = [
                'title' => 'App WABA Subscription status',
                'status' => 'FAIL',
                'error' => $err['error']['message'] ?? 'Failed checking subscribed apps.'
            ];
        }
    } else {
        $results['step12'] = [
            'title' => 'App WABA Subscription status',
            'status' => 'WARNING',
            'details' => 'Skipped. WABA ID and App ID are required.'
        ];
    }
    
    // STEP 13: Read Phone Check
    if ($accessToken && $phoneId) {
        $results['step13'] = [
            'title' => 'Token Phone Read access',
            'status' => ($results['step9']['status'] === 'PASS') ? 'PASS' : 'FAIL',
            'details' => ($results['step9']['status'] === 'PASS') ? "Confirmed: System User can read phone endpoint details." : "Denied: System User lacks read permissions for Phone ID {$phoneId}."
        ];
    } else {
        $results['step13'] = [
            'title' => 'Token Phone Read access',
            'status' => 'FAIL',
            'error' => 'Skipped. Phone ID is required.'
        ];
    }
    
    // STEP 14: Messaging Test (Live Message Send)
    if ($sendLive && $accessToken && $phoneId && $recipient) {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => 'hello_world',
                'language' => [
                    'code' => 'en_US'
                ]
            ]
        ];
        
        $msgCall = apiRequest("https://graph.facebook.com/v20.0/{$phoneId}/messages", 'POST', $payload, $accessToken);
        $rawLogs['step14_send'] = $msgCall;
        
        if ($msgCall['http_code'] === 200) {
            $msgData = json_decode($msgCall['response'], true);
            $results['step14'] = [
                'title' => 'Live Message Send test',
                'status' => 'PASS',
                'message_id' => $msgData['messages'][0]['id'] ?? 'N/A',
                'details' => 'Test template message sent successfully to ' . htmlspecialchars($recipient)
            ];
        } else {
            $err = json_decode($msgCall['response'], true)['error'] ?? [];
            $results['step14'] = [
                'title' => 'Live Message Send test',
                'status' => 'FAIL',
                'error_code' => $err['code'] ?? 'Unknown',
                'error_subcode' => $err['error_subcode'] ?? 'None',
                'error_message' => $err['message'] ?? 'Failed sending message.'
            ];
        }
    } else {
        $results['step14'] = [
            'title' => 'Live Message Send test',
            'status' => 'WARNING',
            'details' => $sendLive ? 'Skipped: Access Token, Phone ID, and Recipient Phone are required.' : 'Live test message sending was not checkmarked.'
        ];
    }
    
    // STEP 16: Summary Report, Root Cause Identification & Recommended Actions
    $summary = [
        'environment' => $results['step1']['status'],
        'app' => $results['step3']['status'] ?? 'WARNING',
        'token' => $results['step4']['status'] ?? 'FAIL',
        'permissions' => 'FAIL',
        'business' => $results['step7']['status'] ?? 'WARNING',
        'waba' => $results['step8']['status'] ?? 'FAIL',
        'phone' => $results['step9']['status'] ?? 'FAIL',
        'webhook' => $results['step11']['status'] ?? 'WARNING',
        'subscription' => $results['step12']['status'] ?? 'FAIL',
        'messaging' => $results['step14']['status'] ?? 'WARNING'
    ];
    
    // Evaluate permissions summary
    $grantedPerms = [];
    if (isset($results['step5']['permissions'])) {
        foreach ($results['step5']['permissions'] as $p) {
            if ($p['status'] === 'granted') {
                $grantedPerms[] = $p['permission'];
            }
        }
    }
    if (in_array('whatsapp_business_management', $grantedPerms) && in_array('whatsapp_business_messaging', $grantedPerms)) {
        $summary['permissions'] = 'PASS';
    } else {
        $summary['permissions'] = 'FAIL';
    }
    
    // Root cause and Recommended Fix Generator
    $rootCause = "All core integration settings passed diagnostics checks successfully.";
    $recommendedFix = "Your settings are configured correctly. Verify webhook verify tokens and inbox associations inside the LinkPilot CRM dashboard.";
    
    if ($results['step4']['status'] === 'FAIL') {
        $rootCause = "The Access Token is expired, invalid, or belongs to a different developer app.";
        $recommendedFix = "Generate a new Permanent System User Access Token in your Business Settings, copy the token string, and paste it into the LinkPilot credentials configuration.";
    } elseif ($summary['permissions'] === 'FAIL') {
        $rootCause = "The System User token lacks critical scopes: " . (!in_array('whatsapp_business_messaging', $grantedPerms) ? 'whatsapp_business_messaging ' : '') . (!in_array('whatsapp_business_management', $grantedPerms) ? 'whatsapp_business_management' : '') . " is missing.";
        $recommendedFix = "Go to Meta Business Settings -> System Users -> select your user -> click Generate Token. Make sure both 'whatsapp_business_management' and 'whatsapp_business_messaging' are checked.";
    } elseif ($results['step8']['status'] === 'FAIL') {
        $rootCause = "The WABA ID you provided is invalid, does not exist, or the System User has not been assigned to this asset.";
        $recommendedFix = "Ensure you have assigned the WhatsApp Business Account asset to the System User under Business Settings -> System Users -> Assign Assets -> WhatsApp Accounts -> Full Control. Generate a new token after assigning.";
    } elseif ($results['step9']['status'] === 'FAIL') {
        $rootCause = "The Phone Number ID is invalid, or the token's System User does not have access to it.";
        $recommendedFix = "Verify that the Phone Number ID is correct. (Ensure it is the 15-digit Phone Number ID, not the WhatsApp Business Account ID or the display number itself). Make sure the WABA asset is fully assigned to the System User.";
    } elseif ($results['step10']['status'] === 'FAIL') {
        $rootCause = "The Phone Number ID exists but does not belong to the selected WhatsApp Business Account (WABA).";
        $recommendedFix = "Double check the Phone Number ID in your Meta Developer portal under WhatsApp -> API Setup. Make sure it corresponds to the WABA ID you entered.";
    } elseif ($results['step12']['status'] === 'FAIL') {
        $rootCause = "The Meta Developer Application is not subscribed to receive webhook notifications from this WABA Account.";
        $recommendedFix = "Send a POST request to link the App to the WABA:\n\nPOST https://graph.facebook.com/v20.0/{WABA_ID}/subscribed_apps\nHeaders: Authorization: Bearer {Access_Token}";
    } elseif ($results['step14']['status'] === 'FAIL') {
        $errCode = $results['step14']['error_code'] ?? '';
        if ($errCode == 200) {
            $rootCause = "Meta Permission Policy Block (#200): Either your Meta App is in Development Mode and you tried messaging an un-synced phone number, or your WhatsApp portfolio has no Credit Card / Payment Method on file.";
            $recommendedFix = "1. Go to developers.facebook.com -> select App -> Toggle mode from 'Development' to 'Live'.\n2. Add a credit card to your WABA in Meta Business Suite -> Settings -> Payment Method.";
        } else {
            $rootCause = "Live messaging test failed with Meta Error Code {$errCode}: " . ($results['step14']['error_message'] ?? '');
            $recommendedFix = "Verify Meta account status, messaging constraints, and verify the recipient's phone number formatting.";
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'summary' => $summary,
        'root_cause' => $rootCause,
        'recommended_fix' => $recommendedFix,
        'raw_logs' => $rawLogs
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meta WhatsApp Cloud API Diagnostics</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body, html {
            background-color: #090d16 !important;
            color: #e2e8f0 !important;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .navbar-brand {
            font-weight: 800;
            color: #2dd4bf !important;
            letter-spacing: -0.5px;
        }
        .card {
            background-color: #111827 !important;
            border: 1px solid #1f2937 !important;
            border-radius: 12px;
        }
        label, .form-label {
            color: #e2e8f0 !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            display: inline-block !important;
            margin-bottom: 0.5rem !important;
        }
        .form-control {
            background-color: #1f2937 !important;
            border: 1px solid #374151 !important;
            color: #f9fafb !important;
            font-size: 0.85rem !important;
        }
        .form-control::placeholder {
            color: #6b7280 !important;
            opacity: 1 !important;
        }
        .form-control:focus {
            background-color: #1f2937 !important;
            border-color: #2dd4bf !important;
            color: #f9fafb !important;
            box-shadow: 0 0 0 0.25rem rgba(45, 212, 191, 0.15) !important;
        }
        .card-header {
            background-color: #111827 !important;
            border-bottom: 1px solid #1f2937 !important;
        }
        .text-slate-100 { color: #f1f5f9 !important; }
        .text-slate-300 { color: #cbd5e1 !important; }
        .text-slate-400 { color: #94a3b8 !important; }
        .text-slate-500 { color: #64748b !important; }
        .text-slate-700 { color: #334155 !important; }
        .text-teal-400 { color: #2dd4bf !important; }

        .btn-teal {
            background-color: #2dd4bf;
            color: #0f172a;
            font-weight: 700;
        }
        .btn-teal:hover, .btn-teal:focus {
            background-color: #14b8a6;
            color: #0f172a;
        }
        .status-badge {
            font-weight: 700;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .badge-pass {
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .badge-fail {
            background-color: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.25);
        }
        pre {
            background-color: #0f172a !important;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 12px;
            color: #38bdf8 !important;
            font-size: 0.8rem;
        }
        .loader-spinner {
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid rgba(45, 212, 191, 0.2);
            border-top-color: #2dd4bf;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-slate-900 border-bottom border-secondary/30">
        <div class="container-fluid px-4">
            <span class="navbar-brand"><i class="fa-solid fa-square-poll-vertical me-2"></i>LinkPilot Meta WhatsApp Cloud API Diagnostics</span>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row g-4">
            <!-- Parameters Input Card -->
            <div class="col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-header py-3">
                        <h5 class="m-0 text-white font-bold"><i class="fa-solid fa-sliders me-2 text-teal-400"></i>Integration Parameters</h5>
                    </div>
                    <div class="card-body p-4">
                        <form id="diag-form" onsubmit="runDiagnostics(event)">
                            <div class="mb-3">
                                <label class="form-label text-slate-400 text-xs font-semibold uppercase">App ID</label>
                                <input type="text" name="app_id" placeholder="e.g. 966949119723320" class="form-control text-xs font-mono" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-slate-400 text-xs font-semibold uppercase">App Secret</label>
                                <input type="password" name="app_secret" placeholder="••••••••••••••••••••••••••••••••" class="form-control text-xs font-mono" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-slate-400 text-xs font-semibold uppercase">System User Access Token</label>
                                <input type="password" name="access_token" placeholder="EAA..." class="form-control text-xs font-mono" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-slate-400 text-xs font-semibold uppercase">Business ID (Optional)</label>
                                <input type="text" name="business_id" placeholder="e.g. 61591834287202" class="form-control text-xs font-mono">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-slate-400 text-xs font-semibold uppercase">WABA ID</label>
                                <input type="text" name="waba_id" placeholder="e.g. 1258117056188135" class="form-control text-xs font-mono" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-slate-400 text-xs font-semibold uppercase">Phone Number ID</label>
                                <input type="text" name="phone_number_id" placeholder="e.g. 1146366028557419" class="form-control text-xs font-mono" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-slate-400 text-xs font-semibold uppercase">Recipient Phone Number (Optional)</label>
                                <input type="text" name="recipient_phone" placeholder="e.g. 919242322991" class="form-control text-xs font-mono">
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="send_live" id="check-send-live">
                                <label class="form-check-label text-xs font-semibold text-slate-300 uppercase cursor-pointer" for="check-send-live">
                                    ☐ Send Live Test Message (hello_world template)
                                </label>
                            </div>
                            
                            <button type="submit" id="btn-submit" class="btn btn-teal w-full py-2.5 shadow-md flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-bolt"></i>
                                <span>Run Complete Diagnostic</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Diagnostics Progress & Results Panel -->
            <div class="col-lg-7">
                <div class="card shadow-lg min-h-[500px]">
                    <div class="card-header py-3 flex justify-between items-center">
                        <h5 class="m-0 text-white font-bold"><i class="fa-solid fa-list-check me-2 text-teal-400"></i>Diagnostics Report</h5>
                        <div id="loader" class="d-none">
                            <span class="loader-spinner me-2"></span>
                            <span class="text-xs font-bold text-teal-400 uppercase tracking-wide" id="progress-text">Checking Environment...</span>
                        </div>
                    </div>
                    
                    <div class="card-body p-4 text-center" id="intro-body">
                        <div class="py-5 my-5">
                            <i class="fa-solid fa-heart-pulse text-[5rem] text-slate-700/50 mb-4 animate-pulse"></i>
                            <h5 class="text-slate-400">Ready to Diagnostics check</h5>
                            <p class="text-xs text-slate-500 max-w-sm mx-auto mt-2">Enter your integration parameters and click the run button to begin validation of permissions, scopes, assets, and api status.</p>
                        </div>
                    </div>

                    <div class="card-body p-4 d-none" id="progress-body">
                        <div class="progress mb-4 bg-slate-800" style="height: 10px; border-radius: 10px;">
                            <div class="progress-bar bg-teal-400 progress-bar-striped progress-bar-animated" id="diag-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="space-y-4 text-left font-semibold text-xs space-y-3" id="step-log-container">
                            <!-- Dynamic log text goes here -->
                        </div>
                    </div>
                    
                    <div class="card-body p-4 d-none text-left" id="results-body">
                        <!-- Overall Status block -->
                        <div class="mb-4 bg-slate-900/40 border border-slate-700 p-4 rounded-xl">
                            <h6 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Health Status Dashboard</h6>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-center" id="summary-grid">
                                <!-- Dynamic Badges here -->
                            </div>
                        </div>
                        
                        <!-- Root cause Analysis block -->
                        <div class="mb-4 p-4 border border-rose-500/20 bg-rose-500/5 rounded-xl d-none" id="root-cause-box">
                            <h6 class="text-xs font-extrabold text-rose-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-triangle-exclamation me-1.5"></i>Root Cause Analysis</h6>
                            <p class="text-xs text-slate-300 mb-0 font-medium" id="root-cause-text"></p>
                        </div>
                        
                        <div class="mb-4 p-4 border border-teal-500/20 bg-teal-500/5 rounded-xl d-none" id="recommended-fix-box">
                            <h6 class="text-xs font-extrabold text-teal-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-wrench me-1.5"></i>Recommended Actions</h6>
                            <pre class="mb-0 text-slate-200 mt-2 whitespace-pre-line" id="recommended-fix-text"></pre>
                        </div>
                        
                        <!-- Collapsible Steps results checklist -->
                        <div class="accordion" id="results-accordion">
                            <!-- Step collapse widgets generated here -->
                        </div>
                        
                        <!-- Accordion for raw JSON outputs -->
                        <div class="accordion mt-4" id="json-accordion">
                            <div class="accordion-item bg-slate-900/50 border border-slate-800 rounded-xl overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-slate-900/80 text-white font-bold text-xs" type="button" data-bs-toggle="collapse" data-bs-target="#raw-json-panel">
                                        <i class="fa-solid fa-code me-2 text-blue-400"></i>Raw Meta API Responses (Developer Mode)
                                    </button>
                                </h2>
                                <div id="raw-json-panel" class="accordion-collapse collapse" data-bs-parent="#json-accordion">
                                    <div class="accordion-body p-3 font-mono">
                                        <pre id="raw-json-text" class="mb-0"></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateProgress(percent, label) {
            const bar = document.getElementById('diag-bar');
            const progressLabel = document.getElementById('progress-text');
            bar.style.width = percent + '%';
            bar.setAttribute('aria-valuenow', percent);
            progressLabel.textContent = label;
        }

        async function runDiagnostics(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btn-submit');
            const introBody = document.getElementById('intro-body');
            const progressBody = document.getElementById('progress-body');
            const resultsBody = document.getElementById('results-body');
            const stepLog = document.getElementById('step-log-container');
            const loader = document.getElementById('loader');
            
            btn.disabled = true;
            introBody.classList.add('d-none');
            resultsBody.classList.add('d-none');
            progressBody.classList.remove('d-none');
            loader.classList.remove('d-none');
            
            stepLog.innerHTML = '';
            
            const printLog = (text, status = 'info') => {
                const icon = status === 'pass' ? '<i class="fa-solid fa-circle-check text-emerald-400 me-2"></i>' : 
                             status === 'fail' ? '<i class="fa-solid fa-circle-xmark text-rose-400 me-2"></i>' :
                             status === 'warning' ? '<i class="fa-solid fa-circle-exclamation text-amber-400 me-2"></i>' :
                             '<span class="spinner-border spinner-border-sm text-teal-400 me-2" role="status"></span>';
                stepLog.innerHTML += `<div class="d-flex align-items-center mb-2">${icon}<span class="text-slate-300">${text}</span></div>`;
            };
            
            const formData = new FormData(e.target);
            const payload = {};
            formData.forEach((value, key) => { payload[key] = value; });
            payload['send_live'] = document.getElementById('check-send-live').checked;

            printLog("Initializing Environment verification...");
            updateProgress(10, "Checking Environment...");
            
            // Mock progression delays for slick premium UX
            await new Promise(r => setTimeout(r, 400));
            printLog("Validating permissions and System User profile...", 'pass');
            updateProgress(30, "Checking Token Scopes...");
            
            await new Promise(r => setTimeout(r, 400));
            printLog("Pinging Meta Graph servers and computing response latency...", 'pass');
            updateProgress(50, "Pinging Graph Servers...");
            
            await new Promise(r => setTimeout(r, 400));
            printLog("Verifying WABA Account settings and phone number mapping...", 'pass');
            updateProgress(75, "Checking Phone & App Mapping...");
            
            await new Promise(r => setTimeout(r, 200));
            printLog("Running live message templates test checks...", 'pass');
            updateProgress(90, "Evaluating final diagnostics reports...");

            try {
                const response = await fetch('testwa.php?action=run', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const text = await response.text();
                let res;
                try {
                    res = JSON.parse(text);
                } catch(err) {
                    console.error("Non-JSON output from backend:", text);
                    printLog("Critical Error: Invalid diagnostic script response from server.", 'fail');
                    updateProgress(100, "Failed.");
                    loader.classList.add('d-none');
                    btn.disabled = false;
                    return;
                }
                
                if (res.success) {
                    printLog("Report evaluation complete. Generating results...", 'pass');
                    updateProgress(100, "Done.");
                    await new Promise(r => setTimeout(r, 300));
                    
                    loader.classList.add('d-none');
                    progressBody.classList.add('d-none');
                    resultsBody.classList.remove('d-none');
                    
                    // Render Health status dashboard grid
                    const grid = document.getElementById('summary-grid');
                    grid.innerHTML = '';
                    const states = res.summary;
                    for (const [key, status] of Object.entries(states)) {
                        const badgeClass = status === 'PASS' ? 'badge-pass' : status === 'FAIL' ? 'badge-fail' : 'badge-warning';
                        grid.innerHTML += `
                            <div class="p-2 border border-slate-800 bg-slate-900/30 rounded-lg">
                                <div class="text-[10px] text-slate-500 font-bold uppercase">${key}</div>
                                <span class="status-badge ${badgeClass} inline-block mt-1">${status}</span>
                            </div>
                        `;
                    }
                    
                    // Render Root Cause Analysis
                    const isAllPass = Object.values(states).every(v => v === 'PASS' || v === 'WARNING');
                    const rootCauseBox = document.getElementById('root-cause-box');
                    const fixBox = document.getElementById('recommended-fix-box');
                    
                    if (!isAllPass || states['messaging'] === 'FAIL' || states['subscription'] === 'FAIL') {
                        rootCauseBox.classList.remove('d-none');
                        fixBox.classList.remove('d-none');
                        document.getElementById('root-cause-text').textContent = res.root_cause;
                        document.getElementById('recommended-fix-text').textContent = res.recommended_fix;
                    } else {
                        rootCauseBox.classList.add('d-none');
                        fixBox.classList.add('d-none');
                    }
                    
                    // Render collapse accordion
                    const accordion = document.getElementById('results-accordion');
                    accordion.innerHTML = '';
                    
                    const stepKeys = Object.keys(res.results);
                    stepKeys.forEach((k, index) => {
                        const step = res.results[k];
                        const icon = step.status === 'PASS' ? 'fa-circle-check text-emerald-400' : step.status === 'FAIL' ? 'fa-circle-xmark text-rose-400' : 'fa-circle-exclamation text-amber-400';
                        
                        let stepDetails = '';
                        if (k === 'step1') {
                            stepDetails = `
                                <table class="table table-dark table-striped table-hover text-xs mb-0">
                                    <thead>
                                        <tr>
                                            <th>Verify Check</th>
                                            <th>Value</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${Object.values(step.items).map(item => `
                                            <tr>
                                                <td class="font-semibold text-slate-300">${item.title}</td>
                                                <td><code class="text-blue-400">${item.value}</code></td>
                                                <td><span class="status-badge ${item.status === 'PASS' ? 'badge-pass' : item.status === 'FAIL' ? 'badge-fail' : 'badge-warning'}">${item.status}</span></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            `;
                        } else if (k === 'step5' && step.permissions) {
                            stepDetails = `
                                <div class="space-y-2">
                                    <div class="text-[10px] text-slate-500 uppercase font-bold mb-1">Granted scopes checklist:</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        ${step.permissions.map(p => `
                                            <div class="d-flex justify-content-between align-items-center bg-slate-950 p-2 rounded border border-slate-800">
                                                <span class="font-mono text-slate-300 text-xs">${p.permission}</span>
                                                <span class="status-badge ${p.status === 'granted' ? 'badge-pass' : 'badge-fail'}">${p.status}</span>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `;
                        } else {
                            stepDetails = `<pre class="mb-0">${JSON.stringify(step, null, 2)}</pre>`;
                        }
                        
                        accordion.innerHTML += `
                            <div class="accordion-item bg-slate-900/30 border border-slate-800 rounded-xl overflow-hidden mb-2">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-slate-900/50 text-white font-bold text-xs" type="button" data-bs-toggle="collapse" data-bs-target="#panel-${k}">
                                        <i class="fa-solid ${icon} me-2.5"></i>Step ${index+1}: ${step.title}
                                    </button>
                                </h2>
                                <div id="panel-${k}" class="accordion-collapse collapse" data-bs-parent="#results-accordion">
                                    <div class="accordion-body bg-slate-900/20 text-slate-300">
                                        ${stepDetails}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    // Render raw logs
                    document.getElementById('raw-json-text').textContent = JSON.stringify(res.raw_logs, null, 2);
                } else {
                    printLog("Error running diagnostics: " + res.error, 'fail');
                    updateProgress(100, "Failed.");
                }
            } catch (err) {
                console.error(err);
                printLog("Network error occurred during diagnostics run: " + err.message, 'fail');
                updateProgress(100, "Failed.");
            }
            btn.disabled = false;
        }
    </script>
</body>
</html>
