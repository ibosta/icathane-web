<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/MaterialManager.php';

$auth = new Auth($pdo);
$auth->requireLogin();

if (!$auth->isTeacher()) {
    header('Location: ../index.php');
    exit;
}

$materialManager = new MaterialManager($pdo);

// Herkese açık materyalleri getir
$stmt = $pdo->prepare("
    SELECT 
        lm.*,
        u.full_name as uploader_name
    FROM lesson_materials lm
    JOIN users u ON lm.uploaded_by = u.id
    WHERE lm.is_public = 1
    ORDER BY lm.created_at DESC
");
$stmt->execute();
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ders Materyalleri - TÜGVA Kocaeli Icathane</title>
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
        
        .material-item {
            border: 2px solid var(--tugva-accent);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .material-item:hover {
            border-color: var(--tugva-primary);
            box-shadow: 0 8px 25px rgba(27, 155, 155, 0.15);
            transform: translateY(-3px);
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
        
        .pdf-viewer-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(27, 155, 155, 0.1);
            margin-top: 2rem;
            overflow: hidden;
            display: none;
        }
        
        .pdf-header {
            background: var(--tugva-accent);
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 600px;
            border: none;
        }
        
        .back-button {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1000;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="teacher.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Ders Materyalleri
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

    <!-- Geri Dön Butonu -->
    <button class="btn btn-tugva back-button" id="backButton" onclick="closePDF()">
        <i class="fas fa-times me-2"></i>
        PDF'i Kapat
    </button>

    <div class="container mt-4">
        <!-- Materyal Listesi -->
        <div id="materialsList">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-folder-open me-2"></i>
                        Ders Materyalleri (<?php echo count($materials); ?> materyal)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($materials)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-4x mb-3"></i>
                            <h4>Henüz materyal bulunmuyor</h4>
                            <p>Yöneticiniz ders materyalleri yüklediğinde burada görüntülenecektir.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($materials as $material): ?>
                            <div class="material-item" onclick="openPDF('<?php echo $material['id']; ?>', '<?php echo htmlspecialchars($material['title']); ?>')">
                                <div class="row align-items-center">
                                    <div class="col-md-1 text-center">
                                        <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                    </div>
                                    <div class="col-md-8">
                                        <h5 class="mb-2">
                                            <i class="fas fa-book me-2"></i>
                                            <?php echo htmlspecialchars($material['title']); ?>
                                        </h5>
                                        
                                        <?php if ($material['description']): ?>
                                            <p class="text-muted mb-2">
                                                <?php echo htmlspecialchars($material['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex gap-4">
                                            <?php if ($material['lesson_name']): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <strong><?php echo htmlspecialchars($material['lesson_name']); ?></strong>
                                                </small>
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($material['uploader_name']); ?>
                                            </small>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d.m.Y', strtotime($material['created_at'])); ?>
                                            </small>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-hdd me-1"></i>
                                                <?php echo $materialManager->formatFileSize($material['file_size']); ?>
                                            </small>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-download me-1"></i>
                                                <?php echo $material['download_count']; ?> görüntüleme
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <span class="badge bg-primary p-2">
                                                <i class="fas fa-eye me-1"></i>
                                                PDF'i Görüntüle
                                            </span>
                                            <a href="download-material.php?id=<?php echo $material['id']; ?>" 
                                               class="btn btn-sm btn-tugva" onclick="event.stopPropagation()">
                                                <i class="fas fa-download"></i> İndir
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hızlı Navigasyon -->
            <div class="card">
                <div class="card-body">
                    <h6>İşlemler:</h6>
                    <a href="teacher.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-arrow-left"></i> Ana Panel
                    </a>
                    <a href="teacher-schedule-view.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-calendar-week"></i> Ders Programım
                    </a>
                    <a href="attendance.php" class="btn btn-outline-primary">
                        <i class="fas fa-clipboard-check"></i> Yoklama Al
                    </a>
                </div>
            </div>
        </div>

        <!-- PDF Görüntüleyici -->
        <div id="pdfViewerContainer" class="pdf-viewer-container">
            <div class="pdf-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0" id="pdfTitle">PDF Görüntüleyici</h5>
                        <small class="text-muted">PDF'i kapatmak için sağ üst köşedeki butona tıklayın</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" onclick="closePDF()">
                        <i class="fas fa-times me-1"></i>
                        Kapat
                    </button>
                </div>
            </div>
            <iframe id="pdfFrame" class="pdf-viewer" src=""></iframe>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPDF(materialId, title) {
            // Materyal listesini gizle
            document.getElementById('materialsList').style.display = 'none';
            
            // PDF container'ı göster
            const container = document.getElementById('pdfViewerContainer');
            container.style.display = 'block';
            
            // PDF title'ı güncelle
            document.getElementById('pdfTitle').textContent = title;
            
            // PDF'i yükle
            const iframe = document.getElementById('pdfFrame');
            iframe.src = `../uploads/materials/` + getMaterialPath(materialId);
            
            // Geri dön butonunu göster
            document.getElementById('backButton').style.display = 'block';
            
            // İndirme sayısını artır
            fetch(`download-material.php?id=${materialId}&view_only=1`);
            
            // Sayfa başına scroll
            window.scrollTo(0, 0);
        }
        
        function closePDF() {
            // PDF container'ı gizle
            document.getElementById('pdfViewerContainer').style.display = 'none';
            
            // Materyal listesini göster
            document.getElementById('materialsList').style.display = 'block';
            
            // Geri dön butonunu gizle
            document.getElementById('backButton').style.display = 'none';
            
            // PDF'i temizle
            document.getElementById('pdfFrame').src = '';
        }
        
        function getMaterialPath(materialId) {
            // Material ID'ye göre dosya yolu döndür
            const materialPaths = {
                <?php foreach ($materials as $material): ?>
                '<?php echo $material['id']; ?>': '<?php echo htmlspecialchars($material['file_path']); ?>',
                <?php endforeach; ?>
            };
            
            return materialPaths[materialId] || '';
        }
        
        // ESC tuşu ile PDF'i kapat
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePDF();
            }
        });
    </script>
</body>
</html>