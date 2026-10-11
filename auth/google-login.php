<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/google.php';

/*
 * Make sure Google Sign-In is configured.
 */
if (!google_enabled()) {
    http_response_code(503);
    exit('Google sign-in is not configured yet.');
}


/*
 * Generate a secure OAuth state value.
 *
 * This is stored in the session and should be
 * verified by google-callback.php.
 */
$state = bin2hex(
    random_bytes(32)
);

$_SESSION['google_state'] = $state;


/*
 * Google OAuth authorization parameters.
 */
$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account'
];


/*
 * Build the Google authorization URL.
 */
$googleUrl =
    'https://accounts.google.com/o/oauth2/v2/auth?' .
    http_build_query(
        $params,
        '',
        '&',
        PHP_QUERY_RFC3986
    );


/*
 * Redirect the customer to Google.
 */
header(
    'Location: ' . $googleUrl,
    true,
    302
);

exit;