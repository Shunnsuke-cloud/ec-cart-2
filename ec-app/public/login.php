<?php
$pageTitle = 'ログイン';
$activePage = 'login';

require_once __DIR__ . '/../app/Auth/session.php';
app_session_start();
require_once __DIR__ . '/../config/database.php';

$errorMessage = '';
$noticeMessage = '';
$form = [
    'email' => '',
];

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $noticeMessage = '会員登録が完了しました。ログインしてください。';
}

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

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('DB接続設定を確認してください。');
        }

        $stmtUser = $pdo->prepare(
            <<<'SQL'
SELECT id, name, email, password, status
FROM users
WHERE email = :email
  AND deleted_at IS NULL
LIMIT 1
SQL
        );
        $stmtUser->execute(['email' => $form['email']]);
        $user = $stmtUser->fetch();

        if (!$user || !password_verify($password, (string)$user['password'])) {
            throw new RuntimeException('メールアドレスまたはパスワードが正しくありません。');
        }

        if ((string)$user['status'] !== 'active') {
            throw new RuntimeException('このアカウントは利用できません。');
        }

        app_session_login((int)$user['id'], (string)$user['name'], (string)$user['email']);

        header('Location: index.php?logged_in=1');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'ログインに失敗しました。時間をおいて再度お試しください。';
    }
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
    <h2>ログイン</h2>

    <?php if ($noticeMessage !== ''): ?>
        <p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

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

    <p class="product-actions">未登録の方は <a href="register.php">会員登録</a> をしてください。</p>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
