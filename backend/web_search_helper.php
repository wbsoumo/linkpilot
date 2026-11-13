<?php
// backend/web_search_helper.php
// Enterprise web search, live weather, and webpage content scraper for LinkPilot AI Agent.

class WebSearchHelper {

    /**
     * Fetches exact real-time live weather for any city or location globally.
     */
    public static function getWeather($location) {
        $location = trim($location);
        if (empty($location)) {
            return ['error' => 'Please provide a city or location name.'];
        }

        $encodedLoc = urlencode($location);
        $url = "https://wttr.in/{$encodedLoc}?format=j1";

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'curl/7.68.0');
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200 || empty($res)) {
                return [
                    'location' => $location,
                    'error' => "Weather service is currently busy or unreachable for '{$location}'. Please try again."
                ];
            }

            $data = json_decode($res, true);
            if (!$data || !isset($data['current_condition'][0])) {
                return [
                    'location' => $location,
                    'error' => "Could not parse meteorological observations for '{$location}'."
                ];
            }

            $current = $data['current_condition'][0];
            $forecastToday = $data['weather'][0] ?? null;

            $tempC = $current['temp_C'] ?? 'N/A';
            $feelsLikeC = $current['FeelsLikeC'] ?? 'N/A';
            $condition = $current['weatherDesc'][0]['value'] ?? 'Clear';
            $humidity = ($current['humidity'] ?? 'N/A') . '%';
            $windSpeed = ($current['windspeedKmph'] ?? 'N/A') . ' km/h';
            $uvIndex = $current['uvIndex'] ?? 'N/A';

            $minTemp = $forecastToday['mintempC'] ?? null;
            $maxTemp = $forecastToday['maxtempC'] ?? null;

            $summary = "Real-time weather in {$location}: {$condition}, {$tempC}°C (Feels like {$feelsLikeC}°C). Humidity: {$humidity}, Wind: {$windSpeed}, UV: {$uvIndex}.";
            if ($minTemp !== null && $maxTemp !== null) {
                $summary .= " Today's forecast: Min {$minTemp}°C, Max {$maxTemp}°C.";
            }

            return [
                'status' => 'success',
                'location' => $location,
                'condition' => $condition,
                'temperature_celsius' => (int)$tempC,
                'feels_like_celsius' => (int)$feelsLikeC,
                'humidity' => $humidity,
                'wind_speed' => $windSpeed,
                'uv_index' => $uvIndex,
                'min_temp' => $minTemp,
                'max_temp' => $maxTemp,
                'formatted_summary' => $summary
            ];
        } catch (Throwable $e) {
            return [
                'location' => $location,
                'error' => 'Weather retrieval exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Performs a web search returning top organic titles, snippets, and source URLs.
     * Uses zero-config DuckDuckGo Lite with fallback to Instant Answers or configured Serper/Tavily keys.
     */
    public static function searchWeb($query, $limit = 5) {
        $query = trim($query);
        if (empty($query)) {
            return ['error' => 'Search query cannot be empty.'];
        }

        $limit = max(1, min(10, (int)$limit));
        $results = [];

        // Check if an external Serper / Tavily key is provided in environment
        $serperKey = getenv('SERPER_API_KEY');
        if (!empty($serperKey)) {
            $serperRes = self::searchViaSerper($query, $serperKey, $limit);
            if (!empty($serperRes)) {
                return [
                    'query' => $query,
                    'provider' => 'serper',
                    'total_found' => count($serperRes),
                    'results' => $serperRes
                ];
            }
        }

        // Primary zero-config engine: DuckDuckGo Lite with DOMXPath
        try {
            $ch = curl_init('https://lite.duckduckgo.com/lite/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['q' => $query]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 200 && !empty($html)) {
                $dom = new DOMDocument();
                @$dom->loadHTML($html);
                $xpath = new DOMXPath($dom);

                $linkNodes = $xpath->query('//a[contains(@class, "result-link")]');
                $snippetNodes = $xpath->query('//td[contains(@class, "result-snippet")]');

                $count = min($linkNodes->length, $limit);
                for ($i = 0; $i < $count; $i++) {
                    $rawUrl = $linkNodes->item($i)->getAttribute('href');
                    if (strpos($rawUrl, 'duckduckgo.com/l/?uddg=') !== false) {
                        parse_str(parse_url($rawUrl, PHP_URL_QUERY), $qp);
                        if (!empty($qp['uddg'])) {
                            $rawUrl = urldecode($qp['uddg']);
                        }
                    }

                    $title = trim($linkNodes->item($i)->textContent);
                    $snippet = $snippetNodes->item($i) ? trim($snippetNodes->item($i)->textContent) : '';

                    if (!empty($title) && (!empty($snippet) || !empty($rawUrl))) {
                        $results[] = [
                            'title' => $title,
                            'snippet' => $snippet,
                            'url' => $rawUrl
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("DuckDuckGo Lite search warning: " . $e->getMessage());
        }

        // Secondary fallback: DuckDuckGo Instant Answer API
        if (empty($results)) {
            try {
                $apiUrl = "https://api.duckduckgo.com/?q=" . urlencode($query) . "&format=json&no_html=1&skip_disambig=1";
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'LinkPilot-AI/1.0');
                $res = curl_exec($ch);
                $data = json_decode($res, true);

                if (!empty($data['AbstractText'])) {
                    $results[] = [
                        'title' => $data['Heading'] ?? $query,
                        'snippet' => $data['AbstractText'],
                        'url' => $data['AbstractURL'] ?? ''
                    ];
                }

                if (!empty($data['RelatedTopics']) && is_array($data['RelatedTopics'])) {
                    foreach (array_slice($data['RelatedTopics'], 0, $limit) as $topic) {
                        if (!empty($topic['Text'])) {
                            $results[] = [
                                'title' => $query,
                                'snippet' => $topic['Text'],
                                'url' => $topic['FirstURL'] ?? ''
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        return [
            'query' => $query,
            'provider' => 'web_search',
            'total_found' => count($results),
            'results' => $results,
            'summary' => empty($results)
                ? "No direct search results found for '{$query}'."
                : "Found " . count($results) . " relevant web sources."
        ];
    }

    /**
     * Safely fetches and extracts clean readable markdown text from any specific public website URL.
     */
    public static function fetchUrlContent($url, $instruction = '') {
        $url = trim($url);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => 'Please provide a valid HTTP or HTTPS URL (e.g. https://example.com).'];
        }

        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return ['error' => 'Only HTTP and HTTPS URLs are supported.'];
        }

        $host = strtolower($parsed['host'] ?? '');
        // SSRF protection
        if (
            $host === 'localhost' ||
            $host === '127.0.0.1' ||
            strpos($host, '192.168.') === 0 ||
            strpos($host, '10.') === 0 ||
            strpos($host, '172.16.') === 0 ||
            substr($host, -6) === '.local'
        ) {
            return ['error' => 'Access to internal/private network addresses is restricted for security.'];
        }

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;

            if ($httpCode >= 400 || empty($html)) {
                return [
                    'url' => $url,
                    'error' => "Failed to fetch webpage content (HTTP {$httpCode}). The website may be protected by Cloudflare/CAPTCHA or unreachable."
                ];
            }

            $title = '';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
                $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $description = '';
            if (preg_match('/<meta[^>]+name=[\"\']description[\"\'][^>]+content=[\"\'](.*?)[\"\']/si', $html, $m)) {
                $description = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            // Clean HTML to readable markdown text
            $clean = preg_replace('/<(script|style|noscript|svg|iframe|header|footer|nav|aside)[^>]*>.*?<\/\\1>/si', '', $html);
            $clean = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/si', "\n\n### $1\n", $clean);
            $clean = preg_replace('/<li[^>]*>(.*?)<\/li>/si', "\n* $1", $clean);
            $clean = preg_replace('/<(p|div|tr)[^>]*>/si', "\n", $clean);
            $clean = strip_tags($clean);
            $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $clean = preg_replace('/[ \t]+/', ' ', $clean);
            $clean = preg_replace('/\n\s*\n+/', "\n\n", $clean);
            $clean = trim($clean);

            // Limit to ~4500 characters so token budget is preserved
            if (mb_strlen($clean) > 4500) {
                $clean = mb_substr($clean, 0, 4500) . "\n\n... [Content truncated for length]";
            }

            return [
                'status' => 'success',
                'url' => $finalUrl,
                'title' => $title,
                'meta_description' => $description,
                'instruction' => $instruction,
                'character_count' => mb_strlen($clean),
                'extracted_content' => $clean
            ];
        } catch (Throwable $e) {
            return [
                'url' => $url,
                'error' => 'Webpage fetch exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Optional search via Serper.dev API if key is available.
     */
    private static function searchViaSerper($query, $apiKey, $limit = 5) {
        try {
            $ch = curl_init('https://google.serper.dev/search');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['q' => $query, 'num' => $limit]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-API-KEY: ' . $apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            $data = json_decode($res, true);

            $out = [];
            if (!empty($data['organic'])) {
                foreach (array_slice($data['organic'], 0, $limit) as $item) {
                    $out[] = [
                        'title' => $item['title'] ?? '',
                        'snippet' => $item['snippet'] ?? '',
                        'url' => $item['link'] ?? ''
                    ];
                }
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}
