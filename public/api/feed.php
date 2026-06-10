<?php
// public/api/feed.php
// Devuelve los últimos N posts de un usuario en JSON.
// Usado por el widget embed de arrabbiata.com.ar

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // permite embeds desde otros dominios

$username = trim($_GET['u'] ?? '');
$limit    = max(1, min(12, (int)($_GET['n'] ?? 6)));

if (!$username) {
    echo json_encode(['error' => 'Parámetro u requerido']);
    exit;
}

$user = get_user_by_username($username);
if (!$user) {
    echo json_encode(['error' => 'Usuario no encontrado']);
    exit;
}

$uid = (int)$user['id'];
$st  = db()->prepare(
    'SELECT id, title, body, thumb_path, photo_path, post_date
     FROM posts WHERE user_id = ?
     ORDER BY post_date DESC LIMIT ?'
);
$st->execute([$uid, $limit]);
$posts = $st->fetchAll();

$items = [];
foreach ($posts as $p) {
    $items[] = [
        'title'      => $p['title'],
        'body'       => mb_substr($p['body'] ?? '', 0, 200),
        'date'       => $p['post_date'],
        'date_fmt'   => format_date($p['post_date']),
        'thumb_url'  => thumb_url($p['thumb_path']),
        'photo_url'  => photo_url($p['photo_path']),
        'post_url'   => user_profile_url($username) . '?date=' . $p['post_date'],
    ];
}

echo json_encode([
    'username'   => $user['username'],
    'display_name' => $user['display_name'] ?: $user['username'],
    'profile_url'  => user_profile_url($username),
    'posts'        => $items,
]);
