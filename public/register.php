<?php
// public/register.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

session_start_safe();
if (current_user()) {
    header('Location: ' . SITE_URL . '/');
    exit;
}

$error   = '';
$success = '';
$code    = trim($_GET['code'] ?? $_POST['code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $new_id = register_user([
            'username' => $_POST['username'] ?? '',
            'email'    => $_POST['email']    ?? '',
            'password' => $_POST['password'] ?? '',
        ], $code);

        // Auto-login
        session_regenerate_id(true);
        $_SESSION['user_id'] = $new_id;

        $username = trim($_POST['username']);
        header('Location: ' . SITE_URL . '/' . rawurlencode($username));
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$page_title = 'Registrarse';
ob_start();
?>
<div class="form-box">
  <h1>Crear cuenta</h1>
  <?php if ($error): ?>
    <div class="form-error"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <div class="form-row">
      <label for="code">Código de invitación</label>
      <input type="text" id="code" name="code" value="<?= h($code) ?>"
             placeholder="16 caracteres" maxlength="16" required autocomplete="off">
    </div>
    <div class="form-row">
      <label for="username">Nombre de usuario</label>
      <input type="text" id="username" name="username"
             value="<?= h($_POST['username'] ?? '') ?>"
             placeholder="letras, números, _ (3-30 chars)" maxlength="30" required autocomplete="username">
    </div>
    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email"
             value="<?= h($_POST['email'] ?? '') ?>" required autocomplete="email">
    </div>
    <div class="form-row">
      <label for="password">Contraseña (mín. 6 caracteres)</label>
      <input type="password" id="password" name="password" required autocomplete="new-password">
    </div>
    <div class="form-submit">
      <button type="submit" class="btn-primary">Crear cuenta</button>
    </div>
  </form>
  <p class="form-note">¿Ya tenés cuenta? <a href="<?= SITE_URL ?>/login.php">Entrar</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
