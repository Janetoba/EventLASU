<?php
require 'db.php';

$token = $_GET['token'] ?? '';
$message = '';
$success = false;

if (!$token) {
    $message = 'Invalid verification link.';
} else {
    // Find a valid, unused, unexpired token
    $stmt = $pdo->prepare("
        SELECT * FROM email_tokens 
        WHERE token = ? AND used = 0 AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $message = 'This link is invalid or has expired. Please register again.';
    } else {
        // Mark user as verified
        $pdo->prepare("UPDATE users SET verified = 1 WHERE id = ?")
            ->execute([$row['user_id']]);

        // Mark token as used so it can't be reused
        $pdo->prepare("UPDATE email_tokens SET used = 1 WHERE id = ?")
            ->execute([$row['id']]);

        $success = true;
        $message = 'Your email is verified! You can now log in.';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Verify — LasuTix</title><link rel="stylesheet" href="style.css"></head>
<body>
  <h1>EventLASU</h1>
  <div class="alert <?= $success ? 'success' : 'error' ?>">
    <?= htmlspecialchars($message) ?>
  </div>
  <a href="auth.php">Go to Login</a>
</body>
</html>