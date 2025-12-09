<?php
$host = 'localhost';
$port = '8888';
$dbname = 'dbname';
$username = 'username';
$password = 'password';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "<!-- Veritabanı bağlantısı başarılı -->";
} catch(PDOException $e) {
    // Detaylı hata bilgisi
    echo "<div style='background: #ffe6e6; padding: 20px; border: 1px solid #ff0000; margin: 20px; border-radius: 10px;'>";
    echo "<h3>🔴 Veritabanı Bağlantı Hatası</h3>";
    echo "<p><strong>Hata:</strong> " . $e->getMessage() . "</p>";
    echo "<hr>";
    echo "<h4>📋 Kontrol Listesi:</h4>";
    echo "<ol>";
    echo "<li><strong>MySQL servisi çalışıyor mu?</strong><br>";
    echo "XAMPP/WAMP/MAMP kontrol panelinden MySQL'i başlatın</li>";
    echo "<li><strong>Veritabanı adı doğru mu?</strong><br>";
    echo "Veritabanı adı: <code>icathane</code></li>";
    echo "<li><strong>Kullanıcı adı/şifre doğru mu?</strong><br>";
    echo "Kullanıcı: <code>root</code>, Şifre: <code>" . (empty($password) ? "boş" : "dolu") . "</code></li>";
    echo "<li><strong>Veritabanı oluşturuldu mu?</strong><br>";
    echo "phpMyAdmin'den 'icathane' veritabanını kontrol edin</li>";
    echo "</ol>";
    echo "<h4>🔧 Hızlı Çözümler:</h4>";
    echo "<p>1. Eğer MySQL şifreniz varsa, yukarıdaki \$password kısmına yazın</p>";
    echo "<p>2. Veritabanı yoksa phpMyAdmin'den 'icathane' adında oluşturun</p>";
    echo "<p>3. XAMPP kullanıyorsanız MySQL servisini başlatın</p>";
    echo "</div>";
    exit;
}
?>