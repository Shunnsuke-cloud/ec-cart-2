<?php
$pageTitle = '購入手続き';
$activePage = 'checkout';
require_once __DIR__ . '/../app/Auth/session.php';
app_session_start();
require_once __DIR__ . '/../config/database.php';

$errorMessage = '';
$noticeMessage = '';
$cartItems = [];

$subtotal = 0;
$shippingFee = 0;
$taxAmount = 0;
$totalAmount = 0;

/**
 * 未ログイン購入に備えて、注文保存に使うユーザーIDを解決します。
 */
function resolveOrderUserId(PDO $pdo): int
{
	if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
		return (int)$_SESSION['user_id'];
	}

	$guestEmail = 'guest-order@example.local';
	$stmtUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
	$stmtUser->execute(['email' => $guestEmail]);
	$user = $stmtUser->fetch();

	if ($user) {
		return (int)$user['id'];
	}

	$randomPassword = bin2hex(random_bytes(16));
	$stmtCreateUser = $pdo->prepare(
		<<<'SQL'
INSERT INTO users (name, email, password, status)
VALUES (:name, :email, :password, :status)
SQL
	);
	$stmtCreateUser->execute([
		'name' => 'ゲスト購入者',
		'email' => $guestEmail,
		'password' => password_hash($randomPassword, PASSWORD_BCRYPT),
		'status' => 'active',
	]);

	return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place_order') {
	$sessionId = session_id();

	try {
		$pdo->beginTransaction();

		$stmtCart = $pdo->prepare(
			'SELECT id FROM carts WHERE session_id = :session_id AND user_id IS NULL ORDER BY id DESC LIMIT 1'
		);
		$stmtCart->execute(['session_id' => $sessionId]);
		$cart = $stmtCart->fetch();

		if (!$cart) {
			throw new RuntimeException('注文対象のカートが見つかりません。');
		}

		$cartId = (int)$cart['id'];
		$stmtCartItems = $pdo->prepare(
			<<<'SQL'
SELECT
	ci.id,
	ci.quantity,
	ci.price,
	pv.id AS variant_id,
	pv.sku,
	pv.stock,
	p.id AS product_id,
	p.name AS product_name,
	(ci.quantity * ci.price) AS line_total
FROM cart_items ci
INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
INNER JOIN products p ON p.id = pv.product_id
WHERE ci.cart_id = :cart_id
ORDER BY ci.id ASC
SQL
		);
		$stmtCartItems->execute(['cart_id' => $cartId]);
		$orderCartItems = $stmtCartItems->fetchAll();

		if (empty($orderCartItems)) {
			throw new RuntimeException('カートに商品がありません。');
		}

		$orderSubtotal = 0;
		foreach ($orderCartItems as $item) {
			if ((int)$item['quantity'] > (int)$item['stock']) {
				throw new RuntimeException('在庫不足の商品があるため注文を確定できません。');
			}
			$orderSubtotal += (int)$item['line_total'];
		}

		$orderShippingFee = $orderSubtotal >= 5000 ? 0 : 700;
		$orderTaxAmount = (int)floor($orderSubtotal * 0.10);
		$orderTotalAmount = $orderSubtotal + $orderShippingFee + $orderTaxAmount;

		$userId = resolveOrderUserId($pdo);
		$orderNumber = 'ORD' . date('YmdHis') . sprintf('%04d', random_int(0, 9999));

		$stmtOrder = $pdo->prepare(
			<<<'SQL'
INSERT INTO orders (
	user_id,
	order_number,
	status,
	subtotal,
	shipping_fee,
	discount_amount,
	tax_amount,
	total_amount,
	payment_status,
	shipping_status
) VALUES (
	:user_id,
	:order_number,
	:status,
	:subtotal,
	:shipping_fee,
	:discount_amount,
	:tax_amount,
	:total_amount,
	:payment_status,
	:shipping_status
)
SQL
		);
		$stmtOrder->execute([
			'user_id' => $userId,
			'order_number' => $orderNumber,
			'status' => 'pending',
			'subtotal' => $orderSubtotal,
			'shipping_fee' => $orderShippingFee,
			'discount_amount' => 0,
			'tax_amount' => $orderTaxAmount,
			'total_amount' => $orderTotalAmount,
			'payment_status' => 'pending',
			'shipping_status' => 'preparing',
		]);
		$orderId = (int)$pdo->lastInsertId();

		$stmtOrderItem = $pdo->prepare(
			<<<'SQL'
INSERT INTO order_items (
	order_id,
	product_id,
	product_variant_id,
	product_name,
	sku,
	unit_price,
	quantity,
	subtotal
) VALUES (
	:order_id,
	:product_id,
	:product_variant_id,
	:product_name,
	:sku,
	:unit_price,
	:quantity,
	:subtotal
)
SQL
		);

		$stmtStockUpdate = $pdo->prepare(
			'UPDATE product_variants SET stock = stock - :quantity WHERE id = :variant_id AND stock >= :quantity'
		);

		foreach ($orderCartItems as $item) {
			$stmtOrderItem->execute([
				'order_id' => $orderId,
				'product_id' => (int)$item['product_id'],
				'product_variant_id' => (int)$item['variant_id'],
				'product_name' => (string)$item['product_name'],
				'sku' => (string)$item['sku'],
				'unit_price' => (int)$item['price'],
				'quantity' => (int)$item['quantity'],
				'subtotal' => (int)$item['line_total'],
			]);

			$stmtStockUpdate->execute([
				'quantity' => (int)$item['quantity'],
				'variant_id' => (int)$item['variant_id'],
			]);

			if ($stmtStockUpdate->rowCount() < 1) {
				throw new RuntimeException('在庫更新に失敗しました。時間をおいて再度お試しください。');
			}
		}

		$stmtPayment = $pdo->prepare(
			<<<'SQL'
INSERT INTO payments (
	order_id,
	payment_method,
	amount,
	status,
	provider
) VALUES (
	:order_id,
	:payment_method,
	:amount,
	:status,
	:provider
)
SQL
		);
		$stmtPayment->execute([
			'order_id' => $orderId,
			'payment_method' => 'credit_card',
			'amount' => $orderTotalAmount,
			'status' => 'pending',
			'provider' => 'PAY.JP',
		]);

		$stmtClearCart = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
		$stmtClearCart->execute(['cart_id' => $cartId]);

		$pdo->commit();
		header('Location: checkout.php?ordered=1&order_no=' . urlencode($orderNumber));
		exit;
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		$errorMessage = $e instanceof RuntimeException
			? $e->getMessage()
			: '注文確定に失敗しました。時間をおいて再度お試しください。';
	}
}

