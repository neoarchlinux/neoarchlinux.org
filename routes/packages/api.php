<?php

declare(strict_types=1);

require_once '/var/www/src/config.php';

header('Content-Type: application/json; charset=utf-8');

$domain = '';

if (isset($_SERVER['HTTP_ORIGIN'])) $domain = $_SERVER['HTTP_ORIGIN'];
else if (isset($_SERVER['HTTP_REFERER'])) $domain = $_SERVER['HTTP_REFERER'];

if (str_ends_with($domain, '.' . $DOMAIN)) {
    header('Access-Control-Allow-Origin: ' . $domain);
}

require_once '/var/www/src/db.php';

$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(50, (int)($_GET['per_page'] ?? 50));

$pdo = getPdo();
$searchResult = searchPackages($pdo, $q, $page, $perPage);

echo json_encode([
    'query'     => $q,
    'page'      => $page,
    'per_page'  => $perPage,
    'total'     => $searchResult['total'],
    'results'   => $searchResult['results'],
], JSON_UNESCAPED_UNICODE);
