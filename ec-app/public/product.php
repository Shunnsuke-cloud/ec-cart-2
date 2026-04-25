<?php
$pageTitle = '商品一覧';
$activePage = 'product';
require_once __DIR__ . '/../config/database.php';

$products = [];
$errorMessage = '';

try {
    $sql = <<<'SQL'
SELECT
    p.id,
    p.name,
    p.slug,
    p.description,
    p.brand,
    COALESCE(MIN(pv.price), 0) AS min_price,
    COALESCE(MAX(pv.price), 0) AS max_price,
    COALESCE(SUM(pv.stock), 0) AS total_stock,
    MAX(CASE WHEN pi.is_main = 1 THEN pi.image_path END) AS main_image
FROM products p
LEFT JOIN product_variants pv ON pv.product_id = p.id
LEFT JOIN product_images pi ON pi.product_id = p.id
WHERE p.status = 'active'
  AND p.deleted_at IS NULL
GROUP BY p.id, p.name, p.slug, p.description, p.brand
ORDER BY p.created_at DESC, p.id DESC
SQL;

    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $errorMessage = '商品一覧の取得に失敗しました。時間をおいて再度お試しください。';
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
	<h2>商品一覧</h2>

	<?php if ($errorMessage !== ''): ?>
		<p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
	<?php elseif (empty($products)): ?>
		<p class="notice">現在表示できる商品がありません。</p>
	<?php else: ?>
		<div class="product-grid">
			<?php foreach ($products as $product): ?>
				<?php
				$description = trim((string)($product['description'] ?? ''));
				if ($description === '') {
					$description = '説明は準備中です。';
				}
				$priceText = ($product['min_price'] > 0)
					? ((int)$product['min_price'] === (int)$product['max_price']
						? number_format((int)$product['min_price']) . '円'
						: number_format((int)$product['min_price']) . '円 - ' . number_format((int)$product['max_price']) . '円')
					: '価格未設定';
				$isInStock = (int)$product['total_stock'] > 0;
				?>
				<article class="product-card">
					<div class="product-card-image-wrap">
						<?php if (!empty($product['main_image'])): ?>
							<a href="product_detail.php?slug=<?php echo urlencode((string)$product['slug']); ?>">
								<img class="product-card-image" src="<?php echo htmlspecialchars((string)$product['main_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?>">
							</a>
						<?php else: ?>
							<div class="product-card-image placeholder">NO IMAGE</div>
						<?php endif; ?>
					</div>
					<div class="product-card-body">
						<p class="product-brand"><?php echo htmlspecialchars((string)($product['brand'] ?? 'BRAND'), ENT_QUOTES, 'UTF-8'); ?></p>
						<h3><?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
						<p class="product-description"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
						<p class="product-price"><?php echo htmlspecialchars($priceText, ENT_QUOTES, 'UTF-8'); ?></p>
						<p class="stock-status <?php echo $isInStock ? 'in-stock' : 'out-of-stock'; ?>">
							<?php echo $isInStock ? '在庫あり' : '在庫なし'; ?>
						</p>
						<p class="product-actions">
							<a class="button" href="product_detail.php?slug=<?php echo urlencode((string)$product['slug']); ?>">商品詳細を見る</a>
						</p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
