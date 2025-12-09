<?php
// Kullanıcı ve Sınıf Yönetimi - SuperUser için
class UserManager {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    // Yeni öğretmen ekle
    public function addTeacher($username, $password, $fullName) {
        // Kullanıcı adı kontrolü
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Bu kullanıcı adı zaten kullanılıyor.'];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password_hash, full_name, role) 
            VALUES (?, ?, ?, 'teacher')
        ");
        
        if ($stmt->execute([$username, $passwordHash, $fullName])) {
            return ['success' => true, 'message' => 'Öğretmen başarıyla eklendi.'];
        }
        
        return ['success' => false, 'message' => 'Öğretmen eklenirken hata oluştu.'];
    }

    // Tüm öğretmenleri listele
    public function getAllTeachers() {
        $stmt = $this->db->prepare("
            SELECT 
                u.id, 
                u.username, 
                u.full_name, 
                u.is_active, 
                u.last_login,
                COUNT(tc.class_id) as class_count
            FROM users u
            LEFT JOIN teacher_classes tc ON u.id = tc.teacher_id
            WHERE u.role = 'teacher'
            GROUP BY u.id
            ORDER BY u.full_name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Öğretmeni deaktif et (güvenli silme)
    public function deactivateTeacher($teacherId) {
        try {
            $this->db->beginTransaction();
            
            // Önce öğretmenin yoklama almış derslerini kontrol et
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM lessons l
                JOIN weekly_schedule ws ON l.schedule_id = ws.id
                WHERE ws.teacher_id = ? AND l.attendance_marked = 1
            ");
            $stmt->execute([$teacherId]);
            $attendedLessons = $stmt->fetchColumn();
            
            if ($attendedLessons > 0) {
                $this->db->rollback();
                return ['success' => false, 'message' => "Bu öğretmenin $attendedLessons dersi için yoklama alınmış. Güvenlik için silinemez."];
            }
            
            // Öğretmeni deaktif et
            $stmt = $this->db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'teacher'");
            $stmt->execute([$teacherId]);
            
            // Sınıf atamalarını kaldır
            $stmt = $this->db->prepare("DELETE FROM teacher_classes WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            
            // Yoklama alınmamış derslerini sil
            $stmt = $this->db->prepare("
                DELETE l FROM lessons l
                JOIN weekly_schedule ws ON l.schedule_id = ws.id
                WHERE ws.teacher_id = ? AND l.attendance_marked = 0
            ");
            $stmt->execute([$teacherId]);
            
            // Haftalık programlarını deaktif et
            $stmt = $this->db->prepare("UPDATE weekly_schedule SET is_active = 0 WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Öğretmen başarıyla kaldırıldı.'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Silme işlemi sırasında hata: ' . $e->getMessage()];
        }
    }

    // Öğretmeni tamamen sil (acil durum için)
    public function deleteTeacher($teacherId) {
        try {
            $this->db->beginTransaction();
            
            // Tüm bağlantılı kayıtları sil
            $stmt = $this->db->prepare("DELETE FROM teacher_classes WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            
            $stmt = $this->db->prepare("
                DELETE l FROM lessons l
                JOIN weekly_schedule ws ON l.schedule_id = ws.id
                WHERE ws.teacher_id = ?
            ");
            $stmt->execute([$teacherId]);
            
            $stmt = $this->db->prepare("DELETE FROM weekly_schedule WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->execute([$teacherId]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Öğretmen tamamen silindi.'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Silme işlemi sırasında hata: ' . $e->getMessage()];
        }
    }

    // Sınıf ekle
    public function addClass($name, $academicYear) {
        $stmt = $this->db->prepare("
            INSERT INTO classes (name, academic_year) 
            VALUES (?, ?)
        ");
        
        if ($stmt->execute([$name, $academicYear])) {
            return ['success' => true, 'message' => 'Sınıf başarıyla eklendi.'];
        }
        
        return ['success' => false, 'message' => 'Sınıf eklenirken hata oluştu.'];
    }

    // Tüm sınıfları listele
    public function getAllClasses() {
        $stmt = $this->db->prepare("
            SELECT 
                c.id, 
                c.name, 
                c.academic_year, 
                c.is_active,
                COUNT(s.id) as student_count,
                COUNT(tc.teacher_id) as teacher_count
            FROM classes c
            LEFT JOIN students s ON c.id = s.class_id AND s.is_active = 1
            LEFT JOIN teacher_classes tc ON c.id = tc.class_id
            GROUP BY c.id
            ORDER BY c.academic_year DESC, c.name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Öğretmeni sınıfa ata
    public function assignTeacherToClass($teacherId, $classId) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO teacher_classes (teacher_id, class_id) 
            VALUES (?, ?)
        ");
        
        if ($stmt->execute([$teacherId, $classId])) {
            return ['success' => true, 'message' => 'Öğretmen sınıfa atandı.'];
        }
        
        return ['success' => false, 'message' => 'Atama sırasında hata oluştu.'];
    }

    // Haftalık program ekle
    public function addWeeklySchedule($classId, $teacherId, $dayOfWeek, $startTime, $endTime, $lessonName) {
        $stmt = $this->db->prepare("
            INSERT INTO weekly_schedule (class_id, teacher_id, day_of_week, start_time, end_time, lesson_name) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$classId, $teacherId, $dayOfWeek, $startTime, $endTime, $lessonName])) {
            return ['success' => true, 'message' => 'Haftalık program eklendi.'];
        }
        
        return ['success' => false, 'message' => 'Program eklenirken hata oluştu.'];
    }

    // Öğrenci ekle
    public function addStudent($fullName, $classId) {
        $stmt = $this->db->prepare("
            INSERT INTO students (full_name, class_id) 
            VALUES (?, ?)
        ");
        
        if ($stmt->execute([$fullName, $classId])) {
            return ['success' => true, 'message' => 'Öğrenci başarıyla eklendi.'];
        }
        
        return ['success' => false, 'message' => 'Öğrenci eklenirken hata oluştu.'];
    }

    // Sınıfın öğrencilerini listele
    public function getClassStudents($classId) {
        $stmt = $this->db->prepare("
            SELECT id, full_name, is_active, created_at
            FROM students 
            WHERE class_id = ?
            ORDER BY full_name
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    // Sınıfın haftalık programını getir
    public function getClassSchedule($classId) {
        $stmt = $this->db->prepare("
            SELECT 
                ws.*,
                u.full_name as teacher_name
            FROM weekly_schedule ws
            JOIN users u ON ws.teacher_id = u.id
            WHERE ws.class_id = ? AND ws.is_active = 1
            ORDER BY ws.day_of_week, ws.start_time
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    // Sistem istatistikleri
    public function getSystemStats() {
        $stats = [];
        
        // Toplam öğretmen
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1");
        $stmt->execute();
        $stats['teachers'] = $stmt->fetchColumn();
        
        // Toplam sınıf
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM classes WHERE is_active = 1");
        $stmt->execute();
        $stats['classes'] = $stmt->fetchColumn();
        
        // Toplam öğrenci
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM students WHERE is_active = 1");
        $stmt->execute();
        $stats['students'] = $stmt->fetchColumn();
        
        // Bu haftaki toplam ders
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM lessons WHERE WEEK(lesson_date) = WEEK(CURDATE())");
        $stmt->execute();
        $stats['weekly_lessons'] = $stmt->fetchColumn();
        
        // Eksik yoklamalar
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM lessons WHERE lesson_date < CURDATE() AND attendance_marked = 0");
        $stmt->execute();
        $stats['missing_attendance'] = $stmt->fetchColumn();
        
        return $stats;
    }
}
?>