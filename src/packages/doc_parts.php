<?php
declare(strict_types=1);

function getRepoOwner(string $repo) : string {
    $repoOwners = [
        'matrix' => 'NeoArch',
        'system' => 'Artix',
        'world' => 'Artix',
        'galaxy' => 'Artix',
        'lib32' => 'Artix',
        'core' => 'Arch',
        'extra' => 'Arch',
        'multilib' => 'Arch',
    ];

    return $repoOwners[$repo];
}

function parseVersionExpr(?string $expr): ?string {
    if ($expr === null || $expr === '') {
        return null;
    }

    if (preg_match('/^(>=|<=|=|>|<)\s*(.+)$/', $expr, $m)) {
        [$_, $op, $ver] = $m;

        return match ($op) {
            '>=' => "minimal version $ver",
            '>'  => "newer than version $ver",
            '<=' => "at most version $ver",
            '<'  => "older than version $ver",
            '='  => "exactly version $ver",
        };
    }

    return "version $expr";
}

function renderComponent(array $component): void {
    $maybeVirtual = '';
    if ($component['is_virtual']) {
        $maybeVirtual = ' class="virtual"';
    }

    echo '<a href="/' . htmlspecialchars($component['name']) . '"' . $maybeVirtual . '>' . htmlspecialchars($component['name']) . '</a>';

    if (!empty($component['version_expr'])) {
        $parsed = parseVersionExpr($component['version_expr']);
        if ($parsed !== null) {
            echo ' (' . htmlspecialchars($parsed) . ')';
        }
    }

    if (!empty($component['relation_description'])) {
        echo ' &mdash; (' . htmlspecialchars($component['relation_description']) . ')';
    }
    
    if (!empty($component['description'])) {
        echo ' &mdash; ' . htmlspecialchars($component['description']);
    }
}

function renderPackageMeta(array $metaRows): void {
    if (empty($metaRows)) return;

    $name = $metaRows[0]['name'];

    $repos = [];
    foreach ($metaRows as $row) {
        $repos[] = [
            'repo' => $row['repo'],
            'version' => $row['version'],
            'description' => $row['description'],
            'url' => $row['url'],
        ];
    }

    $descriptions = array_unique(array_column($repos, 'description'));
    $urls = array_unique(array_column($repos, 'url'));

    echo '<section class="package-meta">';
    echo '<h1>' . htmlspecialchars($name) . '</h1>';

    if (count($descriptions) === 1) {
        echo '<p><strong>Description</strong>: ' . htmlspecialchars($descriptions[0]) . '</p>';
    } else {
        echo '<p><strong>Descriptions</strong>:</p><ul>';
        foreach ($repos as $r) {
            echo '<li><strong>' . getRepoOwner(htmlspecialchars($r['repo'])) . '\'s ' . ':</strong> '
               . htmlspecialchars($r['description']) . '</li>';
        }
        echo '</ul>';
    }

    echo '<p><strong>Available in:</strong> ';
    echo implode(', ', array_map( // TODO: link to https://docs.$DOMAIN/repository/$r['repo'] when page created
        fn($r) => htmlspecialchars(getRepoOwner($r['repo'])) . '\'s ' . htmlspecialchars($r['repo']) . ' (' . htmlspecialchars($r['version']) . ')',
        $repos
    ));
    echo '</p>';

    if (count($urls) === 1) {
        echo '<p><strong>URL</strong>: <a href="' . htmlspecialchars($urls[0]) . '">' . htmlspecialchars($urls[0]) . '</a></p>';
    } else {
        echo '<p><strong>URLs</strong>:</p><ul>';
        foreach ($repos as $r) {
            echo '<li><strong>' . getRepoOwner(htmlspecialchars($r['repo'])) . '\'s ' . ':</strong> '
                . '<a href="' . htmlspecialchars($r['url']) . '">' . htmlspecialchars($r['url']) . '</a></li>';
        }
        echo '</ul>';
    }

    echo '</section>';
}

function renderRelationList(string $title, array $items): void {
    if (empty($items)) return;

    echo '<section>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<ul>';

    foreach ($items as $item) {
        echo '<li>';

        renderComponent($item);

        echo '</li>';
    }

    echo '</ul>';
    echo '</section>';
}

function renderDependencies(array $info): void {
    renderRelationList('Dependencies', $info['dependencies'] ?? []);
    renderRelationList('Optional Dependencies', $info['opt_dependencies'] ?? []);
    renderRelationList('Build-time Dependencies', $info['make_dependencies'] ?? []);
}

function renderDependants(array $info): void {
    renderRelationList('Dependants', $info['dependants'] ?? []);
    renderRelationList('Optional Dependants', $info['opt_dependants'] ?? []);
    renderRelationList('Build-time Dependants', $info['make_dependants'] ?? []);
}

function renderProvides(array $info): void {
    renderRelationList('Provides', $info['provides'] ?? []);
}

function renderProviders(array $info): void {
    renderRelationList('Providers', $info['providers'] ?? []);
}

function renderConflicts(array $info): void {
    renderRelationList('Conflicts', $info['conflicts'] ?? []);
}