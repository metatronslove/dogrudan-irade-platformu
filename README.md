# DOĞRUDAN İRADE PLATFORMU - EKSİKSİZ PAKET

## **1. PROJE FELSEFESİ ve MAKALE**

### RADİKAL DEMOKRASİ: ARACILARI ORTADAN KALDIRAN DOĞRUDAN İRADE PLATFORMU

**Sorun Tespiti: Temsili Demokrasinin Çöküşü**

Modern temsili demokrasi sistemleri, tarihsel olarak oligarşik bir evrime uğramıştır. Seçimle gelen temsilciler, kısa sürede "profesyonel politikacı" sınıfına dönüşmekte ve seçmenlerin gerçek iradesiyle bağlarını koparmaktadır. Bu sistemde:

- **Seçmen manipülasyonu** sistematik hale gelmiştir: Medya kontrolü, söylem mühendisliği, korku politikaları ve popülizm, seçmenin rasyonel karar verme yeteneğini baltalamaktadır.
- **Çıkar çatışmaları** yapısal problemdir: Finans sektörü, silah lobileri, enerji tekelleri ve büyük şirketler, politikacıları fonlama ve lobi faaliyetleriyle satın almaktadır.
- **Temsil krizi** derinleşmektedir: Milletvekilleri seçildikten sonra seçmenlerine hesap vermemekte, parti disiplini adına bireysel iradelerini feda etmekte, ve gerçek sorunlara çözüm üretmek yerine ideolojik kutuplaşmayı derinleştirmektedir.

**Kurumsal Çıkmazlar:**

1. **TBMM**: Yasama süreci halktan kopuk, bürokratik ve lobi etkisine açık.
2. **YSK**: Tarafsızlığı sürekli tartışma konusu olan, siyasi atamalarla yönetilen bir kurum.
3. **Sendikalar ve Meslek Odaları**: Yönetici elitleri, üyelerinin iradesini temsil etmekten uzak, kendi çıkarlarını koruyan kapalı yapılar.
4. **Yerel Yönetimler**: Merkezi hükümetin vesayeti altında, gerçek anlamda yerel iradeyi yansıtamayan yapılar.

**Teknolojik Fırsat: İnternet Devrimi**

21. yüzyıl internet teknolojisi, tarihte ilk kez, milyonlarca insanın eşzamanlı, şeffaf, güvenli ve doğrudan katılımını mümkün kılmaktadır. Artık:

- Coğrafi sınırlar anlamını yitirmiştir.
- Bilgiye erişim demokratikleşmiştir.
- Gerçek zamanlı iletişim ve oylama teknik olarak mümkündür.

**Platform Vizyonu: Aracıları Ortadan Kaldırmak**

Bu platform, tüm temsili kurumları aşan radikal bir alternatif sunmaktadır:

1. **Ulusal düzeyde**: TBMM'nin yasama fonksiyonunu, doğrudan halk oylamalarıyla yerine getirmek.
2. **Yerel düzeyde**: İl ve ilçe meclislerinin karar alma süreçlerini demokratikleştirmek.
3. **Mesleki düzeyde**: Sendika ve meslek odalarında üyelerin doğrudan söz sahibi olmasını sağlamak.
4. **Kurumsal düzeyde**: Şirketlerde hissedar ve çalışan katılımını artırmak.

**Devrimci Yenilik: Negatif Oy Sistemi**

Geleneksel seçim sistemlerindeki en büyük eksiklik, sadece "en az kötü" adayı seçmeye zorlanmaktır. Negatif oy sistemi bu problemi çözmektedir:

- **Popüler ama sevilmeyen adayları filtreler**: Yüksek destek alan ama aynı zamanda yüksek muhalefet toplayan adayların kazanmasını engeller.
- **Toplumsal mutabakatı yansıtır**: Sadece kimin daha çok sevildiğini değil, kimin daha az sevilmediğini de ölçer.
- **Manipülasyonu zorlaştırır**: Medya tarafından pompalanan ancak gerçekte halk desteği olmayan adayları eleyebilir.
- **Daha gerçek bir toplumsal tercihi yansıtır**: Net skor (destek - negatif) formülü, bir adayın gerçek kabul edilebilirliğini ölçer.

