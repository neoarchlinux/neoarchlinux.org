<?php

$code = (int)($_GET['code'] ?? 500);
http_response_code($code);

$uri = $_SERVER['REQUEST_URI'] ?? '';

$messages = [
    400 => "Bad Request! Did you try turning it off and on again?",
    401 => "Unauthorized! You shall not pass!",
    403 => "No, forbidden!",
    404 => "Oops! Page not found. Maybe it went on vacation.",
    405 => "Method Not Allowed. Relax, it's not you, it's HTTP.",
    408 => "Request Timeout. Your patience is impressive, but the server gave up.",
    410 => "Gone. This page has joined a witness protection program.",
    413 => "Payload Too Large! Did you bring a suitcase to a backpack fight?",
    414 => "URI Too Long. That's what she said.",
    415 => "Unsupported Media Type. We only speak BEEP BEEP BOOP BOOP here.",
    418 => "I'm a teapot! (RFC 2324 — don't ask)",
    429 => "Too Many Requests! Chill out, buddy.",
    500 => "Internal Server Error. The server is having a bad day.",
    501 => "Not Implemented. We're still figuring this out.",
    502 => "Bad Gateway. Someone's dropping packets again.",
    503 => "Service Unavailable. Time for a coffee break.",
    504 => "Gateway Timeout. The server went to lunch.",
];

$message = $messages[$code] ?? "Oops! Something very strange happened.";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoArch ISO Builder</title>
    <link rel="stylesheet" href="/index.css">
    <style>
        main {
            padding: 2rem;
            text-align: center;
        }
        
        h1 {
            font-size: 4em;
        }

        p {
            font-size: 1.5em;
        }
    </style>
</head>
<body>

<?php require_once '/var/www/src/header.php'; ?>

<main>
    <h1>Error <?= $code ?></h1>
    <p><?= $message ?></p>
</main>

</body>
</html>
