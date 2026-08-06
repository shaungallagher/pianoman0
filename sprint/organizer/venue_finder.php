<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_role('organizer');

$user = current_user();

function haversine_km($lat1, $lng1, $lat2, $lng2): float {
    $R = 6371.0;
    $lat1 = deg2rad((float)$lat1);
    $lng1 = deg2rad((float)$lng1);
    $lat2 = deg2rad((float)$lat2);
    $lng2 = deg2rad((float)$lng2);

    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;

    $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

$filters = [
    'radius_km' => $user['preferred_venue_radius_km'] ?? null,
    'min_capacity' => $user['preferred_min_venue_capacity'] ?? null,
    'city' => $user['home_city'] ?? '',
    'state' => $user['home_state'] ?? '',
    'country' => $user['home_country'] ?? '',
    'lat' => $user['home_lat'] ?? null,
    'lng' => $user['home_lng'] ?? null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        abort_page('Invalid CSRF token', 400);
    }

    $radius_raw = trim($_POST['radius_km'] ?? '');
    $min_cap_raw = trim($_POST['min_capacity'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $lat_raw = trim($_POST['lat'] ?? '');
    $lng_raw = trim($_POST['lng'] ?? '');

    $filters['radius_km'] = ($radius_raw !== '' && is_numeric($radius_raw)) ? (int)$radius_raw : null;
    $filters['min_capacity'] = ($min_cap_raw !== '' && is_numeric($min_cap_raw)) ? (int)$min_cap_raw : null;
    $filters['city'] = $city;
    $filters['state'] = $state;
    $filters['country'] = $country;

    $filters['lat'] = ($lat_raw !== '' && is_numeric($lat_raw)) ? (float)$lat_raw : null;
    $filters['lng'] = ($lng_raw !== '' && is_numeric($lng_raw)) ? (float)$lng_raw : null;
}

// Load candidate events that have venue data
$stmt = $pdo->query("SELECT * FROM events WHERE venue_name IS NOT NULL OR venue_address IS NOT NULL OR venue_city IS NOT NULL OR venue_lat IS NOT NULL OR venue_lng IS NOT NULL");
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = time();
$results = [];

$hasLatLng = !empty($filters['lat']) && !empty($filters['lng']);
$radiusKm = is_numeric($filters['radius_km']) ? (float)$filters['radius_km'] : null;
$minCap = is_numeric($filters['min_capacity']) ? (int)$filters['min_capacity'] : null;

$cityQ = strtolower(trim((string)$filters['city']));
$stateQ = strtolower(trim((string)$filters['state']));
$countryQ = strtolower(trim((string)$filters['country']));

foreach ($candidates as $e) {
    $venueCap = isset($e['venue_capacity']) ? $e['venue_capacity'] : null;

    if ($minCap !== null && $venueCap !== null) {
        $venueCapInt = is_numeric($venueCap) ? (int)$venueCap : null;
        if ($venueCapInt !== null && $venueCapInt < $minCap) continue;
    }

    $score = 0.0;
    $distanceKm = null;

    if ($hasLatLng && $e['venue_lat'] !== null && $e['venue_lng'] !== null && is_numeric($e['venue_lat']) && is_numeric($e['venue_lng'])) {
        $distanceKm = haversine_km($filters['lat'], $filters['lng'], (float)$e['venue_lat'], (float)$e['venue_lng']);

        if ($radiusKm !== null && $distanceKm > $radiusKm) {
            continue;
        }

        // Lower distance is better.
        $score += max(0.0, 1000.0 - $distanceKm);
    } else {
        $cityE = strtolower(trim((string)($e['venue_city'] ?? '')));
        $stateE = strtolower(trim((string)($e['venue_state'] ?? '')));
        $countryE = strtolower(trim((string)($e['venue_country'] ?? '')));

        if ($countryQ !== '' && $countryE !== '' && $countryQ === $countryE) $score += 300;
        if ($stateQ !== '' && $stateE !== '' && $stateQ === $stateE) $score += 200;
        if ($cityQ !== '' && $cityE !== '' && $cityQ === $cityE) $score += 150;

        if ($cityQ !== '' && $cityE !== '' && str_contains($cityE, $cityQ)) $score += 60;
        if ($stateQ !== '' && $stateE !== '' && str_contains($stateE, $stateQ)) $score += 40;
    }

    // Prefer events starting soon
    $startTs = !empty($e['start_time']) ? strtotime($e['start_time']) : null;
    if ($startTs !== null) {
        // if past, still allow but de-prioritize
        $delta = abs($startTs - $now);
        $score += max(0.0, 500000.0 - $delta);
    }

    $results[] = [
        'event' => $e,
        'score' => $score,
        'distance_km' => $distanceKm,
    ];
}

usort($results, function ($a, $b) {
    if ($a['score'] === $b['score']) {
        $da = $a['distance_km'];
        $db = $b['distance_km'];
        if ($da === $db) return 0;
        if ($da === null) return 1;
        if ($db === null) return -1;
        return $da <=> $db;
    }
    return $b['score'] <=> $a['score'];
});

$top = array_slice($results, 0, 20);

$page_title = 'Venue Finder · Sprint';
include '../includes/header.php';
?>

<h1>Venue Finder (Experimental)</h1>
<p class="meta">Find nearby venue options based on your preferences (location + optional capacity + radius).</p>

<form method="post" class="form" style="margin-bottom:18px;">
    <?= csrf_input_field() ?>

    <div class="form-row">
        <label>Radius (km, optional)
            <input type="number" name="radius_km" value="<?= htmlspecialchars((string)($filters['radius_km'] ?? '')) ?>" min="1" step="1" placeholder="e.g. 25">
        </label>

        <label>Minimum capacity (optional)
            <input type="number" name="min_capacity" value="<?= htmlspecialchars((string)($filters['min_capacity'] ?? '')) ?>" min="0" step="1" placeholder="e.g. 200">
        </label>
    </div>

    <div class="form-row">
        <label>City
            <input type="text" name="city" value="<?= htmlspecialchars((string)($filters['city'] ?? '')) ?>" placeholder="e.g. Austin">
        </label>
        <label>State / Region
            <input type="text" name="state" value="<?= htmlspecialchars((string)($filters['state'] ?? '')) ?>" placeholder="e.g. TX">
        </label>
    </div>

    <label>Country
        <input type="text" name="country" value="<?= htmlspecialchars((string)($filters['country'] ?? '')) ?>" placeholder="e.g. United States">
    </label>

    <div class="form-row">
        <label>Latitude (optional)
            <input type="text" name="lat" value="<?= htmlspecialchars((string)($filters['lat'] ?? '')) ?>" placeholder="e.g. 30.2672">
        </label>
        <label>Longitude (optional)
            <input type="text" name="lng" value="<?= htmlspecialchars((string)($filters['lng'] ?? '')) ?>" placeholder="e.g. -97.7431">
        </label>

        <div class="form-row">
            <button type="button" class="btn" id="use-my-location-btn">Use my location</button>
        </div>



    </div>
    <div id="location-status" class="meta" style="margin:6px 0 0; min-height:18px;"></div>

    <button class="btn">Search Venues</button>
</form>



<h2>Top Venue Matches</h2>
<?php if (count($top) === 0): ?>
    <div class="card">
        <p>No matches found. Try adding lat/lng or expand radius / remove capacity filter.</p>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($top as $row):
            $e = $row['event'];
            $dist = $row['distance_km'];
            $venueLine = trim((string)($e['venue_name'] ?? ''));
            if (!empty($e['venue_city'])) {
                $venueLine .= ($venueLine !== '' ? ' — ' : '') . $e['venue_city'];
            }
            if (!empty($e['venue_state'])) {
                $venueLine .= ($venueLine !== '' ? ', ' : '') . $e['venue_state'];
            }
            if (!empty($e['venue_country'])) {
                $venueLine .= ($venueLine !== '' ? ', ' : '') . $e['venue_country'];
            }
            ?>
            <div class="card interactive">
                <h3><?= htmlspecialchars($e['name']) ?></h3>
                <p><?= htmlspecialchars(substr((string)$e['description'], 0, 140)) ?>...</p>
                <p class="caption">
                    <?php if ($dist !== null): ?>
                        <?= number_format((float)$dist, 1) ?> km away
                    <?php else: ?>
                        Location matched
                    <?php endif; ?>
                    &nbsp;·&nbsp;
                    <?= !empty($e['start_time']) ? htmlspecialchars($e['start_time']) : 'No start time' ?>
                </p>
                <?php if (!empty($e['venue_address']) || !empty($venueLine)): ?>
                    <div class="meta" style="margin-top:8px;">
                        <strong>Venue:</strong> <?= htmlspecialchars($venueLine !== '' ? $venueLine : ($e['venue_address'] ?? '')) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($e['venue_capacity'])): ?>
                    <div class="meta">Capacity: <?= htmlspecialchars((string)$e['venue_capacity']) ?></div>
                <?php endif; ?>

                <a class="btn" href="<?= url('/sprint/public/event.php') ?>?id=<?= (int)$e['id'] ?>" style="margin-top:12px;">View Event</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p class="meta" style="margin-top:18px;">
    Want to improve results? Update your saved preferences in <a class="link" href="<?= url('/sprint/organizer/venue_preferences.php') ?>">Venue Preferences</a>.
</p>

<?php include '../includes/footer.php'; ?>

