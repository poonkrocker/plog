-- ============================================================
-- PIZZALOG — Migration v2
-- Ejecutar en la BD después del schema inicial (schema.sql)
-- ============================================================

-- Nuevos campos en users: redes sociales + mood
ALTER TABLE `users`
  ADD COLUMN `mood`           VARCHAR(100) DEFAULT NULL AFTER `bio`,
  ADD COLUMN `social_ig`      VARCHAR(100) DEFAULT NULL AFTER `mood`,
  ADD COLUMN `social_tt`      VARCHAR(100) DEFAULT NULL AFTER `social_ig`,
  ADD COLUMN `social_x`       VARCHAR(100) DEFAULT NULL AFTER `social_tt`,
  ADD COLUMN `social_yt`      VARCHAR(255) DEFAULT NULL AFTER `social_x`,
  ADD COLUMN `social_maps`    VARCHAR(500) DEFAULT NULL AFTER `social_yt`,
  ADD COLUMN `social_web`     VARCHAR(255) DEFAULT NULL AFTER `social_maps`;

-- Tabla de posts guardados
CREATE TABLE `saved_posts` (
  `user_id`    INT UNSIGNED NOT NULL,
  `post_id`    INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `post_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
