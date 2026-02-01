<?php
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

/**
 * TC Kimlik doğrulama (detaylı)
 */
function validateTCKN($tckn) {
    // 11 karakter mi?
    if (strlen($tckn) != 11) {
        return false;
    }
    
    // Sadece rakamlardan mı oluşuyor?
    if (!ctype_digit($tckn)) {
        return false;
    }
    
    // İlk hane 0 olamaz
    if ($tckn[0] == '0') {
        return false;
    }
    
    // 1, 3, 5, 7, 9. hanelerin toplamının 7 katından 2, 4, 6, 8. hanelerin toplamı çıkartıldığında
    // elde edilen sonucun 10'a bölümünden kalan 10. haneyi vermeli
    $tekler = $tckn[0] + $tckn[2] + $tckn[4] + $tckn[6] + $tckn[8];
    $ciftler = $tckn[1] + $tckn[3] + $tckn[5] + $tckn[7];
    
    if ((($tekler * 7) - $ciftler) % 10 != $tckn[9]) {
        return false;
    }
    
    // İlk 10 hanenin toplamının 10'a bölümünden kalan 11. haneyi vermeli
    $toplam = 0;
    for ($i = 0; $i < 10; $i++) {
        $toplam += $tckn[$i];
    }
    
    return $toplam % 10 == $tckn[10];
}

/**
 * Vergi numarası doğrulama
 */
function validateVKN($vkn) {
    if (strlen($vkn) != 10 || !ctype_digit($vkn)) {
        return false;
    }
    
    $toplam = 0;
    for ($i = 0; $i < 9; $i++) {
        $tmp = ($vkn[$i] + (9 - $i)) % 10;
        $tmp = ($tmp * pow(2, 9 - $i)) % 9;
        if ($tmp != 0 && $vkn[$i] != 9) {
            $toplam += $tmp;
        }
    }
    
    if ($toplam % 10 == 0) {
        $son_rakam = 0;
    } else {
        $son_rakam = 10 - ($toplam % 10);
    }
    
    return $son_rakam == $vkn[9];
}

/**
 * IBAN doğrulama
 */
function validateIBAN($iban) {
    $iban = strtoupper(str_replace(' ', '', $iban));
    
    if (strlen($iban) != 26) {
        return false;
    }
    
    // TR ile başlamalı
    if (substr($iban, 0, 2) != 'TR') {
        return false;
    }
    
    // Sayısal değer kontrolü
    $iban = substr($iban, 4) . substr($iban, 0, 4);
    $iban = str_replace(
        ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
         'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
        ['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
         '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31',
         '32', '33', '34', '35'],
        $iban
    );
    
    $mod = '';
    for ($i = 0; $i < strlen($iban); $i += 6) {
        $mod = intval($mod . substr($iban, $i, 6)) % 97;
    }
    
    return $mod == 1;
}

/**
 * Kredi kartı doğrulama (Luhn algoritması)
 */
function validateCreditCard($number) {
    $number = preg_replace('/\D/', '', $number);
    
    if (strlen($number) < 13 || strlen($number) > 19) {
        return false;
    }
    
    $sum = 0;
    $reverse = strrev($number);
    
    for ($i = 0; $i < strlen($reverse); $i++) {
        $digit = (int)$reverse[$i];
        
        if ($i % 2 == 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
    }
    
    return $sum % 10 == 0;
}

/**
 * Kart tipi belirleme
 */
function getCardType($number) {
    $number = preg_replace('/\D/', '', $number);
    
    // Visa: 4 ile başlar, 13 veya 16 haneli
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
        return 'VISA';
    }
    
    // MasterCard: 51-55 arası ile başlar, 16 haneli
    if (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
        return 'MASTERCARD';
    }
    
    // American Express: 34 veya 37 ile başlar, 15 haneli
    if (preg_match('/^3[47][0-9]{13}$/', $number)) {
        return 'AMEX';
    }
    
    return 'UNKNOWN';
}

/**
 * URL doğrulama
 */
function validateURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * IPv4 doğrulama
 */
function validateIPv4($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

/**
 * IPv6 doğrulama
 */
function validateIPv6($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}

/**
 * E-posta doğrulama (detaylı)
 */
function validateEmailExtended($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Domain MX kaydı kontrolü
    $domain = substr(strrchr($email, "@"), 1);
    return checkdnsrr($domain, 'MX');
}

/**
 * Parola güçlülük kontrolü
 */
function passwordStrength($password) {
    $score = 0;
    
    // Uzunluk
    if (strlen($password) >= 8) $score++;
    if (strlen($password) >= 12) $score++;
    
    // Karakter çeşitliliği
    if (preg_match('/[a-z]/', $password)) $score++; // Küçük harf
    if (preg_match('/[A-Z]/', $password)) $score++; // Büyük harf
    if (preg_match('/[0-9]/', $password)) $score++; // Rakam
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++; // Özel karakter
    
    // Zayıf parola kontrolü
    $weak_passwords = [
        'password', '123456', 'qwerty', 'admin', 'welcome',
        'password123', '123456789', '12345678', '12345'
    ];
    
    if (in_array(strtolower($password), $weak_passwords)) {
        $score = 0;
    }
    
    // Skora göre güç seviyesi
    if ($score <= 2) return 'zayif';
    if ($score <= 4) return 'orta';
    return 'guclu';
}

/**
 * XSS korumalı çıktı
 */
function xss_clean($data) {
    // HTML tag'lerini temizle
    $data = strip_tags($data);
    
    // HTML entity'lerini çevir
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Potansiyel tehlikeli karakterleri temizle
    $data = str_replace(
        ['<', '>', '"', "'", '&', 'javascript:', 'onload', 'onclick', 'onerror'],
        ['&lt;', '&gt;', '&quot;', '&#039;', '&amp;', '', '', '', ''],
        $data
    );
    
    return $data;
}

/**
 * SQL injection korumalı sorgu
 */
function sql_escape($string, $db = null) {
    if ($db instanceof PDO) {
        return $db->quote($string);
    }
    
    // Temel temizleme
    $search = ["\\", "\x00", "\n", "\r", "'", '"', "\x1a"];
    $replace = ["\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z"];
    
    return str_replace($search, $replace, $string);
}

/**
 * CSRF token oluşturma
 */
function generate_csrf_token() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
    
    // Fallback
    return md5(uniqid(mt_rand(), true) . microtime(true));
}

/**
 * Dosya tipi kontrolü
 */
function validate_file_type($file_path, $allowed_types = ['image/jpeg', 'image/png', 'image/gif']) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    return in_array($mime_type, $allowed_types);
}

/**
 * Resim boyutlandırma
 */
function resize_image($source_path, $target_path, $max_width, $max_height) {
    list($orig_width, $orig_height, $type) = getimagesize($source_path);
    
    // Oranları hesapla
    $ratio = $orig_width / $orig_height;
    
    if ($max_width / $max_height > $ratio) {
        $new_width = $max_height * $ratio;
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    }
    
    // Yeni resim oluştur
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Kaynak resmi yükle
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            // Şeffaflığı koru
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    // Boyutlandır
    imagecopyresampled($new_image, $source, 0, 0, 0, 0, 
                       $new_width, $new_height, $orig_width, $orig_height);
    
    // Kaydet
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $target_path, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $target_path, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($new_image, $target_path);
            break;
    }
    
    // Belleği temizle
    imagedestroy($source);
    imagedestroy($new_image);
    
    return true;
}
?>
