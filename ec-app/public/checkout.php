<?php
$pageTitle = '購入手続き';
$activePage = 'checkout';
session_start();
require_once __DIR__ . '/../config/database.php';

$errorMessage = '';
$cartItems = [];

$subtotal = 0;
$shippingFee = 0;
$taxAmount = 0;
$totalAmount = 0;

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
				<button class="button" type="button" disabled>注文を確定する（次対応）</button>
			</aside>
		</div>
	<?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
