<?php
$pageTitle = '商品詳細';
$activePage = 'product';

require_once __DIR__ . '/../config/database.php';

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$product = null;
$variants = [];
$productImages = [];
$errorMessage = '';

if ($slug === '') {
    $errorMessage = '商品が指定されていません。';
} else {
    try {
        $sqlProduct = <<<'SQL'
SELECT
    p.id,
    p.name,
    p.slug,
    p.description,
    p.brand,
    c.name AS category_name,
    MAX(CASE WHEN pi.is_main = 1 THEN pi.image_path END) AS main_image
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
LEFT JOIN product_images pi ON pi.product_id = p.id
WHERE p.slug = :slug
  AND p.status = 'active'
  AND p.deleted_at IS NULL
GROUP BY p.id, p.name, p.slug, p.description, p.brand, c.name
LIMIT 1
SQL;

        $stmtProduct = $pdo->prepare($sqlProduct);
        $stmtProduct->execute(['slug' => $slug]);
        $product = $stmtProduct->fetch();

        if (!$product) {
            $errorMessage = '指定された商品が見つかりません。';
        } else {
            $sqlVariants = <<<'SQL'
SELECT
    id,
    sku,
    color,
    size,
    price,
    stock
FROM product_variants
WHERE product_id = :product_id
ORDER BY color ASC, size ASC, id ASC
SQL;
            $stmtVariants = $pdo->prepare($sqlVariants);
            $stmtVariants->execute(['product_id' => (int)$product['id']]);
            $variants = $stmtVariants->fetchAll();

            $sqlImages = <<<'SQL'
SELECT image_path
FROM product_images
WHERE product_id = :product_id
ORDER BY is_main DESC, sort_order ASC, id ASC
SQL;
            $stmtImages = $pdo->prepare($sqlImages);
            $stmtImages->execute(['product_id' => (int)$product['id']]);
            $productImages = $stmtImages->fetchAll();
        }
    } catch (Throwable $e) {
        $errorMessage = '商品詳細の取得に失敗しました。時間をおいて再度お試しください。';
    }
}

$colors = [];
$sizes = [];
foreach ($variants as $variant) {
    $color = trim((string)($variant['color'] ?? ''));
    $size = trim((string)($variant['size'] ?? ''));

    if ($color !== '' && !in_array($color, $colors, true)) {
        $colors[] = $color;
    }

    if ($size !== '' && !in_array($size, $sizes, true)) {
        $sizes[] = $size;
    }
}

$selectedColor = isset($_GET['color']) ? trim((string)$_GET['color']) : ($colors[0] ?? '');
$selectedSize = isset($_GET['size']) ? trim((string)$_GET['size']) : ($sizes[0] ?? '');
$selectedVariant = null;

foreach ($variants as $variant) {
    $variantColor = trim((string)($variant['color'] ?? ''));
    $variantSize = trim((string)($variant['size'] ?? ''));

    if (($selectedColor === '' || $variantColor === $selectedColor)
        && ($selectedSize === '' || $variantSize === $selectedSize)) {
        $selectedVariant = $variant;
        break;
    }
}

if ($selectedVariant === null && !empty($variants)) {
    $selectedVariant = $variants[0];
    $selectedColor = trim((string)($selectedVariant['color'] ?? ''));
    $selectedSize = trim((string)($selectedVariant['size'] ?? ''));
}

if ($product) {
    $pageTitle = (string)$product['name'];
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
    <h2>商品詳細</h2>

    <?php if ($errorMessage !== ''): ?>
        <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="product-actions"><a class="button" href="product.php">商品一覧へ戻る</a></p>
    <?php elseif ($product !== null): ?>
        <div class="product-detail">
            <div class="product-detail-media">
                <?php if (!empty($product['main_image'])): ?>
                    <img class="detail-main-image" src="<?php echo htmlspecialchars((string)$product['main_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <div class="detail-main-image placeholder">NO IMAGE</div>
                <?php endif; ?>

                <?php if (!empty($productImages)): ?>
                    <div class="detail-thumbs">
                        <?php foreach ($productImages as $image): ?>
                            <img src="<?php echo htmlspecialchars((string)$image['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?>サムネイル">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-detail-info">
                <p class="product-brand"><?php echo htmlspecialchars((string)($product['brand'] ?? 'BRAND'), ENT_QUOTES, 'UTF-8'); ?></p>
                <h3 class="detail-title"><?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="detail-category">カテゴリ: <?php echo htmlspecialchars((string)($product['category_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="product-description"><?php echo htmlspecialchars((string)($product['description'] ?? '説明は準備中です。'), ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if (!empty($variants)): ?>
                    <form method="get" class="sku-selector">
                        <input type="hidden" name="slug" value="<?php echo htmlspecialchars((string)$product['slug'], ENT_QUOTES, 'UTF-8'); ?>">

                        <?php if (!empty($colors)): ?>
                            <label for="color">色</label>
                            <select id="color" name="color">
                                <?php foreach ($colors as $color): ?>
                                    <option value="<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedColor === $color ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if (!empty($sizes)): ?>
                            <label for="size">サイズ</label>
                            <select id="size" name="size">
                                <?php foreach ($sizes as $size): ?>
                                    <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedSize === $size ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <button class="button" type="submit">SKUを選択</button>
                    </form>

                    <?php if ($selectedVariant !== null): ?>
                        <div class="selected-sku">
                            <p>SKU: <?php echo htmlspecialchars((string)$selectedVariant['sku'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>価格: <?php echo number_format((int)$selectedVariant['price']); ?>円</p>
                            <p class="stock-status <?php echo ((int)$selectedVariant['stock'] > 0) ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo ((int)$selectedVariant['stock'] > 0) ? '在庫あり' : '在庫なし'; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="notice">この商品にはSKU情報がありません。</p>
                <?php endif; ?>

                <p class="product-actions"><a class="button" href="product.php">商品一覧へ戻る</a></p>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
