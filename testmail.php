<?php
require 'mailer.php';

// Change 'sendMail' to 'sendVerificationEmail'
// Also, this function now expects 3 arguments: Email, Name, and a Token
if(sendVerificationEmail('your-email@gmail.com', 'LASU Student', 'test-token-123')) {
    echo "Email sent successfully! Check your inbox.";
} else {
    echo "Email failed. Check your XAMPP error logs in the PHPMailer folder.";
}
?>