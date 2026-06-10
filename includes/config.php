<?php
// ============================================================
// includes/config.php
// Copiar a producción y completar con datos reales.
// NO subir al repositorio público.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'pizzalog');
define('DB_USER', 'pizzalog_user');
define('DB_PASS', 'CAMBIAR_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL',   'https://pizzalog.net');
define('SITE_NAME',  'Pizzalog');
define('UPLOADS_DIR', __DIR__ . '/../public/uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');
define('THUMBS_DIR',  UPLOADS_DIR . 'thumbs/');
define('THUMBS_URL',  UPLOADS_URL . 'thumbs/');

// Zona horaria Argentina
date_default_timezone_set('America/Argentina/Cordoba');

// Límites de negocio
define('MAX_POST_BODY_CHARS',    2000);
define('MAX_TITLE_CHARS',         150);
define('MAX_COMMENTS_PER_POST',    20);
define('MAX_PHOTO_WIDTH',         800);
define('MAX_PHOTO_HEIGHT',        600);
define('THUMB_WIDTH',             110);
define('THUMB_HEIGHT',             82);
define('FF_THUMB_WIDTH',          110);
define('FF_THUMB_HEIGHT',          82);
define('DEFAULT_USER_INVITATIONS',  5);
