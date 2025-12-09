<?php
// Ders Materyalleri Yönetimi
class MaterialManager {
    private $db;
    private $uploadDir;

    public function __construct($database) {
        $this->db = $database;
        $this->uploadDir = '../uploads/materials/';
        
        // Upload klasörünü oluştur
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    // Dosya yükleme
    public function uploadMaterial($fileData, $title, $uploadedBy, $description = '', $classId = null, $lessonName = '', $isPublic = false) {
        try {
            // Dosya kontrolü
            if ($fileData['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Dosya yükleme hatası: ' . $this->getUploadErrorMessage($fileData['error'])];
            }

            // Dosya boyutu kontrolü (50MB limit)
            $maxSize = 50 * 1024 * 1024; // 50MB
            if ($fileData['size'] > $maxSize) {
                return ['success' => false, 'message' => 'Dosya boyutu çok büyük. Maksimum 50MB olabilir.'];
            }

            // Dosya tipi kontrolü
            $allowedTypes = [
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

            $fileExtension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
            if (!array_key_exists($fileExtension, $allowedTypes)) {
                return ['success' => false, 'message' => 'Desteklenmeyen dosya formatı. İzin verilen: PDF, DOC, PPT, Excel, resim dosyaları'];
            }

            // Güvenli dosya adı oluştur
            $originalName = pathinfo($fileData['name'], PATHINFO_FILENAME);
            $safeFileName = $this->generateSafeFileName($originalName, $fileExtension);
            $filePath = $this->uploadDir . $safeFileName;

            // Dosyayı kaydet
            if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Dosya kaydedilemedi.'];
            }

            // Veritabanına kaydet
            $stmt = $this->db->prepare("
                INSERT INTO lesson_materials (title, description, file_name, file_path, file_type, file_size, class_id, lesson_name, uploaded_by, is_public)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $title,
                $description,
                $fileData['name'], // Orijinal dosya adı
                $safeFileName,     // Sunucudaki dosya adı
                $fileExtension,
                $fileData['size'],
                $classId ?: null,
                $lessonName ?: null,
                $uploadedBy,
                $isPublic ? 1 : 0
            ]);

            if ($result) {
                return ['success' => true, 'message' => 'Materyal başarıyla yüklendi.', 'material_id' => $this->db->lastInsertId()];
            } else {
                // Başarısız ise dosyayı sil
                unlink($filePath);
                return ['success' => false, 'message' => 'Veritabanı kaydı başarısız.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Hata: ' . $e->getMessage()];
        }
    }

    // Güvenli dosya adı oluştur
    private function generateSafeFileName($originalName, $extension) {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalName);
        $safeName = substr($safeName, 0, 100); // Uzunluk sınırı
        $timestamp = time();
        $random = rand(1000, 9999);
        return $timestamp . '_' . $random . '_' . $safeName . '.' . $extension;
    }

