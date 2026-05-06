<?php

declare(strict_types=1);

const ADMIN_SESSION_ROTATE_SECONDS = 1800;

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
    );

    session_name('ECADMINSESSID');

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
    admin_session_rotate_if_needed();
}

function admin_session_rotate_if_needed(): void
{
    $now = time();

    if (!isset($_SESSION['__admin_session_rotated_at'])) {
        session_regenerate_id(true);
        $_SESSION['__admin_session_rotated_at'] = $now;
        return;
    }

    if ($now - (int)$_SESSION['__admin_session_rotated_at'] >= ADMIN_SESSION_ROTATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['__admin_session_rotated_at'] = $now;
    }
}

function admin_session_login(int $adminId, string $adminName, string $adminEmail): void
{
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['__admin_session_rotated_at'] = time();
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_name'] = $adminName;
    $_SESSION['admin_email'] = $adminEmail;
}

function admin_session_logout(): void
{
    admin_session_start();
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

function admin_require_login(): void
{
    admin_session_start();

    if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
        header('Location: login.php');
        exit;
    }
}

/**
 * ロールベースのアクセス制御をチェック
 * 指定されたロールのいずれかを持つ管理者のみアクセス可能
 * 
 * @param PDO $pdo データベース接続
 * @param string|array $requiredRoles 必要なロール名（文字列または配列）
 * @throws RuntimeException ロール確認に失敗した場合
 */
function admin_require_role(PDO $pdo, $requiredRoles): void
{
    admin_require_login();

    $adminId = (int)$_SESSION['admin_id'];
    $roles = is_string($requiredRoles) ? [$requiredRoles] : (array)$requiredRoles;

    require_once __DIR__ . '/RoleManager.php';
    $roleManager = new RoleManager($pdo);

    if (!$roleManager->hasAnyRole($adminId, $roles)) {
        http_response_code(403);
        exit('アクセス権がありません。');
    }
}

/**
 * 現在のセッション管理者のロール一覧を取得
 * 
 * @param PDO $pdo データベース接続
 * @return array ロール情報の配列
 */
function admin_get_current_roles(PDO $pdo): array
{
    admin_session_start();

    if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
        return [];
    }

    require_once __DIR__ . '/RoleManager.php';
    $roleManager = new RoleManager($pdo);
    return $roleManager->getUserRoles((int)$_SESSION['admin_id']);
}

/**
 * 現在のセッション管理者がロールを持つかチェック
 * 
 * @param PDO $pdo データベース接続
 * @param string $roleName ロール名
 * @return bool ロールを持つ場合 true
 */
function admin_has_role(PDO $pdo, string $roleName): bool
{
    admin_session_start();

    if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
        return false;
    }

    require_once __DIR__ . '/RoleManager.php';
    $roleManager = new RoleManager($pdo);
    return $roleManager->hasRole((int)$_SESSION['admin_id'], $roleName);
}
