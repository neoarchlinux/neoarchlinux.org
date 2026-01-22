<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function ensureDir(string $dir, int $mode = 0775): void {
    if (!is_dir($dir) && !mkdir($dir, $mode, true)) {
        jsonError("Failed to create directory: $dir", 500);
    }
}

function getIpAddress(): string {
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!isset($_SERVER[$key])) continue;

        $value = $_SERVER[$key];

        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            $value = trim($parts[0]);
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    return "0.0.0.0";
}

function copyPreserve(string $src, string $dst): void {
    if (!copy($src, $dst)) {
        jsonError("Failed to copy $src", 500);
    }

    $stat = stat($src);
    if ($stat !== false) {
        touch($dst, $stat['mtime'], $stat['atime']);
        chmod($dst, $stat['mode'] & 0777);
    }
}
