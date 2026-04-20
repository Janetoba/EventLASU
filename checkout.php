<?php
require 'auth_check.php';
requireVerifiedStudent();
require 'db.php';
require 'config.php';

$eventId  = intval($_POST['event_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);
$quantity = max(1, min(5, $quantity));

// Fetch event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) { header('Location: index.php'); exit; }

// Validations
$remaining = $event['capacity'] - $event['tickets_sold'];
if ($quantity > $remaining)  { header("Location: event.php?id=$eventId&err=sold_out"); exit; }
if ($event['user_id'] == $_SESSION['user_id']) { header("Location: event.php?id=$eventId&err=own_event"); exit; }

// Check existing tickets
$check = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) AS held FROM orders WHERE user_id = ? AND event_id = ?");
$check->execute([$_SESSION['user_id'], $eventId]);
$held = intval($check->fetch(PDO::FETCH_ASSOC)['held']);
if ($held + $quantity > 5) { header("Location: event.php?id=$eventId&err=limit"); exit; }

// Handle FREE events — skip payment entirely
if ($event['price'] == 0) {
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO orders (user_id, event_id, quantity, total_price) VALUES (?,?,?,0)")
            ->execute([$_SESSION['user_id'], $eventId, $quantity]);
        $pdo->prepare("UPDATE events SET tickets_sold = tickets_sold + ? WHERE id = ?")
            ->execute([$quantity, $eventId]);
        $pdo->commit();
        header("Location: event.php?id=$eventId&created=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: event.php?id=$eventId&err=fail");
        exit;
    }
}

// Paid event — initialize Paystack transaction
$amountKobo  = intval($event['price'] * $quantity * 100); // Paystack uses kobo
$reference   = 'LTIX_' . uniqid() . '_' . $_SESSION['user_id'];
$userEmail   = $_SESSION['user_email']; // make sure this is in session (see note below)

// Save a pending order so we can match it on callback
// 1. Prepare the SQL with 6 question marks to match the 6 columns
$stmt = $pdo->prepare("
    INSERT INTO orders (user_id, event_id, quantity, total_price, status, paystack_ref)
    VALUES (?, ?, ?, ?, ?, ?)
");

// 2. Execute with exactly 6 values in the array
$stmt->execute([
    $_SESSION['user_id'],
    $eventId, 
    $quantity, 
    ($event['price'] * $quantity),
    'pending', // This is value #5
    $reference // This is value #6
]);

// Call Paystack API to initialize
$ch = curl_init('https://api.paystack.co/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'email'     => $userEmail,
        'amount'    => $amountKobo,
        'reference' => $reference,
        'callback_url' => SITE_URL . '/payment_callback.php',
        'metadata'  => [
            'event_id'  => $eventId,
            'quantity'  => $quantity,
            'user_id'   => $_SESSION['user_id'],
            'event_name'=> $event['title'],
        ],
    ]),
]);
/*$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($response['status']) {
    // Redirect user to Paystack's hosted payment page
    header('Location: ' . $response['data']['authorization_url']);
    exit;
} else {
    header("Location: event.php?id=$eventId&err=payment_init_failed");
    exit;*/
    $raw_response = curl_exec($ch); // Catch the raw string
$response = json_decode($raw_response, true);
curl_close($ch);

if ($response['status']) {
    header('Location: ' . $response['data']['authorization_url']);
    exit;
} else {
    // THIS WILL SHOW YOU EXACTLY WHAT PAYSTACK HATES
    echo "<h1>Paystack Error</h1>";
    echo "<pre>";
    print_r($response); 
    echo "</pre>";
    exit;
}
