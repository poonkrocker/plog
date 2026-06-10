<?php
// public/logout.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
logout_user();
header('Location: ' . SITE_URL . '/');
exit;
