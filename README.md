# Uluslararası FuAy Hastanesi - Diyabet Analiz Paneli

Bu proje, bir hastanenin diyabet verilerini analiz etmek ve görselleştirmek için geliştirilmiş web tabanlı, interaktif bir analiz panelidir (dashboard). Panel, sağlık profesyonelleri ve yöneticileri için hasta verilerinden anlamlı içgörüler elde etmeyi, risk gruplarını belirlemeyi ve veri odaklı kararlar almayı kolaylaştırmayı amaçlamaktadır.

![MIT License](https://img.shields.io/badge/license-MIT-green)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Chart\.js](https://img.shields.io/badge/Chart\.js-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)

---

## ✨ Özellikler

- **İnteraktif KPI Kartları:** Toplam hasta, ortalama VKİ, ortalama glikoz gibi temel metriklerin anlık takibi.
- **Dinamik Filtreleme:** Verileri Yaş, Vücut Kitle İndeksi (VKİ) ve Gebelik Sayısı'na göre anlık olarak filtreleme.
- **Gelişmiş Veri Görselleştirme:**
  - **Çizgi Grafik:** Yaşa göre ortalama glikoz seviyelerindeki değişimi gösterir.
  - **Pasta & Donut Grafikler:** VKİ ve diyabet riski dağılımlarını yüzde olarak sunar.
  - **Gauge (İbre) Grafik:** Genel yüksek risk yüzdesini etkili bir şekilde özetler.
  - **Yatay Çubuk Grafik:** Farklı yaş gruplarının ortalama kan basıncını karşılaştırır.
- **Pivot Tablo:** Yaş ve VKİ'nin kan basıncı üzerindeki birleşik etkisini analiz eder.
- **Güvenli Kod Yapısı:** SQL Injection saldırılarına karşı **Prepared Statements** kullanılarak geliştirilmiştir.
- **Duyarlı Tasarım (Responsive):** Bootstrap kullanılarak farklı ekran boyutlarına uyumlu hale getirilmiştir.

---

## 🛠️ Kullanılan Teknolojiler

- **Backend:** PHP
- **Frontend:** HTML5, CSS3, JavaScript
- **Veritabanı:** MySQL
- **Sunucu:** Apache (XAMPP ile çalıştırılmıştır)
- **Kütüphaneler:**
  - **Chart.js:** Veri görselleştirme ve grafikler için.
  - **Bootstrap:** Arayüz tasarımı ve duyarlılık için.
  - **Font Awesome:** İkonlar için.

---

## 🚀 Kurulum ve Çalıştırma

Bu projeyi yerel makinenizde çalıştırmak için aşağıdaki adımları izleyin.

### Gereksinimler

- [XAMPP](https://www.apachefriends.org/tr/index.html) (Apache ve MySQL sunucularını içerir)

### Adımlar

1.  **Projeyi Klonlayın:**
    ```bash
    git clone https://github.com/elaaky/diyabet-analiz-paneli.git
    ```

2.  **Proje Dosyalarını Taşıyın:**
    Klonladığınız proje klasörünü XAMPP'ın `htdocs` dizinine taşıyın. (Genellikle `C:\xampp\htdocs\` konumundadır).

3.  **Veritabanını Kurun:**
    - XAMPP Kontrol Panelini açın ve Apache ile MySQL modüllerini başlatın.
    - Tarayıcınızdan `http://localhost/phpmyadmin` adresine gidin.
    - Yeni bir veritabanı oluşturun ve adını `diyabet_analiz` olarak belirleyin.
    - Oluşturduğunuz veritabanını seçin ve "İçe Aktar" (Import) sekmesine tıklayın.
    - Proje dosyaları içinde bulunan `[VERITABANI_DOSYANIZ.sql]` dosyasını seçerek içe aktarma işlemini tamamlayın.
      _**Not:** Eğer bir `.sql` dosyanız yoksa, phpMyAdmin'den kendi veritabanınızı `diyabet_analiz` olarak dışa aktarıp projeye eklemeniz tavsiye edilir._

4.  **Veritabanı Bağlantısını Kontrol Edin:**
    Proje kodunda veritabanı bağlantı bilgileri aşağıdaki gibidir. Kendi yerel ayarlarınız farklıysa bu kısmı düzenleyebilirsiniz:
    ```php
    // index.php (veya bağlantı dosyanız)
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $database = "diyabet_analiz";
    ```

5.  **Paneli Çalıştırın:**
    Tarayıcınızın adres çubuğuna `http://localhost/dashboard/proje/diyabet-analiz-paneli/dashboard.php` yazarak panele erişin.

---

## 📄 Lisans

Bu proje MIT Lisansı ile lisanslanmıştır. Daha fazla bilgi için `LICENSE` dosyasına göz atın.

---

## 👤 İletişim

**[Elanur Akkaya]** - [\[GitHub Profil Linkiniz\]](https://github.com/elaaky)
