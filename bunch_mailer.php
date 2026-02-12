<?php
// htdocs/bunch_mailer.php
session_start();

// Security check: Redirect to login if session credentials do not exist
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Bulk Mailer');
        include 'header.php';
    ?>
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-green: #34C759;
            --apple-red: #FF3B30;
            --apple-orange: #FF9500;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --success-green: #34C759;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--apple-bg);
            color: #1c1c1e;
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ========== MAIN LAYOUT ========== */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            background: var(--apple-bg);
        }

        /* ========== HEADER ========== */
        .page-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 24px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
        }

        .header-container {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 28px;
            font-weight: 600;
            color: #1c1c1e;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .header-left p {
            font-size: 15px;
            color: var(--apple-gray);
            font-weight: 400;
        }

        .header-stats {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 8px 16px;
            background: var(--apple-bg);
            border-radius: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1c1c1e;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-item.pending .stat-value {
            color: var(--apple-orange);
        }

        .stat-item.completed .stat-value {
            color: var(--apple-green);
        }

        .stat-item.failed .stat-value {
            color: var(--apple-red);
        }

        /* ========== TABS ========== */
        .tabs-container {
            background: white;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 97px;
            z-index: 99;
        }

        .tabs {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            padding: 0 40px;
        }

        .tab {
            padding: 16px 24px;
            font-size: 14px;
            font-weight: 500;
            color: var(--apple-gray);
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .tab:hover {
            color: #1c1c1e;
        }

        .tab.active {
            color: var(--apple-blue);
            border-bottom-color: var(--apple-blue);
            font-weight: 600;
        }

        /* ========== CONTAINER ========== */
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 40px;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========== TWO COLUMN LAYOUT ========== */
        .compose-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* ========== CARDS ========== */
        .card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            letter-spacing: -0.3px;
        }

        .card-subtitle {
            font-size: 14px;
            color: var(--apple-gray);
            margin-top: 6px;
        }

        /* ========== UPLOAD ZONE ========== */
        .upload-zone {
            border: 3px dashed var(--border);
            border-radius: 16px;
            padding: 60px 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--apple-bg);
            position: relative;
            overflow: hidden;
        }

        .upload-zone:hover {
            border-color: var(--apple-blue);
            background: #F5F9FF;
            transform: translateY(-2px);
        }

        .upload-zone.dragover {
            border-color: var(--apple-blue);
            background: linear-gradient(135deg, #F5F9FF 0%, #E8F4FF 100%);
        }

        .upload-icon {
            font-size: 64px;
            color: var(--apple-blue);
            margin-bottom: 20px;
        }

        .upload-title {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .upload-subtitle {
            font-size: 14px;
            color: var(--apple-gray);
            margin-bottom: 20px;
        }

        .upload-formats {
            font-size: 12px;
            color: var(--apple-gray);
            margin-top: 10px;
        }

        .btn-upload {
            background: var(--apple-blue);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-upload:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        /* ========== FIELD MANAGEMENT (RIGHT SIDEBAR) ========== */
        .field-manager {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 180px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .field-manager-header {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .field-group {
            margin-bottom: 20px;
        }

        .field-group-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 12px;
        }

        .field-item {
            background: var(--apple-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 8px;
            cursor: grab;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .field-item:hover {
            background: #E8F4FF;
            border-color: var(--apple-blue);
            transform: translateX(4px);
        }

        .field-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .field-item .material-icons {
            font-size: 18px;
            color: var(--apple-gray);
        }

        .field-item-text {
            flex: 1;
        }

        .field-item-name {
            font-size: 13px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .field-item-desc {
            font-size: 11px;
            color: var(--apple-gray);
            margin-top: 2px;
        }

        .field-required-badge {
            background: var(--apple-red);
            color: white;
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        /* ========== DROP ZONE (LEFT SIDE FORM) ========== */
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 24px;
            min-height: 400px;
            background: #FAFAFA;
        }

        .drop-zone.drag-over {
            background: #E8F4FF;
            border-color: var(--apple-blue);
        }

        .drop-placeholder {
            text-align: center;
            padding: 60px 20px;
            color: var(--apple-gray);
        }

        .drop-placeholder .material-icons {
            font-size: 64px;
            color: var(--border);
            margin-bottom: 16px;
        }

        .drop-placeholder h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .drop-placeholder p {
            font-size: 14px;
        }

        .dropped-field {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            position: relative;
        }

        .dropped-field-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .dropped-field-label {
            font-size: 13px;
            font-weight: 600;
            color: #1c1c1e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remove-field-btn {
            background: var(--apple-red);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .remove-field-btn:hover {
            background: #cc0000;
        }

        .dropped-field input,
        .dropped-field select,
        .dropped-field textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .dropped-field input:focus,
        .dropped-field select:focus,
        .dropped-field textarea:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .dropped-field textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* ========== MAPPING MODAL ========== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #1c1c1e;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--apple-gray);
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: var(--apple-bg);
            color: #1c1c1e;
        }

        .mapping-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .mapping-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mapping-label {
            font-size: 13px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .mapping-select {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .mapping-select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .preview-table-container {
            margin: 24px 0;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .preview-table {
            width: 100%;
            font-size: 13px;
        }

        .preview-table thead {
            background: var(--apple-bg);
        }

        .preview-table th,
        .preview-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .preview-table th {
            font-weight: 600;
            color: #1c1c1e;
        }

        .preview-table td {
            color: #666;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        .btn-secondary {
            background: var(--apple-bg);
            color: #1c1c1e;
        }

        .btn-secondary:hover {
            background: #E5E5EA;
        }

        .btn-success {
            background: var(--apple-green);
            color: white;
        }

        .btn-success:hover {
            background: #28a745;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 199, 89, 0.3);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #D1FAE5;
            border: 1px solid var(--apple-green);
            color: #065F46;
        }

        .alert-error {
            background: #FEE2E2;
            border: 1px solid var(--apple-red);
            color: #991B1B;
        }

        .alert-info {
            background: #DBEAFE;
            border: 1px solid var(--apple-blue);
            color: #1E40AF;
        }

        .alert .material-icons {
            font-size: 24px;
        }

        /* ========== PROGRESS BAR ========== */
        .progress-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            display: none;
        }

        .progress-container.active {
            display: block;
        }

        .progress-text {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 12px;
        }

        .progress-bar {
            height: 12px;
            background: var(--apple-bg);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--apple-blue) 0%, var(--apple-green) 100%);
            transition: width 0.3s ease-out;
            width: 0%;
        }

        /* ========== QUEUE TABLE ========== */
        .queue-table {
            width: 100%;
            font-size: 13px;
        }

        .queue-table thead {
            background: var(--apple-bg);
        }

        .queue-table th,
        .queue-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .queue-table th {
            font-weight: 600;
            color: #1c1c1e;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-processing {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .status-completed {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-failed {
            background: #FEE2E2;
            color: #991B1B;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--apple-gray);
        }

        /* ========== ATTACHMENT DISPLAY ========== */
        .attachment-preview {
            background: var(--apple-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .attachment-preview .material-icons {
            font-size: 32px;
            color: var(--apple-blue);
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-size: 13px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .attachment-size {
            font-size: 11px;
            color: var(--apple-gray);
            margin-top: 2px;
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- ========== HEADER ========== -->
        <div class="page-header">
            <div class="header-container">
                <div class="header-left">
                    <h1>Bulk Email Manager</h1>
                    <p>Upload CSV files and send emails in bulk</p>
                </div>
                <div class="header-stats">
                    <div class="stat-item pending">
                        <div class="stat-value" id="stat-pending">0</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-item completed">
                        <div class="stat-value" id="stat-completed">0</div>
                        <div class="stat-label">Sent</div>
                    </div>
                    <div class="stat-item failed">
                        <div class="stat-value" id="stat-failed">0</div>
                        <div class="stat-label">Failed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== TABS ========== -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('compose')">
                    <span class="material-icons" style="font-size: 16px; margin-right: 6px;">create</span>
                    Compose Bulk Email
                </button>
                <button class="tab" onclick="switchTab('upload')">
                    <span class="material-icons" style="font-size: 16px; margin-right: 6px;">upload_file</span>
                    Upload CSV
                </button>
                <button class="tab" onclick="switchTab('queue')">
                    <span class="material-icons" style="font-size: 16px; margin-right: 6px;">list</span>
                    Email Queue
                </button>
            </div>
        </div>

        <!-- ========== CONTENT AREA ========== -->
        <div class="content-area">
            <div class="container">
                <!-- ========== COMPOSE TAB ========== -->
                <div class="tab-content active" id="compose-tab">
                    <div class="compose-layout">
                        <!-- LEFT SIDE: DROP ZONE -->
                        <div>
                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <h2 class="card-title">Email Template Builder</h2>
                                        <p class="card-subtitle">Drag fields from the right sidebar to build your email template</p>
                                    </div>
                                </div>
                                <div class="drop-zone" id="dropZone">
                                    <div class="drop-placeholder" id="dropPlaceholder">
                                        <span class="material-icons">touch_app</span>
                                        <h3>Drag & Drop Fields Here</h3>
                                        <p>Build your email template by dragging fields from the right sidebar</p>
                                    </div>
                                </div>
                            </div>

                            <button class="btn btn-success" id="saveTemplateBtn" onclick="saveTemplate()" style="width: 100%; display: none;">
                                <span class="material-icons">save</span>
                                Save Template & Proceed to Upload
                            </button>
                        </div>

                        <!-- RIGHT SIDE: FIELD MANAGER -->
                        <div class="field-manager">
                            <div class="field-manager-header">
                                <span class="material-icons">widgets</span>
                                Available Fields
                            </div>

                            <div class="field-group">
                                <div class="field-group-title">Required Fields</div>
                                <div class="field-item" draggable="true" data-field="recipient_email" data-type="email" data-required="true">
                                    <span class="material-icons">email</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Recipient Email</div>
                                        <div class="field-item-desc">Recipient's email address</div>
                                    </div>
                                    <span class="field-required-badge">Required</span>
                                </div>
                            </div>

                            <div class="field-group">
                                <div class="field-group-title">Email Content</div>
                                <div class="field-item" draggable="true" data-field="subject" data-type="text">
                                    <span class="material-icons">subject</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Subject</div>
                                        <div class="field-item-desc">Email subject line</div>
                                    </div>
                                </div>
                                <div class="field-item" draggable="true" data-field="article_title" data-type="text">
                                    <span class="material-icons">title</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Article Title</div>
                                        <div class="field-item-desc">Main heading in email body</div>
                                    </div>
                                </div>
                                <div class="field-item" draggable="true" data-field="message_content" data-type="textarea">
                                    <span class="material-icons">description</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Message Content</div>
                                        <div class="field-item-desc">Main email body text</div>
                                    </div>
                                </div>
                            </div>

                            <div class="field-group">
                                <div class="field-group-title">Recipient Details</div>
                                <div class="field-item" draggable="true" data-field="recipient_name" data-type="text">
                                    <span class="material-icons">person</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Recipient Name</div>
                                        <div class="field-item-desc">Full name of recipient</div>
                                    </div>
                                </div>
                            </div>

                            <div class="field-group">
                                <div class="field-group-title">Signature Fields</div>
                                <div class="field-item" draggable="true" data-field="closing_wish" data-type="text">
                                    <span class="material-icons">waving_hand</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Closing Wish</div>
                                        <div class="field-item-desc">e.g., "Best Regards"</div>
                                    </div>
                                </div>
                                <div class="field-item" draggable="true" data-field="sender_name" data-type="text">
                                    <span class="material-icons">account_circle</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Sender Name</div>
                                        <div class="field-item-desc">Name of sender</div>
                                    </div>
                                </div>
                                <div class="field-item" draggable="true" data-field="sender_designation" data-type="text">
                                    <span class="material-icons">work</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Sender Designation</div>
                                        <div class="field-item-desc">Job title or role</div>
                                    </div>
                                </div>
                                <div class="field-item" draggable="true" data-field="additional_info" data-type="text">
                                    <span class="material-icons">info</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Additional Info</div>
                                        <div class="field-item-desc">Extra signature line</div>
                                    </div>
                                </div>
                            </div>

                            <div class="field-group">
                                <div class="field-group-title">Attachments</div>
                                <div class="field-item" draggable="true" data-field="attachment" data-type="file">
                                    <span class="material-icons">attach_file</span>
                                    <div class="field-item-text">
                                        <div class="field-item-name">Attachment</div>
                                        <div class="field-item-desc">Upload a file to attach</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== UPLOAD CSV TAB ========== -->
                <div class="tab-content" id="upload-tab">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Upload CSV File</h2>
                                <p class="card-subtitle">Upload a CSV file containing recipient data</p>
                            </div>
                        </div>

                        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFileInput').click()">
                            <div class="upload-icon">
                                <span class="material-icons" style="font-size: inherit; color: inherit;">cloud_upload</span>
                            </div>
                            <h3 class="upload-title">Drop your CSV file here</h3>
                            <p class="upload-subtitle">or click to browse</p>
                            <button class="btn-upload" type="button">
                                <span class="material-icons">upload_file</span>
                                Select CSV File
                            </button>
                            <p class="upload-formats">Supported format: .csv (Max 10MB)</p>
                        </div>

                        <input type="file" id="csvFileInput" accept=".csv" style="display: none;" onchange="handleFileSelect(event)">
                    </div>
                </div>

                <!-- ========== QUEUE TAB ========== -->
                <div class="tab-content" id="queue-tab">
                    <div class="progress-container" id="processingProgress">
                        <div class="progress-text" id="progressText">Processing emails...</div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Email Queue</h2>
                                <p class="card-subtitle">Manage and process pending emails</p>
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <button class="btn btn-primary" id="processQueueBtn" onclick="processQueue()">
                                    <span class="material-icons">send</span>
                                    Process Queue
                                </button>
                                <button class="btn btn-secondary" onclick="clearQueue()">
                                    <span class="material-icons">delete_sweep</span>
                                    Clear Queue
                                </button>
                            </div>
                        </div>

                        <div id="queueTableContainer">
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <h3>No emails in queue</h3>
                                <p>Upload a CSV file to get started</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== MAPPING MODAL ========== -->
    <div class="modal" id="mappingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Map CSV Columns</h2>
                <button class="modal-close" onclick="closeMappingModal()">×</button>
            </div>

            <p style="margin-bottom: 24px; color: var(--apple-gray);">
                Map your CSV columns to email fields. The system has auto-detected some mappings for you.
            </p>

            <div class="mapping-grid" id="mappingGrid">
                <!-- Mapping fields will be dynamically inserted here -->
            </div>

            <div class="preview-table-container">
                <table class="preview-table" id="previewTable">
                    <thead>
                        <tr>
                            <th colspan="100%">Preview (First 5 rows)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Preview data will be inserted here -->
                    </tbody>
                </table>
            </div>

            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeMappingModal()">Cancel</button>
                <button class="btn btn-primary" id="confirmMappingBtn" onclick="confirmMapping()">
                    <span class="material-icons">check_circle</span>
                    Add to Queue
                </button>
            </div>
        </div>
    </div>

    <script>
        // ========== GLOBAL STATE ==========
        let currentCSVData = null;
        let fieldMapping = {};
        let droppedFields = [];
        let templateDefaults = {};

        // ========== TAB SWITCHING ==========
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(`${tabName}-tab`).classList.add('active');

            // Add active class to selected tab
            event.target.closest('.tab').classList.add('active');

            // Load queue if queue tab is selected
            if (tabName === 'queue') {
                loadQueue();
            }
        }

        // ========== DRAG AND DROP FUNCTIONALITY ==========
        const dropZone = document.getElementById('dropZone');
        const dropPlaceholder = document.getElementById('dropPlaceholder');
        const saveBtn = document.getElementById('saveTemplateBtn');

        // Setup drag events for field items
        document.querySelectorAll('.field-item').forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragend', handleDragEnd);
        });

        // Setup drop zone
        dropZone.addEventListener('dragover', handleDragOver);
        dropZone.addEventListener('dragleave', handleDragLeave);
        dropZone.addEventListener('drop', handleDrop);

        let draggedItem = null;

        function handleDragStart(e) {
            draggedItem = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'copy';
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            dropZone.classList.add('drag-over');
            return false;
        }

        function handleDragLeave(e) {
            dropZone.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');

            if (!draggedItem) return;

            const fieldName = draggedItem.dataset.field;
            const fieldType = draggedItem.dataset.type;
            const fieldRequired = draggedItem.dataset.required === 'true';
            const fieldLabel = draggedItem.querySelector('.field-item-name').textContent;

            // Check if field already exists
            if (droppedFields.includes(fieldName)) {
                showAlert('info', `${fieldLabel} is already in the template`);
                return;
            }

            // Hide placeholder
            dropPlaceholder.style.display = 'none';

            // Add field to dropped list
            droppedFields.push(fieldName);

            // Create dropped field element
            const droppedField = createDroppedField(fieldName, fieldLabel, fieldType, fieldRequired);
            dropZone.appendChild(droppedField);

            // Show save button
            saveBtn.style.display = 'flex';

            draggedItem = null;
        }

        function createDroppedField(fieldName, fieldLabel, fieldType, fieldRequired) {
            const div = document.createElement('div');
            div.className = 'dropped-field';
            div.dataset.field = fieldName;

            let inputHTML = '';
            switch (fieldType) {
                case 'email':
                    inputHTML = `<input type="email" id="template_${fieldName}" placeholder="Will be filled from CSV">`;
                    break;
                case 'textarea':
                    inputHTML = `<textarea id="template_${fieldName}" placeholder="Will be filled from CSV or use default below"></textarea>`;
                    break;
                case 'file':
                    inputHTML = `
                        <input type="file" id="template_${fieldName}" style="display: none;" onchange="handleTemplateFileUpload(event, '${fieldName}')">
                        <button class="btn btn-secondary" onclick="document.getElementById('template_${fieldName}').click()" style="width: 100%;">
                            <span class="material-icons">upload_file</span>
                            Upload Attachment
                        </button>
                        <div id="template_${fieldName}_preview"></div>
                    `;
                    break;
                default:
                    inputHTML = `<input type="text" id="template_${fieldName}" placeholder="Will be filled from CSV or use default below">`;
            }

            div.innerHTML = `
                <div class="dropped-field-header">
                    <div class="dropped-field-label">
                        <span class="material-icons">drag_indicator</span>
                        ${fieldLabel}
                        ${fieldRequired ? '<span class="field-required-badge">Required</span>' : ''}
                    </div>
                    ${!fieldRequired ? `<button class="remove-field-btn" onclick="removeField('${fieldName}')">Remove</button>` : ''}
                </div>
                ${inputHTML}
                ${fieldType !== 'file' && fieldType !== 'email' ? `<input type="text" id="default_${fieldName}" placeholder="Enter default value (optional)" style="margin-top: 8px; font-size: 12px; font-style: italic;">` : ''}
            `;

            return div;
        }

        function removeField(fieldName) {
            const index = droppedFields.indexOf(fieldName);
            if (index > -1) {
                droppedFields.splice(index, 1);
            }

            const fieldElement = dropZone.querySelector(`[data-field="${fieldName}"]`);
            if (fieldElement) {
                fieldElement.remove();
            }

            // Show placeholder if no fields left
            if (droppedFields.length === 0) {
                dropPlaceholder.style.display = 'block';
                saveBtn.style.display = 'none';
            }
        }

        function handleTemplateFileUpload(event, fieldName) {
            const file = event.target.files[0];
            if (!file) return;

            // Create FormData and upload file
            const formData = new FormData();
            formData.append('file', file);

            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Store attachment ID in template defaults
                    templateDefaults.attachment_id = data.attachmentId;

                    // Show preview
                    const preview = document.getElementById(`template_${fieldName}_preview`);
                    preview.innerHTML = `
                        <div class="attachment-preview">
                            <span class="material-icons">attach_file</span>
                            <div class="attachment-info">
                                <div class="attachment-name">${file.name}</div>
                                <div class="attachment-size">${formatFileSize(file.size)}</div>
                            </div>
                        </div>
                    `;

                    showAlert('success', 'Attachment uploaded successfully');
                } else {
                    showAlert('error', data.error || 'Failed to upload attachment');
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                showAlert('error', 'Failed to upload attachment');
            });
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function saveTemplate() {
            // Collect default values
            templateDefaults = {};

            droppedFields.forEach(fieldName => {
                const defaultInput = document.getElementById(`default_${fieldName}`);
                if (defaultInput && defaultInput.value.trim()) {
                    templateDefaults[fieldName] = defaultInput.value.trim();
                }
            });

            showAlert('success', 'Template saved! Now upload a CSV file to continue.');
            switchTab('upload');
        }

        // ========== FILE UPLOAD ==========
        const uploadZone = document.getElementById('uploadZone');
        const csvFileInput = document.getElementById('csvFileInput');

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                handleFile(file);
            }
        }

        async function handleFile(file) {
            // Validate file
            if (!file.name.endsWith('.csv')) {
                showAlert('error', 'Please upload a CSV file');
                return;
            }

            const formData = new FormData();
            formData.append('csv_file', file);

            try {
                const response = await fetch('bulk_mail_backend.php?action=analyze', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    currentCSVData = data;
                    showMappingModal(data);
                } else {
                    showAlert('error', data.error || 'Failed to analyze CSV file');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to upload file');
            }
        }

        // ========== MAPPING MODAL ==========
        function showMappingModal(csvData) {
            const modal = document.getElementById('mappingModal');
            const mappingGrid = document.getElementById('mappingGrid');

            // Reset mapping
            fieldMapping = { ...csvData.suggested_mapping };

            // Create mapping fields
            const expectedFields = {
                'recipient_email': 'Recipient Email (required)',
                'recipient_name': 'Recipient Name',
                'subject': 'Subject',
                'article_title': 'Article Title',
                'message_content': 'Message Content',
                'closing_wish': 'Closing Wish',
                'sender_name': 'Sender Name',
                'sender_designation': 'Sender Designation',
                'additional_info': 'Additional Info'
            };

            let html = '';

            Object.entries(expectedFields).forEach(([field, label]) => {
                const required = field === 'recipient_email';
                const selectedColumn = fieldMapping[field] || '';

                html += `
                    <div class="mapping-item">
                        <label class="mapping-label">
                            ${label}
                            ${required ? '<span style="color: var(--apple-red);">*</span>' : ''}
                        </label>
                        <select class="mapping-select" onchange="updateMapping('${field}', this.value)">
                            <option value="">-- Not Mapped --</option>
                            ${csvData.csv_columns.map(col => `
                                <option value="${col}" ${col === selectedColumn ? 'selected' : ''}>
                                    ${col}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                `;
            });

            mappingGrid.innerHTML = html;

            // Update preview table
            updatePreviewTable(csvData);

            // Show modal
            modal.classList.add('active');
        }

        function updateMapping(field, column) {
            if (column) {
                fieldMapping[field] = column;
            } else {
                delete fieldMapping[field];
            }
            updatePreviewTable(currentCSVData);
        }

        function closeMappingModal() {
            document.getElementById('mappingModal').classList.remove('active');
        }

        function updatePreviewTable(csvData) {
            const table = document.getElementById('previewTable');
            let html = '<thead><tr>';

            // Add headers based on mapping
            Object.keys(fieldMapping).forEach(field => {
                const column = fieldMapping[field];
                html += `<th>${field}<br><small style="font-weight: normal; color: var(--apple-gray);">(${column})</small></th>`;
            });

            html += '</tr></thead><tbody>';

            // Add preview rows
            csvData.preview_rows.forEach((row, index) => {
                html += '<tr>';
                Object.entries(fieldMapping).forEach(([field, column]) => {
                    html += `<td>${row[column] || '-'}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody>';
            table.innerHTML = html;
        }

        // ========== CONFIRM MAPPING ==========
        async function confirmMapping() {
            // Validate required fields
            if (!fieldMapping['recipient_email']) {
                showAlert('error', 'Recipient Email field is required');
                return;
            }

            const btn = document.getElementById('confirmMappingBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons">hourglass_empty</span> Adding to queue...';

            try {
                // Read the entire CSV file
                const response = await fetch('bulk_mail_backend.php?action=parse_full_csv', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        filename: currentCSVData.filename,
                        mapping: fieldMapping
                    })
                });

                const parseData = await response.json();

                if (!parseData.success) {
                    throw new Error(parseData.error || 'Failed to parse CSV');
                }

                // Transform CSV data based on mapping
                const emails = parseData.rows.map(row => {
                    const mapped = {};
                    Object.entries(fieldMapping).forEach(([field, column]) => {
                        mapped[field] = row[column] || '';
                    });
                    return mapped;
                });

                // Add to queue with template defaults
                const addResponse = await fetch('bulk_mail_backend.php?action=add_to_queue', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        emails: emails,
                        subject: templateDefaults.subject || 'Bulk Email',
                        article_title: templateDefaults.article_title || '',
                        message_content: templateDefaults.message_content || '',
                        closing_wish: templateDefaults.closing_wish || '',
                        sender_name: templateDefaults.sender_name || '',
                        sender_designation: templateDefaults.sender_designation || '',
                        additional_info: templateDefaults.additional_info || '',
                        attachment_id: templateDefaults.attachment_id || null
                    })
                });

                const data = await addResponse.json();

                if (data.success) {
                    showAlert('success', `Successfully added ${data.added} emails to queue`);
                    closeMappingModal();
                    switchTab('queue');
                    loadQueue();
                } else {
                    showAlert('error', data.error || 'Failed to add emails to queue');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to add emails to queue: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons">check_circle</span> Add to Queue';
            }
        }

        // ========== QUEUE MANAGEMENT ==========
        async function loadQueue() {
            try {
                const response = await fetch('process_bulk_mail.php?action=status');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('stat-pending').textContent = data.pending || 0;
                    document.getElementById('stat-completed').textContent = data.completed || 0;
                    document.getElementById('stat-failed').textContent = data.failed || 0;

                    await loadQueueList();
                }
            } catch (error) {
                console.error('Error loading queue:', error);
            }
        }

        async function loadQueueList() {
            const container = document.getElementById('queueTableContainer');

            try {
                const response = await fetch('process_bulk_mail.php?action=queue_list');
                const data = await response.json();

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
                                            <td>#${item.id}</td>
                                            <td>
                                                <strong>${item.recipient_name || 'N/A'}</strong><br>
                                                <span style="font-size: 12px; color: #666;">${item.recipient_email}</span>
                                            </td>
                                            <td>${item.subject}</td>
                                            <td>
                                                <span class="status-badge status-${item.status}">
                                                    ${item.status}
                                                </span>
                                            </td>
                                            <td style="font-size: 12px;">${formatDate(item.created_at)}</td>
                                            <td style="font-size: 12px;">${item.completed_at ? formatDate(item.completed_at) : '-'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <h3>No emails in queue</h3>
                                <p>Upload a CSV file to get started</p>
                            </div>
                        `;
                    }
                }
            } catch (error) {
                console.error('Error loading queue list:', error);
            }
        }

        async function processQueue() {
            const btn = document.getElementById('processQueueBtn');
            const progressContainer = document.getElementById('processingProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            btn.disabled = true;
            progressContainer.classList.add('active');

            const pending = parseInt(document.getElementById('stat-pending').textContent);

            if (pending === 0) {
                showAlert('error', 'No pending emails to process');
                btn.disabled = false;
                progressContainer.classList.remove('active');
                return;
            }

            let processed = 0;
            let success = 0;
            let failed = 0;

            // Process emails one by one
            for (let i = 0; i < pending; i++) {
                try {
                    const response = await fetch('process_bulk_mail.php?action=process', {
                        method: 'POST'
                    });

                    const data = await response.json();

                    if (data.success) {
                        if (data.email_sent) {
                            success++;
                        } else {
                            failed++;
                        }
                        processed++;
                        const percent = (processed / pending) * 100;
                        progressFill.style.width = percent + '%';
                        progressText.textContent = `Processing ${processed}/${pending} emails (${success} sent, ${failed} failed)...`;

                        await loadQueue();
                        await new Promise(resolve => setTimeout(resolve, 500));
                    } else {
                        failed++;
                        processed++;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    failed++;
                    processed++;
                }
            }

            progressText.textContent = `Completed! Processed ${processed} emails (${success} sent, ${failed} failed).`;
            btn.disabled = false;

            setTimeout(() => {
                progressContainer.classList.remove('active');
                progressFill.style.width = '0%';
            }, 3000);

            if (failed > 0) {
                showAlert('info', `Processed ${processed} emails: ${success} sent successfully, ${failed} failed`);
            } else {
                showAlert('success', `Successfully sent all ${success} emails!`);
            }
        }

        async function clearQueue() {
            if (!confirm('Are you sure you want to clear all pending emails?')) {
                return;
            }

            try {
                const response = await fetch('process_bulk_mail.php?action=clear', {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', data.message);
                    loadQueue();
                } else {
                    showAlert('error', data.error);
                }
            } catch (error) {
                showAlert('error', 'Failed to clear queue');
            }
        }

        // ========== UTILITIES ==========
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString();
        }

        function showAlert(type, message) {
            const container = document.querySelector('.container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;

            const iconMap = {
                'success': 'check_circle',
                'error': 'error',
                'info': 'info'
            };

            alert.innerHTML = `
                <span class="material-icons">${iconMap[type]}</span>
                <div>${message}</div>
            `;

            container.insertBefore(alert, container.firstChild);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // ========== INITIALIZATION ==========
        document.addEventListener('DOMContentLoaded', function() {
            loadQueue();
        });
    </script>
</body>

</html>