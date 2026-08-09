<?php
require_once '../config.php';
require_once '../includes/functions.php';

$cacheDir = __DIR__ . '/../data/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheFile = $cacheDir . '/events_list.json';
$cacheTtl = 30; // seconds
$events = null;

$useCache = is_file($cacheFile) && (time() - filemtime($cacheFile) <= $cacheTtl);
if ($useCache) {
    $raw = @file_get_contents($cacheFile);
    $events = $raw ? json_decode($raw, true) : null;
}

if (!is_array($events)) {
    $events = get_events($pdo);
    @file_put_contents($cacheFile, json_encode($events), LOCK_EX);
}

$page_title = "Sprint — Hackathons";


include '../includes/header.php';
?>

<section class="hero container">
    <div class="ultratitle">Sprint — Hackathons Unified</div>
    <p class="lead">
        Host and attend hackathons with fewer headaches.  
        Create events, manage teams, and collect submissions.
    </p>
    <br>
    <p class="subtitle">Quick start for beginners</p>

    <div class="quickstart-grid container narrow">
        <div class="qs-step card sunken">
            <div class="qs-icon">①</div>
            <h4>Create your account</h4>
            <p>Sign up and log in to access your dashboard and events.</p>
        </div>

        <div class="qs-step card sunken">
            <div class="qs-icon">②</div>
            <h4>Organize or join an event</h4>
            <p>Organizers can create hackathons. Participants can join teams instantly.</p>
        </div>

        <div class="qs-step card sunken">
            <div class="qs-icon">③</div>
            <h4>Build & submit your project</h4>
            <p>Collaborate with your team and submit before the deadline.</p>
        </div>
    </div>

    <div class="hero-buttons">
        <?php if (!current_user_id()): ?>
            <a class="btn outline" href="<?= url('sprint/auth/register.php') ?>">Sign Up</a>
            <a class="btn" href="<?= url('sprint/auth/login.php') ?>">Log In</a>
        <?php elseif (is_admin()): ?>
            <a class="btn cta lg" href="<?= url('sprint/organizer/dashboard.php') ?>">Admin Dashboard</a>
            <a class="btn" href="<?= url('sprint/organizer/logs.php') ?>">View Logs</a>
        <?php elseif (is_organizer()): ?>
            <a class="btn cta lg" href="<?= url('sprint/organizer/create_event.php') ?>">Create Event</a>
            <a class="btn" href="<?= url('sprint/organizer/dashboard.php') ?>">Organizer Dashboard</a>
        <?php elseif (is_judge()): ?>
            <a class="btn cta lg" href="<?= url('sprint/judge/dashboard.php') ?>">Judge Dashboard</a>
            <a class="btn" href="<?= url('sprint/public/index.php') ?>">View Assigned Events</a>
        <?php else: ?>
            <a class="btn cta lg" href="<?= url('sprint/public/teams.php') ?>">Join a Team</a>
            <a class="btn" href="<?= url('sprint/public/index.php') ?>">Browse Events</a>
        <?php endif; ?>
    </div>
</section>

<h2 class="headline section-title">Upcoming Hackathons</h2>

<div class="container card-grid">
<?php foreach ($events as $e): ?>
    <div class="card interactive">
        <h3><?= htmlspecialchars($e['name']) ?></h3>
        <p><?= htmlspecialchars(substr($e['description'], 0, 140)) ?>...</p>
        <p class="caption"><?= htmlspecialchars($e['start_time']) ?> → <?= htmlspecialchars($e['end_time']) ?></p>
        <a class="btn" href="<?= url('/sprint/public/event.php') ?>?id=<?= (int)$e['id'] ?>">View Event</a>
    </div>
<?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>