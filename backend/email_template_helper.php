<?php
// backend/email_template_helper.php

class EmailTemplateHelper {
    
    /**
     * Get the list of all available templates
     */
    public static function getTemplates() {
        return [
            [
                'id' => 'minimalist',
                'name' => 'Minimalist Clean',
                'description' => 'Sleek, spacious layout with clean typography and a simple divider signature. Ideal for a direct, friendly approach.',
                'accent_color' => '#64748B'
            ],
            [
                'id' => 'modern_teal',
                'name' => 'Modern Teal Accent',
                'description' => 'A tech-forward design featuring a premium teal header accent and a structured glassmorphic container layout.',
                'accent_color' => '#14B8A6'
            ],
            [
                'id' => 'clean_tech',
                'name' => 'Clean Tech Slate',
                'description' => 'Sharp layout with custom left borders and slate-gray structural details. Excellent for founders and sales professionals.',
                'accent_color' => '#3B82F6'
            ],
            [
                'id' => 'warm_personal',
                'name' => 'Warm & Personal',
                'description' => 'Serif headers and warm cream accents. Simulates a handwritten-style close, perfect for recruiters and networking.',
                'accent_color' => '#F59E0B'
            ],
            [
                'id' => 'indigo_executive',
                'name' => 'Indigo Executive',
                'description' => 'Polished indigo borders and structured metadata blocks. Best suited for high-level enterprise outreach.',
                'accent_color' => '#6366F1'
            ],
            [
                'id' => 'plain_text',
                'name' => 'Simple Plain Layout',
                'description' => 'A clean layout without any wrapper container, delivering the generated outreach directly as a standard email.',
                'accent_color' => '#0F172A'
            ]
        ];
    }

    /**
     * Wrap the generated email body inside the selected HTML template layout
     */
    public static function wrap($body, $templateId, $senderDetails = []) {
        $name = htmlspecialchars($senderDetails['name'] ?? 'LinkPilot User');
        $title = htmlspecialchars($senderDetails['title'] ?? '');
        $company = htmlspecialchars($senderDetails['company'] ?? '');
        $email = htmlspecialchars($senderDetails['email'] ?? '');
        $linkedin = htmlspecialchars($senderDetails['linkedin'] ?? '');

        // Prepare signature block
        $signature = "<p style='margin: 0; font-size: 14px; font-weight: bold;'>{$name}</p>";
        if (!empty($title)) {
            $sigDetails = $title;
            if (!empty($company)) {
                $sigDetails .= " at {$company}";
            }
            $signature .= "<p style='margin: 2px 0 0 0; font-size: 12px; opacity: 0.8;'>{$sigDetails}</p>";
        }
        if (!empty($linkedin)) {
            $signature .= "<p style='margin: 6px 0 0 0; font-size: 12px;'><a href='{$linkedin}' target='_blank' style='color: #14B8A6; text-decoration: none; font-weight: 500;'>Connect on LinkedIn</a></p>";
        }

        // If $body is already a rich styled HTML template, return directly
        if (strpos($body, 'max-width:') !== false || strpos($body, '<table') !== false || strpos($body, '<div style=') !== false || strpos($body, 'OUTREACH ENGINE') !== false) {
            return $body;
        }

        // Clean body to prevent double styling and make linebreaks look good
        $cleanedBody = $body;

        switch ($templateId) {
            case 'modern_teal':
                return self::getModernTealTemplate($cleanedBody, $signature);
            case 'clean_tech':
                return self::getCleanTechTemplate($cleanedBody, $signature);
            case 'warm_personal':
                return self::getWarmPersonalTemplate($cleanedBody, $signature);
            case 'indigo_executive':
                return self::getIndigoExecutiveTemplate($cleanedBody, $signature);
            case 'plain_text':
                return "<div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #334155; max-width: 600px; margin: 0 auto;'>" . $cleanedBody . "<br><br><br>" . $signature . "</div>";
            case 'minimalist':
            default:
                return self::getMinimalistTemplate($cleanedBody, $signature);
        }
    }

