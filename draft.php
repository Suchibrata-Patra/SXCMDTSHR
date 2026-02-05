<!DOCTYPE html>
<html>
<head><title>Create Draft</title></head>
<body>

<h2>Draft Email</h2>

<form method="POST" action="send.php" enctype="multipart/form-data">
    <label>Recipient Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>Subject:</label><br>
    <input type="text" name="subject" required><br><br>

    <label>Message:</label><br>
    <textarea name="message" rows="8" cols="50"></textarea><br><br>

    <label>Attachment (PDF):</label><br>
    <input type="file" name="attachment" accept=".pdf"><br><br>

    <input type="hidden" name="template" value="">
    <button type="submit">Send Email</button>
</form>

</body>
</html>