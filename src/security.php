<?php
declare(strict_types=1);

// Passwords

$HASH_ALGO = PASSWORD_ARGON2ID;
$HASH_OPTS = [
    'memory_cost' => 1 << 17,
    'time_cost'   => 3,
    'threads'     => 1
];

function hashPassword(string $s): string {
    return password_hash($s, $HASH_ALGO, $HASH_OPTS);
}

function verifyPassword(string $s, string $h): bool {
    return password_verify($s, $h);
}

// Tokens

function hashToken(string $t): string {
    return hash('sha256', $t);
}

function verifyToken(string $t, string $h): bool {
    return hash_equals(hashToken($t), $h);
}
