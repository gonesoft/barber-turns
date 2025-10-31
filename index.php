<?php
/**
 * Barber Turns root front controller.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once INC_PATH . '/session.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/security.php';

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
    case '/barbers':
        require_role('admin');
        $view = 'barbers';
        break;
    case '/users':
        require_role('admin');
        $view = 'users';
        break;
    case '/tv':
        $view = 'tv';
        break;
    case '/logout':
        require_once APP_ROOT . '/auth/logout.php';
        exit;
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
    bt_start_session();
    $viewPath = APP_ROOT . '/public/views/' . $view . '.php';
    if (!file_exists($viewPath)) {
        http_response_code(404);
        $viewPath = APP_ROOT . '/public/views/404.php';
    }

    $page_title = ucfirst($view);
    include APP_ROOT . '/public/views/_layout_header.php';
    include $viewPath;
    include APP_ROOT . '/public/views/_layout_footer.php';
}
