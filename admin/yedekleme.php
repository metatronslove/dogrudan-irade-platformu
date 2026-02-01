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
