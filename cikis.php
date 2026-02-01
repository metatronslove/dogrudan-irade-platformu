<?php
session_start();

if (isset($_SESSION['kullanici_id'])) {
    require_once 'config/database.php';
    $db = new Database();
    
    // Log kaydı
    $db->query(
        "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi) 
         VALUES (?, 'cikis', 'Kullanıcı çıkış yaptı', ?)",
        [$_SESSION['kullanici_id'], $_SERVER['REMOTE_ADDR']]
    );
}

// Tüm sessionları temizle
session_unset();
session_destroy();

// Çerezleri temizle (opsiyonel)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Giriş sayfasına yönlendir
header("Location: giris.php?message=cikis_basarili");
exit;
?>
