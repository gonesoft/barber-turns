<?php
/**
 * Handle Google OAuth 2.0 callback: exchange code, upsert user, start session.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

bt_start_session();

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$storedState = $_SESSION['oauth_state'] ?? null;
unset($_SESSION['oauth_state']);

if ($state === '' || $storedState === null || !hash_equals($storedState, $state)) {
    exit('Invalid OAuth state. Please try logging in again.');
}

if ($code === '') {
    $error = $_GET['error'] ?? 'unknown_error';
    exit('Google OAuth failed: ' . sanitize_text($error));
}

$config = bt_config()['oauth']['google'] ?? [];
$clientId = $config['client_id'] ?? '';
$clientSecret = $config['client_secret'] ?? '';
$redirectUri = $config['redirect_uri'] ?? '';

if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
    exit('Google OAuth is not configured. Set client_id, client_secret, and redirect_uri in includes/config.php.');
}

$tokenResponse = oauth_post('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code',
]);

if (!isset($tokenResponse['access_token'])) {
    exit('Failed to exchange authorization code for tokens.');
}

$userInfo = oauth_get('https://openidconnect.googleapis.com/v1/userinfo', $tokenResponse['access_token']);

if (!isset($userInfo['sub'], $userInfo['email'])) {
    exit('Unable to fetch Google profile data.');
}

$providerId = $userInfo['sub'];
$email = $userInfo['email'];
$name = $userInfo['name'] ?? $userInfo['email'];

$pdo = bt_db();

try {
    $pdo->beginTransaction();

    $selectStmt = $pdo->prepare("SELECT * FROM users WHERE oauth_provider = :provider AND oauth_id = :oauth_id LIMIT 1");
    $selectStmt->execute([
        ':provider' => 'google',
        ':oauth_id' => $providerId,
    ]);

    $user = $selectStmt->fetch();

    if ($user) {
        $updateStmt = $pdo->prepare(
            "UPDATE users
             SET email = :email, name = :name, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $updateStmt->execute([
            ':email' => $email,
            ':name' => $name,
            ':id' => $user['id'],
        ]);
        $userId = (int)$user['id'];
    } else {
        $insertStmt = $pdo->prepare(
            "INSERT INTO users (oauth_provider, oauth_id, email, name, role)
             VALUES (:provider, :oauth_id, :email, :name, 'viewer')"
        );
        $insertStmt->execute([
            ':provider' => 'google',
            ':oauth_id' => $providerId,
            ':email' => $email,
            ':name' => $name,
        ]);
        $userId = (int)$pdo->lastInsertId();
    }

    $fetchStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $fetchStmt->execute([':id' => $userId]);
    $user = $fetchStmt->fetch();

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit('Login failed. Please try again later.');
}

if (!$user) {
    exit('Unable to retrieve user after login.');
}

login_user($user);
header('Location: /queue');
exit;

/**
 * POST helper for OAuth token exchange.
 *
 * @return array<string,mixed>
 */
function oauth_post(string $url, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        exit('OAuth request failed: ' . $error);
    }
    curl_close($ch);

    /** @var array<string,mixed> $decoded */
    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * GET helper for OAuth user info.
 *
 * @return array<string,mixed>
 */
function oauth_get(string $url, string $accessToken): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        exit('OAuth request failed: ' . $error);
    }
    curl_close($ch);

    /** @var array<string,mixed> $decoded */
    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : [];
}
