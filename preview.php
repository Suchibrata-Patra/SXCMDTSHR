<?php
// /Applications/XAMPP/xamppfiles/htdocs/preview.php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    // Get signature components
    $signatureWish = $_POST['signatureWish'] ?? '';
    $signatureName = $_POST['signatureName'] ?? '';
    $signatureDesignation = $_POST['signatureDesignation'] ?? '';
    $signatureExtra = $_POST['signatureExtra'] ?? '';
    
    // Explicitly using your stunning template
    $templatePath = 'templates/template1.html';

    $previewHtml = "";
    if (file_exists($templatePath)) {
        $templateContent = file_get_contents($templatePath);
        
        // Prepare the message: escape HTML for security and handle newlines
        $safeMessage = nl2br(htmlspecialchars($message));
        
        // Swap placeholders
        $previewHtml = str_replace('{{MESSAGE}}', $safeMessage, $templateContent);
        $previewHtml = str_replace('{{SIGNATURE_WISH}}', htmlspecialchars($signatureWish), $previewHtml);
        $previewHtml = str_replace('{{SIGNATURE_NAME}}', htmlspecialchars($signatureName), $previewHtml);
        $previewHtml = str_replace('{{SIGNATURE_DESIGNATION}}', htmlspecialchars($signatureDesignation), $previewHtml);
        $previewHtml = str_replace('{{SIGNATURE_EXTRA}}', nl2br(htmlspecialchars($signatureExtra)), $previewHtml);
        $previewHtml = str_replace('{{YEAR}}', date('Y'), $previewHtml);
    } else {
        $previewHtml = "<div style='color:red; padding:20px;'>Error: Template not found at $templatePath</div>";
    }
?>
<!DOCTYPE html>
<html>
<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Dashboard');
        include 'header.php';
    ?>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #eaeff2; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .preview-window { background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; }
        .toolbar { background: #f8f9fa; padding: 10px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
        .controls { text-align: center; margin-top: 30px; }
        .btn { padding: 12px 30px; border-radius: 5px; cursor: pointer; font-weight: bold; text-decoration: none; border: none; font-size: 16px; }
        .btn-send { background: #1a73e8; color: white; }
        .btn-back { background: #6c757d; color: white; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="text-align:center; color: #333;">Review Your Design</h2>
        
        <div class="preview-window">
            <div class="toolbar">
                <span><strong>To:</strong> <?php echo htmlspecialchars($email); ?></span>
                <span><strong>Subject:</strong> <?php echo htmlspecialchars($subject); ?></span>
            </div>
            <?php echo $previewHtml; ?>
        </div>

        <div class="controls">
            <form action="send.php" method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject); ?>">
                <input type="hidden" name="message" value="<?php echo htmlspecialchars($message); ?>">
                <input type="hidden" name="signatureWish" value="<?php echo htmlspecialchars($signatureWish); ?>">
                <input type="hidden" name="signatureName" value="<?php echo htmlspecialchars($signatureName); ?>">
                <input type="hidden" name="signatureDesignation" value="<?php echo htmlspecialchars($signatureDesignation); ?>">
                <input type="hidden" name="signatureExtra" value="<?php echo htmlspecialchars($signatureExtra); ?>">
                
                <button type="button" class="btn btn-back" onclick="history.back()">Edit Message</button>
                <button type="submit" class="btn btn-send">Confirm & Send Now</button>
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