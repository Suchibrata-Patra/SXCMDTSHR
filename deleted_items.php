<?php
// deleted_items.php - Ultra-Premium Dashboard-Style Trash Interface
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
    <title>Trash Management • SXC MDTS</title>

    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            /* Modern Dashboard Palette */
            --primary-50: #f0f9ff;
            --primary-100: #e0f2fe;
            --primary-200: #bae6fd;
            --primary-300: #7dd3fc;
            --primary-400: #38bdf8;
            --primary-500: #0ea5e9;
            --primary-600: #0284c7;
            --primary-700: #0369a1;
            --primary-800: #075985;
            --primary-900: #0c4a6e;

            /* Sophisticated Grays */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-150: #eff1f3;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;

            /* Action Colors */
            --success-50: #f0fdf4;
            --success-500: #22c55e;
            --success-600: #16a34a;
            --success-700: #15803d;

            --danger-50: #fef2f2;
            --danger-500: #ef4444;
            --danger-600: #dc2626;
            --danger-700: #b91c1c;

            --warning-50: #fffbeb;
            --warning-500: #f59e0b;
            --warning-600: #d97706;

            --info-50: #eff6ff;
            --info-500: #3b82f6;
            --info-600: #2563eb;

            /* Semantic Colors */
            --background: #f8f9fa;
            --surface: #ffffff;
            --surface-hover: #f9fafb;
            --border: #e5e7eb;
            --border-light: #f0f1f3;
            --divider: #eef0f2;

            /* Text Hierarchy */
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-tertiary: #9ca3af;
            --text-disabled: #d1d5db;

            /* Sophisticated Shadows */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);

            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-smooth: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ========== MAIN LAYOUT ========== */
        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            background: var(--background);
        }

        /* ========== PREMIUM HEADER ========== */
        .page-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: var(--shadow-xs);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--danger-50) 0%, var(--danger-100) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--danger-600);
            box-shadow: var(--shadow-sm);
        }

        .header-icon .material-icons-round {
            font-size: 24px;
        }

        .header-content h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .header-content p {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: var(--gray-100);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-left: 8px;
        }

        .stats-badge .material-icons-round {
            font-size: 16px;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-box {
            position: relative;
            width: 320px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 16px 10px 42px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            background: var(--surface);
            transition: all var(--transition-base);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-400);
            box-shadow: 0 0 0 4px var(--primary-50);
        }

        .search-box input::placeholder {
            color: var(--text-tertiary);
        }

        .search-box .material-icons-round {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: var(--text-tertiary);
        }

        .btn-secondary,
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            border: none;
            white-space: nowrap;
        }

        .btn-secondary {
            background: var(--surface);
            border: 1.5px solid var(--border);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--surface-hover);
            border-color: var(--gray-300);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary.active {
            background: var(--primary-50);
            border-color: var(--primary-300);
            color: var(--primary-700);
        }

        .btn-primary {
            background: var(--primary-600);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--primary-700);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            background: var(--primary-600);
            color: white;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }

        /* ========== BULK ACTION BAR ========== */
        .bulk-action-bar {
            background: linear-gradient(135deg, var(--gray-900) 0%, var(--gray-800) 100%);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            transform: translateY(-100%);
            opacity: 0;
            transition: all var(--transition-smooth);
            position: relative;
            z-index: 19;
            box-shadow: var(--shadow-lg);
        }

        .bulk-action-bar.active {
            transform: translateY(0);
            opacity: 1;
        }

        .bulk-action-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-500), var(--success-500), var(--primary-500));
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .bulk-info {
            display: flex;
            align-items: center;
            gap: 16px;
            color: white;
        }

        .selected-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
        }

        .bulk-text {
            font-size: 15px;
            font-weight: 600;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
        }

        .bulk-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            border: none;
        }

        .bulk-btn-restore {
            background: var(--success-500);
            color: white;
        }

        .bulk-btn-restore:hover {
            background: var(--success-600);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
            transform: translateY(-2px);
        }

        .bulk-btn-delete {
            background: var(--danger-500);
            color: white;
        }

        .bulk-btn-delete:hover {
            background: var(--danger-600);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
            transform: translateY(-2px);
        }

        .bulk-btn-clear {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .bulk-btn-clear:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all var(--transition-smooth);
            position: relative;
            z-index: 18;
        }

        .filter-panel.active {
            max-height: 600px;
            padding: 24px 32px;
        }

        .filter-section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .filter-input,
        .filter-select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            background: var(--surface);
            transition: all var(--transition-base);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-400);
            box-shadow: 0 0 0 4px var(--primary-50);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 16px;
            border-top: 1px solid var(--border-light);
        }

        .btn-filter-clear {
            padding: 10px 20px;
            background: var(--gray-100);
            border: none;
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .btn-filter-clear:hover {
            background: var(--gray-200);
        }

        .btn-filter-apply {
            padding: 10px 24px;
            background: var(--primary-600);
            border: none;
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .btn-filter-apply:hover {
            background: var(--primary-700);
            box-shadow: var(--shadow-md);
        }

        /* Active Filters Display */
        .active-filters-bar {
            padding: 16px 32px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .active-filters-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-secondary);
        }

        .active-filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            box-shadow: var(--shadow-xs);
        }

        .active-filter-tag .material-icons-round {
            font-size: 16px;
            color: var(--text-tertiary);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .active-filter-tag .material-icons-round:hover {
            color: var(--danger-500);
        }

        .btn-clear-all-filters {
            padding: 6px 14px;
            background: var(--gray-900);
            border: none;
            border-radius: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .btn-clear-all-filters:hover {
            background: var(--gray-800);
            box-shadow: var(--shadow-sm);
        }

        /* ========== CONTENT AREA ========== */
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 24px 32px;
        }

        /* ========== EMAIL CARDS ========== */
        .emails-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .email-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: grid;
            grid-template-columns: 40px 240px 1fr auto 140px;
            gap: 20px;
            align-items: center;
            transition: all var(--transition-base);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .email-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(180deg, var(--primary-500), var(--primary-600));
            transition: width var(--transition-base);
        }

        .email-card:hover {
            border-color: var(--primary-200);
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }

        .email-card:hover::before {
            width: 4px;
        }

        .email-card.selected {
            background: var(--primary-50);
            border-color: var(--primary-300);
            box-shadow: 0 0 0 2px var(--primary-100);
        }

        .email-card.selected::before {
            width: 4px;
        }

        /* Email Card Sections */
        .email-checkbox-col {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .email-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-600);
        }

        .email-recipient {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .email-content {
            min-width: 0;
        }

        .email-subject {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .email-preview {
            font-size: 13px;
            color: var(--text-tertiary);
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .email-label-col {
            display: flex;
            gap: 8px;
        }

        .label-pill {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            box-shadow: var(--shadow-xs);
        }

        .email-date-col {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .attachment-indicator {
            color: var(--text-tertiary);
            font-size: 16px;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 100px 40px;
        }

        .empty-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .empty-icon::before {
            content: '';
            position: absolute;
            inset: 8px;
            background: var(--surface);
            border-radius: 50%;
        }

        .empty-icon .material-icons-round {
            font-size: 56px;
            color: var(--text-tertiary);
            position: relative;
            z-index: 1;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 24px 32px;
            background: var(--surface);
            border-top: 1px solid var(--border);
        }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all var(--transition-base);
        }

        .page-btn:hover {
            border-color: var(--primary-300);
            background: var(--primary-50);
            color: var(--primary-700);
        }

        .page-btn.active {
            background: var(--primary-600);
            border-color: var(--primary-600);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        /* ========== SCROLLBAR ========== */
        .content-area::-webkit-scrollbar {
            width: 8px;
        }

        .content-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .content-area::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 10px;
        }

        .content-area::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }

        /* ========== ANIMATIONS ========== */
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

        .email-card {
            animation: fadeInUp 0.3s var(--transition-base) backwards;
        }

        <?php for ($i = 0; $i < min(20, count($sentEmails)); $i++): ?>
        .email-card:nth-child(<?= $i + 1 ?>) {
            animation-delay: <?= $i * 0.02 ?>s;
        }
        <?php endfor; ?>

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .email-card {
                grid-template-columns: 40px 1fr 100px;
            }

            .email-recipient,
            .email-label-col {
                display: none;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <!-- Premium Header -->
        <div class="page-header">
            <div class="header-left">
                <div class="header-icon">
                    <span class="material-icons-round">delete</span>
                </div>
                <div class="header-content">
                    <h1>Trash Management</h1>
                    <p>Deleted items are permanently removed after 30 days
                        <span class="stats-badge">
                            <span class="material-icons-round">folder</span>
                            <?= $totalEmails ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="header-actions">
                <div class="search-box">
                    <span class="material-icons-round">search</span>
                    <input type="text" placeholder="Search in trash..." 
                           value="<?= htmlspecialchars($filters['search']) ?>"
                           onchange="handleSearch(this.value)">
                </div>
                <button class="btn-secondary <?= $hasActiveFilters ? 'active' : '' ?>" onclick="toggleFilters()">
                    <span class="material-icons-round" style="font-size: 20px;">tune</span>
                    Filters
                    <?php if ($hasActiveFilters): ?>
                    <span class="filter-badge"><?= count(array_filter($filters)) ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Bulk Action Bar -->
        <div class="bulk-action-bar" id="bulkActionBar">
            <div class="bulk-info">
                <div class="selected-count" id="selectedCount">0</div>
                <div class="bulk-text">items selected</div>
            </div>
            <div class="bulk-actions">
                <button class="bulk-btn bulk-btn-restore" onclick="bulkRestore()">
                    <span class="material-icons-round" style="font-size: 18px;">restore</span>
                    Restore
                </button>
                <button class="bulk-btn bulk-btn-delete" onclick="bulkDeleteForever()">
                    <span class="material-icons-round" style="font-size: 18px;">delete_forever</span>
                    Delete Forever
                </button>
                <button class="bulk-btn bulk-btn-clear" onclick="clearSelection()">
                    Clear Selection
                </button>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel <?= $hasActiveFilters ? 'active' : '' ?>" id="filterPanel">
            <div class="filter-section-title">Advanced Filters</div>
            <form method="GET" action="deleted_items.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Recipient Email</label>
                        <input type="text" name="recipient" class="filter-input" 
                               placeholder="e.g., user@example.com"
                               value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Subject</label>
                        <input type="text" name="subject" class="filter-input" 
                               placeholder="Search by subject"
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
                    <button type="button" class="btn-filter-clear" onclick="clearForm()">Clear Filters</button>
                    <button type="submit" class="btn-filter-apply">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Active Filters Bar -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters-bar">
            <span class="active-filters-label">Active Filters</span>
            <?php if (!empty($filters['search'])): ?>
            <div class="active-filter-tag">
                Search: <?= htmlspecialchars($filters['search']) ?>
                <span class="material-icons-round" onclick="removeFilter('search')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['recipient'])): ?>
            <div class="active-filter-tag">
                Recipient: <?= htmlspecialchars($filters['recipient']) ?>
                <span class="material-icons-round" onclick="removeFilter('recipient')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['subject'])): ?>
            <div class="active-filter-tag">
                Subject: <?= htmlspecialchars($filters['subject']) ?>
                <span class="material-icons-round" onclick="removeFilter('subject')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['label_id'])): ?>
            <div class="active-filter-tag">
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
                <span class="material-icons-round" onclick="removeFilter('label_id')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
            <div class="active-filter-tag">
                Date: <?= htmlspecialchars($filters['date_from'] ?: 'Any') ?> – <?= htmlspecialchars($filters['date_to'] ?: 'Any') ?>
                <span class="material-icons-round" onclick="clearDateFilters()">close</span>
            </div>
            <?php endif; ?>
            <button class="btn-clear-all-filters" onclick="clearAllFilters()">
                Clear All
            </button>
        </div>
        <?php endif; ?>

        <!-- Content Area with Email Cards -->
        <div class="content-area">
            <?php if (empty($sentEmails)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <span class="material-icons-round">delete_sweep</span>
                </div>
                <h3>No items in trash</h3>
                <p>When you delete emails, they'll appear here temporarily before being permanently removed.</p>
            </div>
            <?php else: ?>
            <div class="emails-grid">
                <?php foreach ($sentEmails as $email): ?>
                <div class="email-card">
                    <div class="email-checkbox-col">
                        <input type="checkbox" class="email-checkbox" value="<?= $email['id'] ?>" 
                               onchange="handleCheckboxChange()">
                    </div>

                    <div class="email-recipient" onclick="openEmail(<?= $email['id'] ?>)">
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </div>

                    <div class="email-content" onclick="openEmail(<?= $email['id'] ?>)">
                        <div class="email-subject"><?= htmlspecialchars($email['subject']) ?></div>
                        <div class="email-preview">
                            <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 150)) ?>...
                        </div>
                    </div>

                    <div class="email-label-col">
                        <?php if (!empty($email['label_name'])): ?>
                        <span class="label-pill" style="background: <?= htmlspecialchars($email['label_color']) ?>;">
                            <?= htmlspecialchars($email['label_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="email-date-col" onclick="openEmail(<?= $email['id'] ?>)">
                        <?php if (!empty($email['attachment_names'])): ?>
                        <span class="material-icons-round attachment-indicator">attach_file</span>
                        <?php endif; ?>
                        <?= date('M j, Y', strtotime($email['sent_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $currentParams = $_GET;
            
            if ($page > 3) {
                $currentParams['page'] = 1;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-btn">1</a>';
                if ($page > 4) {
                    echo '<span class="page-btn" style="border: none; cursor: default;">···</span>';
                }
            }
            
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                $currentParams['page'] = $i;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-btn ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
            }
            
            if ($page < $totalPages - 2) {
                if ($page < $totalPages - 3) {
                    echo '<span class="page-btn" style="border: none; cursor: default;">···</span>';
                }
                $currentParams['page'] = $totalPages;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-btn">' . $totalPages . '</a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function handleCheckboxChange() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const bar = document.getElementById('bulkActionBar');
            const count = document.getElementById('selectedCount');

            count.textContent = checkedBoxes.length;

            if (checkedBoxes.length > 0) {
                bar.classList.add('active');
                document.querySelectorAll('.email-card').forEach(card => {
                    const checkbox = card.querySelector('.email-checkbox');
                    card.classList.toggle('selected', checkbox.checked);
                });
            } else {
                bar.classList.remove('active');
                document.querySelectorAll('.email-card').forEach(card => {
                    card.classList.remove('selected');
                });
            }
        }

        function clearSelection() {
            document.querySelectorAll('.email-checkbox').forEach(cb => cb.checked = false);
            handleCheckboxChange();
        }

        async function bulkRestore() {
            const emailIds = Array.from(document.querySelectorAll('.email-checkbox:checked')).map(cb => cb.value);
            if (emailIds.length === 0) {
                showNotification('Please select at least one email', 'error');
                return;
            }

            if (!confirm(`Restore ${emailIds.length} email(s) to inbox?`)) return;

            try {
                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'bulk_restore',
                        email_ids: JSON.stringify(emailIds)
                    })
                });

                const result = await response.json();
                if (result.success) {
                    showNotification(`Restored ${emailIds.length} email(s)`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.message || 'Failed to restore', 'error');
                }
            } catch (error) {
                showNotification('An error occurred', 'error');
            }
        }

        async function bulkDeleteForever() {
            const emailIds = Array.from(document.querySelectorAll('.email-checkbox:checked')).map(cb => cb.value);
            if (emailIds.length === 0) {
                showNotification('Please select at least one email', 'error');
                return;
            }

            if (!confirm(`⚠️ PERMANENT DELETE\n\nDelete ${emailIds.length} email(s) forever? This cannot be undone.`)) return;
            if (!confirm('Are you absolutely sure? This action is irreversible.')) return;

            try {
                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'bulk_delete_forever',
                        email_ids: JSON.stringify(emailIds)
                    })
                });

                const result = await response.json();
                if (result.success) {
                    showNotification(`Permanently deleted ${emailIds.length} email(s)`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.message || 'Failed to delete', 'error');
                }
            } catch (error) {
                showNotification('An error occurred', 'error');
            }
        }

        function openEmail(id) {
            window.open('view_sent_email.php?id=' + id, '_blank');
        }

        function toggleFilters() {
            document.getElementById('filterPanel').classList.toggle('active');
            document.querySelector('.btn-secondary').classList.toggle('active');
        }

        function handleSearch(value) {
            const url = new URL(window.location.href);
            value ? url.searchParams.set('search', value) : url.searchParams.delete('search');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearForm() {
            document.querySelector('#filterPanel form').reset();
        }

        function removeFilter(name) {
            const url = new URL(window.location.href);
            url.searchParams.delete(name);
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
            const notif = document.createElement('div');
            const colors = {
                success: 'var(--success-500)',
                error: 'var(--danger-500)',
                info: 'var(--info-500)'
            };
            
            notif.style.cssText = `
                position: fixed;
                top: 24px;
                right: 24px;
                padding: 16px 24px;
                background: ${colors[type] || colors.info};
                color: white;
                border-radius: 10px;
                font-family: 'Plus Jakarta Sans', sans-serif;
                font-size: 14px;
                font-weight: 600;
                box-shadow: var(--shadow-xl);
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
            `;
            notif.textContent = message;
            document.body.appendChild(notif);

            setTimeout(() => {
                notif.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-box input').focus();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                toggleFilters();
            }
        });

        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(400px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>