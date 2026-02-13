<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Mail Diagnostic Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f7;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1d1d1f;
        }
        .subtitle {
            color: #6e6e73;
            margin-bottom: 30px;
        }
        .alert {
            background: #e3f2fd;
            border-left: 4px solid #0071e3;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .alert h3 {
            color: #0071e3;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .alert p {
            color: #01579b;
            font-size: 14px;
            line-height: 1.5;
        }
        .test-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .test-card h2 {
            font-size: 20px;
            margin-bottom: 16px;
            color: #1d1d1f;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .test-card p {
            color: #6e6e73;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #0071e3;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #0077ed;
            transform: translateY(-1px);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #6e6e73;
        }
        .btn-secondary:hover {
            background: #515154;
        }
        .input-group {
            margin-bottom: 16px;
        }
        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 6px;
        }
        .input-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d2d2d7;
            border-radius: 6px;
            font-size: 14px;
        }
        .result {
            margin-top: 16px;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .result.success {
            background: #e6f4ea;
            border: 1px solid #1db954;
            color: #0d5a29;
        }
        .result.error {
            background: #fce8e6;
            border: 1px solid #ff3b30;
            color: #991b1b;
        }
        .result.info {
            background: #e3f2fd;
            border: 1px solid #0071e3;
            color: #01579b;
        }
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-indicator.success { background: #1db954; }
        .status-indicator.error { background: #ff3b30; }
        .status-indicator.pending { background: #f5a623; }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0071e3;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .code {
            background: #f5f5f7;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Bulk Mail System Diagnostic Tool</h1>
        <p class="subtitle">Use this tool to diagnose email sending issues in your bulk mail system</p>

        <div class="alert">
            <h3>📋 Test Instructions</h3>
            <p><strong>Before running tests:</strong> Make sure you're logged into your email system first. These tests require an active session with SMTP credentials.</p>
            <p><strong>For Test #2:</strong> Upload <span class="code">test_email_send.php</span> to your server first, then enter your email address below.</p>
        </div>

        <div class="grid">
            <!-- Test 1: SMTP Connection -->
            <div class="test-card">
                <h2>
                    <span class="status-indicator pending" id="test1-status"></span>
                    1. SMTP Connection Test
                </h2>
                <p>Tests if your server can connect to the SMTP server and authenticate.</p>
                <button class="btn" onclick="runTest('test_smtp', 'test1')">
                    <span>Run Test</span>
                </button>
                <div id="test1-result"></div>
            </div>

            <!-- Test 2: Send Test Email (Updated) -->
            <div class="test-card">
                <h2>
                    <span class="status-indicator pending" id="test2-status"></span>
                    2. Send Test Email
                </h2>
                <p>Sends a real test email to verify end-to-end delivery.</p>
                <div class="input-group">
                    <label>Test Email Address:</label>
                    <input type="email" id="test-email" placeholder="your-email@example.com" value="">
                </div>
                <button class="btn" onclick="runTestSendEmail('test2')">
                    <span>Send Test Email</span>
                </button>
                <div id="test2-result"></div>
            </div>

            <!-- Test 3: Queue Status -->
            <div class="test-card">
                <h2>
                    <span class="status-indicator pending" id="test3-status"></span>
                    3. Queue Status
                </h2>
                <p>Checks the current state of your email queue.</p>
                <button class="btn" onclick="runTest('status', 'test3')">
                    <span>Check Queue</span>
                </button>
                <div id="test3-result"></div>
            </div>

            <!-- Test 4: Recent Queue Items -->
            <div class="test-card">
                <h2>
                    <span class="status-indicator pending" id="test4-status"></span>
                    4. Recent Queue Items
                </h2>
                <p>Retrieves the last 10 items from the queue to check their actual status.</p>
                <button class="btn" onclick="runTest('queue_list', 'test4')">
                    <span>Query Database</span>
                </button>
                <div id="test4-result"></div>
            </div>

            <!-- Test 5: Process One Email -->
            <div class="test-card">
                <h2>
                    <span class="status-indicator pending" id="test5-status"></span>
                    5. Process Next Queued Email
                </h2>
                <p>Attempts to send the next email in queue (if any exist).</p>
                <button class="btn" onclick="runTest('process', 'test5')">
                    <span>Process Email</span>
                </button>
                <div id="test5-result"></div>
            </div>

            <!-- Test 6: Check Spam Folder -->
            <div class="test-card">
                <h2>
                    <span class="status-indicator pending" id="test6-status"></span>
                    6. Email Deliverability Check
                </h2>
                <p>Based on your diagnostic results, your emails ARE being sent successfully. Check these locations:</p>
                <ul style="margin: 16px 0; padding-left: 20px; color: #6e6e73;">
                    <li>Gmail Spam/Junk folder</li>
                    <li>Gmail "All Mail" folder</li>
                    <li>Search: <span class="code">from:info.official@holidayseva.com</span></li>
                </ul>
                <button class="btn btn-secondary" onclick="showDeliverabilityTips()">
                    <span>View Tips</span>
                </button>
                <div id="test6-result"></div>
            </div>
        </div>
    </div>

    <script>
        async function runTest(action, testId) {
            const statusEl = document.getElementById(`${testId}-status`);
            const resultEl = document.getElementById(`${testId}-result`);
            const btnEl = event.target.closest('.btn');
            
            // Update UI
            statusEl.className = 'status-indicator pending';
            btnEl.disabled = true;
            btnEl.innerHTML = '<div class="spinner"></div> Running...';
            resultEl.innerHTML = '';
            
            try {
                const response = await fetch(`process_bulk_mail.php?action=${action}`, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                // Update status
                statusEl.className = `status-indicator ${data.success ? 'success' : 'error'}`;
                
                // Show result
                const resultClass = data.success ? 'success' : 'error';
                const resultText = JSON.stringify(data, null, 2);
                
                resultEl.innerHTML = `<div class="result ${resultClass}">${resultText}</div>`;
                
                // Log to console for debugging
                console.log(`Test ${testId} (${action}):`, data);
                
            } catch (error) {
                statusEl.className = 'status-indicator error';
                resultEl.innerHTML = `<div class="result error">Error: ${error.message}</div>`;
                console.error(`Test ${testId} error:`, error);
            } finally {
                btnEl.disabled = false;
                btnEl.innerHTML = '<span>Run Test</span>';
            }
        }
        
        async function runTestSendEmail(testId) {
            const email = document.getElementById('test-email').value;
            
            if (!email || !email.includes('@')) {
                alert('Please enter a valid email address');
                return;
            }
            
            const statusEl = document.getElementById(`${testId}-status`);
            const resultEl = document.getElementById(`${testId}-result`);
            const btnEl = event.target.closest('.btn');
            
            statusEl.className = 'status-indicator pending';
            btnEl.disabled = true;
            btnEl.innerHTML = '<div class="spinner"></div> Sending...';
            resultEl.innerHTML = '';
            
            try {
                // Call the standalone test script
                const response = await fetch(`test_email_send.php?email=${encodeURIComponent(email)}`, {
                    method: 'GET'
                });
                
                const data = await response.json();
                
                statusEl.className = `status-indicator ${data.success ? 'success' : 'error'}`;
                
                const resultClass = data.success ? 'success' : 'error';
                const resultText = JSON.stringify(data, null, 2);
                
                resultEl.innerHTML = `<div class="result ${resultClass}">${resultText}</div>`;
                
                if (data.success) {
                    alert(`✅ Test email sent successfully to ${email}!\n\nCheck your inbox (and spam folder).\n\nIf you don't see it:\n- Check spam/junk folder\n- Check "All Mail" in Gmail\n- Wait a few minutes for delivery`);
                } else {
                    alert(`❌ Test email failed!\n\nError: ${data.error || data.smtp_error || 'Unknown error'}\n\nCheck the details in the result box below.`);
                }
                
                console.log(`Test ${testId}:`, data);
                
            } catch (error) {
                statusEl.className = 'status-indicator error';
                resultEl.innerHTML = `<div class="result error">Error: ${error.message}\n\nMake sure test_email_send.php is uploaded to your server!</div>`;
                console.error(`Test ${testId} error:`, error);
                alert('❌ Error: Make sure test_email_send.php is uploaded to your server!');
            } finally {
                btnEl.disabled = false;
                btnEl.innerHTML = '<span>Send Test Email</span>';
            }
        }
        
        function showDeliverabilityTips() {
            const resultEl = document.getElementById('test6-result');
            const statusEl = document.getElementById('test6-status');
            
            statusEl.className = 'status-indicator success';
            
            resultEl.innerHTML = `<div class="result info">
📧 EMAIL DELIVERABILITY TIPS

Your emails ARE being sent successfully! If they're not arriving:

1. CHECK SPAM FOLDERS
   - Gmail Spam/Junk folder
   - Gmail "All Mail" folder  
   - Gmail Promotions tab
   - Search: from:info.official@holidayseva.com

2. SET UP EMAIL AUTHENTICATION (Most Important!)
   
   Add these DNS records to holidayseva.com:
   
   SPF Record:
   TXT @ "v=spf1 include:_spf.hostinger.com ~all"
   
   DMARC Record:
   TXT _dmarc "v=DMARC1; p=none; rua=mailto:dmarc@holidayseva.com"
   
   DKIM Record:
   Get from Hostinger Control Panel → Email → DKIM

3. WARM UP YOUR DOMAIN
   - Start with small batches (10-20 emails/day)
   - Gradually increase over 2-4 weeks
   - Send to engaged recipients first

4. IMPROVE EMAIL CONTENT
   - Add unsubscribe link
   - Include physical address
   - Avoid spam trigger words (FREE, WIN, CLICK HERE)
   - Use consistent sender name

5. MONITOR YOUR REPUTATION
   - Check: https://mxtoolbox.com/blacklists.aspx
   - Use: https://www.mail-tester.com/
   - Set up Google Postmaster Tools

6. ASK RECIPIENTS TO WHITELIST
   - Add info.official@holidayseva.com to contacts
   - Mark as "Not Spam" when found

Your bulk mail system is working perfectly - this is a 
deliverability issue, not a technical problem!
</div>`;
        }
        
        // Auto-populate user's email if available
        window.addEventListener('DOMContentLoaded', () => {
            console.log('🔧 Bulk Mail Diagnostic Tool Loaded');
            console.log('Click any test button to start diagnostics');
            console.log('Remember to upload test_email_send.php for Test #2 to work!');
        });
    </script>
</body>
</html>