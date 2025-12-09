<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/MaterialManager.php';

$auth = new Auth($pdo);
$auth->requireLogin();

$materialManager = new MaterialManager($pdo);
$materialId = $_GET['id'] ?? null;
$viewOnly = isset($_GET['view_only']); // Sadece görüntüleme sayısını artırmak için

if (!$materialId) {
    header('Location: manage-materials.php');
    exit;
}

// Materyal bilgilerini al
$material = $materialManager->getMaterial($materialId);
if (!$material) {
    header('Location: manage-materials.php');
    exit;
}

// Yetki kontrolü (öğretmen ise sadece kendi sınıflarına veya herkese açık materyallere erişebilir)
if ($_SESSION['role'] === 'teacher') {
    if (!$material['is_public']) {
        // Öğretmenin bu sınıfa erişimi var mı kontrol et
        $stmt = $pdo->prepare("
            SELECT 1 FROM teacher_classes tc
            WHERE tc.teacher_id = ? AND tc.class_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $material['class_id']]);
        if (!$stmt->fetch()) {
            header('Location: teacher.php');
            exit;
        }
    }
}

// Dosya yolu
$filePath = '../uploads/materials/' . $material['file_path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    die('Dosya bulunamadı.');
}

// İndirme sayısını artır
$materialManager->incrementDownloadCount($materialId);

// Sadece görüntüleme sayısını artırmak için çağrıldıysa burada dur
if ($viewOnly) {
    http_response_code(200);
    echo json_encode(['status' => 'view_counted']);
    exit;
}

// Dosya indirme işlemi
$fileName = $material['file_name'];
$fileSize = filesize($filePath);

// MIME type belirleme
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed'
];

$mimeType = $mimeTypes[strtolower($material['file_type'])] ?? 'application/octet-stream';

// HTTP başlıkları
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Dosyayı temizle ve gönder
if (ob_get_level()) {
    ob_end_clean();
}

// Büyük dosyalar için chunk'lar halinde gönder
$chunkSize = 8192; // 8KB chunks
$handle = fopen($filePath, 'rb');

if ($handle === false) {
    http_response_code(500);
    die('Dosya okunamadı.');
}

while (!feof($handle)) {
    echo fread($handle, $chunkSize);
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

fclose($handle);
exit;
?>