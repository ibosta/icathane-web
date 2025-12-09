<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/LessonManager.php';

$auth = new Auth($pdo);
$auth->requireLogin();

if (!$auth->isTeacher()) {
    header('Location: ../index.php');
    exit;
}

$lessonManager = new LessonManager($pdo);
$teacherId = $_SESSION['user_id'];

// Bugünkü dersler
$todayLessons = $lessonManager->getTodayLessons($teacherId);

// Yaklaşan dersler
$upcomingLessons = $lessonManager->getUpcomingLessons($teacherId, 7);

// Eksik yoklamalar (KIRMIZI UYARILAR)
$missingAttendance = $lessonManager->getMissingAttendance($teacherId);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğretmen Paneli - TÜGVA Kocaeli Icathane</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --tugva-primary: #1B9B9B;
            --tugva-secondary: #0F7A7A;
            --tugva-light: #F5FDFD;
            --tugva-accent: #E8F8F8;
            --tugva-danger: #dc3545;
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
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-tugva:hover {
            background: var(--tugva-secondary);
            color: white;
            transform: translateY(-2px);
        }
        
        .lesson-card {
            border-left: 4px solid var(--tugva-primary);
            transition: transform 0.3s ease;
        }
        
        .lesson-card:hover {
            transform: translateX(5px);
        }
        
        .missing-lesson {
            border-left: 4px solid var(--tugva-danger);
            background-color: #fff5f5;
        }
        
        .time-badge {
            background-color: var(--tugva-accent);
            color: var(--tugva-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .overdue-badge {
            background-color: var(--tugva-danger);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Mobil Responsive Ayarları */
        @media (max-width: 768px) {
            .welcome-header {
                padding: 1rem;
                text-align: center;
            }
            
            .welcome-header h2 {
                font-size: 1.4rem;
            }
            
            .welcome-header p {
                font-size: 0.9rem;
            }
            
            /* Mobil istatistik kartları */
            .stats-card {
                padding: 1rem 0.5rem;
                margin-bottom: 1rem;
            }
            
            .stats-number {
                font-size: 2rem;
            }
            
            /* Mobil ders kartları */
            .lesson-card {
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            .lesson-card .card-body {
                padding: 1rem;
            }
            
            .lesson-card h6 {
                font-size: 1rem;
            }
            
            .time-badge {
                font-size: 0.8rem;
                padding: 0.2rem 0.5rem;
            }
            
            /* Container padding */
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            /* Mobil navbar */
            .navbar-brand .brand-text {
                font-size: 1.1rem;
            }
            
            .navbar-collapse {
                background: rgba(15, 122, 122, 0.95);
                margin-top: 1rem;
                border-radius: 10px;
                padding: 1rem;
            }
            
            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
                border-radius: 8px;
                margin-bottom: 0.25rem;
                transition: all 0.3s ease;
            }
            
            .navbar-nav .nav-link:hover {
                background-color: rgba(27, 155, 155, 0.3);
                transform: translateX(5px);
            }
            
            /* Mobil eksik yoklama uyarısı */
            .missing-lesson {
                margin-bottom: 1rem;
            }
            
            .missing-lesson .card-body {
                padding: 1rem;
            }
            
            .overdue-badge {
                font-size: 0.75rem;
                padding: 0.2rem 0.5rem;
            }
            
            /* Mobil butonlar */
            .btn {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
            }
            
            .btn-tugva {
                padding: 0.6rem 1rem;
            }
            
            /* Mobil grid düzeni */
            .row {
                margin-left: -8px;
                margin-right: -8px;
            }
            
            .col-md-4 {
                padding-left: 8px;
                padding-right: 8px;
                margin-bottom: 1rem;
            }
            
            /* Alert düzenlemeleri */
            .alert {
                font-size: 0.9rem;
                padding: 1rem;
            }
            
            .alert h5 {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 576px) {
            /* Çok küçük ekranlar */
            .welcome-header h2 {
                font-size: 1.2rem;
            }
            
            .stats-number {
                font-size: 1.8rem;
            }
            
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            /* İstatistikler tek kolon */
            .col-md-4 {
                flex: 0 0 auto;
                width: 100%;
                margin-bottom: 1rem;
            }
            
            /* Kart başlıkları */
            .card-header h5 {
                font-size: 1rem;
            }
            
            /* Ders kartlarında buton düzeni */
            .lesson-card .d-flex {
                flex-direction: column;
                gap: 1rem;
            }
            
            .lesson-card .d-flex > div:last-child {
                text-align: center;
            }
            
            /* Yaklaşan dersler listesi */
            .border-bottom {
                padding: 0.75rem 0;
            }
            
            /* Hızlı işlemler */
            .card:last-child .card-body {
                text-align: center;
            }
            
            .card:last-child .btn {
                margin-bottom: 0.5rem;
                width: 100%;
            }
        }
        
        /* Tablet için özel ayarlar */
        @media (min-width: 768px) and (max-width: 1024px) {
            .stats-card {
                padding: 1.5rem 1rem;
            }
            
            .lesson-card {
                padding: 1.2rem;
            }
        }
        
        .stats-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--tugva-primary);
        }
        
        .welcome-header {
            background: linear-gradient(135deg, var(--tugva-accent), white);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navbar - Mobil Uyumlu -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                <span class="brand-text">TÜGVA Kocaeli Icathane</span>
            </a>
            
            <!-- Mobil Toggle Button -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Mobil Menü -->
                <ul class="navbar-nav me-auto d-lg-none">
                    <li class="nav-item">
                        <a class="nav-link" href="teacher.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Ana Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="teacher-schedule-view.php">
                            <i class="fas fa-calendar-week me-2"></i>
                            Ders Programım
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance.php">
                            <i class="fas fa-clipboard-check me-2"></i>
                            Yoklama Al
                        </a>
                    </li>
                </ul>
                
                <!-- Kullanıcı Bilgileri -->
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3 d-none d-lg-inline">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </span>
                    <div class="d-lg-none">
                        <div class="nav-link text-light">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </div>
                    </div>
                    <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt"></i> 
                        <span class="d-none d-sm-inline">Çıkış</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Hoş Geldin Mesajı -->
        <div class="welcome-header">
            <h2 class="mb-1">Hoş Geldiniz, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-day me-2"></i>
                <?php echo date('d F Y, l'); ?>
            </p>
        </div>

        <!-- Eksik Yoklamalar - KIRMIZI UYARI -->
        <?php if (!empty($missingAttendance)): ?>
        <div class="alert alert-danger" role="alert">
            <h5 class="alert-heading">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Eksik Yoklamalar!
            </h5>
            <p class="mb-0">Geçmiş derslerinizin yoklamasını tamamlamanız gerekiyor.</p>
        </div>
        
        <div class="card missing-lesson mb-4">
            <div class="card-header bg-danger">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Yoklaması Eksik Dersler
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($missingAttendance as $lesson): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong><?php echo htmlspecialchars($lesson['class_name']); ?></strong> - 
                        <?php echo htmlspecialchars($lesson['lesson_name']); ?>
                        <br>
                        <small class="text-muted">
                            <?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?> - 
                            <?php echo date('H:i', strtotime($lesson['start_time'])); ?>-<?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                        </small>
                    </div>
                    <div>
                        <span class="overdue-badge me-2">
                            <?php echo $lesson['days_overdue']; ?> gün gecikme
                        </span>
                        <a href="attendance.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-danger btn-sm">
                            <i class="fas fa-plus"></i> Yoklama Ekle
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body stats-card">
                        <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                        <div class="stats-number"><?php echo count($todayLessons); ?></div>
                        <div class="text-muted">Bugünkü Dersler</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body stats-card">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <div class="stats-number"><?php echo count($upcomingLessons); ?></div>
                        <div class="text-muted">Yaklaşan Dersler</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body stats-card">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                        <div class="stats-number"><?php echo count($missingAttendance); ?></div>
                        <div class="text-muted">Eksik Yoklamalar</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bugünkü Dersler -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day me-2"></i>
                    Bugünkü Derslerim
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($todayLessons)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                        <p>Bugün dersınız bulunmamaktadır.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayLessons as $lesson): ?>
                    <div class="card lesson-card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">
                                        <?php echo htmlspecialchars($lesson['class_name']); ?> - 
                                        <?php echo htmlspecialchars($lesson['lesson_name']); ?>
                                    </h6>
                                    <div class="mb-2">
                                        <span class="time-badge">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('H:i', strtotime($lesson['start_time'])); ?>-<?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                                        </span>
                                        <span class="badge bg-secondary ms-2">
                                            <?php echo $lesson['student_count']; ?> öğrenci
                                        </span>
                                    </div>
                                    <?php if ($lesson['topic']): ?>
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>
                                            Konu: <?php echo htmlspecialchars($lesson['topic']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($lesson['attendance_marked']): ?>
                                        <span class="badge bg-success me-2">
                                            <i class="fas fa-check"></i> Yoklama Alındı
                                        </span>
                                        <a href="attendance.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i> Düzenle
                                        </a>
                                    <?php else: ?>
                                        <a href="attendance.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-tugva">
                                            <i class="fas fa-plus"></i> Yoklama Al
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Yaklaşan Dersler -->
        <?php if (!empty($upcomingLessons)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-week me-2"></i>
                    Bu Haftaki Derslerim
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($upcomingLessons as $lesson): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong><?php echo htmlspecialchars($lesson['class_name']); ?></strong> - 
                        <?php echo htmlspecialchars($lesson['lesson_name']); ?>
                        <br>
                        <small class="text-muted">
                            <?php echo date('d.m.Y l', strtotime($lesson['lesson_date'])); ?> - 
                            <?php echo date('H:i', strtotime($lesson['start_time'])); ?>-<?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                        </small>
                    </div>
                    <span class="badge bg-light text-dark">Yaklaşan</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hızlı Navigasyon -->
        <div class="card mt-4">
            <div class="card-body">
                <h6>Hızlı İşlemler:</h6>
                <a href="teacher-schedule-view.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-calendar-week"></i> Ders Programım
                </a>
                <a href="teacher-materials.php" class="btn btn-outline-info me-2">
                    <i class="fas fa-folder-open"></i> Ders Materyalleri
                </a>
                <a href="attendance.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-clipboard-check"></i> Yoklama Al
                </a>
                <a href="../auth/logout.php" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>