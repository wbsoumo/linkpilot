<?php
// backend/agent/DeliverabilityEngine.php
// Autonomous Agent Engine 2.0: Email Deliverability, RFC Syntax, Disposable Domain & DNS MX Engine.
// Strictly adheres to safe verification standards: no aggressive SMTP mailbox probing.
// Uses clear, calibrated statuses: 'invalid_syntax', 'no_mx', 'disposable', 'domain_has_mx', 'unknown'.

class DeliverabilityEngine {

    const STATUS_INVALID_SYNTAX = 'invalid_syntax';
    const STATUS_NO_MX          = 'no_mx';
    const STATUS_DISPOSABLE     = 'disposable';
    const STATUS_DOMAIN_HAS_MX  = 'domain_has_mx';
    const STATUS_UNKNOWN        = 'unknown';

    /**
     * Comprehensive registry of high-frequency temporary / burner / disposable email domains.
     */
    private static $disposableDomains = [
        'mailinator.com', 'tempmail.com', '10minutemail.com', 'guerrillamail.com',
        'throwawaymail.com', 'sharklasers.com', 'yopmail.com', 'trashmail.com',
        'dispostable.com', 'getairmail.com', 'temp-mail.org', 'fakeinbox.com',
        'maildrop.cc', 'inboxkitten.com', 'mytemp.email', 'burnermail.io',
        'nada.ltd', 'mohmal.com', 'crazymailing.com', 'armyspy.com',
        'cuvox.de', 'dayrep.com', 'einrot.com', 'fleckens.hu',
        'gustr.com', 'jourrapide.com', 'rhyta.com', 'superrito.com',
        'teleworm.us', 'binkmail.com', 'bobmail.info', 'chacuo.net',
        'devnullmail.com', 'discard.email', 'disposablemail.com', 'dodgeit.com',
        'drdrb.net', 'emailondeck.com', 'fakemailgenerator.com', 'filzmail.com',
        'generator.email', 'harakirimail.com', 'incognitomail.org', 'kasmail.com',
        'mailcatch.com', 'mailforspam.com', 'mailnesia.com', 'mailnull.com',
        'meltmail.com', 'mintemail.com', 'mytrashmail.com', 'nomail.xl.cx',
        'owlpic.com', 'pookmail.com', 'safetymail.info', 'spambog.com',
        'spamgourmet.com', 'spaml.de', 'spammotel.com', 'tempinbox.com',
        'trashmail.net', 'trashmail.org', 'trbvm.com', 'wegwerfmail.de'
    ];

    /**
     * Optional DNS MX resolver override for testing and isolated offline environments.
     * @var callable|null
     */
    private static $dnsResolver = null;

    /**
     * Sets a custom DNS resolver callback for testing/mocking.
     * Callable signature: function(string $domain): array (returns array of MX hosts or empty)
     */
    public static function setDnsResolver(?callable $resolver): void {
        self::$dnsResolver = $resolver;
    }

