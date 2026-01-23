<?php

$params = $_POST;

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

    const statusText  = document.querySelector("#status-text");
    const progressFill = document.querySelector("#progress-fill");
    const progressMeta = document.querySelector("#progress-meta");

    let wsKeepAliveInterval = 0;
    let finished = false;

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
            statusText.textContent = data.status;

            if (data.status === 'Done') {
                progressMeta.innerHTML = `ISO build finished, you can download it <a href="${data.download_url}">here</a>.`
                finished = true;
                return;
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
        statusText.textContent = finished ? "Build finished" : "Connection closed";
        progressFill.style.background = finished ? '#55da55' : '#dada55';
        progressFill.style.width = "100%";
        clearInterval(wsKeepAliveInterval);
    };
</script>

</body>
</html>
