# Hafline Saha: geliştirme ortamı kurulumu

Bu dosya, mobil uygulamayı geliştirip test etmek için gereken servisleri ayağa kaldırmayı ve tek seferlik kurulumları anlatır. Hedef makine: **Windows 11 + WSL2 (Ubuntu 24.04) + Docker Desktop**. Mac farkları en sondadır.

---

## 1. Parçalar ve portlar

| Parça | Nerede çalışır | Adres | Görevi |
|---|---|---|---|
| Laravel + Postgres + Redis | Docker Desktop (`compose.yaml`) | `http://192.168.1.200:8010` | API ve admin paneli |
| Plaka OCR (Python) | Windows, `D:\PROJECT\Read-Plate` | `http://localhost:8000` | Fotoğraftan plaka okur. Laravel `host.docker.internal:8000` üzerinden çağırır. |
| Metro (Expo geliştirme sunucusu) | WSL, `mobile/` | `exp://192.168.1.200:8081` | Uygulama kodunu Expo Go'ya canlı yükler |
| Android emülatörü | Windows, Android Studio | `emulator-5554` | Telefon taşımadan test |

`192.168.1.200` bu bilgisayarın Wi-Fi adresidir. Adres değişirse bölüm 7'deki "IP değişti" satırına bak.

---

## 2. Her gün: servisleri başlatma sırası

### 2.1 Docker (Laravel)

1. Docker Desktop'ı aç ve motorun hazır olmasını bekle.
2. WSL terminalinde:

```bash
cd /home/dev/PROJECT/hafline
docker compose up -d
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8010/up   # 200 olmalı
```

- [ ] `/up` 200 dönüyor
- [ ] `/up` 404 ("File not found") dönüyorsa: `docker compose restart app scheduler` (bkz. bölüm 7)

### 2.2 Plaka OCR servisi

Windows PowerShell'de:

```powershell
cd D:\PROJECT\Read-Plate
.\venv\Scripts\Activate.ps1
python main.py
```

- [ ] Konsolda `Sistem hazır!` ve `YOLO: cuda  EasyOCR: cuda` yazıyor
- [ ] http://localhost:8000/docs açılıyor

İlk kurulum ve `.env` (`PLATFORM=win`) için `D:\PROJECT\Read-Plate\README.md`.

### 2.3 Metro (mobil geliştirme sunucusu)

WSL terminalinde:

```bash
cd /home/dev/PROJECT/hafline/mobile
export EXPO_TOKEN="$(tr -d '[:space:]' < ~/.expo-token)"
export REACT_NATIVE_PACKAGER_HOSTNAME=192.168.1.200
npx expo start --go --lan --port 8081
```

- `REACT_NATIVE_PACKAGER_HOSTNAME`: Metro WSL içinde çalıştığı için kendi adresini yanlış (WSL iç adresi) bulur; telefona bilgisayarın Wi-Fi adresini vermesi için gerekir.
- `EXPO_TOKEN`: iPhone'daki Expo Go imzalı proje ister; anahtar olmadan "You need to be signed in" hatası çıkar. Android için şart değil.

