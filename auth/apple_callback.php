<?php
/**
 * Handle Apple Sign In callback: exchange code, decode id_token, upsert user.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/security.php';

bt_start_session();

$state = $_POST['state'] ?? $_GET['state'] ?? '';
$code = $_POST['code'] ?? $_GET['code'] ?? '';
$storedState = $_SESSION['oauth_state'] ?? null;
unset($_SESSION['oauth_state']);

if ($state === '' || $storedState === null || !hash_equals($storedState, $state)) {
    exit('Invalid OAuth state. Please try logging in again.');
}

if ($code === '') {
    $error = $_POST['error'] ?? $_GET['error'] ?? 'unknown_error';
    exit('Apple OAuth failed: ' . sanitize_text($error));
}

$config = bt_config()['oauth']['apple'] ?? [];
$clientId = $config['client_id'] ?? '';
$teamId = $config['team_id'] ?? '';
$keyId = $config['key_id'] ?? '';
$privateKeyPath = $config['private_key_path'] ?? '';
$redirectUri = $config['redirect_uri'] ?? '';

if ($clientId === '' || $teamId === '' || $keyId === '' || $privateKeyPath === '' || $redirectUri === '') {
    exit('Apple OAuth is not fully configured. Update includes/config.php with client_id, team_id, key_id, private_key_path, and redirect_uri.');
}

$clientSecret = generate_apple_client_secret($teamId, $clientId, $keyId, $privateKeyPath);

$tokenResponse = oauth_post('https://appleid.apple.com/auth/token', [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirectUri,
]);

if (!isset($tokenResponse['id_token'])) {
    exit('Failed to exchange authorization code for Apple tokens.');
}

$payload = decode_jwt_payload($tokenResponse['id_token']);

if (!isset($payload['sub'])) {
    exit('Unable to parse Apple id_token payload.');
}

$providerId = $payload['sub'];
$email = $payload['email'] ?? ($_POST['email'] ?? null);
if ($email === null) {
    exit('Apple did not provide an email address. Ensure the Email scope is requested.');
}

$fullName = null;
if (isset($_POST['user'])) {
    $userData = json_decode($_POST['user'], true);
    if (is_array($userData) && isset($userData['name'])) {
        $parts = $userData['name'];
        $fullName = trim(($parts['firstName'] ?? '') . ' ' . ($parts['lastName'] ?? ''));
    }
}

$name = $fullName !== '' ? $fullName : $email;

$pdo = bt_db();

try {
    $pdo->beginTransaction();

    $selectStmt = $pdo->prepare("SELECT * FROM users WHERE oauth_provider = :provider AND oauth_id = :oauth_id LIMIT 1");
    $selectStmt->execute([
        ':provider' => 'apple',
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
            ':provider' => 'apple',
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
 * Build Apple client secret JWT using ES256.
 */
function generate_apple_client_secret(string $teamId, string $clientId, string $keyId, string $privateKeyPath): string
{
    if (!file_exists($privateKeyPath)) {
        exit('Apple private key file not found at ' . sanitize_text($privateKeyPath));
    }

    $privateKey = file_get_contents($privateKeyPath);
    if ($privateKey === false) {
        exit('Unable to read Apple private key file.');
    }

    $header = [
        'alg' => 'ES256',
        'kid' => $keyId,
    ];

    $now = time();
    $claims = [
        'iss' => $teamId,
        'iat' => $now,
        'exp' => $now + 15777000, // ~6 months
        'aud' => 'https://appleid.apple.com',
        'sub' => $clientId,
    ];

    return jwt_encode_es256($header, $claims, $privateKey);
}

/**
 * Encode a JWT with ES256 algorithm.
 */
function jwt_encode_es256(array $header, array $claims, string $privateKey): string
{
    $segments = [];
    $segments[] = rtrim(strtr(base64_encode(json_encode($header, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $segments[] = rtrim(strtr(base64_encode(json_encode($claims, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $signingInput = implode('.', $segments);

    $signature = '';
    $success = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$success) {
        exit('Failed to sign Apple client secret.');
    }

    $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return implode('.', $segments);
}

/**
 * Decode JWT payload and return associative array.
 *
 * @return array<string,mixed>
 */
function decode_jwt_payload(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return [];
    }

    $payload = $parts[1];
    $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/'), true), true);

    return is_array($decoded) ? $decoded : [];
}

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
