<?php
// public/settings.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/image.php';
require_once __DIR__ . '/../includes/config.php';

$me    = require_login();
$uid   = (int)$me['id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $location     = trim($_POST['location']     ?? '');
    $bio          = trim($_POST['bio']          ?? '');
    $mood         = trim($_POST['mood']         ?? '');
    $social_ig    = trim(ltrim($_POST['social_ig']  ?? '', '@'));
    $social_tt    = trim(ltrim($_POST['social_tt']  ?? '', '@'));
    $social_x     = trim(ltrim($_POST['social_x']   ?? '', '@'));
    $social_yt    = trim($_POST['social_yt']    ?? '');
    $social_maps  = trim($_POST['social_maps']  ?? '');
    $social_web   = trim($_POST['social_web']   ?? '');
    $new_password = $_POST['new_password']      ?? '';
    $cur_password = $_POST['current_password']  ?? '';

    try {
        if (mb_strlen($display_name) > 60)  throw new RuntimeException('El nombre no puede superar 60 caracteres.');
        if (mb_strlen($location)     > 100) throw new RuntimeException('La ubicación no puede superar 100 caracteres.');
        if (mb_strlen($bio)          > 300) throw new RuntimeException('La bio no puede superar 300 caracteres.');
        if (mb_strlen($mood)         > 100) throw new RuntimeException('El mood no puede superar 100 caracteres.');
        if (mb_strlen($social_ig)    > 100) throw new RuntimeException('Usuario de Instagram demasiado largo.');
        if (mb_strlen($social_tt)    > 100) throw new RuntimeException('Usuario de TikTok demasiado largo.');
        if (mb_strlen($social_x)     > 100) throw new RuntimeException('Usuario de X demasiado largo.');
        if (mb_strlen($social_yt)    > 255) throw new RuntimeException('URL de YouTube demasiado larga.');
        if (mb_strlen($social_maps)  > 500) throw new RuntimeException('URL de Maps demasiado larga.');
        if (mb_strlen($social_web)   > 255) throw new RuntimeException('URL del sitio web demasiado larga.');

        // Validar URLs si se ingresaron
        if ($social_yt && !str_starts_with($social_yt, '@') && !filter_var($social_yt, FILTER_VALIDATE_URL) && !preg_match('/^@?[\w.-]+$/', $social_yt)) {
            throw new RuntimeException('URL o usuario de YouTube inválido.');
        }
        if ($social_maps && str_starts_with($social_maps, 'http') && !filter_var($social_maps, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('URL de Google Maps inválida.');
        }
        if ($social_web && !filter_var($social_web, FILTER_VALIDATE_URL) && !filter_var('https://' . $social_web, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('URL del sitio web inválida.');
        }

        $avatar_path = $me['avatar_path'];
        if (!empty($_FILES['avatar']['name'])) {
            $avatar_path = process_avatar($_FILES['avatar'], $uid);
        }

        $hash = $me['password_hash'];
        if ($new_password !== '') {
            if (!password_verify($cur_password, $hash))
                throw new RuntimeException('La contraseña actual es incorrecta.');
            if (strlen($new_password) < 6)
                throw new RuntimeException('La nueva contraseña debe tener al menos 6 caracteres.');
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
        }

        $st = db()->prepare(
            'UPDATE users SET
               display_name=?, location=?, bio=?, mood=?,
               social_ig=?, social_tt=?, social_x=?, social_yt=?, social_maps=?, social_web=?,
               avatar_path=?, password_hash=?
             WHERE id=?'
        );
        $st->execute([
            $display_name ?: null, $location ?: null, $bio ?: null, $mood ?: null,
            $social_ig ?: null, $social_tt ?: null, $social_x ?: null,
            $social_yt ?: null, $social_maps ?: null, $social_web ?: null,
            $avatar_path, $hash, $uid
        ]);

        $success = 'Perfil actualizado correctamente.';
        $me = get_user_by_id($uid);

    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$page_title = 'Ajustes';
ob_start();
?>
<div class="form-box profile-box">
  <h1>Ajustes de cuenta</h1>

  <?php if ($error):   echo '<div class="form-error">'    . h($error)   . '</div>'; endif; ?>
  <?php if ($success): echo '<div class="flash-success">' . h($success) . '</div>'; endif; ?>

  <form method="post" enctype="multipart/form-data">

    <!-- Avatar -->
    <div class="form-row">
      <label>Avatar actual</label>
      <div class="avatar-preview">
        <img src="<?= h(avatar_url($me['avatar_path'])) ?>" alt="avatar">
      </div>
      <input type="file" name="avatar" accept="image/*">
    </div>

    <!-- Nombre y ubicación -->
    <div class="form-row">
      <label for="display_name">Nombre para mostrar</label>
      <input type="text" id="display_name" name="display_name"
             value="<?= h($me['display_name'] ?? '') ?>" maxlength="60">
    </div>
    <div class="form-row">
      <label for="location">Ubicación (ej: Córdoba, Argentina)</label>
      <input type="text" id="location" name="location"
             value="<?= h($me['location'] ?? '') ?>" maxlength="100">
    </div>

    <!-- Mood -->
    <div class="form-row">
      <label for="mood">Estado / Mood <span style="color:var(--accent2)">(aparece en tu perfil en color cyan)</span></label>
      <input type="text" id="mood" name="mood"
             value="<?= h($me['mood'] ?? '') ?>" maxlength="100"
             placeholder="ej: escuchando Radiohead, comiendo pizza">
    </div>

    <!-- Bio -->
    <div class="form-row">
      <label for="bio">Bio (máx. 300 chars)</label>
      <textarea id="bio" name="bio" maxlength="300" rows="3"><?= h($me['bio'] ?? '') ?></textarea>
    </div>

    <hr style="border-color:var(--border);margin:14px 0;">
    <div style="font-size:11px;color:var(--accent2);margin-bottom:10px;font-weight:bold;">Redes sociales</div>

    <!-- Instagram -->
    <div class="form-row">
      <label for="social_ig">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" style="vertical-align:middle;margin-right:3px;"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z"/></svg>
        Instagram <span style="color:var(--text-dim)">(solo el usuario, sin @)</span>
      </label>
      <input type="text" id="social_ig" name="social_ig"
             value="<?= h($me['social_ig'] ?? '') ?>" maxlength="100" placeholder="tuusuario">
    </div>

    <!-- TikTok -->
    <div class="form-row">
      <label for="social_tt">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" style="vertical-align:middle;margin-right:3px;"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.75a4.85 4.85 0 01-1.01-.06z"/></svg>
        TikTok <span style="color:var(--text-dim)">(solo el usuario, sin @)</span>
      </label>
      <input type="text" id="social_tt" name="social_tt"
             value="<?= h($me['social_tt'] ?? '') ?>" maxlength="100" placeholder="tuusuario">
    </div>

    <!-- X / Twitter -->
    <div class="form-row">
      <label for="social_x">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" style="vertical-align:middle;margin-right:3px;"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25z"/></svg>
        X / Twitter <span style="color:var(--text-dim)">(solo el usuario, sin @)</span>
      </label>
      <input type="text" id="social_x" name="social_x"
             value="<?= h($me['social_x'] ?? '') ?>" maxlength="100" placeholder="tuusuario">
    </div>

    <!-- YouTube -->
    <div class="form-row">
      <label for="social_yt">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" style="vertical-align:middle;margin-right:3px;"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
        YouTube <span style="color:var(--text-dim)">(usuario @handle o URL completa)</span>
      </label>
      <input type="text" id="social_yt" name="social_yt"
             value="<?= h($me['social_yt'] ?? '') ?>" maxlength="255" placeholder="@tucanal o https://youtube.com/@tucanal">
    </div>

    <!-- Google Maps -->
    <div class="form-row">
      <label for="social_maps">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" style="vertical-align:middle;margin-right:3px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        Google Maps <span style="color:var(--text-dim)">(URL de tu local o ubicación)</span>
      </label>
      <input type="text" id="social_maps" name="social_maps"
             value="<?= h($me['social_maps'] ?? '') ?>" maxlength="500" placeholder="https://maps.google.com/...">
    </div>

    <!-- Sitio web -->
    <div class="form-row">
      <label for="social_web">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" style="vertical-align:middle;margin-right:3px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
        Sitio web propio
      </label>
      <input type="text" id="social_web" name="social_web"
             value="<?= h($me['social_web'] ?? '') ?>" maxlength="255" placeholder="arrabbiata.com.ar">
    </div>

    <hr style="border-color:var(--border);margin:14px 0;">

    <!-- Cambio de contraseña -->
    <div class="form-row">
      <label for="current_password">Contraseña actual <span style="color:var(--text-dim)">(solo si la cambiás)</span></label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password">
    </div>
    <div class="form-row">
      <label for="new_password">Nueva contraseña <span style="color:var(--text-dim)">(dejar vacío para no cambiar)</span></label>
      <input type="password" id="new_password" name="new_password" autocomplete="new-password">
    </div>

    <div class="form-submit">
      <button type="submit" class="btn-primary">Guardar cambios</button>
    </div>
  </form>

  <div style="text-align:center;margin-top:12px;font-size:11px;">
    <a href="<?= user_profile_url($me['username']) ?>">&larr; Volver a mi pizzalog</a>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <a href="<?= SITE_URL ?>/saved.php">Ver mis guardados</a>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
