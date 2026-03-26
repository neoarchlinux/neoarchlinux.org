<?php
declare(strict_types=1);

require_once __DIR__ . '/doc_parts.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package <?= htmlspecialchars($packageInfo['meta'][0]['name']) ?></title>
    <link rel="stylesheet" href="/index.css">
    <style>
        main {
            padding: 2rem;
        }

        main a:link,
        main a:visited,
        main a:hover,
        main a:active {
            color: var(--accent-secondary);
            text-decoration: none;
        }

        main a.virtual {
            color: var(--accent-ternary);
        }

        .file-header {
            font-family: monospace;
        }

        main .file-header a {
            color: var(--primary);
            text-decoration: underline;
        }
    </style>
</head>
<body>

<?php require_once '/var/www/src/header.php'; ?>

<main>

<?php

renderPackageMeta($packageInfo['meta']);

renderFiles($packageInfo['meta'], $packageFiles);

renderDependencies($packageInfo);
renderProvides($packageInfo);

renderDependants($packageInfo);
renderProviders($packageInfo);

renderConflicts($packageInfo);

?>

</main>

</body>
</html>