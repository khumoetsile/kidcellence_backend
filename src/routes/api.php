<?php

// Check the REQUEST_URI and PATH_INFO
$requestUri = $_SERVER["REQUEST_URI"];
$pathInfo = parse_url($requestUri, PHP_URL_PATH);


// Your existing code
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/UserController.php';

$database = new Database();
$db = $database->getConnection();
// session_start();
$user = $_SESSION['user'] ?? null;
$amount = 550; // Example amount for subscription

$requestMethod = $_SERVER["REQUEST_METHOD"];

switch ($pathInfo) {
    case '/register':
        $controller = new UserController($db, $requestMethod);
        $controller->processRequest('register');
        break;
    case '/login':
        $controller = new UserController($db, $requestMethod);
        $controller->processRequest('login');
        break;
    case '/logout':
        $controller = new UserController($db, $requestMethod);
        $controller->processRequest('logout');
        break;
    case '/confirm':
        $controller = new UserController($db, $requestMethod);
        $controller->processRequest('confirm');
        break;
    case '/redirect-to-paysubs':
        $controller = new UserController($db, $requestMethod);
        $controller->redirectToPaySubs($amount);
        break;
    case '/notify-url':
        $controller = new UserController($db, $requestMethod);
        $controller->handleSubscriptionCallback();
        break;
    default:
        http_response_code(404);
        echo json_encode(["error" => "Not found"]);
        break;
}

