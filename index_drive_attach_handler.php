<?php
/**
 * drive_attach_handler.php
 *
 * Accepts a `drive_path` POST parameter pointing to a file inside File_Drive,
 * registers it in the `attachments` table (or the temp-session array used by
 * upload_handler.php), and returns the same JSON shape that upload_handler.php
 * returns so the index.php frontend can treat it identically to a normal upload.
 *
 * Expected JSON response (success):
 * {
 *   "success": true,
 *   "id": 42,
 *   "encrypted_id": "...",
 *   "original_name": "report.pdf",
 *   "extension": "pdf",
 *   "file_size": 102400,
 *   "formatted_size": "100.0 KB",
 *   "deduplicated": false
 * }
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

// ── SECURITY: confirm the path is inside File_Drive ──────────────────────────
$driveDir  = rtrim(env('DRIVE_DIR', __DIR__ . '/File_Drive'), '/');
$realDrive = realpath($driveDir);
$realFile  = realpath($drivePath);

if ($realDrive === false || $realFile === false) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

// Path traversal check
if (strpos($realFile, $realDrive . DIRECTORY_SEPARATOR) !== 0 && $realFile !== $realDrive) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if (!is_file($realFile)) {
    echo json_encode(['success' => false, 'error' => 'Path is not a file']);
    exit;
}

// ── HELPERS ───────────────────────────────────────────────────────────────────
function fmtSize(int $b): string {
    if ($b < 1024)       return "$b B";
    if ($b < 1048576)    return round($b / 1024, 1) . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 1) . ' MB';
    return round($b / 1073741824, 2) . ' GB';
}

// ── GATHER FILE METADATA ──────────────────────────────────────────────────────
$originalName = basename($realFile);
$extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$fileSize     = filesize($realFile);
$mimeType     = mime_content_type($realFile) ?: 'application/octet-stream';
$fileHash     = md5_file($realFile);

// ── REGISTER IN DATABASE ──────────────────────────────────────────────────────
try {
    $pdo = getDatabaseConnection();
    if (!$pdo) throw new Exception('Database connection failed');

    $userId = getUserId($pdo, $userEmail);

    // ── DEDUPLICATION: check if this exact file (by hash) already exists ──────
    $stmt = $pdo->prepare("
        SELECT a.id, a.encrypted_id
        FROM   attachments a
        WHERE  a.file_hash = ?
        LIMIT  1
    ");
    $stmt->execute([$fileHash]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Re-use the existing attachment record
        $attachmentId  = $existing['id'];
        $encryptedId   = $existing['encrypted_id'];
        $deduplicated  = true;
    } else {
        // Insert a new attachment record pointing at the drive file directly
        // (no copy needed — the file already lives in File_Drive)
        $attachmentUuid = generateUuidV4();
        $encryptedId    = null; // computed after insert

        // Detect storage_path column name used by the project
        $colCheck = $pdo->query("SHOW COLUMNS FROM attachments");
        $cols = $colCheck->fetchAll(PDO::FETCH_COLUMN);

        $hasStoragePath  = in_array('storage_path', $cols);
        $hasFilePath     = in_array('file_path', $cols);
        $hasFileUuid     = in_array('file_uuid', $cols);
        $hasAttachUuid   = in_array('attachment_uuid', $cols);
        $hasOriginalName = in_array('original_name', $cols);
        $hasFileName     = in_array('file_name', $cols);

        $pathCol  = $hasStoragePath  ? 'storage_path'  : 'file_path';
        $nameCol  = $hasOriginalName ? 'original_name' : 'file_name';
        $uuidCol  = $hasAttachUuid   ? 'attachment_uuid' : ($hasFileUuid ? 'file_uuid' : null);

        // Build dynamic INSERT
        $insertCols   = [$pathCol, $nameCol, 'file_size', 'mime_type', 'extension', 'file_hash', 'uploaded_at'];
        $insertVals   = [$realFile, $originalName, $fileSize, $mimeType, $extension, $fileHash, date('Y-m-d H:i:s')];
        $insertParams = array_fill(0, count($insertCols), '?');

        if ($uuidCol) {
            $insertCols[]   = $uuidCol;
            $insertVals[]   = $attachmentUuid;
            $insertParams[] = '?';
        }

        $sql = 'INSERT INTO attachments (' . implode(',', $insertCols) . ')
                VALUES (' . implode(',', $insertParams) . ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertVals);
        $attachmentId = (int)$pdo->lastInsertId();

        if (!$attachmentId) throw new Exception('Failed to insert attachment record');

        // Generate & store encrypted ID
        $encryptedId = encryptFileId($attachmentId);
        $pdo->prepare("UPDATE attachments SET encrypted_id = ? WHERE id = ?")
            ->execute([$encryptedId, $attachmentId]);

        $deduplicated = false;
    }

    // ── Grant access to the current user ─────────────────────────────────────
    if ($userId) {
        // Check if access already exists
        $check = $pdo->prepare("SELECT id FROM user_attachment_access WHERE attachment_id = ? AND user_id = ? LIMIT 1");
        $check->execute([$attachmentId, $userId]);
        if (!$check->fetch()) {
            $pdo->prepare("
                INSERT INTO user_attachment_access (attachment_id, user_id, access_type, created_at)
                VALUES (?, ?, 'owner', NOW())
            ")->execute([$attachmentId, $userId]);
        }
    }

    // ── Store in session temp_attachments (mirrors upload_handler.php) ────────
    if (!isset($_SESSION['temp_attachments'])) {
        $_SESSION['temp_attachments'] = [];
    }
    $_SESSION['temp_attachments'][] = $attachmentId;

    echo json_encode([
        'success'        => true,
        'id'             => $attachmentId,
        'encrypted_id'   => $encryptedId,
        'original_name'  => $originalName,
        'extension'      => $extension,
        'file_size'      => $fileSize,
        'formatted_size' => fmtSize($fileSize),
        'deduplicated'   => $deduplicated,
        'source'         => 'drive'
    ]);

} catch (Exception $e) {
    error_log('drive_attach_handler error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}