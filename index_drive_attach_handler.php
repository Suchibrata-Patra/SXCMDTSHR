<?php
/**
 * drive_attach_handler.php
 *
 * Copies a File_Drive file into uploads/attachments/ (identical to what
 * upload_handler.php does), registers it in the `attachments` table, and
 * pushes the FULL array into $_SESSION['temp_attachments'] so send.php
 * finds it exactly the same way as a file uploaded from the browser.
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

// ── VALIDATE INPUT ────────────────────────────────────────────────────────────
$drivePath = isset($_POST['drive_path']) ? trim($_POST['drive_path']) : '';
if ($drivePath === '') {
    echo json_encode(['success' => false, 'error' => 'No drive_path provided']);
    exit;
}

// ── SECURITY: path must resolve inside File_Drive ─────────────────────────────
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
$fileHash         = hash_file('sha256', $realFile);

function dah_fmtSize(int $b): string {
    if ($b < 1024)       return "$b B";
    if ($b < 1048576)    return round($b / 1024, 1)      . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 1)   . ' MB';
    return                      round($b / 1073741824, 2) . ' GB';
}

// ── COPY FILE INTO uploads/attachments/YYYY/MM/uuid.ext ──────────────────────
// This is exactly what upload_handler.php does, so send.php's path logic works.
$uploadDir   = rtrim(env('UPLOAD_ATTACHMENTS_DIR', __DIR__ . '/uploads/attachments'), '/');
$dateSubDir  = date('Y') . '/' . date('m');
$destDir     = $uploadDir . '/' . $dateSubDir;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$fileUuid    = generateUuidV4();
$storedName  = $fileUuid . '.' . $fileExtension;          // e.g. abc123.pdf
$destPath    = $destDir . '/' . $storedName;              // absolute path
$relativePath = $dateSubDir . '/' . $storedName;          // e.g. 2026/02/abc123.pdf

// copy() — never moves the original Drive file
if (!copy($realFile, $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to copy file to upload directory']);
    exit;
}

// ── DATABASE: insert into attachments table ───────────────────────────────────
try {
    $pdo = getDatabaseConnection();
    if (!$pdo) throw new Exception('Database connection failed');

    // Check for deduplication by hash
    $chk = $pdo->prepare("SELECT id FROM attachments WHERE file_hash = ? LIMIT 1");
    $chk->execute([$fileHash]);
    $existing = $chk->fetch();

    if ($existing) {
        $attachmentId = (int) $existing['id'];
        $deduplicated = true;
        $pdo->prepare("UPDATE attachments SET reference_count = reference_count + 1 WHERE id = ?")
            ->execute([$attachmentId]);
    } else {
        // storage_path stores the relative path — same pattern as upload_handler
        $stmt = $pdo->prepare("
            INSERT INTO attachments
                (file_uuid, file_hash, original_filename, file_extension,
                 mime_type, file_size, storage_path, storage_type,
                 reference_count, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'local', 1, NOW())
        ");
        $stmt->execute([
            $fileUuid,
            $fileHash,
            $originalFilename,
            $fileExtension,
            $mimeType,
            $fileSize,
            $relativePath,   // relative path, matching upload_handler pattern
        ]);
        $attachmentId = (int) $pdo->lastInsertId();
        if (!$attachmentId) throw new Exception('Failed to insert attachment record');
        $deduplicated = false;
    }

    $encryptedId = encryptFileId($attachmentId);

    // ── Push the FULL array that send.php expects into session ────────────────
    // send.php looks for: id, path, original_name, file_size, extension, mime_type
    if (!isset($_SESSION['temp_attachments'])) {
        $_SESSION['temp_attachments'] = [];
    }
    $_SESSION['temp_attachments'][] = [
        'id'            => $attachmentId,
        'path'          => $relativePath,       // send.php prepends uploadDir to this
        'original_name' => $originalFilename,
        'file_size'     => $fileSize,
        'extension'     => $fileExtension,
        'mime_type'     => $mimeType,
        'encrypted_id'  => $encryptedId,
    ];

    echo json_encode([
        'success'        => true,
        'id'             => $attachmentId,
        'encrypted_id'   => $encryptedId,
        'original_name'  => $originalFilename,
        'extension'      => $fileExtension,
        'file_size'      => $fileSize,
        'formatted_size' => dah_fmtSize($fileSize),
        'deduplicated'   => $deduplicated,
        'source'         => 'drive',
    ]);

} catch (Exception $e) {
    // Clean up the copied file if DB insert failed
    if (file_exists($destPath)) @unlink($destPath);
    error_log('drive_attach_handler error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}