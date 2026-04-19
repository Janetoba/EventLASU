<?php
// Ensure these paths are 100% correct relative to this file
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * EventLASU Verification Mailer
 */
function sendVerificationEmail($toEmail, $toName, $token) {
    $mail = new PHPMailer(true);
    $verifyLink = "http://localhost/lastix/verify.php?token=" . $token;

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mogboladejanet@gmail.com'; // Change this
        $mail->Password   = 'hqlz unvk iaon ddyr';   // Change this (16 digits)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Email Identity
        $mail->setFrom('noreply@lastix.com', 'EventLASU');
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify your EventLASU account';
        $mail->Body    = "Hello $toName, <br><br> Click here to verify: <a href='$verifyLink'>$verifyLink</a>";

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}