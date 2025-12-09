<?php
// Ders ve Yoklama Yönetimi - Otomatik Ders Oluşturma
class LessonManager {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    // Haftalık programdan otomatik ders oluşturma
    public function createLessonsFromSchedule($weeksAhead = 8) {
        // Önümüzdeki haftalık dersleri oluştur
        $stmt = $this->db->prepare("
            SELECT ws.*, c.name as class_name, u.full_name as teacher_name 
            FROM weekly_schedule ws
            JOIN classes c ON ws.class_id = c.id
            JOIN users u ON ws.teacher_id = u.id
            WHERE ws.is_active = 1 AND c.is_active = 1
        ");
        $stmt->execute();
        $schedules = $stmt->fetchAll();

        $created = 0;
        $today = new DateTime();
        
        foreach ($schedules as $schedule) {
            for ($week = 0; $week < $weeksAhead; $week++) {
                $lessonDate = clone $today;
                $lessonDate->add(new DateInterval("P{$week}W"));
                
                // Haftanın doğru gününü bul (1=Pazartesi, 7=Pazar)
                $currentDayOfWeek = $lessonDate->format('N');
                $targetDayOfWeek = $schedule['day_of_week'];
                
                $dayDifference = $targetDayOfWeek - $currentDayOfWeek;
                if ($dayDifference < 0) {
                    $dayDifference += 7; // Sonraki haftaya geç
                }
                $lessonDate->add(new DateInterval("P{$dayDifference}D"));

                // Bu ders zaten var mı kontrol et
                $checkStmt = $this->db->prepare("SELECT id FROM lessons WHERE schedule_id = ? AND lesson_date = ?");
                $checkStmt->execute([$schedule['id'], $lessonDate->format('Y-m-d')]);
                
                if (!$checkStmt->fetch()) {
                    // Yeni ders oluştur
                    $insertStmt = $this->db->prepare("
                        INSERT INTO lessons (schedule_id, lesson_date) 
                        VALUES (?, ?)
                    ");
                    $insertStmt->execute([$schedule['id'], $lessonDate->format('Y-m-d')]);
                    $created++;
                }
            }
        }

        return $created;
    }

    // Öğretmenin bugünkü derslerini getir
    public function getTodayLessons($teacherId) {
        $today = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT 
                l.id,
                l.lesson_date,
                l.topic,
                l.attendance_marked,
                ws.lesson_name,
                ws.start_time,
                ws.end_time,
                c.name as class_name,
                COUNT(s.id) as student_count,
                COUNT(a.id) as attendance_count
            FROM lessons l
            JOIN weekly_schedule ws ON l.schedule_id = ws.id
            JOIN classes c ON ws.class_id = c.id
            LEFT JOIN students s ON c.id = s.class_id AND s.is_active = 1
            LEFT JOIN attendance a ON l.id = a.lesson_id
            WHERE ws.teacher_id = ? AND l.lesson_date = ?
            GROUP BY l.id
            ORDER BY ws.start_time
        ");
        $stmt->execute([$teacherId, $today]);
        return $stmt->fetchAll();
    }

    // Öğretmenin gelecek derslerini getir
    public function getUpcomingLessons($teacherId, $days = 7) {
        $today = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$days} days"));
        
        $stmt = $this->db->prepare("
            SELECT 
                l.id,
                l.lesson_date,
                l.topic,
                l.attendance_marked,
                ws.lesson_name,
                ws.start_time,
                ws.end_time,
                c.name as class_name
            FROM lessons l
            JOIN weekly_schedule ws ON l.schedule_id = ws.id
            JOIN classes c ON ws.class_id = c.id
            WHERE ws.teacher_id = ? 
            AND l.lesson_date > ? 
            AND l.lesson_date <= ?
            ORDER BY l.lesson_date, ws.start_time
        ");
        $stmt->execute([$teacherId, $today, $endDate]);
        return $stmt->fetchAll();
    }

    // Eksik yoklamaları getir (geçmiş dersler)
    public function getMissingAttendance($teacherId) {
        $today = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT 
                l.id,
                l.lesson_date,
                ws.lesson_name,
                ws.start_time,
                ws.end_time,
                c.name as class_name,
                DATEDIFF(?, l.lesson_date) as days_overdue
            FROM lessons l
            JOIN weekly_schedule ws ON l.schedule_id = ws.id
            JOIN classes c ON ws.class_id = c.id
            WHERE ws.teacher_id = ? 
            AND l.lesson_date < ? 
            AND l.attendance_marked = 0
            ORDER BY l.lesson_date DESC
        ");
        $stmt->execute([$today, $teacherId, $today]);
        return $stmt->fetchAll();
    }

    // Ders için öğrenci listesini getir
    public function getLessonStudents($lessonId) {
        $stmt = $this->db->prepare("
            SELECT 
                s.id,
                s.full_name,
                COALESCE(a.status, 'absent') as status
            FROM lessons l
            JOIN weekly_schedule ws ON l.schedule_id = ws.id
            JOIN students s ON ws.class_id = s.class_id
            LEFT JOIN attendance a ON l.id = a.lesson_id AND s.id = a.student_id
            WHERE l.id = ? AND s.is_active = 1
            ORDER BY s.full_name
        ");
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll();
    }

    // Yoklama kaydet
    public function saveAttendance($lessonId, $topic, $attendanceData) {
        try {
            $this->db->beginTransaction();

            // Ders konusunu güncelle
            $stmt = $this->db->prepare("UPDATE lessons SET topic = ?, attendance_marked = 1 WHERE id = ?");
            $stmt->execute([$topic, $lessonId]);

            // Yoklama kayıtlarını sil ve yeniden ekle
            $deleteStmt = $this->db->prepare("DELETE FROM attendance WHERE lesson_id = ?");
            $deleteStmt->execute([$lessonId]);

            // Yeni yoklama kayıtlarını ekle
            $insertStmt = $this->db->prepare("INSERT INTO attendance (lesson_id, student_id, status) VALUES (?, ?, ?)");
            
            foreach ($attendanceData as $studentId => $status) {
                $insertStmt->execute([$lessonId, $studentId, $status]);
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Yoklama başarıyla kaydedildi.'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Yoklama kaydedilemedi: ' . $e->getMessage()];
        }
    }

    // SuperUser için tüm eksik yoklamaları getir
    public function getAllMissingAttendance() {
        $today = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT 
                l.id,
                l.lesson_date,
                ws.lesson_name,
                ws.start_time,
                ws.end_time,
                c.name as class_name,
                u.full_name as teacher_name,
                DATEDIFF(?, l.lesson_date) as days_overdue
            FROM lessons l
            JOIN weekly_schedule ws ON l.schedule_id = ws.id
            JOIN classes c ON ws.class_id = c.id
            JOIN users u ON ws.teacher_id = u.id
            WHERE l.lesson_date < ? 
            AND l.attendance_marked = 0
            ORDER BY l.lesson_date DESC, u.full_name
        ");
        $stmt->execute([$today, $today]);
        return $stmt->fetchAll();
    }
}
?>