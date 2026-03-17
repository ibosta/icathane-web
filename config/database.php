<?php
// MySQL bağlantı ayarları (SQL dökümü ile uyumlu)
$host = 'localhost';
$port = '3306';
$dbname = 'itaskira_icathane';
$username = 'root';
$password = 'root';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div style='background:#ffe6e6;padding:20px;border:1px solid #ff0000;margin:20px;border-radius:10px'>";
    echo "<h3>Veritabanı Bağlantı Hatası</h3>";
    echo "<p><strong>Hata:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<hr>";
    echo "<p><strong>DSN:</strong> " . htmlspecialchars($dsn) . "</p>";
    echo "<p><strong>Kullanıcı:</strong> " . htmlspecialchars($username) . "</p>";
    echo "<p>phpMyAdmin'de veritabanı adının <code>itaskira_icathane</code> olduğundan emin olun.</p>";
    echo "</div>";
    exit;
}
?>