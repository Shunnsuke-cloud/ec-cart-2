<?php

const EC_SESSION_ROTATE_SECONDS = 1800;

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
    );

    session_name('ECSESSID');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isSecure, true);
    }

    session_start();
    app_session_rotate_if_needed();
}

function app_session_rotate_if_needed(): void
{
    $now = time();

    if (!isset($_SESSION['__session_rotated_at'])) {
        session_regenerate_id(true);
        $_SESSION['__session_rotated_at'] = $now;
        return;
    }

    if ($now - (int)$_SESSION['__session_rotated_at'] >= EC_SESSION_ROTATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['__session_rotated_at'] = $now;
    }
}

function app_session_login(int $userId, string $userName, string $userEmail): void
{
    app_session_start();
    session_regenerate_id(true);
    $_SESSION['__session_rotated_at'] = time();
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_email'] = $userEmail;
}

function app_session_logout(): void
{
    app_session_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();
}