Kontrol (Windows'tan):

```powershell
curl.exe http://192.168.1.200:8081/status   # packager-status:running
```

### 2.4 Uygulamayı açma

| Cihaz | Nasıl |
|---|---|
| Android emülatörü | Android Studio > More Actions > Virtual Device Manager > **Medium_Phone_API_37.0** ▶. Expo Go'yu aç, "Recently opened" listesinden **Hafline Saha**. |
| Android telefon / tablet | Aynı Wi-Fi'ye bağlan. Expo Go > "Enter URL manually" > `exp://192.168.1.200:8081` |
| iPhone | Expo Go'da `yagotak` hesabıyla giriş yap. Safari'ye `exp://192.168.1.200:8081` yaz veya QR okut. Yerel ağ iznini ver. |

Kod değişince uygulama kendiliğinden yenilenir. Takılırsa geliştirici menüsü (emülatörde **Ctrl + M**, telefonda cihazı salla) > **Reload**.

Emülatöre komutla da açılabilir:

```powershell
$adb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe"
& "$env:LOCALAPPDATA\Android\Sdk\emulator\emulator.exe" -avd Medium_Phone_API_37.0
& $adb shell am start -a android.intent.action.VIEW -d "exp://192.168.1.200:8081"
```

Test kullanıcısı: `hafline.api` (saha fişi yetkisi var).

---

## 3. Tek seferlik kurulum (Windows makine)

Bu adımlar bu makinede yapıldı; yeni bir Windows makinede tekrar gerekir.

### 3.1 WSL içinde Node

Windows'taki `npm`'i WSL dosyalarıyla kullanma (yavaş ve hatalı). WSL'e kullanıcı bazlı Node kur:

```bash
curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
source ~/.bashrc
nvm install --lts
cd /home/dev/PROJECT/hafline/mobile && npm install
```

### 3.2 Ortam dosyası

```bash
cp mobile/.env.example mobile/.env.local
# EXPO_PUBLIC_API_URL=http://192.168.1.200:8010
```

`.env.local` git'e girmez. Giriş ekranındaki sunucu adresini önceden doldurur.

### 3.3 Expo hesabı ve anahtarı

- Hesap: `yagotak`. Proje: `@yagotak/hafline-saha` (`app.json` > `extra.eas.projectId`).
- WSL'de `npx expo login` tarayıcıyı açamıyor (Windows `cmd` URL'deki `&` karakterini bölüyor). Bunun yerine erişim anahtarı:
  1. https://expo.dev/settings/access-tokens > **Create token**
  2. `echo 'ANAHTAR' > ~/.expo-token && chmod 600 ~/.expo-token`
- Anahtarı hiçbir yere (sohbet, commit, ekran görüntüsü) yazma.

### 3.4 Telefonların bilgisayara ulaşması (ağ)

WSL varsayılan (NAT) ağ modunda çalışır; telefon WSL'deki Metro'ya doğrudan ulaşamaz. Çözüm Windows port yönlendirmesi. **Yönetici PowerShell**'de:

```powershell
# Wi-Fi ağı "Özel" olmalı (ev ağı). Ağ adı farklıysa Get-NetConnectionProfile ile bak.
Set-NetConnectionProfile -Name 'home_wifi' -NetworkCategory Private

# API (Docker Desktop zaten Windows'ta dinler, sadece güvenlik duvarı izni)
New-NetFirewallRule -Name 'Hafline-API-8010' -DisplayName 'Hafline API (8010)' `
  -Direction Inbound -Protocol TCP -LocalPort 8010 -Action Allow -Profile Private -RemoteAddress 192.168.1.0/24

# Metro: güvenlik duvarı + port yönlendirme
New-NetFirewallRule -Name 'Hafline-Metro-8081' -DisplayName 'Hafline Expo Metro (8081)' `
  -Direction Inbound -Protocol TCP -LocalPort 8081 -Action Allow -Profile Private -RemoteAddress 192.168.1.0/24
netsh interface portproxy add v4tov6 listenaddress=192.168.1.200 listenport=8081 connectaddress=::1 connectport=8081
```

- **v4tov6 ve `::1`**: WSL'in Windows'a açtığı köprü (`wslrelay`) Metro'yu IPv6 `::1` üzerinde dinler. `127.0.0.1`'e yönlendirme bağlantıyı koparır.
- Kontrol: `netsh interface portproxy show all`
- **WSL `networkingMode=mirrored` KULLANMA.** Docker Desktop ile uyumsuz; WSL entegrasyonu kopar, bind mount'lar boş gelir.

### 3.5 Android Studio ve emülatör

```powershell
winget install --id Google.AndroidStudio -e
```

İlk açılışta sihirbaz: **Standard** kurulum, tüm lisansları **Accept**. SDK `%LOCALAPPDATA%\Android\Sdk` altına iner ve **Medium_Phone_API_37.0** sanal cihazı hazır gelir.

Emülatöre Expo Go (SDK 58) kurulumu:

```powershell
$v = Invoke-RestMethod https://api.expo.dev/v2/versions/latest
$url = $v.data.sdkVersions.'58.0.0'.androidClientUrl
Invoke-WebRequest $url -OutFile "$env:TEMP\expo-go.apk"
& "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe" install -r "$env:TEMP\expo-go.apk"
```

Emülatör Android 37 kullanır. Sunmi V2 Pro Android 7.1'dir; eski sürüm davranışı ve yazıcı gerçek cihazda test edilir.

---

## 4. Test verisi

### 4.1 Plaka görselleri

