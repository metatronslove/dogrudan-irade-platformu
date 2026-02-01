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
