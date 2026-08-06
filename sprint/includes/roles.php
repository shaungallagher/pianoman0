<?php

function user_roles_list($user = null): array {
    $user = $user ?: current_user();
    if (!$user) return [];

    $raw = $user['role'] ?? '';
    if (!is_string($raw)) $raw = (string)$raw;

    $parts = array_map('trim', explode(',', strtolower($raw)));
    $parts = array_values(array_filter($parts, fn($p) => $p !== ''));

    // Treat empty/missing role as participant
    if (count($parts) === 0) return ['participant'];

    return $parts;
}

function user_has_role(string $role): bool {
    $user = current_user();
    if (!$user || !current_user_id()) return false;

    $roles = user_roles_list($user);
    return in_array(strtolower($role), $roles, true) || in_array('admin', $roles, true);
}

function require_role($role) {
    $user = current_user();
    if (!$user || !current_user_id()) {
        abort_page('Access denied', 403);
    }

    // Admins bypass role checks.
    if (user_has_role('admin')) return;

    if (!user_has_role((string)$role)) {
        abort_page('Access denied', 403);
    }
}

function is_admin() {
    return user_has_role('admin');
}

function is_organizer() {
    return user_has_role('organizer');
}

function is_judge() {
    return user_has_role('judge');
}

function is_participant() {
    return user_has_role('participant');
}

