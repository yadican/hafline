# Mobil uygulamayı çalıştırma (Expo Go): kısa yol

Her yerde aynı süreç: **ayar dosyası → `npm run dev` → telefonda aç.** Ev, ofis, müşteri Wi-Fi'si, telefon hotspot'u fark etmez; Mac ve Windows (WSL) için komutlar aynıdır.

İlk kurulum ve ayrıntılar: [dev-setup.md](dev-setup.md).

---

## 1. Ayar dosyası: `mobile/.env.local`

Her bilgisayarda bir kez oluşturulur, git'e girmez:

```bash
cd mobile            # Windows: /home/dev/PROJECT/hafline/mobile, Mac: ~/projects/hafline/mobile
cp .env.example .env.local
```

| Ayar | Varsayılan | Ne zaman değiştirilir |
|---|---|---|
| `DEV_HOST_IP` | `auto` | Neredeyse hiç. `auto` her başlatmada bilgisayarın o anki ağ adresini bulur. Yanlış ağı seçerse (ör. hem kablo hem Wi-Fi bağlıysa) adresi elle yaz: `DEV_HOST_IP=10.0.0.25` |
| `METRO_PORT` | `8081` | 8081 başka bir programda doluysa |
| `API_PORT` | `8010` | `compose.yaml`'daki Laravel portu değişirse |
| `API_URL` | (boş) | Uygulama bu bilgisayar yerine başka bir sunucuya bağlansın istenirse, ör. `API_URL=https://test.hafline.com` |

Ağ değişince **dosyaya dokunmak gerekmez**; sadece `npm run dev`'i yeniden çalıştır.

---

## 2. Her çalıştırmada

