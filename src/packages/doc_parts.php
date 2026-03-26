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
    echo '<h2>' . htmlspecialchars($title) . ' (' . count($items) . ')</h2>';
    echo '<ul>';

    foreach ($items as $item) {
        echo '<li>';

        renderComponent($item);

        $descs = array_unique(array_filter($item['descriptions']));

        if (!empty($descs)) {
            if (count($descs) === 1) {
                echo ' &mdash; ';
                echo htmlspecialchars(reset($descs));
            } else {
                echo '<br/>';

                foreach ($item['descriptions'] as $repo => $desc) {
                    echo '&nbsp;&nbsp;&nbsp;&nbsp;&mdash; ';
                    echo htmlspecialchars($desc);
                    echo ' <span style="color:#888;">(' . getRepoOwner(htmlspecialchars($repo)) . '\'s ' . htmlspecialchars($repo) . ')</span>';
                    echo '<br/>';
                }
            }
        }

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

function renderFileHeader(array $file): void {
    echo '<span class="file-header">';

    $m = $file['file_mode'];
    echo sprintf(
        '%s%s%s%s%s%s%s%s%s',

        ndigit($m, 1) & 4 ? 'r' : '-',
        ndigit($m, 1) & 2 ? 'w' : '-',
        ndigit($m, 1) & 1 ? 'x' : '-',

        ndigit($m, 2) & 4 ? 'r' : '-',
        ndigit($m, 2) & 2 ? 'w' : '-',
        ndigit($m, 2) & 1 ? 'x' : '-',

        ndigit($m, 3) & 4 ? 'r' : '-',
        ndigit($m, 3) & 2 ? 'w' : '-',
        ndigit($m, 3) & 1 ? 'x' : '-'
    );

    echo '&nbsp;';

    echo '<a href="#file-' . $file['id'] . '">' . htmlspecialchars($file['file_path']) . '</a>';
    
    if (!empty($file['repo_info'])) {
        echo ' <span class="repo-note" style="color: #888;">';

        echo '(only in ' . implode(', ', array_map( // TODO: link to https://docs.$DOMAIN/repository/$r when page created
            fn($r) => htmlspecialchars(getRepoOwner($r)) . '\'s ' . htmlspecialchars($r),
            $file['repo_info']
        )) . ')';

        echo '</span>';
    }

    echo '</span>';
}

function describeElfBinary(array $elf): string {
    $sentences = [];

    if ($elf['is_static'] && $elf['is_dynamic']) {
        $sentences[] = 'This is a hybrid static/dynamic binary';
    } elseif ($elf['is_static']) {
        $sentences[] = 'This is a statically linked binary';
    } elseif ($elf['is_dynamic']) {
        $sentences[] = 'This is a dynamically linked binary';
    } else {
        $sentences[] = 'This is an ELF binary';
    }

    // $langs = [];
    // if ($elf['binary_cxx']) $langs[] = 'C++';
    // if ($elf['binary_go']) $langs[] = 'Go';
    // if ($elf['binary_rust']) $langs[] = 'Rust';
    // if (!empty($langs)) {
    //     $sentences[] = 'It was written in ' . implode(' and ', $langs);
    // }

    // $compressions = [];
    // if ($elf['compression_bzip2']) $compressions[] = 'bzip2';
    // if ($elf['compression_lzma']) $compressions[] = 'LZMA';
    // if ($elf['compression_zlib']) $compressions[] = 'zlib';
    // if (!empty($compressions)) {
    //     $sentences[] = 'It is compressed with ' . implode(' and ', $compressions);
    // }

    $embeds = [];
    if ($elf['embeds_lua']) $embeds[] = 'Lua';
    if ($elf['embeds_python']) $embeds[] = 'Python';
    if (!empty($embeds)) {
        $sentences[] = 'It embeds a ' . implode(' and ', $embeds) . ' runtime';
    }

    $fileOps = [];
    if ($elf['file_create']) $fileOps[] = 'creates';
    // if ($elf['file_create_temporary']) $fileOps[] = 'creates temporary';
    if ($elf['file_read']) $fileOps[] = 'reads';
    if ($elf['file_write']) $fileOps[] = 'writes to';
    if ($elf['file_delete']) $fileOps[] = 'deletes';
    if ($elf['file_rename']) $fileOps[] = 'renames';
    if (!empty($fileOps)) {
        $sentences[] = 'It ' . joinWithAnd($fileOps) . ' files';
    }

    $dirOps = [];
    if ($elf['directory_create']) $dirOps[] = 'creates';
    if ($elf['directory_read']) $dirOps[] = 'reads';
    if ($elf['directory_remove']) $dirOps[] = 'removes';
    if (!empty($dirOps)) {
        $sentences[] = 'It ' . joinWithAnd($dirOps) . ' directories';
    }

    if ($elf['networking_has']) {
        $netCaps = [];
        if ($elf['networking_dns']) $netCaps[] = 'DNS resolution';
        if ($elf['networking_http']) $netCaps[] = 'HTTP';
        if ($elf['networking_tls']) $netCaps[] = 'TLS/SSL';
        if ($elf['networking_udp']) $netCaps[] = 'UDP';
        if ($elf['networking_server']) $netCaps[] = 'creating a server';
        
        if (!empty($netCaps)) {
            $sentences[] = 'It performs networking operations including ' . joinWithAnd($netCaps);
        } else {
            $sentences[] = 'It performs networking operations';
        }
    }

    // $kernelOps = [];
    // if ($elf['kernel_syscall']) $kernelOps[] = 'system calls';
    // if ($elf['kernel_device_interaction']) $kernelOps[] = 'device interaction';
    // if ($elf['kernel_event_io']) $kernelOps[] = 'I/O event handling';
    // if (!empty($kernelOps)) {
    //     $sentences[] = 'It interacts with the kernel via ' . joinWithAnd($kernelOps);
    // }

    // $memOps = [];
    // if ($elf['memory_map']) $memOps[] = 'memory mapping';
    // if ($elf['memory_shm']) $memOps[] = 'shared memory';
    // if (!empty($memOps)) {
    //     $sentences[] = 'It uses ' . implode(' and ', $memOps);
    // }

    if ($elf['thread_use']) {
        if ($elf['thread_sync']) {
            $sentences[] = 'It uses multiple threads with synchronization';
        } else {
            $sentences[] = 'It uses multiple threads';
        }
    }

    $execBehaviors = [];
    if ($elf['execution_deamonizes']) $execBehaviors[] = 'daemonizes itself';
    if ($elf['execution_debugs']) $execBehaviors[] = 'contains debugging capabilities';
    if ($elf['execution_does']) $execBehaviors[] = 'performs execution';
    if (!empty($execBehaviors)) {
        $sentences[] = 'It ' . joinWithAnd($execBehaviors);
    }

    // $metaOps = [];
    // if ($elf['metadata_query']) $metaOps[] = 'queries metadata';
    // if ($elf['metadata_modify']) $metaOps[] = 'modifies metadata';
    // if (!empty($metaOps)) {
    //     $sentences[] = 'It ' . joinWithAnd($metaOps);
    // }

    $sysOps = [];
    if ($elf['system_env_vars']) $sysOps[] = 'accesses environment variables';
    if ($elf['system_info_detect']) $sysOps[] = 'detects system information';
    if ($elf['system_performance']) $sysOps[] = 'monitors performance';
    if ($elf['system_user_awareness']) $sysOps[] = 'is aware of the current user';
    if (!empty($sysOps)) {
        $sentences[] = 'It ' . joinWithAnd($sysOps);
    }

    if ($elf['privilege_changes']) {
        $sentences[] = 'It changes privileges during execution (<span style="color: yellow;">&#x26A0;</span>)';
    }

    $features = [];
    // if ($elf['supports_audio']) $features[] = 'audio';
    if ($elf['supports_cryptography']) $features[] = 'cryptography';
    if ($elf['supports_encoding_conversion']) $features[] = 'encoding conversion';
    if ($elf['supports_images']) $features[] = 'image processing';
    if ($elf['supports_localization']) $features[] = 'localization';
    if ($elf['supports_unicode']) $features[] = 'Unicode';
    if (!empty($features)) {
        $sentences[] = 'It supports ' . joinWithAnd($features);
    }

    $suspicious = [];
    if ($elf['suspicious_loader_manipulation']) $suspicious[] = 'loader manipulation';
    if ($elf['suspicious_sandboxing']) $suspicious[] = 'sandbox evasion';
    if ($elf['suspicious_self_memory_access']) $suspicious[] = 'self-modifying memory access';
    if (!empty($suspicious)) {
        $sentences[] = 'It exhibits suspicious behavior (<span style="color: yellow;">&#x26A0;</span>): ' . joinWithAnd($suspicious);
    }

    if (count($sentences) === 1) {
        return $sentences[0] . '.';
    }

    return implode('. ', $sentences) . '.';
}

function extractManpageName(string $repo, string $packageName, string $filePath): ?string {
    $basename = basename($filePath);
    
    $manSections = [1, 8];
    
    foreach ($manSections as $section) {
        $manPath = "/app/man/{$repo}/{$packageName}/man{$section}/{$basename}.{$section}.gz";
        
        if (!file_exists($manPath)) {
            $manPath = "/app/man/{$repo}/{$packageName}/man{$section}/{$basename}.gz";
        }
        
        if (!file_exists($manPath)) {
            continue;
        }
        
        $content = @file_get_contents('compress.zlib://' . $manPath);
        if ($content === false) {
            continue;
        }

        $name = parseManpageNameSection($content);
        if ($name !== null) {
            return $name;
        }
    }
    
    return null;
}

function parseManpageNameSection(string $content): ?string {
    $content = preg_replace('/^\.\\".*$/m', '', $content);
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    $patterns = [
        '/\.SH\s+NAME\s*\n(.*?)(?:\.SH|\.SS|$)/is',
        '/\.SH\s+"NAME"\s*\n(.*?)(?:\.SH|\.SS|$)/is',
        '/^NAME\s*\n(.*?)(?:^[A-Z][A-Z\s]*$|\Z)/ms',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $nameSection = $matches[1];
            return cleanManpageName($nameSection);
        }
    }
    
    return null;
}

