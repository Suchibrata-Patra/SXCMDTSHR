<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive Files Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        button {
            background: #0071E3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #005BBF;
        }
        #result {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 12px;
            max-height: 500px;
            overflow: auto;
        }
        .file-item {
            padding: 10px;
            margin: 5px 0;
            background: #f0f0f0;
            border-radius: 4px;
        }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <div class="test-section">
        <h1>🔍 Drive Files Loading Test</h1>
        <p>Click the button below to test if drive files can be loaded</p>
        
        <button onclick="testDriveFiles()">Test Drive Files Loading</button>
        
        <div id="result"></div>
        
        <div id="filesList"></div>
    </div>

    <script>
        async function testDriveFiles() {
            const resultDiv = document.getElementById('result');
            const filesDiv = document.getElementById('filesList');
            
            resultDiv.innerHTML = '<span class="info">⏳ Testing...</span>\n\n';
            filesDiv.innerHTML = '';
            
            try {
                // Step 1: Determine URL
                const currentPath = window.location.pathname;
                const directory = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
                const url = directory + 'bulk_mail_backend.php';
                
                resultDiv.innerHTML += `<span class="info">📍 Current Path:</span> ${currentPath}\n`;
                resultDiv.innerHTML += `<span class="info">📁 Directory:</span> ${directory}\n`;
                resultDiv.innerHTML += `<span class="info">🔗 Backend URL:</span> ${url}\n\n`;
                
                // Step 2: Create FormData
                const formData = new FormData();
                formData.append('action', 'list_drive_files');
                
                resultDiv.innerHTML += '<span class="info">📤 Sending request...</span>\n';
                
                // Step 3: Fetch
                const response = await fetch(url, { 
                    method: 'POST', 
                    body: formData 
                });
                
                resultDiv.innerHTML += `<span class="info">📥 Response Status:</span> ${response.status} ${response.statusText}\n`;
                resultDiv.innerHTML += `<span class="info">✓ Response OK:</span> ${response.ok}\n\n`;
                
                // Step 4: Get response text first
                const responseText = await response.text();
                resultDiv.innerHTML += `<span class="info">📄 Raw Response (first 500 chars):</span>\n${responseText.substring(0, 500)}\n\n`;
                
                // Step 5: Parse JSON
                const data = JSON.parse(responseText);
                
                resultDiv.innerHTML += `<span class="info">📊 Parsed JSON:</span>\n${JSON.stringify(data, null, 2)}\n\n`;
                
                // Step 6: Display results
                if (data.success && data.files && data.files.length > 0) {
                    resultDiv.innerHTML += `<span class="success">✅ SUCCESS! Found ${data.files.length} files</span>\n`;
                    
                    filesDiv.innerHTML = '<h3>Files Found:</h3>';
                    data.files.forEach(file => {
                        filesDiv.innerHTML += `
                            <div class="file-item">
                                <strong>${file.name}</strong><br>
                                Size: ${file.formatted_size}<br>
                                Path: ${file.path}
                            </div>
                        `;
                    });
                } else if (data.success) {
                    resultDiv.innerHTML += '<span class="info">ℹ️ No files found in drive</span>\n';
                } else {
                    resultDiv.innerHTML += `<span class="error">❌ Error: ${data.error || 'Unknown error'}</span>\n`;
                }
                
            } catch (err) {
                resultDiv.innerHTML += `<span class="error">❌ EXCEPTION: ${err.message}</span>\n`;
                resultDiv.innerHTML += `<span class="error">Stack: ${err.stack}</span>\n`;
                console.error('Test error:', err);
            }
        }
        
        // Auto-run on page load
        window.addEventListener('DOMContentLoaded', () => {
            console.log('Test page loaded');
        });
    </script>
</body>
</html>