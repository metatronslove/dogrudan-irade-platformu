<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="col-lg-2">
    <div class="sticky-top" style="top: 20px;">
        <!-- Kullanıcı Bilgisi -->
        <div class="card mb-3 border-primary">
            <div class="card-body text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                    <?= mb_substr($_SESSION['ad_soyad'], 0, 1) ?>
                </div>
                <h6 class="card-title mb-1"><?= htmlspecialchars($_SESSION['ad_soyad']) ?></h6>
                <p class="card-text small text-muted mb-2"><?= $_SESSION['eposta'] ?></p>
                <span class="badge bg-<?= $_SESSION['yetki_seviye'] == 'superadmin' ? 'danger' : 'warning' ?>">
                    <i class="bi bi-shield-check"></i> 
                    <?= ucfirst($_SESSION['yetki_seviye']) ?>
                </span>
            </div>
        </div>
        
        <!-- Menü -->
        <div class="list-group">
            <a href="index.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span>Dashboard</span>
                <span class="badge bg-primary ms-auto">
                    <?php
                    $aktif_oy = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'aktif'");
                    echo $aktif_oy;
                    ?>
                </span>
            </a>
            
            <a href="oylamalar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'oylamalar.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data me-2"></i>
                <span>Oylamalar</span>
                <span class="badge bg-success ms-auto">
                    <?php
                    $sonuclanan = $db->singleValueQuery("SELECT COUNT(*) FROM oylamalar WHERE durum = 'sonuclandi'");
                    echo $sonuclanan;
                    ?>
                </span>
            </a>
            
            <a href="kullanicilar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'kullanicilar.php' ? 'active' : '' ?>">
                <i class="bi bi-people me-2"></i>
                <span>Kullanıcılar</span>
                <span class="badge bg-warning ms-auto">
                    <?php
                    $aktif_kullanici = $db->singleValueQuery("SELECT COUNT(*) FROM kullanicilar WHERE durum = 'aktif'");
                    echo $aktif_kullanici;
                    ?>
                </span>
            </a>
            
            <a href="loglar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'loglar.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-text me-2"></i>
                <span>Sistem Logları</span>
                <span class="badge bg-danger ms-auto">
                    <?php
                    $bugun_log = $db->singleValueQuery("SELECT COUNT(*) FROM sistem_loglari WHERE DATE(tarih) = CURDATE()");
                    echo $bugun_log;
                    ?>
                </span>
            </a>
            
            <a href="ayarlar.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'ayarlar.php' ? 'active' : '' ?>">
                <i class="bi bi-gear me-2"></i>
                <span>Sistem Ayarları</span>
            </a>
            
            <a href="yedekleme.php" class="list-group-item list-group-item-action d-flex align-items-center 
                <?= $current_page == 'yedekleme.php' ? 'active' : '' ?>">
                <i class="bi bi-database me-2"></i>
                <span>Yedekleme</span>
                <span class="badge bg-info ms-auto">
                    <?php
                    $backup_count = 0;
                    if (file_exists('../backups/')) {
                        $backup_count = count(glob('../backups/*'));
                    }
                    echo $backup_count;
                    ?>
                </span>
            </a>
            
            <div class="list-group-item">
                <small class="text-muted">RAPORLAR</small>
            </div>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               onclick="generateReport('daily')">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span>Günlük Rapor</span>
            </a>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               onclick="generateReport('weekly')">
                <i class="bi bi-calendar-week me-2"></i>
                <span>Haftalık Rapor</span>
            </a>
            
            <div class="list-group-item">
                <small class="text-muted">HIZLI İŞLEMLER</small>
            </div>
            
            <a href="../oylama_olustur.php" class="list-group-item list-group-item-action d-flex align-items-center">
                <i class="bi bi-plus-circle me-2"></i>
                <span>Yeni Oylama</span>
            </a>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               data-bs-toggle="modal" data-bs-target="#quickStatsModal">
                <i class="bi bi-lightning me-2"></i>
                <span>Hızlı İstatistik</span>
            </a>
            
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" 
               onclick="clearSystemCache()">
                <i class="bi bi-eraser me-2"></i>
                <span>Önbelleği Temizle</span>
            </a>
            
            <hr class="my-1">
            
            <a href="../index.php" class="list-group-item list-group-item-action d-flex align-items-center">
                <i class="bi bi-house me-2"></i>
                <span>Siteye Dön</span>
            </a>
            
            <a href="../cikis.php" class="list-group-item list-group-item-action d-flex align-items-center text-danger">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span>Çıkış Yap</span>
            </a>
        </div>
        
        <!-- Sistem Durumu -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-heart-pulse"></i> Sistem Durumu
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted d-block">Veritabanı</small>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-success" style="width: 85%"></div>
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Disk Kullanımı</small>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-info" style="width: <?= $usage_percent ?? 45 ?>%"></div>
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Sistem Yükü</small>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-warning" style="width: 65%"></div>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        <i class="bi bi-clock"></i> 
                        <?= date('H:i') ?> | 
                        <?= date('d.m.Y') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı İstatistik Modal -->
<div class="modal fade" id="quickStatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-lightning"></i> Hızlı Sistem İstatistikleri
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="display-6 text-primary"><?= $aktif_oy ?? 0 ?></div>
                            <small>Aktif Oylama</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="display-6 text-success"><?= $aktif_kullanici ?? 0 ?></div>
                            <small>Aktif Kullanıcı</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="display-6 text-warning"><?= $bugun_oy ?? 0 ?></div>
                            <small>Bugünkü Oy</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="display-6 text-info"><?= $bugun_log ?? 0 ?></div>
                            <small>Bugünkü Log</small>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    Son güncelleme: <?= date('H:i:s') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateReport(type) {
    fetch(`api.php?action=generate_report&type=${type}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const reportWindow = window.open('', '_blank');
                reportWindow.document.write(`
                    <html>
                    <head>
                        <title>${type === 'daily' ? 'Günlük' : 'Haftalık'} Rapor</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                            th { background-color: #f8f9fa; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .footer { margin-top: 30px; font-size: 12px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>DOĞRUDAN İRADE PLATFORMU</h2>
                            <h3>${type === 'daily' ? 'Günlük' : 'Haftalık'} Sistem Raporu</h3>
                            <p>${new Date().toLocaleDateString('tr-TR')}</p>
                        </div>
                        
                        <table>
                            <tr>
                                <th>Metrik</th>
                                <th>Değer</th>
                            </tr>
                            ${Object.entries(data.report).map(([key, value]) => `
                                <tr>
                                    <td>${key}</td>
                                    <td>${value}</td>
                                </tr>
                            `).join('')}
                        </table>
                        
                        <div class="footer">
                            <p>Rapor oluşturulma tarihi: ${new Date().toLocaleString('tr-TR')}</p>
                            <p>Oluşturan: <?= $_SESSION['ad_soyad'] ?></p>
                        </div>
                    </body>
                    </html>
                `);
                reportWindow.document.close();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Rapor oluşturulurken hata oluştu.');
            });
}

function clearSystemCache() {
    if (confirm('Sistem önbelleğini temizlemek istediğinize emin misiniz?')) {
        fetch('api.php?action=clear_cache', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sistem önbelleği temizlendi.');
            } else {
                alert('Temizleme sırasında hata: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
        });
    }
}
</script>
