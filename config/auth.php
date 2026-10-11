<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';

/*
|--------------------------------------------------------------------------
| Current Customer
|--------------------------------------------------------------------------
*/
function current_user(): ?array
{
    return isset($_SESSION['customer']) && is_array($_SESSION['customer'])
        ? $_SESSION['customer']
        : null;
}

/*
|--------------------------------------------------------------------------
| Authentication Checks
|--------------------------------------------------------------------------
*/
function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id'])
        && in_array(
            $_SESSION['admin_role'] ?? '',
            ['Admin', 'Staff'],
            true
        );
}

function is_super_admin(): bool
{
    return is_admin_logged_in()
        && !empty($_SESSION['admin_super']);
}

function is_customer_logged_in(): bool
{
    return !empty($_SESSION['customer_id']);
}

function is_logged_in(): bool
{
    return is_admin_logged_in() || is_customer_logged_in();
}

/*
|--------------------------------------------------------------------------
| Access Protection
|--------------------------------------------------------------------------
*/
function require_login(): void
{
    if (!is_admin_logged_in()) {
        http_response_code(403);

        exit(
            'Access denied. Administrator or staff authorization is required.'
        );
    }
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        http_response_code(403);

        exit(
            'Access denied. Administrator or staff authorization is required.'
        );
    }
}

function require_customer(): void
{
    if (!is_customer_logged_in()) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        /*
        | Prevent an invalid/empty redirect destination.
        */
        if ($requestUri === '' || $requestUri[0] !== '/') {
            $requestUri = '/';
        }

        redirect(
            '/auth/customer-login.php?next=' .
            rawurlencode($requestUri)
        );
    }
}

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/
function logout_all(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }

    session_destroy();
}