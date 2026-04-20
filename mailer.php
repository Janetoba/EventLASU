<?php
// Ensure these paths are 100% correct relative to this file
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($toEmail, $toName, $token) {
    $mail = new PHPMailer(true);
    $verifyLink = "http://localhost/lastix/verify.php?token=" . $token;

    try {
        // SMTP Settings
        $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client and server messages
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mogboladejanet@gmail.com'; 
        $mail->Password   = 'hqlz unvk iaon ddyr';   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Email Identity
        $mail->setFrom('noreply@lastix.com', 'EventLASU');
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify your EventLASU account';
        $mail->Body    = "Hello $toName, <br><br> Click here to verify: <a href='$verifyLink'>$verifyLink</a>";
$mail->SMTPOptions = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);
        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}