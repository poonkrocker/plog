<?php
// public/admin/index.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';

$me = require_admin();

$message = '';

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_user') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        if ($target_id && $target_id !== (int)$me['id']) {
            $st = db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?');
            $st->execute([$target_id]);
            $message = 'Usuario actualizado.';
        }
    } elseif ($action === 'gen_invite') {
        try {
            $code    = generate_invitation((int)$me['id']);
            $message = 'Código generado: ' . $code;
        } catch (RuntimeException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_post') {
        $post_id = (int)($_POST['post_id'] ?? 0);
        if ($post_id) {
            $post = get_post_by_id($post_id);
            if ($post) {
                require_once __DIR__ . '/../../includes/image.php';
                delete_post_images($post['photo_path'], $post['thumb_path']);
                db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$post_id]);
                $message = 'Post eliminado.';
            }
        }
    } elseif ($action === 'delete_comment') {
        $cid = (int)($_POST['comment_id'] ?? 0);
        if ($cid) {
            db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$cid]);
            $message = 'Comentario eliminado.';
        }
    }
}

// Stats generales
$stats = db()->query('
    SELECT
      (SELECT COUNT(*) FROM users   WHERE is_active = 1) AS active_users,
      (SELECT COUNT(*) FROM users   WHERE is_active = 0) AS banned_users,
      (SELECT COUNT(*) FROM posts)                        AS total_posts,
      (SELECT COUNT(*) FROM comments)                     AS total_comments,
      (SELECT COUNT(*) FROM invitation_codes WHERE used_by IS NULL) AS open_invites
')->fetch();

// Usuarios recientes
$users_st = db()->query(
    'SELECT u.*, COUNT(p.id) AS post_count
     FROM users u LEFT JOIN posts p ON p.user_id = u.id
     GROUP BY u.id ORDER BY u.created_at DESC LIMIT 30'
);
$users = $users_st->fetchAll();

// Últimos posts
$posts_st = db()->query(
    'SELECT p.*, u.username FROM posts p JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC LIMIT 20'
);
$posts = $posts_st->fetchAll();

// Últimos comentarios
$comments_st = db()->query(
    'SELECT c.*, u.username, po.user_id AS post_owner_id, pu.username AS post_owner
     FROM comments c
     JOIN users u  ON u.id  = c.user_id
     JOIN posts po ON po.id = c.post_id
     JOIN users pu ON pu.id = po.user_id
     ORDER BY c.created_at DESC LIMIT 20'
);
$comments = $comments_st->fetchAll();

$page_title = 'Admin';
ob_start();
?>
<div class="admin-box">
  <h1>Panel de administración</h1>

  <?php if ($message): ?>
    <div class="flash-success"><?= h($message) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div style="display:flex;gap:20px;margin-bottom:14px;font-size:12px;">
    <div>Usuarios activos: <strong style="color:var(--accent)"><?= $stats['active_users'] ?></strong></div>
    <div>Baneados: <strong style="color:#ff4444"><?= $stats['banned_users'] ?></strong></div>
    <div>Posts totales: <strong style="color:var(--accent2)"><?= $stats['total_posts'] ?></strong></div>
    <div>Comentarios: <strong style="color:var(--accent2)"><?= $stats['total_comments'] ?></strong></div>
    <div>Invitaciones abiertas: <strong style="color:var(--text-dim)"><?= $stats['open_invites'] ?></strong></div>
  </div>

  <!-- Generar invitación admin -->
  <form method="post" style="margin-bottom:16px;">
    <input type="hidden" name="action" value="gen_invite">
    <button type="submit" class="btn-primary">Generar invitación (admin)</button>
  </form>

  <!-- Usuarios -->
  <h2>Usuarios (últimos 30)</h2>
  <table class="admin-table">
    <tr>
      <th>Usuario</th><th>Email</th><th>Posts</th><th>Invitaciones</th><th>Registrado</th><th>Estado</th><th>Acción</th>
    </tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><a href="<?= user_profile_url($u['username']) ?>"><?= h($u['username']) ?></a>
          <?php if ($u['is_admin']): ?><span style="color:var(--accent);font-size:10px;"> [admin]</span><?php endif; ?></td>
      <td><?= h($u['email']) ?></td>
      <td><?= (int)$u['post_count'] ?></td>
      <td><?= $u['is_admin'] ? '∞' : (int)$u['invitations_remaining'] ?></td>
      <td><?= date('j/n/Y', strtotime($u['created_at'])) ?></td>
      <td><?= $u['is_active'] ? '<span style="color:#88ffcc">activo</span>' : '<span style="color:#ff4444">baneado</span>' ?></td>
      <td>
        <?php if ($u['id'] != $me['id']): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="toggle_user">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <button type="submit" onclick="return confirm('¿Confirmar?')" style="font-size:10px;padding:1px 6px;background:var(--bg-light);color:var(--text-dim);border:1px solid var(--border);cursor:pointer;">
            <?= $u['is_active'] ? 'banear' : 'activar' ?>
          </button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <!-- Posts recientes -->
  <h2>Posts recientes (últimos 20)</h2>
  <table class="admin-table">
    <tr><th>Usuario</th><th>Título</th><th>Fecha</th><th>Acción</th></tr>
    <?php foreach ($posts as $p): ?>
    <tr>
      <td><a href="<?= user_profile_url($p['username']) ?>"><?= h($p['username']) ?></a></td>
      <td><?= h(mb_substr($p['title'], 0, 40)) ?></td>
      <td><?= h(format_date($p['post_date'])) ?></td>
      <td>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="delete_post">
          <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
          <button type="submit" onclick="return confirm('¿Eliminar post?')" style="font-size:10px;padding:1px 6px;background:#3a0a0a;color:#ff8888;border:1px solid #aa2222;cursor:pointer;">borrar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <!-- Comentarios recientes -->
  <h2>Comentarios recientes (últimos 20)</h2>
  <table class="admin-table">
    <tr><th>Autor</th><th>En el log de</th><th>Texto</th><th>Fecha</th><th>Acción</th></tr>
    <?php foreach ($comments as $c): ?>
    <tr>
      <td><a href="<?= user_profile_url($c['username']) ?>"><?= h($c['username']) ?></a></td>
      <td><a href="<?= user_profile_url($c['post_owner']) ?>"><?= h($c['post_owner']) ?></a></td>
      <td><?= h(mb_substr($c['body'], 0, 60)) ?></td>
      <td><?= date('j/n H:i', strtotime($c['created_at'])) ?></td>
      <td>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="delete_comment">
          <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
          <button type="submit" onclick="return confirm('¿Borrar?')" style="font-size:10px;padding:1px 6px;background:#3a0a0a;color:#ff8888;border:1px solid #aa2222;cursor:pointer;">borrar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../includes/layout.php';
