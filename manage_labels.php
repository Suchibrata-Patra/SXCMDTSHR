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
        $labelColor = $_POST['label_color'] ?? '#5781a9';
        
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
    <?php
    define('PAGE_TITLE', 'SXC MDTS | Manage Labels');
    include 'header.php';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    
    <style>
        /* ══════════════════════════════════════════════════════════
           DRIVE UI DESIGN SYSTEM - Label Management
           ══════════════════════════════════════════════════════════ */
        
        :root {
            /* Foundation Colors */
            --ink:       #1a1a2e;
            --ink-2:     #2d2d44;
            --ink-3:     #6b6b8a;
            --ink-4:     #a8a8c0;
            --bg:        #f0f0f7;
            --surface:   #ffffff;
            --surface-2: #f7f7fc;
            --border:    rgba(100,100,160,0.12);
            --border-2:  rgba(100,100,160,0.22);
            
            /* Accent Colors */
            --blue:      #5781a9;
            --blue-2:    #c6d3ea;
            --blue-glow: rgba(79,70,229,0.15);
            --red:       #ef4444;
            --green:     #10b981;
            --amber:     #f59e0b;
            
            /* System */
            --r:         10px;
            --r-lg:      16px;
            --shadow:    0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg: 0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:      cubic-bezier(.4,0,.2,1);
            --ease-spring: cubic-bezier(.34,1.56,.64,1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
        }

        /* Page Header */
        .page-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 16px;
            color: var(--ink-3);
            font-weight: 400;
        }

        /* Content Card */
        .content-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 32px;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title .material-icons-round {
            font-size: 24px;
            color: var(--blue);
        }

        /* Buttons */
        .btn {
            height: 36px;
            padding: 0 16px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all .18s var(--ease);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn-primary:hover {
            background: #4a6b8f;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(87,129,169,0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-ghost {
            background: transparent;
            color: var(--ink-2);
            border: 1px solid var(--border-2);
        }

        .btn-ghost:hover {
            background: var(--surface-2);
            border-color: var(--ink-3);
        }

        .btn .material-icons-round {
            font-size: 18px;
        }

        /* Labels Table */
        .labels-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .labels-table thead th {
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--ink-3);
            border-bottom: 1px solid var(--border);
        }

        .labels-table tbody tr {
            transition: all .14s var(--ease);
            border-bottom: 1px solid var(--border);
        }

        .labels-table tbody tr:hover {
            background: var(--surface-2);
        }

        .labels-table tbody td {
            padding: 16px;
            vertical-align: middle;
        }

        .label-display {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .label-color-box {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1), inset 0 0 0 1px rgba(255,255,255,0.2);
            flex-shrink: 0;
        }

        .main_label_names {
            font-weight: 500;
            font-size: 14px;
            color: var(--ink);
        }

        .label-count {
            color: var(--ink-3);
            font-size: 13px;
            font-weight: 400;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state .material-icons-round {
            font-size: 72px;
            color: var(--ink-4);
            opacity: 0.5;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--ink-2);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--ink-3);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26,26,46,0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn .18s var(--ease);
        }

        .modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-content {
            background: var(--surface);
            border-radius: var(--r-lg);
            width: 90%;
            max-width: 480px;
            box-shadow: var(--shadow-lg);
            animation: slideUp .24s var(--ease-spring);
            border: 1px solid var(--border-2);
        }

        .modal-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--ink);
        }

        .modal-body {
            padding: 32px;
        }

        .modal-footer {
            padding: 20px 32px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-2);
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        .form-input {
            width: 100%;
            height: 40px;
            padding: 0 14px;
            border: 1px solid var(--border-2);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            background: var(--surface);
            transition: all .18s var(--ease);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
        }

        .form-input::placeholder {
            color: var(--ink-4);
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
            border: 1px solid var(--border-2);
            border-radius: 8px;
            cursor: pointer;
            transition: all .18s var(--ease);
        }

        .color-picker:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .color-hex {
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            color: var(--ink-3);
            font-weight: 500;
        }

        .color-presets {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .color-preset {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all .18s var(--ease-spring);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1), inset 0 0 0 1px rgba(255,255,255,0.2);
        }

        .color-preset:hover {
            transform: scale(1.15);
            border-color: var(--ink);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15), inset 0 0 0 1px rgba(255,255,255,0.3);
        }

        .color-preset:active {
            transform: scale(1.05);
        }

        /* Alert */
        #alertContainer {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .alert {
            background: var(--surface);
            border-radius: var(--r);
            padding: 16px 20px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-2);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 320px;
            animation: slideInRight .24s var(--ease-spring);
            pointer-events: all;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            border-left: 3px solid var(--green);
        }

        .alert-success .material-icons-round {
            color: var(--green);
        }

        .alert-error {
            border-left: 3px solid var(--red);
        }

        .alert-error .material-icons-round {
            color: var(--red);
        }

        .alert .material-icons-round {
            font-size: 22px;
        }

        .alert span:not(.material-icons-round) {
            font-size: 14px;
            color: var(--ink);
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .page-header,
            .content-card {
                padding: 24px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .labels-table {
                font-size: 13px;
            }

            .labels-table thead th,
            .labels-table tbody td {
                padding: 12px 8px;
            }

            .modal-content {
                width: 95%;
            }
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
                <h2 class="card-title">
                    <span class="material-icons-round">label</span>
                    All Labels
                </h2>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <span class="material-icons-round">add</span>
                    Create Label
                </button>
            </div>

            <?php if (empty($labels)): ?>
                <div class="empty-state">
                    <span class="material-icons-round">label_off</span>
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
                            <input type="color" class="color-picker" id="labelColor" name="label_color" value="#5781a9">
                            <span class="color-hex" id="colorHex">#5781a9</span>
                        </div>
                        <div class="color-presets">
                            <div class="color-preset" style="background: #5781a9;" onclick="setColor('#5781a9')" title="Blue"></div>
                            <div class="color-preset" style="background: #10b981;" onclick="setColor('#10b981')" title="Green"></div>
                            <div class="color-preset" style="background: #ef4444;" onclick="setColor('#ef4444')" title="Red"></div>
                            <div class="color-preset" style="background: #f59e0b;" onclick="setColor('#f59e0b')" title="Amber"></div>
                            <div class="color-preset" style="background: #8b5cf6;" onclick="setColor('#8b5cf6')" title="Purple"></div>
                            <div class="color-preset" style="background: #6b6b8a;" onclick="setColor('#6b6b8a')" title="Gray"></div>
                            <div class="color-preset" style="background: #ec4899;" onclick="setColor('#ec4899')" title="Pink"></div>
                            <div class="color-preset" style="background: #06b6d4;" onclick="setColor('#06b6d4')" title="Cyan"></div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
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
            document.getElementById('labelColor').value = '#5781a9';
            colorHex.textContent = '#5781a9';
            document.getElementById('labelModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('labelModal').classList.remove('active');
        }
        
        // Close modal on backdrop click
        document.getElementById('labelModal').addEventListener('click', (e) => {
            if (e.target.id === 'labelModal') {
                closeModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('labelModal').classList.contains('active')) {
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
                <span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error'}</span>
                <span>${message}</span>
            `;
            container.appendChild(alert);
            
            setTimeout(() => {
                alert.style.animation = 'slideInRight .24s var(--ease-spring) reverse';
                setTimeout(() => alert.remove(), 240);
            }, 4000);
        }
    </script>
</body>
</html>