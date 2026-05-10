<?php
$pageTitle = '注文管理';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
$pdo = require __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Admin/csv.php';

$noticeMessage = '';
$errorMessage = '';
$selectedOrderIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_shipping_csv') {
    $selectedOrderIds = array_values(array_filter(array_map('intval', (array)($_POST['order_ids'] ?? [])), static function (int $orderId): bool {
        return $orderId > 0;
    }));

    if (empty($selectedOrderIds)) {
        $errorMessage = 'CSV出力する注文を1件以上選択してください。';
    }
}

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
    o.tracking_number,
    o.shipped_at,
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
    o.tracking_number,
    o.shipped_at,
    o.tracking_number,
    o.shipped_at,
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

    if (!empty($selectedOrderIds)) {
        $placeholders = [];
        foreach ($selectedOrderIds as $index => $orderId) {
            $placeholders[] = ':order_id_' . $index;
        }

        $exportSql = '
SELECT
    o.id,
    o.order_number,
    COALESCE(a.recipient_name, u.name, \'\') AS recipient_name,
    COALESCE(a.postal_code, \'\') AS postal_code,
    CONCAT(
        COALESCE(a.prefecture, \'\'),
        COALESCE(a.city, \'\'),
        COALESCE(a.address_line1, \'\'),
        CASE
            WHEN a.address_line2 IS NULL OR a.address_line2 = \'\' THEN \'\'
            ELSE CONCAT(\' \' , a.address_line2)
        END
    ) AS shipping_address,
    COALESCE(a.phone, u.phone, \'\') AS phone,
    COALESCE(GROUP_CONCAT(oi.product_name ORDER BY oi.id SEPARATOR \'／\'), \'\') AS product_name
FROM orders o
LEFT JOIN users u ON u.id = o.user_id
LEFT JOIN addresses a ON a.user_id = u.id AND a.is_default = 1
LEFT JOIN order_items oi ON oi.order_id = o.id
WHERE o.id IN (' . implode(',', $placeholders) . ')
GROUP BY
    o.id,
    o.order_number,
    a.recipient_name,
    a.postal_code,
    a.prefecture,
    a.city,
    a.address_line1,
    a.address_line2,
    a.phone,
    u.name,
    u.phone
ORDER BY o.created_at ASC, o.id ASC
';

        $stmtExport = $pdo->prepare($exportSql);
        foreach ($selectedOrderIds as $index => $orderId) {
            $stmtExport->bindValue(':order_id_' . $index, $orderId, PDO::PARAM_INT);
        }
        $stmtExport->execute();
        $exportRows = $stmtExport->fetchAll();

        if (empty($exportRows)) {
            $errorMessage = '選択した注文のCSV出力対象が見つかりませんでした。';
        } else {
            $csvRows = [];
            foreach ($exportRows as $row) {
                $csvRows[] = [
                    'recipient_name' => (string)$row['recipient_name'],
                    'postal_code' => (string)$row['postal_code'],
                    'shipping_address' => (string)$row['shipping_address'],
                    'phone' => (string)$row['phone'],
                    'product_name' => (string)$row['product_name'],
                ];
            }

            admin_output_csv(
                ['お届け先氏名', '郵便番号', '住所', '電話番号', '商品名'],
                $csvRows,
                'sagawa_ehiden_' . date('Ymd_His') . '.csv'
            );
        }
    }
} catch (Throwable $e) {
    $errorMessage = $errorMessage !== '' ? $errorMessage : '注文一覧の取得に失敗しました。';
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
                <h2>注文管理</h2>
                <p class="product-actions">
                    <a class="button" href="../index.php">管理画面に戻る</a>
                </p>

                <?php if ($noticeMessage !== ''): ?>
                    <p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if (empty($orders)): ?>
                    <p class="notice">注文がありません。</p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="action" value="export_shipping_csv">
                        <p class="product-actions">
                            <button class="button" type="submit">佐川e飛伝CSV出力</button>
                        </p>
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>選択</th>
                                    <th>注文番号</th>
                                    <th>購入者</th>
                                    <th>点数</th>
                                    <th>金額</th>
                                    <th>支払</th>
                                    <th>配送</th>
                                    <th>追跡番号</th>
                                    <th>発送日時</th>
                                    <th>状態</th>
                                    <th>作成日時</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <input
                                                type="checkbox"
                                                name="order_ids[]"
                                                value="<?php echo (int)$order['id']; ?>"
                                                <?php echo in_array((int)$order['id'], $selectedOrderIds, true) ? 'checked' : ''; ?>
                                            >
                                        </td>
                                        <td><?php echo htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars((string)($order['user_name'] ?? 'ゲスト'), ENT_QUOTES, 'UTF-8'); ?><br>
                                            <small><?php echo htmlspecialchars((string)($order['user_email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td><?php echo (int)$order['item_count']; ?> / <?php echo (int)$order['total_quantity']; ?></td>
                                        <td><?php echo number_format((int)$order['total_amount']); ?>円</td>
                                        <td><?php echo htmlspecialchars((string)$order['payment_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$order['shipping_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($order['tracking_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($order['shipped_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$order['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$order['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><a href="edit.php?id=<?php echo (int)$order['id']; ?>">変更</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
