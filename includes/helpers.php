<?php
// includes/helpers.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// ── POSTS ─────────────────────────────────────────────────────────────────────

function get_user_by_username(string $username): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $st->execute([$username]);
    return $st->fetch() ?: null;
}

function get_user_by_id(int $id): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

// Post más reciente de un usuario
function get_latest_post(int $user_id): ?array {
    $st = db()->prepare(
        'SELECT * FROM posts WHERE user_id = ? ORDER BY post_date DESC LIMIT 1'
    );
    $st->execute([$user_id]);
    return $st->fetch() ?: null;
}

// Post de un usuario en una fecha específica
function get_post_by_date(int $user_id, string $date): ?array {
    $st = db()->prepare(
        'SELECT * FROM posts WHERE user_id = ? AND post_date = ? LIMIT 1'
    );
    $st->execute([$user_id, $date]);
    return $st->fetch() ?: null;
}

function get_post_by_id(int $id): ?array {
    $st = db()->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

// Posts anteriores de un usuario (para la columna derecha), excluye el post actual
function get_previous_posts(int $user_id, string $exclude_date, int $limit = 10): array {
    $st = db()->prepare(
        'SELECT * FROM posts
         WHERE user_id = ? AND post_date < ?
         ORDER BY post_date DESC
         LIMIT ?'
    );
    $st->execute([$user_id, $exclude_date, $limit]);
    return $st->fetchAll();
}

// Post siguiente y anterior (navegación)
function get_adjacent_posts(int $user_id, string $current_date): array {
    $prev_st = db()->prepare(
        'SELECT * FROM posts WHERE user_id = ? AND post_date < ? ORDER BY post_date DESC LIMIT 1'
    );
    $prev_st->execute([$user_id, $current_date]);

    $next_st = db()->prepare(
        'SELECT * FROM posts WHERE user_id = ? AND post_date > ? ORDER BY post_date ASC LIMIT 1'
    );
    $next_st->execute([$user_id, $current_date]);

    return [
        'prev' => $prev_st->fetch() ?: null,
        'next' => $next_st->fetch() ?: null,
    ];
}

// Crear post del día
function create_post(int $user_id, string $title, string $body, array $image_file): int {
    $today = date('Y-m-d');

    // Verificar que no posteó hoy
    if (get_post_by_date($user_id, $today)) {
        throw new RuntimeException('Ya posteaste hoy. Solo se permite un post por día.');
    }

    // Validar título
    $title = trim($title);
    if ($title === '') throw new RuntimeException('El título no puede estar vacío.');
    if (mb_strlen($title) > MAX_TITLE_CHARS) {
        throw new RuntimeException('El título no puede superar ' . MAX_TITLE_CHARS . ' caracteres.');
    }

    // Validar cuerpo
    $body = trim($body);
    if (mb_strlen($body) > MAX_POST_BODY_CHARS) {
        throw new RuntimeException('El texto no puede superar ' . MAX_POST_BODY_CHARS . ' caracteres.');
    }

    // Bloquear emojis (U+1F000 en adelante y otros rangos emoji)
    if (preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE00}-\x{FEFF}]/u', $title . $body)) {
        throw new RuntimeException('No se permiten emojis en el texto.');
    }

    // Procesar imagen
    require_once __DIR__ . '/image.php';
    $paths = process_post_image($image_file, $user_id);

    $st = db()->prepare(
        'INSERT INTO posts (user_id, title, body, photo_path, thumb_path, post_date)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$user_id, $title, $body, $paths['photo'], $paths['thumb'], $today]);
    return (int) db()->lastInsertId();
}

// Contar total de posts de un usuario
function count_user_posts(int $user_id): int {
    $st = db()->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
    $st->execute([$user_id]);
    return (int) $st->fetchColumn();
}

// Posts por año/mes para el archivo calendario
function get_posts_for_month(int $user_id, int $year, int $month): array {
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to   = date('Y-m-t', strtotime($from));
    $st   = db()->prepare(
        'SELECT post_date FROM posts WHERE user_id = ? AND post_date BETWEEN ? AND ? ORDER BY post_date ASC'
    );
    $st->execute([$user_id, $from, $to]);
    return array_column($st->fetchAll(), 'post_date');
}

// Años activos de un usuario (para el archivo)
function get_active_years(int $user_id): array {
    $st = db()->prepare(
        'SELECT DISTINCT YEAR(post_date) AS y FROM posts WHERE user_id = ? ORDER BY y DESC'
    );
    $st->execute([$user_id]);
    return array_column($st->fetchAll(), 'y');
}


// ── COMMENTS ──────────────────────────────────────────────────────────────────

function get_comments(int $post_id): array {
    $st = db()->prepare(
        'SELECT c.*, u.username, u.avatar_path
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ?
         ORDER BY c.created_at ASC'
    );
    $st->execute([$post_id]);
    return $st->fetchAll();
}

