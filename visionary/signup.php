<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
require_once __DIR__ . '/includes/app_layout.php';
if (!isset($_SESSION['signup_count'])) $_SESSION['signup_count'] = [];
// prune older than 1 hour
$_SESSION['signup_count'] = array_filter($_SESSION['signup_count'], function($t){ return $t > time() - 3600; });

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf()) {
    $error = 'Invalid form submission';
  } elseif (count($_SESSION['signup_count']) >= 3) {
    $error = 'Too many signup attempts. Try again later.';
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'poster';
    $allowedRoles = ['poster', 'dev', 'both'];
    if (!in_array($role, $allowedRoles, true)) {
      $role = 'poster';
    }
    if ($username === '' || $password === '') {
      $error = 'Username and password required';
    } elseif (!validate_username($username)) {
      $error = 'Username must be 3-30 characters and use letters, numbers, or underscores';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Invalid email address';
    } else {
      $bad = check_forbidden($username, 'username', ['username'=>$username], 'Signup attempt');
      if ($bad) {
        $error = forbidden_message('username', $bad);
      } else {
        $pdo = getPDO();
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
          $error = 'Username taken';
        } else {
          $pw = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, email, created_at) VALUES (?, ?, ?, ?, ?)');
          $stmt->execute([$username, $pw, $role, $email ?: null, date('c')]);
          $id = $pdo->lastInsertId();
          $_SESSION['signup_count'][] = time();
          login_user_by_id($id);
          session_regenerate_id(true);
          header('Location: ideas.php');
          exit;
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign up — Visionary</title>
    <meta name="description" content="Visionary - A platform for developers and idea crafters to collaborate on innovative ideas.">
    <meta name="author" content="PianoMan0">
    <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
  </head>
  <body>
    <?php render_app_header('Sign Up', 'Create your Visionary account and start sharing ideas.', $user); ?>
    <main class="container">
      <section class="page-card">
        <div class="page-heading">
          <div>
            <h1>Sign up for Visionary</h1>
            <p>Join the community of builders, makers, and idea collaborators. Create your profile and start posting ideas instantly.</p>
          </div>
          <div class="auth-meta">
            Already a member? <a href="login.php">Log in</a>
          </div>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error"><?=h($error)?></div>
        <?php endif; ?>

        <form method="post" class="form-card">
          <?=csrf_field()?>
          <div class="form-group">
            <label for="username">Username</label>
            <input id="username" name="username" placeholder="Choose a username" required>
            <small>3-30 characters, letters, numbers, or underscores only.</small>
          </div>

          <div class="form-group">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" placeholder="you@example.com">
            <small>Optional — used for account recovery and notifications.</small>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="Create a strong password" required>
          </div>

          <div class="form-group">
            <label>Role</label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
              <label class="provider-button button-secondary" style="padding: 12px 14px;justify-content:flex-start;">
                <input type="radio" name="role" value="poster" checked style="margin-right:10px"> Idea Poster
              </label>
              <label class="provider-button button-secondary" style="padding: 12px 14px;justify-content:flex-start;">
                <input type="radio" name="role" value="dev" style="margin-right:10px"> Developer
              </label>
              <label class="provider-button button-secondary" style="padding: 12px 14px;justify-content:flex-start;">
                <input type="radio" name="role" value="both" style="margin-right:10px"> Both
              </label>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="button-primary">Create account</button>
          </div>
        </form>

        <div class="divider"><span>Or continue with</span></div>
        <div class="provider-buttons">
          <a class="provider-button github" href="github_callback.php?oauth=1">GitHub</a>
          <a class="provider-button hackatime" href="hackatime_callback.php?oauth=1">Hackatime</a>
        </div>
      </section>
    </main>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
  </body>
</html>
