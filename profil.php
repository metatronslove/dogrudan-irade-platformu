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
