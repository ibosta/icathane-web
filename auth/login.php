<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$error = '';

// Zaten giriş yapmışsa yönlendir
if ($auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gerekli.';
    } else {
        $result = $auth->login($username, $password);
        if ($result['success']) {
            header('Location: ../index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - TÜGVA Kocaeli Icathane</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --tugva-primary: #1B9B9B;
            --tugva-secondary: #0F7A7A;
            --tugva-light: #F5FDFD;
            --tugva-accent: #E8F8F8;
        }
        
        body {
            background: linear-gradient(135deg, var(--tugva-light), var(--tugva-accent));
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(27, 155, 155, 0.2);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            color: white;
            text-align: center;
            padding: 2rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .btn-tugva {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-tugva:hover {
            background: var(--tugva-secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(27, 155, 155, 0.4);
            color: white;
        }
        
        .form-control {
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 0.75rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--tugva-primary);
            box-shadow: 0 0 0 0.2rem rgba(27, 155, 155, 0.25);
        }
        
        .alert-danger {
            border: none;
            border-radius: 10px;
            background-color: #ffe6e6;
            color: #d63384;
            border-left: 4px solid #d63384;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h3 class="mb-0">TÜGVA Kocaeli Icathane</h3>
                <p class="mb-0 mt-2 opacity-75">Yoklama ve Ders Takip Sistemi</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-bold">Kullanıcı Adı</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required 
                            autofocus
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label fw-bold">Şifre</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            required
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-tugva">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Giriş Yap
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        Giriş bilgileriniz için yöneticinize başvurun
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</body>
</html>