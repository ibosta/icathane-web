<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'classes/LessonManager.php';

$auth = new Auth($pdo);
$auth->requireLogin();

// Otomatik ders oluşturma (günde bir kez çalışsın)
$lessonManager = new LessonManager($pdo);
$lastRun = $_SESSION['last_auto_create'] ?? 0;
if (time() - $lastRun > 86400) { // 24 saat
    $lessonManager->createLessonsFromSchedule();
    $_SESSION['last_auto_create'] = time();
}

// Role göre yönlendirme
if ($auth->isSuperUser()) {
    header('Location: dashboard/superuser.php');
} else {
    header('Location: dashboard/teacher.php');
}
exit;
?>