<nav class="nav">
    <a href="<?= url('public/index.php') ?>">Events</a>

    <?php if (current_user_id()): ?>
        <span class="nav-user">Hi, <?= htmlspecialchars(current_user()['name']) ?></span>

        <?php if (is_admin()): ?>
            <a href="<?= url('sprint/admin/dashboard.php') ?>">Admin Dashboard</a>
        <?php endif; ?>

        <a href="<?= url('sprint/auth/logout.php') ?>">Logout</a>
    <?php else: ?>
        <?php if (getenv('HACKCLUB_CLIENT_ID')): ?>
            <a href="<?= url('sprint/auth/oauth.php') ?>">Login</a>
        <?php endif; ?>
    <?php endif; ?>
</nav>