**Platformun Temel İlkeleri:**

1. **Doğrudan Katılım**: Her vatandaş, her kararda doğrudan söz sahibi olabilir.
2. **Şeffaflık**: Tüm oylama süreçleri ve sonuçları herkese açıktır.
3. **Eşitlik**: Her kullanıcının oyu eşit değerdedir.
4. **Güvenlik**: Oy verme süreci manipülasyona ve hileye karşı korumalıdır.
5. **Kapsayıcılık**: Tüm toplumsal kesimlerin katılımı teşvik edilir.

Bu platform, demokrasinin evriminde yeni bir aşamayı temsil etmektedir: **Dijital Doğrudan Demokrasi**. Artık "temsil edilmeye" değil, "doğrudan söz sahibi olmaya" talibiz.

## **2. TEKNİK GEREKSİNİMLER (PHP/MySQL)**

- **Sunucu Tarafı**: PHP 7.4 veya üzeri
- **Veritabanı**: MySQL 5.7 veya üzeri (veya MariaDB 10.2+)
- **Frontend**: Bootstrap 5.2+ ile responsive ve modern arayüz
- **Güvenlik**:
  - Tüm kullanıcı girdileri filtrelenecek ve validate edilecek
  - SQL sorgularında kesinlikle Prepared Statements kullanılacak
  - Kullanıcı şifreleri `password_hash()` ile hash'lenecek
  - XSS, CSRF ve SQL Injection korumaları uygulanacak
  - HTTPS zorunlu olacak
- **Performans**: Temel önbellekleme mekanizmaları implemente edilecek

## **3. VERİTABANI ŞEMASI**

```sql
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
    INDEX idx_eposta (eposta)
);

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
    -- DESTEK OYU: Hangi adayı destekliyor? (NULL olabilir)
    destek_verilen_aday_id BIGINT NULL,
    -- NEGATİF OY: Hangi adaya karşı? (Bir kullanıcı BİRDEN FAZLA adaya negatif oy verebilir)
    negatif_oy_verilen_aday_id BIGINT NULL,
    oy_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_adresi VARCHAR(45),
    -- Bir kullanıcı aynı oylamada tek bir destek oyu kullanabilir
    UNIQUE KEY tek_destek (oylama_id, kullanici_id),
    -- Bir kullanıcı aynı adaya birden fazla negatif oy veremez
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
```

## **4. ÇEKİRDEK İŞLEVLER ve NEGATİF OY SİSTEMİ**

### A. **Kullanıcı Sistemi:**
- Kayıt sayfası: eposta, şifre, ad-soyad, TC kimlik (güvenli hash), telefon
- Giriş sayfası: eposta/şifre ile authentication
- Profil sayfası: Kullanıcının üye olduğu topluluklar görüntülenmeli (örn: 'İstanbul Seçmeni', 'X Sendikası Üyesi', 'Y Meslek Odası Üyesi')
- Kullanıcı, birden fazla topluluğa üye olabilmeli

### B. **Oylama Oluşturma:**
- Yetkili kullanıcılar yeni oylama başlatabilir
- Oylama türü seçimi: 'Seçim', 'Kanun Teklifi', 'Referandum'
- Oylama başlığı, açıklaması, bitiş tarihi
- Topluluk seçimi: Ulusal, il, ilçe, sendika, meslek odası vb.
- Seçim türü seçilirse, aday listesi ekleme (aday adı, kısa açıklama)
- Referandum/Kanun teklifi türü seçilirse, seçenekler ekleme

### C. **OY KULLANMA ARAYÜZÜ ve MANTIĞI (EN ÖNEMLİ KISIM):**

#### Seçim Oylaması Sayfası:
```
[OYLAMA BAŞLIĞI]
[Açıklama]

ŞU ANKİ SEÇİMLERİNİZ:
✅ Desteklediğiniz Aday: [Aday Adı] (Varsa)
❌ Negatif Oy Verdiğiniz Adaylar: [Aday1], [Aday2]... (Varsa)

ADAY LİSTESİ:

1. [Aday A Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]

2. [Aday B Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]

3. [Aday C Adı]
   [Aday Açıklaması]
   [DESTEK OYU VER] [NEGATİF OY VER]
```