function cleanManpageName(string $nameSection): ?string {
    $nameSection = preg_replace('/\.[A-Z]+\s*/', ' ', $nameSection);
    $nameSection = preg_replace('/\.[a-z]+\s*/', ' ', $nameSection);
    
    $nameSection = preg_replace('/\\\\[fFsS]\S/', '', $nameSection);
    $nameSection = str_replace(['\\-', '\\ '], ['-', ' '], $nameSection);
    
    $nameSection = preg_replace('/\s+/', ' ', $nameSection);
    $nameSection = trim($nameSection);
    
    $nameSection = preg_replace('/\bM\s+/', '', $nameSection);  // Remove bold start markers
    $nameSection = preg_replace('/\bm\s+/', ' ', $nameSection); // Remove bold end markers (keep space)
    $nameSection = preg_replace('/\bd\s+/', '- ', $nameSection); // Replace 'd ' with '- '
    
    $nameSection = preg_replace('/\s+/', ' ', trim($nameSection));
    
    if (($pos = strpos($nameSection, ' - ')) !== false) {
        $description = trim(substr($nameSection, $pos + 3));
        return ucfirst($description);
    }
    
    if (($pos = strpos($nameSection, '-')) !== false) {
        $before = substr($nameSection, max(0, $pos - 1), 1);
        $after = substr($nameSection, $pos + 1, 1);
        
        if ($before === ' ' || $after === ' ') {
            $description = trim(substr($nameSection, $pos + 1));
            return ucfirst($description);
        }
    }
    
    return null;
}

