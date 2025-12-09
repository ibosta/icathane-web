<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);
$message = '';

// Sınıf ekleme
if ($_POST && isset($_POST['add_class'])) {
    $name = trim($_POST['name']);
    $academicYear = trim($_POST['academic_year']);
    
    if (empty($name) || empty($academicYear)) {
        $message = ['type' => 'danger', 'text' => 'Tüm alanları doldurun.'];
    } else {
        $result = $userManager->addClass($name, $academicYear);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Öğrenci ekleme
if ($_POST && isset($_POST['add_student'])) {
    $studentName = trim($_POST['student_name']);
    $classId = $_POST['class_id'];
    
    if (empty($studentName) || empty($classId)) {
        $message = ['type' => 'danger', 'text' => 'Öğrenci adı ve sınıf seçimi gerekli.'];
    } else {
        $result = $userManager->addStudent($studentName, $classId);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Öğretmen atama
if ($_POST && isset($_POST['assign_teacher'])) {
    $teacherId = $_POST['teacher_id'];
    $classId = $_POST['assign_class_id'];
    
    if (empty($teacherId) || empty($classId)) {
        $message = ['type' => 'danger', 'text' => 'Öğretmen ve sınıf seçimi gerekli.'];
    } else {
        $result = $userManager->assignTeacherToClass($teacherId, $classId);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Veriler
$classes = $userManager->getAllClasses();
$teachers = $userManager->getAllTeachers();
$currentYear = date('Y') . '-' . (date('Y') + 1);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınıf Yönetimi - TÜGVA Kocaeli Icathane</title>
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
        
        .class-card {
            border: 2px solid var(--tugva-accent);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .class-card:hover {
            border-color: var(--tugva-primary);
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="superuser.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Sınıf Yönetimi
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
            <!-- Sol Kolon: Form İşlemleri -->
            <div class="col-md-4">
                <!-- Yeni Sınıf Ekleme -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Yeni Sınıf Ekle
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-bold">Sınıf Adı *</label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="örn: 9-A, 10-B, Temel Seviye" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="academic_year" class="form-label fw-bold">Akademik Yıl *</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" value="<?php echo $currentYear; ?>" placeholder="2024-2025" required>
                            </div>
                            
                            <button type="submit" name="add_class" class="btn btn-tugva w-100">
                                <i class="fas fa-save me-2"></i>
                                Sınıf Ekle
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Öğretmen Atama -->
                <?php if (!empty($classes) && !empty($teachers)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-user-tie me-2"></i>
                            Öğretmen Ata
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label fw-bold">Öğretmen Seç</label>
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
                            
                            <div class="mb-3">
                                <label for="assign_class_id" class="form-label fw-bold">Sınıf Seç</label>
                                <select class="form-select" id="assign_class_id" name="assign_class_id" required>
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

                <!-- Öğrenci Ekleme -->
                <?php if (!empty($classes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Öğrenci Ekle
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="student_name" class="form-label fw-bold">Öğrenci Adı *</label>
                                <input type="text" class="form-control" id="student_name" name="student_name" placeholder="Ad Soyad" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="class_id" class="form-label fw-bold">Sınıf Seç</label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Sınıf seçin...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?> 
                                            (<?php echo $class['student_count']; ?> öğrenci)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="add_student" class="btn btn-tugva w-100">
                                <i class="fas fa-user-plus me-2"></i>
                                Öğrenci Ekle
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sağ Kolon: Sınıf Listesi -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-school me-2"></i>
                            Mevcut Sınıflar (<?php echo count($classes); ?> sınıf)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($classes)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-school fa-3x mb-3"></i>
                                <h5>Henüz sınıf eklenmemiş</h5>
                                <p>Sol taraftaki formu kullanarak ilk sınıfınızı oluşturun.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($classes as $class): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="class-card p-3">
                                        <h6 class="mb-2">
                                            <i class="fas fa-door-open me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </h6>
                                        <small class="text-muted d-block mb-2">
                                            <?php echo htmlspecialchars($class['academic_year']); ?>
                                        </small>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-users me-1"></i>
                                                    <?php echo $class['student_count']; ?> öğrenci
                                                </span>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-chalkboard-teacher me-1"></i>
                                                    <?php echo $class['teacher_count']; ?> öğretmen
                                                </span>
                                            </div>
                                            <div>
                                                <a href="class-details.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <?php if ($class['is_active']): ?>
                                            <span class="badge bg-success position-absolute top-0 end-0 m-2">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger position-absolute top-0 end-0 m-2">Pasif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hızlı Navigasyon -->
                <div class="card">
                    <div class="card-body">
                        <h6>Diğer İşlemler:</h6>
                        <a href="manage-teachers.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-chalkboard-teacher"></i> Öğretmen Yönetimi
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
</body>
</html>