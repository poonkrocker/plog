<?php
// public/archive.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';

$username = trim($_GET['u'] ?? '');
if (!$username) { http_response_code(404); die('Usuario no encontrado.'); }
$profile = get_user_by_username($username);
if (!$profile) { http_response_code(404); die('Usuario no encontrado.'); }

$uid   = (int)$profile['id'];
$years = get_active_years($uid);

// Nombres de meses en español
$month_names = ['', 'enero','febrero','marzo','abril','mayo','junio',
                'julio','agosto','septiembre','octubre','noviembre','diciembre'];
$day_names   = ['Lu','Ma','Mi','Ju','Vi','Sá','Do'];

$page_title = 'Archivo de ' . $profile['username'];
ob_start();
?>
<p style="margin-bottom:12px;font-size:12px;">
  <a href="<?= user_profile_url($username) ?>">&larr; Volver al pizzalog de <?= h($username) ?></a>
</p>

<h2 style="font-size:15px;color:var(--accent);margin-bottom:14px;">
  Todas las fotos de <?= h($profile['username']) ?>
</h2>

<?php if (empty($years)): ?>
  <p style="color:var(--text-dim);font-size:12px;">Sin posts todavía.</p>
<?php else: ?>
  <?php foreach ($years as $year): ?>
    <div class="archive-year">
      <h3><?= (int)$year ?></h3>
      <div class="archive-months">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <?php $posts_in_month = get_posts_for_month($uid, $year, $m); ?>
          <?php if (empty($posts_in_month)) continue; ?>
          <div class="archive-month">
            <h4><?= $month_names[$m] ?></h4>
            <?php
            $first_day = mktime(0,0,0,$m,1,$year);
            $days_in_month = (int)date('t', $first_day);
            // 0=Sunday..6=Saturday → convertir a lunes=0
            $start_dow = ((int)date('w', $first_day) + 6) % 7;
            ?>
            <table class="cal-table">
              <tr>
                <?php foreach ($day_names as $dn): ?>
                  <th><?= $dn ?></th>
                <?php endforeach; ?>
              </tr>
              <?php
              $day = 1;
              $col = $start_dow;
              while ($day <= $days_in_month):
                echo '<tr>';
                for ($c = 0; $c < 7; $c++):
                  if (($col > 0 && $day === 1) || $day > $days_in_month):
                    echo '<td></td>';
                    if ($day === 1) $col--;
                  else:
                    $d_str = sprintf('%04d-%02d-%02d', $year, $m, $day);
                    $has   = in_array($d_str, $posts_in_month, true);
                    if ($has):
                      echo '<td class="has-post"><a href="' . user_profile_url($username) . '?date=' . $d_str . '">' . $day . '</a></td>';
                    else:
                      echo '<td>' . $day . '</td>';
                    endif;
                    $day++;
                  endif;
                endfor;
                echo '</tr>';
              endwhile;
              ?>
            </table>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';
