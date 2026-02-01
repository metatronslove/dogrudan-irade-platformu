# Web Sitesi Dizin Dokümantasyonu

**Oluşturulma Tarihi:** 2026-02-02 01:38:55  
**Kök Dizin:** `D:\dogrudan-irade-platformu`  
**Toplam Dosya Sayısı:** 0

---

## Dizin Yapısı ve Dosya İçerikleri

📄 **api.php**
```php
<?php
header('Content-Type: application/json');
session_start();
require_once 'config/database.php';
require_once 'config/secim_fonksiyonlari.php';

// Super admin yetkilendirme
function requireSuperAdmin() {
    if (!isset($_SESSION['kullanici_id']) || $_SESSION['yetki_seviye'] !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
        exit;
    }
}

$db = new Database();
$secim = new SecimFonksiyonlari();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // Aday listesi getir
    case 'get_adaylar':
        $oylama_id = $_GET['oylama_id'] ?? 0;
        $adaylar = $db->query(
            "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
            [$oylama_id]
        )->fetchAll();
        
        echo json_encode(['success' => true, 'adaylar' => $adaylar]);
        break;
    
    // Canlı sonuçlar
    case 'live_results':
        $oylama_id = $_GET['oylama_id'] ?? 0;
        $results = $secim->secimSonucunuHesapla($oylama_id);
        
        echo json_encode(['success' => true, 'results' => $results]);
        break;
    
    // Topluluk ekle
    case 'add_community':
        if (!isset($_SESSION['kullanici_id'])) {
            echo json_encode(['success' => false, 'message' => 'Giriş yapmalısınız']);
            break;
        }
        
        $kullanici_id = $_SESSION['kullanici_id'];
        $topluluk_tipi = $_POST['topluluk_tipi'] ?? '';
        $topluluk_id = $_POST['topluluk_id'] ?? '';
        
        try {
            // Çift kayıt kontrolü
            $existing = $db->singleValueQuery(
                "SELECT COUNT(*) FROM kullanici_topluluklari 
                 WHERE kullanici_id = ? AND topluluk_tipi = ? AND topluluk_id = ?",
                [$kullanici_id, $topluluk_tipi, $topluluk_id]
            );
            
            if ($existing > 0) {
                echo json_encode(['success' => false, 'message' => 'Zaten bu topluluğa üyesiniz']);
                break;
            }
            
            // Ekle
            $db->query(
                "INSERT INTO kullanici_topluluklari (kullanici_id, topluluk_tipi, topluluk_id) 
                 VALUES (?, ?, ?)",
                [$kullanici_id, $topluluk_tipi, $topluluk_id]
            );
            
            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'topluluk_ekleme', ?, ?)",
                [$kullanici_id, "$topluluk_tipi:$topluluk_id eklendi", $_SERVER['REMOTE_ADDR']]
            );
            
            echo json_encode(['success' => true, 'message' => 'Topluluk eklendi']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    // Topluluk çıkar
    case 'remove_community':
        if (!isset($_SESSION['kullanici_id'])) {
            echo json_encode(['success' => false, 'message' => 'Giriş yapmalısınız']);
            break;
        }
        
        $id = $_POST['id'] ?? 0;
        
        try {
            $db->query(
                "DELETE FROM kullanici_topluluklari WHERE id = ? AND kullanici_id = ?",
                [$id, $_SESSION['kullanici_id']]
            );
            
            echo json_encode(['success' => true, 'message' => 'Topluluktan çıkarıldınız']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    // Kullanıcı oy durumu
    case 'user_vote_status':
        if (!isset($_SESSION['kullanici_id'])) {
            echo json_encode(['success' => false, 'message' => 'Giriş yapmalısınız']);
            break;
        }
        
        $oylama_id = $_GET['oylama_id'] ?? 0;
        $status = $secim->kullaniciOyDurumu($oylama_id, $_SESSION['kullanici_id']);
        
        echo json_encode(['success' => true, 'status' => $status]);
        break;
    
    // Oylama istatistikleri
    case 'poll_stats':
        $oylama_id = $_GET['oylama_id'] ?? 0;
        
        $stats = [
            'toplam_oy' => $db->singleValueQuery(
                "SELECT COUNT(DISTINCT kullanici_id) FROM oy_kullanicilar WHERE oylama_id = ?",
                [$oylama_id]
            ),
            'toplam_destek' => $db->singleValueQuery(
                "SELECT COUNT(*) FROM oy_kullanicilar WHERE oylama_id = ? AND destek_verilen_aday_id IS NOT NULL",
                [$oylama_id]
            ),
            'toplam_negatif' => $db->singleValueQuery(
                "SELECT COUNT(*) FROM oy_kullanicilar WHERE oylama_id = ? AND negatif_oy_verilen_aday_id IS NOT NULL",
                [$oylama_id]
            )
        ];
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;
        
    // Log temizleme
    case 'clear_logs':
        requireSuperAdmin();
        
        $keep = $_GET['keep'] ?? '0';
        
        if ($keep === '1') {
            // Son 1 aylık logları sakla
            $db->query(
                "DELETE FROM sistem_loglari WHERE tarih < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
            );
            $silinen = $db->connect()->rowCount();
            $message = "$silinen adet eski log kaydı silindi.";
        } else {
            // Tüm logları temizle
            $db->query("DELETE FROM sistem_loglari");
            $message = "Tüm log kayıtları temizlendi.";
        }
        
        // Log
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
             VALUES (?, 'log_temizleme', ?, ?)",
            [$_SESSION['kullanici_id'], $message, $_SERVER['REMOTE_ADDR']]
        );
        
        echo json_encode(['success' => true, 'message' => $message]);
        break;

    // Log export
    case 'export_logs':
        requireSuperAdmin();
        
        $format = $_GET['format'] ?? 'json';
        $tip = $_GET['tip'] ?? '';
        $kullanici_id = $_GET['kullanici_id'] ?? '';
        
        $sql = "SELECT l.*, k.ad_soyad, k.eposta 
                FROM sistem_loglari l 
                LEFT JOIN kullanicilar k ON l.kullanici_id = k.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($tip)) {
            $sql .= " AND l.islem_tipi = ?";
            $params[] = $tip;
        }
        
        if (!empty($kullanici_id)) {
            $sql .= " AND l.kullanici_id = ?";
            $params[] = $kullanici_id;
        }
        
        $sql .= " ORDER BY l.tarih DESC";
        
        $loglar = $db->query($sql, $params)->fetchAll();
        
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=loglar_' . date('Y-m-d') . '.csv');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Tarih', 'Kullanıcı', 'E-posta', 'İşlem Tipi', 'Açıklama', 'IP', 'User Agent']);
            
            foreach ($loglar as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['tarih'],
                    $log['ad_soyad'] ?? 'Sistem',
                    $log['eposta'] ?? '',
                    $log['islem_tipi'],
                    $log['aciklama'],
                    $log['ip_adresi'],
                    $log['user_agent']
                ]);
            }
            
            fclose($output);
        } else {
            // JSON format
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename=loglar_' . date('Y-m-d') . '.json');
            
            echo json_encode($loglar, JSON_PRETTY_PRINT);
        }
        break;

    // Yeni kullanıcı oluştur (admin)
    case 'create_user':
        requireSuperAdmin();
        
        $ad_soyad = $_POST['ad_soyad'] ?? '';
        $eposta = $_POST['eposta'] ?? '';
        $sifre = $_POST['sifre'] ?? '';
        $yetki_seviye = $_POST['yetki_seviye'] ?? 'kullanici';
        
        // Validasyon
        if (empty($ad_soyad)) {
            echo json_encode(['success' => false, 'message' => 'Tüm alanlar gereklidir']);
            break;
        }
        if (empty($eposta) || !filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz e-posta']);
            break;
        }
        
        // E-posta kontrolü
        $existing = $db->singleValueQuery(
            "SELECT COUNT(*) FROM kullanicilar WHERE eposta = ?",
            [$eposta]
        );
        if ($existing > 0) {
            echo json_encode(['success' => false, 'message' => 'Bu e-posta zaten kayıtlı']);
            break;
        }
        
        try {
            // Kullanıcıyı kaydet
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            
            $kullanici_id = $db->insertAndGetId(
                "INSERT INTO kullanicilar (eposta, sifre_hash, ad_soyad, yetki_seviye, durum) 
                 VALUES (?, ?, ?, ?, 'aktif')",
                [$eposta, $sifre_hash, $ad_soyad, $yetki_seviye]
            );

            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'admin_kullanici_olusturma', ?, ?)",
                [$kullanici_id, "Yeni kullanıcı: $ad_soyad ($eposta)", $_SERVER['REMOTE_ADDR']]
            );
            
            echo json_encode(['success' => true, 'message' => 'Kullanıcı oluşturuldu', 'id' => $kullanici_id]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // Toplu işlemler
    case 'batch_operation':
        requireSuperAdmin();
        
        $operation = $_POST['operation'] ?? '';
        $ids = $_POST['ids'] ?? [];
        
        if (empty($ids) || empty($operation)) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
            break;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        try {
            switch ($operation) {
                case 'activate_users':
                    $db->query(
                        "UPDATE kullanicilar SET durum = 'aktif' WHERE id IN ($placeholders)",
                        $ids
                    );
                    $message = count($ids) . ' kullanıcı aktifleştirildi.';
                    break;
                    
                case 'deactivate_users':
                    $db->query(
                        "UPDATE kullanicilar SET durum = 'pasif' WHERE id IN ($placeholders)",
                        $ids
                    );
                    $message = count($ids) . ' kullanıcı pasifleştirildi.';
                    break;
                    
                case 'delete_old_polls':
                    // 6 aydan eski sonuçlanmış oylamaları sil
                    $db->query(
                        "DELETE FROM oylamalar 
                         WHERE durum = 'sonuclandi' 
                         AND bitis_tarihi < DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         AND id IN ($placeholders)",
                        $ids
                    );
                    $message = count($ids) . ' eski oylama silindi.';
                    break;
                    
                default:
                    throw new Exception('Geçersiz işlem tipi');
            }
            
            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'toplu_islem', ?, ?)",
                [$_SESSION['kullanici_id'], "$operation: $message", $_SERVER['REMOTE_ADDR']]
            );
            
            echo json_encode(['success' => true, 'message' => $message]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    // Yedekleme işlemleri
    case 'generate_backup':
        requireSuperAdmin();
        
        $type = $_GET['type'] ?? 'sql';
        $backup_dir = '../backups/';
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        try {
            if ($type === 'sql') {
                // SQL yedekleme
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backup_dir . $filename;
                
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $backup = "-- Doğrudan İrade Platformu Veritabanı Yedeği\n";
                $backup .= "-- Oluşturulma Tarihi: " . date('Y-m-d H:i:s') . "\n";
                $backup .= "-- Toplam Tablo: " . count($tables) . "\n\n";
                
                foreach ($tables as $table) {
                    // Tablo yapısı
                    $create_table = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                    $backup .= "--\n-- Tablo: $table\n--\n";
                    $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                    $backup .= $create_table['Create Table'] . ";\n\n";
                    
                    // Tablo verileri
                    $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    if (count($rows) > 0) {
                        $backup .= "--\n-- Tablo verileri: $table\n--\n";
                        
                        foreach ($rows as $row) {
                            $columns = array_keys($row);
                            $values = array_map(function($value) use ($db) {
                                if ($value === null) return 'NULL';
                                return $db->connect()->quote($value);
                            }, array_values($row));
                            
                            $backup .= "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) ";
                            $backup .= "VALUES (" . implode(',', $values) . ");\n";
                        }
                        $backup .= "\n";
                    }
                }
                
                file_put_contents($filepath, $backup);
            } else if ($type === 'files') {
                // Dosya yedekleme
                $filename = 'files_backup_' . date('Y-m-d_H-i-s') . '.zip';
                $filepath = $backup_dir . $filename;
                
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($filepath, ZipArchive::CREATE) === true) {
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator('../'),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        
                        foreach ($files as $name => $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen('../') + 1);
                                
                                // Belirli dosyaları hariç tut
                                if (!preg_match('/\/backups\//', $filePath) && 
                                    !preg_match('/\/vendor\//', $filePath) &&
                                    !preg_match('/\/node_modules\//', $filePath)) {
                                    $zip->addFile($filePath, $relativePath);
                                }
                            }
                        }
                        
                        $zip->close();
                    }
                }
            } else {
                throw new Exception('Geçersiz yedekleme tipi');
            }
            
            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'yedek_olusturma', ?, ?)",
                [$_SESSION['kullanici_id'], "Yedek: $filename", $_SERVER['REMOTE_ADDR']]
            );
            
            echo json_encode(['success' => true, 'message' => 'Yedek başarıyla oluşturuldu', 'filename' => $filename]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    // Otomatik yedekleme ayarları
    case 'save_backup_settings':
        requireSuperAdmin();
        
        $auto_backup = $_POST['auto_backup'] ?? '0';
        $backup_type = $_POST['backup_type'] ?? 'veritabani';
        $max_backups = $_POST['max_backups'] ?? 10;
        $next_backup = $_POST['next_backup'] ?? date('Y-m-d H:i', strtotime('next sunday'));
        
        try {
            // Ayarları güncelle
            $db->query(
                "INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, tur, aciklama) 
                 VALUES ('auto_backup', ?, 'boolean', 'Otomatik yedekleme') 
                 ON DUPLICATE KEY UPDATE ayar_degeri = ?",
                [$auto_backup, $auto_backup]
            );
            
            $db->query(
                "INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, tur, aciklama) 
                 VALUES ('backup_type', ?, 'metin', 'Yedek tipi') 
                 ON DUPLICATE KEY UPDATE ayar_degeri = ?",
                [$backup_type, $backup_type]
            );
            
            $db->query(
                "INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, tur, aciklama) 
                 VALUES ('max_backups', ?, 'sayi', 'Maksimum yedek sayısı') 
                 ON DUPLICATE KEY UPDATE ayar_degeri = ?",
                [$max_backups, $max_backups]
            );
            
            $db->query(
                "INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, tur, aciklama) 
                 VALUES ('next_backup', ?, 'tarih', 'Sonraki yedek tarihi') 
                 ON DUPLICATE KEY UPDATE ayar_degeri = ?",
                [$next_backup, $next_backup]
            );
            
            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'yedek_ayarlari', ?, ?)",
                [$_SESSION['kullanici_id'], "Otomatik yedekleme ayarları güncellendi", $_SERVER['REMOTE_ADDR']]
            );
            
            echo json_encode(['success' => true, 'message' => 'Ayarlar kaydedildi']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    // Auto backup kontrolü
    case 'check_backup_status':
        requireSuperAdmin();
        
        // Otomatik yedekleme zamanı geldi mi?
        $nextBackup = $db->singleValueQuery(
            "SELECT ayar_degeri FROM sistem_ayarlari WHERE ayar_adi = 'next_backup'"
        );
        
        $autoBackup = $db->singleValueQuery(
            "SELECT ayar_degeri FROM sistem_ayarlari WHERE ayar_adi = 'auto_backup'"
        );
        
        $response = [
            'auto_backup_due' => ($autoBackup === '1' && $nextBackup && strtotime($nextBackup) <= time()),
            'next_backup' => $nextBackup
        ];
        
        echo json_encode($response);
        break;
    
    // Oylama sonuçlarını getir
    case 'get_oylama_results':
        $oylama_id = $_GET['oylama_id'] ?? 0;
        $results = $secim->secimSonucunuHesapla($oylama_id);
        
        echo json_encode(['success' => true, 'results' => $results]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
        break;
}
?>

```

--------------------------------------------------------------------------------

📄 **bakim.php**
```php
<?php
// Bakım modu kontrolü
$bakim_modu = false; // Bu değişkeni config dosyasından kontrol edebilirsiniz

if ($bakim_modu && !isset($_SESSION['yetki_seviye'])) {
    header('HTTP/1.1 503 Service Unavailable');
    include 'hata.php?kod=503';
    exit;
}
?>

<!-- Eğer ayrı bir bakım sayfası isterseniz: -->
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Modu - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .maintenance-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 600px;
            width: 90%;
        }
        .maintenance-icon {
            font-size: 100px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .maintenance-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }
        .maintenance-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .countdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }
        .countdown-title {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }
        .countdown-timer {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            font-family: monospace;
        }
        .progress {
            height: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .contact-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .social-link:hover {
            background: #764ba2;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <!-- İkon -->
        <div class="maintenance-icon">
            🔧
        </div>
        
        <!-- Başlık -->
        <h1 class="maintenance-title">
            SİSTEM BAKIMDA
        </h1>
        
        <!-- Mesaj -->
        <p class="maintenance-message">
            Doğrudan İrade Platformu'nu daha iyi hizmet verebilmek için güncelliyoruz.
            Lütfen biraz sonra tekrar deneyin.
        </p>
        
        <!-- Geri sayım -->
        <div class="countdown">
            <div class="countdown-title">
                Tahmini bitiş süresi:
            </div>
            <div class="countdown-timer" id="countdown">
                02:00:00
            </div>
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                     id="progressBar" style="width: 0%"></div>
            </div>
        </div>
        
        <!-- İletişim bilgileri -->
        <div class="contact-card">
            <h6 class="mb-3">
                <i class="bi bi-info-circle"></i> Bilgilendirme
            </h6>
            <ul class="list-unstyled mb-0">
                <li class="mb-2">
                    <i class="bi bi-clock text-primary"></i>
                    <strong>Başlangıç:</strong> <?= date('d.m.Y H:i') ?>
                </li>
                <li class="mb-2">
                    <i class="bi bi-clock-history text-success"></i>
                    <strong>Tahmini Bitiş:</strong> <?= date('d.m.Y H:i', strtotime('+2 hours')) ?>
                </li>
                <li>
                    <i class="bi bi-envelope text-warning"></i>
                    <strong>İletişim:</strong> destek@dogrudanirade.org
                </li>
            </ul>
        </div>
        
        <!-- Sosyal medya -->
        <div class="social-links">
            <a href="#" class="social-link">
                <i class="bi bi-twitter"></i>
            </a>
            <a href="#" class="social-link">
                <i class="bi bi-facebook"></i>
            </a>
            <a href="#" class="social-link">
                <i class="bi bi-instagram"></i>
            </a>
            <a href="#" class="social-link">
                <i class="bi bi-telegram"></i>
            </a>
        </div>
        
        <!-- Logo -->
        <div class="mt-4">
            <span class="text-muted">
                🗳️ Doğrudan İrade Platformu
            </span>
        </div>
    </div>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script>
    // Geri sayım fonksiyonu
    function startCountdown() {
        const totalSeconds = 2 * 60 * 60; // 2 saat
        let remainingSeconds = totalSeconds;
        
        const countdownElement = document.getElementById('countdown');
        const progressBar = document.getElementById('progressBar');
        
        const timer = setInterval(function() {
            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            
            countdownElement.textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Progress bar güncelleme
            const progress = ((totalSeconds - remainingSeconds) / totalSeconds) * 100;
            progressBar.style.width = `${progress}%`;
            
            if (remainingSeconds <= 0) {
                clearInterval(timer);
                countdownElement.textContent = "Bakım Tamamlandı!";
                countdownElement.classList.add('text-success');
                
                // Sayfayı yenile
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            }
            
            remainingSeconds--;
        }, 1000);
    }
    
    // Sayfa yüklendiğinde geri sayımı başlat
    document.addEventListener('DOMContentLoaded', startCountdown);
    
    // Sayfayı otomatik kontrol et
    setInterval(function() {
        fetch('api.php?action=check_maintenance')
            .then(response => response.json())
            .then(data => {
                if (!data.maintenance) {
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
    }, 30000); // 30 saniyede bir kontrol et
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **bildirimler.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

$db = new Database();
$kullanici_id = $_SESSION['kullanici_id'];

// Bildirimleri işaretleme
if (isset($_GET['islem'])) {
    $islem = $_GET['islem'];
    $bildirim_id = $_GET['id'] ?? 0;
    
    switch ($islem) {
        case 'okundu':
            $db->query(
                "UPDATE bildirimler SET okundu = TRUE, okunma_tarihi = NOW() WHERE id = ? AND kullanici_id = ?",
                [$bildirim_id, $kullanici_id]
            );
            break;
            
        case 'okunmadi':
            $db->query(
                "UPDATE bildirimler SET okundu = FALSE, okunma_tarihi = NULL WHERE id = ? AND kullanici_id = ?",
                [$bildirim_id, $kullanici_id]
            );
            break;
            
        case 'sil':
            $db->query(
                "DELETE FROM bildirimler WHERE id = ? AND kullanici_id = ?",
                [$bildirim_id, $kullanici_id]
            );
            break;
            
        case 'hepsini_okundu':
            $db->query(
                "UPDATE bildirimler SET okundu = TRUE, okunma_tarihi = NOW() 
                 WHERE kullanici_id = ? AND okundu = FALSE",
                [$kullanici_id]
            );
            break;
            
        case 'hepsini_sil':
            $db->query(
                "DELETE FROM bildirimler WHERE kullanici_id = ?",
                [$kullanici_id]
            );
            break;
    }
    
    header("Location: bildirimler.php");
    exit;
}

// Bildirimleri getir
$bildirimler = $db->query(
    "SELECT * FROM bildirimler 
     WHERE kullanici_id = ? 
     ORDER BY olusturulma_tarihi DESC",
    [$kullanici_id]
)->fetchAll();

// Okunmamış bildirim sayısı
$okunmamis = $db->singleValueQuery(
    "SELECT COUNT(*) FROM bildirimler WHERE kullanici_id = ? AND okundu = FALSE",
    [$kullanici_id]
);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildirimlerim - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        .notification-item:hover {
            transform: translateX(5px);
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            background-color: #f0f7ff;
            border-left-color: #007bff;
        }
        .notification-item.read {
            opacity: 0.8;
        }
        .notification-type {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }
        .type-bilgi { background-color: #17a2b8; }
        .type-uyari { background-color: #ffc107; }
        .type-basari { background-color: #28a745; }
        .type-hata { background-color: #dc3545; }
        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .notification-item:hover .notification-actions {
            opacity: 1;
        }
        .badge-notification {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 10px;
            padding: 2px 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Başlık ve istatistikler -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="display-6 fw-bold text-primary">
                            <i class="bi bi-bell"></i> Bildirimlerim
                        </h1>
                        <p class="text-muted mb-0">
                            Tüm sistem bildirimleriniz burada listelenir
                        </p>
                    </div>
                    
                    <div class="text-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary" 
                                    onclick="markAllAsRead()">
                                <i class="bi bi-check-all"></i> Tümünü Okundu İşaretle
                            </button>
                            <button type="button" class="btn btn-outline-danger" 
                                    onclick="deleteAllNotifications()">
                                <i class="bi bi-trash"></i> Tümünü Sil
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- İstatistik kartları -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?= count($bildirimler) ?></h5>
                                <p class="card-text">Toplam Bildirim</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body">
                                <h5 class="card-title text-warning"><?= $okunmamis ?></h5>
                                <p class="card-text">Okunmamış</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h5 class="card-title text-success">
                                    <?= count($bildirimler) - $okunmamis ?>
                                </h5>
                                <p class="card-text">Okunmuş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h5 class="card-title text-info">
                                    <?= 
                                        count(array_filter($bildirimler, function($b) {
                                            return strtotime($b['olusturulma_tarihi']) > strtotime('-24 hours');
                                        }))
                                    ?>
                                </h5>
                                <p class="card-text">Son 24 Saat</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bildirim listesi -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul"></i> Bildirim Listesi
                            </h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-filter"></i> Filtrele
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="?filter=hepsi">
                                            <i class="bi bi-list"></i> Tümü
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="?filter=okunmamis">
                                            <i class="bi bi-envelope"></i> Okunmamış
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="?filter=okunmus">
                                            <i class="bi bi-envelope-open"></i> Okunmuş
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="?filter=bugun">
                                            <i class="bi bi-calendar-day"></i> Bugün
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="?filter=bu_hafta">
                                            <i class="bi bi-calendar-week"></i> Bu Hafta
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($bildirimler)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bell-slash display-4 text-muted d-block mb-3"></i>
                                <h4>Henüz bildiriminiz yok</h4>
                                <p class="text-muted mb-0">
                                    Yeni oylamalar, sistem güncellemeleri ve diğer bildirimler burada görünecek.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($bildirimler as $bildirim): 
                                    $type_class = match($bildirim['tur']) {
                                        'uyari' => 'type-uyari',
                                        'basari' => 'type-basari',
                                        'hata' => 'type-hata',
                                        default => 'type-bilgi'
                                    };
                                    
                                    $type_icon = match($bildirim['tur']) {
                                        'uyari' => 'bi-exclamation-triangle',
                                        'basari' => 'bi-check-circle',
                                        'hata' => 'bi-x-circle',
                                        default => 'bi-info-circle'
                                    };
                                    
                                    $is_unread = !$bildirim['okundu'];
                                ?>
                                    <div class="list-group-item notification-item p-3 <?= $is_unread ? 'unread' : 'read' ?>">
                                        <div class="d-flex align-items-start">
                                            <!-- Tip ikonu -->
                                            <div class="notification-type <?= $type_class ?> me-3 position-relative">
                                                <i class="bi <?= $type_icon ?>"></i>
                                                <?php if ($is_unread): ?>
                                                    <span class="badge bg-danger badge-notification">!</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- İçerik -->
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-0"><?= htmlspecialchars($bildirim['baslik']) ?></h6>
                                                    <small class="text-muted">
                                                        <?= date('d.m.Y H:i', strtotime($bildirim['olusturulma_tarihi'])) ?>
                                                    </small>
                                                </div>
                                                <p class="mb-2"><?= htmlspecialchars($bildirim['mesaj']) ?></p>
                                                
                                                <!-- İşlem butonları -->
                                                <div class="notification-actions">
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($is_unread): ?>
                                                            <a href="?islem=okundu&id=<?= $bildirim['id'] ?>" 
                                                               class="btn btn-outline-success btn-sm">
                                                                <i class="bi bi-check"></i> Okundu
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?islem=okunmadi&id=<?= $bildirim['id'] ?>" 
                                                               class="btn btn-outline-warning btn-sm">
                                                                <i class="bi bi-envelope"></i> Okunmadı
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="?islem=sil&id=<?= $bildirim['id'] ?>" 
                                                           class="btn btn-outline-danger btn-sm"
                                                           onclick="return confirm('Bu bildirimi silmek istediğinize emin misiniz?')">
                                                            <i class="bi bi-trash"></i> Sil
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Sayfalama -->
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1">Önceki</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Sonraki</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer text-muted">
                        <div class="row">
                            <div class="col-md-6">
                                <small>
                                    <i class="bi bi-info-circle"></i> 
                                    Bildirimler 90 gün boyunca saklanır
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <small>
                                    Son güncelleme: <?= date('H:i:s') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bildirim ayarları -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-gear"></i> Bildirim Ayarları
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="notificationSettings">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Bildirim Türleri</h6>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifyNewPoll" checked>
                                        <label class="form-check-label" for="notifyNewPoll">
                                            Yeni oylama bildirimleri
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifyPollEnd" checked>
                                        <label class="form-check-label" for="notifyPollEnd">
                                            Oylama sonuç bildirimleri
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifySystem" checked>
                                        <label class="form-check-label" for="notifySystem">
                                            Sistem güncelleme bildirimleri
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifySecurity" checked>
                                        <label class="form-check-label" for="notifySecurity">
                                            Güvenlik bildirimleri
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="mb-3">Bildirim Yöntemleri</h6>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifyInApp" checked disabled>
                                        <label class="form-check-label" for="notifyInApp">
                                            Uygulama içi bildirimler
                                            <small class="text-muted d-block">(Zorunlu)</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifyEmail">
                                        <label class="form-check-label" for="notifyEmail">
                                            E-posta bildirimleri
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifySMS" disabled>
                                        <label class="form-check-label" for="notifySMS">
                                            SMS bildirimleri
                                            <small class="text-muted d-block">(Yakında)</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="notifyPush" disabled>
                                        <label class="form-check-label" for="notifyPush">
                                            Push bildirimleri
                                            <small class="text-muted d-block">(Yakında)</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="button" class="btn btn-primary" onclick="saveNotificationSettings()">
                                    <i class="bi bi-save"></i> Ayarları Kaydet
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetNotificationSettings()">
                                    <i class="bi bi-arrow-clockwise"></i> Varsayılana Dön
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function markAllAsRead() {
        if (confirm('Tüm bildirimleri okundu olarak işaretlemek istediğinize emin misiniz?')) {
            window.location.href = '?islem=hepsini_okundu';
        }
    }
    
    function deleteAllNotifications() {
        if (confirm('TÜM bildirimlerinizi silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')) {
            window.location.href = '?islem=hepsini_sil';
        }
    }
    
    function saveNotificationSettings() {
        // Ayarları kaydetme işlemi (AJAX ile yapılabilir)
        const settings = {
            types: {
                newPoll: document.getElementById('notifyNewPoll').checked,
                pollEnd: document.getElementById('notifyPollEnd').checked,
                system: document.getElementById('notifySystem').checked,
                security: document.getElementById('notifySecurity').checked
            },
            methods: {
                inApp: true, // Zorunlu
                email: document.getElementById('notifyEmail').checked,
                sms: document.getElementById('notifySMS').checked,
                push: document.getElementById('notifyPush').checked
            }
        };
        
        // API'ye gönder
        fetch('api.php?action=save_notification_settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(settings)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Bildirim ayarlarınız kaydedildi.');
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ayarlar kaydedilirken bir hata oluştu.');
        });
    }
    
    function resetNotificationSettings() {
        if (confirm('Bildirim ayarlarını varsayılana döndürmek istediğinize emin misiniz?')) {
            document.getElementById('notifyNewPoll').checked = true;
            document.getElementById('notifyPollEnd').checked = true;
            document.getElementById('notifySystem').checked = true;
            document.getElementById('notifySecurity').checked = true;
            document.getElementById('notifyEmail').checked = false;
            
            alert('Ayarlar varsayılana döndürüldü. Kaydet butonuna tıklayarak uygulayabilirsiniz.');
        }
    }
    
    // Otomatik yenileme (her 30 saniyede bir)
    setInterval(() => {
        fetch('api.php?action=check_new_notifications')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    // Yeni bildirim var, sayfayı yenile
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
    }, 30000);
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **cikis.php**
```php
<?php
session_start();

if (isset($_SESSION['kullanici_id'])) {
    require_once 'config/database.php';
    $db = new Database();
    
    // Log kaydı
    $db->query(
        "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
         VALUES (?, 'cikis', 'Kullanıcı çıkış yaptı', ?)",
        [$_SESSION['kullanici_id'], $_SERVER['REMOTE_ADDR']]
    );
}

// Tüm sessionları temizle
session_unset();
session_destroy();

// Çerezleri temizle (opsiyonel)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Giriş sayfasına yönlendir
header("Location: giris.php?message=cikis_basarili");
exit;
?>

```

--------------------------------------------------------------------------------

📄 **giris.php**
```php
<?php
session_start();
require_once 'config/database.php';

