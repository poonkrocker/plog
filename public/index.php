<?php
// public/index.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';

$me      = current_user();
$per_page = 30;
$page    = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($page - 1) * $per_page;
$items   = get_feed_grid($per_page, $offset);

// Total para paginación
$total_st = db()->query(
    'SELECT COUNT(DISTINCT u.id) FROM users u
     JOIN posts p ON p.user_id = u.id
     WHERE u.is_active = 1'
);
$total      = (int)$total_st->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$page_title = 'Inicio';
ob_start();
?>
<div id="feed-header">
  <h2>Pizzalog &mdash; lo último</h2>
  <p style="font-size:11px;color:var(--text-dim);margin-top:4px;">
    <?= $total ?> pizzaloguer<?= $total !== 1 ? 'os' : 'o' ?> activo<?= $total !== 1 ? 's' : '' ?>
  </p>
</div>

<?php if (empty($items)): ?>
  <p style="text-align:center;color:var(--text-dim);padding:30px 0;font-size:12px;">
    Todavía no hay posts. ¡Sé el primero!
    <?php if (!$me): ?>
      <a href="<?= SITE_URL ?>/register.php">Crear cuenta</a>
    <?php endif; ?>
  </p>
<?php else: ?>
  <div class="feed-grid">
    <?php foreach ($items as $item): ?>
      <div class="feed-item">
        <a href="<?= user_profile_url($item['username']) ?>">
          <?php if ($item['thumb_path']): ?>
            <img src="<?= h(thumb_url($item['thumb_path'])) ?>" alt="<?= h($item['username']) ?>">
          <?php else: ?>
            <div class="no-photo-box" style="width:130px;height:97px;">sin foto</div>
          <?php endif; ?>
        </a>
        <a href="<?= user_profile_url($item['username']) ?>" class="feed-username">
          <?= h($item['username']) ?>
        </a>
        <div class="feed-date"><?= h(format_date($item['post_date'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?p=<?= $page - 1 ?>">&larr; anterior</a>
      <?php endif; ?>
      <span style="color:var(--text-dim)">página <?= $page ?> de <?= $total_pages ?></span>
      <?php if ($page < $total_pages): ?>
        <a href="?p=<?= $page + 1 ?>">siguiente &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
