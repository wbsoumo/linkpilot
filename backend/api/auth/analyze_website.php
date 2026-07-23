<?php
// backend/api/auth/analyze_website.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../wallet_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse('error', 'Invalid JSON input', [], 400);
}

$website = trim($input['website'] ?? '');

if (empty($website)) {
    sendJsonResponse('error', 'Website URL is required.', [], 400);
}

// Ensure protocol is present
if (!preg_match('/^https?:\/\//i', $website)) {
    $websiteUrl = 'https://' . $website;
} else {
    $websiteUrl = $website;
}

$host = parse_url($websiteUrl, PHP_URL_HOST) ?: $website;

// Attempt homepage scrape with a short timeout
$scrapedTitle = '';
$scrapedMetaDesc = '';
$scrapedBodyText = '';

try {
    $ch = curl_init($websiteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds max
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($html)) {
        // Extract Title
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $scrapedTitle = trim($matches[1]);
        }
        // Extract Meta Description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $matches)) {
            $scrapedMetaDesc = trim($matches[1]);
        }
        // Extract basic body text (strip tags and clean up whitespace)
        $body = '';
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $body = $matches[1];
        } else {
            $body = $html;
        }
        // Strip scripts and styles
        $body = preg_replace('/<script[^>]*?>.*?<\/script>/is', '', $body);
        $body = preg_replace('/<style[^>]*?>.*?<\/style>/is', '', $body);
        $body = strip_tags($body);
        $body = preg_replace('/\s+/', ' ', $body);
        $scrapedBodyText = substr(trim($body), 0, 1000); // Take first 1000 chars
    }
} catch (Exception $e) {
    // Fail silently to let AI rely on domain information
}

// Formulate AI Prompts
$systemPrompt = <<<PROMPT
You are an expert market intelligence and company profile extractor.
Analyze the given company website/domain and extract the following details. You must respond with a valid JSON object only, containing the following keys and formats:
{
  "company_name": "...",
  "location": "...", (based location of company, e.g. "Based in India", "Based in United States", etc.)
  "company_size": "...", (choose from standard ranges: "1-5 employees", "6-10 employees", "11-50 employees", "51-200 employees", "201-500 employees", "501-1000 employees", "1000+ employees")
  "industry": "...", (industry niche, e.g. "Marketing and Advertising", "Software Development", "E-commerce", "Financial Services", etc.)
  "description": "..."
}
If the scraped website content is empty, use your pre-trained knowledge base to find these details for the domain.
If you do not know the company, make a best-effort guess based on the domain name or provide professional default values (e.g. location based on domain suffix like .in = India, or default company size/industry based on name).
Do not output markdown, code block wrappers (like ```json), preambles, or postambles. Output only valid JSON.
PROMPT;

$userPrompt = "Website URL: " . $websiteUrl . "\n" .
              "Domain Host: " . $host . "\n" .
              "Scraped Title: " . $scrapedTitle . "\n" .
              "Scraped Meta Description: " . $scrapedMetaDesc . "\n" .
              "Scraped Content: " . $scrapedBodyText;

try {
    $aiResult = callAI($systemPrompt, $userPrompt);
    
    $rawText = trim($aiResult['text'] ?? '');
    
    // Strip markdown code block wrappers if present
    if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $rawText, $matches)) {
        $rawText = trim($matches[1]);
    }
    
    $companyData = json_decode($rawText, true);
    
    if (!$companyData) {
        throw new Exception("AI response was not valid JSON: " . $rawText);
    }
    
    // Ensure all required fields exist in response
    $finalData = [
        'company_name' => trim($companyData['company_name'] ?? ucwords(explode('.', $host)[0])),
        'location' => trim($companyData['location'] ?? 'Based in India'),
        'company_size' => trim($companyData['company_size'] ?? '6 to 10 employees'),
        'industry' => trim($companyData['industry'] ?? 'Marketing and Advertising'),
        'description' => trim($companyData['description'] ?? ''),
        'website' => $host
    ];
    
    sendJsonResponse('success', 'Website analyzed successfully.', $finalData);
    
} catch (Exception $e) {
    // Return graceful defaults if AI fails
    $fallbackName = ucwords(explode('.', $host)[0]);
    sendJsonResponse('success', 'Website analysis fallback loaded.', [
        'company_name' => $fallbackName,
        'location' => 'Based in India',
        'company_size' => '6 to 10 employees',
        'industry' => 'Marketing and Advertising',
        'description' => 'Website profile details.',
        'website' => $host,
        'fallback' => true,
        'error_log' => $e->getMessage()
    ]);
}
