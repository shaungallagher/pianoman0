<?php
$nonce = bin2hex(random_bytes(16));
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();

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
    <title>Dashboard - Visionary</title>
    <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <style>
        .dashboard-container { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .dash-card { background: var(--card); padding: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .dash-card h3 { margin: 0 0 12px 0; font-size: 14px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .dash-value { font-size: 32px; font-weight: bold; color: var(--accent); margin-bottom: 4px; }
        .dash-label { font-size: 12px; color: var(--muted); }
        .activity-list { background: var(--card); padding: 20px; border-radius: 6px; }
        .activity-item { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; gap: 12px; align-items: flex-start; }
        body.dark .activity-item { border-bottom-color: rgba(255,255,255,0.1); }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon { font-size: 18px; margin-top: 2px; }
        .activity-text { flex: 1; }
        .activity-text strong { display: block; margin-bottom: 2px; }
        .activity-time { font-size: 12px; color: var(--muted); }
        .quick-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
        .quick-actions a { padding: 10px 16px; background: var(--accent); color: white; text-decoration: none; border-radius: 4px; font-size: 14px; }
        .quick-actions a:hover { opacity: 0.9; }
        .section-title { font-size: 20px; font-weight: bold; margin-bottom: 16px; margin-top: 24px; }
    </style>
</head>
<body>
    <?php render_app_header('Dashboard', 'Your workspace metrics and activity', $user); ?>

    <main>
        <div class="dashboard-container">
            <div class="dashboard-grid" id="statsGrid">
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);">
                    Loading dashboard...
                </div>
            </div>

            <div class="section-title">📊 Your Activity</div>
            <div class="activity-list" id="activityList">
                <p style="text-align: center; color: var(--muted);">Loading activity...</p>
            </div>

            <div class="section-title">🚀 Quick Stats</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div class="dash-card">
                    <h3>Idea Completion Rate</h3>
                    <div class="dash-value" id="completionRate">-</div>
                    <div class="dash-label">of your ideas completed</div>
                </div>
                <div class="dash-card">
                    <h3>Average Engagement</h3>
                    <div class="dash-value" id="avgEngagement">-</div>
                    <div class="dash-label">likes per idea</div>
                </div>
                <div class="dash-card">
                    <h3>This Week's Ideas</h3>
                    <div class="dash-value" id="weekIdeas">0</div>
                    <div class="dash-label">ideas posted</div>
                </div>
            </div>

            <div class="section-title">⭐ Your Top Ideas</div>
            <div id="topIdeas" style="display: grid; gap: 12px;">
                <p style="text-align: center; color: var(--muted);">Loading top ideas...</p>
            </div>

            <div class="section-title">🏆 Top Contributors</div>
            <div id="topContributors" style="display: grid; gap: 10px;">
                <p style="text-align: center; color: var(--muted);">Loading top contributors...</p>
            </div>

            <div style="margin-top: 16px; text-align:center;">
                <a href="idea.php" class="quick-actions" style="display:inline-block;padding:10px 18px;background:var(--accent);color:#fff;border-radius:6px;text-decoration:none;">➕ Post New Idea</a>
            </div>
        </div>
    </main>

    <script nonce="<?=$nonce?>">
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = String(text || '');
            return div.innerHTML;
        }


        async function loadDashboard() {
            try {
                const userRes = await fetch('api.php?action=current_user', {credentials: 'same-origin'});
                const userJson = await userRes.json();
                const currentUsername = (userJson.data && userJson.data.username) ? userJson.data.username : '';

                const res = await fetch('api.php?action=list&limit=100', {credentials: 'same-origin'});
                const json = await res.json();
                if (!json.success || !json.data) return;

                const ideas = json.data;
                const userIdeas = ideas.filter(i => i.author_name === currentUsername);
                
                const completed = userIdeas.filter(i => i.status === 'completed').length;
                const totalLikes = userIdeas.reduce((sum, i) => sum + (i.likes_count || 0), 0);
                const avgLikes = userIdeas.length > 0 ? Math.round(totalLikes / userIdeas.length) : 0;
                
                // Recent ideas this week
                const oneWeekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
                const thisWeek = userIdeas.filter(i => new Date(i.created_at) > oneWeekAgo).length;

                document.getElementById('completionRate').textContent = userIdeas.length > 0 ? Math.round(completed / userIdeas.length * 100) + '%' : '0%';
                document.getElementById('avgEngagement').textContent = avgLikes;
                document.getElementById('weekIdeas').textContent = thisWeek;

                // Top ideas
                const topIds = userIdeas.sort((a, b) => (b.likes_count || 0) - (a.likes_count || 0)).slice(0, 5);
                document.getElementById('topIdeas').innerHTML = topIds.map(idea => `
                    <div class="top-idea-card" data-idea-id="${idea.id}" style="background: var(--card); padding: 12px 16px; border-radius: 4px; cursor: pointer;">
                        <div style="font-weight: bold; margin-bottom: 4px;">${escapeHtml(idea.title)}</div>
                        <div style="font-size: 12px; color: var(--muted);">${escapeHtml(String(idea.likes_count || 0))} ❤ · ${escapeHtml(String(idea.messages_count || 0))} 💬 · ${escapeHtml(idea.status)}</div>
                    </div>
                `).join('') || '<p style="color: var(--muted);">No ideas yet</p>';

                // Main stats
                document.getElementById('statsGrid').innerHTML = `
                    <div class="dash-card">
                        <h3>Ideas Posted</h3>
                        <div class="dash-value">${userIdeas.length}</div>
                        <div class="dash-label">total ideas</div>
                    </div>
                    <div class="dash-card">
                        <h3>Completed</h3>
                        <div class="dash-value">${completed}</div>
                        <div class="dash-label">ideas</div>
                    </div>
                    <div class="dash-card">
                        <h3>Total Engagement</h3>
                        <div class="dash-value">${totalLikes}</div>
                        <div class="dash-label">likes</div>
                    </div>
                    <div class="dash-card">
                        <h3>This Month</h3>
                        <div class="dash-value">${userIdeas.filter(i => new Date(i.created_at) > new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)).length}</div>
                        <div class="dash-label">ideas posted</div>
                    </div>
                `;

                // Top ideas navigation (avoid inline onclick) 
                document.getElementById('topIdeas').querySelectorAll('.top-idea-card').forEach(card => {
                    card.addEventListener('click', () => {
                        window.location = 'idea.php?id=' + card.dataset.ideaId;
                    });
                });

                // Top Contributors (from leaderboard)
                try {
                    const leaders = await fetch('api.php?action=leaderboard&period=week', {credentials: 'same-origin'}).then(r=>r.json());
                    const contr = document.getElementById('topContributors');
                    if (leaders.success && leaders.data && leaders.data.length) {
                        contr.innerHTML = leaders.data.slice(0,5).map(u => `<div style="background: var(--card); padding: 10px 12px; border-radius:6px;">👤 ${escapeHtml(u.username || u.name || 'Guest')} • ${u.points || 0} pts</div>`).join('');
                    } else {
                        contr.innerHTML = '<p style="color: var(--muted);">No contributors yet</p>';
                    }
                } catch (e) {
                    console.warn('Leader load', e);
                    document.getElementById('topContributors').innerHTML = '<p style="color: var(--muted);">Could not load contributors</p>';
                }

                const firstIdea = userIdeas[0] || null;
                const completedIdea = userIdeas.find(i => i.status === 'completed') || null;
                const activityItems = [];

                if (firstIdea) {
                    activityItems.push(`
                        <div class="activity-item">
                            <div class="activity-icon">💡</div>
                            <div class="activity-text">
                                <strong>You posted "${escapeHtml(firstIdea.title)}"</strong>
                                <div class="activity-time">${new Date(firstIdea.created_at).toLocaleDateString()}</div>
                            </div>
                        </div>
                    `);
                }

                if (completedIdea) {
                    activityItems.push(`
                        <div class="activity-item">
                            <div class="activity-icon">✅</div>
                            <div class="activity-text">
                                <strong>You completed "${escapeHtml(completedIdea.title)}"</strong>
                                <div class="activity-time">Recently</div>
                            </div>
                        </div>
                    `);
                }

                activityItems.push(`
                    <div class="activity-item">
                        <div class="activity-icon">🎯</div>
                        <div class="activity-text">
                            <strong>Keep building! Post more ideas to increase engagement</strong>
                            <div class="activity-time">You\'re doing great</div>
                        </div>
                    </div>
                `);

                document.getElementById('activityList').innerHTML = activityItems.join('') || '<p style="text-align: center; color: var(--muted);">No recent activity</p>';
            } catch (e) {
                console.error(e);
            }
        }

        document.addEventListener('DOMContentLoaded', loadDashboard);
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
</body>
</html>
