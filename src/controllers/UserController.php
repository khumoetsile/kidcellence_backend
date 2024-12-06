<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Subscription.php';


class UserController {
    private $db;
    private $requestMethod;

    public function __construct($db, $requestMethod) {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
    }

    public function processRequest($endpoint) {
        switch ($endpoint) {
            case 'register':
                $this->handleRegister();
                break;
            case 'login':
                $this->handleLogin();
                break;
            case 'logout':
                $this->handleLogout();
                break;
            case 'confirm':
                $this->handleConfirmEmail();
                break;
            default:
                $this->notFoundResponse();
                break;
        }
    }


    private function handleRegister() {
        if ($this->requestMethod === 'POST') {
            $input = (array) json_decode(file_get_contents('php://input'), TRUE);
    
            if (!$this->validateUser($input)) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid input']);
                return;
            }
    
            $user = new User($this->db);
            $user->name = $input['name'];
            $user->email = $input['email'];
            $user->type = $input['type'];
            $user->password = $input['password'];
    
            // Generate a unique confirmation token
            $user->emailToken = bin2hex(random_bytes(16));  // 32-character token
            $user->emailVerified = 0;
    
            try {
                if ($user->create()) {
                    // Send confirmation email
                    $this->sendConfirmationEmail($user->email, $user->emailToken);
    
                    http_response_code(201);
                    echo json_encode(['message' => 'User created. Please confirm your email.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'User could not be created']);
                }
            } catch (PDOException $e) {
                // Handle the duplicate email entry error
                if ($e->getCode() === '23000') { // 23000 is the SQLSTATE code for integrity constraint violation
                    http_response_code(409); // Conflict
                    echo json_encode(['message' => 'Email already exists']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'An error occurred while processing your request']);
                }
            }
        } else {
            $this->notFoundResponse();
        }
    }
    