- `D:\plaka-test\` klasöründe test görselleri var.
- `D:\plaka-test\make_plates.py` sistemdeki plakalardan yapay plaka görseli üretir (OCR venv'i ile):

```powershell
& 'D:\PROJECT\Read-Plate\venv\Scripts\python.exe' 'D:\plaka-test\make_plates.py'
```

Klasördeki tüm görselleri emülatör galerisine (`Pictures/plaka-test` albümü) atmak; aynı isimli dosyaların üzerine yazar:

```powershell
$adb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe"
& $adb shell mkdir -p /sdcard/Pictures/plaka-test
Get-ChildItem D:\plaka-test -File -Include *.jpg,*.jpeg,*.png -Recurse | ForEach-Object {
  $t = "/sdcard/Pictures/plaka-test/$($_.Name)"
  & $adb push $_.FullName $t | Out-Null
  & $adb shell "am broadcast -a android.intent.action.MEDIA_SCANNER_SCAN_FILE -d 'file://$($t -replace ' ','%20')'" | Out-Null
}
```

- [ ] Uygulamada galeri seçicisini kapatıp açınca görseller `plaka-test` albümünde görünüyor
- Tek görsel için en hızlısı: dosyayı emülatör penceresine sürükle-bırak (İndirilenler klasörüne düşer)

### 4.2 Bugün sahibi olan kayıtlı plakalar

Fiş kesilebilmesi için plaka kayıtlı ve o gün bir sahibi olmalı:

```bash
docker exec hafline-postgres psql -U hafline -d hafline -At -c "
  select v.plate from vehicles v
  join vehicle_ownerships o on o.vehicle_id = v.id
  where v.deleted_at is null and o.started_on <= current_date
    and (o.ended_on is null or o.ended_on >= current_date)
  order by v.plate limit 10"
```

Mobilden kesilen fişler geliştirme veritabanına gerçek kayıt olarak yazılır; admin panelinde **Taşıma fişleri** altında görünür.

---

## 5. API tipleri

Swagger değişince mobil tipleri yeniden üret:

```bash
cd /home/dev/PROJECT/hafline/mobile
npm run gen:api        # htdocs/storage/api-docs/api-docs.json -> src/api/schema.d.ts
npm run typecheck
npx expo lint
```

Swagger'da cevap şeması olmayan endpoint'lerin tipleri `src/api/types.ts` içinde elle tutulur.

---

## 6. Test APK'sı (EAS Build)

APK, Expo Go ve bilgisayar olmadan çalışan bağımsız uygulamadır. Kod derleme anında içine gömülür; sonraki değişiklikler APK'da ancak yeni derlemeyle görünür. Geliştirirken ana ekrandaki **Hafline Saha** ikonunu değil, Expo Go'yu kullan (bölüm 2.4). Bulutta derlenir:

```bash
cd /home/dev/PROJECT/hafline/mobile
export EXPO_TOKEN="$(tr -d '[:space:]' < ~/.expo-token)"
npx eas-cli@latest build -p android --profile preview --non-interactive
```

- Derleme 10–25 dakika sürer. Takip: https://expo.dev/accounts/yagotak/projects/hafline-saha/builds
- Bitince sayfadaki **Install** / QR ile telefona kurulur ya da APK indirilip `adb install -r dosya.apk`.
- Telefonda "bilinmeyen kaynak" izni ve Play Protect uyarısı ("Yine de yükle") normaldir.

Önemli ayarlar:

- **Kökteki `.easignore`**: EAS git deposunun tamamını yükler. Bu dosya sadece `mobile/` klasörünün gitmesini sağlar. `!/mobile` satırı sonunda `/` olmadan yazılmalı; `!/mobile/` yazılırsa boş paket gider ve derleme saniyeler içinde hata verir.
- **`eas.json` > `preview`**: `EXPO_PUBLIC_API_URL` derlemeye gömülür (`.env.local` yüklenmez).
- **`usesCleartextTraffic: true`** (`app.json`): `http://` bağlantısı için. Müşteri ortamında HTTPS'e geçilince kaldırılmalı.
- **İmza anahtarı (keystore)** Expo sunucusunda. Müşteri dağıtımından önce yedeği indirilmeli: `npx eas-cli@latest credentials -p android`. Kaybolursa mevcut kurulumlar güncellenemez.
- `versionCode` her derlemede otomatik artar (`appVersionSource: remote`).