function renderFiles(array $metaRows, array $files): void {
    $files_elfbin = $files['ELFBIN'];
    $files_confs  = $files['CONF'];
    $files_script = $files['SCRIPT'];
    $files_hooks  = $files['PACMAN_HOOK'];
    $files_slink  = $files['SYMLINK'];

    $filesTotal = 0;

    $filesTotal += count($files_elfbin);
    $filesTotal += count($files_confs);
    $filesTotal += count($files_script);
    $filesTotal += count($files_hooks);
    $filesTotal += count($files_slink);

    if ($filesTotal === 0) return;

    echo '<section>';

    echo '<h2>Files</h2>';

    if (!empty($files_elfbin)) {
        echo '<h3>ELF executables (' . count($files_elfbin) . ')</h3>'; // TODO: link to docs
        echo '<ul>';
        foreach ($files_elfbin as $file) {
            echo '<li class="file" id="file-' . $file['id'] . '">';

            renderFileHeader($file);

            echo '<p style="text-align: justify;">';

            foreach ($metaRows as $row) {
                $manDescription = extractManpageName(
                    $row['repo'],
                    $row['name'],
                    $file['file_path']
                );

                if ($manDescription !== null) {
                    echo '<strong>' . htmlspecialchars($manDescription) . '</strong><br/><br/>';
                    break;
                }
            }

            echo describeElfBinary($file['elf_details']);
            
            echo '</p>';

            echo '</li>';
        }
        echo '</ul>';
    }

    if (!empty($files_confs)) {
        echo '<h3>Configuration files (' . count($files_confs) . ')</h3>'; // TODO: link to docs
        echo '<ul>';

        foreach ($files_confs as $file) {
            echo '<li class="file" id="file-' . $file['id'] . '">';
            
            renderFileHeader($file);
            
            echo '</li>';
        }

        echo '</ul>';
    }

    if (!empty($files_script)) {
        echo '<h3>Scripts (' . count($files_script) . ')</h3>'; // TODO: link to docs
        echo '<ul>';

        foreach ($files_script as $file) {
            echo '<li class="file" id="file-' . $file['id'] . '">';
            
            renderFileHeader($file);

            echo '<p style="text-align: justify;">';

            echo 'A ' . $file['script_executable'] . ' script';

            foreach ($metaRows as $row) {
                $manDescription = extractManpageName(
                    $row['repo'],
                    $row['name'],
                    $file['file_path']
                );

                if ($manDescription !== null) {
                    echo '<br/><br/><strong>' . htmlspecialchars($manDescription) . '</strong>';
                    break;
                }
            }
            
            echo '</p>';
            
            echo '</li>';
        }

        echo '</ul>';
    }

    if (!empty($files_hooks)) {
        echo '<h3>Pacman hooks (' . count($files_hooks) . ')</h3>'; // TODO: link to docs
        echo '<ul>';

        foreach ($files_hooks as $file) {
            echo '<li class="file" id="file-' . $file['id'] . '">';

            $hook = $file['hook_details'];

            renderFileHeader($file);

            if (!empty($hook['action_description'])) {
                $desc = htmlspecialchars($hook['action_description']);
                if (str_ends_with($desc, '...')) $desc = substr($desc, 0, strlen($desc) - 3);
                echo '<p><strong>' . $desc . '</strong></p>';
            }

            if (!empty($hook['triggers'])) {

                foreach ($hook['triggers'] as $trigger) {

                    $when = ($hook['action_when'] === 'PreTransaction') ? 'Before' : 'After';

                    $ops = [];
                    if (!empty($trigger['trigger_on_install'])) $ops[] = 'installing';
                    if (!empty($trigger['trigger_on_upgrade'])) $ops[] = 'upgrading';
                    if (!empty($trigger['trigger_on_remove'])) $ops[] = 'removing';

                    $ops_text = joinWithOr($ops);

                    $type = strtolower($trigger['trigger_type']) === 'path' ? 'file' : 'package';

                    $targets = array_map($type === 'package' ? function ($t) {
                        return '<a href="/' . $t . '">' . $t . '</a>';
                    } : function ($t) {
                        return '<span style="font-family: monospace">/' . htmlspecialchars($t) . '</span>';
                    }, $trigger['targets']);

                    $targets_text = joinWithOr($targets);

                    echo '<p>';
                    echo '<strong>When:</strong> ' . $when . ' ' . $ops_text;
                    
                    if (true) {
                        echo ' any of these ' . $type . 's: ' . $targets_text;
                    } else {

                    }

                    echo '</p>';
                }
            }

            echo '</li>';
        }

        echo '</ul>';
    }

    if (!empty($files_slink)) {
        echo '<h3>Symbolic links (' . count($files_slink) . ')</h3>'; // TODO: link to docs
        echo '<ul>';

        foreach ($files_slink as $file) {
            echo '<li class="file" id="file-' . $file['id'] . '">';
            
            renderFileHeader($file);

            echo '<p style="font-family: monospace;">';
            echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#8658;&nbsp;&nbsp;';
            echo $file['link_target'][0] == '/' ? $file['link_target'] : normalizePath(dirname($file['file_path']) . '/' . $file['link_target']);
            echo '</p>';
            
            echo '</li>';
        }

        echo '</ul>';
    }

    echo '</section>';
}
