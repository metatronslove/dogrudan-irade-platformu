-- Veritabanı oluşturma
CREATE DATABASE IF NOT EXISTS dogrudan_irade DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dogrudan_irade;

-- Oylama Tablosu
CREATE TABLE oylamalar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tur ENUM('secim', 'kanun_teklifi', 'referandum') NOT NULL,
    baslik VARCHAR(500) NOT NULL,
    aciklama TEXT,
    olusturan_id BIGINT NOT NULL,
    topluluk_tipi ENUM('ulusal', 'il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') DEFAULT 'ulusal',
    topluluk_id BIGINT DEFAULT NULL COMMENT 'Hangi il, ilçe, sendika vb. için oylama',
    baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    bitis_tarihi DATETIME,
    durum ENUM('aktif', 'sonuclandi', 'iptal') DEFAULT 'aktif',
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tur (tur),
    INDEX idx_durum (durum),
    INDEX idx_topluluk (topluluk_tipi, topluluk_id)
);

-- Adaylar Tablosu (Seçim türündeki oylamalar için)
CREATE TABLE adaylar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    aday_adi VARCHAR(200) NOT NULL,
    aday_aciklama TEXT,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Kullanıcılar Tablosu
CREATE TABLE kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    eposta VARCHAR(255) UNIQUE NOT NULL,
    sifre_hash VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(200) NOT NULL,
    tc_kimlik VARCHAR(11) UNIQUE COMMENT 'Güvenlik nedeniyle hashlenmiş olarak saklanabilir',
    telefon VARCHAR(20),
    dogum_tarihi DATE,
    kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    son_giris_tarihi DATETIME,
    durum ENUM('aktif', 'pasif', 'askida') DEFAULT 'aktif',
    yetki_seviye ENUM('kullanici', 'yonetici', 'superadmin') DEFAULT 'kullanici',
    INDEX idx_eposta (eposta)
);

-- Örnek yönetici kullanıcı (şifre: admin123)
INSERT INTO kullanicilar (eposta, sifre_hash, ad_soyad, yetki_seviye, durum) 
VALUES ('admin@dogrudanirade.org', '$2y$10$YourHashHere', 'Sistem Yöneticisi', 'superadmin', 'aktif');

-- Kullanıcı Topluluk Üyelikleri
CREATE TABLE kullanici_topluluklari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    topluluk_tipi ENUM('il', 'ilce', 'sendika', 'meslek_odasi', 'universite', 'sirket') NOT NULL,
    topluluk_id BIGINT NOT NULL COMMENT 'İl kodu, sendika IDsi vb.',
    uyelik_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_uyelik (kullanici_id, topluluk_tipi, topluluk_id),
    INDEX idx_kullanici (kullanici_id)
);

-- OYLAR Tablosu (Çekirdek Mantık Burada)
CREATE TABLE oy_kullanicilar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    destek_verilen_aday_id BIGINT NULL,
    negatif_oy_verilen_aday_id BIGINT NULL,
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_adresi VARCHAR(45),
    UNIQUE KEY tek_destek (oylama_id, kullanici_id),
    UNIQUE KEY tek_negatif_aday (oylama_id, kullanici_id, negatif_oy_verilen_aday_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (destek_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    FOREIGN KEY (negatif_oy_verilen_aday_id) REFERENCES adaylar(id) ON DELETE CASCADE,
    INDEX idx_oylama_kullanici (oylama_id, kullanici_id),
    INDEX idx_destek (destek_verilen_aday_id),
    INDEX idx_negatif (negatif_oy_verilen_aday_id)
);

-- Referandum/Kanun Teklifi Seçenekleri
CREATE TABLE oylama_secenekleri (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    secenek_metni VARCHAR(500) NOT NULL,
    tur ENUM('evet', 'hayir', 'alternatif') NOT NULL,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id)
);

-- Referandum Oyları
CREATE TABLE referandum_oylari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    secenek_id BIGINT NOT NULL,
    negatif_oy BOOLEAN DEFAULT FALSE COMMENT 'Bu seçeneğe karşı negatif oy',
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY tek_oylama_kullanici (oylama_id, kullanici_id),
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (secenek_id) REFERENCES oylama_secenekleri(id) ON DELETE CASCADE
);

-- Log Tablosu (Güvenlik ve Denetim)
CREATE TABLE sistem_loglari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NULL,
    islem_tipi VARCHAR(100) NOT NULL,
    aciklama TEXT,
    ip_adresi VARCHAR(45),
    user_agent TEXT,
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tarih (tarih),
    INDEX idx_islem_tipi (islem_tipi)
);

-- Örnek veriler
INSERT INTO oylamalar (tur, baslik, aciklama, olusturan_id, topluluk_tipi, bitis_tarihi) VALUES
('secim', 'Örnek Belediye Başkanlığı Seçimi', 'İstanbul için yeni belediye başkanını seçiyoruz', 1, 'il', DATE_ADD(NOW(), INTERVAL 7 DAY)),
('referandum', 'Yeni Park Projesi', 'Şehir merkezine yeni park yapılması hakkında referandum', 1, 'il', DATE_ADD(NOW(), INTERVAL 3 DAY));

