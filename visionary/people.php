<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
require_once __DIR__ . '/includes/app_layout.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>People - Visionary</title>
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <style>
        .people-container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
        .search-bar { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .search-bar input { flex: 1; min-width: 200px; padding: 12px 16px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        body.dark .search-bar input { border-color: rgba(255,255,255,0.1); }
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tabs button { padding: 8px 12px; border: 1px solid #ddd; background: transparent; border-radius: 4px; cursor: pointer; font-size: 12px; }
        body.dark .filter-tabs button { border-color: rgba(255,255,255,0.2); }
        .filter-tabs button.active { background: var(--accent); color: white; border-color: var(--accent); }
        .people-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .person-card { background: var(--card); padding: 20px; border-radius: 6px; text-align: center; transition: all 0.2s; border: 1px solid transparent; }
        .person-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .person-avatar { width: 80px; height: 80px; border-radius: 50%; background: #e5e7eb; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: var(--text); overflow: hidden; }
        body.dark .person-avatar { background: #2a2e3d; }
        .person-name { font-weight: bold; margin-bottom: 4px; cursor: pointer; color: var(--text); }
        .person-name:hover { text-decoration: underline; }
        .person-role { font-size: 12px; color: var(--muted); margin-bottom: 8px; text-transform: capitalize; }
        .person-stats { display: flex; justify-content: center; gap: 12px; font-size: 12px; color: var(--muted); margin: 8px 0; }
        .person-actions { display: flex; gap: 6px; margin-top: 12px; }
        .person-actions button { flex: 1; padding: 8px; border: 1px solid #ddd; background: transparent; border-radius: 4px; cursor: pointer; font-size: 12px; }
        body.dark .person-actions button { border-color: rgba(255,255,255,0.2); }
        .person-actions button.primary { background: var(--accent); color: white; border-color: var(--accent); }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); grid-column: 1/-1; }
    </style>
</head>
<body>
    <?php render_app_header('People', 'Find creators and developers', $user); ?>

    <main>
        <div class="people-container">
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search by name or username..." />
                <button id="searchButton" style="padding: 12px 20px; background: var(--accent); color: white; border: 0; border-radius: 6px; cursor: pointer;">Search</button>
            </div>

            <div class="filter-tabs">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="poster">Posters</button>
                <button class="filter-btn" data-filter="developer">Developers</button>
                <button class="filter-btn" data-filter="both">Both</button>
            </div>

            <div id="peopleGrid" class="people-grid">
                <div class="empty-state">Loading people...</div>
            </div>
        </div>
    </main>

    <script nonce="<?=$nonce?>">

        const avatarEmojis = ['😀', '😃', '😎', '🤓', '😊', '🙂', '🤔', '😍', '🎉', '🚀', '💡', '⭐', '🎯', '🏆', '👨‍💻', '👩‍💻'];

        function escapeHtml(text) {
            const d = document.createElement('div');
            d.textContent = String(text || '');
            return d.innerHTML;
        }

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                loadPeople();
            });
        });

        async function loadPeople() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
            
            try {
                const res = await fetch('api.php?action=users&limit=100', {credentials: 'same-origin'});
                const json = await res.json();
                
                if (!json.success || !json.data || json.data.length === 0) {
                    document.getElementById('peopleGrid').innerHTML = '<div class="empty-state">No people found</div>';
                    return;
                }

                let users = json.data;

                if (search) {
                    users = users.filter(u => u.username.toLowerCase().includes(search));
                }

                if (activeFilter !== 'all') {
                    users = users.filter(u => {
                        const role = (u.role || 'poster').toLowerCase();
                        if (activeFilter === 'poster') return role === 'poster' || role === 'both';
                        if (activeFilter === 'developer') return role === 'dev' || role === 'both';
                        if (activeFilter === 'both') return role === 'both';
                        return true;
                    });
                }

                if (users.length === 0) {
                    document.getElementById('peopleGrid').innerHTML = '<div class="empty-state">No people found</div>';
                    return;
                }

                const userStats = {};
                const ideasRes = await fetch('api.php?action=list&limit=1000', {credentials: 'same-origin'});
                const ideasJson = await ideasRes.json();
                if (ideasJson.success && ideasJson.data) {
                    ideasJson.data.forEach(idea => {
                        if (!userStats[idea.author_name]) {
                            userStats[idea.author_name] = { ideas: 0, likes: 0, followers: 0 };
                        }
                        userStats[idea.author_name].ideas++;
                        userStats[idea.author_name].likes += idea.likes_count || 0;
                    });
                }

                document.getElementById('peopleGrid').innerHTML = users.map((user, idx) => {
                    const stats = userStats[user.username] || { ideas: 0, likes: 0, followers: 0 };
                    if (typeof user.followers_count === 'number') stats.followers = user.followers_count;
                    const initials = (user.username || 'U').split(/[_\s]+/).map(w => w.charAt(0).toUpperCase()).slice(0,2).join('') || 'U';
                    const avatarHtml = user.avatar_url ? `<img src="${escapeHtml(user.avatar_url)}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">` : `<span>${initials}</span>`;
                    const roleLabel = user.role === 'dev' ? 'Developer' : user.role === 'poster' ? 'Poster' : user.role === 'both' ? 'Poster + Dev' : user.role === 'admin' ? 'Admin' : 'Member';
                    return `
                        <div class="person-card" data-username="${encodeURIComponent(user.username)}">
                            <div class="person-avatar">${avatarHtml}</div>
                            <div class="person-name">${escapeHtml(user.username)}</div>
                            <div class="person-role">${escapeHtml(roleLabel)}</div>
                            <div class="person-stats">
                                <span>💡 ${stats.ideas}</span>
                                <span>❤️ ${stats.likes}</span>
                                <span>👥 ${stats.followers}</span>
                            </div>
                            <div class="person-actions">
                                <button class="primary" data-action="view">View</button>
                                <button data-action="message">Message</button>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (e) {
                console.error(e);
                document.getElementById('peopleGrid').innerHTML = '<div class="empty-state">Error loading people</div>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadPeople();
            document.getElementById('searchButton').addEventListener('click', loadPeople);
            document.getElementById('searchInput').addEventListener('keypress', e => {
                if (e.key === 'Enter') loadPeople();
            });
            document.getElementById('peopleGrid').addEventListener('click', (event) => {
                const card = event.target.closest('.person-card');
                if (card && card.dataset.username) {
                    if (event.target.matches('button[data-action="view"]')) {
                        window.location = 'profile.php?username=' + card.dataset.username;
                        return;
                    }
                    if (event.target.matches('button[data-action="message"]')) {
                        alert('Message feature coming soon!');
                        return;
                    }

                    window.location = 'profile.php?username=' + card.dataset.username;
                }
            });
        });
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
