<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

$db = new Database();
$error = '';
$success = '';
$show_form = true;

// Token kontrolü
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    // Token doğrulama
    $stmt = $db->query(
        "SELECT kullanici_id, son_kullanma FROM sifre_sifirlama_tokenlari 
         WHERE token = ? AND son_kullanma > NOW()",
        [$token]
    );
    $token_data = $stmt->fetch();
    
    if (!$token_data) {
        $error = 'Geçersiz veya süresi dolmuş şifre sıfırlama bağlantısı.';
        $show_form = false;
    } else {
        $user_id = $token_data['kullanici_id'];
        
        // Şifre değiştirme formu göster
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeni_sifre'])) {
            $yeni_sifre = $_POST['yeni_sifre'];
            $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'];
            
            if (strlen($yeni_sifre) < 6) {
                $error = 'Şifre en az 6 karakter olmalıdır.';
            } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
                $error = 'Şifreler eşleşmiyor.';
            } else {
                try {
                    // Şifreyi güncelle
                    $sifre_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                    
                    $db->query(
                        "UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?",
                        [$sifre_hash, $user_id]
                    );
                    
                    // Token'ı sil
                    $db->query(
                        "DELETE FROM sifre_sifirlama_tokenlari WHERE token = ?",
                        [$token]
                    );
                    
                    // Log kaydı
                    logIslem($user_id, 'sifre_sifirlama', 'Şifre başarıyla sıfırlandı', $_SERVER['REMOTE_ADDR']);
                    
                    $success = 'Şifreniz başarıyla değiştirildi. Giriş yapabilirsiniz.';
                    $show_form = false;
                    
                } catch (Exception $e) {
                    $error = 'Şifre değiştirme sırasında bir hata oluştu: ' . $e->getMessage();
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eposta'])) {
    // Şifre sıfırlama isteği
    $eposta = trim($_POST['eposta']);
    
    // Kullanıcıyı bul
    $stmt = $db->query(
        "SELECT id, ad_soyad FROM kullanicilar WHERE eposta = ? AND durum = 'aktif'",
        [$eposta]
    );
    $user = $stmt->fetch();
    
    if ($user) {
        // Token oluştur
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Eski tokenları temizle
        $db->query(
            "DELETE FROM sifre_sifirlama_tokenlari WHERE kullanici_id = ? OR son_kullanma < NOW()",
            [$user['id']]
        );
        
        // Yeni token ekle
        $db->query(
            "INSERT INTO sifre_sifirlama_tokenlari (kullanici_id, token, son_kullanma) 
             VALUES (?, ?, ?)",
            [$user['id'], $token, $expires]
        );
        
        // E-posta gönder (simülasyon)
        $reset_link = getBaseUrl() . "/sifre_sifirla.php?token=$token";
        
        $subject = "Doğrudan İrade - Şifre Sıfırlama";
        $message = "
        <h2>Doğrudan İrade Platformu</h2>
        <p>Merhaba {$user['ad_soyad']},</p>
        <p>Şifre sıfırlama isteğiniz alındı. Aşağıdaki bağlantıya tıklayarak yeni şifrenizi belirleyebilirsiniz:</p>
        <p><a href='$reset_link' style='background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block;'>
            Şifremi Sıfırla
        </a></p>
        <p><small>Bu bağlantı 1 saat geçerlidir.</small></p>
        <p>Eğer bu isteği siz yapmadıysanız, bu e-postayı dikkate almayınız.</p>
        <hr>
        <p><small>Doğrudan İrade Platformu<br>Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!</small></p>
        ";
        
        // E-posta gönderme (gerçek uygulamada aktif edin)
        // sendEmail($eposta, $subject, $message);
        
        // Log
        logIslem($user['id'], 'sifre_sifirlama_istegi', 'Şifre sıfırlama e-postası gönderildi', $_SERVER['REMOTE_ADDR']);
        
        $success = "Şifre sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen e-postanızı kontrol edin.";
        $show_form = false;
        
    } else {
        $error = 'Bu e-posta adresi ile kayıtlı aktif bir kullanıcı bulunamadı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .password-strength {
            height: 5px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .strength-0 { width: 0%; background: #dc3545; }
        .strength-1 { width: 25%; background: #dc3545; }
        .strength-2 { width: 50%; background: #ffc107; }
        .strength-3 { width: 75%; background: #28a745; }
        .strength-4 { width: 100%; background: #28a745; }
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
        }
        .requirement {
            margin-bottom: 3px;
        }
        .requirement.met {
            color: #28a745;
        }
        .requirement.unmet {
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <!-- Logo -->
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold text-primary">
                        <i class="bi bi-shield-lock"></i> Doğrudan İrade
                    </h1>
                    <p class="text-muted">Şifre Sıfırlama</p>
                </div>
                
                <!-- Şifre Sıfırlama Kartı -->
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <?= empty($token) ? '🔑 Şifremi Unuttum' : '🔄 Yeni Şifre Belirle' ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle"></i> <?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                <div class="mt-3">
                                    <a href="giris.php" class="btn btn-success">
                                        <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                                    </a>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-house"></i> Ana Sayfa
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($show_form): ?>
                            <?php if (empty($token)): ?>
                                <!-- E-posta istek formu -->
                                <form method="POST" action="">
                                    <div class="mb-4">
                                        <p class="text-muted">
                                            Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi girin.
                                            Size şifre sıfırlama bağlantısı göndereceğiz.
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="eposta" class="form-label">E-posta Adresiniz</label>
                                        <input type="email" class="form-control form-control-lg" 
                                               id="eposta" name="eposta" required
                                               placeholder="ornek@email.com">
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-send"></i> Şifre Sıfırlama Bağlantısı Gönder
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Yeni şifre formu -->
                                <form method="POST" action="" id="passwordForm">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                    
                                    <div class="mb-4">
                                        <p class="text-muted">
                                            Lütfen yeni şifrenizi belirleyin.
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                                        <input type="password" class="form-control form-control-lg" 
                                               id="yeni_sifre" name="yeni_sifre" required
                                               minlength="6">
                                        <div class="password-strength" id="passwordStrength"></div>
                                        
                                        <div class="password-requirements mt-2">
                                            <div class="requirement" id="reqLength">
                                                <i class="bi bi-circle"></i> En az 6 karakter
                                            </div>
                                            <div class="requirement" id="reqUpper">
                                                <i class="bi bi-circle"></i> En az 1 büyük harf
                                            </div>
                                            <div class="requirement" id="reqLower">
                                                <i class="bi bi-circle"></i> En az 1 küçük harf
                                            </div>
                                            <div class="requirement" id="reqNumber">
                                                <i class="bi bi-circle"></i> En az 1 rakam
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre (Tekrar)</label>
                                        <input type="password" class="form-control form-control-lg" 
                                               id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required>
                                        <div class="invalid-feedback" id="passwordMatchError">
                                            Şifreler eşleşmiyor.
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="bi bi-check-circle"></i> Şifremi Değiştir
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Yardım ve bağlantılar -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="text-center">
                                <a href="giris.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                                </a>
                                <a href="kayit.php" class="btn btn-outline-primary ms-2">
                                    <i class="bi bi-person-plus"></i> Yeni Hesap
                                </a>
                                <a href="index.php" class="btn btn-outline-dark ms-2">
                                    <i class="bi bi-house"></i> Ana Sayfa
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Güvenlik bilgisi -->
                <div class="card mt-4 border-info">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-shield-check text-info"></i> GÜVENLİK BİLGİSİ
                        </h6>
                        <ul class="small mb-0">
                            <li>Şifre sıfırlama bağlantısı 1 saat geçerlidir</li>
                            <li>Bağlantıyı sadece siz kullanabilirsiniz</li>
                            <li>Şifrenizi kimseyle paylaşmayın</li>
                            <li>Şüpheli durumlarda destek ekibiyle iletişime geçin</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script>
    // Şifre güçlülük kontrolü
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('yeni_sifre');
        const confirmInput = document.getElementById('yeni_sifre_tekrar');
        const strengthBar = document.getElementById('passwordStrength');
        const submitBtn = document.getElementById('submitBtn');
        
        // Şifre gereksinimleri elementleri
        const reqLength = document.getElementById('reqLength');
        const reqUpper = document.getElementById('reqUpper');
        const reqLower = document.getElementById('reqLower');
        const reqNumber = document.getElementById('reqNumber');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Uzunluk kontrolü
                if (password.length >= 6) {
                    strength++;
                    reqLength.classList.remove('unmet');
                    reqLength.classList.add('met');
                    reqLength.innerHTML = '<i class="bi bi-check-circle"></i> En az 6 karakter';
                } else {
                    reqLength.classList.remove('met');
                    reqLength.classList.add('unmet');
                    reqLength.innerHTML = '<i class="bi bi-circle"></i> En az 6 karakter';
                }
                
                // Büyük harf kontrolü
                if (/[A-Z]/.test(password)) {
                    strength++;
                    reqUpper.classList.remove('unmet');
                    reqUpper.classList.add('met');
                    reqUpper.innerHTML = '<i class="bi bi-check-circle"></i> En az 1 büyük harf';
                } else {
                    reqUpper.classList.remove('met');
                    reqUpper.classList.add('unmet');
                    reqUpper.innerHTML = '<i class="bi bi-circle"></i> En az 1 büyük harf';
                }
                
                // Küçük harf kontrolü
                if (/[a-z]/.test(password)) {
                    strength++;
                    reqLower.classList.remove('unmet');
                    reqLower.classList.add('met');
                    reqLower.innerHTML = '<i class="bi bi-check-circle"></i> En az 1 küçük harf';
                } else {
                    reqLower.classList.remove('met');
                    reqLower.classList.add('unmet');
                    reqLower.innerHTML = '<i class="bi bi-circle"></i> En az 1 küçük harf';
                }
                
                // Rakam kontrolü
                if (/[0-9]/.test(password)) {
                    strength++;
                    reqNumber.classList.remove('unmet');
                    reqNumber.classList.add('met');
                    reqNumber.innerHTML = '<i class="bi bi-check-circle"></i> En az 1 rakam';
                } else {
                    reqNumber.classList.remove('met');
                    reqNumber.classList.add('unmet');
                    reqNumber.innerHTML = '<i class="bi bi-circle"></i> En az 1 rakam';
                }
                
                // Şifre güçlülüğü göster
                strengthBar.className = 'password-strength strength-' + strength;
                
                // Butonu aktif/pasif yap
                updateSubmitButton();
            });
            
            // Şifre eşleşme kontrolü
            if (confirmInput) {
                confirmInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const confirm = this.value;
                    
                    if (confirm.length > 0 && password !== confirm) {
                        this.classList.add('is-invalid');
                        document.getElementById('passwordMatchError').style.display = 'block';
                    } else {
                        this.classList.remove('is-invalid');
                        document.getElementById('passwordMatchError').style.display = 'none';
                    }
                    
                    updateSubmitButton();
                });
            }
            
            // Form gönderim kontrolü
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Şifre en az 6 karakter olmalıdır.');
                    return;
                }
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Şifreler eşleşmiyor.');
                    return;
                }
                
                // Minimum güçlülük kontrolü
                const hasUpper = /[A-Z]/.test(password);
                const hasLower = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                
                if (!hasUpper || !hasLower || !hasNumber) {
                    if (!confirm('Şifreniz yeterince güçlü değil. Devam etmek istiyor musunuz?')) {
                        e.preventDefault();
                    }
                }
            });
        }
        
        function updateSubmitButton() {
            if (!submitBtn) return;
            
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            // Temel kontroller
            const isValid = password.length >= 6 && password === confirm;
            
            submitBtn.disabled = !isValid;
            submitBtn.classList.toggle('disabled', !isValid);
        }
    });
    </script>
</body>
</html>