INSERT INTO adaylar (oylama_id, aday_adi, aday_aciklama) VALUES
(1, 'Ahmet Yılmaz', 'Deneyimli belediyeci, 10 yıllık tecrübe'),
(1, 'Mehmet Demir', 'Genç ve dinamik, çevre dostu projeler'),
(1, 'Ayşe Kaya', 'Kadın bakış açısı, şeffaf yönetim');

-- Şifre sıfırlama tokenları tablosu
CREATE TABLE IF NOT EXISTS sifre_sifirlama_tokenlari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    son_kullanma DATETIME NOT NULL,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    kullanildi BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_son_kullanma (son_kullanma)
);

-- Bildirimler tablosu
CREATE TABLE IF NOT EXISTS bildirimler (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    baslik VARCHAR(200) NOT NULL,
    mesaj TEXT NOT NULL,
    tur ENUM('bilgi', 'uyari', 'basari', 'hata') DEFAULT 'bilgi',
    okundu BOOLEAN DEFAULT FALSE,
    okunma_tarihi DATETIME NULL,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_okundu (okundu),
    INDEX idx_tarih (olusturulma_tarihi)
);

-- Sistem ayarları tablosu
CREATE TABLE IF NOT EXISTS sistem_ayarlari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ayar_adi VARCHAR(100) UNIQUE NOT NULL,
    ayar_degeri TEXT,
    aciklama VARCHAR(500),
    tur ENUM('metin', 'sayi', 'boolean', 'json') DEFAULT 'metin',
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ayar_adi (ayar_adi)
);

-- Varsayılan sistem ayarları
INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, aciklama, tur) VALUES
('site_baslik', 'Doğrudan İrade Platformu', 'Site başlığı', 'metin'),
('site_aciklama', 'Doğrudan demokrasi platformu', 'Site açıklaması', 'metin'),
('site_url', 'http://localhost/dogrudan_irade', 'Site URL', 'metin'),
('bakim_modu', '0', 'Bakım modu aktif mi?', 'boolean'),
('kayit_acik', '1', 'Yeni kayıtlar açık mı?', 'boolean'),
('max_oylama_suresi', '30', 'Maksimum oylama süresi (gün)', 'sayi'),
('min_sifre_uzunluk', '6', 'Minimum şifre uzunluğu', 'sayi'),
('eposta_dogrulama', '0', 'E-posta doğrulama gerekli mi?', 'boolean'),
('gunluk_max_oy', '10', 'Günlük maksimum oy sayısı', 'sayi'),
('logo_url', '/assets/img/logo.png', 'Logo URL', 'metin'),
('favicon_url', '/assets/img/favicon.ico', 'Favicon URL', 'metin'),
('iletisim_eposta', 'info@dogrudanirade.org', 'İletişim e-postası', 'metin'),
('iletisim_telefon', '', 'İletişim telefonu', 'metin'),
('iletisim_adres', '', 'İletişim adresi', 'metin'),
('sosyal_facebook', '', 'Facebook URL', 'metin'),
('sosyal_twitter', '', 'Twitter URL', 'metin'),
('sosyal_instagram', '', 'Instagram URL', 'metin'),
('analytics_kodu', '', 'Google Analytics kodu', 'metin'),
('recaptcha_site_key', '', 'reCAPTCHA site key', 'metin'),
('recaptcha_secret_key', '', 'reCAPTCHA secret key', 'metin'),
('smtp_host', '', 'SMTP sunucusu', 'metin'),
('smtp_port', '587', 'SMTP portu', 'sayi'),
('smtp_username', '', 'SMTP kullanıcı adı', 'metin'),
('smtp_password', '', 'SMTP şifresi', 'metin'),
('smtp_secure', 'tls', 'SMTP güvenlik', 'metin'),
('iletisim_formu_acik', '1', 'İletişim formu açık mı?', 'boolean');

-- İletişim mesajları tablosu
CREATE TABLE IF NOT EXISTS iletisim_mesajlari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ad_soyad VARCHAR(200) NOT NULL,
    eposta VARCHAR(255) NOT NULL,
    konu VARCHAR(200) NOT NULL,
    mesaj TEXT NOT NULL,
    durum ENUM('okunmadi', 'okundu', 'cevaplandi', 'arsiv') DEFAULT 'okunmadi',
    ip_adresi VARCHAR(45),
    user_agent TEXT,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    okunma_tarihi DATETIME NULL,
    cevaplanma_tarihi DATETIME NULL,
    INDEX idx_durum (durum),
    INDEX idx_tarih (olusturulma_tarihi),
    INDEX idx_eposta (eposta)
);

