<?php
require_once '../config.php';
require_once '../includes/roles.php';
require_login();

// Defensive: profile can be hit after OAuth flows where session shape may be incomplete.
$u = current_user() ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        http_response_code(400);
        $error = 'Invalid CSRF token.';
    }

    if (empty($error)) {
        $name = trim((string)($_POST['name'] ?? ''));
        $profile_text = trim((string)($_POST['profile'] ?? ''));

        if ($name === '') {
            $error = 'Name cannot be empty.';
        } else {
            if (!empty($db_connection_failed)) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['profile'] = $profile_text;
                $success = 'Profile updated locally (DB unavailable).';
            } else {
                try {
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, profile = ? WHERE id = ?');
                    $stmt->execute([$name, $profile_text, current_user_id()]);
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['profile'] = $profile_text;
                    $success = 'Profile updated.';
                } catch (Exception $e) {
                    $error = 'Update failed: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// Refresh defensive user array after POST handling.
$u = current_user() ?? [];

$page_title = 'Profile · Sprint';
include '../includes/header.php';
?>

<?php
// Compatibility: older broken edits may have left stray PHP string fragments.
// If they exist, they should have been removed when this file was restored.
?>
<h1>Profile</h1>

<div class="card" style="margin-top:12px;">
    <div style="display:flex; align-items:center; gap:12px;">
        <img src="<?= htmlspecialchars(user_avatar_url($u, 48), ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:48px;height:48px;border-radius:999px;">
        <div>
            <div style="font-weight:800; font-size:1.1rem;"><?= htmlspecialchars($u['name'] ?? '') ?></div>
            <div class="meta"><?= htmlspecialchars($u['email'] ?? '') ?></div>
        </div>
    </div>
</div>

<?php if (!empty($role_tags)): ?>
    <div class="card" style="margin-top:12px;">
        <h2>Your role(s)</h2>
        <div class="meta" style="margin-top:0.5rem;">
            <?= implode(' · ', $role_tags) ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['profile_error'])): ?>
    <div class="error"><?= htmlspecialchars($_SESSION['profile_error']) ?></div>
    <?php unset($_SESSION['profile_error']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['profile_success'])): ?>
    <div class="success"><?= htmlspecialchars($_SESSION['profile_success']) ?></div>
    <?php unset($_SESSION['profile_success']); ?>
<?php endif; ?>

<div class="container card-grid">
    <div class="card">

        <h2>Edit profile</h2>

        <form method="post" action="<?= url('/sprint/public/profile.php') ?>" class="form">
            <?= csrf_input_field() ?>

            <label for="name">Display name
                <input id="name" name="name" type="text" required value="<?= htmlspecialchars($u['name'] ?? '') ?>">
            </label>

            <label>Email
                <div><?= htmlspecialchars($u['email'] ?? '') ?></div>
            </label>

            <label for="profile">Bio
                <textarea id="profile" name="profile" rows="5"><?= htmlspecialchars($u['profile'] ?? '') ?></textarea>
            </label>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:0.5rem;">
                <button class="btn">Save</button>
                <a class="btn" href="<?= url('/sprint/public/index.php') ?>">Back</a>
            </div>
        </form>

        <hr>

        <h2>Bio preview</h2>
        <p><?= nl2br(htmlspecialchars($u['profile'] ?? '')) ?></p>
    </div>

    <div class="card">
        <h2>Connect accounts</h2>
        <p>Link accounts you use most. Hack Club is the only login provider.</p>

        <?php
        $hasGithub = false;
        if (empty($db_connection_failed) && getenv('GITHUB_CLIENT_ID')) {
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM oauth_accounts WHERE user_id = ? AND provider = 'github' LIMIT 1");
                $stmt->execute([current_user_id()]);
                $hasGithub = (bool)$stmt->fetchColumn();
            } catch (Exception $e) {
                $hasGithub = false;
            }
        }
        ?>

        <?php if (!getenv('GITHUB_CLIENT_ID')): ?>
            <p class="meta">GitHub isn’t configured yet.</p>
        <?php elseif ($hasGithub): ?>
            <p class="meta">GitHub is connected.</p>
        <?php else: ?>
            <div style="margin-top:8px;">
                <a class="btn" href="<?= url('/sprint/auth/github.php') ?>" style="width:100%; display:block;">Connect GitHub</a>
            </div>
        <?php endif; ?>

        <?php
        $hasSlack = false;
        if (empty($db_connection_failed)) {
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM oauth_accounts WHERE user_id = ? AND provider = 'slack' LIMIT 1");
                $stmt->execute([current_user_id()]);
                $hasSlack = (bool)$stmt->fetchColumn();
            } catch (Exception $e) {
                $hasSlack = false;
            }
        }
        ?>

        <?php if (!$hasSlack && getenv('SLACK_CLIENT_ID')): ?>
            <div style="margin-top:8px;">
                <a class="btn" href="<?= url('/sprint/auth/slack.php') ?>" style="width:100%; display:block;">Connect Slack</a>
            </div>
        <?php elseif ($hasSlack): ?>
            <p class="meta">Slack is connected.</p>
        <?php else: ?>
            <p class="meta">Slack isn’t configured yet.</p>
        <?php endif; ?>

        <?php if (getenv('HACKATIME_CLIENT_ID')): ?>
            <div style="margin-top:8px;">
                <a class="btn" href="<?= url('/sprint/auth/hackatime.php') ?>" style="width:100%; display:block;">Connect Hackatime</a>
            </div>
        <?php else: ?>
            <p class="meta">Hackatime isn’t configured yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Everything below is DB-driven; keep it defensive so the page renders even if some queries fail.

