<?php

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf_token($token): bool {
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function abort_page(string $message, int $status = 400): void {
    http_response_code($status);
    $title = $status >= 500 ? 'Server error' : 'Request error';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · Sprint</title>
    </head>
    <body>
        <main>
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <p><a href="<?= htmlspecialchars(url('/sprint/index.php'), ENT_QUOTES, 'UTF-8') ?>">Return to Sprint</a></p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function user_avatar_url(array $user, int $size = 48): string {
    $avatar = $user['slack_avatar_url'] ?? $user['github_avatar_url'] ?? '';
    if (is_string($avatar) && filter_var($avatar, FILTER_VALIDATE_URL)) {
        return $avatar;
    }

    $email = strtolower(trim((string)($user['email'] ?? '')));
    if ($email !== '') {
        return 'https://www.gravatar.com/avatar/' . md5($email) . '?s=' . max(16, min(512, $size)) . '&d=identicon';
    }

    return 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
}

function can_access_event($pdo, $event_id, $user_id = null) {
    if ($user_id === null) {
        $user_id = current_user_id();
    }
    
    // Not logged in users can only access public events
    if (!$user_id) {
        try {
            $stmt = $pdo->prepare("SELECT visibility FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            return $event && $event['visibility'] === 'public';
        } catch (Exception $e) {
            return false;
        }
    }
    
    try {
        // Admins can always access any event
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['role'] === 'admin') {
            return true;
        }
        
        // Get the event visibility
        $stmt = $pdo->prepare("SELECT visibility FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            return false;
        }
        
        // Public events are accessible to all authenticated users
        if ($event['visibility'] === 'public') {
            return true;
        }
        
        // For private events, check if user has access
        // Check if user is an organizer of the event
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND created_by = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
        
        // Check if user is a judge for the event
        $stmt = $pdo->prepare("SELECT id FROM judges WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
        
        // Check if user is a team member/participant in the event
        $stmt = $pdo->prepare("
            SELECT tm.id FROM team_members tm
            JOIN teams t ON t.id = tm.team_id
            WHERE t.event_id = ? AND tm.user_id = ?
        ");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
        
        // User does not have access
        return false;
    } catch (Exception $e) {
        log_db_error("Authorization check failed for event $event_id, user $user_id: " . $e->getMessage());
        return false;
    }
}

function get_event_authorized($pdo, $event_id, $user_id = null) {
    if (!can_access_event($pdo, $event_id, $user_id)) {
        return null;
    }
    
    return get_event($pdo, $event_id);
}

function get_events_authorized($pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = current_user_id();
    }
    
    try {
        if (!$user_id) {
            // Anonymous users only see public events
            $stmt = $pdo->query("SELECT * FROM events WHERE visibility = 'public' ORDER BY start_time ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Fetch all events
        $stmt = $pdo->query("SELECT * FROM events ORDER BY start_time ASC");
        $allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filter based on authorization
        $authorized = [];
        foreach ($allEvents as $event) {
            if (can_access_event($pdo, $event['id'], $user_id)) {
                $authorized[] = $event;
            }
        }
        
        return $authorized;
    } catch (Exception $e) {
        log_db_error("Failed to get authorized events for user $user_id: " . $e->getMessage());
        return [];
    }
}

// Update existing get_events function to use authorization
function get_events($pdo) {
    return get_events_authorized($pdo);
}

// Update existing get_event function to include authorization
function get_event($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_submission($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_user_team($pdo, $event_id, $user_id) {
    if (!$user_id) return null;

    $stmt = $pdo->prepare(
        "SELECT t.*
         FROM teams t
         JOIN team_members tm ON tm.team_id = t.id
         WHERE t.event_id = ? AND tm.user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$event_id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Update get_event_teams to include authorization check
function get_event_teams($pdo, $event_id, $check_auth = true) {
    if ($check_auth && !can_access_event($pdo, $event_id)) {
        return [];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE event_id = ?");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update get_event_submissions to include authorization check
function get_event_submissions($pdo, $event_id, $check_auth = true) {
    if ($check_auth && !can_access_event($pdo, $event_id)) {
        return [];
    }
    
    $stmt = $pdo->prepare("
        SELECT s.*, t.name AS team_name
        FROM submissions s
        JOIN teams t ON t.id = s.team_id
        WHERE s.event_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update get_event_announcements to include authorization check
function get_event_announcements($pdo, $event_id, $check_auth = true) {
    if ($check_auth && !can_access_event($pdo, $event_id)) {
        return [];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE event_id = ? ORDER BY created_at DESC");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
