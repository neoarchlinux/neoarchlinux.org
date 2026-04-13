<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    validationError('Only POST requests are accepted', 405);
}

$params = $_POST;

// hostname

if (!isset($params['hostname']) || empty($params['hostname'])) {
    validationError('Hostname is required', 422);
}

$hostname = trim($params['hostname']);

if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,62}$/', $hostname)) {
    validationError(
        'Hostname must be 1-63 alphanumeric characters or hyphens, starting with alphanumeric', 
        422
    );
}

// language

$validLanguages = [
    "en_US.UTF-8",
    "en_GB.UTF-8",
    "en_AU.UTF-8",
    "en_CA.UTF-8",
    "en_IE.UTF-8",
    "en_NZ.UTF-8",
    "en_SG.UTF-8",
    "en_ZA.UTF-8",
    "en_IN",
    "de_DE.UTF-8",
    "de_AT.UTF-8",
    "de_CH.UTF-8",
    "fr_FR.UTF-8",
    "fr_BE.UTF-8",
    "fr_CA.UTF-8",
    "fr_CH.UTF-8",
    "es_ES.UTF-8",
    "es_MX.UTF-8",
    "es_AR.UTF-8",
    "es_US.UTF-8",
    "it_IT.UTF-8",
    "it_CH.UTF-8",
    "pl_PL.UTF-8",
    "cs_CZ.UTF-8",
    "sk_SK.UTF-8",
    "ru_RU.UTF-8",
    "uk_UA.UTF-8",
    "sl_SI.UTF-8",
    "hr_HR.UTF-8",
    "sv_SE.UTF-8",
    "sv_FI.UTF-8",
    "fi_FI.UTF-8",
    "da_DK.UTF-8",
    "nb_NO.UTF-8",
    "ja_JP.UTF-8",
    "ko_KR.UTF-8",
    "zh_CN.UTF-8",
    "zh_TW.UTF-8",
    "zh_HK.UTF-8",
    "pt_BR.UTF-8",
    "pt_PT.UTF-8",
    "nl_NL.UTF-8",
    "nl_BE.UTF-8",
    "tr_TR.UTF-8",
    "el_GR.UTF-8",
    "he_IL.UTF-8",
    "hi_IN",
    "th_TH.UTF-8",
    "vi_VN"
];

$language = isset($params['language']) ? trim($params['language']) : 'en_US.UTF-8';

if (!in_array($language, $validLanguages, true)) {
    validationError("Invalid language: {$language}. Valid options: " . implode(', ', $validLanguages), 422);
}

// timezone

$timezone = $params['timezone'];

// users

if (!isset($params['users']) || !is_array($params['users']) || empty($params['users'])) {
    validationError('At least one user is required', 422);
}

$validUsers = [];
foreach ($params['users'] as $index => $user) {
    if (!is_array($user)) {
        validationError("Invalid user data at index {$index}", 422);
    }
    
    if (!isset($user['username']) || empty($user['username'])) {
        validationError("Username is required for user at index {$index}", 422);
    }
    
    $username = trim($user['username']);
    
    if (!preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $username)) {
        validationError(
            "Invalid username '{$username}'. Must be 1-32 lowercase alphanumeric characters, " .
            "starting with a letter, and may include hyphens and underscores", 
            422
        );
    }
    
    foreach ($validUsers as $existingUser) {
        if ($existingUser['username'] === $username) {
            validationError("Duplicate username: {$username}", 422);
        }
    }
    
    $password = isset($user['password']) ? $user['password'] : '';
    
    $validUsers[] = [
        'username' => $username,
        'password' => $password,
        'admin' => $user['admin'] === 'on' || $user['admin'] === true || $user['admin'] === 'true',
    ];
}

// kernel

$validKernels = ['linux', 'linux-lts', 'linux-zen', 'linux-hardened'];
$selectedKernels = [];

if (isset($params['kernel']) && is_array($params['kernel'])) {
    foreach ($params['kernel'] as $kernel => $value) {
        if (!in_array($kernel, $validKernels, true)) {
            validationError("Invalid kernel selection: {$kernel}", 422);
        }
        if ($value === 'on' || $value === true || $value === 'true') {
            $selectedKernels[] = $kernel;
        }
    }
}

if (empty($selectedKernels)) {
    validationError('At least one kernel must be selected', 422);
}

// init system

$validInitSystems = ['openrc', 'systemd', 'runit', 's6', 'dinit'];
$initSystem = isset($params['init_system']) ? trim($params['init_system']) : 'openrc';

if (!in_array($initSystem, $validInitSystems, true)) {
    validationError(
        "Invalid init system: {$initSystem}. Valid options: " . implode(', ', $validInitSystems), 
        422
    );
}

// additional packages

