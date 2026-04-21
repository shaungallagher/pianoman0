<?php
ob_start();

$nonce = bin2hex(random_bytes(16));
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();
$viewId = isset($_GET['id']) ? $_GET['id'] : null;
$viewUsername = isset($_GET['username']) ? $_GET['username'] : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Profile - Visionary">
  <title>Profile - Visionary</title>
  <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
  <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
  <style>
    .profile-container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
    
    .profile-header-card {
      background: var(--card);
      border-radius: 8px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    
    .profile-header-content {
      display: flex;
      gap: 20px;
      align-items: flex-start;
    }
    
    .profile-avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: #ddd;
      overflow: hidden;
      flex-shrink: 0;
      border: 2px solid #ddd;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
    }
    
    .profile-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .profile-info {
      flex: 1;
    }
    
    .profile-info h1 {
      margin: 0 0 8px 0;
      font-size: 24px;
    }
    
    .profile-pronouns {
      color: var(--muted);
      font-size: 13px;
      margin-bottom: 12px;
    }
    
    .profile-bio {
      color: inherit;
      line-height: 1.5;
      margin-bottom: 16px;
      max-width: 600px;
    }
    
    .profile-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 12px;
      margin-top: 16px;
    }
    
    .stat-item {
      background: #f5f5f5;
      padding: 10px;
      border-radius: 4px;
      text-align: center;
    }
    
    body.dark .stat-item {
      background: rgba(255,255,255,0.05);
    }
    
    .stat-number {
      font-size: 18px;
      font-weight: bold;
      color: var(--accent);
    }
    
    .stat-label {
      font-size: 11px;
      color: var(--muted);
      margin-top: 4px;
    }
    
    .profile-actions {
      display: flex;
      gap: 8px;
      margin-top: 16px;
      flex-wrap: wrap;
    }
    
    .profile-actions button {
      padding: 8px 14px;
      border-radius: 4px;
      border: 1px solid #ddd;
      background: white;
      cursor: pointer;
      font-size: 13px;
    }
    
    body.dark .profile-actions button {
      background: rgba(255,255,255,0.05);
      border-color: rgba(255,255,255,0.1);
      color: #fff;
    }
    
    .profile-actions button.primary {
      background: var(--accent);
      color: white;
      border-color: var(--accent);
    }

    .profile-role {
      display: inline-block;
      margin-top: 6px;
      padding: 4px 8px;
      border-radius: 999px;
      border: 1px solid rgba(0,0,0,0.15);
      color: var(--muted);
      font-size: 12px;
      background: rgba(0,0,0,0.03);
    }
    .profile-role.banned {
      border-color: #ef4444;
      background: #fee2e2;
      color: #991b1b;
    }
    .member-since {
      font-size: 12px;
      color: var(--muted);
      margin-top: 8px;
    }

    .section {
      background: var(--card);
      border-radius: 6px;
      padding: 16px;
      margin-bottom: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    
    .section h2 {
      margin-top: 0;
      margin-bottom: 12px;
      font-size: 16px;
    }
  </style>
</head>
<body>
  <?php render_app_header('Profile', 'Creator profile and activity', $user); ?>

  <main>

<div class="profile-container">
    <div id="loading" style="text-align:center;padding:40px;color:var(--muted)">
      Loading profile...
    </div>
    <div id="content"></div>
  </div>

  <script nonce="<?=$nonce?>">
    const viewUsername = <?= json_encode($viewUsername, JSON_UNESCAPED_SLASHES|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const viewId = <?= json_encode($viewId, JSON_UNESCAPED_SLASHES|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const currentUser = <?= json_encode($user, JSON_UNESCAPED_SLASHES|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    async function loadProfile() {
      try {
        const params = new URLSearchParams({ action: 'profile' });
        if (viewUsername) params.set('username', viewUsername);
        else if (viewId) params.set('id', viewId);
        else if (currentUser && currentUser.username) params.set('username', currentUser.username);
        else throw new Error('No profile specified');

        const response = await fetch(`api.php?${params.toString()}`, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const json = await response.json();
        if (!json.success || !json.data) throw new Error(json.error || 'No data');

        renderProfile(json.data);
      } catch (err) {
        console.error('Error loading profile:', err);
        document.getElementById('loading').innerHTML = `<p style="color:red;">Error loading profile: ${err.message}</p>`;
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = String(text || '');
      return div.innerHTML;
    }

    function linkifyMentions(text) {
      const esc = escapeHtml(text);
      return esc.replace(/@([A-Za-z0-9_.-]+)/g, (_, u) => 
        `<a class="mention" href="profile.php?username=${encodeURIComponent(u)}">@${escapeHtml(u)}</a>`
      ).replace(/\n/g, '<br>');
    }

    function renderProfile(profile) {
      const container = document.getElementById('content');
      document.getElementById('loading').style.display = 'none';
      
      const headerCard = document.createElement('div');
      headerCard.className = 'profile-header-card';
      headerCard.innerHTML = `
        <div class="profile-header-content">
          <div class="profile-avatar">
            ${profile.avatar_url ? `<img src="${escapeHtml(profile.avatar_url)}" alt="Avatar">` : '👤'}
          </div>
          <div class="profile-info">
            <h1>${escapeHtml(profile.username)}</h1>
            ${profile.role ? `<div class="profile-role ${profile.role === 'banned' ? 'banned' : ''}">${escapeHtml(profile.role)}</div>` : ''}
            ${profile.pronouns ? `<div class="profile-pronouns">${escapeHtml(profile.pronouns)}</div>` : ''}
            ${profile.email ? `<div class="profile-pronouns">${escapeHtml(profile.email)}</div>` : ''}
            ${profile.bio ? `<div class="profile-bio">${linkifyMentions(profile.bio)}</div>` : ''}
            ${profile.created_at ? `<div class="member-since">Joined ${escapeHtml(new Date(profile.created_at).toLocaleDateString())}</div>` : ''}
            
            <div class="profile-stats">
              <div class="stat-item">
                <div class="stat-number">${(profile.stats && profile.stats.ideas_posted) || 0}</div>
                <div class="stat-label">Ideas Posted</div>
              </div>
              <div class="stat-item">
                <div class="stat-number">${(profile.stats && profile.stats.ideas_completed) || 0}</div>
                <div class="stat-label">Completed</div>
              </div>
              <div class="stat-item">
                <div class="stat-number">${(profile.stats && profile.stats.followers) || 0}</div>
                <div class="stat-label">Followers</div>
              </div>
            </div>
            
            <div class="profile-actions" id="profile-actions"></div>
          </div>
        </div>
      `;
      container.appendChild(headerCard);

      if (profile.role === 'banned') {
        const bannedNote = document.createElement('div');
        bannedNote.style.marginBottom = '16px';
        bannedNote.style.padding = '12px';
        bannedNote.style.border = '1px solid #ffdddd';
        bannedNote.style.background = '#fff1f1';
        bannedNote.style.color = '#9b1c1c';
        bannedNote.textContent = 'This account is banned. Most actions are disabled.';
        container.insertBefore(bannedNote, headerCard.nextSibling);
      }

      renderActions(profile, container);

      if (profile.stats && profile.stats.ideas_posted > 0) {
        renderRecentIdeas(profile, container);
      }

      if (profile.achievements && profile.achievements.length > 0) {
        renderAchievements(profile, container);
      }
    }

    function renderActions(profile, container) {
      const actionsDiv = document.getElementById('profile-actions');
      if (!currentUser) return;

      if (currentUser.id === profile.id) {
        const editBtn = document.createElement('button');
        editBtn.className = 'primary';
        editBtn.textContent = '✎ Edit Profile';
        editBtn.onclick = () => window.location.href = 'settings.php';
        actionsDiv.appendChild(editBtn);
      } else if (profile.role === 'banned') {
        const bannedBtn = document.createElement('button');
        bannedBtn.textContent = '🚫 Banned';
        bannedBtn.disabled = true;
        actionsDiv.appendChild(bannedBtn);
      } else {
        const followBtn = document.createElement('button');
        followBtn.className = profile.is_following ? '' : 'primary';
        followBtn.textContent = profile.is_following ? 'Following' : 'Follow';
        followBtn.onclick = async () => {
          try {
            const action = profile.is_following ? 'unfollow' : 'follow';
            const res = await fetch(`api.php?action=${action}`, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({user_id: profile.id})
            });
            const json = await res.json();
            if (json.success) {
              profile.is_following = !profile.is_following;
              followBtn.textContent = profile.is_following ? 'Following' : 'Follow';
              followBtn.className = profile.is_following ? '' : 'primary';
            }
          } catch (err) { console.error(err); }
        };
        actionsDiv.appendChild(followBtn);

        const msgBtn = document.createElement('button');
        msgBtn.textContent = '💬 Message';
        msgBtn.onclick = async () => {
          const msg = prompt(`Send a message to ${escapeHtml(profile.username)}:`);
          if (!msg) return;
          try {
            const res = await fetch('api.php?action=dm_send', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({to_user_id: profile.id, message: msg})
            });
            const json = await res.json();
            if (json.success) alert('Message sent!');
          } catch (err) { console.error(err); }
        };
        actionsDiv.appendChild(msgBtn);

        const reportBtn = document.createElement('button');
        reportBtn.textContent = '🚨 Report User';
        reportBtn.onclick = async () => {
          const reason = prompt('Reason for reporting this user:');
          if (!reason) return;
          const details = prompt('Optional details (e.g. offensive content):', '');
          try {
            const res = await fetch('api.php?action=report_user', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({reported_username: profile.username, reason, details})
            });
            const json = await res.json();
            if (json.success) alert('Report submitted to admins.');
            else alert(json.error || 'Failed to submit report');
          } catch (err) { console.error(err); alert('Network error'); }
        };
        actionsDiv.appendChild(reportBtn);
      }
    }

    function renderRecentIdeas(profile, container) {
      const section = document.createElement('div');
      section.className = 'section';
      section.innerHTML = `
        <h2>💡 Recent Ideas</h2>
        <div id="recent-ideas-list">Loading...</div>
      `;
      container.appendChild(section);

      fetchUserIdeas(profile.username, 5);
    }

    function renderAchievements(profile, container) {
      const section = document.createElement('div');
      section.className = 'section';
      section.innerHTML = '<h2>🏆 Achievements</h2><div class="achievement-grid" id="achievements-grid"></div>';
      container.appendChild(section);

      const grid = section.querySelector('#achievements-grid');
      profile.achievements.forEach(ach => {
        const badge = document.createElement('div');
        badge.className = 'achievement-badge';
        badge.innerHTML = `${ach.icon || '🏆'}<div class="achievement-name">${escapeHtml(ach.label)}</div>`;
        badge.title = ach.description;
        grid.appendChild(badge);
      });
    }

    function fetchUserIdeas(username, limit = 5) {
      fetch(`api.php?action=list&limit=${limit}`, {credentials: 'same-origin'})
        .then(r => r.json())
        .then(json => {
          if (!json.success || !json.data) return;
          const ideas = json.data.filter(i => i.author_name === username);
          const list = document.getElementById('recent-ideas-list');
          list.innerHTML = ideas.map(idea => `
            <div class="recent-idea-item" data-idea-id="${idea.id}" style="padding:12px;border:1px solid #ddd;border-radius:6px;margin-bottom:8px;cursor:pointer">
              <div style="font-weight:bold;margin-bottom:4px">${escapeHtml(idea.title)}</div>
              <div style="font-size:12px;color:var(--muted)">${idea.likes_count} ❤ · ${idea.messages_count} 💬</div>
            </div>
          `).join('') || '<p style="color:var(--muted)">No ideas yet</p>';
        })
        .catch(err => console.error(err));
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadProfile();
      const content = document.getElementById('content');
      if (content) {
        content.addEventListener('click', event => {
          const ideaItem = event.target.closest('.recent-idea-item');
          if (!ideaItem) return;
          const ideaId = ideaItem.dataset.ideaId;
          if (!ideaId) return;
          window.location.href = 'idea.php?id=' + encodeURIComponent(ideaId);
        });
      }
    });
  </script>
  <?php render_app_footer(); ?>
  <?php render_theme_script(); ?>
</main>
</body>
</html>