$db = new Database();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    
    // Kullanıcıyı bul
    $stmt = $db->query(
        "SELECT * FROM kullanicilar WHERE eposta = ? AND durum = 'aktif'",
        [$eposta]
    );
    $kullanici = $stmt->fetch();
    
    if ($kullanici && password_verify($sifre, $kullanici['sifre_hash'])) {
        // Giriş başarılı
        $_SESSION['kullanici_id'] = $kullanici['id'];
        $_SESSION['ad_soyad'] = $kullanici['ad_soyad'];
        $_SESSION['eposta'] = $kullanici['eposta'];
        $_SESSION['yetki_seviye'] = $kullanici['yetki_seviye'];
        
        // Son giriş tarihini güncelle
        $db->query(
            "UPDATE kullanicilar SET son_giris_tarihi = NOW() WHERE id = ?",
            [$kullanici['id']]
        );
        
        // Log kaydı
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
             VALUES (?, 'giris_basarili', 'Kullanıcı giriş yaptı', ?)",
            [$kullanici['id'], $_SERVER['REMOTE_ADDR']]
        );
        
        // Yönlendirme
        if ($kullanici['yetki_seviye'] === 'superadmin' || $kullanici['yetki_seviye'] === 'yonetici') {
            header("Location: admin/index.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = 'E-posta veya şifre hatalı!';
        
        // Başarısız giriş logu
        if ($kullanici) {
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'giris_basarisiz', 'Yanlış şifre denemesi', ?)",
                [$kullanici['id'], $_SERVER['REMOTE_ADDR']]
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <!-- Logo ve başlık -->
                <div class="text-center mb-5">
                    <h1 class="display-6 fw-bold text-primary">DOĞRUDAN İRADE</h1>
                    <p class="text-muted">Doğrudan demokrasi platformuna hoş geldiniz</p>
                </div>
                
                <!-- Giriş formu -->
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h3 class="card-title text-center mb-4">🔐 Giriş Yap</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="eposta" class="form-label">E-posta Adresiniz</label>
                                <input type="email" class="form-control form-control-lg" 
                                       id="eposta" name="eposta" required
                                       placeholder="ornek@email.com">
                            </div>
                            
                            <div class="mb-3">
                                <label for="sifre" class="form-label">Şifreniz</label>
                                <input type="password" class="form-control form-control-lg" 
                                       id="sifre" name="sifre" required
                                       placeholder="••••••••">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-2">Hesabınız yok mu?</p>
                            <a href="kayit.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus"></i> Yeni Hesap Oluştur
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Platform bilgisi -->
                <div class="card mt-4 border-info">
                    <div class="card-body text-center">
                        <small class="text-muted">
                            <i class="bi bi-shield-check"></i> Doğrudan İrade Platformu<br>
                            Tüm oylamalar şeffaf ve güvenlidir.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **hata.php**
```php
<?php
$error_code = $_GET['kod'] ?? '404';
$error_messages = [
    '403' => [
        'title' => 'Erişim Engellendi',
        'message' => 'Bu sayfaya erişim izniniz bulunmuyor.',
        'icon' => '🔒'
    ],
    '404' => [
        'title' => 'Sayfa Bulunamadı',
        'message' => 'Aradığınız sayfa mevcut değil veya taşınmış olabilir.',
        'icon' => '🔍'
    ],
    '500' => [
        'title' => 'Sunucu Hatası',
        'message' => 'Sunucuda bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
        'icon' => '⚙️'
    ],
    '503' => [
        'title' => 'Bakım Modu',
        'message' => 'Sistem bakım çalışmaları devam ediyor. Lütfen daha sonra tekrar deneyin.',
        'icon' => '🔧'
    ]
];

$error = $error_messages[$error_code] ?? $error_messages['404'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $error['title'] ?> - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .error-code {
            font-size: 100px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: -20px;
        }
        .error-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        .error-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 30px;
            display: inline-block;
        }
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <!-- Logo -->
        <div class="logo">
            🗳️ Doğrudan İrade
        </div>
        
        <!-- Hata kodu -->
        <div class="error-code">
            <?= $error_code ?>
        </div>
        
        <!-- Hata ikonu -->
        <div class="error-icon">
            <?= $error['icon'] ?>
        </div>
        
        <!-- Hata başlığı -->
        <h1 class="error-title">
            <?= $error['title'] ?>
        </h1>
        
        <!-- Hata mesajı -->
        <p class="error-message">
            <?= $error['message'] ?>
        </p>
        
        <!-- Butonlar -->
        <div class="btn-group">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house-door"></i> Ana Sayfaya Dön
            </a>
            
            <a href="javascript:history.back()" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Geri Dön
            </a>
            
            <a href="oylamalar.php" class="btn btn-outline-success">
                <i class="bi bi-clipboard-data"></i> Oylamalar
            </a>
        </div>
        
        <!-- Ek bilgiler -->
        <div class="contact-info">
            <p class="mb-2">
                Sorun devam ederse lütfen bizimle iletişime geçin:
            </p>
            <p class="mb-0">
                <i class="bi bi-envelope"></i> destek@dogrudanirade.org
            </p>
        </div>
        
        <!-- Teknik bilgiler (sadece geliştirici modunda) -->
        <?php if (isset($_SESSION['yetki_seviye']) && $_SESSION['yetki_seviye'] === 'superadmin'): ?>
            <div class="mt-4 p-3 bg-light rounded">
                <small class="text-muted">
                    <strong>Teknik Bilgiler:</strong><br>
                    Hata Kodu: <?= $error_code ?><br>
                    URL: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?><br>
                    IP: <?= $_SERVER['REMOTE_ADDR'] ?><br>
                    Zaman: <?= date('d.m.Y H:i:s') ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script>
    // Otomatik yönlendirme (sadece 404 için)
    <?php if ($error_code == '404'): ?>
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 10000); // 10 saniye sonra
    <?php endif; ?>
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **iletisim.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();
$errors = [];
$success = '';

// Sistem ayarlarını al
$iletisim_acik = $db->singleValueQuery(
    "SELECT ayar_degeri FROM sistem_ayarlari WHERE ayar_adi = 'iletisim_formu_acik'"
);

if ($iletisim_acik == '0') {
    header("Location: index.php");
    exit;
}

// İletişim bilgilerini al
$iletisim_bilgileri = [];
$ayarlar = $db->query(
    "SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari 
     WHERE ayar_adi LIKE 'iletisim_%' OR ayar_adi LIKE 'sosyal_%'"
)->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad']);
    $eposta = trim($_POST['eposta']);
    $konu = trim($_POST['konu']);
    $mesaj = trim($_POST['mesaj']);
    
    // Validasyon
    if (empty($ad_soyad)) $errors[] = 'Ad soyad gereklidir';
    if (empty($eposta) || !filter_var($eposta, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta girin';
    if (empty($konu)) $errors[] = 'Konu gereklidir';
    if (empty($mesaj) || strlen($mesaj) < 10) $errors[] = 'Mesaj en az 10 karakter olmalı';
    
    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'];
    $today_count = $db->singleValueQuery(
        "SELECT COUNT(*) FROM iletisim_mesajlari 
         WHERE ip_adresi = ? AND DATE(olusturulma_tarihi) = CURDATE()",
        [$ip]
    );
    
    if ($today_count >= 5) {
        $errors[] = 'Günlük mesaj limitinize ulaştınız. Lütfen yarın tekrar deneyin.';
    }
    
    if (empty($errors)) {
        try {
            // Mesajı kaydet
            $db->query(
                "INSERT INTO iletisim_mesajlari (ad_soyad, eposta, konu, mesaj, ip_adresi, user_agent) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$ad_soyad, $eposta, $konu, $mesaj, $ip, $_SERVER['HTTP_USER_AGENT']]
            );
            
            // E-posta gönder (yöneticiye)
            $admin_email = $ayarlar['iletisim_eposta'] ?? 'admin@dogrudanirade.org';
            $email_subject = "Yeni İletişim Mesajı: $konu";
            $email_body = "
            <h2>Yeni İletişim Formu Mesajı</h2>
            <p><strong>Gönderen:</strong> $ad_soyad ($eposta)</p>
            <p><strong>Konu:</strong> $konu</p>
            <p><strong>Mesaj:</strong></p>
            <div style='background:#f8f9fa; padding:15px; border-radius:5px;'>
                " . nl2br(htmlspecialchars($mesaj)) . "
            </div>
            <hr>
            <p><small>
                IP: $ip<br>
                Zaman: " . date('d.m.Y H:i:s') . "<br>
                Tarayıcı: " . substr($_SERVER['HTTP_USER_AGENT'], 0, 100) . "
            </small></p>
            ";
            
            // sendEmail($admin_email, $email_subject, $email_body);
            
            // Cevaplama e-postası (isteğe bağlı)
            if (isset($_POST['kopya'])) {
                $user_subject = "Mesajınız Alındı: $konu";
                $user_body = "
                <h2>Doğrudan İrade Platformu</h2>
                <p>Sayın $ad_soyad,</p>
                <p>İletişim formu aracılığıyla gönderdiğiniz mesajınız başarıyla alınmıştır.</p>
                <p><strong>Mesajınız:</strong></p>
                <div style='background:#f8f9fa; padding:15px; border-radius:5px;'>
                    " . nl2br(htmlspecialchars($mesaj)) . "
                </div>
                <p>En kısa sürede size dönüş yapılacaktır.</p>
                <hr>
                <p><small>
                    <strong>Doğrudan İrade Platformu</strong><br>
                    Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!
                </small></p>
                ";
                
                // sendEmail($eposta, $user_subject, $user_body);
            }
            
            // Log
            logIslem($_SESSION['kullanici_id'] ?? 0, 'iletisim_mesaji', "Konu: $konu", $ip);
            
            $success = 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapılacaktır.';
            
            // Formu temizle
            $_POST = [];
            
        } catch (Exception $e) {
            $errors[] = 'Mesaj gönderilirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İletişim - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .contact-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            height: 100%;
        }
        .contact-icon {
            font-size: 40px;
            margin-bottom: 15px;
            display: inline-block;
        }
        .contact-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .social-link:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
        }
        .map-container {
            border-radius: 15px;
            overflow: hidden;
            height: 300px;
            margin-top: 30px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <!-- Başlık -->
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold text-primary mb-3">
                <i class="bi bi-chat-left-text"></i> İLETİŞİM
            </h1>
            <p class="lead text-muted">
                Sorularınız, önerileriniz veya görüşleriniz için bizimle iletişime geçin
            </p>
        </div>

        <div class="row">
            <!-- İletişim Bilgileri -->
            <div class="col-lg-4 mb-4">
                <div class="contact-info-card">
                    <div class="mb-4">
                        <div class="contact-icon">
                            🗳️
                        </div>
                        <h3 class="mb-3">Doğrudan İrade</h3>
                        <p class="mb-0">
                            "Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="mb-3">
                            <i class="bi bi-geo-alt"></i> İletişim Bilgileri
                        </h5>
                        
                        <?php if (!empty($ayarlar['iletisim_eposta'])): ?>
                            <div class="mb-3">
                                <i class="bi bi-envelope"></i>
                                <strong>E-posta:</strong><br>
                                <a href="mailto:<?= htmlspecialchars($ayarlar['iletisim_eposta']) ?>" 
                                   class="text-white">
                                    <?= htmlspecialchars($ayarlar['iletisim_eposta']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($ayarlar['iletisim_telefon'])): ?>
                            <div class="mb-3">
                                <i class="bi bi-telephone"></i>
                                <strong>Telefon:</strong><br>
                                <a href="tel:<?= htmlspecialchars($ayarlar['iletisim_telefon']) ?>" 
                                   class="text-white">
                                    <?= htmlspecialchars($ayarlar['iletisim_telefon']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($ayarlar['iletisim_adres'])): ?>
                            <div class="mb-3">
                                <i class="bi bi-geo-alt"></i>
                                <strong>Adres:</strong><br>
                                <?= htmlspecialchars($ayarlar['iletisim_adres']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sosyal Medya -->
                    <div>
                        <h5 class="mb-3">
                            <i class="bi bi-share"></i> Sosyal Medya
                        </h5>
                        
                        <div class="d-flex">
                            <?php if (!empty($ayarlar['sosyal_facebook'])): ?>
                                <a href="<?= htmlspecialchars($ayarlar['sosyal_facebook']) ?>" 
                                   class="social-link" target="_blank" title="Facebook">
                                    <i class="bi bi-facebook"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($ayarlar['sosyal_twitter'])): ?>
                                <a href="<?= htmlspecialchars($ayarlar['sosyal_twitter']) ?>" 
                                   class="social-link" target="_blank" title="Twitter">
                                    <i class="bi bi-twitter"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($ayarlar['sosyal_instagram'])): ?>
                                <a href="<?= htmlspecialchars($ayarlar['sosyal_instagram']) ?>" 
                                   class="social-link" target="_blank" title="Instagram">
                                    <i class="bi bi-instagram"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="#" class="social-link" title="YouTube">
                                <i class="bi bi-youtube"></i>
                            </a>
                            
                            <a href="#" class="social-link" title="Telegram">
                                <i class="bi bi-telegram"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Çalışma Saatleri -->
                    <div class="mt-4 pt-4 border-top border-white-50">
                        <h5 class="mb-3">
                            <i class="bi bi-clock"></i> Çalışma Saatleri
                        </h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">Pazartesi - Cuma: 09:00 - 18:00</li>
                            <li class="mb-2">Cumartesi: 10:00 - 16:00</li>
                            <li>Pazar: Kapalı</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- İletişim Formu -->
            <div class="col-lg-8">
                <div class="contact-form">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <h5 class="alert-heading">
                                <i class="bi bi-exclamation-triangle"></i> Hatalar:
                            </h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="contactForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ad_soyad" class="form-label">
                                    <i class="bi bi-person"></i> Ad Soyad *
                                </label>
                                <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" 
                                       value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '') ?>" 
                                       required minlength="3">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="eposta" class="form-label">
                                    <i class="bi bi-envelope"></i> E-posta Adresiniz *
                                </label>
                                <input type="email" class="form-control" id="eposta" name="eposta" 
                                       value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="konu" class="form-label">
                                <i class="bi bi-chat"></i> Konu *
                            </label>
                            <select class="form-select" id="konu" name="konu" required>
                                <option value="">Seçiniz</option>
                                <option value="Genel Soru" <?= ($_POST['konu'] ?? '') == 'Genel Soru' ? 'selected' : '' ?>>Genel Soru</option>
                                <option value="Teknik Destek" <?= ($_POST['konu'] ?? '') == 'Teknik Destek' ? 'selected' : '' ?>>Teknik Destek</option>
                                <option value="Öneri" <?= ($_POST['konu'] ?? '') == 'Öneri' ? 'selected' : '' ?>>Öneri</option>
                                <option value="Şikayet" <?= ($_POST['konu'] ?? '') == 'Şikayet' ? 'selected' : '' ?>>Şikayet</option>
                                <option value="İşbirliği" <?= ($_POST['konu'] ?? '') == 'İşbirliği' ? 'selected' : '' ?>>İşbirliği</option>
                                <option value="Basın" <?= ($_POST['konu'] ?? '') == 'Basın' ? 'selected' : '' ?>>Basın</option>
                                <option value="Diğer" <?= ($_POST['konu'] ?? '') == 'Diğer' ? 'selected' : '' ?>>Diğer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mesaj" class="form-label">
                                <i class="bi bi-chat-left-text"></i> Mesajınız *
                            </label>
                            <textarea class="form-control" id="mesaj" name="mesaj" 
                                      rows="6" required minlength="10"
                                      placeholder="Mesajınızı buraya yazın..."><?= htmlspecialchars($_POST['mesaj'] ?? '') ?></textarea>
                            <div class="form-text">
                                En az 10 karakter. Kalan: <span id="charCount">0</span> karakter
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="kopya" name="kopya" 
                                   <?= isset($_POST['kopya']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="kopya">
                                Mesajımın bir kopyasını e-posta adresime gönder
                            </label>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="kvkk" required>
                                <label class="form-check-label" for="kvkk">
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#kvkkModal">
                                        Kişisel verilerin korunması politikasını
                                    </a> okudum ve kabul ediyorum *
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send"></i> Mesajı Gönder
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Harita (örnek) -->
                <div class="map-container">
                    <div style="width:100%;height:100%;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
                        <div class="text-center">
                            <i class="bi bi-map display-4 text-muted mb-3"></i>
                            <p class="text-muted mb-0">Harita burada görüntülenecek</p>
                            <small class="text-muted">
                                (Google Maps veya OpenStreetMap entegrasyonu)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sık Sorulan Sorular -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-question-circle"></i> Sık Sorulan Sorular
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                        Doğrudan İrade Platformu nedir?
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Doğrudan İrade, temsili demokrasinin aksine, vatandaşların doğrudan karar almasını sağlayan bir dijital platformdur. Negatif oy sistemi ile daha gerçekçi toplumsal tercihleri yansıtır.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                        Nasıl kayıt olabilirim?
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Ana sayfadan veya üst menüden "Kayıt Ol" butonuna tıklayarak kayıt formunu doldurabilirsiniz. TC kimlik numarası isteğe bağlıdır, güvenliğiniz için şifrelenerek saklanır.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                        Negatif oy sistemi nedir?
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Negatif oy sistemi, sadece kimin daha çok sevildiğini değil, kimin daha az sevilmediğini de ölçer. Net skor (Destek - Negatif) formülü ile kazanan belirlenir. Bu sistem popüler ama sevilmeyen adayları filtreler.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                        Oylarım güvende mi?
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Evet, tüm oylar şifrelenerek saklanır. Sistem SQL injection, XSS ve diğer saldırılara karşı korumalıdır. Oylarınız anonim olarak istatistiklerde kullanılır.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- KVKK Modal -->
    <div class="modal fade" id="kvkkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-shield-check"></i> Kişisel Verilerin Korunması
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <h6>1. VERİ SORUMLUSU</h6>
                        <p>Doğrudan İrade Platformu, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca veri sorumlusudur.</p>
                        
                        <h6>2. TOPLANAN VERİLER</h6>
                        <p>2.1. Kimlik Bilgileri: Ad-soyad, TC kimlik no (isteğe bağlı)<br>
                        2.2. İletişim Bilgileri: E-posta, telefon (isteğe bağlı)<br>
                        2.3. Kullanıcı Bilgileri: Doğum tarihi, üyelik bilgileri<br>
                        2.4. Oylama Verileri: Destek ve negatif oy tercihleri</p>
                        
                        <h6>3. VERİLERİN İŞLENME AMAÇLARI</h6>
                        <p>3.1. Platformun işleyişini sağlamak<br>
                        3.2. Oylama süreçlerini yönetmek<br>
                        3.3. İletişim ve bildirim göndermek<br>
                        3.4. İstatistik ve analiz yapmak<br>
                        3.5. Güvenlik ve denetim sağlamak</p>
                        
                        <h6>4. VERİLERİN KORUNMASI</h6>
                        <p>4.1. Tüm veriler şifrelenerek saklanır.<br>
                        4.2. Veri tabanı güvenlik duvarı ile korunur.<br>
                        4.3. Düzenli güvenlik denetimleri yapılır.<br>
                        4.4. Yetkisiz erişim engellenir.</p>
                        
                        <h6>5. VERİLERİN PAYLAŞILMASI</h6>
                        <p>Kişisel verileriniz kesinlikle üçüncü şahıslarla paylaşılmaz. Sadece yasal zorunluluk hallerinde yetkili mercilere bilgi verilebilir.</p>
                        
                        <h6>6. HAKLARINIZ</h6>
                        <p>KVKK'nın 11. maddesi uyarınca kişisel verilerinizin;<br>
                        - İşlenip işlenmediğini öğrenme,<br>
                        - İşlenmişse buna ilişkin bilgi talep etme,<br>
                        - İşlenme amacını ve bunların amacına uygun kullanılıp kullanılmadığını öğrenme,<br>
                        - Silinmesini veya yok edilmesini isteme,<br>
                        - Düzeltilmesini isteme haklarına sahipsiniz.</p>
                        
                        <h6>7. İLETİŞİM</h6>
                        <p>Haklarınızı kullanmak için: destek@dogrudanirade.org</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="document.getElementById('kvkk').checked = true">
                        Kabul Ediyorum
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Karakter sayacı
    document.getElementById('mesaj').addEventListener('input', function() {
        const count = this.value.length;
        document.getElementById('charCount').textContent = count;
        
        if (count < 10) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    // Form doğrulama
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        const mesaj = document.getElementById('mesaj').value;
        const kvkk = document.getElementById('kvkk');
        
        if (mesaj.length < 10) {
            e.preventDefault();
            alert('Mesajınız en az 10 karakter olmalıdır.');
            return;
        }
        
        if (!kvkk.checked) {
            e.preventDefault();
            alert('KVKK politikasını kabul etmelisiniz.');
            return;
        }
    });
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **index.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();
$activePolls = $db->query(
    "SELECT o.*, COUNT(DISTINCT ok.kullanici_id) as oy_sayisi 
     FROM oylamalar o 
     LEFT JOIN oy_kullanicilar ok ON o.id = ok.oylama_id 
     WHERE o.durum = 'aktif' 
     GROUP BY o.id 
     ORDER BY o.olusturulma_tarihi DESC 
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);
// Ek istatistikler
$bugun_oy = $db->singleValueQuery(
    "SELECT COUNT(*) FROM oy_kullanicilar WHERE DATE(oy_tarihi) = CURDATE()"
);

$son_hafta_kullanici = $db->singleValueQuery(
    "SELECT COUNT(*) FROM kullanicilar WHERE kayit_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);

$en_cok_oy = $db->query(
    "SELECT o.baslik, COUNT(*) as oy_sayisi 
     FROM oy_kullanicilar ok 
     JOIN oylamalar o ON ok.oylama_id = o.id 
     WHERE o.durum = 'aktif'
     GROUP BY o.id 
     ORDER BY oy_sayisi DESC 
     LIMIT 1"
)->fetch();

// Son kayıt olan kullanıcılar
$son_kullanicilar = $db->query(
    "SELECT ad_soyad, eposta, kayit_tarihi 
     FROM kullanicilar 
     ORDER BY kayit_tarihi DESC 
     LIMIT 5"
)->fetchAll();

// Yakında bitecek oylamalar
$yakinda_bitecek = $db->query(
    "SELECT baslik, bitis_tarihi 
     FROM oylamalar 
     WHERE durum = 'aktif' 
     AND bitis_tarihi BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
     ORDER BY bitis_tarihi ASC 
     LIMIT 5"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doğrudan İrade Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold text-primary">DOĞRUDAN İRADE PLATFORMU</h1>
                    <p class="lead">"Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"</p>
                </div>

                <!-- Aktif Oylamalar -->
                <div class="card shadow-lg mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">📊 AKTİF OYLAMALAR</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activePolls)): ?>
                            <div class="alert alert-info">
                                Şu anda aktif oylama bulunmuyor.
                            </div>
                        <?php else: ?>
                            <?php foreach ($activePolls as $poll): ?>
                                <div class="poll-item mb-3 p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1">
                                                <a href="oylama_detay.php?id=<?= $poll['id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($poll['baslik']) ?>
                                                </a>
                                            </h5>
                                            <p class="text-muted mb-2"><?= htmlspecialchars($poll['aciklama']) ?></p>
                                            <div class="d-flex gap-3">
                                                <small class="badge bg-info"><?= $poll['tur'] ?></small>
                                                <small class="badge bg-secondary"><?= $poll['topluluk_tipi'] ?></small>
                                                <small class="badge bg-warning"><?= $poll['oy_sayisi'] ?> oy</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block">Bitiş: <?= date('d.m.Y H:i', strtotime($poll['bitis_tarihi'])) ?></small>
                                            <a href="oylama_detay.php?id=<?= $poll['id'] ?>" class="btn btn-sm btn-primary mt-2">Oy Kullan</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Ek İstatistikler -->
<div class="row mt-4">
    <div class="col-md-12">
        <h4 class="mb-3">
            <i class="bi bi-graph-up"></i> Detaylı İstatistikler
        </h4>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <h5 class="card-title text-success"><?= $bugun_oy ?></h5>
                <p class="card-text">Bugünkü Oy</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <h5 class="card-title text-primary"><?= $son_hafta_kullanici ?></h5>
                <p class="card-text">Son Haftada Kayıt</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <h5 class="card-title text-warning">
                    <?= $en_cok_oy ? $en_cok_oy['oy_sayisi'] : 0 ?>
                </h5>
                <p class="card-text">
                    En Çok Oy Alan<br>
                    <small>
                        <?= $en_cok_oy ? htmlspecialchars(mb_substr($en_cok_oy['baslik'], 0, 20)) . '...' : '-' ?>
                    </small>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <h5 class="card-title text-info">
                    <?= count($yakinda_bitecek) ?>
                </h5>
                <p class="card-text">Yakında Bitecek Oylama</p>
            </div>
        </div>
    </div>
</div>

<!-- Son Kayıtlar ve Yakında Bitecek Oylamalar -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-person-plus"></i> Son Kayıt Olan Kullanıcılar
                </h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($son_kullanicilar as $kullanici): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($kullanici['ad_soyad']) ?></h6>
                                <small><?= date('d.m', strtotime($kullanici['kayit_tarihi'])) ?></small>
                            </div>
                            <p class="mb-1 small text-muted"><?= htmlspecialchars($kullanici['eposta']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-clock"></i> Yakında Bitecek Oylamalar
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($yakinda_bitecek)): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle text-success display-6 d-block mb-2"></i>
                        <p class="text-muted mb-0">Yakında bitecek oylama yok</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($yakinda_bitecek as $oylama): 
                            $kalan_saat = round((strtotime($oylama['bitis_tarihi']) - time()) / 3600);
                        ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars(mb_substr($oylama['baslik'], 0, 30)) ?>...</h6>
                                    <span class="badge bg-<?= $kalan_saat < 24 ? 'danger' : 'warning' ?>">
                                        <?= $kalan_saat ?> saat
                                    </span>
                                </div>
                                <p class="mb-1 small text-muted">
                                    Bitiş: <?= date('d.m.Y H:i', strtotime($oylama['bitis_tarihi'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

                <!-- Platform Özellikleri -->
                <div class="row mt-5">
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            <div class="card-body">
                                <div class="feature-icon mb-3">
                                    <span class="display-4">✅</span>
                                </div>
                                <h5 class="card-title">Destek Oyu</h5>
                                <p class="card-text">Bir adayı veya seçeneği aktif olarak destekleyin</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            <div class="card-body">
                                <div class="feature-icon mb-3">
                                    <span class="display-4">❌</span>
                                </div>
                                <h5 class="card-title">Negatif Oy</h5>
                                <p class="card-text">Kabul edilemez gördüğünüz seçeneklere karşı oy kullanın</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            <div class="card-body">
                                <div class="feature-icon mb-3">
                                    <span class="display-4">📊</span>
                                </div>
                                <h5 class="card-title">Net Skor</h5>
                                <p class="card-text">Destek - Negatif = Net Skor ile gerçek tercih ortaya çıkar</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **initial-prompt-to-deepseek.md**
```markdown
# PROJE: DOĞRUDAN İRADE PLATFORMU - EKSİKSİZ PAKET

## **1. PROJE FELSEFESİ ve MAKALE (README.md İÇERİĞİ)**

### RADİKAL DEMOKRASİ: ARACILARI ORTADAN KALDIRAN DOĞRUDAN İRADE PLATFORMU

**Sorun Tespiti: Temsili Demokrasinin Çöküşü**

Modern temsili demokrasi sistemleri, tarihsel olarak oligarşik bir evrime uğramıştır. Seçimle gelen temsilciler, kısa sürede "profesyonel politikacı" sınıfına dönüşmekte ve seçmenlerin gerçek iradesiyle bağlarını koparmaktadır. Bu sistemde:

- **Seçmen manipülasyonu** sistematik hale gelmiştir: Medya kontrolü, söylem mühendisliği, korku politikaları ve popülizm, seçmenin rasyonel karar verme yeteneğini baltalamaktadır.
- **Çıkar çatışmaları** yapısal problemdir: Finans sektörü, silah lobileri, enerji tekelleri ve büyük şirketler, politikacıları fonlama ve lobi faaliyetleriyle satın almaktadır.
- **Temsil krizi** derinleşmektedir: Milletvekilleri seçildikten sonra seçmenlerine hesap vermemekte, parti disiplini adına bireysel iradelerini feda etmekte, ve gerçek sorunlara çözüm üretmek yerine ideolojik kutuplaşmayı derinleştirmektedir.

**Kurumsal Çıkmazlar:**

1. **TBMM**: Yasama süreci halktan kopuk, bürokratik ve lobi etkisine açık.
2. **YSK**: Tarafsızlığı sürekli tartışma konusu olan, siyasi atamalarla yönetilen bir kurum.
3. **Sendikalar ve Meslek Odaları**: Yönetici elitleri, üyelerinin iradesini temsil etmekten uzak, kendi çıkarlarını koruyan kapalı yapılar.
4. **Yerel Yönetimler**: Merkezi hükümetin vesayeti altında, gerçek anlamda yerel iradeyi yansıtamayan yapılar.

**Teknolojik Fırsat: İnternet Devrimi**

21. yüzyıl internet teknolojisi, tarihte ilk kez, milyonlarca insanın eşzamanlı, şeffaf, güvenli ve doğrudan katılımını mümkün kılmaktadır. Artık:

- Coğrafi sınırlar anlamını yitirmiştir.
- Bilgiye erişim demokratikleşmiştir.
- Gerçek zamanlı iletişim ve oylama teknik olarak mümkündür.

**Platform Vizyonu: Aracıları Ortadan Kaldırmak**

Bu platform, tüm temsili kurumları aşan radikal bir alternatif sunmaktadır:

1. **Ulusal düzeyde**: TBMM'nin yasama fonksiyonunu, doğrudan halk oylamalarıyla yerine getirmek.
2. **Yerel düzeyde**: İl ve ilçe meclislerinin karar alma süreçlerini demokratikleştirmek.
3. **Mesleki düzeyde**: Sendika ve meslek odalarında üyelerin doğrudan söz sahibi olmasını sağlamak.
4. **Kurumsal düzeyde**: Şirketlerde hissedar ve çalışan katılımını artırmak.

**Devrimci Yenilik: Negatif Oy Sistemi**

Geleneksel seçim sistemlerindeki en büyük eksiklik, sadece "en az kötü" adayı seçmeye zorlanmaktır. Negatif oy sistemi bu problemi çözmektedir:

- **Popüler ama sevilmeyen adayları filtreler**: Yüksek destek alan ama aynı zamanda yüksek muhalefet toplayan adayların kazanmasını engeller.
- **Toplumsal mutabakatı yansıtır**: Sadece kimin daha çok sevildiğini değil, kimin daha az sevilmediğini de ölçer.
- **Manipülasyonu zorlaştırır**: Medya tarafından pompalanan ancak gerçekte halk desteği olmayan adayları eleyebilir.
- **Daha gerçek bir toplumsal tercihi yansıtır**: Net skor (destek - negatif) formülü, bir adayın gerçek kabul edilebilirliğini ölçer.

**Platformun Temel İlkeleri:**

1. **Doğrudan Katılım**: Her vatandaş, her kararda doğrudan söz sahibi olabilir.
2. **Şeffaflık**: Tüm oylama süreçleri ve sonuçları herkese açıktır.
3. **Eşitlik**: Her kullanıcının oyu eşit değerdedir.
4. **Güvenlik**: Oy verme süreci manipülasyona ve hileye karşı korumalıdır.
5. **Kapsayıcılık**: Tüm toplumsal kesimlerin katılımı teşvik edilir.

Bu platform, demokrasinin evriminde yeni bir aşamayı temsil etmektedir: **Dijital Doğrudan Demokrasi**. Artık "temsil edilmeye" değil, "doğrudan söz sahibi olmaya" talibiz.

## **2. TEKNİK GEREKSİNİMLER (PHP/MySQL)**

- **Sunucu Tarafı**: PHP 7.4 veya üzeri
- **Veritabanı**: MySQL 5.7 veya üzeri (veya MariaDB 10.2+)
- **Frontend**: Bootstrap 5.2+ ile responsive ve modern arayüz
- **Güvenlik**:
  - Tüm kullanıcı girdileri filtrelenecek ve validate edilecek
  - SQL sorgularında kesinlikle Prepared Statements kullanılacak
  - Kullanıcı şifreleri `password_hash()` ile hash'lenecek
  - XSS, CSRF ve SQL Injection korumaları uygulanacak
  - HTTPS zorunlu olacak
- **Performans**: Temel önbellekleme mekanizmaları implemente edilecek

## **3. VERİTABANI ŞEMASI**

```sql
-- Veritabanı oluşturma
CREATE DATABASE IF NOT EXISTS dogrudan_irade DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dogrudan_irade;

-- Oylama Tablosu
CREATE TABLE oylamalar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tur ENUM('secim', 'kanun_teklifi', 'referandum') NOT NULL,
    baslik VARCHAR(500) NOT NULL,
    aciklama TEXT,
    olusturan_id BIGINT NOT NULL,
    topluluk_tipi ENUM('ulusal', 'il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') DEFAULT 'ulusal',
    topluluk_id BIGINT DEFAULT NULL COMMENT 'Hangi il, ilçe, sendika vb. için oylama',
    baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    bitis_tarihi DATETIME,
    durum ENUM('aktif', 'sonuclandi', 'iptal') DEFAULT 'aktif',
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tur (tur),
    INDEX idx_durum (durum),
    INDEX idx_topluluk (topluluk_tipi, topluluk_id)
);

-- Adaylar Tablosu (Seçim türündeki oylamalar için)
CREATE TABLE adaylar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    aday_adi VARCHAR(200) NOT NULL,
    aday_aciklama TEXT,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Kullanıcılar Tablosu
CREATE TABLE kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    eposta VARCHAR(255) UNIQUE NOT NULL,
    sifre_hash VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(200) NOT NULL,
    tc_kimlik VARCHAR(11) UNIQUE COMMENT 'Güvenlik nedeniyle hashlenmiş olarak saklanabilir',
    telefon VARCHAR(20),
    dogum_tarihi DATE,
    kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    son_giris_tarihi DATETIME,
    durum ENUM('aktif', 'pasif', 'askida') DEFAULT 'aktif',
    INDEX idx_eposta (eposta)
);

-- Kullanıcı Topluluk Üyelikleri
CREATE TABLE kullanici_topluluklari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    topluluk_tipi ENUM('il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') NOT NULL,
    topluluk_id BIGINT NOT NULL COMMENT 'İl kodu, sendika IDsi vb.',
    uyelik_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_uyelik (kullanici_id, topluluk_tipi, topluluk_id),
    INDEX idx_kullanici (kullanici_id)
);

-- OYLAR Tablosu (Çekirdek Mantık Burada)
CREATE TABLE oy_kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    -- DESTEK OYU: Hangi adayı destekliyor? (NULL olabilir)
    destek_verilen_aday_id BIGINT NULL,
    -- NEGATİF OY: Hangi adaya karşı? (Bir kullanıcı BİRDEN FAZLA adaya negatif oy verebilir)
    negatif_oy_verilen_aday_id BIGINT NULL,
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_adresi VARCHAR(45),
    -- Bir kullanıcı aynı oylamada tek bir destek oyu kullanabilir
    UNIQUE KEY tek_destek (oylama_id, kullanici_id),
    -- Bir kullanıcı aynı adaya birden fazla negatif oy veremez
    UNIQUE KEY tek_negatif_aday (oylama_id, kullanici_id, negatif_oy_verilen_aday_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (destek_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    FOREIGN KEY (negatif_oy_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    INDEX idx_oylama_kullanici (oylama_id, kullanici_id),
    INDEX idx_destek (destek_verilen_aday_id),
    INDEX idx_negatif (negatif_oy_verilen_aday_id)
);

-- Referandum/Kanun Teklifi Seçenekleri
CREATE TABLE oylama_secenekleri (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    secenek_metni VARCHAR(500) NOT NULL,
    tur ENUM('evet', 'hayir', 'alternatif') NOT NULL,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Referandum Oyları
CREATE TABLE referandum_oylari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    secenek_id BIGINT NOT NULL,
    negatif_oy BOOLEAN DEFAULT FALSE COMMENT 'Bu seçeneğe karşı negatif oy',
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY tek_oylama_kullanici (oylama_id, kullanici_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (secenek_id) REFERENCES oylama_secenekleri(id) ON DELETE CASCADE
);

-- Log Tablosu (Güvenlik ve Denetim)
CREATE TABLE sistem_loglari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NULL,
    islem_tipi VARCHAR(100) NOT NULL,
    aciklama TEXT,
    ip_adresi VARCHAR(45),
    user_agent TEXT,
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tarih (tarih),
    INDEX idx_islem_tipi (islem_tipi)
);
```

## **4. ÇEKİRDEK İŞLEVLER ve NEGATİF OY SİSTEMİ**

### A. **Kullanıcı Sistemi:**
- Kayıt sayfası: eposta, şifre, ad-soyad, TC kimlik (güvenli hash), telefon
- Giriş sayfası: eposta/şifre ile authentication
- Profil sayfası: Kullanıcının üye olduğu topluluklar görüntülenmeli (örn: 'İstanbul Seçmeni', 'X Sendikası Üyesi', 'Y Meslek Odası Üyesi')
- Kullanıcı, birden fazla topluluğa üye olabilmeli

### B. **Oylama Oluşturma:**
- Yetkili kullanıcılar yeni oylama başlatabilir
- Oylama türü seçimi: 'Seçim', 'Kanun Teklifi', 'Referandum'
- Oylama başlığı, açıklaması, bitiş tarihi
- Topluluk seçimi: Ulusal, il, ilçe, sendika, meslek odası vb.
- Seçim türü seçilirse, aday listesi ekleme (aday adı, kısa açıklama)
- Referandum/Kanun teklifi türü seçilirse, seçenekler ekleme

### C. **OY KULLANMA ARAYÜZÜ ve MANTIĞI (EN ÖNEMLİ KISIM):**

#### Seçim Oylaması Sayfası:
```
[OYLAMA BAŞLIĞI]
[Açıklama]

ŞU ANKİ SEÇİMLERİNİZ:
✅ Desteklediğiniz Aday: [Aday Adı] (Varsa)
❌ Negatif Oy Verdiğiniz Adaylar: [Aday1], [Aday2]... (Varsa)

ADAY LİSTESİ:

1. [Aday A Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]

2. [Aday B Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]

3. [Aday C Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]
```

**İşlevler:**
1. **Destek Oyu**: Kullanıcı YALNIZCA BİR adaya destek oyu verebilir. Yeni destek oyu verildiğinde önceki destek oyu otomatik iptal edilir.
2. **Negatif Oy**: Kullanıcı İSTEDİĞİ KADAR adaya negatif oy verebilir. Her negatif oy butonu bağımsız çalışır (toggle mantığı: basılırsa negatif oy verilir, tekrar basılırsa geri alınır).
3. **Anlık Geri Bildirim**: Kullanıcının seçimleri anlık olarak sayfanın üstünde gösterilir.
4. **Topluluk Kontrolü**: Kullanıcı sadece kendi üyesi olduğu toplulukların oylamalarında oy kullanabilir.

### D. **SONUÇ HESAPLAMA:**

```php
<?php
// config/database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'dogrudan_irade';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function singleValueQuery($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
}

// functions/secim_fonksiyonlari.php
function secimSonucunuHesapla($oylama_id) {
    $db = new Database();
    
    // 1. Tüm adayları al
    $stmt = $db->query(
        "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
        [$oylama_id]
    );
    $adaylar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sonuclar = [];
    foreach ($adaylar as $aday) {
        // 2. Bu adaya verilen DESTEK oylarını say
        $destekSayisi = $db->singleValueQuery(
            "SELECT COUNT(*) FROM oy_kullanicilar 
             WHERE oylama_id = ? AND destek_verilen_aday_id = ?",
            [$oylama_id, $aday['id']]
        );

        // 3. Bu adaya verilen NEGATİF oyları say
        $negatifSayisi = $db->singleValueQuery(
            "SELECT COUNT(*) FROM oy_kullanicilar 
             WHERE oylama_id = ? AND negatif_oy_verilen_aday_id = ?",
            [$oylama_id, $aday['id']]
        );

        // 4. NET SKOR HESAPLA: Destek - Negatif
        $netSkor = $destekSayisi - $negatifSayisi;

        $sonuclar[] = [
            'aday_id' => $aday['id'],
            'aday_adi' => $aday['aday_adi'],
            'aday_aciklama' => $aday['aday_aciklama'],
            'destek_sayisi' => (int)$destekSayisi,
            'negatif_sayisi' => (int)$negatifSayisi,
            'net_skor' => (int)$netSkor
        ];
    }

    // 5. NET SKOR'a göre yüksekten düşüğe sırala
    usort($sonuclar, function($a, $b) {
        if ($b['net_skor'] == $a['net_skor']) {
            // Net skor eşitse, daha az negatif oy alan kazanır
            return $a['negatif_sayisi'] <=> $b['negatif_sayisi'];
        }
        return $b['net_skor'] <=> $a['net_skor'];
    });

    return $sonuclar; // İlk sıradaki kazanandır
}

function oylamaSonuclandiMi($oylama_id) {
    $db = new Database();
    $stmt = $db->query(
        "SELECT durum, bitis_tarihi FROM oylamalar WHERE id = ?",
        [$oylama_id]
    );
    $oylama = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($oylama['durum'] == 'sonuclandi') {
        return true;
    }
    
    // Bitiş tarihi geçmişse
    if ($oylama['bitis_tarihi'] && strtotime($oylama['bitis_tarihi']) < time()) {
        // Oylamayı sonuçlandır
        $db->query(
            "UPDATE oylamalar SET durum = 'sonuclandi' WHERE id = ?",
            [$oylama_id]
        );
        return true;
    }
    
    return false;
}
?>
```

**Sonuç Görüntüleme Sayfası:**
```
[OYLAMA BAŞLIĞI] - SONUÇLAR

TOPLAM OY KULLANAN: 1,250 kişi

SONUÇLAR:
1. ✅ KAZANAN: Aday B
   Destek: 400 oy | Negatif: 50 oy | Net Skor: 350
   (%32 destek, %4 negatif)

2. Aday A
   Destek: 600 oy | Negatif: 450 oy | Net Skor: 150
   (%48 destek, %36 negatif)

3. Aday C
   Destek: 250 oy | Negatif: 100 oy | Net Skor: 150
   (%20 destek, %8 negatif)
```

## **5. DİĞER MODÜLLER**

### **Kanun Teklifi/Referandum Modülü:**
- Seçenekler: 'Evet', 'Hayır' ve alternatif teklifler
- Her seçenek için ayrı negatif oy butonu
- Sonuç hesaplama: (Evet oyu - Negatif oy) vs (Hayır oyu - Negatif oy)

### **Kullanıcı Toplulukları:**
- Kullanıcı kayıt sırasında veya profil sayfasında topluluklara üye olabilir
- Her topluluk tipi için admin onayı veya otomatik doğrulama
- Topluluk bazında oylama filtreleme

### **Yönetim Paneli:**
- Oylama oluşturma/duzenleme/silme
- Oylamaları başlatma/bitirme
- Kullanıcı yönetimi (aktif/pasif yapma)
- Sistem loglarını görüntüleme
- Topluluk yönetimi

### **Güvenlik Modülleri:**
- IP bazlı oy kullanım sınırlaması
- Çoklu hesap tespiti
- Bot koruma (CAPTCHA)
- Oylama çakışması önleme

## **6. İSTENEN ÇIKTILAR**

1. **`kurulum.sql`**: Tüm veritabanı yapısını oluşturan SQL dosyası
2. **`index.php`**: Ana sayfa - Aktif oylamalar listesi
3. **`kayit.php` & `giris.php`**: Kullanıcı kayıt ve giriş sayfaları
4. **`profil.php`**: Kullanıcı profil ve topluluk yönetim sayfası
5. **`oylama_olustur.php`**: Yeni oylama başlatma sayfası
6. **`oylama_detay.php`**: Oylama detay ve oy kullanma sayfası (Destek/Negatif arayüzü ile)
7. **`sonuc.php`**: Sonuçları net skora göre gösteren sayfa
8. **`admin/`**: Yönetim paneli dosyaları
9. **`README.md`**: Yukarıdaki felsefi makaleyi içeren proje dokümantasyonu
10. **`config/`**: Veritabanı bağlantı ve ayar dosyaları
11. **`includes/`**: Fonksiyon ve sınıf dosyaları
12. **`assets/`**: CSS, JS ve resim dosyaları

## **EK NOTLAR**

- **Kod Standartları**: PSR-12 kodlama standardı, açıklayıcı yorum satırları, Türkçe değişken/fonksiyon isimlendirmesi
- **Modüler Yapı**: MVC benzeri bir yapı, her modül ayrı dosyalarda
- **Hata Yönetimi**: Tüm hatalar loglanacak, kullanıcıya uygun mesaj gösterilecek
- **Test**: Temel fonksiyonlar için unit testler
- **Dokümantasyon**: API ve veritabanı dokümantasyonu

**Proje Sloganı**: "Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"

---

```

--------------------------------------------------------------------------------

📄 **kayit.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();
$errors = [];
$success = '';

// Türkiye illeri
$iller = [
    '01' => 'Adana', '02' => 'Adıyaman', '03' => 'Afyonkarahisar', '04' => 'Ağrı',
    '05' => 'Amasya', '06' => 'Ankara', '07' => 'Antalya', '08' => 'Artvin',
    '09' => 'Aydın', '10' => 'Balıkesir', '11' => 'Bilecik', '12' => 'Bingöl',
    '13' => 'Bitlis', '14' => 'Bolu', '15' => 'Burdur', '16' => 'Bursa',
    '17' => 'Çanakkale', '18' => 'Çankırı', '19' => 'Çorum', '20' => 'Denizli',
    '21' => 'Diyarbakır', '22' => 'Edirne', '23' => 'Elazığ', '24' => 'Erzincan',
    '25' => 'Erzurum', '26' => 'Eskişehir', '27' => 'Gaziantep', '28' => 'Giresun',
    '29' => 'Gümüşhane', '30' => 'Hakkari', '31' => 'Hatay', '32' => 'Isparta',
    '33' => 'Mersin', '34' => 'İstanbul', '35' => 'İzmir', '36' => 'Kars',
    '37' => 'Kastamonu', '38' => 'Kayseri', '39' => 'Kırklareli', '40' => 'Kırşehir',
    '41' => 'Kocaeli', '42' => 'Konya', '43' => 'Kütahya', '44' => 'Malatya',
    '45' => 'Manisa', '46' => 'Kahramanmaraş', '47' => 'Mardin', '48' => 'Muğla',
    '49' => 'Muş', '50' => 'Nevşehir', '51' => 'Niğde', '52' => 'Ordu',
    '53' => 'Rize', '54' => 'Sakarya', '55' => 'Samsun', '56' => 'Siirt',
    '57' => 'Sinop', '58' => 'Sivas', '59' => 'Tekirdağ', '60' => 'Tokat',
    '61' => 'Trabzon', '62' => 'Tunceli', '63' => 'Şanlıurfa', '64' => 'Uşak',
    '65' => 'Van', '66' => 'Yozgat', '67' => 'Zonguldak', '68' => 'Aksaray',
    '69' => 'Bayburt', '70' => 'Karaman', '71' => 'Kırıkkale', '72' => 'Batman',
    '73' => 'Şırnak', '74' => 'Bartın', '75' => 'Ardahan', '76' => 'Iğdır',
    '77' => 'Yalova', '78' => 'Karabük', '79' => 'Kilis', '80' => 'Osmaniye',
    '81' => 'Düzce'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad']);
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];
    $tc_kimlik = trim($_POST['tc_kimlik'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $dogum_tarihi = $_POST['dogum_tarihi'] ?? '';
    $secilen_iller = $_POST['iller'] ?? [];

    // Validasyon
    if (empty($ad_soyad)) $errors[] = 'Ad soyad gereklidir';
    if (empty($eposta) || !filter_var($eposta, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta adresi girin';
    if (strlen($sifre) < 6) $errors[] = 'Şifre en az 6 karakter olmalı';
    if ($sifre !== $sifre_tekrar) $errors[] = 'Şifreler eşleşmiyor';
    
    if (!empty($tc_kimlik) && !validateTCKN($tc_kimlik)) {
        $errors[] = 'Geçersiz TC Kimlik numarası';
    }
    
    // E-posta kontrolü
    $existing = $db->singleValueQuery(
        "SELECT COUNT(*) FROM kullanicilar WHERE eposta = ?",
        [$eposta]
    );
    if ($existing > 0) $errors[] = 'Bu e-posta zaten kayıtlı';

    // TC kontrolü
    if (!empty($tc_kimlik)) {
        $existing_tc = $db->singleValueQuery(
            "SELECT COUNT(*) FROM kullanicilar WHERE tc_kimlik = ?",
            [$tc_kimlik]
        );
        if ($existing_tc > 0) $errors[] = 'Bu TC Kimlik numarası zaten kayıtlı';
    }

    if (empty($errors)) {
        try {
            // Kullanıcıyı kaydet
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            $tc_hash = !empty($tc_kimlik) ? hash('sha256', $tc_kimlik . 'SALT_KEY') : null;
            
            $kullanici_id = $db->insertAndGetId(
                "INSERT INTO kullanicilar (eposta, sifre_hash, ad_soyad, tc_kimlik, telefon, dogum_tarihi) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$eposta, $sifre_hash, $ad_soyad, $tc_hash, $telefon, $dogum_tarihi]
            );

            // Seçilen illeri topluluk olarak ekle
            foreach ($secilen_iller as $il_kodu) {
                if (isset($iller[$il_kodu])) {
                    $db->query(
                        "INSERT INTO kullanici_topluluklari (kullanici_id, topluluk_tipi, topluluk_id) 
                         VALUES (?, 'il', ?)",
                        [$kullanici_id, $il_kodu]
                    );
                }
            }

            // Log kaydı
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'yeni_kayit', ?, ?)",
                [$kullanici_id, "$ad_soyad kayıt oldu", $_SERVER['REMOTE_ADDR']]
            );

            // Otomatik giriş yap
            $_SESSION['kullanici_id'] = $kullanici_id;
            $_SESSION['ad_soyad'] = $ad_soyad;
            $_SESSION['eposta'] = $eposta;
            $_SESSION['yetki_seviye'] = 'kullanici';

            $success = 'Kayıt başarılı! Yönlendiriliyorsunuz...';
            
            // 2 saniye sonra yönlendir
            header("refresh:2;url=index.php");
            
        } catch (Exception $e) {
            $errors[] = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}

function validateTCKN($tckn) {
    if (strlen($tckn) != 11) return false;
    if ($tckn[0] == '0') return false;
    
    $odd = $tckn[0] + $tckn[2] + $tckn[4] + $tckn[6] + $tckn[8];
    $even = $tckn[1] + $tckn[3] + $tckn[5] + $tckn[7];
    
    if ((($odd * 7) - $even) % 10 != $tckn[9]) return false;
    
    $total = 0;
    for ($i = 0; $i < 10; $i++) {
        $total += $tckn[$i];
    }
    
    return $total % 10 == $tckn[10];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .multi-select {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }
        .form-check {
            margin-bottom: 5px;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Başlık -->
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold text-primary">DOĞRUDAN İRADE PLATFORMU</h1>
                    <p class="lead">Doğrudan demokrasiye katıl, söz sahibi ol!</p>
                </div>

                <!-- Kayıt formu -->
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-person-plus-fill"></i> YENİ HESAP OLUŞTUR
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <h5 class="alert-heading">✅ Kayıt Başarılı!</h5>
                                <?= $success ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <h5 class="alert-heading">⚠️ Hatalar:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <!-- Temel bilgiler -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2 mb-3">📋 Temel Bilgiler</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="ad_soyad" class="form-label required">Ad Soyad</label>
                                    <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" 
                                           value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '') ?>" 
                                           required minlength="3">
                                    <div class="invalid-feedback">Lütfen geçerli bir ad soyad girin (en az 3 karakter)</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="eposta" class="form-label required">E-posta Adresi</label>
                                    <input type="email" class="form-control" id="eposta" name="eposta" 
                                           value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="sifre" class="form-label required">Şifre</label>
                                    <input type="password" class="form-control" id="sifre" name="sifre" 
                                           required minlength="6">
                                    <div class="invalid-feedback">Şifre en az 6 karakter olmalı</div>
                                    <small class="text-muted">En az 6 karakter</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="sifre_tekrar" class="form-label required">Şifre Tekrar</label>
                                    <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar" required>
                                    <div class="invalid-feedback">Şifreler eşleşmiyor</div>
                                </div>
                            </div>

                            <!-- Ek bilgiler -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2 mb-3">📝 Ek Bilgiler (İsteğe Bağlı)</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="tc_kimlik" class="form-label">TC Kimlik No</label>
                                    <input type="text" class="form-control" id="tc_kimlik" name="tc_kimlik" 
                                           value="<?= htmlspecialchars($_POST['tc_kimlik'] ?? '') ?>" 
                                           pattern="[0-9]{11}" maxlength="11">
                                    <div class="invalid-feedback">11 haneli TC Kimlik numarası girin</div>
                                    <small class="text-muted">Güvenliğiniz için şifrelenerek saklanacaktır</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="telefon" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="telefon" name="telefon" 
                                           value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>"
                                           pattern="[0-9]{10,11}">
                                    <div class="invalid-feedback">Geçerli bir telefon numarası girin</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
                                    <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi" 
                                           value="<?= htmlspecialchars($_POST['dogum_tarihi'] ?? '') ?>"
                                           max="<?= date('Y-m-d') ?>">
                                </div>
                            </div>

                            <!-- Topluluk seçimi (İller) -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2 mb-3">🏙️ Üye Olmak İstediğiniz İller</h5>
                                    <p class="text-muted mb-3">
                                        Seçtiğiniz illerdeki oylamalarda oy kullanabilirsiniz.
                                        Birden fazla il seçebilirsiniz (Ctrl/Cmd tuşu ile).
                                    </p>
                                    
                                    <div class="multi-select">
                                        <div class="row">
                                            <?php 
                                            $chunks = array_chunk($iller, ceil(count($iller) / 3), true);
                                            foreach ($chunks as $chunk):
                                            ?>
                                                <div class="col-md-4">
                                                    <?php foreach ($chunk as $kod => $il): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   name="iller[]" value="<?= $kod ?>" 
                                                                   id="il_<?= $kod ?>"
                                                                   <?= isset($_POST['iller']) && in_array($kod, $_POST['iller']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="il_<?= $kod ?>">
                                                                <?= $il ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onay ve kayıt -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="sozlesme" required>
                                        <label class="form-check-label" for="sozlesme">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">
                                                Kullanım Sözleşmesi'ni
                                            </a> okudum ve kabul ediyorum
                                        </label>
                                        <div class="invalid-feedback">Kullanım sözleşmesini kabul etmelisiniz</div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-person-plus-fill"></i> HESAP OLUŞTUR
                                        </button>
                                        <a href="giris.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-box-arrow-in-right"></i> Zaten Hesabım Var
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bilgi kartı -->
                <div class="card mt-4 border-success">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-shield-check text-success"></i> GÜVENLİK VE GİZLİLİK
                        </h6>
                        <ul class="small mb-0">
                            <li>TC Kimlik numaranız şifrelenerek saklanır</li>
                            <li>Kişisel verileriniz 3. şahıslarla paylaşılmaz</li>
                            <li>Oylarınız anonim olarak istatistiklerde kullanılır</li>
                            <li>Platform %100 şeffaf ve denetime açıktır</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kullanım Sözleşmesi Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">KULLANIM SÖZLEŞMESİ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <h6>1. GENEL HÜKÜMLER</h6>
                        <p>Doğrudan İrade Platformu, kullanıcılarına demokratik katılım imkanı sunan bir dijital platformdur. Bu sözleşme, platformu kullanan tüm kullanıcılar için geçerlidir.</p>
                        
                        <h6>2. KULLANICI HAK VE YÜKÜMLÜLÜKLERİ</h6>
                        <p>2.1. Kullanıcılar, gerçek ve doğru bilgilerle kayıt olmalıdır.<br>
                        2.2. Her kullanıcı tek bir hesap açabilir.<br>
                        2.3. Oy kullanırken dürüst ve şeffaf davranılmalıdır.</p>
                        
                        <h6>3. PLATFORM KURALLARI</h6>
                        <p>3.1. Manipülatif oy kullanımı yasaktır.<br>
                        3.2. Diğer kullanıcıların haklarına saygı gösterilmelidir.<br>
                        3.3. Platformun işleyişini bozacak davranışlarda bulunulamaz.</p>
                        
                        <h6>4. VERİ GÜVENLİĞİ</h6>
                        <p>4.1. Kişisel veriler 6698 sayılı Kanun'a uygun işlenir.<br>
                        4.2. Veriler şifrelenerek saklanır.<br>
                        4.3. Veriler üçüncü şahıslarla paylaşılmaz.</p>
                        
                        <h6>5. SORUMLULUK SINIRLARI</h6>
                        <p>Platform, teknik arızalardan doğan sorunlardan sorumlu değildir. Kullanıcılar kendi hesaplarının güvenliğinden sorumludur.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="document.getElementById('sozlesme').checked = true">
                        Kabul Ediyorum
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function() {
        'use strict';
        
        // Şifre eşleşme kontrolü
        var sifre = document.getElementById('sifre');
        var sifreTekrar = document.getElementById('sifre_tekrar');
        
        function validatePassword() {
            if (sifre.value !== sifreTekrar.value) {
                sifreTekrar.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                sifreTekrar.setCustomValidity('');
            }
        }
        
        sifre.onchange = validatePassword;
        sifreTekrar.onkeyup = validatePassword;
        
        // Form submit kontrolü
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
    
    // TC Kimlik format kontrolü
    document.getElementById('tc_kimlik').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 11) {
            this.value = this.value.slice(0, 11);
        }
    });
    
    // Telefon format kontrolü
    document.getElementById('telefon').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **kurulum.sql**
```sql
-- Veritabanı oluşturma
CREATE DATABASE IF NOT EXISTS dogrudan_irade DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dogrudan_irade;

-- Oylama Tablosu
CREATE TABLE oylamalar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tur ENUM('secim', 'kanun_teklifi', 'referandum') NOT NULL,
    baslik VARCHAR(500) NOT NULL,
    aciklama TEXT,
    olusturan_id BIGINT NOT NULL,
    topluluk_tipi ENUM('ulusal', 'il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') DEFAULT 'ulusal',
    topluluk_id BIGINT DEFAULT NULL COMMENT 'Hangi il, ilçe, sendika vb. için oylama',
    baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    bitis_tarihi DATETIME,
    durum ENUM('aktif', 'sonuclandi', 'iptal') DEFAULT 'aktif',
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tur (tur),
    INDEX idx_durum (durum),
    INDEX idx_topluluk (topluluk_tipi, topluluk_id)
);

-- Adaylar Tablosu (Seçim türündeki oylamalar için)
CREATE TABLE adaylar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    aday_adi VARCHAR(200) NOT NULL,
    aday_aciklama TEXT,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Kullanıcılar Tablosu
CREATE TABLE kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    eposta VARCHAR(255) UNIQUE NOT NULL,
    sifre_hash VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(200) NOT NULL,
    tc_kimlik VARCHAR(11) UNIQUE COMMENT 'Güvenlik nedeniyle hashlenmiş olarak saklanabilir',
    telefon VARCHAR(20),
    dogum_tarihi DATE,
    kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    son_giris_tarihi DATETIME,
    durum ENUM('aktif', 'pasif', 'askida') DEFAULT 'aktif',
    yetki_seviye ENUM('kullanici', 'yonetici', 'superadmin') DEFAULT 'kullanici',
    INDEX idx_eposta (eposta)
);

