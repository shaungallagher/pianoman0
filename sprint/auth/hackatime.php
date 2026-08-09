<?php
require_once '../config.php';

$page_title = 'Connect Hackatime · Sprint';

require_login();

$apiBase = rtrim(getenv('HACKATIME_API_BASE') ?: 'https://hackatime.hackclub.com', '/');

?>

<section class="container" style="margin-top:2rem;">
    <div class="card" style="max-width:560px;margin:0 auto;">
        <h1 style="margin-top:0;">Connect Hackatime</h1>
        <p class="meta">
            Paste your Hackatime API token (WakaTime-compatible). This token is used to look up your Hackatime user and link it to your Sprint account.
        </p>

        <?php if (!empty($_SESSION['profile_error'])): ?>
            <div class="error" style="margin-top:12px;"><?= htmlspecialchars($_SESSION['profile_error']) ?></div>
            <?php unset($_SESSION['profile_error']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['profile_success'])): ?>
            <div class="success" style="margin-top:12px;"><?= htmlspecialchars($_SESSION['profile_success']) ?></div>
            <?php unset($_SESSION['profile_success']); ?>
        <?php endif; ?>

        <form method="post" action="<?= url('/sprint/auth/hackatime_callback.php') ?>">
            <?= csrf_input_field() ?>

            <div style="margin-top:14px;">
                <label for="hackatime_token">Hackatime API token</label>
                <input id="hackatime_token" name="hackatime_token" type="password" required autocomplete="off" placeholder="YOUR_SECRET_TOKEN" style="width:100%;">
                <div class="meta" style="margin-top:8px;">
                    We will validate by calling <code>/api/hackatime/v1/users/current/statusbar/today</code>.
                </div>
            </div>

            <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;">
                <button class="btn" type="submit">Connect</button>
                <a class="btn outline" href="<?= url('/sprint/public/profile.php') ?>" style="display:inline-block;">Cancel</a>
            </div>
        </form>

        <p class="meta" style="margin-top:14px;">
            Hackatime API base: <code><?= htmlspecialchars($apiBase) ?></code>
        </p>
    </div>
</section>

<?php
include '../includes/footer.php';


