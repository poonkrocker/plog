<?php
// public/user.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';

$me       = current_user();
$username = trim($_GET['u'] ?? '');

if (!$username) { http_response_code(404); die('Usuario no encontrado.'); }

$profile = get_user_by_username($username);
if (!$profile) { http_response_code(404); die('Usuario no encontrado.'); }

$uid    = (int) $profile['id'];
$is_own = $me && (int)$me['id'] === $uid;

// Post a mostrar
$req_date = $_GET['date'] ?? null;
if ($req_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $req_date)) {
    $post = get_post_by_date($uid, $req_date);
} else {
    $post = get_latest_post($uid);
}

// ── Acciones POST ──────────────────────────────────────────────
$ff_error = $ff_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me) {

    // FF toggle
    if (isset($_POST['ff_action'])) {
        if ($_POST['ff_action'] === 'add') {
            try { add_favorite((int)$me['id'], $uid); $ff_success = 'Agregado a tus FF.'; }
            catch (RuntimeException $e) { $ff_error = $e->getMessage(); }
        } elseif ($_POST['ff_action'] === 'remove') {
            remove_favorite((int)$me['id'], $uid);
            $ff_success = 'Eliminado de tus FF.';
        }
        $me = current_user();
    }

    // Comentario
    if (isset($_POST['comment_body']) && $post) {
        try {
            add_comment((int)$post['id'], (int)$me['id'], $_POST['comment_body']);
            header('Location: ' . user_profile_url($username) . '?date=' . $post['post_date'] . '#comments');
            exit;
        } catch (RuntimeException $e) {
            $comment_error = $e->getMessage();
        }
    }

    // Borrar comentario
    if (isset($_POST['delete_comment_id'])) {
        try {
            delete_comment((int)$_POST['delete_comment_id'], (int)$me['id']);
            header('Location: ' . user_profile_url($username) . '?date=' . ($post['post_date'] ?? date('Y-m-d')) . '#comments');
            exit;
        } catch (RuntimeException $e) {
            $comment_error = $e->getMessage();
        }
    }
}

$comment_error = $comment_error ?? '';
$is_ff         = $me ? is_favorite((int)$me['id'], $uid) : false;
$is_saved      = ($me && $post) ? is_saved((int)$me['id'], (int)$post['id']) : false;

// Datos columnas
$prev_posts  = $post ? get_previous_posts($uid, $post['post_date'], 10) : [];
$ff_list     = get_favorites($uid, 8);
$ff_total    = count_favorites($uid);
$adjacent    = $post ? get_adjacent_posts($uid, $post['post_date']) : ['prev'=>null,'next'=>null];
$comments    = $post ? get_comments((int)$post['id']) : [];
$comment_count = count($comments);
$total_posts = count_user_posts($uid);

// URL del post actual (para story share)
$post_url = $post ? user_profile_url($username) . '?date=' . $post['post_date'] : '';
$story_img_url = $post ? SITE_URL . '/api/story-image.php?post_id=' . $post['id'] : '';

$page_title = $profile['username'] . ' en ' . SITE_NAME;
ob_start();
?>

<!-- Encabezado de perfil -->
<div id="profile-header">
  <div class="username-title"><?= h($profile['display_name'] ?: $profile['username']) ?></div>
  <?php if ($profile['mood']): ?>
    <div class="profile-mood"><?= h($profile['mood']) ?></div>
  <?php endif; ?>
  <?php if ($profile['location']): ?>
    <div style="font-size:11px;color:var(--text-dim);"><?= h($profile['location']) ?></div>
  <?php endif; ?>
  <?php if ($profile['bio']): ?>
    <div class="bio"><?= h($profile['bio']) ?></div>
  <?php endif; ?>
  <?= render_social_icons($profile) ?>
</div>

<!-- Botón FF -->
<?php if ($me && !$is_own): ?>
<div style="text-align:center; margin-bottom:8px;">
  <form method="post" style="display:inline;">
    <?php if ($is_ff): ?>
      <input type="hidden" name="ff_action" value="remove">
      <button type="submit" class="btn-ff remove">− Quitar de mis FF</button>
    <?php else: ?>
      <input type="hidden" name="ff_action" value="add">
      <button type="submit" class="btn-ff">+ Agregar a mis FF</button>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>
<?php if ($ff_error):   echo '<div class="flash-error">'   . h($ff_error)   . '</div>'; endif; ?>
<?php if ($ff_success): echo '<div class="flash-success">' . h($ff_success) . '</div>'; endif; ?>

