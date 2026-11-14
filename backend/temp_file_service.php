<?php
// backend/temp_file_service.php
// LinkPilot AI: Dedicated Temporary AI-Generated File Storage & Delivery Engine.
// Supports native generation of CSV, Excel (.xlsx), Word (.docx), PDF (.pdf), TXT, and JSON.
// Enforces 30-minute hard expiry, cryptographically secure single-use/time-limited tokens,
// directory traversal defense, and automatic server-side cleanup.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/agent_db_migration.php';

class TempFileService {

    const EXPIRY_SECONDS = 1800; // 30 minutes exactly
    const STORAGE_SUBDIR = '/storage/temp_generated_files';

    /**
     * Resolves the absolute path to the dedicated temporary file storage directory.
     */
    public static function getStorageDir(): string {
        $dir = dirname(__DIR__) . self::STORAGE_SUBDIR;
        self::ensureSecureDirectory($dir);
        return $dir;
    }

    /**
     * Creates storage directory and locks it down with .htaccess & index.html
     * to prevent direct public URL access or directory traversal.
     */
    public static function ensureSecureDirectory(string $dir): void {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // 1. Apache / Litespeed .htaccess block
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "# LinkPilot AI Temporary Storage Protection\n" .
                     "# Deny direct public HTTP/HTTPS access to all files\n" .
                     "<IfModule mod_authz_core.c>\n" .
                     "    Require all denied\n" .
                     "</IfModule>\n" .
                     "<IfModule !mod_authz_core.c>\n" .
                     "    Order deny,allow\n" .
                     "    Deny from all\n" .
                     "</IfModule>\n";
            @file_put_contents($htaccess, $rules);
        }