| Adım | Windows (Ubuntu/WSL terminali) | Mac (Terminal) |
|---|---|---|
| 1. Docker (API + veritabanı) | Docker Desktop'ı aç, sonra `cd /home/dev/PROJECT/hafline && docker compose up -d` | OrbStack'i (veya Docker Desktop'ı) aç, sonra `cd ~/projects/hafline && docker compose up -d` |
| 2. Plaka OCR (fotoğraftan plaka okuma) | PowerShell: `cd D:\PROJECT\Read-Plate; .\venv\Scripts\Activate.ps1; python main.py` | `Read-Plate` README > macOS bölümü |
| 3. Expo | `cd mobile && npm run dev` | `cd mobile && npm run dev` |
| 4. Telefon | Terminaldeki QR'ı okut veya `Expo Go` adresini aç | aynı |
| 5. Uygulamada giriş | Sunucu alanı terminaldeki `Server (app)` adresiyle dolu gelir | aynı |

`npm run dev` başlarken şunu yazar:

```
  Expo Go      : exp://172.20.10.2:8081
  Server (app) : http://172.20.10.2:8010
  Phone check  : http://172.20.10.2:8010/up
```

- **Durdurmak:** terminalde `Ctrl + C`.
- **Ağ değişti / laptop uyudu, adres değişti:** `Ctrl + C`, tekrar `npm run dev`, telefonda uygulamayı yeniden aç. Expo Go'da uygulama her açılışta `npm run dev`'in verdiği sunucu adresini kullanır; oturum açık kalır. (APK'da kayıtlı adres kullanılır; orada çıkış yapıp yeni adresle girilir.)
- **Önbelleği temizleyerek başlatmak:** `npm run dev -- --clear`
- **Windows'ta yeni bir ağda ilk çalıştırma:** bir kez Windows yönetici onayı çıkar, "Evet" de. Aynı ağda bir daha sormaz.
- **Mac'te ilk çalıştırma:** macOS güvenlik duvarı "node gelen bağlantıları kabul etsin mi?" diye sorarsa **İzin Ver**.

---

## 3. Sahada demo

| Durum | Ne yap |
|---|---|
| Müşterinin Wi-Fi'si | Laptop ve telefon aynı Wi-Fi'ye bağlanır, `npm run dev`. Önce telefonun tarayıcısında `Phone check` adresini aç: sayfa açılıyorsa hazır. |
| Müşteri Wi-Fi'si telefonu laptopa bağlatmıyor (`Phone check` açılmıyor) | Misafir ağları cihazları birbirinden yalıtır; ağ ayarıyla çözülmez. Kendi hotspot'una geç (aşağıdaki satır). |
| Mobil internet (telefon hotspot'u) | Laptopu hotspot'a bağla, `npm run dev`. Demo telefonu da aynı hotspot'a bağlı olmalı. Hotspot'u açan iPhone'un kendisinde Expo Go bağlanamazsa: hotspot'u başka bir telefondan aç (demo telefonu da ona bağlansın) veya cep modemi kullan. |

Demo öncesi kontrol listesi:

- [ ] `docker compose up -d` çalıştı, `npm run dev` uyarı vermedi (API çalışmıyorsa `WARNING: API is not answering` yazar)
- [ ] Telefonun tarayıcısında `Phone check` adresi açılıyor
- [ ] Expo Go'da uygulama açıldı, yeni sunucu adresiyle giriş yapıldı
- [ ] Plaka okuma kullanılacaksa OCR servisi açık

---

## 4. Komut dosyaları

| Dosya | Ne yapar | Elle çalıştırılır mı |
|---|---|---|
| `mobile/.env.local` | Bu bilgisayarın ayarları (bölüm 1). Git'e girmez, her bilgisayarda ayrı. | Düzenlenir |
| `mobile/.env.example` | `.env.local` şablonu. Yeni ayar eklenirse buraya da eklenir. | Hayır |
| `mobile/scripts/dev-start.sh` | `npm run dev`'in çalıştırdığı betik. `.env.local`'ı okur; `DEV_HOST_IP=auto` ise adresi bulur (Mac: varsayılan ağ arayüzü, Windows: internete çıkan bağlantı). Windows'ta ağ hazır değilse `dev-network.ps1`'i çağırır. API'nin cevap verdiğini kontrol eder, uygulamanın sunucu adresini (`EXPO_PUBLIC_API_URL`) ayarlar, `~/.expo-token` varsa yükler ve Metro'yu `--go --lan` ile başlatır. | Hayır, `npm run dev` |
| `mobile/scripts/dev-network.ps1` | Sadece Windows. Telefonların WSL'deki Metro'ya ulaşması için: ağı "Özel" yapar, 8010/8081 portlarını yerel ağa açar (güvenlik duvarı), Metro için port yönlendirmesi ekler. Yönetici onayını kendisi ister. `-Check` ile sadece kontrol eder. | Genelde hayır; `npm run dev` gerektiğinde çağırır |

`dev-network.ps1`'i elle çalıştırmak (Windows PowerShell):

```powershell
# Kontrol: adresi yazar, hazırsa çıkış kodu 0, kurulum gerekiyorsa 3
powershell -ExecutionPolicy Bypass -File \\wsl.localhost\Ubuntu-24.04\home\dev\PROJECT\hafline\mobile\scripts\dev-network.ps1 -Check

# Kurulum (yönetici onayı ister)
powershell -ExecutionPolicy Bypass -File \\wsl.localhost\Ubuntu-24.04\home\dev\PROJECT\hafline\mobile\scripts\dev-network.ps1
```

---

## 5. Bir şey ters giderse

| Belirti | Çözüm |
|---|---|
| Expo Go: "There was a problem running the requested app" / "Could not connect" | Eski adres açılmış. Terminaldeki güncel `Expo Go` adresini aç; "Recently opened" listesindeki eski kayıtları kullanma. |
| Telefon `Phone check` adresini açamıyor | Telefon ve bilgisayar aynı ağda mı? Misafir ağı mı (bölüm 3)? iPhone: Ayarlar > Gizlilik ve Güvenlik > Yerel Ağ > Expo Go açık mı? |
| `auto` yanlış adresi buldu | `.env.local` içine `DEV_HOST_IP=<doğru adres>` yaz. Windows'ta adresler: `ipconfig`; Mac'te: `ipconfig getifaddr en0` |
| `DEV_HOST_IP=... is not an address of this PC` | Elle yazılan adres artık geçerli değil (ağ değişmiş). `auto` yap veya yeni adresi yaz. |
| Uygulama açılıyor ama sayfalar sürekli yükleniyor / "Sunucuya ulaşılamadı" | Uygulama eski adresi kullanıyor. Expo Go'da uygulamayı tamamen kapatıp aç (veya cihazı salla > **Reload**). Sürerse telefonda `Phone check` adresini dene. |
| `WARNING: API is not answering` | Docker kapalı: `docker compose up -d`. Ayrıntı: [dev-setup.md](dev-setup.md) bölüm 7. |
