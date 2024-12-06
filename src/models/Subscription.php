<?php
class Subscription {
    private $conn;
    private $table_name = "subscriptions";

    public $id;
    public $user_id;
    public $status;
    public $amount;
    public $start_date;
    public $end_date;
    public $subscription_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create or update subscription record
    public function createOrUpdate() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, status, amount, subscription_id, start_date, end_date)
                  VALUES (:user_id, :status, :amount, :subscription_id, :start_date, :end_date)
                  ON DUPLICATE KEY UPDATE 
                  status = :status, amount = :amount, end_date = :end_date";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':amount', $this->amount);
        $stmt->bindParam(':subscription_id', $this->subscription_id);
        $stmt->bindParam(':start_date', $this->start_date);
        $stmt->bindParam(':end_date', $this->end_date);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Fetch subscription by user ID
    public function fetchByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check if subscription is active
    public function isActive($user_id) {
        $subscription = $this->fetchByUserId($user_id);
        if ($subscription && $subscription['status'] === 'active' && strtotime($subscription['end_date']) > time()) {
            return true;
        }
        return false;
    }
}
