<?php
$pageTitle = '商品詳細';
$activePage = 'product';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Auth/session.php';
app_session_start();

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$product = null;
$variants = [];
$productImages = [];
$errorMessage = '';
$reviewMessage = '';
$reviews = [];
$averageRating = 0;
$totalReviews = 0;

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
            // Load reviews
            $sqlReviewStats = <<<'SQL'
SELECT
    COUNT(id) AS total,
    ROUND(AVG(rating), 1) AS average_rating
FROM reviews
WHERE product_id = :product_id AND status = 'approved'
SQL;
            $stmtReviewStats = $pdo->prepare($sqlReviewStats);
            $stmtReviewStats->execute(['product_id' => (int)$product['id']]);
            $reviewStats = $stmtReviewStats->fetch();
            $totalReviews = (int)($reviewStats['total'] ?? 0);
            $averageRating = (float)($reviewStats['average_rating'] ?? 0);

            $sqlReviews = <<<'SQL'
SELECT
    r.id,
    r.rating,
    r.title,
    r.comment,
    r.created_at,
    u.name AS user_name
FROM reviews r
LEFT JOIN users u ON u.id = r.user_id
WHERE r.product_id = :product_id AND r.status = 'approved'
ORDER BY r.created_at DESC
LIMIT 10
SQL;
            $stmtReviews = $pdo->prepare($sqlReviews);
            $stmtReviews->execute(['product_id' => (int)$product['id']]);
            $reviews = $stmtReviews->fetchAll();

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

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_review') {
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
    $comment = isset($_POST['comment']) ? trim((string)$_POST['comment']) : '';

    if ($product && (int)$product['id'] === $productId && $rating >= 1 && $rating <= 5) {
        try {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

            $sqlInsertReview = <<<'SQL'
INSERT INTO reviews (product_id, user_id, rating, title, comment, status)
VALUES (:product_id, :user_id, :rating, :title, :comment, 'pending')
SQL;
            $stmtInsertReview = $pdo->prepare($sqlInsertReview);
            $stmtInsertReview->execute([
                'product_id' => $productId,
                'user_id' => $userId,
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
            ]);

            $reviewMessage = 'レビューを投稿しました。管理者の審査後、表示されます。';
        } catch (Throwable $e) {
            $errorMessage = 'レビュー投稿に失敗しました。時間をおいて再度お試しください。';
        }
    } else {
        $errorMessage = 'レビュー投稿に失敗しました。入力内容をご確認ください。';
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

                        <form method="post" action="cart.php" class="add-to-cart-form">
                            <input type="hidden" name="variant_id" value="<?php echo (int)$selectedVariant['id']; ?>">

                            <label for="quantity">数量</label>
                            <input
                                id="quantity"
                                name="quantity"
                                type="number"
                                min="1"
                                value="1"
                                <?php echo ((int)$selectedVariant['stock'] > 0) ? '' : 'disabled'; ?>
                            >

                            <button class="button" type="submit" <?php echo ((int)$selectedVariant['stock'] > 0) ? '' : 'disabled'; ?>>
                                カートに追加
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="notice">この商品にはSKU情報がありません。</p>
                <?php endif; ?>

                <p class="product-actions"><a class="button" href="product.php">商品一覧へ戻る</a></p>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- Reviews Section -->
<section class="reviews-section">
    <h3>レビュー</h3>

    <?php if ($reviewMessage !== ''): ?>
        <p class="notice success"><?php echo htmlspecialchars($reviewMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($product && $totalReviews > 0): ?>
        <div class="review-stats">
            <p class="average-rating">
                平均評価: 
                <strong><?php echo number_format($averageRating, 1); ?></strong> / 5.0
                (<?php echo $totalReviews; ?>件)
            </p>
        </div>

        <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <span class="review-rating">
                            <?php 
                            $rating = (int)$review['rating'];
                            echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                            ?>
                        </span>
                        <span class="review-title">
                            <?php echo htmlspecialchars((string)($review['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <p class="review-user">
                        <?php echo htmlspecialchars((string)($review['user_name'] ?? 'ゲスト'), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p class="review-comment">
                        <?php echo htmlspecialchars((string)($review['comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p class="review-date">
                        <?php echo date('Y年m月d日', strtotime((string)$review['created_at'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($product): ?>
        <p class="notice">まだレビューはありません。最初のレビュアーになってください。</p>
    <?php endif; ?>

    <?php if ($product): ?>
        <div class="review-form-section">
            <h4>レビューを投稿する</h4>
            <form method="post" class="review-form">
                <input type="hidden" name="action" value="post_review">
                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">

                <label for="rating">評価</label>
                <select id="rating" name="rating" required>
                    <option value="">-- 選択してください --</option>
                    <option value="5">★★★★★ 5 - とても良い</option>
                    <option value="4">★★★★☆ 4 - 良い</option>
                    <option value="3">★★★☆☆ 3 - 普通</option>
                    <option value="2">★★☆☆☆ 2 - 悪い</option>
                    <option value="1">★☆☆☆☆ 1 - とても悪い</option>
                </select>

                <label for="title">タイトル</label>
                <input id="title" name="title" type="text" placeholder="レビューのタイトルを入力してください" maxlength="255" required>

                <label for="comment">コメント</label>
                <textarea id="comment" name="comment" placeholder="レビューの詳細を入力してください" rows="5" required></textarea>

                <button class="button" type="submit">レビューを投稿する</button>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
