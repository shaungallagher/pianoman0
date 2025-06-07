<?php

try {
    $db = new PDO('sqlite:posts.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //$db->exec("DROP TABLE users");

    // Create user table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        profile TEXT
    )");

    $users = array(
        array("PianoMan0", getenv('USER_PASSWORD_1')),
        array("Ryan (Jules)", getenv('USER_PASSWORD_2')),
    );

    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password) VALUES (:username, :password)");

    foreach ($users as $user) {
        $username = $user[0];
        $password = $user[1];

        if (!empty($password) && strlen($password) < 60) {
            $password = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);        
        $stmt->execute();
    }

    // Create posts table
    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    //$db->exec("DROP TABLE likes");    

    // Create likes table
    $db->exec("CREATE TABLE IF NOT EXISTS likes (
        user_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (post_id) REFERENCES posts(id),
        PRIMARY KEY (user_id, post_id)
    )");

    // Create comments table
    $db->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (post_id) REFERENCES posts(id)
    )");

    // Create uploads table
    $db->exec("CREATE TABLE IF NOT EXISTS uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        file_name TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id)
    )");

    // Create messages table
    $db->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        to_user_id INTEGER NOT NULL,
        from_user_id INTEGER NOT NULL,
        message TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (to_user_id) REFERENCES users(id),
        FOREIGN KEY (from_user_id) REFERENCES users(id)
    )");

    echo "Database setup complete.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

?>
