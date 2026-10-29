# Detay sayfaları: etiket ve değer aynı satırda

Bu dosya, admin detay (show) sayfalarında alan adı ile değerini alt alta değil aynı satırda gösterme işinin planıdır.  
Başka bir agent’a “bu md’yi uygula” diye verilebilir.

Hedef görünüm:

```text
Fiş No        :  T-20261028-132326-98
Plaka         :  34BZN999
Tarih / saat  :  28.10.2026 13:23
```

Etiketler aynı yerden başlar, iki noktalar alt alta durur, değerler aynı çizgiden başlar. Satırlar arasında ince ayırıcı çizgi vardır.

Kural: **form sayfaları etkilenmeyecek.** `x-form.field` bileşenine dokunulmaz; görünüm yalnızca detay sayfasındaki grid’e verilen `is-detail` sınıfıyla açılır.

---

## Hazır olanlar

Altyapı `resources/css/admin.css` içinde, `.panel-form-group.is-wide` kuralının hemen altında:

- `.panel-form-grid.is-detail`: grid’e eklenince içindeki her `x-form.field` iki sütunlu satır olur. Etiket sütunu sabit **13rem** (480px altı ekranda 8.5rem), iki nokta CSS ile eklenir.
- Alanın içindeki etiket dışı her şey (değer, `x-form.status` notu vb.) değer sütununa (`grid-column: 2`) yerleşir; not, değerin altına düşer.
- `is-detail` grubun gövdesindeki `gap` değerini 0 yapar; satır aralığını alanların kendi `padding`’i verir.
- `.panel-detail-columns`: geniş (`is-wide`) kartlarda alanları 768px üstünde iki sütuna dizer. Sütun aralığı 48px; bu, ikinci sütunu alttaki yarım kartların içerik başlangıcıyla aynı hizaya getirir. Bu düzende çizgi alanın alt kenarındadır, böylece yarım kalan son satır da doğru çizgilenir.

Uygulanmış sayfalar (örnek olarak bakılabilir):

- [x] `hauls/partials/show/details.blade.php`: en basit örnek, düz alan listeleri
- [x] `machine-works/partials/show/details.blade.php`: düz alan listeleri
- [x] `fuel-receipts/partials/show/details.blade.php`: düz listeler ve grup sonunda `mt-3` özet kutuları
- [x] `shipments/show.blade.php`: en karmaşık örnek; geniş kartlarda `panel-detail-columns`, alan altı notlar, kartın dibine hizalı toplam kutuları

---

## Uygulama adımları (her sayfa için)

1. Detay partial’ındaki alan listesi içeren grid’e `is-detail` ekle: `<div class="panel-form-grid is-detail">`.
2. Grubun içinde alanları yan yana dizen bir iç grid varsa (`<div class="grid gap-3 sm:grid-cols-2">` gibi) kaldır, alanları sırası bozulmadan düz listeye çevir. Aynı satırda etiketli iki çift yarım karta sığmaz.
3. Grup `is-wide` ise ve en az 3 alanı varsa, alanları `<div class="panel-detail-columns">` içine al.
4. Grubun gövdesinde alan olmayan bir eleman varsa (özet kutusu, `x-form.status`, `panel-hint` paragrafı), gövde `gap` değeri 0 olduğu için son satıra yapışır. Elemanın üstünde `mt-3` yoksa bir sarmalayıcı ile boşluk ver: `<div class="pt-3">…</div>`.
5. Yalnızca **yan yana duran** iki kartın özet kutularını aynı yüksekliğe getirmek istiyorsan grubu `class="flex flex-col [&>.panel-form-group-body]:flex-1"` yap, kutuyu `<div class="mt-auto pt-3">` içine al. Kartların yan yana olup olmadığını grid sırasından kontrol et: 768px üstünde grid iki sütundur ve kartları satır satır dizer, `is-wide` kart tam satır kaplar.
6. En uzun etiketin 13rem’e sığdığını kontrol et. Ölçüm: 16px kalın Manrope’ta “Müşteri satış açıklaması” iki nokta ve boşlukla 204px tutuyor ve sığıyor (13rem = 208px). Daha uzun etiket iki satıra bölünür; gerekiyorsa etiket sütununu genişletme, kullanıcıya sor.
7. `npm run build` çalıştır. Sayfanın görsel kontrolünü kullanıcı yapıyor.

---

## Dokunulmayacaklar / dikkat

- **Tablo içeren grid’ler:** `panel-form-grid-after` ile ayrılmış ve içinde `panel-subtable` bulunan grid’lere `is-detail` ekleme. Örnekler: `fuel-stations/partials/show/tank.blade.php`, `haul-routes` içindeki “Araca özel fiyatlar”, `vehicles/partials/show/ownerships.blade.php` ve `rates.blade.php`. Aynı sayfadaki alan listesi grid’i ayrı bir `div` ise ona uygulanabilir.
- **Çok satırlı değerler:** Değer çip listesi gibi çok satırlıysa (`roles` → “İzinler” grubunda `panel-chips`) satır görünümü çalışır, ancak etiketler 13rem’i aşabilir (izin grup adları). Önce etiket uzunluklarını kontrol et.
- **`audit-logs`:** `x-form.field` değil `<dl>` kullanıyor; bu iş kapsamında değil, dokunma.
- **`fuel-stations`:** çalışma ağacında commit’lenmemiş başka değişiklikler var; bu dosyalara dokunmadan önce kullanıcıya sor.
- Ortak partial’lar (ör. `shipments/partials/totals.blade.php`) form sayfalarında da kullanılıyor; detay sayfası için içlerini değiştirme, gerekirse dışına sarmalayıcı ekle.

---

## Kalan sayfalar

- [x] `banks/partials/show/details.blade.php`
- [x] `cari-entries/partials/show/details.blade.php`
- [x] `cash-categories/partials/show/details.blade.php`
- [x] `dump-sites/partials/show/details.blade.php`
- [x] `employees/partials/show/details.blade.php`
- [x] `employee-bonuses/show.blade.php` (grid doğrudan show dosyasında)
- [x] `employee-payments/show.blade.php` (grid doğrudan show dosyasında)
- [x] `fuel-stations/partials/show/details.blade.php` (yukarıdaki uyarıya bak)
- [x] `haul-routes/partials/show/details.blade.php` (yalnızca ilk grid; ikinci grid tablo)
- [x] `parties/partials/show/details.blade.php`
- [x] `roles/partials/show/details.blade.php` (izin grubu etiketlerinin uzunluğunu kontrol et)
- [x] `sites/partials/show/details.blade.php` (iki grid var; ikincisinin içeriğine bak)
- [x] `users/partials/show/details.blade.php`
- [x] `vehicle-classes/partials/show/details.blade.php`
- [x] `vehicles/partials/show/details.blade.php` (yalnızca details; ownerships ve rates tablo)
