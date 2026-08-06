<?php
require_once '../config.php';
require_role('admin');

$page_title = "OAuth Accounts · Sprint";
include '../includes/header.php';

try {
    $stmt = $pdo->query("SELECT oa.*, u.email AS user_email, u.name AS user_name FROM oauth_accounts oa JOIN users u ON oa.user_id = u.id ORDER BY oa.created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

function mask($s) {
    if (!$s) return '';
    $len = strlen($s);
    if ($len <= 10) return str_repeat('*', $len);
    return substr($s,0,4) . str_repeat('*', max(0,$len-8)) . substr($s,-4);
}

?>
<h1>OAuth Accounts</h1>

<p>Listing of OAuth-linked accounts (provider, user, tokens masked).</p>

<table class="table">
    <thead>
        <tr><th>User</th><th>Provider</th><th>Provider ID</th><th>Access Token</th><th>Refresh Token</th><th>Expires At</th><th>Linked At</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['user_name'] . ' <' . $r['user_email'] . '>') ?></td>
            <td><?= htmlspecialchars($r['provider']) ?></td>
            <td><?= htmlspecialchars($r['provider_user_id']) ?></td>
            <td><code><?= htmlspecialchars(mask($r['access_token'])) ?></code></td>
            <td><code><?= htmlspecialchars(mask($r['refresh_token'])) ?></code></td>
            <td><?= htmlspecialchars($r['expires_at']) ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>