        // 2. Empty index.html to block directory listings if .htaccess bypassed
        $indexHtml = $dir . '/index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory access is forbidden.</h1></body></html>");
        }
    }

    /**
     * Main entrypoint: Generates a temporary file natively in PHP and registers it in the DB.
     *
     * @param int $userId
     * @param string|null $convId
     * @param string $format 'csv', 'xlsx', 'pdf', 'docx', 'txt', 'json'
     * @param string $requestedFilename e.g. "leads_export.xlsx"
     * @param string $title Document title
     * @param array $dataRows Structured row objects/arrays for tables
     * @param string $textContent Text/markdown body
     * @param string $description Brief description
     * @param PDO|null $db
     * @return array File metadata with download_url and 30-minute expiry
     */
    public static function createTempFile(
        int $userId,
        ?string $convId,
        string $format,
        string $requestedFilename,
        string $title,
        array $dataRows = [],
        string $textContent = '',
        string $description = '',
        ?PDO $db = null
    ): array {
        if (!$db) $db = Database::getConnection();
        AgentDBMigration::run($db);

        $storageDir = self::getStorageDir();
        $format = strtolower(trim($format));
        $allowedFormats = ['csv', 'xlsx', 'pdf', 'docx', 'txt', 'json'];
        if (!in_array($format, $allowedFormats)) {
            $format = 'csv';
        }

        // 1. Sanitize user-facing filename
        $safeFilename = self::sanitizeFilename($requestedFilename, $format);
        if (empty($safeFilename)) {
            $safeFilename = 'LinkPilot_' . ucfirst($format) . '_' . date('Ymd_His') . '.' . $format;
        }

        // Auto-populate data from CRM if dataRows is empty and filename/title relates to contacts or deals
        if (empty($dataRows)) {
            $lowerCheck = strtolower($safeFilename . ' ' . $title);
            if (str_contains($lowerCheck, 'contact') || str_contains($lowerCheck, 'lead')) {
                require_once __DIR__ . '/crm_sync_helper.php';
                $rawContacts = CRMSyncHelper::searchContacts($userId, [], 200, 0, $db);
                foreach ($rawContacts as $c) {
                    $dataRows[] = [
                        'ID' => $c['id'] ?? '',
                        'Name' => $c['name'] ?? '',
                        'Email' => $c['email'] ?? '',
                        'Phone' => $c['phone'] ?? '',
                        'Company' => $c['company_name'] ?? '',
                        'Designation' => $c['designation'] ?? '',
                        'Tags' => $c['tags'] ?? '',
                        'Created' => substr($c['created_at'] ?? '', 0, 10)
                    ];
                }
            } elseif (str_contains($lowerCheck, 'deal') || str_contains($lowerCheck, 'pipeline')) {
                $stmt = $db->prepare("SELECT d.id, d.title, d.deal_value, d.currency, d.stage, d.closing_date, co.name AS company, c.name AS contact FROM crm_deals d LEFT JOIN crm_companies co ON d.company_id = co.id LEFT JOIN crm_contacts c ON d.contact_id = c.id WHERE d.user_id = ? ORDER BY d.closing_date ASC LIMIT 200");
                $stmt->execute([$userId]);
                $rawDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rawDeals as $d) {
                    $dataRows[] = [
                        'Deal ID' => $d['id'] ?? '',
                        'Title' => $d['title'] ?? '',
                        'Value' => ($d['currency'] ?? '$') . ' ' . number_format((float)($d['deal_value'] ?? 0), 2),
                        'Stage' => $d['stage'] ?? '',
                        'Company' => $d['company'] ?? '',
                        'Contact' => $d['contact'] ?? '',
                        'Closing Date' => $d['closing_date'] ?? ''
                    ];
                }
            }
        }

        // 2. Generate random non-guessable disk filename (24 bytes random hex + timestamp)
        $storedFilename = 'lp_' . bin2hex(random_bytes(20)) . '_' . time() . '.' . $format;
        $fullPath = $storageDir . '/' . $storedFilename;

        // 3. Generate file content natively based on format
        switch ($format) {
            case 'csv':
                self::generateCsv($fullPath, $dataRows, $textContent, $title);
                $mimeType = 'text/csv; charset=UTF-8';
                break;
            case 'xlsx':
                self::generateXlsx($fullPath, $title, $dataRows, $description);
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'docx':
                self::generateDocx($fullPath, $title, $description, $dataRows, $textContent);
                $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                break;
            case 'pdf':
                self::generatePdf($fullPath, $title, $description, $dataRows, $textContent);
                $mimeType = 'application/pdf';
                break;
            case 'json':
                self::generateJson($fullPath, $dataRows, $textContent, $title);
                $mimeType = 'application/json';
                break;
            case 'txt':
            default:
                self::generateTxt($fullPath, $title, $description, $dataRows, $textContent);
                $mimeType = 'text/plain; charset=UTF-8';
                break;
        }

        if (!file_exists($fullPath) || filesize($fullPath) === 0) {
            throw new Exception("File generation failed for format '{$format}'.");
        }

        $fileSizeBytes = filesize($fullPath);
        $fileSizeFormatted = self::formatBytes($fileSizeBytes);

        // 4. Generate 64-character cryptographically secure token
        $fileToken = bin2hex(random_bytes(32));

        $createdAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::EXPIRY_SECONDS);

        // 5. Record in database
        $stmt = $db->prepare("
            INSERT INTO ai_generated_files (
                file_token, user_id, conversation_id, original_filename, stored_filename,
                file_format, mime_type, file_size_bytes, download_count, created_at, expires_at, is_deleted
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0)
        ");
        $stmt->execute([
            $fileToken,
            $userId,
            $convId,
            $safeFilename,
            $storedFilename,
            $format,
            $mimeType,
            $fileSizeBytes,
            $createdAt,
            $expiresAt
        ]);

        // 6. Construct secure signed and encoded download URL
        $signedToken = self::encodeSignedToken($fileToken, $userId, strtotime($expiresAt));
        $baseUrl = self::resolveBaseUrl();
        $downloadUrl = $baseUrl . '/backend/api/crm/download_temp_file.php?token=' . urlencode($signedToken);

        // 7. Opportunistic cleanup of any expired files
        try {
            self::cleanupExpired($db);
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'token' => $signedToken,
            'raw_token' => $fileToken,
            'filename' => $safeFilename,
            'format' => strtoupper($format),
            'mime_type' => $mimeType,
            'size_bytes' => $fileSizeBytes,
            'size_formatted' => $fileSizeFormatted,
            'download_url' => $downloadUrl,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
            'valid_minutes' => 30,
            'notice' => 'This download link is valid for 30 minutes. The file will be automatically deleted after expiry.'
        ];
    }

    /**
     * Encodes a cryptographically signed, URL-safe base64 token.
     * Prevents URL guessing, tampering, or unauthorized user manipulation.
     */
    public static function encodeSignedToken(string $rawToken, int $userId, int $expiresTimestamp): string {
        $secret = defined('JWT_SECRET') ? JWT_SECRET : 'linkpilot_super_secret_key_2026_jwt_token_auth';
        $payload = json_encode([
            't' => $rawToken,
            'u' => $userId,
            'x' => $expiresTimestamp
        ]);
        $sig = hash_hmac('sha256', $payload, $secret);
        return rtrim(strtr(base64_encode(json_encode([
            'p' => $payload,
            's' => substr($sig, 0, 32)
        ])), '+/', '-_'), '=');
    }

    /**
     * Decodes and verifies signed token or accepts raw 64-hex token.
     */
    public static function decodeSignedToken(string $inputToken): ?array {
        $input = trim($inputToken);
        if (empty($input)) return null;

        $json = base64_decode(strtr($input, '-_', '+/'));
        if (!$json) {
            // Direct raw 64-hex token fallback
            if (preg_match('/^[a-f0-9]{64}$/', $input)) {
                return ['raw_token' => $input, 'user_id' => null, 'expires' => null, 'expired' => false];
            }
            return null;
        }

        $envelope = json_decode($json, true);
        if (!$envelope || empty($envelope['p']) || empty($envelope['s'])) {
            if (preg_match('/^[a-f0-9]{64}$/', $input)) {
                return ['raw_token' => $input, 'user_id' => null, 'expires' => null, 'expired' => false];
            }
            return null;
        }

        $secret = defined('JWT_SECRET') ? JWT_SECRET : 'linkpilot_super_secret_key_2026_jwt_token_auth';
        $expectedSig = substr(hash_hmac('sha256', $envelope['p'], $secret), 0, 32);
        if (!hash_equals($expectedSig, $envelope['s'])) {
            return null; // Tampered token signature!
        }

        $data = json_decode($envelope['p'], true);
        if (!$data || empty($data['t']) || !preg_match('/^[a-f0-9]{64}$/', $data['t'])) {
            return null;
        }

        $isExpired = !empty($data['x']) && time() > (int)$data['x'];

        return [
            'raw_token' => $data['t'],
            'user_id' => !empty($data['u']) ? (int)$data['u'] : null,
            'expires' => !empty($data['x']) ? (int)$data['x'] : null,
            'expired' => $isExpired
        ];
    }

    /**
     * Resolves the secure download record and validates expiry & path traversal.
     */
    public static function getDownloadFile(string $token, ?int $currentUserId = null, ?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();

        // 1. Decode signed token or validate raw 64-hex
        $decoded = self::decodeSignedToken($token);
        if (!$decoded || empty($decoded['raw_token'])) {
            return ['error' => 'invalid_token', 'message' => 'Invalid or tampered download token format.'];
        }

        if (!empty($decoded['expired'])) {
            return [
                'error' => 'expired',
                'filename' => 'Generated File',
                'expires_at' => date('Y-m-d H:i:s', $decoded['expires'] ?? time()),
                'message' => 'This download link has expired. The file was automatically deleted after 30 minutes.'
            ];
        }

        $rawToken = $decoded['raw_token'];
        $stmt = $db->prepare("SELECT * FROM ai_generated_files WHERE file_token = ? LIMIT 1");
        $stmt->execute([$rawToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['is_deleted'] === 1) {
            return ['error' => 'not_found', 'message' => 'File not found or link has already been removed.'];
        }

        // 2. User ownership authorization check: users cannot access other users' files!
        if (!empty($decoded['user_id']) && (int)$row['user_id'] !== (int)$decoded['user_id']) {
            return ['error' => 'forbidden', 'message' => 'Access Denied: You do not have permission to access files generated by another user.'];
        }
        if ($currentUserId !== null && (int)$row['user_id'] !== (int)$currentUserId) {
            return ['error' => 'forbidden', 'message' => 'Access Denied: You do not have permission to access files generated by another user.'];
        }

        // Expiry verification: 30 minutes strictly enforced
        $expiresTimestamp = strtotime($row['expires_at']);
        if (time() >= $expiresTimestamp) {
            // Delete disk file immediately and mark deleted
            $storageDir = self::getStorageDir();
            $expiredDiskPath = $storageDir . '/' . $row['stored_filename'];
            if (file_exists($expiredDiskPath)) {
                @unlink($expiredDiskPath);
            }
            $db->prepare("UPDATE ai_generated_files SET is_deleted = 1, deleted_at = NOW() WHERE id = ?")->execute([$row['id']]);

            return [
                'error' => 'expired',
                'filename' => $row['original_filename'],
                'expires_at' => $row['expires_at'],
                'message' => 'This download link has expired. The file was automatically deleted after 30 minutes.'
            ];
        }

        // Verify physical file exists and stays inside storage directory (anti-traversal check)
        $storageDir = self::getStorageDir();
        $realBase = realpath($storageDir);
        $targetFile = $storageDir . '/' . $row['stored_filename'];
        $realTarget = realpath($targetFile);

        if (!$realTarget || !file_exists($realTarget) || !str_starts_with($realTarget, $realBase)) {
            return ['error' => 'file_missing', 'message' => 'The requested file could not be located on storage.'];
        }

        // Increment download counter
        try {
            $db->prepare("UPDATE ai_generated_files SET download_count = download_count + 1 WHERE id = ?")->execute([$row['id']]);
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'path' => $realTarget,
            'filename' => $row['original_filename'],
            'mime_type' => $row['mime_type'],
            'size' => (int)$row['file_size_bytes'],
            'format' => $row['file_format']
        ];
    }

    /**
     * Server-side cleanup engine: Deletes all expired files from disk and marks DB records as deleted.
     * Also sweeps orphaned files older than 30 minutes.
     */
    public static function cleanupExpired(?PDO $db = null): array {
        if (!$db) $db = Database::getConnection();
        $storageDir = self::getStorageDir();
        $now = date('Y-m-d H:i:s');

        // 1. Find all expired DB records not yet deleted
        $stmt = $db->prepare("SELECT id, stored_filename FROM ai_generated_files WHERE (expires_at <= ? OR created_at <= DATE_SUB(?, INTERVAL 30 MINUTE)) AND is_deleted = 0 LIMIT 200");
        $stmt->execute([$now, $now]);
        $expiredRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dbDeletedCount = 0;
        foreach ($expiredRows as $r) {
            $filePath = $storageDir . '/' . $r['stored_filename'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $upd = $db->prepare("UPDATE ai_generated_files SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
            $upd->execute([$r['id']]);
            $dbDeletedCount++;
        }

        // 2. Sweep directory for any orphaned files older than 30 minutes (safety net)
        $diskOrphansCount = 0;
        if (is_dir($storageDir)) {
            $files = scandir($storageDir);
            $cutoff = time() - self::EXPIRY_SECONDS;
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html') {
                    continue;
                }
                $fp = $storageDir . '/' . $f;
                if (is_file($fp) && filemtime($fp) <= $cutoff) {
                    if (@unlink($fp)) {
                        $diskOrphansCount++;
                    }
                }
            }
        }

        return [
            'db_records_expired' => $dbDeletedCount,
            'orphans_purged' => $diskOrphansCount,
            'timestamp' => $now
        ];
    }

    // =========================================================================
    // NATIVE FILE GENERATORS (Zero external API dependencies)
    // =========================================================================

    /**
     * 1. CSV Generator: UTF-8 BOM, escaped columns, headers & data rows.
     */
    private static function generateCsv(string $path, array $dataRows, string $textContent, string $title): void {
        $fp = fopen($path, 'w');
        if (!$fp) throw new Exception("Cannot open file for writing at {$path}");

        // Prepend UTF-8 BOM so Microsoft Excel correctly displays UTF-8 characters and currency symbols
        fwrite($fp, "\xEF\xBB\xBF");

        $rows = self::normalizeRows($dataRows, $textContent, $title);
        if (!empty($rows)) {
            // Header
            $headers = array_keys($rows[0]);
            fputcsv($fp, $headers);
            // Rows
            foreach ($rows as $r) {
                $line = [];
                foreach ($headers as $h) {
                    $val = $r[$h] ?? '';
                    if (is_array($val)) $val = json_encode($val);
                    $line[] = (string)$val;
                }
                fputcsv($fp, $line);
            }
        } else {
            fputcsv($fp, ['Title', 'Generated At', 'Content']);
            fputcsv($fp, [$title, date('Y-m-d H:i:s'), $textContent]);
        }
        fclose($fp);
    }

    /**
     * 2. Excel OpenXML (.xlsx) Generator: Pure PHP native ZipArchive.
     * Generates true spreadsheet with styled headers (dark blue fill, bold white font) and borders.
     */
    private static function generateXlsx(string $path, string $title, array $dataRows, string $description): void {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("ZipArchive creation failed for XLSX at {$path}");
        }

        $rows = self::normalizeRows($dataRows, $description, $title);
        $headers = !empty($rows) ? array_keys($rows[0]) : ['Document', 'Created At', 'Details'];
        if (empty($rows)) {
            $rows = [['Document' => $title, 'Created At' => date('Y-m-d H:i:s'), 'Details' => $description]];
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Report Data" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        // Styles: 0=Data, 1=Header (Dark Slate #1E293B, Bold White font, Borders, Centered)
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>');

        // Sheet1 Data
        $colLetter = function($idx) {
            $letter = '';
            while ($idx >= 0) {
                $letter = chr($idx % 26 + 65) . $letter;
                $idx = intval($idx / 26) - 1;
            }
            return $letter;
        };

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>';

        $rowIdx = 1;
        // Header Row
        $sheetXml .= '<row r="' . $rowIdx . '" ht="26" customHeight="1">';
        foreach ($headers as $cIdx => $h) {
            $ref = $colLetter($cIdx) . $rowIdx;
            $sheetXml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</t></is></c>';
        }
        $sheetXml .= '</row>';

        // Data Rows
        foreach ($rows as $r) {
            $rowIdx++;
            $sheetXml .= '<row r="' . $rowIdx . '" ht="20" customHeight="1">';
            $cIdx = 0;
            foreach ($headers as $colKey) {
                $val = is_array($r) ? ($r[$colKey] ?? ($r[$cIdx] ?? '')) : '';
                if (is_array($val)) $val = json_encode($val);
                $ref = $colLetter($cIdx) . $rowIdx;
                $sheetXml .= '<c r="' . $ref . '" t="inlineStr" s="0"><is><t>' . htmlspecialchars((string)$val, ENT_XML1, 'UTF-8') . '</t></is></c>';
                $cIdx++;
            }
            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
    }

    /**
     * 3. Word Document OpenXML (.docx) Generator: Pure PHP native ZipArchive.
     * Includes LinkPilot document title banner, subtitle, description, and styled table.
     */
    private static function generateDocx(string $path, string $title, string $description, array $dataRows, string $textContent): void {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("ZipArchive creation failed for DOCX at {$path}");
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

        $esc = function($t) { return htmlspecialchars((string)$t, ENT_XML1, 'UTF-8'); };

        $rows = self::normalizeRows($dataRows, $textContent, $title);
        $headers = !empty($rows) ? array_keys($rows[0]) : [];

        $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <!-- Title -->
    <w:p>
      <w:pPr><w:spacing w:after="160"/></w:pPr>
      <w:r>
        <w:rPr>
          <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>
          <w:b/>
          <w:sz w:val="48"/>
          <w:color w:val="1E293B"/>
        </w:rPr>
        <w:t>' . $esc($title) . '</w:t>
      </w:r>
    </w:p>

    <!-- Subtitle / Meta -->
    <w:p>
      <w:pPr><w:spacing w:after="240"/></w:pPr>
      <w:r>
        <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="64748B"/><w:sz w:val="20"/></w:rPr>
        <w:t>Generated by LinkPilot AI Assistant • ' . date('F j, Y, g:i A') . '</w:t>
      </w:r>
    </w:p>';

        $bodyText = !empty($description) ? $description : $textContent;
        if (!empty($bodyText)) {
            $paragraphs = explode("\n", str_replace(["\r\n", "\r"], "\n", $bodyText));
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if (empty($p)) continue;
                $docXml .= '
    <w:p>
      <w:pPr><w:spacing w:after="180"/></w:pPr>
      <w:r>
        <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:color w:val="334155"/></w:rPr>
        <w:t>' . $esc($p) . '</w:t>
      </w:r>
    </w:p>';
            }
        }

        // Table
        if (!empty($headers) && !empty($rows)) {
            $docXml .= '
    <w:tbl>
      <w:tblPr>
        <w:tblW w:w="5000" w:type="pct"/>
        <w:tblBorders>
          <w:top w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
          <w:left w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
          <w:bottom w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
          <w:right w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
          <w:insideH w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
          <w:insideV w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
        </w:tblBorders>
      </w:tblPr>';

            // Table Header
            $docXml .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
            foreach ($headers as $h) {
                $docXml .= '<w:tc>
                  <w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="1E293B"/><w:tcMar><w:top w:w="120"/><w:bottom w:w="120"/><w:left w:w="120"/><w:right w:w="120"/></w:tcMar></w:tcPr>
                  <w:p><w:r><w:rPr><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="20"/></w:rPr><w:t>' . $esc($h) . '</w:t></w:r></w:p>
                </w:tc>';
            }
            $docXml .= '</w:tr>';

            // Table Rows
            foreach ($rows as $r) {
                $docXml .= '<w:tr>';
                foreach ($headers as $idx => $h) {
                    $v = is_array($r) ? ($r[$h] ?? ($r[$idx] ?? '')) : '';
                    if (is_array($v)) $v = json_encode($v);
                    $docXml .= '<w:tc>
                      <w:tcPr><w:tcMar><w:top w:w="100"/><w:bottom w:w="100"/><w:left w:w="120"/><w:right w:w="120"/></w:tcMar></w:tcPr>
                      <w:p><w:r><w:rPr><w:color w:val="334155"/><w:sz w:val="19"/></w:rPr><w:t>' . $esc($v) . '</w:t></w:r></w:p>
                    </w:tc>';
                }
                $docXml .= '</w:tr>';
            }
            $docXml .= '</w:tbl>';
        }

        $docXml .= '
    <w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
    </w:sectPr>
  </w:body>
</w:document>';

        $zip->addFromString('word/document.xml', $docXml);
        $zip->close();
    }

    /**
     * 4. Pure-PHP Native PDF (%PDF-1.4) Generator.
     * Features LinkPilot header banner, Title, Timestamp, Description text, Table with dark header & zebra rows, page numbers.
     */
    private static function generatePdf(string $path, string $title, string $description, array $dataRows, string $textContent): void {
        $pdf = new MicroPdfWriter();
        $rows = self::normalizeRows($dataRows, $textContent, $title);
        $headers = !empty($rows) ? array_keys($rows[0]) : [];
        $bodyText = !empty($description) ? $description : $textContent;

        $pdf->writeReport($title, $bodyText, $headers, $rows);
        $pdf->save($path);
    }

    /**
     * 5. Plain Text (.txt) Report Generator.
     */
    private static function generateTxt(string $path, string $title, string $description, array $dataRows, string $textContent): void {
        $content = "================================================================================\n";
        $content .= " LINKPILOT AI - " . strtoupper($title) . "\n";
        $content .= " Generated: " . date('F j, Y, g:i A T') . "\n";
        $content .= " Confidential Workspace Document\n";
        $content .= "================================================================================\n\n";

        $desc = !empty($description) ? $description : $textContent;
        if (!empty($desc)) {
            $content .= wordwrap($desc, 80) . "\n\n";
        }

        $rows = self::normalizeRows($dataRows, $textContent, $title);
        if (!empty($rows)) {
            $headers = array_keys($rows[0]);
            // Calculate column widths
            $widths = [];
            foreach ($headers as $h) {
                $widths[$h] = mb_strlen($h);
            }
            foreach ($rows as $r) {
                foreach ($headers as $h) {
                    $val = is_array($r) ? ($r[$h] ?? '') : '';
                    if (is_array($val)) $val = json_encode($val);
                    $widths[$h] = min(40, max($widths[$h], mb_strlen((string)$val)));
                }
            }

            // Header line
            $hdrLine = "";
            $sepLine = "";
            foreach ($headers as $h) {
                $hdrLine .= str_pad(mb_substr($h, 0, $widths[$h]), $widths[$h] + 3);
                $sepLine .= str_repeat('-', $widths[$h]) . "   ";
            }
            $content .= $hdrLine . "\n" . $sepLine . "\n";

            // Data rows
            foreach ($rows as $r) {
                $rowLine = "";
                foreach ($headers as $h) {
                    $val = is_array($r) ? ($r[$h] ?? '') : '';
                    if (is_array($val)) $val = json_encode($val);
                    $rowLine .= str_pad(mb_substr((string)$val, 0, $widths[$h]), $widths[$h] + 3);
                }
                $content .= $rowLine . "\n";
            }
        }

        $content .= "\n---\nEnd of File. LinkPilot AI Assistant.\n";
        file_put_contents($path, $content);
    }

    /**
     * 6. JSON Export Generator.
     */
    private static function generateJson(string $path, array $dataRows, string $textContent, string $title): void {
        $rows = self::normalizeRows($dataRows, $textContent, $title);
        $payload = [
            'meta' => [
                'title' => $title,
                'generated_by' => 'LinkPilot AI',
                'created_at' => date('c'),
                'total_records' => count($rows)
            ],
            'records' => $rows
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Normalizes unstructured or structured inputs into a clean array of row objects.
     */
    private static function normalizeRows(array $dataRows, string $textContent, string $title): array {
        if (!empty($dataRows) && is_array($dataRows)) {
            // Check if associative single object: [{k:v}, {k:v}] vs {k:v}
            $isAssoc = array_keys($dataRows) !== range(0, count($dataRows) - 1);
            if ($isAssoc) {
                return [$dataRows];
            }
            // Ensure elements are objects/assoc arrays
            $cleaned = [];
            foreach ($dataRows as $item) {
                if (is_array($item)) {
                    $cleaned[] = $item;
                } elseif (is_object($item)) {
                    $cleaned[] = (array)$item;
                } else {
                    $cleaned[] = ['Item' => (string)$item];
                }
            }
            return $cleaned;
        }

        // Try parsing JSON if in textContent
        if (!empty($textContent) && (str_starts_with(trim($textContent), '[') || str_starts_with(trim($textContent), '{'))) {
            $decoded = json_decode(trim($textContent), true);
            if (is_array($decoded)) {
                return self::normalizeRows($decoded, '', $title);
            }
        }

        return [];
    }

    /**
     * Sanitizes requested filename, prevents directory traversal, and enforces correct extension.
     */
    public static function sanitizeFilename(string $filename, string $format): string {
        $base = basename($filename);
        $base = preg_replace('/[^\w\s\.-]/u', '_', $base);
        $base = preg_replace('/\s+/', '_', trim($base));
        $ext = '.' . strtolower($format);

        if (!str_ends_with(strtolower($base), $ext)) {
            $base .= $ext;
        }
        return $base;
    }

    /**
     * Formats bytes into human-readable representation.
     */
    public static function formatBytes(int $bytes): string {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Resolves current server base URL.
     */
    private static function resolveBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'linkpilot.work';
        return $protocol . $host;
    }
}

/**
 * Lightweight, zero-dependency %PDF-1.4 Writer.
 * Self-contained pure-PHP class that outputs standard PDF with headers, tables, and pagination.
 */
class MicroPdfWriter {
    private array $pages = [];
    private int $currentPage = 0;
    private float $pageWidth = 595.28;
    private float $pageHeight = 841.89;
    private float $y = 800;

    public function __construct() {
        $this->addPage();
    }

    public function addPage(): void {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
        $this->y = 740;

        // Top branded header
        $this->rect(0, 785, 595.28, 56.89, 'F', [0.08, 0.12, 0.20]);
        $this->text(40, 808, "LinkPilot AI", 16, true, [1, 1, 1]);
        $this->text(40, 794, "AUTONOMOUS BUSINESS ASSISTANT & INTELLIGENCE LAYER", 8, false, [0.6, 0.7, 0.85]);

        // Bottom footer
        $this->line(40, 45, 555, 45, [0.85, 0.88, 0.92]);
        $this->text(40, 32, "Confidential Workspace Export • Generated by LinkPilot AI • " . date('Y-m-d H:i'), 8, false, [0.5, 0.55, 0.6]);
        $this->text(515, 32, "Page " . $this->currentPage, 8, false, [0.5, 0.55, 0.6]);
    }

    public function text(float $x, float $y, string $str, float $size = 10, bool $bold = false, array $color = [0.1, 0.1, 0.1]): void {
        $font = $bold ? '/F2' : '/F1';
        $safeStr = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $str);
        $c = sprintf("%.3F %.3F %.3F rg", $color[0], $color[1], $color[2]);
        $this->pages[$this->currentPage] .= "BT $c $font $size Tf $x $y Td ($safeStr) Tj ET\n";
    }

    public function rect(float $x, float $y, float $w, float $h, string $style = 'F', array $fillColor = [0.95, 0.96, 0.98], array $strokeColor = [0.8, 0.85, 0.9]): void {
        $stream = "";
        if (strpos($style, 'F') !== false) {
            $stream .= sprintf("%.3F %.3F %.3F rg\n", $fillColor[0], $fillColor[1], $fillColor[2]);
        }
        if (strpos($style, 'D') !== false || strpos($style, 'S') !== false) {
            $stream .= sprintf("%.3F %.3F %.3F RG 0.75 w\n", $strokeColor[0], $strokeColor[1], $strokeColor[2]);
        }
        $stream .= sprintf("%.2F %.2F %.2F %.2F re ", $x, $y, $w, $h);
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $this->pages[$this->currentPage] .= $stream . $op . "\n";
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color = [0.8, 0.85, 0.9], float $lineWidth = 0.75): void {
        $c = sprintf("%.3F %.3F %.3F RG", $color[0], $color[1], $color[2]);
        $this->pages[$this->currentPage] .= "$c $lineWidth w $x1 $y1 m $x2 $y2 l S\n";
    }

    public function writeReport(string $title, string $description, array $headers, array $rows): void {
        // Document Title
        $this->text(40, $this->y, $title, 18, true, [0.08, 0.12, 0.20]);
        $this->y -= 18;

        // Meta info
        $this->text(40, $this->y, "Generated on " . date('F j, Y, g:i A') . " • Format: Official PDF Export", 9, false, [0.45, 0.5, 0.55]);
        $this->y -= 14;

        // Divider
        $this->line(40, $this->y, 555, $this->y, [0.85, 0.88, 0.92]);
        $this->y -= 20;

        // Description / Summary
        if (!empty($description)) {
            $words = explode(' ', str_replace(["\r\n", "\r", "\n"], ' ', $description));
            $line = '';
            foreach ($words as $word) {
                if (strlen($line . ' ' . $word) > 85) {
                    $this->text(40, $this->y, trim($line), 10, false, [0.2, 0.25, 0.3]);
                    $this->y -= 15;
                    $line = $word;
                } else {
                    $line .= (empty($line) ? '' : ' ') . $word;
                }
            }
            if (!empty($line)) {
                $this->text(40, $this->y, trim($line), 10, false, [0.2, 0.25, 0.3]);
                $this->y -= 20;
            }
        }

        // Table
        if (!empty($headers) && !empty($rows)) {
            $colCount = count($headers);
            $tableWidth = 515;
            $colWidth = floor($tableWidth / $colCount);

            if ($this->y < 120) {
                $this->addPage();
            }

            // Header row
            $this->rect(40, $this->y - 6, $tableWidth, 22, 'F', [0.12, 0.16, 0.23]);
            $x = 45;
            foreach ($headers as $h) {
                $this->text($x, $this->y + 2, mb_substr($h, 0, 22), 9, true, [1, 1, 1]);
                $x += $colWidth;
            }
            $this->y -= 22;

            // Body rows
            $isZebra = false;
            foreach ($rows as $r) {
                if ($this->y < 65) {
                    $this->addPage();
                    $this->rect(40, $this->y - 6, $tableWidth, 22, 'F', [0.12, 0.16, 0.23]);
                    $x = 45;
                    foreach ($headers as $h) {
                        $this->text($x, $this->y + 2, mb_substr($h, 0, 22), 9, true, [1, 1, 1]);
                        $x += $colWidth;
                    }
                    $this->y -= 22;
                }

                $bgColor = $isZebra ? [0.96, 0.97, 0.99] : [1, 1, 1];
                $this->rect(40, $this->y - 4, $tableWidth, 18, 'F', $bgColor);
                $this->line(40, $this->y - 4, 555, $this->y - 4, [0.9, 0.92, 0.95], 0.5);

                $x = 45;
                foreach ($headers as $idx => $h) {
                    $val = is_array($r) ? ($r[$h] ?? ($r[$idx] ?? '')) : '';
                    if (is_array($val)) $val = json_encode($val);
                    $this->text($x, $this->y + 2, mb_substr((string)$val, 0, 24), 8.5, false, [0.15, 0.2, 0.25]);
                    $x += $colWidth;
                }

                $this->y -= 18;
                $isZebra = !$isZebra;
            }
        }
    }

    public function save(string $filePath): void {
        $numPages = count($this->pages);
        $offsets = [];
        $pdf = "%PDF-1.4\n";

        // Object 1: Catalog
        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Object 2: Pages
        $kids = [];
        for ($i = 1; $i <= $numPages; $i++) {
            $kids[] = (2 + $i) . " 0 R";
        }
        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $numPages >>\nendobj\n";

        $font1Obj = 3 + $numPages * 2;
        $font2Obj = $font1Obj + 1;

        // Page & Content objects
        for ($i = 1; $i <= $numPages; $i++) {
            $pageObjId = 2 + $i;
            $contentObjId = 2 + $numPages + $i;

            $offsets[$pageObjId] = strlen($pdf);
            $pdf .= "$pageObjId 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Contents $contentObjId 0 R /Resources << /Font << /F1 $font1Obj 0 R /F2 $font2Obj 0 R >> >> >>\nendobj\n";

            $stream = $this->pages[$i];
            $streamLen = strlen($stream);
            $offsets[$contentObjId] = strlen($pdf);
            $pdf .= "$contentObjId 0 obj\n<< /Length $streamLen >>\nstream\n" . $stream . "endstream\nendobj\n";
        }

        // Fonts (Standard 14 - requires zero external font files)
        $offsets[$font1Obj] = strlen($pdf);
        $pdf .= "$font1Obj 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        $offsets[$font2Obj] = strlen($pdf);
        $pdf .= "$font2Obj 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Xref table
        $totalObjs = $font2Obj;
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($totalObjs + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($totalObjs + 1) . " /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF\n";

        file_put_contents($filePath, $pdf);
    }
}
