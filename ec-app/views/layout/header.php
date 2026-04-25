<?php
$pageTitle = $pageTitle ?? 'EC Cart';
$activePage = $activePage ?? '';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$isLoggedIn = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
$loginUserName = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | EC Cart</title>
	<link rel="stylesheet" href="css/common.css">
</head>
<body>
	<header class="site-header">
		<div class="container header-inner">
			<h1 class="site-logo"><a href="index.php">EC Cart</a></h1>
			<nav class="site-nav" aria-label="グローバルナビゲーション">
				<ul>
					<li><a class="<?php echo $activePage === 'home' ? 'is-active' : ''; ?>" href="index.php">ホーム</a></li>
					<li><a class="<?php echo $activePage === 'product' ? 'is-active' : ''; ?>" href="product.php">商品</a></li>
					<li><a class="<?php echo $activePage === 'cart' ? 'is-active' : ''; ?>" href="cart.php">カート</a></li>
					<li><a class="<?php echo $activePage === 'checkout' ? 'is-active' : ''; ?>" href="checkout.php">購入手続き</a></li>
					<?php if ($isLoggedIn): ?>
						<li><span class="nav-user"><?php echo htmlspecialchars($loginUserName, ENT_QUOTES, 'UTF-8'); ?>さん</span></li>
						<li><a href="logout.php">ログアウト</a></li>
					<?php else: ?>
						<li><a class="<?php echo $activePage === 'register' ? 'is-active' : ''; ?>" href="register.php">会員登録</a></li>
						<li><a class="<?php echo $activePage === 'login' ? 'is-active' : ''; ?>" href="login.php">ログイン</a></li>
					<?php endif; ?>
				</ul>
			</nav>
		</div>
	</header>
	<main class="site-main">
		<div class="container">
