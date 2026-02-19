<?php
session_start();

// Load environment configuration
require_once 'config.php';

// Security check: Verify credentials from session OR environment
$hasSessionAuth = isset($_SESSION['smtp_user']) && isset($_SESSION['smtp_pass']);
$hasEnvAuth = !empty(env('SMTP_USERNAME')) && !empty(env('SMTP_PASSWORD'));

if (!$hasSessionAuth && !$hasEnvAuth) {
    // No credentials available - redirect to login
    header("Location: login.php");
    exit();
}

// If only ENV credentials are available, populate session for this page
if (!$hasSessionAuth && $hasEnvAuth) {
    $_SESSION['smtp_user'] = env('SMTP_USERNAME');
    $_SESSION['smtp_pass'] = env('SMTP_PASSWORD');
    $_SESSION['smtp_host'] = env('SMTP_HOST');
    $_SESSION['smtp_port'] = env('SMTP_PORT');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Mailmerge');
        include 'header.php';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;450;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --blue:      #0071E3;
            --blue-mid:  #005BBF;
            --blue-glow: rgba(0,113,227,.14);
            --green:     #1DB954;
            --green-bg:  rgba(29,185,84,.09);
            --amber:     #F5A623;
            --amber-bg:  rgba(245,166,35,.10);
            --red:       #FF3B30;

            --ink:       #1D1D1F;
            --ink-2:     #3D3D3F;
            --ink-3:     #6E6E73;
            --ink-4:     #AEAEB2;

            --bg:        #F5F5F7;
            --surface:   #FFFFFF;
            --divider:   rgba(0,0,0,.08);
            --divider-heavy: rgba(0,0,0,.12);

            --radius-sm: 6px;
            --radius:    10px;
            --radius-lg: 14px;

            --shadow-xs: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-sm: 0 2px 8px rgba(0,0,0,.07), 0 1px 3px rgba(0,0,0,.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,.09), 0 2px 6px rgba(0,0,0,.05);

            --ease:      cubic-bezier(.4,0,.2,1);
            --ease-out:  cubic-bezier(0,0,.2,1);
        }

        /* ─── RESET ──────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ─── LAYOUT ─────────────────────────────────────────────────── */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 32px 120px;
        }

        /* ─── PAGE HEADER ────────────────────────────────────────────── */
        .page-header {
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-bottom: 1px solid var(--divider);
            position: sticky;
            top: 0;
            z-index: 200;
        }

        .header-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 32px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title h1 {
            font-size: 17px;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -.3px;
        }

        .header-title .subtitle {
            font-size: 13px;
            color: var(--ink-3);
            font-weight: 400;
        }

        .separator-dot {
            width: 4px; height: 4px;
            border-radius: 50%;
            background: var(--ink-4);
        }

        .header-pills {
            display: flex;
            gap: 6px;
        }

        .stat-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--bg);
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-3);
        }

        .stat-pill .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
        }

        .stat-pill.pending   .dot { background: var(--amber); }
        .stat-pill.sent      .dot { background: var(--green); }
        .stat-pill.failed    .dot { background: var(--red); }

        /* ─── TABS ───────────────────────────────────────────────────── */
        .tabs-bar {
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--divider);
            position: sticky;
            top: 58px;
            z-index: 199;
        }

        .tabs-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 32px;
            display: flex;
            gap: 0;
        }

        .tab {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-3);
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: color .15s var(--ease), border-color .15s var(--ease);
            white-space: nowrap;
        }

        .tab .material-icons-round { font-size: 16px; }

        .tab:hover { color: var(--ink-2); }

        .tab.active {
            color: var(--blue);
            border-bottom-color: var(--blue);
            font-weight: 600;
        }

        /* ─── TAB CONTENT ────────────────────────────────────────────── */
        .tab-content { display: none; }
        .tab-content.active {
            display: block;
            animation: fadeUp .22s var(--ease-out) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── COMPOSE LAYOUT ─────────────────────────────────────────── */
        .compose-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
            align-items: start;
        }

        .compose-layout > * { min-width: 0; }

        /* ─── CARD ───────────────────────────────────────────────────── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--divider);
            box-shadow: var(--shadow-xs);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .card-body { padding: 20px 24px; }

        .section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--ink-4);
            margin-bottom: 12px;
        }

        /* ─── UPLOAD ZONE ────────────────────────────────────────────── */
        .upload-zone {
            border: 1.5px solid var(--divider-heavy);
            border-radius: var(--radius);
            padding: 28px 24px;
            text-align: center;
            cursor: pointer;
            transition: border-color .18s var(--ease), background .18s var(--ease), box-shadow .18s var(--ease);
            background: var(--bg);
            position: relative;
        }

        .upload-zone:hover {
            border-color: var(--blue);
            background: rgba(0,113,227,.03);
            box-shadow: 0 0 0 4px var(--blue-glow);
        }

        .upload-zone.dragover {
            border-color: var(--blue);
            background: rgba(0,113,227,.05);
            box-shadow: 0 0 0 5px var(--blue-glow);
            transform: scale(1.005);
        }

        .upload-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
        }

        .upload-icon-wrap {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: rgba(0,113,227,.1);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 12px;
        }

        .upload-icon-wrap .material-icons-round {
            font-size: 20px;
            color: var(--blue);
        }

        .upload-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .upload-hint {
            font-size: 12px;
            color: var(--ink-4);
        }

        /* file loaded row */
        .file-loaded-row {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: var(--bg);
            border-radius: var(--radius);
            margin-top: 14px;
            border: 1px solid var(--divider);
        }

        .file-loaded-row.visible { display: flex; }

        .file-loaded-row .file-icon {
            font-size: 20px;
            line-height: 1;
        }

        .file-loaded-meta {
            flex: 1; min-width: 0;
        }

        .file-loaded-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-loaded-size {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 1px;
        }

        .file-remove-btn {
            background: none; border: none;
            cursor: pointer;
            color: var(--ink-4);
            display: flex; align-items: center;
            border-radius: 4px;
            padding: 3px;
            transition: color .15s, background .15s;
        }

        .file-remove-btn:hover { color: var(--red); background: rgba(255,59,48,.08); }
        .file-remove-btn .material-icons-round { font-size: 16px; }

        /* ─── ANALYSIS RESULTS ───────────────────────────────────────── */
        .analysis-results {
            display: none;
            animation: fadeUp .25s var(--ease-out) both;
        }

        .analysis-results.active { display: block; }

        /* sticky mapping header */
        .mapping-sticky-header {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--divider);
            border-radius: var(--radius);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            gap: 12px;
            /* position: sticky; */
            top: 116px;
            z-index: 100;
        }

        .mapping-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .mapping-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--ink-3);
            font-weight: 500;
        }

        .mapping-meta-item .material-icons-round { font-size: 14px; }

        .mapping-meta-item.highlight { color: var(--blue); }
        .mapping-meta-item.success   { color: var(--green); }
        .mapping-meta-item.warn      { color: var(--amber); }

        .btn-ghost-sm {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px;
            font-size: 12px; font-weight: 600;
            color: var(--blue);
            background: none;
            border: 1px solid rgba(0,113,227,.25);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: background .15s, border-color .15s;
            white-space: nowrap;
        }

        .btn-ghost-sm:hover {
            background: var(--blue-glow);
            border-color: rgba(0,113,227,.45);
        }

        .btn-ghost-sm .material-icons-round { font-size: 14px; }

        /* 3-col mapping grid */
        .mapping-grid-header {
            display: grid;
            grid-template-columns: 1fr 28px 1fr;
            gap: 8px;
            padding: 0 16px 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--ink-4);
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .mapping-grid {
            display: flex;
            flex-direction: column;
            gap: 0;
            border: 1px solid var(--divider);
            border-radius: var(--radius);
            overflow: hidden;
            background: var(--surface);
        }

        .mapping-row {
            display: grid;
            grid-template-columns: 1fr 28px 1fr;
            gap: 8px;
            align-items: center;
            padding: 8px 14px;
            border-bottom: 1px solid var(--divider);
            transition: background .12s var(--ease);
        }

        .mapping-row:last-child { border-bottom: none; }
        .mapping-row:hover { background: rgba(0,0,0,.018); }

        .mapping-row.is-matched { background: var(--green-bg); }
        .mapping-row.is-matched:hover { background: rgba(29,185,84,.13); }

        .mapping-row.is-required { background: var(--amber-bg); }
        .mapping-row.is-required:hover { background: rgba(245,166,35,.15); }

        .csv-field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-2);
            min-width: 0;
        }

        .csv-field-label span.name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .field-badge {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .04em;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .badge-required {
            background: rgba(245,166,35,.15);
            color: #A0720A;
        }

        .badge-auto {
            background: var(--green-bg);
            color: #1A8C43;
        }

        .arrow-col {
            display: flex; align-items: center; justify-content: center;
            color: var(--ink-4);
            font-size: 14px;
        }

        .arrow-col svg { width: 14px; height: 14px; flex-shrink: 0; }

        .mapping-select {
            width: 100%;
            padding: 6px 10px;
            border: 1.5px solid var(--divider-heavy);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--ink);
            background: var(--surface);
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236E6E73' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 28px;
            transition: border-color .15s, box-shadow .15s;
            font-family: inherit;
        }

        .mapping-select:hover { border-color: rgba(0,113,227,.4); }
        .mapping-select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
        }

        .mapping-select.mapped {
            border-color: rgba(29,185,84,.4);
            color: #1A8C43;
        }

        /* ─── MAPPING SUMMARY ────────────────────────────────────────── */
        .mapping-summary-card {
            background: var(--surface);
            border: 1px solid var(--divider);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .summary-header {
            padding: 12px 20px;
            border-bottom: 1px solid var(--divider);
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-3);
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .summary-pair {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            border-bottom: 1px solid var(--divider);
            font-size: 13px;
        }

        .summary-pair:last-child,
        .summary-pair:nth-last-child(2):nth-child(odd) {
            border-bottom: none;
        }

        .summary-csv {
            font-weight: 600;
            color: var(--ink-2);
            font-family: 'SF Mono', 'Menlo', 'Fira Code', monospace;
            font-size: 12px;
            background: var(--bg);
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }

        .summary-arrow {
            color: var(--ink-4);
            font-size: 12px;
            flex-shrink: 0;
        }

        .summary-field {
            color: var(--ink-3);
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ─── PREVIEW TABLE ──────────────────────────────────────────── */
        .preview-table-wrap {
            border: 1px solid var(--divider);
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: var(--surface);
            margin-bottom: 16px;
        }

        .preview-table-header {
            padding: 12px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preview-table-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-3);
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .preview-table-meta {
            font-size: 11px;
            color: var(--ink-4);
        }

        .preview-scroll {
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }

        .preview-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .preview-scroll::-webkit-scrollbar-track { background: transparent; }
        .preview-scroll::-webkit-scrollbar-thumb { background: var(--divider-heavy); border-radius: 10px; }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .preview-table thead th {
            position: sticky;
            top: 0;
            background: var(--bg);
            padding: 8px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: var(--ink-3);
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
            border-bottom: 1px solid var(--divider-heavy);
            z-index: 10;
        }

        .preview-table tbody tr {
            transition: background .1s;
        }

        .preview-table tbody tr:hover { background: rgba(0,0,0,.024); }

        .preview-table tbody td {
            padding: 8px 14px;
            color: var(--ink-2);
            border-bottom: 1px solid var(--divider);
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .preview-table tbody tr:last-child td { border-bottom: none; }

        /* ─── ATTACHMENT PANEL ───────────────────────────────────────── */
        .attachment-panel {
            background: var(--surface);
            border: 1px solid var(--divider);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-xs);
            position: sticky;
            top: 116px;
        }

        .attach-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid var(--divider);
            cursor: pointer;
            user-select: none;
        }

        .attach-panel-header-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .attach-icon-wrap {
            width: 28px; height: 28px;
            border-radius: 7px;
            background: rgba(0,113,227,.1);
            display: flex; align-items: center; justify-content: center;
        }

        .attach-icon-wrap .material-icons-round { font-size: 15px; color: var(--blue); }

        .attach-panel-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .attach-panel-sub {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 1px;
        }

        .attach-collapse-btn {
            background: none; border: none;
            cursor: pointer;
            color: var(--ink-4);
            display: flex; align-items: center;
            transition: color .15s, transform .2s var(--ease);
        }

        .attach-collapse-btn .material-icons-round { font-size: 18px; }
        .attach-collapse-btn.collapsed { transform: rotate(-90deg); }

        .attach-panel-body {
            padding: 14px 18px;
            overflow: hidden;
            transition: all .25s var(--ease);
        }

        .attach-panel-body.collapsed {
            height: 0 !important;
            padding: 0 18px;
            overflow: hidden;
        }

        /* segmented control */
        .segmented {
            display: flex;
            background: var(--bg);
            border-radius: var(--radius-sm);
            padding: 3px;
            gap: 2px;
            margin-bottom: 14px;
        }

        .segmented-btn {
            flex: 1;
            padding: 6px 8px;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-3);
            background: none;
            cursor: pointer;
            transition: all .15s var(--ease);
            display: flex; align-items: center; justify-content: center;
            gap: 5px;
        }

        .segmented-btn .material-icons-round { font-size: 14px; }

        .segmented-btn.active {
            background: var(--surface);
            color: var(--ink);
            box-shadow: var(--shadow-xs);
            font-weight: 600;
        }

        /* drive file list */
        .drive-list {
            max-height: 260px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .drive-list::-webkit-scrollbar { width: 4px; }
        .drive-list::-webkit-scrollbar-thumb { background: var(--divider-heavy); border-radius: 10px; }

        .drive-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: background .12s;
            border: 1.5px solid transparent;
        }

        .drive-item:hover { background: var(--bg); }

        .drive-item.selected {
            background: rgba(0,113,227,.07);
            border-color: rgba(0,113,227,.25);
        }

        .drive-item-icon { font-size: 18px; line-height: 1; flex-shrink: 0; }

        .drive-item-info { flex: 1; min-width: 0; }

        .drive-item-name {
            font-size: 12.5px;
            font-weight: 500;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .drive-item-size {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 1px;
        }

        .drive-item-check {
            width: 18px; height: 18px;
            border-radius: 50%;
            border: 1.5px solid var(--divider-heavy);
            display: flex; align-items: center; justify-content: center;
            transition: all .15s;
            flex-shrink: 0;
        }

        .drive-item.selected .drive-item-check {
            background: var(--blue);
            border-color: var(--blue);
        }

        .drive-item.selected .drive-item-check .material-icons-round {
            font-size: 11px;
            color: white;
            display: block;
        }

        .drive-item-check .material-icons-round { display: none; }

        /* small upload zone */
        .upload-zone-mini {
            border: 1.5px dashed var(--divider-heavy);
            border-radius: var(--radius-sm);
            padding: 20px 16px;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            position: relative;
        }

        .upload-zone-mini:hover {
            border-color: var(--blue);
            background: rgba(0,113,227,.03);
        }

        .upload-zone-mini input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
        }

        .upload-zone-mini .material-icons-round {
            font-size: 22px;
            color: var(--blue);
            margin-bottom: 6px;
        }

        .upload-zone-mini p { font-size: 12.5px; font-weight: 600; color: var(--ink-2); }
        .upload-zone-mini small { font-size: 11px; color: var(--ink-4); }

        /* selected attachment chip */
        .attachment-chip {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(0,113,227,.07);
            border: 1px solid rgba(0,113,227,.2);
            border-radius: var(--radius-sm);
            margin-top: 12px;
        }

        .attachment-chip.visible { display: flex; }

        .attachment-chip-icon { font-size: 16px; }

        .attachment-chip-info { flex: 1; min-width: 0; }

        .attachment-chip-name {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attachment-chip-size {
            font-size: 11px;
            color: var(--ink-4);
        }

        .attachment-chip-clear {
            background: none; border: none;
            cursor: pointer; color: var(--ink-4);
            display: flex; align-items: center;
            border-radius: 4px; padding: 2px;
            transition: color .15s, background .15s;
        }

        .attachment-chip-clear:hover { color: var(--red); background: rgba(255,59,48,.08); }
        .attachment-chip-clear .material-icons-round { font-size: 15px; }

        /* ─── LOADING SPINNER ────────────────────────────────────────── */
        .loading-wrap {
            padding: 24px;
            text-align: center;
        }

        .spinner-sm {
            width: 20px; height: 20px;
            border: 2px solid var(--divider-heavy);
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin .8s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .loading-wrap p { font-size: 12px; color: var(--ink-4); }

        /* ─── BOTTOM ACTION BAR ──────────────────────────────────────── */
        .action-bar {
            position: fixed;
            bottom: 0;
            left: 0; right: 0;
            z-index: 300;
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-top: 1px solid var(--divider);
            transform: translateY(100%);
            transition: transform .25s var(--ease);
        }

        .action-bar.visible { transform: translateY(0); }

        .action-bar-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 12px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .action-bar-info {
            font-size: 13px;
            color: var(--ink-3);
        }

        .action-bar-info strong { color: var(--ink); }

        .action-btns {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ─── BUTTONS ────────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all .15s var(--ease);
            font-family: inherit;
            white-space: nowrap;
        }

        .btn .material-icons-round { font-size: 16px; }

        .btn-primary {
            background: var(--blue);
            color: white;
            box-shadow: 0 1px 4px rgba(0,113,227,.3);
        }

        .btn-primary:hover {
            background: var(--blue-mid);
            box-shadow: 0 4px 12px rgba(0,113,227,.35);
            transform: translateY(-1px);
        }

        .btn-primary:active { transform: translateY(0); }

        .btn-secondary {
            background: transparent;
            color: var(--ink-2);
            border: 1px solid var(--divider-heavy);
        }

        .btn-secondary:hover {
            background: var(--bg);
            border-color: rgba(0,0,0,.18);
        }

        .btn-danger {
            background: transparent;
            color: var(--red);
            border: 1px solid rgba(255,59,48,.2);
        }

        .btn-danger:hover { background: rgba(255,59,48,.06); }

        .btn:disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* ─── QUEUE TAB ──────────────────────────────────────────────── */
        .queue-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .queue-table-wrap {
            background: var(--surface);
            border: 1px solid var(--divider);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .queue-table-title {
            padding: 14px 20px;
            border-bottom: 1px solid var(--divider);
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .queue-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .queue-table thead th {
            background: var(--bg);
            padding: 9px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: var(--ink-4);
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1px solid var(--divider-heavy);
        }

        .queue-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--divider);
            color: var(--ink-2);
            vertical-align: middle;
        }

        .queue-table tbody tr:last-child td { border-bottom: none; }
        .queue-table tbody tr:hover { background: rgba(0,0,0,.018); }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-chip .dot { width: 5px; height: 5px; border-radius: 50%; }

        .status-pending  { background: rgba(245,166,35,.12); color: #A0720A; }
        .status-pending .dot  { background: var(--amber); }
        .status-completed { background: var(--green-bg); color: #1A8C43; }
        .status-completed .dot { background: var(--green); }
        .status-failed   { background: rgba(255,59,48,.1); color: #C22B22; }
        .status-failed .dot   { background: var(--red); }

        /* ─── PROGRESS ───────────────────────────────────────────────── */
        .progress-card {
            background: var(--surface);
            border: 1px solid var(--divider);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 16px;
            display: none;
        }

        .progress-card.active { display: block; }

        .progress-bar-track {
            height: 5px;
            background: var(--bg);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-bar-fill {
            height: 100%;
            background: var(--blue);
            width: 0%;
            transition: width .3s var(--ease);
            border-radius: 10px;
        }

        .progress-label { font-size: 12px; color: var(--ink-3); }

        /* ─── EMPTY STATE ────────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
        }

        .empty-state-icon { font-size: 40px; margin-bottom: 12px; }

        .empty-state h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .empty-state p { font-size: 13px; color: var(--ink-4); }

        /* ─── ALERTS ─────────────────────────────────────────────────── */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 14px;
            font-size: 13px;
            font-weight: 500;
            animation: slideDown .2s var(--ease-out) both;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert .material-icons-round { font-size: 17px; flex-shrink: 0; }

        .alert-success { background: var(--green-bg); color: #1A8C43; border: 1px solid rgba(29,185,84,.2); }
        .alert-error   { background: rgba(255,59,48,.08); color: #C22B22; border: 1px solid rgba(255,59,48,.2); }
        .alert-info    { background: rgba(0,113,227,.08); color: var(--blue); border: 1px solid rgba(0,113,227,.2); }

        /* ─── ATTACHMENT CONTENT VISIBILITY ─────────────────────────── */
        .attach-content { display: none; }
        .attach-content.active { display: block; }

        /* ─── RESPONSIVE ─────────────────────────────────────────────── */
        @media (max-width: 1100px) {
            .compose-layout {
                grid-template-columns: 1fr;
            }

            .attachment-panel {
                position: static;
            }
        }

        @media (max-width: 720px) {
            .container { padding: 16px 16px 120px; }
            .header-inner { padding: 0 16px; }
            .tabs-inner { padding: 0 16px; }
            .mapping-meta { display: none; }
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">

        <!-- ═══ PAGE HEADER ════════════════════════════════════════════ -->
        <header class="page-header">
            <div class="header-inner">
                <div class="header-title">
                    <h1>Mail Merge</h1>
                    <div class="separator-dot"></div>
                    <span class="subtitle">Send personalised emails at scale</span>
                </div>
                <div class="header-pills">
                    <div class="stat-pill pending">
                        <div class="dot"></div>
                        <span id="stat-pending">0</span> pending
                    </div>
                    <div class="stat-pill sent">
                        <div class="dot"></div>
                        <span id="stat-completed">0</span> sent
                    </div>
                    <div class="stat-pill failed">
                        <div class="dot"></div>
                        <span id="stat-failed">0</span> failed
                    </div>
                </div>
            </div>
        </header>

        <!-- ═══ TABS ════════════════════════════════════════════════════ -->
        <nav class="tabs-bar">
            <div class="tabs-inner">
                <button class="tab active" onclick="switchTab('upload')">
                    <span class="material-icons-round">upload_file</span>
                    Upload CSV
                </button>
                <button class="tab" onclick="switchTab('queue')">
                    <span class="material-icons-round">format_list_bulleted</span>
                    Queue
                </button>
            </div>
        </nav>

        <!-- ═══ CONTENT ══════════════════════════════════════════════════ -->
        <div class="content-area">
            <div class="container">

                <!-- ──── UPLOAD TAB ──────────────────────────────────── -->
                <div id="uploadTab" class="tab-content active">
                    <div class="compose-layout">

                        <!-- LEFT: main column -->
                        <div>
                            <!-- CSV Upload Card -->
                            <div class="card">
                                <div class="card-body">
                                    <p class="section-label">CSV Source</p>

                                    <div class="upload-zone" id="uploadZone">
                                        <input type="file" id="csvFileInput" accept=".csv" onchange="handleCSVUpload(event)">
                                        <div class="upload-icon-wrap">
                                            <span class="material-icons-round">upload_file</span>
                                        </div>
                                        <p class="upload-title">Drop a CSV file, or click to browse</p>
                                        <p class="upload-hint">Supports .csv up to 10 MB</p>
                                    </div>

                                    <!-- file loaded row (shown after upload) -->
                                    <div class="file-loaded-row" id="fileLoadedRow">
                                        <div class="file-loaded-meta">
                                            <div class="file-loaded-name" id="fileLoadedName">—</div>
                                            <div class="file-loaded-size" id="fileLoadedSize">—</div>
                                        </div>
                                        <button class="file-remove-btn" onclick="cancelAnalysis()" title="Remove">
                                            <span class="material-icons-round">close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Analysis Results (shown dynamically) -->
                            <div id="analysisResults" class="analysis-results">

                                <!-- sticky mapping header -->
                                <div class="mapping-sticky-header" id="mappingHeader">
                                    <div class="mapping-meta" id="mappingMeta">
                                        <span class="mapping-meta-item highlight">
                                            <span class="material-icons-round">table_rows</span>
                                            <span id="metaRows">0</span> rows
                                        </span>
                                        <span class="mapping-meta-item">
                                            <span class="material-icons-round">view_column</span>
                                            <span id="metaCols">0</span> columns
                                        </span>
                                        <span class="mapping-meta-item success" id="metaMappedWrap">
                                            <span class="material-icons-round">check_circle</span>
                                            <span id="metaMapped">0</span> mapped
                                        </span>
                                        <span class="mapping-meta-item warn" id="metaRequiredWrap" style="display:none">
                                            <span class="material-icons-round">warning_amber</span>
                                            <span id="metaRequired">0</span> required
                                        </span>
                                    </div>
                                    <button class="btn-ghost-sm" onclick="autoMatchFields()">
                                        <span class="material-icons-round">auto_fix_high</span>
                                        Auto-match
                                    </button>
                                </div>

                                <!-- Mapping Grid -->
                                <div class="card" id="mappingCard">
                                    <div class="card-body">
                                        <p class="section-label">Field Mapping — CSV column → Email field</p>
                                        <div class="mapping-grid-header">
                                            <div>CSV Column</div>
                                            <div></div>
                                            <div>Email Field</div>
                                        </div>
                                        <div class="mapping-grid" id="mappingGrid">
                                            <!-- rows injected by JS -->
                                        </div>
                                    </div>
                                </div>

                                <!-- Mapping Summary -->
                                <div class="mapping-summary-card" id="mappingSummaryCard">
                                    <div class="summary-header">Mapping Summary</div>
                                    <div class="summary-grid" id="summaryGrid">
                                        <!-- pairs injected by JS -->
                                    </div>
                                </div>

                                <!-- Preview Table -->
                                <div class="preview-table-wrap">
                                    <div class="preview-table-header">
                                        <span class="preview-table-title">Data Preview</span>
                                        <span class="preview-table-meta" id="previewMeta">First 5 rows</span>
                                    </div>
                                    <div class="preview-scroll">
                                        <table class="preview-table" id="previewTable">
                                            <!-- injected by JS -->
                                        </table>
                                    </div>
                                </div>

                            </div><!-- /analysisResults -->
                        </div><!-- /left column -->

                        <!-- RIGHT: attachment panel -->
                        <div class="attachment-panel" id="attachmentPanel">
                            <div class="attach-panel-header" onclick="toggleAttachPanel()">
                                <div class="attach-panel-header-left">
                                    <div class="attach-icon-wrap">
                                        <span class="material-icons-round">attach_file</span>
                                    </div>
                                    <div>
                                        <div class="attach-panel-title">Attachment</div>
                                        <div class="attach-panel-sub">Optional — sent to all recipients</div>
                                    </div>
                                </div>
                                <button class="attach-collapse-btn" id="attachCollapseBtn" aria-label="Collapse">
                                    <span class="material-icons-round">expand_more</span>
                                </button>
                            </div>

                            <div class="attach-panel-body" id="attachPanelBody">

                                <!-- Segmented control -->
                                <div class="segmented">
                                    <button class="segmented-btn active" id="segDrive" onclick="switchAttachmentTab('drive')">
                                        <span class="material-icons-round">add_to_drive</span>
                                       Choose from  Drive
                                    </button>
                                    <!-- <button class="segmented-btn" id="segUpload" onclick="switchAttachmentTab('upload')">
                                        <span class="material-icons-round">upload</span>
                                        Upload
                                    </button> -->
                                </div>

                                <!-- Drive content -->
                                <div id="driveAttachContent" class="attach-content active">
                                    <div class="loading-wrap" id="driveLoading">
                                        <div class="spinner-sm"></div>
                                        <p>Loading files…</p>
                                    </div>
                                    <div class="drive-list" id="driveFilesList" style="display:none"></div>
                                </div>

                                <!-- Upload content -->
                                <div id="uploadAttachContent" class="attach-content">
                                    <div class="upload-zone-mini">
                                        <input type="file" id="attachmentFileInput" onchange="handleAttachmentUpload(event)">
                                        <span class="material-icons-round">cloud_upload</span>
                                        <p>Click to upload</p>
                                        <small>Max 25 MB</small>
                                    </div>
                                </div>

                                <!-- Selected chip -->
                                <div class="attachment-chip" id="selectedAttachment">
                                    <span class="attachment-chip-icon" id="selectedAttachmentIcon">📎</span>
                                    <div class="attachment-chip-info">
                                        <div class="attachment-chip-name" id="selectedAttachmentName">—</div>
                                        <div class="attachment-chip-size" id="selectedAttachmentSize">—</div>
                                    </div>
                                    <button class="attachment-chip-clear" onclick="clearSelectedAttachment()" title="Remove">
                                        <span class="material-icons-round">close</span>
                                    </button>
                                </div>

                            </div><!-- /attach-panel-body -->
                        </div><!-- /attachment-panel -->

                    </div><!-- /compose-layout -->
                </div><!-- /uploadTab -->

                <!-- ──── QUEUE TAB ─────────────────────────────────── -->
                <div id="queueTab" class="tab-content">

                    <div class="queue-controls">
                        <button class="btn btn-primary" id="processQueueBtn" onclick="processQueue()">
                            <span class="material-icons-round">send</span>
                            Process Queue
                        </button>
                        <button class="btn btn-secondary" onclick="loadQueue()">
                            <span class="material-icons-round">refresh</span>
                            Refresh
                        </button>
                        <button class="btn btn-danger" onclick="clearQueue()">
                            <span class="material-icons-round">delete_sweep</span>
                            Clear Pending
                        </button>
                    </div>

                    <div id="processingProgress" class="progress-card">
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" id="progressFill"></div>
                        </div>
                        <p class="progress-label" id="progressText">Processing emails…</p>
                    </div>

                    <div class="queue-table-wrap">
                        <div class="queue-table-title">Email Queue</div>
                        <div id="queueTableContainer">
                            <div class="loading-wrap">
                                <div class="spinner-sm"></div>
                                <p>Loading queue…</p>
                            </div>
                        </div>
                    </div>

                </div><!-- /queueTab -->

            </div><!-- /container -->
        </div><!-- /content-area -->

    </div><!-- /main-content -->

    <!-- ═══ STICKY BOTTOM ACTION BAR ═══════════════════════════════════ -->
    <div class="action-bar" id="actionBar">
        <div class="action-bar-inner">
            <div class="action-bar-info">
                <span style="display:none;">Ready to queue <strong id="actionBarCount">0</strong> emails</span>
                <span id="actionBarAttach" style="margin-left:8px;display:none">
                    · 📎 <span id="actionBarAttachName" style="font-size:12px;color:var(--ink-3)"></span>
                </span>
            </div>
            <div class="action-btns">
                <button class="btn btn-secondary" onclick="cancelAnalysis()">Cancel</button>
                <button class="btn btn-primary" id="queueBtn" onclick="addToQueue()">
                    <span class="material-icons-round">add_circle_outline</span>
                    Queue <span id="queueBtnCount">0</span> Emails
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ JAVASCRIPT ═══════════════════════════════════════════════════ -->
    <script>
        /* ═══ GLOBALS ═══════════════════════════════════════════════════ */
        let currentCSVData   = null;
        let currentMapping   = {};
        let selectedAttachmentPath = null;

        const EMAIL_FIELDS = [
            { value: 'recipient_email',    label: 'Email Address' },
            { value: 'recipient_name',     label: 'Recipient Name' },
            { value: 'subject',            label: 'Subject' },
            { value: 'article_title',      label: 'Article Title' },
            { value: 'message_content',    label: 'Message Body' },
            { value: 'closing_wish',       label: 'Closing Wish' },
            { value: 'sender_name',        label: 'Sender Name' },
            { value: 'sender_designation', label: 'Sender Designation' },
        ];

        const REQUIRED_FIELDS = ['recipient_email'];

        // Auto-match keywords map
        const AUTOMATCH = {
            recipient_email:    ['mail', 'email', 'mail_id', 'mailid', 'e-mail'],
            recipient_name:     ['name', 'recipient', 'receiver'],
            subject:            ['subject', 'mail_subject', 'sub'],
            article_title:      ['article', 'title', 'article_title'],
            message_content:    ['message', 'body', 'content', 'personalised', 'personalized'],
            closing_wish:       ['closing', 'wish', 'footer'],
            sender_name:        ['sender', 'from_name', 'from'],
            sender_designation: ['designation', 'title', 'role', 'position'],
        };

        /* ═══ TAB SWITCHING ══════════════════════════════════════════════ */
        function switchTab(name) {
            document.querySelectorAll('.tab').forEach((t, i) => {
                t.classList.toggle('active', (name === 'upload' && i === 0) || (name === 'queue' && i === 1));
            });
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(name + 'Tab').classList.add('active');
        }

        /* ═══ ATTACH PANEL ═══════════════════════════════════════════════ */
        let attachExpanded = true;

        function toggleAttachPanel() {
            attachExpanded = !attachExpanded;
            const body = document.getElementById('attachPanelBody');
            const btn  = document.getElementById('attachCollapseBtn');
            if (attachExpanded) {
                body.style.height = body.scrollHeight + 'px';
                body.classList.remove('collapsed');
                btn.classList.remove('collapsed');
                setTimeout(() => body.style.height = '', 250);
            } else {
                body.style.height = body.scrollHeight + 'px';
                requestAnimationFrame(() => {
                    body.style.height = '0';
                    body.classList.add('collapsed');
                    btn.classList.add('collapsed');
                });
            }
        }

        function switchAttachmentTab(name) {
            document.getElementById('segDrive').classList.toggle('active', name === 'drive');
            document.getElementById('segUpload').classList.toggle('active', name === 'upload');
            document.getElementById('driveAttachContent').classList.toggle('active', name === 'drive');
            document.getElementById('uploadAttachContent').classList.toggle('active', name === 'upload');
        }

        /* ═══ DRIVE FILES ════════════════════════════════════════════════ */
        async function loadDriveFiles() {
            console.log('🔵 loadDriveFiles() called');
            
            const container = document.getElementById('driveFilesList');
            const loading   = document.getElementById('driveLoading');
            const panelBody = document.getElementById('attachPanelBody');

            console.log('🔵 Elements:', { container: !!container, loading: !!loading, panelBody: !!panelBody });

            // Force panel to be expanded
            if (panelBody) {
                panelBody.classList.remove('collapsed');
                panelBody.style.height = '';
            }

            if (!container || !loading) {
                console.error('❌ Required elements not found!');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'list_drive_files');

                const currentPath = window.location.pathname;
                const directory   = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
                const url         = directory + 'bulk_mail_backend.php';

                console.log('🔵 Fetching from:', url);
                
                const response = await fetch(url, { method: 'POST', body: formData });
                const data     = await response.json();

                console.log('🔵 Response:', data);

                if (data.success && data.files && data.files.length > 0) {
                    console.log('✅ Files found:', data.files.length);
                    
                    // FORCE visibility with !important
                    loading.style.cssText = 'display: none !important';
                    container.style.cssText = 'display: flex !important; flex-direction: column; gap: 4px; max-height: 260px; overflow-y: auto;';

                    container.innerHTML = data.files.map(f => {
                        // Escape quotes to prevent breaking onclick
                        const path = f.path.replace(/'/g, "\\'");
                        const name = f.name.replace(/'/g, "\\'");
                        return `
                        <div class="drive-item" onclick="selectDriveFile('${path}','${name}','${f.formatted_size}','${f.extension}')">
                            <span class="drive-item-icon">${getFileIcon(f.extension)}</span>
                            <div class="drive-item-info">
                                <div class="drive-item-name">${f.name}</div>
                                <div class="drive-item-size">${f.formatted_size}</div>
                            </div>
                            <div class="drive-item-check">
                                <span class="material-icons-round">check</span>
                            </div>
                        </div>
                    `;}).join('');
                    
                    console.log('✅ Rendered to DOM');
                } else if (data.success) {
                    // No files
                    container.style.cssText = 'display: none !important';
                    loading.style.cssText = 'display: block !important';
                    loading.innerHTML = `<div class="empty-state"><div class="empty-state-icon">📁</div><h3>No files in drive</h3><p>Upload files to File_Drive folder</p></div>`;
                } else {
                    // Error
                    container.style.cssText = 'display: none !important';
                    loading.style.cssText = 'display: block !important';
                    loading.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⚠️</div><h3>Could not load files</h3><p>${data.error || 'Unknown error'}</p></div>`;
                }
            } catch (err) {
                console.error('❌ Error:', err);
                container.style.cssText = 'display: none !important';
                loading.style.cssText = 'display: block !important';
                loading.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⚠️</div><h3>Load failed</h3><p>${err.message}</p></div>`;
            }
        }

        function selectDriveFile(path, name, size, ext) {
            document.querySelectorAll('.drive-item').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');

            selectedAttachmentPath = path;
            document.getElementById('selectedAttachmentIcon').textContent = getFileIcon(ext);
            document.getElementById('selectedAttachmentName').textContent = name;
            document.getElementById('selectedAttachmentSize').textContent = size;
            document.getElementById('selectedAttachment').classList.add('visible');
            updateActionBar();
        }

        function clearSelectedAttachment() {
            selectedAttachmentPath = null;
            document.getElementById('selectedAttachment').classList.remove('visible');
            document.querySelectorAll('.drive-item').forEach(el => el.classList.remove('selected'));
            updateActionBar();
        }

        function getFileIcon(ext) {
            const map = { pdf:'📄', doc:'📝', docx:'📝', txt:'📝', xls:'📊', xlsx:'📊', csv:'📊',
                          ppt:'📽️', pptx:'📽️', jpg:'🖼️', jpeg:'🖼️', png:'🖼️', gif:'🖼️',
                          zip:'🗜️', rar:'🗜️', '7z':'🗜️', mp4:'🎥', avi:'🎥', mp3:'🎵', wav:'🎵' };
            return map[ext] || '📎';
        }

        /* ═══ ATTACHMENT UPLOAD ══════════════════════════════════════════ */
        async function handleAttachmentUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (file.size > 25 * 1024 * 1024) { showAlert('error', 'File exceeds 25 MB limit'); return; }

            try {
                const formData = new FormData();
                formData.append('files[]', file);

                const response = await fetch('upload_handler.php', { method: 'POST', body: formData });
                const data     = await response.json();

                if (data.success && data.files && data.files.length > 0) {
                    const f   = data.files[0];
                    const ext = file.name.split('.').pop().toLowerCase();
                    selectedAttachmentPath = f.path;
                    document.getElementById('selectedAttachmentIcon').textContent = getFileIcon(ext);
                    document.getElementById('selectedAttachmentName').textContent = file.name;
                    document.getElementById('selectedAttachmentSize').textContent = formatBytes(file.size);
                    document.getElementById('selectedAttachment').classList.add('visible');
                    showAlert('success', 'File uploaded');
                    updateActionBar();
                } else {
                    showAlert('error', data.error || 'Upload failed');
                }
            } catch (err) {
                showAlert('error', 'Failed to upload file');
            }

            event.target.value = '';
        }

        /* ═══ CSV UPLOAD & ANALYSIS ══════════════════════════════════════ */
        async function handleCSVUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Show loading state
            const zone = document.getElementById('uploadZone');
            zone.style.pointerEvents = 'none';
            zone.innerHTML = `<div class="spinner-sm" style="margin:0 auto 8px"></div><p style="font-size:13px;color:var(--ink-3)">Analysing CSV…</p>`;

            try {
                const formData = new FormData();
                formData.append('csv_file', file);
                formData.append('action', 'analyze');

                const response = await fetch('bulk_mail_backend.php', { method: 'POST', body: formData });
                const data     = await response.json();

                // Restore upload zone
                zone.style.pointerEvents = '';
                zone.innerHTML = `
                    <input type="file" id="csvFileInput" accept=".csv" onchange="handleCSVUpload(event)">
                    <div class="upload-icon-wrap"><span class="material-icons-round">upload_file</span></div>
                    <p class="upload-title">Drop a CSV file, or click to browse</p>
                    <p class="upload-hint">Supports .csv up to 10 MB</p>`;

                if (data.success) {
                    currentCSVData = data;
                    // Show file loaded row
                    document.getElementById('fileLoadedName').textContent = data.filename;
                    document.getElementById('fileLoadedSize').textContent = data.csv_columns.length + ' columns · ' + data.total_rows + ' rows';
                    document.getElementById('fileLoadedRow').classList.add('visible');
                    displayAnalysisResults(data);
                } else {
                    showAlert('error', data.error || 'Failed to analyse CSV');
                }
            } catch (err) {
                // Restore upload zone on error
                zone.style.pointerEvents = '';
                zone.innerHTML = `
                    <input type="file" id="csvFileInput" accept=".csv" onchange="handleCSVUpload(event)">
                    <div class="upload-icon-wrap"><span class="material-icons-round">upload_file</span></div>
                    <p class="upload-title">Drop a CSV file, or click to browse</p>
                    <p class="upload-hint">Supports .csv up to 10 MB</p>`;
                showAlert('error', 'Failed to upload CSV: ' + err.message);
            }

            event.target.value = '';
        }

        function displayAnalysisResults(data) {
            currentMapping = data.suggested_mapping || {};

            buildMappingGrid(data.csv_columns);
            buildPreviewTable(data.csv_columns, data.preview_rows);
            updateMappingMeta(data.total_rows, data.csv_columns.length);
            updateMappingSummary();

            document.getElementById('analysisResults').classList.add('active');

            setTimeout(() => {
                document.getElementById('analysisResults').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 80);

            updateActionBar(data.total_rows);
        }

        /* ═══ MAPPING GRID ═══════════════════════════════════════════════ */
        function buildMappingGrid(columns) {
            const grid = document.getElementById('mappingGrid');
            grid.innerHTML = '';

            columns.forEach(col => {
                const mapped    = currentMapping[col] || '';
                const isMatched = !!mapped;
                const isReq     = REQUIRED_FIELDS.includes(mapped);

                const row = document.createElement('div');
                row.className = 'mapping-row' + (isMatched ? ' is-matched' : '');
                row.dataset.csvCol = col;

                // Determine if this column is required
                const reqBadge  = isReq
                    ? `<span class="field-badge badge-required">Required</span>` : '';
                const autoBadge = isMatched
                    ? `<span class="field-badge badge-auto">Auto</span>` : '';

                const options = EMAIL_FIELDS.map(f =>
                    `<option value="${f.value}" ${mapped === f.value ? 'selected' : ''}>${f.label}</option>`
                ).join('');

                row.innerHTML = `
                    <div class="csv-field-label">
                        <span class="name" title="${col}">${col}</span>
                        ${reqBadge}${autoBadge}
                    </div>
                    <div class="arrow-col">
                        <svg viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 7h10M8 3.5L12 7l-4 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <select class="mapping-select ${mapped ? 'mapped' : ''}" onchange="updateMapping('${col}', this.value, this)" title="${col}">
                        <option value="">Skip this field</option>
                        ${options}
                    </select>`;

                grid.appendChild(row);
            });
        }

        function updateMapping(col, value, selectEl) {
            if (value) {
                currentMapping[col] = value;
            } else {
                delete currentMapping[col];
            }

            // Update row style
            const row = selectEl.closest('.mapping-row');
            row.classList.toggle('is-matched', !!value);
            selectEl.classList.toggle('mapped', !!value);

            // Refresh badges
            const labelEl = row.querySelector('.csv-field-label');
            const existingBadges = labelEl.querySelectorAll('.field-badge');
            existingBadges.forEach(b => b.remove());

            if (value) {
                const isReq = REQUIRED_FIELDS.includes(value);
                if (isReq) labelEl.insertAdjacentHTML('beforeend', `<span class="field-badge badge-required">Required</span>`);
                labelEl.insertAdjacentHTML('beforeend', `<span class="field-badge badge-auto">Mapped</span>`);
            }

            updateMappingMeta();
            updateMappingSummary();
        }

        function autoMatchFields() {
            if (!currentCSVData) return;

            currentCSVData.csv_columns.forEach(col => {
                const lower = col.toLowerCase().replace(/[^a-z0-9]/g, '');
                for (const [field, keywords] of Object.entries(AUTOMATCH)) {
                    if (keywords.some(k => lower.includes(k) || k.includes(lower))) {
                        currentMapping[col] = field;
                        break;
                    }
                }
            });

            buildMappingGrid(currentCSVData.csv_columns);
            updateMappingMeta(currentCSVData.total_rows, currentCSVData.csv_columns.length);
            updateMappingSummary();
        }

        function updateMappingMeta(rows, cols) {
            if (rows) document.getElementById('metaRows').textContent = rows;
            if (cols)  document.getElementById('metaCols').textContent = cols;

            const mapped   = Object.keys(currentMapping).length;
            const required = REQUIRED_FIELDS.filter(f => !Object.values(currentMapping).includes(f)).length;

            document.getElementById('metaMapped').textContent   = mapped;
            document.getElementById('metaRequired').textContent = required;

            const reqWrap = document.getElementById('metaRequiredWrap');
            reqWrap.style.display = required > 0 ? 'flex' : 'none';
        }

        function updateMappingSummary() {
            const grid = document.getElementById('summaryGrid');
            const entries = Object.entries(currentMapping);

            if (entries.length === 0) {
                grid.innerHTML = `<div class="summary-pair" style="color:var(--ink-4);font-size:12px;grid-column:1/-1">No fields mapped yet</div>`;
                return;
            }

            grid.innerHTML = entries.map(([csv, field]) => {
                const fieldLabel = EMAIL_FIELDS.find(f => f.value === field)?.label || field;
                return `
                    <div class="summary-pair">
                        <code class="summary-csv">${csv}</code>
                        <span class="summary-arrow">→</span>
                        <span class="summary-field">${fieldLabel}</span>
                    </div>`;
            }).join('');
        }

        /* ═══ PREVIEW TABLE ══════════════════════════════════════════════ */
        function buildPreviewTable(columns, rows) {
            const table = document.getElementById('previewTable');
            const meta  = document.getElementById('previewMeta');

            meta.textContent = `First ${rows.length} of ${currentCSVData?.total_rows || '?'} rows`;

            table.innerHTML = `
                <thead>
                    <tr>${columns.map(c => `<th title="${c}">${c}</th>`).join('')}</tr>
                </thead>
                <tbody>
                    ${rows.map(row =>
                        `<tr>${columns.map(c =>
                            `<td title="${(row[c]||'').replace(/"/g,'&quot;')}">${row[c] || ''}</td>`
                        ).join('')}</tr>`
                    ).join('')}
                </tbody>`;
        }

        /* ═══ ACTION BAR ═════════════════════════════════════════════════ */
        function updateActionBar(count) {
            const bar        = document.getElementById('actionBar');
            const rowCount   = count ?? currentCSVData?.total_rows ?? 0;
            const attachName = document.getElementById('selectedAttachmentName').textContent;
            const hasAttach  = !!selectedAttachmentPath;

            document.getElementById('actionBarCount').textContent  = rowCount;
            
            // Safely update queue button count (element may be temporarily removed during processing)
            const queueBtnCount = document.getElementById('queueBtnCount');
            if (queueBtnCount) {
                queueBtnCount.textContent = rowCount;
            }

            const attachWrap = document.getElementById('actionBarAttach');
            attachWrap.style.display = hasAttach ? 'inline' : 'none';
            if (hasAttach) document.getElementById('actionBarAttachName').textContent = attachName;

            bar.classList.toggle('visible', !!currentCSVData);
        }

        /* ═══ CANCEL ANALYSIS ════════════════════════════════════════════ */
        function cancelAnalysis() {
            currentCSVData = null;
            currentMapping = {};
            document.getElementById('analysisResults').classList.remove('active');
            document.getElementById('fileLoadedRow').classList.remove('visible');
            document.getElementById('actionBar').classList.remove('visible');
        }

        /* ═══ ADD TO QUEUE ═══════════════════════════════════════════════ */
        async function addToQueue() {
            const btn = document.getElementById('queueBtn');
            btn.disabled = true;
            btn.innerHTML = `<div class="spinner-sm" style="width:14px;height:14px;border-width:2px;margin:0"></div> Processing…`;

            try {
                const formData = new FormData();
                formData.append('action', 'parse_full_csv');
                const parseResp = await fetch('bulk_mail_backend.php', { method: 'POST', body: formData });
                const parseData = await parseResp.json();

                if (!parseData.success) throw new Error(parseData.error || 'Failed to parse CSV');

                const emails = parseData.rows.map(row => {
                    const email = {};
                    for (const [csvCol, emailField] of Object.entries(currentMapping)) {
                        email[emailField] = row[csvCol] || '';
                    }
                    return email;
                });

                const addResp = await fetch('bulk_mail_backend.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add_to_queue', emails, drive_file_path: selectedAttachmentPath })
                });
                const addData = await addResp.json();

                if (addData.success) {
                    showAlert('success', `Added ${addData.added} emails to queue`);
                    cancelAnalysis();
                    clearSelectedAttachment();
                    switchTab('queue');
                    loadQueue();
                } else {
                    showAlert('error', addData.error || 'Failed to add to queue');
                }
            } catch (err) {
                showAlert('error', 'Failed: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<span class="material-icons-round">add_circle_outline</span> Queue <span id="queueBtnCount">${currentCSVData?.total_rows||0}</span> Emails`;
            }
        }

        /* ═══ QUEUE MANAGEMENT ═══════════════════════════════════════════ */
        async function loadQueue() {
            try {
                const resp = await fetch('process_bulk_mail.php?action=status');
                const data = await resp.json();
                if (data.success) {
                    document.getElementById('stat-pending').textContent   = data.pending   || 0;
                    document.getElementById('stat-completed').textContent = data.completed || 0;
                    document.getElementById('stat-failed').textContent    = data.failed    || 0;
                    await loadQueueList();
                }
            } catch (err) { console.error('Queue load error:', err); }
        }

        async function loadQueueList() {
            const container = document.getElementById('queueTableContainer');
            try {
                const resp = await fetch('process_bulk_mail.php?action=queue_list');
                const data = await resp.json();
                if (data.success) {
                    if (data.queue && data.queue.length > 0) {
                        container.innerHTML = `
                            <table class="queue-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.queue.map(item => `
                                        <tr>
                                            <td style="color:var(--ink-4);font-size:12px">#${item.id}</td>
                                            <td>
                                                <div style="font-weight:600;font-size:13px">${item.recipient_name || 'N/A'}</div>
                                                <div style="font-size:11px;color:var(--ink-4);margin-top:2px">${item.recipient_email}</div>
                                            </td>
                                            <td style="font-size:13px">${item.subject || '—'}</td>
                                            <td>
                                                <span class="status-chip status-${item.status}">
                                                    <span class="dot"></span>
                                                    ${item.status}
                                                </span>
                                            </td>
                                            <td style="font-size:12px;color:var(--ink-3)">${formatDate(item.created_at)}</td>
                                            <td style="font-size:12px;color:var(--ink-3)">${item.completed_at ? formatDate(item.completed_at) : '—'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>`;
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <h3>No emails in queue</h3>
                                <p>Upload a CSV to get started</p>
                            </div>`;
                    }
                }
            } catch (err) { console.error('Queue list error:', err); }
        }

        async function processQueue() {
            const btn              = document.getElementById('processQueueBtn');
            const progressCard     = document.getElementById('processingProgress');
            const progressFill     = document.getElementById('progressFill');
            const progressText     = document.getElementById('progressText');
            const pending          = parseInt(document.getElementById('stat-pending').textContent);

            if (pending === 0) { showAlert('error', 'No pending emails in queue'); return; }

            // Disable button and show progress card
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons-round">pause_circle</span> Processing...';
            progressCard.classList.add('active');

            let processed = 0, success = 0, failed = 0;
            let failedRecipients = [];
            let continueProcessing = true;

            // Process emails one by one with real-time progress
            while (continueProcessing) {
                try {
                    const resp = await fetch('process_bulk_mail.php?action=process', { method: 'POST' });
                    const data = await resp.json();

                    if (data.success) {
                        if (data.no_more) {
                            // No more emails to process
                            continueProcessing = false;
                            break;
                        }

                        processed++;

                        if (data.email_sent) {
                            success++;
                            console.log(`✓ Email sent to: ${data.recipient}`);
                        } else {
                            failed++;
                            failedRecipients.push({
                                email: data.recipient,
                                error: data.error || 'Unknown error'
                            });
                            console.error(`✗ Failed to send to: ${data.recipient} - ${data.error}`);
                        }

                        // Update progress bar IMMEDIATELY after each email
                        const percentage = Math.round((processed / pending) * 100);
                        progressFill.style.width = percentage + '%';
                        progressText.textContent = `Processing ${processed}/${pending} (${percentage}%) · ${success} sent, ${failed} failed`;

                        // Update stats in real-time
                        await loadQueue();

                    } else {
                        // Error in processing
                        console.error('Processing error:', data.error);
                        showAlert('error', 'Error: ' + data.error);
                        continueProcessing = false;
                        break;
                    }

                    // Small delay to prevent overwhelming the server (250ms between sends)
                    await new Promise(r => setTimeout(r, 250));

                } catch (error) {
                    console.error('Network error:', error);
                    failed++;
                    processed++;
                    
                    // Update progress even on error
                    const percentage = Math.round((processed / pending) * 100);
                    progressFill.style.width = percentage + '%';
                    progressText.textContent = `Processing ${processed}/${pending} (${percentage}%) · ${success} sent, ${failed} failed`;
                    
                    // Continue to next email after network error
                    await new Promise(r => setTimeout(r, 250));
                }
            }

            // COMPLETION - Show final status
            progressFill.style.width = '100%';
            progressText.textContent = `Completed — ${success} sent successfully, ${failed} failed`;
            
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">send</span> Process Queue';

            // Hide progress card after 5 seconds
            setTimeout(() => {
                progressCard.classList.remove('active');
                progressFill.style.width = '0%';
            }, 5000);

            // Show completion alert
            if (failed === 0) {
                showAlert('success', `All ${success} emails sent successfully! 🎉`);
            } else if (success === 0) {
                showAlert('error', `All ${failed} emails failed to send. Check error messages in the queue.`);
            } else {
                showAlert('info', `Sent ${success} emails. ${failed} failed. Check queue for details.`);
            }

            // Log failed emails to console for debugging
            if (failedRecipients.length > 0) {
                console.group('Failed Recipients');
                failedRecipients.forEach(({email, error}) => {
                    console.error(`${email}: ${error}`);
                });
                console.groupEnd();
            }

            // Final queue reload
            await loadQueue();
        }

        async function clearQueue() {
            if (!confirm('Clear all pending emails?')) return;
            try {
                const resp = await fetch('process_bulk_mail.php?action=clear', { method: 'POST' });
                const data = await resp.json();
                data.success ? showAlert('success', data.message) : showAlert('error', data.error);
                loadQueue();
            } catch { showAlert('error', 'Failed to clear queue'); }
        }

        /* ═══ UTILITIES ══════════════════════════════════════════════════ */
        function formatDate(str) {
            if (!str) return 'N/A';
            return new Date(str).toLocaleString();
        }

        function formatBytes(bytes) {
            if (!bytes) return '0 B';
            const k = 1024, s = ['B','KB','MB','GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + s[i];
        }

        function showAlert(type, message) {
            const icons = { success: 'check_circle', error: 'error', info: 'info' };
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<span class="material-icons-round">${icons[type]}</span><span>${message}</span>`;
            const container = document.querySelector('.container');
            container.insertBefore(alert, container.firstChild);
            setTimeout(() => alert.remove(), 4500);
        }

        /* ═══ DRAG & DROP ════════════════════════════════════════════════ */
        const uploadZone = document.getElementById('uploadZone');

        uploadZone.addEventListener('dragover',  e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
        uploadZone.addEventListener('dragleave', ()  => uploadZone.classList.remove('dragover'));
        uploadZone.addEventListener('drop', e => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) handleCSVUpload({ target: { files }, value: '' });
        });

        /* ═══ INIT ═══════════════════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', () => {
            loadQueue();
            loadDriveFiles();
        });
    </script>
</body>
</html>