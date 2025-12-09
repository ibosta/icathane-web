<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);
$message = '';

// Öğretmen ekleme
if ($_POST && isset($_POST['add_teacher'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $fullName = trim($_POST['full_name']);
    
    if (empty($username) || empty($password) || empty($fullName)) {
        $message = ['type' => 'danger', 'text' => 'Tüm alanları doldurun.'];
    } else {
        $result = $userManager->addTeacher($username, $password, $fullName);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Öğretmen atama
if ($_POST && isset($_POST['assign_teacher'])) {
    $teacherId = $_POST['teacher_id'];
    $classId = $_POST['class_id'];
    
    if (empty($teacherId) || empty($classId)) {
        $message = ['type' => 'danger', 'text' => 'Öğretmen ve sınıf seçimi gerekli.'];
    } else {
        $result = $userManager->assignTeacherToClass($teacherId, $classId);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Öğretmen deaktif etme (güvenli silme)
if ($_POST && isset($_POST['deactivate_teacher'])) {
    $teacherId = $_POST['teacher_id'];
    
    if (empty($teacherId)) {
        $message = ['type' => 'danger', 'text' => 'Öğretmen seçimi gerekli.'];
    } else {
        $result = $userManager->deactivateTeacher($teacherId);
        $message = ['type' => $result['success'] ? 'success' : 'warning', 'text' => $result['message']];
    }
}

// Öğretmen tamamen silme (acil durum)
if ($_POST && isset($_POST['delete_teacher_permanently'])) {
    $teacherId = $_POST['teacher_id'];
    
    if (empty($teacherId)) {
        $message = ['type' => 'danger', 'text' => 'Öğretmen seçimi gerekli.'];
    } else {
        $result = $userManager->deleteTeacher($teacherId);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Tüm öğretmenleri ve sınıfları getir
$teachers = $userManager->getAllTeachers();
$classes = $userManager->getAllClasses();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğretmen Yönetimi - TÜGVA Kocaeli Icathane</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --tugva-primary: #1B9B9B;
            --tugva-secondary: #0F7A7A;
            --tugva-light: #F5FDFD;
            --tugva-accent: #E8F8F8;
        }
        
        body {
            background-color: var(--tugva-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            box-shadow: 0 2px 10px rgba(27, 155, 155, 0.3);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(27, 155, 155, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1rem 1.5rem;
        }
        
        .btn-tugva {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            border: none;
            color: white;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-tugva:hover {
            background: var(--tugva-secondary);
            color: white;
            transform: translateY(-2px);
        }
        
        .form-control, .form-select {
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--tugva-primary);
            box-shadow: 0 0 0 0.2rem rgba(27, 155, 155, 0.25);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="superuser.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Öğretmen Yönetimi
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-shield me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Çıkış
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Mesaj -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Yeni Öğretmen Ekleme -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Yeni Öğretmen Ekle
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="full_name" class="form-label fw-bold">Ad Soyad *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label fw-bold">Kullanıcı Adı *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                                <small class="text-muted">Giriş yapmak için kullanacağı ad</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-bold">Şifre *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">En az 6 karakter olmalı</small>
                            </div>
                            
                            <button type="submit" name="add_teacher" class="btn btn-tugva w-100">
                                <i class="fas fa-save me-2"></i>
                                Öğretmen Ekle
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Öğretmen Atama -->
                <?php if (!empty($teachers) && !empty($classes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>
                            Öğretmen Sınıfa Ata
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label fw-bold">Öğretmen Seç *</label>
                                <select class="form-select" id="teacher_id" name="teacher_id" required>
                                    <option value="">Öğretmen seçin...</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['full_name']); ?> 
                                            (<?php echo $teacher['class_count']; ?> sınıf)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="class_id" class="form-label fw-bold">Sınıf Seç *</label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Sınıf seçin...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?> 
                                            (<?php echo $class['academic_year']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="assign_teacher" class="btn btn-tugva w-100">
                                <i class="fas fa-link me-2"></i>
                                Öğretmeni Ata
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Öğretmen Listesi -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Mevcut Öğretmenler (<?php echo count($teachers); ?> kişi)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teachers)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-users-slash fa-3x mb-3"></i>
                                <p>Henüz öğretmen eklenmemiş.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Ad Soyad</th>
                                            <th>Kullanıcı Adı</th>
                                            <th>Sınıf Sayısı</th>
                                            <th>Son Giriş</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($teacher['username']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $teacher['class_count']; ?> sınıf</span>
                                            </td>
                                            <td>
                                                <?php if ($teacher['last_login']): ?>
                                                    <small><?php echo date('d.m.Y H:i', strtotime($teacher['last_login'])); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Hiç giriş yapmadı</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($teacher['is_active']): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Pasif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($teacher['is_active']): ?>
                                                    <!-- Güvenli Silme (Deaktif Et) -->
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('<?php echo htmlspecialchars($teacher['full_name']); ?> adlı öğretmeni deaktif etmek istediğinizden emin misiniz?\\n\\nNot: Yoklama almış dersleri varsa işlem iptal edilecektir.')">
                                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                        <button type="submit" name="deactivate_teacher" class="btn btn-sm btn-outline-warning" title="Güvenli Silme">
                                                            <i class="fas fa-user-slash"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Acil Durum Silme -->
                                                    <form method="POST" style="display: inline; margin-left: 5px;" onsubmit="return confirm('⚠️ DİKKAT! Bu işlem geri alınamaz!\\n\\n<?php echo htmlspecialchars($teacher['full_name']); ?> adlı öğretmeni ve tüm verilerini kalıcı olarak silmek istediğinizden emin misiniz?\\n\\nTüm ders programları, atamalar silinecektir!')">
                                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                        <button type="submit" name="delete_teacher_permanently" class="btn btn-sm btn-outline-danger" title="Kalıcı Silme">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">Pasif Kullanıcı</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hızlı Navigasyon -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6>Diğer İşlemler:</h6>
                        <a href="manage-classes.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-school"></i> Sınıf Yönetimi
                        </a>
                        <a href="manage-schedules.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-calendar"></i> Program Yönetimi
                        </a>
                        <a href="superuser.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Ana Panele Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.querySelector('#password').value;
            if (password.length < 6) {
                e.preventDefault();
                alert('Şifre en az 6 karakter olmalıdır.');
                return false;
            }
        });
    </script>
</body>
</html>