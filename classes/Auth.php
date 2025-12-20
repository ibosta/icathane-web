<?php
// Basit Kimlik Doğrulama Sınıfı
class Auth {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    // Giriş Yap
    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT id, username, password_hash, full_name, role, is_active FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Hesap deaktif durumda.'];
            }

            // Oturum başlat
            session_start();
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();

            // Son giriş zamanını güncelle
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
    }

    // Oturum kontrolü
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Çıkış yap
    public function logout() {
        session_start();
        session_destroy();
    }

    // Yetki kontrolü
    public function isSuperUser() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'superuser';
    }

    public function isTeacher() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
    }

    // Giriş zorunluluğu kontrolü
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /icathane-web/auth/login.php');
            exit;
        }
    }

    // SuperUser yetkisi zorunluluğu
    public function requireSuperUser() {
        $this->requireLogin();
        if (!$this->isSuperUser()) {
            header('Location: ../index.php');
            exit;
        }
    }
}
?>