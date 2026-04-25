<?php
// これを使ってください

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'DB接続テスト';
$activePage = '';
require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
	<h2>DB接続テスト</h2>
	<p>DB接続成功！</p>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
