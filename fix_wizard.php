<?php
/**
 * Automated Foreign Key Fix Wizard
 * Helps you choose and apply the right fix for your system
 */

session_start();
if (!isset($_SESSION['smtp_user'])) {
    die("Please log in first");
}

require_once 'db_config.php';

$step = $_GET['step'] ?? 'analyze';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foreign Key Fix Wizard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1c1c1e;
            margin-bottom: 10px;
        }
        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #721c24;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #155724;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #856404;
        }
        .solution {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .solution:hover {
            border-color: #007AFF;
            background: #f0f7ff;
        }
        .solution.selected {
            border-color: #007AFF;
            background: #e3f2fd;
        }
        .solution h3 {
            margin-top: 0;
            color: #007AFF;
        }
        .pros-cons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }
        .pros, .cons {
            padding: 10px;
            border-radius: 6px;
        }
        .pros {
            background: #d4edda;
        }
        .cons {
            background: #f8d7da;
        }
        button {
            background: #007AFF;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px 10px 10px 0;
        }
        button:hover {
            background: #0051D5;
        }
        button.secondary {
            background: #6c757d;
        }
        button.danger {
            background: #dc3545;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #007AFF;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-top: 5px;
        }
        code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .progress-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-bottom: 3px solid #e0e0e0;
            color: #999;
            font-weight: 600;
        }
        .progress-item.active {
            border-color: #007AFF;
            color: #007AFF;
        }
        .progress-item.completed {
            border-color: #28a745;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Foreign Key Fix Wizard</h1>
        
        <div class="progress">
            <div class="progress-item <?= $step === 'analyze' ? 'active' : 'completed' ?>">1. Analyze</div>
            <div class="progress-item <?= $step === 'choose' ? 'active' : ($step === 'apply' || $step === 'done' ? 'completed' : '') ?>">2. Choose Fix</div>
            <div class="progress-item <?= $step === 'apply' ? 'active' : ($step === 'done' ? 'completed' : '') ?>">3. Apply</div>
            <div class="progress-item <?= $step === 'done' ? 'active' : '' ?>">4. Done</div>
        </div>

        <?php if ($step === 'analyze'): ?>
        <div class="step active">
            <div class="error-box">
                <strong>Error Detected:</strong><br>
                SQLSTATE[23000]: Integrity constraint violation: 1452<br>
                Cannot add or update a child row: a foreign key constraint fails
            </div>

            <h2>System Analysis</h2>
            
            <?php
            try {
                $pdo = getDatabaseConnection();
                
                // Count records in each table
                $stmt = $pdo->query("SELECT COUNT(*) FROM sent_emails WHERE current_status = 1");
                $sentEmailsCount = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM emails WHERE email_type = 'sent'");
                $emailsCount = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM email_read_tracking");
                $trackingCount = $stmt->fetchColumn();
                
                // Check for foreign key
                $stmt = $pdo->query("
                    SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'email_read_tracking'
                    AND CONSTRAINT_NAME = 'fk_read_tracking_email'
                ");
                $hasForeignKey = $stmt->fetchColumn() ? true : false;
            ?>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $sentEmailsCount ?></div>
                    <div class="stat-label">Sent Emails (Legacy)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $emailsCount ?></div>
                    <div class="stat-label">Emails (New)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $trackingCount ?></div>
                    <div class="stat-label">Tracking Records</div>
                </div>
            </div>

            <div class="info-box">
                <strong>Diagnosis:</strong><br>
                • You have <strong><?= $sentEmailsCount ?></strong> emails in the legacy <code>sent_emails</code> table<br>
                • You have <strong><?= $emailsCount ?></strong> emails in the new <code>emails</code> table<br>
                • Foreign Key Status: <strong><?= $hasForeignKey ? 'ENABLED ⚠️' : 'DISABLED ✓' ?></strong><br><br>
                
                The tracking system requires records in the <code>emails</code> table, but your emails are in <code>sent_emails</code>.
                This causes the foreign key constraint to fail.
            </div>

            <?php
            } catch (Exception $e) {
                echo '<div class="error-box">Database connection error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>

            <button onclick="location.href='?step=choose'">Next: Choose Solution →</button>
        </div>

        <?php elseif ($step === 'choose'): ?>
        <div class="step active">
            <h2>Choose Your Fix</h2>
            
            <div class="solution" onclick="selectSolution(this, 1)">
                <h3>✅ Solution 1: Sync Tables (Recommended)</h3>
                <p>Creates corresponding records in <code>emails</code> table for all <code>sent_emails</code></p>
                
                <div class="pros-cons">
                    <div class="pros">
                        <strong>Pros:</strong>
                        <ul>
                            <li>Maintains data integrity</li>
                            <li>Foreign key stays intact</li>
                            <li>Future-proof</li>
                        </ul>
                    </div>
                    <div class="cons">
                        <strong>Cons:</strong>
                        <ul>
                            <li>Duplicates data</li>
                            <li>Need ongoing sync</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="solution" onclick="selectSolution(this, 2)">
                <h3>⚡ Solution 2: Remove Foreign Key (Quick Fix)</h3>
                <p>Removes the foreign key constraint to allow any <code>email_id</code></p>
                
                <div class="pros-cons">
                    <div class="pros">
                        <strong>Pros:</strong>
                        <ul>
                            <li>Quickest fix (5 min)</li>
                            <li>Works immediately</li>
                            <li>No data duplication</li>
                        </ul>
                    </div>
                    <div class="cons">
                        <strong>Cons:</strong>
                        <ul>
                            <li>Loses referential integrity</li>
                            <li>Can have orphaned records</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="solution" onclick="selectSolution(this, 3)">
                <h3>🔄 Solution 3: Dual-Table Support</h3>
                <p>Adds columns to track which table the email comes from</p>
                
                <div class="pros-cons">
                    <div class="pros">
                        <strong>Pros:</strong>
                        <ul>
                            <li>Clean architecture</li>
                            <li>Supports both systems</li>
                            <li>Easy to migrate later</li>
                        </ul>
                    </div>
                    <div class="cons">
                        <strong>Cons:</strong>
                        <ul>
                            <li>Requires schema change</li>
                            <li>More complex</li>
                        </ul>
                    </div>
                </div>
            </div>

            <input type="hidden" id="selectedSolution" value="">
            
            <br>
            <button class="secondary" onclick="location.href='?step=analyze'">← Back</button>
            <button onclick="applySolution()">Apply Selected Fix →</button>
        </div>

        <?php elseif ($step === 'apply'): ?>
        <div class="step active">
            <h2>Applying Fix...</h2>
            
            <?php
            $solution = $_GET['solution'] ?? 2; // Default to quick fix
            
            try {
                $pdo = getDatabaseConnection();
                $pdo->beginTransaction();
                
                if ($solution == 1) {
                    // Solution 1: Sync tables
                    echo '<div class="info-box">Running Solution 1: Syncing tables...</div>';
                    
                    // Create emails records
                    $pdo->exec("
                        INSERT IGNORE INTO emails (
                            email_uuid, tracking_token, message_id, sender_email, recipient_email,
                            subject, body_html, email_type, email_date, sent_at, created_at
                        )
                        SELECT 
                            UUID(), se.tracking_token, CONCAT('legacy_sent_email_', se.id),
                            se.sender_email, se.recipient_email, se.subject, se.message_body,
                            'sent', se.sent_at, se.sent_at, se.sent_at
                        FROM sent_emails se
                        WHERE NOT EXISTS (
                            SELECT 1 FROM emails e WHERE e.message_id = CONCAT('legacy_sent_email_', se.id)
                        )
                        AND se.current_status = 1
                    ");
                    
                    $synced = $pdo->lastInsertId();
                    echo '<div class="success-box">✓ Synced ' . $synced . ' emails to emails table</div>';
                    
                } elseif ($solution == 2) {
                    // Solution 2: Remove FK
                    echo '<div class="info-box">Running Solution 2: Removing foreign key...</div>';
                    
                    $pdo->exec("ALTER TABLE email_read_tracking DROP FOREIGN KEY fk_read_tracking_email");
                    $pdo->exec("ALTER TABLE email_read_tracking MODIFY COLUMN email_id BIGINT(20) SIGNED NOT NULL");
                    
                    echo '<div class="success-box">✓ Foreign key removed successfully</div>';
                    
                } elseif ($solution == 3) {
                    // Solution 3: Dual table
                    echo '<div class="info-box">Running Solution 3: Adding dual-table support...</div>';
                    
                    $pdo->exec("ALTER TABLE email_read_tracking ADD COLUMN source_table ENUM('emails', 'sent_emails') DEFAULT 'emails' AFTER email_id");
                    $pdo->exec("ALTER TABLE email_read_tracking ADD COLUMN source_id BIGINT(20) UNSIGNED NULL AFTER source_table");
                    $pdo->exec("ALTER TABLE email_read_tracking DROP FOREIGN KEY fk_read_tracking_email");
                    $pdo->exec("ALTER TABLE email_read_tracking MODIFY COLUMN email_id BIGINT(20) UNSIGNED NULL");
                    
                    echo '<div class="success-box">✓ Dual-table support added</div>';
                }
                
                $pdo->commit();
                echo '<div class="success-box"><strong>Fix applied successfully!</strong></div>';
                echo '<button onclick="location.href=\'?step=done&solution=' . $solution . '\'">Continue →</button>';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo '<div class="error-box">Error applying fix: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<button class="secondary" onclick="location.href=\'?step=choose\'">← Try Another Solution</button>';
            }
            ?>
        </div>

        <?php elseif ($step === 'done'): ?>
        <div class="step active">
            <div class="success-box">
                <h2>✓ Fix Applied Successfully!</h2>
            </div>

            <?php $solution = $_GET['solution'] ?? 2; ?>

            <h3>Next Steps:</h3>
            
            <?php if ($solution == 1): ?>
            <div class="info-box">
                <strong>You chose: Sync Tables</strong><br><br>
                1. Your <code>sent_emails</code> have been synced to <code>emails</code> table<br>
                2. Upload <code>read_tracking_helper_FIXED.php</code> as <code>read_tracking_helper.php</code><br>
                3. It will auto-create emails records when you send new emails<br>
                4. Test by sending an email
            </div>
            
            <?php elseif ($solution == 2): ?>
            <div class="warning-box">
                <strong>You chose: Remove Foreign Key</strong><br><br>
                1. Foreign key constraint has been removed<br>
                2. Upload <code>read_tracking_helper_FIXED.php</code> as <code>read_tracking_helper.php</code><br>
                3. It will use negative IDs for <code>sent_emails</code><br>
                4. Test by sending an email<br><br>
                ⚠️ Note: You've sacrificed referential integrity for speed. Consider migrating to Solution 1 later.
            </div>
            
            <?php elseif ($solution == 3): ?>
            <div class="info-box">
                <strong>You chose: Dual-Table Support</strong><br><br>
                1. Your schema now supports both tables<br>
                2. Update your tracking code to use <code>source_table</code> and <code>source_id</code> columns<br>
                3. See the documentation for code examples<br>
                4. Test by sending an email
            </div>
            <?php endif; ?>

            <h3>Files to Upload:</h3>
            <ul>
                <li><code>read_tracking_helper_FIXED.php</code> → rename to <code>read_tracking_helper.php</code></li>
                <li><code>track_pixel_debug.php</code> (for testing)</li>
                <li><code>url_diagnostic.php</code> (for diagnostics)</li>
            </ul>

            <button onclick="location.href='url_diagnostic.php'">Open Diagnostic Tool →</button>
            <button class="secondary" onclick="location.href='?step=analyze'">Run Analysis Again</button>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function selectSolution(element, solution) {
            document.querySelectorAll('.solution').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedSolution').value = solution;
        }

        function applySolution() {
            const solution = document.getElementById('selectedSolution').value;
            if (!solution) {
                alert('Please select a solution first');
                return;
            }
            location.href = '?step=apply&solution=' + solution;
        }
    </script>
</body>
</html>