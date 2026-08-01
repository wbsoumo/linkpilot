<?php
// backend/patch_config.php
header('Content-Type: text/plain');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die("Error: config.php not found at $configFile\n");
}

echo "Reading config.php...\n";
$content = file_get_contents($configFile);

$updated = false;

// 1. Add GITHUB_TOKEN definition if not present
if (strpos($content, "define('GITHUB_TOKEN'") === false) {
    echo "1. Adding GITHUB_TOKEN constant...\n";
    $target = "// GitHub Models API Configuration\ndefine('GITHUB_MODELS_MODEL', 'gpt-4o-mini');";
    $replacement = "// GitHub Models API Configuration\ndefine('GITHUB_TOKEN', getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_KEY') ?: 'github_pat_placeholder-please-replace-with-your-actual-token');\ndefine('GITHUB_MODELS_MODEL', 'gpt-4o-mini');";
    
    if (strpos($content, $target) !== false) {
        $content = str_replace($target, $replacement, $content);
        $updated = true;
    } else {
        echo "   WARNING: Target block for GITHUB_TOKEN constant not found.\n";
    }
}

// 2. Update default OpenRouter model to gemini-2.5-flash
if (strpos($content, "define('OPENROUTER_MODEL', 'google/gemini-2.0-flash-lite:free')") !== false) {
    echo "2. Updating default OpenRouter model ID...\n";
    $content = str_replace(
        "define('OPENROUTER_MODEL', 'google/gemini-2.0-flash-lite:free')",
        "define('OPENROUTER_MODEL', 'google/gemini-2.5-flash')",
        $content
    );
    $updated = true;
}

// 3. Prioritize current user's API keys in callAI
if (strpos($content, "WHERE u.role = 'admin' AND k.provider = ?") !== false && strpos($content, "k.user_id = ? AND k.provider = ?") === false) {
    echo "3. Updating key prioritization to check current user first...\n";
    
    $target = "    \$apiKeysList = [];\n    try {\n        // Retrieve all active keys belonging to users with the 'admin' role\n        \$stmtKeys = \$db->prepare(\"\n            SELECT k.id, k.api_key, k.status \n            FROM user_ai_keys k \n            JOIN users u ON k.user_id = u.id \n            WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') \n            ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC\n        \");\n        \$stmtKeys->execute([\$provider]);\n        \$apiKeysList = \$stmtKeys->fetchAll();\n    } catch (Exception \$e) {}";
    
    $replacement = "    \$apiKeysList = [];\n    try {\n        if (\$userId !== null) {\n            \$stmtKeys = \$db->prepare(\"\n                SELECT k.id, k.api_key, k.status \n                FROM user_ai_keys k \n                WHERE k.user_id = ? AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') \n                ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC\n            \");\n            \$stmtKeys->execute([\$userId, \$provider]);\n            \$apiKeysList = \$stmtKeys->fetchAll();\n        }\n        \n        if (empty(\$apiKeysList)) {\n            // Retrieve all active keys belonging to users with the 'admin' role\n            \$stmtKeys = \$db->prepare(\"\n                SELECT k.id, k.api_key, k.status \n                FROM user_ai_keys k \n                JOIN users u ON k.user_id = u.id \n                WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') \n                ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC\n            \");\n            \$stmtKeys->execute([\$provider]);\n            \$apiKeysList = \$stmtKeys->fetchAll();\n        }\n    } catch (Exception \$e) {}";
    
    if (strpos($content, $target) !== false) {
        $content = str_replace($target, $replacement, $content);
        $updated = true;
    } else {
        // Search looser match
        $targetAlt = "        \$stmtKeys = \$db->prepare(\"\n            SELECT k.id, k.api_key, k.status \n            FROM user_ai_keys k \n            JOIN users u ON k.user_id = u.id \n            WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') \n            ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC\n        \");\n        \$stmtKeys->execute([\$provider]);\n        \$apiKeysList = \$stmtKeys->fetchAll();";
        
        $replacementAlt = "        if (\$userId !== null) {\n            \$stmtKeys = \$db->prepare(\"\n                SELECT k.id, k.api_key, k.status \n                FROM user_ai_keys k \n                WHERE k.user_id = ? AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') \n                ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC\n            \");\n            \$stmtKeys->execute([\$userId, \$provider]);\n            \$apiKeysList = \$stmtKeys->fetchAll();\n        }\n        if (empty(\$apiKeysList)) {\n            \$stmtKeys = \$db->prepare(\"\n                SELECT k.id, k.api_key, k.status \n                FROM user_ai_keys k \n                JOIN users u ON k.user_id = u.id \n                WHERE u.role = 'admin' AND k.provider = ? AND k.status NOT IN ('invalid', 'paused') \n                ORDER BY FIELD(k.status, 'active', 'limit_exceeded') ASC, k.id ASC\n            \");\n            \$stmtKeys->execute([\$provider]);\n            \$apiKeysList = \$stmtKeys->fetchAll();\n        }";
        
        if (strpos($content, $targetAlt) !== false) {
            $content = str_replace($targetAlt, $replacementAlt, $content);
            $updated = true;
        } else {
            echo "   WARNING: Key prioritization block not found.\n";
        }
    }
}