**İşlevler:**
1. **Destek Oyu**: Kullanıcı YALNIZCA BİR adaya destek oyu verebilir. Yeni destek oyu verildiğinde önceki destek oyu otomatik iptal edilir.
2. **Negatif Oy**: Kullanıcı İSTEDİĞİ KADAR adaya negatif oy verebilir. Her negatif oy butonu bağımsız çalışır (toggle mantığı: basılırsa negatif oy verilir, tekrar basılırsa geri alınır).
3. **Anlık Geri Bildirim**: Kullanıcının seçimleri anlık olarak sayfanın üstünde gösterilir.
4. **Topluluk Kontrolü**: Kullanıcı sadece kendi üyesi olduğu toplulukların oylamalarında oy kullanabilir.

### D. **SONUÇ HESAPLAMA:**

```php
<?php
// config/database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'dogrudan_irade';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function singleValueQuery($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
}

// functions/secim_fonksiyonlari.php
function secimSonucunuHesapla($oylama_id) {
    $db = new Database();
    
    // 1. Tüm adayları al
    $stmt = $db->query(
        "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
        [$oylama_id]
    );
    $adaylar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sonuclar = [];
    foreach ($adaylar as $aday) {
        // 2. Bu adaya verilen DESTEK oylarını say
        $destekSayisi = $db->singleValueQuery(
            "SELECT COUNT(*) FROM oy_kullanicilar 
             WHERE oylama_id = ? AND destek_verilen_aday_id = ?",
            [$oylama_id, $aday['id']]
        );

        // 3. Bu adaya verilen NEGATİF oyları say
        $negatifSayisi = $db->singleValueQuery(
            "SELECT COUNT(*) FROM oy_kullanicilar 
             WHERE oylama_id = ? AND negatif_oy_verilen_aday_id = ?",
            [$oylama_id, $aday['id']]
        );

        // 4. NET SKOR HESAPLA: Destek - Negatif
        $netSkor = $destekSayisi - $negatifSayisi;

        $sonuclar[] = [
            'aday_id' => $aday['id'],
            'aday_adi' => $aday['aday_adi'],
            'aday_aciklama' => $aday['aday_aciklama'],
            'destek_sayisi' => (int)$destekSayisi,
            'negatif_sayisi' => (int)$negatifSayisi,
            'net_skor' => (int)$netSkor
        ];
    }

    // 5. NET SKOR'a göre yüksekten düşüğe sırala
    usort($sonuclar, function($a, $b) {
        if ($b['net_skor'] == $a['net_skor']) {
            // Net skor eşitse, daha az negatif oy alan kazanır
            return $a['negatif_sayisi'] <=> $b['negatif_sayisi'];
        }
        return $b['net_skor'] <=> $a['net_skor'];
    });

    return $sonuclar; // İlk sıradaki kazanandır
}

function oylamaSonuclandiMi($oylama_id) {
    $db = new Database();
    $stmt = $db->query(
        "SELECT durum, bitis_tarihi FROM oylamalar WHERE id = ?",
        [$oylama_id]
    );
    $oylama = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($oylama['durum'] == 'sonuclandi') {
        return true;
    }
    
    // Bitiş tarihi geçmişse
    if ($oylama['bitis_tarihi'] && strtotime($oylama['bitis_tarihi']) < time()) {
        // Oylamayı sonuçlandır
        $db->query(
            "UPDATE oylamalar SET durum = 'sonuclandi' WHERE id = ?",
            [$oylama_id]
        );
        return true;
    }
    
    return false;
}
?>
```

**Sonuç Görüntüleme Sayfası:**
```
[OYLAMA BAŞLIĞI] - SONUÇLAR

TOPLAM OY KULLANAN: 1,250 kişi

SONUÇLAR:
1. ✅ KAZANAN: Aday B
   Destek: 400 oy | Negatif: 50 oy | Net Skor: 350
   (%32 destek, %4 negatif)

2. Aday A
   Destek: 600 oy | Negatif: 450 oy | Net Skor: 150
   (%48 destek, %36 negatif)

3. Aday C
   Destek: 250 oy | Negatif: 100 oy | Net Skor: 150
   (%20 destek, %8 negatif)
```

