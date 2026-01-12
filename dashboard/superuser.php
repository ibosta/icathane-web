<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';
require_once '../classes/LessonManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);
$lessonManager = new LessonManager($pdo);

// Sistem istatistikleri
$stats = $userManager->getSystemStats();

// Eksik yoklamalar
$missingAttendance = $lessonManager->getAllMissingAttendance();

// Son eklenen öğretmenler
$teachers = $userManager->getAllTeachers();
$recentTeachers = array_slice($teachers, 0, 5);

// Son eklenen sınıflar
$classes = $userManager->getAllClasses();
$recentClasses = array_slice($classes, 0, 5);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli - TÜGVA Kocaeli Icathane</title>
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
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1rem 1.5rem;
        }

        .stats-card {
            text-align: center;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, white, var(--tugva-accent));
            position: relative;
            overflow: hidden;
        }

        .clickable-card {
            cursor: pointer;
            transition: all 0.4s ease;
            border: 2px solid transparent;
        }

        .clickable-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 40px rgba(27, 155, 155, 0.3);
            border-color: var(--tugva-primary);
        }

        .clickable-card:hover .stats-number {
            transform: scale(1.1);
            color: var(--tugva-secondary);
        }

        .clickable-card:hover .stats-action {
            opacity: 1;
            transform: translateY(0);
        }

        .stats-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--tugva-primary);
            transition: all 0.3s ease;
        }

        .stats-label {
            font-size: 1.1rem;
            color: var(--tugva-secondary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stats-action {
            font-size: 0.9rem;
            color: var(--tugva-primary);
            font-weight: 600;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }

        /* Eksik yoklama kartı için özel stiller */
        .danger-card:hover {
            border-color: var(--tugva-danger);
            box-shadow: 0 15px 40px rgba(220, 53, 69, 0.3);
        }

        .danger-number {
            color: var(--tugva-danger) !important;
        }

        .danger-label {
            color: var(--tugva-danger) !important;
        }

        .danger-action {
            color: var(--tugva-danger) !important;
        }

        .danger-card:hover .stats-number {
            color: #c82333 !important;
        }

        /* Kart içi animasyon efekti */
        .clickable-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(27, 155, 155, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s ease;
            opacity: 0;
        }

        .clickable-card:hover::before {
            animation: shine 0.6s ease;
        }

        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
                opacity: 0;
            }

            50% {
                opacity: 1;
            }

            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
                opacity: 0;
            }
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

        .quick-action-modern {
            background: linear-gradient(135deg, white, #f8fdfd);
            border: 2px solid var(--tugva-accent);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.4s ease;
            text-decoration: none;
            color: var(--tugva-primary);
            display: block;
            position: relative;
            overflow: hidden;
        }

        .quick-action-modern:hover {
            border-color: var(--tugva-primary);
            background: linear-gradient(135deg, var(--tugva-accent), white);
            color: var(--tugva-secondary);
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(27, 155, 155, 0.25);
        }

        .quick-icon-wrapper {
            background: linear-gradient(135deg, var(--tugva-accent), rgba(27, 155, 155, 0.1));
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            transition: all 0.3s ease;
        }

        .quick-action-modern:hover .quick-icon-wrapper {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            color: white;
            transform: rotate(5deg) scale(1.1);
        }

        .quick-action-modern:hover .quick-icon-wrapper i {
            color: white;
        }

        .quick-action-arrow {
            position: absolute;
            top: 15px;
            right: 15px;
            opacity: 0;
            transition: all 0.3s ease;
            background: var(--tugva-primary);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .quick-action-modern:hover .quick-action-arrow {
            opacity: 1;
            transform: translateX(0) scale(1);
        }

        .quick-action-modern h6 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .quick-action-modern:hover h6 {
            color: var(--tugva-dark);
        }

        .quick-action-modern small {
            opacity: 0.8;
            transition: all 0.3s ease;
        }

        .quick-action-modern:hover small {
            opacity: 1;
            color: var(--tugva-secondary);
        }

        .welcome-header {
            background: linear-gradient(135deg, var(--tugva-accent), white);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .missing-attendance {
            border-left: 4px solid var(--tugva-danger);
            background-color: #fff5f5;
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
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }

            100% {
                opacity: 1;
            }
        }

        /* Mobil Responsive Ayarları */
        @media (max-width: 768px) {
            .welcome-header {
                padding: 1rem;
                text-align: center;
            }

            .welcome-header h2 {
                font-size: 1.5rem;
            }

            /* Mobil istatistik kartları */
            .stats-card {
                padding: 1rem 0.5rem;
                margin-bottom: 1rem;
            }

            .stats-number {
                font-size: 2rem;
            }

            .stats-label {
                font-size: 0.9rem;
            }

            .clickable-card:hover {
                transform: translateY(-3px) scale(1.01);
            }

            /* Mobil hızlı işlemler */
            .quick-action-modern {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .quick-icon-wrapper {
                width: 60px;
                height: 60px;
                margin-bottom: 0.75rem;
            }

            .quick-action-modern h6 {
                font-size: 1rem;
            }

            .quick-action-modern small {
                font-size: 0.8rem;
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

            /* Eksik yoklamalar tablosu */
            .table-responsive {
                font-size: 0.8rem;
            }

            /* Mobil kartlar için grid düzeni */
            .row {
                margin-left: -8px;
                margin-right: -8px;
            }

            .col-lg-2,
            .col-md-4,
            .col-sm-6 {
                padding-left: 8px;
                padding-right: 8px;
            }

            /* Eksik yoklama uyarısı */
            .alert {
                font-size: 0.9rem;
                padding: 1rem;
            }

            /* Mobil butonlar */
            .btn {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
            }
        }

        @media (max-width: 576px) {

            /* Çok küçük ekranlar */
            .stats-number {
                font-size: 1.8rem;
            }

            .quick-icon-wrapper {
                width: 50px;
                height: 50px;
            }

            .quick-action-modern h6 {
                font-size: 0.9rem;
            }

            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            /* İstatistikler tek kolon */
            .col-lg-2 {
                flex: 0 0 auto;
                width: 50%;
            }

            .welcome-header h2 {
                font-size: 1.3rem;
            }

            .welcome-header p {
                font-size: 0.9rem;
            }
        }

        /* Tablet için özel ayarlar */
        @media (min-width: 768px) and (max-width: 1024px) {
            .stats-card {
                padding: 1.5rem 1rem;
            }

            .quick-action-modern {
                padding: 1.2rem;
            }

            .quick-icon-wrapper {
                width: 70px;
                height: 70px;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar - Mobil Uyumlu -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-crown me-2"></i>
                <span class="brand-text">TÜGVA Kocaeli Icathane</span>
                <span class="brand-role d-none d-md-inline"> - Yönetici</span>
            </a>

            <!-- Mobil Toggle Button -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Mobil Menü -->
                <ul class="navbar-nav me-auto d-lg-none">
                    <li class="nav-item">
                        <a class="nav-link" href="manage-teachers.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Öğretmenler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage-classes.php">
                            <i class="fas fa-school me-2"></i>
                            Sınıflar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage-schedules.php">
                            <i class="fas fa-calendar me-2"></i>
                            Programlar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            Raporlar
                        </a>
                    </li>
                </ul>

                <!-- Kullanıcı Bilgileri -->
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3 d-none d-lg-inline">
                        <i class="fas fa-user-shield me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </span>
                    <div class="d-lg-none">
                        <div class="nav-link text-light">
                            <i class="fas fa-user-shield me-2"></i>
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
            <h2 class="mb-1">
                <i class="fas fa-tachometer-alt me-2"></i>
                Yönetici Paneli
            </h2>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-day me-2"></i>
                <?php echo date('d F Y, l'); ?> - Sistem durumu ve hızlı işlemler
            </p>
        </div>

        <!-- İstatistikler - Tıklanabilir Kartlar -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <a href="manage-teachers.php" class="text-decoration-none">
                    <div class="card stats-card clickable-card">
                        <i class="fas fa-chalkboard-teacher fa-3x mb-3" style="color: var(--tugva-primary);"></i>
                        <div class="stats-number"><?php echo $stats['teachers']; ?></div>
                        <div class="stats-label">Öğretmen</div>
                        <div class="stats-action">
                            <i class="fas fa-arrow-right"></i> Yönet
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <a href="manage-classes.php" class="text-decoration-none">
                    <div class="card stats-card clickable-card">
                        <i class="fas fa-school fa-3x mb-3" style="color: var(--tugva-primary);"></i>
                        <div class="stats-number"><?php echo $stats['classes']; ?></div>
                        <div class="stats-label">Sınıf</div>
                        <div class="stats-action">
                            <i class="fas fa-arrow-right"></i> Yönet
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <a href="manage-classes.php" class="text-decoration-none">
                    <div class="card stats-card clickable-card">
                        <i class="fas fa-users fa-3x mb-3" style="color: var(--tugva-primary);"></i>
                        <div class="stats-number"><?php echo $stats['students']; ?></div>
                        <div class="stats-label">Öğrenci</div>
                        <div class="stats-action">
                            <i class="fas fa-arrow-right"></i> Listele
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <a href="manage-schedules.php" class="text-decoration-none">
                    <div class="card stats-card clickable-card">
                        <i class="fas fa-calendar-week fa-3x mb-3" style="color: var(--tugva-primary);"></i>
                        <div class="stats-number"><?php echo $stats['weekly_lessons']; ?></div>
                        <div class="stats-label">Bu Hafta Ders</div>
                        <div class="stats-action">
                            <i class="fas fa-arrow-right"></i> Program
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <a href="reports.php?report_type=missing" class="text-decoration-none">
                    <div class="card stats-card clickable-card danger-card">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color: var(--tugva-danger);"></i>
                        <div class="stats-number danger-number"><?php echo $stats['missing_attendance']; ?></div>
                        <div class="stats-label danger-label">Eksik Yoklama</div>
                        <div class="stats-action danger-action">
                            <i class="fas fa-arrow-right"></i> Gör
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Hızlı İşlemler -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Hızlı İşlemler
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="manage-teachers.php" class="quick-action-modern">
                            <div class="quick-icon-wrapper">
                                <i class="fas fa-user-plus fa-2x"></i>
                            </div>
                            <h6>Öğretmen Ekle</h6>
                            <small>Yeni öğretmen hesabı oluştur</small>
                            <div class="quick-action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage-classes.php" class="quick-action-modern">
                            <div class="quick-icon-wrapper">
                                <i class="fas fa-plus-square fa-2x"></i>
                            </div>
                            <h6>Sınıf Ekle</h6>
                            <small>Yeni sınıf oluştur</small>
                            <div class="quick-action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage-schedules.php" class="quick-action-modern">
                            <div class="quick-icon-wrapper">
                                <i class="fas fa-calendar-plus fa-2x"></i>
                            </div>
                            <h6>Program Ekle</h6>
                            <small>Haftalık ders programı</small>
                            <div class="quick-action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports.php" class="quick-action-modern">
                            <div class="quick-icon-wrapper">
                                <i class="fas fa-chart-bar fa-2x"></i>
                            </div>
                            <h6>Raporlar</h6>
                            <small>Anlık yoklama raporları</small>
                            <div class="quick-action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="manage-materials.php" class="quick-action-modern">
                            <div class="quick-icon-wrapper">
                                <i class="fas fa-file-upload fa-2x"></i>
                            </div>
                            <h6>Ders Materyalleri</h6>
                            <small>PDF ve dosya yönetimi</small>
                            <div class="quick-action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Eksik Yoklamalar - UYARI -->
        <?php if (!empty($missingAttendance)): ?>
            <div class="card missing-attendance mb-4">
                <div class="card-header bg-danger">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Eksik Yoklamalar - ACİL DİKKAT!
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Öğretmen</th>
                                    <th>Sınıf</th>
                                    <th>Ders</th>
                                    <th>Tarih & Saat</th>
                                    <th>Gecikme</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($missingAttendance, 0, 10) as $missing): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($missing['teacher_name']); ?></td>
                                        <td><?php echo htmlspecialchars($missing['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($missing['lesson_name']); ?></td>
                                        <td>
                                            <?php echo date('d.m.Y', strtotime($missing['lesson_date'])); ?>
                                            <br>
                                            <small><?php echo date('H:i', strtotime($missing['start_time'])); ?>-<?php echo date('H:i', strtotime($missing['end_time'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="overdue-badge">
                                                <?php echo $missing['days_overdue']; ?> gün
                                            </span>
                                        </td>
                                        <td>
                                            <a href="admin-attendance.php?lesson_id=<?php echo htmlspecialchars($missing['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                class="btn btn-sm btn-outline-danger" title="Yoklamayı Tamamla">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($missingAttendance) > 10): ?>
                        <div class="text-center mt-3">
                            <a href="reports.php?type=missing" class="btn btn-danger">
                                Tüm Eksik Yoklamaları Gör (<?php echo count($missingAttendance); ?> adet)
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Son Öğretmenler -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Öğretmenler
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Ad Soyad</th>
                                        <th>Sınıf Sayısı</th>
                                        <th>Son Giriş</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTeachers as $teacher): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $teacher['class_count']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($teacher['last_login']): ?>
                                                    <small><?php echo date('d.m.Y H:i', strtotime($teacher['last_login'])); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Hiç giriş yapmadı</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center">
                            <a href="manage-teachers.php" class="btn btn-tugva btn-sm">
                                Tüm Öğretmenleri Gör
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Son Sınıflar -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-school me-2"></i>
                            Sınıflar
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Sınıf Adı</th>
                                        <th>Öğrenci</th>
                                        <th>Öğretmen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentClasses as $class): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($class['name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo $class['academic_year']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $class['student_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $class['teacher_count']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center">
                            <a href="manage-classes.php" class="btn btn-tugva btn-sm">
                                Tüm Sınıfları Gör
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>