<?php
$pageTitle = '商品新規作成';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
require_once __DIR__ . '/../../../config/database.php';

$errorMessage = '';
$form = [
    'name' => '',
    'slug' => '',
    'brand' => '',
    'description' => '',
    'category_id' => '',
    'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['slug'] = trim((string)($_POST['slug'] ?? ''));
    $form['brand'] = trim((string)($_POST['brand'] ?? ''));
    $form['description'] = trim((string)($_POST['description'] ?? ''));
    $form['category_id'] = trim((string)($_POST['category_id'] ?? ''));
    $form['status'] = trim((string)($_POST['status'] ?? 'active'));

    try {
        if ($form['name'] === '') {
            throw new RuntimeException('商品名を入力してください。');
        }
        if ($form['slug'] === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $form['slug'])) {
            throw new RuntimeException('slug は英小文字・数字・ハイフンのみで入力してください。');
        }
        if (!in_array($form['status'], ['active', 'inactive'], true)) {
            throw new RuntimeException('状態の値が不正です。');
        }
        if ($form['category_id'] !== '' && !ctype_digit($form['category_id'])) {
            throw new RuntimeException('カテゴリIDは数字で入力してください。');
        }

        $stmtExists = $pdo->prepare('SELECT id FROM products WHERE slug = :slug LIMIT 1');
        $stmtExists->execute(['slug' => $form['slug']]);
        if ($stmtExists->fetch()) {
            throw new RuntimeException('そのslugは既に使用されています。');
        }

        $stmtInsert = $pdo->prepare(
            <<<'SQL'
INSERT INTO products (name, slug, description, brand, category_id, status)
VALUES (:name, :slug, :description, :brand, :category_id, :status)
SQL
        );
        $stmtInsert->execute([
            'name' => $form['name'],
            'slug' => $form['slug'],
            'description' => $form['description'] !== '' ? $form['description'] : null,
            'brand' => $form['brand'] !== '' ? $form['brand'] : null,
            'category_id' => $form['category_id'] !== '' ? (int)$form['category_id'] : null,
            'status' => $form['status'],
        ]);

        header('Location: index.php?created=1');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException ? $e->getMessage() : '商品の登録に失敗しました。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | EC Cart Admin</title>
    <link rel="stylesheet" href="../../css/common.css">
</head>
<body>
    <main class="site-main">
        <div class="container">
            <section>
                <h2>商品新規作成</h2>
                <p class="product-actions"><a class="button" href="index.php">一覧へ戻る</a></p>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form method="post" class="auth-form" novalidate>
                    <label for="name">商品名</label>
                    <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="slug">slug</label>
                    <input id="slug" name="slug" type="text" required value="<?php echo htmlspecialchars($form['slug'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="sample-product">

                    <label for="brand">ブランド</label>
                    <input id="brand" name="brand" type="text" value="<?php echo htmlspecialchars($form['brand'], ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="description">説明</label>
                    <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                    <label for="category_id">カテゴリID（任意）</label>
                    <input id="category_id" name="category_id" type="text" value="<?php echo htmlspecialchars($form['category_id'], ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="status">状態</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo $form['status'] === 'active' ? 'selected' : ''; ?>>active</option>
                        <option value="inactive" <?php echo $form['status'] === 'inactive' ? 'selected' : ''; ?>>inactive</option>
                    </select>

                    <button class="button" type="submit">登録する</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
