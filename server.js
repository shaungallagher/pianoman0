const express = require('express');
const fetch = require('node-fetch');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;
const SLACK_BOT_TOKEN = process.env.SLACK_BOT_TOKEN;

if (!SLACK_BOT_TOKEN) {
  console.warn('Warning: SLACK_BOT_TOKEN is not set. /slack-status will return errors.');
}

app.use(express.static('.'));

// /slack-status?user=pianoman0
app.get('/slack-status', async (req, res) => {
  const user = req.query.user;
  if (!user) return res.json({ ok: false, error: 'missing_user' });

  if (!SLACK_BOT_TOKEN) return res.json({ ok: false, error: 'no_token' });

  try {
    const lookup = await fetch('https://slack.com/api/users.lookupByEmail?email=' + encodeURIComponent(user + '@example.com'), {
      headers: { Authorization: `Bearer ${SLACK_BOT_TOKEN}` }
    });
    const lookupJson = await lookup.json();

    let userId;
    if (lookupJson && lookupJson.ok && lookupJson.user) {
      userId = lookupJson.user.id;
    }

    if (!userId) {
      const list = await fetch('https://slack.com/api/users.list', {
        headers: { Authorization: `Bearer ${SLACK_BOT_TOKEN}` }
      });
      const listJson = await list.json();
      if (listJson && listJson.ok && listJson.members) {
        const match = listJson.members.find(m => {
          if (!m || !m.name) return false;
          return m.name.toLowerCase() === user.toLowerCase() || (m.profile && m.profile.display_name && m.profile.display_name.toLowerCase() === user.toLowerCase());
        });
        if (match) userId = match.id;
      }
    }

    if (!userId) return res.json({ ok: false, error: 'user_not_found' });

    const presenceResp = await fetch('https://slack.com/api/users.getPresence?user=' + encodeURIComponent(userId), {
      headers: { Authorization: `Bearer ${SLACK_BOT_TOKEN}` }
    });
    const presenceJson = await presenceResp.json();
    if (presenceJson && presenceJson.ok && presenceJson.presence) {
      return res.json({ ok: true, presence: presenceJson.presence });
    }

    return res.json({ ok: false, error: 'presence_failed' });
  } catch (err) {
    console.error(err);
    return res.json({ ok: false, error: 'exception' });
  }
});

app.listen(PORT, () => console.log(`Server listening on http://localhost:${PORT}`));
