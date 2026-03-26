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

function joinWithExceptLast(array $items, string $glue, string $lastGlue): string {
    $count = count($items);
    
    if ($count === 0) {
        return '';
    }
    
    if ($count === 1) {
        return $items[0];
    }
    
    if ($count === 2) {
        return $items[0] . ' and ' . $items[1];
    }
    
    $last = array_pop($items);
    return implode($glue, $items) . $lastGlue . $last;
}

function joinWithAnd(array $items): string {
    return joinWithExceptLast($items, ', ', ' and ');
}

function joinWithOr(array $items): string {
    return joinWithExceptLast($items, ', ', ' or ');
}

function ndigit(int $in, int $n) {
    $len = (int) floor(log($in, 10)) + 1;
    $rpos = $len - $n;
    $tmp = $in - ($in % pow(10, $rpos));
    return ($tmp % pow(10, $rpos + 1)) / pow(10, $rpos);
}

function normalizePath(string $path): string {
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            array_pop($parts);
        } else {
            $parts[] = $part;
        }
    }
    return '/' . implode('/', $parts);
}