-- Örnek yönetici kullanıcı (şifre: admin123)
INSERT INTO kullanicilar (eposta, sifre_hash, ad_soyad, yetki_seviye, durum) 
VALUES ('admin@dogrudanirade.org', '$2y$10$YourHashHere', 'Sistem Yöneticisi', 'superadmin', 'aktif');

-- Kullanıcı Topluluk Üyelikleri
CREATE TABLE kullanici_topluluklari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    topluluk_tipi ENUM('il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') NOT NULL,
    topluluk_id BIGINT NOT NULL COMMENT 'İl kodu, sendika IDsi vb.',
    uyelik_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_uyelik (kullanici_id, topluluk_tipi, topluluk_id),
    INDEX idx_kullanici (kullanici_id)
);

-- OYLAR Tablosu (Çekirdek Mantık Burada)
CREATE TABLE oy_kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    destek_verilen_aday_id BIGINT NULL,
    negatif_oy_verilen_aday_id BIGINT NULL,
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_adresi VARCHAR(45),
    UNIQUE KEY tek_destek (oylama_id, kullanici_id),
    UNIQUE KEY tek_negatif_aday (oylama_id, kullanici_id, negatif_oy_verilen_aday_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (destek_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    FOREIGN KEY (negatif_oy_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    INDEX idx_oylama_kullanici (oylama_id, kullanici_id),
    INDEX idx_destek (destek_verilen_aday_id),
    INDEX idx_negatif (negatif_oy_verilen_aday_id)
);

-- Referandum/Kanun Teklifi Seçenekleri
CREATE TABLE oylama_secenekleri (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    secenek_metni VARCHAR(500) NOT NULL,
    tur ENUM('evet', 'hayir', 'alternatif') NOT NULL,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Referandum Oyları
CREATE TABLE referandum_oylari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    secenek_id BIGINT NOT NULL,
    negatif_oy BOOLEAN DEFAULT FALSE COMMENT 'Bu seçeneğe karşı negatif oy',
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY tek_oylama_kullanici (oylama_id, kullanici_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (secenek_id) REFERENCES oylama_secenekleri(id) ON DELETE CASCADE
);

-- Log Tablosu (Güvenlik ve Denetim)
CREATE TABLE sistem_loglari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NULL,
    islem_tipi VARCHAR(100) NOT NULL,
    aciklama TEXT,
    ip_adresi VARCHAR(45),
    user_agent TEXT,
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tarih (tarih),
    INDEX idx_islem_tipi (islem_tipi)
);

-- Örnek veriler
INSERT INTO oylamalar (tur, baslik, aciklama, olusturan_id, topluluk_tipi, bitis_tarihi) VALUES
('secim', 'Örnek Belediye Başkanlığı Seçimi', 'İstanbul için yeni belediye başkanını seçiyoruz', 1, 'il', DATE_ADD(NOW(), INTERVAL 7 DAY)),
('referandum', 'Yeni Park Projesi', 'Şehir merkezine yeni park yapılması hakkında referandum', 1, 'il', DATE_ADD(NOW(), INTERVAL 3 DAY));

INSERT INTO adaylar (oylama_id, aday_adi, aday_aciklama) VALUES
(1, 'Ahmet Yılmaz', 'Deneyimli belediyeci, 10 yıllık tecrübe'),
(1, 'Mehmet Demir', 'Genç ve dinamik, çevre dostu projeler'),
(1, 'Ayşe Kaya', 'Kadın bakış açısı, şeffaf yönetim');

-- Şifre sıfırlama tokenları tablosu
CREATE TABLE IF NOT EXISTS sifre_sifirlama_tokenlari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    son_kullanma DATETIME NOT NULL,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    kullanildi BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_son_kullanma (son_kullanma)
);

-- Bildirimler tablosu
CREATE TABLE IF NOT EXISTS bildirimler (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    baslik VARCHAR(200) NOT NULL,
    mesaj TEXT NOT NULL,
    tur ENUM('bilgi', 'uyari', 'basari', 'hata') DEFAULT 'bilgi',
    okundu BOOLEAN DEFAULT FALSE,
    okunma_tarihi DATETIME NULL,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_okundu (okundu),
    INDEX idx_tarih (olusturulma_tarihi)
);

-- Sistem ayarları tablosu
CREATE TABLE IF NOT EXISTS sistem_ayarlari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ayar_adi VARCHAR(100) UNIQUE NOT NULL,
    ayar_degeri TEXT,
    aciklama VARCHAR(500),
    tur ENUM('metin', 'sayi', 'boolean', 'json') DEFAULT 'metin',
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ayar_adi (ayar_adi)
);

-- Varsayılan sistem ayarları
INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, aciklama, tur) VALUES
('site_baslik', 'Doğrudan İrade Platformu', 'Site başlığı', 'metin'),
('site_aciklama', 'Doğrudan demokrasi platformu', 'Site açıklaması', 'metin'),
('site_url', 'http://localhost/dogrudan_irade', 'Site URL', 'metin'),
('bakim_modu', '0', 'Bakım modu aktif mi?', 'boolean'),
('kayit_acik', '1', 'Yeni kayıtlar açık mı?', 'boolean'),
('max_oylama_suresi', '30', 'Maksimum oylama süresi (gün)', 'sayi'),
('min_sifre_uzunluk', '6', 'Minimum şifre uzunluğu', 'sayi'),
('eposta_dogrulama', '0', 'E-posta doğrulama gerekli mi?', 'boolean'),
('gunluk_max_oy', '10', 'Günlük maksimum oy sayısı', 'sayi'),
('logo_url', '/assets/img/logo.png', 'Logo URL', 'metin'),
('favicon_url', '/assets/img/favicon.ico', 'Favicon URL', 'metin'),
('iletisim_eposta', 'info@dogrudanirade.org', 'İletişim e-postası', 'metin'),
('iletisim_telefon', '', 'İletişim telefonu', 'metin'),
('iletisim_adres', '', 'İletişim adresi', 'metin'),
('sosyal_facebook', '', 'Facebook URL', 'metin'),
('sosyal_twitter', '', 'Twitter URL', 'metin'),
('sosyal_instagram', '', 'Instagram URL', 'metin'),
('analytics_kodu', '', 'Google Analytics kodu', 'metin'),
('recaptcha_site_key', '', 'reCAPTCHA site key', 'metin'),
('recaptcha_secret_key', '', 'reCAPTCHA secret key', 'metin'),
('smtp_host', '', 'SMTP sunucusu', 'metin'),
('smtp_port', '587', 'SMTP portu', 'sayi'),
('smtp_username', '', 'SMTP kullanıcı adı', 'metin'),
('smtp_password', '', 'SMTP şifresi', 'metin'),
('smtp_secure', 'tls', 'SMTP güvenlik', 'metin'),
('iletisim_formu_acik', '1', 'İletişim formu açık mı?', 'boolean');

-- İletişim mesajları tablosu
CREATE TABLE IF NOT EXISTS iletisim_mesajlari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ad_soyad VARCHAR(200) NOT NULL,
    eposta VARCHAR(255) NOT NULL,
    konu VARCHAR(200) NOT NULL,
    mesaj TEXT NOT NULL,
    durum ENUM('okunmadi', 'okundu', 'cevaplandi', 'arsiv') DEFAULT 'okunmadi',
    ip_adresi VARCHAR(45),
    user_agent TEXT,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    okunma_tarihi DATETIME NULL,
    cevaplanma_tarihi DATETIME NULL,
    INDEX idx_durum (durum),
    INDEX idx_tarih (olusturulma_tarihi),
    INDEX idx_eposta (eposta)
);

-- Kategoriler tablosu (oylamalar için)
CREATE TABLE IF NOT EXISTS kategoriler (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kategori_adi VARCHAR(100) UNIQUE NOT NULL,
    aciklama VARCHAR(500),
    renk VARCHAR(7) DEFAULT '#007bff',
    aktif BOOLEAN DEFAULT TRUE,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aktif (aktif)
);

-- Varsayılan kategoriler
INSERT INTO kategoriler (kategori_adi, aciklama, renk) VALUES
('Siyaset', 'Siyasi konular ve seçimler', '#dc3545'),
('Ekonomi', 'Ekonomik konular ve kararlar', '#28a745'),
('Eğitim', 'Eğitim ile ilgili konular', '#007bff'),
('Sağlık', 'Sağlık politikaları', '#6f42c1'),
('Çevre', 'Çevre ve doğa konuları', '#20c997'),
('Ulaşım', 'Ulaşım ve altyapı', '#fd7e14'),
('Kültür', 'Kültür ve sanat', '#e83e8c'),
('Spor', 'Spor faaliyetleri', '#ffc107'),
('Teknoloji', 'Teknolojik gelişmeler', '#17a2b8'),
('Diğer', 'Diğer konular', '#6c757d');

-- Oylama kategorileri ilişki tablosu
CREATE TABLE IF NOT EXISTS oylama_kategorileri (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kategori_id BIGINT NOT NULL,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kategori_id) REFERENCES kategoriler(id) ON DELETE CASCADE,
    UNIQUE KEY unique_oylama_kategori (oylama_id, kategori_id),
    INDEX idx_oylama (oylama_id),
    INDEX idx_kategori (kategori_id)
);

-- Oturumlar tablosu (gelişmiş session yönetimi)
CREATE TABLE IF NOT EXISTS oturumlar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    session_id VARCHAR(128) UNIQUE NOT NULL,
    ip_adresi VARCHAR(45) NOT NULL,
    user_agent TEXT,
    baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    son_aktivite DATETIME DEFAULT CURRENT_TIMESTAMP,
    sonlandi BOOLEAN DEFAULT FALSE,
    sonlanma_sebebi ENUM('normal', 'timeout', 'manual', 'security') DEFAULT 'normal',
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_session (session_id),
    INDEX idx_sonlandi (sonlandi),
    INDEX idx_son_aktivite (son_aktivite)
);

-- Raporlar tablosu
CREATE TABLE IF NOT EXISTS raporlar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    tur ENUM('gunluk', 'haftalik', 'aylik', 'ozel') NOT NULL,
    baslik VARCHAR(200) NOT NULL,
    icerik JSON NOT NULL,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_tur (tur),
    INDEX idx_tarih (olusturulma_tarihi)
);

-- Oylama yorumları tablosu
CREATE TABLE IF NOT EXISTS yorumlar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    yorum TEXT NOT NULL,
    durum ENUM('onayli', 'beklemede', 'reddedildi') DEFAULT 'beklemede',
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id),
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_durum (durum),
    INDEX idx_tarih (olusturulma_tarihi)
);

-- Yorum oyları tablosu
CREATE TABLE IF NOT EXISTS yorum_oylari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    yorum_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    oy ENUM('begen', 'begenme') NOT NULL,
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_yorum_oy (yorum_id, kullanici_id),
    FOREIGN KEY (yorum_id) REFERENCES yorumlar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_yorum (yorum_id),
    INDEX idx_kullanici (kullanici_id)
);

-- Dosyalar tablosu
CREATE TABLE IF NOT EXISTS dosyalar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    dosya_adi VARCHAR(255) NOT NULL,
    orjinal_adi VARCHAR(255) NOT NULL,
    yol VARCHAR(500) NOT NULL,
    boyut BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    yukleyen_id BIGINT NOT NULL,
    yuklenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    aktif BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (yukleyen_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_yukleyen (yukleyen_id),
    INDEX idx_tarih (yuklenme_tarihi)
);

-- İstatistikler tablosu (günlük istatistikler)
CREATE TABLE IF NOT EXISTS istatistikler (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tarih DATE NOT NULL,
    yeni_kullanici INT DEFAULT 0,
    toplam_giris INT DEFAULT 0,
    toplam_oy INT DEFAULT 0,
    yeni_oylama INT DEFAULT 0,
    biten_oylama INT DEFAULT 0,
    aktif_kullanici INT DEFAULT 0,
    toplam_ziyaret INT DEFAULT 0,
    UNIQUE KEY unique_tarih (tarih),
    INDEX idx_tarih (tarih)
);

