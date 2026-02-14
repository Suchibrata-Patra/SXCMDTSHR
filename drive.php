<?php
// ═══════════════════════════════════════════════════════════════════
//  drive.php  —  SXC MDTS Secure File Drive
//  Full CRUD + share links + multi-view + preview for any file type
// ═══════════════════════════════════════════════════════════════════
session_start();
require_once 'config.php';
require_once 'db_config.php';

// ── AUTH GUARD ────────────────────────────────────────────────────
$hasSessionAuth = isset($_SESSION['smtp_user']) && isset($_SESSION['smtp_pass']);
$hasEnvAuth     = !empty(env('SMTP_USERNAME')) && !empty(env('SMTP_PASSWORD'));
if (!$hasSessionAuth && !$hasEnvAuth) {
    header('Location: login.php');
    exit();
}
if (!$hasSessionAuth && $hasEnvAuth) {
    $_SESSION['smtp_user'] = env('SMTP_USERNAME');
    $_SESSION['smtp_pass'] = env('SMTP_PASSWORD');
}

$userEmail = $_SESSION['smtp_user'];

// ── CONFIG ────────────────────────────────────────────────────────
$driveDir   = rtrim(env('DRIVE_DIR', __DIR__ . '/File_Drive'), '/');
$baseUrl    = rtrim(env('DRIVE_BASE_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])), '/');
$maxUpload  = 50 * 1024 * 1024;  // 50 MB per file
$shareBase  = $baseUrl . '/drive.php';

// ── HELPERS ───────────────────────────────────────────────────────
function fmtBytes(int $b): string {
    if ($b < 1024)      return "$b B";
    if ($b < 1048576)   return round($b/1024,1).' KB';
    if ($b < 1073741824)return round($b/1048576,1).' MB';
    return round($b/1073741824,2).' GB';
}

function sanitizeName(string $n): string {
    // Get basename only (strip any path separators)
    $n = basename($n);
    // Remove truly dangerous characters: null bytes, slashes, backslashes, angle brackets, pipe, colon, asterisk, question mark, quote chars
    $n = preg_replace('/[\x00\x0a\x0d\/\\\\<>|:*?"\'`]/', '', $n);
    // Prevent double-dots (path traversal)
    $n = preg_replace('/\.\.+/', '.', $n);
    // Collapse multiple spaces
    $n = preg_replace('/\s+/', ' ', $n);
    // Trim leading/trailing spaces and dots
    $n = trim($n, ' .');
    // Ensure not empty
    if ($n === '') $n = 'file';
    return substr($n, 0, 200);
}

function safePath(string $dir, string $file): string|false {
    $base = realpath($dir);
    if (!$base) return false;
    // Construct and normalize the candidate path
    $candidate = $base . DIRECTORY_SEPARATOR . $file;
    // For existing files, use realpath; for non-existing, manually check containment
    if (file_exists($candidate)) {
        $real = realpath($candidate);
        if ($real && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return $real;
        }
        return false;
    }
    // For non-existing files (e.g. rename target check), verify no traversal
    // Normalize by resolving any .. or . components in file portion only
    $normalized = $base . DIRECTORY_SEPARATOR . $file;
    // Check the directory component resolves inside base
    $dirPart = realpath(dirname($normalized));
    if ($dirPart && $dirPart === $base) {
        return $normalized;
    }
    return false;
}

function buildShareToken(string $filename): string {
    return base64_encode($filename . '|' . hash_hmac('sha256', $filename, env('APP_KEY', 'sxc_mdts_drive_key')));
}

function verifyShareToken(string $token): string|false {
    $decoded = base64_decode($token);
    if (!$decoded) return false;
    $parts = explode('|', $decoded, 2);
    if (count($parts) !== 2) return false;
    [$filename, $mac] = $parts;
    $expected = hash_hmac('sha256', $filename, env('APP_KEY', 'sxc_mdts_drive_key'));
    return hash_equals($expected, $mac) ? $filename : false;
}

function getFileCategory(string $ext): string {
    $map = [
        'image'    => ['jpg','jpeg','png','gif','bmp','webp','svg','ico','tiff','avif'],
        'video'    => ['mp4','webm','ogg','avi','mkv','mov','flv','wmv'],
        'audio'    => ['mp3','wav','ogg','flac','aac','m4a','wma'],
        'pdf'      => ['pdf'],
        'doc'      => ['doc','docx','odt','rtf'],
        'sheet'    => ['xls','xlsx','ods','csv'],
        'slide'    => ['ppt','pptx','odp'],
        'code'     => ['php','js','ts','py','rb','java','c','cpp','cs','go','rs','html','css','scss','json','xml','yaml','yml','sh','bash','sql'],
        'notebook' => ['ipynb'],
        'archive'  => ['zip','rar','7z','tar','gz','bz2'],
        'text'     => ['txt','md','log','ini','env','conf'],
        'font'     => ['ttf','otf','woff','woff2'],
    ];
    foreach ($map as $cat => $exts) {
        if (in_array(strtolower($ext), $exts, true)) return $cat;
    }
    return 'other';
}

// Ensure drive directory exists (secure permissions)
if (!is_dir($driveDir)) {
    mkdir($driveDir, 0750, true);
    file_put_contents($driveDir . '/.htaccess', "Options -Indexes\nDeny from all\n");
}

// ── AJAX / ACTION HANDLER ─────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action) {
    header('Content-Type: application/json');

    // ── UPLOAD ────────────────────────────────────────────────────
    if ($action === 'upload') {
        $results = [];
        if (empty($_FILES['files']['name'][0])) {
            echo json_encode(['success'=>false,'error'=>'No files received']);
            exit();
        }
        $count = count($_FILES['files']['name']);
        for ($i = 0; $i < $count; $i++) {
            $err  = $_FILES['files']['error'][$i];
            $size = $_FILES['files']['size'][$i];
            $tmp  = $_FILES['files']['tmp_name'][$i];
            $orig = sanitizeName($_FILES['files']['name'][$i]);

            if ($err !== UPLOAD_ERR_OK) { $results[] = ['name'=>$orig,'ok'=>false,'error'=>"Upload error $err"]; continue; }
            if ($size > $maxUpload)     { $results[] = ['name'=>$orig,'ok'=>false,'error'=>'File too large (max 50 MB)']; continue; }
            if (!is_uploaded_file($tmp)) { $results[] = ['name'=>$orig,'ok'=>false,'error'=>'Invalid upload']; continue; }

            // Deduplicate name
            $dest = $orig;
            $base = pathinfo($orig, PATHINFO_FILENAME);
            $ext  = pathinfo($orig, PATHINFO_EXTENSION);
            $n = 1;
            while (file_exists($driveDir.'/'.$dest)) {
                $dest = $ext ? "{$base}_({$n}).{$ext}" : "{$base}_({$n})";
                $n++;
            }
            if (move_uploaded_file($tmp, $driveDir.'/'.$dest)) {
                chmod($driveDir.'/'.$dest, 0640);
                $results[] = ['name'=>$dest,'ok'=>true,'size'=>fmtBytes($size)];
            } else {
                $results[] = ['name'=>$orig,'ok'=>false,'error'=>'Could not save file'];
            }
        }
        echo json_encode(['success'=>true,'results'=>$results]);
        exit();
    }

    // ── LIST ──────────────────────────────────────────────────────
    if ($action === 'list') {
        $sort  = in_array($_GET['sort'] ?? 'name', ['name','size','date','type']) ? ($_GET['sort'] ?? 'name') : 'name';
        $order = ($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $q     = strtolower(trim($_GET['q'] ?? ''));
        $files = [];
        foreach (new DirectoryIterator($driveDir) as $f) {
            if ($f->isDot() || $f->isDir() || $f->getFilename() === '.htaccess') continue;
            $name = $f->getFilename();
            if ($q && !str_contains(strtolower($name), $q)) continue;
            $ext  = strtolower($f->getExtension());
            $files[] = [
                'name'     => $name,
                'size'     => $f->getSize(),
                'fsize'    => fmtBytes($f->getSize()),
                'ext'      => $ext,
                'cat'      => getFileCategory($ext),
                'mtime'    => $f->getMTime(),
                'date'     => date('d M Y, H:i', $f->getMTime()),
                'token'    => buildShareToken($name),
            ];
        }
        usort($files, function($a, $b) use ($sort, $order) {
            $cmp = match($sort) {
                'size' => $a['size'] <=> $b['size'],
                'date' => $a['mtime'] <=> $b['mtime'],
                'type' => strcmp($a['ext'], $b['ext']),
                default=> strnatcasecmp($a['name'], $b['name']),
            };
            return $order === 'desc' ? -$cmp : $cmp;
        });
        echo json_encode(['success'=>true,'files'=>array_values($files),'count'=>count($files)]);
        exit();
    }

    // ── RENAME ────────────────────────────────────────────────────
    if ($action === 'rename') {
        $old = sanitizeName($_POST['old'] ?? '');
        $new = sanitizeName($_POST['new'] ?? '');
        if (!$old || !$new) { echo json_encode(['success'=>false,'error'=>'Invalid name']); exit(); }
        $oldPath = safePath($driveDir, $old);
        if (!$oldPath) { echo json_encode(['success'=>false,'error'=>'Source not found']); exit(); }
        $newPath = $driveDir.'/'.$new;
        if (file_exists($newPath)) { echo json_encode(['success'=>false,'error'=>'Name already exists']); exit(); }
        rename($oldPath, $newPath);
        chmod($newPath, 0640);
        echo json_encode(['success'=>true,'token'=>buildShareToken($new)]);
        exit();
    }

    // ── DELETE ────────────────────────────────────────────────────
    if ($action === 'delete') {
        $names = (array)($_POST['names'] ?? []);
        $deleted = 0;
        foreach ($names as $n) {
            $p = safePath($driveDir, sanitizeName($n));
            if ($p && is_file($p)) { unlink($p); $deleted++; }
        }
        echo json_encode(['success'=>true,'deleted'=>$deleted]);
        exit();
    }

    // ── SHARE TOKEN ───────────────────────────────────────────────
    if ($action === 'share') {
        $name = sanitizeName($_GET['name'] ?? '');
        if (!$name) { echo json_encode(['success'=>false,'error'=>'No filename']); exit(); }
        echo json_encode(['success'=>true,'token'=>buildShareToken($name),'url'=>$shareBase.'?dl='.urlencode(buildShareToken($name))]);
        exit();
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit();
}

// ── PUBLIC SHARE DOWNLOAD ─────────────────────────────────────────
if (isset($_GET['dl'])) {
    $token    = $_GET['dl'];
    $filename = verifyShareToken($token);
    if (!$filename) { http_response_code(403); echo 'Invalid or expired link.'; exit(); }
    $path = safePath($driveDir, $filename);
    if (!$path) { http_response_code(404); echo 'File not found.'; exit(); }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    header("Content-Type: $mime");
    header('Content-Disposition: inline; filename="'.addslashes($filename).'"');
    header('Content-Length: '.filesize($path));
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit();
}

// ── IN-APP PREVIEW ────────────────────────────────────────────────
if (isset($_GET['preview'])) {
    if (!$hasSessionAuth && !$hasEnvAuth) { http_response_code(403); exit(); }
    $filename = sanitizeName($_GET['preview']);
    $path = safePath($driveDir, $filename);
    if (!$path) { http_response_code(404); echo 'File not found.'; exit(); }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    header("Content-Type: $mime");
    header('Content-Disposition: inline; filename="'.addslashes($filename).'"');
    header('Content-Length: '.filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($path);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php define('PAGE_TITLE', 'SXC MDTS | Drive'); include 'header.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --ink:       #1a1a2e;
            --ink-2:     #2d2d44;
            --ink-3:     #6b6b8a;
            --ink-4:     #a8a8c0;
            --bg:        #f0f0f7;
            --surface:   #ffffff;
            --surface-2: #f7f7fc;
            --border:    rgba(100,100,160,0.12);
            --border-2:  rgba(100,100,160,0.22);
            --blue:      #4f46e5;
            --blue-2:    #6366f1;
            --blue-glow: rgba(79,70,229,0.15);
            --red:       #ef4444;
            --green:     #10b981;
            --amber:     #f59e0b;
            --r:         10px;
            --r-lg:      16px;
            --shadow:    0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg: 0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:      cubic-bezier(.4,0,.2,1);
            --ease-spring: cubic-bezier(.34,1.56,.64,1);
        }

        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ── LAYOUT ─────────────────────────────────────────── */
        .app-shell  { display:flex; flex:1; overflow:hidden; }
        .main-col   { flex:1; display:flex; flex-direction:column; overflow:hidden; }

        /* ── TOP BAR ─────────────────────────────────────────── */
        .topbar {
            height: 60px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }
        .topbar-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .topbar-title .material-icons-round { font-size:20px; color:var(--blue); }
        .topbar-spacer { flex:1; }

        /* Search */
        .search-wrap {
            position: relative;
            width: 280px;
        }
        .search-wrap .material-icons-round {
            position: absolute;
            left: 10px; top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: var(--ink-4);
            pointer-events: none;
        }
        #searchInput {
            width: 100%;
            height: 36px;
            border: 1.5px solid var(--border-2);
            border-radius: 20px;
            padding: 0 12px 0 34px;
            font-family: inherit;
            font-size: 13px;
            background: var(--surface-2);
            color: var(--ink);
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        #searchInput:focus { border-color:var(--blue); box-shadow:0 0 0 3px var(--blue-glow); background:var(--surface); }
        #searchInput::placeholder { color:var(--ink-4); }

        /* View toggles */
        .view-btns {
            display: flex;
            background: var(--surface-2);
            border: 1.5px solid var(--border-2);
            border-radius: 8px;
            overflow: hidden;
        }
        .view-btn {
            width: 34px; height: 34px;
            border: none;
            background: transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-4);
            transition: all .18s;
        }
        .view-btn:hover  { color:var(--blue); background:rgba(79,70,229,.06); }
        .view-btn.active { color:var(--blue); background:rgba(79,70,229,.12); }
        .view-btn .material-icons-round { font-size:17px; }

        /* Sort */
        #sortSelect {
            height:36px; border:1.5px solid var(--border-2); border-radius:8px;
            padding:0 10px; font-family:inherit; font-size:13px;
            background:var(--surface-2); color:var(--ink); outline:none; cursor:pointer;
            transition: border-color .2s;
        }
        #sortSelect:focus { border-color:var(--blue); }

        /* Upload button */
        .btn-upload {
            height: 36px;
            padding: 0 16px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .18s, transform .12s var(--ease-spring), box-shadow .18s;
            white-space: nowrap;
        }
        .btn-upload:hover  { background:var(--blue-2); box-shadow:0 4px 12px var(--blue-glow); }
        .btn-upload:active { transform:scale(.96); }
        .btn-upload .material-icons-round { font-size:16px; }

        /* Delete selected */
        .btn-del-sel {
            height:36px; padding:0 14px;
            background:rgba(239,68,68,.08); color:var(--red);
            border:1.5px solid rgba(239,68,68,.2);
            border-radius:8px; font-family:inherit; font-size:13px;
            font-weight:600; cursor:pointer; display:none;
            align-items:center; gap:6px; transition:all .18s;
        }
        .btn-del-sel.show  { display:flex; }
        .btn-del-sel:hover { background:rgba(239,68,68,.14); }
        .btn-del-sel .material-icons-round { font-size:16px; }

        /* ── CONTENT AREA ─────────────────────────────────────── */
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }
        .content-area::-webkit-scrollbar { width:5px; }
        .content-area::-webkit-scrollbar-track { background:transparent; }
        .content-area::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:10px; }

        /* ── DROPZONE ─────────────────────────────────────────── */
        .drop-overlay {
            position:fixed; inset:0; z-index:2000;
            background:rgba(79,70,229,.18);
            backdrop-filter:blur(6px);
            display:none;
            align-items:center; justify-content:center;
            flex-direction:column; gap:16px;
        }
        .drop-overlay.show { display:flex; }
        .drop-card {
            background:white;
            border:3px dashed var(--blue);
            border-radius:24px;
            padding:60px 80px;
            text-align:center;
            animation: dropPulse 1.4s ease infinite;
        }
        @keyframes dropPulse {
            0%,100% { box-shadow:0 0 0 0 var(--blue-glow); }
            50%      { box-shadow:0 0 0 20px transparent; }
        }
        .drop-card .material-icons-round { font-size:56px; color:var(--blue); }
        .drop-card h2 { font-size:22px; font-weight:700; color:var(--ink); margin-top:12px; }
        .drop-card p  { color:var(--ink-3); font-size:14px; margin-top:4px; }

        /* ── STORAGE BAR ──────────────────────────────────────── */
        .storage-bar-wrap {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:var(--r);
            padding:14px 20px;
            margin-bottom:20px;
            display:flex;
            align-items:center;
            gap:16px;
        }
        .storage-bar-track {
            flex:1; height:6px;
            background:var(--surface-2);
            border-radius:3px;
            overflow:hidden;
        }
        .storage-bar-fill {
            height:100%;
            background:linear-gradient(90deg,var(--blue),var(--blue-2));
            border-radius:3px;
            transition:width .6s var(--ease);
        }
        .storage-label { font-size:12px; font-weight:600; color:var(--ink-3); white-space:nowrap; }

        /* ── FILTER CHIPS ─────────────────────────────────────── */
        .filter-row {
            display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;
        }
        .chip {
            height:30px; padding:0 12px;
            border:1.5px solid var(--border-2);
            border-radius:20px; font-family:inherit;
            font-size:12px; font-weight:600;
            color:var(--ink-3); background:var(--surface);
            cursor:pointer; transition:all .18s;
            display:flex; align-items:center; gap:5px;
        }
        .chip:hover  { border-color:var(--blue); color:var(--blue); background:var(--blue-glow); }
        .chip.active { border-color:var(--blue); color:var(--blue); background:rgba(79,70,229,.1); }
        .chip-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }

        /* ── EMPTY STATE ─────────────────────────────────────── */
        .empty-state {
            display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            padding:80px 0; gap:12px; text-align:center;
        }
        .empty-state .material-icons-round { font-size:56px; color:var(--ink-4); }
        .empty-state h3 { font-size:18px; font-weight:700; color:var(--ink-2); }
        .empty-state p  { font-size:14px; color:var(--ink-3); max-width:300px; }

        /* ── LOADING ─────────────────────────────────────────── */
        .loading-spinner {
            display:flex; align-items:center; justify-content:center;
            padding:80px; gap:12px; color:var(--ink-3); font-weight:600;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spin { animation:spin .8s linear infinite; display:inline-block; }

        /* ── ─────────────────────────────────────────────────── */
        /* LIST VIEW */
        /* ── ─────────────────────────────────────────────────── */
        #fileList.list-view .file-grid { display:none; }
        #fileList.list-view .file-table-wrap { display:block; }
        #fileList.grid-view .file-grid { display:grid; }
        #fileList.grid-view .file-table-wrap { display:none; }
        #fileList.compact-view .file-grid { display:none; }
        #fileList.compact-view .file-table-wrap { display:block; }

        .file-table-wrap {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:var(--r-lg);
            overflow:hidden;
        }
        .file-table {
            width:100%; border-collapse:collapse;
        }
        .file-table thead th {
            background:var(--surface-2);
            padding:10px 16px;
            text-align:left;
            font-size:11px; font-weight:700;
            color:var(--ink-3);
            text-transform:uppercase; letter-spacing:.6px;
            border-bottom:1px solid var(--border);
            white-space:nowrap;
            user-select:none;
        }
        .file-table thead th.sortable { cursor:pointer; }
        .file-table thead th.sortable:hover { color:var(--blue); }
        .file-table thead th.sort-active { color:var(--blue); }

        .file-table tbody tr {
            border-bottom:1px solid var(--border);
            transition:background .14s;
        }
        .file-table tbody tr:last-child { border-bottom:none; }
        .file-table tbody tr:hover { background:var(--surface-2); }
        .file-table tbody tr.selected { background:rgba(79,70,229,.05); }

        .file-table td {
            padding:10px 16px;
            font-size:13.5px;
            vertical-align:middle;
        }
        .file-table td.td-check { width:40px; padding-right:0; }
        .file-table td.td-icon  { width:44px; padding-right:4px; }
        .file-table td.td-name  { font-weight:500; max-width:300px; }
        .file-table td.td-size  { color:var(--ink-3); width:90px; font-size:12px; font-family:'DM Mono',monospace; }
        .file-table td.td-date  { color:var(--ink-3); width:160px; font-size:12px; }
        .file-table td.td-type  { width:80px; }
        .file-table td.td-act   { width:160px; text-align:right; }

        /* Compact view tweaks */
        #fileList.compact-view .file-table td { padding:6px 16px; }
        #fileList.compact-view .file-icon-cell .file-icon-img { width:22px; height:22px; }

        .fname-text {
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
            max-width:280px; display:inline-block; vertical-align:middle;
        }

        /* Checkbox */
        .cb-file {
            width:16px; height:16px; accent-color:var(--blue); cursor:pointer;
        }

        /* Row action buttons */
        .row-actions { display:flex; align-items:center; justify-content:flex-end; gap:6px; opacity:0; transition:opacity .14s; }
        .file-table tbody tr:hover .row-actions { opacity:1; }
        .file-table tbody tr.selected .row-actions { opacity:1; }
        .act-btn {
            width:28px; height:28px;
            border:none; border-radius:6px;
            background:transparent; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            color:var(--ink-3); transition:all .15s;
        }
        .act-btn:hover { background:var(--surface-2); color:var(--blue); }
        .act-btn.del:hover { background:rgba(239,68,68,.08); color:var(--red); }
        .act-btn .material-icons-round { font-size:16px; }

        /* ── ─────────────────────────────────────────────────── */
        /* GRID VIEW */
        /* ── ─────────────────────────────────────────────────── */
        .file-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }
        .grid-card {
            background:var(--surface);
            border:1.5px solid var(--border);
            border-radius:var(--r-lg);
            padding:20px 16px 14px;
            display:flex; flex-direction:column;
            align-items:center; gap:10px;
            cursor:pointer;
            transition:all .18s var(--ease);
            position:relative;
            overflow:hidden;
        }
        .grid-card::before {
            content:'';
            position:absolute; inset:0;
            background:linear-gradient(135deg,rgba(79,70,229,.04),transparent);
            opacity:0; transition:opacity .2s;
        }
        .grid-card:hover { border-color:var(--blue); box-shadow:var(--shadow-lg); transform:translateY(-2px); }
        .grid-card:hover::before { opacity:1; }
        .grid-card.selected { border-color:var(--blue); background:rgba(79,70,229,.04); }
        .grid-card-check {
            position:absolute; top:8px; left:8px;
            opacity:0; transition:opacity .14s;
        }
        .grid-card:hover .grid-card-check, .grid-card.selected .grid-card-check { opacity:1; }

        .grid-icon { width:56px; height:56px; object-fit:contain; flex-shrink:0; }
        .grid-name {
            font-size:12px; font-weight:600; color:var(--ink);
            text-align:center; line-height:1.4;
            overflow:hidden; text-overflow:ellipsis;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
            word-break:break-all; width:100%;
        }
        .grid-meta { font-size:11px; color:var(--ink-4); font-family:'DM Mono',monospace; }
        .grid-actions {
            display:flex; gap:6px; margin-top:4px;
            opacity:0; transition:opacity .15s; width:100%; justify-content:center;
        }
        .grid-card:hover .grid-actions { opacity:1; }

        /* ── FILE ICONS ──────────────────────────────────────── */
        .file-icon-img { width:28px; height:28px; object-fit:contain; }
        .file-type-badge {
            display:inline-block; padding:1px 7px;
            border-radius:4px; font-size:10px; font-weight:700;
            font-family:'DM Mono',monospace; text-transform:uppercase;
            letter-spacing:.3px; background:var(--surface-2);
            border:1px solid var(--border-2); color:var(--ink-3);
        }

        /* ── MODALS ──────────────────────────────────────────── */
        .modal-backdrop {
            position:fixed; inset:0; z-index:1000;
            background:rgba(20,20,40,.55);
            backdrop-filter:blur(4px);
            display:none; align-items:center; justify-content:center;
        }
        .modal-backdrop.show { display:flex; }

        .modal {
            background:var(--surface);
            border-radius:var(--r-lg);
            box-shadow:var(--shadow-lg);
            min-width:400px;
            max-width:90vw;
            animation:modalIn .22s var(--ease-spring) forwards;
        }
        @keyframes modalIn { from{opacity:0;transform:scale(.92) translateY(8px)} to{opacity:1;transform:none} }

        .modal-header {
            padding:20px 24px 16px;
            border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:10px;
        }
        .modal-header h3 { font-size:16px; font-weight:700; flex:1; }
        .modal-close {
            width:28px; height:28px; border:none;
            background:transparent; cursor:pointer; border-radius:6px;
            display:flex; align-items:center; justify-content:center;
            color:var(--ink-3); transition:all .15s;
        }
        .modal-close:hover { background:var(--surface-2); color:var(--ink); }
        .modal-close .material-icons-round { font-size:18px; }
        .modal-body  { padding:20px 24px; }
        .modal-footer { padding:12px 24px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); }

        .modal-input {
            width:100%; height:40px;
            border:1.5px solid var(--border-2);
            border-radius:8px; padding:0 12px;
            font-family:inherit; font-size:14px;
            color:var(--ink); outline:none;
            transition:border-color .2s, box-shadow .2s;
        }
        .modal-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px var(--blue-glow); }

        .share-url-row {
            display:flex; gap:8px; align-items:center; margin-top:12px;
        }
        .share-url-input {
            flex:1; height:38px; border:1.5px solid var(--border-2);
            border-radius:8px; padding:0 10px;
            font-family:'DM Mono',monospace; font-size:12px;
            color:var(--ink-2); background:var(--surface-2); outline:none;
        }
        .share-copy-btn {
            height:38px; padding:0 14px;
            background:var(--blue); color:white;
            border:none; border-radius:8px; font-family:inherit;
            font-size:13px; font-weight:600; cursor:pointer;
            display:flex; align-items:center; gap:6px;
            transition:background .18s;
        }
        .share-copy-btn:hover { background:var(--blue-2); }
        .share-copy-btn .material-icons-round { font-size:15px; }
        .share-qr-hint { font-size:12px; color:var(--ink-3); margin-top:8px; }

        /* preview modal bigger */
        .modal.preview-modal { min-width:min(700px,92vw); max-height:90vh; display:flex; flex-direction:column; }
        .preview-body { flex:1; overflow:auto; padding:0; display:flex; align-items:center; justify-content:center; background:#0d0d1a; min-height:300px; }
        .preview-body iframe, .preview-body img, .preview-body video, .preview-body audio { max-width:100%; max-height:70vh; }
        .preview-body .preview-code { width:100%; height:60vh; overflow:auto; padding:20px; }
        .preview-body .preview-code pre { font-family:'DM Mono',monospace; font-size:13px; line-height:1.6; color:#e2e8f0; white-space:pre-wrap; word-break:break-all; }
        .preview-nb { width:100%; padding:20px; background:var(--surface); font-size:13px; line-height:1.7; }

        /* Btn styles */
        .btn { height:36px; padding:0 16px; border-radius:8px; font-family:inherit; font-size:13px; font-weight:600; cursor:pointer; border:none; transition:all .18s; display:inline-flex; align-items:center; gap:6px; }
        .btn-ghost { background:var(--surface-2); color:var(--ink-2); border:1.5px solid var(--border-2); }
        .btn-ghost:hover { background:var(--bg); }
        .btn-prim { background:var(--blue); color:white; }
        .btn-prim:hover { background:var(--blue-2); }
        .btn-danger { background:var(--red); color:white; }
        .btn-danger:hover { background:#dc2626; }
        .btn .material-icons-round { font-size:15px; }

        /* Toast */
        #toastContainer { position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px; }
        .toast {
            background:var(--ink); color:white;
            padding:10px 16px; border-radius:10px;
            font-size:13px; font-weight:500;
            display:flex; align-items:center; gap:8px;
            box-shadow:0 4px 20px rgba(0,0,0,.25);
            animation:toastIn .25s var(--ease-spring) forwards;
            max-width:320px;
        }
        .toast.success { background:#059669; }
        .toast.error   { background:var(--red); }
        .toast.info    { background:var(--blue); }
        @keyframes toastIn { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:none} }
        @keyframes toastOut { to{opacity:0;transform:translateX(20px)} }
        .toast .material-icons-round { font-size:16px; }

        /* Progress bar */
        .upload-progress-wrap {
            position:fixed; bottom:0; left:0; right:0; z-index:500;
            background:var(--surface); border-top:1px solid var(--border);
            padding:12px 24px; display:none;
            align-items:center; gap:16px;
        }
        .upload-progress-wrap.show { display:flex; }
        .progress-track { flex:1; height:6px; background:var(--bg); border-radius:3px; overflow:hidden; }
        .progress-fill  { height:100%; background:linear-gradient(90deg,var(--blue),var(--blue-2)); border-radius:3px; transition:width .3s var(--ease); }
        .progress-label { font-size:12px; font-weight:600; color:var(--ink-3); white-space:nowrap; }

        /* Staggered grid animation */
        .grid-card, .file-table tbody tr {
            animation: rowFadeIn .2s var(--ease) both;
        }
        @keyframes rowFadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }

        /* Thumbnail toggle button */
        .btn-thumb-toggle {
            height: 36px;
            padding: 0 12px;
            background: var(--surface-2);
            color: var(--ink-3);
            border: 1.5px solid var(--border-2);
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all .18s;
            white-space: nowrap;
        }
        .btn-thumb-toggle:hover  { border-color:var(--blue); color:var(--blue); background:var(--blue-glow); }
        .btn-thumb-toggle.active { border-color:var(--blue); color:var(--blue); background:rgba(79,70,229,.1); }
        .btn-thumb-toggle .material-icons-round { font-size:16px; }

        /* Grid thumbnail mode */
        .file-grid.thumb-mode .grid-card { padding:0 0 12px; overflow:hidden; }
        .file-grid.thumb-mode .grid-thumb-wrap {
            width:100%; aspect-ratio:16/10;
            background:var(--surface-2);
            overflow:hidden;
            display:flex; align-items:center; justify-content:center;
            border-bottom:1px solid var(--border);
            margin-bottom:10px;
            position:relative;
        }
        .file-grid.thumb-mode .grid-thumb-img {
            width:100%; height:100%; object-fit:cover;
            transition:transform .3s var(--ease);
        }
        .grid-card:hover .file-grid.thumb-mode .grid-thumb-img { transform:scale(1.04); }
        .file-grid.thumb-mode .grid-thumb-icon {
            font-size:40px; color:var(--ink-4);
            display:flex; align-items:center; justify-content:center;
            width:100%; height:100%;
        }
        .file-grid.thumb-mode .grid-thumb-icon img {
            width:48px; height:48px; object-fit:contain;
        }
        .file-grid.thumb-mode .grid-name  { padding:0 12px; }
        .file-grid.thumb-mode .grid-meta  { padding:0 12px; }
        .file-grid.thumb-mode .grid-actions { padding:0 12px; }
        .file-grid.thumb-mode .grid-icon { display:none; }
        .file-grid.thumb-mode .grid-card-check { top:6px; left:6px; }

        /* Non-thumb mode: hide thumb wrap */
        .grid-thumb-wrap { display:none; }
        .file-grid.thumb-mode .grid-thumb-wrap { display:flex; }

        /* Hidden file input */
        #fileInput { display:none; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php include 'sidebar.php'; ?>
    <div class="main-col">

        <!-- TOP BAR -->
        <div class="topbar">
            <div class="topbar-title">
                <span class="material-icons-round">add_to_drive</span>
                My Drive
            </div>
            <div class="topbar-spacer"></div>
            <div class="search-wrap">
                <span class="material-icons-round">search</span>
                <input type="text" id="searchInput" placeholder="Search files…" autocomplete="off">
            </div>
            <div class="view-btns">
                <button class="view-btn active" id="btnList" title="List view" onclick="setView('list')">
                    <span class="material-icons-round">format_list_bulleted</span>
                </button>
                <button class="view-btn" id="btnGrid" title="Grid view" onclick="setView('grid')">
                    <span class="material-icons-round">grid_view</span>
                </button>
                <button class="view-btn" id="btnCompact" title="Compact view" onclick="setView('compact')">
                    <span class="material-icons-round">density_small</span>
                </button>
            </div>
            <!-- Thumbnail toggle — only shown in grid view -->
            <button class="btn-thumb-toggle" id="btnThumbToggle" title="Toggle thumbnails" onclick="toggleThumbnails()" style="display:none">
                <span class="material-icons-round" id="thumbToggleIcon">image</span>
                <span id="thumbToggleLabel">Thumbnails</span>
            </button>
            <select id="sortSelect" onchange="applySort(this.value)">
                <option value="name|asc">Name A→Z</option>
                <option value="name|desc">Name Z→A</option>
                <option value="date|desc">Newest first</option>
                <option value="date|asc">Oldest first</option>
                <option value="size|desc">Largest first</option>
                <option value="size|asc">Smallest first</option>
                <option value="type|asc">By type</option>
            </select>
            <button class="btn-del-sel" id="btnDelSel" onclick="deleteSelected()">
                <span class="material-icons-round">delete</span>
                Delete (<span id="selCount">0</span>)
            </button>
            <button class="btn-upload" onclick="document.getElementById('fileInput').click()">
                <span class="material-icons-round">upload</span>
                Upload Files
            </button>
            <input type="file" id="fileInput" multiple onchange="uploadFiles(this.files)">
        </div>

        <!-- MAIN CONTENT -->
        <div class="content-area" id="contentArea">
            <!-- Storage summary -->
            <div class="storage-bar-wrap" id="storageBar">
                <span class="material-icons-round" style="color:var(--blue);font-size:18px">storage</span>
                <div class="storage-bar-track">
                    <div class="storage-bar-fill" id="storageFill" style="width:0%"></div>
                </div>
                <span class="storage-label" id="storageLabel">Calculating…</span>
            </div>

            <!-- Category filter chips -->
            <div class="filter-row" id="filterRow">
                <button class="chip active" data-cat="all" onclick="setFilter('all',this)">All files</button>
                <button class="chip" data-cat="image"    onclick="setFilter('image',this)"><span class="chip-dot" style="background:#f59e0b"></span>Images</button>
                <button class="chip" data-cat="pdf"      onclick="setFilter('pdf',this)"><span class="chip-dot" style="background:#ef4444"></span>PDFs</button>
                <button class="chip" data-cat="doc"      onclick="setFilter('doc',this)"><span class="chip-dot" style="background:#3b82f6"></span>Documents</button>
                <button class="chip" data-cat="sheet"    onclick="setFilter('sheet',this)"><span class="chip-dot" style="background:#10b981"></span>Sheets</button>
                <button class="chip" data-cat="video"    onclick="setFilter('video',this)"><span class="chip-dot" style="background:#8b5cf6"></span>Video</button>
                <button class="chip" data-cat="audio"    onclick="setFilter('audio',this)"><span class="chip-dot" style="background:#ec4899"></span>Audio</button>
                <button class="chip" data-cat="code"     onclick="setFilter('code',this)"><span class="chip-dot" style="background:#06b6d4"></span>Code</button>
                <button class="chip" data-cat="notebook" onclick="setFilter('notebook',this)"><span class="chip-dot" style="background:#f97316"></span>Notebooks</button>
                <button class="chip" data-cat="archive"  onclick="setFilter('archive',this)"><span class="chip-dot" style="background:#6b7280"></span>Archives</button>
            </div>

            <!-- File listing -->
            <div id="fileList" class="list-view">
                <!-- LIST/COMPACT TABLE -->
                <div class="file-table-wrap">
                    <table class="file-table" id="fileTable">
                        <thead>
                            <tr>
                                <th class="td-check"><input type="checkbox" class="cb-file" id="cbAll" onchange="toggleAll(this)"></th>
                                <th class="td-icon"></th>
                                <th class="sortable sort-active" onclick="thSort('name')">Name <span id="sort-name-arrow">↑</span></th>
                                <th class="sortable td-size" onclick="thSort('size')">Size <span id="sort-size-arrow"></span></th>
                                <th class="sortable td-date" onclick="thSort('date')">Modified <span id="sort-date-arrow"></span></th>
                                <th class="td-type">Type</th>
                                <th class="td-act">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fileTableBody">
                            <tr><td colspan="7"><div class="loading-spinner"><span class="spin material-icons-round">autorenew</span> Loading…</div></td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- GRID -->
                <div class="file-grid" id="fileGrid"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── DRAG DROP OVERLAY ───────────────────────────────────── -->
<div class="drop-overlay" id="dropOverlay">
    <div class="drop-card">
        <span class="material-icons-round">cloud_upload</span>
        <h2>Drop files to upload</h2>
        <p>Files will be added to your Drive instantly</p>
    </div>
</div>

<!-- ── UPLOAD PROGRESS ───────────────────────────────────────── -->
<div class="upload-progress-wrap" id="uploadProgress">
    <span class="material-icons-round" style="color:var(--blue);font-size:18px">upload</span>
    <span class="progress-label" id="progressLabel">Uploading…</span>
    <div class="progress-track"><div class="progress-fill" id="progressFill" style="width:0%"></div></div>
</div>

<!-- ── RENAME MODAL ───────────────────────────────────────────── -->
<div class="modal-backdrop" id="renameModal">
    <div class="modal">
        <div class="modal-header">
            <span class="material-icons-round" style="color:var(--blue)">edit</span>
            <h3>Rename File</h3>
            <button class="modal-close" onclick="closeModal('renameModal')"><span class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body">
            <p style="color:var(--ink-3);font-size:13px;margin-bottom:12px">Enter a new name for <strong id="renameOldName"></strong></p>
            <input class="modal-input" type="text" id="renameNewName" placeholder="New filename…" onkeydown="if(event.key==='Enter')doRename()">
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('renameModal')">Cancel</button>
            <button class="btn btn-prim" onclick="doRename()"><span class="material-icons-round">check</span>Rename</button>
        </div>
    </div>
</div>

<!-- ── DELETE CONFIRM MODAL ───────────────────────────────────── -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <span class="material-icons-round" style="color:var(--red)">warning</span>
            <h3>Delete File(s)?</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')"><span class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body">
            <p style="color:var(--ink-3);font-size:14px" id="deleteConfirmMsg">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn btn-danger" onclick="confirmDelete()"><span class="material-icons-round">delete_forever</span>Delete</button>
        </div>
    </div>
</div>

<!-- ── SHARE MODAL ────────────────────────────────────────────── -->
<div class="modal-backdrop" id="shareModal">
    <div class="modal">
        <div class="modal-header">
            <span class="material-icons-round" style="color:var(--green)">share</span>
            <h3>Share File</h3>
            <button class="modal-close" onclick="closeModal('shareModal')"><span class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--ink-2);margin-bottom:4px">Shareable link for <strong id="shareFileName"></strong></p>
            <div class="share-url-row">
                <input class="share-url-input" type="text" id="shareUrl" readonly onclick="this.select()">
                <button class="share-copy-btn" onclick="copyShareUrl()">
                    <span class="material-icons-round">content_copy</span>Copy
                </button>
            </div>
            <p class="share-qr-hint">Anyone with this link can view or download the file directly.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('shareModal')">Close</button>
            <a class="btn btn-prim" id="shareOpenBtn" href="#" target="_blank"><span class="material-icons-round">open_in_new</span>Open Link</a>
        </div>
    </div>
</div>

<!-- ── PREVIEW MODAL ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="previewModal">
    <div class="modal preview-modal">
        <div class="modal-header">
            <span class="material-icons-round" style="color:var(--blue-2)">preview</span>
            <h3 id="previewTitle" style="font-family:'DM Mono',monospace;font-size:14px"></h3>
            <button class="modal-close" onclick="closeModal('previewModal')"><span class="material-icons-round">close</span></button>
        </div>
        <div class="preview-body" id="previewBody">
            <!-- injected dynamically -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('previewModal')">Close</button>
            <a class="btn btn-prim" id="previewDownloadBtn" href="#" download>
                <span class="material-icons-round">download</span>Download
            </a>
        </div>
    </div>
</div>

<!-- ── TOAST CONTAINER ───────────────────────────────────────── -->
<div id="toastContainer"></div>

<script>
// ═══════════════════════════════════════════════════════════
//  CONFIG
// ═══════════════════════════════════════════════════════════
const API = 'drive.php';
const BASE_URL = '<?= $baseUrl ?>';

// ── STATE ─────────────────────────────────────────────────
let allFiles    = [];
let filtered    = [];
let currentView = 'list';
let currentSort = { key:'name', order:'asc' };
let currentFilter = 'all';
let pendingDelete = [];
let showThumbs   = false;  // grid thumbnail mode

// ── ICON MAP ──────────────────────────────────────────────
// Uses PNG icons from /Assets/icons/ folder
const CAT_ICONS = {
    image:    '/Assets/icons/image.png',
    video:    '/Assets/icons/video.png',
    audio:    '/Assets/icons/audio.png',
    pdf:      '/Assets/icons/pdf.png',
    doc:      '/Assets/icons/word.png',
    sheet:    '/Assets/icons/excel.png',
    slide:    '/Assets/icons/ppt.png',
    code:     '/Assets/icons/code.png',
    notebook: '/Assets/icons/notebook.png',
    archive:  '/Assets/icons/archive.png',
    text:     '/Assets/icons/text.png',
    font:     '/Assets/icons/font.png',
    other:    '/Assets/icons/file.png',
};

// Emoji fallback for when PNGs are missing
const CAT_EMOJI = {
    image:'🖼️', video:'🎬', audio:'🎵', pdf:'📄', doc:'📝',
    sheet:'📊', slide:'📋', code:'💻', notebook:'📓',
    archive:'🗜️', text:'📃', font:'🔤', other:'📁',
};

// ═══════════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadFiles();
    setupDragDrop();

    // Live search
    let st;
    document.getElementById('searchInput').addEventListener('input', e => {
        clearTimeout(st);
        st = setTimeout(() => { renderFiles(); }, 180);
    });
});

