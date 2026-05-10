<?php
$pageTitle = 'レビュー管理';

require_once __DIR__ . '/../../../app/Admin/auth.php';
admin_require_login();
$pdo = require __DIR__ . '/../../../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$errorMessage = '';
$formErrorMessage = '';
$successMessage = '';
$review = null;
$product = null;
$user = null;

$reviewStatuses = [
    'pending' => '未審査',
    'approved' => '承認済み',
    'rejected' => '却下',
];

$form = [
    'status' => '',
    'admin_comment' => '',
];

// Delete action
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($id <= 0) {
        $errorMessage = 'レビューが指定されていません。';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM reviews WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            if ($stmt->fetch()) {
                $pdo->beginTransaction();
                $stmtDelete = $pdo->prepare('DELETE FROM reviews WHERE id = :id');
                $stmtDelete->execute(['id' => $id]);
                $pdo->commit();
                header('Location: index.php?deleted=1');
                exit;
            } else {
                throw new RuntimeException('レビューが見つかりません。');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = 'レビューの削除に失敗しました。';
        }
    }
}

if ($id <= 0) {
    $errorMessage = 'レビューが指定されていません。';
} else {
    try {
        // Fetch review details
        $stmt = $pdo->prepare(
            <<<'SQL'
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
    p.id AS product_id,
    p.name AS product_name,
    p.slug AS product_slug
FROM reviews r
LEFT JOIN users u ON u.id = r.user_id
LEFT JOIN products p ON p.id = r.product_id
WHERE r.id = :id
LIMIT 1
SQL
        );
        $stmt->execute(['id' => $id]);
        $review = $stmt->fetch();

        if (!$review) {
            throw new RuntimeException('レビューが見つかりません。');
        }

        $form['status'] = (string)$review['status'];

        // Handle POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newStatus = trim((string)($_POST['status'] ?? ''));
            $adminComment = trim((string)($_POST['admin_comment'] ?? ''));

            if (!array_key_exists($newStatus, $reviewStatuses)) {
                throw new RuntimeException('ステータスの値が不正です。');
            }

            $pdo->beginTransaction();

            try {
                $stmtUpdate = $pdo->prepare(
                    <<<'SQL'
UPDATE reviews
SET
    status = :status,
    updated_at = NOW()
WHERE id = :id
SQL
                );
                $stmtUpdate->execute([
                    'status' => $newStatus,
                    'id' => $id,
                ]);

                // If admin_comment is provided, you could store it in a separate table if needed
                // For now, we just log the update

                $pdo->commit();

                $form['status'] = $newStatus;
                $form['admin_comment'] = $adminComment;
                $successMessage = 'レビューを更新しました。';
                
                // Refresh review data
                $stmt->execute(['id' => $id]);
                $review = $stmt->fetch();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage() ?: 'レビュー情報の取得に失敗しました。';
    }
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

function getRatingStars(int $rating): string
{
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
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
    <style>
        .review-detail {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .review-detail-item {
            margin-bottom: 20px;
        }
        .review-detail-label {
            font-weight: bold;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        .review-detail-value {
            color: #666;
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .review-detail-value a {
            color: #0066cc;
            text-decoration: none;
        }
        .review-detail-value a:hover {
            text-decoration: underline;
        }
        .rating-display {
            font-size: 1.3em;
            color: #ffc107;
            font-weight: bold;
        }
        .review-comment-display {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .form-section {
            background-color: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-family: inherit;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        }
        .form-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-update {
            background-color: #28a745;
            color: white;
        }
        .btn-update:hover {
            background-color: #218838;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .btn-back {
            background-color: #6c757d;
            color: white;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
        .notice.success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <main class="site-main">
        <div class="container">
            <section>
                <h2>レビュー管理</h2>
                <p class="product-actions">
                    <a class="button" href="index.php">レビュー一覧に戻る</a>
                </p>

                <?php if ($successMessage !== ''): ?>
                    <p class="notice success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($review): ?>
                    <div class="review-detail">
                        <h3>レビュー詳細</h3>

                        <div class="review-detail-item">
                            <span class="review-detail-label">ユーザー:</span>
                            <div class="review-detail-value">
                                <strong><?php echo htmlspecialchars((string)($review['user_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <div style="font-size: 0.9em; color: #666;">
                                    <?php echo htmlspecialchars((string)($review['user_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="review-detail-item">
                            <span class="review-detail-label">商品:</span>
                            <div class="review-detail-value">
                                <?php if ($review['product_slug']): ?>
                                    <a href="../../product_detail.php?slug=<?php echo urlencode((string)$review['product_slug']); ?>" target="_blank">
                                        <?php echo htmlspecialchars((string)($review['product_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars((string)($review['product_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="review-detail-item">
                            <span class="review-detail-label">レーティング:</span>
                            <div class="review-detail-value">
                                <span class="rating-display">
                                    <?php echo getRatingStars((int)$review['rating']); ?>
                                    (<?php echo (int)$review['rating']; ?>/5)
                                </span>
                            </div>
                        </div>

                        <div class="review-detail-item">
                            <span class="review-detail-label">タイトル:</span>
                            <div class="review-detail-value">
                                <?php echo htmlspecialchars((string)$review['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <div class="review-detail-item">
                            <span class="review-detail-label">コメント:</span>
                            <div class="review-detail-value review-comment-display">
                                <?php echo htmlspecialchars((string)$review['comment'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <div class="review-detail-item">
                            <span class="review-detail-label">作成日:</span>
                            <div class="review-detail-value">
                                <?php echo date('Y-m-d H:i:s', strtotime((string)$review['created_at'])); ?>
                            </div>
                        </div>

                        <div class="review-detail-item">
                            <span class="review-detail-label">最終更新:</span>
                            <div class="review-detail-value">
                                <?php echo date('Y-m-d H:i:s', strtotime((string)$review['updated_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>ステータス変更</h3>
                        <form method="post">
                            <div class="form-group">
                                <label for="status">ステータス:</label>
                                <select id="status" name="status" required>
                                    <?php foreach ($reviewStatuses as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($form['status'] === $value ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="admin_comment">管理者コメント（内部用）:</label>
                                <textarea id="admin_comment" name="admin_comment" placeholder="このレビューについてのメモを入力できます。"></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-update">更新する</button>
                                <a href="index.php" class="btn-back">戻る</a>
                                <a href="?id=<?php echo (int)$review['id']; ?>&action=delete" class="btn-delete" onclick="return confirm('本当に削除しますか？このアクションは元に戻せません。');">削除する</a>
                            </div>
                        </form>
                    </div>
                <?php elseif ($errorMessage === ''): ?>
                    <p>レビュー情報を取得中...</p>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
