<?php
/**
 * Smart Fire and Rescue Response System
 * EmailNotificationService - Automated CAD Dispatch Alerts
 *
 * Implements direct RFC 5321 SMTP socket transport with STARTTLS,
 * AUTH LOGIN, formatted HTML MIME email generation, and local fallback logging.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/functions.php';

class EmailNotificationService {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
        
        // Ensure storage directory exists for offline/local email auditing
        $storageDir = __DIR__ . '/../storage/emails';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
    }

    /**
     * Send dispatch alert email to emergency personnel for a given dispatch record
     *
     * @param int $dispatchId ID of dispatch_record
     * @return array ['success' => bool, 'status' => string, 'error' => ?string]
     */
    public function sendDispatchNotification(int $dispatchId): array {
        // Retrieve full dispatch, incident, unit, and station details
        $stmt = $this->db->prepare("
            SELECT d.*, 
                   i.incident_id, i.incident_type, i.location_id, i.location_address,
                   i.latitude, i.longitude, i.location_description, i.description AS caller_notes,
                   i.priority_score, i.severity_tier, i.assessment_reason,
                   i.people_trapped, i.injuries_reported, i.hazmat_involved,
                   u.unit_id, u.unit_type,
                   s.station_name, s.contact_email, s.phone AS station_phone,
                   l.location_name
            FROM dispatch_records d
            JOIN incidents i ON d.incident_id = i.incident_id
            JOIN response_units u ON d.unit_id = u.unit_id
            JOIN stations s ON u.station_id = s.station_id
            LEFT JOIN locations l ON i.location_id = l.location_id
            WHERE d.dispatch_id = :id
        ");
        $stmt->execute(['id' => $dispatchId]);
        $record = $stmt->fetch();

        if (!$record) {
            return [
                'success' => false,
                'status'  => 'Failed',
                'error'   => "Dispatch record #{$dispatchId} not found."
            ];
        }

        // Fetch required equipment list for incident
        $eqStmt = $this->db->prepare("
            SELECT ie.equipment_code, et.equipment_name, et.icon 
            FROM incident_equipment ie
            LEFT JOIN equipment_types et ON ie.equipment_code = et.equipment_code
            WHERE ie.incident_id = :inc_id
        ");
        $eqStmt->execute(['inc_id' => $record['incident_id']]);
        $equipmentList = $eqStmt->fetchAll();

        // Determine recipient address
        $recipient = !empty(OFFICER_NOTIFY_EMAIL) ? OFFICER_NOTIFY_EMAIL : ($record['contact_email'] ?? 'dispatch@sudar.gov.in');
        $subject = "🚨 CAD DISPATCH ALERT: Incident #{$record['incident_id']} [{$record['severity_tier']}] - Unit {$record['unit_id']}";

        // Render HTML Email Template
        $htmlBody = $this->buildEmailHtml($record, $equipmentList);

        // Attempt SMTP transmission
        $sendResult = $this->transmitSmtp($recipient, $subject, $htmlBody);

        // Update database with delivery result
        $status = $sendResult['success'] ? 'Sent' : 'Failed';
        $error  = $sendResult['error'] ?? null;
        $note   = $sendResult['note'] ?? '';

        $updateStmt = $this->db->prepare("
            UPDATE dispatch_records 
            SET email_status = :status,
                email_sent_to = :to,
                email_sent_at = NOW(),
                email_error = :err
            WHERE dispatch_id = :id
        ");
        $updateStmt->execute([
            'status' => $status,
            'to'     => $recipient . ($note ? " ({$note})" : ''),
            'err'    => $error,
            'id'     => $dispatchId
        ]);

        // Insert into email_logs audit table
        $logStmt = $this->db->prepare("
            INSERT INTO email_logs (dispatch_id, recipient, subject, status, error_message, sent_at)
            VALUES (:did, :rec, :sub, :st, :err, NOW())
        ");
        $logStmt->execute([
            'did' => $dispatchId,
            'rec' => $recipient,
            'sub' => $subject,
            'st'  => $status,
            'err' => $error
        ]);

        return [
            'success'   => $sendResult['success'],
            'status'    => $status,
            'recipient' => $recipient,
            'note'      => $note,
            'error'     => $error
        ];
    }

    /**
     * Retry sending notification for an existing dispatch record
     */
    public function retryNotification(int $dispatchId): array {
        return $this->sendDispatchNotification($dispatchId);
    }

    /**
     * Transmit email using direct RFC 5321 SMTP socket client
     */
    private function transmitSmtp(string $to, string $subject, string $htmlBody): array {
        $host     = SMTP_HOST;
        $port     = (int)SMTP_PORT;
        $auth     = (bool)SMTP_AUTH;
        $user     = SMTP_USER;
        $pass     = SMTP_PASS;
        $secure   = strtolower(SMTP_SECURE);
        $from     = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;

        // If configured for local fallback / development without live SMTP relay
        $socket = @fsockopen($host, $port, $errno, $errstr, 2.5);

        if (!$socket) {
            // Check if fallback to local file log is permitted
            if (defined('SMTP_LOG_FALLBACK') && SMTP_LOG_FALLBACK) {
                $filename = 'email_dispatch_' . time() . '_' . rand(100, 999) . '.html';
                $filePath = __DIR__ . '/../storage/emails/' . $filename;
                @file_put_contents($filePath, $htmlBody);

                return [
                    'success' => true,
                    'error'   => null,
                    'note'    => "Saved to local log: storage/emails/{$filename}"
                ];
            }

            return [
                'success' => false,
                'error'   => "Cannot connect to SMTP server at {$host}:{$port} ({$errstr})"
            ];
        }

        try {
            // Read greeting
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '220') {
                throw new RuntimeException("Invalid SMTP greeting: {$response}");
            }

            // Send EHLO
            fputs($socket, "EHLO " . gethostname() . "\r\n");
            $this->readSmtpResponse($socket, '250');

            // STARTTLS if requested
            if ($secure === 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $this->readSmtpResponse($socket, '220');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException("TLS handshake failed.");
                }
                fputs($socket, "EHLO " . gethostname() . "\r\n");
                $this->readSmtpResponse($socket, '250');
            }

            // AUTH LOGIN if credentials provided
            if ($auth && !empty($user)) {
                fputs($socket, "AUTH LOGIN\r\n");
                $this->readSmtpResponse($socket, '334');
                fputs($socket, base64_encode($user) . "\r\n");
                $this->readSmtpResponse($socket, '334');
                fputs($socket, base64_encode($pass) . "\r\n");
                $this->readSmtpResponse($socket, '235');
            }

            // MAIL FROM
            fputs($socket, "MAIL FROM:<{$from}>\r\n");
            $this->readSmtpResponse($socket, '250');

            // RCPT TO
            fputs($socket, "RCPT TO:<{$to}>\r\n");
            $this->readSmtpResponse($socket, '250');

            // DATA
            fputs($socket, "DATA\r\n");
            $this->readSmtpResponse($socket, '354');

            // Construct MIME headers and body
            $boundary = "----=_Part_" . md5(uniqid());
            $headers  = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "X-Priority: 1 (Highest)\r\n";
            $headers .= "\r\n";

            $plainText = strip_tags($htmlBody);

            $payload  = $headers;
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $payload .= $plainText . "\r\n\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $payload .= $htmlBody . "\r\n\r\n";
            $payload .= "--{$boundary}--\r\n";
            $payload .= "\r\n.\r\n";

            fputs($socket, $payload);
            $this->readSmtpResponse($socket, '250');

            // QUIT
            fputs($socket, "QUIT\r\n");
            fclose($socket);

            return [
                'success' => true,
                'error'   => null,
                'note'    => "Delivered via SMTP ({$host}:{$port})"
            ];
        } catch (Exception $e) {
            @fclose($socket);

            if (defined('SMTP_LOG_FALLBACK') && SMTP_LOG_FALLBACK) {
                $filename = 'email_fallback_' . time() . '.html';
                @file_put_contents(__DIR__ . '/../storage/emails/' . $filename, $htmlBody);
            }

            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Read and validate SMTP response code
     */
    private function readSmtpResponse($socket, string $expectedCode): string {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // SMTP multi-line responses have hyphen as 4th char (e.g. 250-); end of response has space (250 )
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        if (substr($response, 0, strlen($expectedCode)) !== $expectedCode) {
            throw new RuntimeException("SMTP error (Expected {$expectedCode}): {$response}");
        }
        return $response;
    }

    /**
     * Build rich, responsive HTML CAD dispatch notification
     */
    private function buildEmailHtml(array $record, array $equipmentList): string {
        $incidentUrl = BASE_URL . "incident-details.php?id=" . urlencode((string)$record['incident_id']);

        $tierColors = [
            'Critical' => ['bg' => '#991b1b', 'badge' => '#fee2e2', 'text' => '#991b1b'],
            'High'     => ['bg' => '#c2410c', 'badge' => '#ffedd5', 'text' => '#c2410c'],
            'Medium'   => ['bg' => '#d97706', 'badge' => '#fef3c7', 'text' => '#92400e'],
            'Low'      => ['bg' => '#16a34a', 'badge' => '#dcfce7', 'text' => '#166534']
        ];
        $colors = $tierColors[$record['severity_tier']] ?? $tierColors['Medium'];

        $equipBadges = "";
        foreach ($equipmentList as $eq) {
            $icon = $eq['icon'] ?? '⚙️';
            $name = htmlspecialchars($eq['equipment_name'] ?? $eq['equipment_code']);
            $equipBadges .= "<span style='display:inline-block; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:4px 10px; border-radius:4px; font-size:12px; margin:2px 4px 2px 0;'>{$icon} {$name}</span>";
        }
        if (empty($equipBadges)) {
            $equipBadges = "<em>None specified</em>";
        }

        $gpsInfo = "";
        if (!empty($record['latitude']) && !empty($record['longitude'])) {
            $lat = number_format((float)$record['latitude'], 6);
            $lng = number_format((float)$record['longitude'], 6);
            $mapUrl = "https://www.google.com/maps?q={$lat},{$lng}";
            $gpsInfo = "<tr>
                <td style='padding:8px 0; color:#64748b; font-size:13px;'><strong>GPS Coordinates:</strong></td>
                <td style='padding:8px 0; color:#0f172a; font-size:13px;'>
                    <a href='{$mapUrl}' target='_blank' style='color:#2563eb; text-decoration:none; font-family:monospace;'>
                        📍 {$lat}, {$lng} (Open in Maps &rarr;)
                    </a>
                </td>
            </tr>";
        }

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>CAD Dispatch Alert</title>
</head>
<body style='margin:0; padding:20px; background:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
    <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);'>
        <!-- Header -->
        <tr>
            <td style='background:{$colors['bg']}; padding:24px 28px; color:#ffffff;'>
                <div style='font-size:12px; font-weight:800; letter-spacing:0.08em; text-transform:uppercase; opacity:0.9;'>
                    🚨 " . APP_NAME . " &bull; CAD DISPATCH
                </div>
                <h1 style='margin:6px 0 0; font-size:22px; font-weight:800;'>
                    Incident #{$record['incident_id']}: {$record['incident_type']} Emergency
                </h1>
                <div style='margin-top:10px;'>
                    <span style='background:{$colors['badge']}; color:{$colors['text']}; padding:4px 12px; border-radius:12px; font-weight:800; font-size:12px; text-transform:uppercase;'>
                        {$record['severity_tier']} SEVERITY &bull; SCORE: {$record['priority_score']}/100
                    </span>
                </div>
            </td>
        </tr>

        <!-- Key Unit Assignment Banner -->
        <tr>
            <td style='background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:18px 28px;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td width='50%' style='vertical-align:top;'>
                            <div style='font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;'>Assigned Response Unit</div>
                            <div style='font-size:18px; font-weight:800; color:#0f172a; margin-top:2px;'>
                                🚒 {$record['unit_id']} ({$record['unit_type']})
                            </div>
                            <div style='font-size:12px; color:#475569;'>Base: {$record['station_name']}</div>
                        </td>
                        <td width='50%' style='vertical-align:top; text-align:right;'>
                            <div style='font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;'>Estimated Arrival</div>
                            <div style='font-size:20px; font-weight:800; color:#2563eb; margin-top:2px;'>
                                ⏱️ {$record['estimated_travel_time']} Mins
                            </div>
                            <div style='font-size:11px; color:#64748b;'>Simulated Dijkstra Shortest Path</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Incident Parameters -->
        <tr>
            <td style='padding:24px 28px;'>
                <h3 style='margin:0 0 14px; font-size:15px; color:#0f172a; border-bottom:2px solid #f1f5f9; padding-bottom:8px;'>
                    📍 Location & Dispatch Journey
                </h3>
                <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:20px;'>
                    <tr>
                        <td width='35%' style='padding:6px 0; color:#64748b; font-size:13px;'><strong>Incident Location:</strong></td>
                        <td width='65%' style='padding:6px 0; color:#0f172a; font-size:13px;'>
                            " . htmlspecialchars($record['location_name'] ?? $record['location_id']) . "
                            " . (!empty($record['location_address']) ? "<br><span style='color:#64748b; font-size:12px;'>Address: " . htmlspecialchars($record['location_address']) . "</span>" : "") . "
                        </td>
                    </tr>
                    {$gpsInfo}
                    <tr>
                        <td style='padding:6px 0; color:#64748b; font-size:13px;'><strong>Dispatched Route:</strong></td>
                        <td style='padding:6px 0; font-family:monospace; color:#1e3a8a; font-size:12px; background:#eff6ff; padding:6px 8px; border-radius:4px;'>
                            " . htmlspecialchars($record['route']) . "
                        </td>
                    </tr>
                    <tr>
                        <td style='padding:6px 0; color:#64748b; font-size:13px;'><strong>Dispatch Time:</strong></td>
                        <td style='padding:6px 0; color:#0f172a; font-size:13px; font-family:monospace;'>
                            {$record['dispatched_at']} IST
                        </td>
                    </tr>
                </table>

                <h3 style='margin:18px 0 12px; font-size:15px; color:#0f172a; border-bottom:2px solid #f1f5f9; padding-bottom:8px;'>
                    🧰 Required Equipment Capabilities
                </h3>
                <div style='margin-bottom:20px;'>
                    {$equipBadges}
                </div>

                <h3 style='margin:18px 0 12px; font-size:15px; color:#0f172a; border-bottom:2px solid #f1f5f9; padding-bottom:8px;'>
                    📝 Reported Hazards & Assessment
                </h3>
                <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; font-size:13px; color:#334155; line-height:1.5; margin-bottom:24px;'>
                    <strong>Triage Reasoning:</strong> " . htmlspecialchars($record['assessment_reason'] ?? 'Standard triage rules applied.') . "<br>
                    " . (!empty($record['caller_notes']) ? "<div style='margin-top:6px;'><strong>Caller Notes:</strong> " . nl2br(htmlspecialchars($record['caller_notes'])) . "</div>" : "") . "
                </div>

                <!-- Action CTA Button -->
                <div style='text-align:center; padding:10px 0;'>
                    <a href='{$incidentUrl}' style='display:inline-block; background:#0f172a; color:#ffffff; font-weight:700; font-size:14px; text-decoration:none; padding:12px 28px; border-radius:6px; letter-spacing:0.02em;'>
                        Open Incident CAD Workspace &rarr;
                    </a>
                </div>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style='background:#f8fafc; border-top:1px solid #e2e8f0; padding:16px 28px; font-size:11px; color:#94a3b8; text-align:center;'>
                Automatic transmission generated by " . APP_NAME . " CAD Engine.<br>
                Municipal Town of " . TOWN_NAME . " &bull; For official emergency response personnel only.
            </td>
        </tr>
    </table>
</body>
</html>
";
    }
}