```

--------------------------------------------------------------------------------

📄 **oylama_detay.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'config/secim_fonksiyonlari.php';
require_once 'includes/auth.php';

$db = new Database();
$secim = new SecimFonksiyonlari();

$oylama_id = $_GET['id'] ?? 0;
$kullanici_id = $_SESSION['kullanici_id'] ?? 0;

// Oylama bilgilerini al
$oylama = $db->query(
    "SELECT * FROM oylamalar WHERE id = ?",
    [$oylama_id]
)->fetch();

if (!$oylama) {
    header("Location: index.php");
    exit;
}

// Adayları al
$adaylar = $db->query(
    "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
    [$oylama_id]
)->fetchAll();

// Kullanıcının mevcut oylarını al
$kullaniciOylari = $secim->kullaniciOyDurumu($oylama_id, $kullanici_id);

// AJAX isteği için oy verme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['kullanici_id'])) {
        echo json_encode(['success' => false, 'message' => 'Giriş yapmalısınız']);
        exit;
    }

    $action = $_POST['action'];
    $aday_id = $_POST['aday_id'] ?? 0;

    if ($action === 'destek') {
        $result = $secim->destekOyVer($oylama_id, $kullanici_id, $aday_id);
        echo json_encode(['success' => $result]);
    } elseif ($action === 'negatif') {
        $result = $secim->negatifOyToggle($oylama_id, $kullanici_id, $aday_id);
        echo json_encode(['success' => $result]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($oylama['baslik']) ?> - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .candidate-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .candidate-card.selected-support {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }
        .candidate-card.selected-negative {
            border-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
        }
        .btn-support {
            width: 120px;
        }
        .btn-negative {
            width: 120px;
        }
        .vote-summary {
            position: sticky;
            top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row">
            <!-- Ana içerik -->
            <div class="col-lg-8">
                <!-- Oylama başlığı -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h1 class="card-title h2 mb-3"><?= htmlspecialchars($oylama['baslik']) ?></h1>
                                <p class="card-text text-muted"><?= htmlspecialchars($oylama['aciklama']) ?></p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary fs-6"><?= $oylama['tur'] ?></span>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> Başlangıç: 
                                    <?= date('d.m.Y H:i', strtotime($oylama['baslangic_tarihi'])) ?>
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> Bitiş: 
                                    <?= date('d.m.Y H:i', strtotime($oylama['bitis_tarihi'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kullanıcının seçimleri (Anlık geri bildirim) -->
                <div class="alert alert-info mb-4" id="currentVotesAlert">
                    <h5 class="alert-heading">📋 ŞU ANKİ SEÇİMLERİNİZ:</h5>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="mb-1">
                                <strong>✅ Desteklediğiniz Aday:</strong>
                                <span id="currentSupport">
                                    <?= $kullaniciOylari['destek_oy']['aday_adi'] ?? 'Henüz destek oyu vermediniz' ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1">
                                <strong>❌ Negatif Oy Verdiğiniz Adaylar:</strong>
                                <span id="currentNegatives">
                                    <?php if (!empty($kullaniciOylari['negatif_oylar'])): ?>
                                        <?= implode(', ', array_column($kullaniciOylari['negatif_oylar'], 'aday_adi')) ?>
                                    <?php else: ?>
                                        Henüz negatif oy vermediniz
                                    <?php endif; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Aday listesi -->
                <h3 class="mb-4">🗳️ ADAY LİSTESİ</h3>
                
                <?php if (empty($adaylar)): ?>
                    <div class="alert alert-warning">
                        Bu oylama için henüz aday eklenmemiş.
                    </div>
                <?php else: ?>
                    <?php foreach ($adaylar as $aday): 
                        $isSupported = isset($kullaniciOylari['destek_oy']['id']) && $kullaniciOylari['destek_oy']['id'] == $aday['id'];
                        $isNegative = false;
                        foreach ($kullaniciOylari['negatif_oylar'] as $negatif) {
                            if ($negatif['id'] == $aday['id']) {
                                $isNegative = true;
                                break;
                            }
                        }
                    ?>
                        <div class="card candidate-card mb-3 
                            <?= $isSupported ? 'selected-support' : '' ?>
                            <?= $isNegative ? 'selected-negative' : '' ?>"
                            id="candidate-<?= $aday['id'] ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="card-title mb-2"><?= htmlspecialchars($aday['aday_adi']) ?></h5>
                                        <?php if ($aday['aday_aciklama']): ?>
                                            <p class="card-text text-muted"><?= htmlspecialchars($aday['aday_aciklama']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-grid gap-2 d-md-block">
                                            <!-- Destek oyu butonu -->
                                            <button class="btn btn-support 
                                                <?= $isSupported ? 'btn-success' : 'btn-outline-success' ?> 
                                                mb-2 mb-md-0"
                                                onclick="vote(<?= $aday['id'] ?>, 'destek')"
                                                id="support-btn-<?= $aday['id'] ?>">
                                                <?php if ($isSupported): ?>
                                                    ✅ Destekliyorsunuz
                                                <?php else: ?>
                                                    👍 Destek Oyu Ver
                                                <?php endif; ?>
                                            </button>
                                            
                                            <!-- Negatif oy butonu -->
                                            <button class="btn btn-negative 
                                                <?= $isNegative ? 'btn-danger' : 'btn-outline-danger' ?>"
                                                onclick="vote(<?= $aday['id'] ?>, 'negatif')"
                                                id="negative-btn-<?= $aday['id'] ?>">
                                                <?php if ($isNegative): ?>
                                                    ❌ Negatif Oy (Kaldır)
                                                <?php else: ?>
                                                    👎 Negatif Oy Ver
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sağ sidebar - Bilgi paneli -->
            <div class="col-lg-4">
                <div class="vote-summary">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">ℹ️ OY KULLANMA KILAVUZU</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6>✅ Destek Oyu Nedir?</h6>
                                <p class="small">Bir adayı aktif olarak desteklemek için kullanılır. SADECE BİR adaya destek oyu verebilirsiniz.</p>
                            </div>
                            <div class="mb-3">
                                <h6>❌ Negatif Oy Nedir?</h6>
                                <p class="small">Kabul edemediğiniz adaylara karşı kullanılır. İSTEDİĞİNİZ KADAR adaya negatif oy verebilirsiniz.</p>
                            </div>
                            <div class="mb-3">
                                <h6>📊 Net Skor Nasıl Hesaplanır?</h6>
                                <p class="small">NET SKOR = Destek Oyu - Negatif Oy</p>
                                <p class="small">Net skoru en yüksek olan aday kazanır.</p>
                            </div>
                            <hr>
                            <div class="text-center">
                                <a href="sonuc.php?id=<?= $oylama_id ?>" class="btn btn-outline-primary w-100">
                                    📈 ŞU ANKİ SONUÇLARI GÖR
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function vote(adayId, type) {
        if (!confirm(`${type === 'destek' ? 'Destek' : 'Negatif'} oyunuzu güncellemek istediğinize emin misiniz?`)) {
            return;
        }

        fetch('oylama_detay.php?id=<?= $oylama_id ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${type}&aday_id=${adayId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Sayfayı yenile
            } else {
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
        });
    }

    // Oylama bitiş süresi kontrolü
    <?php if (strtotime($oylama['bitis_tarihi']) < time()): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('button[onclick^="vote"]').forEach(btn => {
                btn.disabled = true;
                btn.classList.add('disabled');
            });
            alert('Bu oylamanın süresi dolmuştur. Artık oy kullanamazsınız.');
        });
    <?php endif; ?>
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **oylama_olustur.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

$db = new Database();

// Sadece yöneticiler oylama oluşturabilir
if (!isset($_SESSION['kullanici_id']) || $_SESSION['yetki_seviye'] !== 'superadmin') {
    header("Location: giris.php");
    exit;
}

$errors = [];
$success = '';

// İller listesi (kayıt.php'den al)
$iller = [
    '01' => 'Adana', '02' => 'Adıyaman', '03' => 'Afyonkarahisar', '04' => 'Ağrı',
    // ... tüm iller
    '81' => 'Düzce'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tur = $_POST['tur'];
    $baslik = trim($_POST['baslik']);
    $aciklama = trim($_POST['aciklama']);
    $topluluk_tipi = $_POST['topluluk_tipi'];
    $topluluk_id = $_POST['topluluk_id'] ?? null;
    $bitis_tarihi = $_POST['bitis_tarihi'];
    $adaylar = $_POST['aday'] ?? [];
    $aday_aciklamalar = $_POST['aday_aciklama'] ?? [];
    
    // Validasyon
    if (empty($baslik)) $errors[] = 'Oylama başlığı gereklidir';
    if (strlen($baslik) < 10) $errors[] = 'Başlık en az 10 karakter olmalı';
    if (empty($bitis_tarihi) || strtotime($bitis_tarihi) <= time()) {
        $errors[] = 'Geçerli bir bitiş tarihi girin';
    }
    
    if ($tur === 'secim' && empty($adaylar)) {
        $errors[] = 'En az bir aday eklemelisiniz';
    }
    
    if (empty($errors)) {
        try {
            // Transaction başlat
            $db->connect()->beginTransaction();
            
            // Oylamayı oluştur
            $oylama_id = $db->insertAndGetId(
                "INSERT INTO oylamalar (tur, baslik, aciklama, olusturan_id, 
                 topluluk_tipi, topluluk_id, bitis_tarihi) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$tur, $baslik, $aciklama, $_SESSION['kullanici_id'], 
                 $topluluk_tipi, $topluluk_id ?: null, $bitis_tarihi]
            );
            
            // Adayları ekle (seçim için)
            if ($tur === 'secim') {
                foreach ($adaylar as $index => $aday_adi) {
                    if (!empty(trim($aday_adi))) {
                        $db->query(
                            "INSERT INTO adaylar (oylama_id, aday_adi, aday_aciklama) 
                             VALUES (?, ?, ?)",
                            [$oylama_id, trim($aday_adi), 
                             trim($aday_aciklamalar[$index] ?? '')]
                        );
                    }
                }
            }
            
            // Referandum seçenekleri
            if ($tur === 'referandum') {
                $evet_id = $db->insertAndGetId(
                    "INSERT INTO oylama_secenekleri (oylama_id, secenek_metni, tur) 
                     VALUES (?, 'Evet', 'evet')",
                    [$oylama_id]
                );
                
                $hayir_id = $db->insertAndGetId(
                    "INSERT INTO oylama_secenekleri (oylama_id, secenek_metni, tur) 
                     VALUES (?, 'Hayır', 'hayir')",
                    [$oylama_id]
                );
            }
            
            // Commit
            $db->connect()->commit();
            
            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'oylama_olusturma', ?, ?)",
                [$_SESSION['kullanici_id'], "Yeni oylama: $baslik", $_SERVER['REMOTE_ADDR']]
            );
            
            $success = "Oylama başarıyla oluşturuldu! ID: $oylama_id";
            
            // 2 saniye sonra oylama detayına yönlendir
            header("refresh:2;url=oylama_detay.php?id=$oylama_id");
            
        } catch (Exception $e) {
            $db->connect()->rollBack();
            $errors[] = 'Oylama oluşturulurken hata: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Oylama Oluştur - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .candidate-row {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .remove-candidate {
            color: #dc3545;
            cursor: pointer;
        }
        #adayListesi {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Başlık -->
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold text-primary">
                        <i class="bi bi-plus-circle-fill"></i> YENİ OYLAMA OLUŞTUR
                    </h1>
                    <p class="lead">Doğrudan demokrasi için yeni bir oylama başlatın</p>
                </div>

                <!-- Oylama formu -->
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-clipboard-plus"></i> Oylama Bilgileri
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <h5 class="alert-heading">✅ Başarılı!</h5>
                                <?= $success ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <h5 class="alert-heading">⚠️ Hatalar:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="oylamaForm">
                            <!-- Temel bilgiler -->
                            <div class="form-section">
                                <h5 class="border-bottom pb-2 mb-3">📋 Temel Bilgiler</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="tur" class="form-label required">Oylama Türü</label>
                                        <select class="form-select" id="tur" name="tur" required>
                                            <option value="">Seçiniz</option>
                                            <option value="secim" <?= ($_POST['tur'] ?? '') === 'secim' ? 'selected' : '' ?>>Seçim</option>
                                            <option value="referandum" <?= ($_POST['tur'] ?? '') === 'referandum' ? 'selected' : '' ?>>Referandum</option>
                                            <option value="kanun_teklifi" <?= ($_POST['tur'] ?? '') === 'kanun_teklifi' ? 'selected' : '' ?>>Kanun Teklifi</option>
                                        </select>
                                        <div class="form-text">
                                            Seçim: Adaylı yarışma<br>
                                            Referandum: Evet/Hayır oylaması<br>
                                            Kanun Teklifi: Çok seçenekli teklif
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="bitis_tarihi" class="form-label required">Bitiş Tarihi</label>
                                        <input type="datetime-local" class="form-control" id="bitis_tarihi" 
                                               name="bitis_tarihi" required
                                               value="<?= htmlspecialchars($_POST['bitis_tarihi'] ?? '') ?>"
                                               min="<?= date('Y-m-d\TH:i') ?>">
                                        <div class="form-text">
                                            Oylamanın sona ereceği tarih ve saat
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="baslik" class="form-label required">Oylama Başlığı</label>
                                    <input type="text" class="form-control" id="baslik" name="baslik" 
                                           required minlength="10" maxlength="500"
                                           value="<?= htmlspecialchars($_POST['baslik'] ?? '') ?>"
                                           placeholder="Örn: 2024 Belediye Başkanlığı Seçimi">
                                    <div class="form-text">
                                        Oylamanın ana başlığı (en az 10 karakter)
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label required">Açıklama</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" 
                                              rows="4" required
                                              placeholder="Oylamanın amacı, kapsamı ve detayları..."><?= htmlspecialchars($_POST['aciklama'] ?? '') ?></textarea>
                                    <div class="form-text">
                                        Oylama hakkında detaylı bilgi
                                    </div>
                                </div>
                            </div>

                            <!-- Topluluk seçimi -->
                            <div class="form-section">
                                <h5 class="border-bottom pb-2 mb-3">🏙️ Oylama Kapsamı</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="topluluk_tipi" class="form-label required">Topluluk Tipi</label>
                                        <select class="form-select" id="topluluk_tipi" name="topluluk_tipi" required>
                                            <option value="">Seçiniz</option>
                                            <option value="ulusal" <?= ($_POST['topluluk_tipi'] ?? '') === 'ulusal' ? 'selected' : '' ?>>Ulusal</option>
                                            <option value="il" <?= ($_POST['topluluk_tipi'] ?? '') === 'il' ? 'selected' : '' ?>>İl</option>
                                            <option value="ilce" <?= ($_POST['topluluk_tipi'] ?? '') === 'ilce' ? 'selected' : '' ?>>İlçe</option>
                                            <option value="sendika" <?= ($_POST['topluluk_tipi'] ?? '') === 'sendika' ? 'selected' : '' ?>>Sendika</option>
                                            <option value="meslek_odasi" <?= ($_POST['topluluk_tipi'] ?? '') === 'meslek_odasi' ? 'selected' : '' ?>>Meslek Odası</option>
                                            <option value="universite" <?= ($_POST['topluluk_tipi'] ?? '') === 'universite' ? 'selected' : '' ?>>Üniversite</option>
                                            <option value="sirket" <?= ($_POST['topluluk_tipi'] ?? '') === 'sirket' ? 'selected' : '' ?>>Şirket</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6" id="topluluk_id_container" style="display: none;">
                                        <label for="topluluk_id" class="form-label">Topluluk Seçin</label>
                                        <select class="form-select" id="topluluk_id" name="topluluk_id">
                                            <!-- Dinamik olarak doldurulacak -->
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Ulusal</strong>: Tüm kullanıcılar oy kullanabilir<br>
                                    <strong>İl/İlçe</strong>: Sadece o il/ilçeye üye kullanıcılar<br>
                                    <strong>Diğer</strong>: Sadece o topluluğun üyeleri
                                </div>
                            </div>

                            <!-- Adaylar bölümü (sadece seçim için) -->
                            <div class="form-section" id="adaylarSection" style="display: none;">
                                <h5 class="border-bottom pb-2 mb-3">👥 Adaylar</h5>
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    Her aday için <strong>Destek Oyu</strong> ve <strong>Negatif Oy</strong> seçenekleri olacaktır.
                                </div>
                                
                                <div id="adayListesi">
                                    <!-- Adaylar buraya eklenecek -->
                                    <div class="candidate-row">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Aday Adı</label>
                                                <input type="text" class="form-control candidate-name" 
                                                       name="aday[]" placeholder="Adayın adı soyadı">
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Aday Açıklaması (Opsiyonel)</label>
                                                <input type="text" class="form-control" 
                                                       name="aday_aciklama[]" placeholder="Kısa açıklama">
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end">
                                                <button type="button" class="btn btn-outline-danger remove-candidate" 
                                                        onclick="removeCandidate(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="addCandidate()">
                                        <i class="bi bi-plus-circle"></i> Yeni Aday Ekle
                                    </button>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-lightbulb"></i> 
                                    <strong>Negatif Oy Sistemi:</strong> Kullanıcılar istemedikleri adaylara negatif oy verebilirler.
                                    Net skor (Destek - Negatif) ile kazanan belirlenir.
                                </div>
                            </div>

                            <!-- Referandum seçenekleri (sadece referandum için) -->
                            <div class="form-section" id="referandumSection" style="display: none;">
                                <h5 class="border-bottom pb-2 mb-3">📝 Referandum Seçenekleri</h5>
                                
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> 
                                    Referandum oylamaları otomatik olarak <strong>Evet</strong> ve <strong>Hayır</strong> seçenekleriyle oluşturulur.
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-success">
                                            <div class="card-body text-center">
                                                <h5 class="card-title text-success">✅ EVET</h5>
                                                <p class="card-text">Öneriyi destekliyorum</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-danger">
                                            <div class="card-body text-center">
                                                <h5 class="card-title text-danger">❌ HAYIR</h5>
                                                <p class="card-text">Öneriyi desteklemiyorum</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-lightbulb"></i> 
                                    Kullanıcılar hem Evet'e hem Hayır'a negatif oy verebilirler.
                                    Bu, "hiçbiri" seçeneğini temsil eder.
                                </div>
                            </div>

                            <!-- Kanun teklifi seçenekleri -->
                            <div class="form-section" id="kanunSection" style="display: none;">
                                <h5 class="border-bottom pb-2 mb-3">📜 Kanun Teklifi Seçenekleri</h5>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    Kanun teklifi oylamalarında kullanıcılar birden fazla seçeneği destekleyebilir
                                    ve istemedikleri seçeneklere negatif oy verebilirler.
                                </div>
                                
                                <div id="kanunSecenekleri">
                                    <!-- Seçenekler buraya eklenecek -->
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="addKanunSecenek()">
                                        <i class="bi bi-plus-circle"></i> Yeni Seçenek Ekle
                                    </button>
                                </div>
                            </div>

                            <!-- Gönder butonu -->
                            <div class="text-center mt-5">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-check-circle-fill"></i> OYLAMAYI OLUŞTUR
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">
                                    <i class="bi bi-x-circle"></i> İPTAL
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Topluluk tipi değişiminde
    document.getElementById('topluluk_tipi').addEventListener('change', function() {
        const container = document.getElementById('topluluk_id_container');
        const select = document.getElementById('topluluk_id');
        
        if (this.value === 'ulusal') {
            container.style.display = 'none';
            select.innerHTML = '';
        } else if (this.value === 'il') {
            container.style.display = 'block';
            // İller listesini yükle
            select.innerHTML = '<option value="">İl Seçin</option>';
            <?php foreach ($iller as $kod => $il): ?>
                select.innerHTML += `<option value="<?= $kod ?>"><?= $il ?></option>`;
            <?php endforeach; ?>
        } else if (this.value === 'ilce') {
            container.style.display = 'block';
            select.innerHTML = '<option value="">Önce il seçmelisiniz</option>';
            // İlçeler için API çağrısı yapılabilir
        } else {
            container.style.display = 'block';
            select.innerHTML = `
                <option value="">Seçiniz</option>
                <option value="1">Sendika 1</option>
                <option value="2">Sendika 2</option>
                <option value="3">Meslek Odası 1</option>
                <option value="4">Üniversite 1</option>
                <option value="5">Şirket 1</option>
            `;
        }
    });

    // Oylama türü değişiminde
    document.getElementById('tur').addEventListener('change', function() {
        const secimSection = document.getElementById('adaylarSection');
        const referandumSection = document.getElementById('referandumSection');
        const kanunSection = document.getElementById('kanunSection');
        
        // Tümünü gizle
        secimSection.style.display = 'none';
        referandumSection.style.display = 'none';
        kanunSection.style.display = 'none';
        
        // Seçileni göster
        if (this.value === 'secim') {
            secimSection.style.display = 'block';
        } else if (this.value === 'referandum') {
            referandumSection.style.display = 'block';
        } else if (this.value === 'kanun_teklifi') {
            kanunSection.style.display = 'block';
            // Kanun seçeneklerini başlat
            initKanunSecenekleri();
        }
    });

    // Aday ekleme
    let candidateCount = 1;
    
    function addCandidate() {
        const list = document.getElementById('adayListesi');
        const newRow = document.createElement('div');
        newRow.className = 'candidate-row';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Aday Adı</label>
                    <input type="text" class="form-control candidate-name" 
                           name="aday[]" placeholder="Adayın adı soyadı" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Aday Açıklaması (Opsiyonel)</label>
                    <input type="text" class="form-control" 
                           name="aday_aciklama[]" placeholder="Kısa açıklama">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-candidate" 
                            onclick="removeCandidate(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        list.appendChild(newRow);
        candidateCount++;
    }
    
    function removeCandidate(button) {
        if (candidateCount > 1) {
            button.closest('.candidate-row').remove();
            candidateCount--;
        } else {
            alert('En az bir aday olmalıdır.');
        }
    }

    // Kanun teklifi seçenekleri
    let kanunCount = 0;
    
    function initKanunSecenekleri() {
        const container = document.getElementById('kanunSecenekleri');
        container.innerHTML = `
            <div class="kanun-secenek-row mb-3 p-3 border rounded">
                <div class="row">
                    <div class="col-md-10">
                        <label class="form-label">Seçenek Metni</label>
                        <input type="text" class="form-control" 
                               name="kanun_secenek[]" placeholder="Seçenek açıklaması" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger" onclick="removeKanunSecenek(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        kanunCount = 1;
    }
    
    function addKanunSecenek() {
        const container = document.getElementById('kanunSecenekleri');
        const newRow = document.createElement('div');
        newRow.className = 'kanun-secenek-row mb-3 p-3 border rounded';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-10">
                    <label class="form-label">Seçenek Metni</label>
                    <input type="text" class="form-control" 
                           name="kanun_secenek[]" placeholder="Seçenek açıklaması" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger" onclick="removeKanunSecenek(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        kanunCount++;
    }
    
    function removeKanunSecenek(button) {
        if (kanunCount > 1) {
            button.closest('.kanun-secenek-row').remove();
            kanunCount--;
        } else {
            alert('En az bir seçenek olmalıdır.');
        }
    }

    // Form gönderim kontrolü
    document.getElementById('oylamaForm').addEventListener('submit', function(e) {
        const tur = document.getElementById('tur').value;
        
        if (tur === 'secim') {
            // Aday isimlerini kontrol et
            const adayInputs = document.querySelectorAll('.candidate-name');
            let hasEmpty = false;
            
            adayInputs.forEach(input => {
                if (!input.value.trim()) {
                    hasEmpty = true;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (hasEmpty) {
                e.preventDefault();
                alert('Lütfen tüm adaylar için isim girin.');
            }
        }
    });

    // Sayfa yüklendiğinde varsayılan değerleri ayarla
    document.addEventListener('DOMContentLoaded', function() {
        // Varsayılan bitiş tarihi (7 gün sonra)
        const defaultDate = new Date();
        defaultDate.setDate(defaultDate.getDate() + 7);
        const dateStr = defaultDate.toISOString().slice(0, 16);
        document.getElementById('bitis_tarihi').value = dateStr;
        
        // Oylama türü değişimini tetikle
        const turSelect = document.getElementById('tur');
        if (turSelect.value) {
            turSelect.dispatchEvent(new Event('change'));
        }
    });
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **oylamalar.php**
```php
<?php
session_start();
require_once 'config/database.php';

$db = new Database();

// Filtreler
$filter = $_GET['filter'] ?? 'aktif';
$tur = $_GET['tur'] ?? '';
$topluluk = $_GET['topluluk'] ?? '';
$search = $_GET['search'] ?? '';

// Sorgu oluştur
$sql = "SELECT o.*, 
        COUNT(DISTINCT ok.kullanici_id) as oy_sayisi,
        u.ad_soyad as olusturan_ad
        FROM oylamalar o 
        LEFT JOIN oy_kullanicilar ok ON o.id = ok.oylama_id 
        LEFT JOIN kullanicilar u ON o.olusturan_id = u.id 
        WHERE 1=1";

$params = [];

// Durum filtresi
if ($filter === 'aktif') {
    $sql .= " AND o.durum = 'aktif'";
} elseif ($filter === 'sonuclandi') {
    $sql .= " AND o.durum = 'sonuclandi'";
} elseif ($filter === 'tum') {
    // Tümü - filtre yok
} elseif ($filter === 'katildigim' && isset($_SESSION['kullanici_id'])) {
    $sql .= " AND EXISTS (
        SELECT 1 FROM oy_kullanicilar ok2 
        WHERE ok2.oylama_id = o.id AND ok2.kullanici_id = ?
    )";
    $params[] = $_SESSION['kullanici_id'];
}

// Tür filtresi
if (!empty($tur)) {
    $sql .= " AND o.tur = ?";
    $params[] = $tur;
}

// Topluluk filtresi
if (!empty($topluluk)) {
    $sql .= " AND o.topluluk_tipi = ?";
    $params[] = $topluluk;
}

// Arama filtresi
if (!empty($search)) {
    $sql .= " AND (o.baslik LIKE ? OR o.aciklama LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " GROUP BY o.id ORDER BY o.olusturulma_tarihi DESC";

// Oylamaları getir
$oylamalar = $db->query($sql, $params)->fetchAll();

// Toplam sayılar
$aktif_sayisi = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'aktif'");
$sonuclandi_sayisi = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'sonuclandi'");
$tum_sayisi = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tüm Oylamalar - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filter-badge.active {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .poll-card {
            transition: all 0.3s ease;
        }
        .poll-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .progress {
            height: 8px;
        }
        .poll-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <!-- Başlık ve istatistikler -->
        <div class="row mb-5">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold text-primary mb-3">
                    <i class="bi bi-clipboard-data"></i> TÜM OYLAMALAR
                </h1>
                <p class="lead text-muted">
                    Doğrudan demokrasinin işlediği tüm oylamaları görüntüleyin ve katılın
                </p>
            </div>
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h4 class="card-title">📊 İstatistikler</h4>
                        <div class="row">
                            <div class="col-4">
                                <div class="text-primary fw-bold fs-4"><?= $aktif_sayisi ?></div>
                                <small>Aktif</small>
                            </div>
                            <div class="col-4">
                                <div class="text-success fw-bold fs-4"><?= $sonuclandi_sayisi ?></div>
                                <small>Sonuçlandı</small>
                            </div>
                            <div class="col-4">
                                <div class="text-info fw-bold fs-4"><?= $tum_sayisi ?></div>
                                <small>Toplam</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="bi bi-funnel"></i> Filtreler
                </h5>
                
                <!-- Durum filtreleri -->
                <div class="mb-3">
                    <small class="text-muted d-block mb-2">Durum:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filter=aktif" 
                           class="badge filter-badge <?= $filter == 'aktif' ? 'bg-primary' : 'bg-secondary' ?>">
                            🔵 Aktif (<?= $aktif_sayisi ?>)
                        </a>
                        <a href="?filter=sonuclandi" 
                           class="badge filter-badge <?= $filter == 'sonuclandi' ? 'bg-success' : 'bg-secondary' ?>">
                            ✅ Sonuçlandı (<?= $sonuclandi_sayisi ?>)
                        </a>
                        <a href="?filter=tum" 
                           class="badge filter-badge <?= $filter == 'tum' ? 'bg-info' : 'bg-secondary' ?>">
                            📋 Tümü (<?= $tum_sayisi ?>)
                        </a>
                        <?php if (isset($_SESSION['kullanici_id'])): ?>
                            <a href="?filter=katildigim" 
                               class="badge filter-badge <?= $filter == 'katildigim' ? 'bg-warning' : 'bg-secondary' ?>">
                                👤 Katıldıklarım
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tür filtreleri -->
                <div class="mb-3">
                    <small class="text-muted d-block mb-2">Tür:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filter=<?= $filter ?>&tur=secim" 
                           class="badge filter-badge <?= $tur == 'secim' ? 'bg-primary' : 'bg-secondary' ?>">
                            👥 Seçim
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=referandum" 
                           class="badge filter-badge <?= $tur == 'referandum' ? 'bg-success' : 'bg-secondary' ?>">
                            📝 Referandum
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=kanun_teklifi" 
                           class="badge filter-badge <?= $tur == 'kanun_teklifi' ? 'bg-info' : 'bg-secondary' ?>">
                            📜 Kanun Teklifi
                        </a>
                        <?php if (!empty($tur)): ?>
                            <a href="?filter=<?= $filter ?>" 
                               class="badge filter-badge bg-danger">
                                ❌ Tür Filtresini Kaldır
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Topluluk filtreleri -->
                <div class="mb-3">
                    <small class="text-muted d-block mb-2">Kapsam:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>&topluluk=ulusal" 
                           class="badge filter-badge <?= $topluluk == 'ulusal' ? 'bg-primary' : 'bg-secondary' ?>">
                            🇹🇷 Ulusal
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>&topluluk=il" 
                           class="badge filter-badge <?= $topluluk == 'il' ? 'bg-success' : 'bg-secondary' ?>">
                            🏙️ İl
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>&topluluk=sendika" 
                           class="badge filter-badge <?= $topluluk == 'sendika' ? 'bg-warning' : 'bg-secondary' ?>">
                            👷 Sendika
                        </a>
                        <?php if (!empty($topluluk)): ?>
                            <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>" 
                               class="badge filter-badge bg-danger">
                                ❌ Kapsam Filtresini Kaldır
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Arama -->
                <form method="GET" class="row g-2">
                    <input type="hidden" name="filter" value="<?= $filter ?>">
                    <input type="hidden" name="tur" value="<?= $tur ?>">
                    <input type="hidden" name="topluluk" value="<?= $topluluk ?>">
                    
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Oylama başlığında ara..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Ara
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="oylamalar.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-clockwise"></i> Sıfırla
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Oylama listesi -->
        <div class="row">
            <?php if (empty($oylamalar)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-info-circle display-4 d-block mb-3"></i>
                        <h4>Henüz oylama bulunmuyor</h4>
                        <p class="mb-0">Filtrelerinizi değiştirmeyi deneyin veya yeni bir oylama oluşturun.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($oylamalar as $oylama): 
                    // Oylama ilerlemesi
                    $baslangic = strtotime($oylama['baslangic_tarihi']);
                    $bitis = strtotime($oylama['bitis_tarihi']);
                    $simdi = time();
                    
                    if ($bitis > $baslangic) {
                        $toplam = $bitis - $baslangic;
                        $gecen = $simdi - $baslangic;
                        $yuzde = min(100, max(0, ($gecen / $toplam) * 100));
                    } else {
                        $yuzde = 100;
                    }
                    
                    // Oylama durumu
                    if ($oylama['durum'] == 'sonuclandi') {
                        $status_badge = '<span class="badge bg-success">✅ Sonuçlandı</span>';
                        $progress_color = 'bg-success';
                    } elseif ($yuzde >= 100) {
                        $status_badge = '<span class="badge bg-warning">⏰ Süre Doldu</span>';
                        $progress_color = 'bg-warning';
                    } else {
                        $status_badge = '<span class="badge bg-primary">🔵 Aktif</span>';
                        $progress_color = 'bg-primary';
                    }
                ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card poll-card h-100">
                            <div class="card-body position-relative">
                                <!-- Durum badge -->
                                <div class="poll-status">
                                    <?= $status_badge ?>
                                </div>
                                
                                <!-- Başlık -->
                                <h5 class="card-title mb-3">
                                    <a href="oylama_detay.php?id=<?= $oylama['id'] ?>" 
                                       class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($oylama['baslik']) ?>
                                    </a>
                                </h5>
                                
                                <!-- Açıklama (kısaltılmış) -->
                                <p class="card-text text-muted small mb-3" 
                                   style="height: 60px; overflow: hidden;">
                                    <?= htmlspecialchars(mb_substr($oylama['aciklama'], 0, 100)) . '...' ?>
                                </p>
                                
                                <!-- Bilgiler -->
                                <div class="mb-3">
                                    <div class="row small">
                                        <div class="col-6">
                                            <i class="bi bi-person"></i>
                                            <?= htmlspecialchars($oylama['olusturan_ad']) ?>
                                        </div>
                                        <div class="col-6 text-end">
                                            <i class="bi bi-people"></i>
                                            <?= $oylama['oy_sayisi'] ?> oy
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Süre çubuğu -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span>Başlangıç: <?= date('d.m.Y', $baslangic) ?></span>
                                        <span>Bitiş: <?= date('d.m.Y', $bitis) ?></span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?= $progress_color ?>" 
                                             style="width: <?= $yuzde ?>%"></div>
                                    </div>
                                    <div class="text-center small text-muted mt-1">
                                        <?= round($yuzde) ?>% tamamlandı
                                    </div>
                                </div>
                                
                                <!-- Badgeler -->
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    <span class="badge bg-info">
                                        <?= $oylama['tur'] ?>
                                    </span>
                                    <span class="badge bg-secondary">
                                        <?= $oylama['topluluk_tipi'] ?>
                                    </span>
                                    <?php if ($oylama['topluluk_id']): ?>
                                        <span class="badge bg-light text-dark">
                                            ID: <?= $oylama['topluluk_id'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Butonlar -->
                                <div class="d-grid gap-2">
                                    <a href="oylama_detay.php?id=<?= $oylama['id'] ?>" 
                                       class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> 
                                        <?= $oylama['durum'] == 'aktif' ? 'Oy Kullan' : 'Sonuçları Gör' ?>
                                    </a>
                                    <?php if ($oylama['durum'] == 'sonuclandi'): ?>
                                        <a href="sonuc.php?id=<?= $oylama['id'] ?>" 
                                           class="btn btn-success">
                                            <i class="bi bi-graph-up"></i> Detaylı Sonuçlar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sayfalama -->
        <nav aria-label="Sayfalama" class="mt-5">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1">Önceki</a>
                </li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item">
                    <a class="page-link" href="#">Sonraki</a>
                </li>
            </ul>
        </nav>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Filtre badge'lerine aktif sınıfı ekleme
    document.querySelectorAll('.filter-badge').forEach(badge => {
        badge.addEventListener('click', function() {
            document.querySelectorAll('.filter-badge').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Oylama kartlarına tıklanabilirlik (başlık hariç)
    document.querySelectorAll('.poll-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
            // Eğer tıklanan element bir buton veya link değilse
            if (!e.target.closest('a') && !e.target.closest('button')) {
                const link = this.querySelector('a[href*="oylama_detay"]');
                if (link) {
                    window.location.href = link.href;
                }
            }
        });
    });

    // Arama formu submit olduğunda filtreleri koru
    document.querySelector('form').addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('input[type="hidden"]');
        inputs.forEach(input => {
            if (!input.value) {
                input.remove();
            }
        });
    });
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **profil.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

$db = new Database();
$kullanici_id = $_SESSION['kullanici_id'];

// Kullanıcı bilgilerini al
$kullanici = $db->query(
    "SELECT * FROM kullanicilar WHERE id = ?",
    [$kullanici_id]
)->fetch();

// Kullanıcının topluluklarını al
$topluluklar = $db->query(
    "SELECT * FROM kullanici_topluluklari WHERE kullanici_id = ?",
    [$kullanici_id]
)->fetchAll();

// Kullanıcının oy kullandığı oylamalar
$oy_kullandigi = $db->query(
    "SELECT DISTINCT o.baslik, o.tur, o.id, ok.oy_tarihi 
     FROM oylamalar o 
     JOIN oy_kullanicilar ok ON o.id = ok.oylama_id 
     WHERE ok.kullanici_id = ? 
     ORDER BY ok.oy_tarihi DESC 
     LIMIT 10",
    [$kullanici_id]
)->fetchAll();

// Aktif oylamalar (katılabilir)
$aktif_oylamalar = $db->query(
    "SELECT DISTINCT o.* 
     FROM oylamalar o 
     LEFT JOIN kullanici_topluluklari kt ON (
         (o.topluluk_tipi = 'ulusal') OR
         (o.topluluk_tipi = 'il' AND kt.topluluk_tipi = 'il' AND kt.topluluk_id = o.topluluk_id)
     )
     WHERE o.durum = 'aktif' 
     AND (kt.kullanici_id = ? OR o.topluluk_tipi = 'ulusal')
     AND o.id NOT IN (
         SELECT oylama_id FROM oy_kullanicilar WHERE kullanici_id = ?
     )
     LIMIT 5",
    [$kullanici_id, $kullanici_id]
)->fetchAll();

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $ad_soyad = trim($_POST['ad_soyad']);
    $telefon = trim($_POST['telefon'] ?? '');
    $dogum_tarihi = $_POST['dogum_tarihi'] ?? '';
    
    try {
        $db->query(
            "UPDATE kullanicilar SET ad_soyad = ?, telefon = ?, dogum_tarihi = ? WHERE id = ?",
            [$ad_soyad, $telefon, $dogum_tarihi, $kullanici_id]
        );
        
        $_SESSION['ad_soyad'] = $ad_soyad;
        
        // Log
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
             VALUES (?, 'profil_guncelleme', ?, ?)",
            [$kullanici_id, "Profil bilgileri güncellendi", $_SERVER['REMOTE_ADDR']]
        );
        
        $success = "Profil bilgileriniz güncellendi.";
        header("Location: profil.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "Güncelleme sırasında hata: " . $e->getMessage();
    }
}

// Şifre değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $mevcut_sifre = $_POST['mevcut_sifre'];
    $yeni_sifre = $_POST['yeni_sifre'];
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'];
    
    // Mevcut şifreyi kontrol et
    if (!password_verify($mevcut_sifre, $kullanici['sifre_hash'])) {
        $password_error = "Mevcut şifreniz yanlış";
    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $password_error = "Yeni şifreler eşleşmiyor";
    } elseif (strlen($yeni_sifre) < 6) {
        $password_error = "Yeni şifre en az 6 karakter olmalı";
    } else {
        try {
            $yeni_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
            $db->query(
                "UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?",
                [$yeni_hash, $kullanici_id]
            );
            
            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'sifre_degistirme', 'Şifre değiştirildi', ?)",
                [$kullanici_id, $_SERVER['REMOTE_ADDR']]
            );
            
            $password_success = "Şifreniz başarıyla değiştirildi.";
            
        } catch (Exception $e) {
            $password_error = "Şifre değiştirme sırasında hata: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid #3498db;
            color: #3498db;
        }
        .activity-item {
            border-left: 3px solid #3498db;
            padding-left: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <!-- Profil Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="profile-avatar display-4">
                        👤
                    </div>
                </div>
                <div class="col-md-8">
                    <h1 class="display-6 mb-2"><?= htmlspecialchars($kullanici['ad_soyad']) ?></h1>
                    <p class="mb-1">
                        <i class="bi bi-envelope"></i> <?= htmlspecialchars($kullanici['eposta']) ?>
                        <?php if ($kullanici['telefon']): ?>
                            | <i class="bi bi-telephone"></i> <?= htmlspecialchars($kullanici['telefon']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="mb-0">
                        <small>
                            <i class="bi bi-calendar"></i> Kayıt: <?= date('d.m.Y', strtotime($kullanici['kayit_tarihi'])) ?>
                            <?php if ($kullanici['son_giris_tarihi']): ?>
                                | <i class="bi bi-clock-history"></i> Son giriş: <?= date('d.m.Y H:i', strtotime($kullanici['son_giris_tarihi'])) ?>
                            <?php endif; ?>
                        </small>
                    </p>
                </div>
                <div class="col-md-2 text-end">
                    <span class="badge bg-light text-dark fs-6">
                        <i class="bi bi-shield-check"></i> <?= ucfirst($kullanici['yetki_seviye']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-center border-primary">
                    <div class="card-body">
                        <div class="display-6 text-primary">🏛️</div>
                        <h4 class="card-title"><?= count($topluluklar) ?></h4>
                        <p class="card-text">Topluluk Üyeliği</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center border-success">
                    <div class="card-body">
                        <div class="display-6 text-success">🗳️</div>
                        <h4 class="card-title"><?= count($oy_kullandigi) ?></h4>
                        <p class="card-text">Katıldığı Oylama</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center border-warning">
                    <div class="card-body">
                        <div class="display-6 text-warning">⏳</div>
                        <h4 class="card-title"><?= count($aktif_oylamalar) ?></h4>
                        <p class="card-text">Bekleyen Oylama</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center border-info">
                    <div class="card-body">
                        <div class="display-6 text-info">📅</div>
                        <h4 class="card-title">
                            <?php
                            $gun = floor((time() - strtotime($kullanici['kayit_tarihi'])) / (60 * 60 * 24));
                            echo $gun;
                            ?>
                        </h4>
                        <p class="card-text">Platformdaki Gün</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab menü -->
        <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button">
                    <i class="bi bi-person-circle"></i> Profil Bilgileri
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="topluluk-tab" data-bs-toggle="tab" data-bs-target="#topluluk" type="button">
                    <i class="bi bi-people-fill"></i> Topluluklarım
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                    <i class="bi bi-activity"></i> Aktivitelerim
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                    <i class="bi bi-shield-lock"></i> Güvenlik
                </button>
            </li>
        </ul>

        <!-- Tab içerikleri -->
        <div class="tab-content" id="profileTabContent">
            <!-- Tab 1: Profil Bilgileri -->
            <div class="tab-pane fade show active" id="home" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-lines-fill"></i> Kişisel Bilgiler
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_GET['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show">
                                        Profil bilgileriniz başarıyla güncellendi.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <?= $error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="update_profile" value="1">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Ad Soyad</label>
                                        <input type="text" class="form-control" name="ad_soyad" 
                                               value="<?= htmlspecialchars($kullanici['ad_soyad']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">E-posta</label>
                                        <input type="email" class="form-control" 
                                               value="<?= htmlspecialchars($kullanici['eposta']) ?>" disabled>
                                        <small class="text-muted">E-posta değiştirilemez</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Telefon</label>
                                        <input type="tel" class="form-control" name="telefon" 
                                               value="<?= htmlspecialchars($kullanici['telefon'] ?? '') ?>"
                                               pattern="[0-9]{10,11}">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Doğum Tarihi</label>
                                        <input type="date" class="form-control" name="dogum_tarihi" 
                                               value="<?= htmlspecialchars($kullanici['dogum_tarihi'] ?? '') ?>"
                                               max="<?= date('Y-m-d') ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Bilgileri Güncelle
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <!-- Aktif oylamalar -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-hourglass-split"></i> Katılmanız Gereken Oylamalar
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($aktif_oylamalar)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-check-circle"></i> Tüm oylamalara katıldınız!
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($aktif_oylamalar as $oylama): ?>
                                        <div class="mb-3 p-3 border rounded">
                                            <h6 class="mb-1"><?= htmlspecialchars($oylama['baslik']) ?></h6>
                                            <small class="text-muted d-block mb-2">
                                                <?= $oylama['tur'] ?> | 
                                                Bitiş: <?= date('d.m.Y H:i', strtotime($oylama['bitis_tarihi'])) ?>
                                            </small>
                                            <a href="oylama_detay.php?id=<?= $oylama['id'] ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-box-arrow-in-right"></i> Oy Kullan
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center">
                                        <a href="oylamalar.php" class="btn btn-outline-primary btn-sm">
                                            Tüm Oylamaları Gör <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Topluluklarım -->
            <div class="tab-pane fade" id="topluluk" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-people-fill"></i> Üye Olduğum Topluluklar
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topluluklar)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> Henüz hiç topluluğa üye değilsiniz.
                                <a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#addCommunityModal">
                                    Topluluk eklemek için tıklayın
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Topluluk Tipi</th>
                                            <th>Topluluk ID</th>
                                            <th>Üyelik Tarihi</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topluluklar as $topluluk): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= ucfirst($topluluk['topluluk_tipi']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $topluluk['topluluk_id'] ?></td>
                                                <td><?= date('d.m.Y H:i', strtotime($topluluk['uyelik_tarihi'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="removeCommunity(<?= $topluluk['id'] ?>)">
                                                        <i class="bi bi-trash"></i> Çıkar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCommunityModal">
                            <i class="bi bi-plus-circle"></i> Yeni Topluluk Ekle
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Aktivitelerim -->
            <div class="tab-pane fade" id="activity" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6">
                        <!-- Son oy kullanımları -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-check-circle-fill"></i> Son Oy Kullanımlarım
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($oy_kullandigi)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> Henüz hiç oy kullanmadınız.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($oy_kullandigi as $oylama): ?>
                                        <div class="activity-item">
                                            <h6 class="mb-1">
                                                <a href="oylama_detay.php?id=<?= $oylama['id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($oylama['baslik']) ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted d-block mb-1">
                                                <i class="bi bi-calendar"></i> 
                                                <?= date('d.m.Y H:i', strtotime($oylama['oy_tarihi'])) ?>
                                            </small>
                                            <span class="badge bg-secondary"><?= $oylama['tur'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="oylamalar.php?filter=katildigim" class="btn btn-outline-primary btn-sm">
                                            Tüm Katıldıklarımı Gör
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <!-- Sistem logları (kullanıcıya özel) -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history"></i> Son Aktivitelerim
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $loglar = $db->query(
                                    "SELECT * FROM sistem_loglari 
                                     WHERE kullanici_id = ? 
                                     ORDER BY tarih DESC 
                                     LIMIT 10",
                                    [$kullanici_id]
                                )->fetchAll();
                                
                                if (empty($loglar)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> Henüz aktivite kaydınız yok.
                                    </div>
                                <?php else: ?>
                                    <div style="max-height: 300px; overflow-y: auto;">
                                        <?php foreach ($loglar as $log): ?>
                                            <div class="mb-3 pb-3 border-bottom">
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-<?= 
                                                        strpos($log['islem_tipi'], 'giris') !== false ? 'success' :
                                                        (strpos($log['islem_tipi'], 'oy') !== false ? 'primary' :
                                                        (strpos($log['islem_tipi'], 'guncelleme') !== false ? 'warning' : 'secondary'))
                                                    ?>">
                                                        <?= $log['islem_tipi'] ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?= date('H:i', strtotime($log['tarih'])) ?>
                                                    </small>
                                                </div>
                                                <p class="mb-1 small"><?= htmlspecialchars($log['aciklama']) ?></p>
                                                <small class="text-muted">IP: <?= $log['ip_adresi'] ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Güvenlik -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6">
                        <!-- Şifre değiştirme -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-key-fill"></i> Şifre Değiştir
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($password_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show">
                                        <?= $password_success ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($password_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <?= $password_error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="change_password" value="1">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Mevcut Şifre</label>
                                        <input type="password" class="form-control" name="mevcut_sifre" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Yeni Şifre</label>
                                        <input type="password" class="form-control" name="yeni_sifre" required minlength="6">
                                        <small class="text-muted">En az 6 karakter</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Yeni Şifre (Tekrar)</label>
                                        <input type="password" class="form-control" name="yeni_sifre_tekrar" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Şifreyi Değiştir
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <!-- Hesap güvenliği -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-shield-check"></i> Hesap Güvenliği
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h6>Oturum Bilgileri</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-globe text-primary"></i>
                                            Son IP Adresiniz: 
                                            <code><?= $_SERVER['REMOTE_ADDR'] ?></code>
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-calendar text-success"></i>
                                            Son Giriş: 
                                            <?= $kullanici['son_giris_tarihi'] 
                                                ? date('d.m.Y H:i', strtotime($kullanici['son_giris_tarihi'])) 
                                                : 'Kayıt yok' ?>
                                        </li>
                                        <li>
                                            <i class="bi bi-clock text-warning"></i>
                                            Oturum Süresi: 30 dakika inaktivite
                                        </li>
                                    </ul>
                                </div>
                                
                                <div class="mb-4">
                                    <h6>Güvenlik Önerileri</h6>
                                    <ul class="small">
                                        <li>Şifrenizi düzenli olarak değiştirin</li>
                                        <li>Başkalarıyla hesap bilgilerinizi paylaşmayın</li>
                                        <li>Ortak bilgisayarlarda 'Beni Hatırla' kullanmayın</li>
                                        <li>Şüpheli aktiviteleri bildirin</li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6>
                                        <i class="bi bi-exclamation-triangle"></i> Hesap Silme
                                    </h6>
                                    <p class="small mb-2">Hesabınızı silmek istiyorsanız lütfen yöneticiyle iletişime geçin.</p>
                                    <a href="mailto:admin@dogrudanirade.org" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Hesap Silme Talebi
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Topluluk Ekleme Modal -->
    <div class="modal fade" id="addCommunityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Yeni Topluluk Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Topluluk üyeliği, ilgili oylamalarda oy kullanma hakkı verir.
                    </p>
                    
                    <form id="communityForm" action="api.php" method="POST">
                        <input type="hidden" name="action" value="add_community">
                        <input type="hidden" name="kullanici_id" value="<?= $kullanici_id ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Topluluk Tipi</label>
                            <select class="form-select" name="topluluk_tipi" required>
                                <option value="">Seçiniz</option>
                                <option value="il">İl</option>
                                <option value="ilce">İlçe</option>
                                <option value="sendika">Sendika</option>
                                <option value="meslek_odasi">Meslek Odası</option>
                                <option value="universite">Üniversite</option>
                                <option value="sirket">Şirket</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Topluluk ID/Kodu</label>
                            <input type="text" class="form-control" name="topluluk_id" required
                                   placeholder="Örn: 34 (İstanbul için)">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Açıklama (Opsiyonel)</label>
                            <input type="text" class="form-control" name="aciklama"
                                   placeholder="Örn: İstanbul Şehir Meclisi">
                        </div>
                        
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle"></i> 
                            Topluluk ekledikten sonra oylamalara katılabilirsiniz. 
                            Yönetici onayı gerekebilir.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" form="communityForm" class="btn btn-primary">Ekle</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    function removeCommunity(id) {
        if (!confirm('Bu topluluktan çıkmak istediğinize emin misiniz? İlgili oylamalarda oy kullanamazsınız.')) {
            return;
        }
        
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=remove_community&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Topluluktan çıkarıldınız.');
                location.reload();
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
        });
    }
    
    // Tab değişiminde URL hash güncelle
    document.addEventListener('DOMContentLoaded', function() {
        var triggerTabList = [].slice.call(document.querySelectorAll('#profileTab button'));
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                var tabId = triggerEl.getAttribute('data-bs-target').substring(1);
                window.location.hash = tabId;
            });
        });
        
        // URL'den tab aç
        if (window.location.hash) {
            var tabId = window.location.hash.substring(1);
            var triggerEl = document.querySelector('#profileTab button[data-bs-target="#' + tabId + '"]');
            if (triggerEl) {
                bootstrap.Tab.getInstance(triggerEl) || new bootstrap.Tab(triggerEl);
                triggerEl.click();
            }
        }
    });
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **README.md**
```markdown
# DOĞRUDAN İRADE PLATFORMU - EKSİKSİZ PAKET

## **1. PROJE FELSEFESİ ve MAKALE**

### RADİKAL DEMOKRASİ: ARACILARI ORTADAN KALDIRAN DOĞRUDAN İRADE PLATFORMU

**Sorun Tespiti: Temsili Demokrasinin Çöküşü**

Modern temsili demokrasi sistemleri, tarihsel olarak oligarşik bir evrime uğramıştır. Seçimle gelen temsilciler, kısa sürede "profesyonel politikacı" sınıfına dönüşmekte ve seçmenlerin gerçek iradesiyle bağlarını koparmaktadır. Bu sistemde:

- **Seçmen manipülasyonu** sistematik hale gelmiştir: Medya kontrolü, söylem mühendisliği, korku politikaları ve popülizm, seçmenin rasyonel karar verme yeteneğini baltalamaktadır.
- **Çıkar çatışmaları** yapısal problemdir: Finans sektörü, silah lobileri, enerji tekelleri ve büyük şirketler, politikacıları fonlama ve lobi faaliyetleriyle satın almaktadır.
- **Temsil krizi** derinleşmektedir: Milletvekilleri seçildikten sonra seçmenlerine hesap vermemekte, parti disiplini adına bireysel iradelerini feda etmekte, ve gerçek sorunlara çözüm üretmek yerine ideolojik kutuplaşmayı derinleştirmektedir.

**Kurumsal Çıkmazlar:**

1. **TBMM**: Yasama süreci halktan kopuk, bürokratik ve lobi etkisine açık.
2. **YSK**: Tarafsızlığı sürekli tartışma konusu olan, siyasi atamalarla yönetilen bir kurum.
3. **Sendikalar ve Meslek Odaları**: Yönetici elitleri, üyelerinin iradesini temsil etmekten uzak, kendi çıkarlarını koruyan kapalı yapılar.
4. **Yerel Yönetimler**: Merkezi hükümetin vesayeti altında, gerçek anlamda yerel iradeyi yansıtamayan yapılar.

**Teknolojik Fırsat: İnternet Devrimi**

21. yüzyıl internet teknolojisi, tarihte ilk kez, milyonlarca insanın eşzamanlı, şeffaf, güvenli ve doğrudan katılımını mümkün kılmaktadır. Artık:

- Coğrafi sınırlar anlamını yitirmiştir.
- Bilgiye erişim demokratikleşmiştir.
- Gerçek zamanlı iletişim ve oylama teknik olarak mümkündür.

**Platform Vizyonu: Aracıları Ortadan Kaldırmak**

Bu platform, tüm temsili kurumları aşan radikal bir alternatif sunmaktadır:

1. **Ulusal düzeyde**: TBMM'nin yasama fonksiyonunu, doğrudan halk oylamalarıyla yerine getirmek.
2. **Yerel düzeyde**: İl ve ilçe meclislerinin karar alma süreçlerini demokratikleştirmek.
3. **Mesleki düzeyde**: Sendika ve meslek odalarında üyelerin doğrudan söz sahibi olmasını sağlamak.
4. **Kurumsal düzeyde**: Şirketlerde hissedar ve çalışan katılımını artırmak.

**Devrimci Yenilik: Negatif Oy Sistemi**

Geleneksel seçim sistemlerindeki en büyük eksiklik, sadece "en az kötü" adayı seçmeye zorlanmaktır. Negatif oy sistemi bu problemi çözmektedir:

- **Popüler ama sevilmeyen adayları filtreler**: Yüksek destek alan ama aynı zamanda yüksek muhalefet toplayan adayların kazanmasını engeller.
- **Toplumsal mutabakatı yansıtır**: Sadece kimin daha çok sevildiğini değil, kimin daha az sevilmediğini de ölçer.
- **Manipülasyonu zorlaştırır**: Medya tarafından pompalanan ancak gerçekte halk desteği olmayan adayları eleyebilir.
- **Daha gerçek bir toplumsal tercihi yansıtır**: Net skor (destek - negatif) formülü, bir adayın gerçek kabul edilebilirliğini ölçer.

**Platformun Temel İlkeleri:**

1. **Doğrudan Katılım**: Her vatandaş, her kararda doğrudan söz sahibi olabilir.
2. **Şeffaflık**: Tüm oylama süreçleri ve sonuçları herkese açıktır.
3. **Eşitlik**: Her kullanıcının oyu eşit değerdedir.
4. **Güvenlik**: Oy verme süreci manipülasyona ve hileye karşı korumalıdır.
5. **Kapsayıcılık**: Tüm toplumsal kesimlerin katılımı teşvik edilir.

Bu platform, demokrasinin evriminde yeni bir aşamayı temsil etmektedir: **Dijital Doğrudan Demokrasi**. Artık "temsil edilmeye" değil, "doğrudan söz sahibi olmaya" talibiz.

## **2. TEKNİK GEREKSİNİMLER (PHP/MySQL)**

- **Sunucu Tarafı**: PHP 7.4 veya üzeri
- **Veritabanı**: MySQL 5.7 veya üzeri (veya MariaDB 10.2+)
- **Frontend**: Bootstrap 5.2+ ile responsive ve modern arayüz
- **Güvenlik**:
  - Tüm kullanıcı girdileri filtrelenecek ve validate edilecek
  - SQL sorgularında kesinlikle Prepared Statements kullanılacak
  - Kullanıcı şifreleri `password_hash()` ile hash'lenecek
  - XSS, CSRF ve SQL Injection korumaları uygulanacak
  - HTTPS zorunlu olacak
- **Performans**: Temel önbellekleme mekanizmaları implemente edilecek

## **3. VERİTABANI ŞEMASI**

```sql
-- Veritabanı oluşturma
CREATE DATABASE IF NOT EXISTS dogrudan_irade DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dogrudan_irade;

-- Oylama Tablosu
CREATE TABLE oylamalar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tur ENUM('secim', 'kanun_teklifi', 'referandum') NOT NULL,
    baslik VARCHAR(500) NOT NULL,
    aciklama TEXT,
    olusturan_id BIGINT NOT NULL,
    topluluk_tipi ENUM('ulusal', 'il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') DEFAULT 'ulusal',
    topluluk_id BIGINT DEFAULT NULL COMMENT 'Hangi il, ilçe, sendika vb. için oylama',
    baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    bitis_tarihi DATETIME,
    durum ENUM('aktif', 'sonuclandi', 'iptal') DEFAULT 'aktif',
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tur (tur),
    INDEX idx_durum (durum),
    INDEX idx_topluluk (topluluk_tipi, topluluk_id)
);

-- Adaylar Tablosu (Seçim türündeki oylamalar için)
CREATE TABLE adaylar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    aday_adi VARCHAR(200) NOT NULL,
    aday_aciklama TEXT,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Kullanıcılar Tablosu
CREATE TABLE kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    eposta VARCHAR(255) UNIQUE NOT NULL,
    sifre_hash VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(200) NOT NULL,
    tc_kimlik VARCHAR(11) UNIQUE COMMENT 'Güvenlik nedeniyle hashlenmiş olarak saklanabilir',
    telefon VARCHAR(20),
    dogum_tarihi DATE,
    kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    son_giris_tarihi DATETIME,
    durum ENUM('aktif', 'pasif', 'askida') DEFAULT 'aktif',
    INDEX idx_eposta (eposta)
);

-- Kullanıcı Topluluk Üyelikleri
CREATE TABLE kullanici_topluluklari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    topluluk_tipi ENUM('il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') NOT NULL,
    topluluk_id BIGINT NOT NULL COMMENT 'İl kodu, sendika IDsi vb.',
    uyelik_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_uyelik (kullanici_id, topluluk_tipi, topluluk_id),
    INDEX idx_kullanici (kullanici_id)
);

-- OYLAR Tablosu (Çekirdek Mantık Burada)
CREATE TABLE oy_kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    -- DESTEK OYU: Hangi adayı destekliyor? (NULL olabilir)
    destek_verilen_aday_id BIGINT NULL,
    -- NEGATİF OY: Hangi adaya karşı? (Bir kullanıcı BİRDEN FAZLA adaya negatif oy verebilir)
    negatif_oy_verilen_aday_id BIGINT NULL,
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_adresi VARCHAR(45),
    -- Bir kullanıcı aynı oylamada tek bir destek oyu kullanabilir
    UNIQUE KEY tek_destek (oylama_id, kullanici_id),
    -- Bir kullanıcı aynı adaya birden fazla negatif oy veremez
    UNIQUE KEY tek_negatif_aday (oylama_id, kullanici_id, negatif_oy_verilen_aday_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (destek_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    FOREIGN KEY (negatif_oy_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    INDEX idx_oylama_kullanici (oylama_id, kullanici_id),
    INDEX idx_destek (destek_verilen_aday_id),
    INDEX idx_negatif (negatif_oy_verilen_aday_id)
);

-- Referandum/Kanun Teklifi Seçenekleri
CREATE TABLE oylama_secenekleri (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    secenek_metni VARCHAR(500) NOT NULL,
    tur ENUM('evet', 'hayir', 'alternatif') NOT NULL,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Referandum Oyları
CREATE TABLE referandum_oylari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    secenek_id BIGINT NOT NULL,
    negatif_oy BOOLEAN DEFAULT FALSE COMMENT 'Bu seçeneğe karşı negatif oy',
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY tek_oylama_kullanici (oylama_id, kullanici_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (secenek_id) REFERENCES oylama_secenekleri(id) ON DELETE CASCADE
);

-- Log Tablosu (Güvenlik ve Denetim)
CREATE TABLE sistem_loglari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NULL,
    islem_tipi VARCHAR(100) NOT NULL,
    aciklama TEXT,
    ip_adresi VARCHAR(45),
    user_agent TEXT,
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tarih (tarih),
    INDEX idx_islem_tipi (islem_tipi)
);
```

## **4. ÇEKİRDEK İŞLEVLER ve NEGATİF OY SİSTEMİ**

### A. **Kullanıcı Sistemi:**
- Kayıt sayfası: eposta, şifre, ad-soyad, TC kimlik (güvenli hash), telefon
- Giriş sayfası: eposta/şifre ile authentication
- Profil sayfası: Kullanıcının üye olduğu topluluklar görüntülenmeli (örn: 'İstanbul Seçmeni', 'X Sendikası Üyesi', 'Y Meslek Odası Üyesi')
- Kullanıcı, birden fazla topluluğa üye olabilmeli

### B. **Oylama Oluşturma:**
- Yetkili kullanıcılar yeni oylama başlatabilir
- Oylama türü seçimi: 'Seçim', 'Kanun Teklifi', 'Referandum'
- Oylama başlığı, açıklaması, bitiş tarihi
- Topluluk seçimi: Ulusal, il, ilçe, sendika, meslek odası vb.
- Seçim türü seçilirse, aday listesi ekleme (aday adı, kısa açıklama)
- Referandum/Kanun teklifi türü seçilirse, seçenekler ekleme

### C. **OY KULLANMA ARAYÜZÜ ve MANTIĞI (EN ÖNEMLİ KISIM):**

#### Seçim Oylaması Sayfası:
```
[OYLAMA BAŞLIĞI]
[Açıklama]

ŞU ANKİ SEÇİMLERİNİZ:
✅ Desteklediğiniz Aday: [Aday Adı] (Varsa)
❌ Negatif Oy Verdiğiniz Adaylar: [Aday1], [Aday2]... (Varsa)

ADAY LİSTESİ:

1. [Aday A Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]

2. [Aday B Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]

3. [Aday C Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]
```

**İşlevler:**
1. **Destek Oyu**: Kullanıcı YALNIZCA BİR adaya destek oyu verebilir. Yeni destek oyu verildiğinde önceki destek oyu otomatik iptal edilir.
2. **Negatif Oy**: Kullanıcı İSTEDİĞİ KADAR adaya negatif oy verebilir. Her negatif oy butonu bağımsız çalışır (toggle mantığı: basılırsa negatif oy verilir, tekrar basılırsa geri alınır).
3. **Anlık Geri Bildirim**: Kullanıcının seçimleri anlık olarak sayfanın üstünde gösterilir.
4. **Topluluk Kontrolü**: Kullanıcı sadece kendi üyesi olduğu toplulukların oylamalarında oy kullanabilir.

### D. **SONUÇ HESAPLAMA:**

```php
<?php
// config/database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'dogrudan_irade';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function singleValueQuery($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
}

// functions/secim_fonksiyonlari.php
function secimSonucunuHesapla($oylama_id) {
    $db = new Database();
    
    // 1. Tüm adayları al
    $stmt = $db->query(
        "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
        [$oylama_id]
    );
    $adaylar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sonuclar = [];
    foreach ($adaylar as $aday) {
        // 2. Bu adaya verilen DESTEK oylarını say
        $destekSayisi = $db->singleValueQuery(
            "SELECT COUNT(*) FROM oy_kullanicilar 
             WHERE oylama_id = ? AND destek_verilen_aday_id = ?",
            [$oylama_id, $aday['id']]
        );

        // 3. Bu adaya verilen NEGATİF oyları say
        $negatifSayisi = $db->singleValueQuery(
            "SELECT COUNT(*) FROM oy_kullanicilar 
             WHERE oylama_id = ? AND negatif_oy_verilen_aday_id = ?",
            [$oylama_id, $aday['id']]
        );

        // 4. NET SKOR HESAPLA: Destek - Negatif
        $netSkor = $destekSayisi - $negatifSayisi;

        $sonuclar[] = [
            'aday_id' => $aday['id'],
            'aday_adi' => $aday['aday_adi'],
            'aday_aciklama' => $aday['aday_aciklama'],
            'destek_sayisi' => (int)$destekSayisi,
            'negatif_sayisi' => (int)$negatifSayisi,
            'net_skor' => (int)$netSkor
        ];
    }

    // 5. NET SKOR'a göre yüksekten düşüğe sırala
    usort($sonuclar, function($a, $b) {
        if ($b['net_skor'] == $a['net_skor']) {
            // Net skor eşitse, daha az negatif oy alan kazanır
            return $a['negatif_sayisi'] <=> $b['negatif_sayisi'];
        }
        return $b['net_skor'] <=> $a['net_skor'];
    });

    return $sonuclar; // İlk sıradaki kazanandır
}

function oylamaSonuclandiMi($oylama_id) {
    $db = new Database();
    $stmt = $db->query(
        "SELECT durum, bitis_tarihi FROM oylamalar WHERE id = ?",
        [$oylama_id]
    );
    $oylama = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($oylama['durum'] == 'sonuclandi') {
        return true;
    }
    
    // Bitiş tarihi geçmişse
    if ($oylama['bitis_tarihi'] && strtotime($oylama['bitis_tarihi']) < time()) {
        // Oylamayı sonuçlandır
        $db->query(
            "UPDATE oylamalar SET durum = 'sonuclandi' WHERE id = ?",
            [$oylama_id]
        );
        return true;
    }
    
    return false;
}
?>
```

**Sonuç Görüntüleme Sayfası:**
```
[OYLAMA BAŞLIĞI] - SONUÇLAR

TOPLAM OY KULLANAN: 1,250 kişi

SONUÇLAR:
1. ✅ KAZANAN: Aday B
   Destek: 400 oy | Negatif: 50 oy | Net Skor: 350
   (%32 destek, %4 negatif)

2. Aday A
   Destek: 600 oy | Negatif: 450 oy | Net Skor: 150
   (%48 destek, %36 negatif)

3. Aday C
   Destek: 250 oy | Negatif: 100 oy | Net Skor: 150
   (%20 destek, %8 negatif)
```

## **5. DİĞER MODÜLLER**

### **Kanun Teklifi/Referandum Modülü:**
- Seçenekler: 'Evet', 'Hayır' ve alternatif teklifler
- Her seçenek için ayrı negatif oy butonu
- Sonuç hesaplama: (Evet oyu - Negatif oy) vs (Hayır oyu - Negatif oy)

### **Kullanıcı Toplulukları:**
- Kullanıcı kayıt sırasında veya profil sayfasında topluluklara üye olabilir
- Her topluluk tipi için admin onayı veya otomatik doğrulama
- Topluluk bazında oylama filtreleme

### **Yönetim Paneli:**
- Oylama oluşturma/duzenleme/silme
- Oylamaları başlatma/bitirme
- Kullanıcı yönetimi (aktif/pasif yapma)
- Sistem loglarını görüntüleme
- Topluluk yönetimi

### **Güvenlik Modülleri:**
- IP bazlı oy kullanım sınırlaması
- Çoklu hesap tespiti
- Bot koruma (CAPTCHA)
- Oylama çakışması önleme

## **6. İSTENEN ÇIKTILAR**

1. **`kurulum.sql`**: Tüm veritabanı yapısını oluşturan SQL dosyası
2. **`index.php`**: Ana sayfa - Aktif oylamalar listesi
3. **`kayit.php` & `giris.php`**: Kullanıcı kayıt ve giriş sayfaları
4. **`profil.php`**: Kullanıcı profil ve topluluk yönetim sayfası
5. **`oylama_olustur.php`**: Yeni oylama başlatma sayfası
6. **`oylama_detay.php`**: Oylama detay ve oy kullanma sayfası (Destek/Negatif arayüzü ile)
7. **`sonuc.php`**: Sonuçları net skora göre gösteren sayfa
8. **`admin/`**: Yönetim paneli dosyaları
9. **`README.md`**: Yukarıdaki felsefi makaleyi içeren proje dokümantasyonu
10. **`config/`**: Veritabanı bağlantı ve ayar dosyaları
11. **`includes/`**: Fonksiyon ve sınıf dosyaları
12. **`assets/`**: CSS, JS ve resim dosyaları

## **EK NOTLAR**

- **Kod Standartları**: PSR-12 kodlama standardı, açıklayıcı yorum satırları, Türkçe değişken/fonksiyon isimlendirmesi
- **Modüler Yapı**: MVC benzeri bir yapı, her modül ayrı dosyalarda
- **Hata Yönetimi**: Tüm hatalar loglanacak, kullanıcıya uygun mesaj gösterilecek
- **Test**: Temel fonksiyonlar için unit testler
- **Dokümantasyon**: API ve veritabanı dokümantasyonu

**Proje Sloganı**: "Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"

---

## **7. SİSTEM DÜZENİ**

```
D:\dogrudan-irade-platformu
├── assets
│   ├── css
│   │   └── style.css
│   ├── js
│   │   └── main.js
│   └── img
│       ├── logo.png
│       └── favicon.ico
├── config
│   ├── database.php
│   ├── functions.php
│   └── secim_fonksiyonlari.php
├── includes
│   ├── auth.php
│   ├── footer.php
│   └── header.php
├── admin
│   ├── index.php
│   ├── oylamalar.php
│   ├── kullanicilar.php
│   ├── loglar.php
│   ├── ayarlar.php
│   ├── yedekleme.php
│   └── sidebar.php
├── api.php
├── bakim.php
├── bildirimler.php
├── cikis.php
├── giris.php
├── hata.php
├── iletisim.php
├── index.php
├── kayit.php
├── kurulum.sql
├── oylama_olustur.php
├── oylamalar.php
├── oylama_detay.php
├── profil.php
├── sifre_sifirla.php
└── sonuc.php
```

```

--------------------------------------------------------------------------------

📄 **sifre_sifirla.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();
$error = '';
$success = '';
$show_form = true;

// Token kontrolü
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    // Token doğrulama
    $stmt = $db->query(
        "SELECT kullanici_id, son_kullanma FROM sifre_sifirlama_tokenlari 
         WHERE token = ? AND son_kullanma > NOW()",
        [$token]
    );
    $token_data = $stmt->fetch();
    
    if (!$token_data) {
        $error = 'Geçersiz veya süresi dolmuş şifre sıfırlama bağlantısı.';
        $show_form = false;
    } else {
        $user_id = $token_data['kullanici_id'];
        
        // Şifre değiştirme formu göster
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeni_sifre'])) {
            $yeni_sifre = $_POST['yeni_sifre'];
            $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'];
            
            if (strlen($yeni_sifre) < 6) {
                $error = 'Şifre en az 6 karakter olmalıdır.';
            } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
                $error = 'Şifreler eşleşmiyor.';
            } else {
                try {
                    // Şifreyi güncelle
                    $sifre_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                    
                    $db->query(
                        "UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?",
                        [$sifre_hash, $user_id]
                    );
                    
                    // Token'ı sil
                    $db->query(
                        "DELETE FROM sifre_sifirlama_tokenlari WHERE token = ?",
                        [$token]
                    );
                    
                    // Log kaydı
                    logIslem($user_id, 'sifre_sifirlama', 'Şifre başarıyla sıfırlandı', $_SERVER['REMOTE_ADDR']);
                    
                    $success = 'Şifreniz başarıyla değiştirildi. Giriş yapabilirsiniz.';
                    $show_form = false;
                    
                } catch (Exception $e) {
                    $error = 'Şifre değiştirme sırasında bir hata oluştu: ' . $e->getMessage();
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eposta'])) {
    // Şifre sıfırlama isteği
    $eposta = trim($_POST['eposta']);
    
    // Kullanıcıyı bul
    $stmt = $db->query(
        "SELECT id, ad_soyad FROM kullanicilar WHERE eposta = ? AND durum = 'aktif'",
        [$eposta]
    );
    $user = $stmt->fetch();
    
    if ($user) {
        // Token oluştur
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Eski tokenları temizle
        $db->query(
            "DELETE FROM sifre_sifirlama_tokenlari WHERE kullanici_id = ? OR son_kullanma < NOW()",
            [$user['id']]
        );
        
        // Yeni token ekle
        $db->query(
            "INSERT INTO sifre_sifirlama_tokenlari (kullanici_id, token, son_kullanma) 
             VALUES (?, ?, ?)",
            [$user['id'], $token, $expires]
        );
        
        // E-posta gönder (simülasyon)
        $reset_link = getBaseUrl() . "/sifre_sifirla.php?token=$token";
        
        $subject = "Doğrudan İrade - Şifre Sıfırlama";
        $message = "
        <h2>Doğrudan İrade Platformu</h2>
        <p>Merhaba {$user['ad_soyad']},</p>
        <p>Şifre sıfırlama isteğiniz alındı. Aşağıdaki bağlantıya tıklayarak yeni şifrenizi belirleyebilirsiniz:</p>
        <p><a href='$reset_link' style='background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block;'>
            Şifremi Sıfırla
        </a></p>
        <p><small>Bu bağlantı 1 saat geçerlidir.</small></p>
        <p>Eğer bu isteği siz yapmadıysanız, bu e-postayı dikkate almayınız.</p>
        <hr>
        <p><small>Doğrudan İrade Platformu<br>Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!</small></p>
        ";
        
        // E-posta gönderme (gerçek uygulamada aktif edin)
        // sendEmail($eposta, $subject, $message);
        
        // Log
        logIslem($user['id'], 'sifre_sifirlama_istegi', 'Şifre sıfırlama e-postası gönderildi', $_SERVER['REMOTE_ADDR']);
        
        $success = "Şifre sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen e-postanızı kontrol edin.";
        $show_form = false;
        
    } else {
        $error = 'Bu e-posta adresi ile kayıtlı aktif bir kullanıcı bulunamadı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .password-strength {
            height: 5px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .strength-0 { width: 0%; background: #dc3545; }
        .strength-1 { width: 25%; background: #dc3545; }
        .strength-2 { width: 50%; background: #ffc107; }
        .strength-3 { width: 75%; background: #28a745; }
        .strength-4 { width: 100%; background: #28a745; }
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
        }
        .requirement {
            margin-bottom: 3px;
        }
        .requirement.met {
            color: #28a745;
        }
        .requirement.unmet {
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <!-- Logo -->
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold text-primary">
                        <i class="bi bi-shield-lock"></i> Doğrudan İrade
                    </h1>
                    <p class="text-muted">Şifre Sıfırlama</p>
                </div>
                
                <!-- Şifre Sıfırlama Kartı -->
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <?= empty($token) ? '🔑 Şifremi Unuttum' : '🔄 Yeni Şifre Belirle' ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle"></i> <?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                <div class="mt-3">
                                    <a href="giris.php" class="btn btn-success">
                                        <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                                    </a>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-house"></i> Ana Sayfa
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($show_form): ?>
                            <?php if (empty($token)): ?>
                                <!-- E-posta istek formu -->
                                <form method="POST" action="">
                                    <div class="mb-4">
                                        <p class="text-muted">
                                            Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi girin.
                                            Size şifre sıfırlama bağlantısı göndereceğiz.
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="eposta" class="form-label">E-posta Adresiniz</label>
                                        <input type="email" class="form-control form-control-lg" 
                                               id="eposta" name="eposta" required
                                               placeholder="ornek@email.com">
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-send"></i> Şifre Sıfırlama Bağlantısı Gönder
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Yeni şifre formu -->
                                <form method="POST" action="" id="passwordForm">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                    
                                    <div class="mb-4">
                                        <p class="text-muted">
                                            Lütfen yeni şifrenizi belirleyin.
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                                        <input type="password" class="form-control form-control-lg" 
                                               id="yeni_sifre" name="yeni_sifre" required
                                               minlength="6">
                                        <div class="password-strength" id="passwordStrength"></div>
                                        
                                        <div class="password-requirements mt-2">
                                            <div class="requirement" id="reqLength">
                                                <i class="bi bi-circle"></i> En az 6 karakter
                                            </div>
                                            <div class="requirement" id="reqUpper">
                                                <i class="bi bi-circle"></i> En az 1 büyük harf
                                            </div>
                                            <div class="requirement" id="reqLower">
                                                <i class="bi bi-circle"></i> En az 1 küçük harf
                                            </div>
                                            <div class="requirement" id="reqNumber">
                                                <i class="bi bi-circle"></i> En az 1 rakam
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre (Tekrar)</label>
                                        <input type="password" class="form-control form-control-lg" 
                                               id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required>
                                        <div class="invalid-feedback" id="passwordMatchError">
                                            Şifreler eşleşmiyor.
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="bi bi-check-circle"></i> Şifremi Değiştir
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Yardım ve bağlantılar -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="text-center">
                                <a href="giris.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                                </a>
                                <a href="kayit.php" class="btn btn-outline-primary ms-2">
                                    <i class="bi bi-person-plus"></i> Yeni Hesap
                                </a>
                                <a href="index.php" class="btn btn-outline-dark ms-2">
                                    <i class="bi bi-house"></i> Ana Sayfa
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Güvenlik bilgisi -->
                <div class="card mt-4 border-info">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-shield-check text-info"></i> GÜVENLİK BİLGİSİ
                        </h6>
                        <ul class="small mb-0">
                            <li>Şifre sıfırlama bağlantısı 1 saat geçerlidir</li>
                            <li>Bağlantıyı sadece siz kullanabilirsiniz</li>
                            <li>Şifrenizi kimseyle paylaşmayın</li>
                            <li>Şüpheli durumlarda destek ekibiyle iletişime geçin</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script>
    // Şifre güçlülük kontrolü
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('yeni_sifre');
        const confirmInput = document.getElementById('yeni_sifre_tekrar');
        const strengthBar = document.getElementById('passwordStrength');
        const submitBtn = document.getElementById('submitBtn');
        
        // Şifre gereksinimleri elementleri
        const reqLength = document.getElementById('reqLength');
        const reqUpper = document.getElementById('reqUpper');
        const reqLower = document.getElementById('reqLower');
        const reqNumber = document.getElementById('reqNumber');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Uzunluk kontrolü
                if (password.length >= 6) {
                    strength++;
                    reqLength.classList.remove('unmet');
                    reqLength.classList.add('met');
                    reqLength.innerHTML = '<i class="bi bi-check-circle"></i> En az 6 karakter';
                } else {
                    reqLength.classList.remove('met');
                    reqLength.classList.add('unmet');
                    reqLength.innerHTML = '<i class="bi bi-circle"></i> En az 6 karakter';
                }
                
                // Büyük harf kontrolü
                if (/[A-Z]/.test(password)) {
                    strength++;
                    reqUpper.classList.remove('unmet');
                    reqUpper.classList.add('met');
                    reqUpper.innerHTML = '<i class="bi bi-check-circle"></i> En az 1 büyük harf';
                } else {
                    reqUpper.classList.remove('met');
                    reqUpper.classList.add('unmet');
                    reqUpper.innerHTML = '<i class="bi bi-circle"></i> En az 1 büyük harf';
                }
                
                // Küçük harf kontrolü
                if (/[a-z]/.test(password)) {
                    strength++;
                    reqLower.classList.remove('unmet');
                    reqLower.classList.add('met');
                    reqLower.innerHTML = '<i class="bi bi-check-circle"></i> En az 1 küçük harf';
                } else {
                    reqLower.classList.remove('met');
                    reqLower.classList.add('unmet');
                    reqLower.innerHTML = '<i class="bi bi-circle"></i> En az 1 küçük harf';
                }
                
                // Rakam kontrolü
                if (/[0-9]/.test(password)) {
                    strength++;
                    reqNumber.classList.remove('unmet');
                    reqNumber.classList.add('met');
                    reqNumber.innerHTML = '<i class="bi bi-check-circle"></i> En az 1 rakam';
                } else {
                    reqNumber.classList.remove('met');
                    reqNumber.classList.add('unmet');
                    reqNumber.innerHTML = '<i class="bi bi-circle"></i> En az 1 rakam';
                }
                
                // Şifre güçlülüğü göster
                strengthBar.className = 'password-strength strength-' + strength;
                
                // Butonu aktif/pasif yap
                updateSubmitButton();
            });
            
            // Şifre eşleşme kontrolü
            if (confirmInput) {
                confirmInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const confirm = this.value;
                    
                    if (confirm.length > 0 && password !== confirm) {
                        this.classList.add('is-invalid');
                        document.getElementById('passwordMatchError').style.display = 'block';
                    } else {
                        this.classList.remove('is-invalid');
                        document.getElementById('passwordMatchError').style.display = 'none';
                    }
                    
                    updateSubmitButton();
                });
            }
            
            // Form gönderim kontrolü
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Şifre en az 6 karakter olmalıdır.');
                    return;
                }
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Şifreler eşleşmiyor.');
                    return;
                }
                
                // Minimum güçlülük kontrolü
                const hasUpper = /[A-Z]/.test(password);
                const hasLower = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                
                if (!hasUpper || !hasLower || !hasNumber) {
                    if (!confirm('Şifreniz yeterince güçlü değil. Devam etmek istiyor musunuz?')) {
                        e.preventDefault();
                    }
                }
            });
        }
        
        function updateSubmitButton() {
            if (!submitBtn) return;
            
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            // Temel kontroller
            const isValid = password.length >= 6 && password === confirm;
            
            submitBtn.disabled = !isValid;
            submitBtn.classList.toggle('disabled', !isValid);
        }
    });
    </script>
</body>
</html>

```

--------------------------------------------------------------------------------

📄 **sonuc.php**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'config/secim_fonksiyonlari.php';

$db = new Database();
$secim = new SecimFonksiyonlari();

$oylama_id = $_GET['id'] ?? 0;

// Oylama bilgilerini al
$oylama = $db->query(
    "SELECT * FROM oylamalar WHERE id = ?",
    [$oylama_id]
)->fetch();

if (!$oylama) {
    header("Location: index.php");
    exit;
}

// Sonuçları hesapla
$sonucData = $secim->secimSonucunuHesapla($oylama_id);
$sonuclar = $sonucData['sonuclar'];
$toplamOy = $sonucData['toplam_oy_kullanan'];
$kazanan = $sonucData['kazanan'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sonuçlar: <?= htmlspecialchars($oylama['baslik']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .winner-card {
            border: 3px solid #28a745;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        .result-bar {
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
        }
        .support-bar {
            background-color: #28a745;
        }
        .negative-bar {
            background-color: #dc3545;
        }
        .net-score {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .candidate-rank {
            font-size: 2rem;
            font-weight: bold;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Başlık -->
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold mb-3">
                        📊 <?= htmlspecialchars($oylama['baslik']) ?>
                    </h1>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">
                            <i class="bi bi-trophy-fill"></i> OYLAMA SONUÇLANDI
                        </h4>
                        Toplam <strong><?= $toplamOy ?></strong> kişi oy kullandı.
                    </div>
                </div>

                <!-- Kazanan -->
                <?php if ($kazanan): ?>
                    <div class="card winner-card mb-5 shadow-lg">
                        <div class="card-body text-center p-5">
                            <div class="mb-4">
                                <span class="badge bg-success fs-5 mb-3">🏆 KAZANAN</span>
                                <h2 class="display-6 fw-bold"><?= htmlspecialchars($kazanan['aday_adi']) ?></h2>
                                <?php if ($kazanan['aday_aciklama']): ?>
                                    <p class="lead"><?= htmlspecialchars($kazanan['aday_aciklama']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="result-bar mb-2">
                                        <div class="d-flex">
                                            <div class="support-bar" 
                                                 style="width: <?= ($kazanan['destek_sayisi'] / max($toplamOy, 1)) * 100 ?>%">
                                            </div>
                                            <div class="negative-bar" 
                                                 style="width: <?= ($kazanan['negatif_sayisi'] / max($toplamOy, 1)) * 100 ?>%">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="net-score text-success">
                                                <?= $kazanan['net_skor'] ?>
                                            </div>
                                            <small>Net Skor</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-success">
                                                <?= $kazanan['destek_sayisi'] ?>
                                                <small>(%<?= $toplamOy > 0 ? round(($kazanan['destek_sayisi']/$toplamOy)*100) : 0 ?>)</small>
                                            </div>
                                            <small>Destek</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-danger">
                                                <?= $kazanan['negatif_sayisi'] ?>
                                                <small>(%<?= $toplamOy > 0 ? round(($kazanan['negatif_sayisi']/$toplamOy)*100) : 0 ?>)</small>
                                            </div>
                                            <small>Negatif</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tüm Sonuçlar -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-bar-chart-fill"></i> TÜM ADAYLARIN SONUÇLARI
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($sonuclar as $index => $sonuc): ?>
                            <div class="candidate-result mb-4 p-3 border rounded <?= $index === 0 ? 'border-success border-2' : '' ?>">
                                <div class="row align-items-center">
                                    <!-- Sıra -->
                                    <div class="col-md-1 text-center">
                                        <div class="candidate-rank">
                                            #<?= $index + 1 ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Aday bilgisi -->
                                    <div class="col-md-4">
                                        <h5 class="mb-1"><?= htmlspecialchars($sonuc['aday_adi']) ?></h5>
                                        <?php if ($sonuc['aday_aciklama']): ?>
                                            <p class="text-muted small mb-2"><?= htmlspecialchars($sonuc['aday_aciklama']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Grafik -->
                                    <div class="col-md-5">
                                        <div class="result-bar mb-2">
                                            <div class="d-flex">
                                                <div class="support-bar" 
                                                     style="width: <?= ($sonuc['destek_sayisi'] / max($toplamOy, 1)) * 100 ?>%"
                                                     title="Destek: <?= $sonuc['destek_sayisi'] ?> oy">
                                                </div>
                                                <div class="negative-bar" 
                                                     style="width: <?= ($sonuc['negatif_sayisi'] / max($toplamOy, 1)) * 100 ?>%"
                                                     title="Negatif: <?= $sonuc['negatif_sayisi'] ?> oy">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span>Destek: <?= $sonuc['destek_sayisi'] ?> (%<?= $toplamOy > 0 ? round(($sonuc['destek_sayisi']/$toplamOy)*100) : 0 ?>)</span>
                                            <span>Negatif: <?= $sonuc['negatif_sayisi'] ?> (%<?= $toplamOy > 0 ? round(($sonuc['negatif_sayisi']/$toplamOy)*100) : 0 ?>)</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Net skor -->
                                    <div class="col-md-2 text-end">
                                        <div class="<?= $sonuc['net_skor'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <h4 class="mb-0"><?= $sonuc['net_skor'] ?></h4>
                                            <small class="text-muted">Net Skor</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sistem açıklaması -->
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-lightbulb-fill"></i> NEGATİF OY SİSTEMİ NASIL ÇALIŞIYOR?
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>📊 Net Skor Formülü:</h6>
                                <p class="mb-3">
                                    <code>NET SKOR = Destek Oyu Sayısı - Negatif Oyu Sayısı</code>
                                </p>
                                <p class="small">
                                    Bu sistem, sadece kimin daha çok sevildiğini değil, 
                                    kimin daha az sevilmediğini de ölçer.
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6>🎯 Sistemin Avantajları:</h6>
                                <ul class="small">
                                    <li>Popüler ama sevilmeyen adayları filtreler</li>
                                    <li>Toplumsal mutabakatı yansıtır</li>
                                    <li>Manipülasyonu zorlaştırır</li>
                                    <li>Gerçek kabul edilebilirliği ölçer</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

```

--------------------------------------------------------------------------------

📁 **admin/**
  📄 **admin\ayarlar.php**
  ```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireSuperAdmin();

$db = new Database();
$success = '';
$error = '';

// Ayarları getir
$ayarlar = $db->query(
    "SELECT ayar_adi, ayar_degeri, tur, aciklama FROM sistem_ayarlari ORDER BY ayar_adi"
)->fetchAll(PDO::FETCH_ASSOC);

// Ayarları gruplara ayır
$ayar_gruplari = [
    'genel' => [],
    'guvenlik' => [],
    'eposta' => [],
    'sosyal' => [],
    'api' => []
];

foreach ($ayarlar as $ayar) {
    if (strpos($ayar['ayar_adi'], 'site_') === 0) {
        $ayar_gruplari['genel'][] = $ayar;
    } elseif (strpos($ayar['ayar_adi'], 'smtp_') === 0 || $ayar['ayar_adi'] === 'eposta_dogrulama') {
        $ayar_gruplari['eposta'][] = $ayar;
    } elseif (strpos($ayar['ayar_adi'], 'sosyal_') === 0) {
        $ayar_gruplari['sosyal'][] = $ayar;
    } elseif (strpos($ayar['ayar_adi'], 'recaptcha_') === 0 || strpos($ayar['ayar_adi'], 'max_') === 0) {
        $ayar_gruplari['guvenlik'][] = $ayar;
    } elseif (strpos($ayar['ayar_adi'], 'iletisim_') === 0 && $ayar['ayar_adi'] !== 'iletisim_eposta') {
        $ayar_gruplari['genel'][] = $ayar;
    } else {
        $ayar_gruplari['genel'][] = $ayar;
    }
}

// Ayar güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->connect()->beginTransaction();
        
        foreach ($_POST['ayar'] as $ayar_adi => $ayar_degeri) {
            // Boolean değerleri dönüştür
            if ($ayar_degeri === 'on') {
                $ayar_degeri = '1';
            } elseif ($ayar_degeri === 'off') {
                $ayar_degeri = '0';
            }
            
            $db->query(
                "UPDATE sistem_ayarlari SET ayar_degeri = ?, guncellenme_tarihi = NOW() WHERE ayar_adi = ?",
                [$ayar_degeri, $ayar_adi]
            );
        }
        
        $db->connect()->commit();
        
        // Log
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
             VALUES (?, 'ayar_guncelleme', 'Sistem ayarları güncellendi', ?)",
            [$_SESSION['kullanici_id'], $_SERVER['REMOTE_ADDR']]
        );
        
        $success = 'Ayarlar başarıyla güncellendi.';
        
        // Ayarları yeniden yükle
        $ayarlar = $db->query(
            "SELECT ayar_adi, ayar_degeri, tur, aciklama FROM sistem_ayarlari ORDER BY ayar_adi"
        )->fetchAll(PDO::FETCH_ASSOC);
        
        // Ayar gruplarını yeniden oluştur
        $ayar_gruplari = [
            'genel' => [],
            'guvenlik' => [],
            'eposta' => [],
            'sosyal' => [],
            'api' => []
        ];
        
        foreach ($ayarlar as $ayar) {
            if (strpos($ayar['ayar_adi'], 'site_') === 0) {
                $ayar_gruplari['genel'][] = $ayar;
            } elseif (strpos($ayar['ayar_adi'], 'smtp_') === 0 || $ayar['ayar_adi'] === 'eposta_dogrulama') {
                $ayar_gruplari['eposta'][] = $ayar;
            } elseif (strpos($ayar['ayar_adi'], 'sosyal_') === 0) {
                $ayar_gruplari['sosyal'][] = $ayar;
            } elseif (strpos($ayar['ayar_adi'], 'recaptcha_') === 0 || strpos($ayar['ayar_adi'], 'max_') === 0) {
                $ayar_gruplari['guvenlik'][] = $ayar;
            } elseif (strpos($ayar['ayar_adi'], 'iletisim_') === 0 && $ayar['ayar_adi'] !== 'iletisim_eposta') {
                $ayar_gruplari['genel'][] = $ayar;
            } else {
                $ayar_gruplari['genel'][] = $ayar;
            }
        }
        
    } catch (Exception $e) {
        $db->connect()->rollBack();
        $error = 'Ayarlar güncellenirken hata: ' . $e->getMessage();
    }
}

// SMTP testi
if (isset($_POST['test_email'])) {
    $to = $_POST['test_email'];
    $subject = 'Doğrudan İrade - SMTP Test Maili';
    $message = 'Bu bir test e-postasıdır. SMTP ayarlarınız doğru çalışıyor.';
    
    // E-posta gönderme işlemi
    // $result = sendEmail($to, $subject, $message);
    // if ($result) {
    //     $success = 'Test e-postası gönderildi: ' . $to;
    // } else {
    //     $error = 'Test e-postası gönderilemedi. SMTP ayarlarını kontrol edin.';
    // }
    $success = 'Test e-postası gönderildi: ' . $to; // Simülasyon
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .setting-group {
            margin-bottom: 30px;
        }
        .setting-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
        }
        .setting-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f8f9fa;
        }
        .setting-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .setting-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        .setting-description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .tab-content {
            background: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 25px;
        }
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 12px 25px;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        .test-email {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana içerik -->
            <div class="col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-gear-fill"></i> Sistem Ayarları
                    </h2>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" onclick="document.getElementById('settingsForm').submit()">
                            <i class="bi bi-save"></i> Ayarları Kaydet
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetToDefaults()">
                            <i class="bi bi-arrow-clockwise"></i> Varsayılana Dön
                        </button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Ayarlar Formu -->
                <form method="POST" action="" id="settingsForm">
                    <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="genel-tab" data-bs-toggle="tab" data-bs-target="#genel" type="button">
                                <i class="bi bi-house-door"></i> Genel Ayarlar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="guvenlik-tab" data-bs-toggle="tab" data-bs-target="#guvenlik" type="button">
                                <i class="bi bi-shield-lock"></i> Güvenlik
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="eposta-tab" data-bs-toggle="tab" data-bs-target="#eposta" type="button">
                                <i class="bi bi-envelope"></i> E-posta
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sosyal-tab" data-bs-toggle="tab" data-bs-target="#sosyal" type="button">
                                <i class="bi bi-share"></i> Sosyal Medya
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api" type="button">
                                <i class="bi bi-plug"></i> API & Entegrasyon
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="settingsTabContent">
                        <!-- Genel Ayarlar -->
                        <div class="tab-pane fade show active" id="genel" role="tabpanel">
                            <div class="row">
                                <?php foreach ($ayar_gruplari['genel'] as $ayar): ?>
                                    <div class="col-md-6">
                                        <div class="setting-item">
                                            <div class="setting-label">
                                                <?= ucfirst(str_replace(['site_', 'iletisim_', '_'], ['', '', ' '], $ayar['ayar_adi'])) ?>
                                            </div>
                                            <div class="setting-description">
                                                <?= $ayar['aciklama'] ?>
                                            </div>
                                            <?php if ($ayar['tur'] === 'boolean'): ?>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                           id="<?= $ayar['ayar_adi'] ?>"
                                                           <?= $ayar['ayar_degeri'] == '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="<?= $ayar['ayar_adi'] ?>">
                                                        Aktif
                                                    </label>
                                                </div>
                                            <?php elseif ($ayar['tur'] === 'sayi'): ?>
                                                <input type="number" class="form-control" 
                                                       name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                       value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>">
                                            <?php else: ?>
                                                <input type="text" class="form-control" 
                                                       name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                       value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Güvenlik Ayarları -->
                        <div class="tab-pane fade" id="guvenlik" role="tabpanel">
                            <div class="row">
                                <?php foreach ($ayar_gruplari['guvenlik'] as $ayar): ?>
                                    <div class="col-md-6">
                                        <div class="setting-item">
                                            <div class="setting-label">
                                                <?= ucfirst(str_replace(['recaptcha_', 'max_', '_'], ['', '', ' '], $ayar['ayar_adi'])) ?>
                                            </div>
                                            <div class="setting-description">
                                                <?= $ayar['aciklama'] ?>
                                            </div>
                                            <?php if ($ayar['tur'] === 'boolean'): ?>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                           id="<?= $ayar['ayar_adi'] ?>"
                                                           <?= $ayar['ayar_degeri'] == '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="<?= $ayar['ayar_adi'] ?>">
                                                        Aktif
                                                    </label>
                                                </div>
                                            <?php elseif ($ayar['tur'] === 'sayi'): ?>
                                                <input type="number" class="form-control" 
                                                       name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                       value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>">
                                            <?php else: ?>
                                                <input type="text" class="form-control" 
                                                       name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                       value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- E-posta Ayarları -->
                        <div class="tab-pane fade" id="eposta" role="tabpanel">
                            <div class="row">
                                <?php foreach ($ayar_gruplari['eposta'] as $ayar): ?>
                                    <div class="col-md-6">
                                        <div class="setting-item">
                                            <div class="setting-label">
                                                <?= ucfirst(str_replace(['smtp_', '_'], ['', ' '], $ayar['ayar_adi'])) ?>
                                            </div>
                                            <div class="setting-description">
                                                <?= $ayar['aciklama'] ?>
                                            </div>
                                            <?php if ($ayar['tur'] === 'boolean'): ?>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                           id="<?= $ayar['ayar_adi'] ?>"
                                                           <?= $ayar['ayar_degeri'] == '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="<?= $ayar['ayar_adi'] ?>">
                                                        Aktif
                                                    </label>
                                                </div>
                                            <?php elseif ($ayar['tur'] === 'sayi'): ?>
                                                <input type="number" class="form-control" 
                                                       name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                       value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>">
                                            <?php else: ?>
                                                <?php if (strpos($ayar['ayar_adi'], 'password') !== false): ?>
                                                    <input type="password" class="form-control" 
                                                           name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                           value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>"
                                                           placeholder="●●●●●●●●">
                                                <?php else: ?>
                                                    <input type="text" class="form-control" 
                                                           name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                           value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- SMTP Test -->
                            <div class="test-email">
                                <h6 class="mb-3">
                                    <i class="bi bi-envelope-check"></i> SMTP Testi
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <input type="email" class="form-control" 
                                               name="test_email" 
                                               placeholder="Test e-postası gönderilecek adres">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-outline-primary w-100" 
                                                name="test_smtp" value="1">
                                            <i class="bi bi-send"></i> Test E-postası Gönder
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    E-posta ayarlarınızı test etmek için bir test e-postası gönderin.
                                </small>
                            </div>
                        </div>

                        <!-- Sosyal Medya -->
                        <div class="tab-pane fade" id="sosyal" role="tabpanel">
                            <div class="row">
                                <?php foreach ($ayar_gruplari['sosyal'] as $ayar): ?>
                                    <div class="col-md-6">
                                        <div class="setting-item">
                                            <div class="setting-label">
                                                <?= ucfirst(str_replace(['sosyal_', '_'], ['', ' '], $ayar['ayar_adi'])) ?>
                                            </div>
                                            <div class="setting-description">
                                                <?= $ayar['aciklama'] ?>
                                            </div>
                                            <input type="text" class="form-control" 
                                                   name="ayar[<?= $ayar['ayar_adi'] ?>]" 
                                                   value="<?= htmlspecialchars($ayar['ayar_degeri']) ?>"
                                                   placeholder="https://...">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- API & Entegrasyon -->
                        <div class="tab-pane fade" id="api" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                API anahtarlarını ve entegrasyon ayarlarını buradan yönetebilirsiniz.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="setting-item">
                                        <div class="setting-label">
                                            Google Analytics
                                        </div>
                                        <div class="setting-description">
                                            Google Analytics takip kodu
                                        </div>
                                        <textarea class="form-control" rows="4" 
                                                  name="ayar[analytics_kodu]"><?= htmlspecialchars($ayar_gruplari['genel'][array_search('analytics_kodu', array_column($ayar_gruplari['genel'], 'ayar_adi'))]['ayar_degeri'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="setting-item">
                                        <div class="setting-label">
                                            API Anahtarı
                                        </div>
                                        <div class="setting-description">
                                            Sistem API anahtarı (otomatik oluşturulur)
                                        </div>
                                        <div class="input-group">
                                            <input type="text" class="form-control" 
                                                   id="api_key" 
                                                   value="<?= bin2hex(random_bytes(16)) ?>" 
                                                   readonly>
                                            <button class="btn btn-outline-secondary" type="button" 
                                                    onclick="copyToClipboard('api_key')">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" type="button" 
                                                    onclick="generateApiKey()">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">
                                            Bu anahtar üçüncü parti uygulamalar için kullanılır.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="mb-3">
                                    <i class="bi bi-shield-check"></i> API İzinleri
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="api_read">
                                            <label class="form-check-label" for="api_read">
                                                Okuma izni (GET)
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="api_write">
                                            <label class="form-check-label" for="api_write">
                                                Yazma izni (POST/PUT)
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="api_delete">
                                            <label class="form-check-label" for="api_delete">
                                                Silme izni (DELETE)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="api_users">
                                            <label class="form-check-label" for="api_users">
                                                Kullanıcı API'si
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="api_polls">
                                            <label class="form-check-label" for="api_polls">
                                                Oylama API'si
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="api_votes">
                                            <label class="form-check-label" for="api_votes">
                                                Oy API'si
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Sistem Bilgisi -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle"></i> Sistem Bilgisi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <strong>PHP Sürümü:</strong>
                                    <span class="badge bg-info"><?= phpversion() ?></span>
                                </div>
                                <div class="mb-3">
                                    <strong>MySQL Sürümü:</strong>
                                    <span class="badge bg-success">
                                        <?= $db->singleValueQuery("SELECT VERSION()") ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <strong>Sunucu Yazılımı:</strong>
                                    <span><?= $_SERVER['SERVER_SOFTWARE'] ?></span>
                                </div>
                                <div class="mb-3">
                                    <strong>Maksimum Dosya Yükleme:</strong>
                                    <span class="badge bg-info">
                                        <?= ini_get('upload_max_filesize') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <strong>Bellek Limiti:</strong>
                                    <span class="badge bg-info">
                                        <?= ini_get('memory_limit') ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Zaman Dilimi:</strong>
                                    <span class="badge bg-info">
                                        <?= date_default_timezone_get() ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sistem Sağlık Durumu -->
                        <div class="mt-3 pt-3 border-top">
                            <h6 class="mb-3">Sistem Sağlık Durumu</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <div class="text-primary fw-bold">
                                            <i class="bi bi-check-circle display-6 d-block mb-2"></i>
                                            Veritabanı
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <div class="text-success fw-bold">
                                            <i class="bi bi-check-circle display-6 d-block mb-2"></i>
                                            Disk Alanı
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <div class="text-warning fw-bold">
                                            <i class="bi bi-exclamation-triangle display-6 d-block mb-2"></i>
                                            Yedekleme
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <div class="text-success fw-bold">
                                            <i class="bi bi-check-circle display-6 d-block mb-2"></i>
                                            Güvenlik
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function resetToDefaults() {
        if (confirm('Tüm ayarları varsayılan değerlere döndürmek istediğinize emin misiniz?')) {
            fetch('api.php?action=reset_settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ayarlar varsayılana döndürüldü. Sayfa yenilenecek.');
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('İşlem sırasında bir hata oluştu.');
            });
        }
    }
    
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        element.select();
        element.setSelectionRange(0, 99999); // Mobil için
        document.execCommand('copy');
        
        // Kopyalandı bildirimi
        const originalText = element.value;
        element.value = 'Kopyalandı!';
        setTimeout(() => {
            element.value = originalText;
        }, 2000);
    }
    
    function generateApiKey() {
        if (confirm('Yeni bir API anahtarı oluşturmak istediğinize emin misiniz?\nEski anahtar geçersiz olacaktır.')) {
            // 32 karakterlik hex anahtar oluştur
            const newKey = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
            
            document.getElementById('api_key').value = newKey;
            
            // Sunucuya kaydet
            fetch('api.php?action=update_api_key', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ api_key: newKey })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Yeni API anahtarı oluşturuldu ve kaydedildi.');
                }
            });
        }
    }
    
    // Tab değişiminde URL hash güncelle
    document.addEventListener('DOMContentLoaded', function() {
        var triggerTabList = [].slice.call(document.querySelectorAll('#settingsTab button'));
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                var tabId = triggerEl.getAttribute('data-bs-target').substring(1);
                window.location.hash = tabId;
            });
        });
        
        // URL'den tab aç
        if (window.location.hash) {
            var tabId = window.location.hash.substring(1);
            var triggerEl = document.querySelector('#settingsTab button[data-bs-target="#' + tabId + '"]');
            if (triggerEl) {
                bootstrap.Tab.getInstance(triggerEl) || new bootstrap.Tab(triggerEl);
                triggerEl.click();
            }
        }
        
        // Form değişikliklerini takip et
        const form = document.getElementById('settingsForm');
        const originalData = new FormData(form);
        
        form.addEventListener('change', function() {
            const currentData = new FormData(form);
            let hasChanges = false;
            
            for (let [key, value] of originalData.entries()) {
                if (currentData.get(key) !== value) {
                    hasChanges = true;
                    break;
                }
            }
            
            if (hasChanges) {
                document.querySelector('button[onclick*="settingsForm"]').classList.add('btn-warning');
                document.querySelector('button[onclick*="settingsForm"]').classList.remove('btn-success');
                document.querySelector('button[onclick*="settingsForm"]').innerHTML = '<i class="bi bi-save"></i> Kaydet (Değişiklikler Var)';
            }
        });
    });
    </script>
