# macOS: performans güncellemesi sonrası container yenileme

Bu dosya, `c78f3a4` commit’indeki performans değişikliklerini macOS’taki Hafline Docker stack’ine uygulamak için iş planıdır.  
Başka bir agent’a “bu md’yi uygula” diye verilebilir.

Hedef: `app`, `scheduler`, `postgres` yeni compose ayarlarıyla yeniden oluşturulur; veri kaybı olmaz; imaj build edilmez.

---

## 0. Ne değişti (bağlam)

| Değişiklik | Nerede | Container yenileme gerekir mi |
|---|---|---|
| `XDEBUG_MODE: ${XDEBUG_MODE:-off}` | `compose.yaml` → `app`, `scheduler` | Evet |
| `command: ["postgres", "-c", "jit=off"]` | `compose.yaml` → `postgres` | Evet |
| `DB_PERSISTENT=true` (kalıcı PDO bağlantısı) | `htdocs/.env` (git’te yok, elle eklenir) | Hayır (config cache’li değilse) |
| PHP kodu (audit defer, tarih filtreleri) | `htdocs/app/...` | Hayır (bind mount) |

Dockerfile değişmedi → `docker compose build` **gerekmez**.

---

## 1. Kodu çek

Repo kökünde (`compose.yaml` olan yer):

```bash
git pull
git log --oneline -3   # c78f3a4 görünmeli
```

- [ ] `c78f3a4` (veya sonrası) çekildi
- [ ] `git status` temiz; yerel değişiklik varsa durup kullanıcıya sor, üzerine yazma

---

## 2. `.env`’e `DB_PERSISTENT` ekle

```bash
grep -q '^DB_PERSISTENT=' htdocs/.env || sed -i '' '/^DB_PASSWORD=/a\
DB_PERSISTENT=true
' htdocs/.env
grep -n '^DB_' htdocs/.env
```

- [ ] `DB_PERSISTENT=true` satırı `DB_PASSWORD`’ün altında, **tek** kez var
- [ ] `htdocs/.env`’de başka hiçbir satıra dokunulmadı

Config cache kullanılıyorsa (`htdocs/bootstrap/cache/config.php` varsa) yenile:

```bash
test -f htdocs/bootstrap/cache/config.php && docker compose exec app php artisan config:cache
```

---

## 3. Sadece etkilenen container’ları yeniden oluştur

```bash
docker compose up -d postgres app scheduler
```

- [ ] `postgres`, `app`, `scheduler` “Recreated / Started”
- [ ] `web`, `redis`, `db_backup` **yeniden başlatılmadı** (gerek yok)
- [ ] `postgres` birkaç saniye kesinti verir; veri `./data/postgres` bind mount’unda, kaybolmaz

`"APP_URL" variable is not set` uyarısı zararsızdır (kök `.env`’deki `L5_SWAGGER_CONST_HOST`), yok say.

---

## 4. Doğrulama

```bash
docker compose ps
docker compose exec app printenv XDEBUG_MODE                              # off
docker compose exec scheduler printenv XDEBUG_MODE                        # off
docker compose exec postgres psql -U hafline -d hafline -Atc 'show jit'   # off
docker compose up -d --dry-run 2>&1 | grep -i recreat || echo "drift yok"
curl -s -o /dev/null -w '%{http_code} %{time_total}\n' http://localhost:8010/up
```

- [ ] Tüm servisler `running`, `postgres` `healthy`
- [ ] `XDEBUG_MODE` iki container’da `off`
- [ ] `jit` = `off`
- [ ] `/up` 200 ve birkaç ms–~20 ms
- [ ] `php -i` / `ini_get('xdebug.mode')` hâlâ `debug,develop` gösterebilir — **normal**; geçerli olan `XDEBUG_MODE` env’idir

Testler (isteğe bağlı ama önerilir):

```bash
docker compose exec app php artisan test --compact
```

- [ ] Tüm testler geçti

---

## 5. Bilinçli olarak yapma

| Yanlış | Doğru |
|---|---|
| `docker compose down` / `down -v` | Sadece `up -d postgres app scheduler` |
| `docker compose build` / `--build` | Gerek yok, Dockerfile değişmedi |
| `./data/` altını silmek / taşımak | Dokunma |
| `optimize:clear` gibi toplu temizlik | Gerekirse sadece `config:cache` (bkz. 2) |
| `.env`’i `.env.example`’dan yeniden kopyalamak | Sadece `DB_PERSISTENT=true` satırını ekle |

---

## 6. Step debug gerekirse

```bash
XDEBUG_MODE=debug docker compose up -d app   # aç
docker compose up -d app                     # kapat (off)
```

---

## Bitiş kriteri

- [ ] Kod `c78f3a4`+ seviyesinde
- [ ] `htdocs/.env` → `DB_PERSISTENT=true`
- [ ] `app` / `scheduler` → `XDEBUG_MODE=off`; `postgres` → `jit=off`
- [ ] `docker compose up -d --dry-run` yeniden oluşturulacak container göstermiyor
- [ ] Admin login ve birkaç sayfa açılıyor; sonuç kullanıcıya kısa rapor olarak verildi
