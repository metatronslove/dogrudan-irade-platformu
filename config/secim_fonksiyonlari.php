<?php
require_once 'database.php';

class SecimFonksiyonlari {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Oylama sonuçlarını hesapla (NEGATİF OY SİSTEMİ)
    public function secimSonucunuHesapla($oylama_id) {
        // 1. Tüm adayları al
        $stmt = $this->db->query(
            "SELECT * FROM adaylar WHERE oylama_id = ? ORDER BY aday_adi",
            [$oylama_id]
        );
        $adaylar = $stmt->fetchAll();

        $sonuclar = [];
        $toplamOyKullanan = 0;

        foreach ($adaylar as $aday) {
            // 2. Bu adaya verilen DESTEK oylarını say
            $destekSayisi = $this->db->singleValueQuery(
                "SELECT COUNT(*) FROM oy_kullanicilar 
                 WHERE oylama_id = ? AND destek_verilen_aday_id = ?",
                [$oylama_id, $aday['id']]
            );

            // 3. Bu adaya verilen NEGATİF oyları say
            $negatifSayisi = $this->db->singleValueQuery(
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

        // 5. Toplam oy kullanan sayısını bul
        $toplamOyKullanan = $this->db->singleValueQuery(
            "SELECT COUNT(DISTINCT kullanici_id) FROM oy_kullanicilar WHERE oylama_id = ?",
            [$oylama_id]
        );

        // 6. NET SKOR'a göre yüksekten düşüğe sırala
        usort($sonuclar, function($a, $b) {
            if ($b['net_skor'] == $a['net_skor']) {
                // Net skor eşitse, daha az negatif oy alan kazanır
                return $a['negatif_sayisi'] <=> $b['negatif_sayisi'];
            }
            return $b['net_skor'] <=> $a['net_skor'];
        });

        return [
            'sonuclar' => $sonuclar,
            'toplam_oy_kullanan' => $toplamOyKullanan,
            'kazanan' => !empty($sonuclar) ? $sonuclar[0] : null
        ];
    }

    // Kullanıcının oy durumunu al
    public function kullaniciOyDurumu($oylama_id, $kullanici_id) {
        $destekOy = $this->db->query(
            "SELECT a.* FROM oy_kullanicilar ok 
             JOIN adaylar a ON ok.destek_verilen_aday_id = a.id 
             WHERE ok.oylama_id = ? AND ok.kullanici_id = ?",
            [$oylama_id, $kullanici_id]
        )->fetch();

        $negatifOylar = $this->db->query(
            "SELECT a.* FROM oy_kullanicilar ok 
             JOIN adaylar a ON ok.negatif_oy_verilen_aday_id = a.id 
             WHERE ok.oylama_id = ? AND ok.kullanici_id = ?",
            [$oylama_id, $kullanici_id]
        )->fetchAll();

        return [
            'destek_oy' => $destekOy,
            'negatif_oylar' => $negatifOylar
        ];
    }

    // Destek oyu ver
    public function destekOyVer($oylama_id, $kullanici_id, $aday_id) {
        try {
            // Önce varsa eski destek oyunu sil
            $this->db->query(
                "DELETE FROM oy_kullanicilar 
                 WHERE oylama_id = ? AND kullanici_id = ? AND destek_verilen_aday_id IS NOT NULL",
                [$oylama_id, $kullanici_id]
            );

            // Yeni destek oyunu ekle
            $this->db->query(
                "INSERT INTO oy_kullanicilar (oylama_id, kullanici_id, destek_verilen_aday_id, ip_adresi) 
                 VALUES (?, ?, ?, ?)",
                [$oylama_id, $kullanici_id, $aday_id, $_SERVER['REMOTE_ADDR']]
            );

            // Log kaydı
            $this->logIslem($kullanici_id, 'destek_oy_verildi', "Oylama: $oylama_id, Aday: $aday_id");

            return true;
        } catch (Exception $e) {
            error_log("Destek oy hatası: " . $e->getMessage());
            return false;
        }
    }

    // Negatif oy ver/al
    public function negatifOyToggle($oylama_id, $kullanici_id, $aday_id) {
        try {
            // Önce bu adaya zaten negatif oy verilmiş mi kontrol et
            $existing = $this->db->singleValueQuery(
                "SELECT COUNT(*) FROM oy_kullanicilar 
                 WHERE oylama_id = ? AND kullanici_id = ? AND negatif_oy_verilen_aday_id = ?",
                [$oylama_id, $kullanici_id, $aday_id]
            );

            if ($existing > 0) {
                // Varsa sil (toggle off)
                $this->db->query(
                    "DELETE FROM oy_kullanicilar 
                     WHERE oylama_id = ? AND kullanici_id = ? AND negatif_oy_verilen_aday_id = ?",
                    [$oylama_id, $kullanici_id, $aday_id]
                );
                $islem = 'negatif_oy_kaldirildi';
            } else {
                // Yoksa ekle (toggle on)
                $this->db->query(
                    "INSERT INTO oy_kullanicilar (oylama_id, kullanici_id, negatif_oy_verilen_aday_id, ip_adresi) 
                     VALUES (?, ?, ?, ?)",
                    [$oylama_id, $kullanici_id, $aday_id, $_SERVER['REMOTE_ADDR']]
                );
                $islem = 'negatif_oy_verildi';
            }

            // Log kaydı
            $this->logIslem($kullanici_id, $islem, "Oylama: $oylama_id, Aday: $aday_id");

            return true;
        } catch (Exception $e) {
            error_log("Negatif oy hatası: " . $e->getMessage());
            return false;
        }
    }

    private function logIslem($kullanici_id, $islem_tipi, $aciklama = '') {
        $this->db->query(
            "INSERT INTO sistem_loglari (kullanici_id, islem_tipi, aciklama, ip_adresi, user_agent) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $kullanici_id,
                $islem_tipi,
                $aciklama,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }
}
?>
