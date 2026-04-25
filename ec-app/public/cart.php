<?php
$pageTitle = 'カート';
$activePage = 'cart';
session_start();
require_once __DIR__ . '/../config/database.php';

$noticeMessage = '';
$errorMessage = '';
$cartItems = [];
$cartTotal = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? (string)$_POST['action'] : 'add';

	if ($action === 'update_quantity') {
		$cartItemId = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;
		$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
		if ($quantity < 1) {
			$quantity = 1;
		}

		if ($cartItemId <= 0) {
			$errorMessage = '更新対象のカート商品が不正です。';
		} else {
			$sessionId = session_id();

			try {
				$pdo->beginTransaction();

				$stmtTarget = $pdo->prepare(
					<<<'SQL'
SELECT
	ci.id,
	pv.stock
FROM carts c
INNER JOIN cart_items ci ON ci.cart_id = c.id
INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
WHERE c.session_id = :session_id
	AND c.user_id IS NULL
	AND ci.id = :cart_item_id
LIMIT 1
SQL
				);
				$stmtTarget->execute([
					'session_id' => $sessionId,
					'cart_item_id' => $cartItemId,
				]);
				$targetItem = $stmtTarget->fetch();

				if (!$targetItem) {
					throw new RuntimeException('更新対象の商品が見つかりません。');
				}

				if ($quantity > (int)$targetItem['stock']) {
					throw new RuntimeException('在庫数を超える数量には変更できません。');
				}

				$stmtUpdate = $pdo->prepare(
					'UPDATE cart_items SET quantity = :quantity WHERE id = :id'
				);
				$stmtUpdate->execute([
					'quantity' => $quantity,
					'id' => $cartItemId,
				]);

				$pdo->commit();
				header('Location: cart.php?updated=1');
				exit;
			} catch (Throwable $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$errorMessage = $e instanceof RuntimeException
					? $e->getMessage()
					: '数量変更に失敗しました。時間をおいて再度お試しください。';
			}
		}
	} elseif ($action === 'delete_item') {
		$cartItemId = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;

		if ($cartItemId <= 0) {
			$errorMessage = '削除対象のカート商品が不正です。';
		} else {
			$sessionId = session_id();

			try {
				$stmtDelete = $pdo->prepare(
					<<<'SQL'
DELETE ci
FROM cart_items ci
INNER JOIN carts c ON c.id = ci.cart_id
WHERE ci.id = :cart_item_id
	AND c.session_id = :session_id
	AND c.user_id IS NULL
SQL
				);
				$stmtDelete->execute([
					'cart_item_id' => $cartItemId,
					'session_id' => $sessionId,
				]);

				if ($stmtDelete->rowCount() < 1) {
					throw new RuntimeException('削除対象の商品が見つかりません。');
				}

				header('Location: cart.php?deleted=1');
				exit;
			} catch (Throwable $e) {
				$errorMessage = $e instanceof RuntimeException
					? $e->getMessage()
					: '商品削除に失敗しました。時間をおいて再度お試しください。';
			}
		}
	} else {
		$variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
		$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
		if ($quantity < 1) {
			$quantity = 1;
		}

		if ($variantId <= 0) {
			$errorMessage = '追加対象のSKUが不正です。';
		} else {
		$sessionId = session_id();

		try {
			$pdo->beginTransaction();

			$stmtVariant = $pdo->prepare(
				'SELECT id, price, stock FROM product_variants WHERE id = :id LIMIT 1'
			);
			$stmtVariant->execute(['id' => $variantId]);
			$variant = $stmtVariant->fetch();

			if (!$variant) {
				throw new RuntimeException('指定されたSKUが見つかりません。');
			}

			if ((int)$variant['stock'] <= 0) {
				throw new RuntimeException('在庫がないためカートに追加できません。');
			}

			if ($quantity > (int)$variant['stock']) {
				throw new RuntimeException('在庫数を超える数量は追加できません。');
			}

			$stmtCart = $pdo->prepare(
				'SELECT id FROM carts WHERE session_id = :session_id AND user_id IS NULL ORDER BY id DESC LIMIT 1'
			);
			$stmtCart->execute(['session_id' => $sessionId]);
			$cart = $stmtCart->fetch();

			if ($cart) {
				$cartId = (int)$cart['id'];
				$stmtTouchCart = $pdo->prepare('UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
				$stmtTouchCart->execute(['id' => $cartId]);
			} else {
				$stmtCreateCart = $pdo->prepare(
					'INSERT INTO carts (user_id, session_id) VALUES (NULL, :session_id)'
				);
				$stmtCreateCart->execute(['session_id' => $sessionId]);
				$cartId = (int)$pdo->lastInsertId();
			}

			$stmtCartItem = $pdo->prepare(
				'SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_variant_id = :variant_id LIMIT 1'
			);
			$stmtCartItem->execute([
				'cart_id' => $cartId,
				'variant_id' => $variantId,
			]);
			$cartItem = $stmtCartItem->fetch();

			if ($cartItem) {
				$newQuantity = (int)$cartItem['quantity'] + $quantity;
				if ($newQuantity > (int)$variant['stock']) {
					throw new RuntimeException('カート内の数量が在庫数を超えています。数量を調整してください。');
				}
				$stmtUpdateItem = $pdo->prepare(
					'UPDATE cart_items SET quantity = :quantity, price = :price WHERE id = :id'
				);
				$stmtUpdateItem->execute([
					'quantity' => $newQuantity,
					'price' => (int)$variant['price'],
					'id' => (int)$cartItem['id'],
				]);
			} else {
				$stmtInsertItem = $pdo->prepare(
					'INSERT INTO cart_items (cart_id, product_variant_id, quantity, price) VALUES (:cart_id, :variant_id, :quantity, :price)'
				);
				$stmtInsertItem->execute([
					'cart_id' => $cartId,
					'variant_id' => $variantId,
					'quantity' => $quantity,
					'price' => (int)$variant['price'],
				]);
			}

			$pdo->commit();
			header('Location: cart.php?added=1');
			exit;
		} catch (Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			$errorMessage = $e instanceof RuntimeException
				? $e->getMessage()
				: 'カート追加に失敗しました。時間をおいて再度お試しください。';
		}
		}
	}
}

