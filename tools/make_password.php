<?php
/**
 * CLI utility to generate Argon2id password hashes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/passwords.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$password = $argv[1] ?? null;

if ($password === null) {
    fwrite(STDERR, "Usage: php tools/make_password.php <plain-text-password>\n");
    exit(1);
}

$hash = hash_password($password);
fwrite(STDOUT, $hash . PHP_EOL);
