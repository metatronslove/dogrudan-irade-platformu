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
