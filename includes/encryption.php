<?php

const ENC_PREFIX = 'enc:v1:';

function getEncryptionKey()
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    $raw = getenv('SPORTMART_ENC_KEY');
    $keyFile = __DIR__ . '/encryption_key.php';

    if (!$raw && is_file($keyFile)) {
        $raw = include $keyFile;
    }

    if (!$raw) {
        $raw = base64_encode(random_bytes(32));
        $contents = "<?php\n// SportMart data-encryption key. DO NOT COMMIT. BACK THIS UP.\nreturn '" . $raw . "';\n";
        if (@file_put_contents($keyFile, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Cannot create includes/encryption_key.php (check folder permissions).');
        }
        @chmod($keyFile, 0600);
    }

    $decoded = base64_decode($raw, true);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new RuntimeException('Invalid encryption key (must be base64 of 32 bytes).');
    }

    return $key = $decoded;
}

function isEncryptedValue($value)
{
    return is_string($value) && strncmp($value, ENC_PREFIX, strlen(ENC_PREFIX)) === 0;
}

function encryptField($value)
{
    if ($value === null || $value === '' || isEncryptedValue($value)) {
        return $value;
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt((string) $value, 'aes-256-gcm', getEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);

    if ($cipher === false) {
        throw new RuntimeException('Encryption failed.');
    }

    return ENC_PREFIX . base64_encode($iv . $tag . $cipher);
}

function decryptField($value)
{
    if ($value === null || $value === '' || !isEncryptedValue($value)) {
        return $value;
    }

    $bin = base64_decode(substr($value, strlen(ENC_PREFIX)), true);
    if ($bin === false || strlen($bin) < 29) {
        return '';
    }

    $plain = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', getEncryptionKey(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));

    return $plain === false ? '' : $plain;
}

function decryptRow($row, array $columns)
{
    if (!is_array($row)) {
        return $row;
    }
    foreach ($columns as $col) {
        if (array_key_exists($col, $row)) {
            $row[$col] = decryptField($row[$col]);
        }
    }
    return $row;
}

function decryptRows(array $rows, array $columns)
{
    foreach ($rows as $i => $row) {
        $rows[$i] = decryptRow($row, $columns);
    }
    return $rows;
}