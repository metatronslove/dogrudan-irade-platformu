<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

$db = new Database();

// Sadece yöneticiler oylama oluşturabilir
if (!isset($_SESSION['kullanici_id']) || $_SESSION['yetki_seviye'] !== 'superadmin') {
    header("Location: giris.php");
    exit;
}

$errors = [];
$success = '';

// İller listesi (kayıt.php'den al)
$iller = [
    '01' => 'Adana', '02' => 'Adıyaman', '03' => 'Afyonkarahisar', '04' => 'Ağrı',
    // ... tüm iller
    '81' => 'Düzce'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tur = $_POST['tur'];
    $baslik = trim($_POST['baslik']);
    $aciklama = trim($_POST['aciklama']);
    $topluluk_tipi = $_POST['topluluk_tipi'];
    $topluluk_id = $_POST['topluluk_id'] ?? null;
    $bitis_tarihi = $_POST['bitis_tarihi'];
    $adaylar = $_POST['aday'] ?? [];
    $aday_aciklamalar = $_POST['aday_aciklama'] ?? [];
    
    // Validasyon
    if (empty($baslik)) $errors[] = 'Oylama başlığı gereklidir';
    if (strlen($baslik) < 10) $errors[] = 'Başlık en az 10 karakter olmalı';
    if (empty($bitis_tarihi) || strtotime($bitis_tarihi) <= time()) {
        $errors[] = 'Geçerli bir bitiş tarihi girin';
    }
    
    if ($tur === 'secim' && empty($adaylar)) {
        $errors[] = 'En az bir aday eklemelisiniz';
    }
    
    if (empty($errors)) {
        try {
            // Transaction başlat
            $db->connect()->beginTransaction();
            
            // Oylamayı oluştur
            $oylama_id = $db->insertAndGetId(
                "INSERT INTO oylamalar (tur, baslik, aciklama, olusturan_id, 
                 topluluk_tipi, topluluk_id, bitis_tarihi) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$tur, $baslik, $aciklama, $_SESSION['kullanici_id'], 
                 $topluluk_tipi, $topluluk_id ?: null, $bitis_tarihi]
            );
            
            // Adayları ekle (seçim için)
            if ($tur === 'secim') {
                foreach ($adaylar as $index => $aday_adi) {
                    if (!empty(trim($aday_adi))) {
                        $db->query(
                            "INSERT INTO adaylar (oylama_id, aday_adi, aday_aciklama) 
                             VALUES (?, ?, ?)",
                            [$oylama_id, trim($aday_adi), 
                             trim($aday_aciklamalar[$index] ?? '')]
                        );
                    }
                }
            }
            
            // Referandum seçenekleri
            if ($tur === 'referandum') {
                $evet_id = $db->insertAndGetId(
                    "INSERT INTO oylama_secenekleri (oylama_id, secenek_metni, tur) 
                     VALUES (?, 'Evet', 'evet')",
                    [$oylama_id]
                );
                
                $hayir_id = $db->insertAndGetId(
                    "INSERT INTO oylama_secenekleri (oylama_id, secenek_metni, tur) 
                     VALUES (?, 'Hayır', 'hayir')",
                    [$oylama_id]
                );
            }
            
            // Commit
            $db->connect()->commit();
            
            // Log
            $db->query(
                "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
                 VALUES (?, 'oylama_olusturma', ?, ?)",
                [$_SESSION['kullanici_id'], "Yeni oylama: $baslik", $_SERVER['REMOTE_ADDR']]
            );
            
            $success = "Oylama başarıyla oluşturuldu! ID: $oylama_id";
            
            // 2 saniye sonra oylama detayına yönlendir
            header("refresh:2;url=oylama_detay.php?id=$oylama_id");
            
        } catch (Exception $e) {
            $db->connect()->rollBack();
            $errors[] = 'Oylama oluşturulurken hata: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Oylama Oluştur - Doğrudan İrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .candidate-row {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .remove-candidate {
            color: #dc3545;
            cursor: pointer;
        }
        #adayListesi {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Başlık -->
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold text-primary">
                        <i class="bi bi-plus-circle-fill"></i> YENİ OYLAMA OLUŞTUR
                    </h1>
                    <p class="lead">Doğrudan demokrasi için yeni bir oylama başlatın</p>
                </div>

                <!-- Oylama formu -->
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-clipboard-plus"></i> Oylama Bilgileri
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <h5 class="alert-heading">✅ Başarılı!</h5>
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

                        <form method="POST" action="" id="oylamaForm">
                            <!-- Temel bilgiler -->
                            <div class="form-section">
                                <h5 class="border-bottom pb-2 mb-3">📋 Temel Bilgiler</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="tur" class="form-label required">Oylama Türü</label>
                                        <select class="form-select" id="tur" name="tur" required>
                                            <option value="">Seçiniz</option>
                                            <option value="secim" <?= ($_POST['tur'] ?? '') === 'secim' ? 'selected' : '' ?>>Seçim</option>
                                            <option value="referandum" <?= ($_POST['tur'] ?? '') === 'referandum' ? 'selected' : '' ?>>Referandum</option>
                                            <option value="kanun_teklifi" <?= ($_POST['tur'] ?? '') === 'kanun_teklifi' ? 'selected' : '' ?>>Kanun Teklifi</option>
                                        </select>
                                        <div class="form-text">
                                            Seçim: Adaylı yarışma<br>
                                            Referandum: Evet/Hayır oylaması<br>
                                            Kanun Teklifi: Çok seçenekli teklif
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="bitis_tarihi" class="form-label required">Bitiş Tarihi</label>
                                        <input type="datetime-local" class="form-control" id="bitis_tarihi" 
                                               name="bitis_tarihi" required
                                               value="<?= htmlspecialchars($_POST['bitis_tarihi'] ?? '') ?>"
                                               min="<?= date('Y-m-d\TH:i') ?>">
                                        <div class="form-text">
                                            Oylamanın sona ereceği tarih ve saat
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="baslik" class="form-label required">Oylama Başlığı</label>
                                    <input type="text" class="form-control" id="baslik" name="baslik" 
                                           required minlength="10" maxlength="500"
                                           value="<?= htmlspecialchars($_POST['baslik'] ?? '') ?>"
                                           placeholder="Örn: 2024 Belediye Başkanlığı Seçimi">
                                    <div class="form-text">
                                        Oylamanın ana başlığı (en az 10 karakter)
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label required">Açıklama</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" 
                                              rows="4" required
                                              placeholder="Oylamanın amacı, kapsamı ve detayları..."><?= htmlspecialchars($_POST['aciklama'] ?? '') ?></textarea>
                                    <div class="form-text">
                                        Oylama hakkında detaylı bilgi
                                    </div>
                                </div>
                            </div>

                            <!-- Topluluk seçimi -->
                            <div class="form-section">
                                <h5 class="border-bottom pb-2 mb-3">🏙️ Oylama Kapsamı</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="topluluk_tipi" class="form-label required">Topluluk Tipi</label>
                                        <select class="form-select" id="topluluk_tipi" name="topluluk_tipi" required>
                                            <option value="">Seçiniz</option>
                                            <option value="ulusal" <?= ($_POST['topluluk_tipi'] ?? '') === 'ulusal' ? 'selected' : '' ?>>Ulusal</option>
                                            <option value="il" <?= ($_POST['topluluk_tipi'] ?? '') === 'il' ? 'selected' : '' ?>>İl</option>
                                            <option value="ilce" <?= ($_POST['topluluk_tipi'] ?? '') === 'ilce' ? 'selected' : '' ?>>İlçe</option>
                                            <option value="sendika" <?= ($_POST['topluluk_tipi'] ?? '') === 'sendika' ? 'selected' : '' ?>>Sendika</option>
                                            <option value="meslek_odasi" <?= ($_POST['topluluk_tipi'] ?? '') === 'meslek_odasi' ? 'selected' : '' ?>>Meslek Odası</option>
                                            <option value="universite" <?= ($_POST['topluluk_tipi'] ?? '') === 'universite' ? 'selected' : '' ?>>Üniversite</option>
                                            <option value="sirket" <?= ($_POST['topluluk_tipi'] ?? '') === 'sirket' ? 'selected' : '' ?>>Şirket</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6" id="topluluk_id_container" style="display: none;">
                                        <label for="topluluk_id" class="form-label">Topluluk Seçin</label>
                                        <select class="form-select" id="topluluk_id" name="topluluk_id">
                                            <!-- Dinamik olarak doldurulacak -->
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Ulusal</strong>: Tüm kullanıcılar oy kullanabilir<br>
                                    <strong>İl/İlçe</strong>: Sadece o il/ilçeye üye kullanıcılar<br>
                                    <strong>Diğer</strong>: Sadece o topluluğun üyeleri
                                </div>
                            </div>

                            <!-- Adaylar bölümü (sadece seçim için) -->
                            <div class="form-section" id="adaylarSection" style="display: none;">
                                <h5 class="border-bottom pb-2 mb-3">👥 Adaylar</h5>
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    Her aday için <strong>Destek Oyu</strong> ve <strong>Negatif Oy</strong> seçenekleri olacaktır.
                                </div>
                                
                                <div id="adayListesi">
                                    <!-- Adaylar buraya eklenecek -->
                                    <div class="candidate-row">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Aday Adı</label>
                                                <input type="text" class="form-control candidate-name" 
                                                       name="aday[]" placeholder="Adayın adı soyadı">
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Aday Açıklaması (Opsiyonel)</label>
                                                <input type="text" class="form-control" 
                                                       name="aday_aciklama[]" placeholder="Kısa açıklama">
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end">
                                                <button type="button" class="btn btn-outline-danger remove-candidate" 
                                                        onclick="removeCandidate(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="addCandidate()">
                                        <i class="bi bi-plus-circle"></i> Yeni Aday Ekle
                                    </button>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-lightbulb"></i> 
                                    <strong>Negatif Oy Sistemi:</strong> Kullanıcılar istemedikleri adaylara negatif oy verebilirler.
                                    Net skor (Destek - Negatif) ile kazanan belirlenir.
                                </div>
                            </div>

                            <!-- Referandum seçenekleri (sadece referandum için) -->
                            <div class="form-section" id="referandumSection" style="display: none;">
                                <h5 class="border-bottom pb-2 mb-3">📝 Referandum Seçenekleri</h5>
                                
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> 
                                    Referandum oylamaları otomatik olarak <strong>Evet</strong> ve <strong>Hayır</strong> seçenekleriyle oluşturulur.
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-success">
                                            <div class="card-body text-center">
                                                <h5 class="card-title text-success">✅ EVET</h5>
                                                <p class="card-text">Öneriyi destekliyorum</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-danger">
                                            <div class="card-body text-center">
                                                <h5 class="card-title text-danger">❌ HAYIR</h5>
                                                <p class="card-text">Öneriyi desteklemiyorum</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-lightbulb"></i> 
                                    Kullanıcılar hem Evet'e hem Hayır'a negatif oy verebilirler.
                                    Bu, "hiçbiri" seçeneğini temsil eder.
                                </div>
                            </div>

                            <!-- Kanun teklifi seçenekleri -->
                            <div class="form-section" id="kanunSection" style="display: none;">
                                <h5 class="border-bottom pb-2 mb-3">📜 Kanun Teklifi Seçenekleri</h5>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    Kanun teklifi oylamalarında kullanıcılar birden fazla seçeneği destekleyebilir
                                    ve istemedikleri seçeneklere negatif oy verebilirler.
                                </div>
                                
                                <div id="kanunSecenekleri">
                                    <!-- Seçenekler buraya eklenecek -->
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="addKanunSecenek()">
                                        <i class="bi bi-plus-circle"></i> Yeni Seçenek Ekle
                                    </button>
                                </div>
                            </div>

                            <!-- Gönder butonu -->
                            <div class="text-center mt-5">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-check-circle-fill"></i> OYLAMAYI OLUŞTUR
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">
                                    <i class="bi bi-x-circle"></i> İPTAL
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Topluluk tipi değişiminde
    document.getElementById('topluluk_tipi').addEventListener('change', function() {
        const container = document.getElementById('topluluk_id_container');
        const select = document.getElementById('topluluk_id');
        
        if (this.value === 'ulusal') {
            container.style.display = 'none';
            select.innerHTML = '';
        } else if (this.value === 'il') {
            container.style.display = 'block';
            // İller listesini yükle
            select.innerHTML = '<option value="">İl Seçin</option>';
            <?php foreach ($iller as $kod => $il): ?>
                select.innerHTML += `<option value="<?= $kod ?>"><?= $il ?></option>`;
            <?php endforeach; ?>
        } else if (this.value === 'ilce') {
            container.style.display = 'block';
            select.innerHTML = '<option value="">Önce il seçmelisiniz</option>';
            // İlçeler için API çağrısı yapılabilir
        } else {
            container.style.display = 'block';
            select.innerHTML = `
                <option value="">Seçiniz</option>
                <option value="1">Sendika 1</option>
                <option value="2">Sendika 2</option>
                <option value="3">Meslek Odası 1</option>
                <option value="4">Üniversite 1</option>
                <option value="5">Şirket 1</option>
            `;
        }
    });

    // Oylama türü değişiminde
    document.getElementById('tur').addEventListener('change', function() {
        const secimSection = document.getElementById('adaylarSection');
        const referandumSection = document.getElementById('referandumSection');
        const kanunSection = document.getElementById('kanunSection');
        
        // Tümünü gizle
        secimSection.style.display = 'none';
        referandumSection.style.display = 'none';
        kanunSection.style.display = 'none';
        
        // Seçileni göster
        if (this.value === 'secim') {
            secimSection.style.display = 'block';
        } else if (this.value === 'referandum') {
            referandumSection.style.display = 'block';
        } else if (this.value === 'kanun_teklifi') {
            kanunSection.style.display = 'block';
            // Kanun seçeneklerini başlat
            initKanunSecenekleri();
        }
    });

    // Aday ekleme
    let candidateCount = 1;
    
    function addCandidate() {
        const list = document.getElementById('adayListesi');
        const newRow = document.createElement('div');
        newRow.className = 'candidate-row';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Aday Adı</label>
                    <input type="text" class="form-control candidate-name" 
                           name="aday[]" placeholder="Adayın adı soyadı" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Aday Açıklaması (Opsiyonel)</label>
                    <input type="text" class="form-control" 
                           name="aday_aciklama[]" placeholder="Kısa açıklama">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-candidate" 
                            onclick="removeCandidate(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        list.appendChild(newRow);
        candidateCount++;
    }
    
    function removeCandidate(button) {
        if (candidateCount > 1) {
            button.closest('.candidate-row').remove();
            candidateCount--;
        } else {
            alert('En az bir aday olmalıdır.');
        }
    }

    // Kanun teklifi seçenekleri
    let kanunCount = 0;
    
    function initKanunSecenekleri() {
        const container = document.getElementById('kanunSecenekleri');
        container.innerHTML = `
            <div class="kanun-secenek-row mb-3 p-3 border rounded">
                <div class="row">
                    <div class="col-md-10">
                        <label class="form-label">Seçenek Metni</label>
                        <input type="text" class="form-control" 
                               name="kanun_secenek[]" placeholder="Seçenek açıklaması" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger" onclick="removeKanunSecenek(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        kanunCount = 1;
    }
    
    function addKanunSecenek() {
        const container = document.getElementById('kanunSecenekleri');
        const newRow = document.createElement('div');
        newRow.className = 'kanun-secenek-row mb-3 p-3 border rounded';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-10">
                    <label class="form-label">Seçenek Metni</label>
                    <input type="text" class="form-control" 
                           name="kanun_secenek[]" placeholder="Seçenek açıklaması" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger" onclick="removeKanunSecenek(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        kanunCount++;
    }
    
    function removeKanunSecenek(button) {
        if (kanunCount > 1) {
            button.closest('.kanun-secenek-row').remove();
            kanunCount--;
        } else {
            alert('En az bir seçenek olmalıdır.');
        }
    }

    // Form gönderim kontrolü
    document.getElementById('oylamaForm').addEventListener('submit', function(e) {
        const tur = document.getElementById('tur').value;
        
        if (tur === 'secim') {
            // Aday isimlerini kontrol et
            const adayInputs = document.querySelectorAll('.candidate-name');
            let hasEmpty = false;
            
            adayInputs.forEach(input => {
                if (!input.value.trim()) {
                    hasEmpty = true;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (hasEmpty) {
                e.preventDefault();
                alert('Lütfen tüm adaylar için isim girin.');
            }
        }
    });

    // Sayfa yüklendiğinde varsayılan değerleri ayarla
    document.addEventListener('DOMContentLoaded', function() {
        // Varsayılan bitiş tarihi (7 gün sonra)
        const defaultDate = new Date();
        defaultDate.setDate(defaultDate.getDate() + 7);
        const dateStr = defaultDate.toISOString().slice(0, 16);
        document.getElementById('bitis_tarihi').value = dateStr;
        
        // Oylama türü değişimini tetikle
        const turSelect = document.getElementById('tur');
        if (turSelect.value) {
            turSelect.dispatchEvent(new Event('change'));
        }
    });
    </script>
</body>
</html>
