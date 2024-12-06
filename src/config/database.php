<?php
class Database {
    private $host = "localhost";
    private $db_name = "khumocob_tarniah";
    private $username = "khumocob_tarniah3";
    private $password = "KEBWQOVtr1Qy";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    public function reconnect() {
        if ($this->conn === null) {
            return $this->getConnection();
        }

        try {
            $this->conn->query('SELECT 1');
        } catch (PDOException $e) {
            if ($e->getCode() === 'HY000') { // MySQL server has gone away
                return $this->getConnection();
            }
            throw $e;
        }

        return $this->conn;
    }
}