// ═══════════════════════════════════════════════════════════
//  FILE LOADING
// ═══════════════════════════════════════════════════════════
async function loadFiles() {
    const { key, order } = currentSort;
    try {
        const r = await fetch(`${API}?action=list&sort=${key}&order=${order}`);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        if (!d.success) throw new Error(d.error || 'Unknown error');
        allFiles = d.files;
        updateStorageBar();
        renderFiles();
    } catch(e) {
        document.getElementById('fileTableBody').innerHTML =
            `<tr><td colspan="7"><div class="empty-state"><span class="material-icons-round">error_outline</span><h3>Could not load files</h3><p>${e.message}</p></div></td></tr>`;
    }
}

function updateStorageBar() {
    const total = allFiles.reduce((s,f) => s + f.size, 0);
    const quota = 5 * 1024 * 1024 * 1024; // 5 GB display quota
    const pct   = Math.min((total / quota) * 100, 100).toFixed(1);
    document.getElementById('storageFill').style.width = pct + '%';
    document.getElementById('storageLabel').textContent = `${fmtBytes(total)} used (${allFiles.length} file${allFiles.length !== 1 ? 's' : ''})`;
}

// ═══════════════════════════════════════════════════════════
//  RENDER
// ═══════════════════════════════════════════════════════════
function renderFiles() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();

    // Apply filters
    filtered = allFiles.filter(f => {
        if (currentFilter !== 'all' && f.cat !== currentFilter) return false;
        if (q && !f.name.toLowerCase().includes(q)) return false;
        return true;
    });

    if (currentView === 'grid') {
        renderGrid();
    } else {
        renderTable();
    }
    updateSelectionUI();
}

