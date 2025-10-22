<?php
/**
 * Initiate Apple Sign In authorization.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once INC_PATH . '/session.php';

bt_start_session();

$config = bt_config()['oauth']['apple'] ?? [];
$clientId = $config['client_id'] ?? '';
$redirectUri = $config['redirect_uri'] ?? '';

if ($clientId === '' || $redirectUri === '') {
    exit('Apple OAuth is not configured. Set client_id and redirect_uri in includes/config.php.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'response_mode' => 'form_post',
    'scope' => 'name email',
    'state' => $state,
];

$authUrl = 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);

header('Location: ' . $authUrl);
exit;
