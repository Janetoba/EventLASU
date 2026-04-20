<?php
session_start();
require 'db.php';
require 'mailer.php';
if (file_exists('mailer.php')) {
    require_once 'mailer.php';
    if (function_exists('sendVerificationEmail')) {
        // success - do nothing
    } else {
        die("FILE EXISTS, BUT FUNCTION IS MISSING. Check spelling in mailer.php.");
    }
} else {
    die("FILE MISSING: PHP cannot find mailer.php in " . __DIR__);
}
// Already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['verified'])) {
    header('Location: index.php'); exit;
}

$error   = '';
$success = '';
$tab     = 'login'; // which tab shows by default

if (isset($_GET['unverified'])) {
    $tab   = 'login';
    $error = 'Please verify your email before continuing. Check your LASU inbox.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'];
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // ── REGISTER ──
    if ($action === 'register') {
        $tab    = 'register';
        $name   = trim($_POST['name'] ?? '');
        $matric = trim($_POST['matric_number'] ?? '');

        if (!$name || !$email || !$password) {
            $error = 'Please fill in all required fields.';
        } elseif (!str_ends_with($email, '@st.lasu.edu.ng')) {
            $error = 'Only LASU student emails (@st.lasu.edu.ng) are allowed.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password, matric_number) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$name, $email, $hashed, $matric]);
                $userId = $pdo->lastInsertId();

                // Generate token
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $pdo->prepare(
                    "INSERT INTO email_tokens (user_id, token, expires_at) VALUES (?, ?, ?)"
                )->execute([$userId, $token, $expiresAt]);

                if (sendVerificationEmail($email, $name, $token)) {
                    $tab     = 'login';
                    $success = "Account created! Check your @st.lasu.edu.ng inbox to verify your email.";
                } else {
                    $error = "Account created but we couldn't send the verification email. Contact support.";
                }
            } catch (PDOException $e) {
                $error = 'That email is already registered. Try logging in.';
            }
        }

    // ── LOGIN ──
    } elseif ($action === 'login') {
        $tab = 'login';
        if (!str_ends_with($email, '@st.lasu.edu.ng')) {
            $error = 'Only LASU student emails (@st.lasu.edu.ng) are allowed.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['verified']) {
                    $error = 'Please verify your email first. Check your LASU inbox for the verification link.';
                } else {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['verified']  = true;
                    header('Location: index.php');
                    exit;
                }
            } else {
                $error = 'Incorrect email or password.';
            }
        }

    // ── RESEND VERIFICATION ──
    } elseif ($action === 'resend') {
        $tab  = 'login';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verified = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Invalidate old tokens
            $pdo->prepare("UPDATE email_tokens SET used = 1 WHERE user_id = ?")
                ->execute([$user['id']]);
            // New token
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare(
                "INSERT INTO email_tokens (user_id, token, expires_at) VALUES (?, ?, ?)"
            )->execute([$user['id'], $token, $expiresAt]);

            sendVerificationEmail($email, $user['name'], $token);
            $success = 'Verification email resent! Check your LASU inbox.';
        } else {
            $success = 'If that email exists and is unverified, we\'ve sent a new link.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — LasuTix</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .auth-section { display: none; }
    .auth-section.active { display: block; }
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Event<span>LASU</span></a>
  <div class="nav-links">
    <a href="index.php">← Browse Events</a>
  </div>
</nav>

<div class="page-wrapper">

  <div style="text-align:center;margin-bottom:32px;">
    <div class="hero-badge" style="display:inline-flex;">LASU Students Only</div>
    <h1 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;letter-spacing:-0.5px;margin-top:12px;">
      Welcome to EventLASU
    </h1>
    <p style="color:var(--muted);font-size:14px;margin-top:6px;">Campus events, by students for students.</p>
  </div>

  <div class="form-card">

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="auth-tabs">
      <button class="auth-tab <?= $tab === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Login</button>
      <button class="auth-tab <?= $tab === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Register</button>
    </div>

    <!-- LOGIN -->
    <div class="auth-section <?= $tab === 'login' ? 'active' : '' ?>" id="tab-login">
      <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label>LASU Student Email</label>
          <input type="email" name="email" placeholder="yourname@st.lasu.edu.ng"
                 pattern=".+@st\.lasu\.edu\.ng"
                 title="Must be a LASU student email ending in @st.lasu.edu.ng"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autocomplete="email" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Your password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Login</button>
      </form>

      <div class="divider" style="margin-top:24px;">didn't get verification email?</div>
      <form method="POST" style="margin-top:0;">
        <input type="hidden" name="action" value="resend">
        <div style="display:flex;gap:8px;">
          <input type="email" name="email" placeholder="your@st.lasu.edu.ng" style="flex:1;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:inherit;font-size:14px;padding:10px 14px;outline:none;">
          <button type="submit" class="btn btn-outline btn-sm">Resend</button>
        </div>
      </form>
    </div>

    <!-- REGISTER -->
    <div class="auth-section <?= $tab === 'register' ? 'active' : '' ?>" id="tab-register">
      <form method="POST">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="As on your student ID"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Matric Number</label>
          <input type="text" name="matric_number" placeholder="e.g. 210401001"
                 value="<?= htmlspecialchars($_POST['matric_number'] ?? '') ?>">
          <div class="form-hint">Optional, but helps with verification</div>
        </div>
        <div class="form-group">
          <label>LASU Student Email *</label>
          <input type="email" name="email" placeholder="yourname@st.lasu.edu.ng"
                 pattern=".+@st\.lasu\.edu\.ng"
                 title="Must be a LASU student email ending in @st.lasu.edu.ng"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autocomplete="email" required>
          <div class="form-hint">Only @st.lasu.edu.ng emails are accepted</div>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
          Create Account
        </button>
        <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:12px;">
          A verification link will be sent to your LASU email
        </p>
      </form>
    </div>

  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.auth-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.auth-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.target.classList.add('active');
}

// Auto-switch to active tab from PHP
const activeTab = '<?= $tab ?>';
document.querySelectorAll('.auth-tab').forEach((btn, i) => {
  if ((activeTab === 'login' && i === 0) || (activeTab === 'register' && i === 1)) {
    btn.classList.add('active');
  } else {
    btn.classList.remove('active');
  }
});
</script>

</body>
</html>
