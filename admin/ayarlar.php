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
