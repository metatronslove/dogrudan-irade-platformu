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
