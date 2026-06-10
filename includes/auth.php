<?php
// includes/auth.php

require_once __DIR__ . '/db.php';

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('PLSESSID');
        session_start();
    }
}

function current_user(): ?array {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache === null) {
        $st = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $st->execute([$_SESSION['user_id']]);
        $cache = $st->fetch() ?: null;
    }
    return $cache;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if (!$user['is_admin']) {
        http_response_code(403);
        die('Acceso denegado.');
    }
    return $user;
}

function login_user(string $username, string $password): array|false {
    $st = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    return $user;
}

function logout_user(): void {
    session_start_safe();
    $_SESSION = [];
    session_destroy();
}

// Devuelve true si el código es válido y no fue usado
function validate_invitation_code(string $code): array|false {
    $st = db()->prepare(
        'SELECT * FROM invitation_codes WHERE code = ? AND used_by IS NULL LIMIT 1'
    );
    $st->execute([trim($code)]);
    return $st->fetch() ?: false;
}

// Registra un usuario nuevo consumiendo su código de invitación
// Devuelve el nuevo user_id o lanza excepción
function register_user(array $data, string $code): int {
    $inv = validate_invitation_code($code);
    if (!$inv) throw new RuntimeException('Código de invitación inválido o ya utilizado.');

    $username = trim($data['username']);
    $email    = trim($data['email']);
    $password = $data['password'];

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        throw new RuntimeException('El usuario solo puede tener letras, números y guión bajo (3-30 caracteres).');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Email inválido.');
    }
    if (strlen($password) < 6) {
        throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
    }

    // Verificar unicidad
    $st = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$username, $email]);
    if ($st->fetch()) throw new RuntimeException('El usuario o email ya existe.');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Crear usuario
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $st = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, invited_by, invitations_remaining)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$username, $email, $hash, $inv['created_by'], DEFAULT_USER_INVITATIONS]);
        $new_id = (int) $pdo->lastInsertId();

        // Marcar invitación como usada
        $st = $pdo->prepare(
            'UPDATE invitation_codes SET used_by = ?, used_at = NOW() WHERE id = ?'
        );
        $st->execute([$new_id, $inv['id']]);

        // Descontar invitación al creador (excepto admin)
        $st = $pdo->prepare(
            'UPDATE users SET invitations_remaining = GREATEST(0, invitations_remaining - 1)
             WHERE id = ? AND is_admin = 0'
        );
        $st->execute([$inv['created_by']]);

        $pdo->commit();
        return $new_id;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Genera un código aleatorio de invitación para el usuario dado
function generate_invitation(int $user_id): string {
    $user = db()->prepare('SELECT is_admin, invitations_remaining FROM users WHERE id = ? LIMIT 1');
    $user->execute([$user_id]);
    $u = $user->fetch();
    if (!$u) throw new RuntimeException('Usuario no encontrado.');
    if (!$u['is_admin'] && $u['invitations_remaining'] < 1) {
        throw new RuntimeException('No tenés invitaciones disponibles.');
    }

    $code = strtoupper(bin2hex(random_bytes(8))); // 16 chars hex
    $st = db()->prepare('INSERT INTO invitation_codes (code, created_by) VALUES (?, ?)');
    $st->execute([$code, $user_id]);
    return $code;
}
