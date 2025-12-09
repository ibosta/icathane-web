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
$lessonId = $_GET['lesson_id'] ?? null;

if (!$lessonId) {
    header('Location: teacher.php');
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
        ws.teacher_id,
        c.name as class_name
    FROM lessons l
    JOIN weekly_schedule ws ON l.schedule_id = ws.id
    JOIN classes c ON ws.class_id = c.id
    WHERE l.id = ?
");
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson || $lesson['teacher_id'] != $_SESSION['user_id']) {
    header('Location: teacher.php');
    exit;
}

// Öğrenci listesi
$students = $lessonManager->getLessonStudents($lessonId);

// Form gönderildiğinde
$message = '';
if ($_POST) {
    $topic = trim($_POST['topic']);
    $attendanceData = $_POST['attendance'] ?? [];
    
    if (empty($topic)) {
        $message = ['type' => 'danger', 'text' => 'Ders konusu zorunludur.'];
    } else {
        $result = $lessonManager->saveAttendance($lessonId, $topic, $attendanceData);
        if ($result['success']) {
            $message = ['type' => 'success', 'text' => $result['message']];
            // Sayfayı yenile
            header('Refresh: 2; url=teacher.php');
        } else {
            $message = ['type' => 'danger', 'text' => $result['message']];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yoklama Al - TÜGVA Kocaeli Icathane</title>
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
        
        .student-row {
            background: white;
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            margin-bottom: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .student-row:hover {
            border-color: var(--tugva-primary);
            box-shadow: 0 4px 15px rgba(27, 155, 155, 0.1);
        }
        
        .form-check-input:checked {
            background-color: var(--tugva-primary);
            border-color: var(--tugva-primary);
        }
        
        .form-control {
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 0.75rem;
        }
        
        .form-control:focus {
            border-color: var(--tugva-primary);
            box-shadow: 0 0 0 0.2rem rgba(27, 155, 155, 0.25);
        }
        
        .lesson-info {
            background: var(--tugva-accent);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-select-all {
            background-color: var(--tugva-accent);
            color: var(--tugva-primary);
            border: 2px solid var(--tugva-primary);
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }
        
        .btn-select-all:hover {
            background-color: var(--tugva-primary);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="teacher.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane
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
        <!-- Ders Bilgileri -->
        <div class="lesson-info">
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-2">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        <?php echo htmlspecialchars($lesson['class_name']); ?> - <?php echo htmlspecialchars($lesson['lesson_name']); ?>
                    </h4>
                    <p class="mb-0">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo date('d F Y, l', strtotime($lesson['lesson_date'])); ?>
                        <span class="ms-3">
                            <i class="fas fa-clock me-2"></i>
                            <?php echo date('H:i', strtotime($lesson['start_time'])); ?>-<?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($lesson['attendance_marked']): ?>
                        <span class="badge bg-success p-2">
                            <i class="fas fa-check me-1"></i> Yoklama Alındı
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark p-2">
                            <i class="fas fa-exclamation-triangle me-1"></i> Yoklama Bekleniyor
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mesaj -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Yoklama Formu -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Yoklama ve Ders Konusu
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Ders Konusu -->
                    <div class="mb-4">
                        <label for="topic" class="form-label fw-bold">Ders Konusu *</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="topic" 
                            name="topic" 
                            value="<?php echo htmlspecialchars($lesson['topic'] ?? ''); ?>"
                            placeholder="Bugün işlenen konuyu yazın..."
                            required
                        >
                    </div>

                    <!-- Hızlı Seçim Butonları -->
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-select-all" onclick="selectAll('present')">
                            <i class="fas fa-check-double"></i> Hepsini Geldi Yap
                        </button>
                        <button type="button" class="btn btn-select-all" onclick="selectAll('absent')">
                            <i class="fas fa-times"></i> Hepsini Gelmedi Yap
                        </button>
                    </div>

                    <!-- Öğrenci Listesi -->
                    <div class="mb-4">
                        <h6 class="mb-3">Öğrenci Yoklaması (<?php echo count($students); ?> öğrenci)</h6>
                        
                        <?php foreach ($students as $student): ?>
                        <div class="student-row">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="radio" 
                                                name="attendance[<?php echo $student['id']; ?>]" 
                                                id="present_<?php echo $student['id']; ?>" 
                                                value="present"
                                                <?php echo ($student['status'] === 'present') ? 'checked' : ''; ?>
                                            >
                                            <label class="form-check-label text-success fw-bold" for="present_<?php echo $student['id']; ?>">
                                                <i class="fas fa-check me-1"></i> Geldi
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="radio" 
                                                name="attendance[<?php echo $student['id']; ?>]" 
                                                id="absent_<?php echo $student['id']; ?>" 
                                                value="absent"
                                                <?php echo ($student['status'] === 'absent') ? 'checked' : ''; ?>
                                            >
                                            <label class="form-check-label text-danger fw-bold" for="absent_<?php echo $student['id']; ?>">
                                                <i class="fas fa-times me-1"></i> Gelmedi
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Kaydet Butonu -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-tugva btn-lg">
                            <i class="fas fa-save me-2"></i>
                            Yoklamayı Kaydet
                        </button>
                        <a href="teacher.php" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fas fa-times me-2"></i>
                            İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAll(status) {
            const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
            radios.forEach(radio => {
                radio.checked = true;
            });
        }

        // Form gönderilmeden önce uyarı
        document.querySelector('form').addEventListener('submit', function(e) {
            const topic = document.querySelector('#topic').value.trim();
            if (topic.length < 5) {
                e.preventDefault();
                alert('Ders konusu en az 5 karakter olmalıdır.');
                return false;
            }
            
            // Hiç öğrenci seçilmemiş mi kontrol et
            const selectedStudents = document.querySelectorAll('input[name^="attendance"]:checked').length;
            if (selectedStudents === 0) {
                e.preventDefault();
                alert('En az bir öğrenci için yoklama seçimi yapmalısınız.');
                return false;
            }
            
            return confirm('Yoklamayı kaydetmek istediğinizden emin misiniz?');
        });
    </script>
</body>
</html>