<?php
$pageTitle = '管理者ログイン';
$activePage = 'admin-login';

require_once __DIR__ . '/../../app/Admin/auth.php';
admin_session_start();
$pdo = require __DIR__ . '/../../config/database.php';

$errorMessage = '';
$form = [
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['email'] = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    try {
        if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('有効なメールアドレスを入力してください。');
        }

        if ($password === '') {
            throw new RuntimeException('パスワードを入力してください。');
        }

        $stmtAdmin = $pdo->prepare(
            <<<'SQL'
SELECT id, name, email, password, status
FROM admin_users
WHERE email = :email
  AND status = 'active'
LIMIT 1
SQL
        );
        $stmtAdmin->execute(['email' => $form['email']]);
        $admin = $stmtAdmin->fetch();

        if (!$admin || !password_verify($password, (string)$admin['password'])) {
            throw new RuntimeException('メールアドレスまたはパスワードが正しくありません。');
        }

        admin_session_login((int)$admin['id'], (string)$admin['name'], (string)$admin['email']);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'ログインに失敗しました。時間をおいて再度お試しください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | EC Cart</title>
    <base href="/cart-system/">
    <link rel="stylesheet" href="css/common.css">
</head>
<body>
    <main class="site-main">
        <div class="container">
            <section>
                <h2>管理者ログイン</h2>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form method="post" class="auth-form" novalidate>
                    <label for="email">メールアドレス</label>
                    <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="password">パスワード</label>
                    <input id="password" name="password" type="password" required>

                    <button class="button" type="submit">ログインする</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