function count_comments(int $post_id): int {
    $st = db()->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
    $st->execute([$post_id]);
    return (int) $st->fetchColumn();
}

function add_comment(int $post_id, int $user_id, string $body): int {
    $body = trim($body);
    if ($body === '') throw new RuntimeException('El comentario no puede estar vacío.');
    if (mb_strlen($body) > 500) throw new RuntimeException('El comentario no puede superar 500 caracteres.');
    if (preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE00}-\x{FEFF}]/u', $body)) {
        throw new RuntimeException('No se permiten emojis en los comentarios.');
    }

    $count = count_comments($post_id);
    if ($count >= MAX_COMMENTS_PER_POST) {
        throw new RuntimeException('Este post ya alcanzó el límite de ' . MAX_COMMENTS_PER_POST . ' comentarios.');
    }

    $st = db()->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)');
    $st->execute([$post_id, $user_id, $body]);
    return (int) db()->lastInsertId();
}

// Solo el dueño del perfil puede borrar comentarios en sus posts
function delete_comment(int $comment_id, int $requesting_user_id): void {
    $pdo = db();
    $st  = $pdo->prepare(
        'SELECT c.id, p.user_id AS post_owner_id
         FROM comments c JOIN posts p ON p.id = c.post_id
         WHERE c.id = ? LIMIT 1'
    );
    $st->execute([$comment_id]);
    $row = $st->fetch();
    if (!$row) throw new RuntimeException('Comentario no encontrado.');

    $user = get_user_by_id($requesting_user_id);
    if ($row['post_owner_id'] !== $requesting_user_id && !$user['is_admin']) {
        throw new RuntimeException('No tenés permiso para borrar este comentario.');
    }

    $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$comment_id]);
}


// ── FAVORITES (FF) ────────────────────────────────────────────────────────────

function get_favorites(int $user_id, int $limit = 10, int $offset = 0): array {
    $st = db()->prepare(
        'SELECT u.id, u.username, u.display_name, u.location,
                p.thumb_path, p.post_date
         FROM favorites f
         JOIN users u ON u.id = f.favorite_user_id
         LEFT JOIN posts p ON p.id = (
             SELECT id FROM posts WHERE user_id = u.id ORDER BY post_date DESC LIMIT 1
         )
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $st->execute([$user_id, $limit, $offset]);
    return $st->fetchAll();
}

function count_favorites(int $user_id): int {
    $st = db()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
    $st->execute([$user_id]);
    return (int) $st->fetchColumn();
}

function is_favorite(int $user_id, int $target_id): bool {
    $st = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND favorite_user_id = ? LIMIT 1');
    $st->execute([$user_id, $target_id]);
    return (bool) $st->fetchColumn();
}

function add_favorite(int $user_id, int $target_id): void {
    if ($user_id === $target_id) throw new RuntimeException('No podés agregarte a vos mismo como FF.');
    try {
        db()->prepare('INSERT INTO favorites (user_id, favorite_user_id) VALUES (?, ?)')->execute([$user_id, $target_id]);
    } catch (\PDOException $e) {
        // Si ya existe (duplicate key), ignorar silenciosamente
        if ($e->getCode() !== '23000') throw $e;
    }
}

function remove_favorite(int $user_id, int $target_id): void {
    db()->prepare('DELETE FROM favorites WHERE user_id = ? AND favorite_user_id = ?')->execute([$user_id, $target_id]);
}


// ── FEED ──────────────────────────────────────────────────────────────────────

// Para la homepage: último post de cada usuario activo, ordenado por post_date DESC
function get_feed_grid(int $limit = 30, int $offset = 0): array {
    $st = db()->prepare(
        'SELECT u.id AS user_id, u.username, u.display_name, u.location,
                p.id AS post_id, p.title, p.thumb_path, p.post_date
         FROM users u
         JOIN posts p ON p.id = (
             SELECT id FROM posts WHERE user_id = u.id ORDER BY post_date DESC LIMIT 1
         )
         WHERE u.is_active = 1
         ORDER BY p.post_date DESC
         LIMIT ? OFFSET ?'
    );
    $st->execute([$limit, $offset]);
    return $st->fetchAll();
}


// ── SAVED POSTS ───────────────────────────────────────────────────────────────

function is_saved(int $user_id, int $post_id): bool {
    $st = db()->prepare('SELECT 1 FROM saved_posts WHERE user_id = ? AND post_id = ? LIMIT 1');
    $st->execute([$user_id, $post_id]);
    return (bool) $st->fetchColumn();
}

function save_post(int $user_id, int $post_id): void {
    try {
        db()->prepare('INSERT INTO saved_posts (user_id, post_id) VALUES (?, ?)')->execute([$user_id, $post_id]);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '23000') throw $e; // ignorar duplicado
    }
}

function unsave_post(int $user_id, int $post_id): void {
    db()->prepare('DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?')->execute([$user_id, $post_id]);
}

