<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$dsn = sprintf(
    'pgsql:host=%s;dbname=%s',
    getenv('DB_HOST'),
    getenv('DB_NAME')
);

$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;
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

$where = '';
$params = [];

if ($q !== '') {
    $where = "WHERE name ILIKE :q OR repo ILIKE :q";
    $params[':q'] = "%$q%";
}

$countSql = "SELECT COUNT(*) FROM packages $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = <<<SQL
SELECT
    name,
    repo,
    version,
    arch,
    description
FROM packages
$where
ORDER BY name
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
