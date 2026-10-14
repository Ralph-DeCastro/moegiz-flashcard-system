<?php
session_start();
require __DIR__ . '/db.php';

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Must be logged in
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// Students only
if (($_SESSION['role'] ?? '') !== 'Student') {
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moegiz &middot; Access denied</title>
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="denied">
    <h1>Students only</h1>
    <p>This dashboard is for student accounts.</p>
    <a href="dashboard.php?logout=1">Log out</a>
  </div>
</body>
</html>
    <?php
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Load fresh user info
$stmt = $db->prepare('SELECT username, email, created_at FROM users WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { // account deleted while logged in
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// Simple stats
$stmt = $db->prepare('SELECT COUNT(*) FROM decks WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($deckCount);
$stmt->fetch();
$stmt->close();

$stmt = $db->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($quizCount);
$stmt->fetch();
$stmt->close();

$username = $user['username'];
$initial  = mb_strtoupper(mb_substr($username, 0, 1));
$since    = date('M Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moegiz &middot; Home</title>
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <h1 class="brand">Moe<span>Giz</span></h1>
    <nav class="nav">
      <a href="dashboard.php" class="active">Home</a>
      <a href="my_decks.php">My decks</a>
      <a href="public_decks.php">Public decks</a>
      <a href="profile.php">Profile</a>
    </nav>
  </aside>

  <!-- main-->
  <div class="main">

    <header class="topbar">
      <div class="user-info">
        <span class="avatar"><?= e($initial) ?></span>
        <div class="meta">
          <div class="name"><?= e($username) ?></div>
          <div class="sub"><?= e($user['email']) ?> &middot; Student since <?= e($since) ?></div>
        </div>
      </div>

      <div class="actions">
        <!-- Notifications -->
        <div class="dropdown">
          <button class="icon-btn" type="button" aria-label="Notifications" data-toggle="notif-menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
            </svg>
          </button>
          <div class="menu" id="notif-menu">
            <div class="empty">No new notifications</div>
          </div>
        </div>

        <!-- Profile dropdown -->
        <div class="dropdown">
          <button class="icon-btn avatar" type="button" aria-label="Account menu" data-toggle="profile-menu"><?= e($initial) ?></button>
          <div class="menu" id="profile-menu">
            <div class="who">Signed in as <strong><?= e($username) ?></strong></div>
            <hr>
            <a href="profile.php">Profile</a>
            <a href="settings.php">Settings</a>
            <hr>
            <a href="dashboard.php?logout=1" class="logout">Logout</a>
          </div>
        </div>
      </div>
    </header>

    <h2 class="page-title">Home</h2>

    <section class="action-cards">
      <a class="action-card" href="create_deck.php">
        <p class="label">Paste</p>
        <p class="desc">Enter topics to generate flashcards</p>
      </a>
      <a class="action-card" href="quiz.php">
        <p class="label">Quiz</p>
        <p class="desc">Pick a deck and answer a quiz</p>
      </a>
    </section>

    <section class="welcome">
      <h2>Welcome back, <?= e($username) ?>!</h2>
      <p>Ready to learn something new today? Create a deck or jump into a quiz to keep your streak going.</p>
      <div class="stats">
        <div class="stat">
          <div class="num"><?= (int)$deckCount ?></div>
          <div class="lbl">My decks</div>
        </div>
        <div class="stat">
          <div class="num"><?= (int)$quizCount ?></div>
          <div class="lbl">Quizzes taken</div>
        </div>
      </div>
    </section>

  </div>
</div>

<script>
  // Simple dropdown toggles: one open at a time, close on outside click / Esc
  const menus = document.querySelectorAll('.menu');
  document.querySelectorAll('[data-toggle]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const target = document.getElementById(btn.dataset.toggle);
      const wasOpen = target.classList.contains('open');
      menus.forEach(m => m.classList.remove('open'));
      if (!wasOpen) target.classList.add('open');
    });
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('.menu')) menus.forEach(m => m.classList.remove('open'));
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') menus.forEach(m => m.classList.remove('open'));
  });
</script>
</body>
</html>
