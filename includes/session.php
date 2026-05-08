<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

$fingerprint = md5($_SERVER['HTTP_USER_AGENT']);
if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $fingerprint) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}
$_SESSION['fingerprint'] = $fingerprint;
?>