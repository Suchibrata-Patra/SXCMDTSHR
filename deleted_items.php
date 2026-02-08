<?php
// deleted_items.php - Ultra-Premium Luxury Interface
session_start();
require 'config.php';
require 'db_config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'recipient' => $_GET['recipient'] ?? '',
    'subject' => $_GET['subject'] ?? '',
    'label_id' => $_GET['label_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Function to fetch only deleted items (current_status = 0)
function getDeletedEmailsLocal($userEmail, $limit, $offset, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    $sql = "SELECT se.*, l.label_name, l.label_color 
            FROM sent_emails se 
            LEFT JOIN labels l ON se.label_id = l.id 
            WHERE se.sender_email = :sender_email 
            AND se.current_status = 0";

    $params = [':sender_email' => $userEmail];

    if (!empty($filters['search'])) {
        $sql .= " AND (se.recipient_email LIKE :search OR se.subject LIKE :search OR se.message_body LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['recipient'])) {
        $sql .= " AND se.recipient_email LIKE :recipient";
        $params[':recipient'] = '%' . $filters['recipient'] . '%';
    }
    
    if (!empty($filters['subject'])) {
        $sql .= " AND se.subject LIKE :subject";
        $params[':subject'] = '%' . $filters['subject'] . '%';
    }
    
    if (!empty($filters['label_id'])) {
        if ($filters['label_id'] === 'unlabeled') {
            $sql .= " AND se.label_id IS NULL";
        } else {
            $sql .= " AND se.label_id = :label_id";
            $params[':label_id'] = $filters['label_id'];
        }
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(se.sent_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(se.sent_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }

    $sql .= " ORDER BY se.sent_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->execute();
    return $stmt->fetchAll();
}

function getDeletedEmailCount($userEmail, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return 0;
    
    $sql = "SELECT COUNT(*) as count FROM sent_emails se 
            WHERE se.sender_email = :sender_email 
            AND se.current_status = 0";
    
    $params = [':sender_email' => $userEmail];
    
    if (!empty($filters['search'])) {
        $sql .= " AND (se.recipient_email LIKE :search OR se.subject LIKE :search OR se.message_body LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['recipient'])) {
        $sql .= " AND se.recipient_email LIKE :recipient";
        $params[':recipient'] = '%' . $filters['recipient'] . '%';
    }
    
    if (!empty($filters['subject'])) {
        $sql .= " AND se.subject LIKE :subject";
        $params[':subject'] = '%' . $filters['subject'] . '%';
    }
    
    if (!empty($filters['label_id'])) {
        if ($filters['label_id'] === 'unlabeled') {
            $sql .= " AND se.label_id IS NULL";
        } else {
            $sql .= " AND se.label_id = :label_id";
            $params[':label_id'] = $filters['label_id'];
        }
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(se.sent_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(se.sent_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

// Pagination & Data Retrieval
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$sentEmails = getDeletedEmailsLocal($userEmail, $perPage, $offset, $filters);
$totalEmails = getDeletedEmailCount($userEmail, $filters);
$totalPages = ceil($totalEmails / $perPage);
$labels = getLabelCounts($userEmail);
$hasActiveFilters = !empty(array_filter($filters));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Items — Mail</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@300;400;500;600;700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Archivo:wght@300;400;500;600;700&family=Geist:wght@300;400;500;600;700&display=swap');
        
        :root {
            --pearl-white: #FAFBFC; --silk-white: #FFFFFF; --champagne: #F8F8F6;
            --platinum: rgba(255,255,255,0.72); --crystal-glass: rgba(255,255,255,0.88);
            --ink-black: #0A0A0A; --charcoal: #1A1A1A; --slate-deep: #3C3C43;
            --slate: rgba(60,60,67,0.85); --slate-medium: rgba(60,60,67,0.60);
            --slate-light: rgba(60,60,67,0.35); --slate-whisper: rgba(60,60,67,0.15);
            --sapphire: #0066CC; --sapphire-glow: rgba(0,102,204,0.08);
            --sapphire-shimmer: rgba(0,102,204,0.15);
            --emerald: #2FB344; --emerald-subtle: rgba(47,179,68,0.08);
            --emerald-border: rgba(47,179,68,0.25);
            --ruby: #E8314C; --ruby-subtle: rgba(232,49,76,0.08);
            --ruby-border: rgba(232,49,76,0.25);
            --shadow-float: 0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.02);
            --shadow-lift: 0 4px 12px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.03);
            --shadow-elevate: 0 12px 32px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
            --shadow-luxe: 0 20px 60px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.06);
            --shadow-inset-silk: inset 0 1px 0 rgba(255,255,255,0.6);
            --ease-luxe: cubic-bezier(0.4,0.0,0.2,1);
            --ease-silk: cubic-bezier(0.25,0.1,0.25,1);
            --duration-swift: 150ms; --duration-smooth: 250ms; --duration-elegant: 400ms;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Geist', -apple-system, sans-serif;
            background: var(--pearl-white);
            color: var(--slate-deep);
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none; z-index: 9999; mix-blend-mode: overlay;
        }

        #main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

        .toolbar {
            background: var(--crystal-glass);
            backdrop-filter: blur(40px) saturate(200%);
            -webkit-backdrop-filter: blur(40px) saturate(200%);
            border-bottom: 1px solid rgba(0,0,0,0.06);
            box-shadow: var(--shadow-inset-silk), 0 1px 3px rgba(0,0,0,0.04);
            padding: 18px 28px;
            display: flex; align-items: center; gap: 20px;
            min-height: 64px; z-index: 100; position: relative;
        }

        .toolbar::after {
            content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6) 50%, transparent);
        }

        .toolbar-title {
            font-family: 'Archivo', sans-serif;
            font-size: 20px; font-weight: 600; color: var(--ink-black);
            letter-spacing: -0.6px;
        }

        .search-container { flex: 1; max-width: 520px; position: relative; }

        .search-input {
            width: 100%; padding: 11px 40px 11px 44px;
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 12px; font-size: 14px;
            color: var(--slate-deep);
            transition: all var(--duration-smooth) var(--ease-luxe);
            font-family: 'Geist', sans-serif;
            box-shadow: var(--shadow-float), inset 0 1px 2px rgba(0,0,0,0.02);
        }

        .search-input::placeholder { color: var(--slate-light); }

        .search-input:focus {
            outline: none; background: var(--silk-white);
            border-color: var(--sapphire);
            box-shadow: 0 0 0 4px var(--sapphire-glow), var(--shadow-lift);
            transform: translateY(-1px);
        }

        .search-icon {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: var(--slate-medium); font-size: 20px; pointer-events: none;
        }

        .clear-search {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,0.04); border: none;
            color: var(--slate-medium); cursor: pointer;
            padding: 5px; border-radius: 6px;
            transition: all var(--duration-swift) var(--ease-silk);
            font-size: 18px; display: flex;
            align-items: center; justify-content: center;
        }

        .clear-search:hover { background: rgba(0,0,0,0.08); color: var(--slate-deep); }
        .clear-search:active { transform: translateY(-50%) scale(0.94); }

        .toolbar-actions { display: flex; gap: 8px; margin-left: auto; }

        .icon-btn {
            background: rgba(255,255,255,0.75);
            border: 1px solid rgba(0,0,0,0.06);
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--slate-medium); cursor: pointer;
            transition: all var(--duration-smooth) var(--ease-luxe);
            box-shadow: var(--shadow-float);
        }

        .icon-btn:hover {
            background: var(--silk-white);
            color: var(--slate-deep);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lift);
            border-color: rgba(0,0,0,0.1);
        }

        .icon-btn:active { transform: translateY(0); transition-duration: var(--duration-swift); }

        .icon-btn.active {
            background: var(--sapphire-shimmer);
            color: var(--sapphire);
            border-color: var(--sapphire);
        }

        .icon-btn .material-icons-round { font-size: 20px; }

        .selection-bar {
            position: absolute; top: 84px; left: 50%; transform: translateX(-50%) translateY(-300%);
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(50px) saturate(200%);
            -webkit-backdrop-filter: blur(50px) saturate(200%);
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: var(--shadow-luxe), var(--shadow-inset-silk);
            border-radius: 18px; padding: 16px 28px;
            display: flex; align-items: center; gap: 20px;
            z-index: 200; opacity: 0; pointer-events: none;
            transition: all var(--duration-elegant) var(--ease-luxe);
        }

        .selection-bar::before {
            content: ''; position: absolute; inset: -1px; border-radius: 18px; padding: 1px;
            background: linear-gradient(135deg, rgba(255,255,255,0.8), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none;
        }

        .selection-bar.active { opacity: 1; pointer-events: all; transform: translateX(-50%) translateY(0); }

        .selection-count {
            font-family: 'Archivo', sans-serif; font-size: 15px; font-weight: 500;
            color: var(--slate); letter-spacing: -0.2px;
        }

        .selection-divider {
            width: 1px; height: 28px;
            background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.1) 20%, rgba(0,0,0,0.1) 80%, transparent);
        }

        .selection-actions { display: flex; gap: 10px; }

        .action-btn {
            height: 38px; padding: 0 18px; border-radius: 11px;
            font-size: 14px; font-weight: 500; cursor: pointer;
            transition: all var(--duration-smooth) var(--ease-luxe);
            border: 1px solid;
            display: flex; align-items: center; gap: 8px;
            font-family: 'Geist', sans-serif; letter-spacing: -0.2px;
            position: relative; overflow: hidden;
        }

        .action-btn::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left var(--duration-elegant) var(--ease-silk);
        }

        .action-btn:hover::before { left: 100%; }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lift);
        }

        .action-btn:active {
            transform: translateY(0) scale(0.98);
            transition-duration: var(--duration-swift);
        }

        .action-btn .material-icons-round { font-size: 17px; }

        .action-btn.restore {
            background: linear-gradient(135deg, var(--emerald-subtle), rgba(47,179,68,0.12));
            border-color: var(--emerald-border); color: var(--emerald);
            box-shadow: 0 2px 8px rgba(47,179,68,0.12);
        }

        .action-btn.restore:hover {
            background: linear-gradient(135deg, rgba(47,179,68,0.12), rgba(47,179,68,0.18));
            box-shadow: 0 4px 16px rgba(47,179,68,0.2);
        }

        .action-btn.delete {
            background: linear-gradient(135deg, var(--ruby-subtle), rgba(232,49,76,0.12));
            border-color: var(--ruby-border); color: var(--ruby);
            box-shadow: 0 2px 8px rgba(232,49,76,0.12);
        }

        .action-btn.delete:hover {
            background: linear-gradient(135deg, rgba(232,49,76,0.12), rgba(232,49,76,0.18));
            box-shadow: 0 4px 16px rgba(232,49,76,0.2);
        }

        .action-btn.clear {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
            border-color: rgba(0,0,0,0.1); color: var(--slate-medium);
            box-shadow: var(--shadow-float);
        }

        .action-btn.clear:hover {
            background: var(--silk-white);
            color: var(--slate-deep);
        }

        .filter-panel {
            position: absolute; top: 64px; right: 0; width: 360px;
            background: var(--silk-white);
            border-left: 1px solid rgba(0,0,0,0.08);
            box-shadow: var(--shadow-elevate);
            height: calc(100vh - 64px);
            transform: translateX(100%);
            transition: transform var(--duration-elegant) var(--ease-luxe);
            z-index: 150; overflow-y: auto;
        }

        .filter-panel.active { transform: translateX(0); }

        .filter-header {
            padding: 24px 28px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex; align-items: center; justify-content: space-between;
            background: linear-gradient(180deg, var(--champagne), var(--silk-white));
        }

        .filter-title {
            font-family: 'Archivo', sans-serif; font-size: 18px; font-weight: 600;
            color: var(--ink-black); letter-spacing: -0.4px;
        }

        .filter-body { padding: 28px; }

        .filter-group { margin-bottom: 24px; }

        .filter-label {
            font-family: 'Archivo', sans-serif; font-size: 11px; font-weight: 600;
            color: var(--slate-medium); margin-bottom: 10px; display: block;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .filter-input, .filter-select {
            width: 100%; padding: 12px 16px;
            background: rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 10px; font-size: 14px;
            color: var(--slate-deep);
            font-family: 'Geist', sans-serif;
            transition: all var(--duration-smooth) var(--ease-luxe);
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
        }

        .filter-input:focus, .filter-select:focus {
            outline: none; border-color: var(--sapphire);
            background: var(--silk-white);
            box-shadow: 0 0 0 4px var(--sapphire-glow), var(--shadow-float);
            transform: translateY(-1px);
        }

        .filter-actions {
            display: flex; gap: 10px; padding: 20px 28px 28px;
            border-top: 1px solid rgba(0,0,0,0.06);
            background: var(--champagne);
        }

        .filter-btn {
            flex: 1; padding: 12px 20px; border-radius: 11px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            transition: all var(--duration-smooth) var(--ease-luxe);
            border: 1px solid;
            font-family: 'Geist', sans-serif; letter-spacing: -0.2px;
            box-shadow: var(--shadow-float);
        }

        .filter-btn.primary {
            background: linear-gradient(135deg, var(--sapphire), #0052A3);
            color: var(--silk-white); border-color: var(--sapphire);
        }

        .filter-btn.primary:hover {
            background: linear-gradient(135deg, #0052A3, var(--sapphire));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lift);
        }

        .filter-btn.secondary {
            background: var(--silk-white);
            color: var(--slate-deep);
            border-color: rgba(0,0,0,0.1);
        }

        .filter-btn.secondary:hover {
            background: var(--champagne);
            transform: translateY(-2px);
        }

        .filter-btn:active {
            transform: translateY(0) scale(0.98);
            transition-duration: var(--duration-swift);
        }

        .active-filters {
            padding: 14px 28px;
            background: var(--silk-white);
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex; gap: 10px; flex-wrap: wrap;
        }

        .filter-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            background: linear-gradient(135deg, rgba(0,0,0,0.04), rgba(0,0,0,0.06));
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 20px; font-size: 12px; font-weight: 500;
            color: var(--slate-deep); letter-spacing: -0.1px;
            box-shadow: var(--shadow-float);
            transition: all var(--duration-swift) var(--ease-silk);
        }

        .filter-chip:hover {
            background: linear-gradient(135deg, rgba(0,0,0,0.06), rgba(0,0,0,0.08));
            transform: translateY(-1px);
        }

        .filter-chip .material-icons-round {
            font-size: 16px; cursor: pointer;
            transition: all var(--duration-swift) var(--ease-silk);
        }

        .filter-chip .material-icons-round:hover {
            color: var(--ruby);
            transform: rotate(90deg);
        }

        .content-area { flex: 1; overflow-y: auto; padding: 28px; }

        .content-area::-webkit-scrollbar { width: 10px; }
        .content-area::-webkit-scrollbar-track { background: transparent; }
        .content-area::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, rgba(0,0,0,0.12), rgba(0,0,0,0.18));
            border-radius: 10px; border: 2px solid var(--pearl-white);
        }
        .content-area::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, rgba(0,0,0,0.18), rgba(0,0,0,0.24));
        }

        .email-list { display: flex; flex-direction: column; gap: 10px; }

        .email-row {
            background: var(--silk-white); border-radius: 12px;
            box-shadow: var(--shadow-float);
            padding: 18px 20px;
            display: grid;
            grid-template-columns: 32px 220px 1fr auto 110px;
            gap: 18px; align-items: center;
            transition: all var(--duration-smooth) var(--ease-luxe);
            cursor: pointer;
            border: 1px solid rgba(0,0,0,0.04);
            position: relative; overflow: hidden;
        }

        .email-row::before {
            content: ''; position: absolute; top: 0; left: 0; width: 3px; height: 0;
            background: linear-gradient(180deg, var(--sapphire), var(--emerald));
            transition: height var(--duration-smooth) var(--ease-luxe);
        }

        .email-row:hover {
            box-shadow: var(--shadow-lift);
            transform: translateY(-2px);
            border-color: rgba(0,0,0,0.08);
        }

        .email-row:hover::before { height: 100%; }

        .email-row.selected {
            background: linear-gradient(135deg, var(--sapphire-glow), var(--sapphire-shimmer));
            box-shadow: 0 0 0 2px var(--sapphire), var(--shadow-lift);
            border-color: var(--sapphire);
        }

        .email-row.selected::before { height: 100%; background: var(--sapphire); }

        .email-checkbox-col { display: flex; align-items: center; justify-content: center; }

        .email-checkbox {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid rgba(0,0,0,0.15);
            appearance: none; cursor: pointer;
            transition: all var(--duration-smooth) var(--ease-luxe);
            position: relative; background: var(--silk-white);
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
        }

        .email-checkbox:hover {
            border-color: var(--sapphire);
            box-shadow: 0 0 0 4px var(--sapphire-glow);
            transform: scale(1.1);
        }

        .email-checkbox:checked {
            background: linear-gradient(135deg, var(--sapphire), #0052A3);
            border-color: var(--sapphire);
            box-shadow: 0 2px 8px rgba(0,102,204,0.3);
        }

        .email-checkbox:checked::after {
            content: '✓'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            color: white; font-size: 12px; font-weight: bold;
        }

        .email-from {
            font-family: 'Geist', sans-serif; font-size: 14px; font-weight: 600;
            color: var(--slate-deep);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            letter-spacing: -0.2px;
        }

        .email-content { min-width: 0; }

        .email-subject {
            font-family: 'Archivo', sans-serif; font-size: 16px; font-weight: 600;
            color: var(--ink-black); margin-bottom: 4px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            letter-spacing: -0.4px;
        }

        .email-preview {
            font-family: 'Geist', sans-serif; font-size: 13px;
            color: var(--slate-medium);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            line-height: 1.5;
        }

        .email-label { display: flex; align-items: center; justify-content: center; }

        .label-dot {
            width: 10px; height: 10px; border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        .email-meta {
            font-family: 'Geist', sans-serif; font-size: 13px;
            color: var(--slate-light); text-align: right;
            display: flex; align-items: center; justify-content: flex-end; gap: 6px;
            font-weight: 500; letter-spacing: -0.1px;
        }

        .email-meta .material-icons-round { font-size: 17px; }

        .empty-state {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 100px 40px; text-align: center;
        }

        .empty-icon {
            width: 88px; height: 88px;
            background: linear-gradient(135deg, rgba(0,0,0,0.02), rgba(0,0,0,0.04));
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px; box-shadow: var(--shadow-float);
        }

        .empty-icon .material-icons-round { font-size: 40px; color: var(--slate-light); }

        .empty-state h3 {
            font-family: 'Archivo', sans-serif; font-size: 22px; font-weight: 600;
            color: var(--slate-deep); margin-bottom: 8px; letter-spacing: -0.6px;
        }

        .empty-state p {
            font-family: 'Geist', sans-serif; font-size: 15px;
            color: var(--slate-medium); max-width: 460px; line-height: 1.6;
        }

        .pagination {
            display: flex; justify-content: center; align-items: center; gap: 6px;
            padding: 20px 28px;
            background: linear-gradient(180deg, var(--silk-white), var(--champagne));
            border-top: 1px solid rgba(0,0,0,0.06);
        }

        .page-button {
            min-width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 10px; font-size: 14px; font-weight: 500;
            color: var(--slate-deep); text-decoration: none;
            transition: all var(--duration-smooth) var(--ease-luxe);
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: var(--shadow-float);
        }

        .page-button:hover {
            background: var(--silk-white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lift);
        }

        .page-button:active {
            transform: translateY(0) scale(0.96);
            transition-duration: var(--duration-swift);
        }

        .page-button.active {
            background: linear-gradient(135deg, var(--sapphire), #0052A3);
            color: var(--silk-white);
            border-color: var(--sapphire);
            box-shadow: 0 4px 12px rgba(0,102,204,0.3);
        }

        @media (max-width: 1024px) {
            .email-row { grid-template-columns: 32px 180px 1fr auto 90px; }
        }

        @media (max-width: 768px) {
            .email-row { grid-template-columns: 32px 1fr 90px; gap: 12px; }
            .email-from { display: none; }
            .email-label { display: none; }
        }

        .email-row {
            animation: fadeInUp var(--duration-elegant) var(--ease-luxe) backwards;
        }

        .email-row:nth-child(1) { animation-delay: 50ms; }
        .email-row:nth-child(2) { animation-delay: 100ms; }
        .email-row:nth-child(3) { animation-delay: 150ms; }
        .email-row:nth-child(4) { animation-delay: 200ms; }
        .email-row:nth-child(5) { animation-delay: 250ms; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-wrapper">
        <div class="toolbar">
            <div class="toolbar-title">Deleted Items</div>
            <div class="search-container">
                <span class="material-icons-round search-icon">search</span>
                <input type="text" class="search-input" placeholder="Search deleted items..." value="<?= htmlspecialchars($filters['search']) ?>" onkeydown="if(event.key === 'Enter') handleSearch(this.value)">
                <?php if (!empty($filters['search'])): ?>
                <button class="clear-search" onclick="clearSearch()"><span class="material-icons-round">close</span></button>
                <?php endif; ?>
            </div>
            <div class="toolbar-actions">
                <button class="icon-btn" onclick="toggleFilters()" title="Filters"><span class="material-icons-round">tune</span></button>
                <button class="icon-btn" onclick="location.reload()" title="Refresh"><span class="material-icons-round">refresh</span></button>
            </div>
        </div>
        <div class="selection-bar" id="selectionBar">
            <span class="selection-count"><span id="selectedCount">0</span> selected</span>
            <div class="selection-divider"></div>
            <div class="selection-actions">
                <button class="action-btn restore" onclick="bulkRestore()"><span class="material-icons-round">restore</span>Restore</button>
                <button class="action-btn delete" onclick="bulkDeleteForever()"><span class="material-icons-round">delete</span>Delete</button>
                <button class="action-btn clear" onclick="clearSelection()"><span class="material-icons-round">close</span>Clear</button>
            </div>
        </div>
        <div class="filter-panel" id="filterPanel">
            <div class="filter-header">
                <div class="filter-title">Filters</div>
                <button class="icon-btn" onclick="toggleFilters()"><span class="material-icons-round">close</span></button>
            </div>
            <form method="GET" action="">
                <div class="filter-body">
                    <div class="filter-group">
                        <label class="filter-label">Recipient Email</label>
                        <input type="text" name="recipient" class="filter-input" placeholder="Filter by recipient..." value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Subject Contains</label>
                        <input type="text" name="subject" class="filter-input" placeholder="Filter by subject..." value="<?= htmlspecialchars($filters['subject']) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Label</label>
                        <select name="label_id" class="filter-select">
                            <option value="">All labels</option>
                            <option value="unlabeled" <?= $filters['label_id'] === 'unlabeled' ? 'selected' : '' ?>>Unlabeled</option>
                            <?php foreach ($labels as $label): ?>
                            <option value="<?= $label['id'] ?>" <?= $filters['label_id'] == $label['id'] ? 'selected' : '' ?>><?= htmlspecialchars($label['label_name']) ?> (<?= $label['count'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="filter-btn primary">Apply Filters</button>
                    <button type="button" class="filter-btn secondary" onclick="clearForm()">Clear</button>
                </div>
            </form>
        </div>
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters">
            <?php if (!empty($filters['search'])): ?><div class="filter-chip">Search: <?= htmlspecialchars($filters['search']) ?><span class="material-icons-round" onclick="removeFilter('search')">close</span></div><?php endif; ?>
            <?php if (!empty($filters['recipient'])): ?><div class="filter-chip">To: <?= htmlspecialchars($filters['recipient']) ?><span class="material-icons-round" onclick="removeFilter('recipient')">close</span></div><?php endif; ?>
            <?php if (!empty($filters['subject'])): ?><div class="filter-chip">Subject: <?= htmlspecialchars($filters['subject']) ?><span class="material-icons-round" onclick="removeFilter('subject')">close</span></div><?php endif; ?>
            <?php if (!empty($filters['label_id'])): ?><div class="filter-chip"><?php if ($filters['label_id'] === 'unlabeled') { echo 'Unlabeled'; } else { foreach ($labels as $label) { if ($label['id'] == $filters['label_id']) { echo htmlspecialchars($label['label_name']); break; } } } ?><span class="material-icons-round" onclick="removeFilter('label_id')">close</span></div><?php endif; ?>
            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?><div class="filter-chip"><?= htmlspecialchars($filters['date_from'] ?: '...') ?> – <?= htmlspecialchars($filters['date_to'] ?: '...') ?><span class="material-icons-round" onclick="clearDateFilters()">close</span></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="content-area">
            <?php if (empty($sentEmails)): ?>
            <div class="empty-state">
                <div class="empty-icon"><span class="material-icons-round">delete_outline</span></div>
                <h3>No Deleted Items</h3>
                <p>Deleted emails appear here for 30 days before being permanently removed. They can be restored at any time during this period.</p>
            </div>
            <?php else: ?>
            <div class="email-list">
                <?php foreach ($sentEmails as $email): ?>
                <div class="email-row">
                    <div class="email-checkbox-col"><input type="checkbox" class="email-checkbox" value="<?= $email['id'] ?>" onchange="handleCheckboxChange()"></div>
                    <div class="email-from" onclick="openEmail(<?= $email['id'] ?>)"><?= htmlspecialchars($email['recipient_email']) ?></div>
                    <div class="email-content" onclick="openEmail(<?= $email['id'] ?>)">
                        <div class="email-subject"><?= htmlspecialchars($email['subject']) ?></div>
                        <div class="email-preview"><?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 120)) ?></div>
                    </div>
                    <div class="email-label"><?php if (!empty($email['label_color'])): ?><div class="label-dot" style="background: <?= htmlspecialchars($email['label_color']) ?>;" title="<?= htmlspecialchars($email['label_name']) ?>"></div><?php endif; ?></div>
                    <div class="email-meta" onclick="openEmail(<?= $email['id'] ?>)"><?php if (!empty($email['attachment_names'])): ?><span class="material-icons-round">attach_file</span><?php endif; ?><?= date('M j', strtotime($email['sent_at'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php $currentParams = $_GET; if ($page > 1) { $currentParams['page'] = $page - 1; echo '<a href="?' . http_build_query($currentParams) . '" class="page-button">‹</a>'; } for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) { $currentParams['page'] = $i; echo '<a href="?' . http_build_query($currentParams) . '" class="page-button ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>'; } if ($page < $totalPages) { $currentParams['page'] = $page + 1; echo '<a href="?' . http_build_query($currentParams) . '" class="page-button">›</a>'; } ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
        function handleCheckboxChange() { const checked = document.querySelectorAll('.email-checkbox:checked'); const bar = document.getElementById('selectionBar'); const count = document.getElementById('selectedCount'); count.textContent = checked.length; if (checked.length > 0) { bar.classList.add('active'); document.querySelectorAll('.email-row').forEach(row => { const checkbox = row.querySelector('.email-checkbox'); row.classList.toggle('selected', checkbox.checked); }); } else { bar.classList.remove('active'); document.querySelectorAll('.email-row').forEach(row => { row.classList.remove('selected'); }); } }
        function clearSelection() { document.querySelectorAll('.email-checkbox').forEach(cb => cb.checked = false); handleCheckboxChange(); }
        async function bulkRestore() { const ids = Array.from(document.querySelectorAll('.email-checkbox:checked')).map(cb => cb.value); if (ids.length === 0) return; if (!confirm(`Restore ${ids.length} ${ids.length === 1 ? 'item' : 'items'}?`)) return; try { const response = await fetch('bulk_trash_actions.php', { method: 'POST', body: new URLSearchParams({ action: 'bulk_restore', email_ids: JSON.stringify(ids) }) }); const result = await response.json(); if (result.success) { setTimeout(() => location.reload(), 800); } else { alert(result.message || 'Failed'); } } catch (error) { alert('Error occurred'); } }
        async function bulkDeleteForever() { const ids = Array.from(document.querySelectorAll('.email-checkbox:checked')).map(cb => cb.value); if (ids.length === 0) return; if (!confirm(`Permanently delete ${ids.length} ${ids.length === 1 ? 'item' : 'items'}? This cannot be undone.`)) return; try { const response = await fetch('bulk_trash_actions.php', { method: 'POST', body: new URLSearchParams({ action: 'bulk_delete_forever', email_ids: JSON.stringify(ids) }) }); const result = await response.json(); if (result.success) { setTimeout(() => location.reload(), 800); } else { alert(result.message || 'Failed'); } } catch (error) { alert('Error occurred'); } }
        function openEmail(id) { window.open('view_sent_email.php?id=' + id, '_blank'); }
        function toggleFilters() { document.getElementById('filterPanel').classList.toggle('active'); }
        function handleSearch(value) { const url = new URL(window.location.href); value ? url.searchParams.set('search', value) : url.searchParams.delete('search'); url.searchParams.delete('page'); window.location.href = url.toString(); }
        function clearSearch() { const input = document.querySelector('.search-input'); input.value = ''; handleSearch(''); }
        function clearForm() { document.querySelector('#filterPanel form').reset(); }
        function removeFilter(name) { const url = new URL(window.location.href); url.searchParams.delete(name); url.searchParams.delete('page'); window.location.href = url.toString(); }
        function clearDateFilters() { const url = new URL(window.location.href); url.searchParams.delete('date_from'); url.searchParams.delete('date_to'); url.searchParams.delete('page'); window.location.href = url.toString(); }
        document.addEventListener('keydown', (e) => { if ((e.metaKey || e.ctrlKey) && e.key === 'f') { e.preventDefault(); document.querySelector('.search-input').focus(); } if (e.key === 'Escape') { const filterPanel = document.getElementById('filterPanel'); if (filterPanel.classList.contains('active')) { toggleFilters(); } } });
        document.querySelector('.content-area').style.scrollBehavior = 'smooth';
    </script>
</body>
</html>