# Prod’a geçiş checklist’i (performans + ops)

Bu dosya, Hafline’ı geliştirme stack’inden production’a taşırken yapılacak altyapı işlerinin planıdır.  
Başka bir agent’a “bu md’yi uygula” diye verilebilir.

Kapsam: Docker imajı, compose, PHP/Laravel runtime ayarları, veri/izinler.  
Uygulama seviyesindeki güvenlik maddeleri ayrı dosyada: `htdocs/docs/security-checklist.md`.

Kural: **geliştirme deneyimi bozulmayacak.** Dev stack (`compose.yaml`) bugünkü gibi çalışmaya devam etmeli; prod farkları build argümanı / ayrı compose dosyası ile gelmeli.

---

## 1. Xdebug prod imajında olmayacak (yüksek)

Durum: `docker/php/Dockerfile` Xdebug’ı koşulsuz kurup enable ediyor; `docker/php/xdebug.ini` → `xdebug.mode=debug,develop`.  
Ölçüm (W11 + WSL, aynı container): Eloquent sorgu döngüsü ~2× yavaş, saf PHP ~10× yavaş. `develop` modu her HTTP isteğinde, scheduler’da ve seeder’da aktif.  
Ayrıca `debug` modu açık olduğu için `XDEBUG_TRIGGER` gönderen herkes 9003’e debug bağlantısı tetikleyebilir.

- [ ] Dockerfile’a `ARG INSTALL_XDEBUG=false` ekle; `pecl install xdebug`, `docker-php-ext-enable xdebug` ve `COPY xdebug.ini` sadece `true` iken çalışsın
- [ ] Dev `compose.yaml` → `build.args.INSTALL_XDEBUG: "true"` (PHPStorm trigger debug aynen çalışmalı)
- [x] Dev’de bile `develop` modunu varsayılan kapat: `compose.yaml` → `app` ve `scheduler` servislerinde `XDEBUG_MODE: ${XDEBUG_MODE:-off}` (W11 + WSL ölçümü: `/admin/banks` 162 ms → 50 ms)

  Her makinede (macOS / W11) `git pull` sonrası uygula ve doğrula:

  ```bash
  docker compose up -d app scheduler
  docker compose exec app printenv XDEBUG_MODE   # off
  curl -s -o /dev/null -w '%{time_total}\n' http://localhost:8010/up
  ```

  `php -i` / `ini_get('xdebug.mode')` hâlâ `debug,develop` gösterir; geçerli olan `XDEBUG_MODE` ortam değişkenidir.

  Step debug gerektiğinde aç, iş bitince kapat:

  ```bash
  XDEBUG_MODE=debug docker compose up -d app   # aç
  docker compose up -d app                     # kapat (off)
  ```

- [ ] Prod imajında doğrula: `php -m | grep -i xdebug` boş dönmeli

Kontrol: `docker/php/Dockerfile`, `docker/php/xdebug.ini`, `compose.yaml`.

---

## 2. Prod compose dosyası (yüksek)

- [ ] `compose.prod.yaml` (override) oluştur; prod’da `docker compose -f compose.yaml -f compose.prod.yaml up -d`
- [ ] Prod imajı: `INSTALL_XDEBUG=false`
- [ ] **Port yayını:** `postgres` (5433), `redis` (6380), `rabbitmq` (5673 / 15673), `kafka` (9093) şu an `0.0.0.0`’a açık. Prod’da ya hiç publish etme ya da `127.0.0.1:` ile sınırla. Dışarı açık tek port `web` olmalı
- [ ] **Şifreler:** `compose.yaml` içinde `hafline/hafline` sabit (postgres, db_backup, rabbitmq). Prod’da kök `.env` / secret’tan oku (`${POSTGRES_PASSWORD}` vb.); `htdocs/.env` ile aynı değerler
- [ ] **İmaj sürümleri sabitle:** `postgres:latest`, `redis:latest`, `apache/kafka:latest` yerine major/minor sabit tag. Özellikle Postgres `latest` major atlarsa `./data/postgres` açılmaz
- [ ] `restart: unless-stopped` tüm servislerde kalsın; `app` ve `web` için healthcheck ekle
- [ ] Log rotasyonu: Docker `logging.options.max-size` / `max-file`

