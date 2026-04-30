<?php

declare(strict_types=1);

function admin_send_shipping_notification(PDO $pdo, array $mailConfig, int $orderId): array
{
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT
    o.id,
    o.order_number,
    o.shipping_status,
    o.tracking_number,
    o.shipped_at,
    o.shipping_notified_at,
    u.name AS user_name,
    u.email AS user_email,
    COALESCE(a.recipient_name, u.name, '') AS recipient_name,
    COALESCE(a.postal_code, '') AS postal_code,
    CONCAT(
        COALESCE(a.prefecture, ''),
        COALESCE(a.city, ''),
        COALESCE(a.address_line1, ''),
        CASE
            WHEN a.address_line2 IS NULL OR a.address_line2 = '' THEN ''
            ELSE CONCAT(' ', a.address_line2)
        END
    ) AS shipping_address
FROM orders o
LEFT JOIN users u ON u.id = o.user_id
LEFT JOIN addresses a ON a.user_id = u.id AND a.is_default = 1
WHERE o.id = :id
LIMIT 1
SQL
    );
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return [false, '注文が見つかりません。'];
    }

    if ((string)$order['shipping_status'] !== 'shipped') {
        return [false, '配送状態が「発送済み」の注文のみ発送通知を送信できます。'];
    }

    if (!empty($order['shipping_notified_at'])) {
        return [false, 'この注文にはすでに発送通知が送信されています。'];
    }

    $recipientEmail = trim((string)($order['user_email'] ?? ''));
    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return [false, '通知先メールアドレスが見つかりません。'];
    }

    $recipientName = trim((string)($order['recipient_name'] ?? $order['user_name'] ?? 'お客様'));
    if ($recipientName === '') {
        $recipientName = 'お客様';
    }

    $subject = '【EC Cart】発送完了のお知らせ ' . (string)$order['order_number'];
    $bodyLines = [
        $recipientName . ' 様',
        '',
        'ご注文商品を発送しました。',
        '注文番号: ' . (string)$order['order_number'],
        '発送日時: ' . admin_format_datetime((string)($order['shipped_at'] ?? '')),
        '追跡番号: ' . trim((string)($order['tracking_number'] ?? '')),
        'お届け先: ' . trim((string)($order['shipping_address'] ?? '')),
        '郵便番号: ' . trim((string)($order['postal_code'] ?? '')),
        '',
        '配送状況は配送会社の追跡ページでご確認ください。',
        '',
        'このメールは自動送信です。',
    ];

    $headers = [];
    $fromAddress = trim((string)($mailConfig['from_address'] ?? ''));
    $fromName = trim((string)($mailConfig['from_name'] ?? 'EC Cart'));
    if ($fromAddress !== '') {
        $encodedFromName = function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($fromName, 'UTF-8')
            : $fromName;
        $headers[] = 'From: ' . $encodedFromName . ' <' . $fromAddress . '>';
    }
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headerString = implode("\r\n", $headers);
    $body = implode("\r\n", $bodyLines);

    $sent = false;
    $encodedSubject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8')
        : $subject;
    if (function_exists('mb_send_mail')) {
        if (function_exists('mb_language')) {
            mb_language('Japanese');
        }
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }
        $sent = mb_send_mail($recipientEmail, $subject, $body, $headerString);
    } else {
        $sent = mail($recipientEmail, $encodedSubject, $body, $headerString);
    }

    if (!$sent) {
        return [false, '発送通知メールの送信に失敗しました。'];
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE orders SET shipping_notified_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
    );
    $stmtUpdate->execute(['id' => $orderId]);

    return [true, '発送通知メールを送信しました。'];
}

function admin_format_datetime(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '-';
    }

    return str_replace('T', ' ', substr($value, 0, 16));
}