$livePackages = [];
if (isset($params['live_package']) && is_array($params['live_package'])) {
    foreach ($params['live_package'] as $pkgName => $value) {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $pkgName)) {
            validationError("Invalid package name format: {$pkgName}", 422);
        }

        if ($value === 'on' || $value === true || $value === 'true') {
            $livePackages[] = $pkgName;
        }
    }
}

$systemPackages = [];
if (isset($params['system_package']) && is_array($params['system_package'])) {
    foreach ($params['system_package'] as $pkgName => $value) {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $pkgName)) {
            validationError("Invalid package name format: {$pkgName}", 422);
        }

        if ($value === 'on' || $value === true || $value === 'true') {
            $systemPackages[] = $pkgName;
        }
    }
}

// ~

$params = [
    'hostname' => $hostname,
    'language' => $language,
    'timezone' => $timezone,
    'users' => $validUsers,
    'kernels' => $selectedKernels,
    'init_system' => $initSystem,
    'live_packages' => $livePackages,
    'system_packages' => $systemPackages
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoArch ISO Builder</title>
    <link rel="stylesheet" href="/index.css">
    <style>
        .build-container {
            max-width: 720px;
            margin: 80px auto;
            padding: 32px;
            background-color: var(--bg-nav);
            border-radius: 12px;
            border: 1px solid rgba(205, 214, 244, 0.08);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .build-status {
            font-size: 1.05rem;
            margin-bottom: 18px;
            color: var(--fg-text);
            opacity: 0.95;
        }

        .progress-wrapper {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .progress-bar {
            width: 100%;
            height: 14px;
            background-color: rgba(205, 214, 244, 0.08);
            border-radius: 999px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(
                90deg,
                var(--accent-primary),
                var(--accent-secondary)
            );
            border-radius: 999px;
            transition: width 0.35s ease;
        }

        .progress-meta {
            font-size: 0.85rem;
            opacity: 0.75;
            text-align: center;
        }
    </style>
</head>
<body>

<?php require_once '/var/www/src/header.php'; ?>

<div class="build-container">
    <div class="build-status" id="status-text">Waiting for server</div>

    <div class="progress-wrapper">
        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill"></div>
        </div>
        <div class="progress-meta" id="progress-meta"></div>
    </div>
</div>

<script>
    const params = <?= json_encode($params, JSON_UNESCAPED_SLASHES) ?>;

    const ws = new WebSocket(<?php echo "'wss://iso.$DOMAIN/ws'"; ?>);

    let status = 'Waiting for server';

    const statusText  = document.querySelector("#status-text");
    const progressFill = document.querySelector("#progress-fill");
    const progressMeta = document.querySelector("#progress-meta");

    let wsKeepAliveInterval = 0;

    ws.onopen = () => {
        console.log("%c WS connection opened", "color: #bada55");

        ws.send(JSON.stringify({
            type: "init",
            params: params,
        }));

        wsKeepAliveInterval = setInterval(() => {
            ws.send('');
        }, 30 * 1000);
    };

    ws.onmessage = (e) => {
        const data = JSON.parse(e.data);

        clearInterval(wsKeepAliveInterval);
        wsKeepAliveInterval = setInterval(() => {
            ws.send('');
        }, 30 * 1000);

        if (data.status) {
            console.log("Status:", data);
            statusText.textContent = status = data.status;

            switch (data.status) {
                case 'Done': {
                    progressMeta.innerHTML = `ISO build finished, you can download it <a href="${data.download_url}">here</a>.`
                    return;
                }
                case 'Error': {
                    statusText.textContent = "WebSocket error";
                    progressFill.style.background = '#da5555';
                    progressFill.style.width = "100%";
                }
            }
        }

        if (
            typeof data.phase === "number" &&
            typeof data.phase_count === "number" &&
            data.phase_count > 0
        ) {
            const percent = Math.min(
                100,
                Math.round((data.phase / data.phase_count) * 100)
            );

            progressFill.style.width = percent + "%";
        }
    };

    ws.onerror = (e) => {
        console.error("WS error", e);
        statusText.textContent = "WebSocket error";
        progressFill.style.background = '#da5555';
        progressFill.style.width = "100%";
        clearInterval(wsKeepAliveInterval);
    };

    ws.onclose = () => {
        console.log("%c WS connection closed", "color: #da5555");
        statusText.textContent =
            status == 'Error' ? "An error occured during you build" :
            status == 'Done' ? "Build finished" :
            "Connection closed";
        progressFill.style.background =
            status == 'Error' ? '#da5555' :
            status == 'Done' ? '#55da55' :
            '#dada55';
        progressFill.style.width = "100%";
        clearInterval(wsKeepAliveInterval);
    };
</script>

</body>
</html>
