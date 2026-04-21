<?php
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
    <title>Analytics - Visionary</title>
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-container { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .stat-card h3 { margin: 0 0 8px 0; font-size: 14px; color: var(--muted); text-transform: uppercase; }
        .stat-value { font-size: 28px; font-weight: bold; color: var(--accent); margin-bottom: 4px; }
        .stat-label { font-size: 12px; color: var(--muted); }
        .chart-container { background: var(--card); padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        .chart-title { font-size: 16px; font-weight: bold; margin-bottom: 16px; }
        .chart-wrapper { position: relative; height: 300px; }
        .trending-section { background: var(--card); padding: 20px; border-radius: 6px; }
        .trending-item { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        body.dark .trending-item { border-bottom-color: rgba(255,255,255,0.1); }
        .trending-item:last-child { border-bottom: none; }
        .trending-tag { color: var(--accent); font-weight: bold; }
    </style>
</head>
<body>
    <?php render_app_header('Analytics', 'Platform insights and trends', $user); ?>

    <main>
        <div class="analytics-container">
            <div class="analytics-grid">
                <div class="stat-card">
                    <h3>Total Ideas</h3>
                    <div class="stat-value" id="totalIdeas">0</div>
                    <div class="stat-label">on the platform</div>
                </div>
                <div class="stat-card">
                    <h3>Completed This Month</h3>
                    <div class="stat-value" id="completedMonth">0</div>
                    <div class="stat-label">ideas finished</div>
                </div>
                <div class="stat-card">
                    <h3>Active Users</h3>
                    <div class="stat-value" id="activeUsers">0</div>
                    <div class="stat-label">this month</div>
                </div>
                <div class="stat-card">
                    <h3>Total Engagement</h3>
                    <div class="stat-value" id="totalEngagement">0</div>
                    <div class="stat-label">likes & comments</div>
                </div>
            </div>

            <div class="chart-container">
                <div class="chart-title">Ideas Posted Over Time</div>
                <div class="chart-wrapper">
                    <canvas id="ideasChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <div class="chart-title">Engagement Trends</div>
                <div class="chart-wrapper">
                    <canvas id="engagementChart"></canvas>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="trending-section">
                    <div style="font-size: 16px; font-weight: bold; margin-bottom: 16px;">🔥 Trending Tags</div>
                    <div id="trendingTags"></div>
                </div>

                <div class="trending-section">
                    <div style="font-size: 16px; font-weight: bold; margin-bottom: 16px;">Status Distribution</div>
                    <div id="statusDistribution"></div>
                </div>
            </div>
        </div>
    </main>

    <script nonce="<?=$nonce?>">

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"]+/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]);
        }

        async function loadAnalytics() {
            try {
                const res = await fetch('api.php?action=list&limit=1000', {credentials: 'same-origin'});
                const json = await res.json();
                
                if (!json.success || !json.data) return;

                const ideas = json.data;
                const now = new Date();

                // Calculate stats
                const totalIdeas = ideas.length;
                const completedThisMonth = ideas.filter(i => {
                    const date = new Date(i.created_at);
                    return i.status === 'completed' && 
                           date.getMonth() === now.getMonth() && 
                           date.getFullYear() === now.getFullYear();
                }).length;

                // Get unique authors this month
                const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                const activeUsersThisMonth = new Set(
                    ideas.filter(i => new Date(i.created_at) > monthAgo)
                          .map(i => i.author_name)
                ).size;

                const totalEngagement = ideas.reduce((sum, i) => sum + (i.likes_count || 0) + (i.messages_count || 0), 0);

                // Update stats
                document.getElementById('totalIdeas').textContent = totalIdeas;
                document.getElementById('completedMonth').textContent = completedThisMonth;
                document.getElementById('activeUsers').textContent = activeUsersThisMonth;
                document.getElementById('totalEngagement').textContent = totalEngagement;

                // Ideas over time (last 7 days)
                const ideasByDay = {};
                for (let i = 6; i >= 0; i--) {
                    const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                    const dateStr = date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                    ideasByDay[dateStr] = ideas.filter(idea => {
                        const ideaDate = new Date(idea.created_at);
                        return ideaDate.toLocaleDateString() === date.toLocaleDateString();
                    }).length;
                }

                const ctx1 = document.getElementById('ideasChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: Object.keys(ideasByDay),
                        datasets: [{
                            label: 'Ideas Posted',
                            data: Object.values(ideasByDay),
                            borderColor: 'var(--accent)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });

                // Engagement trends
                const engagementByDay = {};
                for (let i = 6; i >= 0; i--) {
                    const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                    const dateStr = date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                    engagementByDay[dateStr] = ideas.filter(idea => {
                        const ideaDate = new Date(idea.created_at);
                        return ideaDate.toLocaleDateString() === date.toLocaleDateString();
                    }).reduce((sum, i) => sum + (i.likes_count || 0), 0);
                }

                const ctx2 = document.getElementById('engagementChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(engagementByDay),
                        datasets: [{
                            label: 'Likes',
                            data: Object.values(engagementByDay),
                            backgroundColor: 'var(--accent)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });

                // Trending tags
                const tags = {};
                ideas.forEach(idea => {
                    if (idea.tags) {
                        idea.tags.split(',').forEach(tag => {
                            const t = tag.trim();
                            tags[t] = (tags[t] || 0) + 1;
                        });
                    }
                });

                const topTags = Object.entries(tags)
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 8);

                document.getElementById('trendingTags').innerHTML = topTags.map(([tag, count]) => `
                    <div class="trending-item">
                        <span class="trending-tag">#${escapeHtml(tag)}</span>
                        <span style="font-size: 12px; color: var(--muted);">${escapeHtml(String(count))} ideas</span>
                    </div>
                `).join('');

                // Status distribution
                const byStatus = {};
                ideas.forEach(idea => {
                    byStatus[idea.status] = (byStatus[idea.status] || 0) + 1;
                });

                document.getElementById('statusDistribution').innerHTML = Object.entries(byStatus)
                    .map(([status, count]) => {
                        const icons = {
                            'open': '🔓',
                            'building': '🔨',
                            'completed': '✅',
                            'pending': '⏳'
                        };
                        const percentage = Math.round(count / totalIdeas * 100);
                        return `
                            <div class="trending-item">
                                <span>${icons[status] || '•'} ${escapeHtml(status)}</span>
                                <span style="font-size: 12px; color: var(--muted);">${escapeHtml(String(count))} (${escapeHtml(String(percentage))}%)</span>
                            </div>
                        `;
                    }).join('');

            } catch (e) {
                console.error(e);
            }
        }

        document.addEventListener('DOMContentLoaded', loadAnalytics);
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
</body>
</html>
