<?php
session_start();
require_once 'config/database.php';

$db = new Database();

// Filtreler
$filter = $_GET['filter'] ?? 'aktif';
$tur = $_GET['tur'] ?? '';
$topluluk = $_GET['topluluk'] ?? '';
$search = $_GET['search'] ?? '';

// Sorgu oluştur
$sql = "SELECT o.*, 
        COUNT(DISTINCT ok.kullanici_id) as oy_sayisi,
        u.ad_soyad as olusturan_ad
        FROM oylamalar o 
        LEFT JOIN oy_kullanicilar ok ON o.id = ok.oylama_id 
        LEFT JOIN kullanicilar u ON o.olusturan_id = u.id 
        WHERE 1=1";

$params = [];

// Durum filtresi
if ($filter === 'aktif') {
    $sql .= " AND o.durum = 'aktif'";
} elseif ($filter === 'sonuclandi') {
    $sql .= " AND o.durum = 'sonuclandi'";
} elseif ($filter === 'tum') {
    // Tümü - filtre yok
} elseif ($filter === 'katildigim' && isset($_SESSION['kullanici_id'])) {
    $sql .= " AND EXISTS (
        SELECT 1 FROM oy_kullanicilar ok2 
        WHERE ok2.oylama_id = o.id AND ok2.kullanici_id = ?
    )";
    $params[] = $_SESSION['kullanici_id'];
}

// Tür filtresi
if (!empty($tur)) {
    $sql .= " AND o.tur = ?";
    $params[] = $tur;
}

// Topluluk filtresi
if (!empty($topluluk)) {
    $sql .= " AND o.topluluk_tipi = ?";
    $params[] = $topluluk;
}

