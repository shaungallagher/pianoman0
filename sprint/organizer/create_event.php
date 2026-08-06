<?php
require_once '../config.php';
require_role('organizer');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $message = "Invalid CSRF token. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $judging_mode = in_array($_POST['judging_mode'] ?? 'judges', ['judges','peer']) ? $_POST['judging_mode'] : 'judges';

        $start_ts = !empty($_POST['start_time']) ? strtotime($_POST['start_time']) : false;
        $end_ts = !empty($_POST['end_time']) ? strtotime($_POST['end_time']) : false;
        $start = $start_ts !== false ? date('Y-m-d H:i:s', $start_ts) : null;
        $end = $end_ts !== false ? date('Y-m-d H:i:s', $end_ts) : null;

        if ($name && $start && $end) {
            $venue_name = trim($_POST['venue_name'] ?? '');
            $venue_address = trim($_POST['venue_address'] ?? '');
            $venue_city = trim($_POST['venue_city'] ?? '');
            $venue_state = trim($_POST['venue_state'] ?? '');
            $venue_country = trim($_POST['venue_country'] ?? '');
            $venue_capacity_raw = trim($_POST['venue_capacity'] ?? '');

            $venue_lat_raw = trim($_POST['venue_lat'] ?? '');
            $venue_lng_raw = trim($_POST['venue_lng'] ?? '');

            $venue_capacity = ($venue_capacity_raw !== '' && is_numeric($venue_capacity_raw)) ? (int)$venue_capacity_raw : null;
            $venue_lat = ($venue_lat_raw !== '' && is_numeric($venue_lat_raw)) ? (float)$venue_lat_raw : null;
            $venue_lng = ($venue_lng_raw !== '' && is_numeric($venue_lng_raw)) ? (float)$venue_lng_raw : null;

            $venue_name = $venue_name !== '' ? $venue_name : null;
            $venue_address = $venue_address !== '' ? $venue_address : null;
            $venue_city = $venue_city !== '' ? $venue_city : null;
            $venue_state = $venue_state !== '' ? $venue_state : null;
            $venue_country = $venue_country !== '' ? $venue_country : null;

            try {
                $stmt = $pdo->prepare("INSERT INTO events (name, description, start_time, end_time, judging_mode, created_by, venue_name, venue_address, venue_city, venue_state, venue_country, venue_lat, venue_lng, venue_capacity) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $desc, $start, $end, $judging_mode, current_user_id(), $venue_name, $venue_address, $venue_city, $venue_state, $venue_country, $venue_lat, $venue_lng, $venue_capacity]);


                try {
                    $eventId = $pdo->lastInsertId();
                    $creator = current_user()['name'] ?? 'unknown';
                    $text = "New event created: $name\nStart: $start\nEnd: $end\nBy: $creator";
                    if (function_exists('send_slack_message')) send_slack_message($text);
                } catch (Exception $notifyEx) {
                    if (function_exists('log_db_error')) log_db_error('Slack notify failed: ' . $notifyEx->getMessage());
                }

                header("Location: " . url('organizer/dashboard.php'));
                exit;
            } catch (Exception $e) {
                $err = $e->getMessage();
                if (stripos($err, 'judging_mode') !== false && (stripos($err, 'no column') !== false || stripos($err, 'unknown column') !== false)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO events (name, description, start_time, end_time, created_by) VALUES (?,?,?,?,?)");
                        $stmt->execute([$name, $desc, $start, $end, current_user_id()]);

                        try {
                            $eventId = $pdo->lastInsertId();
                            $creator = current_user()['name'] ?? 'unknown';
                            $text = "New event created: $name\nStart: $start\nEnd: $end\nBy: $creator";
                            if (function_exists('send_slack_message')) send_slack_message($text);
                        } catch (Exception $notifyEx) {
                            if (function_exists('log_db_error')) log_db_error('Slack notify failed: ' . $notifyEx->getMessage());
                        }

                        header("Location: " . url('organizer/dashboard.php'));
                        exit;
                    } catch (Exception $e2) {
                        if (function_exists('log_db_error')) log_db_error("Create event failed after fallback: " . $e2->getMessage());
                        $message = "Failed to create event: " . htmlspecialchars($e2->getMessage());
                    }
                } else {
                    if (function_exists('log_db_error')) log_db_error("Create event failed: " . $e->getMessage());
                    $message = "Failed to create event: " . htmlspecialchars($e->getMessage());
                }
            }
        } else {
            $message = "Name, start, and end time are required.";
        }
    }
}

$page_title = "Create Event · Sprint";
include '../includes/header.php';
?>

<h1>Create Event</h1>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_input_field() ?>
    <label>Name
        <input type="text" name="name" required>
    </label>

    <label>Description
        <textarea name="description" rows="5"></textarea>
    </label>

    <label>Start Time
        <input type="datetime-local" name="start_time" required>
    </label>

    <label>End Time
        <input type="datetime-local" name="end_time" required>
    </label>

    <label>Judging Mode
        <select name="judging_mode">
            <option value="judges">Judges (default)</option>
            <option value="peer">Peer judging</option>
        </select>
    </label>

    <h2 style="margin-top:18px;">Venue (optional)</h2>

    <label>Venue Name
        <input type="text" name="venue_name">
    </label>

    <label>Venue Address
        <textarea name="venue_address" rows="2" placeholder="Street address (optional)"></textarea>
    </label>

    <div class="form-row">
        <label>City
            <input type="text" name="venue_city">
        </label>
        <label>State / Region
            <input type="text" name="venue_state">
        </label>
    </div>

    <label>Country
        <input type="text" name="venue_country">
    </label>

    <div class="form-row">
        <label>Latitude (optional)
            <input type="text" name="venue_lat" placeholder="e.g. 30.2672">
        </label>
        <label>Longitude (optional)
            <input type="text" name="venue_lng" placeholder="e.g. -97.7431">
        </label>
    </div>

    <label>Venue Capacity (optional)
        <input type="number" name="venue_capacity" min="0" step="1">
    </label>

    <button class="btn">Create Event</button>
</form>


<?php include '../includes/footer.php'; ?>
