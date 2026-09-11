<?php
// backend/lead_scoring_engine.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_sync_helper.php';

class LeadScoringEngine {
    private static $version = 1;

    /**
     * Calculate or retrieve explainable lead score using real CRM evidence
     */
    public static function calculateLeadScore($userId, $leadId, PDO $db = null, $forceRecalculate = false) {
        if (!$db) {
            $db = Database::getConnection();
        }

        $userId = (int)$userId;
        $leadId = (int)$leadId;

        // Check cache unless forced
        if (!$forceRecalculate) {
            $stmtCache = $db->prepare("SELECT * FROM crm_lead_scores WHERE user_id = ? AND lead_id = ?");
            $stmtCache->execute([$userId, $leadId]);
            $cache = $stmtCache->fetch(PDO::FETCH_ASSOC);
            if ($cache && (time() - strtotime($cache['calculated_at'])) < 3600) {
                // Fetch signals breakdown
                $stmtSig = $db->prepare("SELECT category, signal_name, score_impact, evidence_text FROM crm_lead_score_signals WHERE user_id = ? AND lead_id = ?");
                $stmtSig->execute([$userId, $leadId]);
                $cache['signals'] = $stmtSig->fetchAll(PDO::FETCH_ASSOC);
                return $cache;
            }
        }

        // Fetch lead record
        $stmtLead = $db->prepare("SELECT * FROM crm_leads WHERE id = ? AND user_id = ?");
        $stmtLead->execute([$leadId, $userId]);
        $lead = $stmtLead->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            return null;
        }

        $contactId = $lead['contact_id'];
        $companyId = $lead['company_id'];

        // Base score starts at 50
        $score = 50;
        $signals = [];

        // 1. Pipeline Stage & Status Signals
        $stage = strtolower(trim($lead['stage'] ?? 'new'));
        if (in_array($stage, ['proposal', 'negotiation', 'qualified'])) {
            $score += 15;
            $signals[] = [
                'category' => 'intent',
                'signal_name' => 'Advanced Stage',
                'score_impact' => 15,
                'evidence_text' => "Lead is currently in '{$lead['stage']}' stage"
            ];
        } elseif ($stage === 'closed won') {
            $score = 100;
            $signals[] = [
                'category' => 'relationship',
                'signal_name' => 'Closed Won Customer',
                'score_impact' => 50,
                'evidence_text' => "Customer deal has been closed won"
            ];
        } elseif ($stage === 'closed lost') {
            $score = 10;
            $signals[] = [
                'category' => 'relationship',
                'signal_name' => 'Closed Lost',
                'score_impact' => -40,
                'evidence_text' => "Deal was marked closed lost"
            ];
        }

        // 2. Budget / Deal Value Signals
        $budget = (float)($lead['budget'] ?? 0.00);
        if ($budget >= 100000) {
            $score += 20;
            $signals[] = [
                'category' => 'deal',
                'signal_name' => 'High Budget Opportunity',
                'score_impact' => 20,
                'evidence_text' => "Lead budget is ₹" . number_format($budget, 2)
            ];
        } elseif ($budget >= 25000) {
            $score += 10;
            $signals[] = [
                'category' => 'deal',
                'signal_name' => 'Substantial Budget Opportunity',
                'score_impact' => 10,
                'evidence_text' => "Lead budget is ₹" . number_format($budget, 2)
            ];
        }

        // 3. Activity Timeline & Communication Signals
        $lastActivityAt = null;
        if ($contactId) {
            $stmtAct = $db->prepare("
                SELECT channel, direction, summary, created_at 
                FROM crm_activity_timeline 
                WHERE contact_id = ? AND user_id = ? 
                ORDER BY created_at DESC LIMIT 10
            ");
            $stmtAct->execute([$contactId, $userId]);
            $activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($activities)) {
                $lastActivityAt = $activities[0]['created_at'];

                $inboundCount = 0;
                $pricingQuestion = false;

                foreach ($activities as $act) {
                    if ($act['direction'] === 'inbound') {
                        $inboundCount++;
                    }
                    if (stripos($act['summary'], 'price') !== false || stripos($act['summary'], 'cost') !== false || stripos($act['summary'], 'proposal') !== false) {
                        $pricingQuestion = true;
                    }
                }

                if ($inboundCount > 0) {
                    $score += 15;
                    $signals[] = [
                        'category' => 'engagement',
                        'signal_name' => 'Active Inbound Communication',
                        'score_impact' => 15,
                        'evidence_text' => "Lead replied to communication ({$inboundCount} inbound messages)"
                    ];
                }

                if ($pricingQuestion) {
                    $score += 15;
                    $signals[] = [
                        'category' => 'intent',
                        'signal_name' => 'Pricing / Proposal Inquiries',
                        'score_impact' => 15,
                        'evidence_text' => "Customer explicitly asked about pricing or proposal details"
                    ];
                }
            }
        }

        // 4. Recency & Inactivity Decay
        if ($lastActivityAt) {
            $daysInactive = (time() - strtotime($lastActivityAt)) / 86400;
            if ($daysInactive <= 2) {
                $score += 10;
                $signals[] = [
                    'category' => 'recency',
                    'signal_name' => 'Recent Interaction',
                    'score_impact' => 10,
                    'evidence_text' => "Engaged within the last 48 hours"
                ];
            } elseif ($daysInactive >= 14 && $stage !== 'closed won') {
                $score -= 20;
                $signals[] = [
                    'category' => 'recency',
                    'signal_name' => 'Inactivity Decay',
                    'score_impact' => -20,
                    'evidence_text' => "No communication recorded for over 14 days"
                ];
            }
        } else {
            // New lead without activity
            $score -= 5;
            $signals[] = [
                'category' => 'recency',
                'signal_name' => 'No Recorded Activity',
                'score_impact' => -5,
                'evidence_text' => "No communication timeline history available yet"
            ];
        }

