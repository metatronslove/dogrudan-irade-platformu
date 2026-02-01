<?php
session_start();
require_once 'config/database.php';
require_once 'config/secim_fonksiyonlari.php';
require_once 'includes/auth.php';

$db = new Database();
$secim = new SecimFonksiyonlari();

$oylama_id = $_GET['id'] ?? 0;
$kullanici_id = $_SESSION['kullanici_id'] ?? 0;

// Oylama bilgilerini al
$oylama = $db->query(
    "SELECT * FROM oylamalar WHERE id = ?",
    [$oylama_id]
)->fetch();

if (!$oylama) {
    header("Location: index.php");
    exit;
}

// Adayları al
$adaylar = $db->query(
    "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
    [$oylama_id]
)->fetchAll();

// Kullanıcının mevcut oylarını al
$kullaniciOylari = $secim->kullaniciOyDurumu($oylama_id, $kullanici_id);

// AJAX isteği için oy verme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['kullanici_id'])) {
        echo json_encode(['success' => false, 'message' => 'Giriş yapmalısınız']);
        exit;
    }

    $action = $_POST['action'];
    $aday_id = $_POST['aday_id'] ?? 0;

    if ($action === 'destek') {
        $result = $secim->destekOyVer($oylama_id, $kullanici_id, $aday_id);
        echo json_encode(['success' => $result]);
    } elseif ($action === 'negatif') {
        $result = $secim->negatifOyToggle($oylama_id, $kullanici_id, $aday_id);
        echo json_encode(['success' => $result]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($oylama['baslik']) ?> - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .candidate-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .candidate-card.selected-support {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }
        .candidate-card.selected-negative {
            border-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
        }
        .btn-support {
            width: 120px;
        }
        .btn-negative {
            width: 120px;
        }
        .vote-summary {
            position: sticky;
            top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row">
            <!-- Ana içerik -->
            <div class="col-lg-8">
                <!-- Oylama başlığı -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h1 class="card-title h2 mb-3"><?= htmlspecialchars($oylama['baslik']) ?></h1>
                                <p class="card-text text-muted"><?= htmlspecialchars($oylama['aciklama']) ?></p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary fs-6"><?= $oylama['tur'] ?></span>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> Başlangıç: 
                                    <?= date('d.m.Y H:i', strtotime($oylama['baslangic_tarihi'])) ?>
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> Bitiş: 
                                    <?= date('d.m.Y H:i', strtotime($oylama['bitis_tarihi'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kullanıcının seçimleri (Anlık geri bildirim) -->
                <div class="alert alert-info mb-4" id="currentVotesAlert">
                    <h5 class="alert-heading">📋 ŞU ANKİ SEÇİMLERİNİZ:</h5>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="mb-1">
                                <strong>✅ Desteklediğiniz Aday:</strong>
                                <span id="currentSupport">
                                    <?= $kullaniciOylari['destek_oy']['aday_adi'] ?? 'Henüz destek oyu vermediniz' ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1">
                                <strong>❌ Negatif Oy Verdiğiniz Adaylar:</strong>
                                <span id="currentNegatives">
                                    <?php if (!empty($kullaniciOylari['negatif_oylar'])): ?>
                                        <?= implode(', ', array_column($kullaniciOylari['negatif_oylar'], 'aday_adi')) ?>
                                    <?php else: ?>
                                        Henüz negatif oy vermediniz
                                    <?php endif; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Aday listesi -->
                <h3 class="mb-4">🗳️ ADAY LİSTESİ</h3>
                
                <?php if (empty($adaylar)): ?>
                    <div class="alert alert-warning">
                        Bu oylama için henüz aday eklenmemiş.
                    </div>
                <?php else: ?>
                    <?php foreach ($adaylar as $aday): 
                        $isSupported = isset($kullaniciOylari['destek_oy']['id']) && $kullaniciOylari['destek_oy']['id'] == $aday['id'];
                        $isNegative = false;
                        foreach ($kullaniciOylari['negatif_oylar'] as $negatif) {
                            if ($negatif['id'] == $aday['id']) {
                                $isNegative = true;
                                break;
                            }
                        }
                    ?>
                        <div class="card candidate-card mb-3 
                            <?= $isSupported ? 'selected-support' : '' ?>
                            <?= $isNegative ? 'selected-negative' : '' ?>"
                            id="candidate-<?= $aday['id'] ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="card-title mb-2"><?= htmlspecialchars($aday['aday_adi']) ?></h5>
                                        <?php if ($aday['aday_aciklama']): ?>
                                            <p class="card-text text-muted"><?= htmlspecialchars($aday['aday_aciklama']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-grid gap-2 d-md-block">
                                            <!-- Destek oyu butonu -->
                                            <button class="btn btn-support 
                                                <?= $isSupported ? 'btn-success' : 'btn-outline-success' ?> 
                                                mb-2 mb-md-0"
                                                onclick="vote(<?= $aday['id'] ?>, 'destek')"
                                                id="support-btn-<?= $aday['id'] ?>">
                                                <?php if ($isSupported): ?>
                                                    ✅ Destekliyorsunuz
                                                <?php else: ?>
                                                    👍 Destek Oyu Ver
                                                <?php endif; ?>
                                            </button>
                                            
                                            <!-- Negatif oy butonu -->
                                            <button class="btn btn-negative 
                                                <?= $isNegative ? 'btn-danger' : 'btn-outline-danger' ?>"
                                                onclick="vote(<?= $aday['id'] ?>, 'negatif')"
                                                id="negative-btn-<?= $aday['id'] ?>">
                                                <?php if ($isNegative): ?>
                                                    ❌ Negatif Oy (Kaldır)
                                                <?php else: ?>
                                                    👎 Negatif Oy Ver
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sağ sidebar - Bilgi paneli -->
            <div class="col-lg-4">
                <div class="vote-summary">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">ℹ️ OY KULLANMA KILAVUZU</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6>✅ Destek Oyu Nedir?</h6>
                                <p class="small">Bir adayı aktif olarak desteklemek için kullanılır. SADECE BİR adaya destek oyu verebilirsiniz.</p>
                            </div>
                            <div class="mb-3">
                                <h6>❌ Negatif Oy Nedir?</h6>
                                <p class="small">Kabul edemediğiniz adaylara karşı kullanılır. İSTEDİĞİNİZ KADAR adaya negatif oy verebilirsiniz.</p>
                            </div>
                            <div class="mb-3">
                                <h6>📊 Net Skor Nasıl Hesaplanır?</h6>
                                <p class="small">NET SKOR = Destek Oyu - Negatif Oy</p>
                                <p class="small">Net skoru en yüksek olan aday kazanır.</p>
                            </div>
                            <hr>
                            <div class="text-center">
                                <a href="sonuc.php?id=<?= $oylama_id ?>" class="btn btn-outline-primary w-100">
                                    📈 ŞU ANKİ SONUÇLARI GÖR
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function vote(adayId, type) {
        if (!confirm(`${type === 'destek' ? 'Destek' : 'Negatif'} oyunuzu güncellemek istediğinize emin misiniz?`)) {
            return;
        }

        fetch('oylama_detay.php?id=<?= $oylama_id ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${type}&aday_id=${adayId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Sayfayı yenile
            } else {
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
        });
    }

    // Oylama bitiş süresi kontrolü
    <?php if (strtotime($oylama['bitis_tarihi']) < time()): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('button[onclick^="vote"]').forEach(btn => {
                btn.disabled = true;
                btn.classList.add('disabled');
            });
            alert('Bu oylamanın süresi dolmuştur. Artık oy kullanamazsınız.');
        });
    <?php endif; ?>
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
