<?php
// public/invitations.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';

$me    = require_login();
$uid   = (int)$me['id'];
$error = '';
$success = '';
$new_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    try {
        $new_code = generate_invitation($uid);
        $success  = 'Código generado.';
        // Recargar para reflejar invitaciones restantes
        $me = get_user_by_id($uid);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

// Códigos generados por este usuario
$st = db()->prepare(
    'SELECT ic.code, ic.created_at, ic.used_at,
            u.username AS used_by_username
     FROM invitation_codes ic
     LEFT JOIN users u ON u.id = ic.used_by
     WHERE ic.created_by = ?
     ORDER BY ic.created_at DESC
     LIMIT 50'
);
$st->execute([$uid]);
$my_codes = $st->fetchAll();

$page_title = 'Invitaciones';
ob_start();
?>
<div class="form-box" style="width:500px;">
  <h1>Invitaciones</h1>

  <?php if (!$me['is_admin']): ?>
    <p style="font-size:12px;color:var(--text-dim);margin-bottom:12px;">
      Tenés <strong style="color:var(--accent)"><?= (int)$me['invitations_remaining'] ?></strong> invitación<?= $me['invitations_remaining'] != 1 ? 'es' : '' ?> disponible<?= $me['invitations_remaining'] != 1 ? 's' : '' ?>.
    </p>
  <?php else: ?>
    <p style="font-size:12px;color:var(--text-dim);margin-bottom:12px;">
      Sos admin — podés generar todas las invitaciones que quieras.
    </p>
  <?php endif; ?>

  <?php if ($error):   echo '<div class="form-error">'    . h($error)   . '</div>'; endif; ?>
  <?php if ($success): echo '<div class="flash-success">' . h($success) . '</div>'; endif; ?>

  <?php if ($new_code): ?>
    <div class="flash-info">
      Nuevo código: <strong style="font-family:monospace;font-size:14px;letter-spacing:2px;"><?= h($new_code) ?></strong><br>
      <small>Compartilo con la persona que querés invitar. El link de registro es:<br>
      <a href="<?= SITE_URL ?>/register.php?code=<?= urlencode($new_code) ?>"><?= SITE_URL ?>/register.php?code=<?= h($new_code) ?></a></small>
    </div>
  <?php endif; ?>

  <?php if ($me['is_admin'] || $me['invitations_remaining'] > 0): ?>
    <form method="post" style="margin-bottom:16px;">
      <button type="submit" name="generate" value="1" class="btn-primary">Generar nuevo código</button>
    </form>
  <?php endif; ?>

  <?php if (!empty($my_codes)): ?>
    <h2 style="font-size:13px;color:var(--accent2);margin-bottom:8px;">Mis códigos</h2>
    <table style="width:100%;border-collapse:collapse;font-size:11px;">
      <tr>
        <th style="text-align:left;padding:3px 6px;color:var(--text-dim);border-bottom:1px solid var(--border);">Código</th>
        <th style="text-align:left;padding:3px 6px;color:var(--text-dim);border-bottom:1px solid var(--border);">Estado</th>
        <th style="text-align:left;padding:3px 6px;color:var(--text-dim);border-bottom:1px solid var(--border);">Creado</th>
      </tr>
      <?php foreach ($my_codes as $c): ?>
        <tr>
          <td style="padding:3px 6px;font-family:monospace;color:var(--accent2);border-bottom:1px solid var(--border);"><?= h($c['code']) ?></td>
          <td style="padding:3px 6px;border-bottom:1px solid var(--border);">
            <?php if ($c['used_by_username']): ?>
              <span style="color:#88ffcc;">usado por <?= h($c['used_by_username']) ?></span>
            <?php else: ?>
              <span style="color:var(--text-dim);">disponible</span>
            <?php endif; ?>
          </td>
          <td style="padding:3px 6px;color:var(--text-dim);border-bottom:1px solid var(--border);">
            <?= date('j/n/Y', strtotime($c['created_at'])) ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <p class="form-note" style="margin-top:16px;">
    <a href="<?= user_profile_url($me['username']) ?>">&larr; Volver a mi pizzalog</a>
  </p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