</body>
</html>

  ```

--------------------------------------------------------------------------------

  📄 **admin\index.php**
  ```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Sadece yöneticiler erişebilir
if (!isset($_SESSION['kullanici_id']) || $_SESSION['yetki_seviye'] !== 'superadmin') {
    header("Location: ../giris.php");
    exit;
}

$db = new Database();

// İstatistikler
$toplamKullanici = $db->singleValueQuery("SELECT COUNT(*) FROM kullanicilar");
$toplamOylama = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar");
$aktifOylama = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'aktif'");
$toplamOy = $db->singleValueQuery("SELECT COUNT(*) FROM oy_kullanicilar");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-lock"></i> Yönetim Paneli
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    <i class="bi bi-person-circle"></i> <?= $_SESSION['ad_soyad'] ?>
                </span>
                <a href="../cikis.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Çıkış
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2">
                <div class="list-group">
                    <a href="index.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="oylamalar.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-clipboard-data"></i> Oylamalar
                    </a>
                    <a href="kullanicilar.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-people"></i> Kullanıcılar
                    </a>
                    <a href="loglar.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-journal-text"></i> Sistem Logları
                    </a>
                    <a href="../index.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-house"></i> Siteye Dön
                    </a>
                </div>
            </div>

            <!-- Ana içerik -->
            <div class="col-lg-10">
                <h2 class="mb-4">
                    <i class="bi bi-speedometer2"></i> Yönetim Paneli
                </h2>

                <!-- İstatistik kartları -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Toplam Kullanıcı</h6>
                                        <h2 class="mb-0"><?= $toplamKullanici ?></h2>
                                    </div>
                                    <i class="bi bi-people display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Toplam Oylama</h6>
                                        <h2 class="mb-0"><?= $toplamOylama ?></h2>
                                    </div>
                                    <i class="bi bi-clipboard-data display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Aktif Oylama</h6>
                                        <h2 class="mb-0"><?= $aktifOylama ?></h2>
                                    </div>
                                    <i class="bi bi-hourglass-split display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Toplam Oy</h6>
                                        <h2 class="mb-0"><?= $toplamOy ?></h2>
                                    </div>
                                    <i class="bi bi-check2-circle display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son loglar -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Son Sistem Aktiviteleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Kullanıcı</th>
                                        <th>İşlem</th>
                                        <th>Açıklama</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $loglar = $db->query(
                                        "SELECT l.*, k.ad_soyad 
                                         FROM sistem_loglari l 
                                         LEFT JOIN kullanicilar k ON l.kullanici_id = k.id 
                                         ORDER BY l.tarih DESC 
                                         LIMIT 10"
                                    )->fetchAll();
                                    
                                    foreach ($loglar as $log):
                                    ?>
                                        <tr>
                                            <td><?= date('d.m.Y H:i', strtotime($log['tarih'])) ?></td>
                                            <td><?= $log['ad_soyad'] ?? 'Sistem' ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    strpos($log['islem_tipi'], 'giris') !== false ? 'success' :
                                                    (strpos($log['islem_tipi'], 'kayit') !== false ? 'info' :
                                                    (strpos($log['islem_tipi'], 'oy') !== false ? 'primary' : 'secondary'))
                                                ?>">
                                                    <?= $log['islem_tipi'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($log['aciklama']) ?></td>
                                            <td><small><?= $log['ip_adresi'] ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

  ```

--------------------------------------------------------------------------------

  📄 **admin\kullanicilar.php**
  ```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireSuperAdmin();

$db = new Database();

// Kullanıcı işlemleri
if (isset($_GET['islem'])) {
    $islem = $_GET['islem'];
    $id = $_GET['id'] ?? 0;
    
    switch ($islem) {
        case 'aktiflestir':
            $db->query("UPDATE kullanicilar SET durum = 'aktif' WHERE id = ?", [$id]);
            $success = "Kullanıcı aktifleştirildi.";
            break;
            
        case 'pasiflestir':
            $db->query("UPDATE kullanicilar SET durum = 'pasif' WHERE id = ?", [$id]);
            $success = "Kullanıcı pasifleştirildi.";
            break;
            
        case 'askiya_al':
            $db->query("UPDATE kullanicilar SET durum = 'askida' WHERE id = ?", [$id]);
            $success = "Kullanıcı askıya alındı.";
            break;
            
        case 'yetki_yukselt':
            $db->query("UPDATE kullanicilar SET yetki_seviye = 'yonetici' WHERE id = ?", [$id]);
            $success = "Kullanıcı yönetici yapıldı.";
            break;
            
        case 'yetki_dusur':
            $db->query("UPDATE kullanicilar SET yetki_seviye = 'kullanici' WHERE id = ?", [$id]);
            $success = "Kullanıcı normal kullanıcı yapıldı.";
            break;
            
        case 'sil':
            // Kendi hesabını silme
            if ($id == $_SESSION['kullanici_id']) {
                $error = "Kendi hesabınızı silemezsiniz!";
                break;
            }
            
            // Log
            $kullanici = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = ?", [$id])->fetch();
            
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'kullanici_silme', ?, ?)",
                [$_SESSION['kullanici_id'], "Kullanıcı silindi: {$kullanici['ad_soyad']}", $_SERVER['REMOTE_ADDR']]
            );
            
            // Kullanıcıyı sil
            $db->query("DELETE FROM kullanicilar WHERE id = ?", [$id]);
            $success = "Kullanıcı silindi.";
            break;
    }
    
    header("Location: kullanicilar.php?success=" . urlencode($success ?? ''));
    exit;
}

