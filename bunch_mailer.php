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
        define('PAGE_TITLE', 'SXC MDTS | Mailmerge');
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
            grid-template-columns: 1fr 420px;
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
            border-radius: 12px;
            padding: 48px 32px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--apple-bg);
        }

        .upload-zone:hover {
            border-color: var(--apple-blue);
            background: #F0F7FF;
        }

        .upload-zone.dragover {
            border-color: var(--apple-blue);
            background: #E5F2FF;
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 64px;
            color: var(--apple-blue);
            margin-bottom: 16px;
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
        }

        /* ========== FILE ATTACHMENT PANEL ========== */
        .attachment-panel {
            background: white;
            border-radius: 16px;
            padding: 32px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            height: fit-content;
            position: sticky;
            top: 220px;
        }

        .attachment-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .attachment-header .material-icons {
            font-size: 28px;
            color: var(--apple-blue);
        }

        .attachment-header-text h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 4px;
        }

        .attachment-header-text p {
            font-size: 13px;
            color: var(--apple-gray);
        }

        .attachment-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            background: var(--apple-bg);
            padding: 4px;
            border-radius: 10px;
        }

        .attachment-tab {
            flex: 1;
            padding: 10px;
            background: transparent;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--apple-gray);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .attachment-tab:hover {
            color: #1c1c1e;
        }

        .attachment-tab.active {
            background: white;
            color: var(--apple-blue);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        .attachment-tab .material-icons {
            font-size: 18px;
        }

        .attachment-content {
            display: none;
        }

        .attachment-content.active {
            display: block;
        }

        /* Drive Files List */
        .drive-files-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 16px;
        }

        .drive-file-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .drive-file-item:hover {
            border-color: var(--apple-blue);
            background: #F0F7FF;
        }

        .drive-file-item.selected {
            border-color: var(--apple-blue);
            background: #E5F2FF;
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.1);
        }

        .drive-file-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--apple-bg);
            border-radius: 8px;
            margin-right: 12px;
            font-size: 20px;
        }

        .drive-file-info {
            flex: 1;
            min-width: 0;
        }

        .drive-file-name {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .drive-file-size {
            font-size: 12px;
            color: var(--apple-gray);
            margin-top: 2px;
        }

        .drive-file-check {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .drive-file-item.selected .drive-file-check {
            background: var(--apple-blue);
            border-color: var(--apple-blue);
        }

        .drive-file-item.selected .drive-file-check .material-icons {
            color: white;
            font-size: 16px;
        }

        /* Upload Zone Small */
        .upload-zone-small {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--apple-bg);
        }

        .upload-zone-small:hover {
            border-color: var(--apple-blue);
            background: #F0F7FF;
        }

        .upload-zone-small .material-icons {
            font-size: 48px;
            color: var(--apple-blue);
            margin-bottom: 12px;
        }

        .upload-zone-small p {
            font-size: 14px;
            color: var(--apple-gray);
            margin-bottom: 4px;
        }

        .upload-zone-small small {
            font-size: 12px;
            color: var(--apple-gray);
        }

        /* Selected Attachment Display */
        .selected-attachment {
            background: #E5F2FF;
            border: 2px solid var(--apple-blue);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            display: none;
        }

        .selected-attachment.active {
            display: block;
        }

        .selected-attachment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .selected-attachment-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .clear-attachment {
            background: transparent;
            border: none;
            color: var(--apple-red);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }

        .clear-attachment:hover {
            background: rgba(255, 59, 48, 0.1);
        }

        .selected-attachment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .selected-attachment-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 10px;
            font-size: 24px;
        }

        .selected-attachment-details {
            flex: 1;
        }

        .selected-attachment-name {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 4px;
        }

        .selected-attachment-size {
            font-size: 12px;
            color: var(--apple-gray);
        }

        /* ========== ANALYSIS RESULTS ========== */
        .analysis-results {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-top: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            display: none;
        }

        .analysis-results.active {
            display: block;
        }

        .analysis-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .analysis-info h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 6px;
        }

        .analysis-info p {
            font-size: 14px;
            color: var(--apple-gray);
        }

        .analysis-stats {
            display: flex;
            gap: 16px;
        }

        .analysis-stat {
            text-align: center;
            padding: 12px 16px;
            background: var(--apple-bg);
            border-radius: 10px;
        }

        .analysis-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--apple-blue);
            margin-bottom: 4px;
        }

        .analysis-stat-label {
            font-size: 11px;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Column Mapping */
        .column-mapping {
            margin-bottom: 32px;
        }

        .mapping-header {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 16px;
        }

        .mapping-grid {
            display: grid;
            gap: 12px;
        }

        .mapping-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 12px;
            align-items: center;
        }

        .mapping-label {
            font-size: 13px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .mapping-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            color: #1c1c1e;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .mapping-select:hover {
            border-color: var(--apple-blue);
        }

        .mapping-select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        /* Preview Table */
        .preview-section {
            margin-top: 32px;
        }

        .preview-header {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 16px;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .preview-table th {
            background: var(--apple-bg);
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preview-table td {
            padding: 12px;
            font-size: 13px;
            color: #1c1c1e;
            border-top: 1px solid var(--border);
        }

        .preview-table tr:hover {
            background: var(--apple-bg);
        }

        /* Action Buttons */
        .analysis-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #1c1c1e;
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--apple-bg);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn .material-icons {
            font-size: 18px;
        }

        /* ========== QUEUE TABLE ========== */
        .queue-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        .queue-table th {
            background: var(--apple-bg);
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .queue-table td {
            padding: 16px;
            font-size: 13px;
            color: #1c1c1e;
            border-top: 1px solid var(--border);
        }

        .queue-table tr:hover {
            background: var(--apple-bg);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #FFF4E5;
            color: var(--apple-orange);
        }

        .status-completed {
            background: #E5F8ED;
            color: var(--apple-green);
        }

        .status-failed {
            background: #FFE5E5;
            color: var(--apple-red);
        }

        /* ========== QUEUE CONTROLS ========== */
        .queue-controls {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .queue-controls .btn {
            flex: 1;
        }

        /* ========== PROGRESS BAR ========== */
        .processing-progress {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            display: none;
        }

        .processing-progress.active {
            display: block;
        }

        .progress-bar {
            height: 8px;
            background: var(--apple-bg);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-fill {
            height: 100%;
            background: var(--apple-blue);
            width: 0%;
            transition: width 0.3s;
        }

        .progress-text {
            font-size: 14px;
            color: var(--apple-gray);
            text-align: center;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
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

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
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
            background: #E5F8ED;
            color: var(--apple-green);
        }

        .alert-error {
            background: #FFE5E5;
            color: var(--apple-red);
        }

        .alert-info {
            background: #E5F2FF;
            color: var(--apple-blue);
        }

        .alert .material-icons {
            font-size: 24px;
        }

        /* ========== LOADING SPINNER ========== */
        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 3px solid var(--border);
            border-top: 3px solid var(--apple-blue);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .compose-layout {
                grid-template-columns: 1fr;
            }

            .attachment-panel {
                position: static;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- ========== PAGE HEADER ========== -->
        <div class="page-header">
            <div class="header-container">
                <div class="header-left">
                    <h1>📧 Mailmerge</h1>
                    <p>Upload CSV and send personalized emails to multiple recipients</p>
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
                <button class="tab active" onclick="switchTab('upload')">
                    <span class="material-icons">upload_file</span>
                    Upload CSV
                </button>
                <button class="tab" onclick="switchTab('queue')">
                    <span class="material-icons">list</span>
                    Queue Management
                </button>
            </div>
        </div>

        <!-- ========== CONTENT AREA ========== -->
        <div class="content-area">
            <div class="container">
                
                <!-- ========== UPLOAD TAB ========== -->
                <div id="uploadTab" class="tab-content active">
                    <div class="compose-layout">
                        <!-- LEFT: CSV Upload -->
                        <div>
                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <h2 class="card-title">Upload CSV File</h2>
                                        <p class="card-subtitle">Select a CSV file containing recipient details</p>
                                    </div>
                                </div>

                                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFileInput').click()">
                                    <div class="material-icons upload-icon">cloud_upload</div>
                                    <h3 class="upload-title">Drop CSV file here or click to browse</h3>
                                    <p class="upload-subtitle">Maximum file size: 10MB</p>
                                    <input type="file" id="csvFileInput" accept=".csv" style="display: none;" onchange="handleCSVUpload(event)">
                                </div>
                            </div>

                            <!-- Analysis Results -->
                            <div id="analysisResults" class="analysis-results">
                                <!-- Will be populated dynamically -->
                            </div>
                        </div>

                        <!-- RIGHT: File Attachment Panel -->
                        <div class="attachment-panel">
                            <div class="attachment-header">
                                <span class="material-icons">attach_file</span>
                                <div class="attachment-header-text">
                                    <h3>Attach File</h3>
                                    <p>Optional: Add attachment to all emails</p>
                                </div>
                            </div>

                            <!-- Attachment Tabs -->
                            <div class="attachment-tabs">
                                <button class="attachment-tab active" onclick="switchAttachmentTab('drive')">
                                    <span class="material-icons">folder</span>
                                    From Drive
                                </button>
                                <button class="attachment-tab" onclick="switchAttachmentTab('upload')">
                                    <span class="material-icons">upload</span>
                                    Upload New
                                </button>
                            </div>

                            <!-- Drive Tab Content -->
                            <div id="driveAttachmentTab" class="attachment-content active">
                                <div class="loading">
                                    <div class="spinner"></div>
                                    <p style="color: var(--apple-gray); font-size: 14px;">Loading drive files...</p>
                                </div>
                                <div id="driveFilesList" class="drive-files-list" style="display: none;">
                                    <!-- Drive files will be loaded here -->
                                </div>
                            </div>

                            <!-- Upload Tab Content -->
                            <div id="uploadAttachmentTab" class="attachment-content">
                                <div class="upload-zone-small" onclick="document.getElementById('attachmentFileInput').click()">
                                    <div class="material-icons">cloud_upload</div>
                                    <p><strong>Click to upload file</strong></p>
                                    <small>Max size: 25MB</small>
                                    <input type="file" id="attachmentFileInput" style="display: none;" onchange="handleAttachmentUpload(event)">
                                </div>
                            </div>

                            <!-- Selected Attachment Display -->
                            <div id="selectedAttachment" class="selected-attachment">
                                <div class="selected-attachment-header">
                                    <span class="selected-attachment-title">Selected File</span>
                                    <button class="clear-attachment" onclick="clearSelectedAttachment()">
                                        <span class="material-icons">close</span>
                                    </button>
                                </div>
                                <div class="selected-attachment-info">
                                    <div class="selected-attachment-icon" id="selectedAttachmentIcon">
                                        📎
                                    </div>
                                    <div class="selected-attachment-details">
                                        <div class="selected-attachment-name" id="selectedAttachmentName">
                                            File name
                                        </div>
                                        <div class="selected-attachment-size" id="selectedAttachmentSize">
                                            0 KB
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== QUEUE TAB ========== -->
                <div id="queueTab" class="tab-content">
                    <!-- Queue Controls -->
                    <div class="queue-controls">
                        <button class="btn btn-primary" id="processQueueBtn" onclick="processQueue()">
                            <span class="material-icons">send</span>
                            Process Queue
                        </button>
                        <button class="btn btn-secondary" onclick="loadQueue()">
                            <span class="material-icons">refresh</span>
                            Refresh
                        </button>
                        <button class="btn btn-secondary" onclick="clearQueue()">
                            <span class="material-icons">delete_sweep</span>
                            Clear Pending
                        </button>
                    </div>

                    <!-- Processing Progress -->
                    <div id="processingProgress" class="processing-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <p class="progress-text" id="progressText">Processing emails...</p>
                    </div>

                    <!-- Queue Table -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Email Queue</h2>
                        </div>
                        <div id="queueTableContainer">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p style="color: var(--apple-gray);">Loading queue...</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentCSVData = null;
        let currentMapping = {};
        let selectedAttachmentPath = null;

        // ========== TAB SWITCHING ==========
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.closest('.tab').classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            if (tabName === 'upload') {
                document.getElementById('uploadTab').classList.add('active');
            } else if (tabName === 'queue') {
                document.getElementById('queueTab').classList.add('active');
                loadQueue();
            }
        }

        function switchAttachmentTab(tabName) {
            // Update attachment tab buttons
            document.querySelectorAll('.attachment-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.closest('.attachment-tab').classList.add('active');

            // Update attachment tab content
            document.querySelectorAll('.attachment-content').forEach(content => {
                content.classList.remove('active');
            });

            if (tabName === 'drive') {
                document.getElementById('driveAttachmentTab').classList.add('active');
            } else if (tabName === 'upload') {
                document.getElementById('uploadAttachmentTab').classList.add('active');
            }
        }

        // ========== DRIVE FILES ==========
        async function loadDriveFiles() {
            try {
                const response = await fetch('bulk_mail_backend.php?action=list_drive_files');
                const data = await response.json();

                const container = document.getElementById('driveFilesList');
                const loading = document.querySelector('#driveAttachmentTab .loading');

                if (data.success && data.files && data.files.length > 0) {
                    loading.style.display = 'none';
                    container.style.display = 'block';
                    
                    container.innerHTML = data.files.map(file => `
                        <div class="drive-file-item" onclick="selectDriveFile('${file.path}', '${file.name}', '${file.formatted_size}', '${file.extension}')">
                            <div class="drive-file-icon">${getFileIcon(file.extension)}</div>
                            <div class="drive-file-info">
                                <div class="drive-file-name">${file.name}</div>
                                <div class="drive-file-size">${file.formatted_size}</div>
                            </div>
                            <div class="drive-file-check">
                                <span class="material-icons" style="display: none;">check</span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    loading.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">📁</div>
                            <h3>No files in drive</h3>
                            <p>Upload files to /SXCMDTSHR/File_Drive</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading drive files:', error);
                showAlert('error', 'Failed to load drive files');
            }
        }

        function selectDriveFile(path, name, size, extension) {
            // Remove selected class from all items
            document.querySelectorAll('.drive-file-item').forEach(item => {
                item.classList.remove('selected');
                item.querySelector('.material-icons').style.display = 'none';
            });

            // Add selected class to clicked item
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('.material-icons').style.display = 'block';

            // Update selected attachment display
            selectedAttachmentPath = path;
            document.getElementById('selectedAttachmentIcon').textContent = getFileIcon(extension);
            document.getElementById('selectedAttachmentName').textContent = name;
            document.getElementById('selectedAttachmentSize').textContent = size;
            document.getElementById('selectedAttachment').classList.add('active');
        }

        function clearSelectedAttachment() {
            selectedAttachmentPath = null;
            document.getElementById('selectedAttachment').classList.remove('active');
            
            // Clear selection in drive files
            document.querySelectorAll('.drive-file-item').forEach(item => {
                item.classList.remove('selected');
                item.querySelector('.material-icons').style.display = 'none';
            });
        }

        function getFileIcon(extension) {
            const icons = {
                'pdf': '📄',
                'doc': '📝',
                'docx': '📝',
                'txt': '📝',
                'xls': '📊',
                'xlsx': '📊',
                'csv': '📊',
                'ppt': '📽️',
                'pptx': '📽️',
                'jpg': '🖼️',
                'jpeg': '🖼️',
                'png': '🖼️',
                'gif': '🖼️',
                'zip': '🗜️',
                'rar': '🗜️',
                '7z': '🗜️',
                'mp4': '🎥',
                'avi': '🎥',
                'mp3': '🎵',
                'wav': '🎵'
            };
            return icons[extension] || '📎';
        }

        // ========== ATTACHMENT UPLOAD ==========
        async function handleAttachmentUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Check file size (25MB limit)
            if (file.size > 25 * 1024 * 1024) {
                showAlert('error', 'File size exceeds 25MB limit');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('files[]', file);

                const response = await fetch('upload_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.files && data.files.length > 0) {
                    const uploadedFile = data.files[0];
                    selectedAttachmentPath = uploadedFile.path;
                    
                    const extension = file.name.split('.').pop().toLowerCase();
                    document.getElementById('selectedAttachmentIcon').textContent = getFileIcon(extension);
                    document.getElementById('selectedAttachmentName').textContent = file.name;
                    document.getElementById('selectedAttachmentSize').textContent = formatBytes(file.size);
                    document.getElementById('selectedAttachment').classList.add('active');
                    
                    showAlert('success', 'File uploaded successfully');
                } else {
                    showAlert('error', data.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showAlert('error', 'Failed to upload file');
            }

            // Reset input
            event.target.value = '';
        }

        // ========== CSV UPLOAD AND ANALYSIS ==========
        async function handleCSVUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            try {
                const formData = new FormData();
                formData.append('csv_file', file);

                const response = await fetch('bulk_mail_backend.php?action=analyze', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    displayAnalysisResults(data);
                } else {
                    showAlert('error', data.error || 'Failed to analyze CSV');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to upload CSV: ' + error.message);
            }

            // Reset input
            event.target.value = '';
        }

        function displayAnalysisResults(data) {
            const container = document.getElementById('analysisResults');
            
            // Initialize mapping with suggestions
            currentMapping = data.suggested_mapping || {};
            
            const mappingOptions = `
                <option value="">-- Skip this field --</option>
                <option value="recipient_email">Recipient Email</option>
                <option value="recipient_name">Recipient Name</option>
                <option value="subject">Email Subject</option>
                <option value="article_title">Article Title</option>
                <option value="message_content">Message Content</option>
                <option value="closing_wish">Closing Wish</option>
                <option value="sender_name">Sender Name</option>
                <option value="sender_designation">Sender Designation</option>
            `;

            container.innerHTML = `
                <div class="analysis-header">
                    <div class="analysis-info">
                        <h3>CSV Analysis Complete</h3>
                        <p>${data.filename}</p>
                    </div>
                    <div class="analysis-stats">
                        <div class="analysis-stat">
                            <div class="analysis-stat-value">${data.total_rows}</div>
                            <div class="analysis-stat-label">Total Rows</div>
                        </div>
                        <div class="analysis-stat">
                            <div class="analysis-stat-value">${data.csv_columns.length}</div>
                            <div class="analysis-stat-label">Columns</div>
                        </div>
                    </div>
                </div>

                <div class="column-mapping">
                    <h4 class="mapping-header">Map CSV Columns to Email Fields</h4>
                    <div class="mapping-grid">
                        ${data.csv_columns.map(column => `
                            <div class="mapping-row">
                                <label class="mapping-label">${column}</label>
                                <select class="mapping-select" data-column="${column}" onchange="updateMapping('${column}', this.value)">
                                    ${mappingOptions.split('\n').map(opt => {
                                        const value = opt.match(/value="([^"]*)"/)?.[1] || '';
                                        const isSelected = currentMapping[column] === value;
                                        return opt.replace('<option', `<option ${isSelected ? 'selected' : ''}`);
                                    }).join('')}
                                </select>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div class="preview-section">
                    <h4 class="preview-header">Preview (First 5 rows)</h4>
                    <table class="preview-table">
                        <thead>
                            <tr>
                                ${data.csv_columns.map(col => `<th>${col}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.preview_rows.map(row => `
                                <tr>
                                    ${data.csv_columns.map(col => `<td>${row[col] || ''}</td>`).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>

                <div class="analysis-actions">
                    <button class="btn btn-secondary" onclick="cancelAnalysis()">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button class="btn btn-primary" onclick="addToQueue()">
                        <span class="material-icons">add_circle</span>
                        Add to Queue
                    </button>
                </div>
            `;

            container.classList.add('active');
        }

        function updateMapping(column, value) {
            if (value) {
                currentMapping[column] = value;
            } else {
                delete currentMapping[column];
            }
        }

        function cancelAnalysis() {
            document.getElementById('analysisResults').classList.remove('active');
            currentMapping = {};
        }

        async function addToQueue() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons">hourglass_empty</span> Processing...';

            try {
                // Get full CSV data
                const parseResponse = await fetch('bulk_mail_backend.php?action=parse_full_csv');
                const parseData = await parseResponse.json();

                if (!parseData.success) {
                    throw new Error(parseData.error || 'Failed to parse CSV');
                }

                // Map CSV rows to email format
                const emails = parseData.rows.map(row => {
                    const email = {};
                    for (const [csvColumn, emailField] of Object.entries(currentMapping)) {
                        email[emailField] = row[csvColumn] || '';
                    }
                    return email;
                });

                // Add to queue with attachment path
                const addResponse = await fetch('bulk_mail_backend.php?action=add_to_queue', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        emails: emails,
                        drive_file_path: selectedAttachmentPath
                    })
                });

                const data = await addResponse.json();

                if (data.success) {
                    showAlert('success', `Successfully added ${data.added} emails to queue`);
                    cancelAnalysis();
                    clearSelectedAttachment();
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
                btn.innerHTML = '<span class="material-icons">add_circle</span> Add to Queue';
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
                                            <td>${item.subject || 'No Subject'}</td>
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

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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

        // Drag and drop for CSV upload
        const uploadZone = document.getElementById('uploadZone');
        
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
                document.getElementById('csvFileInput').files = files;
                handleCSVUpload({ target: { files: files } });
            }
        });

        // ========== INITIALIZATION ==========
        document.addEventListener('DOMContentLoaded', function() {
            loadQueue();
            loadDriveFiles();
        });
    </script>
</body>

</html>