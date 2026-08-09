<?php
require_once '../config.php';
require_role('admin');

$logFile = __DIR__ . '/../logs/db_errors.log';
$cleared = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (validate_csrf_token($token) && file_exists($logFile)) {
        file_put_contents($logFile, "");
        $cleared = true;
    }
}

$contents = file_exists($logFile) ? file_get_contents($logFile) : '';

$page_title = "Logs · Sprint";
include '../includes/header.php';
?>

<h1>Application Logs</h1>

<?php if ($cleared): ?>
    <p class="flash">Logs cleared.</p>
<?php endif; ?>

<form method="post">
    <?= csrf_input_field() ?>
    <button class="btn">Clear logs</button>
    <a class="btn" href="<?= url('/sprint/admin/db_status.php') ?>">DB Status</a>
</form>

<h2>db_errors.log</h2>
<pre class="card sunken" style="white-space:pre-wrap;max-height:400px;overflow:auto;"><?= htmlspecialchars($contents) ?></pre>

<?php include '../includes/footer.php'; ?>

