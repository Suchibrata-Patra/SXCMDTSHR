<?php
/**
 * drive_attach_handler.php
 *
 * Registers a File_Drive file in the `attachments` table and returns
 * the same JSON shape that upload_handler.php returns, so send.php
 * treats it identically to a manually uploaded file.
 *
 * attachments table columns (from live schema):
 *   id, file_uuid, file_hash, original_filename, file_extension,
 *   mime_type, file_size, storage_path, storage_type,
 *   reference_count, uploaded_at, last_accessed
 *
 * Note: user_attachment_access requires a non-null email_id (FK to emails),
 * so we skip that insert here — it is linked when the email is sent.
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

// ── AUTH GUARD ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userEmail = $_SESSION['smtp_user'];

// ── VALIDATE INPUT ────────────────────────────────────────────────────────────
$drivePath = isset($_POST['drive_path']) ? trim($_POST['drive_path']) : '';
if ($drivePath === '') {
    echo json_encode(['success' => false, 'error' => 'No drive_path provided']);
    exit;
}

// ── SECURITY: path must be inside File_Drive ──────────────────────────────────
$driveDir  = rtrim(env('DRIVE_DIR', __DIR__ . '/File_Drive'), '/');
$realDrive = realpath($driveDir);
$realFile  = realpath($drivePath);

if (!$realDrive || !$realFile) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}
if (strpos($realFile, $realDrive . DIRECTORY_SEPARATOR) !== 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}
if (!is_file($realFile)) {
    echo json_encode(['success' => false, 'error' => 'Path is not a file']);
    exit;
}

// ── FILE METADATA ─────────────────────────────────────────────────────────────
$originalFilename = basename($realFile);
$fileExtension    = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
$fileSize         = (int) filesize($realFile);
$mimeType         = mime_content_type($realFile) ?: 'application/octet-stream';
// Use SHA-256 to match the schema comment ("SHA256 hash")
$fileHash         = hash_file('sha256', $realFile);

function dah_fmtSize(int $b): string {
    if ($b < 1024)       return "$b B";
    if ($b < 1048576)    return round($b / 1024, 1)      . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 1)   . ' MB';
    return                      round($b / 1073741824, 2) . ' GB';
}

// ── DATABASE ──────────────────────────────────────────────────────────────────
try {
    $pdo = getDatabaseConnection();
    if (!$pdo) throw new Exception('Database connection failed');

    // ── DEDUPLICATION: same file already registered? ──────────────────────────
    $chk = $pdo->prepare("SELECT id FROM attachments WHERE file_hash = ? LIMIT 1");
    $chk->execute([$fileHash]);
    $existing = $chk->fetch();

    if ($existing) {
        $attachmentId = (int) $existing['id'];
        $deduplicated = true;

        // Bump reference_count
        $pdo->prepare("UPDATE attachments SET reference_count = reference_count + 1 WHERE id = ?")
            ->execute([$attachmentId]);
    } else {
        // ── INSERT using exact column names from the live schema ──────────────
        $fileUuid = generateUuidV4();

        $stmt = $pdo->prepare("
            INSERT INTO attachments
                (file_uuid, file_hash, original_filename, file_extension,
                 mime_type, file_size, storage_path, storage_type,
                 reference_count, uploaded_at)
            VALUES
                (?, ?, ?, ?,
                 ?, ?, ?, 'local',
                 1, NOW())
        ");
        $stmt->execute([
            $fileUuid,
            $fileHash,
            $originalFilename,
            $fileExtension,
            $mimeType,
            $fileSize,
            $realFile,      // storage_path — full absolute path to the drive file
        ]);

        $attachmentId = (int) $pdo->lastInsertId();
        if (!$attachmentId) throw new Exception('Failed to insert attachment record');

        $deduplicated = false;
    }

    // ── encryptFileId() is defined in db_config.php ───────────────────────────
    $encryptedId = encryptFileId($attachmentId);

    // ── Session temp list (mirrors upload_handler.php behaviour) ─────────────
    if (!isset($_SESSION['temp_attachments'])) {
        $_SESSION['temp_attachments'] = [];
    }
    $_SESSION['temp_attachments'][] = $attachmentId;

    echo json_encode([
        'success'        => true,
        'id'             => $attachmentId,
        'encrypted_id'   => $encryptedId,
        'original_name'  => $originalFilename,   // key name expected by index.php JS
        'extension'      => $fileExtension,
        'file_size'      => $fileSize,
        'formatted_size' => dah_fmtSize($fileSize),
        'deduplicated'   => $deduplicated,
        'source'         => 'drive',
    ]);

} catch (Exception $e) {
    error_log('drive_attach_handler error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}