<?php
require 'db.php';
require 'config.php';

$reference = $_GET['reference'] ?? '';
if (!$reference) { header('Location: index.php'); exit; }

// Verify with Paystack
$ch = curl_init("https://api.paystack.co/transaction/verify/" . urlencode($reference));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// Find the pending order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE paystack_ref = ? AND status = 'pending'");
$stmt->execute([$reference]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    // Already processed or doesn't exist
    header('Location: dashboard.php');
    exit;
}

if ($response['status'] && $response['data']['status'] === 'success') {
    // Payment confirmed — finalize the order
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?")
            ->execute([$order['id']]);

        $pdo->prepare("UPDATE events SET tickets_sold = tickets_sold + ? WHERE id = ?")
            ->execute([$order['quantity'], $order['event_id']]);

        $pdo->commit();

        header('Location: dashboard.php?paid=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: dashboard.php?err=confirm_failed');
        exit;
    }
} else {
    // Payment failed or was cancelled
    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")
        ->execute([$order['id']]);

    $stmt = $pdo->prepare("SELECT event_id FROM orders WHERE id = ?");
    $stmt->execute([$order['id']]);
    $eventId = $stmt->fetch()['event_id'];

    header("Location: event.php?id=$eventId&err=payment_failed");
    exit;
}