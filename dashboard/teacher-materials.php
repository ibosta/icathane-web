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
$teacherId = $_SESSION['user_id'];

// Öğretmenin erişebileceği materyalleri getir
$materials = $materialManager->getTeacherMaterials($teacherId);

// Sınıf bazında gruplama
$materialsByClass = [];
$publicMaterials = [];

foreach ($materials as $material) {
    if ($material['is_public'] || !$material['class_id']) {
        $publicMaterials[] = $material;
    } else {
        $materialsByClass[$material['class_name']][] = $material;
    }
}

// Filtreler
$classFilter = $_GET['class_filter'] ?? '';
$typeFilter = $_GET['type_filter'] ?? '';

if ($classFilter || $typeFilter) {
    $filteredMaterials = [];
    foreach ($materials as $material) {
        $includeByClass = empty($classFilter) || 
                         ($classFilter === 'public' && $material['is_public']) ||
                         ($material['class_name'] === $classFilter);
        $includeByType = empty($typeFilter) || $material['file_type'] === $typeFilter;
        
        if ($includeByClass && $includeByType) {
            $filteredMaterials[] = $material;
        }
    }
    $materials = $filteredMaterials;
}

// Dosya tiplerini al
$fileTypes = [];
foreach ($materials as $material) {
    if (!in_array($material['file_type'], $fileTypes)) {
        $fileTypes[] = $material['file_type'];
    }
}
sort($fileTypes);
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
        
        .filter-bar {
            background: var(--tugva-accent);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .class-section {
            margin-bottom: 2rem;
        }
        
        .class-header {
            background: var(--tugva-accent);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--tugva-primary);
        }
        
        .btn-tugva {
            background: linear-gradient(135deg, var(--tugva-primary), var(--tugva-secondary));
            border: none;
            color: white;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-tugva:hover {
            background: var(--tugva-secondary);
            color: white;
            transform: translateY(-2px);
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

    <div class="container mt-4">
        <!-- Filtreler -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="class_filter" onchange="this.form.submit()">
                        <option value="">Tüm Materyaller</option>
                        <option value="public" <?php echo $classFilter === 'public' ? 'selected' : ''; ?>>Herkese Açık</option>
                        <?php foreach (array_keys($materialsByClass) as $className): ?>
                            <option value="<?php echo htmlspecialchars($className); ?>" <?php echo $classFilter === $className ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($className); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="type_filter" onchange="this.form.submit()">
                        <option value="">Tüm Dosya Tipleri</option>
                        <?php foreach ($fileTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $typeFilter === $type ? 'selected' : ''; ?>>
                                <?php echo strtoupper($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <?php echo count($materials); ?> materyal bulundu
                        </small>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleView('grid')">
                                <i class="fas fa-th"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleView('list')">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php if (empty($materials)): ?>
            <!-- Materyal Yok -->
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-folder-open fa-4x mb-3 text-muted"></i>
                    <h4>Henüz materyal bulunmuyor</h4>
                    <p class="text-muted">
                        Yöneticiniz ders materyalleri yüklediğinde burada görüntülenecektir.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <!-- Herkese Açık Materyaller -->
            <?php if (!empty($publicMaterials)): ?>
                <div class="class-section">
                    <div class="class-header">
                        <h5 class="mb-0">
                            <i class="fas fa-globe me-2"></i>
                            Herkese Açık Materyaller (<?php echo count($publicMaterials); ?>)
                        </h5>
                    </div>
                    <div id="materials-container">
                        <?php foreach ($publicMaterials as $material): ?>
                            <div class="material-item">
                                <div class="d-flex align-items-start">
                                    <div class="file-icon">
                                        <i class="<?php echo $materialManager->getFileIcon($material['file_type']); ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($material['title']); ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="fas fa-globe"></i> Herkese Açık
                                            </span>
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
                                                    <?php echo date('d.m.Y', strtotime($material['created_at'])); ?>
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
                                                   class="btn btn-sm btn-tugva">
                                                    <i class="fas fa-download"></i> İndir
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sınıf Bazında Materyaller -->
            <?php foreach ($materialsByClass as $className => $classMaterials): ?>
                <div class="class-section">
                    <div class="class-header">
                        <h5 class="mb-0">
                            <i class="fas fa-school me-2"></i>
                            <?php echo htmlspecialchars($className); ?> (<?php echo count($classMaterials); ?> materyal)
                        </h5>
                    </div>
                    <div>
                        <?php foreach ($classMaterials as $material): ?>
                            <div class="material-item">
                                <div class="d-flex align-items-start">
                                    <div class="file-icon">
                                        <i class="<?php echo $materialManager->getFileIcon($material['file_type']); ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($material['title']); ?>
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
                                                    <?php echo date('d.m.Y', strtotime($material['created_at'])); ?>
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
                                                   class="btn btn-sm btn-tugva">
                                                    <i class="fas fa-download"></i> İndir
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleView(view) {
            // Grid/List view toggle functionality
            const container = document.getElementById('materials-container');
            if (view === 'grid') {
                // Grid view implementation
                console.log('Grid view activated');
            } else {
                // List view implementation
                console.log('List view activated');
            }
        }
    </script>
</body>
</html>