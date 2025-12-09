<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/MaterialManager.php';

$auth = new Auth($pdo);
$auth->requireLogin();

$materialManager = new MaterialManager($pdo);
$materialId = $_GET['id'] ?? null;

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
    $error = 'Dosya bulunamadı.';
}

// PDF mı kontrol et
$isPDF = strtolower($material['file_type']) === 'pdf';
$isImage = in_array(strtolower($material['file_type']), ['jpg', 'jpeg', 'png', 'gif']);
$isViewable = $isPDF || $isImage;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($material['title']); ?> - TÜGVA Kocaeli Icathane</title>
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
            margin: 0;
            padding: 0;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            box-shadow: 0 2px 10px rgba(27, 155, 155, 0.3);
        }
        
        .material-info {
            background: var(--tugva-accent);
            border-radius: 0 0 15px 15px;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
        }
        
        .viewer-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(27, 155, 155, 0.1);
            overflow: hidden;
            margin: 1rem;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 80vh;
            border: none;
        }
        
        .image-viewer {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        
        .download-prompt {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 15px;
            margin: 2rem;
            box-shadow: 0 4px 20px rgba(27, 155, 155, 0.1);
        }
        
        .file-icon-large {
            font-size: 4rem;
            margin-bottom: 1rem;
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
        
        .toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .fullscreen-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 9999;
            display: none;
        }
        
        .fullscreen-container.active {
            display: block;
        }
        
        .fullscreen-toolbar {
            background: #333;
            color: white;
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .fullscreen-content {
            height: calc(100% - 50px);
            overflow: auto;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="javascript:history.back()">
                <i class="fas fa-arrow-left me-2"></i>
                Geri Dön
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-<?php echo $_SESSION['role'] === 'superuser' ? 'user-shield' : 'user'; ?> me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <!-- Materyal Bilgileri -->
    <div class="material-info">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1">
                        <i class="<?php echo $materialManager->getFileIcon($material['file_type']); ?> me-2"></i>
                        <?php echo htmlspecialchars($material['title']); ?>
                    </h4>
                    <div class="text-muted">
                        <small>
                            <i class="fas fa-file me-1"></i>
                            <?php echo htmlspecialchars($material['file_name']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-hdd me-1"></i>
                            <?php echo $materialManager->formatFileSize($material['file_size']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($material['uploader_name']); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('d.m.Y H:i', strtotime($material['created_at'])); ?>
                        </small>
                    </div>
                    <?php if ($material['description']): ?>
                        <p class="mb-0 mt-2"><?php echo htmlspecialchars($material['description']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="download-material.php?id=<?php echo $material['id']; ?>" class="btn btn-tugva">
                            <i class="fas fa-download me-1"></i> İndir
                        </a>
                        <?php if ($isViewable): ?>
                            <button class="btn btn-outline-primary" onclick="toggleFullscreen()">
                                <i class="fas fa-expand me-1"></i> Tam Ekran
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <!-- Hata Durumu -->
        <div class="download-prompt">
            <i class="fas fa-exclamation-triangle file-icon-large text-danger"></i>
            <h3>Dosya Bulunamadı</h3>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($error); ?></p>
            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Geri Dön
            </a>
        </div>
    <?php elseif ($isPDF): ?>
        <!-- PDF Görüntüleyici -->
        <div class="viewer-container">
            <div class="toolbar">
                <div>
                    <small class="text-muted">
                        <i class="fas fa-file-pdf text-danger me-1"></i>
                        PDF Görüntüleyici
                    </small>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-secondary me-2" onclick="printPDF()">
                        <i class="fas fa-print"></i> Yazdır
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleFullscreen()">
                        <i class="fas fa-expand"></i> Tam Ekran
                    </button>
                </div>
            </div>
            <iframe id="pdfViewer" class="pdf-viewer" src="../uploads/materials/<?php echo htmlspecialchars($material['file_path']); ?>#toolbar=1&navpanes=1&scrollbar=1"></iframe>
        </div>
    <?php elseif ($isImage): ?>
        <!-- Resim Görüntüleyici -->
        <div class="viewer-container">
            <div class="toolbar">
                <div>
                    <small class="text-muted">
                        <i class="fas fa-file-image text-info me-1"></i>
                        Resim Görüntüleyici
                    </small>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleFullscreen()">
                        <i class="fas fa-expand"></i> Tam Ekran
                    </button>
                </div>
            </div>
            <div class="p-3">
                <img id="imageViewer" class="image-viewer" src="../uploads/materials/<?php echo htmlspecialchars($material['file_path']); ?>" alt="<?php echo htmlspecialchars($material['title']); ?>">
            </div>
        </div>
    <?php else: ?>
        <!-- İndirme İsteği -->
        <div class="download-prompt">
            <i class="<?php echo $materialManager->getFileIcon($material['file_type']); ?> file-icon-large"></i>
            <h3>Dosyayı Görüntüle</h3>
            <p class="text-muted mb-4">
                Bu dosya türü (<?php echo strtoupper($material['file_type']); ?>) tarayıcıda görüntülenemez. 
                İndirerek açabilirsiniz.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="download-material.php?id=<?php echo $material['id']; ?>" class="btn btn-tugva">
                    <i class="fas fa-download me-2"></i>
                    Dosyayı İndir
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>
                    Geri Dön
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tam Ekran Görüntüleyici -->
    <div id="fullscreenContainer" class="fullscreen-container">
        <div class="fullscreen-toolbar">
            <div>
                <strong><?php echo htmlspecialchars($material['title']); ?></strong>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-light me-2" onclick="printContent()">
                    <i class="fas fa-print"></i> Yazdır
                </button>
                <button class="btn btn-sm btn-outline-light" onclick="toggleFullscreen()">
                    <i class="fas fa-compress"></i> Çıkış
                </button>
            </div>
        </div>
        <div class="fullscreen-content">
            <?php if ($isPDF): ?>
                <iframe id="fullscreenPDF" style="width: 100%; height: 100%; border: none;" src="../uploads/materials/<?php echo htmlspecialchars($material['file_path']); ?>#toolbar=1&navpanes=1&scrollbar=1"></iframe>
            <?php elseif ($isImage): ?>
                <div style="text-align: center; padding: 20px;">
                    <img id="fullscreenImage" style="max-width: 100%; max-height: 90vh;" src="../uploads/materials/<?php echo htmlspecialchars($material['file_path']); ?>" alt="<?php echo htmlspecialchars($material['title']); ?>">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tam ekran toggle
        function toggleFullscreen() {
            const container = document.getElementById('fullscreenContainer');
            container.classList.toggle('active');
        }

        // PDF yazdırma
        function printPDF() {
            const iframe = document.getElementById('pdfViewer');
            iframe.contentWindow.print();
        }

        // İçeriği yazdır
        function printContent() {
            <?php if ($isPDF): ?>
                const iframe = document.getElementById('fullscreenPDF');
                iframe.contentWindow.print();
            <?php elseif ($isImage): ?>
                const img = document.getElementById('fullscreenImage');
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title><?php echo htmlspecialchars($material['title']); ?></title>
                            <style>
                                body { margin: 0; text-align: center; }
                                img { max-width: 100%; height: auto; }
                            </style>
                        </head>
                        <body>
                            <img src="${img.src}" alt="<?php echo htmlspecialchars($material['title']); ?>">
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            <?php endif; ?>
        }

        // ESC tuşu ile tam ekrandan çıkış
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const container = document.getElementById('fullscreenContainer');
                if (container.classList.contains('active')) {
                    toggleFullscreen();
                }
            }
        });

        // Sayfa yüklendiğinde indirme sayısını artır
        window.addEventListener('load', function() {
            // AJAX ile indirme sayısını artır (görüntüleme olarak sayılır)
            fetch(`download-material.php?id=<?php echo $material['id']; ?>&view_only=1`);
        });
    </script>
</body>
</html>