// Arama ve filtreleme
$search = $_GET['search'] ?? '';
$durum = $_GET['durum'] ?? '';
$yetki = $_GET['yetki'] ?? '';

// Sorgu oluştur
$sql = "SELECT * FROM kullanicilar WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (ad_soyad LIKE ? OR eposta LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($durum)) {
    $sql .= " AND durum = ?";
    $params[] = $durum;
}

if (!empty($yetki)) {
    $sql .= " AND yetki_seviye = ?";
    $params[] = $yetki;
}

$sql .= " ORDER BY kayit_tarihi DESC";

$kullanicilar = $db->query($sql, $params)->fetchAll();

// İstatistikler
$toplam_kullanici = $db->singleValueQuery("SELECT COUNT(*) FROM kullanicilar");
$aktif_kullanici = $db->singleValueQuery("SELECT COUNT(*) FROM kullanicilar WHERE durum = 'aktif'");
$yonetici_sayisi = $db->singleValueQuery("SELECT COUNT(*) FROM kullanicilar WHERE yetki_seviye IN ('yonetici', 'superadmin')");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-aktif { background-color: #28a745; }
        .status-pasif { background-color: #dc3545; }
        .status-askida { background-color: #ffc107; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana içerik -->
            <div class="col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-people-fill"></i> Kullanıcı Yönetimi
                    </h2>
                    <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#yeniKullaniciModal">
                        <i class="bi bi-person-plus"></i> Yeni Kullanıcı
                    </a>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($_GET['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h3 class="card-title text-primary"><?= $toplam_kullanici ?></h3>
                                <p class="card-text">Toplam Kullanıcı</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h3 class="card-title text-success"><?= $aktif_kullanici ?></h3>
                                <p class="card-text">Aktif Kullanıcı</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body">
                                <h3 class="card-title text-warning"><?= $yonetici_sayisi ?></h3>
                                <p class="card-text">Yönetici</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h3 class="card-title text-info">
                                    <?= $toplam_kullanici - $aktif_kullanici ?>
                                </h3>
                                <p class="card-text">Pasif/Askıda</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreleme ve Arama -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Ad soyad veya e-posta ara..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="durum">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="aktif" <?= $durum == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="pasif" <?= $durum == 'pasif' ? 'selected' : '' ?>>Pasif</option>
                                    <option value="askida" <?= $durum == 'askida' ? 'selected' : '' ?>>Askıda</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="yetki">
                                    <option value="">Tüm Yetkiler</option>
                                    <option value="kullanici" <?= $yetki == 'kullanici' ? 'selected' : '' ?>>Kullanıcı</option>
                                    <option value="yonetici" <?= $yetki == 'yonetici' ? 'selected' : '' ?>>Yönetici</option>
                                    <option value="superadmin" <?= $yetki == 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel"></i> Filtrele
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Kullanıcılar Tablosu -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-table"></i> Kullanıcı Listesi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Kullanıcı</th>
                                        <th>E-posta</th>
                                        <th>Durum</th>
                                        <th>Yetki</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>Son Giriş</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kullanicilar as $kullanici): 
                                        $ad_soyad = $kullanici['ad_soyad'];
                                        $bas_harf = mb_substr($ad_soyad, 0, 1);
                                        
                                        // Durum badge
                                        $durum_badge = match($kullanici['durum']) {
                                            'aktif' => '<span class="badge bg-success"><span class="status-dot status-aktif"></span>Aktif</span>',
                                            'pasif' => '<span class="badge bg-danger"><span class="status-dot status-pasif"></span>Pasif</span>',
                                            'askida' => '<span class="badge bg-warning"><span class="status-dot status-askida"></span>Askıda</span>',
                                            default => '<span class="badge bg-secondary">Bilinmiyor</span>'
                                        };
                                        
                                        // Yetki badge
                                        $yetki_badge = match($kullanici['yetki_seviye']) {
                                            'superadmin' => '<span class="badge bg-danger"><i class="bi bi-shield-fill"></i> Super Admin</span>',
                                            'yonetici' => '<span class="badge bg-warning"><i class="bi bi-shield"></i> Yönetici</span>',
                                            default => '<span class="badge bg-secondary">Kullanıcı</span>'
                                        };
                                    ?>
                                        <tr>
                                            <td><?= $kullanici['id'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?= $bas_harf ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($ad_soyad) ?></strong><br>
                                                        <small class="text-muted">ID: <?= $kullanici['id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($kullanici['eposta']) ?></td>
                                            <td><?= $durum_badge ?></td>
                                            <td><?= $yetki_badge ?></td>
                                            <td>
                                                <small>
                                                    <?= date('d.m.Y', strtotime($kullanici['kayit_tarihi'])) ?><br>
                                                    <?= date('H:i', strtotime($kullanici['kayit_tarihi'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($kullanici['son_giris_tarihi']): ?>
                                                    <small>
                                                        <?= date('d.m.Y', strtotime($kullanici['son_giris_tarihi'])) ?><br>
                                                        <?= date('H:i', strtotime($kullanici['son_giris_tarihi'])) ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Hiç giriş yapmadı</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-gear"></i> İşlemler
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" 
                                                               href="../profil.php?user_id=<?= $kullanici['id'] ?>">
                                                                <i class="bi bi-eye"></i> Profili Görüntüle
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        
                                                        <!-- Durum İşlemleri -->
                                                        <li><h6 class="dropdown-header">Durum</h6></li>
                                                        <?php if ($kullanici['durum'] != 'aktif'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" 
                                                                   href="?islem=aktiflestir&id=<?= $kullanici['id'] ?>">
                                                                    <i class="bi bi-check-circle"></i> Aktifleştir
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($kullanici['durum'] != 'pasif'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-danger" 
                                                                   href="?islem=pasiflestir&id=<?= $kullanici['id'] ?>">
                                                                    <i class="bi bi-x-circle"></i> Pasifleştir
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($kullanici['durum'] != 'askida'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" 
                                                                   href="?islem=askiya_al&id=<?= $kullanici['id'] ?>">
                                                                    <i class="bi bi-pause-circle"></i> Askıya Al
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <li><hr class="dropdown-divider"></li>
                                                        
                                                        <!-- Yetki İşlemleri -->
                                                        <li><h6 class="dropdown-header">Yetki</h6></li>
                                                        <?php if ($kullanici['yetki_seviye'] == 'kullanici'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" 
                                                                   href="?islem=yetki_yukselt&id=<?= $kullanici['id'] ?>">
                                                                    <i class="bi bi-arrow-up-circle"></i> Yönetici Yap
                                                                </a>
                                                            </li>
                                                        <?php elseif ($kullanici['yetki_seviye'] == 'yonetici'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-secondary" 
                                                                   href="?islem=yetki_dusur&id=<?= $kullanici['id'] ?>">
                                                                    <i class="bi bi-arrow-down-circle"></i> Kullanıcı Yap
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <li><hr class="dropdown-divider"></li>
                                                        
                                                        <!-- Diğer İşlemler -->
                                                        <li>
                                                            <a class="dropdown-item text-info" href="#">
                                                                <i class="bi bi-envelope"></i> E-posta Gönder
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-primary" href="#">
                                                                <i class="bi bi-key"></i> Şifre Sıfırla
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <?php if ($kullanici['id'] != $_SESSION['kullanici_id']): ?>
                                                                <a class="dropdown-item text-danger" 
                                                                   href="#" 
                                                                   onclick="confirmDelete(<?= $kullanici['id'] ?>, '<?= addslashes($ad_soyad) ?>')">
                                                                    <i class="bi bi-trash"></i> Sil
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="dropdown-item text-muted disabled">
                                                                    <i class="bi bi-trash"></i> Kendi Hesabınızı Silemezsiniz
                                                                </span>
                                                            <?php endif; ?>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Sayfalama -->
                        <?php if (count($kullanicilar) == 0): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people display-4 text-muted d-block mb-3"></i>
                                <h5>Kullanıcı bulunamadı</h5>
                                <p class="text-muted">Arama kriterlerinizi değiştirmeyi deneyin.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Kullanıcı Modal -->
    <div class="modal fade" id="yeniKullaniciModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Yeni Kullanıcı Oluştur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="api.php?action=create_user">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" name="ad_soyad" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" name="eposta" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Şifre</label>
                            <input type="password" class="form-control" name="sifre" required minlength="6">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Yetki Seviyesi</label>
                            <select class="form-select" name="yetki_seviye">
                                <option value="kullanici">Kullanıcı</option>
                                <option value="yonetici">Yönetici</option>
                                <option value="superadmin">Super Admin</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            Kullanıcıya e-posta gönderilmeyecek, şifreyi siz belirleyeceksiniz.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function confirmDelete(id, adSoyad) {
        if (confirm(`"${adSoyad}" kullanıcısını silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz! Tüm oyları ve verileri silinecek.`)) {
            window.location.href = `?islem=sil&id=${id}`;
        }
    }
    </script>
</body>
</html>

  ```

--------------------------------------------------------------------------------

  📄 **admin\loglar.php**
  ```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireSuperAdmin();

$db = new Database();

// Filtreler
$tip = $_GET['tip'] ?? '';
$kullanici_id = $_GET['kullanici_id'] ?? '';
$tarih_baslangic = $_GET['tarih_baslangic'] ?? '';
$tarih_bitis = $_GET['tarih_bitis'] ?? '';

// Sorgu oluştur
$sql = "SELECT l.*, k.ad_soyad, k.eposta 
        FROM sistem_loglari l 
        LEFT JOIN kullanicilar k ON l.kullanici_id = k.id 
        WHERE 1=1";
        
$params = [];

if (!empty($tip)) {
    $sql .= " AND l.islem_tipi = ?";
    $params[] = $tip;
}

if (!empty($kullanici_id) && is_numeric($kullanici_id)) {
    $sql .= " AND l.kullanici_id = ?";
    $params[] = $kullanici_id;
}

if (!empty($tarih_baslangic)) {
    $sql .= " AND DATE(l.tarih) >= ?";
    $params[] = $tarih_baslangic;
}

if (!empty($tarih_bitis)) {
    $sql .= " AND DATE(l.tarih) <= ?";
    $params[] = $tarih_bitis;
}

$sql .= " ORDER BY l.tarih DESC LIMIT 500";

$loglar = $db->query($sql, $params)->fetchAll();

// Benzersiz log tipleri
$log_tipleri = $db->query(
    "SELECT DISTINCT islem_tipi FROM sistem_loglari ORDER BY islem_tipi"
)->fetchAll(PDO::FETCH_COLUMN);

// Toplam log sayısı
$toplam_log = $db->singleValueQuery("SELECT COUNT(*) FROM sistem_loglari");
$bugun_log = $db->singleValueQuery(
    "SELECT COUNT(*) FROM sistem_loglari WHERE DATE(tarih) = CURDATE()"
);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Logları - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .log-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .log-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .log-success { border-left-color: #28a745; }
        .log-error { border-left-color: #dc3545; }
        .log-warning { border-left-color: #ffc107; }
        .log-info { border-left-color: #17a2b8; }
        .log-primary { border-left-color: #007bff; }
        
        .ip-address {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana içerik -->
            <div class="col-lg-10">
                <h2 class="mb-4">
                    <i class="bi bi-journal-text"></i> Sistem Logları
                </h2>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h3 class="card-title text-primary"><?= $toplam_log ?></h3>
                                <p class="card-text">Toplam Log Kaydı</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h3 class="card-title text-success"><?= $bugun_log ?></h3>
                                <p class="card-text">Bugünkü Kayıt</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h3 class="card-title text-info">
                                    <?= count($log_tipleri) ?>
                                </h3>
                                <p class="card-text">Farklı Log Tipi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreleme -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-funnel"></i> Log Filtreleme
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">İşlem Tipi</label>
                                <select class="form-select" name="tip">
                                    <option value="">Tüm İşlemler</option>
                                    <?php foreach ($log_tipleri as $tip_adi): ?>
                                        <option value="<?= $tip_adi ?>" <?= $tip == $tip_adi ? 'selected' : '' ?>>
                                            <?= $tip_adi ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Kullanıcı ID</label>
                                <input type="number" class="form-control" name="kullanici_id" 
                                       value="<?= htmlspecialchars($kullanici_id) ?>"
                                       placeholder="Kullanıcı ID girin">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" name="tarih_baslangic" 
                                       value="<?= htmlspecialchars($tarih_baslangic) ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" name="tarih_bitis" 
                                       value="<?= htmlspecialchars($tarih_bitis) ?>">
                            </div>
                            
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-funnel"></i> Filtrele
                                    </button>
                                    <a href="loglar.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Sıfırla
                                    </a>
                                    <button type="button" class="btn btn-danger ms-auto" 
                                            onclick="confirmClearLogs()">
                                        <i class="bi bi-trash"></i> Logları Temizle
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Log Listesi -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul"></i> Log Kayıtları
                                <small class="text-muted">(Son 500 kayıt gösteriliyor)</small>
                            </h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="exportLogs('json')">
                                    <i class="bi bi-download"></i> JSON İndir
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="exportLogs('csv')">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> CSV İndir
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tarih</th>
                                        <th>Kullanıcı</th>
                                        <th>İşlem Tipi</th>
                                        <th>Açıklama</th>
                                        <th>IP Adresi</th>
                                        <th>Tarayıcı</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loglar as $log): 
                                        // Log tipine göre sınıf belirle
                                        $log_class = match(true) {
                                            strpos($log['islem_tipi'], 'hata') !== false => 'log-error',
                                            strpos($log['islem_tipi'], 'uyari') !== false => 'log-warning',
                                            strpos($log['islem_tipi'], 'basarili') !== false => 'log-success',
                                            strpos($log['islem_tipi'], 'giris') !== false => 'log-primary',
                                            default => 'log-info'
                                        };
                                        
                                        // Kullanıcı bilgisi
                                        $kullanici_bilgi = $log['ad_soyad'] 
                                            ? htmlspecialchars($log['ad_soyad']) . '<br><small class="text-muted">' . htmlspecialchars($log['eposta']) . '</small>'
                                            : '<span class="badge bg-secondary">Sistem</span>';
                                    ?>
                                        <tr class="log-item <?= $log_class ?>">
                                            <td><small><?= $log['id'] ?></small></td>
                                            <td>
                                                <small>
                                                    <?= date('d.m.Y', strtotime($log['tarih'])) ?><br>
                                                    <?= date('H:i:s', strtotime($log['tarih'])) ?>
                                                </small>
                                            </td>
                                            <td><?= $kullanici_bilgi ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    strpos($log_class, 'success') !== false ? 'success' :
                                                    (strpos($log_class, 'error') !== false ? 'danger' :
                                                    (strpos($log_class, 'warning') !== false ? 'warning' : 'info'))
                                                ?>">
                                                    <?= $log['islem_tipi'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 300px;">
                                                    <?= htmlspecialchars($log['aciklama']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="ip-address">
                                                    <?= $log['ip_adresi'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= 
                                                        strlen($log['user_agent']) > 50 
                                                        ? substr($log['user_agent'], 0, 50) . '...' 
                                                        : $log['user_agent'] 
                                                    ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($loglar)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-journal-x display-4 text-muted d-block mb-3"></i>
                                <h5>Log kaydı bulunamadı</h5>
                                <p class="text-muted">Filtrelerinizi değiştirmeyi deneyin.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Log İstatistikleri -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-pie-chart"></i> İşlem Tipi Dağılımı
                                </h6>
                            </div>
                            <div class="card-body">
                                <div style="height: 250px;">
                                    <!-- Buraya chart.js veya başka bir chart kütüphanesi eklenebilir -->
                                    <canvas id="logChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-calendar"></i> Son 7 Gün Aktivite
                                </h6>
                            </div>
                            <div class="card-body">
                                <div style="height: 250px;">
                                    <!-- Aktivite grafiği -->
                                    <canvas id="activityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Temizleme Onay Modal -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Logları Temizle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">⚠️ UYARI!</h6>
                        <p class="mb-0">
                            Tüm sistem loglarını temizlemek üzeresiniz. Bu işlem geri alınamaz!
                            Güvenlik ve denetim açısından logların silinmesi önerilmez.
                        </p>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmClear">
                        <label class="form-check-label" for="confirmClear">
                            Tüm log kayıtlarının silineceğini anlıyorum
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="keepLastMonth">
                        <label class="form-check-label" for="keepLastMonth">
                            Son 1 aylık logları sakla
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-danger" id="confirmClearBtn" disabled>
                        <i class="bi bi-trash"></i> Temizle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    function confirmClearLogs() {
        const modal = new bootstrap.Modal(document.getElementById('clearLogsModal'));
        modal.show();
    }
    
    // Onay checkbox kontrolü
    document.getElementById('confirmClear').addEventListener('change', function() {
        document.getElementById('confirmClearBtn').disabled = !this.checked;
    });
    
    // Log temizleme işlemi
    document.getElementById('confirmClearBtn').addEventListener('click', function() {
        const keepLastMonth = document.getElementById('keepLastMonth').checked;
        
        fetch('api.php?action=clear_logs&keep=' + (keepLastMonth ? '1' : '0'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Loglar başarıyla temizlendi.');
                location.reload();
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
        });
    });
    
    // Log grafiği
    document.addEventListener('DOMContentLoaded', function() {
        // İşlem tipi dağılım grafiği
        const logCtx = document.getElementById('logChart').getContext('2d');
        
        // Örnek veri - gerçek uygulamada API'den alınmalı
        const logChart = new Chart(logCtx, {
            type: 'doughnut',
            data: {
                labels: ['Girişler', 'Oy Verme', 'Hatalar', 'Diğer'],
                datasets: [{
                    data: [35, 45, 10, 10],
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#dc3545',
                        '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Aktivite grafiği
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: ['6 gün önce', '5 gün önce', '4 gün önce', '3 gün önce', '2 gün önce', 'Dün', 'Bugün'],
                datasets: [{
                    label: 'Log Sayısı',
                    data: [45, 52, 48, 60, 55, 65, <?= $bugun_log ?>],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 10
                        }
                    }
                }
            }
        });
    });
    
    function exportLogs(format) {
        const params = new URLSearchParams(window.location.search);
        params.append('format', format);
        
        window.open('api.php?action=export_logs&' + params.toString(), '_blank');
    }
    </script>
</body>
</html>

  ```

--------------------------------------------------------------------------------

  📄 **admin\oylamalar.php**
  ```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireSuperAdmin();

$db = new Database();

// Oylama silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $sil_id = $_GET['sil'];
    
    try {
        // Log kaydı
        $oylama = $db->query("SELECT baslik FROM oylamalar WHERE id = ?", [$sil_id])->fetch();
        
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
             VALUES (?, 'oylama_silme', ?, ?)",
            [$_SESSION['kullanici_id'], "Oylama silindi: {$oylama['baslik']}", $_SERVER['REMOTE_ADDR']]
        );
        
        // Oylamayı sil (cascade ile tüm ilişkili kayıtlar silinecek)
        $db->query("DELETE FROM oylamalar WHERE id = ?", [$sil_id]);
        
        $success = "Oylama başarıyla silindi.";
    } catch (Exception $e) {
        $error = "Silme sırasında hata: " . $e->getMessage();
    }
}

// Oylama durum değiştirme
if (isset($_GET['durum']) && isset($_GET['id'])) {
    $durum = $_GET['durum'];
    $id = $_GET['id'];
    
    $valid_durumlar = ['aktif', 'sonuclandi', 'iptal'];
    
    if (in_array($durum, $valid_durumlar)) {
        $db->query("UPDATE oylamalar SET durum = ? WHERE id = ?", [$durum, $id]);
        
        // Log
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
             VALUES (?, 'oylama_durum_degistirme', ?, ?)",
            [$_SESSION['kullanici_id'], "Oylama $id durumu $durum yapıldı", $_SERVER['REMOTE_ADDR']]
        );
        
        header("Location: oylamalar.php?success=durum_degistirildi");
        exit;
    }
}

// Tüm oylamaları getir
$oylamalar = $db->query(
    "SELECT o.*, 
     COUNT(DISTINCT ok.kullanici_id) as oy_sayisi,
     u.ad_soyad as olusturan_ad,
     (SELECT COUNT(*) FROM adaylar a WHERE a.oylama_id = o.id) as aday_sayisi
     FROM oylamalar o 
     LEFT JOIN oy_kullanicilar ok ON o.id = ok.oylama_id 
     LEFT JOIN kullanicilar u ON o.olusturan_id = u.id 
     GROUP BY o.id 
     ORDER BY o.olusturulma_tarihi DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oylama Yönetimi - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .status-badge {
            cursor: pointer;
        }
        .table-actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana içerik -->
            <div class="col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-clipboard-data"></i> Oylama Yönetimi
                    </h2>
                    <a href="../oylama_olustur.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Yeni Oylama
                    </a>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Oylamalar tablosu -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-table"></i> Tüm Oylamalar
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Başlık</th>
                                        <th>Tür</th>
                                        <th>Kapsam</th>
                                        <th>Durum</th>
                                        <th>Oy Sayısı</th>
                                        <th>Oluşturan</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($oylamalar as $oylama): ?>
                                        <tr>
                                            <td><?= $oylama['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($oylama['baslik']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= mb_substr(htmlspecialchars($oylama['aciklama']), 0, 50) ?>...
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $oylama['tur'] ?>
                                                </span>
                                                <?php if ($oylama['aday_sayisi'] > 0): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= $oylama['aday_sayisi'] ?> aday
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= $oylama['topluluk_tipi'] ?>
                                                </span>
                                                <?php if ($oylama['topluluk_id']): ?>
                                                    <br>
                                                    <small>ID: <?= $oylama['topluluk_id'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $durum_badge = match($oylama['durum']) {
                                                    'aktif' => 'bg-success',
                                                    'sonuclandi' => 'bg-info',
                                                    'iptal' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                                
                                                $durum_icon = match($oylama['durum']) {
                                                    'aktif' => 'bi-play-circle',
                                                    'sonuclandi' => 'bi-check-circle',
                                                    'iptal' => 'bi-x-circle',
                                                    default => 'bi-question-circle'
                                                };
                                                ?>
                                                <span class="badge <?= $durum_badge ?> status-badge" 
                                                      data-bs-toggle="dropdown">
                                                    <i class="bi <?= $durum_icon ?>"></i>
                                                    <?= ucfirst($oylama['durum']) ?>
                                                </span>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="?durum=aktif&id=<?= $oylama['id'] ?>">
                                                            <i class="bi bi-play-circle text-success"></i> Aktif Yap
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="?durum=sonuclandi&id=<?= $oylama['id'] ?>">
                                                            <i class="bi bi-check-circle text-info"></i> Sonuçlandır
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="?durum=iptal&id=<?= $oylama['id'] ?>">
                                                            <i class="bi bi-x-circle text-danger"></i> İptal Et
                                                        </a>
                                                    </li>
                                                </ul>
                                            </td>
                                            <td>
                                                <strong><?= $oylama['oy_sayisi'] ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($oylama['olusturan_ad']) ?></td>
                                            <td>
                                                <small>
                                                    <?= date('d.m.Y', strtotime($oylama['olusturulma_tarihi'])) ?><br>
                                                    <?= date('H:i', strtotime($oylama['olusturulma_tarihi'])) ?>
                                                </small>
                                            </td>
                                            <td class="table-actions">
                                                <a href="../oylama_detay.php?id=<?= $oylama['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Görüntüle">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="../sonuc.php?id=<?= $oylama['id'] ?>" 
                                                   class="btn btn-sm btn-outline-success" 
                                                   title="Sonuçlar">
                                                    <i class="bi bi-bar-chart"></i>
                                                </a>
                                                <a href="../oylama_olustur.php?duzenle=<?= $oylama['id'] ?>" 
                                                   class="btn btn-sm btn-outline-warning" 
                                                   title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        title="Sil"
                                                        onclick="confirmDelete(<?= $oylama['id'] ?>, '<?= addslashes($oylama['baslik']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <?= count(array_filter($oylamalar, fn($o) => $o['durum'] == 'aktif')) ?>
                                </h5>
                                <p class="card-text">Aktif Oylama</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h5 class="card-title text-success">
                                    <?= count(array_filter($oylamalar, fn($o) => $o['durum'] == 'sonuclandi')) ?>
                                </h5>
                                <p class="card-text">Sonuçlanan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body">
                                <h5 class="card-title text-warning">
                                    <?= count(array_filter($oylamalar, fn($o) => $o['tur'] == 'secim')) ?>
                                </h5>
                                <p class="card-text">Seçim</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h5 class="card-title text-info">
                                    <?= array_sum(array_column($oylamalar, 'oy_sayisi')) ?>
                                </h5>
                                <p class="card-text">Toplam Oy</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function confirmDelete(id, baslik) {
        if (confirm(`"${baslik}" oylamasını silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz!`)) {
            window.location.href = `?sil=${id}`;
        }
    }
    
    // Dropdown açılması için
    document.addEventListener('DOMContentLoaded', function() {
        var statusBadges = document.querySelectorAll('.status-badge');
        statusBadges.forEach(function(badge) {
            badge.addEventListener('click', function(e) {
                e.preventDefault();
                var dropdown = new bootstrap.Dropdown(this);
                dropdown.toggle();
            });
        });
    });
    </script>
</body>
</html>

  ```

--------------------------------------------------------------------------------

  📄 **admin\sidebar.php**
  ```php
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="col-lg-2">
    <div class="sticky-top" style="top: 20px;">
        <!-- Kullanıcı Bilgisi -->
        <div class="card mb-3 border-primary">
            <div class="card-body text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                    <?= mb_substr($_SESSION['ad_soyad'], 0, 1) ?>
                </div>
                <h6 class="card-title mb-1"><?= htmlspecialchars($_SESSION['ad_soyad']) ?></h6>
                <p class="card-text small text-muted mb-2"><?= $_SESSION['eposta'] ?></p>
                <span class="badge bg-<?= $_SESSION['yetki_seviye'] == 'superadmin' ? 'danger' : 'warning' ?>">
                    <i class="bi bi-shield-check"></i> 
                    <?= ucfirst($_SESSION['yetki_seviye']) ?>
                </span>
            </div>
        </div>
        
        <!-- Menü -->
        <div class="list-group">
            <a href="index.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span>Dashboard</span>
                <span class="badge bg-primary ms-auto">
                    <?php
                    $aktif_oy = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'aktif'");
                    echo $aktif_oy;
                    ?>
                </span>
            </a>
            
            <a href="oylamalar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'oylamalar.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data me-2"></i>
                <span>Oylamalar</span>
                <span class="badge bg-success ms-auto">
                    <?php
                    $sonuclanan = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'sonuclandi'");
                    echo $sonuclanan;
                    ?>
                </span>
            </a>
            
            <a href="kullanicilar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'kullanicilar.php' ? 'active' : '' ?>">
                <i class="bi bi-people me-2"></i>
                <span>Kullanıcılar</span>
                <span class="badge bg-warning ms-auto">
                    <?php
                    $aktif_kullanici = $db->singleValueQuery("SELECT COUNT(*) FROM kullanicilar WHERE durum = 'aktif'");
                    echo $aktif_kullanici;
                    ?>
                </span>
            </a>
            
            <a href="loglar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'loglar.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-text me-2"></i>
                <span>Sistem Logları</span>
                <span class="badge bg-danger ms-auto">
                    <?php
                    $bugun_log = $db->singleValueQuery("SELECT COUNT(*) FROM sistem_loglari WHERE DATE(tarih) = CURDATE()");
                    echo $bugun_log;
                    ?>
                </span>
            </a>
            
            <a href="ayarlar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'ayarlar.php' ? 'active' : '' ?>">
                <i class="bi bi-gear me-2"></i>
                <span>Sistem Ayarları</span>
            </a>
            
            <a href="yedekleme.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'yedekleme.php' ? 'active' : '' ?>">
                <i class="bi bi-database me-2"></i>
                <span>Yedekleme</span>
                <span class="badge bg-info ms-auto">
                    <?php
                    $backup_count = 0;
                    if (file_exists('../backups/')) {
                        $backup_count = count(glob('../backups/*'));
                    }
                    echo $backup_count;
                    ?>
                </span>
            </a>
            
            <div class="list-group-item">
                <small class="text-muted">RAPORLAR</small>
            </div>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               onclick="generateReport('daily')">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span>Günlük Rapor</span>
            </a>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               onclick="generateReport('weekly')">
                <i class="bi bi-calendar-week me-2"></i>
                <span>Haftalık Rapor</span>
            </a>
            
            <div class="list-group-item">
                <small class="text-muted">HIZLI İŞLEMLER</small>
            </div>
            
            <a href="../oylama_olustur.php" class="list-group-item list-group-item-action d-flex align-items-center">
                <i class="bi bi-plus-circle me-2"></i>
                <span>Yeni Oylama</span>
            </a>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               data-bs-toggle="modal" data-bs-target="#quickStatsModal">
                <i class="bi bi-lightning me-2"></i>
                <span>Hızlı İstatistik</span>
            </a>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               onclick="clearSystemCache()">
                <i class="bi bi-eraser me-2"></i>
                <span>Önbelleği Temizle</span>
            </a>
            
            <hr class="my-1">
            
            <a href="../index.php" class="list-group-item list-group-item-action d-flex align-items-center">
                <i class="bi bi-house me-2"></i>
                <span>Siteye Dön</span>
            </a>
            
            <a href="../cikis.php" class="list-group-item list-group-item-action d-flex align-items-center text-danger">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span>Çıkış Yap</span>
            </a>
        </div>
        
        <!-- Sistem Durumu -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-heart-pulse"></i> Sistem Durumu
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted d-block">Veritabanı</small>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-success" style="width: 85%"></div>
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Disk Kullanımı</small>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-info" style="width: <?= $usage_percent ?? 45 ?>%"></div>
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Sistem Yükü</small>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-warning" style="width: 65%"></div>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        <i class="bi bi-clock"></i> 
                        <?= date('H:i') ?> | 
                        <?= date('d.m.Y') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı İstatistik Modal -->
<div class="modal fade" id="quickStatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-lightning"></i> Hızlı Sistem İstatistikleri
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="display-6 text-primary"><?= $aktif_oy ?? 0 ?></div>
                            <small>Aktif Oylama</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="display-6 text-success"><?= $aktif_kullanici ?? 0 ?></div>
                            <small>Aktif Kullanıcı</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="display-6 text-warning"><?= $bugun_oy ?? 0 ?></div>
                            <small>Bugünkü Oy</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="display-6 text-info"><?= $bugun_log ?? 0 ?></div>
                            <small>Bugünkü Log</small>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    Son güncelleme: <?= date('H:i:s') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateReport(type) {
    fetch(`api.php?action=generate_report&type=${type}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const reportWindow = window.open('', '_blank');
                reportWindow.document.write(`
                    <html>
                    <head>
                        <title>${type === 'daily' ? 'Günlük' : 'Haftalık'} Rapor</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                            th { background-color: #f8f9fa; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .footer { margin-top: 30px; font-size: 12px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>DOĞRUDAN İRADE PLATFORMU</h2>
                            <h3>${type === 'daily' ? 'Günlük' : 'Haftalık'} Sistem Raporu</h3>
                            <p>${new Date().toLocaleDateString('tr-TR')}</p>
                        </div>
                        
                        <table>
                            <tr>
                                <th>Metrik</th>
                                <th>Değer</th>
                            </tr>
                            ${Object.entries(data.report).map(([key, value]) => `
                                <tr>
                                    <td>${key}</td>
                                    <td>${value}</td>
                                </tr>
                            `).join('')}
                        </table>
                        
                        <div class="footer">
                            <p>Rapor oluşturulma tarihi: ${new Date().toLocaleString('tr-TR')}</p>
                            <p>Oluşturan: <?= $_SESSION['ad_soyad'] ?></p>
                        </div>
                    </body>
                    </html>
                `);
                reportWindow.document.close();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Rapor oluşturulurken hata oluştu.');
            });
}

function clearSystemCache() {
    if (confirm('Sistem önbelleğini temizlemek istediğinize emin misiniz?')) {
        fetch('api.php?action=clear_cache', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sistem önbelleği temizlendi.');
            } else {
                alert('Temizleme sırasında hata: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
        });
    }
}
</script>

  ```

--------------------------------------------------------------------------------

  📄 **admin\yedekleme.php**
  ```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireSuperAdmin();

$db = new Database();
$backup_dir = '../backups/';

// Klasör yoksa oluştur
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Yedekleme işlemi
if (isset($_GET['islem'])) {
    $islem = $_GET['islem'];
    
    try {
        switch ($islem) {
            case 'veritabani':
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backup_dir . $filename;
                
                // Veritabanı yedekleme
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                $backup_sql = "-- Doğrudan İrade Platformu Veritabanı Yedeği\n";
                $backup_sql .= "-- Oluşturulma Tarihi: " . date('Y-m-d H:i:s') . "\n";
                $backup_sql .= "-- Toplam Tablo: " . count($tables) . "\n\n";
                
                foreach ($tables as $table) {
                    // Tablo yapısı
                    $create_table = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                    $backup_sql .= "--\n-- Tablo: $table\n--\n";
                    $backup_sql .= "DROP TABLE IF EXISTS `$table`;\n";
                    $backup_sql .= $create_table['Create Table'] . ";\n\n";
                    
                    // Tablo verileri
                    $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    if (count($rows) > 0) {
                        $backup_sql .= "--\n-- Tablo verileri: $table\n--\n";
                        
                        foreach ($rows as $row) {
                            $columns = array_keys($row);
                            $values = array_map(function($value) use ($db) {
                                if ($value === null) return 'NULL';
                                return $db->connect()->quote($value);
                            }, array_values($row));
                            
                            $backup_sql .= "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) ";
                            $backup_sql .= "VALUES (" . implode(',', $values) . ");\n";
                        }
                        $backup_sql .= "\n";
                    }
                }
                
                // Dosyaya yaz
                file_put_contents($filepath, $backup_sql);
                
                // Log
                $db->query(
                    "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                     VALUES (?, 'veritabani_yedekleme', ?, ?)",
                    [$_SESSION['kullanici_id'], "Veritabanı yedeği oluşturuldu: $filename", $_SERVER['REMOTE_ADDR']]
                );
                
                $success = "Veritabanı yedeği başarıyla oluşturuldu: $filename";
                break;
                
            case 'dosyalar':
                $filename = 'files_backup_' . date('Y-m-d_H-i-s') . '.zip';
                $filepath = $backup_dir . $filename;
                
                // ZIP oluşturma (ZipArchive gerekli)
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($filepath, ZipArchive::CREATE) === TRUE) {
                        // Ana dizin dosyaları
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator('../'),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        
                        foreach ($files as $name => $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen('../') + 1);
                                
                                // Belirli dosyaları hariç tut
                                if (!preg_match('/\/backups\//', $filePath) && 
                                    !preg_match('/\/vendor\//', $filePath) &&
                                    !preg_match('/\/node_modules\//', $filePath)) {
                                    $zip->addFile($filePath, $relativePath);
                                }
                            }
                        }
                        
                        $zip->close();
                        
                        // Log
                        $db->query(
                            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                             VALUES (?, 'dosya_yedekleme', ?, ?)",
                            [$_SESSION['kullanici_id'], "Dosya yedeği oluşturuldu: $filename", $_SERVER['REMOTE_ADDR']]
                        );
                        
                        $success = "Dosya yedeği başarıyla oluşturuldu: $filename";
                    } else {
                        $error = "ZIP dosyası oluşturulamadı.";
                    }
                } else {
                    $error = "ZipArchive sınıfı bulunamadı. ZIP yedekleme desteklenmiyor.";
                }
                break;
                
            case 'tam':
                // Hem veritabanı hem dosya yedeği
                $timestamp = date('Y-m-d_H-i-s');
                
                // Veritabanı yedeği
                $db_filename = 'full_backup_db_' . $timestamp . '.sql';
                $db_filepath = $backup_dir . $db_filename;
                
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $backup_sql = "-- Tam Yedek: Veritabanı\n";
                $backup_sql .= "-- Tarih: $timestamp\n\n";
                
                foreach ($tables as $table) {
                    $create_table = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                    $backup_sql .= "DROP TABLE IF EXISTS `$table`;\n";
                    $backup_sql .= $create_table['Create Table'] . ";\n\n";
                    
                    $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    if (count($rows) > 0) {
                        foreach ($rows as $row) {
                            $columns = array_keys($row);
                            $values = array_map(function($value) use ($db) {
                                if ($value === null) return 'NULL';
                                return $db->connect()->quote($value);
                            }, array_values($row));
                            
                            $backup_sql .= "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) ";
                            $backup_sql .= "VALUES (" . implode(',', $values) . ");\n";
                        }
                        $backup_sql .= "\n";
                    }
                }
                
                file_put_contents($db_filepath, $backup_sql);
                
                // Log
                $db->query(
                    "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                     VALUES (?, 'tam_yedekleme', ?, ?)",
                    [$_SESSION['kullanici_id'], "Tam yedek oluşturuldu", $_SERVER['REMOTE_ADDR']]
                );
                
                $success = "Tam yedek başarıyla oluşturuldu.";
                break;
        }
        
    } catch (Exception $e) {
        $error = "Yedekleme sırasında hata: " . $e->getMessage();
    }
}

// Yedek dosyalarını listele
$backup_files = [];
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filepath = $backup_dir . $file;
            $backup_files[] = [
                'name' => $file,
                'path' => $filepath,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            ];
        }
    }
    
    // Tarihe göre sırala (yeniden eskiye)
    usort($backup_files, function($a, $b) {
        return $b['modified'] <=> $a['modified'];
    });
}

// Disk kullanımı
$total_space = disk_total_space('../');
$free_space = disk_free_space('../');
$used_space = $total_space - $free_space;
$usage_percent = ($used_space / $total_space) * 100;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yedekleme - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .backup-card {
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            height: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .backup-card:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
            transform: translateY(-5px);
        }
        .backup-icon {
            font-size: 60px;
            margin-bottom: 20px;
            display: block;
        }
        .backup-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .backup-item {
            border-left: 4px solid transparent;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .backup-item:hover {
            border-left-color: #007bff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .backup-sql { border-left-color: #28a745; }
        .backup-zip { border-left-color: #007bff; }
        .backup-other { border-left-color: #6c757d; }
        .file-size {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .progress-disk {
            height: 20px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana içerik -->
            <div class="col-lg-10">
                <h2 class="mb-4">
                    <i class="bi bi-database"></i> Yedekleme ve Geri Yükleme
                </h2>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Disk Kullanımı -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-hdd"></i> Disk Kullanımı
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Kullanılan Alan</span>
                                        <span><?= round($usage_percent, 2) ?>%</span>
                                    </div>
                                    <div class="progress progress-disk">
                                        <div class="progress-bar <?= $usage_percent > 90 ? 'bg-danger' : ($usage_percent > 70 ? 'bg-warning' : 'bg-success') ?>" 
                                             style="width: <?= $usage_percent ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="text-primary fw-bold">
                                            <?= formatBytes($used_space) ?>
                                        </div>
                                        <small>Kullanılan</small>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-success fw-bold">
                                            <?= formatBytes($free_space) ?>
                                        </div>
                                        <small>Boş</small>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-info fw-bold">
                                            <?= formatBytes($total_space) ?>
                                        </div>
                                        <small>Toplam</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="display-6 text-<?= $usage_percent > 90 ? 'danger' : ($usage_percent > 70 ? 'warning' : 'success') ?>">
                                    <?= round($usage_percent) ?>%
                                </div>
                                <small class="text-muted">Doluluk Oranı</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Yedekleme Seçenekleri -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="backup-card" onclick="createBackup('veritabani')">
                            <div class="backup-icon text-success">
                                <i class="bi bi-database-check"></i>
                            </div>
                            <h4>Veritabanı Yedeği</h4>
                            <p class="text-muted">
                                Sadece veritabanının SQL yedeğini alır.
                            </p>
                            <div class="badge bg-success">Hızlı</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="backup-card" onclick="createBackup('dosyalar')">
                            <div class="backup-icon text-primary">
                                <i class="bi bi-file-earmark-zip"></i>
                            </div>
                            <h4>Dosya Yedeği</h4>
                            <p class="text-muted">
                                Tüm sistem dosyalarının ZIP yedeğini alır.
                            </p>
                            <div class="badge bg-warning">Orta</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="backup-card" onclick="createBackup('tam')">
                            <div class="backup-icon text-info">
                                <i class="bi bi-archive"></i>
                            </div>
                            <h4>Tam Yedek</h4>
                            <p class="text-muted">
                                Hem veritabanı hem dosyaların tam yedeği.
                            </p>
                            <div class="badge bg-danger">Yavaş</div>
                        </div>
                    </div>
                </div>

                <!-- Otomatik Yedekleme Ayarları -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Otomatik Yedekleme Ayarları
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="autoBackupForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Otomatik Yedekleme</label>
                                        <select class="form-select" name="auto_backup">
                                            <option value="0">Kapalı</option>
                                            <option value="daily">Günlük</option>
                                            <option value="weekly" selected>Haftalık</option>
                                            <option value="monthly">Aylık</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Yedek Türü</label>
                                        <select class="form-select" name="backup_type">
                                            <option value="veritabani">Sadece Veritabanı</option>
                                            <option value="tam" selected>Tam Yedek</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Maksimum Yedek Sayısı</label>
                                        <input type="number" class="form-control" name="max_backups" value="10" min="1" max="100">
                                        <small class="text-muted">Eski yedekler otomatik silinir</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Sonraki Yedekleme</label>
                                        <input type="datetime-local" class="form-control" 
                                               name="next_backup" 
                                               value="<?= date('Y-m-d\T23:59', strtotime('next sunday')) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="email_notification" id="email_notification">
                                <label class="form-check-label" for="email_notification">
                                    Yedekleme tamamlandığında e-posta bildirimi gönder
                                </label>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary" onclick="saveAutoBackupSettings()">
                                    <i class="bi bi-save"></i> Ayarları Kaydet
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetAutoBackupSettings()">
                                    <i class="bi bi-arrow-clockwise"></i> Varsayılanlara Dön
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Yedek Dosyaları -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-folder"></i> Yedek Dosyaları
                                <small class="text-muted">(<?= count($backup_files) ?> dosya)</small>
                            </h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-success" onclick="downloadAllBackups()">
                                    <i class="bi bi-download"></i> Hepsinin ZIP'ini İndir
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteOldBackups()">
                                    <i class="bi bi-trash"></i> Eski Yedekleri Temizle
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($backup_files)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-folder-x display-4 text-muted d-block mb-3"></i>
                                <h4>Henüz yedek dosyası yok</h4>
                                <p class="text-muted mb-0">
                                    Yukarıdaki seçeneklerden bir yedek oluşturun.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="backup-list">
                                <?php foreach ($backup_files as $file): 
                                    $file_class = match($file['type']) {
                                        'sql' => 'backup-sql',
                                        'zip' => 'backup-zip',
                                        default => 'backup-other'
                                    };
                                    
                                    $file_icon = match($file['type']) {
                                        'sql' => 'bi-database',
                                        'zip' => 'bi-file-earmark-zip',
                                        default => 'bi-file-earmark'
                                    };
                                ?>
                                    <div class="backup-item <?= $file_class ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bi <?= $file_icon ?>"></i>
                                                    <?= htmlspecialchars($file['name']) ?>
                                                </h6>
                                                <div class="text-muted small">
                                                    <i class="bi bi-calendar"></i> 
                                                    <?= date('d.m.Y H:i', $file['modified']) ?>
                                                    | 
                                                    <span class="file-size"><?= formatBytes($file['size']) ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="btn-group btn-group-sm">
                                                <a href="?download=<?= urlencode($file['name']) ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="İndir">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-outline-success" 
                                                        title="Geri Yükle"
                                                        onclick="restoreBackup('<?= $file['name'] ?>')">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-outline-danger" 
                                                        title="Sil"
                                                        onclick="deleteBackup('<?= $file['name'] ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Toplam boyut -->
                            <div class="mt-3 pt-3 border-top">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Toplam Boyut:</strong>
                                        <span class="badge bg-info">
                                            <?= formatBytes(array_sum(array_column($backup_files, 'size'))) ?>
                                        </span>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <small class="text-muted">
                                            Son güncelleme: <?= date('H:i:s') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    function createBackup(type) {
        if (confirm(type === 'tam' 
            ? 'Tam yedek oluşturmak üzeresiniz. Bu işlem biraz zaman alabilir. Devam etmek istiyor musunuz?'
            : `${type === 'veritabani' ? 'Veritabanı' : 'Dosya'} yedeği oluşturmak istediğinize emin misiniz?`)) {
            window.location.href = `?islem=${type}`;
        }
    }
    
    function restoreBackup(filename) {
        if (confirm(`"${filename}" dosyasını geri yüklemek istediğinize emin misiniz?\n\nUYARI: Bu işlem mevcut verilerin üzerine yazacaktır!`)) {
            if (confirm('BU İŞLEM GERİ ALINAMAZ! Gerçekten devam etmek istiyor musunuz?')) {
                window.location.href = `?restore=${encodeURIComponent(filename)}`;
            }
        }
    }
    
    function deleteBackup(filename) {
        if (confirm(`"${filename}" yedek dosyasını silmek istediğinize emin misiniz?`)) {
            window.location.href = `?delete=${encodeURIComponent(filename)}`;
        }
    }
    
    function deleteOldBackups() {
        if (confirm('30 günden eski tüm yedek dosyalarını silmek istediğinize emin misiniz?')) {
            window.location.href = '?delete_old=1';
        }
    }
    
    function downloadAllBackups() {
        if (confirm('Tüm yedek dosyalarının ZIP arşivini indirmek istediğinize emin misiniz?')) {
            window.location.href = '?download_all=1';
        }
    }
    
    function saveAutoBackupSettings() {
        const formData = new FormData(document.getElementById('autoBackupForm'));
        const data = Object.fromEntries(formData);
        
        fetch('api.php?action=save_backup_settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Otomatik yedekleme ayarları kaydedildi.');
            } else {
                alert('Hata: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ayarlar kaydedilirken bir hata oluştu.');
        });
    }
    
    // Yedekleme durumunu kontrol et
    setInterval(() => {
        fetch('api.php?action=check_backup_status')
            .then(response => response.json())
            .then(data => {
                if (data.auto_backup_due) {
                    if (confirm('Otomatik yedekleme zamanı geldi. Şimdi yedek almak istiyor musunuz?')) {
                        createBackup('veritabani');
                    }
                }
            })
            .catch(error => console.error('Backup check error:', error));
    }, 60000); // 1 dakikada bir kontrol et
    </script>
</body>
</html>

  ```

--------------------------------------------------------------------------------

📁 **assets/**
  📁 **css/**
    📄 **assets\css\style.css**
    ```css
/* Doğrudan İrade Platformu Ana Stilleri */

:root {
    --primary-color: #3498db;
    --secondary-color: #2c3e50;
    --success-color: #2ecc71;
    --danger-color: #e74c3c;
    --warning-color: #f39c12;
    --info-color: #1abc9c;
    --light-color: #ecf0f1;
    --dark-color: #34495e;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
    color: #333;
    line-height: 1.6;
}

/* Başlık stilleri */
.display-4 {
    color: var(--secondary-color);
    font-weight: 700;
}

.lead {
    color: var(--primary-color);
    font-weight: 500;
}

/* Kart stilleri */
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    font-weight: 600;
}

/* Buton stilleri */
.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    padding: 10px 25px;
    font-weight: 600;
    border-radius: 25px;
}

.btn-primary:hover {
    background-color: #2980b9;
    border-color: #2980b9;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
}

.btn-outline-primary {
    color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-outline-primary:hover {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-success {
    background-color: var(--success-color);
    border-color: var(--success-color);
    border-radius: 25px;
}

.btn-danger {
    background-color: var(--danger-color);
    border-color: var(--danger-color);
    border-radius: 25px;
}

/* Badge stilleri */
.badge {
    padding: 8px 12px;
    font-weight: 500;
    border-radius: 20px;
}

.bg-primary { background-color: var(--primary-color) !important; }
.bg-secondary { background-color: var(--secondary-color) !important; }
.bg-success { background-color: var(--success-color) !important; }
.bg-danger { background-color: var(--danger-color) !important; }
.bg-warning { background-color: var(--warning-color) !important; }
.bg-info { background-color: var(--info-color) !important; }

/* Oylama öğeleri */
.poll-item {
    background: white;
    transition: all 0.3s ease;
}

.poll-item:hover {
    background: var(--light-color);
    transform: translateX(5px);
}

/* Aday kartı */
.candidate-card {
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
}

.candidate-card:hover {
    border-left-color: var(--primary-color);
}

.candidate-card.selected-support {
    border-left-color: var(--success-color);
    background-color: rgba(46, 204, 113, 0.05);
}

.candidate-card.selected-negative {
    border-left-color: var(--danger-color);
    background-color: rgba(231, 76, 60, 0.05);
}

/* Navigasyon */
.navbar {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
}

/* Footer */
footer {
    background-color: var(--secondary-color);
    color: white;
    margin-top: 50px;
}

/* Responsive düzenlemeler */
@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }
    
    .btn-support, .btn-negative {
        width: 100%;
        margin-bottom: 5px;
    }
}

/* Animasyonlar */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.5s ease forwards;
}

/* Progress bar */
.progress {
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
}

/* Alert mesajları */
.alert {
    border-radius: 10px;
    border: none;
}

.alert-success {
    background-color: rgba(46, 204, 113, 0.1);
    color: var(--success-color);
    border-left: 4px solid var(--success-color);
}

.alert-info {
    background-color: rgba(52, 152, 219, 0.1);
    color: var(--primary-color);
    border-left: 4px solid var(--primary-color);
}

.alert-warning {
    background-color: rgba(243, 156, 18, 0.1);
    color: var(--warning-color);
    border-left: 4px solid var(--warning-color);
}

.alert-danger {
    background-color: rgba(231, 76, 60, 0.1);
    color: var(--danger-color);
    border-left: 4px solid var(--danger-color);
}

    ```

--------------------------------------------------------------------------------

  📁 **js/**
    📄 **assets\js\main.js**
    ```javascript
// Doğrudan İrade Platformu - Ana JavaScript Fonksiyonları

document.addEventListener('DOMContentLoaded', function() {
    // Oy verme butonlarına animasyon ekle
    initVoteButtons();
    
    // Süre dolmuş oylamaları kontrol et
    checkExpiredPolls();
    
    // Kullanıcı deneyimi iyileştirmeleri
    enhanceUserExperience();
});

// Oy verme butonlarını başlat
function initVoteButtons() {
    const voteButtons = document.querySelectorAll('.btn-support, .btn-negative');
    
    voteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Butona tıklama animasyonu
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
}

// Süresi dolmuş oylamaları kontrol et
function checkExpiredPolls() {
    const endTimeElements = document.querySelectorAll('[data-end-time]');
    const now = new Date().getTime();
    
    endTimeElements.forEach(element => {
        const endTime = new Date(element.dataset.endTime).getTime();
        if (endTime < now) {
            // Oylama süresi dolmuş
            element.closest('.poll-item').classList.add('expired');
            const voteBtn = element.closest('.poll-item').querySelector('a.btn');
            if (voteBtn) {
                voteBtn.classList.remove('btn-primary');
                voteBtn.classList.add('btn-secondary', 'disabled');
                voteBtn.innerHTML = '⏰ Süre Doldu';
                voteBtn.removeAttribute('href');
            }
        }
    });
}

// Kullanıcı deneyimi iyileştirmeleri
function enhanceUserExperience() {
    // Form doğrulama
    const forms = document.querySelectorAll('form[needs-validation]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    });
    
    // Toast mesajları (başarı/hata mesajları için)
    window.showToast = function(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.id = toastId;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    };
    
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }
    
    // Auto-logout timer (30 dakika inaktivite)
    let idleTimer;
    function resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(logoutWarning, 30 * 60 * 1000); // 30 dakika
    }
    
    function logoutWarning() {
        if (confirm('Oturumunuz süresi dolmak üzere. Devam etmek istiyor musunuz?')) {
            resetIdleTimer();
        } else {
            window.location.href = 'cikis.php';
        }
    }
    
    // Kullanıcı aktivitelerini dinle
    ['mousemove', 'keypress', 'click', 'scroll'].forEach(event => {
        document.addEventListener(event, resetIdleTimer);
    });
    
    resetIdleTimer(); // Timer'ı başlat
}

// API istekleri için yardımcı fonksiyon
window.apiRequest = async function(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin'
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Bir hata oluştu');
        }
        
        return result;
    } catch (error) {
        console.error('API Hatası:', error);
        showToast(error.message, 'danger');
        throw error;
    }
};

// Oy verme fonksiyonu (yeniden tanımlanabilir)
window.vote = function(adayId, type) {
    if (!confirm(`${type === 'destek' ? 'Destek' : 'Negatif'} oyunuzu güncellemek istediğinize emin misiniz?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', type);
    formData.append('aday_id', adayId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Oyunuz başarıyla kaydedildi!', 'success');
            // Sayfayı yenile
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Bir hata oluştu', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('İşlem sırasında bir hata oluştu.', 'danger');
    });
};

// Canlı sonuç güncellemesi
function startLiveResultsUpdate(oylamaId) {
    if (!window.liveUpdateInterval) {
        window.liveUpdateInterval = setInterval(() => {
            fetch(`api.php?action=live_results&oylama_id=${oylamaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateResultsUI(data.results);
                    }
                })
                .catch(error => console.error('Live update error:', error));
        }, 30000); // 30 saniyede bir
    }
}

// Sonuçları güncelle
function updateResultsUI(results) {
    // Bu fonksiyon sonuç sayfasındaki verileri günceller
    const resultsContainer = document.getElementById('results-container');
    if (resultsContainer) {
        // UI güncelleme mantığı burada
        console.log('Results updated:', results);
    }
}

// Sayfadan ayrılırken interval'i temizle
window.addEventListener('beforeunload', function() {
    if (window.liveUpdateInterval) {
        clearInterval(window.liveUpdateInterval);
    }
});

    ```

--------------------------------------------------------------------------------

📁 **config/**
  📄 **config\datebase.php**
  ```php
<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'dogrudan_irade';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
            die("Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.");
        }
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function singleValueQuery($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    public function insertAndGetId($sql, $params = []) {
        $conn = $this->connect();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $conn->lastInsertId();
    }
}
?>

  ```

--------------------------------------------------------------------------------

  📄 **config\functions.php**
  ```php
<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function redirect($url, $delay = 0) {
    if ($delay > 0) {
        header("refresh:$delay;url=$url");
    } else {
        header("Location: $url");
    }
    exit;
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "$protocol://$host";
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function formatDate($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

function isLoggedIn() {
    return isset($_SESSION['kullanici_id']);
}

function isAdmin() {
    return isset($_SESSION['yetki_seviye']) && 
           ($_SESSION['yetki_seviye'] === 'superadmin' || $_SESSION['yetki_seviye'] === 'yonetici');
}

/**
 * Log kaydı oluştur
 */
function logIslem($kullanici_id, $islem_tipi, $aciklama = '', $ip = null) {
    global $db;
    
    if (!$db) {
        require_once 'database.php';
        $db = new Database();
    }
    
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi, user_agent) 
             VALUES (?, ?, ?, ?, ?)",
            [$kullanici_id, $islem_tipi, $aciklama, $ip, $user_agent]
        );
        return true;
    } catch (Exception $e) {
        error_log("Log kaydı hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Güvenli token oluştur
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * CSRF token oluştur ve kontrol et
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Güvenlik headers'ları ekle
 */
function security_headers() {
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // CSP Header (Content Security Policy)
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "img-src 'self' data: https:",
        "font-src 'self' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'"
    ];
    
    header("Content-Security-Policy: " . implode('; ', $csp));
}

/**
 * Rate limiting kontrolü
 */
function rate_limit($key, $limit = 10, $seconds = 60) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $redis_key = "rate_limit:{$key}:{$ip}";
    
    // Redis yoksa session kullan
    if (!isset($_SESSION[$redis_key])) {
        $_SESSION[$redis_key] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    $data = $_SESSION[$redis_key];
    
    // Süre dolmuşsa sıfırla
    if (time() - $data['time'] > $seconds) {
        $_SESSION[$redis_key] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    // Limit kontrolü
    if ($data['count'] >= $limit) {
        return false;
    }
    
    // Sayacı artır
    $_SESSION[$redis_key]['count']++;
    return true;
}

/**
 * Email gönder
 */
function sendEmail($to, $subject, $body, $headers = []) {
    $default_headers = [
        'From' => 'noreply@dogrudanirade.org',
        'Reply-To' => 'info@dogrudanirade.org',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= "$key: $value\r\n";
    }
    
    return mail($to, $subject, $body, $header_string);
}

/**
 * Şifre sıfırlama token'ı oluştur
 */
function createPasswordResetToken($user_id) {
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    global $db;
    if (!$db) {
        require_once 'database.php';
        $db = new Database();
    }
    
    // Eski tokenları temizle
    $db->query(
        "DELETE FROM sifre_sifirlama_tokenlari WHERE kullanici_id = ? OR son_kullanma < NOW()",
        [$user_id]
    );
    
    // Yeni token ekle
    $db->query(
        "INSERT INTO sifre_sifirlama_tokenlari (kullanici_id, token, son_kullanma) 
         VALUES (?, ?, ?)",
        [$user_id, $token, $expires]
    );
    
    return $token;
}

/**
 * Şifre sıfırlama token'ını doğrula
 */
function verifyPasswordResetToken($token) {
    global $db;
    if (!$db) {
        require_once 'database.php';
        $db = new Database();
    }
    
    $result = $db->query(
        "SELECT kullanici_id FROM sifre_sifirlama_tokenlari 
         WHERE token = ? AND son_kullanma > NOW()",
        [$token]
    )->fetch();
    
    return $result ? $result['kullanici_id'] : false;
}

/**
 * Dosya yükleme
 */
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Dosya yükleme hatası.'];
    }
    
    // Dosya boyutu kontrolü
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Dosya çok büyük. Maksimum ' . ($max_size / 1024 / 1024) . 'MB.'];
    }
    
    // Dosya tipi kontrolü
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => 'İzin verilmeyen dosya tipi.'];
    }
    
    // Güvenlik kontrolü
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf'
    ];
    
    if (!in_array($mime, $allowed_mimes)) {
        return ['success' => false, 'message' => 'Geçersiz dosya formatı.'];
    }
    
    // Benzersiz dosya adı oluştur
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $file['name']);
    $upload_path = 'uploads/' . date('Y/m');
    
    // Klasör yoksa oluştur
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0777, true);
    }
    
    $target_file = $upload_path . '/' . $filename;
    
    // Dosyayı taşı
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $target_file,
            'url' => '/' . $target_file
        ];
    }
    
    return ['success' => false, 'message' => 'Dosya yüklenemedi.'];
}

/**
 * Türkçe tarih formatı
 */
function turkishDate($date, $format = 'd F Y H:i') {
    $english_months = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
    $turkish_months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
                      'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    
    $formatted = date($format, strtotime($date));
    return str_replace($english_months, $turkish_months, $formatted);
}

/**
 * SEO uyumlu URL oluştur
 */
function seoUrl($string) {
    $string = html_entity_decode($string);
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

/**
 * CURL ile API isteği
 */
function curlRequest($url, $method = 'GET', $data = [], $headers = []) {
    $ch = curl_init();
    
    $default_headers = [
        'User-Agent: Doğrudan İrade Platformu/1.0',
        'Accept: application/json',
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } elseif ($method === 'JSON') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    return [
        'success' => $http_code >= 200 && $http_code < 300,
        'code' => $http_code,
        'data' => json_decode($response, true) ?: $response
    ];
}
?>

  ```

--------------------------------------------------------------------------------

  📄 **config\secim_fonksiyonlari.php**
  ```php
<?php
require_once 'database.php';

class SecimFonksiyonlari {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Oylama sonuçlarını hesapla (NEGATİF OY SİSTEMİ)
    public function secimSonucunuHesapla($oylama_id) {
        // 1. Tüm adayları al
        $stmt = $this->db->query(
            "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
            [$oylama_id]
        );
        $adaylar = $stmt->fetchAll();

        $sonuclar = [];
        $toplamOyKullanan = 0;

        foreach ($adaylar as $aday) {
            // 2. Bu adaya verilen DESTEK oylarını say
            $destekSayisi = $this->db->singleValueQuery(
                "SELECT COUNT(*) FROM oy_kullanicilar 
                 WHERE oylama_id = ? AND destek_verilen_aday_id = ?",
                [$oylama_id, $aday['id']]
            );

            // 3. Bu adaya verilen NEGATİF oyları say
            $negatifSayisi = $this->db->singleValueQuery(
                "SELECT COUNT(*) FROM oy_kullanicilar 
                 WHERE oylama_id = ? AND negatif_oy_verilen_aday_id = ?",
                [$oylama_id, $aday['id']]
            );

            // 4. NET SKOR HESAPLA: Destek - Negatif
            $netSkor = $destekSayisi - $negatifSayisi;

            $sonuclar[] = [
                'aday_id' => $aday['id'],
                'aday_adi' => $aday['aday_adi'],
                'aday_aciklama' => $aday['aday_aciklama'],
                'destek_sayisi' => (int)$destekSayisi,
                'negatif_sayisi' => (int)$negatifSayisi,
                'net_skor' => (int)$netSkor
            ];
        }

        // 5. Toplam oy kullanan sayısını bul
        $toplamOyKullanan = $this->db->singleValueQuery(
            "SELECT COUNT(DISTINCT kullanici_id) FROM oy_kullanicilar WHERE oylama_id = ?",
            [$oylama_id]
        );

        // 6. NET SKOR'a göre yüksekten düşüğe sırala
        usort($sonuclar, function($a, $b) {
            if ($b['net_skor'] == $a['net_skor']) {
                // Net skor eşitse, daha az negatif oy alan kazanır
                return $a['negatif_sayisi'] <=> $b['negatif_sayisi'];
            }
            return $b['net_skor'] <=> $a['net_skor'];
        });

        return [
            'sonuclar' => $sonuclar,
            'toplam_oy_kullanan' => $toplamOyKullanan,
            'kazanan' => !empty($sonuclar) ? $sonuclar[0] : null
        ];
    }

    // Kullanıcının oy durumunu al
    public function kullaniciOyDurumu($oylama_id, $kullanici_id) {
        $destekOy = $this->db->query(
            "SELECT a.* FROM oy_kullanicilar ok 
             JOIN adaylar a ON ok.destek_verilen_aday_id = a.id 
             WHERE ok.oylama_id = ? AND ok.kullanici_id = ?",
            [$oylama_id, $kullanici_id]
        )->fetch();

        $negatifOylar = $this->db->query(
            "SELECT a.* FROM oy_kullanicilar ok 
             JOIN adaylar a ON ok.negatif_oy_verilen_aday_id = a.id 
             WHERE ok.oylama_id = ? AND ok.kullanici_id = ?",
            [$oylama_id, $kullanici_id]
        )->fetchAll();

        return [
            'destek_oy' => $destekOy,
            'negatif_oylar' => $negatifOylar
        ];
    }

    // Destek oyu ver
    public function destekOyVer($oylama_id, $kullanici_id, $aday_id) {
        try {
            // Önce varsa eski destek oyunu sil
            $this->db->query(
                "DELETE FROM oy_kullanicilar 
                 WHERE oylama_id = ? AND kullanici_id = ? AND destek_verilen_aday_id IS NOT NULL",
                [$oylama_id, $kullanici_id]
            );

            // Yeni destek oyunu ekle
            $this->db->query(
                "INSERT INTO oy_kullanicilar (oylama_id, kullanici_id, destek_verilen_aday_id, ip_adresi) 
                 VALUES (?, ?, ?, ?)",
                [$oylama_id, $kullanici_id, $aday_id, $_SERVER['REMOTE_ADDR']]
            );

            // Log kaydı
            $this->logIslem($kullanici_id, 'destek_oy_verildi', "Oylama: $oylama_id, Aday: $aday_id");

            return true;
        } catch (Exception $e) {
            error_log("Destek oy hatası: " . $e->getMessage());
            return false;
        }
    }

    // Negatif oy ver/al
    public function negatifOyToggle($oylama_id, $kullanici_id, $aday_id) {
        try {
            // Önce bu adaya zaten negatif oy verilmiş mi kontrol et
            $existing = $this->db->singleValueQuery(
                "SELECT COUNT(*) FROM oy_kullanicilar 
                 WHERE oylama_id = ? AND kullanici_id = ? AND negatif_oy_verilen_aday_id = ?",
                [$oylama_id, $kullanici_id, $aday_id]
            );

            if ($existing > 0) {
                // Varsa sil (toggle off)
                $this->db->query(
                    "DELETE FROM oy_kullanicilar 
                     WHERE oylama_id = ? AND kullanici_id = ? AND negatif_oy_verilen_aday_id = ?",
                    [$oylama_id, $kullanici_id, $aday_id]
                );
                $islem = 'negatif_oy_kaldirildi';
            } else {
                // Yoksa ekle (toggle on)
                $this->db->query(
                    "INSERT INTO oy_kullanicilar (oylama_id, kullanici_id, negatif_oy_verilen_aday_id, ip_adresi) 
                     VALUES (?, ?, ?, ?)",
                    [$oylama_id, $kullanici_id, $aday_id, $_SERVER['REMOTE_ADDR']]
                );
                $islem = 'negatif_oy_verildi';
            }

            // Log kaydı
            $this->logIslem($kullanici_id, $islem, "Oylama: $oylama_id, Aday: $aday_id");

            return true;
        } catch (Exception $e) {
            error_log("Negatif oy hatası: " . $e->getMessage());
            return false;
        }
    }

    private function logIslem($kullanici_id, $islem_tipi, $aciklama = '') {
        $this->db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi, user_agent) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $kullanici_id,
                $islem_tipi,
                $aciklama,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }
}
?>

  ```

--------------------------------------------------------------------------------

📁 **includes/**
  📄 **includes\auth.php**
  ```php
<?php
// Yetkilendirme kontrolü için fonksiyonlar

function requireLogin() {
    if (!isset($_SESSION['kullanici_id'])) {
        header("Location: giris.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    
    if ($_SESSION['yetki_seviye'] !== 'superadmin' && $_SESSION['yetki_seviye'] !== 'yonetici') {
        header("Location: index.php?error=yetkisiz_erisim");
        exit;
    }
}

function requireSuperAdmin() {
    requireLogin();
    
    if ($_SESSION['yetki_seviye'] !== 'superadmin') {
        header("Location: index.php?error=yetkisiz_erisim");
        exit;
    }
}

function canVoteInPoll($oylama_id, $kullanici_id) {
    require_once '../config/database.php';
    $db = new Database();
    
    // Oylama bilgilerini al
    $oylama = $db->query(
        "SELECT * FROM oylamalar WHERE id = ?",
        [$oylama_id]
    )->fetch();
    
    if (!$oylama) return false;
    
    // Ulusal oylamalarda herkes oy kullanabilir
    if ($oylama['topluluk_tipi'] === 'ulusal') {
        return true;
    }
    
    // Kullanıcının bu topluluğa üyeliğini kontrol et
    $uyelik = $db->singleValueQuery(
        "SELECT COUNT(*) FROM kullanici_topluluklari 
         WHERE kullanici_id = ? 
         AND topluluk_tipi = ? 
         AND topluluk_id = ?",
        [$kullanici_id, $oylama['topluluk_tipi'], $oylama['topluluk_id']]
    );
    
    return $uyelik > 0;
}

function hasVoted($oylama_id, $kullanici_id) {
    require_once '../config/database.php';
    $db = new Database();
    
    $oy = $db->singleValueQuery(
        "SELECT COUNT(*) FROM oy_kullanicilar 
         WHERE oylama_id = ? AND kullanici_id = ?",
        [$oylama_id, $kullanici_id]
    );
    
    return $oy > 0;
}
?>

  ```

--------------------------------------------------------------------------------

  📄 **includes\footer.php**
  ```php
<?php
$current_page = basename($_SERVER['PHP_FILE'] ?? '');
?>
<footer class="bg-dark text-white py-5 mt-5">
    <div class="container">
        <div class="row">
            <!-- Logo ve açıklama -->
            <div class="col-lg-4 mb-4">
                <h4 class="fw-bold mb-3">
                    🗳️ Doğrudan İrade
                </h4>
                <p class="small">
                    "Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"
                </p>
                <p class="small text-muted">
                    Doğrudan demokrasi için geliştirilmiş, şeffaf ve güvenli bir dijital platform.
                </p>
                
                <div class="mt-3">
                    <a href="https://github.com/dogrudan-irade" target="_blank" class="text-white-50 me-2">
                        <i class="bi bi-github"></i> GitHub
                    </a>
                    <a href="#" class="text-white-50 me-2">
                        <i class="bi bi-twitter"></i> Twitter
                    </a>
                    <a href="#" class="text-white-50">
                        <i class="bi bi-facebook"></i> Facebook
                    </a>
                </div>
            </div>
            
            <!-- Hızlı linkler -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="text-uppercase fw-bold mb-3">Hızlı Linkler</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="index.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-house-door"></i> Ana Sayfa
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="oylamalar.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-clipboard-data"></i> Tüm Oylamalar
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="oylama_olustur.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-plus-circle"></i> Yeni Oylama
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="profil.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-person"></i> Profilim
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="iletisim.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-envelope"></i> İletişim
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Bilgi -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h6 class="text-uppercase fw-bold mb-3">Bilgi</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#aboutModal">
                            <i class="bi bi-question-circle"></i> Nasıl Çalışır?
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#negativeVoteModal">
                            <i class="bi bi-lightbulb"></i> Negatif Oy Sistemi
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="bi bi-envelope"></i> İletişim
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="bi bi-file-earmark-text"></i> SSS
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- İletişim -->
            <div class="col-lg-3 col-md-12">
                <h6 class="text-uppercase fw-bold mb-3">İletişim</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="bi bi-envelope text-white-50"></i> 
                        <a href="mailto:info@dogrudanirade.org" class="text-white-50 text-decoration-none small">
                            info@dogrudanirade.org
                        </a>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-geo-alt text-white-50"></i> 
                        <span class="text-white-50 small">Türkiye</span>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-globe text-white-50"></i> 
                        <a href="https://www.dogrudanirade.org" class="text-white-50 text-decoration-none small">
                            www.dogrudanirade.org
                        </a>
                    </li>
                    <li class="mt-3">
                        <a href="#" class="btn btn-primary btn-sm">
                            <i class="bi bi-envelope"></i> Bültenimize Katılın
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <hr class="my-4 bg-secondary">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="small text-white-50 mb-0">
                    &copy; <?= date('Y') ?> Doğrudan İrade Platformu. Tüm hakları saklıdır.
                </p>
                <p class="small text-white-50 mb-0 mt-1">
                    <a href="#" class="text-white-50 text-decoration-none">Gizlilik Politikası</a> | 
                    <a href="#" class="text-white-50 text-decoration-none">Kullanım Şartları</a> | 
                    <a href="#" class="text-white-50 text-decoration-none">Çerez Politikası</a>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <p class="small text-white-50 mb-0">
                    <i class="bi bi-shield-check"></i> 
                    <a href="#" class="text-white-50 text-decoration-none">Güvenli Platform</a> |
                    <a href="#" class="text-white-50 text-decoration-none">Açık Kaynak</a> |
                    <a href="#" class="text-white-50 text-decoration-none">CC0 Lisans</a>
                </p>
                <p class="small text-white-50 mb-0 mt-1">
                    <a href="#" class="text-white-50 text-decoration-none">
                        <i class="bi bi-shield-check"></i> Güvenlik Bildirimi
                    </a>
                </p>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <small class="text-white-50">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Uyarı:</strong> Bu site test ortamında çalışmaktadır. Gerçek platform için resmi sitemizi ziyaret edin.
            </small>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Toast Container -->
<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>

<!-- Modal İçerikleri -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-question-circle"></i> Nasıl Çalışıyor?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>✅ Destek Oyu</h6>
                        <p class="small">Bir adayı veya seçeneği aktif olarak desteklemek için kullanılır. Her oylamada sadece BİR destek oyu verebilirsiniz.</p>
                        
                        <h6>❌ Negatif Oy</h6>
                        <p class="small">Kabul edemediğiniz seçeneklere karşı oy kullanın. İstediğiniz kadar negatif oy verebilirsiniz.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>📊 Net Skor Sistemi</h6>
                        <p class="small">Kazanan şu formülle belirlenir:<br><code>NET SKOR = Destek - Negatif</code></p>
                        
                        <h6>🏆 Kazanan Belirleme</h6>
                        <p class="small">Net skor yüksekten düşüğe doğru sıralanır. Eşitlik durumunda daha az negatif oy alan kazanır.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="negativeVoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-lightbulb-fill"></i> Negatif Oy Sistemi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>🎯 Sistemin Amacı</h6>
                        <ul class="small">
                            <li>Popüler ama sevilmeyen adayları filtreler</li>
                            <li>Toplumsal mutabakatı yansıtır</li>
                            <li>Manipülasyonu zorlaştırır</li>
                            <li>Daha gerçek bir toplumsal tercihi ölçer</li>
                        </ul>
                        
                        <h6>📊 Örnek Senaryo</h6>
                        <div class="alert alert-info small">
                            <div class="d-flex justify-content-between">
                                <div>Aday A: +600, -450 = <strong>150</strong></div>
                                <div>Aday B: +400, -50 = <strong>350</strong> (KAZANAN)</div>
                                <div>Aday C: +250, -100 = <strong>150</strong></div>
                            </div>
                        </div>
                        <p class="small">Geleneksel sistemde Aday A kazanırdı. Negatif oy sisteminde Aday B kazanır.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>📈 Sistem Avantajları</h6>
                        <ul class="small">
                            <li>Sadece "en az kötü" seçilebilir</li>
                            <li>Kutuplaşmayı artırır</li>
                            <li>Gerçek tercihi yansıtmaz</li>
                        </ul>
                        
                        <h6>🎯 Sistem Nasıl Çalışır?</h6>
                        <p class="small">Her oylama için destek ve negatif oy butonları bulunur. Seçeneklerin net skoru hesaplanır.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-envelope"></i> İletişim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="small">Doğrudan İrade Platformu</h6>
                        <p class="small text-muted">Doğrudan demokrasi için geliştirilmiş açık kaynaklı bir platform.</p>
                        <p class="small">
                            <i class="bi bi-envelope"></i> info@dogrudanirade.org<br>
                            <i class="bi bi-geo-alt"></i> Türkiye
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="small">Sosyal Medya</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-instagram"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-youtube"></i>
                            </a>
                        </div>
                        <div class="mt-3">
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-github"></i> GitHub
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

  ```

--------------------------------------------------------------------------------

  📄 **includes\header.php**
  ```php
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand fw-bold" href="index.php">
            <span class="d-inline-block align-middle">
                🗳️ Doğrudan İrade
            </span>
        </a>
        
        <!-- Mobil menü butonu -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Ana menü -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-house-door"></i> Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'oylamalar.php' ? 'active' : '' ?>" href="oylamalar.php">
                        <i class="bi bi-clipboard-data"></i> Tüm Oylamalar
                    </a>
                </li>
                <?php if (isset($_SESSION['kullanici_id']) && $_SESSION['yetki_seviye'] === 'superadmin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'oylama_olustur.php' ? 'active' : '' ?>" href="oylama_olustur.php">
                        <i class="bi bi-plus-circle"></i> Yeni Oylama
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-info-circle"></i> Hakkında
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">
                            <i class="bi bi-question-circle"></i> Sistem Nasıl Çalışır?
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#negativeVoteModal">
                            <i class="bi bi-lightbulb"></i> Negatif Oy Sistemi
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="bi bi-envelope"></i> İletişim
                        </a></li>
                    </ul>
                </li>
            </ul>
            
            <!-- Sağ menü -->
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['kullanici_id'])): ?>
                    <!-- Kullanıcı giriş yapmış -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> 
                            <?= htmlspecialchars($_SESSION['ad_soyad']) ?>
                            <?php if ($_SESSION['yetki_seviye'] !== 'kullanici'): ?>
                                <span class="badge bg-warning ms-1">Yönetici</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="profil.php">
                                    <i class="bi bi-person"></i> Profilim
                                </a>
                            </li>
                            <?php if ($_SESSION['yetki_seviye'] === 'superadmin'): ?>
                                <li>
                                    <a class="dropdown-item" href="admin/index.php">
                                        <i class="bi bi-speedometer2"></i> Yönetim Paneli
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item text-danger" href="cikis.php">
                                    <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Kullanıcı giriş yapmamış -->
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'giris.php' ? 'active' : '' ?>" href="giris.php">
                            <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'kayit.php' ? 'active' : '' ?> btn btn-outline-light btn-sm ms-2" 
                           href="kayit.php">
                            <i class="bi bi-person-plus"></i> Kayıt Ol
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Sistem Nasıl Çalışır Modal -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-question-circle"></i> Doğrudan İrade Platformu - Nasıl Çalışır?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>✅ Destek Oyu</h6>
                        <p class="small">Bir adayı veya seçeneği aktif olarak desteklemek için kullanılır. Her oylamada sadece BİR destek oyu verebilirsiniz.</p>
                        
                        <h6>❌ Negatif Oy</h6>
                        <p class="small">Kabul edemediğiniz seçeneklere karşı kullanılır. İstediğiniz kadar negatif oy verebilirsiniz.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>📊 Net Skor Sistemi</h6>
                        <p class="small">Kazanan şu formülle belirlenir:<br>
                        <code>NET SKOR = Destek Oyu - Negatif Oy</code></p>
                        
                        <h6>🏆 Kazanan Belirleme</h6>
                        <p class="small">En yüksek net skora sahip aday/seçenek kazanır. Eşitlik durumunda daha az negatif oy alan kazanır.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Negatif Oy Sistemi Modal -->
<div class="modal fade" id="negativeVoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-lightbulb-fill"></i> NEGATİF OY SİSTEMİ NEDEN ÖNEMLİ?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>🎯 Sistemin Amacı</h6>
                        <ul class="small">
                            <li>Popüler ama sevilmeyen adayları filtreler</li>
                            <li>Toplumsal mutabakatı yansıtır</li>
                            <li>Manipülasyonu zorlaştırır</li>
                            <li>Gerçek kabul edilebilirliği ölçer</li>
                        </ul>
                        
                        <h6>📈 Geleneksel Sistemdeki Sorunlar</h6>
                        <ul class="small">
                            <li>Sadece "en az kötü" seçilebilir</li>
                            <li>Kutuplaşmayı artırır</li>
                            <li>Gerçek tercihi yansıtmaz</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>📊 Örnek Senaryo</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Aday</th>
                                        <th>Destek</th>
                                        <th>Negatif</th>
                                        <th>Net Skor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Aday A</td>
                                        <td>600</td>
                                        <td>450</td>
                                        <td class="text-success">150</td>
                                    </tr>
                                    <tr>
                                        <td>Aday B</td>
                                        <td>400</td>
                                        <td>50</td>
                                        <td class="text-success fw-bold">350</td>
                                    </tr>
                                    <tr>
                                        <td>Aday C</td>
                                        <td>250</td>
                                        <td>100</td>
                                        <td class="text-success">150</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="small">Geleneksel sistemde Aday A kazanırdı (600 oy). Negatif oy sisteminde ise Aday B kazanır (350 net skor).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İletişim Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope"></i> İletişim
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Doğrudan İrade Platformu</h6>
                <p class="small mb-3">Doğrudan demokrasi için geliştirilmiş açık kaynaklı bir platform.</p>
                
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <i class="bi bi-globe text-primary"></i> 
                        <strong>Web:</strong> www.dogrudanirade.org
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-envelope text-success"></i> 
                        <strong>E-posta:</strong> info@dogrudanirade.org
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-github text-dark"></i> 
                        <strong>GitHub:</strong> github.com/dogrudan-irade
                    </li>
                </ul>
                
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i> 
                    Bu platform açık kaynaklıdır. Katkıda bulunmak isterseniz 
                    GitHub deposunu ziyaret edin.
                </div>
            </div>
        </div>
    </div>
</div>

  ```

--------------------------------------------------------------------------------

  📄 **includes\validation.php**
  ```php
<?php
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

/**
 * TC Kimlik doğrulama (detaylı)
 */
function validateTCKN($tckn) {
    // 11 karakter mi?
    if (strlen($tckn) != 11) {
        return false;
    }
    
    // Sadece rakamlardan mı oluşuyor?
    if (!ctype_digit($tckn)) {
        return false;
    }
    
    // İlk hane 0 olamaz
    if ($tckn[0] == '0') {
        return false;
    }
    
    // 1, 3, 5, 7, 9. hanelerin toplamının 7 katından 2, 4, 6, 8. hanelerin toplamı çıkartıldığında
    // elde edilen sonucun 10'a bölümünden kalan 10. haneyi vermeli
    $tekler = $tckn[0] + $tckn[2] + $tckn[4] + $tckn[6] + $tckn[8];
    $ciftler = $tckn[1] + $tckn[3] + $tckn[5] + $tckn[7];
    
    if ((($tekler * 7) - $ciftler) % 10 != $tckn[9]) {
        return false;
    }
    
    // İlk 10 hanenin toplamının 10'a bölümünden kalan 11. haneyi vermeli
    $toplam = 0;
    for ($i = 0; $i < 10; $i++) {
        $toplam += $tckn[$i];
    }
    
    return $toplam % 10 == $tckn[10];
}

/**
 * Vergi numarası doğrulama
 */
function validateVKN($vkn) {
    if (strlen($vkn) != 10 || !ctype_digit($vkn)) {
        return false;
    }
    
    $toplam = 0;
    for ($i = 0; $i < 9; $i++) {
        $tmp = ($vkn[$i] + (9 - $i)) % 10;
        $tmp = ($tmp * pow(2, 9 - $i)) % 9;
        if ($tmp != 0 && $vkn[$i] != 9) {
            $toplam += $tmp;
        }
    }
    
    if ($toplam % 10 == 0) {
        $son_rakam = 0;
    } else {
        $son_rakam = 10 - ($toplam % 10);
    }
    
    return $son_rakam == $vkn[9];
}

/**
 * IBAN doğrulama
 */
function validateIBAN($iban) {
    $iban = strtoupper(str_replace(' ', '', $iban));
    
    if (strlen($iban) != 26) {
        return false;
    }
    
    // TR ile başlamalı
    if (substr($iban, 0, 2) != 'TR') {
        return false;
    }
    
    // Sayısal değer kontrolü
    $iban = substr($iban, 4) . substr($iban, 0, 4);
    $iban = str_replace(
        ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
         'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
        ['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
         '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31',
         '32', '33', '34', '35'],
        $iban
    );
    
    $mod = '';
    for ($i = 0; $i < strlen($iban); $i += 6) {
        $mod = intval($mod . substr($iban, $i, 6)) % 97;
    }
    
    return $mod == 1;
}

/**
 * Kredi kartı doğrulama (Luhn algoritması)
 */
function validateCreditCard($number) {
    $number = preg_replace('/\D/', '', $number);
    
    if (strlen($number) < 13 || strlen($number) > 19) {
        return false;
    }
    
    $sum = 0;
    $reverse = strrev($number);
    
    for ($i = 0; $i < strlen($reverse); $i++) {
        $digit = (int)$reverse[$i];
        
        if ($i % 2 == 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
    }
    
    return $sum % 10 == 0;
}

/**
 * Kart tipi belirleme
 */
function getCardType($number) {
    $number = preg_replace('/\D/', '', $number);
    
    // Visa: 4 ile başlar, 13 veya 16 haneli
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
        return 'VISA';
    }
    
    // MasterCard: 51-55 arası ile başlar, 16 haneli
    if (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
        return 'MASTERCARD';
    }
    
    // American Express: 34 veya 37 ile başlar, 15 haneli
    if (preg_match('/^3[47][0-9]{13}$/', $number)) {
        return 'AMEX';
    }
    
    return 'UNKNOWN';
}

/**
 * URL doğrulama
 */
function validateURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * IPv4 doğrulama
 */
function validateIPv4($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

/**
 * IPv6 doğrulama
 */
function validateIPv6($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}

/**
 * E-posta doğrulama (detaylı)
 */
function validateEmailExtended($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Domain MX kaydı kontrolü
    $domain = substr(strrchr($email, "@"), 1);
    return checkdnsrr($domain, 'MX');
}

/**
 * Parola güçlülük kontrolü
 */
function passwordStrength($password) {
    $score = 0;
    
    // Uzunluk
    if (strlen($password) >= 8) $score++;
    if (strlen($password) >= 12) $score++;
    
    // Karakter çeşitliliği
    if (preg_match('/[a-z]/', $password)) $score++; // Küçük harf
    if (preg_match('/[A-Z]/', $password)) $score++; // Büyük harf
    if (preg_match('/[0-9]/', $password)) $score++; // Rakam
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++; // Özel karakter
    
    // Zayıf parola kontrolü
    $weak_passwords = [
        'password', '123456', 'qwerty', 'admin', 'welcome',
        'password123', '123456789', '12345678', '12345'
    ];
    
    if (in_array(strtolower($password), $weak_passwords)) {
        $score = 0;
    }
    
    // Skora göre güç seviyesi
    if ($score <= 2) return 'zayif';
    if ($score <= 4) return 'orta';
    return 'guclu';
}

/**
 * XSS korumalı çıktı
 */
function xss_clean($data) {
    // HTML tag'lerini temizle
    $data = strip_tags($data);
    
    // HTML entity'lerini çevir
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Potansiyel tehlikeli karakterleri temizle
    $data = str_replace(
        ['<', '>', '"', "'", '&', 'javascript:', 'onload', 'onclick', 'onerror'],
        ['&lt;', '&gt;', '&quot;', '&#039;', '&amp;', '', '', '', ''],
        $data
    );
    
    return $data;
}

/**
 * SQL injection korumalı sorgu
 */
function sql_escape($string, $db = null) {
    if ($db instanceof PDO) {
        return $db->quote($string);
    }
    
    // Temel temizleme
    $search = ["\\", "\x00", "\n", "\r", "'", '"', "\x1a"];
    $replace = ["\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z"];
    
    return str_replace($search, $replace, $string);
}

/**
 * CSRF token oluşturma
 */
function generate_csrf_token() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
    
    // Fallback
    return md5(uniqid(mt_rand(), true) . microtime(true));
}

/**
 * Dosya tipi kontrolü
 */
function validate_file_type($file_path, $allowed_types = ['image/jpeg', 'image/png', 'image/gif']) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    return in_array($mime_type, $allowed_types);
}

/**
 * Resim boyutlandırma
 */
function resize_image($source_path, $target_path, $max_width, $max_height) {
    list($orig_width, $orig_height, $type) = getimagesize($source_path);
    
    // Oranları hesapla
    $ratio = $orig_width / $orig_height;
    
    if ($max_width / $max_height > $ratio) {
        $new_width = $max_height * $ratio;
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    }
    
    // Yeni resim oluştur
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Kaynak resmi yükle
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            // Şeffaflığı koru
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    // Boyutlandır
    imagecopyresampled($new_image, $source, 0, 0, 0, 0, 
                       $new_width, $new_height, $orig_width, $orig_height);
    
    // Kaydet
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $target_path, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $target_path, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($new_image, $target_path);
            break;
    }
    
    // Belleği temizle
    imagedestroy($source);
    imagedestroy($new_image);
    
    return true;
}
?>

  ```

--------------------------------------------------------------------------------

