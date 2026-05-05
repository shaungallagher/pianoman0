(async function () {
  const indicator = document.getElementById('slack-status-indicator');
  const dot = indicator && indicator.querySelector('.dot');
  const text = indicator && indicator.querySelector('.text');
  const username = 'pianoman0';
  const endpoint = '/slack-status.php';

  function setLoading() {
    indicator.className = 'loading';
    dot.style.background = '#9e9e9e';
    text.textContent = 'Checking Slack...';
  }

  function setOnline() {
    indicator.className = 'online';
    dot.style.background = '#00e676';
    text.textContent = '@' + username + ' is online';
  }

  function setOffline() {
    indicator.className = 'offline';
    dot.style.background = '#9e9e9e';
    text.textContent = '@' + username + ' is offline';
  }

  function setError() {
    indicator.className = 'offline';
    dot.style.background = '#ff7043';
    text.textContent = 'Status unavailable';
  }

  if (!indicator) return;

  async function fetchStatus() {
    try {
      setLoading();
      const res = await fetch(endpoint + '?user=' + encodeURIComponent(username), { cache: 'no-store' });
      if (!res.ok) throw new Error('Network');
      const j = await res.json();
      if (j && j.ok && j.presence) {
        if (j.presence === 'active' || j.presence === 'online') setOnline();
        else setOffline();
      } else {
        setError();
      }
    } catch (e) {
      setError();
    }
  }

  // Fetch status every 30s
  fetchStatus();
  setInterval(fetchStatus, 30000);
})();
