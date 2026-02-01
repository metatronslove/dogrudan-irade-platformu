<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();
$errors = [];
$success = '';

// Sistem ayarlarını al
$iletisim_acik = $db->singleValueQuery(
    "SELECT ayar_degeri FROM sistem_ayarlari WHERE ayar_adi = 'iletisim_formu_acik'"
);

if ($iletisim_acik == '0') {
    header("Location: index.php");
    exit;
}

// İletişim bilgilerini al
$iletisim_bilgileri = [];
$ayarlar = $db->query(
    "SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari 
     WHERE ayar_adi LIKE 'iletisim_%' OR ayar_adi LIKE 'sosyal_%'"
)->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad']);
    $eposta = trim($_POST['eposta']);
    $konu = trim($_POST['konu']);
    $mesaj = trim($_POST['mesaj']);
    
    // Validasyon
    if (empty($ad_soyad)) $errors[] = 'Ad soyad gereklidir';
    if (empty($eposta) || !filter_var($eposta, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta girin';
    if (empty($konu)) $errors[] = 'Konu gereklidir';
    if (empty($mesaj) || strlen($mesaj) < 10) $errors[] = 'Mesaj en az 10 karakter olmalı';
    
    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'];
    $today_count = $db->singleValueQuery(
        "SELECT COUNT(*) FROM iletisim_mesajlari 
         WHERE ip_adresi = ? AND DATE(olusturulma_tarihi) = CURDATE()",
        [$ip]
    );
    
    if ($today_count >= 5) {
        $errors[] = 'Günlük mesaj limitinize ulaştınız. Lütfen yarın tekrar deneyin.';
    }
    
    if (empty($errors)) {
        try {
            // Mesajı kaydet
            $db->query(
                "INSERT INTO iletisim_mesajlari (ad_soyad, eposta, konu, mesaj, ip_adresi, user_agent) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$ad_soyad, $eposta, $konu, $mesaj, $ip, $_SERVER['HTTP_USER_AGENT']]
            );
            
            // E-posta gönder (yöneticiye)
            $admin_email = $ayarlar['iletisim_eposta'] ?? 'admin@dogrudanirade.org';
            $email_subject = "Yeni İletişim Mesajı: $konu";
            $email_body = "
            <h2>Yeni İletişim Formu Mesajı</h2>
            <p><strong>Gönderen:</strong> $ad_soyad ($eposta)</p>
            <p><strong>Konu:</strong> $konu</p>
            <p><strong>Mesaj:</strong></p>
            <div style='background:#f8f9fa; padding:15px; border-radius:5px;'>
                " . nl2br(htmlspecialchars($mesaj)) . "
            </div>
            <hr>
            <p><small>
                IP: $ip<br>
                Zaman: " . date('d.m.Y H:i:s') . "<br>
                Tarayıcı: " . substr($_SERVER['HTTP_USER_AGENT'], 0, 100) . "
            </small></p>
            ";
            
            // sendEmail($admin_email, $email_subject, $email_body);
            
            // Cevaplama e-postası (isteğe bağlı)
            if (isset($_POST['kopya'])) {
                $user_subject = "Mesajınız Alındı: $konu";
                $user_body = "
                <h2>Doğrudan İrade Platformu</h2>
                <p>Sayın $ad_soyad,</p>
                <p>İletişim formu aracılığıyla gönderdiğiniz mesajınız başarıyla alınmıştır.</p>
                <p><strong>Mesajınız:</strong></p>
                <div style='background:#f8f9fa; padding:15px; border-radius:5px;'>
                    " . nl2br(htmlspecialchars($mesaj)) . "
                </div>
                <p>En kısa sürede size dönüş yapılacaktır.</p>
                <hr>
                <p><small>
                    <strong>Doğrudan İrade Platformu</strong><br>
                    Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!
                </small></p>
                ";
                
                // sendEmail($eposta, $user_subject, $user_body);
            }
            
            // Log
            logIslem($_SESSION['kullanici_id'] ?? 0, 'iletisim_mesaji', "Konu: $konu", $ip);
            
            $success = 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapılacaktır.';
            
            // Formu temizle
            $_POST = [];
            
        } catch (Exception $e) {
            $errors[] = 'Mesaj gönderilirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İletişim - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .contact-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            height: 100%;
        }
        .contact-icon {
            font-size: 40px;
            margin-bottom: 15px;
            display: inline-block;
        }
        .contact-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .social-link:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
        }
        .map-container {
            border-radius: 15px;
            overflow: hidden;
            height: 300px;
            margin-top: 30px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <!-- Başlık -->
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold text-primary mb-3">
                <i class="bi bi-chat-left-text"></i> İLETİŞİM
            </h1>
            <p class="lead text-muted">
                Sorularınız, önerileriniz veya görüşleriniz için bizimle iletişime geçin
            </p>
        </div>

        <div class="row">
            <!-- İletişim Bilgileri -->
            <div class="col-lg-4 mb-4">
                <div class="contact-info-card">
                    <div class="mb-4">
                        <div class="contact-icon">
                            🗳️
                        </div>
                        <h3 class="mb-3">Doğrudan İrade</h3>
                        <p class="mb-0">
                            "Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="mb-3">
                            <i class="bi bi-geo-alt"></i> İletişim Bilgileri
                        </h5>
                        
                        <?php if (!empty($ayarlar['iletisim_eposta'])): ?>
                            <div class="mb-3">
                                <i class="bi bi-envelope"></i>
                                <strong>E-posta:</strong><br>
                                <a href="mailto:<?= htmlspecialchars($ayarlar['iletisim_eposta']) ?>" 
                                   class="text-white">
                                    <?= htmlspecialchars($ayarlar['iletisim_eposta']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($ayarlar['iletisim_telefon'])): ?>
                            <div class="mb-3">
                                <i class="bi bi-telephone"></i>
                                <strong>Telefon:</strong><br>
                                <a href="tel:<?= htmlspecialchars($ayarlar['iletisim_telefon']) ?>" 
                                   class="text-white">
                                    <?= htmlspecialchars($ayarlar['iletisim_telefon']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($ayarlar['iletisim_adres'])): ?>
                            <div class="mb-3">
                                <i class="bi bi-geo-alt"></i>
                                <strong>Adres:</strong><br>
                                <?= htmlspecialchars($ayarlar['iletisim_adres']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sosyal Medya -->
                    <div>
                        <h5 class="mb-3">
                            <i class="bi bi-share"></i> Sosyal Medya
                        </h5>
                        
                        <div class="d-flex">
                            <?php if (!empty($ayarlar['sosyal_facebook'])): ?>
                                <a href="<?= htmlspecialchars($ayarlar['sosyal_facebook']) ?>" 
                                   class="social-link" target="_blank" title="Facebook">
                                    <i class="bi bi-facebook"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($ayarlar['sosyal_twitter'])): ?>
                                <a href="<?= htmlspecialchars($ayarlar['sosyal_twitter']) ?>" 
                                   class="social-link" target="_blank" title="Twitter">
                                    <i class="bi bi-twitter"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($ayarlar['sosyal_instagram'])): ?>
                                <a href="<?= htmlspecialchars($ayarlar['sosyal_instagram']) ?>" 
                                   class="social-link" target="_blank" title="Instagram">
                                    <i class="bi bi-instagram"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="#" class="social-link" title="YouTube">
                                <i class="bi bi-youtube"></i>
                            </a>
                            
                            <a href="#" class="social-link" title="Telegram">
                                <i class="bi bi-telegram"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Çalışma Saatleri -->
                    <div class="mt-4 pt-4 border-top border-white-50">
                        <h5 class="mb-3">
                            <i class="bi bi-clock"></i> Çalışma Saatleri
                        </h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">Pazartesi - Cuma: 09:00 - 18:00</li>
                            <li class="mb-2">Cumartesi: 10:00 - 16:00</li>
                            <li>Pazar: Kapalı</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- İletişim Formu -->
            <div class="col-lg-8">
                <div class="contact-form">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <h5 class="alert-heading">
                                <i class="bi bi-exclamation-triangle"></i> Hatalar:
                            </h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="contactForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ad_soyad" class="form-label">
                                    <i class="bi bi-person"></i> Ad Soyad *
                                </label>
                                <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" 
                                       value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '') ?>" 
                                       required minlength="3">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="eposta" class="form-label">
                                    <i class="bi bi-envelope"></i> E-posta Adresiniz *
                                </label>
                                <input type="email" class="form-control" id="eposta" name="eposta" 
                                       value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="konu" class="form-label">
                                <i class="bi bi-chat"></i> Konu *
                            </label>
                            <select class="form-select" id="konu" name="konu" required>
                                <option value="">Seçiniz</option>
                                <option value="Genel Soru" <?= ($_POST['konu'] ?? '') == 'Genel Soru' ? 'selected' : '' ?>>Genel Soru</option>
                                <option value="Teknik Destek" <?= ($_POST['konu'] ?? '') == 'Teknik Destek' ? 'selected' : '' ?>>Teknik Destek</option>
                                <option value="Öneri" <?= ($_POST['konu'] ?? '') == 'Öneri' ? 'selected' : '' ?>>Öneri</option>
                                <option value="Şikayet" <?= ($_POST['konu'] ?? '') == 'Şikayet' ? 'selected' : '' ?>>Şikayet</option>
                                <option value="İşbirliği" <?= ($_POST['konu'] ?? '') == 'İşbirliği' ? 'selected' : '' ?>>İşbirliği</option>
                                <option value="Basın" <?= ($_POST['konu'] ?? '') == 'Basın' ? 'selected' : '' ?>>Basın</option>
                                <option value="Diğer" <?= ($_POST['konu'] ?? '') == 'Diğer' ? 'selected' : '' ?>>Diğer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mesaj" class="form-label">
                                <i class="bi bi-chat-left-text"></i> Mesajınız *
                            </label>
                            <textarea class="form-control" id="mesaj" name="mesaj" 
                                      rows="6" required minlength="10"
                                      placeholder="Mesajınızı buraya yazın..."><?= htmlspecialchars($_POST['mesaj'] ?? '') ?></textarea>
                            <div class="form-text">
                                En az 10 karakter. Kalan: <span id="charCount">0</span> karakter
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="kopya" name="kopya" 
                                   <?= isset($_POST['kopya']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="kopya">
                                Mesajımın bir kopyasını e-posta adresime gönder
                            </label>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="kvkk" required>
                                <label class="form-check-label" for="kvkk">
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#kvkkModal">
                                        Kişisel verilerin korunması politikasını
                                    </a> okudum ve kabul ediyorum *
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send"></i> Mesajı Gönder
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Harita (örnek) -->
                <div class="map-container">
                    <div style="width:100%;height:100%;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
                        <div class="text-center">
                            <i class="bi bi-map display-4 text-muted mb-3"></i>
                            <p class="text-muted mb-0">Harita burada görüntülenecek</p>
                            <small class="text-muted">
                                (Google Maps veya OpenStreetMap entegrasyonu)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sık Sorulan Sorular -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-question-circle"></i> Sık Sorulan Sorular
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                        Doğrudan İrade Platformu nedir?
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Doğrudan İrade, temsili demokrasinin aksine, vatandaşların doğrudan karar almasını sağlayan bir dijital platformdur. Negatif oy sistemi ile daha gerçekçi toplumsal tercihleri yansıtır.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                        Nasıl kayıt olabilirim?
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Ana sayfadan veya üst menüden "Kayıt Ol" butonuna tıklayarak kayıt formunu doldurabilirsiniz. TC kimlik numarası isteğe bağlıdır, güvenliğiniz için şifrelenerek saklanır.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                        Negatif oy sistemi nedir?
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Negatif oy sistemi, sadece kimin daha çok sevildiğini değil, kimin daha az sevilmediğini de ölçer. Net skor (Destek - Negatif) formülü ile kazanan belirlenir. Bu sistem popüler ama sevilmeyen adayları filtreler.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                        Oylarım güvende mi?
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Evet, tüm oylar şifrelenerek saklanır. Sistem SQL injection, XSS ve diğer saldırılara karşı korumalıdır. Oylarınız anonim olarak istatistiklerde kullanılır.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- KVKK Modal -->
    <div class="modal fade" id="kvkkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-shield-check"></i> Kişisel Verilerin Korunması
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <h6>1. VERİ SORUMLUSU</h6>
                        <p>Doğrudan İrade Platformu, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca veri sorumlusudur.</p>
                        
                        <h6>2. TOPLANAN VERİLER</h6>
                        <p>2.1. Kimlik Bilgileri: Ad-soyad, TC kimlik no (isteğe bağlı)<br>
                        2.2. İletişim Bilgileri: E-posta, telefon (isteğe bağlı)<br>
                        2.3. Kullanıcı Bilgileri: Doğum tarihi, üyelik bilgileri<br>
                        2.4. Oylama Verileri: Destek ve negatif oy tercihleri</p>
                        
                        <h6>3. VERİLERİN İŞLENME AMAÇLARI</h6>
                        <p>3.1. Platformun işleyişini sağlamak<br>
                        3.2. Oylama süreçlerini yönetmek<br>
                        3.3. İletişim ve bildirim göndermek<br>
                        3.4. İstatistik ve analiz yapmak<br>
                        3.5. Güvenlik ve denetim sağlamak</p>
                        
                        <h6>4. VERİLERİN KORUNMASI</h6>
                        <p>4.1. Tüm veriler şifrelenerek saklanır.<br>
                        4.2. Veri tabanı güvenlik duvarı ile korunur.<br>
                        4.3. Düzenli güvenlik denetimleri yapılır.<br>
                        4.4. Yetkisiz erişim engellenir.</p>
                        
                        <h6>5. VERİLERİN PAYLAŞILMASI</h6>
                        <p>Kişisel verileriniz kesinlikle üçüncü şahıslarla paylaşılmaz. Sadece yasal zorunluluk hallerinde yetkili mercilere bilgi verilebilir.</p>
                        
                        <h6>6. HAKLARINIZ</h6>
                        <p>KVKK'nın 11. maddesi uyarınca kişisel verilerinizin;<br>
                        - İşlenip işlenmediğini öğrenme,<br>
                        - İşlenmişse buna ilişkin bilgi talep etme,<br>
                        - İşlenme amacını ve bunların amacına uygun kullanılıp kullanılmadığını öğrenme,<br>
                        - Silinmesini veya yok edilmesini isteme,<br>
                        - Düzeltilmesini isteme haklarına sahipsiniz.</p>
                        
                        <h6>7. İLETİŞİM</h6>
                        <p>Haklarınızı kullanmak için: destek@dogrudanirade.org</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="document.getElementById('kvkk').checked = true">
                        Kabul Ediyorum
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Karakter sayacı
    document.getElementById('mesaj').addEventListener('input', function() {
        const count = this.value.length;
        document.getElementById('charCount').textContent = count;
        
        if (count < 10) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    // Form doğrulama
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        const mesaj = document.getElementById('mesaj').value;
        const kvkk = document.getElementById('kvkk');
        
        if (mesaj.length < 10) {
            e.preventDefault();
            alert('Mesajınız en az 10 karakter olmalıdır.');
            return;
        }
        
        if (!kvkk.checked) {
            e.preventDefault();
            alert('KVKK politikasını kabul etmelisiniz.');
            return;
        }
    });
    </script>
</body>
</html>
