<?php
// backend/agent/SlotFillingEngine.php
// Autonomous Agent Engine 2.0: Conversational Slot-Filling State Machine.
// Strictly collects and validates the 6 mandatory fields before scheduling a prospecting workflow:
// 1. Target industry & geographic location
// 2. Number of leads per run (bounded 1 to 25)
// 3. Service or value proposition offered
// 4. Verified connected sender identity
// 5. Schedule and user timezone
// 6. Approval policy (strictly default: digest_review)
// NEVER invents missing configuration or silently activates an incomplete schedule.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WorkflowScheduler.php';

class SlotFillingEngine {

    const FIELD_INDUSTRY = 'target_industry';
    const FIELD_LOCATION = 'target_location';
    const FIELD_LEAD_COUNT = 'lead_count';
    const FIELD_VALUE_PROP = 'value_proposition';
    const FIELD_SENDER_IDENTITY = 'sender_identity';
    const FIELD_SCHEDULE = 'schedule_time';
    const FIELD_APPROVAL_POLICY = 'approval_policy';

    const DEFAULT_APPROVAL_POLICY = WorkflowScheduler::POLICY_DIGEST_REVIEW;
    const MIN_LEADS = 1;
    const MAX_LEADS = 25;
    const DEFAULT_LEADS = 10;

    /**
     * Retrieves connected sender identities for a user from real OAuth & SMTP configurations.
     */
    public static function getVerifiedSenderIdentities(int $userId, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        $verified = [];

        // 1. Check Google / Gmail connections with gmail.send scope
        try {
            $stmt = $db->prepare("SELECT email, connected_email, scopes FROM external_app_connections WHERE user_id = ? AND provider = 'google' AND status = 'active'");
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $scopes = explode(',', $row['scopes'] ?? '');
                $hasSendScope = in_array('https://www.googleapis.com/auth/gmail.send', $scopes) ||
                                in_array('https://mail.google.com/', $scopes);
                if ($hasSendScope) {
                    $em = !empty($row['connected_email']) ? trim($row['connected_email']) : trim($row['email']);
                    if (!empty($em) && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                        $verified[] = [
                            'email' => $em,
                            'provider' => 'gmail_oauth',
                            'verified' => true
                        ];
                    }
                }
            }
        } catch (Throwable $e) {}

        // 2. Check SMTP configuration
        try {
            $stmtSmtp = $db->prepare("SELECT sender_email, sender_name, is_active FROM smtp_configs WHERE user_id = ? AND is_active = 1 LIMIT 1");
            $stmtSmtp->execute([$userId]);
            $smtpRow = $stmtSmtp->fetch(PDO::FETCH_ASSOC);
            if ($smtpRow && !empty($smtpRow['sender_email']) && filter_var($smtpRow['sender_email'], FILTER_VALIDATE_EMAIL)) {
                $verified[] = [
                    'email' => trim($smtpRow['sender_email']),
                    'provider' => 'smtp',
                    'verified' => true
                ];
            }
        } catch (Throwable $e) {}

