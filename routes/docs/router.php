<?php

require_once '/var/www/lib/parsedown/Parsedown.php';

$parsedown = new Parsedown();
$parsedown->setSafeMode(true);

$base = __DIR__;
$path = trim($_GET['path'] ?? '', '/');
$file = realpath("$base/$path.md");
$index = realpath("$base/$path/index.md");

if (
    ($file && str_starts_with($file, $base)) ||
    ($index && str_starts_with($index, $base))
) {
    $target = $file ?: $index;
} else {
    http_response_code(404);
    exit;
}

$content = file_get_contents($target);
$html = $parsedown->text($content);

preg_match('/^# (.+)$/m', $content, $matches);
$title = $matches[1] ?? 'Docs';

$seenIds = [];
$html = preg_replace_callback(
    '/<(h[2-6])>(.*?)<\/\1>/s',
    function ($m) use (&$seenIds) {
        $tag = $m[1];
        $inner = $m[2];
        $id = slugify(strip_tags($inner));
        if (isset($seenIds[$id])) {
            $id .= '-' . (++$seenIds[$id]);
        } else {
            $seenIds[$id] = 0;
        }
        return "<{$tag} id=\"{$id}\">{$inner}</{$tag}>";
    },
    $html
);

$toc = extractHeadings($content);

$pages = getPages($base, $base);
$pageKeys = array_column($pages, 'path');
$pageIdx = array_search($path, $pageKeys, true);
$prevPage = ($pageIdx !== false && $pageIdx > 0) ? $pages[$pageIdx - 1] : null;
$nextPage = ($pageIdx !== false && $pageIdx < count($pages) - 1) ? $pages[$pageIdx + 1] : null;

function getDocTitle(string $filePath): string
{
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return basename($filePath, '.md');
    }

    $firstLine = fgets($handle);
    fclose($handle);

    if ($firstLine === false) {
        return basename($filePath, '.md');
    }

    // Strip leading "# " and any BOM
    $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
    $title = preg_replace('/^#\s+/', '', trim($firstLine));

    return $title ?: basename($filePath, '.md');
}