## **5. DİĞER MODÜLLER**

### **Kanun Teklifi/Referandum Modülü:**
- Seçenekler: 'Evet', 'Hayır' ve alternatif teklifler
- Her seçenek için ayrı negatif oy butonu
- Sonuç hesaplama: (Evet oyu - Negatif oy) vs (Hayır oyu - Negatif oy)

### **Kullanıcı Toplulukları:**
- Kullanıcı kayıt sırasında veya profil sayfasında topluluklara üye olabilir
- Her topluluk tipi için admin onayı veya otomatik doğrulama
- Topluluk bazında oylama filtreleme

### **Yönetim Paneli:**
- Oylama oluşturma/duzenleme/silme
- Oylamaları başlatma/bitirme
- Kullanıcı yönetimi (aktif/pasif yapma)
- Sistem loglarını görüntüleme
- Topluluk yönetimi

### **Güvenlik Modülleri:**
- IP bazlı oy kullanım sınırlaması
- Çoklu hesap tespiti
- Bot koruma (CAPTCHA)
- Oylama çakışması önleme

## **6. İSTENEN ÇIKTILAR**

1. **`kurulum.sql`**: Tüm veritabanı yapısını oluşturan SQL dosyası
2. **`index.php`**: Ana sayfa - Aktif oylamalar listesi
3. **`kayit.php` & `giris.php`**: Kullanıcı kayıt ve giriş sayfaları
4. **`profil.php`**: Kullanıcı profil ve topluluk yönetim sayfası
5. **`oylama_olustur.php`**: Yeni oylama başlatma sayfası
6. **`oylama_detay.php`**: Oylama detay ve oy kullanma sayfası (Destek/Negatif arayüzü ile)
7. **`sonuc.php`**: Sonuçları net skora göre gösteren sayfa
8. **`admin/`**: Yönetim paneli dosyaları
9. **`README.md`**: Yukarıdaki felsefi makaleyi içeren proje dokümantasyonu
10. **`config/`**: Veritabanı bağlantı ve ayar dosyaları
11. **`includes/`**: Fonksiyon ve sınıf dosyaları
12. **`assets/`**: CSS, JS ve resim dosyaları

## **EK NOTLAR**

- **Kod Standartları**: PSR-12 kodlama standardı, açıklayıcı yorum satırları, Türkçe değişken/fonksiyon isimlendirmesi
- **Modüler Yapı**: MVC benzeri bir yapı, her modül ayrı dosyalarda
- **Hata Yönetimi**: Tüm hatalar loglanacak, kullanıcıya uygun mesaj gösterilecek
- **Test**: Temel fonksiyonlar için unit testler
- **Dokümantasyon**: API ve veritabanı dokümantasyonu

**Proje Sloganı**: "Temsil Edilmek İstemiyoruz, Doğrudan Söz Sahibi Olmak İstiyoruz!"

---

## **7. SİSTEM DÜZENİ**

```
D:\dogrudan-irade-platformu
├── assets
│   ├── css
│   │   └── style.css
│   ├── js
│   │   └── main.js
│   └── img
│       ├── logo.png
│       └── favicon.ico
├── config
│   ├── database.php
│   ├── functions.php
│   └── secim_fonksiyonlari.php
├── includes
│   ├── auth.php
│   ├── footer.php
│   └── header.php
├── admin
│   ├── index.php
│   ├── oylamalar.php
│   ├── kullanicilar.php
│   ├── loglar.php
│   ├── ayarlar.php
│   ├── yedekleme.php
│   └── sidebar.php
├── api.php
├── bakim.php
├── bildirimler.php
├── cikis.php
├── giris.php
├── hata.php
├── iletisim.php
├── index.php
├── kayit.php
├── kurulum.sql
├── oylama_olustur.php
├── oylamalar.php
├── oylama_detay.php
├── profil.php
├── sifre_sifirla.php
└── sonuc.php
```

## ☕ Destek Olun / Support

Projemi beğendiyseniz, bana bir kahve ısmarlayarak destek olabilirsiniz!

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://buymeacoffee.com/metatronslove)

Teşekkürler! 🙏
