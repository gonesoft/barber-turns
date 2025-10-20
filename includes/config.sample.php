<?php
/**
 * Barber Turns configuration template.
 *
 * Copy this file to config.php and replace placeholder values with
 * DreamHost database credentials and OAuth keys.
 */
return [
    'app_name' => 'Barber Turns',
    'base_url' => 'https://example.com',
    'environment' => 'production',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'barber_turns',
        'user' => 'db_user',
        'pass' => 'db_password',
        'charset' => 'utf8mb4',
    ],
    'oauth' => [
        'google' => [
            'client_id' => 'GOOGLE_CLIENT_ID',
            'client_secret' => 'GOOGLE_CLIENT_SECRET',
            'redirect_uri' => 'https://example.com/auth/google_callback.php',
        ],
        'apple' => [
            'client_id' => 'APPLE_SERVICE_ID',
            'team_id' => 'APPLE_TEAM_ID',
            'key_id' => 'APPLE_KEY_ID',
            'private_key_path' => '/path/to/AuthKey.p8',
            'redirect_uri' => 'https://example.com/auth/apple_callback.php',
        ],
    ],
    'security' => [
        'session_name' => 'barberturns_session',
        'cookie_domain' => '',
        'cookie_secure' => true,
        'cookie_samesite' => 'None',
        'csrf_secret' => 'change_this_csrf_secret',
    ],
];
