<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$lessonId = $_GET['lesson_id'] ?? null;

if (!$lessonId) {
    header('Location: reports.php');
    exit;
}

// Ders bilgilerini al
$stmt = $pdo->prepare("
    SELECT 
        l.id,
        l.lesson_date,
        l.topic,
        l.attendance_marked,
        ws.lesson_name,
        ws.start_time,
        ws.end_time,
        c.name as class_name,
        c.academic_year,
        u.full_name as teacher_name
    FROM lessons l
    JOIN weekly_schedule ws ON l.schedule_id = ws.id
    JOIN classes c ON ws.class_id = c.id
    JOIN users u ON ws.teacher_id = u.id
    WHERE l.id = ?
");
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: reports.php');
    exit;
}

// Öğrenci yoklama detaylarını al
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.full_name,
        COALESCE(a.status, 'absent') as attendance_status,
        a.recorded_at
    FROM students s
    JOIN weekly_schedule ws ON ws.class_id = s.class_id
    JOIN lessons l ON l.schedule_id = ws.id
    LEFT JOIN attendance a ON l.id = a.lesson_id AND s.id = a.student_id
    WHERE l.id = ? AND s.is_active = 1
    ORDER BY s.full_name
");
$stmt->execute([$lessonId]);
$students = $stmt->fetchAll();

// İstatistikleri hesapla
$totalStudents = count($students);
$presentCount = count(array_filter($students, function($s) { return $s['attendance_status'] === 'present'; }));
$absentCount = $totalStudents - $presentCount;
$attendanceRate = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 1) : 0;

// Öğrencinin genel devam durumunu al
function getStudentAttendanceHistory($pdo, $studentId, $classId) {
    $stmt = $pdo->prepare("
        SELECT 
            l.lesson_date,
            ws.lesson_name,
            COALESCE(a.status, 'absent') as status,
            l.topic
        FROM lessons l
        JOIN weekly_schedule ws ON l.schedule_id = ws.id
        LEFT JOIN attendance a ON l.id = a.lesson_id AND a.student_id = ?
        WHERE ws.class_id = (
            SELECT class_id FROM students WHERE id = ?
        ) AND l.attendance_marked = 1
        ORDER BY l.lesson_date DESC
        LIMIT 10
    ");
    $stmt->execute([$studentId, $studentId]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ders Detayları - TÜGVA Kocaeli Icathane</title>
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
        
        .lesson-info {
            background: var(--tugva-accent);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .student-item {
            background: white;
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .student-item:hover {
            border-color: var(--tugva-primary);
            background-color: var(--tugva-light);
        }
        
        .student-item.present {
            border-left: 5px solid #28a745;
        }
        
        .student-item.absent {
            border-left: 5px solid #dc3545;
        }
        
        .status-present {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-absent {
            color: #dc3545;
            font-weight: bold;
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
        
        .modal-content {
            border-radius: 15px;
        }
        
        .history-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="reports.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Ders Detayları
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
        <!-- Ders Bilgileri -->
        <div class="lesson-info">
            <div class="row">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-chalkboard-teacher me-2" style="color: var(--tugva-primary);"></i>
                        <?php echo htmlspecialchars($lesson['lesson_name']); ?>
                    </h2>
                    <p class="mb-1">
                        <strong>Sınıf:</strong> <?php echo htmlspecialchars($lesson['class_name']); ?> 
                        (<?php echo htmlspecialchars($lesson['academic_year']); ?>)
                    </p>
                    <p class="mb-1">
                        <strong>Öğretmen:</strong> <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Tarih:</strong> <?php echo date('d F Y, l', strtotime($lesson['lesson_date'])); ?>
                    </p>
                    <p class="mb-2">
                        <strong>Saat:</strong> 
                        <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - 
                        <?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                    </p>
                    <?php if ($lesson['topic']): ?>
                        <p class="mb-0">
                            <strong>Konu:</strong> 
                            <span class="badge bg-info"><?php echo htmlspecialchars($lesson['topic']); ?></span>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($lesson['attendance_marked']): ?>
                        <span class="badge bg-success p-2 mb-2">
                            <i class="fas fa-check me-1"></i> Yoklama Alındı
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark p-2 mb-2">
                            <i class="fas fa-exclamation-triangle me-1"></i> Yoklama Eksik
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-users fa-2x mb-2" style="color: var(--tugva-primary);"></i>
                    <div class="stat-number"><?php echo $totalStudents; ?></div>
                    <small>Toplam Öğrenci</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-check-circle fa-2x mb-2" style="color: #28a745;"></i>
                    <div class="stat-number" style="color: #28a745;"><?php echo $presentCount; ?></div>
                    <small>Gelen</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-times-circle fa-2x mb-2" style="color: #dc3545;"></i>
                    <div class="stat-number" style="color: #dc3545;"><?php echo $absentCount; ?></div>
                    <small>Gelmeyen</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-chart-pie fa-2x mb-2" style="color: var(--tugva-primary);"></i>
                    <div class="stat-number">%<?php echo $attendanceRate; ?></div>
                    <small>Devam Oranı</small>
                </div>
            </div>
        </div>

        <!-- Öğrenci Listesi -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Öğrenci Yoklama Detayları
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success mb-3">
                            <i class="fas fa-check me-2"></i>
                            Gelenler (<?php echo $presentCount; ?> kişi)
                        </h6>
                        <?php foreach ($students as $student): ?>
                            <?php if ($student['attendance_status'] === 'present'): ?>
                                <div class="student-item present" onclick="showStudentHistory(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                            </h6>
                                        </div>
                                        <div>
                                            <span class="status-present">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Geldi
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-danger mb-3">
                            <i class="fas fa-times me-2"></i>
                            Gelmeyenler (<?php echo $absentCount; ?> kişi)
                        </h6>
                        <?php foreach ($students as $student): ?>
                            <?php if ($student['attendance_status'] === 'absent'): ?>
                                <div class="student-item absent" onclick="showStudentHistory(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                            </h6>
                                        </div>
                                        <div>
                                            <span class="status-absent">
                                                <i class="fas fa-times-circle me-1"></i>
                                                Gelmedi
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (empty($students)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-users-slash fa-3x mb-3"></i>
                        <h5>Öğrenci bulunamadı</h5>
                        <p>Bu sınıfta kayıtlı öğrenci bulunmuyor.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hızlı Navigasyon -->
        <div class="card">
            <div class="card-body">
                <h6>İşlemler:</h6>
                <a href="reports.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left"></i> Raporlara Dön
                </a>
                <a href="reports.php?report_type=attendance" class="btn btn-outline-primary me-2">
                    <i class="fas fa-clipboard-list"></i> Tüm Yoklamalar
                </a>
                <a href="superuser.php" class="btn btn-outline-secondary">
                    <i class="fas fa-tachometer-alt"></i> Ana Panel
                </a>
            </div>
        </div>
    </div>

    <!-- Öğrenci Geçmişi Modal -->
    <div class="modal fade" id="studentHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>
                        <span id="studentName"></span> - Devam Geçmişi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentHistoryContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Yükleniyor...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showStudentHistory(studentId, studentName) {
            document.getElementById('studentName').textContent = studentName;
            
            // Modal'ı aç
            const modal = new bootstrap.Modal(document.getElementById('studentHistoryModal'));
            modal.show();
            
            // AJAX ile geçmişi getir
            fetch(`student-history.php?student_id=${studentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('studentHistoryContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('studentHistoryContent').innerHTML = 
                        '<div class="alert alert-danger">Veri yüklenirken hata oluştu.</div>';
                });
        }
    </script>
</body>
</html>