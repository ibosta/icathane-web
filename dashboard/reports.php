<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);

// Filtre parametreleri
$classFilter = $_GET['class_id'] ?? '';
$teacherFilter = $_GET['teacher_id'] ?? '';
$dateStart = $_GET['date_start'] ?? date('Y-m-01'); // Bu ayın başı
$dateEnd = $_GET['date_end'] ?? date('Y-m-d'); // Bugün
$reportType = $_GET['report_type'] ?? 'attendance';

// Tüm sınıflar ve öğretmenler
$classes = $userManager->getAllClasses();
$teachers = $userManager->getAllTeachers();

// Sınıf bazlı yoklama raporunu getir
function getAttendanceReport($pdo, $classId = '', $teacherId = '', $dateStart = '', $dateEnd = '')
{
    $sql = "
        SELECT 
            l.id as lesson_id,
            l.lesson_date,
            l.topic,
            l.attendance_marked,
            ws.lesson_name,
            ws.start_time,
            ws.end_time,
            c.id as class_id,
            c.name as class_name,
            u.full_name as teacher_name,
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            ROUND(
                (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0) / 
                NULLIF(COUNT(DISTINCT s.id), 0), 1
            ) as attendance_rate
        FROM lessons l
        JOIN weekly_schedule ws ON l.schedule_id = ws.id
        JOIN classes c ON ws.class_id = c.id
        JOIN users u ON ws.teacher_id = u.id
        LEFT JOIN students s ON c.id = s.class_id AND s.is_active = 1
        LEFT JOIN attendance a ON l.id = a.lesson_id AND s.id = a.student_id
        WHERE l.attendance_marked = 1
    ";

    $params = [];

    if (!empty($classId)) {
        $sql .= " AND c.id = ?";
        $params[] = $classId;
    }

    if (!empty($teacherId)) {
        $sql .= " AND u.id = ?";
        $params[] = $teacherId;
    }

    if (!empty($dateStart)) {
        $sql .= " AND l.lesson_date >= ?";
        $params[] = $dateStart;
    }

    if (!empty($dateEnd)) {
        $sql .= " AND l.lesson_date <= ?";
        $params[] = $dateEnd;
    }

    $sql .= " GROUP BY l.id ORDER BY l.lesson_date DESC, c.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Eksik yoklama raporunu getir
function getMissingAttendanceReport($pdo, $classId = '', $teacherId = '')
{
    $sql = "
        SELECT 
            l.id,
            l.lesson_date,
            ws.lesson_name,
            ws.start_time,
            ws.end_time,
            c.name as class_name,
            u.full_name as teacher_name,
            DATEDIFF(CURDATE(), l.lesson_date) as days_overdue,
            COUNT(s.id) as total_students
        FROM lessons l
        JOIN weekly_schedule ws ON l.schedule_id = ws.id
        JOIN classes c ON ws.class_id = c.id
        JOIN users u ON ws.teacher_id = u.id
        LEFT JOIN students s ON c.id = s.class_id AND s.is_active = 1
        WHERE l.lesson_date < CURDATE() 
        AND l.attendance_marked = 0
    ";

    $params = [];

    if (!empty($classId)) {
        $sql .= " AND c.id = ?";
        $params[] = $classId;
    }

    if (!empty($teacherId)) {
        $sql .= " AND u.id = ?";
        $params[] = $teacherId;
    }

    $sql .= " GROUP BY l.id ORDER BY l.lesson_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Sınıf istatistikleri
function getClassStats($pdo, $classId = '', $dateStart = '', $dateEnd = '')
{
    $sql = "
        SELECT 
            c.name as class_name,
            COUNT(DISTINCT s.id) as total_students,
            COUNT(DISTINCT l.id) as total_lessons,
            SUM(CASE WHEN l.attendance_marked = 1 THEN 1 ELSE 0 END) as completed_lessons,
            COUNT(DISTINCT a.id) as total_attendances,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            ROUND(
                (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0) / 
                NULLIF(COUNT(DISTINCT a.id), 0), 2
            ) as attendance_rate
        FROM classes c
        LEFT JOIN students s ON c.id = s.class_id AND s.is_active = 1
        LEFT JOIN weekly_schedule ws ON c.id = ws.class_id
        LEFT JOIN lessons l ON ws.id = l.schedule_id
        LEFT JOIN attendance a ON l.id = a.lesson_id
        WHERE c.is_active = 1
    ";

    $params = [];

    if (!empty($classId)) {
        $sql .= " AND c.id = ?";
        $params[] = $classId;
    }

    if (!empty($dateStart)) {
        $sql .= " AND l.lesson_date >= ?";
        $params[] = $dateStart;
    }

    if (!empty($dateEnd)) {
        $sql .= " AND l.lesson_date <= ?";
        $params[] = $dateEnd;
    }

    $sql .= " GROUP BY c.id ORDER BY c.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Raporları getir
if ($reportType === 'attendance') {
    $attendanceData = getAttendanceReport($pdo, $classFilter, $teacherFilter, $dateStart, $dateEnd);
} elseif ($reportType === 'missing') {
    $missingData = getMissingAttendanceReport($pdo, $classFilter, $teacherFilter);
} else {
    $classStats = getClassStats($pdo, $classFilter, $dateStart, $dateEnd);
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - TÜGVA Kocaeli Icathane</title>
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
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-tugva:hover {
            background: var(--tugva-secondary);
            color: white;
            transform: translateY(-2px);
        }

        .form-control,
        .form-select {
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 0.5rem 0.75rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--tugva-primary);
            box-shadow: 0 0 0 0.2rem rgba(27, 155, 155, 0.25);
        }

        .report-tabs {
            background: var(--tugva-accent);
            border-radius: 15px;
            padding: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .report-tab {
            background: transparent;
            border: none;
            color: var(--tugva-primary);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            margin: 0 0.25rem;
            transition: all 0.3s ease;
        }

        .report-tab.active {
            background: var(--tugva-primary);
            color: white;
        }

        .present {
            color: #28a745;
            font-weight: bold;
        }

        .absent {
            color: #dc3545;
            font-weight: bold;
        }

        .missing {
            background-color: #ffe6e6;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, white, var(--tugva-accent));
            border-radius: 12px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--tugva-primary);
        }

        .export-buttons {
            background: var(--tugva-accent);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="superuser.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Raporlar
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
        <!-- Rapor Türü Seçimi -->
        <div class="report-tabs">
            <?php
            // Mevcut filtreleri koruyarak URL oluştur
            $currentFilters = array_filter($_GET);
            unset($currentFilters['report_type']); // report_type'ı çıkar, yenisini ekleyeceğiz
            
            $attendanceUrl = 'reports.php?report_type=attendance' . (!empty($currentFilters) ? '&' . http_build_query($currentFilters) : '');
            $missingUrl = 'reports.php?report_type=missing' . (!empty($currentFilters) ? '&' . http_build_query($currentFilters) : '');
            $statsUrl = 'reports.php?report_type=stats' . (!empty($currentFilters) ? '&' . http_build_query($currentFilters) : '');
            ?>
            <a href="<?php echo $attendanceUrl; ?>"
                class="report-tab <?php echo $reportType === 'attendance' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check me-2"></i>
                Yoklama Detayları
            </a>
            <a href="<?php echo $missingUrl; ?>"
                class="report-tab <?php echo $reportType === 'missing' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Eksik Yoklamalar
            </a>
            <a href="<?php echo $statsUrl; ?>"
                class="report-tab <?php echo $reportType === 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar me-2"></i>
                Sınıf İstatistikleri
            </a>
        </div>

        <!-- Filtreler -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    Filtreler
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($reportType); ?>">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Sınıf</label>
                            <select class="form-select" name="class_id" onchange="updateReportTabs()">
                                <option value="">Tüm Sınıflar</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $classFilter == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Öğretmen</label>
                            <select class="form-select" name="teacher_id" onchange="updateReportTabs()">
                                <option value="">Tüm Öğretmenler</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo $teacherFilter == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($reportType !== 'missing'): ?>
                            <div class="col-md-2">
                                <label class="form-label">Başlangıç</label>
                                <input type="date" class="form-control" name="date_start"
                                    value="<?php echo htmlspecialchars($dateStart); ?>" onchange="updateReportTabs()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Bitiş</label>
                                <input type="date" class="form-control" name="date_end"
                                    value="<?php echo htmlspecialchars($dateEnd); ?>" onchange="updateReportTabs()">
                            </div>
                        <?php endif; ?>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-tugva w-100">
                                <i class="fas fa-search me-1"></i>
                                Filtrele
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Export Butonları -->
        <div class="export-buttons">
            <h6 class="mb-2"><i class="fas fa-download me-2"></i>Dışa Aktar:</h6>
            <button class="btn btn-outline-success btn-sm me-2" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i> Excel
            </button>
            <button class="btn btn-outline-danger btn-sm me-2" onclick="exportToPDF()">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </button>
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Yazdır
            </button>
        </div>

        <?php if ($reportType === 'attendance'): ?>
            <!-- Yoklama Detayları -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Yoklama Detayları (<?php echo isset($attendanceData) ? count($attendanceData) : 0; ?> kayıt)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($attendanceData) && !empty($attendanceData)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Sınıf</th>
                                        <th>Ders</th>
                                        <th>Öğretmen</th>
                                        <th>Konu</th>
                                        <th>Gelenler</th>
                                        <th>Gelmeyenler</th>
                                        <th>Devam Oranı</th>
                                        <th>Detay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceData as $record): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y', strtotime($record['lesson_date'])); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['class_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($record['lesson_name']); ?>
                                                <br><small class="text-muted">
                                                    <?php echo date('H:i', strtotime($record['start_time'])); ?>-<?php echo date('H:i', strtotime($record['end_time'])); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['teacher_name']); ?></td>
                                            <td>
                                                <?php if ($record['topic']): ?>
                                                    <small><?php echo htmlspecialchars($record['topic']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Konu girilmemiş</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    ✅ <?php echo $record['present_count'] ?: 0; ?> kişi
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    ❌ <?php echo $record['absent_count'] ?: 0; ?> kişi
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($record['total_students'] > 0): ?>
                                                    <div class="d-flex align-items-center">
                                                        <strong style="color: var(--tugva-primary);">
                                                            %<?php echo $record['attendance_rate'] ?: 0; ?>
                                                        </strong>
                                                        <div class="progress ms-2" style="width: 60px; height: 8px;">
                                                            <div class="progress-bar"
                                                                style="width: <?php echo $record['attendance_rate'] ?: 0; ?>%; background-color: var(--tugva-primary);">
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="lesson-details.php?lesson_id=<?php echo $record['lesson_id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Öğrenciler
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-clipboard fa-3x mb-3"></i>
                            <h5>Yoklama verisi bulunamadı</h5>
                            <p>Seçilen kriterlere uygun yoklama kaydı bulunmuyor.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'missing'): ?>
            <!-- Eksik Yoklamalar -->
            <div class="card">
                <div class="card-header bg-danger">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Eksik Yoklamalar (<?php echo isset($missingData) ? count($missingData) : 0; ?> ders)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($missingData) && !empty($missingData)): ?>
                        <div class="alert alert-danger">
                            <strong>⚠️ Dikkat!</strong> Aşağıdaki derslerin yoklaması alınmamıştır.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Sınıf</th>
                                        <th>Ders</th>
                                        <th>Öğretmen</th>
                                        <th>Saat</th>
                                        <th>Gecikme</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($missingData as $missing): ?>
                                        <tr class="missing align-middle">
                                            <td><?php echo date('d.m.Y', strtotime($missing['lesson_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($missing['class_name']); ?></td>
                                            <td><?php echo htmlspecialchars($missing['lesson_name']); ?></td>
                                            <td><?php echo htmlspecialchars($missing['teacher_name']); ?></td>
                                            <td>
                                                <?php echo date('H:i', strtotime($missing['start_time'])); ?>-
                                                <?php echo date('H:i', strtotime($missing['end_time'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?php echo $missing['days_overdue']; ?> gün
                                                </span>
                                            </td>
                                            <td>
                                                <a href="admin-attendance.php?lesson_id=<?php echo $missing['id']; ?>"
                                                    class="btn btn-sm btn-danger shadow-sm">
                                                    <i class="fas fa-edit me-1"></i> Tamamla
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-success py-5">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h5>Tüm yoklamalar alınmış!</h5>
                            <p>Eksik yoklama bulunmuyor.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Sınıf İstatistikleri -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Sınıf İstatistikleri
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($classStats) && !empty($classStats)): ?>
                        <div class="row">
                            <?php foreach ($classStats as $stat): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="stat-card">
                                        <h6 class="mb-3"><?php echo htmlspecialchars($stat['class_name']); ?></h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="stat-number"><?php echo $stat['total_students']; ?></div>
                                                <small>Öğrenci</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="stat-number">
                                                    <?php echo $stat['completed_lessons']; ?>/<?php echo $stat['total_lessons']; ?>
                                                </div>
                                                <small>Tamamlanan Ders</small>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="mt-3">
                                            <h4 style="color: var(--tugva-primary);">
                                                %<?php echo $stat['attendance_rate'] ?: '0'; ?>
                                            </h4>
                                            <small>Devam Oranı</small>
                                        </div>
                                        <div class="progress mt-2" style="height: 8px;">
                                            <div class="progress-bar"
                                                style="width: <?php echo $stat['attendance_rate'] ?: 0; ?>%; background-color: var(--tugva-primary);">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-chart-bar fa-3x mb-3"></i>
                            <h5>İstatistik verisi bulunamadı</h5>
                            <p>Henüz yeterli veri bulunmuyor.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hızlı Navigasyon -->
        <div class="card">
            <div class="card-body">
                <h6>İşlemler:</h6>
                <a href="superuser.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-tachometer-alt"></i> Ana Panel
                </a>
                <a href="manage-classes.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-school"></i> Sınıf Yönetimi
                </a>
                <a href="manage-teachers.php" class="btn btn-outline-primary">
                    <i class="fas fa-chalkboard-teacher"></i> Öğretmen Yönetimi
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sekme linklerini filtre değerlerine göre güncelle
        function updateReportTabs() {
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);

            // report_type'ı çıkar
            params.delete('report_type');

            // Mevcut filtreleri al
            const filterString = params.toString();
            const baseUrl = 'reports.php?';

            // Sekme linklerini güncelle
            const attendanceTab = document.querySelector('a[href*="report_type=attendance"]');
            const missingTab = document.querySelector('a[href*="report_type=missing"]');
            const statsTab = document.querySelector('a[href*="report_type=stats"]');

            if (attendanceTab) {
                attendanceTab.href = baseUrl + 'report_type=attendance' + (filterString ? '&' + filterString : '');
            }
            if (missingTab) {
                missingTab.href = baseUrl + 'report_type=missing' + (filterString ? '&' + filterString : '');
            }
            if (statsTab) {
                statsTab.href = baseUrl + 'report_type=stats' + (filterString ? '&' + filterString : '');
            }
        }

        // Sayfa yüklendiğinde sekme linklerini güncelle
        document.addEventListener('DOMContentLoaded', function () {
            updateReportTabs();
        });

        function exportToExcel() {
            // Excel export - basit CSV formatı
            let table = document.querySelector('#attendanceTable');
            if (!table) {
                alert('Export edilecek tablo bulunamadı.');
                return;
            }

            let csv = [];
            let rows = table.querySelectorAll('tr');

            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    row.push(cols[j].innerText);
                }
                csv.push(row.join(','));
            }

            let csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'tugva_yoklama_raporu.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportToPDF() {
            alert('PDF export özelliği yakında eklenecek.');
        }
    </script>
</body>

</html>