// Arama filtresi
if (!empty($search)) {
    $sql .= " AND (o.baslik LIKE ? OR o.aciklama LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " GROUP BY o.id ORDER BY o.olusturulma_tarihi DESC";

// Oylamaları getir
$oylamalar = $db->query($sql, $params)->fetchAll();

// Toplam sayılar
$aktif_sayisi = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'aktif'");
$sonuclandi_sayisi = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'sonuclandi'");
$tum_sayisi = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tüm Oylamalar - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filter-badge.active {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .poll-card {
            transition: all 0.3s ease;
        }
        .poll-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .progress {
            height: 8px;
        }
        .poll-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <!-- Başlık ve istatistikler -->
        <div class="row mb-5">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold text-primary mb-3">
                    <i class="bi bi-clipboard-data"></i> TÜM OYLAMALAR
                </h1>
                <p class="lead text-muted">
                    Doğrudan demokrasinin işlediği tüm oylamaları görüntüleyin ve katılın
                </p>
            </div>
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h4 class="card-title">📊 İstatistikler</h4>
                        <div class="row">
                            <div class="col-4">
                                <div class="text-primary fw-bold fs-4"><?= $aktif_sayisi ?></div>
                                <small>Aktif</small>
                            </div>
                            <div class="col-4">
                                <div class="text-success fw-bold fs-4"><?= $sonuclandi_sayisi ?></div>
                                <small>Sonuçlandı</small>
                            </div>
                            <div class="col-4">
                                <div class="text-info fw-bold fs-4"><?= $tum_sayisi ?></div>
                                <small>Toplam</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="bi bi-funnel"></i> Filtreler
                </h5>
                
                <!-- Durum filtreleri -->
                <div class="mb-3">
                    <small class="text-muted d-block mb-2">Durum:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filter=aktif" 
                           class="badge filter-badge <?= $filter == 'aktif' ? 'bg-primary' : 'bg-secondary' ?>">
                            🔵 Aktif (<?= $aktif_sayisi ?>)
                        </a>
                        <a href="?filter=sonuclandi" 
                           class="badge filter-badge <?= $filter == 'sonuclandi' ? 'bg-success' : 'bg-secondary' ?>">
                            ✅ Sonuçlandı (<?= $sonuclandi_sayisi ?>)
                        </a>
                        <a href="?filter=tum" 
                           class="badge filter-badge <?= $filter == 'tum' ? 'bg-info' : 'bg-secondary' ?>">
                            📋 Tümü (<?= $tum_sayisi ?>)
                        </a>
                        <?php if (isset($_SESSION['kullanici_id'])): ?>
                            <a href="?filter=katildigim" 
                               class="badge filter-badge <?= $filter == 'katildigim' ? 'bg-warning' : 'bg-secondary' ?>">
                                👤 Katıldıklarım
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tür filtreleri -->
                <div class="mb-3">
                    <small class="text-muted d-block mb-2">Tür:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filter=<?= $filter ?>&tur=secim" 
                           class="badge filter-badge <?= $tur == 'secim' ? 'bg-primary' : 'bg-secondary' ?>">
                            👥 Seçim
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=referandum" 
                           class="badge filter-badge <?= $tur == 'referandum' ? 'bg-success' : 'bg-secondary' ?>">
                            📝 Referandum
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=kanun_teklifi" 
                           class="badge filter-badge <?= $tur == 'kanun_teklifi' ? 'bg-info' : 'bg-secondary' ?>">
                            📜 Kanun Teklifi
                        </a>
                        <?php if (!empty($tur)): ?>
                            <a href="?filter=<?= $filter ?>" 
                               class="badge filter-badge bg-danger">
                                ❌ Tür Filtresini Kaldır
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Topluluk filtreleri -->
                <div class="mb-3">
                    <small class="text-muted d-block mb-2">Kapsam:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>&topluluk=ulusal" 
                           class="badge filter-badge <?= $topluluk == 'ulusal' ? 'bg-primary' : 'bg-secondary' ?>">
                            🇹🇷 Ulusal
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>&topluluk=il" 
                           class="badge filter-badge <?= $topluluk == 'il' ? 'bg-success' : 'bg-secondary' ?>">
                            🏙️ İl
                        </a>
                        <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>&topluluk=sendika" 
                           class="badge filter-badge <?= $topluluk == 'sendika' ? 'bg-warning' : 'bg-secondary' ?>">
                            👷 Sendika
                        </a>
                        <?php if (!empty($topluluk)): ?>
                            <a href="?filter=<?= $filter ?>&tur=<?= $tur ?>" 
                               class="badge filter-badge bg-danger">
                                ❌ Kapsam Filtresini Kaldır
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Arama -->
                <form method="GET" class="row g-2">
                    <input type="hidden" name="filter" value="<?= $filter ?>">
                    <input type="hidden" name="tur" value="<?= $tur ?>">
                    <input type="hidden" name="topluluk" value="<?= $topluluk ?>">
                    
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Oylama başlığında ara..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Ara
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="oylamalar.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-clockwise"></i> Sıfırla
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Oylama listesi -->
        <div class="row">
            <?php if (empty($oylamalar)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-info-circle display-4 d-block mb-3"></i>
                        <h4>Henüz oylama bulunmuyor</h4>
                        <p class="mb-0">Filtrelerinizi değiştirmeyi deneyin veya yeni bir oylama oluşturun.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($oylamalar as $oylama): 
                    // Oylama ilerlemesi
                    $baslangic = strtotime($oylama['baslangic_tarihi']);
                    $bitis = strtotime($oylama['bitis_tarihi']);
                    $simdi = time();
                    
                    if ($bitis > $baslangic) {
                        $toplam = $bitis - $baslangic;
                        $gecen = $simdi - $baslangic;
                        $yuzde = min(100, max(0, ($gecen / $toplam) * 100));
                    } else {
                        $yuzde = 100;
                    }
                    
                    // Oylama durumu
                    if ($oylama['durum'] == 'sonuclandi') {
                        $status_badge = '<span class="badge bg-success">✅ Sonuçlandı</span>';
                        $progress_color = 'bg-success';
                    } elseif ($yuzde >= 100) {
                        $status_badge = '<span class="badge bg-warning">⏰ Süre Doldu</span>';
                        $progress_color = 'bg-warning';
                    } else {
                        $status_badge = '<span class="badge bg-primary">🔵 Aktif</span>';
                        $progress_color = 'bg-primary';
                    }
                ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card poll-card h-100">
                            <div class="card-body position-relative">
                                <!-- Durum badge -->
                                <div class="poll-status">
                                    <?= $status_badge ?>
                                </div>
                                
                                <!-- Başlık -->
                                <h5 class="card-title mb-3">
                                    <a href="oylama_detay.php?id=<?= $oylama['id'] ?>" 
                                       class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($oylama['baslik']) ?>
                                    </a>
                                </h5>
                                
                                <!-- Açıklama (kısaltılmış) -->
                                <p class="card-text text-muted small mb-3" 
                                   style="height: 60px; overflow: hidden;">
                                    <?= htmlspecialchars(mb_substr($oylama['aciklama'], 0, 100)) . '...' ?>
                                </p>
                                
                                <!-- Bilgiler -->
                                <div class="mb-3">
                                    <div class="row small">
                                        <div class="col-6">
                                            <i class="bi bi-person"></i>
                                            <?= htmlspecialchars($oylama['olusturan_ad']) ?>
                                        </div>
                                        <div class="col-6 text-end">
                                            <i class="bi bi-people"></i>
                                            <?= $oylama['oy_sayisi'] ?> oy
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Süre çubuğu -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span>Başlangıç: <?= date('d.m.Y', $baslangic) ?></span>
                                        <span>Bitiş: <?= date('d.m.Y', $bitis) ?></span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?= $progress_color ?>" 
                                             style="width: <?= $yuzde ?>%"></div>
                                    </div>
                                    <div class="text-center small text-muted mt-1">
                                        <?= round($yuzde) ?>% tamamlandı
                                    </div>
                                </div>
                                
                                <!-- Badgeler -->
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    <span class="badge bg-info">
                                        <?= $oylama['tur'] ?>
                                    </span>
                                    <span class="badge bg-secondary">
                                        <?= $oylama['topluluk_tipi'] ?>
                                    </span>
                                    <?php if ($oylama['topluluk_id']): ?>
                                        <span class="badge bg-light text-dark">
                                            ID: <?= $oylama['topluluk_id'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Butonlar -->
                                <div class="d-grid gap-2">
                                    <a href="oylama_detay.php?id=<?= $oylama['id'] ?>" 
                                       class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> 
                                        <?= $oylama['durum'] == 'aktif' ? 'Oy Kullan' : 'Sonuçları Gör' ?>
                                    </a>
                                    <?php if ($oylama['durum'] == 'sonuclandi'): ?>
                                        <a href="sonuc.php?id=<?= $oylama['id'] ?>" 
                                           class="btn btn-success">
                                            <i class="bi bi-graph-up"></i> Detaylı Sonuçlar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sayfalama -->
        <nav aria-label="Sayfalama" class="mt-5">
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
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Filtre badge'lerine aktif sınıfı ekleme
    document.querySelectorAll('.filter-badge').forEach(badge => {
        badge.addEventListener('click', function() {
            document.querySelectorAll('.filter-badge').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Oylama kartlarına tıklanabilirlik (başlık hariç)
    document.querySelectorAll('.poll-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
            // Eğer tıklanan element bir buton veya link değilse
            if (!e.target.closest('a') && !e.target.closest('button')) {
                const link = this.querySelector('a[href*="oylama_detay"]');
                if (link) {
                    window.location.href = link.href;
                }
            }
        });
    });

    // Arama formu submit olduğunda filtreleri koru
    document.querySelector('form').addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('input[type="hidden"]');
        inputs.forEach(input => {
            if (!input.value) {
                input.remove();
            }
        });
    });
    </script>
</body>
</html>
