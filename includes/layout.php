<?php
// includes/layout.php
// Uso:
//   $page_title = 'Login';
//   ob_start();
//   ... HTML del contenido ...
//   $content = ob_get_clean();
//   include __DIR__ . '/layout.php';

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$_current_user = current_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title ?? SITE_NAME) ?> — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/pizzalog.css">
</head>
<body>

<header id="site-header">
  <div class="logo">Pizza<span>log</span></div>
  <nav>
    <?php if ($_current_user): ?>
      <a href="<?= user_profile_url($_current_user['username']) ?>">mi pizzalog</a>
      <a href="<?= SITE_URL ?>/upload.php">subir foto</a>
      <a href="<?= SITE_URL ?>/settings.php">ajustes</a>
      <a href="<?= SITE_URL ?>/saved.php">guardados</a>
      <a href="<?= SITE_URL ?>/invitations.php">invitaciones (<?= (int)$_current_user['invitations_remaining'] ?>)</a>
      <?php if ($_current_user['is_admin']): ?>
        <a href="<?= SITE_URL ?>/admin/">admin</a>
      <?php endif; ?>
      <a href="<?= SITE_URL ?>/logout.php">salir</a>
    <?php else: ?>
      <a href="<?= SITE_URL ?>/">inicio</a>
      <a href="<?= SITE_URL ?>/login.php">entrar</a>
      <a href="<?= SITE_URL ?>/register.php">registrarse</a>
    <?php endif; ?>
  </nav>
</header>

<div id="wrapper">
<?= $content ?? '' ?>
</div>

<footer id="site-footer">
  <?= SITE_NAME ?> &mdash; <a href="<?= SITE_URL ?>">pizzalog.net</a>
</footer>

</body>
</html>