function get_saved_posts(int $user_id, int $limit = 20, int $offset = 0): array {
    $st = db()->prepare(
        'SELECT p.*, u.username, sp.created_at AS saved_at
         FROM saved_posts sp
         JOIN posts p ON p.id = sp.post_id
         JOIN users u ON u.id = p.user_id
         WHERE sp.user_id = ?
         ORDER BY sp.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $st->execute([$user_id, $limit, $offset]);
    return $st->fetchAll();
}

function count_saved_posts(int $user_id): int {
    $st = db()->prepare('SELECT COUNT(*) FROM saved_posts WHERE user_id = ?');
    $st->execute([$user_id]);
    return (int) $st->fetchColumn();
}


// ── SOCIAL LINKS ─────────────────────────────────────────────────────────────

/**
 * Devuelve array de redes configuradas con label, url e ícono SVG inline.
 * Solo incluye las que el usuario completó.
 */
function get_social_links(array $user): array {
    $links = [];

    if (!empty($user['social_ig'])) {
        $handle = ltrim($user['social_ig'], '@');
        $links[] = [
            'label' => '@' . $handle,
            'url'   => 'https://instagram.com/' . rawurlencode($handle),
            'icon'  => 'ig',
            'title' => 'Instagram',
        ];
    }
    if (!empty($user['social_tt'])) {
        $handle = ltrim($user['social_tt'], '@');
        $links[] = [
            'label' => '@' . $handle,
            'url'   => 'https://tiktok.com/@' . rawurlencode($handle),
            'icon'  => 'tt',
            'title' => 'TikTok',
        ];
    }
    if (!empty($user['social_x'])) {
        $handle = ltrim($user['social_x'], '@');
        $links[] = [
            'label' => '@' . $handle,
            'url'   => 'https://x.com/' . rawurlencode($handle),
            'icon'  => 'x',
            'title' => 'X / Twitter',
        ];
    }
    if (!empty($user['social_yt'])) {
        $yt = $user['social_yt'];
        $url = str_starts_with($yt, 'http') ? $yt : 'https://youtube.com/@' . rawurlencode(ltrim($yt, '@'));
        $links[] = [
            'label' => 'YouTube',
            'url'   => $url,
            'icon'  => 'yt',
            'title' => 'YouTube',
        ];
    }
    if (!empty($user['social_maps'])) {
        $maps = $user['social_maps'];
        $url  = str_starts_with($maps, 'http') ? $maps : 'https://maps.google.com/?q=' . rawurlencode($maps);
        $links[] = [
            'label' => 'Maps',
            'url'   => $url,
            'icon'  => 'maps',
            'title' => 'Google Maps',
        ];
    }
    if (!empty($user['social_web'])) {
        $web = $user['social_web'];
        if (!str_starts_with($web, 'http')) $web = 'https://' . $web;
        $links[] = [
            'label' => parse_url($web, PHP_URL_HOST) ?: $web,
            'url'   => $web,
            'icon'  => 'web',
            'title' => 'Sitio web',
        ];
    }

    return $links;
}

/**
 * Renderiza los íconos de redes sociales del perfil.
 * Usa SVG inline para no depender de fuentes externas.
 */
function render_social_icons(array $user): string {
    $links = get_social_links($user);
    if (empty($links)) return '';

    // SVG paths por red
    $svgs = [
        'ig'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
        'tt'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.75a4.85 4.85 0 01-1.01-.06z"/></svg>',
        'x'    => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>',
        'yt'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
        'maps' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
        'web'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>',
    ];

    $out = '<div class="social-icons">';
    foreach ($links as $l) {
        $svg = $svgs[$l['icon']] ?? '';
        $out .= '<a href="' . h($l['url']) . '" target="_blank" rel="noopener" title="' . h($l['title']) . '" class="social-icon social-icon--' . $l['icon'] . '">'
              . $svg
              . '</a>';
    }
    $out .= '</div>';
    return $out;
}


// ── UTILITIES ────────────────────────────────────────────────────────────────

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function thumb_url(?string $thumb_path): string {
    if (!$thumb_path) return SITE_URL . '/assets/img/no-photo.png';
    return THUMBS_URL . basename($thumb_path);
}

function photo_url(?string $photo_path): string {
    if (!$photo_path) return SITE_URL . '/assets/img/no-photo.png';
    return UPLOADS_URL . basename($photo_path);
}

function avatar_url(?string $avatar_path): string {
    if (!$avatar_path) return SITE_URL . '/assets/img/default-avatar.png';
    return UPLOADS_URL . basename($avatar_path);
}

function format_date(string $date): string {
    // "13/5/2026"
    $ts = strtotime($date);
    return date('j/n/Y', $ts);
}

function user_profile_url(string $username): string {
    return SITE_URL . '/' . rawurlencode($username);
}
