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
        // No need for nl2br or htmlspecialchars since it's already formatted
        
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
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: #eaeff2; 
            margin: 0; 
            padding: 20px; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .preview-header {
            background: white;
            padding: 20px 30px;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 0;
        }
        .preview-header h2 {
            margin: 0 0 16px 0;
            color: #1a1a1a;
            font-size: 24px;
        }
        .preview-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .preview-info-row {
            display: flex;
            gap: 8px;
            font-size: 14px;
        }
        .preview-info-label {
            font-weight: 600;
            color: #666;
            min-width: 80px;
        }
        .preview-info-value {
            color: #1a1a1a;
        }
        .preview-window { 
            background: white; 
            border-radius: 0 0 8px 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            overflow: hidden; 
        }
        .controls { 
            text-align: center; 
            margin-top: 30px; 
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn { 
            padding: 12px 30px; 
            border-radius: 7px; 
            cursor: pointer; 
            font-weight: 500; 
            text-decoration: none; 
            border: none; 
            font-size: 15px; 
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
        }
        .btn-secondary { 
            background: white;
            color: #1a73e8;
            border: 1px solid #1a73e8;
        }
        .btn-secondary:hover {
            background: #f0f7ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="preview-header">
            <h2>📧 Email Preview</h2>
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