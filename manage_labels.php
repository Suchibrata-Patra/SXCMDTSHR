<?php
// manage_labels.php - Label Management Interface
// Labels are global/shared across all users
session_start();

if (file_exists('config.php')) {
    require 'config.php';
}

require 'db_config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $labelName = trim($_POST['label_name'] ?? '');
        $labelColor = $_POST['label_color'] ?? '#0973dc';
        
        if (empty($labelName)) {
            echo json_encode(['success' => false, 'message' => 'Label name is required']);
            exit;
        }
        
        $result = createLabel($userEmail, $labelName, $labelColor);
        
        if (is_array($result) && isset($result['error'])) {
            echo json_encode(['success' => false, 'message' => $result['error']]);
        } elseif ($result) {
            echo json_encode(['success' => true, 'message' => 'Label created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create label']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Get all labels with counts for this user
$labels = getLabelCounts($userEmail);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SXC MDTS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --primary-white: #ffffff;
            --background-gray: #f8f9fa;
            --border-light: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --accent-primary: #0d6efd;
            --accent-success: #34a853;
            --accent-danger: #dc3545;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--background-gray);
            color: var(--text-primary);
            display: flex;
            height: 100vh;
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .page-subtitle {
            font-size: 16px;
            color: var(--text-secondary);
        }

        .content-card {
            background: var(--primary-white);
            border-radius: 12px;
            padding: 32px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-light);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--accent-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #0a58ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
        }

        .btn-secondary {
            background: var(--background-gray);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--border-light);
        }

        /* Labels Table */
        .labels-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .labels-table thead th {
            text-align: left;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-light);
        }

        .labels-table tbody tr {
            background: #ffffff;
            border: 1px solid var(--border-light);
            transition: all 0.2s;
        }

        .labels-table tbody tr:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .labels-table tbody td {
            padding: 16px;
        }

        .label-display {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .label-color-box {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .main_label_names {
            font-weight: 600;
            font-size: 15px;
        }

        .label-count {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn-icon {
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background: var(--background-gray);
        }

        .btn-icon .material-icons {
            font-size: 20px;
            color: var(--text-secondary);
        }

        .btn-icon:hover .material-icons {
            color: var(--text-primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state .material-icons {
            font-size: 64px;
            color: var(--border-light);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid var(--border-light);
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px 24px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        /* Color Picker */
        .color-picker-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .color-picker {
            width: 60px;
            height: 40px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            cursor: pointer;
        }

        .color-hex {
            font-family: monospace;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .color-presets {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .color-preset {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .color-preset:hover {
            transform: scale(1.1);
            border-color: var(--text-primary);
        }

        /* Alert */
        #alertContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .alert {
            background: white;
            border-radius: 8px;
            padding: 16px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
        }

        .alert-success {
            border-left: 4px solid var(--accent-success);
        }

        .alert-success .material-icons {
            color: var(--accent-success);
        }

        .alert-error {
            border-left: 4px solid var(--accent-danger);
        }

        .alert-error .material-icons {
            color: var(--accent-danger);
        }
    </style>
</head>
<body>
    <?php include ('sidebar.php') ?>
    <div id="alertContainer"></div>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Manage Labels</h1>
            <p class="page-subtitle">Create and organize labels to categorize your emails</p>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">All Labels</h2>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <span class="material-icons" style="font-size: 18px;">add</span>
                    Create Label
                </button>
            </div>

            <?php if (empty($labels)): ?>
                <div class="empty-state">
                    <span class="material-icons">label_off</span>
                    <h3>No labels yet</h3>
                    <p>Create your first label to start organizing your emails</p>
                </div>
            <?php else: ?>
                <table class="labels-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Your Emails</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($labels as $label): ?>
                        <tr>
                            <td>
                                <div class="label-display">
                                    <div class="label-color-box" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;"></div>
                                    <span class="main_label_names"><?= htmlspecialchars($label['label_name']) ?></span>
                                </div>
                            </td>
                            <td>
    <span class="label-count">
        <?= htmlspecialchars($label['email_count'] ?? '0') ?> emails
    </span>
</td>
<td>
    <span class="label-count">
        <?= !empty($label['created_at']) ? date('M j, Y', strtotime($label['created_at'])) : 'N/A' ?>
    </span>
</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div id="labelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Create Label</h2>
            </div>
            
            <form id="labelForm">
                <div class="modal-body">
                    <input type="hidden" id="labelId" name="label_id">
                    <input type="hidden" id="formAction" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label" for="labelName">Label Name</label>
                        <input type="text" class="form-input" id="labelName" name="label_name" placeholder="e.g., Work, Personal, Urgent" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Label Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" class="color-picker" id="labelColor" name="label_color" value="#0973dc">
                            <span class="color-hex" id="colorHex">#0973dc</span>
                        </div>
                        <div class="color-presets">
                            <div class="color-preset" style="background: #0973dc;" onclick="setColor('#0973dc')"></div>
                            <div class="color-preset" style="background: #34a853;" onclick="setColor('#34a853')"></div>
                            <div class="color-preset" style="background: #ea4335;" onclick="setColor('#ea4335')"></div>
                            <div class="color-preset" style="background: #fbbc04;" onclick="setColor('#fbbc04')"></div>
                            <div class="color-preset" style="background: #5f6368;" onclick="setColor('#5f6368')"></div>
                            <div class="color-preset" style="background: #8e24aa;" onclick="setColor('#8e24aa')"></div>
                            <div class="color-preset" style="background: #ff6d00;" onclick="setColor('#ff6d00')"></div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Label</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const colorPicker = document.getElementById('labelColor');
        const colorHex = document.getElementById('colorHex');
        
        colorPicker.addEventListener('input', (e) => {
            colorHex.textContent = e.target.value;
        });
        
        function setColor(color) {
            colorPicker.value = color;
            colorHex.textContent = color;
        }
        
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create Label';
            document.getElementById('formAction').value = 'create';
            document.getElementById('labelId').value = '';
            document.getElementById('labelName').value = '';
            document.getElementById('labelColor').value = '#0973dc';
            colorHex.textContent = '#0973dc';
            document.getElementById('labelModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('labelModal').classList.remove('active');
        }
        
        document.getElementById('labelModal').addEventListener('click', (e) => {
            if (e.target.id === 'labelModal') {
                closeModal();
            }
        });
        
        document.getElementById('labelForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('ajax', '1');
            
            try {
                const response = await fetch('manage_labels.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'error');
            }
        });
        
        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <span class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</span>
                <span>${message}</span>
            `;
            container.appendChild(alert);
            
            setTimeout(() => alert.remove(), 5000);
        }
    </script>
</body>
</html>