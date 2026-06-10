-- ============================================================
-- PIZZALOG — Schema SQL
-- MySQL 8.x / MariaDB 10.x
-- Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = 'America/Argentina/Cordoba';

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`              VARCHAR(30)     NOT NULL,
  `email`                 VARCHAR(255)    NOT NULL,
  `password_hash`         VARCHAR(255)    NOT NULL,
  `display_name`          VARCHAR(60)     DEFAULT NULL,
  `location`              VARCHAR(100)    DEFAULT NULL,
  `bio`                   VARCHAR(300)    DEFAULT NULL,
  `mood`                  VARCHAR(100)    DEFAULT NULL,
  `social_ig`             VARCHAR(100)    DEFAULT NULL,
  `social_tt`             VARCHAR(100)    DEFAULT NULL,
  `social_x`              VARCHAR(100)    DEFAULT NULL,
  `social_yt`             VARCHAR(255)    DEFAULT NULL,
  `social_maps`           VARCHAR(500)    DEFAULT NULL,
  `social_web`            VARCHAR(255)    DEFAULT NULL,
  `avatar_path`           VARCHAR(255)    DEFAULT NULL,
  `invited_by`            INT UNSIGNED    DEFAULT NULL,
  `invitations_remaining` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `is_admin`              TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active`             TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`),
  FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primer usuario admin (password debe cambiarse en primer login)
-- Password placeholder: se inserta desde install.php
-- INSERT INTO users ... (ver install.php)


-- ------------------------------------------------------------
-- INVITATION CODES
-- ------------------------------------------------------------
CREATE TABLE `invitation_codes` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `code`         CHAR(16)      NOT NULL,
  `created_by`   INT UNSIGNED  NOT NULL,
  `used_by`      INT UNSIGNED  DEFAULT NULL,
  `used_at`      DATETIME      DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`used_by`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- POSTS
-- ------------------------------------------------------------
CREATE TABLE `posts` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `title`        VARCHAR(150)  NOT NULL DEFAULT '',
  `body`         TEXT          DEFAULT NULL,
  `photo_path`   VARCHAR(255)  NOT NULL,
  `thumb_path`   VARCHAR(255)  NOT NULL,
  `post_date`    DATE          NOT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_date` (`user_id`, `post_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_post_date` (`post_date`),
  INDEX `idx_user_id`   (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- COMMENTS
-- ------------------------------------------------------------
CREATE TABLE `comments` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `post_id`    INT UNSIGNED  NOT NULL,
  `user_id`    INT UNSIGNED  NOT NULL,
  `body`       VARCHAR(500)  NOT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)    ON DELETE CASCADE,
  INDEX `idx_post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- FAVORITES (FF)
-- ------------------------------------------------------------
CREATE TABLE `favorites` (
  `user_id`          INT UNSIGNED NOT NULL,
  `favorite_user_id` INT UNSIGNED NOT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `favorite_user_id`),
  FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`favorite_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- SAVED POSTS
-- ------------------------------------------------------------
CREATE TABLE `saved_posts` (
  `user_id`    INT UNSIGNED NOT NULL,
  `post_id`    INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `post_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
