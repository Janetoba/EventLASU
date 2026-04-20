<?php
require 'auth_check.php';
requireVerifiedStudent();
require 'db.php';

$userId = $_SESSION['user_id'];

// Events this student posted
$myEvents = $pdo->prepare("
    SELECT e.*,
           COUNT(o.id) AS order_count,
           COALESCE(SUM(o.quantity), 0) AS total_sold,
           COALESCE(SUM(o.total_price), 0) AS total_revenue
    FROM events e
    LEFT JOIN orders o ON o.event_id = e.id
    WHERE e.user_id = ?
    GROUP BY e.id
    ORDER BY e.event_date DESC
");
$myEvents->execute([$userId]);
$myEvents = $myEvents->fetchAll(PDO::FETCH_ASSOC);

// Tickets this student bought
$myTickets = $pdo->prepare("
    SELECT o.*, e.title AS event_title, e.venue, e.event_date,
           e.image, u.name AS poster_name, e.id AS event_id
    FROM orders o
    JOIN events e ON o.event_id = e.id
    JOIN users u ON e.user_id = u.id
    WHERE o.user_id = ?
    ORDER BY o.ordered_at DESC
");
$myTickets->execute([$userId]);
$myTickets = $myTickets->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$totalSpent    = array_sum(array_column($myTickets, 'total_price'));
$totalRevenue  = array_sum(array_column($myEvents, 'total_revenue'));
$upcomingCount = count(array_filter($myTickets, fn($t) => strtotime($t['event_date']) > time()));
?>

<?php if (isset($_GET['paid'])): ?>
  <div class="alert alert-success">🎉 Payment confirmed! Your ticket is secured.</div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — LasuTix</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Event<span>LASU</span></a>
  <div class="nav-links">
    <a href="create_event.php">+ Post Event</a>
    <a href="index.php">Browse</a>
    <a href="logout.php">Logout</a>
  </div>
</nav>

<div class="section" style="padding-top:40px;">

  <div style="margin-bottom:32px;">
    <p style="color:var(--muted);font-size:14px;">Welcome back</p>
    <h1 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;letter-spacing:-0.5px;">
      <?= htmlspecialchars($_SESSION['user_name']) ?>
    </h1>
  </div>

  <!-- Stats -->
  <div class="dashboard-grid" style="margin-bottom:40px;">
    <div class="stat-card">
      <div class="stat-label">Upcoming Tickets</div>
      <div class="stat-value" style="color:var(--green);"><?= $upcomingCount ?></div>
      <div class="stat-sub"><?= count($myTickets) ?> total purchased</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Events Posted</div>
      <div class="stat-value"><?= count($myEvents) ?></div>
      <div class="stat-sub"><?= array_sum(array_column($myEvents, 'total_sold')) ?> tickets sold across all</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Spent</div>
      <div class="stat-value">₦<?= number_format($totalSpent, 0) ?></div>
      <div class="stat-sub">on tickets from other events</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Revenue Earned</div>
      <div class="stat-value" style="color:var(--green);">₦<?= number_format($totalRevenue, 0) ?></div>
      <div class="stat-sub">from your posted events</div>
    </div>
  </div>

  <!-- My Tickets -->
  <div class="section-header">
    <span class="section-title">My Tickets</span>
    <span class="section-count"><?= count($myTickets) ?> purchased</span>
  </div>

  <?php if (empty($myTickets)): ?>
    <div class="empty-state" style="padding:40px 0;">
      <div class="icon">🎟️</div>
      <h3>No tickets yet</h3>
      <p>Browse events and grab your first ticket</p>
      <a href="index.php" class="btn btn-primary" style="margin-top:16px;">Browse Events</a>
    </div>
  <?php else: ?>
    <?php foreach ($myTickets as $ticket):
      $isPast = strtotime($ticket['event_date']) < time();
    ?>
      <div class="ticket-item">
        <div class="ticket-dot <?= $isPast ? 'past' : '' ?>"></div>
        <div class="ticket-info">
          <h4>
            <a href="event.php?id=<?= $ticket['event_id'] ?>" style="color:var(--text);text-decoration:none;">
              <?= htmlspecialchars($ticket['event_title']) ?>
            </a>
          </h4>
          <span>
            <?= htmlspecialchars($ticket['venue']) ?> ·
            <?= date('D, M j, Y · g:i A', strtotime($ticket['event_date'])) ?>
            <?= $isPast ? ' <span class="badge badge-muted" style="margin-left:4px;">Past</span>' : '' ?>
          </span>
          <div style="font-size:12px;color:var(--muted);margin-top:4px;">
            Bought <?= date('M j, Y', strtotime($ticket['ordered_at'])) ?> ·
            Posted by <?= htmlspecialchars($ticket['poster_name']) ?>
          </div>
        </div>
        <div style="text-align:right;">
          <div class="ticket-qty"><?= $ticket['quantity'] ?> ticket<?= $ticket['quantity'] > 1 ? 's' : '' ?></div>
          <div style="font-size:13px;color:var(--muted);margin-top:2px;">
            <?= $ticket['total_price'] == 0 ? 'Free' : '₦' . number_format($ticket['total_price'], 0) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- My Posted Events -->
  <div class="section-header" style="margin-top:48px;">
    <span class="section-title">Events I Posted</span>
    <a href="create_event.php" class="btn btn-primary btn-sm">+ New Event</a>
  </div>

  <?php if (empty($myEvents)): ?>
    <div class="empty-state" style="padding:40px 0;">
      <div class="icon">🎪</div>
      <h3>No events posted yet</h3>
      <p>List your event and start selling tickets to fellow LASU students</p>
      <a href="create_event.php" class="btn btn-primary" style="margin-top:16px;">Post an Event</a>
    </div>
  <?php else: ?>
    <?php foreach ($myEvents as $event):
      $isPast = strtotime($event['event_date']) < time();
      $pct    = $event['capacity'] > 0 ? ($event['total_sold'] / $event['capacity']) * 100 : 0;
    ?>
      <div class="ticket-item" style="flex-direction:column;align-items:flex-start;gap:14px;">
        <div style="display:flex;width:100%;justify-content:space-between;align-items:flex-start;">
          <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
              <h4>
                <a href="event.php?id=<?= $event['id'] ?>" style="color:var(--text);text-decoration:none;">
                  <?= htmlspecialchars($event['title']) ?>
                </a>
              </h4>
              <?php if ($isPast): ?>
                <span class="badge badge-muted">Past</span>
              <?php else: ?>
                <span class="badge badge-green">Live</span>
              <?php endif; ?>
            </div>
            <span style="font-size:13px;color:var(--muted);">
              <?= htmlspecialchars($event['venue']) ?> ·
              <?= date('D, M j, Y', strtotime($event['event_date'])) ?>
            </span>
          </div>
          <div style="text-align:right;">
            <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--green);">
              ₦<?= number_format($event['total_revenue'], 0) ?>
            </div>
            <div style="font-size:12px;color:var(--muted);"><?= $event['total_sold'] ?> sold</div>
          </div>
        </div>
        <!-- Progress bar -->
        <div style="width:100%;">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px;">
            <span><?= $event['total_sold'] ?> / <?= $event['capacity'] ?> tickets sold</span>
            <span><?= round($pct) ?>%</span>
          </div>
          <div style="height:5px;background:rgba(255,255,255,0.08);border-radius:100px;overflow:hidden;">
            <div style="height:100%;width:<?= min(100, $pct) ?>%;background:var(--green);border-radius:100px;transition:width .4s;"></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

</body>
</html>
