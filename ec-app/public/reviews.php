<?php
$pageTitle = 'レビュー一覧';
$activePage = '';

$pdo = require __DIR__ . '/../config/database.php';

$productSlug = isset($_GET['product']) ? trim((string)$_GET['product']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

$product = null;
$reviews = [];
$totalReviews = 0;
$totalPages = 0;
$averageRating = 0;
$errorMessage = '';

if ($productSlug === '') {
    $errorMessage = '商品が指定されていません。';
} else {
    try {
        // Get product info
        $sqlProduct = <<<'SQL'
SELECT
    p.id,
    p.name,
    p.slug
FROM products p
WHERE p.slug = :slug
  AND p.status = 'active'
  AND p.deleted_at IS NULL
LIMIT 1
SQL;
        $stmtProduct = $pdo->prepare($sqlProduct);
        $stmtProduct->execute(['slug' => $productSlug]);
        $product = $stmtProduct->fetch();

        if (!$product) {
            $errorMessage = '指定された商品が見つかりません。';
        } else {
            // Get review stats
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
            $totalPages = ceil($totalReviews / $perPage);

            if ($page > $totalPages && $totalPages > 0) {
                $page = $totalPages;
            }

            // Get reviews with pagination
            $offset = ($page - 1) * $perPage;
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
LIMIT :limit OFFSET :offset
SQL;
            $stmtReviews = $pdo->prepare($sqlReviews);
            $stmtReviews->bindValue(':product_id', (int)$product['id'], PDO::PARAM_INT);
            $stmtReviews->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmtReviews->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtReviews->execute();
            $reviews = $stmtReviews->fetchAll();
        }
    } catch (Throwable $e) {
        $errorMessage = 'レビュー情報の取得に失敗しました。時間をおいて再度お試しください。';
    }
}

if ($product) {
    $pageTitle = (string)$product['name'] . ' - レビュー一覧';
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section class="reviews-page">
    <h1>レビュー一覧</h1>

    <?php if ($errorMessage !== ''): ?>
        <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="product-actions"><a class="button" href="product.php">商品一覧へ戻る</a></p>
    <?php elseif ($product !== null): ?>
        <div class="reviews-container">
            <div class="product-info">
                <h2>
                    <a href="product_detail.php?slug=<?php echo urlencode((string)$product['slug']); ?>">
                        <?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </h2>

                <?php if ($totalReviews > 0): ?>
                    <div class="review-stats">
                        <p class="average-rating">
                            平均評価: <strong><?php echo number_format($averageRating, 1); ?></strong> / 5.0
                        </p>
                        <p class="total-reviews">
                            レビュー数: <strong><?php echo $totalReviews; ?></strong>件
                        </p>
                    </div>
                <?php else: ?>
                    <p class="notice">まだレビューはありません。</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($reviews)): ?>
                <div class="reviews-list">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-rating">
                                    <?php 
                                    $rating = (int)$review['rating'];
                                    echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                    echo ' ' . $rating . '/5';
                                    ?>
                                </div>
                                <div class="review-title">
                                    <?php echo htmlspecialchars((string)($review['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="review-meta">
                                <span class="review-user">
                                    <?php echo htmlspecialchars((string)($review['user_name'] ?? 'ゲスト'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="review-date">
                                    <?php echo date('Y年m月d日', strtotime((string)$review['created_at'])); ?>
                                </span>
                            </div>

                            <div class="review-body">
                                <p class="review-comment">
                                    <?php echo htmlspecialchars((string)($review['comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="reviews.php?product=<?php echo urlencode($productSlug); ?>&page=1" class="page-link">最初</a>
                            <a href="reviews.php?product=<?php echo urlencode($productSlug); ?>&page=<?php echo $page - 1; ?>" class="page-link">前へ</a>
                        <?php endif; ?>

                        <span class="page-info">
                            <?php echo $page; ?> / <?php echo $totalPages; ?>
                        </span>

                        <?php if ($page < $totalPages): ?>
                            <a href="reviews.php?product=<?php echo urlencode($productSlug); ?>&page=<?php echo $page + 1; ?>" class="page-link">次へ</a>
                            <a href="reviews.php?product=<?php echo urlencode($productSlug); ?>&page=<?php echo $totalPages; ?>" class="page-link">最後</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <p class="product-actions">
                <a class="button" href="product_detail.php?slug=<?php echo urlencode((string)$product['slug']); ?>">商品詳細へ戻る</a>
            </p>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
