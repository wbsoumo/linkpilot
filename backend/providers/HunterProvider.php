<?php
// backend/providers/HunterProvider.php

require_once __DIR__ . '/EmailProviderInterface.php';

class HunterProvider implements EmailProviderInterface {
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function findEmail($firstName, $lastName, $companyName, $domain, $title = '') {
        if (empty($this->apiKey)) {
            return null;
        }

        $params = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'api_key' => $this->apiKey
        ];

        if (!empty($domain)) {
            $params['domain'] = $domain;
        } elseif (!empty($companyName)) {
            $params['company'] = $companyName;
        } else {
            return null; // Need either domain or company
        }

        $url = 'https://api.hunter.io/v2/email-finder?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot/1.0');
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('Hunter API curl error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('Hunter API returned HTTP status code: ' . $httpCode . '. Body: ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['data']['email'])) {
            $score = isset($data['data']['score']) ? (int)$data['data']['score'] : 50;
            $status = isset($data['data']['verification']['status']) ? $data['data']['verification']['status'] : 'unknown';
            
            return [
                'email' => $data['data']['email'],
                'score' => $score,
                'status' => $status
            ];
        }

        return null;
    }

    public function getRemainingCredits() {
        if (empty($this->apiKey)) {
            return null;
        }

        $url = 'https://api.hunter.io/v2/account?api_key=' . urlencode($this->apiKey);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot/1.0');
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            curl_close($ch);
            return null;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['data']['calls']['left'])) {
                return (int)$data['data']['calls']['left'];
            }
        }

        return null;
    }
}
