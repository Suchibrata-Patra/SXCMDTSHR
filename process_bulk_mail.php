<?php
/**
 * process_bulk_mail.php — Production-Ready Bulk Mail Processor
 *
 * Refactored to fix all reliability, safety, and performance issues:
 *  - SMTPKeepAlive: one SMTP connection reused across an entire batch
 *  - Retry mechanism with exponential back-off (max MAX_RETRIES attempts)
 *  - Automatic recovery of items stuck in "processing" (stale lock recovery)
 *  - Path-traversal protection on attachment paths
 *  - Correct variable scoping in every catch block
 *  - Duplicate getUserId() call eliminated
 *  - Proper HTML-entity decoding for plain-text AltBody
 *  - set_time_limit() for long batch runs
 *  - Server-side process_batch action (reduces round-trips)
 *  - Retry / reset_failed / clear_failed API actions
 *  - Full structured logging via logEvent()
 *
 * ─── REQUIRED SCHEMA MIGRATION ────────────────────────────────────────────
 * Run once on your database before deploying this file:
 *
 *   ALTER TABLE bulk_mail_queue
 *     ADD COLUMN IF NOT EXISTS retry_count     INT          NOT NULL DEFAULT 0,
 *     ADD COLUMN IF NOT EXISTS retry_after     DATETIME     NULL,
 *     ADD COLUMN IF NOT EXISTS last_error_at   DATETIME     NULL,
 *     ADD COLUMN IF NOT EXISTS locked_until    DATETIME     NULL;
 *
 *   CREATE INDEX IF NOT EXISTS idx_bmq_process
 *     ON bulk_mail_queue (user_id, status, retry_after, created_at);
 * ──────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════
//  BOOTSTRAP
// ═══════════════════════════════════════════════════════════════════════════

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

/** Maximum automatic retry attempts for a single email. */
define('MAX_RETRIES', 3);

/** Seconds after which a "processing" item is considered stale and re-queued. */
define('STALE_LOCK_SECONDS', 300);

/** Delay in seconds between consecutive sends within a batch (server-side rate limiting). */
define('INTER_EMAIL_DELAY_MS', 300);          // milliseconds → converted below

/** Maximum emails to process in a single process_batch call. */
define('MAX_BATCH_SIZE', 50);

/** Default batch size when not specified by the caller. */
define('DEFAULT_BATCH_SIZE', 20);

/**
 * Absolute paths that attachment files are allowed to live under.
 * Any drive_file_path that is not under one of these prefixes is rejected.
 */
define('ALLOWED_ATTACHMENT_DIRS', [
    '/home/u955994755/domains/holidayseva.com/public_html/SXC_MDTS/File_Drive',
    __DIR__ . '/uploads/attachments',
]);

/** SMTP host (mirrors send.php). */
define('SMTP_HOST', 'smtp.hostinger.com');

/** SMTP port for SMTPS/SSL. */
define('SMTP_PORT', 465);

/** Default sender display name fallback. */
define('DEFAULT_DISPLAY_NAME', "St. Xavier's College");

// ═══════════════════════════════════════════════════════════════════════════
//  AUTHENTICATION GUARD
// ═══════════════════════════════════════════════════════════════════════════

