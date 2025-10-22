<?php
/**
 * Load configuration overrides from environment variables.
 *
 * Returns an array compatible with includes/config.php or an empty array
 * when no APP_* variables are defined.
 */

declare(strict_types=1);

function bt_config_from_env(): array
{
    $env = getenv();

    if (!isset($env['APP_ENV'])) {
        return [];
    }

    $pollMs = isset($env['APP_UI_POLL_MS']) ? (int)$env['APP_UI_POLL_MS'] : null;

    return [
        'app_name' => $env['APP_NAME'] ?? 'Barber Turns',
        'base_url' => $env['APP_BASE_URL'] ?? 'http://localhost:8080',
        'environment' => $env['APP_ENV'],
        'db' => [
            'host' => $env['APP_DB_HOST'] ?? 'db',
            'port' => isset($env['APP_DB_PORT']) ? (int)$env['APP_DB_PORT'] : 3306,
            'name' => $env['APP_DB_NAME'] ?? 'barber_turns',
            'user' => $env['APP_DB_USER'] ?? 'barber_user',
            'pass' => $env['APP_DB_PASS'] ?? 'barber_pass',
            'charset' => $env['APP_DB_CHARSET'] ?? 'utf8mb4',
        ],
        'oauth' => [
            'google' => [
                'client_id' => $env['APP_GOOGLE_CLIENT_ID'] ?? '',
                'client_secret' => $env['APP_GOOGLE_CLIENT_SECRET'] ?? '',
                'redirect_uri' => $env['APP_GOOGLE_REDIRECT_URI'] ?? '',
            ],
            'apple' => [
                'client_id' => $env['APP_APPLE_CLIENT_ID'] ?? '',
                'team_id' => $env['APP_APPLE_TEAM_ID'] ?? '',
                'key_id' => $env['APP_APPLE_KEY_ID'] ?? '',
                'private_key_path' => $env['APP_APPLE_PRIVATE_KEY'] ?? '',
                'redirect_uri' => $env['APP_APPLE_REDIRECT_URI'] ?? '',
            ],
        ],
        'security' => [
            'session_name' => $env['APP_SESSION_NAME'] ?? 'barberturns_session',
            'cookie_domain' => $env['APP_COOKIE_DOMAIN'] ?? '',
            'cookie_secure' => isset($env['APP_COOKIE_SECURE']) ? filter_var($env['APP_COOKIE_SECURE'], FILTER_VALIDATE_BOOLEAN) : true,
            'cookie_samesite' => $env['APP_COOKIE_SAMESITE'] ?? 'None',
            'csrf_secret' => $env['APP_CSRF_SECRET'] ?? 'change_this_csrf_secret',
        ],
        'ui' => [
            'poll_ms' => $pollMs ?? 3000,
        ],
    ];
}