---

## 7. Sorun giderme

| Belirti | Sebep | Çözüm |
|---|---|---|
| Uygulamada "Sunucuya ulaşılamadı" | Telefon API'ye erişemiyor | Telefonun tarayıcısında `http://192.168.1.200:8010/up` aç. Açılmıyorsa Wi-Fi, güvenlik duvarı kuralı, ağın "Özel" olması. Açılıyorsa sorun uygulamada; Metro kayıtlarına bak (`WARN API request failed`). |
| iPhone Expo Go: "You need to be signed in" | Telefon veya Metro Expo'ya giriş yapmamış | Telefonda `yagotak` ile giriş. Metro'yu `EXPO_TOKEN` ile başlat. |
| Telefon Metro'ya bağlanamıyor | Port yönlendirme yok / yanlış | `netsh interface portproxy show all` > `192.168.1.200:8081 -> ::1:8081` olmalı |
| IP değişti (`192.168.1.200` değil) | Modem yeni adres verdi | Port yönlendirmeyi yeni adresle yeniden ekle, `.env.local` ve `eas.json` içindeki adresi güncelle. Kalıcı çözüm: modemde bu bilgisayara IP rezervasyonu. |
| `/up` 404 "File not found" | Docker, WSL entegrasyonu hazır olmadan container başlattı; proje klasörü boş bağlandı | `docker compose restart app scheduler` (gerekirse `web db_backup` de) |
| WSL'de `docker` bulunamıyor, `/var/run/docker.sock` yok | Docker Desktop'ta Ubuntu entegrasyonu kapandı | Docker Desktop > Settings > Resources > WSL Integration > Ubuntu-24.04 aç, Apply. Ayar dosyasını (`%APPDATA%\Docker\settings-store.json`) PowerShell `Set-Content` ile **düzenleme**: BOM ekler, Docker tüm ayarları sıfırlar (veri klasörü `D:\Docker-Containers\DockerDesktopWSL` dahil). |
| `wsl --shutdown` sonrası Docker bozuldu | Docker Desktop açıkken WSL kapatıldı | Önce `docker desktop stop`, sonra `wsl --shutdown`, sonra `docker desktop start` |
| OCR: torch `LoadLibraryExW` hatası | İlk yükleme yavaş / yarıda kesildi | Tekrar başlat. Sürerse hata mesajının **son satırına** bak. |
| Tarama "Plaka okuma servisine ulaşılamadı" (502) | OCR servisi kapalı | 2.2'deki adımlarla başlat. Şu an bu durumda fiş kesilemiyor (tarama kaydı oluşmuyor); backend'de çözülecek. |
| Kod değişikliği emülatörde görünmüyor | Açık olan uygulama Expo Go değil, kurulu APK (`com.hafline.saha`) | APK'yı kapat, Expo Go'dan aç (bölüm 2.4). Metro `CI=1` ile başlatıldıysa dosya izleme kapalıdır; `CI` olmadan yeniden başlat. |
| APK açılınca siyah ekran | Emülatör ekranı uykuda | Ekrana tıkla / `adb shell input keyevent KEYCODE_WAKEUP` |
| Token 1 saatte doluyor | API'de yenileme endpoint'i yok | Backend'de `auth/refresh` eklenecek; şimdilik tekrar giriş. |

---

## 8. Mac'te çalışırken

- Port yönlendirme, güvenlik duvarı kuralları ve `REACT_NATIVE_PACKAGER_HOSTNAME` gerekmez; `npx expo start` ağ adresini kendisi bulur ve terminalde QR gösterir.
- `npx expo login` tarayıcıyla sorunsuz çalışır; `~/.expo-token` gerekmez.
- `mobile/.env.local` içindeki adresi Mac'in adresiyle değiştir: `ipconfig getifaddr en0`
- iOS Simülatörü: Xcode kuruluysa Metro terminalinde `i`. Android emülatörü: `a`.
- Kafka/RabbitMQ compose'tan çıkarıldı; ilk açılışta `docker compose up -d --remove-orphans` ve `data/kafka`, `data/rabbitmq` klasörlerini sil.
- OCR için `D:\PROJECT\Read-Plate\README.md` > macOS bölümü (`PLATFORM=mac`).
