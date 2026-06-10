<?php
// public/api/save.php — Toggle guardar/desguardar un post
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

$me = current_user();
if (!$me) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);
$action  = $_POST['action'] ?? '';

if (!$post_id || !in_array($action, ['save', 'unsave'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$post = get_post_by_id($post_id);
if (!$post) {
    echo json_encode(['ok' => false, 'error' => 'Post no encontrado']);
    exit;
}

// No se puede guardar el propio post
if ((int)$post['user_id'] === (int)$me['id']) {
    echo json_encode(['ok' => false, 'error' => 'No podés guardar tu propio post']);
    exit;
}

if ($action === 'save') {
    save_post((int)$me['id'], $post_id);
    echo json_encode(['ok' => true, 'saved' => true]);
} else {
    unsave_post((int)$me['id'], $post_id);
    echo json_encode(['ok' => true, 'saved' => false]);
}
