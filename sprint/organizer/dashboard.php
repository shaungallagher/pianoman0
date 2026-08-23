<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_role('organizer');

$events = get_events($pdo);
$page_title = "Organizer Dashboard · Sprint";

include '../includes/header.php';
?>

<h1>Organizer Dashboard</h1>

<div class="card-grid">
    <a class="card" href="create_event.php">
        <h2>Create Event</h2>
        <p>Start a new hackathon</p>
    </a>

    <a class="card" href="venue_preferences.php">
        <h2>Venue Preferences</h2>
        <p>Set your location + search radius</p>
    </a>

    <a class="card" href="venue_finder.php">
        <h2>Venue Finder</h2>
        <p>Get venue suggestions based on distance & filters</p>
    </a>


    <?php foreach ($events as $e): ?>
        <a class="card" href="manage_event.php?id=<?= (int)$e['id'] ?>">
            <h2><?= htmlspecialchars($e['name']) ?></h2>
            <p><?= htmlspecialchars(substr($e['description'], 0, 100)) ?>...</p>
        </a>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
