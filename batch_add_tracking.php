<?php
/**
 * Batch Migration Script
 * Add read tracking to all existing sent emails
 * 
 * RUN THIS ONCE after deploying the read receipt system
 * 
 * Usage:
 * php batch_add_tracking.php
 * 
 * Or visit in browser:
 * https://yourdomain.com/batch_add_tracking.php
 */

session_start();
require_once 'db_config.php';
require_once 'read_tracking_helper.php';
require_once 'email_tracking_integration.php';

// Security check - only allow super admins or authenticated users
if (!isset($_SESSION['smtp_user'])) {
    die("ERROR: Authentication required. Please log in first.");
}

$userEmail = $_SESSION['smtp_user'];

// Optional: Restrict to super admin only
// if (!isSuperAdmin()) {
//     die("ERROR: Super admin access required.");
// }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Add Tracking - Migration</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 800px;
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
            margin-bottom: 20px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
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
            display: none;
        }
        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            display: none;
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
            margin-top: 20px;
        }
        button:hover {
            background: #0051D5;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .progress {
            margin-top: 20px;
            display: none;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #007AFF;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Batch Add Read Tracking</h1>
        
        <div class="info-box">
            <strong>ℹ️ What this does:</strong><br>
            This script will add read receipt tracking to all your previously sent emails that don't have tracking yet. 
            Each email will get a unique tracking token, allowing you to see when recipients open them.
        </div>

        <div class="warning-box">
            <strong>⚠️ Important:</strong><br>
            • Tracking will only work for emails sent from now on (emails already delivered won't be tracked)<br>
            • This is a one-time migration for existing database records<br>
            • Future emails will automatically include tracking
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="totalEmails">-</div>
                <div class="stat-label">Total Sent Emails</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="withoutTracking">-</div>
                <div class="stat-label">Without Tracking</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="processed">0</div>
                <div class="stat-label">Processed</div>
            </div>
        </div>

        <button id="startBtn" onclick="startMigration()">Start Migration</button>
        <button id="checkBtn" onclick="checkStatus()" style="background: #6c757d;">Check Status</button>

        <div class="progress" id="progressContainer">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%;">0%</div>
            </div>
        </div>

        <div class="success-box" id="successBox"></div>
        <div class="error-box" id="errorBox"></div>
    </div>

    <script>
        async function checkStatus() {
            try {
                const response = await fetch('migration_status.php');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('totalEmails').textContent = data.total_emails;
                    document.getElementById('withoutTracking').textContent = data.without_tracking;
                }
            } catch (error) {
                console.error('Error checking status:', error);
            }
        }

        async function startMigration() {
            const startBtn = document.getElementById('startBtn');
            const progressContainer = document.getElementById('progressContainer');
            const progressFill = document.getElementById('progressFill');
            const processedStat = document.getElementById('processed');
            const successBox = document.getElementById('successBox');
            const errorBox = document.getElementById('errorBox');

            startBtn.disabled = true;
            progressContainer.style.display = 'block';
            successBox.style.display = 'none';
            errorBox.style.display = 'none';

            let totalProcessed = 0;
            let batchSize = 100;
            let hasMore = true;

            while (hasMore) {
                try {
                    const response = await fetch('run_migration_batch.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ batch_size: batchSize })
                    });

                    const data = await response.json();

                    if (data.success) {
                        totalProcessed += data.processed;
                        processedStat.textContent = totalProcessed;

                        // Update progress
                        const withoutTracking = parseInt(document.getElementById('withoutTracking').textContent);
                        const progress = Math.min(100, Math.round((totalProcessed / withoutTracking) * 100));
                        progressFill.style.width = progress + '%';
                        progressFill.textContent = progress + '%';

                        if (data.processed < batchSize) {
                            hasMore = false;
                        }

                        // Small delay between batches
                        await new Promise(resolve => setTimeout(resolve, 500));

                    } else {
                        hasMore = false;
                        errorBox.textContent = '❌ Error: ' + (data.error || 'Unknown error');
                        errorBox.style.display = 'block';
                    }

                } catch (error) {
                    hasMore = false;
                    errorBox.textContent = '❌ Network error: ' + error.message;
                    errorBox.style.display = 'block';
                }
            }

            // Migration complete
            startBtn.disabled = false;
            successBox.innerHTML = `
                <strong>✅ Migration Complete!</strong><br>
                Successfully added tracking to ${totalProcessed} emails.
            `;
            successBox.style.display = 'block';

            // Refresh stats
            checkStatus();
        }

        // Load initial stats
        window.addEventListener('DOMContentLoaded', checkStatus);
    </script>
</body>
</html>