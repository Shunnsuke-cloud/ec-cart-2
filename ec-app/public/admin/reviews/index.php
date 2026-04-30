<?php
$pageTitle = 'レビュー管理';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
$pdo = require __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Admin/csv.php';

$noticeMessage = '';
$errorMessage = '';
$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$isCsvExport = isset($_GET['export']) && $_GET['export'] === 'csv';

$reviews = [];
$totalReviews = 0;
$totalPages = 1;

$statusOptions = [
    '' => 'すべて',
    'pending' => '未審査',
    'approved' => '承認済み',
    'rejected' => '却下',
];

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $noticeMessage = 'レビューを削除しました。';
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $noticeMessage = 'レビューステータスを更新しました。';
}

try {
    // 合計件数を取得
    $countSql = 'SELECT COUNT(r.id) AS total FROM reviews r';
    if ($statusFilter !== '') {
        $countSql .= ' WHERE r.status = :status';
    }
    
    $stmtCount = $pdo->prepare($countSql);
    if ($statusFilter !== '') {
        $stmtCount->execute(['status' => $statusFilter]);
    } else {
        $stmtCount->execute();
    }
    $countResult = $stmtCount->fetch();
    $totalReviews = (int)($countResult['total'] ?? 0);
    $totalPages = max(1, (int)ceil($totalReviews / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    // レビュー一覧を取得
    $sql = <<<'SQL'
SELECT
    r.id,
    r.user_id,
    r.product_id,
    r.rating,
    r.title,
    r.comment,
    r.status,
    r.created_at,
    r.updated_at,
    u.name AS user_name,
    u.email AS user_email,
    p.name AS product_name,
    p.slug AS product_slug
FROM reviews r
LEFT JOIN users u ON u.id = r.user_id
LEFT JOIN products p ON p.id = r.product_id
SQL;

    if ($statusFilter !== '') {
        $sql .= ' WHERE r.status = :status';
    }

    $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT :offset, :perPage';

    $stmt = $pdo->prepare($sql);
    if ($statusFilter !== '') {
        $stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll();

    if ($isCsvExport) {
        $exportSql = <<<'SQL'
SELECT
    r.id,
    r.user_id,
    r.product_id,
    r.rating,
    r.title,
    r.comment,
    r.status,
    r.created_at,
    r.updated_at,
    u.name AS user_name,
    u.email AS user_email,
    p.name AS product_name,
    p.slug AS product_slug
FROM reviews r
LEFT JOIN users u ON u.id = r.user_id
LEFT JOIN products p ON p.id = r.product_id
SQL;

        if ($statusFilter !== '') {
            $exportSql .= ' WHERE r.status = :status';
        }

        $exportSql .= ' ORDER BY r.created_at DESC, r.id DESC';

        $stmtExport = $pdo->prepare($exportSql);
        if ($statusFilter !== '') {
            $stmtExport->bindValue(':status', $statusFilter, PDO::PARAM_STR);
        }
        $stmtExport->execute();
        $exportReviews = $stmtExport->fetchAll();

        $csvRows = [];
        foreach ($exportReviews as $review) {
            $csvRows[] = [
                'user_name' => (string)($review['user_name'] ?? 'N/A'),
                'user_email' => (string)($review['user_email'] ?? ''),
                'product_name' => (string)($review['product_name'] ?? ''),
                'product_slug' => (string)($review['product_slug'] ?? ''),
                'rating' => (string)$review['rating'],
                'title' => (string)$review['title'],
                'comment' => (string)$review['comment'],
                'status' => (string)$review['status'],
                'created_at' => (string)$review['created_at'],
                'updated_at' => (string)$review['updated_at'],
            ];
        }

        $filename = 'reviews';
        if ($statusFilter !== '') {
            $filename .= '_' . $statusFilter;
        }
        $filename .= '_' . date('Ymd_His') . '.csv';

        admin_output_csv(
            ['ユーザー名', 'ユーザーメール', '商品名', '商品スラッグ', '評価', 'タイトル', 'コメント', 'ステータス', '作成日', '更新日'],
            $csvRows,
            $filename
        );
    }
} catch (Throwable $e) {
    $errorMessage = 'レビュー一覧の取得に失敗しました。';
}

function getStatusLabel(string $status): string
{
    $labels = [
        'pending' => '未審査',
        'approved' => '承認済み',
        'rejected' => '却下',
    ];
    return $labels[$status] ?? $status;
}

function getStatusClass(string $status): string
{
    $classes = [
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
    ];
    return $classes[$status] ?? '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | EC Cart Admin</title>
    <link rel="stylesheet" href="../../css/common.css">
    <style>
        .filter-form {
            margin-bottom: 20px;
        }
        .filter-form label {
            margin-right: 10px;
            font-weight: bold;
        }
        .filter-form select {
            padding: 5px 10px;
            margin-right: 20px;
        }
        .filter-form button {
            padding: 5px 15px;
        }
        .review-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .review-table th,
        .review-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .review-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .review-table tr:hover {
            background-color: #f9f9f9;
        }
        .review-title {
            font-weight: bold;
            color: #333;
        }
        .review-comment {
            font-size: 0.9em;
            color: #666;
            max-width: 300px;
            word-wrap: break-word;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-pending {
            background-color: #ffd700;
            color: #333;
        }
        .status-approved {
            background-color: #90ee90;
            color: #333;
        }
        .status-rejected {
            background-color: #ff6b6b;
            color: #fff;
        }
        .rating {
            color: #ffc107;
            font-weight: bold;
        }
        .review-actions {
            font-size: 0.9em;
        }
        .review-actions a {
            margin-right: 10px;
            color: #0066cc;
            text-decoration: none;
        }
        .review-actions a:hover {
            text-decoration: underline;
        }
        .product-link {
            color: #0066cc;
            text-decoration: none;
        }
        .product-link:hover {
            text-decoration: underline;
        }
        .pagination {
            margin-top: 30px;
            text-align: center;
        }
        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-decoration: none;
            color: #0066cc;
        }
        .pagination span.current {
            background-color: #0066cc;
            color: #fff;
            border-color: #0066cc;
        }
        .pagination a:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <main class="site-main">
        <div class="container">
            <section>
                <h2>レビュー管理</h2>
                <p class="product-actions">
                    <a class="button" href="../index.php">管理画面に戻る</a>
                    <a class="button" href="?status=<?php echo htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8'); ?>&amp;export=csv">CSV出力</a>
                </p>

                <?php if ($noticeMessage !== ''): ?>
                    <p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form method="get" class="filter-form">
                    <label for="status-filter">ステータス:</label>
                    <select id="status-filter" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($statusFilter === $value ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">フィルター</button>
                </form>

                <?php if (empty($reviews) && $totalReviews === 0): ?>
                    <p>レビューがありません。</p>
                <?php else: ?>
                    <p>全<?php echo $totalReviews; ?>件 / <?php echo $currentPage; ?>ページ</p>
                    <table class="review-table">
                        <thead>
                            <tr>
                                <th>ユーザー</th>
                                <th>商品</th>
                                <th>レーティング</th>
                                <th>タイトル</th>
                                <th>コメント</th>
                                <th>ステータス</th>
                                <th>作成日</th>
                                <th>アクション</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars((string)($review['user_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.85em; color: #666;">
                                            <?php echo htmlspecialchars((string)($review['user_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($review['product_slug']): ?>
                                            <a class="product-link" href="../../product_detail.php?slug=<?php echo urlencode((string)$review['product_slug']); ?>" target="_blank">
                                                <?php echo htmlspecialchars((string)($review['product_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars((string)($review['product_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="rating">
                                            <?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?>
                                            (<?php echo (int)$review['rating']; ?>/5)
                                        </span>
                                    </td>
                                    <td class="review-title">
                                        <?php echo htmlspecialchars((string)$review['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="review-comment">
                                        <?php echo htmlspecialchars((string)$review['comment'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusClass((string)$review['status']); ?>">
                                            <?php echo getStatusLabel((string)$review['status']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.85em;">
                                        <?php echo date('Y-m-d H:i', strtotime((string)$review['created_at'])); ?>
                                    </td>
                                    <td class="review-actions">
                                        <a href="edit.php?id=<?php echo (int)$review['id']; ?>">編集</a>
                                        <a href="edit.php?id=<?php echo (int)$review['id']; ?>&action=delete" onclick="return confirm('本当に削除しますか？');">削除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=1<?php echo ($statusFilter ? '&status=' . urlencode($statusFilter) : ''); ?>">最初</a>
                                <a href="?page=<?php echo ($currentPage - 1); ?><?php echo ($statusFilter ? '&status=' . urlencode($statusFilter) : ''); ?>">前へ</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                <?php if ($i === $currentPage): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php echo ($statusFilter ? '&status=' . urlencode($statusFilter) : ''); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo ($currentPage + 1); ?><?php echo ($statusFilter ? '&status=' . urlencode($statusFilter) : ''); ?>">次へ</a>
                                <a href="?page=<?php echo $totalPages; ?><?php echo ($statusFilter ? '&status=' . urlencode($statusFilter) : ''); ?>">最後</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
