<?php

declare(strict_types=1);

require_once '/var/www/src/db.php';

$pdo = getPdo();

$q = $_GET['q'] ?? null;

if ($q !== null) {
    if (!preg_match('/^[a-zA-Z0-9-]{2}$/', $q)) {
        http_response_code(400);
        exit('Invalid query');
    }
}

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';

if ($q === null) {
    header('Content-Type: application/xml; charset=utf-8');

    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    $stmt = $pdo->query("
        SELECT DISTINCT 
            CASE 
                WHEN LENGTH(name) >= 2 THEN LEFT(name, 2)
                ELSE name
            END AS prefix
        FROM components
        ORDER BY prefix
    ");

    foreach ($stmt as $row) {
        $prefix = $row['prefix'];

        echo '<sitemap>';
        echo '<loc>https://packages.' . $DOMAIN . '/sitemap?q=' . htmlspecialchars($prefix, ENT_XML1) . '</loc>';
        echo '</sitemap>';
    }

    echo '</sitemapindex>';
    exit;
}

$prefix = $q . '%';

$stmt = $pdo->prepare("
    SELECT c.name, MAX(pm.last_updated) AS last_updated
    FROM components c
    LEFT JOIN package_meta pm ON pm.name = c.name
    WHERE c.name LIKE :prefix
    GROUP BY c.name
    ORDER BY c.name
");

$stmt->execute([
    ':prefix' => $prefix
]);

$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

foreach ($components as $c) {
    echo '<url>';

    echo '<loc>';
    echo 'https://packages.' . $DOMAIN . '/' . htmlspecialchars($c['name'], ENT_XML1);
    echo '</loc>';

    if ($c['last_updated'] !== null) {
        echo '<lastmod>' . $c['last_updated'] . '</lastmod>';
    }

    echo '</url>';
}

echo '</urlset>';