Kontrol: `compose.yaml`, yeni `compose.prod.yaml`.

---

## 3. Laravel runtime ayarları (yüksek)

`htdocs/.env` prod değerleri:

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` gerçek domain
- [ ] `LOG_LEVEL=warning` (veya `error`), `LOG_STACK=daily`
- [ ] `L5_SWAGGER_GENERATE_ALWAYS=false` — `true` iken her istekte spec yeniden üretilir (yavaş). Kök `.env`’de `true` duruyor; o dosya compose için, Laravel `htdocs/.env` okur — ikisini de düzelt
- [ ] `APP_KEY`, `JWT_SECRET` prod’a özel üretilmiş (dev’den kopyalanmamış)

Deploy adımları (container içinde):

- [ ] `composer install --no-dev --optimize-autoloader --no-interaction` (debugbar, boost, pail vb. dev paketleri prod’da olmamalı)
- [ ] `php artisan optimize` (config/route/view/event cache)
- [ ] `php artisan migrate --force`
- [ ] **`db:seed` çalıştırma.** `DatabaseSeeder` → `LoadTestSeeder` (~390k sahte kayıt) çağırıyor; factory varsayılan şifresi sabit. Prod’da sadece gerekli seeder’lar tek tek: `RolePermissionSeeder`, `LocationSeeder`, `VehicleClassSeeder`, `CashCategorySeeder`; admin kullanıcısı güçlü şifreyle elle

Kontrol: `htdocs/.env.example`, kök `.env`, `config/l5-swagger.php`, `database/seeders/DatabaseSeeder.php`.

---

## 4. PHP / OPcache (orta)

- [ ] Prod’da OPcache açık ve doğrula: `php -i | grep -E 'opcache.enable|opcache.jit'`
- [ ] Prod ini (sadece prod imajına): `opcache.validate_timestamps=0`, `opcache.memory_consumption=256`, `opcache.max_accelerated_files=20000`; deploy sonrası container restart ile cache yenilenir
- [ ] `realpath_cache_size=4096K`, `realpath_cache_ttl=600`
- [ ] PHP-FPM `pm` ayarları (`pm.max_children`) sunucu RAM’ine göre

İmajda `php.ini` yok (resmi imaj varsayılanları). Ölçülen değerler: `display_errors=1`, `expose_php=1`, `memory_limit=128M`, `date.timezone=UTC`.

- [ ] Prod imajında `php.ini-production` baz alınsın: `cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"` (dev’de `php.ini-development`)
- [ ] `display_errors=Off`, `log_errors=On`, `expose_php=Off`
- [ ] `date.timezone=Europe/Istanbul` (Laravel `config/app.php` zaten İstanbul; native PHP / log zaman damgaları uyumlu olsun)
- [ ] `memory_limit`: rapor / export sayfalarını load test verisiyle dene; 128M yetmiyorsa 256M–512M
- [ ] FPM `request_terminate_timeout` ve nginx `fastcgi_read_timeout` (varsayılan 60s) uzun raporlarla uyumlu olsun

Kontrol: `docker/php/` (yeni `opcache-prod.ini`, `php-prod.ini`), Dockerfile.

---

## 5. Frontend build (orta)

Durum: PHP imajında Node yok; dev’de `node:lts` container ile `npm ci && npm run build` yapıldı.

- [ ] Prod’da `public/build` deploy sırasında üretilsin: Dockerfile’da multi-stage (`node` stage → `public/build` kopyala) veya CI’da build
- [ ] Prod’da `npm run dev` / Vite dev server yok; `public/hot` dosyası bulunmamalı

---

## 6. Dosya izinleri ve veri dizinleri (orta)

İlk kurulumda karşılaşılanlar:

