# Icathane Web 🏫🚀

[![PHP](https://img.shields.io/badge/PHP-8.1+-red?style=flat&logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-blue?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com)
[![License](https://img.shields.io/github/license/ibosta/icathane-web)](LICENSE)
[![Issues](https://img.shields.io/github/issues/ibosta/icathane-web)](https://github.com/ibosta/icathane-web/issues)
[![Stars](https://img.shields.io/github/stars/ibosta/icathane-web?style=social)](https://github.com/ibosta/icathane-web/stargazers)
[![Forks](https://img.shields.io/github/forks/ibosta/icathane-web?style=social)](https://github.com/ibosta/icathane-web/network/members)

**Icathane Web**, Icathane eğitim platformu içerisinde kodlama ve yazılım derslerinin **yoklamalarını (attendance)**, **ders materyallerini** ve **ilerleme istatistiklerini** tek bir yerde yönetmek için geliştirilmiş tam teşekküllü bir web uygulamasıdır. PHP ve MySQL tabanlı backend ile öğretmenler ve öğrenciler için kapsamlı bir yönetim paneli sunar.

## 🎯 Proje Amacı
Icathane platformunda yer alan **öğretmenler** ve **öğrenciler** için:
- Ders yoklamalarını hızlıca kaydetme ve takip etme.
- Ders materyallerini (slaytlar, videolar, ödevler) yükleme ve paylaşma.
- **Öğrenci ve öğretmen ilerlemelerini istatistikler** ile görselleştirme (katılım oranı, tamamlanma %, performans grafikleri).
- Yoklamaları, notları ve materyalleri **aynı yerde** tutma.
- Ekstra özellikler: Bildirimler, raporlama, rol bazlı erişim (öğretmen/öğrenci/admin).

Bu platform, eğitim süreçlerini dijitalleştirerek zaman tasarrufu sağlar ve veri odaklı kararlar alınmasına yardımcı olur.

## ✨ Özellikler
| Özellik | Açıklama |
|---------|----------|
| **👥 Kullanıcı Yönetimi** | Öğretmen/Öğrenci kayıt, login, rol bazlı dashboard. |
| **📋 Yoklama Sistemi** | QR kod veya manuel yoklama, gerçek zamanlı katılım takibi. |
| **📚 Materyal Yönetimi** | Dosya yükleme (PDF, ZIP, Video), kategorize etme, indirme linkleri. |
| **📊 İstatistikler** | Grafikler (Chart.js), katılım raporları, ilerleme takibi. |
| **🔔 Bildirimler** | Email/SMS entegrasyonu, yeni materyal/yoklama uyarıları. |
| **🔒 Güvenlik** | PDO ile SQL injection koruması, session yönetimi, şifre hashleme. |
| **📱 Responsive** | Mobil uyumlu tasarım (Bootstrap). |
| **⚙️ Admin Paneli** | Kullanıcı/ Ders yönetimi, yedekleme. |

## 🛠 Teknoloji Yığını
```
Backend: PHP 8.1+ (OOP, PDO)
Database: MySQL 8.0+ (InnoDB)
Frontend: HTML5, CSS3, Bootstrap 5, JavaScript (Vanilla + jQuery)
Charts: Chart.js
Diğer: Composer (opsiyonel), PHPMailer (email), QR Code Generator
Deployment: Apache/Nginx + XAMPP/WAMP/MAMP (geliştirme)
```

## 🚀 Kurulum Rehberi
### Gereksinimler
- PHP 8.1+ (PDO, mysqli enabled)
- MySQL 8.0+
- Apache/Nginx web server
- Composer (opsiyonel)

### Adım Adım Kurulum
1. **Depoyu Klonlayın:**
   ```bash
   git clone https://github.com/ibosta/icathane-web.git
   cd icathane-web
   ```

2. **Web Sunucusuna Yerleştirin:**
   - XAMPP/WAMP: `htdocs/` klasörüne kopyalayın.
   - URL: `http://localhost/icathane-web/`

3. **Veritabanı Oluşturun:**
   - MySQL'e bağlanın (phpMyAdmin veya CLI).
   - Yeni DB: `icathane_db`
   - `database.sql` dosyasını import edin.

4. **Config Dosyasını Düzenleyin:**
   `config/database.php`:
   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Şifrenizi girin
   define('DB_NAME', 'icathane_db');
   ?>
   ```

5. **Uygulamayı Başlatın:**
   - Tarayıcıda `http://localhost/icathane-web/` açın.
   - Varsayılan login: Admin → `admin@itaskira.com` / `admin123`

6. **Composer (Opsiyonel):**
   ```bash
   composer install
   ```

## 📁 Dosya Yapısı
```
icathane-web/
├── assets/              # CSS, JS, Images, Fonts
│   ├── css/
│   ├── js/
│   └── uploads/         # Materyaller buraya yüklenir
├── config/              # DB config, constants
├── includes/            # Header, Footer, Functions
├── admin/               # Admin paneli sayfaları
├── teacher/             # Öğretmen dashboard
├── student/             # Öğrenci dashboard
├── ajax/                # AJAX handlers (yoklama, istatistik)
├── database.sql         # Veritabanı şeması ve sample data
├── index.php            # Ana sayfa
├── login.php
├── logout.php
└── README.md
```

**Live:** [tugva.itaskira.com](tugva.itaskira.com)

## 💻 Kullanım Örnekleri
### Öğretmen Yoklama Alma
1. Teacher dashboard → Ders seç → "Yoklama Başlat".

### İstatistik Görüntüleme
```php
// ajax/get_stats.php örneği
$stats = $pdo->query("SELECT COUNT(*) as attendance FROM attendances WHERE lesson_id = ?");
echo json_encode($stats);
```

## 🤝 Katkıda Bulunma
1. Fork edin.
2. `git checkout -b feature/yeni-ozellik`
3. Commit: `git commit -m "feat: yoklama QR entegrasyonu"`
4. Push & PR açın.

**Yönergeler:** PHPStan kullanın, kodunuzu test edin.

## 📄 Lisans
[MIT License](LICENSE) - Ticari kullanım serbest.

## 👨‍💻 İletişim
**İbrahim Taşkıran** (@ibosta)  
[![GitHub](https://img.shields.io/badge/GitHub-ibosta-black?style=flat&logo=github)](https://github.com/ibosta)  
[![LinkedIn](https://img.shields.io/badge/LinkedIn-Connect-blue?style=flat&logo=linkedin)](https://linkedin.com/in/itaskira)  
Email: [ibosta@example.com](mailto:info@itaskira.com)

---

⭐ **Yıldızlayın ve fork'layın!** Eğitim teknolojilerine katkıda bulunun. Sorular için [Issues](https://github.com/ibosta/icathane-web/issues/new) açın.
```
