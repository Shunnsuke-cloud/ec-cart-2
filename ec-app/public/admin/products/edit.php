<?php
$pageTitle = '商品編集';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
$pdo = require __DIR__ . '/../../../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMessage = '';
$form = [
    'name' => '',
    'slug' => '',
    'brand' => '',
    'description' => '',
    'category_id' => '',
    'status' => 'active',
];

if ($id <= 0) {
    $errorMessage = '商品が指定されていません。';
} else {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form['name'] = trim((string)($_POST['name'] ?? ''));
            $form['slug'] = trim((string)($_POST['slug'] ?? ''));
            $form['brand'] = trim((string)($_POST['brand'] ?? ''));
            $form['description'] = trim((string)($_POST['description'] ?? ''));
            $form['category_id'] = trim((string)($_POST['category_id'] ?? ''));
            $form['status'] = trim((string)($_POST['status'] ?? 'active'));

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

            $stmtExists = $pdo->prepare('SELECT id FROM products WHERE slug = :slug AND id <> :id LIMIT 1');
            $stmtExists->execute(['slug' => $form['slug'], 'id' => $id]);
            if ($stmtExists->fetch()) {
                throw new RuntimeException('そのslugは既に使用されています。');
            }

            $stmtUpdate = $pdo->prepare(
                <<<'SQL'
UPDATE products
SET name = :name,
    slug = :slug,
    description = :description,
    brand = :brand,
    category_id = :category_id,
    status = :status,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
SQL
            );
            $stmtUpdate->execute([
                'name' => $form['name'],
                'slug' => $form['slug'],
                'description' => $form['description'] !== '' ? $form['description'] : null,
                'brand' => $form['brand'] !== '' ? $form['brand'] : null,
                'category_id' => $form['category_id'] !== '' ? (int)$form['category_id'] : null,
                'status' => $form['status'],
                'id' => $id,
            ]);

            header('Location: index.php?updated=1');
            exit;
        }

        $stmt = $pdo->prepare('SELECT id, name, slug, description, brand, category_id, status FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new RuntimeException('商品が見つかりません。');
        }

        $form['name'] = (string)$product['name'];
        $form['slug'] = (string)$product['slug'];
        $form['brand'] = (string)($product['brand'] ?? '');
        $form['description'] = (string)($product['description'] ?? '');
        $form['category_id'] = (string)($product['category_id'] ?? '');
        $form['status'] = (string)$product['status'];
    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException ? $e->getMessage() : '商品の取得・更新に失敗しました。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | EC Cart Admin</title>
    <base href="/cart-system/">
    <link rel="stylesheet" href="css/common.css">
</head>
<body>
    <main class="site-main">
        <div class="container">
            <section>
                <h2>商品編集</h2>
                <p class="product-actions"><a class="button" href="index.php">一覧へ戻る</a></p>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php else: ?>
                    <form method="post" class="auth-form" novalidate>
                        <label for="name">商品名</label>
                        <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>">

                        <label for="slug">slug</label>
                        <input id="slug" name="slug" type="text" required value="<?php echo htmlspecialchars($form['slug'], ENT_QUOTES, 'UTF-8'); ?>">

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

                        <button class="button" type="submit">更新する</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
