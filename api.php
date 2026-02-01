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
