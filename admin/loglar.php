<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireSuperAdmin();

$db = new Database();

// Filtreler
$tip = $_GET['tip'] ?? '';
$kullanici_id = $_GET['kullanici_id'] ?? '';
$tarih_baslangic = $_GET['tarih_baslangic'] ?? '';
$tarih_bitis = $_GET['tarih_bitis'] ?? '';

// Sorgu oluştur
$sql = "SELECT l.*, k.ad_soyad, k.eposta 
        FROM sistem_loglari l 
        LEFT JOIN kullanicilar k ON l.kullanici_id = k.id 
        WHERE 1=1";
        
$params = [];

if (!empty($tip)) {
    $sql .= " AND l.islem_tipi = ?";
    $params[] = $tip;
}

if (!empty($kullanici_id) && is_numeric($kullanici_id)) {
    $sql .= " AND l.kullanici_id = ?";
    $params[] = $kullanici_id;
}

if (!empty($tarih_baslangic)) {
    $sql .= " AND DATE(l.tarih) >= ?";
    $params[] = $tarih_baslangic;
}

if (!empty($tarih_bitis)) {
    $sql .= " AND DATE(l.tarih) <= ?";
    $params[] = $tarih_bitis;
}

$sql .= " ORDER BY l.tarih DESC LIMIT 500";

$loglar = $db->query($sql, $params)->fetchAll();

// Benzersiz log tipleri
$log_tipleri = $db->query(
    "SELECT DISTINCT islem_tipi FROM sistem_loglari ORDER BY islem_tipi"
)->fetchAll(PDO::FETCH_COLUMN);

