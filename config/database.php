<?php
// Database configuration
class Database {
    private $host = 'sql104.byethost13.com';
    private $db_name = 'b13_39672354_agoncillo_disaster_system';
    private $username = 'b13_39672354';
    private $password = 'mers@2025';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Create a PDO connection and assign it to $pdo
$database = new Database();
$pdo = $database->getConnection();
?>
