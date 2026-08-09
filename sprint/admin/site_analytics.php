<?php
require_once '../config.php';
require_role('organizer');

// Basic site-wide metrics
$totUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totEvents = $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$totTeams = $pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
$totSubmissions = $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();

$topEventsStmt = $pdo->query('SELECT e.name, COUNT(s.id) AS submissions FROM events e LEFT JOIN submissions s ON s.event_id=e.id GROUP BY e.id ORDER BY submissions DESC LIMIT 8');
$topEvents = $topEventsStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Analytics · Sprint";
include '../includes/header.php';
?>

<h1>Analytics</h1>

<div class="card-grid">
    <div class="card"><h2><?= (int)$totUsers ?></h2><p>Users</p></div>
    <div class="card"><h2><?= (int)$totEvents ?></h2><p>Events</p></div>
    <div class="card"><h2><?= (int)$totTeams ?></h2><p>Teams</p></div>
    <div class="card"><h2><?= (int)$totSubmissions ?></h2><p>Submissions</p></div>
</div>

<h2 style="margin-top:18px;">Top Events by Submissions</h2>
<canvas id="topEventsChart" width="800" height="300" style="max-width:100%;height:300px;"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('topEventsChart').getContext('2d');
const labels = <?= json_encode(array_column($topEvents, 'name')) ?>;
const data = <?= json_encode(array_column($topEvents, 'submissions')) ?>;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{ label: 'Submissions', data: data, backgroundColor: 'rgba(54,162,235,0.6)' }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>

<?php include '../includes/footer.php'; ?>
