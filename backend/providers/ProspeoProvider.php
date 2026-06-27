<?php
// backend/providers/ProspeoProvider.php

require_once __DIR__ . '/EmailProviderInterface.php';

class ProspeoProvider implements EmailProviderInterface {
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function findEmail($firstName, $lastName, $companyName, $domain, $title = '') {
        if (empty($this->apiKey)) {
            return null;
        }

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName
        ];

        if (!empty($domain)) {
            $payload['domain'] = $domain;
        }
        if (!empty($companyName)) {
            $payload['company'] = $companyName;
        }

        $url = 'https://api.prospeo.io/v1/email-finder';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-KEY: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot/1.0');
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('Prospeo API curl error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('Prospeo API returned HTTP status code: ' . $httpCode . '. Body: ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['email'])) {
            $score = isset($data['score']) ? (int)$data['score'] : 50;
            $status = isset($data['email_status']) ? $data['email_status'] : 'unknown';
            
            return [
                'email' => $data['email'],
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

        $url = 'https://api.prospeo.io/v1/credits';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-KEY: ' . $this->apiKey
        ]);
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
            if (isset($data['credits']['remaining'])) {
                return (int)$data['credits']['remaining'];
            }
        }

        return null;
    }
}
