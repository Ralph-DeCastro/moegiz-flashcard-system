<?php
session_start();
require __DIR__ . '/db.php';

// Already logged in? Go to dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$mode   = (($_GET['mode'] ?? '') === 'register') ? 'register' : 'login';
$errors = [];
$notice = '';
$old    = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ---------- REGISTER ----------
        if ($action === 'register') {
            $mode     = 'register';
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm'] ?? '';
            $old      = ['username' => $username, 'email' => $email];

            if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
                $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore).';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                $errors[] = 'Please enter a valid email address.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if ($password !== $confirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (!$errors) {
                $stmt = $db->prepare('SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1');
                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $errors[] = 'That username or email is already registered.';
                }
                $stmt->close();
            }

            if (!$errors) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'Student'; // public sign-ups are always students
                try {
                    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('ssss', $username, $email, $hash, $role);
                    $stmt->execute();
                    $stmt->close();
                    $mode   = 'login';
                    $old    = ['username' => $username, 'email' => ''];
                    $notice = 'Account created! You can now log in.';
                } catch (mysqli_sql_exception $e) {
                    $errors[] = 'Could not create account. Please try again.';
                }
            }
        }

        // ---------- LOGIN ----------
        if ($action === 'login') {
            $mode       = 'login';
            $identifier = trim($_POST['identifier'] ?? '');
            $password   = $_POST['password'] ?? '';
            $old['username'] = $identifier;

            if ($identifier === '' || $password === '') {
                $errors[] = 'Please enter your username/email and password.';
            } else {
                $stmt = $db->prepare('SELECT user_id, username, password_hash, role FROM users WHERE username = ? OR email = ? LIMIT 1');
                $stmt->bind_param('ss', $identifier, $identifier);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = (int)$user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = $user['role'];
                    $_SESSION['csrf']     = bin2hex(random_bytes(32));
                    header('Location: dashboard.php');
                    exit;
                }
                $errors[] = 'Incorrect username/email or password.';
            }
        }
    }
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moegiz &middot; <?= $mode === 'register' ? 'Create account' : 'Log in' ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="card">
    <h1 class="brand">Moe<span>giz</span></h1>
    <p class="tagline">Learn smarter with flashcards.</p>

    <nav class="tabs">
      <a href="index.php" class="<?= $mode === 'login' ? 'active' : '' ?>">Log in</a>
      <a href="index.php?mode=register" class="<?= $mode === 'register' ? 'active' : '' ?>">Sign up</a>
    </nav>

    <?php if ($notice): ?>
      <div class="alert ok"><?= e($notice) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert error">
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($mode === 'register'): ?>
      <form method="post" action="index.php?mode=register" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="register">

        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required maxlength="50">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="100">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

        <label for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">

        <button type="submit">Create account</button>
      </form>
    <?php else: ?>
      <form method="post" action="index.php" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="login">

        <label for="identifier">Username or email</label>
        <input type="text" id="identifier" name="identifier" value="<?= e($old['username']) ?>" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit">Log in</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