- [ ] `htdocs/storage`, `htdocs/bootstrap/cache` → `www-data` (uid 33) yazabilmeli; yoksa web 500 döner
- [ ] `data/haul-scans`, `data/haul-photos` → `www-data` yazabilmeli
- [ ] `data/kafka` → uid 1000 (Kafka imajı `appuser`); yoksa Kafka `AccessDeniedException` ile döngüye girer
- [ ] Bunları elle değil, entrypoint / kurulum scripti ile otomatik yap (her saha makinesinde tekrarlanabilir olsun)

Kontrol: `docker/php/Dockerfile`, `scripts/`.

---

## 7. Repo / satır sonları (düşük)

Durum: Windows üzerinden gelen kopyada `compose.yaml`, `docker/**`, `*.sh` CRLF oldu; `db-backup.sh` CRLF ile bash’te çalışmaz.

- [ ] Kök dizine `.gitattributes`: `* text=auto eol=lf` (gerekirse `*.png binary` vb.)
- [ ] Ekledikten sonra `git add --renormalize .` ve commit

---

## 8. Kuyruk / arka plan işleri (bilgi)

- [ ] `QUEUE_CONNECTION=redis` ama şu an `ShouldQueue` / `dispatch` kullanan iş yok, compose’ta queue worker servisi de yok. Kuyruklu iş eklenirse `queue` servisi (`php artisan queue:work --tries=3 --max-time=3600`) eklenmeli
- [ ] `scheduler` servisi prod’da da çalışıyor olmalı (`hauls:prune-scans` vb.)

---

## 9. Yedekleme (orta)

- [ ] `db_backup` prod’da çalışıyor; `./backups` yazılabilir
- [ ] En az bir kez **geri yükleme testi** (yedekten boş DB’ye restore + uygulama açılıyor mu)
- [ ] Yedekler sunucu dışına da kopyalanıyor mu (disk arızası senaryosu)
- [ ] **Saha fotoğrafları yedekte yok (yüksek):** `db-backup.sh` sadece `pg_dump` alıyor. `data/haul-photos` (ve saklanıyorsa `data/haul-scans`) DB’de path olarak duruyor; disk giderse kayıtlar kalır, görseller gider. Medya için ayrı yedek (rsync / restic vb.) ekle
- [ ] Yedek hatası sadece container log’una yazılıyor (`[backup] FAILED`); başarısız yedek için uyarı (mail / webhook / izleme) olsun
- [ ] Disk planı: günde 2 dump × 90 gün = ~180 dosya + fotoğraflar + Docker imajları; boş alan uyarısı

Kontrol: `docker/scripts/db-backup.sh`, `scripts/backup-postgres.sh`.

---

## 10. HTTPS, reverse proxy ve `APP_URL` (yüksek)

Durum: nginx sadece `:80` dinliyor, TLS yok. `bootstrap/app.php` içinde `trustProxies` yok. `SESSION_SECURE_COOKIE` tanımsız.  
İmzalı fotoğraf URL’i (`routes/api.php` → `->middleware('signed')`) ve tüm `route()` / `url()` çıktıları `APP_URL` + istek şemasına bağlı.

- [ ] TLS nerede sonlanacak karar ver: nginx’te sertifika **veya** önde reverse proxy (Caddy / Traefik / Cloudflare Tunnel vb.)
- [ ] Önde proxy varsa `bootstrap/app.php` → `$middleware->trustProxies(at: …)` ekle. Yoksa: imzalı URL’ler `http` ile üretilip `https`’te **imza hatası** verir; IP bazlı throttle (`throttle:20,1`, admin login 5 deneme) tüm kullanıcıları **proxy IP’si** üzerinden tek kişi sayar
- [ ] HTTPS’te `SESSION_SECURE_COOKIE=true`
- [ ] `APP_URL` sahada telefonların eriştiği adres olmalı (domain veya sabit LAN IP / hostname). `localhost:8010` kalırsa telefona giden imzalı foto linkleri açılmaz

