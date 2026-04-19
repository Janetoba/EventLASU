<?php
// auth_check.php
// Include this at the top of any page that requires a verified, logged-in student

function requireVerifiedStudent(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: auth.php');
        exit;
    }
    if (empty($_SESSION['verified'])) {
        // User is logged in but not verified — redirect with message
        header('Location: auth.php?unverified=1');
        exit;
    }
}