if (isset($_GET['added']) && $_GET['added'] === '1') {
	$noticeMessage = '商品をカートに追加しました。';
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
	$noticeMessage = '数量を更新しました。';
}

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
	$noticeMessage = '商品をカートから削除しました。';
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
		$cartTotal += (int)$item['line_total'];
	}
} catch (Throwable $e) {
	$errorMessage = 'カート情報の取得に失敗しました。時間をおいて再度お試しください。';
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
	<h2>ショッピングカート</h2>

	<?php if ($noticeMessage !== ''): ?>
		<p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
	<?php endif; ?>

	<?php if ($errorMessage !== ''): ?>
		<p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
	<?php elseif (empty($cartItems)): ?>
		<p class="notice">カートに商品はありません。</p>
		<p class="product-actions"><a class="button" href="product.php">商品一覧を見る</a></p>
	<?php else: ?>
		<div class="cart-table-wrap">
			<table class="cart-table">
				<thead>
					<tr>
						<th>商品名</th>
						<th>SKU</th>
						<th>単価</th>
						<th>数量</th>
						<th>小計</th>
						<th>操作</th>
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
							<td>
								<form method="post" class="cart-qty-form">
									<input type="hidden" name="action" value="update_quantity">
									<input type="hidden" name="cart_item_id" value="<?php echo (int)$item['id']; ?>">
									<input type="number" name="quantity" min="1" value="<?php echo (int)$item['quantity']; ?>">
									<button type="submit" class="button">変更</button>
								</form>
							</td>
							<td><?php echo number_format((int)$item['line_total']); ?>円</td>
							<td>
								<form method="post" class="cart-delete-form" onsubmit="return confirm('この商品をカートから削除しますか？');">
									<input type="hidden" name="action" value="delete_item">
									<input type="hidden" name="cart_item_id" value="<?php echo (int)$item['id']; ?>">
									<button type="submit" class="button button-danger">削除</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th colspan="5">合計</th>
						<th><?php echo number_format($cartTotal); ?>円</th>
					</tr>
				</tfoot>
			</table>
		</div>
		<p class="product-actions"><a class="button" href="checkout.php">購入手続きへ進む</a></p>
	<?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
