<?php
$pageTitle = '注文ステータス変更';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
$pdo = require __DIR__ . '/../../../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMessage = '';
$formErrorMessage = '';
$order = null;
$orderItems = [];

$orderStatuses = [
    'confirmed' => '確定',
    'processing' => '処理中',
    'completed' => '完了',
    'cancelled' => 'キャンセル',
];
$paymentStatuses = [
    'pending' => '未決済',
    'paid' => '支払済み',
    'refunded' => '返金済み',
    'failed' => '失敗',
];
$shippingStatuses = [
    'preparing' => '準備中',
    'packed' => '梱包済み',
    'shipped' => '発送済み',
    'delivered' => '配達済み',
    'cancelled' => 'キャンセル',
];

$form = [
    'status' => '',
    'payment_status' => '',
    'shipping_status' => '',
    'tracking_number' => '',
    'shipped_at' => '',
];

if ($id <= 0) {
    $errorMessage = '注文が指定されていません。';
} else {
    try {
        $stmt = $pdo->prepare(
            <<<'SQL'
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
    u.name AS user_name,
    u.email AS user_email
FROM orders o
LEFT JOIN users u ON u.id = o.user_id
WHERE o.id = :id
LIMIT 1
SQL
        );
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new RuntimeException('注文が見つかりません。');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form['status'] = trim((string)($_POST['status'] ?? ''));
            $form['payment_status'] = trim((string)($_POST['payment_status'] ?? ''));
            $form['shipping_status'] = trim((string)($_POST['shipping_status'] ?? ''));

            if (!array_key_exists($form['status'], $orderStatuses)) {
                throw new RuntimeException('注文状態の値が不正です。');
            }
            if (!array_key_exists($form['payment_status'], $paymentStatuses)) {
                throw new RuntimeException('支払状態の値が不正です。');
            }
            if (!array_key_exists($form['shipping_status'], $shippingStatuses)) {
                throw new RuntimeException('配送状態の値が不正です。');
            }
               $form['tracking_number'] = trim((string)($_POST['tracking_number'] ?? ''));
               $shipped_at_input = trim((string)($_POST['shipped_at'] ?? ''));

            $stmtUpdate = $pdo->prepare(
                <<<'SQL'
UPDATE orders
SET status = :status,
    payment_status = :payment_status,
    shipping_status = :shipping_status,
       tracking_number = :tracking_number,
       shipped_at = :shipped_at,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
SQL
            );
            $stmtUpdate->execute([
                'status' => $form['status'],
                'payment_status' => $form['payment_status'],
                'shipping_status' => $form['shipping_status'],
                'id' => $id,
                   'tracking_number' => $form['tracking_number'] !== '' ? $form['tracking_number'] : null,
                   'shipped_at' => $shipped_at_input !== '' ? $shipped_at_input : null,
            ]);

            header('Location: index.php?updated=1');
            exit;
        }

        $form['status'] = (string)$order['status'];
        $form['payment_status'] = (string)$order['payment_status'];
        $form['shipping_status'] = (string)$order['shipping_status'];
        $form['tracking_number'] = (string)($order['tracking_number'] ?? '');
        if (!empty($order['shipped_at'])) {
            // convert to datetime-local format (YYYY-MM-DDTHH:MM)
            $form['shipped_at'] = str_replace(' ', 'T', substr($order['shipped_at'], 0, 16));
        }

        $stmtItems = $pdo->prepare(
            <<<'SQL'
SELECT
    product_name,
    sku,
    unit_price,
    quantity,
    subtotal
FROM order_items
WHERE order_id = :order_id
ORDER BY id ASC
SQL
        );
        $stmtItems->execute(['order_id' => $id]);
        $orderItems = $stmtItems->fetchAll();
    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException ? $e->getMessage() : '注文情報の取得・更新に失敗しました。';
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
                <h2>注文ステータス変更</h2>
                <p class="product-actions">
                    <a class="button" href="index.php">一覧へ戻る</a>
                </p>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif ($order !== null): ?>
                    <div class="order-summary">
                        <h3><?php echo htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p>購入者: <?php echo htmlspecialchars((string)($order['user_name'] ?? 'ゲスト'), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string)($order['user_email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>合計: <?php echo number_format((int)$order['total_amount']); ?>円</p>
                        <p>作成日時: <?php echo htmlspecialchars((string)$order['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <?php if (!empty($orderItems)): ?>
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>商品名</th>
                                    <th>SKU</th>
                                    <th>単価</th>
                                    <th>数量</th>
                                    <th>小計</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((int)$item['unit_price']); ?>円</td>
                                        <td><?php echo (int)$item['quantity']; ?></td>
                                        <td><?php echo number_format((int)$item['subtotal']); ?>円</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ($formErrorMessage !== ''): ?>
                        <p class="notice error"><?php echo htmlspecialchars($formErrorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <form method="post" class="auth-form" novalidate>
                        <label for="status">注文状態</label>
                        <select id="status" name="status">
                            <?php foreach ($orderStatuses as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['status'] === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="payment_status">支払状態</label>
                        <select id="payment_status" name="payment_status">
                            <?php foreach ($paymentStatuses as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['payment_status'] === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="shipping_status">配送状態</label>
                        <select id="shipping_status" name="shipping_status">
                            <?php foreach ($shippingStatuses as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['shipping_status'] === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                           <label for="tracking_number">追跡番号</label>
                           <input type="text" id="tracking_number" name="tracking_number" value="<?php echo htmlspecialchars($form['tracking_number'], ENT_QUOTES, 'UTF-8'); ?>">
                           <label for="shipped_at">発送日時</label>
                           <input type="datetime-local" id="shipped_at" name="shipped_at" value="<?php echo htmlspecialchars($form['shipped_at'], ENT_QUOTES, 'UTF-8'); ?>">

                        <button class="button" type="submit">更新する</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
