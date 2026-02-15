<?php
/**
 * Deleted Items Page - Modern Minimalist UI
 * Shows deleted inbox messages with preview
 * Redesigned with clean white aesthetic matching drive.php
 */
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'db_config.php';
require_once 'inbox_functions.php';

$userEmail = $_SESSION['smtp_user'];

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_deleted_messages':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $filters = ['search' => $_GET['search'] ?? ''];
            
            $messages = getDeletedMessages($userEmail, $limit, $offset, $filters);
            $total = getDeletedMessageCount($userEmail, $filters);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'total' => $total
            ]);
            exit();
            
        case 'get_message':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            try {
                $pdo = getDatabaseConnection();
                if (!$pdo) {
                    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
                    exit();
                }
                
                $stmt = $pdo->prepare("
                    SELECT * FROM inbox_messages 
                    WHERE id = :id AND user_email = :email AND is_deleted = 1
                ");
                
                $stmt->execute([
                    ':id' => $messageId,
                    ':email' => $userEmail
                ]);
                
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($message) {
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Message not found or not deleted']);
                }
            } catch (PDOException $e) {
                error_log("Error getting message: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit();
            
        case 'restore':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = restoreInboxMessage($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'permanent_delete':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = permanentDeleteMessage($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
    }
}

/**
 * Get deleted messages
 */
function getDeletedMessages($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT 
                    id, message_id, sender_email, sender_name, 
                    subject, body_preview, received_date, deleted_at,
                    has_attachments, attachment_data
                FROM inbox_messages 
                WHERE user_email = :email 
                AND is_deleted = 1";
        
        $params = [':email' => $userEmail];
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (subject LIKE :search OR sender_email LIKE :search OR body_preview LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY deleted_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting deleted messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get deleted message count
 */
function getDeletedMessageCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $sql = "SELECT COUNT(*) as count 
                FROM inbox_messages 
                WHERE user_email = :email 
                AND is_deleted = 1";
        
        $params = [':email' => $userEmail];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (subject LIKE :search OR sender_email LIKE :search OR body_preview LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting deleted count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Restore deleted message
 */
function restoreInboxMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_deleted = 0, deleted_at = NULL 
            WHERE id = :id AND user_email = :email AND is_deleted = 1
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error restoring message: " . $e->getMessage());
        return false;
    }
}

/**
 * Permanently delete message
 */
function permanentDeleteMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            DELETE FROM inbox_messages 
            WHERE id = :id AND user_email = :email AND is_deleted = 1
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error permanently deleting message: " . $e->getMessage());
        return false;
    }
}

// Initial data load
$messages = getDeletedMessages($userEmail, 50, 0) ?? [];
$totalCount = getDeletedMessageCount($userEmail) ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Items | SXC MDTS</title>
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #f9fafb 100%);
            min-height: 100vh;
            color: var(--gray-900);
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .page-title .material-icons-round {
            font-size: 28px;
            color: var(--danger);
        }

        .count-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: var(--danger);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Search Bar */
        .search-container {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            color: var(--gray-400);
            font-size: 20px;
            pointer-events: none;
        }

        #search-input {
            width: 100%;
            padding: 10px 16px 10px 44px;
            border: 1.5px solid var(--gray-200);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            background: white;
            color: var(--gray-900);
            transition: all 0.2s;
        }

        #search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        #search-input::placeholder {
            color: var(--gray-400);
        }

        /* Action Buttons */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border: 1.5px solid var(--gray-200);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        .btn .material-icons-round {
            font-size: 18px;
        }

        /* Main Content */
        .main-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 24px 32px;
        }

        .content-wrapper {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 24px;
            height: calc(100vh - 140px);
        }

        /* Messages List Panel */
        .messages-panel {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-100);
        }

        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-subtitle {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 4px;
        }

        .messages-list {
            flex: 1;
            overflow-y: auto;
        }

        /* Custom Scrollbar */
        .messages-list::-webkit-scrollbar,
        .message-content::-webkit-scrollbar {
            width: 6px;
        }

        .messages-list::-webkit-scrollbar-track,
        .message-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .messages-list::-webkit-scrollbar-thumb,
        .message-content::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 10px;
        }

        .messages-list::-webkit-scrollbar-thumb:hover,
        .message-content::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }

        /* Message Item */
        .message-item {
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .message-item:hover {
            background: var(--gray-50);
        }

        .message-item.active {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border-left: 3px solid var(--primary);
        }

        .message-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #fecaca 0%, #fee2e2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .message-icon .material-icons-round {
            font-size: 20px;
            color: var(--danger);
        }

        .message-info {
            flex: 1;
            min-width: 0;
        }

        .message-sender {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-subject {
            font-size: 13px;
            color: var(--gray-700);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-preview {
            font-size: 12px;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
        }

        .message-date {
            font-size: 11px;
            color: var(--gray-400);
        }

        .attachment-badge {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            font-size: 11px;
            color: var(--gray-500);
        }

        .attachment-badge .material-icons-round {
            font-size: 14px;
        }

        /* Preview Panel */
        .preview-panel {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .preview-header {
            padding: 20px 28px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preview-actions {
            display: flex;
            gap: 8px;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: none;
            background: white;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 1.5px solid var(--gray-200);
        }

        .icon-btn:hover {
            background: var(--gray-50);
            color: var(--gray-900);
        }

        .icon-btn.restore {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: var(--success);
            border-color: #86efac;
        }

        .icon-btn.restore:hover {
            background: linear-gradient(135deg, #a7f3d0 0%, #86efac 100%);
        }

        .icon-btn.delete {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: var(--danger);
            border-color: #fca5a5;
        }

        .icon-btn.delete:hover {
            background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);
        }

        .icon-btn .material-icons-round {
            font-size: 20px;
        }

        /* Message Detail */
        .message-detail {
            flex: 1;
            overflow-y: auto;
            padding: 28px;
        }

        .message-header {
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 2px solid var(--gray-100);
        }

        .message-subject-large {
            font-size: 22px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 16px;
            line-height: 1.4;
        }

        .message-from {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }

        .sender-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ddd6fe 0%, #c4b5fd 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
        }

        .sender-info {
            flex: 1;
        }

        .sender-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .sender-email {
            font-size: 13px;
            color: var(--gray-500);
        }

        .message-dates {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 16px;
        }

        .date-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--gray-600);
        }

        .date-row .material-icons-round {
            font-size: 16px;
            color: var(--gray-400);
        }

        .date-label {
            font-weight: 500;
            min-width: 80px;
        }

        /* Message Body */
        .message-body {
            font-size: 14px;
            line-height: 1.8;
            color: var(--gray-700);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* Attachments */
        .attachments-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid var(--gray-100);
        }

        .attachments-header {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }

        .attachment-item:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        .attachment-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .attachment-icon .material-icons-round {
            font-size: 22px;
            color: var(--primary);
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-900);
        }

        .attachment-size {
            font-size: 12px;
            color: var(--gray-500);
        }

        /* Empty States */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            text-align: center;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .empty-icon .material-icons-round {
            font-size: 40px;
            color: var(--gray-400);
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 14px;
            color: var(--gray-500);
        }

        /* Highlight */
        mark {
            background-color: #fef08a;
            color: var(--gray-900);
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 500;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
            min-width: 300px;
            animation: slideIn 0.3s ease;
            border: 1.5px solid var(--gray-200);
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .toast.success {
            border-color: var(--success);
        }

        .toast.error {
            border-color: var(--danger);
        }

        .toast .material-icons-round {
            font-size: 22px;
        }

        .toast.success .material-icons-round {
            color: var(--success);
        }

        .toast.error .material-icons-round {
            color: var(--danger);
        }

        .toast-message {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-900);
        }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }

        .modal-backdrop.show {
            display: flex;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 450px;
            animation: scaleIn 0.2s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--gray-100);
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            padding: 24px 28px;
        }

        .modal-text {
            font-size: 14px;
            color: var(--gray-600);
            line-height: 1.6;
        }

        .modal-footer {
            padding: 16px 28px;
            border-top: 1px solid var(--gray-100);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content-wrapper {
                grid-template-columns: 380px 1fr;
            }
        }

        @media (max-width: 968px) {
            .content-wrapper {
                grid-template-columns: 1fr;
                height: auto;
            }

            .messages-panel {
                max-height: 500px;
            }

            .preview-panel {
                min-height: 400px;
            }
        }

        @media (max-width: 640px) {
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                max-width: none;
            }

            .main-container {
                padding: 16px;
            }

            .content-wrapper {
                gap: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="page-title">
                    <span class="material-icons-round">delete</span>
                    Deleted Items
                    <span class="count-badge" id="total-count"><?= number_format($totalCount) ?></span>
                </div>
            </div>

            <div class="search-container">
                <div class="search-wrapper">
                    <span class="material-icons-round search-icon">search</span>
                    <input 
                        type="text" 
                        id="search-input" 
                        placeholder="Search deleted messages..."
                        autocomplete="off"
                    >
                </div>
            </div>

            <div class="header-actions">
                <a href="inbox.php" class="btn btn-secondary">
                    <span class="material-icons-round">arrow_back</span>
                    Back to Inbox
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="content-wrapper">
            <!-- Messages List Panel -->
            <div class="messages-panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <span class="material-icons-round" style="color: var(--danger);">inbox</span>
                        Messages
                    </div>
                    <div class="panel-subtitle">
                        <span id="deleted-count"><?= number_format($totalCount) ?></span> deleted item<?= $totalCount !== 1 ? 's' : '' ?>
                    </div>
                </div>

                <div class="messages-list" id="messages-container">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <span class="material-icons-round">delete_outline</span>
                            </div>
                            <div class="empty-title">No deleted messages</div>
                            <div class="empty-text">Your trash is empty</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-item" data-id="<?= htmlspecialchars($msg['id']) ?>" onclick="loadMessage(<?= htmlspecialchars($msg['id']) ?>)">
                                <div class="message-icon">
                                    <span class="material-icons-round">mail</span>
                                </div>
                                <div class="message-info">
                                    <div class="message-sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></div>
                                    <div class="message-subject"><?= htmlspecialchars($msg['subject'] ?: '(No Subject)') ?></div>
                                    <div class="message-preview"><?= htmlspecialchars(substr($msg['body_preview'] ?? '', 0, 80)) ?></div>
                                    <div class="message-meta">
                                        <span class="message-date"><?= date('M j, Y', strtotime($msg['deleted_at'])) ?></span>
                                        <?php if ($msg['has_attachments']): ?>
                                            <span class="attachment-badge">
                                                <span class="material-icons-round">attach_file</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Preview Panel -->
            <div class="preview-panel">
                <div class="preview-header">
                    <div class="panel-title">Message Preview</div>
                    <div class="preview-actions" id="preview-actions" style="display: none;">
                        <button class="icon-btn restore" onclick="restoreMessage()" title="Restore">
                            <span class="material-icons-round">restore_from_trash</span>
                        </button>
                        <button class="icon-btn delete" onclick="confirmDelete()" title="Delete Permanently">
                            <span class="material-icons-round">delete_forever</span>
                        </button>
                    </div>
                </div>

                <div class="message-detail" id="message-detail">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <span class="material-icons-round">mail_outline</span>
                        </div>
                        <div class="empty-title">Select a message</div>
                        <div class="empty-text">Choose a message from the list to view its contents</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-backdrop" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="material-icons-round" style="color: var(--danger);">warning</span>
                    Permanently Delete Message
                </div>
            </div>
            <div class="modal-body">
                <p class="modal-text">
                    Are you sure you want to permanently delete this message? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn" style="background: var(--danger); color: white;" onclick="permanentDelete()">Delete Forever</button>
            </div>
        </div>
    </div>

    <script>
        let selectedMessageId = null;
        let currentQuery = '';

        // Load message details
        function loadMessage(id) {
            selectedMessageId = id;
            
            // Update active state
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-id="${id}"]`).classList.add('active');

            // Show loading
            const detailDiv = document.getElementById('message-detail');
            detailDiv.innerHTML = `
                <div class="loading-spinner">
                    <span class="material-icons-round spin" style="font-size: 40px; color: var(--gray-400);">autorenew</span>
                </div>
            `;

            // Fetch message
            fetch(`?action=get_message&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.message) {
                        displayMessage(data.message);
                        document.getElementById('preview-actions').style.display = 'flex';
                    } else {
                        showToast('Failed to load message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error loading message', 'error');
                });
        }

        // Display message in preview
        function displayMessage(msg) {
            const detailDiv = document.getElementById('message-detail');
            
            // Get initials for avatar
            const senderName = msg.sender_name || msg.sender_email;
            const initials = senderName.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            
            // Parse attachments
            let attachmentsHtml = '';
            if (msg.has_attachments && msg.attachment_data) {
                try {
                    const attachments = JSON.parse(msg.attachment_data);
                    attachmentsHtml = `
                        <div class="attachments-section">
                            <div class="attachments-header">
                                <span class="material-icons-round">attach_file</span>
                                Attachments (${attachments.length})
                            </div>
                            ${attachments.map(att => `
                                <div class="attachment-item">
                                    <div class="attachment-icon">
                                        <span class="material-icons-round">insert_drive_file</span>
                                    </div>
                                    <div class="attachment-info">
                                        <div class="attachment-name">${escapeHtml(att.name)}</div>
                                        <div class="attachment-size">${formatBytes(att.size)}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                } catch (e) {
                    console.error('Error parsing attachments:', e);
                }
            }

            detailDiv.innerHTML = `
                <div class="message-header">
                    <div class="message-subject-large">${escapeHtml(msg.subject || '(No Subject)')}</div>
                    
                    <div class="message-from">
                        <div class="sender-avatar">${initials}</div>
                        <div class="sender-info">
                            <div class="sender-name">${escapeHtml(senderName)}</div>
                            <div class="sender-email">${escapeHtml(msg.sender_email)}</div>
                        </div>
                    </div>

                    <div class="message-dates">
                        <div class="date-row">
                            <span class="material-icons-round">schedule</span>
                            <span class="date-label">Received:</span>
                            <span>${formatDate(msg.received_date)}</span>
                        </div>
                        <div class="date-row">
                            <span class="material-icons-round">delete</span>
                            <span class="date-label">Deleted:</span>
                            <span>${formatDate(msg.deleted_at)}</span>
                        </div>
                    </div>
                </div>

                <div class="message-body">${escapeHtml(msg.body_preview || '')}</div>
                
                ${attachmentsHtml}
            `;

            // Store original content for highlighting
            detailDiv.dataset.originalContent = detailDiv.innerHTML;
            
            // Apply highlighting if search is active
            if (currentQuery) {
                highlightMessageView(currentQuery);
            }
        }

        // Restore message
        function restoreMessage() {
            if (!selectedMessageId) return;

            fetch(`?action=restore&id=${selectedMessageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message restored successfully', 'success');
                        
                        // Remove from list
                        const messageItem = document.querySelector(`[data-id="${selectedMessageId}"]`);
                        if (messageItem) {
                            messageItem.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => {
                                messageItem.remove();
                                updateCount();
                                
                                // Clear preview
                                document.getElementById('message-detail').innerHTML = `
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <span class="material-icons-round">mail_outline</span>
                                        </div>
                                        <div class="empty-title">Select a message</div>
                                        <div class="empty-text">Choose a message from the list to view its contents</div>
                                    </div>
                                `;
                                document.getElementById('preview-actions').style.display = 'none';
                                selectedMessageId = null;
                            }, 300);
                        }
                    } else {
                        showToast('Failed to restore message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error restoring message', 'error');
                });
        }

        // Confirm permanent delete
        function confirmDelete() {
            openModal('deleteModal');
        }

        // Permanent delete
        function permanentDelete() {
            if (!selectedMessageId) return;

            closeModal('deleteModal');

            fetch(`?action=permanent_delete&id=${selectedMessageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message permanently deleted', 'success');
                        
                        // Remove from list
                        const messageItem = document.querySelector(`[data-id="${selectedMessageId}"]`);
                        if (messageItem) {
                            messageItem.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => {
                                messageItem.remove();
                                updateCount();
                                
                                // Clear preview
                                document.getElementById('message-detail').innerHTML = `
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <span class="material-icons-round">mail_outline</span>
                                        </div>
                                        <div class="empty-title">Select a message</div>
                                        <div class="empty-text">Choose a message from the list to view its contents</div>
                                    </div>
                                `;
                                document.getElementById('preview-actions').style.display = 'none';
                                selectedMessageId = null;
                            }, 300);
                        }
                    } else {
                        showToast('Failed to delete message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error deleting message', 'error');
                });
        }

        // Search functionality with highlighting
        const originalMessageContent = new Map();

        // Store original messages on page load
        function storeOriginalMessages() {
            const messageItems = document.querySelectorAll('.message-item');
            messageItems.forEach(item => {
                const id = item.dataset.id;
                const sender = item.querySelector('.message-sender');
                const subject = item.querySelector('.message-subject');
                const preview = item.querySelector('.message-preview');
                
                if (sender && subject && preview) {
                    originalMessageContent.set(id, {
                        sender: sender.textContent,
                        subject: subject.textContent,
                        preview: preview.textContent
                    });
                }
            });
        }

        // Initialize on load
        if (document.querySelectorAll('.message-item').length > 0) {
            storeOriginalMessages();
        }

        // Search with highlighting
        document.getElementById('search-input').addEventListener('input', function(e) {
            const query = e.target.value.trim();
            currentQuery = query;
            performClientSearch(query);
        });

        function performClientSearch(query) {
            const messageItems = document.querySelectorAll('.message-item');
            
            if (!query) {
                // Show all messages and remove highlights
                messageItems.forEach(item => {
                    item.style.display = 'flex';
                    const id = item.dataset.id;
                    if (originalMessageContent.has(id)) {
                        const original = originalMessageContent.get(id);
                        const sender = item.querySelector('.message-sender');
                        const subject = item.querySelector('.message-subject');
                        const preview = item.querySelector('.message-preview');
                        
                        if (sender) sender.textContent = original.sender;
                        if (subject) subject.textContent = original.subject;
                        if (preview) preview.textContent = original.preview;
                    }
                });
                
                // Clear message view highlights
                clearMessageViewHighlights();
                
                // Remove empty state if present
                const emptyState = document.querySelector('.empty-state-search');
                if (emptyState) emptyState.remove();
                return;
            }
            
            let visibleCount = 0;
            
            messageItems.forEach(item => {
                const id = item.dataset.id;
                const original = originalMessageContent.get(id);
                
                if (!original) {
                    item.style.display = 'none';
                    return;
                }
                
                const senderMatch = original.sender.toLowerCase().includes(query.toLowerCase());
                const subjectMatch = original.subject.toLowerCase().includes(query.toLowerCase());
                const previewMatch = original.preview.toLowerCase().includes(query.toLowerCase());
                
                if (senderMatch || subjectMatch || previewMatch) {
                    item.style.display = 'flex';
                    visibleCount++;
                    
                    const sender = item.querySelector('.message-sender');
                    const subject = item.querySelector('.message-subject');
                    const preview = item.querySelector('.message-preview');
                    
                    // Highlight matches
                    if (sender) {
                        sender.innerHTML = senderMatch ? highlightText(original.sender, query) : original.sender;
                    }
                    if (subject) {
                        subject.innerHTML = subjectMatch ? highlightText(original.subject, query) : original.subject;
                    }
                    if (preview) {
                        preview.innerHTML = previewMatch ? highlightText(original.preview, query) : original.preview;
                    }
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show empty state if no results
            const messagesContainer = document.getElementById('messages-container');
            const existingEmpty = messagesContainer.querySelector('.empty-state-search');
            
            if (visibleCount === 0 && !existingEmpty) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state empty-state-search';
                emptyState.innerHTML = `
                    <div class="empty-icon">
                        <span class="material-icons-round">search_off</span>
                    </div>
                    <div class="empty-title">No messages found</div>
                    <div class="empty-text">Try a different search term</div>
                `;
                messagesContainer.appendChild(emptyState);
            } else if (visibleCount > 0 && existingEmpty) {
                existingEmpty.remove();
            }
            
            // Highlight in currently viewed message
            if (selectedMessageId) {
                highlightMessageView(query);
            }
        }

        // Highlight text with yellow background
        function highlightText(text, query) {
            if (!query) return text;
            
            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }

        // Escape special regex characters
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // Highlight in message view pane
        function highlightMessageView(query) {
            const messageDetail = document.getElementById('message-detail');
            if (!messageDetail) return;
            
            if (!messageDetail.dataset.originalContent) return;
            
            if (!query) {
                messageDetail.innerHTML = messageDetail.dataset.originalContent;
                return;
            }
            
            const originalContent = messageDetail.dataset.originalContent;
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = originalContent;
            
            // Highlight text in all text nodes
            const walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);
            const textNodes = [];
            while (walker.nextNode()) {
                textNodes.push(walker.currentNode);
            }
            
            textNodes.forEach(node => {
                if (node.textContent.toLowerCase().includes(query.toLowerCase())) {
                    const span = document.createElement('span');
                    span.innerHTML = highlightText(node.textContent, query);
                    node.parentNode.replaceChild(span, node);
                }
            });
            
            messageDetail.innerHTML = tempDiv.innerHTML;
        }

        // Clear highlights in message view
        function clearMessageViewHighlights() {
            const messageDetail = document.getElementById('message-detail');
            if (messageDetail && messageDetail.dataset.originalContent) {
                messageDetail.innerHTML = messageDetail.dataset.originalContent;
            }
        }

        // Update count
        function updateCount() {
            fetch('?action=get_deleted_messages&limit=1')
                .then(res => res.json())
                .then(data => {
                    const count = data.total || 0;
                    document.getElementById('total-count').textContent = count.toLocaleString();
                    document.getElementById('deleted-count').textContent = count.toLocaleString();
                    
                    // Show empty state if no messages
                    if (count === 0 && document.querySelectorAll('.message-item').length === 0) {
                        document.getElementById('messages-container').innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <span class="material-icons-round">delete_outline</span>
                                </div>
                                <div class="empty-title">No deleted messages</div>
                                <div class="empty-text">Your trash is empty</div>
                            </div>
                        `;
                    }
                });
        }

        // Modal functions
        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        // Close modal on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    closeModal(backdrop.id);
                }
            });
        });

        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.show').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check_circle' : 'error';
            
            toast.innerHTML = `
                <span class="material-icons-round">${icon}</span>
                <span class="toast-message">${escapeHtml(message)}</span>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>