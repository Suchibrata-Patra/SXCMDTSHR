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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Merge — SXC MDTS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

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
            max-width: 1400px;
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
            max-width: 1400px;
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
            max-width: 1400px;
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
            background: #E3F2FF;
            transform: scale(1.02);
            box-shadow: 0 8px 24px rgba(0, 122, 255, 0.15);
        }

        .upload-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--apple-blue), #0051D5);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0, 122, 255, 0.25);
        }

        .upload-icon .material-icons {
            font-size: 32px;
            color: white;
        }

        .upload-text {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .upload-hint {
            font-size: 14px;
            color: var(--apple-gray);
            margin-bottom: 20px;
        }

        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .upload-btn:hover {
            background: #0051D5;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 122, 255, 0.4);
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
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: modalFade 0.3s ease;
        }

        .modal.active {
            display: flex;
        }

        @keyframes modalFade {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 1200px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlide 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--apple-blue), #0051D5);
            color: white;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-title {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.3px;
        }

        .modal-subtitle {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 4px;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 32px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: var(--apple-bg);
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: var(--apple-gray);
        }

        /* ========== MAPPING INTERFACE ========== */
        .mapping-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 32px;
            margin-bottom: 32px;
            align-items: start;
        }

        .mapping-column {
            background: var(--apple-bg);
            border-radius: 16px;
            padding: 24px;
            min-height: 400px;
        }

        .mapping-column-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mapping-column-title .material-icons {
            font-size: 18px;
        }

        .mapping-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 50px;
        }

        .mapping-arrow .material-icons {
            font-size: 32px;
            color: var(--apple-gray);
        }

        /* Draggable Items */
        .draggable-item {
            background: white;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            cursor: grab;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .draggable-item:hover {
            border-color: var(--apple-blue);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.15);
        }

        .draggable-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
            transform: rotate(3deg) scale(1.05);
        }

        .drag-handle {
            color: var(--apple-gray);
            font-size: 20px;
        }

        .draggable-item-text {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .draggable-item-badge {
            font-size: 11px;
            font-weight: 600;
            color: white;
            background: var(--apple-blue);
            padding: 4px 10px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .draggable-item-badge.optional {
            background: var(--apple-gray);
        }

        /* Drop Zones */
        .drop-zone {
            background: white;
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            min-height: 50px;
            transition: all 0.2s;
            position: relative;
        }

        .drop-zone.empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--apple-gray);
            font-size: 13px;
            font-style: italic;
        }

        .drop-zone.drag-over {
            border-color: var(--apple-blue);
            background: #F5F9FF;
            border-style: solid;
        }

        .drop-zone.filled {
            border-style: solid;
            border-color: var(--apple-green);
            background: #F0FDF4;
        }

        .drop-zone-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            margin-bottom: 8px;
            display: block;
        }

        .drop-zone-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .drop-zone-remove {
            color: var(--apple-red);
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            border-radius: 50%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .drop-zone-remove:hover {
            background: rgba(255, 59, 48, 0.1);
        }

        /* ========== PREVIEW SECTION ========== */
        .preview-section {
            background: var(--apple-bg);
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
        }

        .preview-title {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .preview-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .preview-table th {
            background: var(--apple-bg);
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        .preview-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: #1c1c1e;
            border-bottom: 1px solid var(--border);
        }

        .preview-table tr:last-child td {
            border-bottom: none;
        }

        .preview-table tr:hover {
            background: var(--apple-bg);
        }

        /* ========== BUTTONS ========== */
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .btn-primary:hover {
            background: #0051D5;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 122, 255, 0.4);
        }

        .btn-primary:disabled {
            background: var(--apple-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: white;
            color: #1c1c1e;
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--apple-bg);
        }

        .btn-success {
            background: var(--apple-green);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.3);
        }

        .btn-success:hover {
            background: #28A745;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(52, 199, 89, 0.4);
        }

        .btn-danger {
            background: var(--apple-red);
            color: white;
        }

        .btn-danger:hover {
            background: #E02020;
        }

        .modal-footer {
            padding: 24px 32px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            background: var(--apple-bg);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #F0FDF4;
            border: 1px solid #86EFAC;
            color: #166534;
        }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        .alert-info {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            color: #1E40AF;
        }

        .alert .material-icons {
            font-size: 24px;
        }

        /* ========== QUEUE TABLE ========== */
        .queue-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .queue-table thead {
            background: var(--apple-bg);
        }

        .queue-table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .queue-table td {
            padding: 16px;
            font-size: 14px;
            color: #1c1c1e;
            border-bottom: 1px solid var(--border);
        }

        .queue-table tr:hover {
            background: var(--apple-bg);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
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
            padding: 60px 40px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
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

        /* ========== PROGRESS BAR ========== */
        .progress-container {
            display: none;
            margin: 20px 0;
        }

        .progress-container.active {
            display: block;
        }

        .progress-bar {
            height: 8px;
            background: var(--apple-bg);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--apple-blue), #0051D5);
            border-radius: 10px;
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 13px;
            color: var(--apple-gray);
            text-align: center;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .mapping-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .mapping-arrow {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- ========== PAGE HEADER ========== -->
        <div class="page-header">
            <div class="header-container">
                <div class="header-left">
                    <h1>📧 Mail Merge</h1>
                    <p>Send personalized bulk emails with CSV import</p>
                </div>
                <div class="header-stats">
                    <div class="stat-item pending">
                        <div class="stat-value" id="stat-pending">0</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-item completed">
                        <div class="stat-value" id="stat-completed">0</div>
                        <div class="stat-label">Completed</div>
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
                <button class="tab active" onclick="switchTab('upload')">
                    <span class="material-icons">upload_file</span>
                    Upload CSV
                </button>
                <button class="tab" onclick="switchTab('queue')">
                    <span class="material-icons">list</span>
                    Email Queue
                </button>
            </div>
        </div>

        <!-- ========== MAIN CONTENT AREA ========== -->
        <div class="content-area">
            <div class="container">
                <!-- ========== UPLOAD TAB ========== -->
                <div class="tab-content active" id="upload-tab">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Upload CSV File</h2>
                                <p class="card-subtitle">Import your recipient list from a CSV file</p>
                            </div>
                        </div>

                        <div class="upload-zone" id="uploadZone">
                            <div class="upload-icon">
                                <span class="material-icons">cloud_upload</span>
                            </div>
                            <div class="upload-text">Drop your CSV file here</div>
                            <div class="upload-hint">or click to browse • Maximum 10MB</div>
                            <input type="file" id="csvFileInput" accept=".csv" style="display: none;">
                            <button class="upload-btn" onclick="document.getElementById('csvFileInput').click()">
                                <span class="material-icons">folder_open</span>
                                Choose File
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ========== QUEUE TAB ========== -->
                <div class="tab-content" id="queue-tab">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Email Queue</h2>
                                <p class="card-subtitle">Manage and process your email queue</p>
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <button class="btn btn-secondary" onclick="loadQueue()">
                                    <span class="material-icons">refresh</span>
                                    Refresh
                                </button>
                                <button class="btn btn-success" id="processQueueBtn" onclick="processQueue()">
                                    <span class="material-icons">send</span>
                                    Process Queue
                                </button>
                                <button class="btn btn-danger" onclick="clearQueue()">
                                    <span class="material-icons">delete</span>
                                    Clear Pending
                                </button>
                            </div>
                        </div>

                        <div class="progress-container" id="processingProgress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                            <div class="progress-text" id="progressText">Processing emails...</div>
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
                <div>
                    <div class="modal-title">Map CSV Columns</div>
                    <div class="modal-subtitle">Drag and drop to match your CSV columns with email fields</div>
                </div>
                <button class="modal-close" onclick="closeMappingModal()">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <div class="modal-body">
                <!-- File Info -->
                <div class="alert alert-info">
                    <span class="material-icons">info</span>
                    <div>
                        <strong>File:</strong> <span id="csvFileName"></span> •
                        <strong>Rows:</strong> <span id="csvRowCount"></span>
                    </div>
                </div>

                <!-- Mapping Grid -->
                <div class="mapping-grid">
                    <!-- CSV Columns (Left) -->
                    <div class="mapping-column">
                        <div class="mapping-column-title">
                            <span class="material-icons">table_chart</span>
                            Your CSV Columns
                        </div>
                        <div id="csvColumnsList"></div>
                    </div>

                    <!-- Arrow -->
                    <div class="mapping-arrow">
                        <span class="material-icons">arrow_forward</span>
                    </div>

                    <!-- Email Fields (Right) -->
                    <div class="mapping-column">
                        <div class="mapping-column-title">
                            <span class="material-icons">mail</span>
                            Email Fields
                        </div>
                        <div id="emailFieldsList"></div>
                    </div>
                </div>

                <!-- Preview -->
                <div class="preview-section">
                    <div class="preview-title">
                        <span class="material-icons">preview</span>
                        Preview (First 3 Rows)
                    </div>
                    <div class="preview-table">
                        <table id="previewTable"></table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeMappingModal()">Cancel</button>
                <button class="btn btn-primary" id="confirmMappingBtn" onclick="confirmMapping()">
                    <span class="material-icons">check_circle</span>
                    Add to Queue
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentCSVData = null;
        let fieldMapping = {};
        let draggedElement = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            loadQueue();
            setupUploadZone();
            setInterval(loadQueue, 10000); // Refresh every 10 seconds
        });

        // ========== TAB SWITCHING ==========
        function switchTab(tabName) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            event.target.closest('.tab').classList.add('active');

            // Update content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');

            if (tabName === 'queue') {
                loadQueue();
            }
        }

        // ========== UPLOAD ZONE SETUP ==========
        function setupUploadZone() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('csvFileInput');

            // Drag and drop
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
                    handleFileUpload(files[0]);
                }
            });

            // File input change
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileUpload(e.target.files[0]);
                }
            });
        }

        // ========== FILE UPLOAD HANDLER ==========
        async function handleFileUpload(file) {
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
                    openMappingModal(data);
                } else {
                    showAlert('error', data.error || 'Failed to analyze CSV file');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showAlert('error', 'Failed to upload file: ' + error.message);
            }
        }

        // ========== MAPPING MODAL ==========
        function openMappingModal(csvData) {
            document.getElementById('csvFileName').textContent = csvData.filename;
            document.getElementById('csvRowCount').textContent = csvData.total_rows;

            // Populate CSV columns (draggable)
            const csvColumnsList = document.getElementById('csvColumnsList');
            csvColumnsList.innerHTML = '';
            csvData.csv_columns.forEach(column => {
                const item = document.createElement('div');
                item.className = 'draggable-item';
                item.draggable = true;
                item.dataset.column = column;
                item.innerHTML = `
                    <span class="material-icons drag-handle">drag_indicator</span>
                    <span class="draggable-item-text">${column}</span>
                `;

                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);

                csvColumnsList.appendChild(item);
            });

            // Populate email fields (drop zones)
            const emailFieldsList = document.getElementById('emailFieldsList');
            emailFieldsList.innerHTML = '';

            const fields = {
                'recipient_email': { label: 'Recipient Email', required: true },
                'recipient_name': { label: 'Recipient Name', required: false },
                'subject': { label: 'Subject', required: false },
                'article_title': { label: 'Article Title', required: false },
                'message_content': { label: 'Message Content', required: false }
            };

            Object.entries(fields).forEach(([key, value]) => {
                const dropZone = document.createElement('div');
                dropZone.className = 'drop-zone empty';
                dropZone.dataset.field = key;
                dropZone.innerHTML = `
                    <span class="drop-zone-label">
                        ${value.label}
                        ${value.required ? '<span class="draggable-item-badge">Required</span>' : '<span class="draggable-item-badge optional">Optional</span>'}
                    </span>
                    <div class="drop-zone-placeholder">Drop CSV column here</div>
                `;

                dropZone.addEventListener('dragover', handleDragOver);
                dropZone.addEventListener('dragleave', handleDragLeave);
                dropZone.addEventListener('drop', handleDrop);

                emailFieldsList.appendChild(dropZone);
            });

            // Auto-map if suggestions available
            if (csvData.suggested_mapping) {
                Object.entries(csvData.suggested_mapping).forEach(([column, field]) => {
                    const dropZone = document.querySelector(`.drop-zone[data-field="${field}"]`);
                    if (dropZone) {
                        fillDropZone(dropZone, column);
                    }
                });
            }

            // Update preview
            updatePreview(csvData.preview_rows);

            // Show modal
            document.getElementById('mappingModal').classList.add('active');
        }

        function closeMappingModal() {
            document.getElementById('mappingModal').classList.remove('active');
            currentCSVData = null;
            fieldMapping = {};
        }

        // ========== DRAG AND DROP HANDLERS ==========
        function handleDragStart(e) {
            draggedElement = e.target;
            e.target.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', e.target.dataset.column);
        }

        function handleDragEnd(e) {
            e.target.classList.remove('dragging');
            draggedElement = null;
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            const dropZone = e.currentTarget;
            dropZone.classList.remove('drag-over');

            const column = e.dataTransfer.getData('text/plain');
            fillDropZone(dropZone, column);

            // Hide dragged element
            if (draggedElement) {
                draggedElement.style.display = 'none';
            }
        }

        function fillDropZone(dropZone, column) {
            dropZone.classList.remove('empty');
            dropZone.classList.add('filled');
            dropZone.innerHTML = `
                <span class="drop-zone-label">${dropZone.querySelector('.drop-zone-label') ? dropZone.querySelector('.drop-zone-label').textContent : dropZone.dataset.field}</span>
                <div class="drop-zone-content">
                    <span><strong>${column}</strong></span>
                    <button class="drop-zone-remove" onclick="clearDropZone(this)">
                        <span class="material-icons">close</span>
                    </button>
                </div>
            `;

            fieldMapping[dropZone.dataset.field] = column;
        }

        function clearDropZone(button) {
            const dropZone = button.closest('.drop-zone');
            const field = dropZone.dataset.field;

            // Show the dragged element again
            const column = fieldMapping[field];
            const draggableItem = document.querySelector(`.draggable-item[data-column="${column}"]`);
            if (draggableItem) {
                draggableItem.style.display = 'flex';
            }

            delete fieldMapping[field];

            dropZone.classList.remove('filled');
            dropZone.classList.add('empty');
            dropZone.innerHTML = `
                <span class="drop-zone-label">${dropZone.dataset.field}</span>
                <div class="drop-zone-placeholder">Drop CSV column here</div>
            `;

            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('dragleave', handleDragLeave);
            dropZone.addEventListener('drop', handleDrop);
        }

        // ========== PREVIEW TABLE ==========
        function updatePreview(previewRows) {
            const table = document.getElementById('previewTable');
            if (!previewRows || previewRows.length === 0) {
                table.innerHTML = '<p>No preview available</p>';
                return;
            }

            const headers = Object.keys(previewRows[0]);
            let html = '<thead><tr>';
            headers.forEach(header => {
                html += `<th>${header}</th>`;
            });
            html += '</tr></thead><tbody>';

            previewRows.slice(0, 3).forEach(row => {
                html += '<tr>';
                headers.forEach(header => {
                    html += `<td>${row[header] || ''}</td>`;
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
                // Transform CSV data based on mapping
                const emails = currentCSVData.preview_rows.map(row => {
                    const mapped = {};
                    Object.entries(fieldMapping).forEach(([field, column]) => {
                        mapped[field] = row[column] || '';
                    });
                    return mapped;
                });

                // Add to queue
                const response = await fetch('bulk_mail_backend.php?action=add_to_queue', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        emails: emails,
                        subject: 'Bulk Email',
                        article_title: '',
                        message_content: '',
                        closing_wish: '',
                        sender_name: '',
                        sender_designation: '',
                        additional_info: ''
                    })
                });

                const data = await response.json();

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

            for (let i = 0; i < pending; i++) {
                try {
                    const response = await fetch('process_bulk_mail.php?action=process', {
                        method: 'POST'
                    });

                    const data = await response.json();

                    if (data.success) {
                        processed++;
                        const percent = (processed / pending) * 100;
                        progressFill.style.width = percent + '%';
                        progressText.textContent = `Processing ${processed}/${pending} emails...`;

                        await loadQueue();
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            }

            progressText.textContent = `Completed! Processed ${processed} emails.`;
            btn.disabled = false;

            setTimeout(() => {
                progressContainer.classList.remove('active');
                progressFill.style.width = '0%';
            }, 3000);

            showAlert('success', `Successfully processed ${processed} emails`);
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
    </script>
</body>

</html>