    /**
     * Validates an email address through syntax checking, disposable detection, and DNS MX record lookup.
     * Does NOT perform aggressive SMTP handshakes or mailbox probing.
     *
     * @param string $email The raw email address to inspect.
     * @param bool $checkDns Whether to execute DNS MX checks (defaults to true).
     * @return array Standardized verification result payload.
     */
    public static function validateEmail(string $email, bool $checkDns = true): array {
        $rawEmail = trim($email);
        $cleanEmail = strtolower($rawEmail);

        // 1. Initial basic check
        if (empty($cleanEmail)) {
            return [
                'email' => $rawEmail,
                'domain' => '',
                'status' => self::STATUS_INVALID_SYNTAX,
                'is_deliverable' => false,
                'reason' => 'Email address is blank or empty.',
                'mx_records' => []
            ];
        }

        // 2. Syntax Validation: Check for invalid characters, spaces, consecutive dots
        if (
            strpos($cleanEmail, ' ') !== false ||
            strpos($cleanEmail, '..') !== false ||
            strpos($cleanEmail, '@') === false ||
            substr_count($cleanEmail, '@') !== 1
        ) {
            return [
                'email' => $cleanEmail,
                'domain' => '',
                'status' => self::STATUS_INVALID_SYNTAX,
                'is_deliverable' => false,
                'reason' => 'Email contains spaces, consecutive dots, or invalid @ structure.',
                'mx_records' => []
            ];
        }

        $parts = explode('@', $cleanEmail);
        $localPart = $parts[0] ?? '';
        $domainPart = $parts[1] ?? '';

        if (empty($localPart) || empty($domainPart)) {
            return [
                'email' => $cleanEmail,
                'domain' => $domainPart,
                'status' => self::STATUS_INVALID_SYNTAX,
                'is_deliverable' => false,
                'reason' => 'Missing username or domain component.',
                'mx_records' => []
            ];
        }

        // Strict RFC 5322 syntax validation via filter_var
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $cleanEmail,
                'domain' => $domainPart,
                'status' => self::STATUS_INVALID_SYNTAX,
                'is_deliverable' => false,
                'reason' => 'Email failed RFC 5322 syntax validation.',
                'mx_records' => []
            ];
        }

        // Domain must contain a dot and a valid TLD of at least 2 characters
        if (strpos($domainPart, '.') === false || !preg_match('/\.[a-z]{2,}$/i', $domainPart)) {
            return [
                'email' => $cleanEmail,
                'domain' => $domainPart,
                'status' => self::STATUS_INVALID_SYNTAX,
                'is_deliverable' => false,
                'reason' => 'Domain lacks valid top-level domain extension.',
                'mx_records' => []
            ];
        }

        // 3. Disposable Email Domain Detection
        if (self::isDisposable($domainPart)) {
            return [
                'email' => $cleanEmail,
                'domain' => $domainPart,
                'status' => self::STATUS_DISPOSABLE,
                'is_deliverable' => false,
                'reason' => 'Domain is identified as a temporary / disposable email service.',
                'mx_records' => []
            ];
        }

        // 4. DNS MX Record Verification (if requested)
        if ($checkDns) {
            $mxCheck = self::checkDnsMx($domainPart);
            if ($mxCheck['has_mx']) {
                return [
                    'email' => $cleanEmail,
                    'domain' => $domainPart,
                    'status' => self::STATUS_DOMAIN_HAS_MX,
                    'is_deliverable' => true,
                    'reason' => 'Domain has valid, configured DNS MX mail exchanger records.',
                    'mx_records' => $mxCheck['hosts']
                ];
            } else {
                return [
                    'email' => $cleanEmail,
                    'domain' => $domainPart,
                    'status' => self::STATUS_NO_MX,
                    'is_deliverable' => false,
                    'reason' => 'Domain does not have active DNS MX mail exchanger records.',
                    'mx_records' => []
                ];
            }
        }

        // If DNS check was skipped
        return [
            'email' => $cleanEmail,
            'domain' => $domainPart,
            'status' => self::STATUS_UNKNOWN,
            'is_deliverable' => false,
            'reason' => 'Syntax valid but DNS verification was omitted.',
            'mx_records' => []
        ];
    }

    /**
     * Checks whether a domain belongs to a known temporary or burner service.
     */
    public static function isDisposable(string $domain): bool {
        $cleanDomain = strtolower(trim($domain));
        if (in_array($cleanDomain, self::$disposableDomains, true)) {
            return true;
        }

        // Check subdomain matching (e.g. sub.mailinator.com)
        foreach (self::$disposableDomains as $disp) {
            if (str_ends_with($cleanDomain, '.' . $disp)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolves DNS MX records for a target domain.
     * Employs custom resolver if injected; otherwise uses standard PHP checkdnsrr and dns_get_record.
     */
    public static function checkDnsMx(string $domain): array {
        $domain = strtolower(trim($domain));

        if (is_callable(self::$dnsResolver)) {
            $hosts = call_user_func(self::$dnsResolver, $domain);
            return [
                'has_mx' => !empty($hosts),
                'hosts' => is_array($hosts) ? array_values($hosts) : []
            ];
        }

        // Native DNS resolution
        $hosts = [];
        $hasMx = false;

        if (function_exists('checkdnsrr') && checkdnsrr($domain, 'MX')) {
            $hasMx = true;
            if (function_exists('dns_get_record')) {
                $records = @dns_get_record($domain, DNS_MX);
                if (is_array($records)) {
                    foreach ($records as $r) {
                        if (!empty($r['target'])) {
                            $hosts[] = $r['target'];
                        }
                    }
                }
            }
            if (empty($hosts)) {
                $hosts[] = $domain; // Fallback indicator
            }
        } elseif (function_exists('checkdnsrr') && checkdnsrr($domain, 'A')) {
            // Some legacy mail servers accept mail on the apex A record without explicit MX
            $hasMx = true;
            $hosts[] = $domain;
        }

        return [
            'has_mx' => $hasMx,
            'hosts' => $hosts
        ];
    }

    /**
     * Normalizes a company domain or website URL into a bare host (e.g., "https://www.example.com/about" -> "example.com").
     */
    public static function normalizeDomain(string $urlOrDomain): string {
        $str = trim($urlOrDomain);
        if (empty($str)) return '';

        // Add scheme if missing so parse_url functions accurately
        if (!preg_match('~^(?:f|ht)tps?://~i', $str)) {
            $str = 'http://' . $str;
        }

        $parsed = parse_url($str, PHP_URL_HOST);
        if (!$parsed) {
            $parsed = $urlOrDomain;
        }

        $host = strtolower($parsed);
        // Strip leading www.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }
}
