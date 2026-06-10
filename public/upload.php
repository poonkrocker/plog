<?php
// public/upload.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';

$me = require_login();
$uid = (int) $me['id'];

$today     = date('Y-m-d');
$has_post  = (bool) get_post_by_date($uid, $today);
$error     = '';
$success   = '';

// Edición de post existente (solo título y cuerpo, no foto)
$edit_id  = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$edit_post = null;
if ($edit_id) {
    $edit_post = get_post_by_id($edit_id);
    if (!$edit_post || (int)$edit_post['user_id'] !== $uid) {
        $edit_post = null;
        $edit_id   = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body']  ?? '');

    if ($edit_id && $edit_post) {
        // -- EDITAR: solo título y cuerpo --
        try {
            if ($title === '') throw new RuntimeException('El título no puede estar vacío.');
            if (mb_strlen($title) > MAX_TITLE_CHARS)
                throw new RuntimeException('El título no puede superar ' . MAX_TITLE_CHARS . ' caracteres.');
            if (mb_strlen($body) > MAX_POST_BODY_CHARS)
                throw new RuntimeException('El texto no puede superar ' . MAX_POST_BODY_CHARS . ' caracteres.');
            if (preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE00}-\x{FEFF}]/u', $title . $body))
                throw new RuntimeException('No se permiten emojis.');

            $st = db()->prepare('UPDATE posts SET title = ?, body = ? WHERE id = ? AND user_id = ?');
            $st->execute([$title, $body, $edit_id, $uid]);

            header('Location: ' . user_profile_url($me['username']) . '?date=' . $edit_post['post_date']);
            exit;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    } else {
        // -- NUEVO POST --
        if ($has_post) {
            $error = 'Ya posteaste hoy. Podés editar tu post de hoy.';
        } else {
            try {
                $post_id = create_post($uid, $title, $body, $_FILES['photo'] ?? []);
                header('Location: ' . user_profile_url($me['username']));
                exit;
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$page_title = $edit_id ? 'Editar post' : 'Subir foto del día';
ob_start();
?>
<div class="form-box upload-box">
  <h1><?= $edit_id ? 'Editar post' : 'Subir foto del día' ?></h1>

  <?php if ($has_post && !$edit_id): ?>
    <div class="flash-info">
      Ya subiste tu foto de hoy.
      <?php $tp = get_latest_post($uid); ?>
      <?php if ($tp): ?>
        <a href="<?= SITE_URL ?>/upload.php?edit=<?= $tp['id'] ?>">Editar título o texto</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($error):   echo '<div class="form-error">'   . h($error)   . '</div>'; endif; ?>
  <?php if ($success): echo '<div class="flash-success">' . h($success) . '</div>'; endif; ?>

  <?php if (!$has_post || $edit_id): ?>
  <form method="post" enctype="multipart/form-data">

    <div class="form-row">
      <label for="title">Título</label>
      <input type="text" id="title" name="title" maxlength="<?= MAX_TITLE_CHARS ?>"
             value="<?= h($edit_post['title'] ?? $_POST['title'] ?? '') ?>" required>
      <div class="char-count"><span id="title-count">0</span>/<?= MAX_TITLE_CHARS ?></div>
    </div>

    <?php if (!$edit_id): ?>
    <div class="form-row">
      <label for="photo">Foto (JPG, PNG, GIF, WebP — máx. 10 MB)</label>
      <input type="file" id="photo" name="photo" accept="image/*" required>
      <div class="upload-preview" id="preview-box">
        <img id="preview-img" src="" alt="Preview">
      </div>
    </div>
    <?php endif; ?>

    <div class="form-row">
      <label for="body">Texto del post (opcional, máx. <?= MAX_POST_BODY_CHARS ?> caracteres, sin emojis)</label>
      <textarea id="body" name="body" maxlength="<?= MAX_POST_BODY_CHARS ?>" rows="5"><?= h($edit_post['body'] ?? $_POST['body'] ?? '') ?></textarea>
      <div class="char-count"><span id="body-count">0</span>/<?= MAX_POST_BODY_CHARS ?></div>
    </div>

    <div class="form-submit">
      <button type="submit" class="btn-primary"><?= $edit_id ? 'Guardar cambios' : 'Publicar' ?></button>
    </div>
  </form>
  <?php endif; ?>

  <p class="form-note">
    <a href="<?= user_profile_url($me['username']) ?>">&larr; Volver a mi pizzalog</a>
  </p>
</div>

<script>
// Contadores de caracteres
function updateCount(inputId, countId) {
    const el = document.getElementById(inputId);
    const cnt = document.getElementById(countId);
    if (!el || !cnt) return;
    const update = () => { cnt.textContent = el.value.length; };
    el.addEventListener('input', update);
    update();
}
updateCount('title', 'title-count');
updateCount('body',  'body-count');

// Preview de imagen
const fileInput = document.getElementById('photo');
if (fileInput) {
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const box = document.getElementById('preview-box');
            const img = document.getElementById('preview-img');
            img.src = e.target.result;
            box.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
