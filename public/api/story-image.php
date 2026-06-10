<?php
// public/api/story-image.php
// Genera una imagen 1080x1920 (formato Story) para compartir en Instagram.
// Incluye: foto del post centrada, nombre de usuario, título, URL del Pizzalog.

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

$post_id = (int)($_GET['post_id'] ?? 0);
if (!$post_id) { http_response_code(400); die('post_id requerido'); }

$post = get_post_by_id($post_id);
if (!$post) { http_response_code(404); die('Post no encontrado'); }

$user = get_user_by_id((int)$post['user_id']);
if (!$user) { http_response_code(404); die('Usuario no encontrado'); }

// Cache: si ya existe la imagen story, servirla directamente
$cache_name = 'story_' . $post_id . '.jpg';
$cache_path = UPLOADS_DIR . $cache_name;

if (file_exists($cache_path) && (time() - filemtime($cache_path)) < 86400) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($cache_path);
    exit;
}

// ── Generar imagen ──────────────────────────────────────────────
$W = 1080;
$H = 1920;

try {
    // Canvas de fondo (color teal oscuro del Pizzalog)
    $canvas = new \Imagick();
    $canvas->newImage($W, $H, new \ImagickPixel('#0d2a2a'));
    $canvas->setImageFormat('jpeg');

    // Gradiente sutil en el fondo
    $grad = new \Imagick();
    $grad->newPseudoImage($W, $H, 'gradient:#1a3a3a-#050f0f');
    $canvas->compositeImage($grad, \Imagick::COMPOSITE_OVER, 0, 0);
    $grad->destroy();

    // ── Foto del post, centrada verticalmente (zona media) ──────
    $photo_path_full = UPLOADS_DIR . basename($post['photo_path']);
    if (file_exists($photo_path_full)) {
        $photo = new \Imagick($photo_path_full);
        $photo->stripImage();
        $photo->autoOrient();

        // Fit dentro de 900x900 manteniendo proporción
        $photo->resizeImage(900, 900, \Imagick::FILTER_LANCZOS, 1, true);
        $pw = $photo->getImageWidth();
        $ph = $photo->getImageHeight();
        $px = (int)(($W - $pw) / 2);
        $py = (int)(($H - $ph) / 2) - 80; // levemente arriba del centro

        // Sombra suave
        $shadow = clone $photo;
        $shadow->setImageBackgroundColor(new \ImagickPixel('black'));
        $shadow->shadowImage(60, 8, 0, 0);
        $canvas->compositeImage($shadow, \Imagick::COMPOSITE_OVER, $px - 10, $py + 10);
        $shadow->destroy();

        $canvas->compositeImage($photo, \Imagick::COMPOSITE_OVER, $px, $py);
        $photo->destroy();
    }

    // ── Textos ──────────────────────────────────────────────────
    $draw = new \ImagickDraw();
    $draw->setTextAntialias(true);

    // Nombre de usuario (zona superior)
    $draw->setFillColor('#ff44cc');       // magenta
    $draw->setFontSize(52);
    $draw->setFontWeight(700);
    $draw->setTextAlignment(\Imagick::ALIGN_CENTER);
    $canvas->annotateImage($draw, $W / 2, 130, 0, '@' . $user['username']);

    // Título del post
    $title = mb_strlen($post['title']) > 60
        ? mb_substr($post['title'], 0, 57) . '...'
        : $post['title'];
    $draw->setFillColor('#ffffff');
    $draw->setFontSize(38);
    $draw->setFontWeight(400);
    $canvas->annotateImage($draw, $W / 2, 195, 0, $title);

    // Fecha
    $draw->setFillColor('#7aabab');
    $draw->setFontSize(30);
    $canvas->annotateImage($draw, $W / 2, 248, 0, format_date($post['post_date']));

    // URL del Pizzalog (zona inferior)
    $draw->setFillColor('#44ffee');       // cyan
    $draw->setFontSize(34);
    $canvas->annotateImage($draw, $W / 2, $H - 90, 0, 'pizzalog.net/' . $user['username']);

    // Texto "via Pizzalog" pequeño
    $draw->setFillColor('#2a6666');
    $draw->setFontSize(24);
    $canvas->annotateImage($draw, $W / 2, $H - 50, 0, 'Pizzalog — la red de los amantes de la pizza');

    $draw->destroy();

    // ── Guardar y servir ────────────────────────────────────────
    $canvas->setImageCompressionQuality(90);
    $canvas->writeImage($cache_path);

    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    header('Content-Disposition: attachment; filename="pizzalog-story.jpg"');
    echo $canvas->getImageBlob();
    $canvas->destroy();

} catch (\ImagickException $e) {
    http_response_code(500);
    die('Error generando imagen: ' . $e->getMessage());
}
