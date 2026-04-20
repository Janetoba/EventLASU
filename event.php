<?php
session_start();
require 'db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Fetch event with poster info
$stmt = $pdo->prepare("
    SELECT e.*, u.name AS poster_name, u.id AS poster_id
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) { header('Location: index.php'); exit; }

$remaining  = $event['capacity'] - $event['tickets_sold'];
$sold_out   = $remaining <= 0;
$is_past    = strtotime($event['event_date']) < time();
$is_owner   = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['poster_id'];

$error   = '';
$success = '';

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buy') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: auth.php'); exit;
    }
    if (!isset($_SESSION['verified'])) {
        $error = 'Please verify your email before buying tickets.';
    } elseif ($is_owner) {
        $error = 'You cannot buy tickets for your own event.';
    } elseif ($sold_out || $is_past) {
        $error = 'Tickets are no longer available for this event.';
    } else {
        $qty = intval($_POST['quantity'] ?? 1);
        if ($qty < 1) $qty = 1;
        if ($qty > 10) $qty = 10;
        if ($qty > $remaining) $qty = $remaining;

        $total = $event['price'] * $qty;

        // Check if user already has tickets
        $check = $pdo->prepare("SELECT SUM(quantity) AS total_qty FROM orders WHERE user_id = ? AND event_id = ?");
        $check->execute([$_SESSION['user_id'], $id]);
        $existing = intval($check->fetch(PDO::FETCH_ASSOC)['total_qty']);

        if ($existing + $qty > 5) {
            $error = 'You can only hold up to 5 tickets per event.';
        } else {
            // Create order + update tickets sold
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO orders (user_id, event_id, quantity, total_price)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $id, $qty, $total]);

                $pdo->prepare("UPDATE events SET tickets_sold = tickets_sold + ? WHERE id = ?")
                    ->execute([$qty, $id]);

                $pdo->commit();
                $success = "🎉 You're in! Got $qty ticket" . ($qty > 1 ? 's' : '') . " for " . htmlspecialchars($event['title']) . ". Check your dashboard.";

                // Refresh event data
                $stmt = $pdo->prepare("SELECT e.*, u.name AS poster_name, u.id AS poster_id FROM events e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
                $stmt->execute([$id]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                $remaining = $event['capacity'] - $event['tickets_sold'];
                $sold_out  = $remaining <= 0;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($event['title']) ?> — EventLASU</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Event<span>LASU</span></a>
  <div class="nav-links">
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="create_event.php">+ Post Event</a>
      <a href="dashboard.php">My Tickets</a>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="auth.php">Login</a>
    <?php endif; ?>
  </div>
</nav>

<div class="event-detail">
  <div style="margin-bottom:20px;">
    <a href="index.php" style="color:var(--muted);text-decoration:none;font-size:14px;">← All Events</a>
  </div>

  <?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">🎉 Your event is live! Share the link with friends.</div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Event image -->
  <div class="event-detail-img">
    <?php if ($event['image']): ?>
      <img src="uploads/<?= htmlspecialchars($event['image']) ?>" alt="<?= htmlspecialchars($event['title']) ?>">
    <?php else: ?>
      🎟️
    <?php endif; ?>
  </div>

  <div class="event-detail-header">
    <div style="display:flex;gap:8px;align-items:center;margin-top:20px;">
      <span class="badge badge-green"><?= htmlspecialchars($event['category'] ?? 'Event') ?></span>
      <?php if ($is_past): ?>
        <span class="badge badge-muted">Past Event</span>
      <?php elseif ($sold_out): ?>
        <span class="badge badge-warning">Sold Out</span>
      <?php endif; ?>
    </div>
    <h1><?= htmlspecialchars($event['title']) ?></h1>
  </div>

  <div class="event-detail-body">
    <!-- Left: details -->
    <div>
      <div class="event-meta-list">
        <div class="event-meta-item">
          <span class="icon">📅</span>
          <div>
            <div class="label">Date & Time</div>
            <strong><?= date('l, F j, Y', strtotime($event['event_date'])) ?></strong>
            at <?= date('g:i A', strtotime($event['event_date'])) ?>
          </div>
        </div>
        <div class="event-meta-item">
          <span class="icon">📍</span>
          <div>
            <div class="label">Venue</div>
            <strong><?= htmlspecialchars($event['venue']) ?></strong>
          </div>
        </div>
        <div class="event-meta-item">
          <span class="icon">👤</span>
          <div>
            <div class="label">Posted by</div>
            <strong><?= htmlspecialchars($event['poster_name']) ?></strong>
            <?php if ($is_owner): ?><span class="badge badge-green" style="margin-left:6px;">You</span><?php endif; ?>
          </div>
        </div>
        <div class="event-meta-item">
          <span class="icon">🎟️</span>
          <div>
            <div class="label">Availability</div>
            <strong><?= $sold_out ? 'Sold out' : "$remaining tickets remaining" ?></strong>
            of <?= $event['capacity'] ?> total
          </div>
        </div>
      </div>

      <?php if ($event['description']): ?>
        <div style="margin-top:24px;">
          <p style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;font-weight:600;margin-bottom:12px;">About this event</p>
          <div class="event-description"><?= nl2br(htmlspecialchars($event['description'])) ?></div>
        </div>
      <?php endif; ?>

      <!-- Owner controls -->
      <?php if ($is_owner): ?>
        <div style="margin-top:32px;padding:20px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);">
          <p style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;font-weight:600;margin-bottom:12px;">Your event stats</p>
          <div style="display:flex;gap:24px;">
            <div>
              <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:var(--green);"><?= $event['tickets_sold'] ?></div>
              <div style="font-size:12px;color:var(--muted);">Tickets sold</div>
            </div>
            <div>
              <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;">₦<?= number_format($event['tickets_sold'] * $event['price'], 0) ?></div>
              <div style="font-size:12px;color:var(--muted);">Total revenue</div>
            </div>
            <div>
              <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;"><?= $remaining ?></div>
              <div style="font-size:12px;color:var(--muted);">Remaining</div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right: buy box -->
    <div>
      <div class="buy-box">
        <?php if ($event['price'] == 0): ?>
          <div class="price-big" style="font-size:28px;color:var(--text);">Free</div>
        <?php else: ?>
          <div class="price-big">₦<?= number_format($event['price'], 0) ?></div>
        <?php endif; ?>
        <div class="per">per ticket</div>

        <?php if ($is_owner): ?>
          <div class="alert alert-info">This is your event — you can't buy your own tickets.</div>
          <a href="dashboard.php" class="btn btn-outline" style="width:100%;justify-content:center;">View Dashboard</a>

        <?php elseif ($is_past): ?>
          <div class="alert alert-error">This event has already passed.</div>

        <?php elseif ($sold_out): ?>
          <div class="alert alert-error">All tickets have been sold.</div>

        <?php elseif (!isset($_SESSION['user_id'])): ?>
          <a href="auth.php" class="btn btn-primary" style="width:100%;justify-content:center;">Login to Get Tickets</a>
          <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:12px;">LASU students only · @st.lasu.edu.ng</p>

        <?php else: ?>
          <form method="POST" action="checkout.php">
  <input type="hidden" name="event_id"  value="<?= $event['id'] ?>">
  <input type="hidden" name="quantity"  id="qty-input" value="1">

  <div style="font-size:13px;color:var(--muted);margin-bottom:8px;">Quantity</div>
  <div class="qty-selector">
    <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
    <span class="qty-display" id="qty-display">1</span>
    <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
    <span style="font-size:13px;color:var(--muted);">max 5</span>
  </div>

  <!-- total display stays the same -->
  ...

  <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
    🎟️ Pay with Paystack
  </button>
</form>
            
          <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:12px;">Max 5 tickets per person</p>
        <?php endif; ?>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
          <p style="font-size:12px;color:var(--muted);">Share this event</p>
          <button onclick="navigator.clipboard.writeText(window.location.href).then(()=>alert('Link copied!'))"
                  class="btn btn-outline btn-sm" style="margin-top:8px;width:100%;justify-content:center;">
            Copy Link
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const pricePerTicket = <?= floatval($event['price']) ?>;
const maxQty = Math.min(5, <?= $remaining ?>);

let currentQty = 1; 

function changeQty(delta) {
    // Use currentQty instead of just qty
    currentQty = Math.max(1, Math.min(maxQty, currentQty + delta));
    
    document.getElementById('qty-display').textContent = currentQty;
    document.getElementById('qty-input').value = currentQty;
    
    const total = pricePerTicket * currentQty;
    const fmt = n => '₦' + n.toLocaleString('en-NG', {minimumFractionDigits: 0});
    
    // Safety check: ensure these IDs exist in your HTML
    if(document.getElementById('subtotal')) document.getElementById('subtotal').textContent = fmt(total);
    if(document.getElementById('total')) document.getElementById('total').textContent = fmt(total);
}
</script>

</body>
</html>
