<?php

function github_parse_repo_url($repoUrl) {
    $repoUrl = trim((string)$repoUrl);
    if ($repoUrl === '') return null;

    // Accept common forms:
    // - https://github.com/owner/repo
    // - https://github.com/owner/repo/
    // - git@github.com:owner/repo.git
    // - https://github.com/owner/repo.git
    $owner = null;
    $repo = null;

    if (preg_match('#^git@github\.com:([^/]+)/([^/]+?)(?:\.git)?$#i', $repoUrl, $m)) {
        $owner = $m[1];
        $repo = $m[2];
    } else {
        $parts = parse_url($repoUrl);
        $path = $parts['path'] ?? '';
        $path = trim($path, '/');
        $path = preg_replace('/\.git$/i', '', $path);
        $segs = array_values(array_filter(explode('/', $path)));
        if (count($segs) >= 2) {
            $owner = $segs[0];
            $repo = $segs[1];
        }
    }

    if (!$owner || !$repo) return null;
    $owner = trim($owner);
    $repo = trim($repo);
    if ($owner === '' || $repo === '') return null;

    return [$owner, $repo];
}

function github_fetch_repo_preview($repoFullName, $accessToken = null) {
    $repoFullName = trim((string)$repoFullName);
    if ($repoFullName === '') return null;

    $ch = curl_init('https://api.github.com/repos/' . rawurlencode($repoFullName));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github+json',
        'User-Agent: Sprint-App'
    ]);

    if ($accessToken) {
        $chHeaders = [
            'Accept: application/vnd.github+json',
            'User-Agent: Sprint-App',
            'Authorization: Bearer ' . $accessToken
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $chHeaders);
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $resp = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('GitHub API request failed: ' . $err);
    }
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        throw new Exception('GitHub API returned invalid JSON (HTTP ' . (int)$httpStatus . ').');
    }

    if ((int)$httpStatus >= 400) {
        $msg = $data['message'] ?? ('HTTP ' . (int)$httpStatus);
        throw new Exception('GitHub API error: ' . $msg);
    }

    return [
        'repo_full_name' => $data['full_name'] ?? $repoFullName,
        'owner_login' => $data['owner']['login'] ?? null,
        'repo_name' => $data['name'] ?? null,
        'description' => $data['description'] ?? null,
        'language' => $data['language'] ?? null,
        'stargazers_count' => $data['stargazers_count'] ?? null,
        'forks_count' => $data['forks_count'] ?? null,
        'watchers_count' => $data['watchers_count'] ?? null,
        'html_url' => $data['html_url'] ?? null,
        'avatar_url' => $data['owner']['avatar_url'] ?? null,
    ];
}

function github_fetch_and_cache_repo_preview($pdo, $submissionId, $repoUrl) {
    $parsed = github_parse_repo_url($repoUrl);
    if (!$parsed) {
        throw new Exception('Unsupported GitHub repo URL. Expected github.com/<owner>/<repo>');
    }
    [$owner, $repo] = $parsed;

    $repoFullName = $owner . '/' . $repo;
    $provider = 'github';

    $existing = null;
    try {
        $stmt = $pdo->prepare('SELECT * FROM github_repo_cache WHERE submission_id = ? LIMIT 1');
        $stmt->execute([(int)$submissionId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $existing = null;
    }

    $apiToken = getenv('GITHUB_API_TOKEN') ?: null;

    // Always refresh here; callers can decide caching rules by not calling.
    $meta = github_fetch_repo_preview($repoFullName, $apiToken);
    if (!$meta) throw new Exception('GitHub API returned empty metadata.');

    $meta['repo_url'] = 'https://github.com/' . $repoFullName;

    // Upsert (MySQL vs SQLite both support INSERT OR IGNORE but not native upsert everywhere)
    $stmt = $pdo->prepare('SELECT id FROM github_repo_cache WHERE submission_id = ? LIMIT 1');
    $stmt->execute([(int)$submissionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $upd = $pdo->prepare('UPDATE github_repo_cache SET repo_full_name=?, owner_login=?, repo_name=?, repo_url=?, description=?, language=?, stargazers_count=?, forks_count=?, watchers_count=?, html_url=?, avatar_url=?, fetched_at=CURRENT_TIMESTAMP WHERE submission_id=?');
        $upd->execute([
            $meta['repo_full_name'],
            $meta['owner_login'],
            $meta['repo_name'],
            $meta['repo_url'],
            $meta['description'],
            $meta['language'],
            $meta['stargazers_count'],
            $meta['forks_count'],
            $meta['watchers_count'],
            $meta['html_url'],
            $meta['avatar_url'],
            (int)$submissionId
        ]);
    } else {
        $ins = $pdo->prepare('INSERT INTO github_repo_cache (submission_id, provider, repo_full_name, owner_login, repo_name, repo_url, description, language, stargazers_count, forks_count, watchers_count, html_url, avatar_url, fetched_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $ins->execute([
            (int)$submissionId,
            $provider,
            $meta['repo_full_name'],
            $meta['owner_login'],
            $meta['repo_name'],
            $meta['repo_url'],
            $meta['description'],
            $meta['language'],
            $meta['stargazers_count'],
            $meta['forks_count'],
            $meta['watchers_count'],
            $meta['html_url'],
            $meta['avatar_url'],
        ]);
    }

    $out = $existing ?: $meta;
    return $out;
}

function github_get_cached_repo_preview($pdo, $submissionId) {
    $stmt = $pdo->prepare('SELECT * FROM github_repo_cache WHERE submission_id = ? LIMIT 1');
    $stmt->execute([(int)$submissionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

