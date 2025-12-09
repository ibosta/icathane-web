<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$studentId = $_GET['student_id'] ?? null;
$classId = $_GET['class_id'] ?? null;

if (!$studentId || !$classId) {
    header('Location: manage-classes.php');
    exit;
}

// Öğrenci ve sınıf bilgilerini al
$stmt = $pdo->prepare("
    SELECT 
        s.full_name as student_name,
        c.name as class_name,
        c.academic_year,
        s.created_at as enrollment_date
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ? AND c.id = ?
");
$stmt->execute([$studentId, $classId]);
$studentInfo = $stmt->fetch();

if (!$studentInfo) {
    header('Location: manage-classes.php');
    exit;
}

// Öğrencinin tüm yoklama geçmişini al
$stmt = $pdo->prepare("
    SELECT 
        l.lesson_date,
        ws.lesson_name,
        ws.start_time,
        ws.end_time,
        ws.day_of_week,
        u.full_name as teacher_name,
        l.topic,
        COALESCE(a.status, 'absent') as status,
        a.recorded_at,
        l.attendance_marked,
        CASE 
            WHEN l.attendance_marked = 0 THEN 'not_marked'
            ELSE COALESCE(a.status, 'absent')
        END as display_status
    FROM lessons l
    JOIN weekly_schedule ws ON l.schedule_id = ws.id
    JOIN users u ON ws.teacher_id = u.id
    LEFT JOIN attendance a ON l.id = a.lesson_id AND a.student_id = ?
    WHERE ws.class_id = ?
    ORDER BY l.lesson_date DESC
");
$stmt->execute([$studentId, $classId]);
$attendanceHistory = $stmt->fetchAll();

// İstatistikleri hesapla
$totalLessons = count(array_filter($attendanceHistory, function($h) { return $h['display_status'] !== 'not_marked'; }));
$presentCount = count(array_filter($attendanceHistory, function($h) { return $h['display_status'] === 'present'; }));
$absentCount = count(array_filter($attendanceHistory, function($h) { return $h['display_status'] === 'absent'; }));
$notMarkedCount = count(array_filter($attendanceHistory, function($h) { return $h['display_status'] === 'not_marked'; }));

$attendanceRate = $totalLessons > 0 ? round(($presentCount / $totalLessons) * 100, 1) : 0;
$absenceRate = $totalLessons > 0 ? round(($absentCount / $totalLessons) * 100, 1) : 0;

// Aylık devam istatistikleri
$monthlyStats = [];
foreach ($attendanceHistory as $record) {
    if ($record['display_status'] !== 'not_marked') {
        $month = date('Y-m', strtotime($record['lesson_date']));
        if (!isset($monthlyStats[$month])) {
            $monthlyStats[$month] = ['total' => 0, 'present' => 0, 'absent' => 0];
        }
        $monthlyStats[$month]['total']++;
        $monthlyStats[$month][$record['display_status']]++;
    }
}

// Haftalık devam durumu (son 4 hafta)
$weeklyStats = [];
for ($i = 0; $i < 4; $i++) {
    $weekStart = date('Y-m-d', strtotime("-$i weeks monday"));
    $weekEnd = date('Y-m-d', strtotime("-$i weeks sunday"));
    $weekLabel = "Hafta " . ($i + 1);
    
    $weeklyStats[$weekLabel] = ['total' => 0, 'present' => 0, 'absent' => 0];
    
    foreach ($attendanceHistory as $record) {
        if ($record['display_status'] !== 'not_marked' && 
            $record['lesson_date'] >= $weekStart && 
            $record['lesson_date'] <= $weekEnd) {
            $weeklyStats[$weekLabel]['total']++;
            $weeklyStats[$weekLabel][$record['display_status']]++;
        }
    }
}

$days = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Devam Raporu - TÜGVA Kocaeli Icathane</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --tugva-primary: #1B9B9B;
            --tugva-secondary: #0F7A7A;
            --tugva-light: #F5FDFD;
            --tugva-accent: #E8F8F8;
            --tugva-danger: #dc3545;
            --tugva-success: #28a745;
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
        
        .student-info {
            background: var(--tugva-accent);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, white, var(--tugva-accent));
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .progress-custom {
            height: 12px;
            border-radius: 6px;
            margin: 0.5rem 0;
        }
        
        .attendance-item {
            border-left: 4px solid;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .attendance-item.present {
            border-left-color: var(--tugva-success);
            background-color: #f8fff8;
        }
        
        .attendance-item.absent {
            border-left-color: var(--tugva-danger);
            background-color: #fff5f5;
        }
        
        .attendance-item.not_marked {
            border-left-color: var(--tugva-warning);
            background-color: #fffbf0;
        }
        
        .monthly-card {
            background: white;
            border: 1px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .evaluation-box {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        
        .evaluation-excellent { background: linear-gradient(135deg, #e8f5e8, #f0fff0); border-left: 4px solid var(--tugva-success); }
        .evaluation-good { background: linear-gradient(135deg, #e8f4fd, #f0f9ff); border-left: 4px solid var(--tugva-primary); }
        .evaluation-warning { background: linear-gradient(135deg, #fff3cd, #fffbf0); border-left: 4px solid var(--tugva-warning); }
        .evaluation-critical { background: linear-gradient(135deg, #ffe6e6, #fff5f5); border-left: 4px solid var(--tugva-danger); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="class-details.php?id=<?php echo $classId; ?>">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Devam Raporu
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
        <!-- Öğrenci Bilgileri -->
        <div class="student-info">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-1">
                        <i class="fas fa-user-graduate me-2" style="color: var(--tugva-primary);"></i>
                        <?php echo htmlspecialchars($studentInfo['student_name']); ?>
                    </h2>
                    <p class="mb-1">
                        <strong>Sınıf:</strong> <?php echo htmlspecialchars($studentInfo['class_name']); ?> 
                        (<?php echo htmlspecialchars($studentInfo['academic_year']); ?>)
                    </p>
                    <p class="mb-0">
                        <strong>Kayıt Tarihi:</strong> <?php echo date('d F Y', strtotime($studentInfo['enrollment_date'])); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Yazdır
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="exportReport()">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ana İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--tugva-primary);"><?php echo $totalLessons; ?></div>
                    <div class="stat-label" style="color: var(--tugva-primary);">Toplam Ders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--tugva-success);"><?php echo $presentCount; ?></div>
                    <div class="stat-label" style="color: var(--tugva-success);">Katıldığı Ders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--tugva-danger);"><?php echo $absentCount; ?></div>
                    <div class="stat-label" style="color: var(--tugva-danger);">Devamsızlık</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--tugva-primary);">%<?php echo $attendanceRate; ?></div>
                    <div class="stat-label" style="color: var(--tugva-primary);">Devam Oranı</div>
                    <div class="progress progress-custom">
                        <div class="progress-bar" style="width: <?php echo $attendanceRate; ?>%; background-color: var(--tugva-primary);"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Değerlendirme -->
        <?php if ($attendanceRate >= 90): ?>
            <div class="evaluation-box evaluation-excellent">
                <h5><i class="fas fa-star me-2" style="color: var(--tugva-success);"></i>Mükemmel Devam!</h5>
                <p class="mb-0">Bu öğrenci çok düzenli ders takibi göstermektedir. %<?php echo $attendanceRate; ?> devam oranı ile örnek bir performans sergilemektedir.</p>
            </div>
        <?php elseif ($attendanceRate >= 75): ?>
            <div class="evaluation-box evaluation-good">
                <h5><i class="fas fa-thumbs-up me-2" style="color: var(--tugva-primary);"></i>İyi Devam</h5>
                <p class="mb-0">Devam durumu kabul edilebilir seviyededir. %<?php echo $attendanceRate; ?> oranı ile düzenli katılım göstermektedir.</p>
            </div>
        <?php elseif ($attendanceRate >= 60): ?>
            <div class="evaluation-box evaluation-warning">
                <h5><i class="fas fa-exclamation-triangle me-2" style="color: var(--tugva-warning);"></i>Dikkat Gerekli</h5>
                <p class="mb-0">Devam oranı %<?php echo $attendanceRate; ?> ile yetersiz seviyededir. Öğrenci ve veli ile görüşme yapılması önerilir.</p>
            </div>
        <?php else: ?>
            <div class="evaluation-box evaluation-critical">
                <h5><i class="fas fa-times-circle me-2" style="color: var(--tugva-danger);"></i>Kritik Durum</h5>
                <p class="mb-0">%<?php echo $attendanceRate; ?> devam oranı kritik seviyededir. Acil olarak öğrenci ve veli ile görüşme yapılmalıdır.</p>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Haftalık İstatistikler -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-week me-2"></i>
                            Son 4 Hafta Analizi
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($weeklyStats as $week => $stats): ?>
                            <?php 
                            $weekRate = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 1) : 0;
                            $weekColor = $weekRate >= 80 ? 'success' : ($weekRate >= 60 ? 'warning' : 'danger');
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-0"><?php echo $week; ?></h6>
                                    <small class="text-muted">
                                        <?php echo $stats['present']; ?> geldi / <?php echo $stats['total']; ?> ders
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $weekColor; ?> p-2">
                                        %<?php echo $weekRate; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="progress progress-custom">
                                <div class="progress-bar bg-<?php echo $weekColor; ?>" style="width: <?php echo $weekRate; ?>%;"></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty(array_filter($weeklyStats, function($s) { return $s['total'] > 0; }))): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                <p>Son 4 haftada ders kaydı bulunmuyor.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Aylık İstatistikler -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Aylık Performans
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($monthlyStats)): ?>
                            <?php foreach (array_reverse($monthlyStats, true) as $month => $stats): ?>
                                <?php 
                                $monthRate = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 1) : 0;
                                $monthName = date('F Y', strtotime($month . '-01'));
                                $monthColor = $monthRate >= 80 ? 'success' : ($monthRate >= 60 ? 'warning' : 'danger');
                                ?>
                                <div class="monthly-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><?php echo $monthName; ?></h6>
                                        <span class="badge bg-<?php echo $monthColor; ?>">%<?php echo $monthRate; ?></span>
                                    </div>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-success"><strong><?php echo $stats['present']; ?></strong><br>Geldi</small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-danger"><strong><?php echo $stats['absent']; ?></strong><br>Gelmedi</small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-primary"><strong><?php echo $stats['total']; ?></strong><br>Toplam</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <p>Henüz aylık veri bulunmuyor.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detaylı Devam Geçmişi -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Detaylı Devam Geçmişi (<?php echo count($attendanceHistory); ?> ders)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($attendanceHistory)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                        <h5>Henüz ders kaydı bulunmuyor</h5>
                        <p>Bu öğrenci için ders geçmişi bulunmamaktadır.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-12">
                            <?php foreach ($attendanceHistory as $record): ?>
                                <div class="attendance-item <?php echo $record['display_status']; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1">
                                                <strong><?php echo htmlspecialchars($record['lesson_name']); ?></strong>
                                                <span class="ms-2">
                                                    <?php if ($record['display_status'] === 'present'): ?>
                                                        <span class="badge bg-success">✅ Katıldı</span>
                                                    <?php elseif ($record['display_status'] === 'absent'): ?>
                                                        <span class="badge bg-danger">❌ Katılmadı</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">⏳ Yoklama Alınmamış</span>
                                                    <?php endif; ?>
                                                </span>
                                            </h6>
                                            <div class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d F Y, l', strtotime($record['lesson_date'])); ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($record['start_time'])); ?>-<?php echo date('H:i', strtotime($record['end_time'])); ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($record['teacher_name']); ?>
                                            </div>
                                            <?php if ($record['topic']): ?>
                                                <div class="mt-2">
                                                    <small><strong>Konu:</strong> 
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($record['topic']); ?></span>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <?php if ($record['recorded_at']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-save me-1"></i>
                                                    Yoklama: <?php echo date('d.m.Y H:i', strtotime($record['recorded_at'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Özet ve Öneriler -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lightbulb me-2"></i>
                            Öneriler ve Yorum
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($attendanceRate >= 90): ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-trophy me-2"></i>Mükemmel Performans</h6>
                                <ul class="mb-0">
                                    <li>Öğrenci düzenli katılım göstermektedir</li>
                                    <li>Motivasyonu yüksek tutulmalı</li>
                                    <li>Örnek davranışları paylaşılabilir</li>
                                </ul>
                            </div>
                        <?php elseif ($attendanceRate >= 75): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>İyi Seviye</h6>
                                <ul class="mb-0">
                                    <li>Genel olarak düzenli katılım var</li>
                                    <li>Eksik derslerin nedenleri araştırılabilir</li>
                                    <li>Devam oranını %90'a çıkarma hedeflenebilir</li>
                                </ul>
                            </div>
                        <?php elseif ($attendanceRate >= 60): ?>
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Dikkat Gerekli</h6>
                                <ul class="mb-0">
                                    <li>Veli ile görüşme yapılması önerilir</li>
                                    <li>Devamsızlık nedenleri araştırılmalı</li>
                                    <li>Öğrenciye destek sağlanmalı</li>
                                    <li>Yakın takip gereklidir</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-circle me-2"></i>Acil Müdahale</h6>
                                <ul class="mb-0">
                                    <li>Derhal veli ile görüşme yapılmalı</li>
                                    <li>Devamsızlığın köklü nedenleri bulunmalı</li>
                                    <li>Özel destek programı uygulanmalı</li>
                                    <li>Günlük takip gereklidir</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <small>
                                <strong>Rapor Tarihi:</strong> <?php echo date('d F Y, H:i'); ?><br>
                                <strong>Raporu Hazırlayan:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-download me-2"></i>
                            Rapor İşlemleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>
                                Raporu Yazdır
                            </button>
                            <button class="btn btn-outline-success" onclick="exportReport()">
                                <i class="fas fa-file-excel me-2"></i>
                                Excel'e Aktar
                            </button>
                            <a href="class-details.php?id=<?php echo $classId; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Sınıfa Dön
                            </a>
                        </div>
                        
                        <hr>
                        
                        <h6>Hızlı İstatistikler</h6>
                        <small>
                            <strong>Toplam Kayıtlı Gün:</strong> <?php echo count($attendanceHistory); ?><br>
                            <strong>Yoklama Alınan:</strong> <?php echo $totalLessons; ?><br>
                            <strong>Bekleyen Yoklama:</strong> <?php echo $notMarkedCount; ?><br>
                            <strong>En Son Ders:</strong> 
                            <?php 
                            if (!empty($attendanceHistory)) {
                                echo date('d.m.Y', strtotime($attendanceHistory[0]['lesson_date']));
                            } else {
                                echo "Henüz ders yok";
                            }
                            ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportReport() {
            // Basit CSV export
            const studentName = "<?php echo addslashes($studentInfo['student_name']); ?>";
            const className = "<?php echo addslashes($studentInfo['class_name']); ?>";
            
            let csv = "Öğrenci Devam Raporu\n";
            csv += "Öğrenci: " + studentName + "\n";
            csv += "Sınıf: " + className + "\n";
            csv += "Rapor Tarihi: <?php echo date('d.m.Y H:i'); ?>\n\n";
            csv += "ÖZET İSTATİSTİKLER\n";
            csv += "Toplam Ders,Katıldığı,Devamsızlık,Devam Oranı\n";
            csv += "<?php echo $totalLessons; ?>,<?php echo $presentCount; ?>,<?php echo $absentCount; ?>,<?php echo $attendanceRate; ?>%\n\n";
            csv += "DETAYLI GEÇMİŞ\n";
            csv += "Tarih,Ders,Öğretmen,Durum,Konu\n";
            
            <?php foreach ($attendanceHistory as $record): ?>
            csv += "<?php echo date('d.m.Y', strtotime($record['lesson_date'])); ?>,";
            csv += "<?php echo addslashes($record['lesson_name']); ?>,";
            csv += "<?php echo addslashes($record['teacher_name']); ?>,";
            csv += "<?php echo $record['display_status'] === 'present' ? 'Katıldı' : ($record['display_status'] === 'absent' ? 'Katılmadı' : 'Yoklama Alınmamış'); ?>,";
            csv += "<?php echo addslashes($record['topic'] ?? ''); ?>\n";
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = studentName + '_devam_raporu.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>