$role_tags = [];
try {
    if (empty($db_connection_failed)) {
        $rawRole = $u['role'] ?? '';
        if (!is_string($rawRole)) $rawRole = (string)$rawRole;

        $parts = array_map('trim', explode(',', strtolower($rawRole)));
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        if (count($parts) === 0) $parts = ['participant'];

        $labels = [
            'admin' => 'Admin',
            'organizer' => 'Organizer',
            'judge' => 'Judge',
            'participant' => 'Participant',
        ];

        foreach ($parts as $p) {
            $role_tags[] = $labels[$p] ?? ucfirst($p);
        }
    }
} catch (Exception $e) {
    $role_tags = [];
}

try {
    $orgCount = 0;
    if (empty($db_connection_failed)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'organizer'");
        $orgCount = $stmt ? intval($stmt->fetchColumn()) : 0;
    }
} catch (Exception $e) {
    $orgCount = 0;
}

if ($orgCount === 0 && (($u['role'] ?? '') !== 'organizer')):
?>
    <div class="card" style="margin-top:18px;">
        <h2>Site setup</h2>
        <p>No organizers exist for this instance yet. If you're the site owner you can claim the organizer role.</p>
        <a class="btn" href="<?= url('/sprint/organizer/claim_organizer.php') ?>">Claim organizer role</a>
    </div>
<?php endif; ?>

<div class="card" style="margin-top:18px;">
    <h2>Hackathons attended</h2>
    <?php
    $attended = [];
    if (empty($db_connection_failed)) {
        try {
            $stmt = $pdo->prepare("SELECT e.*
                FROM events e
                JOIN user_event_attendance uea ON uea.event_id = e.id
                WHERE uea.user_id = ?
                ORDER BY e.start_time DESC");
            $stmt->execute([current_user_id()]);
            $att1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT DISTINCT e.*
                FROM events e
                JOIN teams t ON t.event_id = e.id
                JOIN team_members tm ON tm.team_id = t.id
                WHERE tm.user_id = ?
                ORDER BY e.start_time DESC");
            $stmt->execute([current_user_id()]);
            $att2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $byId = [];
            foreach (array_merge($att1, $att2) as $ev) {
                if (isset($ev['id'])) $byId[$ev['id']] = $ev;
            }
            $attended = array_values($byId);
        } catch (Exception $e) {
            $attended = [];
        }
    }

    if (empty($attended)) {
        echo '<p>No recorded hackathons attended yet.</p>';
    } else {
        echo '<ul class="list">';
        foreach ($attended as $e) {
            $id = $e['id'] ?? null;
            if ($id === null) continue;
            echo '<li><a href="' . htmlspecialchars(url('/sprint/public/event.php') . '?id=' . $id) . '">' . htmlspecialchars($e['name'] ?? '') . '</a> — ' . htmlspecialchars(substr($e['description'] ?? '', 0, 140)) . '</li>';
        }
        echo '</ul>';
    }
    ?>
</div>

<div class="card" style="margin-top:18px;">
    <h2>Linked accounts</h2>
    <?php
    $linked = [];
    if (empty($db_connection_failed)) {
        try {
            $stmt = $pdo->prepare("SELECT provider, provider_user_id, created_at
                FROM oauth_accounts WHERE user_id = ?
                ORDER BY created_at DESC");
            $stmt->execute([current_user_id()]);
            $linked = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $linked = [];
        }
    }
    ?>

    <?php if (empty($linked)): ?>
        <p>No linked third-party accounts.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($linked as $l): ?>
                <li>
                    <?php if (($l['provider'] ?? '') === 'github'): ?>
                        <img src="https://avatars.githubusercontent.com/<?= rawurlencode($l['provider_user_id'] ?? '') ?>?s=24" alt="" style="vertical-align:middle;width:24px;height:24px;border-radius:4px;margin-right:8px;">
                        <strong>GitHub</strong> —
                        <a href="https://github.com/<?= rawurlencode($l['provider_user_id'] ?? '') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($l['provider_user_id'] ?? '') ?></a>
                    <?php else: ?>
                        <?= htmlspecialchars($l['provider'] ?? '') ?> — <?= htmlspecialchars($l['provider_user_id'] ?? '') ?>
                    <?php endif; ?>

                    <form method="post" action="<?= url('/sprint/auth/unlink.php') ?>" style="display:inline;margin-left:8px;">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="provider" value="<?= htmlspecialchars($l['provider'] ?? '') ?>">
                        <button class="btn" onclick="return confirm('Unlink <?= htmlspecialchars($l['provider'] ?? '') ?>?')">Unlink</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

