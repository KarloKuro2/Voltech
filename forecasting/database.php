<?php
require_once 'config.php';

class Database {
    private $conn;

    public function __construct() {
        $this->connect();
        $this->createTables();
    }

    private function connect() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    private function createTables() {
        $sql = "CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_name VARCHAR(255) NOT NULL,
            old_size FLOAT NOT NULL,
            old_cost FLOAT NOT NULL,
            new_size FLOAT NOT NULL,
            estimated_cost FLOAT NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->conn->query($sql);
    }

    public function saveProject($data) {
        $stmt = $this->conn->prepare("INSERT INTO projects (project_name, old_size, old_cost, new_size, estimated_cost, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdddds", $data['project_name'], $data['old_size'], $data['old_cost'], $data['new_size'], $data['estimated_cost'], $data['notes']);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getRecentProjects($limit = 10) {
        $result = $this->conn->query("SELECT * FROM projects ORDER BY created_at DESC LIMIT $limit");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getProjectStats() {
        $stats = [];
        $result = $this->conn->query("SELECT 
            COUNT(*) as total_projects,
            AVG(estimated_cost) as avg_cost,
            MAX(estimated_cost) as max_cost,
            MIN(estimated_cost) as min_cost,
            AVG(new_size) as avg_size,
            (SELECT estimated_cost FROM projects ORDER BY created_at DESC LIMIT 1) as latest_cost
            FROM projects");
        return $result->fetch_assoc();
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
