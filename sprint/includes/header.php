<?php if (!isset($page_title)) $page_title = "Sprint"; ?>
<?php
// If maintenance mode is enabled, show the maintenance page to non-admins.
if (!empty($maintenance_mode) && !(function_exists('current_user') && function_exists('is_admin') && is_admin())) {
    // Output a maintenance page; admins may still access the site while maintenance is enabled.
    include __DIR__ . '/maintenance.php';
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="<?= url('/sprint/assets/all.css') ?>">

    <script src="<?= url('/sprint/assets/app.js') ?>" defer></script>
    <script src="<?= url('/sprint/assets/ui.js') ?>" defer></script>



    <link rel="icon" href="data:," />

</head>

<body>
<header class="topbar fade-in" role="banner">

    <div class="logo"><a href="<?= url('/sprint/public/index.php') ?>">Sprint</a></div>

    <nav class="nav">
        <input id="event-search" type="search" placeholder="Search events…" aria-label="Search events" />

        <?php if (current_user_id()): ?>
            <?php if (is_admin()): ?>
                <a href="<?= url('/sprint/admin/dashboard.php') ?>">Admin</a>
            <?php else: ?>
                <a href="<?= url('/sprint/admin/admin_login.php') ?>">Admin</a>
            <?php endif; ?>



            <?php if (is_organizer()): ?>
                <a href="<?= url('/sprint/organizer/dashboard.php') ?>">Organizer</a>
            <?php endif; ?>

            <?php if (is_judge()): ?>
                <a href="<?= url('/sprint/judge/dashboard.php') ?>">Judge</a>
            <?php endif; ?>

            <span class="nav-user">
                <img src="<?= htmlspecialchars(user_avatar_url(current_user(), 32), ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:32px;height:32px;border-radius:999px;vertical-align:middle;margin-right:8px;">
                <span style="vertical-align:middle;display:inline-block;">
                    <?= htmlspecialchars(current_user()['name']) ?>
                    <?php if (!empty(current_user()['verification_status'])): ?>
                        <span title="Verified" style="color:green;margin-left:6px;">✓</span>
                    <?php endif; ?>
                    <br>
                    <small style="opacity:0.85;display:block;line-height:1;"><?= htmlspecialchars(current_user()['email']) ?></small>
                </span>
            </span>
            <a href="<?= url('/sprint/public/profile.php') ?>">Profile</a>
            <a href="<?= url('/sprint/public/report_incident.php') ?>">Report</a>
            <a href="<?= url('/sprint/auth/logout.php') ?>">Logout</a>
        <?php else: ?>
            <a href="<?= url('/sprint/auth/login.php') ?>">Login</a>
        <?php endif; ?>

        <button id="theme-toggle" class="btn" style="margin-left:1rem;">Theme</button>
    </nav>
</header>

<?php if (!empty($db_connection_failed)): ?>
    <div style="background:#fff3cd;color:#856404;padding:8px;text-align:center;border-bottom:1px solid #f0e6b6;">
        Database connection unavailable — site running in degraded mode; some features may be limited. 
        <a href="<?= url('/sprint/admin/db_status.php') ?>">Check DB status (admin only)</a>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['oauth_redirect_mismatch'])): ?>
    <div style="background:#fff3cd;color:#856404;padding:10px;border-radius:6px;margin:12px 0;">
        <?= htmlspecialchars($_SESSION['oauth_redirect_mismatch']) ?>
    </div>
    <?php unset($_SESSION['oauth_redirect_mismatch']); ?>
<?php endif; ?>

<main class="container fade-in">
