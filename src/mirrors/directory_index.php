<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>NeoArch Linux Docs</title>
    <link rel="stylesheet" href="/index.css">
    <style>
        main {
            padding: 2rem;
        }

        table {
            width: 100%;
            table-layout: auto;
            border-collapse: collapse;
            font-family: monospace;
        }

        td:first-child {
            text-align: left;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        td:nth-child(2),
        td:nth-child(3) {
            text-align: right;
            white-space: nowrap;
        }

        td img {
            vertical-align: middle;
        }

        td a:link,
        td a:visited,
        td a:hover,
        td a:active {
            color: var(--accent-secondary);
            text-decoration: none;
        }
    </style>
</head>
<body>

<?php

require_once '/var/www/src/header.php';

function icon_for($filePath) {
    if (is_dir($filePath)) return '/icons/directory.svg';

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    return match($ext) {
        'tar', 'zst', 'tar.zst', 'zip', 'gz', 'bz2' => '/icons/archive.svg',
        'sig' => '/icons/signature.svg',
        'db', 'files', 'links' => '/icons/database.svg',
        default => '/icons/file.svg'
    };
}

$uri = $_GET['uri'] ?? '/';
$path = realpath('/var/mirrors' . $uri);

if (!$path || !is_dir($path)) {
    http_response_code(404);
    exit;
}

$entries = scandir($path);

echo "<main><h1>Index of $uri</h1><table><tbody>";
foreach ($entries as $f) {
    if ($f === '.') continue;
    if ($f === '..' && $uri === '/') continue;

    $fullPath = $path . DIRECTORY_SEPARATOR . $f;
    $link = rtrim($uri, '/') . '/' . $f;

    $symlinkText = '';
    if (is_link($fullPath)) {
        $target = readlink($fullPath);
        $symlinkText = "<small>&nbsp;->&nbsp;$target</small>";
    }

    $icon = icon_for($fullPath);

    $mtime = date("Y-m-d H:i:s", filemtime($fullPath));

    if (is_dir($fullPath)) {
        $size = (int) trim(shell_exec("du -sb " . escapeshellarg($fullPath) . " | cut -f1"));
    } else {
        $size = filesize($fullPath);
    }

    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units)-1) { $size /= 1024; $i++; }
    $sizeStr = sprintf("%.1f %s", $size, $units[$i]);

    echo "<tr>";
    echo "<td><img src='$icon' alt='' style='width:16px;height:16px;margin-right:0.5rem'> <a href='$link'>$f</a>$symlinkText</td>";
    echo "<td>$mtime</td>";
    echo "<td style='text-align:right'>$sizeStr</td>";
    echo "</tr>";
}

echo "</tbody></table></main>";

?>

</body>
</html>
