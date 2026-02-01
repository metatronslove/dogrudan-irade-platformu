<?php
// Bakım modu kontrolü
$bakim_modu = false; // Bu değişkeni config dosyasından kontrol edebilirsiniz

if ($bakim_modu && !isset($_SESSION['yetki_seviye'])) {
    header('HTTP/1.1 503 Service Unavailable');
    include 'hata.php?kod=503';
    exit;
}
?>

<!-- Eğer ayrı bir bakım sayfası isterseniz: -->
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Modu - Doğrudan İrade</title>
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
        .maintenance-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 600px;
            width: 90%;
        }
        .maintenance-icon {
            font-size: 100px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .maintenance-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }
        .maintenance-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .countdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }
        .countdown-title {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }
        .countdown-timer {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            font-family: monospace;
        }
        .progress {
            height: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .contact-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .social-link:hover {
            background: #764ba2;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <!-- İkon -->
        <div class="maintenance-icon">
            🔧
        </div>
        
        <!-- Başlık -->
        <h1 class="maintenance-title">
            SİSTEM BAKIMDA
        </h1>
        
        <!-- Mesaj -->
        <p class="maintenance-message">
            Doğrudan İrade Platformu'nu daha iyi hizmet verebilmek için güncelliyoruz.
            Lütfen biraz sonra tekrar deneyin.
        </p>
        
        <!-- Geri sayım -->
        <div class="countdown">
            <div class="countdown-title">
                Tahmini bitiş süresi:
            </div>
            <div class="countdown-timer" id="countdown">
                02:00:00
            </div>
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                     id="progressBar" style="width: 0%"></div>
            </div>
        </div>
        
        <!-- İletişim bilgileri -->
        <div class="contact-card">
            <h6 class="mb-3">
                <i class="bi bi-info-circle"></i> Bilgilendirme
            </h6>
            <ul class="list-unstyled mb-0">
                <li class="mb-2">
                    <i class="bi bi-clock text-primary"></i>
                    <strong>Başlangıç:</strong> <?= date('d.m.Y H:i') ?>
                </li>
                <li class="mb-2">
                    <i class="bi bi-clock-history text-success"></i>
                    <strong>Tahmini Bitiş:</strong> <?= date('d.m.Y H:i', strtotime('+2 hours')) ?>
                </li>
                <li>
                    <i class="bi bi-envelope text-warning"></i>
                    <strong>İletişim:</strong> destek@dogrudanirade.org
                </li>
            </ul>
        </div>
        
        <!-- Sosyal medya -->
        <div class="social-links">
            <a href="#" class="social-link">
                <i class="bi bi-twitter"></i>
            </a>
            <a href="#" class="social-link">
                <i class="bi bi-facebook"></i>
            </a>
            <a href="#" class="social-link">
                <i class="bi bi-instagram"></i>
            </a>
            <a href="#" class="social-link">
                <i class="bi bi-telegram"></i>
            </a>
        </div>
        
        <!-- Logo -->
        <div class="mt-4">
            <span class="text-muted">
                🗳️ Doğrudan İrade Platformu
            </span>
        </div>
    </div>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script>
    // Geri sayım fonksiyonu
    function startCountdown() {
        const totalSeconds = 2 * 60 * 60; // 2 saat
        let remainingSeconds = totalSeconds;
        
        const countdownElement = document.getElementById('countdown');
        const progressBar = document.getElementById('progressBar');
        
        const timer = setInterval(function() {
            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            
            countdownElement.textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Progress bar güncelleme
            const progress = ((totalSeconds - remainingSeconds) / totalSeconds) * 100;
            progressBar.style.width = `${progress}%`;
            
            if (remainingSeconds <= 0) {
                clearInterval(timer);
                countdownElement.textContent = "Bakım Tamamlandı!";
                countdownElement.classList.add('text-success');
                
                // Sayfayı yenile
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            }
            
            remainingSeconds--;
        }, 1000);
    }
    
    // Sayfa yüklendiğinde geri sayımı başlat
    document.addEventListener('DOMContentLoaded', startCountdown);
    
    // Sayfayı otomatik kontrol et
    setInterval(function() {
        fetch('api.php?action=check_maintenance')
            .then(response => response.json())
            .then(data => {
                if (!data.maintenance) {
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
    }, 30000); // 30 saniyede bir kontrol et
    </script>
</body>
</html>