function renderTable() {
    const tbody = document.getElementById('fileTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7">${emptyState()}</td></tr>`;
        return;
    }

    const isCompact = currentView === 'compact';
    tbody.innerHTML = filtered.map((f, i) => `
        <tr data-name="${esc(f.name)}" data-cat="${f.cat}"
            style="animation-delay:${Math.min(i*18,200)}ms"
            onclick="rowClick(event,'${esc(f.name)}')"
            class="">
            <td class="td-check">
                <input type="checkbox" class="cb-file row-cb"
                    onclick="event.stopPropagation()"
                    onchange="rowCheckChange()"
                    data-name="${esc(f.name)}">
            </td>
            <td class="td-icon file-icon-cell">
                <img class="file-icon-img" src="${iconSrc(f.cat)}"
                     alt="${f.cat}" onerror="this.innerHTML='${CAT_EMOJI[f.cat]||'📁'}';this.style.display='none';this.nextSibling&&(this.nextSibling.style.display='')">
            </td>
            <td class="td-name">
                <span class="fname-text" title="${esc(f.name)}">${esc(f.name)}</span>
            </td>
            <td class="td-size">${f.fsize}</td>
            <td class="td-date">${f.date}</td>
            <td class="td-type"><span class="file-type-badge">${esc(f.ext||'—')}</span></td>
            <td class="td-act">
                <div class="row-actions">
                    <button class="act-btn" onclick="event.stopPropagation();openPreview('${esc(f.name)}','${f.cat}')" title="Preview">
                        <span class="material-icons-round">visibility</span>
                    </button>
                    <button class="act-btn" onclick="event.stopPropagation();openShare('${esc(f.name)}','${esc(f.token)}')" title="Share">
                        <span class="material-icons-round">share</span>
                    </button>
                    <a class="act-btn" href="${API}?preview=${encodeURIComponent(f.name)}" download="${esc(f.name)}" onclick="event.stopPropagation()" title="Download">
                        <span class="material-icons-round">download</span>
                    </a>
                    <button class="act-btn" onclick="event.stopPropagation();openRename('${esc(f.name)}')" title="Rename">
                        <span class="material-icons-round">edit</span>
                    </button>
                    <button class="act-btn del" onclick="event.stopPropagation();askDelete(['${esc(f.name)}'])" title="Delete">
                        <span class="material-icons-round">delete</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderGrid() {
    const grid = document.getElementById('fileGrid');
    // Keep thumb-mode class in sync
    grid.classList.toggle('thumb-mode', showThumbs);

    if (filtered.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1">${emptyState()}</div>`;
        return;
    }

    const imgExts = ['jpg','jpeg','png','gif','bmp','webp','svg','ico','avif'];

    grid.innerHTML = filtered.map((f, i) => {
        const ext = f.ext.toLowerCase();
        const isImage = imgExts.includes(ext);
        const previewUrl = `${API}?preview=${encodeURIComponent(f.name)}`;

        // Build thumbnail section (only used in thumb-mode)
        let thumbHtml = '';
        if (showThumbs) {
            if (isImage) {
                thumbHtml = `<div class="grid-thumb-wrap">
                    <img class="grid-thumb-img" src="${previewUrl}" alt="${esc(f.name)}"
                         loading="lazy"
                         onerror="this.parentElement.innerHTML='<div class=grid-thumb-icon><img src=\'${iconSrc(f.cat)}\' alt=\'\'></div>'">
                </div>`;
            } else if (f.cat === 'video') {
                // Video poster frame via video element
                thumbHtml = `<div class="grid-thumb-wrap" style="background:#0f172a">
                    <video class="grid-thumb-img" preload="metadata" muted
                        style="object-fit:cover"
                        onerror="this.parentElement.innerHTML='<div class=grid-thumb-icon><img src=\'${iconSrc(f.cat)}\' alt=\'\'></div>'">
                        <source src="${previewUrl}#t=1">
                    </video>
                </div>`;
            } else {
                // Non-previewable: show large category icon
                thumbHtml = `<div class="grid-thumb-wrap">
                    <div class="grid-thumb-icon">
                        <img src="${iconSrc(f.cat)}" alt="${f.cat}">
                    </div>
                </div>`;
            }
        }

        return `
        <div class="grid-card" data-name="${esc(f.name)}"
             style="animation-delay:${Math.min(i*20,300)}ms"
             ondblclick="openPreview('${esc(f.name)}','${f.cat}')">
            <input type="checkbox" class="cb-file row-cb grid-card-check"
                onclick="event.stopPropagation()"
                onchange="rowCheckChange()"
                data-name="${esc(f.name)}">
            ${thumbHtml}
            <img class="grid-icon" src="${iconSrc(f.cat)}" alt="${f.cat}"
                 onerror="this.style.fontSize='40px';this.style.lineHeight='1';this.alt='${CAT_EMOJI[f.cat]||'📁'}'">
            <div class="grid-name" title="${esc(f.name)}">${esc(f.name)}</div>
            <div class="grid-meta">${f.fsize} • .${f.ext||'—'}</div>
            <div class="grid-actions">
                <button class="act-btn" onclick="event.stopPropagation();openPreview('${esc(f.name)}','${f.cat}')" title="Preview">
                    <span class="material-icons-round">visibility</span>
                </button>
                <button class="act-btn" onclick="event.stopPropagation();openShare('${esc(f.name)}','${esc(f.token)}')" title="Share">
                    <span class="material-icons-round">share</span>
                </button>
                <a class="act-btn" href="${API}?preview=${encodeURIComponent(f.name)}" download="${esc(f.name)}" onclick="event.stopPropagation()" title="Download">
                    <span class="material-icons-round">download</span>
                </a>
                <button class="act-btn del" onclick="event.stopPropagation();askDelete(['${esc(f.name)}'])" title="Delete">
                    <span class="material-icons-round">delete</span>
                </button>
            </div>
        </div>`;
    }).join('');
}

