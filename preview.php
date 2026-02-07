<?php
// /Applications/XAMPP/xamppfiles/htdocs/preview.php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $articletitle = $_POST['articletitle'] ?? '';
    
    // Using template1.html
    $templatePath = 'templates/template1.html';

    $previewHtml = "";
    if (file_exists($templatePath)) {
        $templateContent = file_get_contents($templatePath);
        
        // The message is already HTML from the rich text editor
        // Do NOT escape or modify it - use it exactly as provided
        
        // Replace placeholders
        $previewHtml = str_replace('{{MESSAGE}}', $message, $templateContent);
        $previewHtml = str_replace('{{articletitle}}', htmlspecialchars($articletitle), $previewHtml);
        $previewHtml = str_replace('{{YEAR}}', date('Y'), $previewHtml);
    } else {
        $previewHtml = "<div style='color:red; padding:20px;'>Error: Template not found at $templatePath</div>";
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Preview - SXC MDTS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: #f1f3f4; 
            margin: 0; 
            padding: 20px; 
        }

        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }

        .preview-header {
            background: white;
            padding: 24px 32px;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 1px 3px rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15);
            margin-bottom: 0;
        }

        .preview-header h2 {
            margin: 0 0 20px 0;
            color: #202124;
            font-size: 22px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .preview-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .preview-info-row {
            display: flex;
            gap: 12px;
            font-size: 14px;
            align-items: baseline;
        }

        .preview-info-label {
            font-weight: 500;
            color: #5f6368;
            min-width: 80px;
        }

        .preview-info-value {
            color: #202124;
            flex: 1;
        }

        .preview-window { 
            background: white; 
            border-radius: 0 0 8px 8px; 
            box-shadow: 0 1px 3px rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15);
            overflow: hidden; 
        }

        .controls { 
            text-align: center; 
            margin-top: 24px; 
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn { 
            padding: 10px 24px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: 500; 
            text-decoration: none; 
            border: none; 
            font-size: 14px; 
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary { 
            background: #1a73e8; 
            color: white; 
        }

        .btn-primary:hover {
            background: #1557b0;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
        }

        .btn-secondary { 
            background: white;
            color: #5f6368;
            border: 1px solid #dadce0;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #5f6368;
            color: #202124;
        }

        .email-icon {
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="preview-header">
            <h2>
                <span class="email-icon">📧</span>
                Email Preview
            </h2>
            <div class="preview-info">
                <div class="preview-info-row">
                    <span class="preview-info-label">To:</span>
                    <span class="preview-info-value"><?php echo htmlspecialchars($email); ?></span>
                </div>
                <div class="preview-info-row">
                    <span class="preview-info-label">Subject:</span>
                    <span class="preview-info-value"><?php echo htmlspecialchars($subject); ?></span>
                </div>
            </div>
        </div>
        
        <div class="preview-window">
            <?php echo $previewHtml; ?>
        </div>

        <div class="controls">
            <button type="button" class="btn btn-secondary" onclick="window.close()">
                ← Back to Editor
            </button>
            <form action="send.php" method="POST" style="display: inline;">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject); ?>">
                <input type="hidden" name="message" value="<?php echo htmlspecialchars($message); ?>">
                <input type="hidden" name="articletitle" value="<?php echo htmlspecialchars($articletitle); ?>">
                <input type="hidden" name="message_is_html" value="true">
                
                <button type="submit" class="btn btn-primary">
                    ✓ Confirm & Send Email
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
} else {
    header("Location: index.php");
    exit();
}
?>