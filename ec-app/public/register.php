<?php
$pageTitle = '会員登録';
$activePage = 'register';

$pdo = require __DIR__ . '/../config/database.php';

$errorMessage = '';
$noticeMessage = '';

$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['email'] = trim((string)($_POST['email'] ?? ''));
    $form['phone'] = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    try {
        if ($form['name'] === '') {
            throw new RuntimeException('お名前を入力してください。');
        }

        if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('有効なメールアドレスを入力してください。');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('パスワードは8文字以上で入力してください。');
        }

        if ($password !== $passwordConfirm) {
            throw new RuntimeException('確認用パスワードが一致しません。');
        }

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('DB接続設定を確認してください。');
        }

        $stmtExists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmtExists->execute(['email' => $form['email']]);
        if ($stmtExists->fetch()) {
            throw new RuntimeException('このメールアドレスは既に登録されています。');
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmtCreate = $pdo->prepare(
            <<<'SQL'
INSERT INTO users (name, email, password, phone, status)
VALUES (:name, :email, :password, :phone, :status)
SQL
        );
        $stmtCreate->execute([
            'name' => $form['name'],
            'email' => $form['email'],
            'password' => $hashedPassword,
            'phone' => $form['phone'] !== '' ? $form['phone'] : null,
            'status' => 'active',
        ]);

        header('Location: login.php?registered=1');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : '会員登録に失敗しました。時間をおいて再度お試しください。';
    }
}

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $noticeMessage = '会員登録が完了しました。';
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
    <h2>会員登録</h2>

    <?php if ($noticeMessage !== ''): ?>
        <p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="product-actions"><a class="button" href="product.php">商品一覧へ</a></p>
    <?php else: ?>
        <?php if ($errorMessage !== ''): ?>
            <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="post" class="auth-form" novalidate>
            <label for="name">お名前</label>
            <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="email">メールアドレス</label>
            <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="phone">電話番号（任意）</label>
            <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($form['phone'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="password">パスワード（8文字以上）</label>
            <input id="password" name="password" type="password" required>

            <label for="password_confirm">パスワード（確認）</label>
            <input id="password_confirm" name="password_confirm" type="password" required>

            <button class="button" type="submit">登録する</button>
        </form>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
