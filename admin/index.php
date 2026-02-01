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
