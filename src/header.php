<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function maybeActive(string $host, string $path = ''): string
{
    $currentHost = $_SERVER['HTTP_HOST'];
    $currentPath = trim($_SERVER['REQUEST_URI'], '/');

    if ($currentHost !== $host . '.' . getenv('DOMAIN') && !($currentHost === getenv('DOMAIN') && $host === '')) {
        return '';
    }

    return $currentPath === $path
        ? 'class="active"'
        : '';
}

?>
<header class="header">
    <div class="header-inner">
        <a href="https://<?php echo $DOMAIN; ?>" class="header-logo">
            <img class="logo" src="/icons/favicon-256.png" alt="NeoArch Logo" />
        </a>

        <button class="hamburger" id="hamburger">&#9776;</button>

        <nav class="header-nav" id="header-nav">
            <a href="https://<?php echo $DOMAIN; ?>" <?= maybeActive('') ?>>Home</a>
            <a href="https://docs.<?php echo $DOMAIN; ?>/general/install-guide" <?= maybeActive('docs', 'general/install-guide') ?>>Install</a>
            <a href="https://docs.<?php echo $DOMAIN; ?>" <?= maybeActive('docs') ?>>Docs</a>
            <a href="https://iso.<?php echo $DOMAIN; ?>" <?= maybeActive('iso') ?>>ISO</a>
            <a href="https://packages.<?php echo $DOMAIN; ?>" <?= maybeActive('packages') ?>>Packages</a>
            <a href="https://mirrors.<?php echo $DOMAIN; ?>" <?= maybeActive('mirrors') ?>>Mirrors</a>
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