    // Upload hata mesajları
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Dosya çok büyük';
            case UPLOAD_ERR_PARTIAL:
                return 'Dosya kısmen yüklendi';
            case UPLOAD_ERR_NO_FILE:
                return 'Dosya seçilmedi';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Geçici klasör bulunamadı';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Dosya yazılamadı';
            default:
                return 'Bilinmeyen hata';
        }
    }

    // Materyal listesi getir
    public function getMaterials($classId = null, $lessonName = null, $isPublic = null, $userId = null) {
        $sql = "
            SELECT 
                lm.*,
                c.name as class_name,
                u.full_name as uploader_name
            FROM lesson_materials lm
            LEFT JOIN classes c ON lm.class_id = c.id
            JOIN users u ON lm.uploaded_by = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($classId !== null) {
            $sql .= " AND (lm.class_id = ? OR lm.is_public = 1)";
            $params[] = $classId;
        }
        
        if ($lessonName !== null) {
            $sql .= " AND lm.lesson_name = ?";
            $params[] = $lessonName;
        }
        
        if ($isPublic !== null) {
            $sql .= " AND lm.is_public = ?";
            $params[] = $isPublic ? 1 : 0;
        }
        
        if ($userId !== null) {
            $sql .= " AND lm.uploaded_by = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY lm.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Öğretmenin erişebileceği materyaller
    public function getTeacherMaterials($teacherId) {
        $sql = "
            SELECT 
                lm.*,
                c.name as class_name,
                u.full_name as uploader_name
            FROM lesson_materials lm
            LEFT JOIN classes c ON lm.class_id = c.id
            LEFT JOIN teacher_classes tc ON c.id = tc.class_id
            JOIN users u ON lm.uploaded_by = u.id
            WHERE (tc.teacher_id = ? OR lm.is_public = 1)
            ORDER BY lm.created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll();
    }

    // Sınıf materyalleri
    public function getClassMaterials($classId) {
        $sql = "
            SELECT 
                lm.*,
                u.full_name as uploader_name
            FROM lesson_materials lm
            JOIN users u ON lm.uploaded_by = u.id
            WHERE (lm.class_id = ? OR lm.is_public = 1)
            ORDER BY lm.created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    // Materyal bilgisi getir
    public function getMaterial($materialId) {
        $stmt = $this->db->prepare("
            SELECT 
                lm.*,
                c.name as class_name,
                u.full_name as uploader_name
            FROM lesson_materials lm
            LEFT JOIN classes c ON lm.class_id = c.id
            JOIN users u ON lm.uploaded_by = u.id
            WHERE lm.id = ?
        ");
        $stmt->execute([$materialId]);
        return $stmt->fetch();
    }

    // İndirme sayısını artır
    public function incrementDownloadCount($materialId) {
        $stmt = $this->db->prepare("UPDATE lesson_materials SET download_count = download_count + 1 WHERE id = ?");
        return $stmt->execute([$materialId]);
    }

    // Materyal sil
    public function deleteMaterial($materialId, $userId = null) {
        try {
            // Materyal bilgilerini al
            $material = $this->getMaterial($materialId);
            if (!$material) {
                return ['success' => false, 'message' => 'Materyal bulunamadı.'];
            }

            // Yetki kontrolü (kendi yüklediği veya superuser)
            if ($userId && $material['uploaded_by'] != $userId) {
                // SuperUser kontrolü
                $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if (!$user || $user['role'] !== 'superuser') {
                    return ['success' => false, 'message' => 'Bu materyali silme yetkiniz yok.'];
                }
            }

            // Dosyayı sil
            $filePath = $this->uploadDir . $material['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Veritabanından sil
            $stmt = $this->db->prepare("DELETE FROM lesson_materials WHERE id = ?");
            if ($stmt->execute([$materialId])) {
                return ['success' => true, 'message' => 'Materyal başarıyla silindi.'];
            } else {
                return ['success' => false, 'message' => 'Veritabanı silme hatası.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Hata: ' . $e->getMessage()];
        }
    }

    // Dosya boyutunu okunabilir formata çevir
    public function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    // Dosya ikonu getir
    public function getFileIcon($fileType) {
        $icons = [
            'pdf' => 'fas fa-file-pdf text-danger',
            'doc' => 'fas fa-file-word text-primary',
            'docx' => 'fas fa-file-word text-primary',
            'ppt' => 'fas fa-file-powerpoint text-warning',
            'pptx' => 'fas fa-file-powerpoint text-warning',
            'xls' => 'fas fa-file-excel text-success',
            'xlsx' => 'fas fa-file-excel text-success',
            'jpg' => 'fas fa-file-image text-info',
            'jpeg' => 'fas fa-file-image text-info',
            'png' => 'fas fa-file-image text-info',
            'gif' => 'fas fa-file-image text-info',
            'txt' => 'fas fa-file-alt text-secondary',
            'zip' => 'fas fa-file-archive text-dark',
            'rar' => 'fas fa-file-archive text-dark'
        ];

        return $icons[strtolower($fileType)] ?? 'fas fa-file text-muted';
    }

    // İstatistikler
    public function getStats() {
        $stats = [];
        
        // Toplam materyal
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM lesson_materials");
        $stmt->execute();
        $stats['total_materials'] = $stmt->fetchColumn();
        
        // Toplam boyut
        $stmt = $this->db->prepare("SELECT SUM(file_size) FROM lesson_materials");
        $stmt->execute();
        $stats['total_size'] = $stmt->fetchColumn() ?: 0;
        
        // En çok indirilen
        $stmt = $this->db->prepare("SELECT SUM(download_count) FROM lesson_materials");
        $stmt->execute();
        $stats['total_downloads'] = $stmt->fetchColumn() ?: 0;
        
        // Dosya tiplerinin dağılımı
        $stmt = $this->db->prepare("SELECT file_type, COUNT(*) as count FROM lesson_materials GROUP BY file_type ORDER BY count DESC");
        $stmt->execute();
        $stats['file_types'] = $stmt->fetchAll();
        
        return $stats;
    }
}
?>