    private static function getMinimalistTemplate($body, $signature) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;
                    background-color: #F8FAFC;
                    margin: 0;
                    padding: 20px;
                }
                .wrapper {
                    max-width: 580px;
                    margin: 0 auto;
                    background-color: #FFFFFF;
                    border: 1px solid #E2E8F0;
                    border-radius: 8px;
                    padding: 30px;
                }
                .content {
                    font-size: 15px;
                    line-height: 1.6;
                    color: #334155;
                }
                .divider {
                    margin: 25px 0;
                    border-top: 1px solid #E2E8F0;
                }
                .footer {
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class='wrapper'>
                <div class='content'>
                    {$body}
                </div>
                <div class='divider'></div>
                <div class='footer'>
                    {$signature}
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function getModernTealTemplate($body, $signature) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;
                    background-color: #F1F5F9;
                    margin: 0;
                    padding: 30px 20px;
                }
                .wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #FFFFFF;
                    border-top: 4px solid #14B8A6;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
                    overflow: hidden;
                }
                .inner-container {
                    padding: 32px 40px;
                }
                .content {
                    font-size: 15px;
                    line-height: 1.65;
                    color: #1E293B;
                }
                .divider {
                    height: 1px;
                    background: linear-gradient(to right, #E2E8F0, #F1F5F9);
                    margin: 30px 0;
                }
                .footer {
                    background-color: #FAFAFA;
                    padding: 20px 40px;
                    border-top: 1px solid #F3F4F6;
                }
            </style>
        </head>
        <body>
            <div class='wrapper'>
                <div class='inner-container'>
                    <div class='content'>
                        {$body}
                    </div>
                    <div class='divider'></div>
                    <div class='signature-block'>
                        {$signature}
                    </div>
                </div>
                <div class='footer'>
                    <p style='margin: 0; font-size: 11px; color: #94A3B8; text-align: center;'>Sent securely via LinkPilot AI Outreach Engine</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function getCleanTechTemplate($body, $signature) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;
                    background-color: #F8FAFC;
                    margin: 0;
                    padding: 20px;
                }
                .wrapper {
                    max-width: 580px;
                    margin: 0 auto;
                    background-color: #FFFFFF;
                    border: 1px solid #E2E8F0;
                    border-radius: 10px;
                    padding: 35px;
                }
                .accent-bar {
                    width: 32px;
                    height: 3px;
                    background-color: #3B82F6;
                    margin-bottom: 25px;
                }
                .content {
                    font-size: 15px;
                    line-height: 1.6;
                    color: #334155;
                    border-left: 2px solid #F1F5F9;
                    padding-left: 15px;
                }
                .footer {
                    margin-top: 35px;
                    padding-top: 20px;
                    border-top: 1px solid #F1F5F9;
                }
            </style>
        </head>
        <body>
            <div class='wrapper'>
                <div class='accent-bar'></div>
                <div class='content'>
                    {$body}
                </div>
                <div class='footer'>
                    {$signature}
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function getWarmPersonalTemplate($body, $signature) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: \"Georgia\", serif;
                    background-color: #FAFaf7;
                    margin: 0;
                    padding: 30px 20px;
                }
                .wrapper {
                    max-width: 550px;
                    margin: 0 auto;
                    background-color: #FFFFFF;
                    border: 1px solid #E6E6DF;
                    border-radius: 4px;
                    padding: 40px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
                }
                .content {
                    font-size: 16px;
                    line-height: 1.7;
                    color: #2D3748;
                }
                .divider {
                    margin: 30px 0;
                    border-top: 1px dashed #E6E6DF;
                }
                .footer {
                    font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;
                }
            </style>
        </head>
        <body>
            <div class='wrapper'>
                <div class='content'>
                    {$body}
                </div>
                <div class='divider'></div>
                <div class='footer'>
                    {$signature}
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function getIndigoExecutiveTemplate($body, $signature) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif;
                    background-color: #FAFBFD;
                    margin: 0;
                    padding: 25px;
                }
                .wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #FFFFFF;
                    border: 1px solid #E0E7FF;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.03);
                }
                .header {
                    background-color: #6366F1;
                    height: 6px;
                }
                .body-container {
                    padding: 35px 40px;
                }
                .content {
                    font-size: 15px;
                    line-height: 1.6;
                    color: #1E293B;
                }
                .divider {
                    height: 1px;
                    background-color: #E2E8F0;
                    margin: 30px 0;
                }
                .footer {
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div class='wrapper'>
                <div class='header'></div>
                <div class='body-container'>
                    <div class='content'>
                        {$body}
                    </div>
                    <div class='divider'></div>
                    <div class='footer'>
                        {$signature}
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
