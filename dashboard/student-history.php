<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$studentId = $_GET['student_id'] ?? null;

if (!$studentId) {
    echo '<div class="alert alert-danger">Öğrenci ID\'si bulunamadı.</div>';
    exit;
}

// Öğrenci bilgilerini al
$stmt = $pdo->prepare("
    SELECT s.full_name, c.name as class_name, c.academic_year
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    echo '<div class="alert alert-danger">Öğrenci bulunamadı.</div>';
    exit;
}

// Öğrencinin tüm yoklama geçmişini al
$stmt = $pdo->prepare("
    SELECT 
        l.lesson_date,
        ws.lesson_name,
        ws.start_time,
        ws.end_time,
        u.full_name as teacher_name,
        l.topic,
        COALESCE(a.status, 'absent') as status,
        a.recorded_at,
        CASE 
            WHEN l.attendance_marked = 0 THEN 'not_marked'
            ELSE COALESCE(a.status, 'absent')
        END as display_status
    FROM lessons l
    JOIN weekly_schedule ws ON l.schedule_id = ws.id
    JOIN classes c ON ws.class_id = c.id
    JOIN users u ON ws.teacher_id = u.id
    LEFT JOIN attendance a ON l.id = a.lesson_id AND a.student_id = ?
    WHERE c.id = (SELECT class_id FROM students WHERE id = ?)
    ORDER BY l.lesson_date DESC
    LIMIT 20
");
$stmt->execute([$studentId, $studentId]);
$history = $stmt->fetchAll();

// İstatistikleri hesapla
$totalLessons = count(array_filter($history, function($h) { return $h['display_status'] !== 'not_marked'; }));
$presentCount = count(array_filter($history, function($h) { return $h['display_status'] === 'present'; }));
$absentCount = count(array_filter($history, function($h) { return $h['display_status'] === 'absent'; }));
$attendanceRate = $totalLessons > 0 ? round(($presentCount / $totalLessons) * 100, 1) : 0;
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-info">
            <strong>Sınıf:</strong> <?php echo htmlspecialchars($student['class_name']); ?> 
            (<?php echo htmlspecialchars($student['academic_year']); ?>)
        </div>
    </div>
</div>

<!-- İstatistikler -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 10px;">
            <h4 class="mb-1" style="color: #1B9B9B;"><?php echo $totalLessons; ?></h4>
            <small>Toplam Ders</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 10px;">
            <h4 class="mb-1" style="color: #28a745;"><?php echo $presentCount; ?></h4>
            <small>Geldi</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 10px;">
            <h4 class="mb-1" style="color: #dc3545;"><?php echo $absentCount; ?></h4>
            <small>Gelmedi</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 10px;">
            <h4 class="mb-1" style="color: #1B9B9B;">%<?php echo $attendanceRate; ?></h4>
            <small>Devam Oranı</small>
        </div>
    </div>
</div>

<!-- Devam Progress Bar -->
<div class="mb-4">
    <div class="d-flex justify-content-between mb-2">
        <span>Genel Devam Durumu</span>
        <span><strong>%<?php echo $attendanceRate; ?></strong></span>
    </div>
    <div class="progress" style="height: 12px;">
        <div class="progress-bar" 
             style="width: <?php echo $attendanceRate; ?>%; background-color: #1B9B9B;">
        </div>
    </div>
</div>

<!-- Geçmiş Dersler -->
<h6 class="mb-3">Son <?php echo count($history); ?> Ders</h6>

<?php if (empty($history)): ?>
    <div class="text-center text-muted py-4">
        <i class="fas fa-calendar-times fa-2x mb-2"></i>
        <p>Henüz ders kaydı bulunmuyor.</p>
    </div>
<?php else: ?>
    <div class="timeline">
        <?php foreach ($history as $record): ?>
            <div class="timeline-item mb-3">
                <div class="d-flex align-items-start">
                    <div class="timeline-icon me-3">
                        <?php if ($record['display_status'] === 'present'): ?>
                            <i class="fas fa-check-circle text-success fa-lg"></i>
                        <?php elseif ($record['display_status'] === 'absent'): ?>
                            <i class="fas fa-times-circle text-danger fa-lg"></i>
                        <?php else: ?>
                            <i class="fas fa-question-circle text-warning fa-lg"></i>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-content flex-grow-1 p-3" style="background: #f8f9fa; border-radius: 10px; border-left: 4px solid <?php echo $record['display_status'] === 'present' ? '#28a745' : ($record['display_status'] === 'absent' ? '#dc3545' : '#ffc107'); ?>;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1">
                                    <?php echo htmlspecialchars($record['lesson_name']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo date('d F Y, l', strtotime($record['lesson_date'])); ?> - 
                                    <?php echo date('H:i', strtotime($record['start_time'])); ?>-<?php echo date('H:i', strtotime($record['end_time'])); ?>
                                    <br>
                                    Öğretmen: <?php echo htmlspecialchars($record['teacher_name']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($record['display_status'] === 'present'): ?>
                                    <span class="badge bg-success">✅ Geldi</span>
                                <?php elseif ($record['display_status'] === 'absent'): ?>
                                    <span class="badge bg-danger">❌ Gelmedi</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">⏳ Yoklama Alınmamış</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($record['topic']): ?>
                            <div class="mt-2">
                                <small>
                                    <strong>Konu:</strong> 
                                    <span class="badge bg-info"><?php echo htmlspecialchars($record['topic']); ?></span>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($record['recorded_at']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Yoklama kaydedilme: <?php echo date('d.m.Y H:i', strtotime($record['recorded_at'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Ek Bilgi -->
<div class="mt-4 p-3" style="background: #e8f5e5; border-radius: 10px; border-left: 4px solid #28a745;">
    <h6><i class="fas fa-info-circle me-2"></i>Devam Durumu Değerlendirmesi</h6>
    <small>
        <?php if ($attendanceRate >= 90): ?>
            <span class="text-success"><strong>Mükemmel!</strong> Çok düzenli devam ediyor.</span>
        <?php elseif ($attendanceRate >= 75): ?>
            <span class="text-warning"><strong>İyi.</strong> Devam durumu kabul edilebilir seviyede.</span>
        <?php elseif ($attendanceRate >= 60): ?>
            <span class="text-danger"><strong>Dikkat!</strong> Devam durumu yetersiz.</span>
        <?php else: ?>
            <span class="text-danger"><strong>Kritik!</strong> Devam durumu çok düşük.</span>
        <?php endif; ?>
        <br>
        <em>Son <?php echo $totalLessons; ?> dersten <?php echo $presentCount; ?> tanesine katıldı.</em>
    </small>
</div>

<style>
.timeline-item {
    position: relative;
}

.timeline-icon {
    position: relative;
    z-index: 1;
}

.timeline-content {
    position: relative;
    margin-left: 0;
}

.timeline-item:not(:last-child) .timeline-icon::after {
    content: '';
    position: absolute;
    top: 30px;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: 60px;
    background-color: #dee2e6;
    z-index: -1;
}
</style>