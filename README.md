# php-yurticikargo-entegrasyon
# Yurtiçi Kargo API Kütüphanesi

Modern ve güvenli PHP/SOAP kargo işlemleri için geliştirilmiş hafif, test moduna sahip ve kullanışlı bir kütüphane. E-ticaret sistemleri için kargo iş emri oluşturma, durum sorgulama ve iptal işlemlerini nesne yönelimli (OOP) bir mimari ile sunar.

## ✨ Özellikler

- **SOAP tabanlı**: Yurtiçi Kargo Web Servisleri ile doğrudan ve güvenli iletişim.
- **Test (Sandbox) Modu**: Test aşamasında otomatik evrensel hesap (7111G...) kullanımı ile risksiz geliştirme.
- **Otomatik Veri Temizleme**: Telefon numarasındaki boşlukları silme ve il/ilçe isimlerini büyük harfe çevirme.
- **WSDL Strictness Koruması**: Eksik parametre hatalarına (`ttDocumentId` vb.) karşı otomatik şema doldurma.
- **Standart Yanıt Yapısı**: Tüm metotlardan dönen tek tip dizi (`array`) tabanlı sonuç sistemi.
- **Hata Ayıklama (Debug)**: Son XML istek (request) ve yanıtlarını (response) anında görüntüleme.

---

## 🚀 Kurulum

Sınıf dosyasını projenize dahil edin:

```php
require_once 'YurticiKargoAPI.php';
```

---

## ⚙️ Yapılandırma ve Başlatma

Sınıfı kullanacağınız ortama (Test veya Canlı) göre iki farklı şekilde başlatabilirsiniz.

### 1. Test Modunda Başlatma (Geliştirme Aşaması)
Üçüncü parametreyi `true` olarak gönderdiğinizde, sistem sizden kullanıcı adı ve şifre beklemez. Kargo talepleri gerçek panele düşmez ve kurye çağrılmaz.

```php
$kargo = new YurticiKargoAPI(null, null, true);

if ($kargo->isTestModeActive()) {
    echo "Sistem Sandbox modunda çalışıyor.";
}
```

### 2. Canlı Modda Başlatma (Üretim Aşaması)
Müşterilerinizden gerçek siparişler almaya başladığınızda, Yurtiçi Kargo şubenizden aldığınız API bilgilerini girerek sistemi canlıya alırsınız.

```php
$apiUser = 'FIRMA_KULLANICI_ADINIZ';
$apiPass = 'FIRMA_SIFRENIZ';

$kargo = new YurticiKargoAPI($apiUser, $apiPass);
```

---

## 📦 Yanıt (Response) Standartları

Kütüphane içerisindeki tüm metotlar, kullanımı kolaylaştırmak adına standart bir dizi döndürür:

```php
Array
(
    [status]  => true | false    // İşlemin genel başarı durumu
    [message] => string          // API'den dönen sonuç mesajı veya hata açıklaması
    [code]    => string | null   // (Varsa) API Hata Kodu
    [data]    => object | null   // (Varsa) API'den dönen tüm ham detay objesi
)
```

---

## 💻 Kullanım Kılavuzu

### 1. Kargo İş Emri Oluşturma (`createShipment`)
Yeni bir sipariş alındığında, Yurtiçi Kargo sistemine barkod talebi iletmek için kullanılır.

```php
// Gerekli verileri bir dizi içerisinde hazırlayın
$siparisData = [
    'cargoKey'   => 'SIP-10045',          // (ZORUNLU) Sisteminizdeki benzersiz sipariş numarası
    'invoiceKey' => 'FAT-2026114',        // (Opsiyonel) Fatura numarası
    'name'       => 'Mustafa Salman',       // (ZORUNLU) Alıcı Ad Soyad
    'address'    => 'Merkez Mah. Atatürk Cad. No:1', // (ZORUNLU) Alıcı Adresi
    'phone'      => '0 555 123 45 67',    // (ZORUNLU) Sınıf içindeki boşlukları otomatik temizler
    'city'       => 'istanbul',           // (ZORUNLU) Sınıf otomatik büyük harf yapar
    'district'   => 'kadıköy',            // (ZORUNLU) Sınıf otomatik büyük harf yapar
    'desi'       => 2,                    // (Opsiyonel) Paketin desisi (Varsayılan: 1)
    'cargoCount' => 1                     // (Opsiyonel) Paket sayısı (Varsayılan: 1)
];

// Metodu çağırın
$olustur = $kargo->createShipment($siparisData);

if ($olustur['status']) {
    echo "Kargo Başarıyla Oluştu!";
    // İleride sorgulamak için $siparisData['cargoKey'] değerini veritabanınıza kaydedin.
} else {
    echo "Kargo Oluşturulamadı: " . $olustur['message'];
}
```

### 2. Kargo Durumu Sorgulama (`queryShipment`)
Daha önce oluşturulmuş bir kargonun anlık durumunu, hareketlerini veya takip numarasını almak için kullanılır.

```php
$cargoKey = 'SIP-10045'; // Oluştururken verdiğiniz benzersiz anahtar

$sorgu = $kargo->queryShipment($cargoKey);

if ($sorgu['status']) {
    echo "Kargo Sistemde Bulundu!<br>";
    
    // Takip numarası atandıysa çekiyoruz
    $takipNo = $sorgu['data']->shippingDeliveryDetailVO->cargoTrackingNo ?? 'Henüz Atanmadı';
    echo "Takip Numarası: " . $takipNo;
} else {
    echo "Sorgulama Hatası: " . $sorgu['message'];
}
```

### 3. Kargo Siparişini İptal Etme (`cancelShipment`)
Şube gönderiyi henüz fiziksel olarak teslim almadan veya müşteri siparişi iptal ettiğinde, kargo kaydını sistemden silmek için kullanılır.

```php
$cargoKey = 'SIP-10045'; // İptal edilecek kargonun referans anahtarı

$iptal = $kargo->cancelShipment($cargoKey);

if ($iptal['status']) {
    echo "Kargo iş emri sistemden başarıyla silindi.";
} else {
    echo "İptal Başarısız: " . $iptal['message'];
}
```

### 4. Hata Ayıklama / Debugging (`getLastLogs`)
Sistemde beklenmeyen bir durum oluştuğunda, Yurtiçi Kargo sunucularına giden ham XML verisini ve gelen yanıtı görüntülemenizi sağlar.

```php
// Herhangi bir createShipment veya queryShipment işleminden hemen sonra çağırın:
$loglar = $kargo->getLastLogs();

echo "<h3>Giden İstek (Request):</h3>";
echo "<pre>" . htmlspecialchars($loglar['request']) . "</pre>";

echo "<h3>Gelen Yanıt (Response):</h3>";
echo "<pre>" . htmlspecialchars($loglar['response']) . "</pre>";
```
