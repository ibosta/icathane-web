<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);
$scheduleId = $_GET['id'] ?? null;
$message = '';

if (!$scheduleId) {
    header('Location: manage-schedules.php');
    exit;
}

// Mevcut program bilgilerini al
$stmt = $pdo->prepare("
    SELECT 
        ws.*,
        c.name as class_name,
        u.full_name as teacher_name
    FROM weekly_schedule ws
    JOIN classes c ON ws.class_id = c.id
    JOIN users u ON ws.teacher_id = u.id
    WHERE ws.id = ? AND ws.is_active = 1
");
$stmt->execute([$scheduleId]);
$schedule = $stmt->fetch();

if (!$schedule) {
    header('Location: manage-schedules.php');
    exit;
}

// Program güncelleme
if ($_POST && isset($_POST['update_schedule'])) {
    $classId = $_POST['class_id'];
    $teacherId = $_POST['teacher_id'];
    $dayOfWeek = $_POST['day_of_week'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $lessonName = trim($_POST['lesson_name']);
    
    if (empty($classId) || empty($teacherId) || empty($dayOfWeek) || empty($startTime) || empty($endTime) || empty($lessonName)) {
        $message = ['type' => 'danger', 'text' => 'Tüm alanları doldurun.'];
    } elseif ($startTime >= $endTime) {
        $message = ['type' => 'danger', 'text' => 'Bitiş saati başlangıç saatinden sonra olmalıdır.'];
    } else {
        try {
            // Yoklama alınmış ders var mı kontrol et
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE schedule_id = ? AND attendance_marked = 1");
            $stmt->execute([$scheduleId]);
            $attendedLessons = $stmt->fetchColumn();
            
            if ($attendedLessons > 0 && ($classId != $schedule['class_id'] || $teacherId != $schedule['teacher_id'])) {
                $message = ['type' => 'warning', 'text' => "Bu programın $attendedLessons dersi için yoklama alınmış. Sınıf ve öğretmen değiştirilemez. Sadece zaman ve ders adı düzenlenebilir."];
            } else {
                // Programı güncelle
                $stmt = $pdo->prepare("
                    UPDATE weekly_schedule 
                    SET class_id = ?, teacher_id = ?, day_of_week = ?, start_time = ?, end_time = ?, lesson_name = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$classId, $teacherId, $dayOfWeek, $startTime, $endTime, $lessonName, $scheduleId])) {
                    $message = ['type' => 'success', 'text' => 'Program başarıyla güncellendi.'];
                    
                    // Güncellenmiş bilgileri tekrar al
                    $stmt = $pdo->prepare("
                        SELECT 
                            ws.*,
                            c.name as class_name,
                            u.full_name as teacher_name
                        FROM weekly_schedule ws
                        JOIN classes c ON ws.class_id = c.id
                        JOIN users u ON ws.teacher_id = u.id
                        WHERE ws.id = ?
                    ");
                    $stmt->execute([$scheduleId]);
                    $schedule = $stmt->fetch();
                } else {
                    $message = ['type' => 'danger', 'text' => 'Program güncellenirken hata oluştu.'];
                }
            }
        } catch (Exception $e) {
            $message = ['type' => 'danger', 'text' => 'Hata: ' . $e->getMessage()];
        }
    }
}

// Veriler
$classes = $userManager->getAllClasses();
$teachers = $userManager->getAllTeachers();

$days = [
    1 => 'Pazartesi',
    2 => 'Salı', 
    3 => 'Çarşamba',
    4 => 'Perşembe',
    5 => 'Cuma',
    6 => 'Cumartesi',
    7 => 'Pazar'
];

// Bu programa ait ders istatistikleri
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_lessons,
        SUM(CASE WHEN attendance_marked = 1 THEN 1 ELSE 0 END) as attended_lessons,
        SUM(CASE WHEN attendance_marked = 0 THEN 1 ELSE 0 END) as pending_lessons
    FROM lessons 
    WHERE schedule_id = ?
");
$stmt->execute([$scheduleId]);
$lessonStats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Düzenle - TÜGVA Kocaeli Icathane</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --tugva-primary: #1B9B9B;
            --tugva-secondary: #0F7A7A;
            --tugva-light: #F5FDFD;
            --tugva-accent: #E8F8F8;
            --tugva-warning: #ffc107;
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
        
        .program-info {
            background: var(--tugva-accent);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1rem;
            background: linear-gradient(135deg, white, var(--tugva-accent));
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--tugva-primary);
        }
        
        .warning-note {
            background: #fff3cd;
            border-left: 4px solid var(--tugva-warning);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="manage-schedules.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Program Düzenle
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

        <!-- Mevcut Program Bilgileri -->
        <div class="program-info">
            <div class="row">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-edit me-2" style="color: var(--tugva-primary);"></i>
                        Program Düzenleme
                    </h2>
                    <p class="mb-1">
                        <strong>Mevcut:</strong> 
                        <?php echo htmlspecialchars($schedule['class_name']); ?> - 
                        <?php echo htmlspecialchars($schedule['lesson_name']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Öğretmen:</strong> <?php echo htmlspecialchars($schedule['teacher_name']); ?>
                    </p>
                    <p class="mb-0">
                        <strong>Zaman:</strong> 
                        <?php echo $days[$schedule['day_of_week']]; ?> - 
                        <?php echo date('H:i', strtotime($schedule['start_time'])); ?>-<?php echo date('H:i', strtotime($schedule['end_time'])); ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-calendar fa-2x mb-2" style="color: var(--tugva-primary);"></i>
                        <div class="stat-number"><?php echo $lessonStats['total_lessons']; ?></div>
                        <small>Toplam Ders</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uyarı -->
        <?php if ($lessonStats['attended_lessons'] > 0): ?>
        <div class="warning-note">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Dikkat!</h6>
            <p class="mb-0">
                Bu programın <strong><?php echo $lessonStats['attended_lessons']; ?> dersi</strong> için yoklama alınmış. 
                Sınıf ve öğretmen değişikliği önceki dersleri etkileyebilir.
                <br><small class="text-muted">Sadece zaman ve ders adı güvenle değiştirilebilir.</small>
            </p>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- İstatistikler -->
            <div class="col-md-4">
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-check-circle fa-2x mb-2" style="color: #28a745;"></i>
                            <div class="stat-number" style="color: #28a745;"><?php echo $lessonStats['attended_lessons']; ?></div>
                            <small>Tamamlanan Ders</small>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-clock fa-2x mb-2" style="color: var(--tugva-warning);"></i>
                            <div class="stat-number" style="color: var(--tugva-warning);"><?php echo $lessonStats['pending_lessons']; ?></div>
                            <small>Bekleyen Ders</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Düzenleme Formu -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cog me-2"></i>
                            Program Bilgilerini Güncelle
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="class_id" class="form-label fw-bold">Sınıf *</label>
                                    <select class="form-select" id="class_id" name="class_id" required>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" 
                                                    <?php echo $class['id'] == $schedule['class_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['name']); ?> 
                                                (<?php echo $class['academic_year']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="teacher_id" class="form-label fw-bold">Öğretmen *</label>
                                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>" 
                                                    <?php echo $teacher['id'] == $schedule['teacher_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lesson_name" class="form-label fw-bold">Ders Adı *</label>
                                <input type="text" class="form-control" id="lesson_name" name="lesson_name" 
                                       value="<?php echo htmlspecialchars($schedule['lesson_name']); ?>" 
                                       placeholder="örn: Matematik, Türkçe, Din Dersi" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="day_of_week" class="form-label fw-bold">Gün *</label>
                                    <select class="form-select" id="day_of_week" name="day_of_week" required>
                                        <?php foreach ($days as $num => $day): ?>
                                            <option value="<?php echo $num; ?>" 
                                                    <?php echo $num == $schedule['day_of_week'] ? 'selected' : ''; ?>>
                                                <?php echo $day; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="start_time" class="form-label fw-bold">Başlangıç</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" 
                                           value="<?php echo $schedule['start_time']; ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="end_time" class="form-label fw-bold">Bitiş</label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" 
                                           value="<?php echo $schedule['end_time']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="update_schedule" class="btn btn-tugva">
                                    <i class="fas fa-save me-2"></i>
                                    Değişiklikleri Kaydet
                                </button>
                                <a href="manage-schedules.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    İptal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const startTime = document.querySelector('#start_time').value;
            const endTime = document.querySelector('#end_time').value;
            
            if (startTime >= endTime) {
                e.preventDefault();
                alert('Bitiş saati başlangıç saatinden sonra olmalıdır!');
                return false;
            }
            
            return confirm('Program bilgilerini güncellemek istediğinizden emin misiniz?');
        });
    </script>
</body>
</html>