// 4. Update fallbackKey logic to support GITHUB_TOKEN constant and GITHUB_API_KEY
if (strpos($content, "\$fallbackKey = getenv('GITHUB_TOKEN') ?: '';") !== false) {
    echo "4. Updating fallbackKey logic in callAI...\n";
    $content = str_replace(
        "\$fallbackKey = getenv('GITHUB_TOKEN') ?: '';",
        "\$fallbackKey = getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_KEY') ?: (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '');",
        $content
    );
    $updated = true;
}

// 5. Add max_tokens limit to API requests (only if not already present)
if (strpos($content, '"max_tokens" => 1000') === false) {
    echo "5. Adding max_tokens => 1000 limits...\n";
    
    // github_models block
    $targetGithub = "                \$postFields = [\n                    \"model\" => \$model,\n                    \"messages\" => [\n                        [\"role\" => \"system\", \"content\" => \$systemPrompt],\n                        [\"role\" => \"user\", \"content\" => \$userPrompt]\n                    ]\n                ];";
    $replacementGithub = "                \$postFields = [\n                    \"model\" => \$model,\n                    \"messages\" => [\n                        [\"role\" => \"system\", \"content\" => \$systemPrompt],\n                        [\"role\" => \"user\", \"content\" => \$userPrompt]\n                    ],\n                    \"max_tokens\" => 1000\n                ];";
    if (strpos($content, $targetGithub) !== false) {
        $content = str_replace($targetGithub, $replacementGithub, $content);
        $updated = true;
    }
    
    // google_ai_studio block
    $targetGoogle = "                \$postFields = [\n                    \"model\" => \$model,\n                    \"messages\" => [\n                        [\"role\" => \"system\", \"content\" => \$systemPrompt],\n                        [\"role\" => \"user\", \"content\" => \$userPrompt]\n                    ]\n                ];";
    $replacementGoogle = "                \$postFields = [\n                    \"model\" => \$model,\n                    \"messages\" => [\n                        [\"role\" => \"system\", \"content\" => \$systemPrompt],\n                        [\"role\" => \"user\", \"content\" => \$userPrompt]\n                    ],\n                    \"max_tokens\" => 1000\n                ];";
    if (strpos($content, $targetGoogle) !== false) {
        $content = str_replace($targetGoogle, $replacementGoogle, $content);
        $updated = true;
    }
    
    // openrouter block
    $targetOpenrouter = "                        \$postFields = [\n                            \"model\" => \$currentModel,\n                            \"messages\" => [\n                                [\"role\" => \"system\", \"content\" => \$systemPrompt],\n                                [\"role\" => \"user\", \"content\" => \$userPrompt]\n                            ]\n                        ];";
    $replacementOpenrouter = "                        \$postFields = [\n                            \"model\" => \$currentModel,\n                            \"messages\" => [\n                                [\"role\" => \"system\", \"content\" => \$systemPrompt],\n                                [\"role\" => \"user\", \"content\" => \$userPrompt]\n                            ],\n                            \"max_tokens\" => 1000\n                        ];";
    if (strpos($content, $targetOpenrouter) !== false) {
        $content = str_replace($targetOpenrouter, $replacementOpenrouter, $content);
        $updated = true;
    }
}

if ($updated) {
    echo "Saving patched config.php...\n";
    if (file_put_contents($configFile, $content) !== false) {
        echo "SUCCESS: config.php patched successfully!\n";
    } else {
        echo "Error: Failed to write patched config.php!\n";
    }
} else {
    echo "config.php is already up to date.\n";
}

if (function_exists('opcache_reset')) {
    echo "Resetting PHP OPcache...\n";
    if (opcache_reset()) {
        echo "SUCCESS: OPcache reset successfully!\n";
    } else {
        echo "FAILED: OPcache reset failed.\n";
    }
}
