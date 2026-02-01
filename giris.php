<?php
session_start();
require_once 'config/database.php';

$db = new Database();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    
    // Kullanıcıyı bul
    $stmt = $db->query(
        "SELECT * FROM kullanicilar WHERE eposta = ? AND durum = 'aktif'",
        [$eposta]
    );
    $kullanici = $stmt->fetch();
    
    if ($kullanici && password_verify($sifre, $kullanici['sifre_hash'])) {
        // Giriş başarılı
        $_SESSION['kullanici_id'] = $kullanici['id'];
        $_SESSION['ad_soyad'] = $kullanici['ad_soyad'];
        $_SESSION['eposta'] = $kullanici['eposta'];
        $_SESSION['yetki_seviye'] = $kullanici['yetki_seviye'];
        
        // Son giriş tarihini güncelle
        $db->query(
            "UPDATE kullanicilar SET son_giris_tarihi = NOW() WHERE id = ?",
            [$kullanici['id']]
        );
        
        // Log kaydı
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
             VALUES (?, 'giris_basarili', 'Kullanıcı giriş yaptı', ?)",
            [$kullanici['id'], $_SERVER['REMOTE_ADDR']]
        );
        
        // Yönlendirme
        if ($kullanici['yetki_seviye'] === 'superadmin' || $kullanici['yetki_seviye'] === 'yonetici') {
            header("Location: admin/index.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = 'E-posta veya şifre hatalı!';
        
        // Başarısız giriş logu
        if ($kullanici) {
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'giris_basarisiz', 'Yanlış şifre denemesi', ?)",
                [$kullanici['id'], $_SERVER['REMOTE_ADDR']]
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <!-- Logo ve başlık -->
                <div class="text-center mb-5">
                    <h1 class="display-6 fw-bold text-primary">DOĞRUDAN İRADE</h1>
                    <p class="text-muted">Doğrudan demokrasi platformuna hoş geldiniz</p>
                </div>
                
                <!-- Giriş formu -->
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h3 class="card-title text-center mb-4">🔐 Giriş Yap</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="eposta" class="form-label">E-posta Adresiniz</label>
                                <input type="email" class="form-control form-control-lg" 
                                       id="eposta" name="eposta" required
                                       placeholder="ornek@email.com">
                            </div>
                            
                            <div class="mb-3">
                                <label for="sifre" class="form-label">Şifreniz</label>
                                <input type="password" class="form-control form-control-lg" 
                                       id="sifre" name="sifre" required
                                       placeholder="••••••••">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-2">Hesabınız yok mu?</p>
                            <a href="kayit.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus"></i> Yeni Hesap Oluştur
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Platform bilgisi -->
                <div class="card mt-4 border-info">
                    <div class="card-body text-center">
                        <small class="text-muted">
                            <i class="bi bi-shield-check"></i> Doğrudan İrade Platformu<br>
                            Tüm oylamalar şeffaf ve güvenlidir.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
