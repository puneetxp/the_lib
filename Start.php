<?php
ob_start();
session_start([
    'cookie_lifetime' => 1440,
    'cookie_secure' => secure,
    "cookie_path" => '/',
    'cookie_domain' => web,
    'cookie_httponly' => httponly,
    'cookie_samesite'=>same_site
]);
$timezone = date_default_timezone_set("Asia/Kolkata");
if (json_decode(file_get_contents('php://input'), true)) {
    $_POST = json_decode(file_get_contents('php://input'), true);
}
