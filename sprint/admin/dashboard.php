<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/roles.php';

require_role('admin');

$page_title = "Admin Dashboard · Sprint";
include '../includes/header.php';

try {
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $userCount = 0;
}

try {
    $eventCount = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
} catch (Exception $e) {
    $eventCount = 0;
}

try {
    $submissionCount = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
} catch (Exception $e) {
    $submissionCount = 0;
}

$oauthTableRows = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM oauth_accounts");
    $oauthTableRows = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $oauthTableRows = 0;
}

?>

<h1>Admin Dashboard</h1>

<div class="card-grid" style="margin-top:1.25rem;">
    <div class="card sunken">
        <h2>Overview</h2>
        <ul class="list" style="margin-top:0.75rem;">
            <li><strong>Users:</strong> <?= htmlspecialchars((string)$userCount) ?></li>
            <li><strong>Events:</strong> <?= htmlspecialchars((string)$eventCount) ?></li>
            <li><strong>Submissions:</strong> <?= htmlspecialchars((string)$submissionCount) ?></li>
            <li><strong>OAuth links:</strong> <?= htmlspecialchars((string)$oauthTableRows) ?></li>
        </ul>
    </div>

    <a class="card" href="oauth_admin.php" style="text-decoration:none;">
        <h2>OAuth Accounts</h2>
        <p>Inspect linked OAuth accounts and view masked tokens.</p>
    </a>

    <a class="card" href="logs.php" style="text-decoration:none;">
        <h2>Application Logs</h2>
        <p>View and clear db error logs.</p>
    </a>

    <a class="card" href="db_status.php" style="text-decoration:none;">
        <h2>DB Status</h2>
        <p>Check connectivity to the database.</p>
    </a>


    <a class="card" href="../public/profile.php" style="text-decoration:none;">
        <h2>Profile</h2>
        <p>Manage linked accounts from your profile.</p>
    </a>
</div>

<div class="card" style="margin-top:1.25rem;">
    <h2>Admin-only tools</h2>
    <p class="meta">
        Use these pages to troubleshoot auth providers and database connectivity.
    </p>

    <ul class="list" style="margin-top:0.75rem;">
        <li>
            <a href="../admin/oauth_admin.php">OAuth Accounts</a>
        </li>
        <li>
            <a href="../admin/logs.php">Logs</a>
        </li>
        <li>
            <a href="../admin/db_status.php">DB Status</a>
        </li>
    </ul>
</div>

<?php include '../includes/footer.php'; ?>

