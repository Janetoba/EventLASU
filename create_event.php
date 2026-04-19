<?php
require 'auth_check.php';
requireVerifiedStudent();
require 'db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $venue       = trim($_POST['venue'] ?? '');
    $event_date  = $_POST['event_date'] ?? '';
    $price       = floatval($_POST['price'] ?? 0);
    $capacity    = intval($_POST['capacity'] ?? 0);
    $category    = $_POST['category'] ?? 'Other';

    // Validation
    if (!$title || !$venue || !$event_date || !$capacity) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($event_date) <= time()) {
        $error = 'Event date must be in the future.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } elseif ($capacity < 1 || $capacity > 10000) {
        $error = 'Capacity must be between 1 and 10,000.';
    } else {
        // Handle image upload
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            $fileSize = $_FILES['image']['size'];

            if (!in_array($fileType, $allowed)) {
                $error = 'Image must be JPG, PNG, WEBP or GIF.';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $error = 'Image must be under 5MB.';
            } else {
                if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                $ext    = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image  = uniqid('event_', true) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $image);
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("
                INSERT INTO events (user_id, title, description, venue, event_date, price, capacity, category, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], $title, $description,
                $venue, $event_date, $price, $capacity, $category, $image
            ]);
            $eventId = $pdo->lastInsertId();
            header("Location: event.php?id=$eventId&created=1");
            exit;
        }
    }
}

$categories = ['Music', 'Tech', 'Sports', 'Party', 'Academic', 'Comedy', 'Fashion', 'Food', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Post an Event — EventLASU</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Event<span>LASU</span></a>
  <div class="nav-links">
    <a href="dashboard.php">My Tickets</a>
    <a href="logout.php">Logout</a>
  </div>
</nav>

<div class="page-wrapper wide">
  <div style="margin-bottom:24px;">
    <a href="index.php" style="color:var(--muted);text-decoration:none;font-size:14px;">← Back to events</a>
  </div>

  <div class="form-card">
    <h2>Post an Event</h2>
    <p class="subtitle">List your event and start selling tickets to LASU students.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

      <div class="form-group">
        <label>Event Title *</label>
        <input type="text" name="title" placeholder="e.g. LASU End of Year Party 2025"
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Category *</label>
          <select name="category">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>>
                <?= $cat ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Venue *</label>
          <input type="text" name="venue" placeholder="e.g. LASU Sports Complex"
                 value="<?= htmlspecialchars($_POST['venue'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Date & Time *</label>
          <input type="datetime-local" name="event_date"
                 value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>"
                 min="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
        <div class="form-group">
          <label>Total Tickets (Capacity) *</label>
          <input type="number" name="capacity" placeholder="e.g. 200" min="1" max="10000"
                 value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Ticket Price (₦)</label>
          <input type="number" name="price" placeholder="0 for free" min="0" step="50"
                 value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
          <div class="form-hint">Enter 0 to make this a free event.</div>
        </div>
        <div class="form-group">
          <label>Event Flyer / Image</label>
          <input type="file" name="image" accept="image/*"
                 style="padding:10px 16px;cursor:pointer;">
          <div class="form-hint">JPG, PNG or WEBP · Max 5MB</div>
        </div>
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea name="description" placeholder="Tell people what to expect — performers, dress code, what's included..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:12px;margin-top:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">
          🎟️ Post Event
        </button>
        <a href="index.php" class="btn btn-outline">Cancel</a>
      </div>

    </form>
  </div>

  <!-- Tips card -->
  <div class="form-card" style="margin-top:20px;padding:24px;">
    <p style="font-size:13px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:12px;">Tips for a great listing</p>
    <ul style="font-size:14px;color:var(--muted);display:flex;flex-direction:column;gap:8px;padding-left:20px;">
      <li>Add a clear event flyer — listings with images get 3× more views</li>
      <li>Mention dress code, performers or guest speakers in the description</li>
      <li>Set an accurate capacity — you can't sell more tickets than this</li>
      <li>Free events still need registration so you know your headcount</li>
    </ul>
  </div>
</div>

</body>
</html>
