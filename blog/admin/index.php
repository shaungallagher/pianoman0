<?php
session_start();
require_once __DIR__ . '/../includes.php';
if (!empty($_SESSION['blog_admin'])) {
    header('Location: dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    $admin_pw = env_get('ADMIN_PASSWORD', '');
    if ($pw === $admin_pw && $admin_pw !== '') {
        $_SESSION['blog_admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login — Blog</title>
  <link rel="stylesheet" href="/style.css">
  <style>
    .login-wrap { max-width:420px; margin:120px auto; padding:24px; background:var(--main-header-background); border:2px solid var(--main-decor-color); border-radius:12px; }
    input[type=password] { width:100%; padding:10px; border-radius:6px; border:1px solid #333; background:#111; color:#fff; }
    button { background:var(--main-decor-color); color:#000; padding:10px 14px; border-radius:6px; border:none; font-weight:700; }
    .error { color:#ff6b6b; margin-bottom:10px; }
  </style>
</head>
<body>
  <div class="login-wrap">
    <h3 style="color:var(--main-decor-color)">Admin Login</h3>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="">
      <label>Password</label>
      <input type="password" name="password" autocomplete="current-password" />
      <div style="margin-top:12px"><button type="submit">Log in</button></div>
    </form>
  </div>
</body>
</html>
