<?php
// includes/image.php
// Requiere Imagick (confirmado: 3.8.1 disponible en el hosting)

require_once __DIR__ . '/config.php';

/**
 * Procesa la foto subida para un post:
 * - Valida tipo y tamaño
 * - Redimensiona a máximo 800x600 manteniendo proporción
 * - Guarda versión completa y thumbnail 110x82 (crop centrado)
 * - Devuelve ['photo' => path_relativo, 'thumb' => path_relativo]
 *
 * @throws RuntimeException en cualquier error
 */
function process_post_image(array $file, int $user_id): array {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size_bytes = 10 * 1024 * 1024; // 10 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el archivo (código ' . $file['error'] . ').');
    }
    if ($file['size'] > $max_size_bytes) {
        throw new RuntimeException('La imagen no puede superar 10 MB.');
    }

    // Verificar tipo real con Imagick (no confiar en MIME del cliente)
    $tmp = $file['tmp_name'];
    try {
        $im = new \Imagick($tmp);
    } catch (\ImagickException $e) {
        throw new RuntimeException('El archivo no es una imagen válida.');
    }

    $format = strtolower($im->getImageFormat());
    $allowed_formats = ['jpeg', 'png', 'gif', 'webp'];
    if (!in_array($format, $allowed_formats, true)) {
        $im->destroy();
        throw new RuntimeException('Formato no permitido. Usá JPG, PNG, GIF o WebP.');
    }
    $ext = ($format === 'jpeg') ? 'jpg' : $format;

    // Nombres de archivo únicos basados en user + fecha
    $date_str  = date('Y-m-d');
    $base_name = $user_id . '_' . $date_str . '_' . bin2hex(random_bytes(4));
    $photo_name = $base_name . '.' . $ext;
    $thumb_name = $base_name . '_thumb.' . $ext;

    $photo_full_path = UPLOADS_DIR . $photo_name;
    $thumb_full_path = THUMBS_DIR  . $thumb_name;

    // --- Foto completa: máximo 800x600, mantiene proporción ---
    $im->stripImage(); // eliminar EXIF
    $im->autoOrient();

    $w = $im->getImageWidth();
    $h = $im->getImageHeight();

    if ($w > MAX_PHOTO_WIDTH || $h > MAX_PHOTO_HEIGHT) {
        $im->resizeImage(MAX_PHOTO_WIDTH, MAX_PHOTO_HEIGHT, \Imagick::FILTER_LANCZOS, 1, true);
    }

    // Comprimir
    if ($format === 'jpeg') {
        $im->setImageCompressionQuality(88);
    }

    $im->writeImage($photo_full_path);

    // --- Thumbnail: 110x82 crop centrado ---
    $thumb = clone $im;

    // Primero fit, luego crop al centro
    $tw = THUMB_WIDTH;
    $th = THUMB_HEIGHT;

    $ratio_w = $thumb->getImageWidth()  / $tw;
    $ratio_h = $thumb->getImageHeight() / $th;
    $ratio   = min($ratio_w, $ratio_h);

    $fit_w = (int) round($thumb->getImageWidth()  / $ratio);
    $fit_h = (int) round($thumb->getImageHeight() / $ratio);

    $thumb->resizeImage($fit_w, $fit_h, \Imagick::FILTER_LANCZOS, 1);

    $crop_x = (int) max(0, floor(($fit_w - $tw) / 2));
    $crop_y = (int) max(0, floor(($fit_h - $th) / 2));
    $thumb->cropImage($tw, $th, $crop_x, $crop_y);

    if ($format === 'jpeg') {
        $thumb->setImageCompressionQuality(82);
    }

    $thumb->writeImage($thumb_full_path);

    $im->destroy();
    $thumb->destroy();

    return [
        'photo' => $photo_name,
        'thumb' => $thumb_name,
    ];
}

/**
 * Procesa el avatar del usuario.
 * Redimensiona a 200x200 crop centrado.
 * Devuelve el nombre del archivo guardado.
 */
function process_avatar(array $file, int $user_id): string {
    $max_size_bytes = 5 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el avatar.');
    }
    if ($file['size'] > $max_size_bytes) {
        throw new RuntimeException('El avatar no puede superar 5 MB.');
    }

    try {
        $im = new \Imagick($file['tmp_name']);
    } catch (\ImagickException $e) {
        throw new RuntimeException('Archivo de avatar inválido.');
    }

    $format = strtolower($im->getImageFormat());
    $ext    = ($format === 'jpeg') ? 'jpg' : $format;

    $im->stripImage();
    $im->autoOrient();

    // 200x200 crop centrado
    $size = 200;
    $w = $im->getImageWidth();
    $h = $im->getImageHeight();
    $ratio = max($size / $w, $size / $h);
    $nw = (int) round($w * $ratio);
    $nh = (int) round($h * $ratio);
    $im->resizeImage($nw, $nh, \Imagick::FILTER_LANCZOS, 1);
    $cx = (int) floor(($nw - $size) / 2);
    $cy = (int) floor(($nh - $size) / 2);
    $im->cropImage($size, $size, $cx, $cy);
    $im->setImageCompressionQuality(85);

    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    $path     = UPLOADS_DIR . $filename;
    $im->writeImage($path);
    $im->destroy();

    return $filename;
}

/**
 * Borra archivos de imagen de un post (foto + thumb).
 */
function delete_post_images(string $photo_path, string $thumb_path): void {
    $f = UPLOADS_DIR . basename($photo_path);
    $t = THUMBS_DIR  . basename($thumb_path);
    if (file_exists($f)) @unlink($f);
    if (file_exists($t)) @unlink($t);
}
