<?php
// public/saved.php — Posts guardados por el usuario logueado
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';

$me  = require_login();
$uid = (int)$me['id'];

$per_page    = 24;
$page        = max(1, (int)($_GET['p'] ?? 1));
$offset      = ($page - 1) * $per_page;
$saved       = get_saved_posts($uid, $per_page, $offset);
$total       = count_saved_posts($uid);
$total_pages = max(1, (int)ceil($total / $per_page));

$page_title = 'Mis guardados';
ob_start();
?>
<div style="margin-bottom:14px;">
  <h2 style="font-size:15px;color:var(--accent);display:inline;">Mis guardados</h2>
  <span style="font-size:11px;color:var(--text-dim);margin-left:8px;"><?= $total ?> post<?= $total !== 1 ? 's' : '' ?></span>
  <span style="float:right;font-size:11px;">
    <a href="<?= user_profile_url($me['username']) ?>">&larr; mi pizzalog</a>
  </span>
</div>

<?php if (empty($saved)): ?>
  <p style="color:var(--text-dim);font-size:12px;padding:20px 0;">
    Todavía no guardaste ningún post. Cuando veas un post que te guste, hacé clic en "Guardar".
  </p>
<?php else: ?>
  <div class="saved-grid">
    <?php foreach ($saved as $s): ?>
      <div class="saved-item">
        <a href="<?= user_profile_url($s['username']) ?>?date=<?= $s['post_date'] ?>">
          <img src="<?= h(thumb_url($s['thumb_path'])) ?>" alt="<?= h($s['title']) ?>">
        </a>
        <a href="<?= user_profile_url($s['username']) ?>" class="saved-username">
          <?= h($s['username']) ?>
        </a>
        <div class="saved-date"><?= h(format_date($s['post_date'])) ?></div>
        <!-- Quitar guardado inline -->
        <button
          class="btn-save saved"
          data-post-id="<?= $s['id'] ?>"
          data-saved="1"
          onclick="removeSaved(this)"
          style="margin-top:3px;font-size:10px;">
          ✕ quitar
        </button>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?p=<?= $page-1 ?>">&larr; anterior</a>
      <?php endif; ?>
      <span style="color:var(--text-dim)">página <?= $page ?> de <?= $total_pages ?></span>
      <?php if ($page < $total_pages): ?>
        <a href="?p=<?= $page+1 ?>">siguiente &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script>
function removeSaved(btn) {
    const postId = btn.dataset.postId;
    btn.disabled = true;
    fetch('<?= SITE_URL ?>/api/save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId + '&action=unsave'
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Quitar el item del DOM suavemente
            const item = btn.closest('.saved-item');
            item.style.opacity = '0';
            item.style.transition = 'opacity 0.3s';
            setTimeout(() => item.remove(), 300);
        }
    })
    .catch(() => { btn.disabled = false; });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
