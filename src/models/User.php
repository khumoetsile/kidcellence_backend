<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

    class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $email;
    public $password;
    public $type;
    public $emailToken;
    public $emailVerified;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {

        $query = "INSERT INTO " . $this->table_name . " SET 
            name=:name, 
            email=:email, 
            type=:type, 
            password=:password, 
            email_token=:email_token, 
            email_verified=:email_verified";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->password = htmlspecialchars(strip_tags($this->password));

        // Hash the password
        $hashedPassword = password_hash($this->password, PASSWORD_BCRYPT);

        // $this->emailToken = bin2hex(random_bytes(16)); 
        $this->emailVerified = 0;  

        // Bind data
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":password", $hashedPassword);
        $stmt->bindParam(":email_token", $this->emailToken);
        $stmt->bindParam(":email_verified", $this->emailVerified);
        

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    public function login() {
    $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':email', $this->email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the email is verified
        if (!$row['email_verified']) {
            return 'Email not verified';
        }

        // Verify the password against the hashed value
        if (password_verify($this->password, $row['password'])) {
            // Password is correct, proceed with login
            $this->id = $row['id'];
            $this->type = $row['type'];
            $this->name = $row['name'];
            return true;
        }
    }

    return false; // Invalid credentials
}


    // Method to confirm the email using the token
    public function confirmEmail($token) {
        $query = "UPDATE " . $this->table_name . " 
                  SET email_verified = 1, email_token = NULL 
                  WHERE email_token = :token AND email_verified = 0";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
    
        if ($stmt->execute()) {
            $affectedRows = $stmt->rowCount();
            error_log("Rows affected: " . $affectedRows);
    
            if ($affectedRows > 0) {
                error_log("Email confirmed for token: " . $token);
                return true; // Email confirmation was successful
            } else {
                error_log("No rows affected. Token not found or already confirmed.");
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Error executing query: " . implode(", ", $errorInfo));
        }
    
        return false;
    }



}

