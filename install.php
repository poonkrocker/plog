<?php
// install.php — ELIMINAR después de la primera ejecución exitosa
// Acceder una sola vez desde el navegador: https://pizzalog.net/install.php

define('INSTALL_SECRET', 'CAMBIAR_ESTE_SECRETO'); // cambiar antes de subir

if (($_GET['secret'] ?? '') !== INSTALL_SECRET) {
    http_response_code(403);
    die('Acceso denegado. Pasá el parámetro ?secret=TU_SECRETO');
}

require_once __DIR__ . '/includes/config.php';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die('Error de conexión: ' . htmlspecialchars($e->getMessage()));
}

$schema = file_get_contents(__DIR__ . '/schema.sql');

// Ejecutar sentencia por sentencia
$statements = array_filter(
    array_map('trim', preg_split('/;\s*\n/', $schema)),
    fn($s) => $s !== '' && !str_starts_with($s, '--')
);

$errors = [];
foreach ($statements as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) {
        $errors[] = htmlspecialchars($e->getMessage()) . '<br><pre>' . htmlspecialchars($sql) . '</pre>';
    }
}

// Crear usuario admin inicial
$admin_user = 'eze';
$admin_mail = 'hola@arrabbiata.com.ar'; // ← cambiar antes de subir
$admin_pass = bin2hex(random_bytes(8)); // contraseña generada aleatoriamente

try {
    $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
    $st   = $pdo->prepare(
        'INSERT IGNORE INTO users (username, email, password_hash, invitations_remaining, is_admin)
         VALUES (?, ?, ?, 9999, 1)'
    );
    $st->execute([$admin_user, $admin_mail, $hash]);
    $admin_created = $pdo->lastInsertId() > 0;
} catch (PDOException $e) {
    $errors[] = 'Error creando admin: ' . htmlspecialchars($e->getMessage());
    $admin_created = false;
}

// Directorios de uploads
$dirs = [UPLOADS_DIR, THUMBS_DIR];
foreach ($dirs as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}

?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Pizzalog — Instalación</title>
<style>body{background:#1a3a3a;color:#ccc;font-family:Verdana,sans-serif;padding:30px;} h1{color:#ff44cc;} pre{background:#0d2020;padding:10px;} .ok{color:#88ffcc;} .err{color:#ff8888;}</style>
</head>
<body>
<h1>Pizzalog — Instalación</h1>

<?php if ($errors): ?>
  <p class="err">Se encontraron errores (puede que las tablas ya existieran):</p>
  <?php foreach ($errors as $e): ?>
    <p class="err"><?= $e ?></p>
  <?php endforeach; ?>
<?php else: ?>
  <p class="ok">✓ Tablas creadas correctamente.</p>
<?php endif; ?>

<?php if ($admin_created): ?>
  <p class="ok">✓ Usuario admin creado:</p>
  <pre>
  Usuario:    <?= htmlspecialchars($admin_user) ?>
  Email:      <?= htmlspecialchars($admin_mail) ?>
  Contraseña: <?= htmlspecialchars($admin_pass) ?>
  </pre>
  <p style="color:#ff8888;"><strong>⚠ Anotá esta contraseña AHORA y cambiala desde Ajustes. No se vuelve a mostrar.</strong></p>
<?php else: ?>
  <p style="color:var(--text-dim)">Usuario admin ya existía o no se pudo crear.</p>
<?php endif; ?>

<p class="ok">✓ Directorios de uploads verificados.</p>

<p style="margin-top:20px;color:#ff8888;">
  <strong>⚠ IMPORTANTE: eliminá este archivo del servidor ahora que terminó la instalación.</strong><br>
  <code>rm install.php</code>
</p>

<p><a href="<?= SITE_URL ?>" style="color:#44ffee;">Ir al Pizzalog &rarr;</a></p>
</body>
</html>