-- Kategoriler tablosu (oylamalar için)
CREATE TABLE IF NOT EXISTS kategoriler (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kategori_adi VARCHAR(100) UNIQUE NOT NULL,
    aciklama VARCHAR(500),
    renk VARCHAR(7) DEFAULT '#007bff',
    aktif BOOLEAN DEFAULT TRUE,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aktif (aktif)
);

-- Varsayılan kategoriler
INSERT INTO kategoriler (kategori_adi, aciklama, renk) VALUES
('Siyaset', 'Siyasi konular ve seçimler', '#dc3545'),
('Ekonomi', 'Ekonomik konular ve kararlar', '#28a745'),
('Eğitim', 'Eğitim ile ilgili konular', '#007bff'),
('Sağlık', 'Sağlık politikaları', '#6f42c1'),
('Çevre', 'Çevre ve doğa konuları', '#20c997'),
('Ulaşım', 'Ulaşım ve altyapı', '#fd7e14'),
('Kültür', 'Kültür ve sanat', '#e83e8c'),
('Spor', 'Spor faaliyetleri', '#ffc107'),
('Teknoloji', 'Teknolojik gelişmeler', '#17a2b8'),
('Diğer', 'Diğer konular', '#6c757d');

-- Oylama kategorileri ilişki tablosu
CREATE TABLE IF NOT EXISTS oylama_kategorileri (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kategori_id BIGINT NOT NULL,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kategori_id) REFERENCES kategoriler(id) ON DELETE CASCADE,
    UNIQUE KEY unique_oylama_kategori (oylama_id, kategori_id),
    INDEX idx_oylama (oylama_id),
    INDEX idx_kategori (kategori_id)
);

-- Oturumlar tablosu (gelişmiş session yönetimi)
CREATE TABLE IF NOT EXISTS oturumlar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    session_id VARCHAR(128) UNIQUE NOT NULL,
    ip_adresi VARCHAR(45) NOT NULL,
    user_agent TEXT,
    baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    son_aktivite DATETIME DEFAULT CURRENT_TIMESTAMP,
    sonlandi BOOLEAN DEFAULT FALSE,
    sonlanma_sebebi ENUM('normal', 'timeout', 'manual', 'security') DEFAULT 'normal',
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_session (session_id),
    INDEX idx_sonlandi (sonlandi),
    INDEX idx_son_aktivite (son_aktivite)
);

-- Raporlar tablosu
CREATE TABLE IF NOT EXISTS raporlar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id BIGINT NOT NULL,
    tur ENUM('gunluk', 'haftalik', 'aylik', 'ozel') NOT NULL,
    baslik VARCHAR(200) NOT NULL,
    icerik JSON NOT NULL,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_tur (tur),
    INDEX idx_tarih (olusturulma_tarihi)
);

-- Oylama yorumları tablosu
CREATE TABLE IF NOT EXISTS yorumlar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    oylama_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    yorum TEXT NOT NULL,
    durum ENUM('onayli', 'beklemede', 'reddedildi') DEFAULT 'beklemede',
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (oylama_id) REFERENCES oylamalar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_oylama (oylama_id),
    INDEX idx_kullanici (kullanici_id),
    INDEX idx_durum (durum),
    INDEX idx_tarih (olusturulma_tarihi)
);

-- Yorum oyları tablosu
CREATE TABLE IF NOT EXISTS yorum_oylari (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    yorum_id BIGINT NOT NULL,
    kullanici_id BIGINT NOT NULL,
    oy ENUM('begen', 'begenme') NOT NULL,
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_yorum_oy (yorum_id, kullanici_id),
    FOREIGN KEY (yorum_id) REFERENCES yorumlar(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_yorum (yorum_id),
    INDEX idx_kullanici (kullanici_id)
);

-- Dosyalar tablosu
CREATE TABLE IF NOT EXISTS dosyalar (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    dosya_adi VARCHAR(255) NOT NULL,
    orjinal_adi VARCHAR(255) NOT NULL,
    yol VARCHAR(500) NOT NULL,
    boyut BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    yukleyen_id BIGINT NOT NULL,
    yuklenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    aktif BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (yukleyen_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    INDEX idx_yukleyen (yukleyen_id),
    INDEX idx_tarih (yuklenme_tarihi)
);

-- İstatistikler tablosu (günlük istatistikler)
CREATE TABLE IF NOT EXISTS istatistikler (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tarih DATE NOT NULL,
    yeni_kullanici INT DEFAULT 0,
    toplam_giris INT DEFAULT 0,
    toplam_oy INT DEFAULT 0,
    yeni_oylama INT DEFAULT 0,
    biten_oylama INT DEFAULT 0,
    aktif_kullanici INT DEFAULT 0,
    toplam_ziyaret INT DEFAULT 0,
    UNIQUE KEY unique_tarih (tarih),
    INDEX idx_tarih (tarih)
);
