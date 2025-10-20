<?php
/**
 * Barber Turns front controller and minimal router.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

$route = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

switch ($route) {
    case '/':
    case '/queue':
        require_login();
        $view = 'queue';
        break;
    case '/login':
        $view = 'login';
        break;
    case '/settings':
        require_owner();
        $view = 'settings';
        break;
    case '/tv':
        $view = 'tv';
        break;
    default:
        http_response_code(404);
        $view = '404';
}

render_view($view);

/**
 * Render a view within the shared layout.
 */
function render_view(string $view): void
{
    $view_path = __DIR__ . '/views/' . $view . '.php';
    if (!file_exists($view_path)) {
        http_response_code(404);
        $view_path = __DIR__ . '/views/404.php';
    }

    $page_title = ucfirst($view);
    include __DIR__ . '/views/_layout_header.php';
    include $view_path;
    include __DIR__ . '/views/_layout_footer.php';
}
