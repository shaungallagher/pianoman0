<?php
$nonce = bin2hex(random_bytes(16));
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();

if (!$user) {
  header('Location: login.php');
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Settings - Visionary">
  <meta name="author" content="PianoMan0">
  <title>Visionary — Settings</title>
  <link rel="preload" href="<?=asset_url('css/style.css')?>" as="style">
  <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
  <style>
    .settings-container { max-width:900px; margin:24px auto; padding:0 16px; }
    .settings-tabs { display: flex; gap: 0; border-bottom: 2px solid #ddd; margin-bottom: 24px; flex-wrap: wrap; }
    .settings-tab { padding: 12px 16px; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; }
    .settings-tab.active { border-bottom-color: var(--accent); font-weight: bold; }
    .settings-tab:hover { background: #f5f5f5; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .settings-form { display: flex; flex-direction: column; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-weight: bold; }
    .form-group input, .form-group textarea, .form-group select { 
      padding: 10px; 
      border: 1px solid #ddd; 
      border-radius: 4px; 
      font-family: inherit;
      font-size: 14px;
    }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .checkbox-group { display: flex; gap: 16px; flex-wrap: wrap; }
    .checkbox-item { display: flex; align-items: center; gap: 8px; }
    .checkbox-item input { cursor: pointer; width: 18px; height: 18px; }
    .checkbox-item span { cursor: pointer; }
    .button-group { display: flex; gap: 8px; flex-wrap: wrap; }
    .button-group button { 
      padding: 10px 16px; 
      background: var(--accent); 
      color: white; 
      border: none; 
      border-radius: 4px; 
      cursor: pointer;
      font-weight: 500;
    }
    .button-group button:hover { opacity: 0.9; }
    .button-group button.secondary { background: #666; }
    .button-group button.secondary:hover { background: #555; }
    .button-group button.danger { background: #ef4444; }
    .button-group button.danger:hover { background: #dc2626; }
    .message { padding: 12px; border-radius: 4px; margin-bottom: 16px; }
    .success-message { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .error-message { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .info-card { background: #f0f9ff; border: 1px solid #e0f2fe; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
    body.dark .info-card { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
    .preview-avatar { width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 2px solid var(--accent); margin-top: 8px; }
    .preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .settings-section { background: var(--card); padding: 20px; border-radius: 8px; margin-bottom: 20px; }
  </style>
</head>
<body>
  <?php render_app_header('Settings', 'Edit profile, preferences, and account details', $user); ?>

  <main>
    <div class="settings-container">
      <div class="settings-tabs">
        <div class="settings-tab active" data-tab="profile">👤 Profile</div>
        <div class="settings-tab" data-tab="preferences">⚙ Preferences</div>
        <div class="settings-tab" data-tab="notifications">🔔 Notifications</div>
        <div class="settings-tab" data-tab="privacy">🔒 Privacy</div>
        <div class="settings-tab" data-tab="support">🛎 Support</div>
        <div class="settings-tab" data-tab="account">🔑 Account</div>
      </div>

      <div id="message"></div>

      <div id="profile" class="tab-content active">
        <div class="settings-section">
          <h2>Profile Settings</h2>
          <form id="profileForm" class="settings-form">
            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" value="<?=h($user['username'])?>" placeholder="Choose a username" required>
              <small style="color:var(--muted)">Update your username anytime. Keep it 3-30 characters, letters, numbers, or underscores only.</small>
            </div>

            <div class="form-group">
              <label for="bio">Bio</label>
              <textarea id="bio" placeholder="Tell us about yourself..."></textarea>
              <small style="color:var(--muted)">Markdown and mentions (@username) are supported</small>
            </div>

            <div class="form-group">
              <label for="pronouns">Pronouns (optional)</label>
              <input type="text" id="pronouns" placeholder="e.g., she/her, he/him, they/them">
            </div>

            <div class="form-group">
              <label for="email">Email (optional)</label>
              <input type="email" id="email" placeholder="you@example.com">
            </div>

            <div class="form-group">
              <label for="avatar_url">Avatar URL</label>
              <input type="url" id="avatar_url" placeholder="https://example.com/avatar.jpg">
              <small style="color:var(--muted)">Link to an image (JPG, PNG, GIF)</small>
            </div>

            <div id="imagePreview" style="display:none">
              <label>Preview:</label>
              <div class="preview-avatar">
                <img id="previewImg" src="" alt="Avatar preview">
              </div>
            </div>

            <div class="button-group">
              <button type="submit">💾 Save Profile</button>
              <button type="button" id="cancelProfile" class="secondary">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <div id="preferences" class="tab-content">
        <div class="settings-section">
          <h2>Preferences</h2>
          <form id="preferencesForm" class="settings-form">
            <div class="form-group">
              <label for="theme">Theme</label>
              <select id="theme">
                <option value="auto">Auto (system)</option>
                <option value="light">Light</option>
                <option value="dark">Dark</option>
              </select>
            </div>

            <div class="form-group">
              <label>Default View</label>
              <div class="checkbox-group">
                <label class="checkbox-item">
                  <input type="radio" name="default_view" value="all" checked> All Ideas
                </label>
                <label class="checkbox-item">
                  <input type="radio" name="default_view" value="my"> My Ideas
                </label>
                <label class="checkbox-item">
                  <input type="radio" name="default_view" value="trending"> Trending
                </label>
              </div>
            </div>

            <div class="form-group">
              <label>Sort Ideas By</label>
              <select id="sort_preference">
                <option value="date">Newest First</option>
                <option value="likes">Most Liked</option>
                <option value="messages">Most Active</option>
              </select>
            </div>

            <div class="form-group">
              <label>Ideas Per Page</label>
              <select id="ideas_per_page">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
              </select>
            </div>

            <div class="button-group">
              <button type="submit">💾 Save Preferences</button>
            </div>
          </form>
        </div>
      </div>

      <div id="notifications" class="tab-content">
        <div class="settings-section">
          <h2>Notification Settings</h2>
          <div class="info-card">
            Control what notifications you receive and how they're displayed.
          </div>
          
          <form id="notificationsForm" class="settings-form">
            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="notify_idea_claimed" checked>
                <span>Someone claims my idea</span>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="notify_idea_completed" checked>
                <span>My idea is marked complete</span>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="notify_new_message" checked>
                <span>New comment on my idea</span>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="notify_new_follower" checked>
                <span>Someone follows me</span>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="notify_direct_message">
                <span>Direct messages</span>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="notify_mention" checked>
                <span>When someone mentions me (@username)</span>
              </label>
            </div>

            <div class="button-group">
              <button type="submit">💾 Save Notifications</button>
            </div>
          </form>
        </div>
      </div>

      <div id="privacy" class="tab-content">
        <div class="settings-section">
          <h2>Privacy Settings</h2>
          
          <form id="privacyForm" class="settings-form">
            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="profile_public" checked>
                <span>Public Profile (anyone can see)</span>
              </label>
              <small style="color:var(--muted)">Uncheck to make your profile private</small>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="show_followers" checked>
                <span>Show my followers count</span>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="allow_dms" checked>
                <span>Allow direct messages from anyone</span>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-item">
                <input type="checkbox" id="show_email" >
                <span>Make email visible on profile</span>
              </label>
            </div>

            <div class="form-group">
              <label>Allow mentions</label>
              <select id="mention_preference">
                <option value="anyone">Anyone can mention me</option>
                <option value="followers">Only followers</option>
                <option value="none">Nobody</option>
              </select>
            </div>

            <div class="button-group">
              <button type="submit">💾 Save Privacy Settings</button>
            </div>
          </form>
        </div>
      </div>

      <div id="support" class="tab-content">
        <div class="settings-section">
          <h2>Contact Admins</h2>
          <p class="info-card">Use this form to send a message directly to admins and get help.</p>
          <form id="adminMessageForm" class="settings-form">
            <div class="form-group">
              <label for="adminSubject">Subject</label>
              <input type="text" id="adminSubject" placeholder="Enter subject" required>
            </div>
            <div class="form-group">
              <label for="adminMessage">Message</label>
              <textarea id="adminMessage" placeholder="Describe your issue or request" required></textarea>
            </div>
            <div class="form-group">
              <label for="adminAttachment">Attach file (optional)</label>
              <input type="file" id="adminAttachment" accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.txt">
              <small style="color:var(--muted)">Upload one file up to 10MB.</small>
            </div>
            <div class="button-group">
              <button type="submit">📩 Send to Admins</button>
            </div>
            <div id="adminMessageStatus" style="margin-top: 10px;"></div>
          </form>
        </div>
      </div>

      <div id="account" class="tab-content">
        <div class="settings-section">
          <h2>Account & Security</h2>
          
          <div class="info-card">
            <strong style="font-weight:bold">Account Info</strong><br>
            Username: <code id="accountUsername"><?=h($user['username'])?></code><br>
            Role: <code id="accountRole"><?=h($user['role'])?></code><br>
            Joined: <code><?=h(!empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : date('M d, Y'))?></code>
            <div class="button-group">
              <button type="button" id="logoutBtn" class="secondary">🚪 Log Out</button>
              <button type="button" id="deleteAccountBtn" class="danger">🗑 Delete Account</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script nonce="<?=$nonce?>">
    const applyTheme = () => {
      const pref = localStorage.getItem('pref_theme') || 'auto';
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (pref === 'dark') document.body.classList.add('dark');
      else if (pref === 'light') document.body.classList.remove('dark');
      else {
        if (prefersDark) document.body.classList.add('dark'); else document.body.classList.remove('dark');
      }
      try { localStorage.setItem('visionary-dark', document.body.classList.contains('dark') ? '1' : '0'); } catch (e) {}
    };
    applyTheme();
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        const isDark = document.body.classList.contains('dark');
        const next = isDark ? 'light' : 'dark';
        try { localStorage.setItem('pref_theme', next); } catch (e) {}
        applyTheme();
      });
    }

    const cancelProfile = document.getElementById('cancelProfile');
    if (cancelProfile) {
      cancelProfile.addEventListener('click', () => {
        window.location.href = 'ideas.php';
      });
    }

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => { window.location.href = 'logout.php'; });
    }

    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    if (deleteAccountBtn) {
      deleteAccountBtn.addEventListener('click', async () => {
        if (!confirm('Delete your account? This cannot be undone.')) return;
        try {
          const res = await fetch('api.php?action=delete_account', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
          });
          const json = await res.json();
          if (json.success) {
            alert('Account deleted. Goodbye.');
            window.location.href = 'logout.php';
          } else {
            alert('Failed to delete account: ' + (json.error || 'Unknown error'));
          }
        } catch (err) {
          console.error(err);
          alert('Network error deleting account.');
        }
      });
    }

    document.querySelectorAll('.settings-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        const tabName = tab.dataset.tab;
        document.getElementById(tabName).classList.add('active');
      });
    });

    const messageEl = document.getElementById('message');

    function showMessage(text, isSuccess = true) {
      messageEl.innerHTML = '';
      const msg = document.createElement('div');
      msg.className = 'message ' + (isSuccess ? 'success-message' : 'error-message');
      msg.textContent = text;
      messageEl.appendChild(msg);
      setTimeout(() => { messageEl.innerHTML = ''; }, 4000);
    }

    function setStatusMessage(container, text, isSuccess = true) {
      container.innerHTML = '';
      const msg = document.createElement('div');
      msg.className = isSuccess ? 'success-message' : 'error-message';
      msg.textContent = text;
      container.appendChild(msg);
    }

    async function loadProfileData() {
      try {
        const res = await fetch('api.php?action=profile&username=<?=urlencode($user['username'])?>', {
          credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success && json.data) {
          const profile = json.data;
          document.getElementById('username').value = profile.username || '';
          document.getElementById('bio').value = profile.bio || '';
          document.getElementById('pronouns').value = profile.pronouns || '';
          document.getElementById('email').value = profile.email || '';
          document.getElementById('avatar_url').value = profile.avatar_url || '';
          if (profile.avatar_url) {
            updateImagePreview(profile.avatar_url);
          }
        }
      } catch (e) { console.error(e); }
    }

    document.getElementById('avatar_url').addEventListener('change', function() {
      if (this.value) updateImagePreview(this.value);
      else document.getElementById('imagePreview').style.display = 'none';
    });

    function updateImagePreview(url) {
      document.getElementById('previewImg').src = url;
      document.getElementById('imagePreview').style.display = 'block';
    }

    document.getElementById('profileForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        const res = await fetch('api.php?action=update_profile', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            username: document.getElementById('username').value.trim(),
            bio: document.getElementById('bio').value.trim(),
            pronouns: document.getElementById('pronouns').value.trim(),
            email: document.getElementById('email').value.trim(),
            avatar_url: document.getElementById('avatar_url').value.trim()
          })
        });
        const json = await res.json();
        if (json.success) {
          if (json.data && json.data.username) {
            document.getElementById('username').value = json.data.username;
            const accountUsername = document.getElementById('accountUsername');
            if (accountUsername) accountUsername.textContent = json.data.username;
          }
          showMessage('✓ Profile updated!');
        } else showMessage('✗ ' + (json.error || 'Failed'), false);
      } catch (err) {
        console.error(err);
        showMessage('✗ Network error', false);
      }
    });

    const themeSelect = document.getElementById('theme');
    themeSelect.addEventListener('change', function() {
      const theme = this.value;
      try { localStorage.setItem('pref_theme', theme); } catch (e) {}
      applyTheme();
    });

    document.getElementById('preferencesForm').addEventListener('submit', (e) => {
      e.preventDefault();
      localStorage.setItem('pref_default_view', document.querySelector('input[name="default_view"]:checked').value);
      localStorage.setItem('pref_sort', document.getElementById('sort_preference').value);
      localStorage.setItem('pref_per_page', document.getElementById('ideas_per_page').value);
      showMessage('✓ Preferences saved!');
    });

    document.getElementById('theme').value = localStorage.getItem('pref_theme') || 'auto';
    document.querySelector(`input[name="default_view"][value="${localStorage.getItem('pref_default_view') || 'all'}"]`).checked = true;
    document.getElementById('sort_preference').value = localStorage.getItem('pref_sort') || 'date';
    document.getElementById('ideas_per_page').value = localStorage.getItem('pref_per_page') || '20';

    document.getElementById('notificationsForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const prefs = {
        idea_claimed: document.getElementById('notify_idea_claimed').checked,
        idea_completed: document.getElementById('notify_idea_completed').checked,
        new_message: document.getElementById('notify_new_message').checked,
        new_follower: document.getElementById('notify_new_follower').checked,
        direct_message: document.getElementById('notify_direct_message').checked,
        mention: document.getElementById('notify_mention').checked
      };
      localStorage.setItem('notify_prefs', JSON.stringify(prefs));
      showMessage('✓ Notification settings saved!');
    });

    document.getElementById('privacyForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const privacy = {
        profile_public: document.getElementById('profile_public').checked,
        show_followers: document.getElementById('show_followers').checked,
        allow_dms: document.getElementById('allow_dms').checked,
        show_email: document.getElementById('show_email').checked,
        mention_preference: document.getElementById('mention_preference').value
      };
      localStorage.setItem('privacy_prefs', JSON.stringify(privacy));
      showMessage('✓ Privacy settings saved!');
    });

    const privacyPrefs = JSON.parse(localStorage.getItem('privacy_prefs') || '{}');
    document.getElementById('profile_public').checked = privacyPrefs.profile_public !== false;
    document.getElementById('show_followers').checked = privacyPrefs.show_followers !== false;
    document.getElementById('allow_dms').checked = privacyPrefs.allow_dms !== false;
    document.getElementById('show_email').checked = privacyPrefs.show_email === true;
    document.getElementById('mention_preference').value = privacyPrefs.mention_preference || 'anyone';

    const notifyPrefs = JSON.parse(localStorage.getItem('notify_prefs') || '{}');
    document.getElementById('notify_idea_claimed').checked = notifyPrefs.idea_claimed !== false;
    document.getElementById('notify_idea_completed').checked = notifyPrefs.idea_completed !== false;
    document.getElementById('notify_new_message').checked = notifyPrefs.new_message !== false;
    document.getElementById('notify_new_follower').checked = notifyPrefs.new_follower !== false;
    document.getElementById('notify_direct_message').checked = notifyPrefs.direct_message !== false;
    document.getElementById('notify_mention').checked = notifyPrefs.mention !== false;

    const adminForm = document.getElementById('adminMessageForm');
    if (adminForm) {
      adminForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const statusEl = document.getElementById('adminMessageStatus');
        const subject = document.getElementById('adminSubject').value.trim();
        const message = document.getElementById('adminMessage').value.trim();
        const attachmentInput = document.getElementById('adminAttachment');
        if (!subject || !message) {
          statusEl.innerHTML = '<div class="error-message">Subject and message are required.</div>';
          return;
        }
        let attachmentUrl = null;
        if (attachmentInput && attachmentInput.files.length > 0) {
          statusEl.innerHTML = '<div class="info-card">Uploading attachment…</div>';
          const file = attachmentInput.files[0];
          const formData = new FormData();
          formData.append('file', file);
          try {
            const uploadRes = await fetch('api.php?action=upload', {
              method: 'POST',
              credentials: 'same-origin',
              body: formData
            });
            const uploadJson = await uploadRes.json();
            if (!uploadJson.success) {
              setStatusMessage(statusEl, 'File upload failed: ' + (uploadJson.error || 'Unknown error'), false);
              return;
            }
            attachmentUrl = uploadJson.url;
          } catch (err) {
            setStatusMessage(statusEl, 'Network error uploading attachment', false);
            console.error(err);
            return;
          }
        }
        try {
          const res = await fetch('api.php?action=admin_message_send', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({subject, message, attachment_url: attachmentUrl})
          });
          const json = await res.json();
          if (json.success) {
            adminForm.reset();
            setStatusMessage(statusEl, 'Message sent to admins. They will respond soon.', true);
          } else {
            setStatusMessage(statusEl, (json.error || 'Failed to send message'), false);
          }
        } catch (err) {
          setStatusMessage(statusEl, 'Network error sending message', false);
          console.error(err);
        }
      });
    }

    document.addEventListener('DOMContentLoaded', loadProfileData);
  </script>
  <?php render_app_footer(); ?>
  <?php render_theme_script(); ?>
</body>
</html>
