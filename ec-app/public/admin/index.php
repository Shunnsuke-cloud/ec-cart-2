<?php
$pageTitle = '管理者ダッシュボード';

require_once __DIR__ . '/../../app/Admin/auth.php';
admin_require_login();
$adminName = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | EC Cart</title>
    <link rel="stylesheet" href="../css/common.css">
</head>
<body>
    <main class="site-main">
        <div class="container">
            <section>
                <h2>管理者ダッシュボード</h2>
                <p>ようこそ、<?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?>さん。</p>
                <p class="product-actions"><a class="button" href="products/index.php">商品管理へ</a></p>
                <p class="product-actions"><a class="button" href="orders/index.php">注文管理へ</a></p>
                <p class="product-actions"><a class="button" href="logout.php">ログアウト</a></p>
            </section>
        </div>
    </main>
</body>
</html>
