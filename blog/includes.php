<?php
// blog/includes.php - minimal helpers for the blog
function load_env_file() {
    static $env = null;
    if ($env !== null) return $env;
    $env = [];
    $path = __DIR__ . '/../.env';
    if (!file_exists($path)) return $env;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
    return $env;
}

function env_get($key, $default = null) {
    $env = load_env_file();
    return array_key_exists($key, $env) ? $env[$key] : $default;
}

function posts_dir() { return __DIR__ . '/posts'; }
function uploads_dir() { return __DIR__ . '/uploads'; }

function ensure_dirs() {
    if (!is_dir(posts_dir())) mkdir(posts_dir(), 0755, true);
    if (!is_dir(uploads_dir())) mkdir(uploads_dir(), 0755, true);
}

function slugify($text) {
    // basic ascii-friendly slug
    $text = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^A-Za-z0-9-]+/', '-', $text);
    $text = strtolower(trim($text, '-'));
    if ($text === '') $text = 'post-' . time();
    // avoid collision by appending timestamp if file exists
    $file = posts_dir() . '/' . $text . '.json';
    if (file_exists($file)) {
        $text .= '-' . time();
    }
    return $text;
}

function get_all_posts($onlyPublished = false) {
    ensure_dirs();
    $posts = [];
    foreach (glob(posts_dir().'/*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;
        if ($onlyPublished && (!isset($data['status']) || $data['status'] !== 'published')) continue;
        $posts[] = $data;
    }
    usort($posts, function($a,$b){
        $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
        $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
        return $tb - $ta;
    });
    return $posts;
}

function get_post($slug) {
    ensure_dirs();
    $file = posts_dir() . '/' . $slug . '.json';
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function save_post($post, $existing_slug = null) {
    ensure_dirs();
    if (empty($post['slug'])) {
       $post['slug'] = slugify($post['title'] ?? 'post');
    }
    if ($existing_slug && $existing_slug !== $post['slug']) {
        $old = posts_dir() . '/' . $existing_slug . '.json';
        if (file_exists($old)) @unlink($old);
    }
    $now = date('c');
    if (empty($post['created_at'])) $post['created_at'] = $now;
    $post['updated_at'] = $now;
    $path = posts_dir() . '/' . $post['slug'] . '.json';
    file_put_contents($path, json_encode($post, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    return $post['slug'];
}

function excerpt($html, $len = 200) {
    $text = trim(strip_tags($html));
    if (strlen($text) <= $len) return $text;
    return substr($text, 0, $len) . '...';
}

function unique_filename($name) {
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    try {
        $base = bin2hex(random_bytes(6)) . '_' . time();
    } catch (Exception $e) {
        $base = dechex(mt_rand()) . '_' . time();
    }
    return $base . ($ext ? '.' . $ext : '');
}

?>
