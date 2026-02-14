<?php
/**
 * drive_attach_handler.php
 *
 * Accepts a `drive_path` POST parameter pointing to a file inside File_Drive,
 * registers it in the `attachments` table, and returns the same JSON shape
 * that upload_handler.php returns — so index.php / send.php need no changes.
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

// ── AUTH GUARD
if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userEmail = $_SESSION['smtp_user'];

// ── VALIDATE INPUT
$drivePath = isset($_POST['drive_path']) ? trim($_POST['drive_path']) : '';
if ($drivePath === '') {
    echo json_encode(['success' => false, 'error' => 'No drive_path provided']);
    exit;
}

// ── SECURITY: confirm the path is inside File_Drive
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

// ── HELPER
function dah_fmtSize(int $b): string {
    if ($b < 1024)       return "$b B";
    if ($b < 1048576)    return round($b/1024,1).' KB';
    if ($b < 1073741824) return round($b/1048576,1).' MB';
    return round($b/1073741824,2).' GB';
}

// ── FILE METADATA
$originalName = basename($realFile);
$extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$fileSize     = (int)filesize($realFile);
$mimeType     = mime_content_type($realFile) ?: 'application/octet-stream';
$fileHash     = md5_file($realFile);

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) throw new Exception('Database connection failed');

    $userId = getUserId($pdo, $userEmail);

    // Discover actual columns once — avoids every "unknown column" error
    $colRows = $pdo->query("SHOW COLUMNS FROM attachments")->fetchAll(PDO::FETCH_COLUMN);
    $hasCols = array_flip($colRows);

    // ── DEDUPLICATION (only if file_hash column exists)
    $deduplicated = false;
    $attachmentId = null;

    if (isset($hasCols['file_hash'])) {
        $chk = $pdo->prepare("SELECT id FROM attachments WHERE file_hash = ? LIMIT 1");
        $chk->execute([$fileHash]);
        $existing = $chk->fetch();
        if ($existing) {
            $attachmentId = (int)$existing['id'];
            $deduplicated = true;
        }
    }

    // ── INSERT if not deduplicated
    if (!$deduplicated) {
        $pathCol = isset($hasCols['storage_path']) ? 'storage_path' : 'file_path';
        $nameCol = isset($hasCols['original_name']) ? 'original_name' : 'file_name';

        $cols = [];
        $vals = [];

        $cols[] = $pathCol;    $vals[] = $realFile;
        $cols[] = $nameCol;    $vals[] = $originalName;
        $cols[] = 'file_size'; $vals[] = $fileSize;

        if (isset($hasCols['mime_type']))  { $cols[] = 'mime_type';  $vals[] = $mimeType; }
        if (isset($hasCols['extension']))  { $cols[] = 'extension';  $vals[] = $extension; }
        if (isset($hasCols['file_hash']))  { $cols[] = 'file_hash';  $vals[] = $fileHash; }

        if (isset($hasCols['attachment_uuid'])) { $cols[] = 'attachment_uuid'; $vals[] = generateUuidV4(); }
        elseif (isset($hasCols['file_uuid']))   { $cols[] = 'file_uuid';       $vals[] = generateUuidV4(); }

        if (isset($hasCols['uploaded_at']))    { $cols[] = 'uploaded_at';  $vals[] = date('Y-m-d H:i:s'); }
        elseif (isset($hasCols['created_at'])) { $cols[] = 'created_at';   $vals[] = date('Y-m-d H:i:s'); }

        $ph  = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO attachments ('.implode(',', $cols).') VALUES ('.$ph.')';
        $pdo->prepare($sql)->execute($vals);
        $attachmentId = (int)$pdo->lastInsertId();

        if (!$attachmentId) throw new Exception('Failed to insert attachment record');

        // Store encrypted_id back only if that column exists in the table
        if (isset($hasCols['encrypted_id'])) {
            $pdo->prepare("UPDATE attachments SET encrypted_id = ? WHERE id = ?")
                ->execute([encryptFileId($attachmentId), $attachmentId]);
        }
    }

    // Always derive encrypted_id from the numeric id — never read it from DB
    $encryptedId = encryptFileId($attachmentId);

    // ── Grant user access record
    if ($userId) {
        $ac = $pdo->prepare("SELECT id FROM user_attachment_access WHERE attachment_id = ? AND user_id = ? LIMIT 1");
        $ac->execute([$attachmentId, $userId]);
        if (!$ac->fetch()) {
            $pdo->prepare("INSERT INTO user_attachment_access (attachment_id, user_id, access_type, created_at) VALUES (?,?,'owner',NOW())")
                ->execute([$attachmentId, $userId]);
        }
    }

    // ── Mirror upload_handler session behaviour
    if (!isset($_SESSION['temp_attachments'])) $_SESSION['temp_attachments'] = [];
    $_SESSION['temp_attachments'][] = $attachmentId;

    echo json_encode([
        'success'        => true,
        'id'             => $attachmentId,
        'encrypted_id'   => $encryptedId,
        'original_name'  => $originalName,
        'extension'      => $extension,
        'file_size'      => $fileSize,
        'formatted_size' => dah_fmtSize($fileSize),
        'deduplicated'   => $deduplicated,
        'source'         => 'drive',
    ]);

} catch (Exception $e) {
    error_log('drive_attach_handler error: '.$e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}