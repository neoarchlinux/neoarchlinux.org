<?php
declare(strict_types=1);

require_once __DIR__ . '/utils.php';

function getStagingPath(string $base, string $repo, string $arch, string $fileName): string {
    $dir = "$base/$repo/$arch";
    ensureDir($dir);
    return "$dir/$fileName";
}

function getFinalDir(string $base, string $repo, string $arch): string {
    $dir = rtrim($base, '/') . '/' . $repo . '/os/' . $arch;
    return $dir;
}
