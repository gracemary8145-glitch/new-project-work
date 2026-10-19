
<?php
// Apply these settings before starting a session.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_set_cookie_params([
    'httponly' => true,
    'secure' => false, // Localhost HTTP only; use true with HTTPS in production.
    'samesite' => 'Lax',
    'path' => '/',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
