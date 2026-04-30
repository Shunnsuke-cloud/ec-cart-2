<?php
$pageTitle = '商品削除';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
$pdo = require __DIR__ . '/../../../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMessage = '';
$product = null;

if ($id <= 0) {
    $errorMessage = '商品が指定されていません。';
} else {
    try {
        $stmt = $pdo->prepare('SELECT id, name, slug FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new RuntimeException('商品が見つかりません。');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmtDelete = $pdo->prepare('UPDATE products SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmtDelete->execute(['id' => $id]);
            header('Location: index.php?deleted=1');
            exit;
        }
    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException ? $e->getMessage() : '商品の削除に失敗しました。';
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
                <h2>商品削除</h2>
                <p class="product-actions"><a class="button" href="index.php">一覧へ戻る</a></p>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif ($product !== null): ?>
                    <p class="notice">以下の商品を削除します。よろしいですか？</p>
                    <ul>
                        <li>ID: <?php echo (int)$product['id']; ?></li>
                        <li>商品名: <?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?></li>
                        <li>slug: <?php echo htmlspecialchars((string)$product['slug'], ENT_QUOTES, 'UTF-8'); ?></li>
                    </ul>
                    <form method="post">
                        <button class="button" type="submit">削除する</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