Kontrol: `bootstrap/app.php`, `routes/api.php`, `docker/nginx/default.conf`, `htdocs/.env`.

---

## 11. JWT blacklist ve Redis (yüksek)

Durum: JWT blacklist `Illuminate` cache storage’da → `CACHE_STORE=redis`. Session da Redis’te. Redis `maxmemory=0`, `maxmemory-policy=noeviction`, şifre yok.

- [ ] Deploy’da **`php artisan cache:clear` / `optimize:clear` çalıştırma.** Cache ile birlikte JWT blacklist silinir; logout edilmiş / iptal edilmiş token’lar (`JWT_REFRESH_TTL=20160` → 14 gün) tekrar geçerli olur. Deploy’da sadece `optimize` (config/route/view cache)
- [ ] Redis’e `maxmemory` verilirse policy `noeviction` veya `volatile-*` kalsın; `allkeys-lru` blacklist ve session anahtarlarını sessizce siler
- [ ] Tercihen blacklist için ayrı cache store / Redis DB (cache temizliği güvenli hale gelir)
- [ ] Redis’e şifre (`requirepass`) + `REDIS_PASSWORD`; port dışarı açık değilse düşük öncelik

Kontrol: `config/jwt.php`, `config/cache.php`, `config/database.php` (redis), `compose.yaml`.

---

## 12. Kullanılmayan servisler (orta)

Durum: `rabbitmq` ve `kafka` compose’ta çalışıyor ama uygulama kodunda hiç kullanılmıyor (sadece `.env.example`’da değişken var). Mail de gönderilmiyor (`MAIL_MAILER=log` yeterli).

- [ ] Prod / saha compose’undan `rabbitmq` ve `kafka`’yı çıkar (veya `profiles:` ile opsiyonel yap): RAM (Kafka JVM), disk, açık port ve bakım yükü
- [ ] İleride kullanılacaksa o gün eklenir; şimdilik `.env`’deki `RABBITMQ_*` / `KAFKA_*` değişkenleri bilgi amaçlı kalabilir
- [ ] Şifre sıfırlama / bildirim maili eklenirse gerçek mailer (SMTP) ayarı

---

## 13. Nginx (orta)

Durum: `docker/nginx/default.conf` minimal; `X-Powered-By` gizli, dotfile’lar engelli, `client_max_body_size 32M` (PHP 20M/25M ile uyumlu).

- [ ] `server_tokens off;`
- [ ] `gzip on;` (css/js/json/svg)
- [ ] `/build/` hash’li asset’ler için uzun cache: `expires 1y; add_header Cache-Control "public, immutable";`
- [ ] Güvenlik başlıkları: `X-Content-Type-Options nosniff`, `X-Frame-Options SAMEORIGIN`, `Referrer-Policy strict-origin-when-cross-origin`; HTTPS varsa `Strict-Transport-Security`
- [ ] `fastcgi_read_timeout` uzun raporlara göre (bkz. bölüm 4)
- [ ] Healthcheck için Laravel `/up` route’u hazır (`bootstrap/app.php` → `health: '/up'`)

---

## 14. Saha makinesi (W11 + WSL + Docker Desktop) işletim ayarları (yüksek)

Sahada sunucu bir Windows laptop/PC ise, “container restart policy” tek başına yetmez:

