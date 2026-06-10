<?php
// public/login.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

session_start_safe();
if (current_user()) {
    header('Location: ' . SITE_URL . '/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $user = login_user($username, $password);
        if ($user) {
            $redirect = $_SESSION['login_redirect'] ?? SITE_URL . '/' . rawurlencode($user['username']);
            unset($_SESSION['login_redirect']);
            header('Location: ' . $redirect);
            exit;
        }
    }
    $error = 'Usuario o contraseña incorrectos.';
}

$page_title = 'Entrar';
ob_start();
?>
<div class="form-box">
  <h1>Entrar al Pizzalog</h1>
  <?php if ($error): ?>
    <div class="form-error"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <div class="form-row">
      <label for="username">Usuario</label>
      <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" required>
    </div>
    <div class="form-row">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <div class="form-submit">
      <button type="submit" class="btn-primary">Entrar</button>
    </div>
  </form>
  <p class="form-note">¿No tenés cuenta? Necesitás una <strong>invitación</strong>. <a href="<?= SITE_URL ?>/register.php">Registrarse</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
