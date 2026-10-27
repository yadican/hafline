# macOS: günlük geliştirme komutları

Hafline’ı macOS + Docker (OrbStack veya Docker Desktop) üzerinde günlük olarak nasıl yöneteceğinin kısa rehberi.  
WSL karşılığı: `htdocs/docs/wsl-dev-commands.md`. Komutlar ve kısayollar iki ortamda aynıdır; fark sadece kurulumdadır.

Özet: PHP, Composer ve Artisan **container’da** (`hafline-app`), Node/npm ve Git **macOS’ta** çalışır.

---

## 1. Önkoşullar (tek seferlik)

- [ ] Docker: **OrbStack** (önerilen, daha hafif/hızlı) veya Docker Desktop
- [ ] Homebrew
- [ ] Node 24 (LTS): `brew install nvm` + `nvm install --lts` veya `brew install node@24`
- [ ] Cursor’da komut paleti → **Shell Command: Install 'cursor' command in PATH**

Doğrulama:

```bash
docker version
docker compose version
node -v    # v24.x
which node # /Users/... veya /opt/homebrew/...
```

Proje:

```bash
mkdir -p ~/projects && cd ~/projects
git clone <GITHUB_REPO_URL> hafline
cd hafline
cp htdocs/.env.example htdocs/.env   # yoksa
docker compose up -d --build
cursor .
```

- `~/Desktop`, `~/Documents` (iCloud senkronu açıksa) altına clone **yapma**; `~/projects` gibi senkronsuz bir klasör kullan

---

## 2. Kısayolları kur (tek seferlik)

macOS’ta varsayılan shell **zsh**. `~/.zshrc` dosyasının sonuna ekle:

```bash
# Hafline
export HAFLINE_DIR="$HOME/projects/hafline"

_hf_tty() { if [ -t 0 ] && [ -t 1 ]; then echo -it; else echo -i; fi; }

alias hf='cd "$HAFLINE_DIR"'
dc()       { docker compose -f "$HAFLINE_DIR/compose.yaml" "$@"; }
art()      { docker exec $(_hf_tty) hafline-app php artisan "$@"; }
composer() { docker exec $(_hf_tty) hafline-app composer "$@"; }
pest()     { docker exec $(_hf_tty) hafline-app php artisan test "$@"; }
pint()     { docker exec $(_hf_tty) hafline-app vendor/bin/pint "$@"; }
tinker()   { art tinker; }
hfsh()     { docker exec -it hafline-app bash; }
hfdb()     { docker exec -it hafline-postgres psql -U hafline -d hafline; }
hflog()    { tail -f "$HAFLINE_DIR/htdocs/storage/logs/laravel.log"; }
hfclear()  { art optimize:clear; }
```

Açık terminalde hemen kullanmak için: `source ~/.zshrc`

- `HAFLINE_DIR`’i kendi clone path’ine göre değiştir
- Homebrew ile `composer` kuruluysa bu fonksiyon onu gölgeler; bilinçli: proje container’daki PHP 8.5 + Composer ile çalışır
- `_hf_tty`: terminalden çalışınca `-it`, script/agent’tan çalışınca `-i` verir; `the input device is not a TTY` hatasını önler

---

## 3. Komut tablosu

### Docker stack

| İş | Komut |
|---|---|
| Stack’i başlat | `dc up -d` |
| Durdur (veri kalır) | `dc stop` |
| Durum | `dc ps` |
| Log izle | `dc logs -f app` (veya `web`, `postgres`, `scheduler`) |
| Servis yeniden başlat | `dc restart app` |
| Dockerfile değiştiyse | `dc up -d --build app scheduler` |
| Container’a gir | `hfsh` |

### Laravel

| İş | Komut |
|---|---|
| Herhangi bir artisan komutu | `art <komut>` (ör. `art route:list`) |
| Migration | `art migrate` |
| Yeni dosya | `art make:model Foo -mf --no-interaction` |
| Cache sıfırla (hepsi) | `hfclear` |
| Sadece uygulama cache’i (Redis) | `art cache:clear` |
| Config cache yenile | `art config:cache` |
| Tinker | `tinker` |
| Laravel log | `hflog` |

### Composer / test / format

| İş | Komut |
|---|---|
| Paket kur | `composer install` / `composer require vendor/paket` |
| Testler | `pest --compact` |
| Tek test | `pest --compact --filter=TestAdi` |
| Kod formatı (değişen dosyalar) | `pint --dirty` |

### Frontend (macOS’ta, container’da değil)

```bash
hf && cd htdocs
npm install
npm run dev     # Vite dev server
npm run build   # production build
```

`node_modules` WSL/Linux’tan kopyalandıysa native binary’ler (`@rolldown/binding-linux-*`) macOS’ta çalışmaz: `rm -rf node_modules && npm install`.

### Veritabanı

| İş | Komut |
|---|---|
| psql | `hfdb` |
| TablePlus / DBeaver ile bağlan | `localhost:5433`, kullanıcı/şifre/db: `hafline` |
| Redis | `localhost:6380` |

### Xdebug

```bash
XDEBUG_MODE=debug dc up -d app   # aç
dc up -d app                     # kapat (varsayılan: off)
```

---

## 4. Sık sorunlar

| Belirti | Sebep / çözüm |
|---|---|
| `php: command not found` | PHP macOS’ta değil, container’da. `art ...` kullan |
| `command not found: art` | Kısayollar yüklenmemiş: `source ~/.zshrc` |
| `Cannot connect to the Docker daemon` | OrbStack / Docker Desktop açık değil |
| `the input device is not a TTY` | `docker exec -it` script içinden çağrılmış. Kısayolları kullan (`_hf_tty`) |
| `"APP_URL" variable is not set` | Zararsız; kök `.env`’deki `L5_SWAGGER_CONST_HOST` satırından gelir |
| Değişiklik ekrana yansımıyor | `hfclear`; frontend ise `npm run build` veya `npm run dev` |
| `Unable to locate file in Vite manifest` | `npm run build` |
| Vite / rolldown native binding hatası | `node_modules` başka işletim sisteminden gelmiş: `rm -rf node_modules && npm install` |

---

## 5. Bilinçli olarak yapma

| Yanlış | Doğru |
|---|---|
| Homebrew ile PHP kurup projeyi onunla çalıştırmak | Container’dakini kullan (`art`, `composer`) |
| Repoyu iCloud senkronlu klasörde tutmak | `~/projects/hafline` |
| `node_modules`’u başka makineden kopyalamak | Her makinede `npm install` |
| Her seferinde `--build` | Sadece `docker/php/Dockerfile` değişince build |
| `./data/` altını silmek | Dokunma (Postgres + Redis verisi) |
