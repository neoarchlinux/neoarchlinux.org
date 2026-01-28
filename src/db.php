<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getPdo(): PDO {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;

    try {
        $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";

        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}

function getRepoId(PDO $pdo, string $repo): int {
    $stmt = $pdo->prepare("SELECT id FROM repos WHERE repo_name = :repo");
    $stmt->execute([':repo' => $repo]);
    $id = $stmt->fetchColumn();
    if (!$id) jsonError("Repository '$repo' does not exist", 400);
    return (int) $id;
}

function getArchId(PDO $pdo, string $arch): int {
    $stmt = $pdo->prepare("SELECT id FROM arches WHERE arch_name = :arch");
    $stmt->execute([':arch' => $arch]);
    $id = $stmt->fetchColumn();
    if (!$id) jsonError("Architecture '$arch' does not exist", 400);
    return (int) $id;
}

function getPackageId(PDO $pdo, string $packageName): int {
    $stmt = $pdo->prepare("SELECT id FROM packages WHERE package_name = :package_name");
    $stmt->execute([':package_name' => $packageName]);
    $id = $stmt->fetchColumn();
    if (!$id) jsonError("Package '$packageName' does not exist", 400);
    return (int) $id;
}

function insertPackageVersion(
    PDO $pdo,
    int $packageId,
    int $repoId,
    int $archId,
    string $packageVersion,
    string $packageRelease,
    string $fileName,
    string $checksum,
    int $sizeBytes,
    int $uploadedBy,
    string $sourceIp,
    string $userAgent
): int {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE package_versions
                            SET overwritten = TRUE
                            WHERE package_id = :package_id
                            AND repo_id = :repo_id
                            AND arch_id = :arch_id
                            AND (package_version || '-' || package_release) != (:package_version || '-' || :package_release)");

        $stmt->execute([
            ':package_id' => $packageId,
            ':repo_id' => $repoId,
            ':arch_id' => $archId,
            ':package_version' => $packageVersion,
            ':package_release' => $packageRelease
        ]);

        $stmt = $pdo->prepare("INSERT INTO package_versions(
                                   package_id, repo_id, arch_id,
                                   uploaded_by, package_version,
                                   package_release, file_name,
                                   checksum_sha256, size_bytes
                               ) VALUES(
                                   :package_id, :repo_id, :arch_id,
                                   :uploaded_by, :package_version,
                                   :package_release, :file_name,
                                   :checksum_sha256, :size_bytes
                               ) RETURNING id");

        $stmt->execute([
            ':package_id' => $packageId,
            ':repo_id' => $repoId,
            ':arch_id' => $archId,
            ':uploaded_by' => $uploadedBy,
            ':package_version' => $packageVersion,
            ':package_release' => $packageRelease,
            ':file_name' => $fileName,
            ':checksum_sha256' => $checksum,
            ':size_bytes' => $sizeBytes
        ]);

        $pkgVersionId = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO package_history(package_version_id, source_ip, user_agent)
                            VALUES(:pkg_version_id, :ip, :ua)");

        $stmt->execute([
            ':pkg_version_id' => $pkgVersionId,
            ':ip' => $sourceIp,
            ':ua' => $userAgent
        ]);

        $pdo->commit();

        return $pkgVersionId;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getUserByUsername(PDO $pdo, string $username): mixed {
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, created_at FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: false;
}

function getUploadTokenByUser(PDO $pdo, int $userId) : mixed {
    $stmt = $pdo->prepare("SELECT id, user_id, token_hash, signing_key_fingerprint, created_at FROM upload_tokens WHERE user_id = :u LIMIT 1");
    $stmt->execute([':u' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: false;
}

function getPackageFilenamesForRepoArch(PDO $pdo, int $repoId, int $archId): array {
    $stmt = $pdo->prepare("SELECT file_name FROM package_versions
                           WHERE repo_id = :repo_id AND arch_id = :arch_id AND overwritten = FALSE
                           ORDER BY created_at ASC");
    $stmt->execute([':repo_id' => $repoId, ':arch_id' => $archId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    return $rows ?: [];
}

function searchPackages(PDO $pdo, string $q, int $page, int $perPage) {
    $offset = ($page - 1) * $perPage;

    $repoPriority = <<<SQL
        CASE repo
            WHEN 'matrix'    THEN 1
            WHEN 'system'    THEN 2
            WHEN 'world'     THEN 2
            WHEN 'galaxy'    THEN 2
            WHEN 'lib32'     THEN 2
            WHEN 'core'      THEN 3
            WHEN 'extra'     THEN 4
            WHEN 'multilib'  THEN 4
            ELSE 100
        END
    SQL;

    $where = '';
    $params = [];

    if ($q !== '') {
        $where = "WHERE name ILIKE :q OR description ILIKE :q";
        $params[':q'] = "%$q%";
    }

    $countSql = <<<SQL
        SELECT COUNT(*) FROM (
            SELECT DISTINCT ON (name) name
            FROM package_meta
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
            version,
            description
        FROM package_meta
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

    return [
        'total'   => $total,
        'results' => $stmt->fetchAll()
    ];
}
