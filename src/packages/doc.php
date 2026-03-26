<?php
declare(strict_types=1);

require_once '/var/www/src/db.php';
require_once '/var/www/src/utils.php';

$component_name = $_GET['component_name'];
$pdo = getPdo();

function groupRelations(array $rows): array {
    $grouped = [];

    foreach ($rows as $row) {
        $key = $row['name'] . '|' . ($row['version_expr'] ?? '') . '|' . ($row['is_virtual'] ?? 0);

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'name' => $row['name'],
                'version_expr' => $row['version_expr'],
                'is_virtual' => $row['is_virtual'],
                'descriptions' => [],
            ];
        }

        $grouped[$key]['descriptions'][$row['repo']] = $row['description'];
    }

    return array_values($grouped);
}

function fetchRelations(PDO $pdo, string $name, array $relationMap): array {
    $res = [];

    foreach ($relationMap as $key => $fn) {
        $data = $fn($pdo, $name);

        if (!empty($data)) {
            $res[$key] = groupRelations($data);
        }
    }

    return $res;
}

$forward = [
    'dependencies'    => 'getDependencies',
    'opt_dependencies'=> 'getOptDependencies',
    'make_dependencies'=> 'getMakeDependencies',
    'provides'        => 'getProvides',
];

$reverse = [
    'dependants'      => 'getDependants',
    'opt_dependants'  => 'getOptDependants',
    'make_dependants' => 'getMakeDependants',
    'providers'       => 'getProviders',
];

$packageMeta = getPackageMeta($pdo, $component_name);
if (!empty($packageMeta)) {
    $packageInfo = ['meta' => $packageMeta];

    $conflicts = getConflicts($pdo, $component_name);
    if (!empty($conflicts)) $packageInfo['conflicts'] = $conflicts;

    $packageInfo += fetchRelations($pdo, $component_name, $forward);
    $packageInfo += fetchRelations($pdo, $component_name, $reverse);

    $packageFiles = getPackageFiles($pdo, $component_name);

    require_once __DIR__ . '/doc_package.php';
    exit;
}

$component = getComponent($pdo, $component_name);
if (!empty($component)) {
    $componentInfo = ['component' => $component];

    $conflicts = getConflicts($pdo, $component_name);
    if (!empty($conflicts)) $componentInfo['conflicts'] = $conflicts;

    $componentInfo += fetchRelations($pdo, $component_name, $reverse);

    require_once __DIR__ . '/doc_component.php';
    exit;
}

http_response_code(404);
