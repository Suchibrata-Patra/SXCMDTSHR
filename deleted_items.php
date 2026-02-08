<?php
// deleted_items.php - Ultra-Premium Trash Archive
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
    <title>Trash · SXC MDTS</title>

    <!-- Premium Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=SF+Pro+Display:wght@300;400;500;600;700&family=Crimson+Pro:wght@200;300;400;500;600&family=Newsreader:ital,wght@0,300;0,400;0,500;1,300;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <style>
        :root {
            /* Apple-inspired Neutral Palette */
            --neutral-50: #fafaf9;
            --neutral-100: #f5f5f4;
            --neutral-200: #e7e5e4;
            --neutral-300: #d6d3d1;
            --neutral-400: #a8a29e;
            --neutral-500: #78716c;
            --neutral-600: #57534e;
            --neutral-700: #44403c;
            --neutral-800: #292524;
            --neutral-900: #1c1917;

            /* Nature-inspired Organic Colors */
            --sage-50: #f6f7f6;
            --sage-100: #e8ebe8;
            --sage-200: #d1d8d1;
            --sage-300: #a8b5a8;
            --sage-400: #7d927d;
            --sage-500: #5a6f5a;
            --sage-600: #4a5d4a;
            --sage-700: #3d4c3d;
            
            /* Uber-inspired Sophisticated Accents */
            --charcoal: #000000;
            --charcoal-soft: #0a0a0a;
            --charcoal-light: #1a1a1a;
            
            /* Premium Action Colors */
            --restore-primary: #34c759;
            --restore-hover: #2fb350;
            --delete-primary: #ff3b30;
            --delete-hover: #e63329;
            
            /* Semantic Colors */
            --background: #ffffff;
            --surface: #fafaf9;
            --surface-elevated: #ffffff;
            --border-subtle: rgba(0, 0, 0, 0.06);
            --border: rgba(0, 0, 0, 0.1);
            --border-strong: rgba(0, 0, 0, 0.18);
            
            /* Text Hierarchy */
            --text-primary: #1c1917;
            --text-secondary: #57534e;
            --text-tertiary: #a8a29e;
            --text-quaternary: #d6d3d1;
            
            /* Glass Effects */
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.18);
            
            /* Shadows - Apple-style refined */
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.16);
            
            /* Transitions */
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif;
            background: var(--surface);
            color: var(--text-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            letter-spacing: -0.011em;
        }

        /* Main Wrapper */
        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* Organic Background Pattern */
        #main-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 400px;
            background: 
                radial-gradient(circle at 20% 50%, rgba(138, 180, 138, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(106, 142, 106, 0.02) 0%, transparent 50%),
                linear-gradient(180deg, var(--sage-50) 0%, transparent 100%);
            pointer-events: none;
            z-index: 0;
        }

        /* ========== PREMIUM HEADER ========== */
        .page-header {
            background: var(--surface-elevated);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid var(--border-subtle);
            padding: 32px 48px 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }

        .header-left {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .page-title {
            font-family: 'Crimson Pro', Georgia, serif;
            font-size: 48px;
            font-weight: 300;
            color: var(--text-primary);
            letter-spacing: -0.03em;
            line-height: 1.1;
            display: flex;
            align-items: center;
            gap: 16px;
            animation: fadeInUp 0.6s var(--ease-out);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .title-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(255, 59, 48, 0.08) 0%, rgba(255, 59, 48, 0.04) 100%);
            border-radius: 12px;
            color: var(--delete-primary);
            animation: iconFloat 4s var(--ease-in-out) infinite;
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-6px) rotate(-2deg); }
        }

        .title-icon .material-icons-round {
            font-size: 24px;
        }

        .page-subtitle {
            font-family: 'Newsreader', Georgia, serif;
            font-size: 16px;
            font-weight: 400;
            color: var(--text-secondary);
            letter-spacing: -0.01em;
            padding-left: 58px;
            line-height: 1.5;
            animation: fadeInUp 0.6s var(--ease-out) 0.1s both;
        }

        .email-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 12px;
            padding: 4px 12px;
            background: rgba(0, 0, 0, 0.04);
            border-radius: 8px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 13px;
            font-weight: 590;
            color: var(--text-secondary);
            letter-spacing: -0.01em;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            animation: fadeInUp 0.6s var(--ease-out) 0.2s both;
        }

        .search-container {
            position: relative;
        }

        .search-input {
            width: 380px;
            padding: 12px 20px 12px 48px;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 400;
            letter-spacing: -0.01em;
            color: var(--text-primary);
            background: var(--surface-elevated);
            transition: all 0.3s var(--ease-out);
            box-shadow: var(--shadow-xs);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--sage-400);
            box-shadow: 0 0 0 4px rgba(90, 111, 90, 0.08), var(--shadow-sm);
            transform: translateY(-1px);
        }

        .search-input::placeholder {
            color: var(--text-tertiary);
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            pointer-events: none;
            font-size: 20px;
        }

        .btn-filter-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--surface-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.01em;
            cursor: pointer;
            transition: all 0.3s var(--ease-out);
            box-shadow: var(--shadow-xs);
        }

        .btn-filter-toggle:hover {
            background: var(--neutral-50);
            border-color: var(--border);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-filter-toggle.active {
            background: var(--sage-100);
            border-color: var(--sage-300);
            color: var(--sage-700);
        }

        .filter-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            background: var(--sage-600);
            color: white;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        /* ========== UBER-INSPIRED BULK TOOLBAR ========== */
        .bulk-action-toolbar {
            background: var(--charcoal);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 16px 48px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.5s var(--ease-out);
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 9;
        }

        .bulk-action-toolbar.active {
            transform: translateY(0);
            opacity: 1;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.9);
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .selection-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 12px;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            color: white;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
        }

        .btn-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border: none;
            border-radius: 10px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.01em;
            cursor: pointer;
            transition: all 0.3s var(--ease-out);
        }

        .btn-toolbar-restore {
            background: var(--restore-primary);
            color: white;
        }

        .btn-toolbar-restore:hover {
            background: var(--restore-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(52, 199, 89, 0.3);
        }

        .btn-toolbar-restore:active {
            transform: translateY(0);
        }

        .btn-toolbar-delete {
            background: var(--delete-primary);
            color: white;
        }

        .btn-toolbar-delete:hover {
            background: var(--delete-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 59, 48, 0.3);
        }

        .btn-toolbar-delete:active {
            transform: translateY(0);
        }

        .btn-toolbar-clear {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .btn-toolbar-clear:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            background: var(--surface-elevated);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.5s var(--ease-out);
            position: relative;
            z-index: 8;
        }

        .filter-panel.active {
            max-height: 500px;
            padding: 32px 48px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .filter-group label {
            display: block;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .filter-input,
        .filter-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 400;
            letter-spacing: -0.01em;
            color: var(--text-primary);
            background: var(--background);
            transition: all 0.3s var(--ease-out);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--sage-400);
            box-shadow: 0 0 0 4px rgba(90, 111, 90, 0.08);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-apply,
        .btn-clear {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.01em;
            cursor: pointer;
            transition: all 0.3s var(--ease-out);
        }

        .btn-apply {
            background: var(--charcoal);
            color: white;
        }

        .btn-apply:hover {
            background: var(--charcoal-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-clear {
            background: var(--neutral-100);
            color: var(--text-primary);
        }

        .btn-clear:hover {
            background: var(--neutral-200);
        }

        /* Active Filters */
        .active-filters {
            padding: 20px 48px;
            background: var(--sage-50);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 7;
        }

        .active-filters-label {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: white;
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            box-shadow: var(--shadow-xs);
        }

        .filter-badge-close {
            cursor: pointer;
            color: var(--text-tertiary);
            transition: all 0.2s var(--ease-out);
            display: flex;
            align-items: center;
        }

        .filter-badge-close:hover {
            color: var(--delete-primary);
            transform: scale(1.1);
        }

        .btn-clear-all {
            padding: 8px 18px;
            background: var(--charcoal);
            color: white;
            border: none;
            border-radius: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: -0.01em;
            cursor: pointer;
            transition: all 0.3s var(--ease-out);
            box-shadow: var(--shadow-xs);
        }

        .btn-clear-all:hover {
            background: var(--charcoal-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        /* ========== NATURE-INSPIRED EMAIL LIST ========== */
        .email-list-container {
            flex: 1;
            overflow-y: auto;
            background: var(--surface);
            position: relative;
            z-index: 1;
        }

        .email-list {
            padding: 16px 0;
        }

        .email-item {
            display: grid;
            grid-template-columns: 56px 280px 1fr auto 140px;
            gap: 24px;
            align-items: center;
            padding: 20px 48px;
            background: var(--surface-elevated);
            border-bottom: 1px solid var(--border-subtle);
            margin: 0 24px 8px;
            border-radius: 16px;
            transition: all 0.4s var(--ease-out);
            cursor: pointer;
            position: relative;
            box-shadow: var(--shadow-xs);
        }

        .email-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(180deg, var(--sage-500) 0%, var(--sage-600) 100%);
            border-radius: 16px 0 0 16px;
            transition: width 0.4s var(--ease-out);
        }

        .email-item:hover {
            transform: translateX(8px);
            box-shadow: var(--shadow-md);
        }

        .email-item:hover::before {
            width: 4px;
        }

        .email-item.selected {
            background: linear-gradient(135deg, rgba(90, 111, 90, 0.04) 0%, rgba(90, 111, 90, 0.02) 100%);
            border-color: var(--sage-300);
            box-shadow: 0 0 0 2px rgba(90, 111, 90, 0.12), var(--shadow-sm);
        }

        .email-item.selected::before {
            width: 4px;
        }

        /* Checkbox Column */
        .col-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .email-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: var(--sage-600);
            border-radius: 6px;
        }

        /* Recipient Column */
        .col-recipient {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Subject & Preview Column */
        .col-subject {
            min-width: 0;
            line-height: 1.5;
        }

        .subject-text {
            font-family: 'Newsreader', Georgia, serif;
            font-weight: 500;
            font-size: 16px;
            color: var(--text-primary);
            margin-right: 10px;
            letter-spacing: -0.01em;
        }

        .snippet-text {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            color: var(--text-tertiary);
            font-size: 14px;
            font-weight: 400;
            letter-spacing: -0.01em;
        }

        /* Label Column */
        .col-label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .label-badge {
            padding: 6px 14px;
            border-radius: 12px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-xs);
        }

        /* Date Column */
        .col-date {
            text-align: right;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            letter-spacing: -0.01em;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .attachment-icon {
            color: var(--text-tertiary);
            font-size: 16px;
        }

        /* ========== ORGANIC EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 120px 40px;
            animation: fadeIn 0.8s var(--ease-out);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .empty-state-icon {
            width: 140px;
            height: 140px;
            margin: 0 auto 32px;
            background: linear-gradient(135deg, rgba(90, 111, 90, 0.06) 0%, rgba(90, 111, 90, 0.02) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: breathe 4s var(--ease-in-out) infinite;
        }

        @keyframes breathe {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(90, 111, 90, 0.15);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 30px rgba(90, 111, 90, 0);
            }
        }

        .empty-state-icon::before {
            content: '';
            position: absolute;
            inset: 12px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(90, 111, 90, 0.08) 0%, transparent 100%);
        }

        .empty-state-icon .material-icons-round {
            font-size: 64px;
            color: var(--sage-500);
            position: relative;
            z-index: 1;
        }

        .empty-state h3 {
            font-family: 'Crimson Pro', Georgia, serif;
            font-size: 32px;
            font-weight: 400;
            color: var(--text-primary);
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .empty-state p {
            font-family: 'Newsreader', Georgia, serif;
            font-size: 17px;
            font-weight: 300;
            color: var(--text-secondary);
            max-width: 460px;
            margin: 0 auto;
            line-height: 1.6;
            letter-spacing: -0.01em;
        }

        /* ========== REFINED PAGINATION ========== */
        .pagination {
            display: flex;
            gap: 6px;
            justify-content: center;
            padding: 28px 48px;
            background: var(--surface-elevated);
            border-top: 1px solid var(--border-subtle);
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.01em;
            transition: all 0.3s var(--ease-out);
            background: var(--background);
        }

        .page-link:hover {
            background: var(--neutral-50);
            border-color: var(--border);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .page-link.active {
            background: var(--charcoal);
            color: white;
            border-color: var(--charcoal);
            box-shadow: var(--shadow-sm);
        }

        /* ========== PREMIUM SCROLLBAR ========== */
        .email-list-container::-webkit-scrollbar {
            width: 12px;
        }

        .email-list-container::-webkit-scrollbar-track {
            background: transparent;
            margin: 16px 0;
        }

        .email-list-container::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.12);
            border-radius: 10px;
            border: 3px solid var(--surface);
        }

        .email-list-container::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 1400px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .page-header {
                padding: 24px 32px 20px;
            }

            .page-title {
                font-size: 38px;
            }

            .email-item {
                grid-template-columns: 56px 1fr 100px;
                gap: 16px;
                padding: 16px 32px;
                margin: 0 16px 6px;
            }

            .col-recipient,
            .col-label {
                display: none;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ========== STAGGERED ENTRANCE ANIMATIONS ========== */
        .email-item {
            animation: slideInRight 0.5s var(--ease-out) backwards;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        <?php for ($i = 0; $i < min(20, count($sentEmails)); $i++): ?>
        .email-item:nth-child(<?= $i + 1 ?>) {
            animation-delay: <?= $i * 0.03 ?>s;
        }
        <?php endfor; ?>
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <!-- Premium Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">
                    <div class="title-icon">
                        <span class="material-icons-round">delete</span>
                    </div>
                    Trash
                </h1>
                <p class="page-subtitle">
                    Items deleted 30 days ago are permanently removed
                    <span class="email-count-badge">
                        <?= $totalEmails ?> <?= $totalEmails === 1 ? 'item' : 'items' ?>
                    </span>
                </p>
            </div>

            <div class="header-actions">
                <div class="search-container">
                    <span class="material-icons-round search-icon">search</span>
                    <input type="text" class="search-input" placeholder="Search in trash..." 
                           value="<?= htmlspecialchars($filters['search']) ?>"
                           onchange="handleSearch(this.value)">
                </div>
                <button class="btn-filter-toggle <?= $hasActiveFilters ? 'active' : '' ?>" onclick="toggleFilters()">
                    <span class="material-icons-round" style="font-size: 20px;">tune</span>
                    Filters
                    <?php if ($hasActiveFilters): ?>
                    <span class="filter-count-badge"><?= count(array_filter($filters)) ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Uber-inspired Bulk Toolbar -->
        <div class="bulk-action-toolbar" id="bulkActionToolbar">
            <div class="toolbar-left">
                <div class="selection-info">
                    <span class="selection-count" id="selectedCount">0</span>
                    <span>selected</span>
                </div>
            </div>
            <div class="toolbar-actions">
                <button class="btn-toolbar btn-toolbar-restore" onclick="bulkRestore()">
                    <span class="material-icons-round" style="font-size: 20px;">restore</span>
                    Restore
                </button>
                <button class="btn-toolbar btn-toolbar-delete" onclick="bulkDeleteForever()">
                    <span class="material-icons-round" style="font-size: 20px;">delete_forever</span>
                    Delete Forever
                </button>
                <button class="btn-toolbar btn-toolbar-clear" onclick="clearSelection()">
                    Clear
                </button>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel <?= $hasActiveFilters ? 'active' : '' ?>" id="filterPanel">
            <form method="GET" action="deleted_items.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Recipient Email</label>
                        <input type="text" name="recipient" class="filter-input" 
                               placeholder="Filter by recipient"
                               value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Subject</label>
                        <input type="text" name="subject" class="filter-input" 
                               placeholder="Filter by subject"
                               value="<?= htmlspecialchars($filters['subject']) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Label</label>
                        <select name="label_id" class="filter-select">
                            <option value="">All Labels</option>
                            <option value="unlabeled" <?= $filters['label_id'] === 'unlabeled' ? 'selected' : '' ?>>
                                Unlabeled
                            </option>
                            <?php foreach ($labels as $label): ?>
                            <option value="<?= $label['id'] ?>" <?= $filters['label_id'] == $label['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label['label_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" class="filter-input" 
                               value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" class="filter-input" 
                               value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn-clear" onclick="clearForm()">Clear</button>
                    <button type="submit" class="btn-apply">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Active Filters -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters">
            <span class="active-filters-label">Active Filters</span>
            <?php if (!empty($filters['search'])): ?>
            <div class="filter-badge">
                Search: <?= htmlspecialchars($filters['search']) ?>
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('search')" style="font-size: 18px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['recipient'])): ?>
            <div class="filter-badge">
                Recipient: <?= htmlspecialchars($filters['recipient']) ?>
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('recipient')" style="font-size: 18px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['subject'])): ?>
            <div class="filter-badge">
                Subject: <?= htmlspecialchars($filters['subject']) ?>
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('subject')" style="font-size: 18px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['label_id'])): ?>
            <div class="filter-badge">
                Label: <?php 
                    if ($filters['label_id'] === 'unlabeled') {
                        echo 'Unlabeled';
                    } else {
                        foreach ($labels as $label) {
                            if ($label['id'] == $filters['label_id']) {
                                echo htmlspecialchars($label['label_name']);
                                break;
                            }
                        }
                    }
                ?>
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('label_id')" style="font-size: 18px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
            <div class="filter-badge">
                Date: <?= htmlspecialchars($filters['date_from'] ?: 'Any') ?> – <?= htmlspecialchars($filters['date_to'] ?: 'Any') ?>
                <span class="material-icons-round filter-badge-close" onclick="clearDateFilters()" style="font-size: 18px;">close</span>
            </div>
            <?php endif; ?>
            <button class="btn-clear-all" onclick="clearAllFilters()">
                Clear All
            </button>
        </div>
        <?php endif; ?>

        <!-- Nature-inspired Email List -->
        <div class="email-list-container">
            <?php if (empty($sentEmails)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons-round">delete_sweep</span>
                </div>
                <h3>No items in trash</h3>
                <p>When you delete emails, they'll appear here for 30 days before being permanently removed.</p>
            </div>
            <?php else: ?>
            <div class="email-list">
                <?php foreach ($sentEmails as $email): ?>
                <div class="email-item">
                    <div class="col-checkbox">
                        <input type="checkbox" class="email-checkbox" value="<?= $email['id'] ?>" 
                               onchange="handleCheckboxChange()">
                    </div>

                    <div class="col-recipient" onclick="openEmail(<?= $email['id'] ?>)">
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </div>

                    <div class="col-subject" onclick="openEmail(<?= $email['id'] ?>)">
                        <span class="subject-text"><?= htmlspecialchars($email['subject']) ?></span>
                        <span class="snippet-text">
                            — <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 120)) ?>...
                        </span>
                    </div>

                    <div class="col-label">
                        <?php if (!empty($email['label_name'])): ?>
                        <span class="label-badge" style="background: <?= htmlspecialchars($email['label_color']) ?>;">
                            <?= htmlspecialchars($email['label_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="col-date" onclick="openEmail(<?= $email['id'] ?>)">
                        <?php if (!empty($email['attachment_names'])): ?>
                        <span class="material-icons-round attachment-icon">attach_file</span>
                        <?php endif; ?>
                        <?= date('M j, Y', strtotime($email['sent_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Refined Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $currentParams = $_GET;
            
            if ($page > 3) {
                $currentParams['page'] = 1;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-link">1</a>';
                if ($page > 4) {
                    echo '<span class="page-link" style="border: none; background: none; cursor: default;">···</span>';
                }
            }
            
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                $currentParams['page'] = $i;
                $queryString = http_build_query($currentParams);
                echo '<a href="?' . $queryString . '" class="page-link ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
            }
            
            if ($page < $totalPages - 2) {
                if ($page < $totalPages - 3) {
                    echo '<span class="page-link" style="border: none; background: none; cursor: default;">···</span>';
                }
                $currentParams['page'] = $totalPages;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-link">' . $totalPages . '</a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Checkbox Selection Management
        function handleCheckboxChange() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const toolbar = document.getElementById('bulkActionToolbar');
            const selectedCount = document.getElementById('selectedCount');

            selectedCount.textContent = checkedBoxes.length;

            if (checkedBoxes.length > 0) {
                toolbar.classList.add('active');
                document.querySelectorAll('.email-item').forEach(item => {
                    const checkbox = item.querySelector('.email-checkbox');
                    if (checkbox.checked) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                });
            } else {
                toolbar.classList.remove('active');
                document.querySelectorAll('.email-item').forEach(item => {
                    item.classList.remove('selected');
                });
            }
        }

        function clearSelection() {
            document.querySelectorAll('.email-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            handleCheckboxChange();
        }

        // Bulk Restore - Change current_status from 0 to 1
        async function bulkRestore() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const emailIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (emailIds.length === 0) {
                showNotification('Please select at least one email to restore', 'error');
                return;
            }

            if (!confirm(`Restore ${emailIds.length} ${emailIds.length === 1 ? 'email' : 'emails'} to inbox?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'bulk_restore');
                formData.append('email_ids', JSON.stringify(emailIds));

                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(`Successfully restored ${emailIds.length} ${emailIds.length === 1 ? 'email' : 'emails'}`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Failed to restore emails: ' + (result.message || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while restoring emails', 'error');
            }
        }

        // Bulk Delete Forever - Change current_status from 0 to 2
        async function bulkDeleteForever() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const emailIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (emailIds.length === 0) {
                showNotification('Please select at least one email to delete permanently', 'error');
                return;
            }

            if (!confirm(`⚠️ PERMANENT DELETE\n\nDelete ${emailIds.length} ${emailIds.length === 1 ? 'email' : 'emails'} forever?\nThis action cannot be undone.`)) {
                return;
            }

            // Double confirmation for permanent deletion
            if (!confirm('Are you absolutely sure? This will permanently delete the selected emails and they cannot be recovered.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'bulk_delete_forever');
                formData.append('email_ids', JSON.stringify(emailIds));

                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(`Permanently deleted ${emailIds.length} ${emailIds.length === 1 ? 'email' : 'emails'}`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Failed to delete emails: ' + (result.message || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while deleting emails', 'error');
            }
        }

        function openEmail(emailId) {
            window.open('view_sent_email.php?id=' + emailId, '_blank');
        }

        function toggleFilters() {
            const panel = document.getElementById('filterPanel');
            const btn = document.querySelector('.btn-filter-toggle');
            panel.classList.toggle('active');
            btn.classList.toggle('active');
        }

        function handleSearch(value) {
            const url = new URL(window.location.href);
            if (value) {
                url.searchParams.set('search', value);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearForm() {
            const form = document.querySelector('#filterPanel form');
            form.reset();
        }

        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearDateFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('date_from');
            url.searchParams.delete('date_to');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearAllFilters() {
            window.location.href = 'deleted_items.php';
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'var(--restore-primary)' : 'var(--delete-primary)';
            
            notification.style.cssText = `
                position: fixed;
                top: 28px;
                right: 28px;
                padding: 18px 28px;
                background: ${bgColor};
                color: white;
                border-radius: 14px;
                box-shadow: var(--shadow-xl);
                z-index: 10000;
                font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
                font-size: 15px;
                font-weight: 600;
                letter-spacing: -0.01em;
                animation: slideInNotification 0.4s var(--ease-out);
                backdrop-filter: blur(10px);
            `;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOutNotification 0.4s var(--ease-out)';
                setTimeout(() => notification.remove(), 400);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Cmd/Ctrl + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }

            // Cmd/Ctrl + F to toggle filters
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                toggleFilters();
            }

            // R key for restore
            if (e.key === 'r' || e.key === 'R') {
                const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
                if (checkedBoxes.length > 0 && !e.target.matches('input[type="text"], input[type="date"], select')) {
                    e.preventDefault();
                    bulkRestore();
                }
            }

            // Delete key for permanent delete
            if (e.key === 'Delete' || (e.key === 'Backspace' && (e.metaKey || e.ctrlKey))) {
                const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
                if (checkedBoxes.length > 0 && !e.target.matches('input[type="text"], input[type="date"], select')) {
                    e.preventDefault();
                    bulkDeleteForever();
                }
            }
        });

        // Add notification animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInNotification {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOutNotification {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>