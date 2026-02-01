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
