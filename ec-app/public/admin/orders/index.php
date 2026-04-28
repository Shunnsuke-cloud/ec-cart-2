<?php
$pageTitle = '注文管理';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
require_once __DIR__ . '/../../../config/database.php';

$noticeMessage = '';
$errorMessage = '';

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $noticeMessage = '注文ステータスを更新しました。';
}

$orders = [];

try {
    $sql = <<<'SQL'
SELECT
    o.id,
    o.order_number,
    o.status,
    o.payment_status,
    o.shipping_status,
    o.subtotal,
    o.shipping_fee,
    o.discount_amount,
    o.tax_amount,
    o.total_amount,
    o.created_at,
    o.updated_at,
    u.name AS user_name,
    u.email AS user_email,
    COUNT(oi.id) AS item_count,
    COALESCE(SUM(oi.quantity), 0) AS total_quantity
FROM orders o
LEFT JOIN users u ON u.id = o.user_id
LEFT JOIN order_items oi ON oi.order_id = o.id
GROUP BY
    o.id,
    o.order_number,
    o.status,
    o.payment_status,
    o.shipping_status,
    o.subtotal,
    o.shipping_fee,
    o.discount_amount,
    o.tax_amount,
    o.total_amount,
    o.created_at,
    o.updated_at,
    u.name,
    u.email
ORDER BY o.created_at DESC, o.id DESC
SQL;
    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errorMessage = '注文一覧の取得に失敗しました。';
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
                <h2>注文管理</h2>
                <p class="product-actions">
                    <a class="button" href="../index.php">管理画面に戻る</a>
                </p>

                <?php if ($noticeMessage !== ''): ?>
                    <p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif (empty($orders)): ?>
                    <p class="notice">注文がありません。</p>
                <?php else: ?>
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>注文番号</th>
                                <th>購入者</th>
                                <th>点数</th>
                                <th>金額</th>
                                <th>支払</th>
                                <th>配送</th>
                                <th>状態</th>
                                <th>作成日時</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars((string)($order['user_name'] ?? 'ゲスト'), ENT_QUOTES, 'UTF-8'); ?><br>
                                        <small><?php echo htmlspecialchars((string)($order['user_email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td><?php echo (int)$order['item_count']; ?> / <?php echo (int)$order['total_quantity']; ?></td>
                                    <td><?php echo number_format((int)$order['total_amount']); ?>円</td>
                                    <td><?php echo htmlspecialchars((string)$order['payment_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)$order['shipping_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)$order['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)$order['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><a href="edit.php?id=<?php echo (int)$order['id']; ?>">変更</a></td>
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
