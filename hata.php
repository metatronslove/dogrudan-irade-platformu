<?php
$error_code = $_GET['kod'] ?? '404';
$error_messages = [
    '403' => [
        'title' => 'Erişim Engellendi',
        'message' => 'Bu sayfaya erişim izniniz bulunmuyor.',
        'icon' => '🔒'
    ],
    '404' => [
        'title' => 'Sayfa Bulunamadı',
        'message' => 'Aradığınız sayfa mevcut değil veya taşınmış olabilir.',
        'icon' => '🔍'
    ],
    '500' => [
        'title' => 'Sunucu Hatası',
        'message' => 'Sunucuda bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
        'icon' => '⚙️'
    ],
    '503' => [
        'title' => 'Bakım Modu',
        'message' => 'Sistem bakım çalışmaları devam ediyor. Lütfen daha sonra tekrar deneyin.',
        'icon' => '🔧'
    ]
];

$error = $error_messages[$error_code] ?? $error_messages['404'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $error['title'] ?> - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .error-code {
            font-size: 100px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: -20px;
        }
        .error-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        .error-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 30px;
            display: inline-block;
        }
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <!-- Logo -->
        <div class="logo">
            🗳️ Doğrudan İrade
        </div>
        
        <!-- Hata kodu -->
        <div class="error-code">
            <?= $error_code ?>
        </div>
        
        <!-- Hata ikonu -->
        <div class="error-icon">
            <?= $error['icon'] ?>
        </div>
        
        <!-- Hata başlığı -->
        <h1 class="error-title">
            <?= $error['title'] ?>
        </h1>
        
        <!-- Hata mesajı -->
        <p class="error-message">
            <?= $error['message'] ?>
        </p>
        
        <!-- Butonlar -->
        <div class="btn-group">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house-door"></i> Ana Sayfaya Dön
            </a>
            
            <a href="javascript:history.back()" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Geri Dön
            </a>
            
            <a href="oylamalar.php" class="btn btn-outline-success">
                <i class="bi bi-clipboard-data"></i> Oylamalar
            </a>
        </div>
        
        <!-- Ek bilgiler -->
        <div class="contact-info">
            <p class="mb-2">
                Sorun devam ederse lütfen bizimle iletişime geçin:
            </p>
            <p class="mb-0">
                <i class="bi bi-envelope"></i> destek@dogrudanirade.org
            </p>
        </div>
        
        <!-- Teknik bilgiler (sadece geliştirici modunda) -->
        <?php if (isset($_SESSION['yetki_seviye']) && $_SESSION['yetki_seviye'] === 'superadmin'): ?>
            <div class="mt-4 p-3 bg-light rounded">
                <small class="text-muted">
                    <strong>Teknik Bilgiler:</strong><br>
                    Hata Kodu: <?= $error_code ?><br>
                    URL: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?><br>
                    IP: <?= $_SERVER['REMOTE_ADDR'] ?><br>
                    Zaman: <?= date('d.m.Y H:i:s') ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script>
    // Otomatik yönlendirme (sadece 404 için)
    <?php if ($error_code == '404'): ?>
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 10000); // 10 saniye sonra
    <?php endif; ?>
    </script>
</body>
</html>
