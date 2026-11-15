<?php
declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/bootstrap.php';
$routes = require dirname(__DIR__) . '/routes/web.php';
$route = $_GET['route'] ?? 'login';
if (!isset($routes[$route])) {
    http_response_code(404);
    echo 'Page introuvable.';
    exit;
}
[$controllerClass, $action] = $routes[$route];
(new $controllerClass())->$action();