- [ ] Docker Desktop → **Start Docker Desktop when you sign in** açık; Windows otomatik oturum açma veya Docker’ın oturumsuz başlaması çözülmüş olmalı. Elektrik kesintisi / restart sonrası stack kendiliğinden ayağa kalkmalı — **test et**
- [ ] Uyku / hazırda bekletme kapalı (prizdeyken), kapak kapatınca “hiçbir şey yapma”
- [ ] Windows Update: etkin saatler / otomatik yeniden başlatma iş saatlerine denk gelmesin
- [ ] **WSL saat kayması:** host uykudan dönünce WSL saati geri kalabiliyor → sefer zaman damgaları ve JWT `exp` kontrolü bozulur. Uyku kapalıyken bile periyodik kontrol: `date` (WSL) ile Windows saatini karşılaştır; gerekirse `hwclock -s` / systemd-timesyncd
- [ ] `%UserProfile%\.wslconfig` → `memory=` / `processors=` sınırı (varsayılan RAM’in %50’si); Postgres + PHP + Redis için yeterli, Windows’u boğmayacak değer
- [ ] WSL `ext4.vhdx` zamanla büyür, kendiliğinden küçülmez; disk alanı izlenmeli
- [ ] Telefonlar laptop’a LAN üzerinden bağlanacaksa: Windows Firewall’da web portu (8010 / 80 / 443) için inbound kural; sabit IP veya DHCP rezervasyonu
- [ ] `/etc/wsl.conf` `[user] default=` geçerli bir kullanıcıyı göstermeli (bu makinede `default=yadi` ama kullanıcı yok → WSL root açılıyor, dosyalar root sahipli). Saha kurulumunda kullanıcı + sahiplik tutarlı olsun

Kontrol: `htdocs/docs/wsl-docker-field-setup.md` ile birlikte uygula.

---

## 15. İzleme ve log (orta)

- [ ] Uptime kontrolü: `GET /up` (dışarıdan veya makine içinden cron)
- [ ] Laravel log’ları `LOG_STACK=daily` + saklama günü (`LOG_DAILY_DAYS`)
- [ ] Hata takibi (Sentry / Flare vb.) veya en azından `error` seviyesinde mail / webhook
- [ ] Postgres: `shared_buffers=128MB` varsayılan; 1 yıllık veriyle raporlar yavaşsa `shared_buffers`, `work_mem`, `effective_cache_size` makine RAM’ine göre ayarla; yavaş sorgu log’u (`log_min_duration_statement`)

---

## 16. Agent iş planı (özet görev listesi)

1. [ ] Dockerfile’a `INSTALL_XDEBUG` build arg; dev compose `true`, `xdebug.mode=debug`
2. [ ] `compose.prod.yaml`: Xdebug’sız imaj, port kısıtları, şifreler env’den, sabit imaj tag’leri, log rotasyonu, `rabbitmq` / `kafka` yok
3. [ ] Prod `php.ini` (production baz) + OPcache ini + multi-stage frontend build
4. [ ] İzinleri otomatik ayarlayan entrypoint / kurulum scripti
5. [ ] `.gitattributes` + renormalize
6. [ ] HTTPS / reverse proxy kararı → `trustProxies`, `SESSION_SECURE_COOKIE`, doğru `APP_URL`; imzalı foto URL’i telefondan açılıyor mu test et
7. [ ] Nginx prod ayarları (gzip, cache, güvenlik başlıkları, timeout)
8. [ ] Medya (fotoğraf) yedeği + yedek hatası uyarısı; restore testi
9. [ ] Prod `.env` şablonu / deploy komut listesi (`--no-dev`, `optimize`, `migrate --force`; seed yok, `cache:clear` yok)
10. [ ] Saha makinesi işletim ayarları (bölüm 14) ve reboot testi
11. [ ] `security-checklist.md` maddeleriyle birlikte son kontrol
12. [ ] Dev stack’in (`docker compose up -d`, Xdebug trigger debug) hâlâ çalıştığını doğrula

---

## Bitiş kriteri

- [ ] Prod imajında `php -m` Xdebug göstermiyor; OPcache açık
- [ ] Dışarıya sadece web portu açık
- [ ] `APP_DEBUG=false`, dev paketleri yüklü değil, config/route cache’li
- [ ] Prod DB’de load test verisi yok
- [ ] HTTPS arkasında imzalı foto URL’i telefondan açılıyor; throttle gerçek istemci IP’sini görüyor
- [ ] DB + fotoğraf yedeği alınıyor ve bir kez geri yüklendi
- [ ] Saha makinesi elektrik kesintisi / restart sonrası müdahalesiz ayağa kalkıyor
- [ ] Dev stack değişmeden çalışıyor
- [ ] `security-checklist.md` tamamlandı