function emptyState() {
    return `<div class="empty-state">
        <span class="material-icons-round">folder_open</span>
        <h3>No files found</h3>
        <p>Upload files or change your filter</p>
    </div>`;
}

function iconSrc(cat) {
    return CAT_ICONS[cat] || CAT_ICONS.other;
}

// ═══════════════════════════════════════════════════════════
//  VIEW / SORT / FILTER
// ═══════════════════════════════════════════════════════════
function setView(v) {
    currentView = v;
    const fl = document.getElementById('fileList');
    fl.className = v + '-view';
    ['list','grid','compact'].forEach(x => {
        document.getElementById('btn'+x.charAt(0).toUpperCase()+x.slice(1)).classList.toggle('active', x===v);
    });
    // Show thumbnail toggle only in grid view
    const thumbBtn = document.getElementById('btnThumbToggle');
    thumbBtn.style.display = v === 'grid' ? 'flex' : 'none';
    renderFiles();
}

function applySort(val) {
    const [key, order] = val.split('|');
    currentSort = { key, order };
    sortFiles();
    renderFiles();
}

function thSort(key) {
    if (currentSort.key === key) {
        currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = { key, order:'asc' };
    }
    // Update arrows
    ['name','size','date'].forEach(k => {
        const el = document.getElementById('sort-'+k+'-arrow');
        if (el) el.textContent = currentSort.key === k ? (currentSort.order === 'asc' ? '↑' : '↓') : '';
    });
    // Update header active class
    document.querySelectorAll('.file-table thead th').forEach(th => th.classList.remove('sort-active'));
    sortFiles();
    renderFiles();
}