    private function sendConfirmationEmail($email, $token) {
        $subject = "Email Confirmation";
        // The frontend Angular app should be handling the confirmation and call the backend
        $confirmationLink = "https://kidcellence.com/auth/confirm?token=" . $token;
    
        $message = "Hi there,\n\nPlease confirm your email by clicking the following link:\n" . $confirmationLink . "\n\nThank you!";
    
        // Send the email
        mail($email, $subject, $message);
    }


    
    private function handleConfirmEmail() {
    // header('Content-Type: application/json');
    header('Content-Type: application/json; charset=utf-8');


    if ($this->requestMethod === 'GET') {
        $token = $_GET['token'] ?? null;

        if (!$token) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid token']);
            return;
        }

        error_log('Received token: ' . $token); // Log the received token

        $user = new User($this->db);
        if ($user->confirmEmail($token)) {
            http_response_code(200);
            echo json_encode(['message' => 'Email confirmed successfully']);  // Ensure message is included
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid or expired token']);  // Return error message in JSON
        }
    } else {
        $this->notFoundResponse();
    }
}


    private function handleLogin() {
    if ($this->requestMethod === 'POST') {
        $input = (array) json_decode(file_get_contents('php://input'), TRUE);

        if (!$this->validateLogin($input)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid input']);
            return;
        }

        $user = new User($this->db);
        $user->email = $input['email'];
        $user->password = $input['password'];

        $result = $user->login();

        if ($result === 'Email not verified') {
            http_response_code(403);
            echo json_encode(['message' => 'Please verify your email before logging in.']);
            return;
        }

        if ($result) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_id'] = $user->id;

            session_regenerate_id(true);

            // Check if the user has an active subscription
            $subscription = new Subscription($this->db);
            $hasSubscription = $subscription->isActive($user->id);

            // Include 'name' and 'hasSubscription' in the response
            http_response_code(200);
            echo json_encode([
                'message' => 'Login successful',
                'session_id' => session_id(),
                'userId' => $user->id,
                'type' => $user->type,
                'name' => $user->name,
                'hasSubscription' => $hasSubscription
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid email or password']);
        }
    } else {
        $this->notFoundResponse();
    }
}

    function redirectToPaySubs($amount) {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    $encryptionKey = 'secret';
    $uniqueReference = 'SUBS_' . $user_id . '_' . time();
    
    $data = array(
        'VERSION'            => 21,
        'PAYGATE_ID'         => 10011072130,
        'REFERENCE'          => $uniqueReference,
        'AMOUNT'             => $amount * 100,  // Amount in cents
        'CURRENCY'           => 'ZAR',
        'RETURN_URL'         => 'https://dev.kidcellence.com/notify-url',
        'TRANSACTION_DATE'   => gmdate("Y-m-d H:i"),
        'EMAIL'              => 'kmolabi@gmail.com',
        'SUBS_START_DATE'    => gmdate("Y-m-d", strtotime("+1 day")),
        'SUBS_END_DATE'      => gmdate("Y-m-d", strtotime("+1 year")),
        'SUBS_FREQUENCY'     => 201,
        'PROCESS_NOW'        => 'YES',
        'PROCESS_NOW_AMOUNT' => $amount * 100
    );

    $checksumString = implode('|', [
        $data['VERSION'], 
        $data['PAYGATE_ID'], 
        $data['REFERENCE'], 
        $data['AMOUNT'], 
        $data['CURRENCY'], 
        $data['RETURN_URL'], 
        $data['TRANSACTION_DATE'], 
        $data['EMAIL'], 
        $data['SUBS_START_DATE'], 
        $data['SUBS_END_DATE'], 
        $data['SUBS_FREQUENCY'], 
        $data['PROCESS_NOW'], 
        $data['PROCESS_NOW_AMOUNT'],
        $encryptionKey
    ]);

    $data['CHECKSUM'] = md5($checksumString);
    echo '<form action="https://www.paygate.co.za/paysubs/process.trans" method="POST">';
    foreach ($data as $key => $value) {
        echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
    }
    echo '<button type="submit">Pay Now</button>';
    echo '</form>';
}



//     public function handleSubscriptionCallback() {
//     error_log('Callback received from PayGate.');
//     error_log(print_r($_POST, true));  // Log all incoming data for review

//     $paygateId = $_POST['PAYGATE_ID'] ?? null;
//     $reference = $_POST['REFERENCE'] ?? null;
//     $transactionStatus = $_POST['TRANSACTION_STATUS'] ?? null;
//     $resultCode = $_POST['RESULT_CODE'] ?? null;
//     $authCode = $_POST['AUTH_CODE'] ?? '';  
//     $amount = $_POST['AMOUNT'] ?? null;
//     $resultDesc = $_POST['RESULT_DESC'] ?? null;
//     $transactionId = $_POST['TRANSACTION_ID'] ?? '';  
//     $subscriptionId = $_POST['SUBSCRIPTION_ID'] ?? null;
//     $riskIndicator = $_POST['RISK_INDICATOR'] ?? null;
//     $checksum = $_POST['CHECKSUM'] ?? null;
//     $encryptionKey = 'secret'; // Replace with your actual encryption key

//     // Ensure required fields are present
//     if (!$paygateId || !$reference || !$transactionStatus || !$resultCode || !$subscriptionId || !$amount || !$checksum) {
//         http_response_code(400);
//         echo json_encode(['message' => 'Missing required fields']);
//         return;
//     }

//     // Create the checksum string in the exact format
//     $checksumString = implode('|', [
//         $paygateId,
//         $reference,
//         $transactionStatus,
//         $resultCode,
//         $authCode,
//         $amount,
//         $resultDesc,
//         $transactionId,
//         $subscriptionId,
//         $riskIndicator,
//         $encryptionKey
//     ]);

//     // Calculate the checksum
//     $calculatedChecksum = md5($checksumString);
//     error_log("Calculated Checksum: " . $calculatedChecksum);  // Log calculated checksum
//     error_log("Received Checksum: " . $checksum);  // Log received checksum for comparison

//     if ($calculatedChecksum !== $checksum) {
//         http_response_code(400);
//         echo json_encode(['message' => 'Invalid checksum']);
//         return;
//     }

//     // Log the transaction status and result code to diagnose the issue
//     error_log("Transaction Status: " . $transactionStatus);
//     error_log("Result Code: " . $resultCode);

//     $userId = str_replace('SUBS_', '', $reference);

//     if ($transactionStatus == 1 && $resultCode == 990017) {
//         $subscription = new Subscription($this->db);
//         $subscription->user_id = $userId;
//         $subscription->status = 'active';
//         $subscription->amount = $amount;
//         $subscription->subscription_id = $subscriptionId;
//         $subscription->start_date = gmdate('Y-m-d');
//         $subscription->end_date = gmdate('Y-m-d', strtotime("+1 year"));

//         if ($subscription->createOrUpdate()) {
//             http_response_code(200);
//             echo json_encode(['message' => 'Subscription updated successfully']);
//         } else {
//             http_response_code(500);
//             echo json_encode(['message' => 'Failed to update subscription']);
//         }
//     } else {
//         // Log result description to understand failure reason
//         error_log("Subscription payment failed: " . $resultDesc);
//         http_response_code(400);
//         echo json_encode(['message' => 'Subscription payment failed']);
//     }
// }



    public function handleSubscriptionCallback() {
    error_log('Callback received from PayGate.');
    error_log(print_r($_POST, true)); // Log all incoming data for review

    // Capture incoming fields
    $paygateId = $_POST['PAYGATE_ID'] ?? null;
    $reference = $_POST['REFERENCE'] ?? null;
    $transactionStatus = $_POST['TRANSACTION_STATUS'] ?? null;
    $resultCode = $_POST['RESULT_CODE'] ?? null;
    $authCode = $_POST['AUTH_CODE'] ?? '';  
    $amount = $_POST['AMOUNT'] ?? null;
    $resultDesc = $_POST['RESULT_DESC'] ?? null;
    $transactionId = $_POST['TRANSACTION_ID'] ?? '';  
    $subscriptionId = $_POST['SUBSCRIPTION_ID'] ?? null;
    $riskIndicator = $_POST['RISK_INDICATOR'] ?? null;
    $checksum = $_POST['CHECKSUM'] ?? null;
    $encryptionKey = 'secret'; // Replace with your actual encryption key

    // Ensure required fields are present
    if (!$paygateId || !$reference || !$transactionStatus || !$resultCode || !$subscriptionId || !$amount || !$checksum) {
        error_log("Missing required fields in callback.");
        http_response_code(400);
        echo json_encode(['message' => 'Missing required fields']);
        return;
    }

    // Recalculate checksum
    $checksumString = implode('|', [
        $paygateId,
        $reference,
        $transactionStatus,
        $resultCode,
        $authCode,
        $amount,
        $resultDesc,
        $transactionId,
        $subscriptionId,
        $riskIndicator,
        $encryptionKey
    ]);

    $calculatedChecksum = md5($checksumString);
    error_log("Calculated Checksum: " . $calculatedChecksum);
    error_log("Received Checksum: " . $checksum);

    if ($calculatedChecksum !== $checksum) {
        error_log("Checksum validation failed.");
        http_response_code(400);
        echo json_encode(['message' => 'Invalid checksum']);
        return;
    }

    // Log transaction status and result code for debugging
    error_log("Transaction Status: " . $transactionStatus);
    error_log("Result Code: " . $resultCode);

    // Handle subscription processing
    $userId = str_replace('SUBS_', '', $reference);

    if ($transactionStatus == '1' && $resultCode == '990017') {
        // Approved transaction
        $subscription = new Subscription($this->db);
        $subscription->user_id = $userId;
        $subscription->status = 'active';
        $subscription->amount = $amount;
        $subscription->subscription_id = $subscriptionId;
        $subscription->start_date = gmdate('Y-m-d');
        $subscription->end_date = gmdate('Y-m-d', strtotime("+1 year"));
        
        ob_start();
        
        if ($subscription->createOrUpdate()) {
            http_response_code(200);
            echo json_encode(['message' => 'Subscription updated successfully']);
            header('Location: https://kidcellence.com/freelancer/dashboards');
            exit;
        } else {
            error_log("Failed to update subscription.");
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update subscription']);
        }
        ob_end_flush();
    } else {
        // Transaction not approved or failed
        error_log("Transaction failed with Result Code: $resultCode, Status: $transactionStatus");
        error_log("Description: " . $resultDesc);
        http_response_code(400);
        echo json_encode(['message' => 'Subscription payment failed']);
    }
}





    private function handleLogout() {
        session_start();
        // Destroy the session to log out the user
        $_SESSION = []; // Clear session data
        session_unset(); // Free all session variables
        session_destroy(); // Destroy the session

        // Optionally delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        http_response_code(200);
        echo json_encode(['message' => 'Logged out']);
    }

    private function validateUser($input) {
        return isset($input['name']) && isset($input['email']) && isset($input['password']);
    }

    private function validateLogin($input) {
        return isset($input['email']) && isset($input['password']);
    }

    private function notFoundResponse() {
        http_response_code(404);
        echo json_encode(['message' => 'Not Found']);
    }
}

// $database = new Database();
// $db = $database->getConnection();

// // Get the request method and endpoint
// $requestMethod = $_SERVER['REQUEST_METHOD'];
// $endpoint = $_GET['endpoint'] ?? null;

// if ($endpoint) {
//     $controller = new UserController($db, $requestMethod);
//     $controller->processRequest($endpoint);
// } else {
//     http_response_code(404);
//     echo json_encode(['message' => 'Endpoint not specified']);
// }
