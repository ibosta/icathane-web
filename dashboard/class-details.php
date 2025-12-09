<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);
$classId = $_GET['id'] ?? null;
$message = '';

if (!$classId) {
    header('Location: manage-classes.php');
    exit;
}

// Sınıf bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$classId]);
$class = $stmt->fetch();

if (!$class) {
    header('Location: manage-classes.php');
    exit;
}

// Tek öğrenci ekleme
if ($_POST && isset($_POST['add_single_student'])) {
    $studentName = trim($_POST['student_name']);
    if (empty($studentName)) {
        $message = ['type' => 'danger', 'text' => 'Öğrenci adı gerekli.'];
    } else {
        $result = $userManager->addStudent($studentName, $classId);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Toplu öğrenci ekleme
if ($_POST && isset($_POST['add_bulk_students'])) {
    $studentList = trim($_POST['student_list']);
    if (empty($studentList)) {
        $message = ['type' => 'danger', 'text' => 'Öğrenci listesi gerekli.'];
    } else {
        $students = explode("\n", $studentList);
        $added = 0;
        $errors = [];
        
        foreach ($students as $student) {
            $student = trim($student);
            if (!empty($student)) {
                $result = $userManager->addStudent($student, $classId);
                if ($result['success']) {
                    $added++;
                } else {
                    $errors[] = $student . ': ' . $result['message'];
                }
            }
        }
        
        if ($added > 0) {
            $message = ['type' => 'success', 'text' => "$added öğrenci başarıyla eklendi."];
        }
        if (!empty($errors)) {
            $message['text'] .= ' Hatalar: ' . implode(', ', array_slice($errors, 0, 3));
        }
    }
}

// Öğrenci silme
if ($_POST && isset($_POST['delete_student'])) {
    $studentId = $_POST['student_id'];
    $stmt = $pdo->prepare("UPDATE students SET is_active = 0 WHERE id = ? AND class_id = ?");
    if ($stmt->execute([$studentId, $classId])) {
        $message = ['type' => 'success', 'text' => 'Öğrenci silindi.'];
    } else {
        $message = ['type' => 'danger', 'text' => 'Öğrenci silinemedi.'];
    }
}

// Öğretmen atama/değiştirme
if ($_POST && isset($_POST['assign_teacher_to_class'])) {
    $teacherId = $_POST['teacher_id'];
    
    if (empty($teacherId)) {
        $message = ['type' => 'danger', 'text' => 'Öğretmen seçimi gerekli.'];
    } else {
        $result = $userManager->assignTeacherToClass($teacherId, $classId);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Öğretmen kaldırma
if ($_POST && isset($_POST['remove_teacher'])) {
    $removeTeacherId = $_POST['remove_teacher_id'];
    
    $stmt = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND class_id = ?");
    if ($stmt->execute([$removeTeacherId, $classId])) {
        $message = ['type' => 'success', 'text' => 'Öğretmen sınıftan kaldırıldı.'];
    } else {
        $message = ['type' => 'danger', 'text' => 'Öğretmen kaldırılamadı.'];
    }
}

// Sınıf verilerini al
$students = $userManager->getClassStudents($classId);
$schedule = $userManager->getClassSchedule($classId);

// Sınıfın öğretmenlerini al (detaylı)
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name 
    FROM teacher_classes tc
    JOIN users u ON tc.teacher_id = u.id
    WHERE tc.class_id = ?
");
$stmt->execute([$classId]);
$assignedTeachers = $stmt->fetchAll();
$classTeacherNames = array_column($assignedTeachers, 'full_name');

// Tüm öğretmenleri al (atama için)
$allTeachers = $userManager->getAllTeachers();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['name']); ?> - TÜGVA Kocaeli Icathane</title>
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
        
        .student-item {
            background: white;
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .student-item:hover {
            border-color: var(--tugva-primary);
            background-color: var(--tugva-light);
        }
        
        .class-info {
            background: var(--tugva-accent);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .bulk-textarea {
            min-height: 150px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="manage-classes.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Sınıf Detayları
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

        <!-- Sınıf Bilgileri -->
        <div class="class-info">
            <div class="row">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-school me-2" style="color: var(--tugva-primary);"></i>
                        <?php echo htmlspecialchars($class['name']); ?>
                    </h2>
                    <p class="mb-2">
                        <strong>Akademik Yıl:</strong> <?php echo htmlspecialchars($class['academic_year']); ?>
                    </p>
                    <p class="mb-2">
                        <strong>Öğretmenler:</strong> 
                        <?php if (!empty($classTeacherNames)): ?>
                            <?php echo implode(', ', array_map('htmlspecialchars', $classTeacherNames)); ?>
                            <a href="#teacherManagement" class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="collapse">
                                <i class="fas fa-edit"></i> Değiştir
                            </a>
                        <?php else: ?>
                            <span class="text-muted">Henüz öğretmen atanmamış</span>
                            <a href="#teacherManagement" class="btn btn-sm btn-tugva ms-2" data-bs-toggle="collapse">
                                <i class="fas fa-plus"></i> Öğretmen Ata
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="mb-2">
                        <span class="badge bg-success p-2">
                            <i class="fas fa-users me-1"></i>
                            <?php echo count($students); ?> Öğrenci
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-info p-2">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo count($schedule); ?> Ders Programı
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Öğretmen Yönetimi (Gizli Bölüm) -->
        <div class="collapse" id="teacherManagement">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user-tie me-2"></i>
                        Öğretmen Yönetimi
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Mevcut Öğretmenler -->
                        <div class="col-md-6">
                            <h6>Atanmış Öğretmenler:</h6>
                            <?php if (!empty($assignedTeachers)): ?>
                                <?php foreach ($assignedTeachers as $teacher): ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span><?php echo htmlspecialchars($teacher['full_name']); ?></span>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu öğretmeni sınıftan kaldırmak istediğinizden emin misiniz?')">
                                        <input type="hidden" name="remove_teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" name="remove_teacher" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Henüz öğretmen atanmamış.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Yeni Öğretmen Atama -->
                        <div class="col-md-6">
                            <h6>Yeni Öğretmen Ata:</h6>
                            <?php if (!empty($allTeachers)): ?>
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <select class="form-select" name="teacher_id" required>
                                            <option value="">Öğretmen seçin...</option>
                                            <?php foreach ($allTeachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>">
                                                    <?php echo htmlspecialchars($teacher['full_name']); ?> 
                                                    (<?php echo $teacher['class_count']; ?> sınıf)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="assign_teacher_to_class" class="btn btn-tugva btn-sm">
                                        <i class="fas fa-plus me-1"></i> Öğretmeni Ata
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted">Önce öğretmen oluşturmalısınız.</p>
                                <a href="manage-teachers.php" class="btn btn-tugva btn-sm">
                                    <i class="fas fa-plus me-1"></i> Öğretmen Ekle
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sol Kolon: Öğrenci Ekleme -->
            <div class="col-md-4">
                <!-- Tek Öğrenci Ekleme -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Tek Öğrenci Ekle
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="student_name" class="form-label fw-bold">Ad Soyad</label>
                                <input type="text" class="form-control" id="student_name" name="student_name" placeholder="Öğrenci adını yazın..." required>
                            </div>
                            <button type="submit" name="add_single_student" class="btn btn-tugva w-100">
                                <i class="fas fa-plus me-2"></i>
                                Öğrenci Ekle
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Toplu Öğrenci Ekleme -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Toplu Öğrenci Ekle
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="student_list" class="form-label fw-bold">Öğrenci Listesi</label>
                                <textarea class="form-control bulk-textarea" id="student_list" name="student_list" placeholder="Her satıra bir öğrenci adı yazın:&#10;&#10;Ali Veli&#10;Ayşe Demir&#10;Mehmet Kaya&#10;Fatma Çelik&#10;Ahmet Yılmaz" required></textarea>
                                <small class="text-muted">Her satıra bir öğrenci adı yazın</small>
                            </div>
                            <button type="submit" name="add_bulk_students" class="btn btn-tugva w-100">
                                <i class="fas fa-plus me-2"></i>
                                Toplu Ekle
                            </button>
                        </form>
                        
                        <div class="mt-3 p-2" style="background: #f8f9fa; border-radius: 8px;">
                            <small>
                                <strong>💡 İpucu:</strong><br>
                                • Her satıra bir öğrenci adı<br>
                                • "Ad Soyad" formatında<br>
                                • Boş satırlar otomatik atlanır
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon: Öğrenci Listesi -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Sınıf Öğrencileri (<?php echo count($students); ?> kişi)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-user-slash fa-3x mb-3"></i>
                                <h5>Henüz öğrenci yok</h5>
                                <p>Sol taraftaki formları kullanarak öğrenci ekleyin.</p>
                            </div>
                        <?php else: ?>
                            <!-- Öğrenci arama -->
                            <div class="mb-3">
                                <input type="text" class="form-control" id="studentSearch" placeholder="Öğrenci ara..." onkeyup="searchStudents()">
                            </div>
                            
                            <!-- Öğrenci listesi -->
                            <div id="studentList">
                                <?php foreach ($students as $index => $student): ?>
                                <div class="student-item" data-student-name="<?php echo strtolower($student['full_name']); ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                Kayıt: <?php echo date('d.m.Y', strtotime($student['created_at'])); ?>
                                                <?php if (!$student['is_active']): ?>
                                                    <span class="badge bg-danger ms-2">Pasif</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-light text-dark me-2">#<?php echo $index + 1; ?></span>
                                            
                                            <!-- Devamsızlık Raporu Butonu -->
                                            <a href="student-attendance-report.php?student_id=<?php echo $student['id']; ?>&class_id=<?php echo $classId; ?>" 
                                               class="btn btn-sm btn-outline-info me-1" 
                                               title="Devamsızlık Raporu">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                            
                                            <?php if ($student['is_active']): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu öğrenciyi silmek istediğinizden emin misiniz?')">
                                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                    <button type="submit" name="delete_student" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ders Programı -->
                <?php if (!empty($schedule)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-calendar-week me-2"></i>
                            Haftalık Ders Programı
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        $days = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
                        foreach ($schedule as $lesson): 
                        ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <strong><?php echo htmlspecialchars($lesson['lesson_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $days[$lesson['day_of_week']]; ?> - 
                                        <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                                    </small>
                                </div>
                                <span class="badge bg-primary">
                                    <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - 
                                    <?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hızlı Navigasyon -->
                <div class="card">
                    <div class="card-body">
                        <h6>İşlemler:</h6>
                        <a href="manage-classes.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left"></i> Sınıf Listesi
                        </a>
                        <a href="manage-schedules.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-calendar-plus"></i> Program Ekle
                        </a>
                        <a href="superuser.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tachometer-alt"></i> Ana Panel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Öğrenci arama
        function searchStudents() {
            const searchInput = document.getElementById('studentSearch').value.toLowerCase();
            const students = document.querySelectorAll('.student-item');
            
            students.forEach(function(student) {
                const studentName = student.getAttribute('data-student-name');
                if (studentName.includes(searchInput)) {
                    student.style.display = 'block';
                } else {
                    student.style.display = 'none';
                }
            });
        }

        // Toplu ekleme önizlemesi
        document.getElementById('student_list').addEventListener('input', function() {
            const lines = this.value.split('\n').filter(line => line.trim() !== '');
            const count = lines.length;
            
            // Form altında önizleme göster (opsiyonel)
            console.log(`${count} öğrenci eklenecek`);
        });
    </script>
</body>
</html>