// Toplam log sayısı
$toplam_log = $db->singleValueQuery("SELECT COUNT(*) FROM sistem_loglari");
$bugun_log = $db->singleValueQuery(
    "SELECT COUNT(*) FROM sistem_loglari WHERE DATE(tarih) = CURDATE()"
);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Logları - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .log-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .log-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .log-success { border-left-color: #28a745; }
        .log-error { border-left-color: #dc3545; }
        .log-warning { border-left-color: #ffc107; }
        .log-info { border-left-color: #17a2b8; }
        .log-primary { border-left-color: #007bff; }
        
        .ip-address {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
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
                    <i class="bi bi-journal-text"></i> Sistem Logları
                </h2>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h3 class="card-title text-primary"><?= $toplam_log ?></h3>
                                <p class="card-text">Toplam Log Kaydı</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h3 class="card-title text-success"><?= $bugun_log ?></h3>
                                <p class="card-text">Bugünkü Kayıt</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h3 class="card-title text-info">
                                    <?= count($log_tipleri) ?>
                                </h3>
                                <p class="card-text">Farklı Log Tipi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreleme -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-funnel"></i> Log Filtreleme
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">İşlem Tipi</label>
                                <select class="form-select" name="tip">
                                    <option value="">Tüm İşlemler</option>
                                    <?php foreach ($log_tipleri as $tip_adi): ?>
                                        <option value="<?= $tip_adi ?>" <?= $tip == $tip_adi ? 'selected' : '' ?>>
                                            <?= $tip_adi ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Kullanıcı ID</label>
                                <input type="number" class="form-control" name="kullanici_id" 
                                       value="<?= htmlspecialchars($kullanici_id) ?>"
                                       placeholder="Kullanıcı ID girin">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" name="tarih_baslangic" 
                                       value="<?= htmlspecialchars($tarih_baslangic) ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" name="tarih_bitis" 
                                       value="<?= htmlspecialchars($tarih_bitis) ?>">
                            </div>
                            
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-funnel"></i> Filtrele
                                    </button>
                                    <a href="loglar.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Sıfırla
                                    </a>
                                    <button type="button" class="btn btn-danger ms-auto" 
                                            onclick="confirmClearLogs()">
                                        <i class="bi bi-trash"></i> Logları Temizle
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Log Listesi -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul"></i> Log Kayıtları
                                <small class="text-muted">(Son 500 kayıt gösteriliyor)</small>
                            </h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="exportLogs('json')">
                                    <i class="bi bi-download"></i> JSON İndir
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="exportLogs('csv')">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> CSV İndir
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tarih</th>
                                        <th>Kullanıcı</th>
                                        <th>İşlem Tipi</th>
                                        <th>Açıklama</th>
                                        <th>IP Adresi</th>
                                        <th>Tarayıcı</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loglar as $log): 
                                        // Log tipine göre sınıf belirle
                                        $log_class = match(true) {
                                            strpos($log['islem_tipi'], 'hata') !== false => 'log-error',
                                            strpos($log['islem_tipi'], 'uyari') !== false => 'log-warning',
                                            strpos($log['islem_tipi'], 'basarili') !== false => 'log-success',
                                            strpos($log['islem_tipi'], 'giris') !== false => 'log-primary',
                                            default => 'log-info'
                                        };
                                        
                                        // Kullanıcı bilgisi
                                        $kullanici_bilgi = $log['ad_soyad'] 
                                            ? htmlspecialchars($log['ad_soyad']) . '<br><small class="text-muted">' . htmlspecialchars($log['eposta']) . '</small>'
                                            : '<span class="badge bg-secondary">Sistem</span>';
                                    ?>
                                        <tr class="log-item <?= $log_class ?>">
                                            <td><small><?= $log['id'] ?></small></td>
                                            <td>
                                                <small>
                                                    <?= date('d.m.Y', strtotime($log['tarih'])) ?><br>
                                                    <?= date('H:i:s', strtotime($log['tarih'])) ?>
                                                </small>
                                            </td>
                                            <td><?= $kullanici_bilgi ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    strpos($log_class, 'success') !== false ? 'success' :
                                                    (strpos($log_class, 'error') !== false ? 'danger' :
                                                    (strpos($log_class, 'warning') !== false ? 'warning' : 'info'))
                                                ?>">
                                                    <?= $log['islem_tipi'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 300px;">
                                                    <?= htmlspecialchars($log['aciklama']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="ip-address">
                                                    <?= $log['ip_adresi'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= 
                                                        strlen($log['user_agent']) > 50 
                                                        ? substr($log['user_agent'], 0, 50) . '...' 
                                                        : $log['user_agent'] 
                                                    ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($loglar)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-journal-x display-4 text-muted d-block mb-3"></i>
                                <h5>Log kaydı bulunamadı</h5>
                                <p class="text-muted">Filtrelerinizi değiştirmeyi deneyin.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Log İstatistikleri -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-pie-chart"></i> İşlem Tipi Dağılımı
                                </h6>
                            </div>
                            <div class="card-body">
                                <div style="height: 250px;">
                                    <!-- Buraya chart.js veya başka bir chart kütüphanesi eklenebilir -->
                                    <canvas id="logChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-calendar"></i> Son 7 Gün Aktivite
                                </h6>
                            </div>
                            <div class="card-body">
                                <div style="height: 250px;">
                                    <!-- Aktivite grafiği -->
                                    <canvas id="activityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Temizleme Onay Modal -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Logları Temizle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">⚠️ UYARI!</h6>
                        <p class="mb-0">
                            Tüm sistem loglarını temizlemek üzeresiniz. Bu işlem geri alınamaz!
                            Güvenlik ve denetim açısından logların silinmesi önerilmez.
                        </p>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmClear">
                        <label class="form-check-label" for="confirmClear">
                            Tüm log kayıtlarının silineceğini anlıyorum
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="keepLastMonth">
                        <label class="form-check-label" for="keepLastMonth">
                            Son 1 aylık logları sakla
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-danger" id="confirmClearBtn" disabled>
                        <i class="bi bi-trash"></i> Temizle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    function confirmClearLogs() {
        const modal = new bootstrap.Modal(document.getElementById('clearLogsModal'));
        modal.show();
    }
    
    // Onay checkbox kontrolü
    document.getElementById('confirmClear').addEventListener('change', function() {
        document.getElementById('confirmClearBtn').disabled = !this.checked;
    });
    
    // Log temizleme işlemi
    document.getElementById('confirmClearBtn').addEventListener('click', function() {
        const keepLastMonth = document.getElementById('keepLastMonth').checked;
        
        fetch('api.php?action=clear_logs&keep=' + (keepLastMonth ? '1' : '0'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Loglar başarıyla temizlendi.');
                location.reload();
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
        });
    });
    
    // Log grafiği
    document.addEventListener('DOMContentLoaded', function() {
        // İşlem tipi dağılım grafiği
        const logCtx = document.getElementById('logChart').getContext('2d');
        
        // Örnek veri - gerçek uygulamada API'den alınmalı
        const logChart = new Chart(logCtx, {
            type: 'doughnut',
            data: {
                labels: ['Girişler', 'Oy Verme', 'Hatalar', 'Diğer'],
                datasets: [{
                    data: [35, 45, 10, 10],
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#dc3545',
                        '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Aktivite grafiği
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: ['6 gün önce', '5 gün önce', '4 gün önce', '3 gün önce', '2 gün önce', 'Dün', 'Bugün'],
                datasets: [{
                    label: 'Log Sayısı',
                    data: [45, 52, 48, 60, 55, 65, <?= $bugun_log ?>],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 10
                        }
                    }
                }
            }
        });
    });
    
    function exportLogs(format) {
        const params = new URLSearchParams(window.location.search);
        params.append('format', format);
        
        window.open('api.php?action=export_logs&' + params.toString(), '_blank');
    }
    </script>
</body>
</html>
