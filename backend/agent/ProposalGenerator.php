<?php
// backend/agent/ProposalGenerator.php
// Autonomous Agent Engine 2.0: Personalized Proposal & Compliant Outreach Drafting Engine.
// Enforces mandatory commercial compliance, clear sender identification, and one-click unsubscribe footers.

class ProposalGenerator {

    /**
     * Generates a personalized outreach proposal tailored to a qualified prospect.
     *
     * @param array $prospect Discovered prospect record (company, email, decision-maker, etc.)
     * @param string $valueProposition Value proposition / solution offered
     * @param string $senderIdentity Connected verified sender email
     * @param string $senderName Sender or business display name
     * @param array $options Additional campaign customization options
     * @return array Draft payload containing subject, HTML body, plain text body, recipient, and metadata.
     */
    public static function generateProposal(
        array $prospect,
        string $valueProposition,
        string $senderIdentity,
        string $senderName = 'LinkPilot AI Team',
        array $options = []
    ): array {
        $companyName = trim($prospect['company_name'] ?? 'Your Organization');
        $recipientName = !empty($prospect['decision_maker_name']) ? trim($prospect['decision_maker_name']) : 'Team';
        $location = trim($prospect['business_location'] ?? '');
        $recipientEmail = strtolower(trim($prospect['business_email'] ?? ''));
        $trackingId = bin2hex(random_bytes(12));
        $unsubUrl = (defined('BASE_URL') ? BASE_URL : 'https://linkpilot.work') . "/backend/api/crm/unsubscribe.php?id={$trackingId}";

        // Craft high-converting, professional subject line
        $subjectTemplates = [
            "Quick question regarding {$companyName}",
            "Collaboration opportunity for {$companyName}",
            "Enhancing operations at {$companyName} with {$valueProposition}"
        ];
        $subject = $subjectTemplates[0];
        if (!empty($options['custom_subject'])) {
            $subject = str_replace(
                ['{company}', '{lead_name}', '{service}'],
                [$companyName, $recipientName, $valueProposition],
                $options['custom_subject']
            );
        }

        // Salutation
        $greeting = "Hi {$recipientName},";
        if (empty($prospect['decision_maker_name'])) {
            $greeting = "Hello {$companyName} Team,";
        }

        // Location context mention
        $locContext = !empty($location) ? " here in {$location}" : "";

        // Plain text body
        $textBody = "{$greeting}\n\n" .
            "I noticed the impressive work {$companyName} is doing{$locContext}. I am reaching out to see if you are exploring ways to leverage {$valueProposition}.\n\n" .
            "We recently helped similar teams streamline client engagement and boost conversion efficiency without adding overhead.\n\n" .
            "Would you be open to a brief 10-minute introductory conversation this Thursday or Friday to exchange thoughts?\n\n" .
            "Best regards,\n" .
            "{$senderName}\n" .
            "{$senderIdentity}\n\n" .
            "---\n" .
            "Compliance & Preferences: You received this business inquiry because your organization is active in this sector. " .
            "If you prefer not to receive future updates, please unsubscribe here: {$unsubUrl}";

        // Professional HTML body
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; background-color: #f8fafc; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .greeting { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 16px; }
        .paragraph { font-size: 15px; margin-bottom: 16px; color: #334155; }
        .highlight { background: #f1f5f9; border-left: 3px solid #3b82f6; padding: 12px 16px; border-radius: 4px; font-weight: 500; margin: 20px 0; }
        .signature { margin-top: 28px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #475569; }
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; line-height: 1.5; }
        .footer a { color: #64748b; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="greeting">{$greeting}</div>
        <div class="paragraph">
            I noticed the impressive work <strong>{$companyName}</strong> is delivering{$locContext}. I am reaching out to see if your team is exploring ways to accelerate operations with <strong>{$valueProposition}</strong>.
        </div>
        <div class="highlight">
            We recently helped peer organizations streamline client acquisition and elevate conversion rates with automated intelligence.
        </div>
        <div class="paragraph">
            Would you be open to a brief 10-minute introductory conversation this Thursday or Friday to exchange thoughts on how this could benefit {$companyName}?
        </div>
        <div class="signature">
            <strong>{$senderName}</strong><br>
            <span>{$senderIdentity}</span>
        </div>
        <div class="footer">
            This B2B commercial outreach was sent to {$recipientEmail}. If you prefer not to receive commercial proposals from us, please <a href="{$unsubUrl}">click here to unsubscribe</a>.
        </div>
    </div>
</body>
</html>
HTML;

        return [
            'tracking_id' => $trackingId,
            'company_name' => $companyName,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'body_text' => $textBody,
            'body_html' => $htmlBody,
            'sender_email' => $senderIdentity,
            'sender_name' => $senderName,
            'value_proposition' => $valueProposition,
            'unsubscribe_url' => $unsubUrl,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}
