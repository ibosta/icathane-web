<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/UserManager.php';
require_once '../classes/MaterialManager.php';

$auth = new Auth($pdo);
$auth->requireSuperUser();

$userManager = new UserManager($pdo);
$materialManager = new MaterialManager($pdo);
$message = '';

// Materyal yükleme
if ($_POST && isset($_POST['upload_material'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $classId = $_POST['class_id'] ?: null;
    $lessonName = trim($_POST['lesson_name']);
    $isPublic = isset($_POST['is_public']);
    
    if (empty($title)) {
        $message = ['type' => 'danger', 'text' => 'Materyal başlığı gerekli.'];
    } elseif (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $message = ['type' => 'danger', 'text' => 'Dosya seçilmedi.'];
    } else {
        $result = $materialManager->uploadMaterial(
            $_FILES['material_file'], 
            $title, 
            $_SESSION['user_id'],
            $description, 
            $classId, 
            $lessonName, 
            $isPublic
        );
        $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
    }
}

// Materyal silme
if ($_POST && isset($_POST['delete_material'])) {
    $materialId = $_POST['material_id'];
    $result = $materialManager->deleteMaterial($materialId, $_SESSION['user_id']);
    $message = ['type' => $result['success'] ? 'success' : 'danger', 'text' => $result['message']];
}

// Veriler
$classes = $userManager->getAllClasses();
$materials = $materialManager->getMaterials();
$stats = $materialManager->getStats();

// Filtreler
$classFilter = $_GET['class_filter'] ?? '';
$typeFilter = $_GET['type_filter'] ?? '';

if ($classFilter || $typeFilter) {
    $filteredMaterials = [];
    foreach ($materials as $material) {
        $includeByClass = empty($classFilter) || $material['class_id'] == $classFilter || ($classFilter === 'public' && $material['is_public']);
        $includeByType = empty($typeFilter) || $material['file_type'] === $typeFilter;
        
        if ($includeByClass && $includeByType) {
            $filteredMaterials[] = $material;
        }
    }
    $materials = $filteredMaterials;
}
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
        
        .form-control, .form-select {
            border: 2px solid var(--tugva-accent);
            border-radius: 10px;
            padding: 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--tugva-primary);
            box-shadow: 0 0 0 0.2rem rgba(27, 155, 155, 0.25);
        }
        
        .material-item {
            border: 2px solid var(--tugva-accent);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
        }
        
        .material-item:hover {
            border-color: var(--tugva-primary);
            box-shadow: 0 8px 25px rgba(27, 155, 155, 0.15);
            transform: translateY(-3px);
        }
        
        .file-icon {
            font-size: 2rem;
            margin-right: 1rem;
        }
        
        .upload-area {
            border: 3px dashed var(--tugva-accent);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: #fafbfc;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: var(--tugva-primary);
            background: var(--tugva-accent);
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
        
        .filter-bar {
            background: var(--tugva-accent);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="superuser.php">
                <i class="fas fa-arrow-left me-2"></i>
                TÜGVA Kocaeli Icathane - Ders Materyalleri
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
        <!-- Mesaj -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-file fa-2x mb-2" style="color: var(--tugva-primary);"></i>
                    <div class="stat-number"><?php echo $stats['total_materials']; ?></div>
                    <small>Toplam Materyal</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-hdd fa-2x mb-2" style="color: var(--tugva-primary);"></i>
                    <div class="stat-number"><?php echo $materialManager->formatFileSize($stats['total_size']); ?></div>
                    <small>Toplam Boyut</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-download fa-2x mb-2" style="color: var(--tugva-primary);"></i>
                    <div class="stat-number"><?php echo $stats['total_downloads']; ?></div>
                    <small>Toplam İndirme</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-file-pdf fa-2x mb-2" style="color: #dc3545;"></i>
                    <div class="stat-number">
                        <?php 
                        $pdfCount = 0;
                        foreach ($stats['file_types'] as $type) {
                            if ($type['file_type'] === 'pdf') {
                                $pdfCount = $type['count'];
                                break;
                            }
                        }
                        echo $pdfCount;
                        ?>
                    </div>
                    <small>PDF Dosyaları</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sol Kolon: Yükleme Formu -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Yeni Materyal Yükle
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label fw-bold">Materyal Başlığı *</label>
                                <input type="text" class="form-control" id="title" name="title" required placeholder="örn: Matematik Ders Notları">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label fw-bold">Açıklama</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Materyalle ilgili kısa açıklama..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="class_id" class="form-label fw-bold">Sınıf</label>
                                <select class="form-select" id="class_id" name="class_id">
                                    <option value="">Tüm Sınıflar (Genel)</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Boş bırakırsanız tüm sınıflar görebilir</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lesson_name" class="form-label fw-bold">Ders Adı</label>
                                <input type="text" class="form-control" id="lesson_name" name="lesson_name" placeholder="örn: Matematik, Türkçe">
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1">
                                    <label class="form-check-label fw-bold" for="is_public">
                                        <i class="fas fa-globe me-2"></i>
                                        Herkese Açık
                                    </label>
                                    <small class="d-block text-muted">Tüm öğretmenler görebilsin</small>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Dosya Seçin *</label>
                                <div class="upload-area" onclick="document.getElementById('material_file').click()">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-2" style="color: var(--tugva-primary);"></i>
                                    <p class="mb-2">Dosyayı seçmek için tıklayın</p>
                                    <small class="text-muted">PDF, DOC, PPT, Excel, Resim dosyaları<br>Maksimum 50MB</small>
                                </div>
                                <input type="file" id="material_file" name="material_file" class="d-none" required onchange="updateFileName(this)">
                                <div id="fileName" class="mt-2 text-success" style="display: none;"></div>
                            </div>
                            
                            <button type="submit" name="upload_material" class="btn btn-tugva w-100">
                                <i class="fas fa-upload me-2"></i>
                                Materyal Yükle
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon: Materyal Listesi -->
            <div class="col-md-8">
                <!-- Filtreler -->
                <div class="filter-bar">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" name="class_filter" onchange="this.form.submit()">
                                <option value="">Tüm Sınıflar</option>
                                <option value="public" <?php echo $classFilter === 'public' ? 'selected' : ''; ?>>Herkese Açık</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $classFilter == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="type_filter" onchange="this.form.submit()">
                                <option value="">Tüm Dosya Tipleri</option>
                                <?php foreach ($stats['file_types'] as $type): ?>
                                    <option value="<?php echo $type['file_type']; ?>" <?php echo $typeFilter === $type['file_type'] ? 'selected' : ''; ?>>
                                        <?php echo strtoupper($type['file_type']); ?> (<?php echo $type['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5 text-end">
                            <small class="text-muted">Toplam: <?php echo count($materials); ?> materyal</small>
                        </div>
                    </form>
                </div>

                <!-- Materyal Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-folder-open me-2"></i>
                            Ders Materyalleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($materials)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <h5>Henüz materyal yüklenmemiş</h5>
                                <p>Sol taraftaki formu kullanarak ilk materyalinizi yükleyin.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <div class="material-item">
                                    <div class="d-flex align-items-start">
                                        <div class="file-icon">
                                            <i class="<?php echo $materialManager->getFileIcon($material['file_type']); ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($material['title']); ?>
                                                <?php if ($material['is_public']): ?>
                                                    <span class="badge bg-success ms-2">
                                                        <i class="fas fa-globe"></i> Herkese Açık
                                                    </span>
                                                <?php endif; ?>
                                            </h6>
                                            
                                            <div class="text-muted mb-2">
                                                <small>
                                                    <i class="fas fa-file me-1"></i>
                                                    <?php echo htmlspecialchars($material['file_name']); ?>
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-hdd me-1"></i>
                                                    <?php echo $materialManager->formatFileSize($material['file_size']); ?>
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-download me-1"></i>
                                                    <?php echo $material['download_count']; ?> indirme
                                                </small>
                                            </div>
                                            
                                            <?php if ($material['description']): ?>
                                                <div class="mb-2">
                                                    <small><?php echo htmlspecialchars($material['description']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($material['uploader_name']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('d.m.Y H:i', strtotime($material['created_at'])); ?>
                                                        <?php if ($material['class_name']): ?>
                                                            <span class="mx-2">•</span>
                                                            <i class="fas fa-school me-1"></i>
                                                            <?php echo htmlspecialchars($material['class_name']); ?>
                                                        <?php endif; ?>
                                                        <?php if ($material['lesson_name']): ?>
                                                            <span class="mx-2">•</span>
                                                            <i class="fas fa-book me-1"></i>
                                                            <?php echo htmlspecialchars($material['lesson_name']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <a href="view-material.php?id=<?php echo $material['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary me-1" target="_blank">
                                                        <i class="fas fa-eye"></i> Görüntüle
                                                    </a>
                                                    <a href="download-material.php?id=<?php echo $material['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success me-1">
                                                        <i class="fas fa-download"></i> İndir
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu materyali silmek istediğinizden emin misiniz?')">
                                                        <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                                        <button type="submit" name="delete_material" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hızlı Navigasyon -->
        <div class="card">
            <div class="card-body">
                <h6>İşlemler:</h6>
                <a href="superuser.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-tachometer-alt"></i> Ana Panel
                </a>
                <a href="manage-teachers.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-chalkboard-teacher"></i> Öğretmenler
                </a>
                <a href="manage-classes.php" class="btn btn-outline-primary">
                    <i class="fas fa-school"></i> Sınıflar
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateFileName(input) {
            const fileName = input.files[0]?.name;
            const fileSize = input.files[0]?.size;
            const fileNameDiv = document.getElementById('fileName');
            
            if (fileName) {
                const sizeText = fileSize ? ` (${formatFileSize(fileSize)})` : '';
                fileNameDiv.innerHTML = `<i class="fas fa-check me-2"></i><strong>Seçilen dosya:</strong> ${fileName}${sizeText}`;
                fileNameDiv.style.display = 'block';
            } else {
                fileNameDiv.style.display = 'none';
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes >= 1048576) {
                return Math.round(bytes / 1048576 * 100) / 100 + ' MB';
            } else if (bytes >= 1024) {
                return Math.round(bytes / 1024 * 100) / 100 + ' KB';
            } else {
                return bytes + ' B';
            }
        }
        
        // Drag & Drop
        const uploadArea = document.querySelector('.upload-area');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            uploadArea.classList.add('dragover');
        }
        
        function unhighlight(e) {
            uploadArea.classList.remove('dragover');
        }
        
        uploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            document.getElementById('material_file').files = files;
            updateFileName(document.getElementById('material_file'));
        }
    </script>
</body>
</html>