if (isset($_GET['ordered']) && $_GET['ordered'] === '1') {
	$orderNo = isset($_GET['order_no']) ? (string)$_GET['order_no'] : '';
	if ($orderNo !== '') {
		$noticeMessage = '注文を受け付けました。注文番号: ' . $orderNo;
	}
}

try {
	$sessionId = session_id();
	$stmtItems = $pdo->prepare(
		<<<'SQL'
SELECT
	ci.id,
	ci.quantity,
	ci.price,
	pv.sku,
	pv.color,
	pv.size,
	pv.id AS product_variant_id,
	p.slug,
	p.name AS product_name,
	(ci.quantity * ci.price) AS line_total
FROM carts c
INNER JOIN cart_items ci ON ci.cart_id = c.id
INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
INNER JOIN products p ON p.id = pv.product_id
WHERE c.session_id = :session_id
	AND c.user_id IS NULL
ORDER BY ci.id DESC
SQL
	);
	$stmtItems->execute(['session_id' => $sessionId]);
	$cartItems = $stmtItems->fetchAll();

	foreach ($cartItems as $item) {
		$subtotal += (int)$item['line_total'];
	}

	$shippingFee = $subtotal >= 5000 ? 0 : 700;
	$taxAmount = (int)floor($subtotal * 0.10);
	$totalAmount = $subtotal + $shippingFee + $taxAmount;
} catch (Throwable $e) {
	$errorMessage = '注文確認情報の取得に失敗しました。時間をおいて再度お試しください。';
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
	<h2>注文確認</h2>

	<?php if ($noticeMessage !== ''): ?>
		<p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
	<?php endif; ?>

	<?php if ($errorMessage !== ''): ?>
		<p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
		<p class="product-actions"><a class="button" href="cart.php">カートへ戻る</a></p>
	<?php elseif (empty($cartItems)): ?>
		<p class="notice">カートに商品がありません。先に商品を追加してください。</p>
		<p class="product-actions"><a class="button" href="product.php">商品一覧を見る</a></p>
	<?php else: ?>
		<div class="checkout-layout">
			<div class="checkout-items">
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
						<?php foreach ($cartItems as $item): ?>
							<tr>
								<td>
									<a href="product_detail.php?slug=<?php echo urlencode((string)$item['slug']); ?>">
										<?php echo htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8'); ?>
									</a>
									<div class="cart-meta">
										色: <?php echo htmlspecialchars((string)($item['color'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> /
										サイズ: <?php echo htmlspecialchars((string)($item['size'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
									</div>
								</td>
								<td><?php echo htmlspecialchars((string)$item['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo number_format((int)$item['price']); ?>円</td>
								<td><?php echo (int)$item['quantity']; ?></td>
								<td><?php echo number_format((int)$item['line_total']); ?>円</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<aside class="order-summary">
				<h3>お支払い合計</h3>
				<dl>
					<div>
						<dt>小計</dt>
						<dd><?php echo number_format($subtotal); ?>円</dd>
					</div>
					<div>
						<dt>送料</dt>
						<dd><?php echo number_format($shippingFee); ?>円</dd>
					</div>
					<div>
						<dt>消費税 (10%)</dt>
						<dd><?php echo number_format($taxAmount); ?>円</dd>
					</div>
					<div class="summary-total">
						<dt>合計金額</dt>
						<dd><?php echo number_format($totalAmount); ?>円</dd>
					</div>
				</dl>

				<p class="summary-note">※ 小計5,000円以上で送料無料です。</p>
				<p class="product-actions"><a class="button" href="cart.php">カートへ戻る</a></p>
				<form method="post" class="order-submit-form" onsubmit="return confirm('この内容で注文を確定しますか？');">
					<input type="hidden" name="action" value="place_order">
					<button class="button" type="submit">注文を確定する</button>
				</form>
			</aside>
		</div>
	<?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
