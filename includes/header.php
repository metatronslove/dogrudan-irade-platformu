<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand fw-bold" href="index.php">
            <span class="d-inline-block align-middle">
                🗳️ Doğrudan İrade
            </span>
        </a>
        
        <!-- Mobil menü butonu -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Ana menü -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-house-door"></i> Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'oylamalar.php' ? 'active' : '' ?>" href="oylamalar.php">
                        <i class="bi bi-clipboard-data"></i> Tüm Oylamalar
                    </a>
                </li>
                <?php if (isset($_SESSION['kullanici_id']) && $_SESSION['yetki_seviye'] === 'superadmin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'oylama_olustur.php' ? 'active' : '' ?>" href="oylama_olustur.php">
                        <i class="bi bi-plus-circle"></i> Yeni Oylama
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-info-circle"></i> Hakkında
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">
                            <i class="bi bi-question-circle"></i> Sistem Nasıl Çalışır?
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#negativeVoteModal">
                            <i class="bi bi-lightbulb"></i> Negatif Oy Sistemi
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="bi bi-envelope"></i> İletişim
                        </a></li>
                    </ul>
                </li>
            </ul>
            
            <!-- Sağ menü -->
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['kullanici_id'])): ?>
                    <!-- Kullanıcı giriş yapmış -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> 
                            <?= htmlspecialchars($_SESSION['ad_soyad']) ?>
                            <?php if ($_SESSION['yetki_seviye'] !== 'kullanici'): ?>
                                <span class="badge bg-warning ms-1">Yönetici</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="profil.php">
                                    <i class="bi bi-person"></i> Profilim
                                </a>
                            </li>
                            <?php if ($_SESSION['yetki_seviye'] === 'superadmin'): ?>
                                <li>
                                    <a class="dropdown-item" href="admin/index.php">
                                        <i class="bi bi-speedometer2"></i> Yönetim Paneli
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item text-danger" href="cikis.php">
                                    <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Kullanıcı giriş yapmamış -->
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'giris.php' ? 'active' : '' ?>" href="giris.php">
                            <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'kayit.php' ? 'active' : '' ?> btn btn-outline-light btn-sm ms-2" 
                           href="kayit.php">
                            <i class="bi bi-person-plus"></i> Kayıt Ol
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Sistem Nasıl Çalışır Modal -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-question-circle"></i> Doğrudan İrade Platformu - Nasıl Çalışır?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>✅ Destek Oyu</h6>
                        <p class="small">Bir adayı veya seçeneği aktif olarak desteklemek için kullanılır. Her oylamada sadece BİR destek oyu verebilirsiniz.</p>
                        
                        <h6>❌ Negatif Oy</h6>
                        <p class="small">Kabul edemediğiniz seçeneklere karşı kullanılır. İstediğiniz kadar negatif oy verebilirsiniz.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>📊 Net Skor Sistemi</h6>
                        <p class="small">Kazanan şu formülle belirlenir:<br>
                        <code>NET SKOR = Destek Oyu - Negatif Oy</code></p>
                        
                        <h6>🏆 Kazanan Belirleme</h6>
                        <p class="small">En yüksek net skora sahip aday/seçenek kazanır. Eşitlik durumunda daha az negatif oy alan kazanır.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Negatif Oy Sistemi Modal -->
<div class="modal fade" id="negativeVoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-lightbulb-fill"></i> NEGATİF OY SİSTEMİ NEDEN ÖNEMLİ?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>🎯 Sistemin Amacı</h6>
                        <ul class="small">
                            <li>Popüler ama sevilmeyen adayları filtreler</li>
                            <li>Toplumsal mutabakatı yansıtır</li>
                            <li>Manipülasyonu zorlaştırır</li>
                            <li>Gerçek kabul edilebilirliği ölçer</li>
                        </ul>
                        
                        <h6>📈 Geleneksel Sistemdeki Sorunlar</h6>
                        <ul class="small">
                            <li>Sadece "en az kötü" seçilebilir</li>
                            <li>Kutuplaşmayı artırır</li>
                            <li>Gerçek tercihi yansıtmaz</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>📊 Örnek Senaryo</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Aday</th>
                                        <th>Destek</th>
                                        <th>Negatif</th>
                                        <th>Net Skor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Aday A</td>
                                        <td>600</td>
                                        <td>450</td>
                                        <td class="text-success">150</td>
                                    </tr>
                                    <tr>
                                        <td>Aday B</td>
                                        <td>400</td>
                                        <td>50</td>
                                        <td class="text-success fw-bold">350</td>
                                    </tr>
                                    <tr>
                                        <td>Aday C</td>
                                        <td>250</td>
                                        <td>100</td>
                                        <td class="text-success">150</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="small">Geleneksel sistemde Aday A kazanırdı (600 oy). Negatif oy sisteminde ise Aday B kazanır (350 net skor).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İletişim Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope"></i> İletişim
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Doğrudan İrade Platformu</h6>
                <p class="small mb-3">Doğrudan demokrasi için geliştirilmiş açık kaynaklı bir platform.</p>
                
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <i class="bi bi-globe text-primary"></i> 
                        <strong>Web:</strong> www.dogrudanirade.org
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-envelope text-success"></i> 
                        <strong>E-posta:</strong> info@dogrudanirade.org
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-github text-dark"></i> 
                        <strong>GitHub:</strong> github.com/dogrudan-irade
                    </li>
                </ul>
                
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i> 
                    Bu platform açık kaynaklıdır. Katkıda bulunmak isterseniz 
                    GitHub deposunu ziyaret edin.
                </div>
            </div>
        </div>
    </div>
</div>
