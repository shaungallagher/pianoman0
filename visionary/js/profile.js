(function () {
  const dataEl = document.getElementById("profile-data");
  const init = dataEl ? JSON.parse(dataEl.textContent || "{}") : {};

  const currentUser = init.currentUser || null;

  function escapeHtml(s) {
    return String(s || "").replace(/[&<>"']/g, c => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    })[c]);
  }

  function linkifyMentions(text) {
    if (!text) return "";
    const escaped = escapeHtml(text);
    return escaped.replace(/@(\w+)/g, '<a href="user/$1">@$1</a>');
  }

  function formatDate(iso) {
    if (!iso) return "";
    try {
      const d = new Date(iso);
      const s = Math.floor((Date.now() - d.getTime()) / 1000);

      if (s < 10) return "just now";
      if (s < 60) return `${s} seconds ago`;
      if (s < 3600) {
        const m = Math.floor(s / 60);
        return m === 1 ? "1 minute ago" : `${m} minutes ago`;
      }
      if (s < 86400) {
        const h = Math.floor(s / 3600);
        return h === 1 ? "1 hour ago" : `${h} hours ago`;
      }
      if (s < 7 * 86400) {
        const d2 = Math.floor(s / 86400);
        return d2 === 1 ? "1 day ago" : `${d2} days ago`;
      }

      return (
        d.toLocaleDateString(undefined, {
          year: "numeric",
          month: "short",
          day: "numeric"
        }) +
        " at " +
        d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
      );
    } catch {
      return iso;
    }
  }

  function renderProfile(profile) {
    const wrap = document.getElementById("profile-wrap");
    if (!wrap) return;

    wrap.innerHTML = `
      <h1>${escapeHtml(profile.username)}</h1>
      ${profile.pronouns ? `<p class="pronouns">${escapeHtml(profile.pronouns)}</p>` : ""}
      <div id="bio-wrap">${linkifyMentions(profile.bio || "")}</div>
    `;

    if (currentUser && currentUser.id === profile.userId) {
      const btn = document.createElement("button");
      btn.textContent = "Edit Profile";
      btn.addEventListener("click", editProfile);
      wrap.appendChild(btn);
    }
  }

  async function loadProfile() {
    try {
      const response = await fetch("api.php?action=profile", {
        credentials: "same-origin"
      });

      if (!response.ok) {
        throw new Error(`HTTP error ${response.status}`);
      }

      const profileData = await response.json();
      renderProfile(profileData);
    } catch (err) {
      console.error("Error loading profile:", err);
      document.getElementById("content").innerText = "Error loading profile";
    }
  }

  async function editProfile() {
    const bio = document.getElementById("bio-wrap").innerText;
    const avatar = prompt("Enter new avatar URL:");
    const pronounsEl = document.querySelector(".pronouns");
    const pronouns = pronounsEl ? pronounsEl.innerText : null;

    try {
      const response = await fetch("api.php?action=update_profile", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          bio,
          avatar_url: avatar,
          pronouns
        })
      });

      const data = await response.json();

      if (data.success) {
        document.getElementById("bio-wrap").innerHTML =
          linkifyMentions(data.bio || "");

        if (pronouns && pronounsEl) {
          pronounsEl.innerText = pronouns;
        }
      } else {
        alert(data.error || "Failed to update profile");
      }
    } catch (err) {
      console.error("Error updating profile:", err);
      alert("Network error");
    }
  }

  document.addEventListener("DOMContentLoaded", loadProfile);
})();
