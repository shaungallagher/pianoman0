<?php
require_once __DIR__ . '/../auth.php';

function app_nav_links($user) {
    $links = [
        ['url' => 'index.php', 'label' => 'Home', 'icon' => '🏠'],
        ['url' => 'ideas.php', 'label' => 'Ideas', 'icon' => '💡'],
        ['url' => 'people.php', 'label' => 'People', 'icon' => '👥'],
        ['url' => 'analytics.php', 'label' => 'Analytics', 'icon' => '📈'],
    ];
    if ($user) {
        $links[] = ['url' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'];
        $links[] = ['url' => 'notifications.php', 'label' => 'Notifications', 'icon' => '🔔'];
        $links[] = ['url' => 'settings.php', 'label' => 'Settings', 'icon' => '⚙️'];
        $links[] = ['url' => 'logout.php', 'label' => 'Logout', 'icon' => '🚪'];
    } else {
        $links[] = ['url' => 'login.php', 'label' => 'Login', 'icon' => '🔐'];
        $links[] = ['url' => 'signup.php', 'label' => 'Sign Up', 'icon' => '✍️'];
    }
    return $links;
}

function render_app_header($title = 'Visionary', $subtitle = '', $user = null) {
    if ($user === null) {
        $user = current_user();
    }
    $links = app_nav_links($user);
    echo '<header class="app-header">';
    echo '<div class="app-header-inner">';
    echo '<div class="brand"><a href="index.php">Visionary</a><span class="brand-sub">' . htmlspecialchars($subtitle) . '</span></div>';
    echo '<nav class="app-nav"><ul>';
    foreach ($links as $link) {
        echo '<li><a href="' . htmlspecialchars($link['url']) . '">';
        echo '<span class="nav-icon">' . htmlspecialchars($link['icon']) . '</span> ' . htmlspecialchars($link['label']);
        echo '</a></li>';
    }
    echo '</ul></nav>';
    echo '<div class="header-controls">';
    if ($user) {
        echo '<div id="notifications-root" class="notifications"></div>';
    }
    echo '<button id="themeToggle" class="theme-toggle" aria-label="Toggle theme">🌙</button>';
    if ($user) {
        echo '<a class="avatar-link" href="profile.php?username=' . urlencode($user['username']) . '">' . htmlspecialchars($user['username']) . '</a>';
    }
    echo '</div>';
    echo '</div>';
    echo '</header>';
}

function render_app_footer() {
    $year = date('Y');
    echo '<footer class="app-footer"><p>Visionary © ' . $year . ' · <a href="privacy.html">Privacy</a> · <a href="terms.html">Terms</a></p></footer>';
}

function render_theme_script() {
    $csrf = csrf_token();
    echo "<script nonce=\"" . htmlspecialchars($GLOBALS['nonce'] ?? '') . "\">\n";
    echo "window.VISIONARY_CSRF = '" . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . "';\n";
    echo "(function() {\n";
    echo "  function prefersDark() { return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; }\n";
    echo "  function applyThemeInternal() {\n";
    echo "    var pref = localStorage.getItem('pref_theme');\n";
    echo "    var isDark = false;\n";
    echo "    if (pref === 'dark') { isDark = true; } else if (pref === 'light') { isDark = false; } else { isDark = prefersDark(); }\n";
    echo "    if (isDark) document.body.classList.add('dark'); else document.body.classList.remove('dark');\n";
    echo "    // Backwards compatibility for older code that reads visionary-dark\n";
    echo "    try { localStorage.setItem('visionary-dark', isDark ? '1' : '0'); } catch (e) {}\n";
    echo "  }\n";
    echo "  const originalFetch = window.fetch.bind(window);\n";
    echo "  window.fetch = function(input, init) {\n";
    echo "    let method = 'GET';\n";
    echo "    let url = input;\n";
    echo "    if (input instanceof Request) { method = input.method || 'GET'; url = input.url; } else if (init && init.method) { method = init.method; }\n";
    echo "    method = method.toUpperCase();\n";
    echo "    if (typeof url === 'string' && url.includes('api.php') && !['GET','HEAD'].includes(method)) {\n";
    echo "      init = init || {};\n";
    echo "      init.credentials = init.credentials || 'same-origin';\n";
    echo "      init.headers = Object.assign({}, init.headers, {'X-CSRF-Token': window.VISIONARY_CSRF});\n";
    echo "    }\n";
    echo "    return originalFetch(input, init);\n";
    echo "  };\n";
    echo "  document.addEventListener('DOMContentLoaded', function() {\n";
    echo "    applyThemeInternal();\n";
    echo "    var themeToggle = document.getElementById('themeToggle');\n";
    echo "    if (themeToggle) {\n";
    echo "      function updateToggleIcon() { if (!themeToggle) return; themeToggle.textContent = document.body.classList.contains('dark') ? '☀️' : '🌙'; }\n";
    echo "      updateToggleIcon();\n";
    echo "      themeToggle.addEventListener('click', function() {\n";
    echo "        // toggle explicit theme between dark and light (sets pref_theme), keep legacy key in sync\n";
    echo "        var isDarkNow = document.body.classList.contains('dark');\n";
    echo "        var next = isDarkNow ? 'light' : 'dark';\n";
    echo "        try { localStorage.setItem('pref_theme', next); } catch (e) {}\n";
    echo "        applyThemeInternal();\n";
    echo "        updateToggleIcon();\n";
    echo "      });\n";
    echo "    }\n";
    echo "  });\n";
    echo "    // React to system preference changes when in 'auto' mode\n";
    echo "    if (window.matchMedia) {\n";
    echo "      try { window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() { if (localStorage.getItem('pref_theme') === 'auto') applyThemeInternal(); }); } catch(e) {}\n";
    echo "    }\n";
    echo "})();\n";
    echo "</script>\n";
}
