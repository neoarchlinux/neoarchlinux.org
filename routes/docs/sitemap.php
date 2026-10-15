<?php

declare(strict_types=1);

require_once '/var/www/src/config.php';

function getSitemapPages(string $dir, string $base): array
{
    $pages = [];

    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $full = "$dir/$file";

        if (is_dir($full)) {
            $pages = array_merge($pages, getSitemapPages($full, $base));
        } elseif (str_ends_with($file, '.md')) {
            $rel = substr(ltrim(str_replace($base, '', $full), '/'), 0, -3);

            if ($rel === 'index') {
                $rel = '';
            } elseif (str_ends_with($rel, '/index')) {
                $rel = substr($rel, 0, -6);
            }

            $pages[] = [
                'path' => $rel,
                'last_updated' => filemtime($full),
            ];
        }
    }

    return $pages;
}

$pages = getSitemapPages(__DIR__, __DIR__);

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

foreach ($pages as $p) {
    echo '<url>';

    echo '<loc>';
    echo 'https://docs.' . $DOMAIN . '/' . htmlspecialchars($p['path'], ENT_XML1);
    echo '</loc>';

    echo '<lastmod>' . date('Y-m-d', $p['last_updated']) . '</lastmod>';

    echo '</url>';
}

echo '</urlset>';