if (!isset($_SESSION['smtp_user'], $_SESSION['smtp_pass'])) {
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

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new RuntimeException('Database connection failed.');
    }

    $userId = getUserId($pdo, $smtpUser);
    if (!$userId) {
        throw new RuntimeException('Authenticated user not found in database.');
    }

    // ── Ensure schema columns exist (idempotent, only runs DDL if missing) ──
    ensureSchemaColumns($pdo);

    // ── Automatically recover items stuck in "processing" on every request ──
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
            $filter = $_GET['filter'] ?? 'all'; // all | pending | completed | failed

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
            outputJson($result);
            break;

        // ─── PROCESS BATCH ────────────────────────────────────────────────
        //  Processes multiple emails in one HTTP request, reusing the SMTP
        //  connection across the whole batch.
        case 'process_batch':
            $batchSize = min(
                (int)($_POST['batch_size'] ?? $_GET['batch_size'] ?? DEFAULT_BATCH_SIZE),
                MAX_BATCH_SIZE
            );

            $result = processBatch($pdo, $userId, $smtpUser, $smtpPass, $settings, $batchSize);
            outputJson($result);
            break;

        // ─── RETRY FAILED ─────────────────────────────────────────────────
        //  Re-queues failed items so they can be retried.
        case 'retry_failed':
            $stmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET  status      = 'pending',
                     retry_after  = NULL,
                     error_message = CONCAT('[retried] ', COALESCE(error_message,''))
                WHERE user_id = ?
                  AND status = 'failed'
            ");
            $stmt->execute([$userId]);
            $requeued = $stmt->rowCount();

            logEvent('INFO', "Retry-failed: re-queued {$requeued} items for user {$userId}");

            outputJson([
                'success'  => true,
                'requeued' => $requeued,
                'message'  => "Re-queued {$requeued} failed email(s) for retry.",
            ]);
            break;

        // ─── RESET SINGLE ITEM ────────────────────────────────────────────
        case 'reset_item':
            $itemId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($itemId <= 0) {
                throw new InvalidArgumentException('Missing or invalid item id.');
            }

            $stmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET  status       = 'pending',
                     retry_count  = 0,
                     retry_after  = NULL,
                     error_message = NULL
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$itemId, $userId]);

            outputJson([
                'success' => (bool)$stmt->rowCount(),
                'message' => $stmt->rowCount() ? 'Item reset to pending.' : 'Item not found.',
            ]);
            break;

        // ─── CLEAR PENDING ────────────────────────────────────────────────
        case 'clear':
            $stmt = $pdo->prepare("
                DELETE FROM bulk_mail_queue
                WHERE user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$userId]);
            $deleted = $stmt->rowCount();

            outputJson([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Cleared {$deleted} pending email(s) from queue.",
            ]);
            break;

        // ─── CLEAR FAILED ─────────────────────────────────────────────────
        case 'clear_failed':
            $stmt = $pdo->prepare("
                DELETE FROM bulk_mail_queue
                WHERE user_id = ? AND status = 'failed'
            ");
            $stmt->execute([$userId]);
            $deleted = $stmt->rowCount();

            outputJson([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Cleared {$deleted} failed email(s) from queue.",
            ]);
            break;

        // ─── RECOVER STALE ────────────────────────────────────────────────
        //  Manually triggers stale-lock recovery (also auto-runs on every request).
        case 'recover_stale':
            $recovered = recoverStaleItems($pdo, $userId);
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
    logEvent('ERROR', 'Dispatch error: ' . $e->getMessage());
    outputJson(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════════════════════════
//  QUEUE PROCESSING — SINGLE EMAIL
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Claims and processes the next eligible pending email.
 *
 * Eligible = status='pending' AND (retry_after IS NULL OR retry_after <= NOW())
 *
 * Returns a result array suitable for JSON output.
 */
function processNextEmail(
    PDO    $pdo,
    int    $userId,
    string $smtpUser,
    string $smtpPass,
    array  $settings,
    ?PHPMailer $sharedMailer = null
): array {
    $queueItem = null;

    try {
        // ── Claim next eligible item atomically ──────────────────────────
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
                'success'  => true,
                'email_sent' => false,
                'no_more'  => true,
                'message'  => 'No pending emails in queue.',
            ];
        }

        // Mark as processing (with a time-limited lock)
        $pdo->prepare("
            UPDATE bulk_mail_queue
            SET  status                = 'processing',
                 processing_started_at = NOW(),
                 locked_until          = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ?
        ")->execute([STALE_LOCK_SECONDS, $queueItem['id']]);

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => 'Queue claim failed: ' . $e->getMessage()];
    }

    // ── Send ─────────────────────────────────────────────────────────────
    $sendResult = sendBulkEmail($pdo, $queueItem, $userId, $smtpUser, $smtpPass, $settings, $sharedMailer);

    // ── Persist outcome ──────────────────────────────────────────────────
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

        logEvent('INFO', "Sent OK → {$queueItem['recipient_email']} (queue_id={$queueItem['id']})");

        return [
            'success'        => true,
            'email_sent'     => true,
            'recipient'      => $queueItem['recipient_email'],
            'recipient_name' => $queueItem['recipient_name'] ?? '',
            'subject'        => $queueItem['subject'] ?? '',
            'message'        => 'Email sent successfully.',
        ];

    } else {
        // ── Retry logic ──────────────────────────────────────────────────
        $retryCount = (int)($queueItem['retry_count'] ?? 0) + 1;
        $errorMsg   = $sendResult['error'] ?? 'Unknown error';
        $permanent  = $sendResult['permanent'] ?? false;   // non-retryable errors skip back-off

        if (!$permanent && $retryCount < MAX_RETRIES) {
            // Exponential back-off: 2 min, 8 min, 32 min
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

            logEvent('WARN', "Retry {$retryCount}/" . MAX_RETRIES .
                " scheduled for {$queueItem['recipient_email']} at {$retryAfter}. Error: {$errorMsg}");
        } else {
            // Max retries reached or permanent failure → mark failed
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

            logEvent('ERROR', "Permanently failed → {$queueItem['recipient_email']}. Error: {$errorMsg}");
        }

        return [
            'success'        => true,  // the API call succeeded; email itself failed
            'email_sent'     => false,
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
 * Processes up to $batchSize emails in one call, reusing a single SMTP
 * connection for the entire run (SMTPKeepAlive).
 *
 * Returns aggregate statistics plus per-email results.
 */
function processBatch(
    PDO    $pdo,
    int    $userId,
    string $smtpUser,
    string $smtpPass,
    array  $settings,
    int    $batchSize = DEFAULT_BATCH_SIZE
): array {
    // Extend execution time for large batches (30 s per email + 60 s buffer)
    set_time_limit($batchSize * 30 + 60);

    $results  = [];
    $sent     = 0;
    $failed   = 0;
    $noMore   = false;

    // ── Create a single reusable PHPMailer instance ───────────────────────
    $mailer = buildMailer($smtpUser, $smtpPass, $settings);
    $mailer->SMTPKeepAlive = true;   // THE critical change for bulk sending

    for ($i = 0; $i < $batchSize; $i++) {
        $result = processNextEmail($pdo, $userId, $smtpUser, $smtpPass, $settings, $mailer);

        if (!empty($result['no_more'])) {
            $noMore = true;
            break;
        }

        if (!$result['success']) {
            // Fatal error (DB, auth, etc.) — stop the batch
            $results[] = $result;
            $failed++;
            logEvent('ERROR', 'Batch halted due to fatal error: ' . ($result['error'] ?? 'unknown'));
            break;
        }

        $results[] = $result;

        if ($result['email_sent']) {
            $sent++;
        } else {
            $failed++;
        }

        // Server-side rate limiting between sends
        if ($i < $batchSize - 1 && !$noMore) {
            usleep(INTER_EMAIL_DELAY_MS * 1000);
        }
    }

    // ── Close the shared SMTP connection cleanly ──────────────────────────
    try {
        $mailer->smtpClose();
    } catch (Throwable $e) {
        logEvent('WARN', 'smtpClose error (non-fatal): ' . $e->getMessage());
    }

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
 * Sends one email for the given queue item.
 *
 * @param  PHPMailer|null $sharedMailer  Pre-configured mailer to reuse (keepalive).
 *                                       Pass null for one-shot sends.
 * @return array  ['success'=>true, 'sent_email_id'=>int]
 *              | ['success'=>false, 'error'=>string, 'permanent'=>bool]
 */
function sendBulkEmail(
    PDO       $pdo,
    array     $queueItem,
    int       $userId,
    string    $smtpUser,
    string    $smtpPass,
    array     $settings,
    ?PHPMailer $sharedMailer = null
): array {
    $mail = $sharedMailer ?? buildMailer($smtpUser, $smtpPass, $settings);

    // When reusing a keepalive mailer, always reset addresses/attachments
    // so the previous recipient is not included in the new send.
    $mail->clearAddresses();
    $mail->clearReplyTos();
    $mail->clearAttachments();
    $mail->clearCustomHeaders();

    try {
        // ── Display name ─────────────────────────────────────────────────
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : DEFAULT_DISPLAY_NAME;

        $mail->setFrom($smtpUser, $displayName);
        $mail->addReplyTo($smtpUser, $displayName);

        // ── Recipient ────────────────────────────────────────────────────
        $recipientEmail = filter_var(trim((string)($queueItem['recipient_email'] ?? '')), FILTER_SANITIZE_EMAIL);

        if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            // Invalid address — permanent failure, do not retry.
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

        // ── Subject & body fields ─────────────────────────────────────────
        $subject         = (string)($queueItem['subject']          ?: 'Official Communication');
        $articleTitle    = (string)($queueItem['article_title']    ?: 'Official Communication');
        $messageContent  = (string)($queueItem['message_content']  ?: '');
        $closingWish     = (string)($queueItem['closing_wish']     ?: 'Best Regards,');
        $senderName      = (string)($queueItem['sender_name']      ?: $displayName);
        $senderDesig     = (string)($queueItem['sender_designation'] ?: '');
        $additionalInfo  = (string)($queueItem['additional_info']  ?: "St. Xavier's College (Autonomous), Kolkata");

        $mail->Subject = $subject;

        // ── Build HTML body ───────────────────────────────────────────────
        $emailBody = buildEmailBody(
            $articleTitle, $messageContent, $closingWish,
            $senderName,   $senderDesig,    $additionalInfo
        );

        $mail->isHTML(true);
        $mail->Body    = $emailBody;
        $mail->AltBody = buildPlainTextBody($emailBody);

        // ── Attachment (drive file) ───────────────────────────────────────
        $driveFilePath = (string)($queueItem['drive_file_path'] ?? '');

        if ($driveFilePath !== '') {
            if (!isAllowedAttachmentPath($driveFilePath)) {
                // Log but treat as permanent (admin config issue, not retryable)
                logEvent('WARN', "Blocked unsafe attachment path: {$driveFilePath} for queue_id={$queueItem['id']}");
                return [
                    'success'   => false,
                    'permanent' => true,
                    'error'     => 'Attachment path is outside the allowed directories.',
                ];
            }

            if (!file_exists($driveFilePath) || !is_readable($driveFilePath)) {
                logEvent('WARN', "Attachment not found: {$driveFilePath}");
                // Continue without attachment rather than failing the email entirely;
                // log the warning so the admin can investigate.
            } else {
                $mail->addAttachment($driveFilePath, basename($driveFilePath));
            }
        }

        // ── Send ──────────────────────────────────────────────────────────
        if (!$mail->send()) {
            throw new MailerException('PHPMailer send() failed: ' . $mail->ErrorInfo);
        }

        // ── Log to sent_emails_new ────────────────────────────────────────
        $emailUuid = generateUuidV4();
        $attachmentCount = ($driveFilePath !== '' && file_exists($driveFilePath)) ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO sent_emails_new (
                email_uuid, user_id, sender_email, recipient_email, recipient_name,
                subject, article_title, message_content,
                closing_wish, sender_name, sender_designation, additional_info,
                cc_emails, bcc_emails, attachment_count,
                sent_at, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                '', '', ?,
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            $emailUuid,
            $userId,
            $smtpUser,
            $recipientEmail,
            $recipientName,
            $subject,
            $articleTitle,
            $messageContent,
            $closingWish,
            $senderName,
            $senderDesig,
            $additionalInfo,
            $attachmentCount,
        ]);

        $sentEmailId = (int)$pdo->lastInsertId();

        // ── Log attachment record ─────────────────────────────────────────
        if ($attachmentCount > 0) {
            $ext      = strtolower(pathinfo($driveFilePath, PATHINFO_EXTENSION));
            $mimeType = getMimeTypeFromExtension($ext);

            $pdo->prepare("
                INSERT INTO sent_email_attachments_new (
                    sent_email_id, email_uuid, original_filename,
                    stored_filename, file_path, file_size,
                    mime_type, file_extension, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $sentEmailId,
                $emailUuid,
                basename($driveFilePath),
                basename($driveFilePath),
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

        // Classify: permanent failures should not be retried.
        $permanent = isSmtpPermanentFailure($errorMessage);

        logEvent('ERROR',
            sprintf('Send failed — recipient=%s, queue_id=%d, permanent=%s, error=%s',
                $queueItem['recipient_email'] ?? 'unknown',
                $queueItem['id']              ?? 0,
                $permanent ? 'yes' : 'no',
                $errorMessage
            )
        );

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
 * Creates and fully configures a PHPMailer instance.
 * Call once per batch; keep alive for the entire batch.
 */
function buildMailer(string $smtpUser, string $smtpPass, array $settings): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->SMTPDebug  = 0;            // Change to SMTP::DEBUG_SERVER for SMTP tracing
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    // SSL settings for compatibility with shared hosting certificates
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    // Connection timeout — fail fast rather than hanging
    $mail->Timeout = 20;

    return $mail;
}

// ═══════════════════════════════════════════════════════════════════════════
//  EMAIL BODY BUILDERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Builds the HTML email body from the template (or a fallback).
 */
function buildEmailBody(
    string $articleTitle,
    string $messageContent,
    string $closingWish,
    string $senderName,
    string $senderDesig,
    string $additionalInfo
): string {
    $templatePath = __DIR__ . '/templates/template1.html';

    // Safely escape all dynamic values for HTML context
    $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if (file_exists($templatePath)) {
        $template = file_get_contents($templatePath);

        return str_replace(
            ['{{articletitle}}', '{{MESSAGE}}', '{{SIGNATURE_WISH}}',
             '{{SIGNATURE_NAME}}', '{{SIGNATURE_DESIGNATION}}', '{{SIGNATURE_EXTRA}}'],
            [
                $e($articleTitle),
                nl2br($e($messageContent)),
                $e($closingWish),
                $e($senderName),
                $e($senderDesig),
                $e($additionalInfo),
            ],
            $template
        );
    }

    // Minimal fallback (template file missing).
    // Pre-compute all escaped values — PHP heredoc does not support closure calls.
    $safeTitle   = $e($articleTitle);
    $safeMessage = nl2br($e($messageContent));
    $safeWish    = $e($closingWish);
    $safeName    = $e($senderName);
    $safeDesig   = $e($senderDesig);
    $safeInfo    = $e($additionalInfo);

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; }
            h2   { color: #1a1a1a; border-bottom: 2px solid #0071E3; padding-bottom: 8px; }
            .sig { margin-top: 28px; border-top: 1px solid #ddd; padding-top: 14px; font-size: 14px; }
        </style>
    </head>
    <body>
        <h2>{$safeTitle}</h2>
        <p>{$safeMessage}</p>
        <div class="sig">
            <p>{$safeWish}<br>
            <strong>{$safeName}</strong><br>
            {$safeDesig}<br>
            <em>{$safeInfo}</em></p>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Converts an HTML email body into a clean plain-text alternative.
 *
 * Fixes the original bug: strip_tags() alone leaves &amp; &lt; etc. in the
 * output, which look terrible in plain-text email clients.
 */
function buildPlainTextBody(string $html): string
{
    // Replace block elements with newlines before stripping tags
    $text = preg_replace(['/<br\s*\/?>/i', '/<\/p>/i', '/<\/div>/i', '/<\/h[1-6]>/i'], "\n", $html);
    $text = strip_tags((string)$text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text);    // collapse excess blank lines

    return trim((string)$text);
}

// ═══════════════════════════════════════════════════════════════════════════
//  STALE LOCK RECOVERY
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Finds items that have been in "processing" longer than STALE_LOCK_SECONDS
 * and resets them to "pending" so they can be retried.
 *
 * This handles crashed/timed-out PHP processes gracefully.
 *
 * @return int  Number of items recovered.
 */
function recoverStaleItems(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare("
            UPDATE bulk_mail_queue
            SET  status        = 'pending',
                 retry_count   = retry_count + 1,
                 error_message = CONCAT(
                     COALESCE(error_message, ''),
                     ' [recovered from stale processing lock]'
                 ),
                 locked_until  = NULL
            WHERE user_id  = ?
              AND status   = 'processing'
              AND (
                   locked_until IS NOT NULL AND locked_until < NOW()
                OR processing_started_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
              )
        ");

        $stmt->execute([$userId, STALE_LOCK_SECONDS]);
        $recovered = $stmt->rowCount();

        if ($recovered > 0) {
            logEvent('INFO', "Recovered {$recovered} stale processing item(s) for user {$userId}");
        }

        return $recovered;

    } catch (Throwable $e) {
        logEvent('WARN', 'Stale lock recovery failed (non-fatal): ' . $e->getMessage());
        return 0;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Returns queue statistics for the current user.
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
 * Ensures the required retry-related columns exist.
 * Uses ADD COLUMN IF NOT EXISTS which is safe to run repeatedly.
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
            // ADD COLUMN IF NOT EXISTS is MySQL 8.0.3+; older versions may error — safe to ignore
            logEvent('WARN', 'Schema migration skipped (may already exist): ' . $e->getMessage());
        }
    }

    $checked = true;
}

/**
 * Returns true if the given file path is under one of the allowed attachment directories.
 * Prevents path traversal (e.g. ../../etc/passwd).
 */
function isAllowedAttachmentPath(string $path): bool
{
    // Resolve symlinks and ".." segments
    $real = realpath($path);

    if ($real === false) {
        return false;   // path does not exist — also blocked
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
 * Classifies an SMTP error message as permanent (do not retry) or transient.
 *
 * Permanent:   Invalid recipient (5xx), authentication failure, account suspended.
 * Transient:   Rate limits, connection timeouts, temporary server errors (4xx).
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
        if (str_contains($lower, $marker)) {
            return true;
        }
    }

    return false;
}

/**
 * Converts raw SMTP error messages into user-friendly descriptions.
 */
function humanReadableSmtpError(string $error): string
{
    $lower = strtolower($error);

    if (str_contains($lower, 'data not accepted') || str_contains($lower, '550')) {
        return 'SMTP rejected the email. Possible causes: invalid recipient, spam filters, or sending limit reached.';
    }
    if (str_contains($lower, 'connect') || str_contains($lower, 'timeout')) {
        return 'Could not connect to SMTP server. Check your internet connection.';
    }
    if (str_contains($lower, 'auth') || str_contains($lower, 'authenticate')) {
        return 'SMTP authentication failed. Check your email credentials.';
    }
    if (str_contains($lower, 'rate') || str_contains($lower, 'too many')) {
        return 'SMTP rate limit reached. Email will be retried automatically.';
    }
    if (str_contains($lower, 'invalid') && str_contains($lower, 'address')) {
        return 'Invalid email address — this email will not be retried.';
    }

    return $error;
}

/**
 * Maps file extensions to MIME types.
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
 * Structured log helper — writes to PHP error_log with a consistent prefix.
 *
 * @param  string $level  INFO | WARN | ERROR
 * @param  string $message
 */
function logEvent(string $level, string $message): void
{
    error_log(sprintf('[BULK_MAILER][%s][%s] %s', $level, date('Y-m-d H:i:s'), $message));
}

/**
 * Outputs a JSON-encoded response and terminates.
 */
function outputJson(array $data): never
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * generateUuidV4 — used for email_uuid.
 * Defined here as a fallback if db_config.php does not provide it.
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