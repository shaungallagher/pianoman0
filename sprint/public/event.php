<?php
require_once '../config.php';
require_once '../includes/functions.php';

$event_id = $_GET['id'] ?? null;
$event = get_event($pdo, $event_id);

if (!$event) {
    abort_page('Event not found', 404);
}

$teams = get_event_teams($pdo, $event_id);
$submissions = get_event_submissions($pdo, $event_id);
$announcements = get_event_announcements($pdo, $event_id);

$page_title = $event['name'] . " · Sprint";
include '../includes/header.php';
?>

<h1><?= htmlspecialchars($event['name']) ?></h1>
<p><?= nl2br(htmlspecialchars($event['description'])) ?></p>

<?php
$hasVenue = !empty($event['venue_name']) || !empty($event['venue_address']) || !empty($event['venue_city']) || !empty($event['venue_country']) || !empty($event['venue_lat']) || !empty($event['venue_lng']);
if ($hasVenue):
    $venueLine = trim((string)($event['venue_name'] ?? ''));
    if (!empty($event['venue_address'])) {
        $venueLine .= ($venueLine !== '' ? ' — ' : '') . $event['venue_address'];
    }
    $cityState = trim((string)($event['venue_city'] ?? ''));
    if (!empty($event['venue_state'])) {
        $cityState .= ($cityState !== '' ? ', ' : '') . $event['venue_state'];
    }
    if ($cityState !== '') {
        $venueLine .= ($venueLine !== '' ? ' — ' : '') . $cityState;
    }
    if (!empty($event['venue_country'])) {
        $venueLine .= ($venueLine !== '' ? ', ' : '') . $event['venue_country'];
    }
?>

<div class="card" style="margin-top:14px;">
    <div class="meta"><strong>Venue</strong></div>
    <div><?= htmlspecialchars($venueLine !== '' ? $venueLine : 'Details not available') ?></div>

    <?php if (!empty($event['venue_capacity'])): ?>
        <div class="meta" style="margin-top:8px;">Capacity: <?= htmlspecialchars((string)$event['venue_capacity']) ?></div>
    <?php endif; ?>

    <?php if (!empty($event['venue_lat']) && !empty($event['venue_lng'])): ?>
        <div class="meta" style="margin-top:8px;">
            Coordinates: <?= htmlspecialchars((string)$event['venue_lat']) ?>, <?= htmlspecialchars((string)$event['venue_lng']) ?>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>


<div class="event-actions">
    <?php if (current_user_id()): ?>
        <a class="btn" href="teams.php?event_id=<?= intval($event_id) ?>">Teams</a>
        <a class="btn" href="submit.php?event_id=<?= intval($event_id) ?>">Submit</a>
        <a class="btn" href="leaderboard.php?event_id=<?= intval($event_id) ?>">Leaderboard</a>

        <?php if (!is_organizer()): ?>
            <a class="btn outline" href="<?= url('/sprint/organizer/claim_organizer_for_event.php') . '?event_id=' . intval($event_id) ?>">Become organizer</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<h2>Announcements</h2>
<?php foreach ($announcements as $a): ?>
    <div class="card">
        <p><?= nl2br(htmlspecialchars($a['message'])) ?></p>
        <p class="meta"><?= htmlspecialchars($a['created_at']) ?></p>
    </div>
<?php endforeach; ?>

<h2>Submissions</h2>
<div class="card-grid">
<?php foreach ($submissions as $s): ?>
    <?php
    $github = null;
    try {
        $github = github_get_cached_repo_preview($pdo, (int)$s['id']);
    } catch (Exception $e) {
        $github = null;
    }
    ?>
    <div class="card">
        <h3><?= htmlspecialchars($s['title']) ?></h3>
        <p class="meta">Team: <?= htmlspecialchars($s['team_name']) ?></p>
        <p><?= htmlspecialchars(substr($s['description'], 0, 120)) ?>...</p>

        <?php if (!empty($s['repo_url'])): ?>
            <div style="margin-top:10px;">
                <div class="meta" style="margin-bottom:4px;">Repo</div>
                <a class="link" href="<?= htmlspecialchars($s['repo_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars($s['repo_url']) ?>
                </a>

                <?php if (!empty($github) && !empty($github['html_url'])): ?>
                    <div class="card" style="margin-top:10px; padding:12px; background:rgba(0,0,0,0.03);">
                        <div style="display:flex; align-items:flex-start; gap:10px;">
                            <?php if (!empty($github['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($github['avatar_url']) ?>" alt="" style="width:36px;height:36px;border-radius:8px;flex:0 0 auto;">
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:700;">
                                    <a href="<?= htmlspecialchars($github['html_url']) ?>" target="_blank" rel="noopener noreferrer">
                                        <?= htmlspecialchars($github['repo_full_name'] ?? '') ?>
                                    </a>
                                </div>
                                <?php if (!empty($github['language'])): ?>
                                    <div class="meta">Language: <?= htmlspecialchars($github['language']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($github['description'])): ?>
                                    <div style="margin-top:6px;">
                                        <?= htmlspecialchars(substr((string)$github['description'], 0, 160)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="meta" style="margin-top:8px;">
                                    ⭐ <?= htmlspecialchars((string)($github['stargazers_count'] ?? 0)) ?> &nbsp; · &nbsp;
                                    Forks: <?= htmlspecialchars((string)($github['forks_count'] ?? 0)) ?>
                                </div>
                            </div>
                        </div>

                        <?php if (current_user_id()): ?>
                            <form method="post" action="<?= url('/sprint/public/refresh_github_preview.php') ?>" style="margin-top:10px;">
                                <?= csrf_input_field() ?>
                                <input type="hidden" name="event_id" value="<?= (int)$event_id ?>">
                                <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                                <button class="btn outline" type="submit">Refresh GitHub preview</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if (current_user_id()): ?>
                        <form method="post" action="<?= url('/sprint/public/refresh_github_preview.php') ?>" style="margin-top:10px;">
                            <?= csrf_input_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int)$event_id ?>">
                            <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                            <button class="btn outline" type="submit">Generate GitHub preview</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($s['screenshot_path'])): ?>
            <div style="margin-top:8px;"><img src="<?= htmlspecialchars(url($s['screenshot_path'])) ?>" alt="Screenshot" style="max-width:100%;border-radius:8px;"></div>
        <?php endif; ?>
        <?php if (!empty($s['video_path'])): ?>
            <div style="margin-top:8px;"><video controls style="max-width:100%;border-radius:8px;"><source src="<?= htmlspecialchars(url($s['video_path'])) ?>">Your browser does not support video playback.</video></div>
        <?php endif; ?>
        <p style="margin-top:8px;"><a class="btn" href="edit_submission.php?id=<?= (int)$s['id'] ?>">View / Edit</a></p>
    </div>
<?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>

