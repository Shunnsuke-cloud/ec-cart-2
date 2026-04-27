<?php
$pageTitle = '住所登録';
$activePage = 'address';

require_once __DIR__ . '/../app/Auth/session.php';
app_session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$errorMessage = '';
$noticeMessage = '';
$form = [
    'postal_code' => '',
    'prefecture' => '',
    'city' => '',
    'address_line1' => '',
    'address_line2' => '',
    'recipient_name' => '',
    'phone' => '',
    'is_default' => '1',
];

try {
    $stmtAddresses = $pdo->prepare(
        <<<'SQL'
SELECT id, postal_code, prefecture, city, address_line1, address_line2, recipient_name, phone, is_default, created_at
FROM addresses
WHERE user_id = :user_id
ORDER BY is_default DESC, created_at DESC, id DESC
SQL
    );
    $stmtAddresses->execute(['user_id' => $userId]);
    $addresses = $stmtAddresses->fetchAll();
} catch (Throwable $e) {
    $addresses = [];
    $errorMessage = '登録住所の取得に失敗しました。時間をおいて再度お試しください。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['postal_code'] = trim((string)($_POST['postal_code'] ?? ''));
    $form['prefecture'] = trim((string)($_POST['prefecture'] ?? ''));
    $form['city'] = trim((string)($_POST['city'] ?? ''));
    $form['address_line1'] = trim((string)($_POST['address_line1'] ?? ''));
    $form['address_line2'] = trim((string)($_POST['address_line2'] ?? ''));
    $form['recipient_name'] = trim((string)($_POST['recipient_name'] ?? ''));
    $form['phone'] = trim((string)($_POST['phone'] ?? ''));
    $form['is_default'] = isset($_POST['is_default']) ? '1' : '0';

    try {
        if ($form['postal_code'] === '') {
            throw new RuntimeException('郵便番号を入力してください。');
        }

        if ($form['prefecture'] === '') {
            throw new RuntimeException('都道府県を入力してください。');
        }

        if ($form['city'] === '') {
            throw new RuntimeException('市区町村を入力してください。');
        }

        if ($form['address_line1'] === '') {
            throw new RuntimeException('住所1を入力してください。');
        }

        if ($form['recipient_name'] === '') {
            throw new RuntimeException('宛名を入力してください。');
        }

        $pdo->beginTransaction();

        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = :user_id');
        $stmtCount->execute(['user_id' => $userId]);
        $addressCount = (int)$stmtCount->fetchColumn();

        $isDefault = $addressCount === 0 ? 1 : ((int)$form['is_default'] === 1 ? 1 : 0);

        $stmtInsert = $pdo->prepare(
            <<<'SQL'
INSERT INTO addresses (
    user_id,
    postal_code,
    prefecture,
    city,
    address_line1,
    address_line2,
    recipient_name,
    phone,
    is_default
) VALUES (
    :user_id,
    :postal_code,
    :prefecture,
    :city,
    :address_line1,
    :address_line2,
    :recipient_name,
    :phone,
    :is_default
)
SQL
        );
        $stmtInsert->execute([
            'user_id' => $userId,
            'postal_code' => $form['postal_code'],
            'prefecture' => $form['prefecture'],
            'city' => $form['city'],
            'address_line1' => $form['address_line1'],
            'address_line2' => $form['address_line2'] !== '' ? $form['address_line2'] : null,
            'recipient_name' => $form['recipient_name'],
            'phone' => $form['phone'] !== '' ? $form['phone'] : null,
            'is_default' => $isDefault,
        ]);

        if ($isDefault === 1) {
            $newAddressId = (int)$pdo->lastInsertId();
            $stmtResetDefault = $pdo->prepare(
                'UPDATE addresses SET is_default = 0 WHERE user_id = :user_id AND id <> :address_id'
            );
            $stmtResetDefault->execute([
                'user_id' => $userId,
                'address_id' => $newAddressId,
            ]);
        }

        $pdo->commit();
        header('Location: address.php?registered=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : '住所登録に失敗しました。時間をおいて再度お試しください。';
    }
}

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $noticeMessage = '住所を登録しました。';
}

require_once __DIR__ . '/../views/layout/header.php';
?>

<section>
    <h2>住所登録</h2>

    <?php if ($noticeMessage !== ''): ?>
        <p class="notice"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <p class="notice error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <div class="address-layout">
        <form method="post" class="auth-form address-form" novalidate>
            <label for="postal_code">郵便番号</label>
            <input id="postal_code" name="postal_code" type="text" required value="<?php echo htmlspecialchars($form['postal_code'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="prefecture">都道府県</label>
            <input id="prefecture" name="prefecture" type="text" required value="<?php echo htmlspecialchars($form['prefecture'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="city">市区町村</label>
            <input id="city" name="city" type="text" required value="<?php echo htmlspecialchars($form['city'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="address_line1">住所1</label>
            <input id="address_line1" name="address_line1" type="text" required value="<?php echo htmlspecialchars($form['address_line1'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="address_line2">住所2（建物名など）</label>
            <input id="address_line2" name="address_line2" type="text" value="<?php echo htmlspecialchars($form['address_line2'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="recipient_name">宛名</label>
            <input id="recipient_name" name="recipient_name" type="text" required value="<?php echo htmlspecialchars($form['recipient_name'], ENT_QUOTES, 'UTF-8'); ?>">

            <label for="phone">電話番号（任意）</label>
            <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($form['phone'], ENT_QUOTES, 'UTF-8'); ?>">

            <label class="checkbox-field" for="is_default">
                <input id="is_default" name="is_default" type="checkbox" value="1" <?php echo $form['is_default'] === '1' ? 'checked' : ''; ?>>
                既定の住所にする
            </label>

            <button class="button" type="submit">住所を登録する</button>
        </form>

        <div class="address-list-wrap">
            <h3>登録済み住所</h3>

            <?php if (empty($addresses)): ?>
                <p class="notice">まだ住所は登録されていません。</p>
            <?php else: ?>
                <div class="address-list">
                    <?php foreach ($addresses as $address): ?>
                        <article class="address-card">
                            <p class="address-flag"><?php echo ((int)$address['is_default'] === 1) ? '既定' : '登録済み'; ?></p>
                            <p><?php echo htmlspecialchars((string)$address['recipient_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>〒<?php echo htmlspecialchars((string)$address['postal_code'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><?php echo htmlspecialchars((string)$address['prefecture'], ENT_QUOTES, 'UTF-8'); ?><?php echo htmlspecialchars((string)$address['city'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><?php echo htmlspecialchars((string)$address['address_line1'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (!empty($address['address_line2'])): ?>
                                <p><?php echo htmlspecialchars((string)$address['address_line2'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($address['phone'])): ?>
                                <p>電話: <?php echo htmlspecialchars((string)$address['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
