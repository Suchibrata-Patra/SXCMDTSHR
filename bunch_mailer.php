<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Email Campaign Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
            margin-top: 4px;
        }
        
        .user-info {
            text-align: right;
            opacity: 0.95;
        }
        
        .user-email {
            font-size: 13px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
        }
        
        .tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: #666;
        }
        
        .tab:hover {
            background: #fff;
            color: #667eea;
        }
        
        .tab.active {
            background: white;
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
            animation: fadeIn 0.3s ease-in;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .upload-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .upload-zone {
            border: 3px dashed #ccc;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .upload-zone:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .upload-zone.dragover {
            border-color: #667eea;
            background: #e3e9ff;
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: #667eea;
        }
        
        .file-input {
            display: none;
        }
        
        .mapping-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .mapping-modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 1400px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .mapping-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
        }
        
        .column-list {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
        }
        
        .column-list h3 {
            font-size: 16px;
            margin-bottom: 16px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .column-item {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.2s;
        }
        
        .column-item:hover {
            border-color: #667eea;
            transform: translateX(4px);
        }
        
        .column-item.dragging {
            opacity: 0.5;
        }
        
        .column-name {
            font-weight: 500;
            color: #333;
        }
        
        .column-samples {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .expected-field {
            background: white;
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
            min-height: 60px;
            transition: all 0.2s;
        }
        
        .expected-field.required::before {
            content: '* ';
            color: #f44336;
            font-weight: bold;
        }
        
        .expected-field.dragover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .expected-field.mapped {
            border-style: solid;
            border-color: #4caf50;
            background: #f1f8f4;
        }
        
        .field-label {
            font-weight: 600;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .field-description {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .mapped-column {
            background: #667eea;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        
        .remove-mapping {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
        }
        
        .badge {
            background: #ff9800;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge.required {
            background: #f44336;
        }
        
        .badge.optional {
            background: #9e9e9e;
        }
        
        .badge.email {
            background: #2196f3;
        }
        
        .queue-section {
            margin-top: 30px;
        }
        
        .queue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .queue-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .queue-table th {
            background: #f8f9fa;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .queue-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .queue-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-processing {
            background: #e3f2fd;
            color: #0d47a1;
        }
        
        .status-completed {
            background: #e8f5e9;
            color: #1b5e20;
        }
        
        .status-failed {
            background: #ffebee;
            color: #c62828;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover:not(:disabled) {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-success {
            background: #4caf50;
        }
        
        .btn-danger {
            background: #f44336;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            color: #0d47a1;
        }
        
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            color: #1b5e20;
        }
        
        .alert-warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            color: #e65100;
        }
        
        .alert-error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }
        
        .progress-container {
            margin-top: 20px;
            display: none;
        }
        
        .progress-container.active {
            display: block;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
            width: 0%;
        }
        
        .progress-text {
            font-size: 13px;
            color: #666;
            text-align: center;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        @media (max-width: 768px) {
            .mapping-container {
                grid-template-columns: 1fr;
            }
            
            .queue-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📧 Bulk Email Campaign Manager</h1>
                <p>Upload CSV, map columns, and send bulk emails with ease</p>
            </div>
            <div class="user-info">
                <div class="user-email" id="userEmail">Loading...</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('upload')">
                📁 Upload CSV
            </div>
            <div class="tab" onclick="switchTab('queue')">
                📋 Email Queue
            </div>
            <div class="tab" onclick="switchTab('history')">
                📊 Campaign History
            </div>
        </div>
        
        <!-- Upload Tab -->
        <div class="tab-content active" id="upload-tab">
            <div class="upload-section">
                <h2 style="margin-bottom: 20px;">Upload CSV File</h2>
                
                <div class="alert alert-info">
                    <span>💡</span>
                    <div>
                        <strong>Smart Column Mapping:</strong>
                        <p style="margin-top: 4px;">Upload any CSV format - our system will automatically detect and map your columns! No need to match exact column names.</p>
                    </div>
                </div>
                
                <div class="upload-zone" id="uploadZone">
                    <div class="upload-icon">📁</div>
                    <h3>Drop your CSV file here</h3>
                    <p>or click to browse</p>
                    <p style="margin-top: 12px; font-size: 13px; color: #666;">Any CSV structure supported • Auto-detects columns • Smart mapping</p>
                    <input type="file" id="fileInput" class="file-input" accept=".csv">
                </div>
                
                <div id="uploadResult" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <!-- Queue Tab -->
        <div class="tab-content" id="queue-tab">
            <div class="queue-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Email Queue Status</h2>
                    <div class="button-group" style="margin: 0;">
                        <button class="btn btn-small" onclick="refreshQueue()">
                            🔄 Refresh
                        </button>
                        <button class="btn btn-success btn-small" onclick="processQueue()" id="processQueueBtn">
                            ▶️ Process Queue
                        </button>
                        <button class="btn btn-danger btn-small" onclick="clearQueue()">
                            🗑️ Clear Pending
                        </button>
                    </div>
                </div>
                
                <div class="queue-stats" id="queueStats">
                    <div class="stat-card">
                        <div class="stat-value" id="stat-pending">0</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);">
                        <div class="stat-value" id="stat-processing">0</div>
                        <div class="stat-label">Processing</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);">
                        <div class="stat-value" id="stat-completed">0</div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);">
                        <div class="stat-value" id="stat-failed">0</div>
                        <div class="stat-label">Failed</div>
                    </div>
                </div>
                
                <div class="progress-container" id="processingProgress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Processing emails...</div>
                </div>
                
                <div id="queueTableContainer">
                    <div class="loading">
                        <div class="spinner"></div>
                        Loading queue...
                    </div>
                </div>
            </div>
        </div>
        
        <!-- History Tab -->
        <div class="tab-content" id="history-tab">
            <div class="queue-section">
                <h2 style="margin-bottom: 20px;">Campaign History</h2>
                
                <div id="historyContainer">
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No campaigns yet</h3>
                        <p>Upload a CSV to start your first campaign</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Column Mapping Modal -->
    <div class="mapping-modal" id="mappingModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2>Map Your CSV Columns</h2>
                    <p style="margin-top: 4px; opacity: 0.9;">Drag columns from left to right to map them</p>
                </div>
                <button class="modal-close" onclick="closeMappingModal()">×</button>
            </div>
            
            <div class="modal-body">
                <div class="alert alert-info">
                    <span>💡</span>
                    <div>
                        <strong>Auto-Detection Active:</strong>
                        <p style="margin-top: 4px;">We've automatically mapped columns we recognized (shown in green). Review and adjust as needed.</p>
                    </div>
                </div>
                
                <div id="csvInfo" style="margin-bottom: 20px;"></div>
                
                <div class="mapping-container">
                    <div class="column-list">
                        <h3>
                            <span>📋</span> Your CSV Columns
                        </h3>
                        <div id="csvColumns"></div>
                    </div>
                    
                    <div class="column-list">
                        <h3>
                            <span>🎯</span> Expected Fields
                        </h3>
                        <div id="expectedFields"></div>
                    </div>
                </div>
                
                <div class="button-group" style="justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="closeMappingModal()">
                        Cancel
                    </button>
                    <button class="btn btn-success" onclick="processWithMapping()" id="processMappingBtn">
                        ✓ Process CSV
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        let currentFile = null;
        let csvData = null;
        let columnMapping = {};
        let expectedFields = {};
        let draggedElement = null;
        let queueRefreshInterval = null;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', async () => {
            await checkSession();
            await fetchExpectedFields();
            initializeUpload();
            loadQueue();
            
            // Auto-refresh queue every 5 seconds when on queue tab
            queueRefreshInterval = setInterval(() => {
                if (document.getElementById('queue-tab').classList.contains('active')) {
                    loadQueue();
                }
            }, 5000);
        });
        
        // Check if user is logged in
        async function checkSession() {
            try {
                const response = await fetch('process_bulk_mail.php?action=test');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('userEmail').textContent = data.user_email || 'User';
                } else {
                    showAlert('error', 'Session expired. Please login again.');
                    // Redirect to login page
                    setTimeout(() => window.location.href = 'index.html', 2000);
                }
            } catch (error) {
                console.error('Session check failed:', error);
            }
        }
        
        // Fetch expected fields configuration
        async function fetchExpectedFields() {
            try {
                const response = await fetch('bulk_mail_backend.php?action=get_expected_fields');
                const data = await response.json();
                
                if (data.success) {
                    expectedFields = data.expected_fields;
                }
            } catch (error) {
                console.error('Error fetching expected fields:', error);
            }
        }
        
        // Initialize file upload
        function initializeUpload() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('fileInput');
            
            uploadZone.addEventListener('click', () => fileInput.click());
            
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
                
                if (e.dataTransfer.files.length) {
                    handleFileSelect(e.dataTransfer.files[0]);
                }
            });
            
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length) {
                    handleFileSelect(e.target.files[0]);
                }
            });
        }
        
        // Handle file selection
        async function handleFileSelect(file) {
            if (!file.name.endsWith('.csv')) {
                showAlert('error', 'Please select a CSV file');
                return;
            }
            
            currentFile = file;
            
            const resultDiv = document.getElementById('uploadResult');
            resultDiv.innerHTML = '<div class="loading"><div class="spinner"></div>Analyzing CSV file...</div>';
            
            const formData = new FormData();
            formData.append('csv_file', file);
            
            try {
                const response = await fetch('bulk_mail_backend.php?action=analyze', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    csvData = data;
                    columnMapping = data.suggested_mapping || {};
                    
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <span>✅</span>
                            <div>
                                <strong>CSV Analyzed Successfully!</strong>
                                <p style="margin-top: 8px;">
                                    File: <strong>${file.name}</strong><br>
                                    Rows: <strong>${data.total_rows}</strong> | 
                                    Columns: <strong>${data.csv_columns.length}</strong> | 
                                    Auto-mapped: <strong>${Object.keys(columnMapping).length}</strong>
                                </p>
                                <button class="btn" onclick="openMappingModal()" style="margin-top: 12px;">
                                    Next: Map Columns →
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    throw new Error(data.error || 'Failed to analyze CSV');
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <span>❌</span>
                        <div>
                            <strong>Error:</strong>
                            <p style="margin-top: 4px;">${error.message}</p>
                        </div>
                    </div>
                `;
            }
        }
        
        // Open mapping modal
        function openMappingModal() {
            document.getElementById('mappingModal').classList.add('active');
            renderMappingInterface();
        }
        
        // Close mapping modal
        function closeMappingModal() {
            document.getElementById('mappingModal').classList.remove('active');
        }
        
        // Render mapping interface
        function renderMappingInterface() {
            // CSV Info
            document.getElementById('csvInfo').innerHTML = `
                <div style="display: flex; gap: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px;">
                    <div><strong>File:</strong> ${currentFile.name}</div>
                    <div><strong>Rows:</strong> ${csvData.total_rows}</div>
                    <div><strong>Columns:</strong> ${csvData.csv_columns.length}</div>
                    <div><strong>Auto-mapped:</strong> ${Object.keys(columnMapping).length}/${Object.keys(expectedFields).length}</div>
                </div>
            `;
            
            // CSV Columns
            const csvColumnsContainer = document.getElementById('csvColumns');
            csvColumnsContainer.innerHTML = '';
            
            csvData.csv_columns.forEach(column => {
                const isMapped = Object.values(columnMapping).includes(column);
                
                if (!isMapped) {
                    const analysis = csvData.column_analysis[column] || {};
                    const div = document.createElement('div');
                    div.className = 'column-item';
                    div.draggable = true;
                    div.dataset.column = column;
                    
                    const samples = analysis.sample_values || [];
                    
                    div.innerHTML = `
                        <div>
                            <div class="column-name">${column}</div>
                            ${samples.length > 0 ? `
                                <div class="column-samples">
                                    Sample: ${samples.slice(0, 2).join(', ')}
                                </div>
                            ` : ''}
                        </div>
                        ${analysis.likely_email ? '<span class="badge email">📧 Email</span>' : ''}
                    `;
                    
                    div.addEventListener('dragstart', handleDragStart);
                    div.addEventListener('dragend', handleDragEnd);
                    
                    csvColumnsContainer.appendChild(div);
                }
            });
            
            if (csvColumnsContainer.children.length === 0) {
                csvColumnsContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">All columns mapped!</div>';
            }
            
            // Expected Fields
            const expectedFieldsContainer = document.getElementById('expectedFields');
            expectedFieldsContainer.innerHTML = '';
            
            Object.entries(expectedFields).forEach(([fieldKey, fieldConfig]) => {
                const div = document.createElement('div');
                div.className = 'expected-field' + (fieldConfig.required ? ' required' : '');
                div.dataset.field = fieldKey;
                
                const mappedColumn = columnMapping[fieldKey];
                const autoMapped = csvData.suggested_mapping && csvData.suggested_mapping[fieldKey] === mappedColumn;
                
                if (mappedColumn && autoMapped) {
                    div.classList.add('mapped');
                }
                
                div.innerHTML = `
                    <div class="field-label">
                        <span>${fieldConfig.label}</span>
                        <span class="badge ${fieldConfig.required ? 'required' : 'optional'}">
                            ${fieldConfig.required ? 'Required' : 'Optional'}
                        </span>
                    </div>
                    <div class="field-description">${fieldConfig.description}</div>
                    ${mappedColumn ? `
                        <div class="mapped-column">
                            <span>📌 ${mappedColumn}</span>
                            <button class="remove-mapping" onclick="removeMapping('${fieldKey}')">×</button>
                        </div>
                    ` : '<div style="color: #999; font-size: 13px; font-style: italic;">Drop a column here</div>'}
                `;
                
                div.addEventListener('dragover', handleDragOver);
                div.addEventListener('dragleave', handleDragLeave);
                div.addEventListener('drop', handleDrop);
                
                expectedFieldsContainer.appendChild(div);
            });
            
            updateProcessButton();
        }
        
        // Drag and drop handlers
        function handleDragStart(e) {
            draggedElement = e.target.closest('.column-item');
            draggedElement.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
        
        function handleDragEnd(e) {
            if (draggedElement) {
                draggedElement.classList.remove('dragging');
            }
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            e.target.closest('.expected-field').classList.add('dragover');
            e.dataTransfer.dropEffect = 'move';
        }
        
        function handleDragLeave(e) {
            e.target.closest('.expected-field').classList.remove('dragover');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            
            const fieldDiv = e.target.closest('.expected-field');
            fieldDiv.classList.remove('dragover');
            
            if (!draggedElement) return;
            
            const column = draggedElement.dataset.column;
            const field = fieldDiv.dataset.field;
            
            columnMapping[field] = column;
            renderMappingInterface();
        }
        
        function removeMapping(fieldKey) {
            delete columnMapping[fieldKey];
            renderMappingInterface();
        }
        
        function updateProcessButton() {
            const btn = document.getElementById('processMappingBtn');
            
            let allRequiredMapped = true;
            Object.entries(expectedFields).forEach(([fieldKey, fieldConfig]) => {
                if (fieldConfig.required && !columnMapping[fieldKey]) {
                    allRequiredMapped = false;
                }
            });
            
            btn.disabled = !allRequiredMapped;
        }
        
        // Process CSV with mapping
        async function processWithMapping() {
            const btn = document.getElementById('processMappingBtn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Processing...';
            
            const formData = new FormData();
            formData.append('csv_file', currentFile);
            formData.append('mapping', JSON.stringify({ column_mapping: columnMapping }));
            
            try {
                const response = await fetch('bulk_mail_backend.php?action=process_with_mapping', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closeMappingModal();
                    
                    document.getElementById('uploadResult').innerHTML = `
                        <div class="alert alert-success">
                            <span>✅</span>
                            <div>
                                <strong>CSV Processed Successfully!</strong>
                                <p style="margin-top: 8px;">
                                    ${data.processed_count} emails added to queue<br>
                                    ${data.error_count > 0 ? `${data.error_count} rows had errors` : ''}
                                </p>
                                <button class="btn" onclick="switchTab('queue')" style="margin-top: 12px;">
                                    View Queue →
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Clear file input
                    document.getElementById('fileInput').value = '';
                    
                    // Refresh queue
                    loadQueue();
                } else {
                    throw new Error(data.error || 'Processing failed');
                }
            } catch (error) {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = '✓ Process CSV';
            }
        }
        
        // Load queue data
        async function loadQueue() {
            try {
                const response = await fetch('process_bulk_mail.php?action=status');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('stat-pending').textContent = data.pending || 0;
                    document.getElementById('stat-processing').textContent = data.processing || 0;
                    document.getElementById('stat-completed').textContent = data.completed || 0;
                    document.getElementById('stat-failed').textContent = data.failed || 0;
                    
                    await loadQueueList();
                }
            } catch (error) {
                console.error('Error loading queue:', error);
            }
        }
        
        // Load queue list
        async function loadQueueList() {
            const container = document.getElementById('queueTableContainer');
            
            try {
                const response = await fetch('process_bulk_mail.php?action=queue_list');
                const data = await response.json();
                
                if (data.success && data.queue.length > 0) {
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
                                                ${item.status.toUpperCase()}
                                            </span>
                                            ${item.error_message ? `<br><span style="font-size: 11px; color: #f44336;">${item.error_message}</span>` : ''}
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
                            <p>Upload a CSV file to add emails to the queue</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading queue list:', error);
            }
        }
        
        // Process queue
        async function processQueue() {
            const btn = document.getElementById('processQueueBtn');
            const progressContainer = document.getElementById('processingProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            
            btn.disabled = true;
            progressContainer.classList.add('active');
            
            let processed = 0;
            const total = parseInt(document.getElementById('stat-pending').textContent);
            
            if (total === 0) {
                alert('No pending emails to process');
                btn.disabled = false;
                progressContainer.classList.remove('active');
                return;
            }
            
            // Process emails one by one
            for (let i = 0; i < total; i++) {
                try {
                    const response = await fetch('process_bulk_mail.php?action=process', {
                        method: 'POST'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        processed++;
                        const percent = (processed / total) * 100;
                        progressFill.style.width = percent + '%';
                        progressText.textContent = `Processing ${processed}/${total} emails...`;
                        
                        await loadQueue();
                        
                        // Small delay between emails
                        await new Promise(resolve => setTimeout(resolve, 500));
                    } else {
                        console.error('Error processing email:', data.error);
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
        }
        
        // Clear queue
        async function clearQueue() {
            if (!confirm('Are you sure you want to clear all pending emails from the queue?')) {
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
        
        // Refresh queue
        function refreshQueue() {
            loadQueue();
            showAlert('success', 'Queue refreshed');
        }
        
        // Switch tabs
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Load data if needed
            if (tabName === 'queue') {
                loadQueue();
            }
        }
        
        // Helper functions
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString();
        }
        
        function showAlert(type, message) {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type}`;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '10000';
            toast.style.minWidth = '300px';
            toast.innerHTML = `
                <span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span>
                <div>${message}</div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>