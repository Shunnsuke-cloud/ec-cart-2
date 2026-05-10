<?php
$pageTitle = '商品管理';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
$pdo = require __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Admin/csv.php';

$errorMessage = '';
$noticeMessage = '';
$isCsvExport = isset($_GET['export']) && $_GET['export'] === 'csv';

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $noticeMessage = '商品を登録しました。';
}
if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $noticeMessage = '商品を更新しました。';
}
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $noticeMessage = '商品を削除しました。';
}

$products = [];

try {
    $sql = <<<'SQL'
SELECT
    p.id,
    p.name,
    p.slug,
    p.brand,
    p.status,
    p.category_id,
    p.created_at,
    p.updated_at,
    COALESCE(SUM(pv.stock), 0) AS total_stock,
    COUNT(DISTINCT pv.id) AS variant_count
FROM products p
LEFT JOIN product_variants pv ON pv.product_id = p.id
WHERE p.deleted_at IS NULL
GROUP BY p.id, p.name, p.slug, p.brand, p.status, p.category_id, p.created_at, p.updated_at
ORDER BY p.created_at DESC, p.id DESC
SQL;
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();

    if ($isCsvExport) {
        $csvRows = [];
        foreach ($products as $product) {
            $csvRows[] = [
                'id' => (string)$product['id'],
                'name' => (string)$product['name'],
                'slug' => (string)$product['slug'],
                'brand' => (string)($product['brand'] ?? ''),
                'status' => (string)$product['status'],
                'variant_count' => (string)$product['variant_count'],
                'total_stock' => (string)$product['total_stock'],
                'created_at' => (string)$product['created_at'],
                'updated_at' => (string)$product['updated_at'],
            ];
        }

        admin_output_csv(
            ['ID', '商品名', 'スラッグ', 'ブランド', '状態', 'SKU数', '在庫合計', '作成日時', '更新日時'],
            $csvRows,
            'products_' . date('Ymd_His') . '.csv'
        );
    }
} catch (Throwable $e) {
    $errorMessage = '商品一覧の取得に失敗しました。';
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
                <h2>商品管理</h2>

                <p class="product-actions">
                    <a class="button" href="new.php">新規商品を追加</a>
                    <a class="button" href="?export=csv">CSV出力</a>
                    <a class="button" href="../index.php">管理画面に戻る</a>
                </p>

                <?php if ($noticeMessage !== ''): ?>
                    <p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif (empty($products)): ?>
                    <p class="notice">登録済みの商品がありません。</p>
                <?php else: ?>
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>商品名</th>
                                <th>SKU数</th>
                                <th>在庫合計</th>
                                <th>状態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo (int)$product['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <small><?php echo htmlspecialchars((string)$product['slug'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td><?php echo (int)$product['variant_count']; ?></td>
                                    <td><?php echo number_format((int)$product['total_stock']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$product['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?php echo (int)$product['id']; ?>">編集</a>
                                        |
                                        <a href="delete.php?id=<?php echo (int)$product['id']; ?>">削除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
