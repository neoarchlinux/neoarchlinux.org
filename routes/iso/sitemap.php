<?php

declare(strict_types=1);

require_once '/var/www/src/config.php';

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

echo '<url>';
echo '<loc>https://iso.' . $DOMAIN . '/</loc>';
echo '<lastmod>' . date('Y-m-d', filemtime(__DIR__ . '/index.php')) . '</lastmod>';
echo '</url>';

echo '</urlset>';