        // Clamp score between 0 and 100
        $finalScore = max(0, min(100, $score));

        // Derive Labels
        $priorityLabel = 'low';
        if ($finalScore >= 80) $priorityLabel = 'very_high';
        elseif ($finalScore >= 65) $priorityLabel = 'high';
        elseif ($finalScore >= 40) $priorityLabel = 'medium';

        $intentLevel = ($finalScore >= 70) ? 'high' : (($finalScore >= 45) ? 'medium' : 'low');
        $riskLevel = ($lastActivityAt && (time() - strtotime($lastActivityAt)) > 864000) ? 'high' : 'low';

        // Check if historical data exists to calculate statistically sound conversion probability
        $stmtTotalClosed = $db->prepare("SELECT COUNT(*) FROM crm_leads WHERE user_id = ? AND stage IN ('Closed Won', 'Closed Lost')");
        $stmtTotalClosed->execute([$userId]);
        $totalClosed = (int)$stmtTotalClosed->fetchColumn();

        $conversionLikelihood = "Heuristic High";
        if ($totalClosed >= 30) {
            $stmtWon = $db->prepare("SELECT COUNT(*) FROM crm_leads WHERE user_id = ? AND stage = 'Closed Won'");
            $stmtWon->execute([$userId]);
            $wonCount = (int)$stmtWon->fetchColumn();
            $rate = round(($wonCount / $totalClosed) * 100, 1);
            $conversionLikelihood = "{$rate}% (Based on {$totalClosed} historical outcomes)";
        } else {
            $conversionLikelihood = "Insufficient historical data for ML model";
        }

        // Generate explainable summary & recommendation
        $summaryText = "Lead score is {$finalScore}/100. Key drivers: " . implode('; ', array_map(function($s) { return $s['evidence_text']; }, array_slice($signals, 0, 3)));
        
        $recommendedAction = "Follow up with lead today.";
        if ($finalScore >= 80) {
            $recommendedAction = "Urgent: Send tailored proposal and request start date call today.";
        } elseif ($riskLevel === 'high') {
            $recommendedAction = "At Risk: Re-engage via email or call to prevent deal cooling.";
        }

        // Fetch previous score for history recording
        $stmtPrev = $db->prepare("SELECT score FROM crm_lead_scores WHERE user_id = ? AND lead_id = ?");
        $stmtPrev->execute([$userId, $leadId]);
        $prevScore = $stmtPrev->fetchColumn();

        if ($prevScore !== false && (int)$prevScore !== $finalScore) {
            $db->prepare("
                INSERT INTO crm_lead_score_history (user_id, lead_id, previous_score, new_score, reason, scoring_version)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$userId, $leadId, (int)$prevScore, $finalScore, $summaryText, self::$version]);

            // Emit automation trigger event for score threshold
            try {
                require_once __DIR__ . '/workflow_runner.php';
                WorkflowRunner::triggerEvent($userId, 'lead.score_changed', [
                    'lead_id' => $leadId,
                    'previous_score' => (int)$prevScore,
                    'new_score' => $finalScore,
                    'score_drop' => ((int)$prevScore - $finalScore)
                ], $db);
            } catch (Throwable $e) {}
        }

        // Save / Update crm_lead_scores
        $stmtSave = $db->prepare("
            INSERT INTO crm_lead_scores 
            (user_id, lead_id, score, priority_label, intent_level, risk_level, conversion_likelihood, summary_text, recommended_action, scoring_version)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                score = VALUES(score),
                priority_label = VALUES(priority_label),
                intent_level = VALUES(intent_level),
                risk_level = VALUES(risk_level),
                conversion_likelihood = VALUES(conversion_likelihood),
                summary_text = VALUES(summary_text),
                recommended_action = VALUES(recommended_action),
                calculated_at = NOW()
        ");
        $stmtSave->execute([
            $userId, $leadId, $finalScore, $priorityLabel, $intentLevel, $riskLevel, $conversionLikelihood, $summaryText, $recommendedAction, self::$version
        ]);

        // Save signals breakdown
        $db->prepare("DELETE FROM crm_lead_score_signals WHERE user_id = ? AND lead_id = ?")->execute([$userId, $leadId]);
        $stmtSigIns = $db->prepare("
            INSERT INTO crm_lead_score_signals (user_id, lead_id, category, signal_name, score_impact, evidence_text)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($signals as $sig) {
            $stmtSigIns->execute([$userId, $leadId, $sig['category'], $sig['signal_name'], $sig['score_impact'], $sig['evidence_text']]);
        }

        return [
            'lead_id' => $leadId,
            'score' => $finalScore,
            'priority_label' => $priorityLabel,
            'intent_level' => $intentLevel,
            'risk_level' => $riskLevel,
            'conversion_likelihood' => $conversionLikelihood,
            'summary_text' => $summaryText,
            'recommended_action' => $recommendedAction,
            'signals' => $signals
        ];
    }

    /**
     * Get top priority / hot leads for workspace
     */
    public static function getHotLeads($userId, PDO $db = null) {
        if (!$db) $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT l.id, l.name, l.company, l.email, l.phone, l.budget, l.stage, s.score, s.priority_label, s.intent_level, s.recommended_action, s.summary_text
            FROM crm_leads l
            LEFT JOIN crm_lead_scores s ON l.id = s.lead_id AND l.user_id = s.user_id
            WHERE l.user_id = ? AND l.stage NOT IN ('Closed Won', 'Closed Lost')
            ORDER BY COALESCE(s.score, 50) DESC, l.budget DESC
            LIMIT 15
        ");
        $stmt->execute([(int)$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
