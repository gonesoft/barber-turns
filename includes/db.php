<?php
/**
 * Barber Turns database connection factory using PDO.
 *
 * Call bt_db() whenever a database handle is needed; the connection is cached.
 */

declare(strict_types=1);

if (!function_exists('bt_config')) {
    /**
     * Load application configuration from includes/config.php.
     */
    function bt_config(): array
    {
        static $config;
        if ($config === null) {
            $config = require __DIR__ . '/config.php';
        }

        return $config;
    }
}

/**
 * Return a shared PDO connection using config.php credentials.
 */
function bt_db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = bt_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['name'],
        $config['db']['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
    } catch (PDOException $e) {
        error_log('bt_db connection failed: ' . $e->getMessage() . ' DSN=' . $dsn);
        throw $e;
    }

    return $pdo;
}