function slugify(string $text): string
{
    $text = preg_replace('/`[^`]*`|\*{1,2}([^*]*)\*{1,2}|_{1,2}([^_]*)_{1,2}/', '$1$2', $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    return preg_replace('/[\s-]+/', '-', trim($text));
}

function extractHeadings(string $content): array
{
    $headings = [];
    $seen = [];
    preg_match_all('/^(#{2,6})\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $text = trim($m[2]);
        $id = slugify($text);
        if (isset($seen[$id])) {
            $id .= '-' . (++$seen[$id]);
        } else {
            $seen[$id] = 0;
        }
        $headings[] = ['level' => strlen($m[1]), 'text' => $text, 'id' => $id];
    }
    return $headings;
}

function renderToc(array $toc): void
{
    if (empty($toc)) {
        return;
    }
    echo '<ul class="toc-list">';
    foreach ($toc as $h) {
        $id = htmlspecialchars($h['id']);
        $text = htmlspecialchars(preg_replace('/`([^`]*)`|\*{1,2}([^*]*)\*{1,2}/', '$1$2', $h['text']));
        $indent = ($h['level'] - 2) * 12;
        echo "<li style=\"padding-left:{$indent}px\"><a href=\"#{$id}\">{$text}</a></li>";
    }
    echo '</ul>';
}

function getPages(string $dir, string $base): array
{
    $pages = [];

    $indexFile = "$dir/index.md";
    if (file_exists($indexFile)) {
        $rel = ltrim(str_replace($base, '', $dir), '/');
        $pages[] = [
            'path' => $rel,
            'file' => $indexFile,
            'title' => getDocTitle($indexFile),
        ];
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $full = "$dir/$file";
        $rel = ltrim(str_replace($base, '', $full), '/');

        if (is_dir($full)) {
            $pages = array_merge($pages, getPages($full, $base));
        } elseif (str_ends_with($file, '.md') && $file !== 'index.md') {
            $urlRel = substr($rel, 0, -3);
            $pages[] = [
                'path' => $urlRel,
                'file' => $full,
                'title' => getDocTitle($full),
            ];
        }
    }

    return $pages;
}

function renderTree(string $dir, string $base, string $currentPath, array $toc = []): void
{
    $files = scandir($dir);

    echo '<ul>';

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $full = "$dir/$file";
        $rel = ltrim(str_replace($base, '', $full), '/');

        if (is_dir($full)) {
            $indexFile = "$full/index.md";
            $hasIndex = file_exists($indexFile);

            if ($hasIndex) {
                $url = '/' . $rel;
                $isIndexActive = ($currentPath === $rel || $currentPath === "$rel/index");
                $isActive = $isIndexActive || str_starts_with($currentPath, $rel . '/');
                $active = $isActive ? 'class="active"' : '';
                $name = getDocTitle($indexFile);

                echo "<li><a href=\"$url\" $active>" . htmlspecialchars($name) . '</a>';
                if ($isIndexActive) {
                    renderToc($toc);
                }
                renderTree($full, $base, $currentPath, $toc);
                echo '</li>';
            } else {
                echo '<li><strong>' . htmlspecialchars($file) . '</strong>';
                renderTree($full, $base, $currentPath, $toc);
                echo '</li>';
            }
        } elseif (str_ends_with($file, '.md')) {
            if ($file === 'index.md') {
                // Suppressed: rendered as its parent directory link
                continue;
            }

            $urlRel = substr($rel, 0, -3); // strip .md
            $url = '/' . $urlRel;
            $isActive = ($currentPath === $urlRel);
            $active = $isActive ? 'class="active"' : '';
            $name = getDocTitle($full);

            echo "<li><a href=\"$url\" $active>" . htmlspecialchars($name) . '</a>';
            if ($isActive) {
                renderToc($toc);
            }
            echo '</li>';
        }
    }

    echo '</ul>';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="NeoArch Linux Docs - <?= htmlspecialchars($title) ?>">
    <title><?= htmlspecialchars($title) ?> | NeoArch Docs</title>
    <link rel="stylesheet" href="/index.css">
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        .container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        aside {
            width: 280px;
            min-width: 280px;
            height: 100%;
            overflow-y: auto;
            padding: 24px;
            border-right: 1px solid white;
        }

        aside ul {
            list-style: '↳';
            padding: 0;
            margin: 0;
        }

        aside ul ul {
            padding-left: 14px;
            margin-top: 4px;
        }

        aside li {
            margin-bottom: 2px;
            line-height: 1.4;
        }

        aside li strong {
            display: block;
            padding: 4px 0;
            font-weight: 600;
        }

        aside a,
        aside a:visited {
            text-decoration: none;
            color: var(--accent-secondary);
            display: block;
            padding: 4px 0;
        }

        aside a.active {
            color: var(--accent-ternary);
            font-weight: bold;
        }

        main {
            text-align: justify;
            flex: 1;
            height: 100%;
            overflow-y: auto;
            padding: 24px 48px 64px;
        }

        main a,
        main a:visited {
            text-decoration: none;
            color: var(--accent-secondary);
        }

        .doc-nav {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin: 24px 0;
            padding: 12px 0;
            border-top: 1px solid rgba(205, 214, 244, 0.15);
            border-bottom: 1px solid rgba(205, 214, 244, 0.15);
        }

        .doc-nav a {
            color: var(--accent-secondary);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .doc-nav a:hover {
            text-decoration: underline;
        }

        .doc-nav .nav-next {
            margin-left: auto;
        }

        main img {
            max-width: 100%;
            height: auto;
        }

        .toc-list {
            list-style: none;
            margin-top: 4px;
        }

        .toc-list li a {
            font-size: 0.82rem;
            opacity: 0.8;
        }

        .toc-list li a:hover {
            opacity: 1;
        }
    </style>
</head>

<body>
    <?php require_once '/var/www/src/header.php'; ?>

    <div class="container">
        <aside>
            <a href="/" class="active">NeoArch Documentation</a>
            <ul>
                <?php renderTree($base, $base, $path, $toc); ?>
            </ul>
        </aside>
        <main>
            <?php
            $navHtml = '<nav class="doc-nav">';
            if ($prevPage) {
                $prevUrl = '/' . $prevPage['path'];
                $prevTitle = htmlspecialchars($prevPage['title']);
                $navHtml .= "<a href=\"{$prevUrl}\" class=\"nav-prev\">&#8592; Prev - {$prevTitle}</a>";
            }
            if ($nextPage) {
                $nextUrl = '/' . $nextPage['path'];
                $nextTitle = htmlspecialchars($nextPage['title']);
                $navHtml .= "<a href=\"{$nextUrl}\" class=\"nav-next\">Next - {$nextTitle} &#8594;</a>";
            }
            $navHtml .= '</nav>';

            $showNav = $prevPage || $nextPage;
            if ($showNav)
                echo $navHtml;
            ?>
            <?= $html ?>
            <?php if ($showNav)
                echo $navHtml; ?>
        </main>
    </div>
</body>

</html>