<!-- Layout 3 columnas -->
<div id="layout">

  <!-- ── Col izquierda: FF ── -->
  <div id="col-left">
    <div class="col-section-title">Amigos/Favoritos</div>
    <div class="col-section-sub">de <?= h($profile['username']) ?></div>

    <?php if (empty($ff_list)): ?>
      <p style="font-size:11px;color:var(--text-dim);text-align:center;">Sin FFs aún.</p>
    <?php else: ?>
      <div class="ff-list">
        <?php foreach ($ff_list as $ff): ?>
          <div class="ff-item">
            <a href="<?= user_profile_url($ff['username']) ?>">
              <?php if ($ff['thumb_path']): ?>
                <img src="<?= h(thumb_url($ff['thumb_path'])) ?>" alt="<?= h($ff['username']) ?>">
              <?php else: ?>
                <div class="no-photo-box" style="width:<?= FF_THUMB_WIDTH ?>px;height:<?= FF_THUMB_HEIGHT ?>px;">sin foto</div>
              <?php endif; ?>
            </a>
            <a href="<?= user_profile_url($ff['username']) ?>" class="ff-username"><?= h($ff['username']) ?></a>
            <?php if ($ff['location']): ?>
              <span class="ff-location"><?= h($ff['location']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($ff_total > 8): ?>
        <a href="<?= SITE_URL ?>/favorites.php?u=<?= h($profile['username']) ?>" class="more-link">más FFs &rarr;</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- ── Col central ── -->
  <div id="col-center">

    <?php if (!$post): ?>
      <div style="text-align:center;padding:30px 0;color:var(--text-dim);font-size:12px;">
        <?= h($profile['username']) ?> todavía no posteó nada.
        <?php if ($is_own): ?>
          <br><br><a href="<?= SITE_URL ?>/upload.php" class="btn-primary" style="display:inline-block;padding:6px 18px;">Subir primera foto</a>
        <?php endif; ?>
      </div>
    <?php else: ?>

      <!-- Navegación -->
      <div id="post-nav">
        <?php if ($adjacent['prev']): ?>
          <a href="<?= user_profile_url($username) ?>?date=<?= $adjacent['prev']['post_date'] ?>">&larr;Anterior</a>
        <?php else: ?>
          <span style="color:var(--text-dim)">&larr;Anterior</span>
        <?php endif; ?>
        <?php if ($adjacent['next']): ?>
          <a href="<?= user_profile_url($username) ?>?date=<?= $adjacent['next']['post_date'] ?>">Siguiente&rarr;</a>
        <?php else: ?>
          <span style="color:var(--text-dim)">Siguiente&rarr;</span>
        <?php endif; ?>
      </div>

      <!-- Post principal -->
      <div id="post-main">
        <div class="post-title"><?= h($post['title']) ?></div>
        <div class="post-photo">
          <img src="<?= h(photo_url($post['photo_path'])) ?>" alt="<?= h($post['title']) ?>">
        </div>
        <?php if (trim($post['body'] ?? '') !== ''): ?>
          <div class="post-body"><?= h($post['body']) ?></div>
        <?php endif; ?>

        <!-- Barra de acciones -->
        <div id="post-actions">
          <span style="font-size:10px;color:var(--text-dim);flex:1;"><?= h(format_date($post['post_date'])) ?></span>

          <?php if ($is_own): ?>
            <a href="<?= SITE_URL ?>/upload.php?edit=<?= $post['id'] ?>" style="font-size:11px;color:var(--text-dim);">editar</a>
          <?php endif; ?>

          <?php if ($me && !$is_own): ?>
            <!-- Guardar post -->
            <button
              class="btn-save<?= $is_saved ? ' saved' : '' ?>"
              data-post-id="<?= $post['id'] ?>"
              data-saved="<?= $is_saved ? '1' : '0' ?>"
              onclick="toggleSave(this)"
              title="<?= $is_saved ? 'Quitar de guardados' : 'Guardar post' ?>">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="<?= $is_saved ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
              </svg>
              <span><?= $is_saved ? 'Guardado' : 'Guardar' ?></span>
            </button>
          <?php endif; ?>

          <!-- Compartir como Story (solo móvil) -->
          <?php if (!empty($profile['social_ig'])): ?>
            <a href="intent://camera/#Intent;package=com.instagram.android;scheme=https;S.browser_fallback_url=<?= urlencode(SITE_URL) ?>;end"
               class="btn-story story-mobile-only"
               id="btn-share-story"
               data-story-url="<?= h($story_img_url) ?>"
               title="Compartir en Instagram Stories">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
              </svg>
              Story
            </a>
            <span class="story-desktop-msg">Compartir como Story: abrí desde tu celular</span>
          <?php endif; ?>
        </div><!-- /post-actions -->
      </div><!-- /post-main -->

      <!-- Comentarios -->
      <div id="comments-section">
        <div class="comments-header">
          Comentarios (<?= $comment_count ?>/<?= MAX_COMMENTS_PER_POST ?>)
          <?php if ($is_own && $comment_count > 0): ?>
            &nbsp;<span style="font-weight:normal;color:var(--text-dim)">— podés borrar los que quieras</span>
          <?php endif; ?>
        </div>

        <?php if ($comment_error): ?>
          <div class="flash-error" style="margin:6px 8px;"><?= h($comment_error) ?></div>
        <?php endif; ?>

        <?php if (empty($comments)): ?>
          <div style="padding:8px;font-size:11px;color:var(--text-dim);">Sin comentarios todavía.</div>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment-item" id="c<?= $c['id'] ?>">
              <span class="c-author"><a href="<?= user_profile_url($c['username']) ?>"><?= h($c['username']) ?></a>:</span>
              <span class="c-body"><?= h($c['body']) ?></span>
              <span class="c-date"><?= date('j/n/Y H:i', strtotime($c['created_at'])) ?></span>
              <?php if ($is_own || ($me && (int)$me['is_admin'])): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="delete_comment_id" value="<?= $c['id'] ?>">
                  <button type="submit" class="c-delete" onclick="return confirm('¿Borrar comentario?')">✕</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($me && $comment_count < MAX_COMMENTS_PER_POST): ?>
          <div id="comment-form">
            <form method="post" action="<?= user_profile_url($username) ?>?date=<?= $post['post_date'] ?>#comments">
              <textarea name="comment_body" maxlength="500" placeholder="Tu comentario..." rows="2"></textarea>
              <button type="submit">Comentar</button>
            </form>
          </div>
        <?php elseif ($comment_count >= MAX_COMMENTS_PER_POST): ?>
          <div id="comments-full-limit">Este post alcanzó el límite de <?= MAX_COMMENTS_PER_POST ?> comentarios.</div>
        <?php elseif (!$me): ?>
          <div style="padding:6px 8px;font-size:11px;color:var(--text-dim);">
            <a href="<?= SITE_URL ?>/login.php">Entrá</a> para comentar.
          </div>
        <?php endif; ?>
      </div>

    <?php endif; // $post ?>
  </div><!-- /col-center -->

  <!-- ── Col derecha: fotos anteriores ── -->
  <div id="col-right">
    <div class="col-section-title">Todas las fotos</div>
    <div class="col-section-sub">de <?= h($profile['username']) ?></div>

    <?php if (empty($prev_posts)): ?>
      <p style="font-size:11px;color:var(--text-dim);text-align:center;">Sin fotos anteriores.</p>
    <?php else: ?>
      <div class="prev-photos-list">
        <?php foreach ($prev_posts as $pp): ?>
          <div class="prev-photo-item">
            <a href="<?= user_profile_url($username) ?>?date=<?= $pp['post_date'] ?>">
              <img src="<?= h(thumb_url($pp['thumb_path'])) ?>" alt="<?= h(format_date($pp['post_date'])) ?>">
            </a>
            <div class="thumb-date"><?= h(format_date($pp['post_date'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($total_posts > 11): ?>
        <a href="<?= SITE_URL ?>/archive.php?u=<?= h($profile['username']) ?>" class="more-link">más fotos &rarr;</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div><!-- /layout -->

<script>
// ── Guardar/desguardar post (AJAX) ─────────────────────────────
function toggleSave(btn) {
    const postId  = btn.dataset.postId;
    const saved   = btn.dataset.saved === '1';
    const action  = saved ? 'unsave' : 'save';
    const span    = btn.querySelector('span');
    const svg     = btn.querySelector('svg');

    btn.disabled = true;

    fetch('<?= SITE_URL ?>/api/save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId + '&action=' + action
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const nowSaved = data.saved;
            btn.dataset.saved = nowSaved ? '1' : '0';
            btn.classList.toggle('saved', nowSaved);
            span.textContent  = nowSaved ? 'Guardado' : 'Guardar';
            svg.setAttribute('fill', nowSaved ? 'currentColor' : 'none');
            btn.title = nowSaved ? 'Quitar de guardados' : 'Guardar post';
        }
    })
    .finally(() => { btn.disabled = false; });
}

// ── Compartir como Story en Instagram (móvil) ──────────────────
// Flujo: descarga la imagen story desde el servidor → abre el compartir nativo del OS
const storyBtn = document.getElementById('btn-share-story');
if (storyBtn) {
    storyBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        const storyUrl = this.dataset.storyUrl || '<?= h($story_img_url) ?>';
        if (!storyUrl) return;

        // Web Share API con archivo (requiere móvil moderno)
        if (navigator.canShare) {
            try {
                const resp = await fetch(storyUrl);
                const blob = await resp.blob();
                const file = new File([blob], 'pizzalog-story.jpg', { type: 'image/jpeg' });
                if (navigator.canShare({ files: [file] })) {
                    await navigator.share({ files: [file], title: '<?= h(addslashes($post['title'] ?? '')) ?>' });
                    return;
                }
            } catch(err) { /* fallback */ }
        }

        // Fallback: descargar la imagen para que el usuario la suba manualmente
        const a = document.createElement('a');
        a.href     = storyUrl;
        a.download = 'pizzalog-story.jpg';
        a.click();
        setTimeout(() => {
            alert('La imagen se descargó. Abrí Instagram, creá una nueva Story y seleccioná la imagen descargada.');
        }, 500);
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
