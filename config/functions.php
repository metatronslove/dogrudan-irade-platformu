<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function redirect($url, $delay = 0) {
    if ($delay > 0) {
        header("refresh:$delay;url=$url");
    } else {
        header("Location: $url");
    }
    exit;
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "$protocol://$host";
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function formatDate($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

function isLoggedIn() {
    return isset($_SESSION['kullanici_id']);
}

function isAdmin() {
    return isset($_SESSION['yetki_seviye']) && 
           ($_SESSION['yetki_seviye'] === 'superadmin' || $_SESSION['yetki_seviye'] === 'yonetici');
}

/**
 * Log kaydı oluştur
 */
function logIslem($kullanici_id, $islem_tipi, $aciklama = '', $ip = null) {
    global $db;
    
    if (!$db) {
        require_once 'database.php';
        $db = new Database();
    }
    
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi, user_agent) 
             VALUES (?, ?, ?, ?, ?)",
            [$kullanici_id, $islem_tipi, $aciklama, $ip, $user_agent]
        );
        return true;
    } catch (Exception $e) {
        error_log("Log kaydı hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Güvenli token oluştur
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * CSRF token oluştur ve kontrol et
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Güvenlik headers'ları ekle
 */
function security_headers() {
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // CSP Header (Content Security Policy)
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "img-src 'self' data: https:",
        "font-src 'self' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'"
    ];
    
    header("Content-Security-Policy: " . implode('; ', $csp));
}

/**
 * Rate limiting kontrolü
 */
function rate_limit($key, $limit = 10, $seconds = 60) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $redis_key = "rate_limit:{$key}:{$ip}";
    
    // Redis yoksa session kullan
    if (!isset($_SESSION[$redis_key])) {
        $_SESSION[$redis_key] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    $data = $_SESSION[$redis_key];
    
    // Süre dolmuşsa sıfırla
    if (time() - $data['time'] > $seconds) {
        $_SESSION[$redis_key] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    // Limit kontrolü
    if ($data['count'] >= $limit) {
        return false;
    }
    
    // Sayacı artır
    $_SESSION[$redis_key]['count']++;
    return true;
}

/**
 * Email gönder
 */
function sendEmail($to, $subject, $body, $headers = []) {
    $default_headers = [
        'From' => 'noreply@dogrudanirade.org',
        'Reply-To' => 'info@dogrudanirade.org',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= "$key: $value\r\n";
    }
    
    return mail($to, $subject, $body, $header_string);
}

/**
 * Şifre sıfırlama token'ı oluştur
 */
function createPasswordResetToken($user_id) {
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    global $db;
    if (!$db) {
        require_once 'database.php';
        $db = new Database();
    }
    
    // Eski tokenları temizle
    $db->query(
        "DELETE FROM sifre_sifirlama_tokenlari WHERE kullanici_id = ? OR son_kullanma < NOW()",
        [$user_id]
    );
    
    // Yeni token ekle
    $db->query(
        "INSERT INTO sifre_sifirlama_tokenlari (kullanici_id, token, son_kullanma) 
         VALUES (?, ?, ?)",
        [$user_id, $token, $expires]
    );
    
    return $token;
}

/**
 * Şifre sıfırlama token'ını doğrula
 */
function verifyPasswordResetToken($token) {
    global $db;
    if (!$db) {
        require_once 'database.php';
        $db = new Database();
    }
    
    $result = $db->query(
        "SELECT kullanici_id FROM sifre_sifirlama_tokenlari 
         WHERE token = ? AND son_kullanma > NOW()",
        [$token]
    )->fetch();
    
    return $result ? $result['kullanici_id'] : false;
}

/**
 * Dosya yükleme
 */
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Dosya yükleme hatası.'];
    }
    
    // Dosya boyutu kontrolü
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Dosya çok büyük. Maksimum ' . ($max_size / 1024 / 1024) . 'MB.'];
    }
    
    // Dosya tipi kontrolü
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => 'İzin verilmeyen dosya tipi.'];
    }
    
    // Güvenlik kontrolü
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf'
    ];
    
    if (!in_array($mime, $allowed_mimes)) {
        return ['success' => false, 'message' => 'Geçersiz dosya formatı.'];
    }
    
    // Benzersiz dosya adı oluştur
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $file['name']);
    $upload_path = 'uploads/' . date('Y/m');
    
    // Klasör yoksa oluştur
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0777, true);
    }
    
    $target_file = $upload_path . '/' . $filename;
    
    // Dosyayı taşı
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $target_file,
            'url' => '/' . $target_file
        ];
    }
    
    return ['success' => false, 'message' => 'Dosya yüklenemedi.'];
}

/**
 * Türkçe tarih formatı
 */
function turkishDate($date, $format = 'd F Y H:i') {
    $english_months = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
    $turkish_months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
                      'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    
    $formatted = date($format, strtotime($date));
    return str_replace($english_months, $turkish_months, $formatted);
}

/**
 * SEO uyumlu URL oluştur
 */
function seoUrl($string) {
    $string = html_entity_decode($string);
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

/**
 * CURL ile API isteği
 */
function curlRequest($url, $method = 'GET', $data = [], $headers = []) {
    $ch = curl_init();
    
    $default_headers = [
        'User-Agent: Doğrudan İrade Platformu/1.0',
        'Accept: application/json',
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } elseif ($method === 'JSON') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    return [
        'success' => $http_code >= 200 && $http_code < 300,
        'code' => $http_code,
        'data' => json_decode($response, true) ?: $response
    ];
}
?>
