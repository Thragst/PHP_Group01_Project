<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/search_engine.php';

$q = sanitizeSearchQuery($_GET['q'] ?? '');
if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(['suggestions' => [], 'didYouMean' => null]);
    exit;
}

try {
    $search = smartSearch($pdo, $q);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['suggestions' => [], 'didYouMean' => null]);
    exit;
}

$suggestions = [];
foreach (array_slice($search['results'], 0, 6) as $p) {
    $suggestions[] = [
        'id'       => $p['product_id'],
        'name'     => $p['name'],
        'brand'    => $p['brand'],
        'category' => $p['category'],
        'price'    => number_format($p['price'], 2),
        'image'    => productImageSrc($p['image_path']),
        'url'      => 'index.php?page=productDetails&product_id=' . (int) $p['product_id'],
    ];
}

echo json_encode([
    'suggestions' => $suggestions,
    'total'       => $search['total'],
    'didYouMean'  => $search['didYouMean'],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
