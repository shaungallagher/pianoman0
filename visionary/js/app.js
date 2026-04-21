document.addEventListener('DOMContentLoaded', () => {
  const listEl = document.getElementById('list');
  const hasIdeaList = !!listEl;
  
  function safeSetList(html) {
    if (!listEl) return;
    listEl.innerHTML = html;
  }
  
  const settingsBtn = document.getElementById('settingsBtn');
  if (settingsBtn) {
    settingsBtn.addEventListener('click', () => { window.location.href = 'settings.php'; });
  }
  const cancelBtn = document.getElementById('cancelBtn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => { window.location.href = 'ideas.php'; });
  }

  let allIdeas = [];
  let totalIdeas = 0;
  let currentFilter = 'all';
  let searchQuery = '';
  let currentSort = 'date';
  let currentOffset = 0;
  const limit = 20;
  
  const notifRoot = document.getElementById('notifications-root');
  let notifOpen = false;
  async function fetchNotificationsList() {
    try {
      const res = await fetch('api.php?action=notifications_list', {credentials: 'same-origin'});
      return await res.json();
    } catch (e) { return {success:false}; }
  }
  async function fetchNotificationsCount() {
    try {
      const res = await fetch('api.php?action=notifications_count', {credentials: 'same-origin'});
      return await res.json();
    } catch (e) { return {success:false}; }
  }
  async function markRead(id) {
    try {
      await fetch('api.php?action=notifications_mark_read', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id})});
    } catch (e){}
  }
  async function markAllRead() {
    try {
      await fetch('api.php?action=notifications_mark_all_read', {method:'POST', credentials:'same-origin'});
    } catch (e){}
  }
  function timeAgo(iso) {
    try {
      const d = new Date(iso);
      const s = Math.floor((Date.now() - d.getTime())/1000);
      if (s < 10) return 'just now';
      if (s < 60) return s + ' seconds ago';
      if (s < 3600) return Math.floor(s/60) + (Math.floor(s/60) === 1 ? ' minute ago' : ' minutes ago');
      if (s < 86400) return Math.floor(s/3600) + (Math.floor(s/3600) === 1 ? ' hour ago' : ' hours ago');
      if (s < 7*86400) return Math.floor(s/86400) + (Math.floor(s/86400) === 1 ? ' day ago' : ' days ago');
      return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) + ' at ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    } catch (e) { return '' }
  }

  function linkifyMentions(text){
    if (!text) return '';
    const esc = escapeHtml(text);
    return esc.replace(/@([A-Za-z0-9_.-]+)/g, function(_, uname){
      const u = encodeURIComponent(uname);
      return '<a class="mention" href="profile.php?username=' + u + '">@' + escapeHtml(uname) + '</a>';
    }).replace(/\n/g, '<br>');
  }
  function renderNotificationsDropdown(notifs) {
    if (!notifRoot) return;
    let dd = notifRoot.querySelector('.notif-dropdown');
    if (!dd) {
      dd = document.createElement('div');
      dd.className = 'notif-dropdown';
      dd.innerHTML = `<div class="head"><strong>Notifications</strong><div><button class="notif-action" data-action="markall">Mark all read</button></div></div><div class="list"></div>`;
      notifRoot.appendChild(dd);
      dd.style.display = 'none';
      dd.querySelector('[data-action="markall"]').addEventListener('click', async (e)=>{ await markAllRead(); await refreshNotifications(); });
    }
    const list = dd.querySelector('.list');
    list.innerHTML = '';
    if (!notifs || !notifs.length) {
      list.innerHTML = '<div class="notif-empty">No notifications</div>'; return;
    }
    notifs.forEach(n => {
      const item = document.createElement('div');
      item.className = 'notif-item' + (n.is_read==0 ? ' unread' : '');
      const msg = document.createElement('div'); msg.className='msg'; msg.innerText = n.message || 'Notification';
      const time = document.createElement('div'); time.className='time'; time.innerText = timeAgo(n.created_at || n.createdAt || '');
      item.appendChild(msg);
      item.appendChild(time);
      item.addEventListener('click', async ()=>{
        if (n.url) {
          await markRead(n.id);
          window.location = n.url;
        } else {
          await markRead(n.id);
          await refreshNotifications();
        }
      });
      list.appendChild(item);
    });
  }
  async function refreshNotifications() {
    if (!notifRoot) return;
    const json = await fetchNotificationsList();
    if (json && json.success) {
      renderNotificationsDropdown(json.data);
    }
    const cjson = await fetchNotificationsCount();
    const count = (cjson && cjson.success && cjson.data && cjson.data.count) ? cjson.data.count : 0;
    const bell = notifRoot.querySelector('.notif-bell');
    if (bell) {
      const cntEl = bell.querySelector('.count');
      if (cntEl) cntEl.innerText = count > 99 ? '99+' : String(count);
      bell.style.display = count ? 'inline-block' : 'inline-block';
      if (count == 0) cntEl.style.display = 'none'; else cntEl.style.display = 'inline-block';
    }
  }
  if (notifRoot) {
    notifRoot.innerHTML = '<button class="notif-bell" title="Notifications"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17H9m6 0a3 3 0 11-6 0m8-4v-3a6 6 0 10-12 0v3l-2 2v1h18v-1l-2-2z"></path></svg><span class="count" style="display:none">0</span></button>';
    const bell = notifRoot.querySelector('.notif-bell');
    bell.addEventListener('click', async (e)=>{
      e.stopPropagation();
      notifOpen = !notifOpen;
      const dd = notifRoot.querySelector('.notif-dropdown');
      if (notifOpen) {
        if (!dd) await refreshNotifications();
        if (dd) dd.style.display = 'block';
      } else if (dd) dd.style.display = 'none';
    });
    document.addEventListener('click', ()=>{ notifOpen=false; const dd=notifRoot.querySelector('.notif-dropdown'); if (dd) dd.style.display='none'; });
    refreshNotifications();
    setInterval(refreshNotifications, 20000);
  }
  const form = document.getElementById('ideaForm');
  let CURRENT_USER = window.CURRENT_USER || null;

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const user = await fetchCurrentUser();
      if (!user || !['poster','both'].includes(user.role)) return alert('Not authorized to post ideas');
      const title = form.title.value.trim();
      const description = form.description.value.trim();
      const tags = form.tags.value.trim();
      if (!title) return alert('Title required');
      try {
        const res = await fetch('api.php?action=create', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({title, description, tags})
        });
        const json = await res.json();
        if (json.success) {
          form.reset();
          currentOffset = 0;
          fetchList(false);
          const msg = document.getElementById('ideaFormMessage');
          if (msg) { msg.textContent = 'Idea posted successfully!'; msg.style.display = 'block'; setTimeout(() => msg.style.display = 'none', 3200); }
        } else {
          const msg = document.getElementById('ideaFormMessage');
          if (msg) { msg.textContent = json.error || 'Failed to post idea'; msg.style.display = 'block'; msg.style.color = '#842029'; msg.style.background = '#f8d7da'; msg.style.borderColor = '#f5c2c7'; }
          else alert(json.error || 'Failed to post idea');
        }
      } catch (err) {
        console.error('create idea error', err);
        alert('Network error');
      }
    });
  }

  const templateSelect = document.getElementById('templateSelect');
  if (templateSelect) {
    templateSelect.addEventListener('change', () => {
      const val = templateSelect.value;
      if (val === 'web app') {
        form.title.value = 'New Web App Idea';
        form.description.value = 'Describe your innovative web app idea here. What problem does it solve? Who is the target audience?';
        form.tags.value = 'web, app';
      } else if (val === 'mobile app') {
        form.title.value = 'New Mobile App Idea';
        form.description.value = 'Describe your mobile app concept. What platforms? Key features?';
        form.tags.value = 'mobile, app';
      } else if (val === 'game') {
        form.title.value = 'New Game Idea';
        form.description.value = 'Describe your game idea. Genre? Mechanics? Story?';
        form.tags.value = 'game';
      }
    });
  }

  async function fetchCurrentUser() {
    if (CURRENT_USER === null) {
      try {
        const res = await fetch('api.php?action=current_user', {credentials: 'same-origin'});
        const json = await res.json();
        if (json.success) CURRENT_USER = json.data;
      } catch (err) {
        console.error('fetchCurrentUser error', err);
      }
    }
    return CURRENT_USER;
  }

  async function fetchList(append = false) {
    if (!hasIdeaList) return;
    try {
      const res = await fetch(`api.php?action=list&sort=${currentSort}&limit=${limit}&offset=${currentOffset}`, {credentials: 'same-origin'});
      const json = await res.json();
      if (json && json.success) {
        if (append) {
          allIdeas = allIdeas.concat(json.data);
        } else {
          allIdeas = json.data;
        }
        totalIdeas = json.total;
        filterAndRenderList();
      }
      else console.error('fetchList failed', json);
    } catch (err) {
      console.error('fetchList error', err);
      safeSetList('<p>Failed to load ideas (check console)</p>');
    }
  }

  function filterAndRenderList() {
    let filtered = allIdeas;
    
    if (currentFilter === 'my') {
      filtered = filtered.filter(item => item.author_name === CURRENT_USER.username);
    } else if (currentFilter !== 'all') {
      filtered = filtered.filter(item => item.status === currentFilter);
    }
    
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(item => 
        (item.title && item.title.toLowerCase().includes(query)) ||
        (item.description && item.description.toLowerCase().includes(query)) ||
        (item.author_name && item.author_name.toLowerCase().includes(query))
      );
    }
    
    renderList(filtered);
  }

  function renderList(items) {
    if (!hasIdeaList) return;
    listEl.innerHTML = '';
    if (!items.length) {
      listEl.innerHTML = '<p>No ideas yet. Be the first!</p>';
      return;
    }
    items.forEach(item => {
      const card = document.createElement('div');
      card.className = 'idea';
      const authorHtml = item.author_name ? ('<a href="profile.php?username=' + encodeURIComponent(item.author_name) + '">' + escapeHtml(item.author_name) + '</a>') : 'Anonymous';
      const devHtml = item.developer_name ? (' — Dev: <a href="profile.php?username=' + encodeURIComponent(item.developer_name) + '">' + escapeHtml(item.developer_name) + '</a>') : '';
      card.innerHTML = `
        <h3><a href="idea.php?id=${item.id}">${escapeHtml(item.title)}</a></h3>
        <p class="meta">By ${authorHtml} — <strong>${escapeHtml(item.status)}</strong>${devHtml}</p>
        <p>${linkifyMentions(item.description || '')}</p>
        <p style="font-size:12px;color:#666;margin:4px 0;">${item.likes_count || 0} ❤ · ${item.messages_count || 0} 💬</p>
      `;
      if (item.tags) {
        const tagsP = document.createElement('p');
        tagsP.style.fontSize = '12px';
        tagsP.style.color = '#666';
        tagsP.style.margin = '4px 0';
        tagsP.textContent = 'Tags: ' + item.tags;
        card.appendChild(tagsP);
      }
      const actions = document.createElement('div');
      actions.className = 'actions';
      actions.style.display = 'flex';
      actions.style.gap = '8px';
      actions.style.flexWrap = 'wrap';
      
      // Like button
      const likeBtn = document.createElement('button');
      likeBtn.textContent = item.user_liked ? '❤ Unlike' : '❤ Like';
      likeBtn.style.opacity = '0.7';
      likeBtn.onclick = async (e) => {
        e.stopPropagation();
        const user = await fetchCurrentUser();
        if (!user) return alert('Sign in to like ideas');
        try {
          const isLiked = item.user_liked;
          const action = isLiked ? 'unlike_idea' : 'like_idea';
          const res = await fetch('api.php?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({idea_id: item.id})
          });
          const json = await res.json();
          if (json.success) {
            item.user_liked = !isLiked;
            likeBtn.textContent = item.user_liked ? '❤ Unlike' : '❤ Like';
            const statsLine = card.querySelector('p:nth-of-type(2)');
            if (statsLine) statsLine.textContent = json.data.likes_count + ' ❤ · ' + (item.messages_count || 0) + ' 💬';
            item.likes_count = json.data.likes_count;
          } else alert(json.error || 'Failed');
        } catch (err) {
          console.error('like error', err);
        }
      };
      actions.appendChild(likeBtn);
      
      // Favorite button
      const favBtn = document.createElement('button');
      favBtn.textContent = item.user_favorited ? '⭐ Unfavorite' : '☆ Favorite';
      favBtn.style.opacity = '0.7';
      favBtn.onclick = async (e) => {
        e.stopPropagation();
        const user = await fetchCurrentUser();
        if (!user) return alert('Sign in to favorite ideas');
        try {
          const isFav = item.user_favorited;
          const action = isFav ? 'unfavorite_idea' : 'favorite_idea';
          const res = await fetch('api.php?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({idea_id: item.id})
          });
          const json = await res.json();
          if (json.success) {
            item.user_favorited = !isFav;
            favBtn.textContent = item.user_favorited ? '⭐ Unfavorite' : '☆ Favorite';
          } else alert(json.error || 'Failed');
        } catch (err) {
          console.error('favorite error', err);
        }
      };
      actions.appendChild(favBtn);
      
      if (item.status === 'open') {
        const claimBtn = document.createElement('button');
        claimBtn.textContent = 'Claim as dev';
        claimBtn.onclick = () => claim(item.id);
        actions.appendChild(claimBtn);
      }
      if (item.status === 'in_progress') {
        const completeBtn = document.createElement('button');
        completeBtn.textContent = 'Mark complete';
        completeBtn.onclick = () => complete(item.id);
        actions.appendChild(completeBtn);
      }
      card.appendChild(actions);
      listEl.appendChild(card);
    });
    
    if (allIdeas.length < totalIdeas) {
      const loadMoreDiv = document.createElement('div');
      loadMoreDiv.style.textAlign = 'center';
      loadMoreDiv.style.margin = '20px 0';
      const loadMoreBtn = document.createElement('button');
      loadMoreBtn.textContent = 'Load More Ideas';
      loadMoreBtn.onclick = () => {
        currentOffset += limit;
        fetchList(true);
      };
      loadMoreDiv.appendChild(loadMoreBtn);
      listEl.appendChild(loadMoreDiv);
    }
  }

  function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function claim(id) {
    const user = await fetchCurrentUser();
    if (!user) return alert('Sign in as developer to claim');
    if (!['dev','both'].includes(user.role)) return alert('Only developers can claim ideas');
    try {
      const res = await fetch('api.php?action=claim', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id})});
      const json = await res.json();
      if (json.success) fetchList(); else alert(json.error || 'Failed');
    } catch (err) {
      console.error('claim error', err);
      alert('Network or server error claiming idea (see console)');
    }
  }

  async function complete(id) {
    if (!confirm('Mark this idea as completed?')) return;
    try {
      const res = await fetch('api.php?action=complete', {method:'POST', credentials: 'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id})});
      const json = await res.json();
      if (json.success) fetchList(); else alert(json.error || 'Failed');
    } catch (err) {
      console.error('complete error', err);
      alert('Network or server error completing idea (see console)');
    }
  }

  const searchInput = document.getElementById('searchInput');
  const statusFilter = document.getElementById('statusFilter');
  
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value;
      filterAndRenderList();
    });
  }
  
  if (statusFilter) {
    statusFilter.addEventListener('change', (e) => {
      currentFilter = e.target.value;
      filterAndRenderList();
    });
  }

  const sortSelect = document.getElementById('sortSelect');
  if (sortSelect) {
    sortSelect.addEventListener('change', (e) => {
      currentSort = e.target.value;
      fetchList();
    });
  }

  const exportBtn = document.getElementById('exportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      const data = JSON.stringify(allIdeas, null, 2);
      const blob = new Blob([data], {type: 'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'ideas.json';
      a.click();
    });
  }

  if (hasIdeaList) {
    fetchList();
  }
  
  const dd = notifRoot && notifRoot.querySelector('.notif-dropdown');
  if (dd) dd.style.display = 'none';
});

