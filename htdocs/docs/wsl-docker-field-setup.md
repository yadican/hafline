# W11 + WSL + Docker saha / geliştirme kurulum planı

Bu dosya, Hafline’ı Windows 11 + WSL2 + Docker Desktop makinelerinde çalıştırmak için iş planıdır.  
Başka bir agent’a “bu md’yi uygula” diye verilebilir.

Hedef: kod, volume’lar ve (ileride) GPU’lu plaka OCR aynı WSL filesystem’inde; Windows path (`C:\` / `/mnt/c`) kullanılmaz.

---

## 0. Önkoşullar (host makine)

- [ ] Windows 11
- [ ] WSL2 + bir Linux distro (tercihen Ubuntu)
- [ ] Docker Desktop kurulu, **WSL2 backend** açık
- [ ] Docker Desktop → Settings → Resources → WSL integration → ilgili distro **açık**
- [ ] (Plaka OCR / GPU için) NVIDIA Windows driver (WSL CUDA destekli); WSL içinde `nvidia-smi` çalışır
- [ ] Git, WSL içinde kullanılacak (Windows Git’e güvenme)

Doğrulama (WSL terminal):

```bash
wsl.exe -l -v          # Windows PowerShell: VERSION 2
uname -a               # Linux
docker version
docker compose version
nvidia-smi             # GPU kullanılacaksa
```

---

## 1. Projeyi nereye koy

**Kural: tüm repo WSL ext4 altında.**

Önerilen path:

```text
/home/<user>/projects/hafline
```

- [ ] `C:\Users\...`, OneDrive, Desktop altına clone **yapma**
- [ ] `/mnt/c/...` üzerinden geliştirme / `docker compose` **yapma**

### Clone (WSL terminal)

```bash
cd ~
mkdir -p projects
cd ~/projects
git clone <GITHUB_REPO_URL> hafline
cd hafline
```

- [ ] Clone tamamlandı, `ls` ile `compose.yaml`, `htdocs/`, `docker/` görünüyor

### Editör

- [ ] Cursor / VS Code: **Remote - WSL** ile `~/projects/hafline` aç
- [ ] Windows’tan sadece `\\wsl$\Ubuntu\home\<user>\projects\hafline` ile gezilebilir; run/edit WSL tarafında

---

## 2. Docker stack (Laravel tarafı)

Repo kökünde (`compose.yaml` olan yer):

```bash
cd ~/projects/hafline
cp htdocs/.env.example htdocs/.env   # yoksa
# .env değerlerini saha/makineye göre düzenle
docker compose build
docker compose up -d
```

Bind mount’lar (zaten compose’ta):

| Host (WSL)     | Container              |
|----------------|------------------------|
| `./htdocs`     | `/var/www/html`        |
| `./data`       | `/var/www/data` + DB’ler |
| `./backups`    | backup çıktısı         |

- [ ] Servisler ayağa kalktı: `docker compose ps`
- [ ] Web: tarayıcıdan `http://localhost:8010` (compose portu)
- [ ] Gerekirse: `docker compose exec app php artisan migrate`, `composer install`, `npm` build — hep container veya WSL path üzerinden

### Yapma

- [ ] PowerShell’den Windows path ile `docker compose` çalıştırma
- [ ] Projeyi Windows’ta tutup WSL’den `/mnt/c/.../hafline` mount etme

---

## 3. Plaka OCR (Python + GPU) — mimari

Laravel OCR’ı **ayrı HTTP servisi** olarak çağırır:

- Client: `htdocs/app/Services/Api/Ocr/PlateScanClient.php`
- Config: `OCR_SCAN_URL`, `OCR_TIMEOUT` (`htdocs/.env.example`, `config/services.php`)
- Beklenen endpoint örneği: `/api/v1/ocr/scan-plate`
- Servis adı (mevcut yorum): `hafriyat-ocr`

**GPU sadece Python OCR sürecinde kullanılır.** PHP/nginx/postgres container’larına GPU verme.

### Yerleşim

```text
~/projects/
├── hafline/           # bu repo — Docker CPU stack
└── hafriyat-ocr/      # Python OCR (ayrı repo veya alt klasör) — GPU
```

- [ ] OCR kodu + model ağırlıkları WSL disk’te (`/mnt/c` değil)
- [ ] Laravel sadece HTTP client kalsın; OCR mantığı PHP’ye taşınmasın

### Bağlantı seçenekleri

**A — Geçici / tek makine geliştirme**  
OCR WSL’de host process (ör. `uvicorn` `:8000`):

```env
OCR_SCAN_URL=http://host.docker.internal:8000/api/v1/ocr/scan-plate
OCR_TIMEOUT=30
```

- [ ] PHP container’dan `host.docker.internal:8000` erişimi doğrula

**B — Saha / kalıcı (tercih)**  
OCR’ı Docker Compose’a GPU’lu servis olarak ekle (aynı network `hafline`):

```env
OCR_SCAN_URL=http://ocr:8000/api/v1/ocr/scan-plate
```

- [ ] `deploy.resources.reservations.devices` / NVIDIA runtime ile GPU passthrough
- [ ] Container içinde `nvidia-smi` veya framework CUDA smoke test
- [ ] `PlateScanClient` ile scan endpoint’i uçtan uca test

### GPU checklist (OCR makinesi)

- [ ] Windows NVIDIA driver güncel
- [ ] WSL: `nvidia-smi` OK
- [ ] Docker GPU: `docker run --rm --gpus all nvidia/cuda:12.x-base nvidia-smi` (sürüme göre imaj adı)
- [ ] OCR framework (PyTorch / Ultralytics / Paddle vb.) CUDA build ile kurulu
- [ ] CPU fallback isteniyorsa dokümante et; saha GPU’suz makine davranışı net olsun

---

## 4. Saha kurulum tekrarı (kopyala-yapıştır prosedür)

Her yeni W11 makinede sıra:

1. [ ] WSL2 + Ubuntu + Docker Desktop (WSL integration)
2. [ ] (OCR varsa) NVIDIA driver + `nvidia-smi`
3. [ ] `git clone` → `~/projects/hafline`
4. [ ] `htdocs/.env` saha değerleri (DB, JWT, `OCR_SCAN_URL`, …)
5. [ ] `docker compose up -d --build`
6. [ ] OCR servisini ayağa kaldır (A veya B)
7. [ ] Admin login + field haul scan smoke test
8. [ ] Backup path `./backups` yazılabilir mi kontrol

Aynı path convention her sahada: `/home/<user>/projects/hafline`.

---

## 5. Agent iş planı (özet görev listesi)

Agent’a verilecek sırayla görevler:

1. [ ] Önkoşulları doğrula (WSL2, Docker, isteğe GPU)
2. [ ] Repoyu `~/projects/hafline` altına clone et (Windows path kullanma)
3. [ ] `.env` oluştur / saha değerlerini doldur; `OCR_SCAN_URL`’i seçilen moda göre ayarla
4. [ ] `docker compose build && docker compose up -d`
5. [ ] Uygulama health: web port, migrate, temel login
6. [ ] (Varsa) `hafriyat-ocr`’ı WSL’de kur; GPU smoke test; Laravel’den scan çağrısı
7. [ ] İsteğe bağlı: `compose.yaml`’a GPU’lu `ocr` servisi ekle ve network’e bağla
8. [ ] Kısa saha runbook notu: clone path, portlar, `OCR_SCAN_URL`, bilinen sorunlar

---

## 6. Bilinçli olarak yapma

| Yanlış | Doğru |
|--------|--------|
| Repo `C:\Users\...\hafline` | `~/projects/hafline` (WSL) |
| Compose’u `/mnt/c/...` ile çalıştırma | WSL home path’ten compose |
| OCR modellerini Windows disk’te tutma | WSL ext4 |
| GPU’yu PHP container’a verme | GPU yalnızca Python OCR |
| OCR’ı Laravel içine gömme | HTTP servis + `OCR_SCAN_URL` |

---

## 7. İlgili mevcut dosyalar

- `compose.yaml` — app, scheduler, web, postgres, redis, backup
- `htdocs/.env.example` — `OCR_SCAN_URL`, `OCR_TIMEOUT`
- `htdocs/config/services.php` — `services.ocr`
- `htdocs/app/Services/Api/Ocr/PlateScanClient.php`
- `htdocs/docs/security-checklist.md` — güvenlik bitiş checklist’i (ayrı konu)

---

## Bitiş kriteri

- [ ] Proje sadece WSL path’ten çalışıyor
- [ ] `docker compose ps` healthy / running
- [ ] Tarayıcıdan admin erişimi OK
- [ ] OCR açıksa: scan endpoint Laravel üzerinden plaka döndürüyor; GPU kullanılıyorsa `nvidia-smi` ile süreç görünüyor
- [ ] Bu prosedür ikinci bir W11 makinede tekrarlabilir durumda
