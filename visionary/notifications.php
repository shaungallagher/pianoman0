<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
require_once __DIR__ . '/includes/app_layout.php';

if (!$user) {
    http_response_code(401);
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Visionary</title>
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <style>
        .notif-container { max-width: 700px; margin: 24px auto; padding: 0 16px; }
        .notif-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .notif-header button { padding: 8px 12px; background: #666; color: white; border: 0; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .notif-list { }
        .notif-item { background: var(--card); padding: 16px; margin-bottom: 12px; border-radius: 6px; border-left: 3px solid #ddd; cursor: pointer; transition: all 0.2s; }
        .notif-item:hover { border-left-color: var(--accent); transform: translateX(4px); }
        .notif-item.unread { background: rgba(102, 126, 234, 0.05); }
        .notif-item.unread::before { content: '●'; position: absolute; font-size: 8px; color: var(--accent); margin-left: -16px; }
        .notif-icon { font-size: 20px; margin-right: 12px; display: inline-block; }
        .notif-text { display: inline-block; }
        .notif-title { font-weight: bold; display: block; margin-bottom: 4px; }
        .notif-desc { font-size: 13px; color: var(--muted); display: block; }
        .notif-time { font-size: 11px; color: var(--muted); margin-top: 6px; display: block; }
        .notif-action { display: inline-block; background: var(--accent); color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; margin-top: 6px; cursor: pointer; }
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #eee; }
        body.dark .filter-tabs { border-bottom-color: rgba(255,255,255,0.1); }
        .filter-tabs button { padding: 10px 12px; background: transparent; border: none; cursor: pointer; font-size: 13px; color: var(--muted); border-bottom: 2px solid transparent; }
        .filter-tabs button.active { color: var(--accent); border-bottom-color: var(--accent); }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state-emoji { font-size: 48px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <?php render_app_header('Notifications', 'Stay updated with alerts', $user); ?>

    <main>
        <div class="notif-container">
            <div class="filter-tabs">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="ideas">Ideas</button>
                <button class="filter-btn" data-filter="messages">Messages</button>
                <button class="filter-btn" data-filter="follows">Follows</button>
                <button class="filter-btn" data-filter="announcements">Announcements</button>
            </div>

            <div class="notif-header">
                <div></div>
                <button id="clearAllBtn">Clear all</button>
            </div>

            <div id="notificationsList" class="notif-list">
                <div class="empty-state">
                    <div class="empty-state-emoji">📭</div>
                    <p>Loading notifications...</p>
                </div>
            </div>
        </div>
    </main>

    <script nonce="<?=$nonce?>">

        let currentFilter = 'all';
        let allNotifications = [];

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentFilter = btn.dataset.filter;
                showNotifications();
            });
        });

        async function fetchNotificationsFromServer() {
            try {
                const res = await fetch('api.php?action=notifications_list', {credentials: 'same-origin'});
                const json = await res.json();
                if (!json.success || !json.data) return [];
                return json.data.map(n => ({
                    id: n.id,
                    user: n.actor_username || 'A user',
                    type: n.type || 'notification',
                    url: n.url || null,
                    idea: n.url && n.url.includes('idea.php?id=') ? n.url.split('idea.php?id=')[1] : null,
                    icon: n.type === 'follow' ? '👤' : (n.type === 'message' ? '💌' : (n.type === 'like' ? '❤️' : (n.type === 'idea_claimed' ? '🛠️' : (n.type === 'idea_completed' ? '✅' : '🔔')))),
                    message: n.message || '',
                    timestamp: new Date(n.created_at || Date.now()),
                    unread: !n.is_read
                }));
            } catch (e) {
                console.error(e);
                return [];
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = String(text || '');
            return div.innerHTML;
        }

        function showNotifications() {
            let filtered = allNotifications;

            if (currentFilter !== 'all') {
                filtered = allNotifications.filter(n => {
                    if (currentFilter === 'ideas') return ['idea_message', 'idea_claimed', 'idea_completed'].includes(n.type);
                    if (currentFilter === 'messages') return ['message', 'dm'].includes(n.type);
                    if (currentFilter === 'announcements') return n.type === 'announcement';
                    return n.type === currentFilter;
                });
            }

            const list = document.getElementById('notificationsList');
            list.innerHTML = '';

            if (filtered.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'empty-state';
                empty.innerHTML = `
                    <div class="empty-state-emoji">✨</div>
                    <p>No ${escapeHtml(currentFilter === 'all' ? 'notifications' : currentFilter)}</p>
                `;
                list.appendChild(empty);
                return;
            }

            filtered.forEach(notif => {
                const item = document.createElement('div');
                item.className = 'notif-item';
                if (notif.unread) item.classList.add('unread');
                item.dataset.notifId = notif.id;
                if (notif.url) item.dataset.url = notif.url;

                const icon = document.createElement('span');
                icon.className = 'notif-icon';
                icon.textContent = notif.icon;

                const text = document.createElement('div');
                text.className = 'notif-text';

                const title = document.createElement('span');
                title.className = 'notif-title';
                const strong = document.createElement('strong');
                strong.textContent = notif.user;
                title.appendChild(strong);
                title.appendChild(document.createTextNode(' ' + getNotificationAction(notif.type)));
                if (notif.idea) {
                    const em = document.createElement('em');
                    em.textContent = notif.idea;
                    title.appendChild(document.createTextNode(' '));
                    title.appendChild(em);
                }

                text.appendChild(title);

                if (notif.message) {
                    const desc = document.createElement('span');
                    desc.className = 'notif-desc';
                    desc.textContent = '"' + notif.message + '"';
                    text.appendChild(desc);
                }

                const time = document.createElement('span');
                time.className = 'notif-time';
                time.textContent = getTimeAgo(notif.timestamp);
                text.appendChild(time);

                if (notif.url) {
                    const action = document.createElement('span');
                    action.className = 'notif-action';
                    action.textContent = 'View →';
                    text.appendChild(action);
                }

                item.appendChild(icon);
                item.appendChild(text);
                list.appendChild(item);
            });
        }

        function getNotificationAction(type) {
            const actions = {
                'like': 'liked',
                'comment': 'commented on',
                'follow': 'started following you',
                'share': 'shared',
                'message': 'sent you a message',
                'dm': 'sent you a message',
                'idea_message': 'commented on',
                'idea_claimed': 'claimed',
                'idea_completed': 'completed',
                'announcement': 'sent an announcement',
                'mention': 'mentioned you'
            };
            return actions[type] || 'interacted with';
        }

        function getTimeAgo(date) {
            const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
            if (seconds < 60) return 'just now';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `${minutes}m ago`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours}h ago`;
            const days = Math.floor(hours / 24);
            return `${days}d ago`;
        }

        function handleNotificationClick(notifId, ideaId) {
            if (ideaId) {
                window.location = 'idea.php?id=' + ideaId;
            }
        }

        function clearAllNotifications() {
            if (confirm('Clear all notifications?')) {
                allNotifications = [];
                showNotifications();
            }
        }

        document.addEventListener('DOMContentLoaded', async () => {
            allNotifications = await fetchNotificationsFromServer();
            showNotifications();

            document.getElementById('clearAllBtn').addEventListener('click', async () => {
                if (!confirm('Mark all notifications as read?')) return;
                try {
                    await fetch('api.php?action=notifications_mark_all_read', { method: 'POST', credentials: 'same-origin' });
                    allNotifications = allNotifications.map(n => ({ ...n, unread: false }));
                    showNotifications();
                } catch (e) { console.error(e); }
            });

            document.getElementById('notificationsList').addEventListener('click', async (event) => {
                const item = event.target.closest('.notif-item');
                if (!item) return;
                const notifId = item.dataset.notifId;
                const ideaId = item.dataset.ideaId;
                if (notifId) {
                    try {
                        await fetch('api.php?action=notifications_mark_read', { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id: notifId}) });
                        const it = allNotifications.find(n => String(n.id) === String(notifId));
                        if (it) it.unread = false;
                        showNotifications();
                    } catch (e) { console.error(e); }
                }
                const url = item.dataset.url;
                if (url) {
                    window.location = url;
                }
            });
        });
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
</body>
</html>
