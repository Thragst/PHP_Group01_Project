<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

require_once __DIR__ . '/../includes/db.php';

$targets = [
    ['users',  'user_id',  ['phone', 'physical_address']],
    ['orders', 'order_id', ['shipping_address', 'contact_number']],
];

foreach ($targets as [$table, $idCol, $cols]) {
    $rows = $pdo->query("SELECT `$idCol`, `" . implode('`, `', $cols) . "` FROM `$table`")->fetchAll();
    $done = 0;

    foreach ($rows as $row) {
        $set = [];
        $params = [];
        foreach ($cols as $col) {
            $val = $row[$col];
            if ($val !== null && $val !== '' && !isEncryptedValue($val)) {
                $set[] = "`$col` = ?";
                $params[] = encryptField($val);
            }
        }
        if ($set) {
            $params[] = $row[$idCol];
            $pdo->prepare("UPDATE `$table` SET " . implode(', ', $set) . " WHERE `$idCol` = ?")->execute($params);
            $done++;
        }
    }
    echo "$table: encrypted $done row(s)\n";
}
echo "Done. Back up includes/encryption_key.php - without it the data cannot be decrypted.\n";