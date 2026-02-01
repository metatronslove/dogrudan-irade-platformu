<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();
$errors = [];
$success = '';

// Türkiye illeri
$iller = [
    '01' => 'Adana', '02' => 'Adıyaman', '03' => 'Afyonkarahisar', '04' => 'Ağrı',
    '05' => 'Amasya', '06' => 'Ankara', '07' => 'Antalya', '08' => 'Artvin',
    '09' => 'Aydın', '10' => 'Balıkesir', '11' => 'Bilecik', '12' => 'Bingöl',
    '13' => 'Bitlis', '14' => 'Bolu', '15' => 'Burdur', '16' => 'Bursa',
    '17' => 'Çanakkale', '18' => 'Çankırı', '19' => 'Çorum', '20' => 'Denizli',
    '21' => 'Diyarbakır', '22' => 'Edirne', '23' => 'Elazığ', '24' => 'Erzincan',
    '25' => 'Erzurum', '26' => 'Eskişehir', '27' => 'Gaziantep', '28' => 'Giresun',
    '29' => 'Gümüşhane', '30' => 'Hakkari', '31' => 'Hatay', '32' => 'Isparta',
    '33' => 'Mersin', '34' => 'İstanbul', '35' => 'İzmir', '36' => 'Kars',
    '37' => 'Kastamonu', '38' => 'Kayseri', '39' => 'Kırklareli', '40' => 'Kırşehir',
    '41' => 'Kocaeli', '42' => 'Konya', '43' => 'Kütahya', '44' => 'Malatya',
    '45' => 'Manisa', '46' => 'Kahramanmaraş', '47' => 'Mardin', '48' => 'Muğla',
    '49' => 'Muş', '50' => 'Nevşehir', '51' => 'Niğde', '52' => 'Ordu',
    '53' => 'Rize', '54' => 'Sakarya', '55' => 'Samsun', '56' => 'Siirt',
    '57' => 'Sinop', '58' => 'Sivas', '59' => 'Tekirdağ', '60' => 'Tokat',
    '61' => 'Trabzon', '62' => 'Tunceli', '63' => 'Şanlıurfa', '64' => 'Uşak',
    '65' => 'Van', '66' => 'Yozgat', '67' => 'Zonguldak', '68' => 'Aksaray',
    '69' => 'Bayburt', '70' => 'Karaman', '71' => 'Kırıkkale', '72' => 'Batman',
    '73' => 'Şırnak', '74' => 'Bartın', '75' => 'Ardahan', '76' => 'Iğdır',
    '77' => 'Yalova', '78' => 'Karabük', '79' => 'Kilis', '80' => 'Osmaniye',
    '81' => 'Düzce'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad']);
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];
    $tc_kimlik = trim($_POST['tc_kimlik'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $dogum_tarihi = $_POST['dogum_tarihi'] ?? '';
    $secilen_iller = $_POST['iller'] ?? [];

    // Validasyon
    if (empty($ad_soyad)) $errors[] = 'Ad soyad gereklidir';
    if (empty($eposta) || !filter_var($eposta, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta adresi girin';
    if (strlen($sifre) < 6) $errors[] = 'Şifre en az 6 karakter olmalı';
    if ($sifre !== $sifre_tekrar) $errors[] = 'Şifreler eşleşmiyor';
    
    if (!empty($tc_kimlik) && !validateTCKN($tc_kimlik)) {
        $errors[] = 'Geçersiz TC Kimlik numarası';
    }
    
    // E-posta kontrolü
    $existing = $db->singleValueQuery(
        "SELECT COUNT(*) FROM kullanicilar WHERE eposta = ?",
        [$eposta]
    );
    if ($existing > 0) $errors[] = 'Bu e-posta zaten kayıtlı';

    // TC kontrolü
    if (!empty($tc_kimlik)) {
        $existing_tc = $db->singleValueQuery(
            "SELECT COUNT(*) FROM kullanicilar WHERE tc_kimlik = ?",
            [$tc_kimlik]
        );
        if ($existing_tc > 0) $errors[] = 'Bu TC Kimlik numarası zaten kayıtlı';
    }

    if (empty($errors)) {
        try {
            // Kullanıcıyı kaydet
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            $tc_hash = !empty($tc_kimlik) ? hash('sha256', $tc_kimlik . 'SALT_KEY') : null;
            
            $kullanici_id = $db->insertAndGetId(
                "INSERT INTO kullanicilar (eposta, sifre_hash, ad_soyad, tc_kimlik, telefon, dogum_tarihi) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$eposta, $sifre_hash, $ad_soyad, $tc_hash, $telefon, $dogum_tarihi]
            );

            // Seçilen illeri topluluk olarak ekle
            foreach ($secilen_iller as $il_kodu) {
                if (isset($iller[$il_kodu])) {
                    $db->query(
                        "INSERT INTO kullanici_topluluklari (kullanici_id, topluluk_tipi, topluluk_id) 
                         VALUES (?, 'il', ?)",
                        [$kullanici_id, $il_kodu]
                    );
                }
            }

            // Log kaydı
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'yeni_kayit', ?, ?)",
                [$kullanici_id, "$ad_soyad kayıt oldu", $_SERVER['REMOTE_ADDR']]
            );

            // Otomatik giriş yap
            $_SESSION['kullanici_id'] = $kullanici_id;
            $_SESSION['ad_soyad'] = $ad_soyad;
            $_SESSION['eposta'] = $eposta;
            $_SESSION['yetki_seviye'] = 'kullanici';

            $success = 'Kayıt başarılı! Yönlendiriliyorsunuz...';
            
            // 2 saniye sonra yönlendir
            header("refresh:2;url=index.php");
            
        } catch (Exception $e) {
            $errors[] = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}

function validateTCKN($tckn) {
    if (strlen($tckn) != 11) return false;
    if ($tckn[0] == '0') return false;
    
    $odd = $tckn[0] + $tckn[2] + $tckn[4] + $tckn[6] + $tckn[8];
    $even = $tckn[1] + $tckn[3] + $tckn[5] + $tckn[7];
    
    if ((($odd * 7) - $even) % 10 != $tckn[9]) return false;
    
    $total = 0;
    for ($i = 0; $i < 10; $i++) {
        $total += $tckn[$i];
    }
    
    return $total % 10 == $tckn[10];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .multi-select {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }
        .form-check {
            margin-bottom: 5px;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Başlık -->
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold text-primary">DOĞRUDAN İRADE PLATFORMU</h1>
                    <p class="lead">Doğrudan demokrasiye katıl, söz sahibi ol!</p>
                </div>

                <!-- Kayıt formu -->
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-person-plus-fill"></i> YENİ HESAP OLUŞTUR
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <h5 class="alert-heading">✅ Kayıt Başarılı!</h5>
                                <?= $success ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <h5 class="alert-heading">⚠️ Hatalar:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <!-- Temel bilgiler -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2 mb-3">📋 Temel Bilgiler</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="ad_soyad" class="form-label required">Ad Soyad</label>
                                    <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" 
                                           value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '') ?>" 
                                           required minlength="3">
                                    <div class="invalid-feedback">Lütfen geçerli bir ad soyad girin (en az 3 karakter)</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="eposta" class="form-label required">E-posta Adresi</label>
                                    <input type="email" class="form-control" id="eposta" name="eposta" 
                                           value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="sifre" class="form-label required">Şifre</label>
                                    <input type="password" class="form-control" id="sifre" name="sifre" 
                                           required minlength="6">
                                    <div class="invalid-feedback">Şifre en az 6 karakter olmalı</div>
                                    <small class="text-muted">En az 6 karakter</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="sifre_tekrar" class="form-label required">Şifre Tekrar</label>
                                    <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar" required>
                                    <div class="invalid-feedback">Şifreler eşleşmiyor</div>
                                </div>
                            </div>

                            <!-- Ek bilgiler -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2 mb-3">📝 Ek Bilgiler (İsteğe Bağlı)</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="tc_kimlik" class="form-label">TC Kimlik No</label>
                                    <input type="text" class="form-control" id="tc_kimlik" name="tc_kimlik" 
                                           value="<?= htmlspecialchars($_POST['tc_kimlik'] ?? '') ?>" 
                                           pattern="[0-9]{11}" maxlength="11">
                                    <div class="invalid-feedback">11 haneli TC Kimlik numarası girin</div>
                                    <small class="text-muted">Güvenliğiniz için şifrelenerek saklanacaktır</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="telefon" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="telefon" name="telefon" 
                                           value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>"
                                           pattern="[0-9]{10,11}">
                                    <div class="invalid-feedback">Geçerli bir telefon numarası girin</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
                                    <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi" 
                                           value="<?= htmlspecialchars($_POST['dogum_tarihi'] ?? '') ?>"
                                           max="<?= date('Y-m-d') ?>">
                                </div>
                            </div>

                            <!-- Topluluk seçimi (İller) -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2 mb-3">🏙️ Üye Olmak İstediğiniz İller</h5>
                                    <p class="text-muted mb-3">
                                        Seçtiğiniz illerdeki oylamalarda oy kullanabilirsiniz.
                                        Birden fazla il seçebilirsiniz (Ctrl/Cmd tuşu ile).
                                    </p>
                                    
                                    <div class="multi-select">
                                        <div class="row">
                                            <?php 
                                            $chunks = array_chunk($iller, ceil(count($iller) / 3), true);
                                            foreach ($chunks as $chunk):
                                            ?>
                                                <div class="col-md-4">
                                                    <?php foreach ($chunk as $kod => $il): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   name="iller[]" value="<?= $kod ?>" 
                                                                   id="il_<?= $kod ?>"
                                                                   <?= isset($_POST['iller']) && in_array($kod, $_POST['iller']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="il_<?= $kod ?>">
                                                                <?= $il ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onay ve kayıt -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="sozlesme" required>
                                        <label class="form-check-label" for="sozlesme">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">
                                                Kullanım Sözleşmesi'ni
                                            </a> okudum ve kabul ediyorum
                                        </label>
                                        <div class="invalid-feedback">Kullanım sözleşmesini kabul etmelisiniz</div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-person-plus-fill"></i> HESAP OLUŞTUR
                                        </button>
                                        <a href="giris.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-box-arrow-in-right"></i> Zaten Hesabım Var
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bilgi kartı -->
                <div class="card mt-4 border-success">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-shield-check text-success"></i> GÜVENLİK VE GİZLİLİK
                        </h6>
                        <ul class="small mb-0">
                            <li>TC Kimlik numaranız şifrelenerek saklanır</li>
                            <li>Kişisel verileriniz 3. şahıslarla paylaşılmaz</li>
                            <li>Oylarınız anonim olarak istatistiklerde kullanılır</li>
                            <li>Platform %100 şeffaf ve denetime açıktır</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kullanım Sözleşmesi Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">KULLANIM SÖZLEŞMESİ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <h6>1. GENEL HÜKÜMLER</h6>
                        <p>Doğrudan İrade Platformu, kullanıcılarına demokratik katılım imkanı sunan bir dijital platformdur. Bu sözleşme, platformu kullanan tüm kullanıcılar için geçerlidir.</p>
                        
                        <h6>2. KULLANICI HAK VE YÜKÜMLÜLÜKLERİ</h6>
                        <p>2.1. Kullanıcılar, gerçek ve doğru bilgilerle kayıt olmalıdır.<br>
                        2.2. Her kullanıcı tek bir hesap açabilir.<br>
                        2.3. Oy kullanırken dürüst ve şeffaf davranılmalıdır.</p>
                        
                        <h6>3. PLATFORM KURALLARI</h6>
                        <p>3.1. Manipülatif oy kullanımı yasaktır.<br>
                        3.2. Diğer kullanıcıların haklarına saygı gösterilmelidir.<br>
                        3.3. Platformun işleyişini bozacak davranışlarda bulunulamaz.</p>
                        
                        <h6>4. VERİ GÜVENLİĞİ</h6>
                        <p>4.1. Kişisel veriler 6698 sayılı Kanun'a uygun işlenir.<br>
                        4.2. Veriler şifrelenerek saklanır.<br>
                        4.3. Veriler üçüncü şahıslarla paylaşılmaz.</p>
                        
                        <h6>5. SORUMLULUK SINIRLARI</h6>
                        <p>Platform, teknik arızalardan doğan sorunlardan sorumlu değildir. Kullanıcılar kendi hesaplarının güvenliğinden sorumludur.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="document.getElementById('sozlesme').checked = true">
                        Kabul Ediyorum
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function() {
        'use strict';
        
        // Şifre eşleşme kontrolü
        var sifre = document.getElementById('sifre');
        var sifreTekrar = document.getElementById('sifre_tekrar');
        
        function validatePassword() {
            if (sifre.value !== sifreTekrar.value) {
                sifreTekrar.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                sifreTekrar.setCustomValidity('');
            }
        }
        
        sifre.onchange = validatePassword;
        sifreTekrar.onkeyup = validatePassword;
        
        // Form submit kontrolü
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
    
    // TC Kimlik format kontrolü
    document.getElementById('tc_kimlik').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 11) {
            this.value = this.value.slice(0, 11);
        }
    });
    
    // Telefon format kontrolü
    document.getElementById('telefon').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    </script>
</body>
</html>
