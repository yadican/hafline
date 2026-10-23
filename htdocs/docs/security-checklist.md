# Güvenlik checklist (proje bitişi)

Kaynak: Laravel klasik güvenlik kontrolleri (Medium makalesi + uygulama taraması).  
Proje tamamlanınca maddeleri tek tek doğrula ve işaretle.

---

## Öncelikli düzeltmeler

- [ ] **API login rate limit (yüksek)**  
  `POST /api/v1/auth/login` için throttle / RateLimiter ekle (admin login’deki 5 deneme modeline benzer).  
  Kontrol: `routes/api.php`, `App\Http\Requests\Api\Auth\LoginRequest` (veya controller).

- [ ] **Medya erişimi (orta)**  
  `/media/{path}` anonim kalmasın: auth veya signed/expiring URL kullan.  
  `routes/api.php` içindeki signed photo route ile hizala; istemcilere unsigned `/media` URL verme.  
  Kontrol: `routes/web.php`, `MediaController`, `SecureImageIngest::url()`.

- [ ] **Example dosya yükleme (orta)**  
  `mimes` / `mimetypes` zorunlu kıl; mümkünse private disk. Public disk kalacaksa sıkı allowlist.  
  Kontrol: `ExampleRequest`, `ExampleService`.

- [ ] **Swagger prod koruması (orta)**  
  Production’da `l5-swagger` middleware ile auth / IP kısıtı.  
  Kontrol: `config/l5-swagger.php`.

---

## Ops / config doğrulama

- [ ] Production’da `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` ve `JWT_SECRET` üretilmiş, örnek/default değil
- [ ] DB / RabbitMQ vb. şifreler `.env.example` değerlerinden farklı
- [ ] Debugbar production’da yüklü/aktif değil (`require-dev` kalmalı)
- [ ] CORS: sabit SPA domain’i varsa `allowed_origins` daralt (Bearer + `credentials: false` ise düşük öncelik)

---

## Savunma derinliği (düşük)

- [ ] `AuditLog` için `$guarded = []` yerine `$fillable` / kısıtlı guarded
- [ ] Factory/seeder varsayılan şifrelerinin prod’a sızmadığını doğrula

---

## Zaten sağlam — regresyon kontrolü

Bunlar şu an iyi; bitişte “hâlâ doğru mu?” diye bak.

- [ ] Mass assignment: create/update’te `$request->all()` yok; Form Request + `validated()`
- [ ] CSRF: web formlarında `@csrf`; CSRF exception listesi yok
- [ ] XSS: uygulama Blade’lerinde `{!! !!}` ile kullanıcı HTML’i yok
- [ ] SQL: `whereRaw` / `DB::raw` / `orderByRaw` request string birleştirmiyor
- [ ] Open redirect: `redirect($request->…)` yok; login `intended` + sabit fallback
- [ ] Directory traversal: `/media` path’te `..` reddediliyor
- [ ] `unserialize` kullanıcı girdisinde yok
- [ ] Admin login throttle çalışıyor
- [ ] Field haul upload: `SecureImageIngest` MIME / finfo / re-encode
- [ ] Admin + API route’larda Spatie `can:` / `permission:` middleware
- [ ] JWT blacklist açık; TTL env ile yönetiliyor

---

## Bitiş notu

| Öncelik | Madde              | Durum |
|---------|--------------------|-------|
| Yüksek  | API login throttle | ☐     |
| Orta    | `/media` auth/signed | ☐   |
| Orta    | Example upload     | ☐     |
| Orta    | Swagger koruması   | ☐     |
| Ops     | Prod env/secrets   | ☐     |

Tamamlanınca bu dosyadaki tüm `- [ ]` işaretleri `- [x]` olmalı.
