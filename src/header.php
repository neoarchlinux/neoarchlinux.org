<?php
declare(strict_types=1);

function maybeActive(string $host, string $path = ''): string {
    $currentHost = $_SERVER['HTTP_HOST'];
    $currentPath = trim($_SERVER['REQUEST_URI'], '/');

    if ($currentHost !== $host . '.neoarchlinux.org' && !($currentHost === 'neoarchlinux.org' && $host === '')) {
        return '';
    }

    return $currentPath === $path
        ? 'class="active"'
        : '';
}

?>
<header class="header">
    <div class="header-inner">
        <a href="https://neoarchlinux.org" class="header-logo">
            <img class="logo" src="/icons/favicon-256.png" alt="NeoArch Logo"/>
        </a>

        <button class="hamburger" id="hamburger">&#9776;</button>

        <nav class="header-nav" id="header-nav">
            <a href="https://neoarchlinux.org" <?= maybeActive('') ?>>Home</a>
            <a href="https://docs.neoarchlinux.org/installation-guide" <?= maybeActive('docs', 'installation-guide') ?>>Install</a>
            <a href="https://docs.neoarchlinux.org" <?= maybeActive('docs') ?>>Docs</a>
            <a href="https://iso.neoarchlinux.org" <?= maybeActive('iso') ?>>ISO</a>

            <div class="header-nav-dropdown desktop-only">
                <span class="nav-more">More &blacktriangledown;</span>
                <div class="nav-dropdown-menu">
                    <a href="https://packages.neoarchlinux.org" <?= maybeActive('packages') ?>>Packages</a>
                    <a href="https://mirrors.neoarchlinux.org">Mirrors</a>
                </div>
            </div>

            <div class="mobile-more-links mobile-only">
                <a href="https://packages.neoarchlinux.org" <?= maybeActive('packages') ?>>Packages</a>
                <a href="https://mirrors.neoarchlinux.org">Mirrors</a>
            </div>
        </nav>
    </div>

    <script>
        const hamburger = document.getElementById('hamburger');
        const nav = document.getElementById('header-nav');

        hamburger.addEventListener('click', () => {
            nav.classList.toggle('open');
        });
    </script>
</header>