function sortFiles() {
    const { key, order } = currentSort;
    allFiles.sort((a, b) => {
        let cmp = 0;
        if (key === 'size') cmp = a.size - b.size;
        else if (key === 'date') cmp = a.mtime - b.mtime;
        else if (key === 'type') cmp = a.ext.localeCompare(b.ext);
        else cmp = a.name.localeCompare(b.name, undefined, {sensitivity:'base', numeric:true});
        return order === 'desc' ? -cmp : cmp;
    });
}

function setFilter(cat, btn) {
    currentFilter = cat;
    document.querySelectorAll('.chip').forEach(c => c.classList.toggle('active', c.dataset.cat === cat));
    renderFiles();
}

function toggleThumbnails() {
    showThumbs = !showThumbs;
    const btn   = document.getElementById('btnThumbToggle');
    const icon  = document.getElementById('thumbToggleIcon');
    const label = document.getElementById('thumbToggleLabel');
    const grid  = document.getElementById('fileGrid');
    btn.classList.toggle('active', showThumbs);
    icon.textContent  = showThumbs ? 'hide_image' : 'image';
    label.textContent = showThumbs ? 'Icons' : 'Thumbnails';
    grid.classList.toggle('thumb-mode', showThumbs);
    // Re-render grid to build/remove thumb elements
    if (currentView === 'grid') renderGrid();
}

