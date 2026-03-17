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

// Geçmiş dersleri getir
$pastLessons = $lessonManager->getTeacherPastLessons($teacherId);

// Export isteği geldiyse
if (isset($_GET['export']) && $_GET['export'] == 'xls') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=gecmis_dersler_ve_yoklamalar_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<thead><tr>';
    echo '<th>Tarih</th><th>Saat</th><th>Sınıf</th><th>Ders</th><th>Konu / İçerik</th><th>Öğrenci Sayısı</th><th>Gelen Sayısı</th><th>Gelen Öğrenciler</th><th>Gelmeyen Sayısı</th><th>Gelmeyen Öğrenciler</th>';
    echo '</tr></thead><tbody>';
    foreach ($pastLessons as $lesson) {
        $students = $lessonManager->getLessonStudents($lesson['id']);
        $presentStudents = [];
        $absentStudents = [];
        foreach ($students as $student) {
            if ($student['status'] === 'present') {
                $presentStudents[] = $student['full_name'];
            } else {
                $absentStudents[] = $student['full_name'];
            }
        }
        
        echo '<tr>';
        echo '<td>' . date('d.m.Y', strtotime($lesson['lesson_date'])) . '</td>';
        echo '<td>' . date('H:i', strtotime($lesson['start_time'])) . ' - ' . date('H:i', strtotime($lesson['end_time'])) . '</td>';
        echo '<td>' . htmlspecialchars($lesson['class_name']) . '</td>';
        echo '<td>' . htmlspecialchars($lesson['lesson_name']) . '</td>';
        echo '<td>' . htmlspecialchars($lesson['topic'] ?? 'Konu Girilmemiş') . '</td>';
        echo '<td>' . $lesson['total_students'] . '</td>';
        echo '<td>' . $lesson['present_count'] . '</td>';
        echo '<td>' . htmlspecialchars(implode(', ', $presentStudents)) . '</td>';
        echo '<td>' . $lesson['absent_count'] . '</td>';
        echo '<td>' . htmlspecialchars(implode(', ', $absentStudents)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geçmiş Dersler ve Yoklamalar - TÜGVA Kocaeli Icathane</title>
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="teacher.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Geçmiş Derslerim
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Geçmiş Yoklamalar ve Ders İçerikleri
                </h5>
                <a href="?export=xls" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-file-excel me-1"></i> Excel İndir
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($pastLessons)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-folder-open fa-4x mb-3 text-muted"></i>
                        <h4>Henüz geçmiş ders kaydı bulunmuyor.</h4>
                        <p>Yoklamasını aldığınız dersler burada listelenecektir.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Sınıf / Ders</th>
                                    <th>Konu (İçerik)</th>
                                    <th>Katılım</th>
                                    <th>Detay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pastLessons as $lesson): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-calendar-alt text-muted me-1"></i> <?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?><br>
                                            <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo date('H:i', strtotime($lesson['start_time'])); ?> - <?php echo date('H:i', strtotime($lesson['end_time'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($lesson['class_name']); ?></strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars($lesson['lesson_name']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($lesson['topic']): ?>
                                                <span class="badge bg-info text-dark text-wrap" style="max-width: 200px; line-height: 1.5;">
                                                    <?php echo htmlspecialchars($lesson['topic']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">Girilmemiş</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <small class="text-success"><i class="fas fa-check-circle me-1"></i> <?php echo $lesson['present_count']; ?> Geldi</small>
                                                <small class="text-danger"><i class="fas fa-times-circle me-1"></i> <?php echo $lesson['absent_count']; ?> Gelmedi</small>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="attendance.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> İncele
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h6>Hızlı İşlemler:</h6>
                <a href="teacher.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-tachometer-alt"></i> Ana Panel
                </a>
                <a href="teacher-materials.php" class="btn btn-outline-info">
                    <i class="fas fa-folder-open"></i> Ders Materyalleri
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
