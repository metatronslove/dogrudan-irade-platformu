<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'dogrudan_irade';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
            die("Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.");
        }
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function singleValueQuery($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    public function insertAndGetId($sql, $params = []) {
        $conn = $this->connect();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $conn->lastInsertId();
    }
}
?>