// ═══════════════════════════════════════════════════════════
//  UPLOAD
// ═══════════════════════════════════════════════════════════
async function uploadFiles(fileList) {
    if (!fileList || !fileList.length) return;
    const fd = new FormData();
    fd.append('action', 'upload');
    Array.from(fileList).forEach(f => fd.append('files[]', f));

    const prog   = document.getElementById('uploadProgress');
    const fill   = document.getElementById('progressFill');
    const label  = document.getElementById('progressLabel');
    prog.classList.add('show');
    fill.style.width = '0%';
    label.textContent = `Uploading ${fileList.length} file(s)…`;

    try {
        const xhr = new XMLHttpRequest();
        await new Promise((res, rej) => {
            xhr.upload.onprogress = e => {
                if (e.lengthComputable) {
                    const p = Math.round((e.loaded / e.total) * 100);
                    fill.style.width = p + '%';
                    label.textContent = `Uploading… ${p}%`;
                }
            };
            xhr.onload = () => {
                if (xhr.status === 200) res(xhr.responseText);
                else rej(new Error('HTTP ' + xhr.status));
            };
            xhr.onerror = () => rej(new Error('Network error'));
            xhr.open('POST', API);
            xhr.send(fd);
        });

        const data = JSON.parse(xhr.responseText);
        if (data.success) {
            const ok  = data.results.filter(r => r.ok).length;
            const bad = data.results.filter(r => !r.ok);
            toast(`${ok} file(s) uploaded`, 'success');
            bad.forEach(b => toast(`${b.name}: ${b.error}`, 'error'));
        } else {
            toast(data.error || 'Upload failed', 'error');
        }
        await loadFiles();
    } catch(e) {
        toast('Upload error: ' + e.message, 'error');
    } finally {
        setTimeout(() => prog.classList.remove('show'), 800);
        document.getElementById('fileInput').value = '';
    }
}

