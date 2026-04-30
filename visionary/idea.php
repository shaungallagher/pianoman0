<?php
$nonce = bin2hex(random_bytes(16));
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();
$id = (int)($_REQUEST['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo "Missing idea id";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Visionary — Idea</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <?php render_app_header('Idea Details', 'View and interact with a single idea', $user); ?>
  <main style="max-width:900px;margin:24px auto;padding:0 16px">
    <section id="idea-view">
      <h2 id="idea-title">Loading…</h2>
      <p class="meta" id="idea-meta"></p>
      <p id="idea-desc"></p>
      <div id="idea-stats" style="margin:12px 0;padding:12px;background:#f5f5f5;border-radius:4px;font-size:14px;display:flex;gap:16px">
        <span id="stat-likes">❤ 0 likes</span>
        <span id="stat-msgs">💬 0 messages</span>
      </div>
      <div id="idea-actions" style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button id="likeBtn" style="padding:6px 12px">❤ Like</button>
      </div>
      <div id="idea-extra-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"></div>
      <div id="shareButtons" style="margin-top:8px"></div>
    </section>

    <section id="chat">
      <h3>Chat</h3>
      <div id="messages" style="border:1px solid #ddd;padding:12px;min-height:120px;max-height:400px;overflow:auto;background:#fff"></div>
      <?php if ($user): ?>
      <form id="msgForm" style="margin-top:8px" action="?id=<?=$id?>" method="post">
        <input type="hidden" name="id" value="<?=$id?>">
        <input type="hidden" name="idea_id" value="<?=$id?>">
        <input type="text" id="msgInput" name="message" placeholder="Say something…" style="width:60%" required />
        <input type="file" id="msgFile" name="file" style="width:20%" />
        <button type="submit">Send</button>
      </form>
      <?php else: ?>
      <p><a href="login.php">Log in</a> to participate in the chat.</p>
      <?php endif; ?>
    </section>

  </main>
  <script nonce="<?=$nonce?>">
    const IDEA_ID = <?=json_encode($id)?>;
    const CURRENT_USER = <?=json_encode($user)?>;
  </script>
  <script nonce="<?=$nonce?>" defer src="<?=asset_url('js/app.js')?>"></script>
  <script nonce="<?=$nonce?>">
    async function fetchIdea() {
      try {
        const res = await fetch('api.php?action=idea&id=' + IDEA_ID, {credentials: 'same-origin'});
        const json = await res.json();
        if (json.success) {
          const data = json.data;
          document.getElementById('idea-title').textContent = data.title;
          const authorLink = data.author_name ? ('<a href="profile.php?username=' + encodeURIComponent(data.author_name) + '">' + escapeHtml(data.author_name) + '</a>') : 'Anonymous';
          const devPart = data.developer_name ? (' — Dev: <a href="profile.php?username=' + encodeURIComponent(data.developer_name) + '">' + escapeHtml(data.developer_name) + '</a>') : '';
          document.getElementById('idea-meta').innerHTML = 'By ' + authorLink + ' — <strong>' + escapeHtml(data.status) + '</strong>' + devPart;
          document.getElementById('idea-desc').innerHTML = linkifyMentions(data.description || '');
          renderShareButtons(data);
          const extraActions = document.getElementById('idea-extra-actions');
          if (extraActions) {
            extraActions.innerHTML = '';
            if (data.status === 'open' && CURRENT_USER && ['dev','both'].includes(CURRENT_USER.role)) {
              const claimBtn = document.createElement('button');
              claimBtn.textContent = 'Claim as dev';
              claimBtn.addEventListener('click', () => {
                claim(data.id).then(fetchIdea).catch(console.error);
              });
              extraActions.appendChild(claimBtn);
            }
            if (data.status === 'in_progress' && CURRENT_USER && ['dev','both'].includes(CURRENT_USER.role)) {
              const completeBtn = document.createElement('button');
              completeBtn.textContent = 'Mark complete';
              completeBtn.addEventListener('click', () => {
                complete(data.id).then(fetchIdea).catch(console.error);
              });
              extraActions.appendChild(completeBtn);
            }
            if (CURRENT_USER && CURRENT_USER.username === data.author_name) {
              const editBtn = document.createElement('button');
              editBtn.textContent = 'Edit Idea';
              editBtn.addEventListener('click', () => editIdea(data));
              extraActions.appendChild(editBtn);
              const deleteBtn = document.createElement('button');
              deleteBtn.textContent = 'Delete Idea';
              deleteBtn.style.background = '#f00';
              deleteBtn.addEventListener('click', () => {
                if (confirm('Delete this idea? This cannot be undone.')) {
                  fetch('api.php?action=delete_idea', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({id: data.id})
                  }).then(r => r.json()).then(json => {
                    if (json.success) {
                      window.location.href = 'ideas.php';
                    } else alert(json.error || 'Failed to delete');
                  }).catch(err => alert('Network error'));
                }
              });
              extraActions.appendChild(deleteBtn);
            } else if (CURRENT_USER) {
              const reportBtn = document.createElement('button');
              reportBtn.textContent = 'Report Idea';
              reportBtn.addEventListener('click', () => {
                const reason = prompt('Reason for reporting this idea:');
                if (reason) {
                  fetch('api.php?action=report_idea', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({id: data.id, reason})
                  }).then(r => r.json()).then(json => {
                    alert(json.success ? 'Idea reported' : 'Failed to report');
                  }).catch(err => alert('Network error'));
                }
              });
              extraActions.appendChild(reportBtn);
            }
          }
          loadIdeaStats();
        } else {
          document.getElementById('idea-title').textContent = 'Error loading idea';
        }
      } catch (err) {
        console.error('fetchIdea error', err);
        document.getElementById('idea-title').textContent = 'Error loading idea';
      }
    }

    async function loadIdeaStats() {
      try {
        const res = await fetch('api.php?action=idea_stats&idea_id=' + IDEA_ID, {credentials: 'same-origin'});
        const json = await res.json();
        if (json.success) {
          const stats = json.data;
          document.getElementById('stat-likes').textContent = '❤ ' + stats.likes_count + ' ' + (stats.likes_count === 1 ? 'like' : 'likes');
          document.getElementById('stat-msgs').textContent = '💬 ' + stats.messages_count + ' ' + (stats.messages_count === 1 ? 'message' : 'messages');

          const likeBtn = document.getElementById('likeBtn');
          if (likeBtn) {
            if (stats.user_liked) {
              likeBtn.textContent = '❤ Unlike';
              likeBtn.style.background = '#ffcccc';
            } else {
              likeBtn.textContent = '❤ Like';
              likeBtn.style.background = '';
            }
          }
        }
      } catch (err) {
        console.error('loadIdeaStats error', err);
      }
    }

    async function fetchMessages() {
      try {
        const res = await fetch('api.php?action=messages&id=' + IDEA_ID, {credentials: 'same-origin'});
        const json = await res.json();
        if (json.success) renderMessages(json.data);
      } catch (err) {
        console.error('fetchMessages', err);
      }
    }

    function renderMessages(items) {
      const wrap = document.getElementById('messages');
      wrap.innerHTML = '';
      items.forEach(m => {
        const d = document.createElement('div');
        d.style.padding = '6px 0';
        let nameHtml = escapeHtml(m.username || 'Anon');
        if (m.user_id) nameHtml = '<a href="profile.php?id=' + encodeURIComponent(m.user_id) + '">' + nameHtml + '</a>';
        else if (m.username) nameHtml = '<a href="profile.php?username=' + encodeURIComponent(m.username) + '">' + nameHtml + '</a>';
        var msgHtml = '<strong>' + nameHtml + '</strong> <span style="color:#666;font-size:12px">' + (m.created_at ? ' — ' + formatDate(m.created_at) : '') + '</span><div>' + linkifyMentions(m.message) + '</div>';
        if (m.attachment_url) {
          var a = escapeHtml(m.attachment_url);
          if (/(\.png|\.jpg|\.jpeg|\.gif|\.webp)$/i.test(m.attachment_url)) {
            msgHtml += '<div style="margin-top:6px"><a href="' + a + '" target="_blank"><img src="' + a + '" style="max-width:320px;max-height:240px;border:1px solid #ddd"/></a></div>';
          } else {
            msgHtml += '<div style="margin-top:6px"><a href="' + a + '" target="_blank">Attachment</a></div>';
          }
        }
        d.innerHTML = msgHtml;
        wrap.appendChild(d);
      });
      wrap.scrollTop = wrap.scrollHeight;
    }

    function escapeHtml(s) {
      if (s === null || s === undefined) return '';
      return String(s).replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c];
      });
    }

    function formatDate(iso) {
      if (!iso) return '';
      try {
        const d = new Date(iso);
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return diff + 's';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 7 * 86400) return Math.floor(diff / 86400) + 'd';
        return d.toLocaleString();
      } catch (e) {
        return iso;
      }
    }

    function linkifyMentions(text) {
      if (!text) return '';
      const esc = escapeHtml(text);
      return esc.replace(/@(\w[\w\-\.]*)/g, function(_, uname) {
        const u = encodeURIComponent(uname);
        return '<a class="mention" href="profile.php?username=' + u + '">@' + escapeHtml(uname) + '</a>';
      }).replace(/\n/g, '<br>');
    }

    function renderShareButtons(data) {
      try {
        const container = document.getElementById('shareButtons');
        if (!container) return;

        const url = window.location.href;
        const title = data.title || '';
        const excerpt = data.description || '';

        const u = encodeURIComponent(url);
        const t = encodeURIComponent(title);
        const txt = encodeURIComponent(excerpt);

        const buttons = [
          {label:'Twitter', href:'https://twitter.com/intent/tweet?url='+u+'&text='+t},
          {label:'X', href:'https://twitter.com/intent/tweet?url='+u+'&text='+t},
          {label:'LinkedIn', href:'https://www.linkedin.com/sharing/share-offsite/?url='+u},
          {label:'Reddit', href:'https://www.reddit.com/submit?url='+u+'&title='+t},
          {label:'WhatsApp', href:'https://wa.me/?text='+encodeURIComponent(title+' '+url)},
          {label:'Telegram', href:'https://t.me/share/url?url='+u+'&text='+t},
          {label:'Email', href:'mailto:?subject='+t+'&body='+encodeURIComponent(excerpt+'\n\n'+url)},
          {label:'Copy Link', href:'#', copy:true}
        ];

        container.innerHTML = `
          <button id="shareToggle" class="share-main-btn">Share</button>
          <div id="sharePanel" class="share-panel" style="display:none;">
            ${buttons.map(b =>
              `<a class="share-btn" href="${b.href}"${b.copy ? ' data-copy="1"' : ''} target="_blank" rel="noopener">
                ${escapeHtml(b.label)}
              </a>`
            ).join('')}
          </div>
        `;

        const toggle = container.querySelector('#shareToggle');
        const panel = container.querySelector('#sharePanel');

        toggle.addEventListener('click', () => {
          panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        });

        panel.querySelectorAll('[data-copy]').forEach(el =>
          el.addEventListener('click', function(e) {
            e.preventDefault();
            if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url)
              .then(() => {
                el.textContent = 'Copied';
                setTimeout(() => el.textContent = 'Copy Link', 1200);
              })
              .catch(() => alert('Copy failed'));
          })
        );

      } catch (e) {
        console.warn('renderShareButtons', e);
      }
    }

    document.addEventListener('DOMContentLoaded', function(){
      fetchIdea();
      fetchMessages();
      setInterval(fetchMessages, 3000);
      
      // Like button handler
      const likeBtn = document.getElementById('likeBtn');
      if (likeBtn) {
        likeBtn.addEventListener('click', async (e) => {
          e.preventDefault();
          if (!CURRENT_USER) return alert('Sign in to like ideas');
          try {
            const isLiked = likeBtn.textContent.includes('Unlike');
            const action = isLiked ? 'unlike_idea' : 'like_idea';
            const res = await fetch('api.php?action=' + action, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({idea_id: IDEA_ID})
            });
            const json = await res.json();
            if (json.success) {
              loadIdeaStats();
            } else {
              alert(json.error || 'Failed to like idea');
            }
          } catch (err) {
            console.error('like error', err);
            alert('Network error');
          }
        });
      }
      
      var form = document.getElementById('msgForm');
      if (form) {
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          var input = document.getElementById('msgInput');
          var text = input.value.trim();
          if (!text) return;
          try {
            var fileInput = document.getElementById('msgFile');
            var attachmentUrl = null;
            if (fileInput && fileInput.files && fileInput.files[0]) {
              var fd = new FormData();
              fd.append('file', fileInput.files[0]);
              var up = await fetch('api.php?action=upload', { method: 'POST', body: fd, credentials: 'same-origin' });
              var upj = await up.json();
              if (upj.success) attachmentUrl = upj.url;
              else {
                alert('File upload failed: ' + (upj.error || 'Unknown error'));
                return;
              }
            }
            var res = await fetch('api.php?action=post_message', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({idea_id: IDEA_ID, message: text, attachment_url: attachmentUrl})
            });
            var json = await res.json();
            if (json.success) {
              input.value = '';
              if (fileInput) fileInput.value = '';
              fetchMessages();
            } else {
              alert(json.error || 'Failed to post message');
            }
          } catch (err) {
            console.error('post message', err);
            alert('Network error posting message');
          }
        });
      }
      
      
      fetchIdea();
      fetchMessages();
    });

    function editIdea(data) {
      const newTitle = prompt('New title:', data.title);
      const newDesc = prompt('New description:', data.description);
      if (newTitle && newDesc) {
        fetch('api.php?action=update_idea', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({id: data.id, title: newTitle, description: newDesc})
        }).then(r => r.json()).then(json => {
          if (json.success) {
            fetchIdea();
          } else alert(json.error || 'Failed to update');
        }).catch(err => alert('Network error'));
      }
    }
  </script>
  <?php render_app_footer(); ?>
  <?php render_theme_script(); ?>
</body>
</html>