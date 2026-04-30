<?php

declare(strict_types=1);

function admin_output_csv(array $headers, array $rows, string $filename): void
{
    if (headers_sent()) {
        throw new RuntimeException('CSV出力を開始できません。');
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('CSV出力に失敗しました。');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        fputcsv($output, array_values((array)$row));
    }

    fclose($output);
    exit;
}