// ─── DRAG & DROP ─────────────────────────────────────────
function setupDragDrop() {
    const body = document.body;
    let dragCount = 0;
    body.addEventListener('dragenter', e => { e.preventDefault(); dragCount++; document.getElementById('dropOverlay').classList.add('show'); });
    body.addEventListener('dragleave', e => { dragCount--; if (dragCount <= 0) { dragCount=0; document.getElementById('dropOverlay').classList.remove('show'); } });
    body.addEventListener('dragover',  e => e.preventDefault());
    body.addEventListener('drop',      e => {
        e.preventDefault(); dragCount=0;
        document.getElementById('dropOverlay').classList.remove('show');
        if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
    });
}

// ═══════════════════════════════════════════════════════════
//  SELECTION
// ═══════════════════════════════════════════════════════════
function rowClick(e, name) {
    if (e.target.type === 'checkbox') return;
    const cb = document.querySelector(`.row-cb[data-name="${CSS.escape(name)}"]`);
    if (cb) { cb.checked = !cb.checked; rowCheckChange(); }
}

function toggleAll(masterCb) {
    document.querySelectorAll('.row-cb').forEach(cb => {
        cb.checked = masterCb.checked;
        cb.closest('tr, .grid-card')?.classList.toggle('selected', masterCb.checked);
    });
    updateSelectionUI();
}

function rowCheckChange() {
    const all = document.querySelectorAll('.row-cb');
    all.forEach(cb => {
        const parent = cb.closest('tr') || cb.closest('.grid-card');
        if (parent) parent.classList.toggle('selected', cb.checked);
    });
    const master = document.getElementById('cbAll');
    if (master) {
        const checked = [...all].filter(c => c.checked).length;
        master.indeterminate = checked > 0 && checked < all.length;
        master.checked = checked === all.length && all.length > 0;
    }
    updateSelectionUI();
}

function updateSelectionUI() {
    const sel = [...document.querySelectorAll('.row-cb:checked')];
    const btn = document.getElementById('btnDelSel');
    document.getElementById('selCount').textContent = sel.length;
    btn.classList.toggle('show', sel.length > 0);
}

function getSelectedNames() {
    return [...document.querySelectorAll('.row-cb:checked')].map(cb => cb.dataset.name);
}

// ═══════════════════════════════════════════════════════════
//  RENAME
// ═══════════════════════════════════════════════════════════
let renameTarget = '';
function openRename(name) {
    renameTarget = name;
    document.getElementById('renameOldName').textContent = name;
    document.getElementById('renameNewName').value = name;
    openModal('renameModal');
    setTimeout(() => {
        const inp = document.getElementById('renameNewName');
        inp.focus();
        const dot = name.lastIndexOf('.');
        inp.setSelectionRange(0, dot > 0 ? dot : name.length);
    }, 100);
}

