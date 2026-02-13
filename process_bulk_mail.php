<?php
declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════
//  ENHANCED ERROR LOGGING SETUP
// ═══════════════════════════════════════════════════════════════════════════

error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't expose errors in JSON responses
ini_set('log_errors', '1');

// Create logs directory
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

ini_set('error_log', $logsDir . '/php_errors_' . date('Ymd') . '.log');

/**
 * Enhanced logging function with context
 * Logs to: /logs/bulk_mail_YYYYMMDD.log
 */
function logEvent(string $level, string $message, array $context = []): void
{
    static $logFile = null;
    
    if ($logFile === null) {
        $logFile = __DIR__ . '/logs/bulk_mail_' . date('Ymd') . '.log';
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    
    $logEntry = sprintf(
        "[%s][%s] %s%s\n",
        $level,
        $timestamp,
        $message,
        $contextStr
    );
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also log critical errors to PHP error log
    if (in_array($level, ['ERROR', 'CRITICAL'], true)) {
        error_log("[BULK_MAILER][$level] $message");
    }
}

logEvent('INFO', 'Bulk mail processor started', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'action' => $_GET['action'] ?? $_POST['action'] ?? 'none',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);


session_start();

require_once 'db_config.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

// ═══════════════════════════════════════════════════════════════════════════
//  CONFIGURATION CONSTANTS
// ═══════════════════════════════════════════════════════════════════════════

/** Maximum automatic retry attempts for a single email */
define('MAX_RETRIES', 3);

/** Seconds after which a "processing" item is considered stale and re-queued */
define('STALE_LOCK_SECONDS', 300);

/** Delay in milliseconds between consecutive sends (rate limiting) */
define('INTER_EMAIL_DELAY_MS', 300);

/** Maximum emails to process in a single batch */
define('MAX_BATCH_SIZE', 50);

/** Default batch size when not specified */
define('DEFAULT_BATCH_SIZE', 20);

/**
 * Allowed attachment directories (path traversal protection)
 * Any file outside these paths will be rejected
 */
define('ALLOWED_ATTACHMENT_DIRS', [
    '/home/u955994755/domains/holidayseva.com/public_html/SXC_MDTS/File_Drive',
    __DIR__ . '/uploads/attachments',
]);

/** SMTP Configuration */
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('DEFAULT_DISPLAY_NAME', "St. Xavier's College");

// ═══════════════════════════════════════════════════════════════════════════
//  AUTHENTICATION GUARD
// ═══════════════════════════════════════════════════════════════════════════

if (!isset($_SESSION['smtp_user'], $_SESSION['smtp_pass'])) {
    logEvent('WARN', 'Unauthorized access attempt', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized — please log in.']);
    exit();
}

header('Content-Type: application/json');

// ═══════════════════════════════════════════════════════════════════════════
//  MAIN DISPATCH
// ═══════════════════════════════════════════════════════════════════════════

$action     = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
$smtpUser   = $_SESSION['smtp_user'];
$smtpPass   = $_SESSION['smtp_pass'];
$settings   = $_SESSION['user_settings'] ?? [];

logEvent('INFO', "Action requested: '$action'", ['smtp_user' => $smtpUser]);

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new RuntimeException('Database connection failed.');
    }

    $userId = getUserId($pdo, $smtpUser);
    if (!$userId) {
        throw new RuntimeException('Authenticated user not found in database.');
    }

    // Ensure schema columns exist (idempotent)
    ensureSchemaColumns($pdo);

    // Automatically recover items stuck in "processing"
    recoverStaleItems($pdo, $userId);

    switch ($action) {

        // ─── STATUS ───────────────────────────────────────────────────────
        case 'status':
            outputJson(getQueueStats($pdo, $userId));
            break;

        // ─── QUEUE LIST ───────────────────────────────────────────────────
        case 'queue_list':
            $limit  = min((int)($_GET['limit'] ?? 200), 500);
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            $filter = $_GET['filter'] ?? 'all';

            $validFilters  = ['all', 'pending', 'processing', 'completed', 'failed'];
            $statusFilter  = in_array($filter, $validFilters, true) ? $filter : 'all';

            $whereParts = ['user_id = ?'];
            $params     = [$userId];

            if ($statusFilter !== 'all') {
                $whereParts[] = 'status = ?';
                $params[]     = $statusFilter;
            }

            $where = implode(' AND ', $whereParts);

            $stmt = $pdo->prepare("
                SELECT
                    id, recipient_email, recipient_name, subject, article_title,
                    status, error_message, retry_count,
                    created_at, processing_started_at, completed_at, retry_after
                FROM bulk_mail_queue
                WHERE {$where}
                ORDER BY
                    FIELD(status,'processing','pending','failed','completed'),
                    created_at DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

            logEvent('DEBUG', 'Queue list retrieved', [
                'filter' => $statusFilter,
                'count' => count($queue)
            ]);

            outputJson([
                'success' => true,
                'queue'   => $queue,
                'offset'  => $offset,
                'limit'   => $limit,
                'filter'  => $statusFilter,
            ]);
            break;

        // ─── PROCESS SINGLE ───────────────────────────────────────────────
        case 'process':
            $result = processNextEmail($pdo, $userId, $smtpUser, $smtpPass, $settings);
            
            logEvent(
                $result['email_sent'] ? 'SUCCESS' : 'INFO',
                $result['email_sent'] ? 'Email sent successfully' : 'Email processing completed',
                [
                    'email_sent' => $result['email_sent'] ?? false,
                    'recipient' => $result['recipient'] ?? 'unknown',
                    'no_more' => $result['no_more'] ?? false
                ]
            );
            
            outputJson($result);
            break;

        // ─── TEST SMTP CONNECTION ──────────────────────────────────────────
        case 'test_smtp':
            logEvent('INFO', 'SMTP connection test initiated');
            
            $debugLog = [];
            try {
                $testMailer = buildMailer($smtpUser, $smtpPass, $settings, false);
                $testMailer->SMTPDebug  = 4;
                $testMailer->Debugoutput = function (string $str) use (&$debugLog) {
                    $debugLog[] = rtrim($str);
                };
                
                $connected = $testMailer->smtpConnect([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ]);
                $testMailer->smtpClose();

                logEvent(
                    $connected ? 'SUCCESS' : 'ERROR',
                    'SMTP connection test completed',
                    ['connected' => $connected]
                );

                outputJson([
                    'success'    => $connected,
                    'connected'  => $connected,
                    'smtp_user'  => $smtpUser,
                    'host'       => SMTP_HOST . ':' . SMTP_PORT,
                    'log'        => $debugLog,
                    'message'    => $connected
                        ? 'SMTP connection and authentication successful.'
                        : 'SMTP connection failed — check log for details.',
                ]);
            } catch (Throwable $e) {
                logEvent('ERROR', 'SMTP connection test failed', [
                    'error' => $e->getMessage()
                ]);
                
                outputJson([
                    'success'   => false,
                    'connected' => false,
                    'error'     => $e->getMessage(),
                    'log'       => $debugLog,
                ]);
            }
            break;

        // ─── PROCESS BATCH ─────────────────────────────────────────────────
        case 'process_batch':
            $batchSize = min((int)($_POST['batch_size'] ?? DEFAULT_BATCH_SIZE), MAX_BATCH_SIZE);
            
            logEvent('INFO', 'Batch processing initiated', ['batch_size' => $batchSize]);
            
            $result = processBatch($pdo, $userId, $smtpUser, $smtpPass, $settings, $batchSize);
            
            logEvent('INFO', 'Batch processing completed', [
                'sent' => $result['batch_sent'] ?? 0,
                'failed' => $result['batch_failed'] ?? 0
            ]);
            
            outputJson($result);
            break;

        // ─── RETRY FAILED ──────────────────────────────────────────────────
        case 'retry_failed':
            $stmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET status = 'pending',
                    retry_count = 0,
                    retry_after = NULL,
                    error_message = NULL
                WHERE user_id = ? AND status = 'failed'
            ");
            $stmt->execute([$userId]);
            $retried = $stmt->rowCount();

            logEvent('INFO', 'Failed emails retried', ['count' => $retried]);

            outputJson([
                'success' => true,
                'retried' => $retried,
                'message' => "Retried {$retried} failed email(s).",
            ]);
            break;

        // ─── CLEAR FAILED ──────────────────────────────────────────────────
        case 'clear_failed':
            $stmt = $pdo->prepare("
                DELETE FROM bulk_mail_queue
                WHERE user_id = ? AND status = 'failed'
            ");
            $stmt->execute([$userId]);
            $deleted = $stmt->rowCount();

            logEvent('INFO', 'Failed emails cleared', ['count' => $deleted]);

            outputJson([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Cleared {$deleted} failed email(s) from queue.",
            ]);
            break;

        // ─── RECOVER STALE ─────────────────────────────────────────────────
        case 'recover_stale':
            $recovered = recoverStaleItems($pdo, $userId);
            
            logEvent('INFO', 'Stale items recovered', ['count' => $recovered]);
            
            outputJson([
                'success'   => true,
                'recovered' => $recovered,
                'message'   => "Recovered {$recovered} stale processing item(s).",
            ]);
            break;

        default:
            throw new InvalidArgumentException("Unknown action: '{$action}'");
    }

} catch (Throwable $e) {
    http_response_code(400);
    
    logEvent('ERROR', 'Request failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    outputJson(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════════════════════════
//  QUEUE PROCESSING — SINGLE EMAIL
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Claims and processes the next eligible pending email
 */
function processNextEmail(
    PDO    $pdo,
    int    $userId,
    string $smtpUser,
    string $smtpPass,
    array  $settings,
    ?PHPMailer $sharedMailer = null,
    bool   $debugToFile      = false
): array {
    $queueItem = null;

    try {
        // Atomically claim next eligible item
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT * FROM bulk_mail_queue
            WHERE  user_id     = ?
              AND  status      = 'pending'
              AND  (retry_after IS NULL OR retry_after <= NOW())
            ORDER BY created_at ASC
            LIMIT  1
            FOR UPDATE
        ");
        $stmt->execute([$userId]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$queueItem) {
            $pdo->rollBack();
            return [
                'success'    => true,
                'email_sent' => false,
                'no_more'    => true,
                'message'    => 'No pending emails in queue.',
            ];
        }

        // Mark as processing
        $pdo->prepare("
            UPDATE bulk_mail_queue
            SET  status                = 'processing',
                 processing_started_at = NOW(),
                 locked_until          = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ?
        ")->execute([STALE_LOCK_SECONDS, $queueItem['id']]);

        $pdo->commit();

        logEvent('DEBUG', 'Queue item claimed', [
            'queue_id' => $queueItem['id'],
            'recipient' => $queueItem['recipient_email']
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        logEvent('ERROR', 'Queue claim failed', ['error' => $e->getMessage()]);
        
        return ['success' => false, 'error' => 'Queue claim failed: ' . $e->getMessage()];
    }

    // Send the email
    $sendResult = sendBulkEmail($pdo, $queueItem, $userId, $smtpUser, $smtpPass, $settings, $sharedMailer, $debugToFile);

    // Persist outcome
    if ($sendResult['success']) {
        $pdo->prepare("
            UPDATE bulk_mail_queue
            SET  status        = 'completed',
                 completed_at  = NOW(),
                 sent_email_id = ?,
                 error_message = NULL,
                 locked_until  = NULL
            WHERE id = ?
        ")->execute([$sendResult['sent_email_id'] ?? null, $queueItem['id']]);

        logEvent('SUCCESS', 'Email sent successfully', [
            'queue_id' => $queueItem['id'],
            'recipient' => $queueItem['recipient_email'],
            'sent_email_id' => $sendResult['sent_email_id'] ?? null
        ]);

        return [
            'success'        => true,
            'email_sent'     => true,
            'recipient'      => $queueItem['recipient_email'],
            'recipient_name' => $queueItem['recipient_name'] ?? '',
            'subject'        => $queueItem['subject'] ?? '',
            'message'        => 'Email sent successfully.',
        ];

    } else {
        // Retry logic
        $retryCount = (int)($queueItem['retry_count'] ?? 0) + 1;
        $errorMsg   = $sendResult['error'] ?? 'Unknown error';
        $permanent  = $sendResult['permanent'] ?? false;

        if (!$permanent && $retryCount < MAX_RETRIES) {
            // Exponential backoff: 2 min, 8 min, 32 min
            $backoffSeconds = (int)(120 * pow(4, $retryCount - 1));
            $retryAfter     = date('Y-m-d H:i:s', time() + $backoffSeconds);

            $pdo->prepare("
                UPDATE bulk_mail_queue
                SET  status        = 'pending',
                     retry_count   = ?,
                     retry_after   = ?,
                     last_error_at = NOW(),
                     error_message = ?,
                     locked_until  = NULL
                WHERE id = ?
            ")->execute([$retryCount, $retryAfter, $errorMsg, $queueItem['id']]);

            logEvent('WARN', 'Email send failed - will retry', [
                'queue_id' => $queueItem['id'],
                'recipient' => $queueItem['recipient_email'],
                'retry_count' => $retryCount,
                'retry_after' => $retryAfter,
                'error' => $errorMsg
            ]);
        } else {
            // Max retries reached or permanent failure
            $pdo->prepare("
                UPDATE bulk_mail_queue
                SET  status        = 'failed',
                     completed_at  = NOW(),
                     retry_count   = ?,
                     last_error_at = NOW(),
                     error_message = ?,
                     locked_until  = NULL
                WHERE id = ?
            ")->execute([$retryCount, $errorMsg, $queueItem['id']]);

            logEvent('ERROR', 'Email permanently failed', [
                'queue_id' => $queueItem['id'],
                'recipient' => $queueItem['recipient_email'],
                'retry_count' => $retryCount,
                'permanent' => $permanent,
                'error' => $errorMsg
            ]);
        }

        return [
            'success'        => true,  // API call succeeded
            'email_sent'     => false, // Email itself failed
            'recipient'      => $queueItem['recipient_email'],
            'recipient_name' => $queueItem['recipient_name'] ?? '',
            'subject'        => $queueItem['subject'] ?? '',
            'error'          => humanReadableSmtpError($errorMsg),
            'retry_count'    => $retryCount,
            'will_retry'     => !$permanent && ($retryCount < MAX_RETRIES),
            'message'        => 'Email failed: ' . humanReadableSmtpError($errorMsg),
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  QUEUE PROCESSING — BATCH
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Processes multiple emails in one call with connection reuse
 */
function processBatch(
    PDO    $pdo,
    int    $userId,
    string $smtpUser,
    string $smtpPass,
    array  $settings,
    int    $batchSize = DEFAULT_BATCH_SIZE
): array {
    set_time_limit(0); // Allow long-running batch

    $mailer  = buildMailer($smtpUser, $smtpPass, $settings, false);
    $mailer->SMTPKeepAlive = true; // Reuse connection

    $sent    = 0;
    $failed  = 0;
    $results = [];
    $noMore  = false;

    for ($i = 0; $i < $batchSize; $i++) {
        $result = processNextEmail($pdo, $userId, $smtpUser, $smtpPass, $settings, $mailer, false);

        if (!$result['success']) {
            break; // Critical error
        }

        if ($result['no_more'] ?? false) {
            $noMore = true;
            break;
        }

        if ($result['email_sent']) {
            $sent++;
        } else {
            $failed++;
        }

        $results[] = $result;

        // Rate limiting delay
        if ($i < $batchSize - 1) {
            usleep(INTER_EMAIL_DELAY_MS * 1000);
        }
    }

    // Close keepalive connection
    $mailer->smtpClose();

    $stats = getQueueStats($pdo, $userId);

    return [
        'success'      => true,
        'batch_sent'   => $sent,
        'batch_failed' => $failed,
        'no_more'      => $noMore,
        'results'      => $results,
        'queue_stats'  => $stats,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
//  EMAIL SENDING — CORE
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Sends one email for the given queue item
 */
function sendBulkEmail(
    PDO       $pdo,
    array     $queueItem,
    int       $userId,
    string    $smtpUser,
    string    $smtpPass,
    array     $settings,
    ?PHPMailer $sharedMailer = null,
    bool      $debugToFile   = false
): array {
    $mail = $sharedMailer ?? buildMailer($smtpUser, $smtpPass, $settings, $debugToFile);

    // Reset for reuse
    $mail->clearAddresses();
    $mail->clearReplyTos();
    $mail->clearAttachments();
    $mail->clearCustomHeaders();

    try {
        // Sender
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : DEFAULT_DISPLAY_NAME;
        $mail->setFrom($smtpUser, $displayName);
        $mail->addReplyTo($smtpUser, $displayName);

        // Recipient
        $recipientEmail = filter_var(trim((string)($queueItem['recipient_email'] ?? '')), FILTER_SANITIZE_EMAIL);

        if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success'   => false,
                'permanent' => true,
                'error'     => 'Invalid recipient email address: ' . ($queueItem['recipient_email'] ?? '(empty)'),
            ];
        }

        $recipientName = trim((string)($queueItem['recipient_name'] ?? ''));
        if ($recipientName !== '') {
            $mail->addAddress($recipientEmail, $recipientName);
        } else {
            $mail->addAddress($recipientEmail);
        }

        // Subject & body
        $subject         = (string)($queueItem['subject']          ?: 'Official Communication');
        $articleTitle    = (string)($queueItem['article_title']    ?: 'Official Communication');
        $messageContent  = (string)($queueItem['message_content']  ?: '');
        $closingWish     = (string)($queueItem['closing_wish']     ?: 'Best Regards,');
        $senderName      = (string)($queueItem['sender_name']      ?: $displayName);
        $senderDesig     = (string)($queueItem['sender_designation'] ?: '');
        $additionalInfo  = (string)($queueItem['additional_info']  ?: "St. Xavier's College (Autonomous), Kolkata");

        $mail->Subject = $subject;

        // Build HTML body
        $emailBody = buildEmailBody(
            $articleTitle, $messageContent, $closingWish,
            $senderName,   $senderDesig,    $additionalInfo
        );

        $mail->isHTML(true);
        $mail->Body    = $emailBody;
        $mail->AltBody = buildPlainTextBody($emailBody);

        // Attachment
        $driveFilePath = (string)($queueItem['drive_file_path'] ?? '');

        if ($driveFilePath !== '') {
            if (!isAllowedAttachmentPath($driveFilePath)) {
                logEvent('WARN', 'Blocked unsafe attachment path', [
                    'path' => $driveFilePath,
                    'queue_id' => $queueItem['id']
                ]);
                
                return [
                    'success'   => false,
                    'permanent' => true,
                    'error'     => 'Attachment path is outside the allowed directories.',
                ];
            }

            if (!file_exists($driveFilePath) || !is_readable($driveFilePath)) {
                logEvent('WARN', 'Attachment not found - continuing without it', [
                    'path' => $driveFilePath
                ]);
            } else {
                $mail->addAttachment($driveFilePath, basename($driveFilePath));
            }
        }

        // Send
        logEvent('DEBUG', 'Attempting to send email', [
            'recipient' => $recipientEmail,
            'subject' => $subject
        ]);

        if (!$mail->send()) {
            throw new MailerException('PHPMailer send() failed: ' . $mail->ErrorInfo);
        }

        logEvent('SUCCESS', 'PHPMailer send() returned true', [
            'recipient' => $recipientEmail
        ]);

        // Log to sent_emails_new
        $emailUuid      = generateUuidV4();
        $hasAttachment  = ($driveFilePath !== '' && file_exists($driveFilePath)) ? 1 : 0;
        $bodyText       = buildPlainTextBody($emailBody);

        $stmt = $pdo->prepare("
            INSERT INTO sent_emails_new (
                email_uuid,
                sender_email,   sender_name,
                recipient_email,
                cc_list,        bcc_list,
                subject,        article_title,
                body_text,      body_html,
                has_attachments, email_type,
                is_deleted,
                sent_at,        created_at
            ) VALUES (
                ?,
                ?, ?,
                ?,
                '', '',
                ?, ?,
                ?, ?,
                ?, 'sent',
                0,
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            $emailUuid,
            $smtpUser,      $senderName,
            $recipientEmail,
            $subject,       $articleTitle,
            $bodyText,      $emailBody,
            $hasAttachment,
        ]);

        $sentEmailId = (int)$pdo->lastInsertId();

        logEvent('SUCCESS', 'Email logged to database', [
            'sent_email_id' => $sentEmailId,
            'recipient' => $recipientEmail
        ]);

        // Log attachment
        if ($hasAttachment) {
            $fileName = basename($driveFilePath);
            $ext      = strtolower(pathinfo($driveFilePath, PATHINFO_EXTENSION));
            $mimeType = getMimeTypeFromExtension($ext);

            $pdo->prepare("
                INSERT INTO sent_email_attachments_new (
                    sent_email_id, email_uuid,
                    original_filename, stored_filename,
                    file_path, file_size,
                    mime_type, file_extension,
                    uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $sentEmailId,
                $emailUuid,
                $fileName,
                $fileName,
                $driveFilePath,
                @filesize($driveFilePath) ?: 0,
                $mimeType,
                $ext,
            ]);
        }

        return [
            'success'       => true,
            'sent_email_id' => $sentEmailId,
        ];

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        $permanent = isSmtpPermanentFailure($errorMessage);

        logEvent('ERROR', 'Email send failed', [
            'recipient' => $queueItem['recipient_email'] ?? 'unknown',
            'queue_id' => $queueItem['id'] ?? 0,
            'permanent' => $permanent,
            'error' => $errorMessage
        ]);

        return [
            'success'   => false,
            'permanent' => $permanent,
            'error'     => $errorMessage,
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  PHPMAILER BUILDER
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Creates and configures a PHPMailer instance
 */
function buildMailer(string $smtpUser, string $smtpPass, array $settings, bool $debugToFile = false): PHPMailer
{
    $mail = new PHPMailer(true);

    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    // SSL Options (for Hostinger shared hosting)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    // Content Settings
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->XMailer = ' '; // Suppress X-Mailer header (reduces spam score)

    // Debug output
    if ($debugToFile) {
        $mail->SMTPDebug  = 4;
        $logFile          = __DIR__ . '/logs/smtp_debug_' . date('Ymd_His') . '.log';
        
        logEvent('INFO', 'SMTP debug logging enabled', ['log_file' => $logFile]);
        
        $mail->Debugoutput = function (string $str) use ($logFile) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $str . "\n", FILE_APPEND | LOCK_EX);
        };
    } else {
        $mail->SMTPDebug = 0;
    }

    return $mail;
}

// ═══════════════════════════════════════════════════════════════════════════
//  EMAIL BODY BUILDERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Builds HTML email body from template
 */
function buildEmailBody(
    string $articleTitle,
    string $messageContent,
    string $closingWish,
    string $senderName,
    string $senderDesignation,
    string $additionalInfo
): string {
    $templatePath = __DIR__ . '/templates/template1.html';
    
    if (!file_exists($templatePath)) {
        // Fallback template if file doesn't exist
        return "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h1>{$articleTitle}</h1>
                <p>" . nl2br(htmlspecialchars($messageContent)) . "</p>
                <p>{$closingWish}<br>
                <strong>{$senderName}</strong><br>
                {$senderDesignation}<br>
                {$additionalInfo}</p>
            </body>
            </html>
        ";
    }
    
    $emailTemplate = file_get_contents($templatePath);
    
    return str_replace([
        '{{articletitle}}',
        '{{MESSAGE}}',
        '{{SIGNATURE_WISH}}',
        '{{SIGNATURE_NAME}}',
        '{{SIGNATURE_DESIGNATION}}',
        '{{SIGNATURE_EXTRA}}'
    ], [
        htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8'),
        nl2br(htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8')),
        htmlspecialchars($closingWish, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($senderDesignation, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($additionalInfo, ENT_QUOTES, 'UTF-8')
    ], $emailTemplate);
}

/**
 * Builds plain text version of email body
 */
function buildPlainTextBody(string $htmlBody): string
{
    // Strip HTML tags
    $text = strip_tags($htmlBody);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\n\s+/', "\n", $text);
    
    return trim($text);
}

// ═══════════════════════════════════════════════════════════════════════════
//  RECOVERY & MAINTENANCE
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Recovers items stuck in "processing" status
 */
function recoverStaleItems(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare("
            UPDATE bulk_mail_queue
            SET status        = 'pending',
                locked_until  = NULL,
                retry_after   = NULL
            WHERE user_id = ?
              AND status  = 'processing'
              AND (
                    locked_until IS NULL
                 OR locked_until < DATE_SUB(NOW(), INTERVAL ? SECOND)
              )
        ");

        $stmt->execute([$userId, STALE_LOCK_SECONDS]);
        $recovered = $stmt->rowCount();

        if ($recovered > 0) {
            logEvent('INFO', 'Recovered stale processing items', [
                'count' => $recovered,
                'user_id' => $userId
            ]);
        }

        return $recovered;

    } catch (Throwable $e) {
        logEvent('WARN', 'Stale lock recovery failed (non-fatal)', [
            'error' => $e->getMessage()
        ]);
        return 0;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Returns queue statistics
 */
function getQueueStats(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                SUM(status = 'pending')    AS pending,
                SUM(status = 'processing') AS processing,
                SUM(status = 'completed')  AS completed,
                SUM(status = 'failed')     AS failed
            FROM bulk_mail_queue
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success'    => true,
            'pending'    => (int)($row['pending']    ?? 0),
            'processing' => (int)($row['processing'] ?? 0),
            'completed'  => (int)($row['completed']  ?? 0),
            'failed'     => (int)($row['failed']     ?? 0),
            'total'      => array_sum(array_map('intval', $row ?? [])),
        ];

    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Ensures required schema columns exist
 */
function ensureSchemaColumns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $alterStatements = [
        "ALTER TABLE bulk_mail_queue ADD COLUMN IF NOT EXISTS retry_count  INT      NOT NULL DEFAULT 0",
        "ALTER TABLE bulk_mail_queue ADD COLUMN IF NOT EXISTS retry_after  DATETIME NULL",
        "ALTER TABLE bulk_mail_queue ADD COLUMN IF NOT EXISTS last_error_at DATETIME NULL",
        "ALTER TABLE bulk_mail_queue ADD COLUMN IF NOT EXISTS locked_until  DATETIME NULL",
    ];

    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // Ignore errors (columns may already exist)
        }
    }

    $checked = true;
}

/**
 * Path traversal protection
 */
function isAllowedAttachmentPath(string $path): bool
{
    $real = realpath($path);

    if ($real === false) {
        return false;
    }

    foreach (ALLOWED_ATTACHMENT_DIRS as $allowedDir) {
        $realAllowed = realpath($allowedDir);
        if ($realAllowed !== false && strncmp($real, $realAllowed . DIRECTORY_SEPARATOR, strlen($realAllowed) + 1) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Classifies SMTP errors as permanent or transient
 */
function isSmtpPermanentFailure(string $errorMessage): bool
{
    $permanent = [
        'invalid recipient',
        'invalid address',
        'recipient not accepted',
        'user unknown',
        'no such user',
        'address rejected',
        'authentication failed',
        'authenticate',
        'access denied',
        'account suspended',
        'account disabled',
        '550 ',
        '551 ',
        '553 ',
        '554 ',
    ];

    $lower = strtolower($errorMessage);
    foreach ($permanent as $marker) {
        if (false !== strpos($lower, $marker)) {
            return true;
        }
    }

    return false;
}

/**
 * Converts SMTP errors to human-readable messages
 */
function humanReadableSmtpError(string $error): string
{
    $lower = strtolower($error);

    if ((false !== strpos($lower, 'data not accepted')) || (false !== strpos($lower, '550'))) {
        return 'SMTP rejected the email. Possible causes: invalid recipient, spam filters, or sending limit reached.';
    }
    if ((false !== strpos($lower, 'connect')) || (false !== strpos($lower, 'timeout'))) {
        return 'Could not connect to SMTP server. Check your internet connection.';
    }
    if ((false !== strpos($lower, 'auth')) || (false !== strpos($lower, 'authenticate'))) {
        return 'SMTP authentication failed. Check your email credentials.';
    }
    if ((false !== strpos($lower, 'rate')) || (false !== strpos($lower, 'too many'))) {
        return 'SMTP rate limit reached. Email will be retried automatically.';
    }
    if ((false !== strpos($lower, 'invalid')) && (false !== strpos($lower, 'address'))) {
        return 'Invalid email address — this email will not be retried.';
    }

    return $error;
}

/**
 * Maps file extensions to MIME types
 */
function getMimeTypeFromExtension(string $ext): string
{
    static $map = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'zip'  => 'application/zip',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
    ];

    return $map[strtolower($ext)] ?? 'application/octet-stream';
}

/**
 * Outputs JSON response and terminates
 */
function outputJson(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Generates UUID v4
 */
if (!function_exists('generateUuidV4')) {
    function generateUuidV4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}