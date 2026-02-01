<?php
$current_page = basename($_SERVER['PHP_FILE'] ?? '');
?>
<footer class="bg-dark text-white py-5 mt-5">
    <div class="container">
        <div class="row">
            <!-- Logo ve açıklama -->
            <div class="col-lg-4 mb-4">
                <h4 class="fw-bold mb-3">
                    🗳️ Doğrudan İrade
                </h4>
                <p class="small">
                    "Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"
                </p>
                <p class="small text-muted">
                    Doğrudan demokrasi için geliştirilmiş, şeffaf ve güvenli bir dijital platform.
                </p>
                
                <div class="mt-3">
                    <a href="https://github.com/dogrudan-irade" target="_blank" class="text-white-50 me-2">
                        <i class="bi bi-github"></i> GitHub
                    </a>
                    <a href="#" class="text-white-50 me-2">
                        <i class="bi bi-twitter"></i> Twitter
                    </a>
                    <a href="#" class="text-white-50">
                        <i class="bi bi-facebook"></i> Facebook
                    </a>
                </div>
            </div>
            
            <!-- Hızlı linkler -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="text-uppercase fw-bold mb-3">Hızlı Linkler</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="index.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-house-door"></i> Ana Sayfa
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="oylamalar.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-clipboard-data"></i> Tüm Oylamalar
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="oylama_olustur.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-plus-circle"></i> Yeni Oylama
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="profil.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-person"></i> Profilim
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="iletisim.php" class="text-white-50 text-decoration-none small">
                            <i class="bi bi-envelope"></i> İletişim
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Bilgi -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h6 class="text-uppercase fw-bold mb-3">Bilgi</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#aboutModal">
                            <i class="bi bi-question-circle"></i> Nasıl Çalışır?
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#negativeVoteModal">
                            <i class="bi bi-lightbulb"></i> Negatif Oy Sistemi
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="bi bi-envelope"></i> İletişim
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none small" 
                           data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="bi bi-file-earmark-text"></i> SSS
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- İletişim -->
            <div class="col-lg-3 col-md-12">
                <h6 class="text-uppercase fw-bold mb-3">İletişim</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="bi bi-envelope text-white-50"></i> 
                        <a href="mailto:info@dogrudanirade.org" class="text-white-50 text-decoration-none small">
                            info@dogrudanirade.org
                        </a>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-geo-alt text-white-50"></i> 
                        <span class="text-white-50 small">Türkiye</span>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-globe text-white-50"></i> 
                        <a href="https://www.dogrudanirade.org" class="text-white-50 text-decoration-none small">
                            www.dogrudanirade.org
                        </a>
                    </li>
                    <li class="mt-3">
                        <a href="#" class="btn btn-primary btn-sm">
                            <i class="bi bi-envelope"></i> Bültenimize Katılın
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <hr class="my-4 bg-secondary">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="small text-white-50 mb-0">
                    &copy; <?= date('Y') ?> Doğrudan İrade Platformu. Tüm hakları saklıdır.
                </p>
                <p class="small text-white-50 mb-0 mt-1">
                    <a href="#" class="text-white-50 text-decoration-none">Gizlilik Politikası</a> | 
                    <a href="#" class="text-white-50 text-decoration-none">Kullanım Şartları</a> | 
                    <a href="#" class="text-white-50 text-decoration-none">Çerez Politikası</a>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <p class="small text-white-50 mb-0">
                    <i class="bi bi-shield-check"></i> 
                    <a href="#" class="text-white-50 text-decoration-none">Güvenli Platform</a> |
                    <a href="#" class="text-white-50 text-decoration-none">Açık Kaynak</a> |
                    <a href="#" class="text-white-50 text-decoration-none">CC0 Lisans</a>
                </p>
                <p class="small text-white-50 mb-0 mt-1">
                    <a href="#" class="text-white-50 text-decoration-none">
                        <i class="bi bi-shield-check"></i> Güvenlik Bildirimi
                    </a>
                </p>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <small class="text-white-50">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Uyarı:</strong> Bu site test ortamında çalışmaktadır. Gerçek platform için resmi sitemizi ziyaret edin.
            </small>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Toast Container -->
<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>

<!-- Modal İçerikleri -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-question-circle"></i> Nasıl Çalışıyor?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>✅ Destek Oyu</h6>
                        <p class="small">Bir adayı veya seçeneği aktif olarak desteklemek için kullanılır. Her oylamada sadece BİR destek oyu verebilirsiniz.</p>
                        
                        <h6>❌ Negatif Oy</h6>
                        <p class="small">Kabul edemediğiniz seçeneklere karşı oy kullanın. İstediğiniz kadar negatif oy verebilirsiniz.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>📊 Net Skor Sistemi</h6>
                        <p class="small">Kazanan şu formülle belirlenir:<br><code>NET SKOR = Destek - Negatif</code></p>
                        
                        <h6>🏆 Kazanan Belirleme</h6>
                        <p class="small">Net skor yüksekten düşüğe doğru sıralanır. Eşitlik durumunda daha az negatif oy alan kazanır.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="negativeVoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-lightbulb-fill"></i> Negatif Oy Sistemi</h5>
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
                            <li>Daha gerçek bir toplumsal tercihi ölçer</li>
                        </ul>
                        
                        <h6>📊 Örnek Senaryo</h6>
                        <div class="alert alert-info small">
                            <div class="d-flex justify-content-between">
                                <div>Aday A: +600, -450 = <strong>150</strong></div>
                                <div>Aday B: +400, -50 = <strong>350</strong> (KAZANAN)</div>
                                <div>Aday C: +250, -100 = <strong>150</strong></div>
                            </div>
                        </div>
                        <p class="small">Geleneksel sistemde Aday A kazanırdı. Negatif oy sisteminde Aday B kazanır.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>📈 Sistem Avantajları</h6>
                        <ul class="small">
                            <li>Sadece "en az kötü" seçilebilir</li>
                            <li>Kutuplaşmayı artırır</li>
                            <li>Gerçek tercihi yansıtmaz</li>
                        </ul>
                        
                        <h6>🎯 Sistem Nasıl Çalışır?</h6>
                        <p class="small">Her oylama için destek ve negatif oy butonları bulunur. Seçeneklerin net skoru hesaplanır.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-envelope"></i> İletişim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="small">Doğrudan İrade Platformu</h6>
                        <p class="small text-muted">Doğrudan demokrasi için geliştirilmiş açık kaynaklı bir platform.</p>
                        <p class="small">
                            <i class="bi bi-envelope"></i> info@dogrudanirade.org<br>
                            <i class="bi bi-geo-alt"></i> Türkiye
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="small">Sosyal Medya</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-instagram"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-youtube"></i>
                            </a>
                        </div>
                        <div class="mt-3">
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-github"></i> GitHub
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
