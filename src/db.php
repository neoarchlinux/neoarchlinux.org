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

function markPackageVersionOverwritten(
    PDO $pdo,
    int $packageId,
    int $repoId,
    int $archId,
    string $packageVersion,
    string $packageRelease
) {
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
            WHEN 'extra'     THEN 3
            WHEN 'multilib'  THEN 3
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
        'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

function getPackageMeta(PDO $pdo, string $packageName) {
    $sql = <<<SQL
        SELECT repo, name, version, description, url
        FROM package_meta
        WHERE name = :name
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue("name", $packageName, PDO::PARAM_STR);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getComponent(PDO $pdo, string $componentName) {
    $sql = <<<SQL
        SELECT name, is_virtual
        FROM components
        WHERE name = :name
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue("name", $componentName, PDO::PARAM_STR);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRelation(PDO $pdo, string $packageName, string $relation, bool $withDesc) {
    $maybeDesc = $withDesc ? ', pr.relation_description' : '';
    
    $sql = <<<SQL
        SELECT
            c.name,
            pr.version_expr,
            c.is_virtual,
            COALESCE(p2.description, p3.description) AS description,
            p.repo
            $maybeDesc
        FROM package_relations pr
        JOIN components c ON pr.component_id = c.id
        JOIN package_meta p ON pr.package_id = p.id

        LEFT JOIN package_meta p2 
            ON p2.name = c.name 
        AND p2.repo = p.repo

        LEFT JOIN package_meta p3 
            ON p3.name = c.name

        WHERE p.name = :name
        AND c.name != p.name
        AND pr.relation_type = :relation;
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue("name", $packageName, PDO::PARAM_STR);
    $stmt->bindValue("relation", $relation, PDO::PARAM_STR);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getReverseRelation(PDO $pdo, string $componentName, string $relation, bool $withDesc) {
    $maybeDesc = $withDesc ? ', pr.relation_description' : '';
    
    $sql = <<<SQL
        SELECT DISTINCT p.name, pr.version_expr, p.description $maybeDesc
        FROM package_relations pr
        JOIN components c ON pr.component_id = c.id
        JOIN package_meta p ON pr.package_id = p.id
        WHERE c.name = :name AND c.name != p.name AND pr.relation_type = :relation;
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue("name", $componentName, PDO::PARAM_STR);
    $stmt->bindValue("relation", $relation, PDO::PARAM_STR);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDependencies(PDO $pdo, string $packageName, bool $withDesc = false): array {
    return getRelation($pdo, $packageName, 'DEPENDS', $withDesc);
}

function getOptDependencies(PDO $pdo, string $packageName, bool $withDesc = true): array {
    return getRelation($pdo, $packageName, 'OPTDEPENDS', $withDesc);
}

function getMakeDependencies(PDO $pdo, string $packageName, bool $withDesc = false): array {
    return getRelation($pdo, $packageName, 'MAKEDEPENDS', $withDesc);
}

function getProvides(PDO $pdo, string $packageName, bool $withDesc = false): array {
    return getRelation($pdo, $packageName, 'PROVIDES', $withDesc);
}

function getDependants(PDO $pdo, string $componentName, bool $withDesc = false): array {
    return getReverseRelation($pdo, $componentName, 'DEPENDS', $withDesc);
}

function getOptDependants(PDO $pdo, string $componentName, bool $withDesc = true): array {
    return getReverseRelation($pdo, $componentName, 'OPTDEPENDS', $withDesc);
}

function getMakeDependants(PDO $pdo, string $componentName, bool $withDesc = false): array {
    return getReverseRelation($pdo, $componentName, 'MAKEDEPENDS', $withDesc);
}

function getProviders(PDO $pdo, string $componentName, bool $withDesc = false): array {
    return getReverseRelation($pdo, $componentName, 'PROVIDES', $withDesc);
}

function getConflicts(PDO $pdo, string $packageName, bool $withDesc = false): array {
    $r1 = getRelation($pdo, $packageName, 'CONFLICTS', $withDesc);
    $r2 = getReverseRelation($pdo, $packageName, 'CONFLICTS', $withDesc);

    return array_merge($r1, $r2);
}

function getPackageFiles(PDO $pdo, string $packageName): array
{
    $result = [
        'EMPTY' => [],
        'ELFBIN' => [],
        'ELFLIB' => [],
        'CONF' => [],
        'SCRIPT' => [],
        'PACMAN_HOOK' => [],
        'TEXT' => [],
        'SYMLINK' => [],
        'DATA' => [],
        'OTHER' => [],
    ];

    $stmt = $pdo->prepare("
        SELECT 
            pf.id,
            pf.file_path,
            pf.file_type,
            pf.file_mode,
            pf.file_size,
            pm.repo
        FROM package_files pf
        JOIN package_meta pm ON pf.package_id = pm.id
        WHERE pm.name = :name
    ");
    $stmt->execute([':name' => $packageName]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allRepos = array_unique(array_column($files, 'repo'));
    sort($allRepos); // TODO: order by my taste C:

    if (empty($files)) {
        return $result;
    }

    $fileIds = array_column($files, 'id');

    $elfbinData = [];
    if (!empty($fileIds)) {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $elfStmt = $pdo->prepare("
            SELECT * FROM package_file_elfbin 
            WHERE file_id IN ($placeholders)
        ");
        $elfStmt->execute($fileIds);
        while ($row = $elfStmt->fetch(PDO::FETCH_ASSOC)) {
            $elfbinData[$row['file_id']] = [
                'is_static' => (bool) $row['is_static'],
                'is_dynamic' => (bool) $row['is_dynamic'],
                'binary_cxx' => (bool) $row['binary_cxx'],
                'binary_go' => (bool) $row['binary_go'],
                'binary_rust' => (bool) $row['binary_rust'],
                'compression_bzip2' => (bool) $row['compression_bzip2'],
                'compression_lzma' => (bool) $row['compression_lzma'],
                'compression_zlib' => (bool) $row['compression_zlib'],
                'directory_create' => (bool) $row['directory_create'],
                'directory_read' => (bool) $row['directory_read'],
                'directory_remove' => (bool) $row['directory_remove'],
                'embeds_lua' => (bool) $row['embeds_lua'],
                'embeds_python' => (bool) $row['embeds_python'],
                'execution_deamonizes' => (bool) $row['execution_deamonizes'],
                'execution_debugs' => (bool) $row['execution_debugs'],
                'execution_does' => (bool) $row['execution_does'],
                'file_create' => (bool) $row['file_create'],
                'file_create_temporary' => (bool) $row['file_create_temporary'],
                'file_delete' => (bool) $row['file_delete'],
                'file_read' => (bool) $row['file_read'],
                'file_rename' => (bool) $row['file_rename'],
                'file_write' => (bool) $row['file_write'],
                'kernel_device_interaction' => (bool) $row['kernel_device_interaction'],
                'kernel_event_io' => (bool) $row['kernel_event_io'],
                'kernel_syscall' => (bool) $row['kernel_syscall'],
                'memory_map' => (bool) $row['memory_map'],
                'memory_shm' => (bool) $row['memory_shm'],
                'metadata_modify' => (bool) $row['metadata_modify'],
                'metadata_query' => (bool) $row['metadata_query'],
                'networking_dns' => (bool) $row['networking_dns'],
                'networking_has' => (bool) $row['networking_has'],
                'networking_http' => (bool) $row['networking_http'],
                'networking_server' => (bool) $row['networking_server'],
                'networking_tls' => (bool) $row['networking_tls'],
                'networking_udp' => (bool) $row['networking_udp'],
                'privilege_changes' => (bool) $row['privilege_changes'],
                'supports_audio' => (bool) $row['supports_audio'],
                'supports_cryptography' => (bool) $row['supports_cryptography'],
                'supports_encoding_conversion' => (bool) $row['supports_encoding_conversion'],
                'supports_images' => (bool) $row['supports_images'],
                'supports_localization' => (bool) $row['supports_localization'],
                'supports_unicode' => (bool) $row['supports_unicode'],
                'suspicious_loader_manipulation' => (bool) $row['suspicious_loader_manipulation'],
                'suspicious_sandboxing' => (bool) $row['suspicious_sandboxing'],
                'suspicious_self_memory_access' => (bool) $row['suspicious_self_memory_access'],
                'system_env_vars' => (bool) $row['system_env_vars'],
                'system_info_detect' => (bool) $row['system_info_detect'],
                'system_performance' => (bool) $row['system_performance'],
                'system_user_awareness' => (bool) $row['system_user_awareness'],
                'thread_sync' => (bool) $row['thread_sync'],
                'thread_use' => (bool) $row['thread_use'],
            ];
        }
    }

    $confUsersData = [];
    if (!empty($fileIds)) {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $confStmt = $pdo->prepare("
            SELECT file_id, conf_user FROM package_file_conf_users 
            WHERE file_id IN ($placeholders)
        ");
        $confStmt->execute($fileIds);
        while ($row = $confStmt->fetch(PDO::FETCH_ASSOC)) {
            $confUsersData[$row['file_id']] = $row['conf_user'];
        }
    }

    $scriptData = [];
    if (!empty($fileIds)) {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $scriptStmt = $pdo->prepare("
            SELECT file_id, script_executable FROM package_file_script 
            WHERE file_id IN ($placeholders)
        ");
        $scriptStmt->execute($fileIds);
        while ($row = $scriptStmt->fetch(PDO::FETCH_ASSOC)) {
            $scriptData[$row['file_id']] = $row['script_executable'];
        }
    }

    $pacmanHookData = [];
    if (!empty($fileIds)) {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $hookStmt = $pdo->prepare("
            SELECT file_id, action_description, action_when 
            FROM package_file_pacman_hook 
            WHERE file_id IN ($placeholders)
        ");
        $hookStmt->execute($fileIds);
        while ($row = $hookStmt->fetch(PDO::FETCH_ASSOC)) {
            $pacmanHookData[$row['file_id']] = [
                'action_description' => $row['action_description'],
                'action_when' => $row['action_when'],
                'triggers' => [],
            ];
        }

        if (!empty($pacmanHookData)) {
            $hookFileIds = array_keys($pacmanHookData);
            $triggerPlaceholders = implode(',', array_fill(0, count($hookFileIds), '?'));
            $triggerStmt = $pdo->prepare("
                SELECT 
                    id,
                    file_id,
                    trigger_type,
                    trigger_on_install,
                    trigger_on_upgrade,
                    trigger_on_remove
                FROM package_file_pacman_hook_triggers 
                WHERE file_id IN ($triggerPlaceholders)
            ");
            $triggerStmt->execute($hookFileIds);
            
            $triggerIds = [];
            $triggerMap = [];
            while ($row = $triggerStmt->fetch(PDO::FETCH_ASSOC)) {
                $triggerId = (int) $row['id'];
                $fileId = (int) $row['file_id'];
                $triggerIds[] = $triggerId;
                $triggerMap[$triggerId] = $fileId;
                
                $pacmanHookData[$fileId]['triggers'][] = [
                    'id' => $triggerId,
                    'trigger_type' => $row['trigger_type'],
                    'trigger_on_install' => (bool) $row['trigger_on_install'],
                    'trigger_on_upgrade' => (bool) $row['trigger_on_upgrade'],
                    'trigger_on_remove' => (bool) $row['trigger_on_remove'],
                    'targets' => [],
                ];
            }

            if (!empty($triggerIds)) {
                $targetPlaceholders = implode(',', array_fill(0, count($triggerIds), '?'));
                $targetStmt = $pdo->prepare("
                    SELECT trigger_id, trigger_target 
                    FROM pacman_hook_trigger_targets 
                    WHERE trigger_id IN ($targetPlaceholders)
                ");
                $targetStmt->execute($triggerIds);
                
                $targetMap = [];
                while ($row = $targetStmt->fetch(PDO::FETCH_ASSOC)) {
                    $tid = (int) $row['trigger_id'];
                    if (!isset($targetMap[$tid])) {
                        $targetMap[$tid] = [];
                    }
                    $targetMap[$tid][] = $row['trigger_target'];
                }

                foreach ($pacmanHookData as $fileId => &$hook) {
                    foreach ($hook['triggers'] as &$trigger) {
                        $tid = $trigger['id'];
                        if (isset($targetMap[$tid])) {
                            $trigger['targets'] = $targetMap[$tid];
                        }
                        unset($trigger['id']);
                    }
                }
            }
        }
    }

    $symlinkData = [];
    if (!empty($fileIds)) {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $symlinkStmt = $pdo->prepare("
            SELECT file_id, link_target FROM package_file_symlinks 
            WHERE file_id IN ($placeholders)
        ");
        $symlinkStmt->execute($fileIds);
        while ($row = $symlinkStmt->fetch(PDO::FETCH_ASSOC)) {
            $symlinkData[$row['file_id']] = $row['link_target'];
        }
    }

    $grouped = [];

    foreach ($files as $file) {
        $path = $file['file_path'];

        if (!isset($grouped[$path])) {
            $grouped[$path] = [
                'base' => $file,
                'repos' => [],
                'variants' => [],
            ];
        }

        $grouped[$path]['repos'][] = $file['repo'];
        $grouped[$path]['variants'][] = $file;
    }

    foreach ($grouped as $entry) {
        $file = $entry['base'];
        $fileId = (int)$file['id'];
        $fileType = $file['file_type'];

        $repos = array_unique($entry['repos']);
        sort($repos); // TODO: same here

        $repoInfo = null;
        if (count($repos) !== count($allRepos)) {
            $repoInfo = $repos;
        }

        $fileEntry = [
            'id' => $fileId,
            'file_path' => $file['file_path'],
            'file_mode' => (int)$file['file_mode'],
            'file_size' => (int)$file['file_size'],
            'repo_info' => $repoInfo,
        ];

        switch ($fileType) {
            case 'ELFBIN':
            case 'ELFLIB':
                if (isset($elfbinData[$fileId])) {
                    $fileEntry['elf_details'] = $elfbinData[$fileId];
                }
                break;

            case 'CONF':
                if (isset($confUsersData[$fileId])) {
                    $fileEntry['conf_user'] = $confUsersData[$fileId];
                }
                break;

            case 'SCRIPT':
                if (isset($scriptData[$fileId])) {
                    $fileEntry['script_executable'] = $scriptData[$fileId];
                }
                break;

            case 'PACMAN_HOOK':
                if (isset($pacmanHookData[$fileId])) {
                    $fileEntry['hook_details'] = $pacmanHookData[$fileId];
                }
                break;

            case 'SYMLINK':
                if (isset($symlinkData[$fileId])) {
                    $fileEntry['link_target'] = $symlinkData[$fileId];
                }
                break;

            case 'EMPTY':
            case 'TEXT':
            case 'DATA':
            case 'OTHER':
                break;
        }

        $result[$fileType][] = $fileEntry;
    }

    return $result;
}
