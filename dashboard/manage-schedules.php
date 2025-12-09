<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);
$message = '';

// Haftalık program ekleme
if ($_POST && isset($_POST['add_schedule'])) {
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
        $result = $userManager->addWeeklySchedule($classId, $teacherId, $dayOfWeek, $startTime, $endTime, $lessonName);
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Program silme
if ($_POST && isset($_POST['delete_schedule'])) {
    $scheduleId = $_POST['schedule_id'];
    
    try {
        // Önce bu programa ait dersleri kontrol et
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE schedule_id = ? AND attendance_marked = 1");
        $stmt->execute([$scheduleId]);
        $attendedLessons = $stmt->fetchColumn();
        
        if ($attendedLessons > 0) {
            $message = ['type' => 'warning', 'text' => "Bu programın $attendedLessons dersi için yoklama alınmış. Silme iptal edildi."];
        } else {
            // Önce gelecek dersleri sil
            $stmt = $pdo->prepare("DELETE FROM lessons WHERE schedule_id = ? AND attendance_marked = 0");
            $stmt->execute([$scheduleId]);
            
            // Sonra programı sil
            $stmt = $pdo->prepare("UPDATE weekly_schedule SET is_active = 0 WHERE id = ?");
            if ($stmt->execute([$scheduleId])) {
                $message = ['type' => 'success', 'text' => 'Program başarıyla silindi.'];
            } else {
                $message = ['type' => 'danger', 'text' => 'Program silinirken hata oluştu.'];
            }
        }
    } catch (Exception $e) {
        $message = ['type' => 'danger', 'text' => 'Hata: ' . $e->getMessage()];
    }
}

// Veriler
$classes = $userManager->getAllClasses();
$teachers = $userManager->getAllTeachers();

// Haftalık programları getir
$stmt = $pdo->query("
    SELECT 
        ws.*,
        c.name as class_name,
        u.full_name as teacher_name
    FROM weekly_schedule ws
    JOIN classes c ON ws.class_id = c.id
    JOIN users u ON ws.teacher_id = u.id
    WHERE ws.is_active = 1
    ORDER BY c.name, ws.day_of_week, ws.start_time
");
$schedules = $stmt->fetchAll();

$days = [
    1 => 'Pazartesi',
    2 => 'Salı', 
    3 => 'Çarşamba',
    4 => 'Perşembe',
    5 => 'Cuma',
    6 => 'Cumartesi',
    7 => 'Pazar'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Yönetimi - TÜGVA Kocaeli Icathane</title>
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
        
        .schedule-item {
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .schedule-item:hover {
            border-color: var(--tugva-primary);
            background-color: var(--tugva-light);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="superuser.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Program Yönetimi
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
            <!-- Sol Kolon: Program Ekleme -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-plus me-2"></i>
                            Haftalık Program Ekle
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($classes) || empty($teachers)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Program eklemek için önce sınıf ve öğretmen oluşturmalısınız.
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="class_id" class="form-label fw-bold">Sınıf *</label>
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
                                
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label fw-bold">Öğretmen *</label>
                                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                                        <option value="">Öğretmen seçin...</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>">
                                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="lesson_name" class="form-label fw-bold">Ders Adı *</label>
                                    <input type="text" class="form-control" id="lesson_name" name="lesson_name" placeholder="örn: Matematik, Türkçe, Din Dersi" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="day_of_week" class="form-label fw-bold">Gün *</label>
                                    <select class="form-select" id="day_of_week" name="day_of_week" required>
                                        <option value="">Gün seçin...</option>
                                        <?php foreach ($days as $num => $day): ?>
                                            <option value="<?php echo $num; ?>"><?php echo $day; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="start_time" class="form-label fw-bold">Başlangıç</label>
                                        <input type="time" class="form-control" id="start_time" name="start_time" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="end_time" class="form-label fw-bold">Bitiş</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time" required>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_schedule" class="btn btn-tugva w-100">
                                    <i class="fas fa-save me-2"></i>
                                    Program Ekle
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="mt-4 p-3" style="background: var(--tugva-accent); border-radius: 10px;">
                            <h6><i class="fas fa-lightbulb me-2"></i>Nasıl Çalışır:</h6>
                            <small>
                                <strong>1. Adım:</strong> Sınıf seçin (örn: 9-A)<br>
                                <strong>2. Adım:</strong> Öğretmen seçin (örn: Ahmet Yılmaz)<br>
                                <strong>3. Adım:</strong> Ders adı (örn: Matematik)<br>
                                <strong>4. Adım:</strong> Gün + Saat (örn: Salı 12:30-14:30)<br><br>
                                <span style="color: var(--tugva-primary); font-weight: bold;">
                                    ✅ Sonuç: Ahmet Yılmaz her Salı 12:30'da 9-A sınıfında Matematik dersi verecek
                                </span><br>
                                <span style="color: var(--tugva-secondary);">
                                    🔄 Sistem otomatik 8 hafta boyunca dersler oluşturur
                                </span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon: Mevcut Programlar -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-week me-2"></i>
                            Haftalık Programlar (<?php echo count($schedules); ?> ders)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schedules)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <h5>Henüz program eklenmemiş</h5>
                                <p>Sol taraftaki formu kullanarak haftalık ders programını oluşturun.</p>
                            </div>
                        <?php else: ?>
                            <!-- Günlere göre gruplama -->
                            <?php 
                            $groupedSchedules = [];
                            foreach ($schedules as $schedule) {
                                $groupedSchedules[$schedule['day_of_week']][] = $schedule;
                            }
                            ?>
                            
                            <?php foreach ($days as $dayNum => $dayName): ?>
                                <?php if (isset($groupedSchedules[$dayNum])): ?>
                                    <h6 class="mb-3 mt-4">
                                        <i class="fas fa-calendar-day me-2 text-primary"></i>
                                        <?php echo $dayName; ?>
                                    </h6>
                                    
                                    <?php foreach ($groupedSchedules[$dayNum] as $schedule): ?>
                                        <div class="schedule-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <strong><?php echo htmlspecialchars($schedule['lesson_name']); ?></strong>
                                                    </h6>
                                                    <div class="text-muted mb-2">
                                                        <i class="fas fa-school me-1"></i>
                                                        <?php echo htmlspecialchars($schedule['class_name']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($schedule['teacher_name']); ?>
                                                    </div>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('H:i', strtotime($schedule['start_time'])); ?> - 
                                                        <?php echo date('H:i', strtotime($schedule['end_time'])); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <a href="edit-schedule.php?id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu programı silmek istediğinizden emin misiniz?\\n\\nNot: Yoklama alınmış dersler varsa program silinemez.')">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                        <button type="submit" name="delete_schedule" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
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
                        <a href="manage-classes.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-school"></i> Sınıf Yönetimi
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