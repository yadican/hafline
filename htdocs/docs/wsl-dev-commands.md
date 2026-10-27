# WSL: günlük geliştirme komutları

Hafline’ı Windows 11 + WSL2 + Docker üzerinde günlük olarak nasıl yöneteceğinin kısa rehberi.  
İlk kurulum için: `htdocs/docs/wsl-docker-field-setup.md`. macOS karşılığı: `htdocs/docs/mac-dev-commands.md`.

Özet: PHP, Composer ve Artisan **container’da** (`hafline-app`), Node/npm ve Git **WSL’de** çalışır. Windows tarafında (PowerShell, Windows npm, Windows Git) hiçbir şey çalıştırılmaz.

---

## 1. Editörü doğru aç

Proje Windows’tan `\\wsl.localhost\...` yoluyla açılırsa terminal PowerShell olur, Windows npm devreye girer, CRLF uyarıları ve yavaşlık başlar.

Ubuntu terminalinden:

```bash
cd /home/dev/PROJECT/hafline   # kendi path’in
cursor .                       # VS Code için: code .
```

- [ ] Cursor’ın sol altında **WSL: Ubuntu-24.04** yazıyor
- [ ] Entegre terminal `bash`, `pwd` → `/home/...` (UNC path değil)
- [ ] `which node` → `~/.nvm/...` (`/mnt/c/Program Files/nodejs` değil)

---

## 2. Kısayolları kur (tek seferlik)

`~/.bash_aliases` dosyasına ekle (`.bashrc` bu dosyayı otomatik yükler):

```bash
# Hafline
export HAFLINE_DIR=/home/dev/PROJECT/hafline

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

Açık terminalde hemen kullanmak için: `source ~/.bash_aliases`

- `HAFLINE_DIR`’i kendi clone path’ine göre değiştir
- `_hf_tty`: terminalden çalışınca `-it` (renkli çıktı, tinker), script/agent’tan çalışınca `-i` verir; `the input device is not a TTY` hatasını önler
- Kısayollar her dizinden çalışır; container içinde çalışma dizini zaten `/var/www/html` (`htdocs`)

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

### Frontend (WSL’de, container’da değil)

```bash
hf && cd htdocs
npm install
npm run dev     # Vite dev server
npm run build   # production build
```

### Veritabanı

| İş | Komut |
|---|---|
| psql | `hfdb` |
| Windows’taki DB aracından bağlan | `localhost:5433`, kullanıcı/şifre/db: `hafline` |
| Redis (host’tan) | `localhost:6380` |

### Xdebug

```bash
XDEBUG_MODE=debug dc up -d app   # aç
dc up -d app                     # kapat (varsayılan: off)
```

---

## 4. PowerShell’den tek seferlik komut

Editör WSL’de açık değilken hızlıca bir şey çalıştırmak gerekirse:

```powershell
wsl -d Ubuntu-24.04 -- docker exec hafline-app php artisan optimize:clear
wsl -d Ubuntu-24.04 -- bash -ic "hf && dc ps"
```

`bash -ic` (interaktif) şart: `.bashrc` interaktif olmayan shell’de erken çıktığı için `bash -lc` ile ne alias’lar ne de `nvm` (node) yüklenir.

---

## 5. Sık sorunlar

| Belirti | Sebep / çözüm |
|---|---|
| `php: command not found` | PHP WSL’de değil, container’da. `art ...` kullan |
| `node` yok veya `/mnt/c/...` npm çalışıyor | Terminal PowerShell ya da interaktif olmayan shell. Editörü WSL’den aç (bkz. 1) |
| `CRLF will be replaced by LF` uyarıları | Dosyalar Windows tarafından yazılıyor. Editörü WSL’den aç |
| `the input device is not a TTY` | `docker exec -it` script içinden çağrılmış. Kısayolları kullan (`_hf_tty`) |
| `"APP_URL" variable is not set` | Zararsız; kök `.env`’deki `L5_SWAGGER_CONST_HOST` satırından gelir |
| Değişiklik ekrana yansımıyor | `hfclear`; frontend ise `npm run build` veya `npm run dev` |
| `Unable to locate file in Vite manifest` | `npm run build` |

---

## 6. Bilinçli olarak yapma

| Yanlış | Doğru |
|---|---|
| PowerShell’de `npm`, `git`, `composer` | WSL terminalinde |
| Projeyi `\\wsl.localhost\...` ile açmak | `cursor .` ile WSL’den açmak |
| WSL’e PHP / Composer kurmak | Container’dakini kullan (`art`, `composer`) |
| Her seferinde `--build` | Sadece `docker/php/Dockerfile` değişince build |
| `./data/` altını silmek | Dokunma (Postgres + Redis verisi) |