async function doRename() {
    const newName = document.getElementById('renameNewName').value.trim();
    if (!newName || newName === renameTarget) { closeModal('renameModal'); return; }

    try {
        const fd = new FormData();
        fd.append('action', 'rename');
        fd.append('old', renameTarget);
        fd.append('new', newName);
        const r = await fetch(API, { method:'POST', body:fd });
        const d = await r.json();
        if (d.success) {
            toast(`Renamed to "${newName}"`, 'success');
            closeModal('renameModal');
            await loadFiles();
        } else {
            toast(d.error || 'Rename failed', 'error');
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }
}

// ═══════════════════════════════════════════════════════════
//  DELETE
// ═══════════════════════════════════════════════════════════
function askDelete(names) {
    pendingDelete = names;
    document.getElementById('deleteConfirmMsg').textContent =
        names.length === 1 ? `Delete "${names[0]}"? This cannot be undone.` :
        `Delete ${names.length} selected files? This cannot be undone.`;
    openModal('deleteModal');
}

function deleteSelected() {
    const names = getSelectedNames();
    if (names.length) askDelete(names);
}

async function confirmDelete() {
    if (!pendingDelete.length) return;
    try {
        const fd = new FormData();
        fd.append('action', 'delete');
        pendingDelete.forEach(n => fd.append('names[]', n));
        const r = await fetch(API, { method:'POST', body:fd });
        const d = await r.json();
        if (d.success) {
            toast(`${d.deleted} file(s) deleted`, 'success');
            closeModal('deleteModal');
            await loadFiles();
        } else {
            toast(d.error || 'Delete failed', 'error');
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }
}

// ═══════════════════════════════════════════════════════════
//  SHARE
// ═══════════════════════════════════════════════════════════
function openShare(name, token) {
    const url = `${window.location.origin}${window.location.pathname}?dl=${encodeURIComponent(token)}`;
    document.getElementById('shareFileName').textContent = name;
    document.getElementById('shareUrl').value = url;
    document.getElementById('shareOpenBtn').href = url;
    openModal('shareModal');
}

function copyShareUrl() {
    const inp = document.getElementById('shareUrl');
    inp.select();
    navigator.clipboard.writeText(inp.value).then(() => {
        toast('Link copied to clipboard!', 'success');
    }).catch(() => {
        document.execCommand('copy');
        toast('Link copied!', 'success');
    });
}

// ═══════════════════════════════════════════════════════════
//  PREVIEW
// ═══════════════════════════════════════════════════════════
async function openPreview(name, cat) {
    const previewUrl = `${API}?preview=${encodeURIComponent(name)}`;
    const body = document.getElementById('previewBody');
    document.getElementById('previewTitle').textContent = name;
    document.getElementById('previewDownloadBtn').href = previewUrl;
    document.getElementById('previewDownloadBtn').download = name;

    body.innerHTML = `<div class="loading-spinner" style="color:white"><span class="spin material-icons-round">autorenew</span></div>`;
    openModal('previewModal');

    const ext = name.split('.').pop().toLowerCase();

    // ── IMAGE ─────────────────────────────
    if (['jpg','jpeg','png','gif','bmp','webp','svg','ico','tiff','avif'].includes(ext)) {
        body.innerHTML = `<img src="${previewUrl}" alt="${esc(name)}" style="max-width:100%;max-height:70vh;object-fit:contain;display:block;margin:auto;">`;
        return;
    }

    // ── VIDEO ─────────────────────────────
    if (['mp4','webm','ogg','mov'].includes(ext)) {
        body.innerHTML = `<video controls autoplay style="max-width:100%;max-height:70vh;display:block">
            <source src="${previewUrl}">Your browser does not support video.
        </video>`;
        return;
    }

    // ── AUDIO ─────────────────────────────
    if (['mp3','wav','ogg','flac','aac','m4a'].includes(ext)) {
        body.innerHTML = `<div style="padding:40px;text-align:center">
            <span class="material-icons-round" style="font-size:80px;color:#a5b4fc">music_note</span>
            <p style="color:#e2e8f0;margin:16px 0 12px;font-weight:600">${esc(name)}</p>
            <audio controls autoplay style="width:100%;max-width:400px">
                <source src="${previewUrl}">
            </audio>
        </div>`;
        return;
    }

    // ── PDF ───────────────────────────────
    if (ext === 'pdf') {
        body.innerHTML = `<iframe src="${previewUrl}" style="width:100%;height:70vh;border:none;display:block"></iframe>`;
        return;
    }

    // ── SVG ───────────────────────────────
    if (ext === 'svg') {
        body.innerHTML = `<img src="${previewUrl}" style="max-width:100%;max-height:70vh;background:white;padding:20px;display:block;margin:auto;">`;
        return;
    }

    // ── JUPYTER NOTEBOOK (.ipynb) ────────
    if (ext === 'ipynb') {
        try {
            const resp = await fetch(previewUrl);
            const nb   = await resp.json();
            const cells = (nb.cells || []).map(cell => {
                const src = Array.isArray(cell.source) ? cell.source.join('') : (cell.source || '');
                const ct  = cell.cell_type;
                if (ct === 'markdown') {
                    return `<div style="padding:12px 0;border-bottom:1px solid #e5e7eb;color:#1a1a2e">${escNb(src)}</div>`;
                } else if (ct === 'code') {
                    const outputs = (cell.outputs||[]).map(o => {
                        const txt = Array.isArray(o.text) ? o.text.join('') : (o.text || '');
                        const data = o.data || {};
                        if (data['image/png']) return `<img src="data:image/png;base64,${data['image/png']}" style="max-width:100%">`;
                        if (data['text/html']) return `<div>${(Array.isArray(data['text/html'])?data['text/html'].join(''):data['text/html'])}</div>`;
                        return txt ? `<pre style="background:#f3f4f6;padding:8px;border-radius:6px;font-size:12px;white-space:pre-wrap;color:#374151">${esc(txt)}</pre>` : '';
                    }).join('');
                    return `<div style="margin:10px 0">
                        <pre style="background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;font-size:12px;overflow-x:auto;white-space:pre-wrap">${esc(src)}</pre>
                        ${outputs}
                    </div>`;
                }
                return `<pre style="font-size:12px">${esc(src)}</pre>`;
            }).join('');
            body.style.background = 'white';
            body.innerHTML = `<div class="preview-nb">${cells}</div>`;
        } catch(e) {
            body.innerHTML = notSupported(name, 'Could not render notebook: ' + e.message);
        }
        return;
    }

    // ── CODE & TEXT FILES ─────────────────
    const textExts = ['php','js','ts','py','rb','java','c','cpp','cs','go','rs',
                      'html','css','scss','json','xml','yaml','yml','sh','bash',
                      'sql','txt','md','log','ini','env','conf','csv'];
    if (textExts.includes(ext)) {
        try {
            const resp = await fetch(previewUrl);
            const text = await resp.text();
            body.style.background = '#0f172a';
            body.innerHTML = `<div class="preview-code">
                <pre style="color:#e2e8f0;font-size:13px;line-height:1.7;white-space:pre-wrap;word-break:break-all">${esc(text.substring(0, 80000))}</pre>
            </div>`;
        } catch(e) {
            body.innerHTML = notSupported(name);
        }
        return;
    }

    // ── FALLBACK ───────────────────────────
    body.innerHTML = notSupported(name);
}

function notSupported(name, msg) {
    return `<div style="text-align:center;padding:60px;color:#94a3b8">
        <span class="material-icons-round" style="font-size:64px;display:block;margin:0 auto 16px">preview</span>
        <h3 style="color:white;margin-bottom:8px">Preview not available</h3>
        <p>${msg || 'This file type cannot be previewed in the browser.'}</p>
        <p style="margin-top:8px;font-size:12px">${esc(name)}</p>
    </div>`;
}

function escNb(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

// ═══════════════════════════════════════════════════════════
//  MODAL HELPERS
// ═══════════════════════════════════════════════════════════
function openModal(id) {
    document.getElementById(id).classList.add('show');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    if (id === 'previewModal') {
        document.getElementById('previewBody').innerHTML = '';
        document.getElementById('previewBody').style.background = '#0d0d1a';
    }
}

// Close on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(m => {
    m.addEventListener('click', e => {
        if (e.target === m) closeModal(m.id);
    });
});

// ESC key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.show').forEach(m => closeModal(m.id));
    }
});

// ═══════════════════════════════════════════════════════════
//  TOAST
// ═══════════════════════════════════════════════════════════
function toast(msg, type='info') {
    const icons = { success:'check_circle', error:'error', info:'info' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span class="material-icons-round">${icons[type]||'info'}</span>${esc(msg)}`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => {
        el.style.animation = 'toastOut .3s ease forwards';
        setTimeout(() => el.remove(), 300);
    }, 3200);
}

// ═══════════════════════════════════════════════════════════
//  UTILS
// ═══════════════════════════════════════════════════════════
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function fmtBytes(b) {
    if (b < 1024) return b+' B';
    if (b < 1048576) return (b/1024).toFixed(1)+' KB';
    if (b < 1073741824) return (b/1048576).toFixed(1)+' MB';
    return (b/1073741824).toFixed(2)+' GB';
}
</script>
</body>
</html>