<?php
/**
 * Initiate Google OAuth 2.0 authorization.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

bt_start_session();

$config = bt_config()['oauth']['google'] ?? [];
$clientId = $config['client_id'] ?? '';
$redirectUri = $config['redirect_uri'] ?? '';

if ($clientId === '' || $redirectUri === '') {
    exit('Google OAuth is not configured. Set client_id and redirect_uri in includes/config.php.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'offline',
    'prompt' => 'consent',
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $authUrl);
exit;
