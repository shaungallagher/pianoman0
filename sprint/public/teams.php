<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$event_id = $_GET['event_id'];
$event = get_event($pdo, $event_id);

$user_team = get_user_team($pdo, $event_id, current_user_id());
$teams = get_event_teams($pdo, $event_id);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $message = 'Invalid CSRF token.';
    } else {
        $team_name = trim($_POST['team_name']);

        if ($team_name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO teams (name, event_id) VALUES (?,?)");
                $stmt->execute([$team_name, $event_id]);
                $team_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id) VALUES (?,?)");
                $stmt->execute([$team_id, current_user_id()]);

                header("Location: teams.php?event_id=$event_id");
                exit;
            } catch (Exception $e) {
                if (function_exists('log_db_error')) log_db_error('Create team failed: ' . $e->getMessage());
                $message = 'Failed to create team.';
            }
        }
    }
}

$page_title = "Teams · Sprint";
include '../includes/header.php';
?>

<h1>Teams for <?= htmlspecialchars($event['name']) ?></h1>

<?php if ($user_team): ?>
    <p>You are in team <strong><?= htmlspecialchars($user_team['name']) ?></strong></p>
<?php else: ?>
    <form method="post" class="form">
        <?= csrf_input_field() ?>
        <label>Create a new team
            <input type="text" name="team_name" required>
        </label>
        <button class="btn">Create Team</button>
    </form>
<?php endif; ?>

<h2>All Teams</h2>
<ul class="list">
<?php foreach ($teams as $t): ?>
    <li><?= htmlspecialchars($t['name']) ?></li>
<?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
