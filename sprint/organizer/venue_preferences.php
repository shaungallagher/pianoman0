<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_role('organizer');

$user = current_user();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $home_city = trim($_POST['home_city'] ?? '');
        $home_state = trim($_POST['home_state'] ?? '');
        $home_country = trim($_POST['home_country'] ?? '');

        $home_lat_raw = trim($_POST['home_lat'] ?? '');
        $home_lng_raw = trim($_POST['home_lng'] ?? '');

        $radius_km_raw = trim($_POST['preferred_venue_radius_km'] ?? '');
        $min_capacity_raw = trim($_POST['preferred_min_venue_capacity'] ?? '');

        $home_lat = ($home_lat_raw !== '' && is_numeric($home_lat_raw)) ? (float)$home_lat_raw : null;
        $home_lng = ($home_lng_raw !== '' && is_numeric($home_lng_raw)) ? (float)$home_lng_raw : null;

        $preferred_venue_radius_km = ($radius_km_raw !== '' && is_numeric($radius_km_raw)) ? (int)$radius_km_raw : null;
        $preferred_min_venue_capacity = ($min_capacity_raw !== '' && is_numeric($min_capacity_raw)) ? (int)$min_capacity_raw : null;

        $home_city = $home_city !== '' ? $home_city : null;
        $home_state = $home_state !== '' ? $home_state : null;
        $home_country = $home_country !== '' ? $home_country : null;

        // If the schema is behind (missing home_city etc.), avoid crashing and show an actionable error.
        try {
            $colsNeeded = [
                'home_city',
                'home_state',
                'home_country',
                'home_lat',
                'home_lng',
                'preferred_venue_radius_km',
                'preferred_min_venue_capacity',
            ];

            // Detect missing columns in a DB-agnostic way.
            $missingCols = [];
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

            if (strpos($driver, 'sqlite') !== false) {
                $stmt = $pdo->query("PRAGMA table_info(users)");
                $existing = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    if (!empty($r['name'])) $existing[$r['name']] = true;
                }
                foreach ($colsNeeded as $c) {
                    if (empty($existing[$c])) $missingCols[] = $c;
                }
            } else {
                $stmt = $pdo->prepare(
                    "SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?"
                );
                $dbName = getenv('DB_NAME') ?: 'sprint';
                $stmt->execute([$dbName, 'users']);
                $existing = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    if (!empty($r['column_name'])) $existing[$r['column_name']] = true;
                }
                foreach ($colsNeeded as $c) {
                    if (empty($existing[$c])) $missingCols[] = $c;
                }
            }

            if (!empty($missingCols)) {
                $error = 'Preferences could not be saved because your database schema is missing columns: ' . htmlspecialchars(implode(', ', $missingCols)) . '. Run `php scripts/migrate_add_venue_search.php` (or re-init the DB).';
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users SET 
                        home_city = ?,
                        home_state = ?,
                        home_country = ?,
                        home_lat = ?,
                        home_lng = ?,
                        preferred_venue_radius_km = ?,
                        preferred_min_venue_capacity = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $home_city,
                    $home_state,
                    $home_country,
                    $home_lat,
                    $home_lng,
                    $preferred_venue_radius_km,
                    $preferred_min_venue_capacity,
                    current_user_id(),
                ]);

                $message = 'Preferences saved.';
                $user = current_user();
            }
        } catch (Exception $e) {
            $error = 'Failed to save preferences: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Prefer DB values if present
$home_city = $user['home_city'] ?? '';
$home_state = $user['home_state'] ?? '';
$home_country = $user['home_country'] ?? '';
$home_lat = $user['home_lat'] ?? '';
$home_lng = $user['home_lng'] ?? '';
$radius_km = $user['preferred_venue_radius_km'] ?? '';
$min_capacity = $user['preferred_min_venue_capacity'] ?? '';

$page_title = 'Venue Preferences · Sprint';
include '../includes/header.php';
?>

<h1>Venue Preferences</h1>
<p class="meta">Set your location so Sprint can rank venue suggestions for you.</p>

<?php if ($message): ?>
    <p class="flash"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="flash error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_input_field() ?>

    <label>Home City
        <input type="text" name="home_city" value="<?= htmlspecialchars((string)$home_city) ?>" placeholder="e.g. Austin">
    </label>

    <label>Home State / Region
        <input type="text" name="home_state" value="<?= htmlspecialchars((string)$home_state) ?>" placeholder="e.g. TX">
    </label>

    <label>Home Country
        <input type="text" name="home_country" value="<?= htmlspecialchars((string)$home_country) ?>" placeholder="e.g. United States">
    </label>

    <div class="form-row">
        <label>Latitude (optional)
            <input type="text" name="home_lat" value="<?= htmlspecialchars((string)$home_lat) ?>" placeholder="e.g. 30.2672">
        </label>
        <label>Longitude (optional)
            <input type="text" name="home_lng" value="<?= htmlspecialchars((string)$home_lng) ?>" placeholder="e.g. -97.7431">
        </label>
    </div>

    <label>Preferred search radius (km)
        <input type="number" name="preferred_venue_radius_km" value="<?= htmlspecialchars((string)$radius_km) ?>" min="1" step="1" placeholder="e.g. 25">
    </label>

    <label>Minimum venue capacity (optional)
        <input type="number" name="preferred_min_venue_capacity" value="<?= htmlspecialchars((string)$min_capacity) ?>" min="0" step="1" placeholder="e.g. 200">
    </label>

    <button class="btn">Save Preferences</button>
</form>

<p class="meta" style="margin-top:16px;">
    Tip: If you enter latitude/longitude, Sprint can sort by distance. If you only enter city/state/country, Sprint falls back to location text matching.
</p>

<?php include '../includes/footer.php'; ?>