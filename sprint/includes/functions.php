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
