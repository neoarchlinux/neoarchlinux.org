<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$domain = '';

if (isset($_SERVER['HTTP_ORIGIN'])) $domain = $_SERVER['HTTP_ORIGIN'];
else if (isset($_SERVER['HTTP_REFERER'])) $domain = $_SERVER['HTTP_REFERER'];

if (str_ends_with($domain, '.neoarchlinux.org')) {
    header('Access-Control-Allow-Origin: ' . $domain);
}

$dsn = sprintf(
    'pgsql:host=%s;dbname=%s',
    getenv('DB_HOST'),
    getenv('DB_NAME')
);

$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(50, (int)($_GET['per_page'] ?? 50));
$offset   = ($page - 1) * $perPage;

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'database connection failed',
        'reason' => $e
    ]);
    exit;
}

$repoPriority = <<<SQL
CASE repo
    WHEN 'matrix'    THEN 1
    WHEN 'system'    THEN 2
    WHEN 'world'     THEN 3
    WHEN 'galaxy'    THEN 4
    WHEN 'lib32'     THEN 5
    WHEN 'core'      THEN 6
    WHEN 'extra'     THEN 7
    WHEN 'multilib'  THEN 8
    ELSE 100
END
SQL;

$where = '';
$params = [];

if ($q !== '') {
    $where = "WHERE name ILIKE :q OR repo ILIKE :q";
    $params[':q'] = "%$q%";
}

$countSql = <<<SQL
SELECT COUNT(*) FROM (
    SELECT DISTINCT ON (name) name
    FROM packages
    $where
    ORDER BY name, $repoPriority
) AS t
SQL;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = <<<SQL
SELECT DISTINCT ON (name)
    name,
    repo,
    version,
    arch,
    description
FROM packages
$where
ORDER BY
    name,
    $repoPriority
LIMIT :limit OFFSET :offset
SQL;

$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$results = $stmt->fetchAll();

echo json_encode([
    'query'     => $q,
    'page'      => $page,
    'per_page'  => $perPage,
    'total'     => $total,
    'results'   => $results,
], JSON_UNESCAPED_UNICODE);
