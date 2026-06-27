<?php
// backend/providers/ApolloProvider.php

require_once __DIR__ . '/EmailProviderInterface.php';

class ApolloProvider implements EmailProviderInterface {
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function findEmail($firstName, $lastName, $companyName, $domain, $title = '') {
        if (empty($this->apiKey)) {
            return null;
        }

        $payload = [
            'api_key' => $this->apiKey,
            'first_name' => $firstName,
            'last_name' => $lastName
        ];

        if (!empty($domain)) {
            $payload['domain'] = $domain;
        }
        if (!empty($companyName)) {
            $payload['organization_name'] = $companyName;
        }
        if (!empty($title)) {
            $payload['title'] = $title;
        }

        $url = 'https://api.apollo.io/v1/people/match';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot/1.0');
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('Apollo API curl error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('Apollo API returned HTTP status code: ' . $httpCode . '. Body: ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['person']['email'])) {
            $status = isset($data['person']['email_status']) ? $data['person']['email_status'] : 'unknown';
            $score = ($status === 'verified' || $status === 'deliverable') ? 95 : 60;
            
            return [
                'email' => $data['person']['email'],
                'score' => $score,
                'status' => $status
            ];
        }

        return null;
    }

    public function getRemainingCredits() {
        // Apollo doesn't provide a public API credits checker endpoint via this authentication key
        return null;
    }
}
