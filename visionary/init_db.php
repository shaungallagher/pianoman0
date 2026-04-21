<?php
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
$dbPath = $dataDir . '/visionary.db';


$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS ideas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    author_name TEXT,
    developer_name TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    created_at TEXT,
    updated_at TEXT
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password_hash TEXT,
    role TEXT,
    github_id TEXT,
    created_at TEXT
)");

$now = date('c');
$stmt = $pdo->prepare('INSERT INTO ideas (title, description, author_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(["Testing!", "A test post!", "Test", 'open', $now, $now]);

$pdo->exec("CREATE TABLE IF NOT EXISTS idea_likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    idea_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at TEXT,
    FOREIGN KEY(idea_id) REFERENCES ideas(id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    UNIQUE(idea_id, user_id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    author_id INTEGER,
    author_name TEXT,
    status TEXT DEFAULT 'draft',
    tags TEXT,
    featured_image TEXT,
    created_at TEXT,
    updated_at TEXT,
    published_at TEXT,
    FOREIGN KEY(author_id) REFERENCES users(id)
)");


$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$check->execute(['demo']);
if ($check->fetchColumn() == 0) {
    $pw = password_hash('demo', PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)')
        ->execute(['demo', $pw, 'both', $now]);
}

echo "Initialized DB at: $dbPath\n";
