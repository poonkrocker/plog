<?php
// public/favorites.php — lista completa de FFs de un usuario
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';

$username = trim($_GET['u'] ?? '');
if (!$username) { http_response_code(404); die('Usuario no encontrado.'); }
$profile = get_user_by_username($username);
if (!$profile) { http_response_code(404); die('Usuario no encontrado.'); }

$uid      = (int)$profile['id'];
$per_page = 24;
$page     = max(1, (int)($_GET['p'] ?? 1));
$offset   = ($page - 1) * $per_page;
$ff_list  = get_favorites($uid, $per_page, $offset);
$ff_total = count_favorites($uid);
$total_pages = max(1, (int)ceil($ff_total / $per_page));

$page_title = 'FFs de ' . $profile['username'];
ob_start();
?>
<p style="margin-bottom:12px;font-size:12px;">
  <a href="<?= user_profile_url($username) ?>">&larr; Volver al pizzalog de <?= h($username) ?></a>
</p>

<h2 style="font-size:15px;color:var(--accent);margin-bottom:14px;">
  Amigos/Favoritos de <?= h($profile['username']) ?> (<?= $ff_total ?>)
</h2>

<?php if (empty($ff_list)): ?>
  <p style="color:var(--text-dim);font-size:12px;">Sin FFs todavía.</p>
<?php else: ?>
  <div class="feed-grid">
    <?php foreach ($ff_list as $ff): ?>
      <div class="feed-item">
        <a href="<?= user_profile_url($ff['username']) ?>">
          <?php if ($ff['thumb_path']): ?>
            <img src="<?= h(thumb_url($ff['thumb_path'])) ?>" alt="<?= h($ff['username']) ?>">
          <?php else: ?>
            <div class="no-photo-box" style="width:130px;height:97px;">sin foto</div>
          <?php endif; ?>
        </a>
        <a href="<?= user_profile_url($ff['username']) ?>" class="feed-username">
          <?= h($ff['username']) ?>
        </a>
        <?php if ($ff['location']): ?>
          <div class="feed-date"><?= h($ff['location']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?u=<?= h($username) ?>&p=<?= $page-1 ?>">&larr; anterior</a>
      <?php endif; ?>
      <span style="color:var(--text-dim)">página <?= $page ?> de <?= $total_pages ?></span>
      <?php if ($page < $total_pages): ?>
        <a href="?u=<?= h($username) ?>&p=<?= $page+1 ?>">siguiente &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
