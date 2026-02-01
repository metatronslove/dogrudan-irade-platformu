<?php
// Yetkilendirme kontrolü için fonksiyonlar

function requireLogin() {
    if (!isset($_SESSION['kullanici_id'])) {
        header("Location: giris.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    
    if ($_SESSION['yetki_seviye'] !== 'superadmin' && $_SESSION['yetki_seviye'] !== 'yonetici') {
        header("Location: index.php?error=yetkisiz_erisim");
        exit;
    }
}

function requireSuperAdmin() {
    requireLogin();
    
    if ($_SESSION['yetki_seviye'] !== 'superadmin') {
        header("Location: index.php?error=yetkisiz_erisim");
        exit;
    }
}

function canVoteInPoll($oylama_id, $kullanici_id) {
    require_once '../config/database.php';
    $db = new Database();
    
    // Oylama bilgilerini al
    $oylama = $db->query(
        "SELECT * FROM oylamalar WHERE id = ?",
        [$oylama_id]
    )->fetch();
    
    if (!$oylama) return false;
    
    // Ulusal oylamalarda herkes oy kullanabilir
    if ($oylama['topluluk_tipi'] === 'ulusal') {
        return true;
    }
    
    // Kullanıcının bu topluluğa üyeliğini kontrol et
    $uyelik = $db->singleValueQuery(
        "SELECT COUNT(*) FROM kullanici_topluluklari 
         WHERE kullanici_id = ? 
         AND topluluk_tipi = ? 
         AND topluluk_id = ?",
        [$kullanici_id, $oylama['topluluk_tipi'], $oylama['topluluk_id']]
    );
    
    return $uyelik > 0;
}

function hasVoted($oylama_id, $kullanici_id) {
    require_once '../config/database.php';
    $db = new Database();
    
    $oy = $db->singleValueQuery(
        "SELECT COUNT(*) FROM oy_kullanicilar 
         WHERE oylama_id = ? AND kullanici_id = ?",
        [$oylama_id, $kullanici_id]
    );
    
    return $oy > 0;
}
?>
