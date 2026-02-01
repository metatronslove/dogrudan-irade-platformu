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
