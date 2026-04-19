<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description"
        content="NeoArch Linux - An Arch and Artix based distribution featuring the NAPM package manager, init system freedom, and effortless system customization.">
    <title>NeoArch Linux</title>
    <link rel="stylesheet" href="/index.css">
    <style>
        main {
            margin: 0 auto;
            padding: 0 24px 64px;
        }

        .hero {
            padding: 80px 0 60px;
            text-align: center;
            margin-bottom: 48px;
        }

        .hero img {
            width: min(50%, 512px);
        }

        .hero h1 {
            font-size: clamp(2.5rem, 8vw, 4rem);
            font-weight: 700;
            margin: 0 0 16px;
            background: var(--accent-secondary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
        }

        .tagline {
            font-size: 1.25rem;
            color: var(--fg-text);
            opacity: 0.8;
            margin: 0 0 32px;
            line-height: 1.5;
        }

        .cta-group {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background-color: var(--accent-primary);
            color: var(--fg-text);
            border-color: var(--accent-secondary);
        }

        .btn-primary:hover {
            background-color: var(--accent-secondary);
            color: var(--bg-site);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--fg-text);
            border-color: rgba(205, 214, 244, 0.2);
        }

        .btn-secondary:hover {
            border-color: var(--accent-secondary);
            color: var(--accent-secondary);
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 48px;
        }

        .feature {
            padding: 32px 0;
            /* border-top: 1px solid rgba(205, 214, 244, 0.2); */
            border-left: 3px solid var(--accent-primary);
            padding-left: 32px;
            margin-left: -35px;
        }

        .feature h2 {
            font-size: 1.75rem;
            margin: 0 0 16px;
            color: var(--fg-text);
            font-weight: 600;
        }

        .feature p {
            font-size: 1.05rem;
            line-height: 1.7;
            margin: 0 0 16px;
            opacity: 0.9;
        }

        .feature a {
            color: var(--accent-secondary);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }

        .feature a:hover {
            border-bottom-color: var(--accent-secondary);
        }

        .code-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 24px 0;
            font-family: "SF Mono", Monaco, "Cascadia Code", "Roboto Mono", Consolas, monospace;
            font-size: 0.85rem;
        }

        .code-block {
            background-color: var(--bg-nav);
            border: 1px solid rgba(205, 214, 244, 0.1);
            border-radius: 8px;
            padding: 20px;
            overflow-x: auto;
        }

        .code-block.error {
            border-left: 3px solid #f38ba8;
        }

        .code-block.success {
            border-left: 3px solid var(--accent-secondary);
        }

        .code-block pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }

        .code-block.error pre {
            color: #f38ba8;
        }

        .code-block.success pre {
            color: var(--accent-secondary);
        }

        .highlight {
            border-left-color: var(--accent-secondary);
            border-radius: 0 8px 8px 0;
            padding: 32px;
            margin-left: -35px;
        }

        .highlight h2 {
            color: var(--accent-secondary);
        }

        code {
            background-color: rgba(205, 214, 244, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: "SF Mono", Monaco, monospace;
            font-size: 0.9em;
            color: var(--accent-ternary);
        }

        @media (max-width: 768px) {
            .hero {
                padding: 48px 0 40px;
            }

            .feature {
                padding-left: 20px;
                margin-left: -23px;
            }

            .highlight {
                padding: 24px;
                margin-left: -23px;
            }

            .code-comparison {
                grid-template-columns: 1fr;
            }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
    </style>
</head>

<body>

    <?php require_once '/var/www/src/header.php'; ?>

    <main>
        <section class="hero">
            <img src="/icons/favicon-1024.png">
            <h1>NeoArch Linux</h1>
            <p class="tagline">Arch Linux evolved. Init freedom meets intelligent package management.<br>From newcomers
                to power users, your system works the way you want it to.</p>
            <div class="cta-group">
                <a href="https://iso.<?= $DOMAIN ?>" class="btn btn-primary">Download ISO</a>
                <a href="https://docs.<?= $DOMAIN ?>/general/install-guide" class="btn btn-secondary">Installation
                    Guide</a>
            </div>
        </section>

        <section class="features">
            <article class="feature">
                <h2>Init System Freedom</h2>
                <p>NeoArch unites the best of Arch and Artix Linux. Choose systemd, OpenRC, runit, dinit, or s6 during
                    installation. Your system, your philosophy, zero compromise.</p>
            </article>

            <article class="feature highlight">
                <h2>Meet NAPM</h2>
                <p>The NeoArch Package Manager is the root of your system. While pacman requires manual intervention for
                    dependency conflicts, keyring issues, and database locks, NAPM automates error resolution and
                    provides a unified interface that just works.</p>

                <div class="code-comparison">
                    <div class="code-block error">
                        <pre>$ sudo pacman -S fastfetch
resolving dependencies...
looking for conflicting packages...

Packages (2) yyjson-0.12.0-1.3  fastfetch-2.58.0-1

Total Download Size:   0.52 MiB
Total Installed Size:  2.28 MiB

:: Proceed with installation? [Y/n] 
:: Retrieving packages...
 fastfetch-2.58.0-1-x86_64.pkg.tar.zst failed to download
 yyjson-0.12.0-1.3-x86_64 is up to date
 Total (2/2)                      529.6 KiB  1295 KiB/s 00:00 [----------------------------------] 100%
error: failed retrieving file 'fastfetch-2.58.0-1-x86_64.pkg.tar.zst' from mirrors.dotsrc.org : The requested URL returned error: 404
warning: failed to retrieve some files
error: failed to commit transaction (failed to retrieve some files)
Errors occurred, no packages were upgraded.</pre>
                    </div>
                    <div class="code-block success">
                        <pre>$ sudo napm install fastfetch
I: Installing fastfetch-2.58.0-1 with all its dependencies
I: Resolving dependencies
I: Checking for conflicts
I: Retrieving 1 packages, total size 542341
[ 1s] [========================================] [FAILED] fastfetch-2.58.0-1-x86_64.pkg.tar.zst failed
E: Package retireve failed
W: Stale database detected, update and upgrade required
W: System needs to be updated and upgraded
ACT: Do you want to remove old databases and run napm update --no-file-cache, and napm upgrade automatically? [Y/n]: 
I: Removing stale databases
I: # napm update --no-file-cache

<i>Update logs</i>

I: # napm upgrade

<i>Upgrade logs</i>

I: Installing fastfetch-2.58.0-1 with all its dependencies
I: Resolving dependencies
I: Checking for conflicts
I: Retrieving 1 packages, total size 545534
[ 1s] [========================================] 100% fastfetch-2.60.0-1-x86_64.pkg.tar.zst done
I: Checking keys in keyring
I: Checking for file integrity
I: Checking for file conflicts
I: Starting transaction
I: Installing yyjson-0.12.0-1.3
I: Installing fastfetch-2.60.0-1</pre>
                    </div>
                </div>

                <p><a href="https://docs.<?= $DOMAIN ?>/napm">Explore NAPM documentation &rightarrow;</a></p>
            </article>

            <article class="feature">
                <h2>Simplified Arch Experience</h2>
                <p>NeoArch preserves everything you love about Arch: the rolling release model, the AUR, the
                    performance, while removing the friction. Our <a
                        href="https://docs.<?= $DOMAIN ?>/general/install-guide">installation guide</a> gets you from
                    boot to desktop in minutes, not hours, with sensible defaults that don't sacrifice flexibility.</p>
            </article>

            <article class="feature">
                <h2>Your System, Pre-configured</h2>
                <p>Tired of reconfiguring after every install? NeoArch custom ISOs let you bake your kernel, desktop
                    environment, drivers, and dotfiles directly into the installation medium. Whether you manage a fleet
                    of workstations or just want your rice preserved, <a href="https://iso.<?= $DOMAIN ?>">generate
                        your perfect ISO</a> and deploy in minutes.</p>
            </article>

            <?php /* <article class="feature">
  <h2>Rice at the Speed of Thought</h2>
  <p>Aesthetic configuration shouldn't take hours. The <code>napm rice</code> command reads your profile and automatically applies window manager themes, terminal colors, font configurations, and keyboard shortcuts. Backup your setup to a single file, share it across machines, or deploy it on fresh installs instantly.</p>
</article> */ ?>
        </section>
    </main>

</body>

</html>