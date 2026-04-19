<?php
session_start();
require 'db.php';

// Fetch upcoming events with poster's name
$stmt = $pdo->query("
    SELECT e.*, u.name AS poster_name
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.event_date >= NOW()
    ORDER BY e.event_date ASC
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = ['Music', 'Tech', 'Sports', 'Party', 'Academic', 'Comedy', 'Fashion', 'Food', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EventLASU — Campus Events</title>
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
      <a href="auth.php" class="btn btn-primary btn-sm">Register</a>
    <?php endif; ?>
  </div>
</nav>

<?php if (isset($_SESSION['user_id'])): ?>
<div style="max-width:1100px;margin:0 auto;padding:16px 24px 0;">
  <div class="alert alert-success" style="margin:0;">
    👋 Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> — ready to grab tickets or
    <a href="create_event.php" style="color:var(--green);font-weight:600;">post your own event?</a>
  </div>
</div>
<?php endif; ?>

<div class="hero">
  <div class="hero-badge">LASU Students Only</div>
  <h1>Campus events,<br><em>by students</em> for students.</h1>
  <p>Buy & sell tickets for parties, concerts, seminars and more — exclusively for LASU.</p>
  <?php if (!isset($_SESSION['user_id'])): ?>
    <a href="auth.php" class="btn btn-primary">Get Started Free</a>
  <?php endif; ?>
</div>

<div class="section">

  <!-- Category filter -->
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
    <button class="btn btn-sm btn-primary" onclick="filterEvents('all')">All</button>
    <?php foreach ($categories as $cat): ?>
      <button class="btn btn-sm btn-outline" onclick="filterEvents('<?= $cat ?>')"><?= $cat ?></button>
    <?php endforeach; ?>
  </div>

  <div class="section-header">
    <span class="section-title">Upcoming Events</span>
    <span class="section-count"><?= count($events) ?> event<?= count($events) !== 1 ? 's' : '' ?></span>
  </div>

  <?php if (empty($events)): ?>
    <div class="empty-state">
      <div class="icon">🎟️</div>
      <h3>No events yet</h3>
      <p>Be the first to post an event on LasuTix!</p>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="create_event.php" class="btn btn-primary" style="margin-top:16px;">Post an Event</a>
      <?php else: ?>
        <a href="auth.php" class="btn btn-primary" style="margin-top:16px;">Register to Post</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="events-grid" id="events-grid">
      <?php foreach ($events as $event):
        $sold_out = $event['tickets_sold'] >= $event['capacity'];
        $low = !$sold_out && ($event['capacity'] - $event['tickets_sold']) <= 10;
        $remaining = $event['capacity'] - $event['tickets_sold'];
      ?>
        <div class="event-card" data-category="<?= htmlspecialchars($event['category'] ?? 'Other') ?>">
          <div class="event-card-img">
            <?php if ($event['image']): ?>
              <img src="uploads/<?= htmlspecialchars($event['image']) ?>" alt="<?= htmlspecialchars($event['title']) ?>">
            <?php else: ?>
              🎟️
            <?php endif; ?>
          </div>
          <div class="event-card-body">
            <div class="event-card-category"><?= htmlspecialchars($event['category'] ?? 'Event') ?></div>
            <h3><?= htmlspecialchars($event['title']) ?></h3>
            <div class="event-card-meta">
              <span class="meta-date"><?= date('D, M j, Y · g:i A', strtotime($event['event_date'])) ?></span>
              <span class="meta-venue"><?= htmlspecialchars($event['venue']) ?></span>
              <span class="meta-host">Posted by <?= htmlspecialchars($event['poster_name']) ?></span>
            </div>
          </div>
          <div class="event-card-footer">
            <div>
              <?php if ($event['price'] == 0): ?>
                <span class="price-free">Free Entry</span>
              <?php else: ?>
                <span class="price">₦<?= number_format($event['price'], 0) ?></span>
              <?php endif; ?>
              <div class="tickets-left <?= $sold_out ? 'tickets-out' : ($low ? 'tickets-low' : '') ?>">
                <?php if ($sold_out): ?>
                  Sold out
                <?php elseif ($low): ?>
                  Only <?= $remaining ?> left!
                <?php else: ?>
                  <?= $remaining ?> tickets left
                <?php endif; ?>
              </div>
            </div>
            <?php if ($sold_out): ?>
              <span class="badge badge-muted">Sold Out</span>
            <?php else: ?>
              <a href="event.php?id=<?= $event['id'] ?>" class="btn btn-primary btn-sm">View →</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
function filterEvents(category) {
  const cards = document.querySelectorAll('.event-card');
  cards.forEach(card => {
    if (category === 'all' || card.dataset.category === category) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
  // Update button styles
  document.querySelectorAll('[onclick^="filterEvents"]').forEach(btn => {
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-outline');
  });
  event.target.classList.add('btn-primary');
  event.target.classList.remove('btn-outline');
}
</script>

</body>
</html>
