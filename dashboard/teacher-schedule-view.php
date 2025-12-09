<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();

if (!$auth->isTeacher()) {
    header('Location: ../index.php');
    exit;
}

$teacherId = $_SESSION['user_id'];

// Öğretmenin tüm programlarını getir
$stmt = $pdo->prepare("
    SELECT 
        ws.id,
        ws.day_of_week,
        ws.start_time,
        ws.end_time,
        ws.lesson_name,
        c.name as class_name,
        c.academic_year
    FROM weekly_schedule ws
    JOIN classes c ON ws.class_id = c.id
    WHERE ws.teacher_id = ? AND ws.is_active = 1
    ORDER BY ws.day_of_week, ws.start_time
");
$stmt->execute([$teacherId]);
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
    <title>Ders Programım - TÜGVA Kocaeli Icathane</title>
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
        
        .schedule-item {
            background: white;
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
        
        .day-header {
            background: var(--tugva-accent);
            color: var(--tugva-primary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .time-badge {
            background: var(--tugva-primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="teacher.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Ders Programım
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Çıkış
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-week me-2"></i>
                    Haftalık Ders Programım
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($schedules)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                        <h5>Henüz ders programınız yok</h5>
                        <p>Yöneticiniz size sınıf atadığında programınız burada görünecektir.</p>
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
                            <div class="day-header">
                                <i class="fas fa-calendar-day me-2"></i>
                                <?php echo $dayName; ?>
                            </div>
                            
                            <?php foreach ($groupedSchedules[$dayNum] as $schedule): ?>
                                <div class="schedule-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-book me-2"></i>
                                                <strong><?php echo htmlspecialchars($schedule['lesson_name']); ?></strong>
                                            </h6>
                                            <div class="text-muted mb-2">
                                                <i class="fas fa-school me-1"></i>
                                                <?php echo htmlspecialchars($schedule['class_name']); ?>
                                                <span class="mx-2">•</span>
                                                <small><?php echo htmlspecialchars($schedule['academic_year']); ?></small>
                                            </div>
                                            <span class="time-badge">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($schedule['start_time'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['end_time'])); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <a href="attendance.php?schedule_id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-clipboard-check"></i> Yoklama
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <br>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Özet Bilgi -->
                    <div class="mt-4 p-3" style="background: var(--tugva-accent); border-radius: 10px;">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h5 class="text-primary"><?php echo count($schedules); ?></h5>
                                <small>Toplam Ders</small>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-primary"><?php echo count(array_unique(array_column($schedules, 'class_name'))); ?></h5>
                                <small>Farklı Sınıf</small>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-primary"><?php echo count($groupedSchedules); ?></h5>
                                <small>Ders Günü</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hızlı Navigasyon -->
        <div class="card">
            <div class="card-body">
                <h6>İşlemler:</h6>
                <a href="teacher.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-tachometer-alt"></i> Ana Panel
                </a>
                <a href="attendance.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-clipboard-check"></i> Yoklama Al
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>