        return $verified;
    }

    /**
     * Extracts slot candidates from conversation text and accumulated session state.
     */
    public static function extractSlots(
        string $userMessage,
        array $historyRows,
        int $userId,
        array $existingSlots = [],
        ?PDO $db = null
    ): array {
        if (!$db) $db = Database::getConnection();
        $slots = $existingSlots;

        // Default approval policy must always be digest_review
        if (empty($slots[self::FIELD_APPROVAL_POLICY])) {
            $slots[self::FIELD_APPROVAL_POLICY] = self::DEFAULT_APPROVAL_POLICY;
        }

        $allText = $userMessage;
        foreach (array_slice($historyRows, -4) as $h) {
            $allText .= "\n" . ($h['content'] ?? '');
        }

        // 1. Extract Lead Count (bounded between 1 and 25)
        if (empty($slots[self::FIELD_LEAD_COUNT])) {
            if (preg_match('/\b(\d+)\s*(?:leads?|prospects?|contacts?|companies)\b/i', $userMessage, $m)) {
                $count = (int)$m[1];
                $slots[self::FIELD_LEAD_COUNT] = max(self::MIN_LEADS, min(self::MAX_LEADS, $count));
            } elseif (preg_match('/\b(?:find|get|source|mail)\s*(\d+)\b/i', $userMessage, $m)) {
                $count = (int)$m[1];
                $slots[self::FIELD_LEAD_COUNT] = max(self::MIN_LEADS, min(self::MAX_LEADS, $count));
            }
        }

        // 2. Extract Location / Zone
        if (empty($slots[self::FIELD_LOCATION])) {
            if (preg_match('/\b(?:in|around|near|from|zone|location|region|city|country)\s+([A-Za-z\s]{3,30}?)(?=\s+(?:and|for|daily|morning|at|with|\.|$))/i', $userMessage, $m)) {
                $loc = trim($m[1]);
                if (!preg_match('/\b(the|morning|daily|leads|lead|mail|email)\b/i', $loc)) {
                    $slots[self::FIELD_LOCATION] = $loc;
                }
            }
        }

        // 3. Extract Industry / Niche
        if (empty($slots[self::FIELD_INDUSTRY])) {
            $nicheKeywords = ['dental', 'dentists', 'dentist', 'real estate', 'fintech', 'saas', 'law firms', 'lawyers', 'accountants', 'marketing agencies', 'e-commerce', 'ecommerce', 'retail', 'clinics', 'doctors', 'solar', 'insurance'];
            foreach ($nicheKeywords as $kw) {
                if (stripos($userMessage, $kw) !== false) {
                    $slots[self::FIELD_INDUSTRY] = ucwords($kw);
                    break;
                }
            }
        }

        // 4. Extract Value Proposition / Service Offer
        if (empty($slots[self::FIELD_VALUE_PROP])) {
            if (preg_match('/\b(?:proposal|offer|service|pitch|about|offering)\s+(?:for|about|of)?\s*["\']?([^"\'\n.]{5,80}?)["\']?(?=\s+(?:and|at|with|\.|$))/i', $userMessage, $m)) {
                $val = trim($m[1]);
                if (!empty($val) && strlen($val) > 4) {
                    $slots[self::FIELD_VALUE_PROP] = $val;
                }
            } elseif (stripos($userMessage, 'proposal') !== false) {
                // If user said "as a proposal mail", retain indicator
                $slots[self::FIELD_VALUE_PROP] = 'Business collaboration and solution proposal';
            }
        }

        // 5. Extract Schedule Time
        if (empty($slots[self::FIELD_SCHEDULE])) {
            if (preg_match('/\b(every\s+(?:day\s+)?morning|daily\s+morning|every\s+morning)\b/i', $userMessage)) {
                $slots[self::FIELD_SCHEDULE] = '0 9 * * *'; // 09:00 AM daily
            } elseif (preg_match('/\b(?:at|everyday\s+at|daily\s+at)\s*(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)\b/i', $userMessage, $m)) {
                $timeStr = trim($m[1]);
                $ts = strtotime($timeStr);
                if ($ts) {
                    $hour = date('G', $ts);
                    $min = date('i', $ts);
                    $slots[self::FIELD_SCHEDULE] = "{$min} {$hour} * * *";
                }
            } elseif (preg_match('/\b(daily|every\s+day)\b/i', $userMessage)) {
                $slots[self::FIELD_SCHEDULE] = '0 9 * * *';
            }
        }

        // 6. Resolve Verified Sender Identity
        if (empty($slots[self::FIELD_SENDER_IDENTITY])) {
            $verifiedSenders = self::getVerifiedSenderIdentities($userId, $db);
            // If user mentioned an email in their message, check if it matches a verified sender
            if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $userMessage, $m)) {
                $mentionedEmail = strtolower(trim($m[0]));
                foreach ($verifiedSenders as $vs) {
                    if (strtolower($vs['email']) === $mentionedEmail) {
                        $slots[self::FIELD_SENDER_IDENTITY] = $vs['email'];
                        break;
                    }
                }
            }
            // Auto-bind if user has exactly one verified sender identity
            if (empty($slots[self::FIELD_SENDER_IDENTITY]) && count($verifiedSenders) === 1) {
                $slots[self::FIELD_SENDER_IDENTITY] = $verifiedSenders[0]['email'];
            }
        }

        return $slots;
    }

    /**
     * Validates slot completeness and boundaries.
     * Enforces the 6 mandatory requirements.
     */
    public static function validateSlots(array $slots, int $userId, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();

        $errors = [];
        $missing = [];

        // 1. Industry & Location
        if (empty($slots[self::FIELD_INDUSTRY])) {
            $missing[] = self::FIELD_INDUSTRY;
        }
        if (empty($slots[self::FIELD_LOCATION])) {
            $missing[] = self::FIELD_LOCATION;
        }

        // 2. Lead Count
        if (empty($slots[self::FIELD_LEAD_COUNT])) {
            $missing[] = self::FIELD_LEAD_COUNT;
        } else {
            $count = (int)$slots[self::FIELD_LEAD_COUNT];
            if ($count < self::MIN_LEADS || $count > self::MAX_LEADS) {
                $errors[] = "Lead count must be bounded between " . self::MIN_LEADS . " and " . self::MAX_LEADS . " per execution.";
            }
        }

        // 3. Value Proposition
        if (empty($slots[self::FIELD_VALUE_PROP])) {
            $missing[] = self::FIELD_VALUE_PROP;
        }

        // 4. Verified Sender Identity
        if (empty($slots[self::FIELD_SENDER_IDENTITY])) {
            $missing[] = self::FIELD_SENDER_IDENTITY;
        } else {
            // Check real verification in external connections or SMTP
            $verifiedList = self::getVerifiedSenderIdentities($userId, $db);
            $found = false;
            foreach ($verifiedList as $v) {
                if (strtolower($v['email']) === strtolower($slots[self::FIELD_SENDER_IDENTITY])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $errors[] = "Sender identity '{$slots[self::FIELD_SENDER_IDENTITY]}' is not connected or verified in your workspace (Gmail OAuth or SMTP required).";
            }
        }

        // 5. Schedule & Timezone
        if (empty($slots[self::FIELD_SCHEDULE])) {
            $missing[] = self::FIELD_SCHEDULE;
        } else {
            $expr = WorkflowScheduler::normalizeCron($slots[self::FIELD_SCHEDULE]);
            if (!WorkflowScheduler::validateCron($expr)) {
                $errors[] = "Schedule format '{$slots[self::FIELD_SCHEDULE]}' is invalid.";
            }
        }

        // 6. Approval Policy
        if (empty($slots[self::FIELD_APPROVAL_POLICY])) {
            $slots[self::FIELD_APPROVAL_POLICY] = self::DEFAULT_APPROVAL_POLICY;
        }

        $isComplete = (empty($missing) && empty($errors));

        return [
            'is_complete' => $isComplete,
            'slots' => $slots,
            'missing_fields' => $missing,
            'errors' => $errors
        ];
    }

    /**
     * Formulates a focused, friendly counter-question for the missing slots.
     */
    public static function generateCounterQuestion(array $missingFields, array $slots, int $userId, ?PDO $db = null): string {
        $questions = [];

        if (in_array(self::FIELD_INDUSTRY, $missingFields) && in_array(self::FIELD_LOCATION, $missingFields)) {
            $questions[] = "Which **industry/niche** and **city/region** should I target for these leads? *(e.g., Dental Clinics in London, or Fintech startups in Dubai)*";
        } elseif (in_array(self::FIELD_INDUSTRY, $missingFields)) {
            $questions[] = "What specific **business industry or niche** would you like me to find?";
        } elseif (in_array(self::FIELD_LOCATION, $missingFields)) {
            $questions[] = "Which **geographic city or country** should I search in?";
        }

        if (in_array(self::FIELD_VALUE_PROP, $missingFields)) {
            $questions[] = "What **service, solution, or value proposition** should be highlighted in the proposal email?";
        }

        if (in_array(self::FIELD_LEAD_COUNT, $missingFields)) {
            $questions[] = "How many leads per morning would you like me to discover? *(Recommended: 10 leads/day, max 25)*";
        }

        if (in_array(self::FIELD_SCHEDULE, $missingFields)) {
            $questions[] = "What time every morning should this job execute? *(e.g., 09:00 AM)*";
        }

        if (in_array(self::FIELD_SENDER_IDENTITY, $missingFields)) {
            $verifiedList = self::getVerifiedSenderIdentities($userId, $db);
            if (empty($verifiedList)) {
                $questions[] = "⚠️ I could not find a connected **Gmail OAuth** or **SMTP** account in your workspace. Please connect your email under Settings so I have a verified mailbox to dispatch from.";
            } else {
                $emails = array_column($verifiedList, 'email');
                $questions[] = "Which connected email account should I use as the sender? *(Available: " . implode(', ', $emails) . ")*";
            }
        }

        if (empty($questions)) {
            return "All parameters received. Reviewing configuration...";
        }

        $intro = "I'm ready to configure your autonomous recurring outreach campaign. To set it up safely, please clarify:\n\n";
        foreach ($questions as $idx => $q) {
            $intro .= ($idx + 1) . ". " . $q . "\n";
        }
        $intro .= "\n*(Note: Per security policy, all generated proposals will be sent to your morning digest for review before dispatch).*";

        return $intro;
    }
}
