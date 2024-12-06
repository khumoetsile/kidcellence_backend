<?php
require_once __DIR__ . '/../models/User.php';
use \Firebase\JWT\JWT;

class SecureUserController {
    private $db;
    private $requestMethod;

    private $secretKey = 'your_secret_key'; // Change this to a more secure key

    public function __construct($db, $requestMethod) {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
    }

    public function processRequest() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = $this->getTokenFromHeader($authHeader);

        if ($token) {
            try {
                $decoded = JWT::decode($token, $this->secretKey, ['HS256']);
                $email = $decoded->email;

                // Now you can use the email to perform further actions
                // Example: Fetch user details from the database
                $user = new User($this->db);
                $user->email = $email;
                $userDetails = $user->getUserByEmail();

                if ($userDetails) {
                    http_response_code(200);
                    echo json_encode(['user' => $userDetails]);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'User not found']);
                }
            } catch (Exception $e) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid token']);
            }
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Token not provided']);
        }
    }

    private function getTokenFromHeader($authHeader) {
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
