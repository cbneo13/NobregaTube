CREATE TABLE IF NOT EXISTS users (
  uid VARCHAR(20) NOT NULL,
  username VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100) NOT NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  email_verification_token CHAR(64) DEFAULT NULL,
  email_verification_expires DATETIME DEFAULT NULL,
  joined TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  staff TINYINT(1) NOT NULL DEFAULT 0,
  termination TINYINT(1) NOT NULL DEFAULT 0,
  suspended_until DATETIME DEFAULT NULL,
  last_ip VARCHAR(45) DEFAULT NULL,
  name VARCHAR(500) NOT NULL DEFAULT '',
  birthday DATE DEFAULT NULL,
  gender TINYINT UNSIGNED NOT NULL DEFAULT 0,
  relationship TINYINT UNSIGNED NOT NULL DEFAULT 0,
  about VARCHAR(500) NOT NULL DEFAULT '',
  website VARCHAR(500) NOT NULL DEFAULT '',
  hometown VARCHAR(500) NOT NULL DEFAULT '',
  city VARCHAR(500) NOT NULL DEFAULT '',
  country CHAR(2) DEFAULT NULL,
  occupations VARCHAR(500) NOT NULL DEFAULT '',
  companies VARCHAR(500) NOT NULL DEFAULT '',
  schools VARCHAR(500) NOT NULL DEFAULT '',
  hobbies VARCHAR(500) NOT NULL DEFAULT '',
  fav_media VARCHAR(500) NOT NULL DEFAULT '',
  music VARCHAR(500) NOT NULL DEFAULT '',
  books VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (uid),
  UNIQUE KEY users_username_unique (username),
  UNIQUE KEY users_email_unique (email),
  UNIQUE KEY users_email_verification_token_unique (email_verification_token),
  KEY users_by_ip_joined (last_ip, joined),
  KEY users_by_joined (joined)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signup_limits (
  ip_address VARCHAR(45) NOT NULL,
  blocked_until DATETIME DEFAULT NULL,
  PRIMARY KEY (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signup_global_limit (
  id TINYINT UNSIGNED NOT NULL,
  blocked_until DATETIME DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO signup_global_limit (id) VALUES (1);

CREATE TABLE IF NOT EXISTS ip_bans (
  ban_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_range VARCHAR(43) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(20) DEFAULT NULL,
  PRIMARY KEY (ban_id),
  UNIQUE KEY ip_bans_range_unique (ip_range),
  KEY ip_bans_expiration (expires_at),
  CONSTRAINT ip_bans_creator_fk FOREIGN KEY (created_by) REFERENCES users (uid) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  pmid VARCHAR(20) NOT NULL,
  sender VARCHAR(20) NOT NULL,
  receiver VARCHAR(20) NOT NULL,
  subject TEXT NOT NULL,
  attached VARCHAR(20) DEFAULT NULL,
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  body TEXT NOT NULL,
  isRead TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (pmid),
  KEY messages_unread_by_receiver (receiver, isRead),
  KEY messages_by_sender (sender, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_notice (
  notice_id TINYINT UNSIGNED NOT NULL,
  message VARCHAR(1000) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (notice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_tickets (
  ticket BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_uid VARCHAR(20) DEFAULT NULL,
  sender VARCHAR(100) NOT NULL,
  subject TINYINT UNSIGNED NOT NULL,
  message MEDIUMTEXT NOT NULL,
  submitted DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved TINYINT(1) NOT NULL DEFAULT 0,
  solution MEDIUMTEXT DEFAULT NULL,
  resolved_at DATETIME DEFAULT NULL,
  resolved_by VARCHAR(20) DEFAULT NULL,
  PRIMARY KEY (ticket),
  KEY contact_tickets_open (resolved, submitted),
  KEY contact_tickets_sender (sender, submitted),
  KEY contact_tickets_resolved_by (resolved_by),
  CONSTRAINT contact_tickets_account_fk FOREIGN KEY (account_uid) REFERENCES users (uid) ON DELETE SET NULL,
  CONSTRAINT contact_tickets_resolver_fk FOREIGN KEY (resolved_by) REFERENCES users (uid) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
  vid VARCHAR(20) NOT NULL,
  uid VARCHAR(20) NOT NULL,
  tags VARCHAR(900) NOT NULL,
  title VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  file VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  flv_path VARCHAR(255) DEFAULT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  privacy TINYINT(1) NOT NULL DEFAULT 1,
  allow_comments TINYINT(1) NOT NULL DEFAULT 1,
  uploaded DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  converted TINYINT(1) NOT NULL DEFAULT 0,
  duration INT UNSIGNED NOT NULL DEFAULT 0,
  queue_error VARCHAR(255) DEFAULT NULL,
  recorddate DATE DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  addrcountry VARCHAR(100) DEFAULT NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (vid),
  KEY videos_by_user (uid),
  KEY videos_by_user_uploaded (uid, uploaded),
  KEY videos_public_recent (privacy, uploaded),
  CONSTRAINT videos_user_fk FOREIGN KEY (uid) REFERENCES users (uid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS picks (
  video VARCHAR(20) NOT NULL,
  featured DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (video),
  KEY picks_by_featured (featured),
  CONSTRAINT picks_video_fk FOREIGN KEY (video) REFERENCES videos (vid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  cid VARCHAR(20) NOT NULL,
  vid VARCHAR(20) NOT NULL,
  uid VARCHAR(20) NOT NULL,
  body TEXT NOT NULL,
  post_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  removed TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (cid),
  KEY comments_by_video (vid, post_date),
  KEY comments_by_user (uid, post_date),
  CONSTRAINT comments_video_fk FOREIGN KEY (vid) REFERENCES videos (vid) ON DELETE CASCADE,
  CONSTRAINT comments_user_fk FOREIGN KEY (uid) REFERENCES users (uid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
  uid VARCHAR(20) NOT NULL,
  vid VARCHAR(20) NOT NULL,
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (uid, vid),
  KEY favorites_by_video (vid),
  CONSTRAINT favorites_video_fk FOREIGN KEY (vid) REFERENCES videos (vid) ON DELETE CASCADE,
  CONSTRAINT favorites_user_fk FOREIGN KEY (uid) REFERENCES users (uid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
