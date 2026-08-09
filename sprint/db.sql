CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  start_time DATETIME,
  end_time DATETIME,
  visibility ENUM('public','private') DEFAULT 'public',
  judging_mode ENUM('judges','peer') DEFAULT 'judges',
  created_by INT,

  -- Venue / location (optional but enables venue finding)
  venue_name VARCHAR(255) DEFAULT NULL,
  venue_address VARCHAR(255) DEFAULT NULL,
  venue_city VARCHAR(255) DEFAULT NULL,
  venue_state VARCHAR(255) DEFAULT NULL,
  venue_country VARCHAR(255) DEFAULT NULL,
  venue_lat DECIMAL(10,7) DEFAULT NULL,
  venue_lng DECIMAL(10,7) DEFAULT NULL,
  venue_capacity INT DEFAULT NULL
);


CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  weight FLOAT DEFAULT 1,
  FOREIGN KEY (event_id) REFERENCES events(id)
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  slack_username VARCHAR(255),
  slack_id VARCHAR(255) DEFAULT NULL,
  openid_sub VARCHAR(255) DEFAULT NULL,
  verification_status TINYINT(1) DEFAULT 0,
  profile TEXT DEFAULT NULL,
  slack_avatar_url VARCHAR(512) DEFAULT NULL,
  role ENUM('organizer','judge','participant','admin') DEFAULT 'participant',

  -- Organizer preference / home location (optional)
  home_city VARCHAR(255) DEFAULT NULL,
  home_state VARCHAR(255) DEFAULT NULL,
  home_country VARCHAR(255) DEFAULT NULL,
  home_lat DECIMAL(10,7) DEFAULT NULL,
  home_lng DECIMAL(10,7) DEFAULT NULL,
  preferred_venue_radius_km INT DEFAULT NULL,
  preferred_min_venue_capacity INT DEFAULT NULL,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  repo_url VARCHAR(255),
  demo_url VARCHAR(255),
  screenshot_path VARCHAR(255) DEFAULT NULL,
  video_path VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE prizes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL,
  judge_id INT NOT NULL,
  category VARCHAR(255),
  score FLOAT,
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (judge_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_id INT NOT NULL,
  added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE oauth_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL,
  provider_user_id VARCHAR(255) NOT NULL,
  access_token TEXT,
  refresh_token TEXT,
  expires_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY provider_user (provider, provider_user_id)
);

-- Track user attendance at events (useful when users don't have teams)
CREATE TABLE user_event_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_id INT NOT NULL,
  attended_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  UNIQUE KEY user_event (user_id, event_id)
);

-- Emergency/incident reporting for attendees to notify organizers
CREATE TABLE emergency_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT DEFAULT NULL,
  user_id INT DEFAULT NULL,
  title VARCHAR(255),
  description TEXT,
  location VARCHAR(255) DEFAULT NULL,
  severity ENUM('low','medium','high') DEFAULT 'low',
  status ENUM('open','acknowledged','resolved') DEFAULT 'open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  resolved_by INT DEFAULT NULL,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE github_repo_cache (
  id INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL UNIQUE,
  provider VARCHAR(20) NOT NULL DEFAULT 'github',
  repo_full_name VARCHAR(255) NOT NULL,
  owner_login VARCHAR(255) NOT NULL,
  repo_name VARCHAR(255) NOT NULL,
  repo_url VARCHAR(512) NOT NULL,
  description TEXT DEFAULT NULL,
  language VARCHAR(255) DEFAULT NULL,
  stargazers_count INT DEFAULT NULL,
  forks_count INT DEFAULT NULL,
  watchers_count INT DEFAULT NULL,
  html_url VARCHAR(512) DEFAULT NULL,
  avatar_url VARCHAR(512) DEFAULT NULL,
  fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);

