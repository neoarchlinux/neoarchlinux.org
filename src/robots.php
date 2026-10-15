<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$site = $_GET['site'] ?? '';

$hosts = [
    'home' => $DOMAIN,
    'docs' => 'docs.' . $DOMAIN,
    'iso' => 'iso.' . $DOMAIN,
    'packages' => 'packages.' . $DOMAIN,
];

if ($site !== 'mirrors' && !isset($hosts[$site])) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

echo "User-agent: *\n";

if ($site === 'mirrors') {
    echo "Disallow: /\n";
    exit;
}

echo "Allow: /\n";

if ($site === 'iso') {
    echo "Disallow: /build\n";
    echo "Disallow: /ws\n";
    echo "Disallow: /download/\n";
}

echo "\n";
echo 'Sitemap: https://' . $hosts[$site] . "/sitemap\n";
