# Ön Muhasebe Planı (Fatura · Vade · Stok · Plakaya Satış · Yetki · Seed)

Bu dosya iş listesidir. Her madde tek başına verilebilecek kadar küçüktür:
"**3 numaralı işi yap**" demek yeterli. Bir iş bitince yanındaki kutu işaretlenir.

---

## Her işte geçerli kurallar

- Migration dosyası yazılır, **çalıştırılmaz**; migration'ı kullanıcı çalıştırır.
- Yeni yetki `RolePermissionSeeder` içine eklenir; kullanıcı seeder'ı yeniden çalıştırır.
- Her belge cari satırını **kendisi** yazar (`Ledger::sync`); bakiye hiçbir yerde saklanmaz, hareketlerden hesaplanır.
- Para hareketi olan her şey ödeme tipine uygun hesaptan geçer (nakit → kasa, havale → banka, kart → POS/kart).
- Her işin sonunda: ilgili testler yazılır/güncellenir, `vendor/bin/pint --dirty`, testler geçer, `ledger:audit` kontrolleri yeni belgeyi kapsar.
- Ekranlar mevcut düzene uyar (`docs/detail-page-layout.md`, mevcut `x-form.*`, `x-table.*` bileşenleri).

---

## Kararlar (önerilen varsayılan · değişirse burada düzeltilir)

| # | Konu | Karar |
|---|---|---|
| K1 | KDV | Satır başına KDV %, varsayılan **0**. Sıfırken ekranda tek "Tutar" görünür. Tevkifat yok. Stok maliyeti = matrah. |
| K2 | Vade | **İsteğe bağlı, tek tarih**, borçta da alacakta da. Sadece hatırlatır; bakiyeye, rapora etkisi yok. Taksit, kredi kartı ekstresi, POS valörü **yok**. |
| K3 | Ödeme durumu | Fatura girilirken: **Ödenmedi / Ödendi / Kısmen ödendi** (satışta Tahsil edilmedi / edildi / kısmen). Sonra durum **ödemelerden hesaplanır**, elle seçilmez. |
| K4 | Cariye yazma | Fatura **kaydedildiği gün** cariye yazılır (borç o gün doğar). Kasa sadece ödeme yapılınca değişir. |
| K5 | Çift kayıt | Fatura ekranı açılınca Cari hareketleri'nde firma türlerinin "ödeme tipi boş" (vadeli fatura) kaydı **kapatılır**; orada yalnız ödeme/tahsilat kalır. Aynı firmada aynı fatura no ikinci kez girilemez. |
| K6 | Fotoğraf | Faturaya en fazla 4 dosya: jpeg/png/webp (küçültülerek) ve **PDF** (tür + boyut kontrolüyle). Korumalı diskte, yetkiliye gösterilir. |
| K7 | Taslak | İlk sürümde **yok** (gerekirse 17. iş). |
| K8 | Plakaya satış | Plakaya **tamamı satış günü** yazılır, taksit yok. Fiyat kalem kartındaki varsayılan satış fiyatından önerilir, değiştirilebilir. Maliyetin altında uyarı verir, engellemez. |
| K9 | Bizim borcumuz | Plakaya satış, satıcıya olan borcumuzu **değiştirmez**; ikisi arasında virman yok. |
| K10 | Stok takibi | Adet/litre bazında, seri no / zimmet **yok**. Ortalama maliyet (yakıt deposundaki gibi). Stok eksiye düşemez. |
| K11 | Alacak hatırlatması | Varsayılan vade **boş**; isteyen girer. Plakaya satışta hatırlatma, plaka bakiyesi o tutarı kapatınca ya da elle kapanır. |

---

## Tamamlananlar

- [x] Hesaplar arası virman (Cari hareketleri → İşlem: Hesaplar Arası Virman; Kasa/Banka ekranlarında kısayol butonları)
- [x] Kasa Hareketleri raporu yalnız kasa hesaplarını sayar; kasa açılış bakiyesi rapora girer
- [x] `ledger:audit`: "Kasa raporu = kasa hesaplarının bakiyesi" kontrolü
- [x] Çek/senet yönü işlem türünden alınır (kodla girilen çekli ödemeler hata vermez)

---

## A · Fatura defteri (stoksuz)

- [ ] **1. Fatura tabloları ve modeller**
  - `invoices`: yön (alış/satış), cari, fatura no, tarih, vade (boş olabilir), matrah, KDV, toplam, şantiye (boş olabilir), açıklama, oluşturan, zaman damgaları, yumuşak silme.
  - `invoice_lines`: fatura, açıklama, miktar, birim fiyat, KDV %, matrah, KDV, toplam, gider/gelir kategorisi (boş olabilir), sıra.
  - Veritabanı kuralları: tutarlar ≥ 0, toplam = matrah + KDV, aynı cari + yön + fatura no tekil.
  - `Invoice`, `InvoiceLine` modelleri, factory'ler.
  - Bitti: migration dosyası + modeller + factory; testte fabrika ile fatura oluşuyor.

- [ ] **2. Faturanın cariye yazılması**
  - `InvoiceLedger`: alış → cariye **alacak** (toplam), satış → cariye **borç** (toplam); modül `general`, şantiye faturadaki şantiye.
  - Morph map'e `invoice` eklenir.
  - `LedgerAudit`: belge eşleşmesi ve modül/şantiye kontrollerine fatura eklenir.
  - Bitti: fatura oluşturma/düzenleme/silme cari satırını doğru yazıyor, ters kayıtla düzeltiyor; `ledger:audit` geçiyor.

- [ ] **3. Fatura servisi ve doğrulama**
  - `InvoiceService`: oluştur / düzenle / sil; satır toplamlarını ve KDV'yi hesaplar (K1).
  - `InvoiceRequest`: cari zorunlu, en az bir satır, miktar > 0, vade ≥ tarih, fatura no tekil (K5), sistem carisi (Geçici Cari) seçilemez.
  - Alışta cariye Tedarikçi, satışta Müşteri rolü kendiliğinden verilir.
  - Bitti: servis testleri (toplamlar, KDV 0 ve %20, tekil no, rol atama).

- [ ] **4a. Fatura listesi + menü + yetki**
  - Liste: tarih, no, cari, toplam, ödenen, kalan, vade, durum; filtre: yön, cari, tarih.
  - Menü: Finans altında **"Faturalar"**. Yetki: `invoice` (görüntüle/ekle/düzenle/sil) `RolePermissionSeeder`'a.
  - Bitti: yetkisiz yönlendirme ve liste filtresi testleri.
- [ ] **4b. Fatura formu (satırlı)**
  - Üst bilgiler + satır ekle/sil (Alpine), KDV % (varsayılan 0, sıfırken sade görünüm), toplamlar canlı; oluştur ve düzenle.
  - Bitti: oluştur, düzenle, doğrulama hatası testleri.
- [ ] **4c. Fatura detayı + silme**
  - Bilgiler, satırlar, cari satırına bağlantı; silme onayı.
  - Bitti: detay ve silme testleri.

- [ ] **5. Cari ekstresinden faturaya bağlantı**
  - Cari kartı ekstresinde fatura satırı "Alış faturası A-1023" olarak görünür, tıklanınca fatura detayı açılır (`entry-link`).
  - Bitti: cari kartında bağlantı testi.

- [ ] **6. Fatura fotoğrafı / PDF**
  - `invoice_attachments` tablosu; formda ve detayda yükleme (en fazla 4), detayda önizleme / "PDF'i aç", silme.
  - Resim `SecureImageIngest` ile küçültülür; PDF tür + boyut kontrolüyle saklanır (K6). Sadece fatura görme yetkisi olan açar.
  - Listede ataç simgesi.
  - Bitti: yükleme, yetkisiz erişim reddi, PDF kabul, geçersiz dosya reddi testleri.

## B · Ödeme ve ödeme durumu

- [ ] **7. Ödeme ↔ fatura eşleşmesi**
  - `invoice_payments` tablosu: fatura, ödeme belgesi (cari hareketi), tutar.
  - Fatura: ödenen = eşleşmeler toplamı, kalan = toplam − ödenen, durum = Ödenmedi / Kısmen / Ödendi (+ vadesi geçti etiketi) **hesaplanır** (K3).
  - Kurallar: eşleşme faturanın kalanını ve ödemenin tutarını aşamaz.
  - Bitti: durum hesaplama ve sınır testleri.

- [ ] **8. Fatura detayından "Öde" / "Tahsil et"**
  - Buton küçük form açar: tutar (varsayılan kalan), tarih, ödeme tipi, hesap (çek/senette no + vade).
  - Kaydedince mevcut cari hareketi yazılır (Satıcı Cari borç / Alıcı Cari alacak) + eşleşme. Çek/senet portföye girer.
  - Bitti: kısmi ödeme, tam ödeme, çekle ödeme, hesap bakiyesi testleri.

- [ ] **9a. Fatura girişinde "Ödendi"**
  - Formda "Ödeme durumu" alanı: Ödenmedi (vade alanı) / Ödendi (ödeme tipi + hesap). Fatura ve ödeme tek işlemde yazılır.
  - Bitti: cari, kasa ve durum testleri.
- [ ] **9b. Fatura girişinde "Kısmen ödendi"**
  - Ödenen tutar + ödeme tipi + hesap + kalan için vade; ödenen tutar faturadan büyük olamaz, eşitse "Ödendi" sayılır.
  - Bitti: kısmi tutar, sınır ve durum testleri.

- [ ] **10. Ödeme silinince / değişince fatura geri açılır**
  - Ödeme silinirse eşleşmesi kalkar; tutar eşleşmenin altına düşürülürse eşleşme de düşer.
  - Çek karşılıksız çıkarsa kapattığı fatura yeniden açılır.
  - Eşleşmesi olan fatura silinemez (önce eşleşme kaldırılır).
  - Bitti: silme, tutar düşürme, karşılıksız çek, silme engeli testleri.

- [ ] **11. Bağlanmamış ödemeyi faturaya bağlama**
  - Fatura detayında o carinin faturaya bağlanmamış ödemeleri listelenir; "Bağla" ile eşleşir. "En eskiden otomatik dağıt" seçeneği.
  - Bitti: avans sonradan faturaya bağlanıyor, fazla ödeme bekliyor testleri.

- [ ] **12. Cari hareketlerinde vadeli fatura kaydını kapatma** (K5)
  - Firma türlerinde ödeme tipi zorunlu olur; formda "Fatura için Faturalar ekranını kullanın" notu.
  - Bitti: ödeme tipsiz firma kaydı reddi testi.

- [ ] **13. Fatura denetim kontrolleri**
  - `ledger:audit`: fatura toplamı = satırlar; cari satırı = toplam; eşleşmeler ≤ fatura ve ≤ ödeme; durum ödemelerle tutarlı.
  - Bitti: bozuk veride kontrolün yakaladığı testler.

## C · Vade hatırlatması

- [ ] **14. Gösterge panelinde Alacaklar / Borçlar**
  - Mevcut çek/senet hatırlatıcısının yanına iki başlık: vadesi geçen, 7 gün içinde, 30 gün içinde (K2).
  - Kaynak: vadesi girilmiş ve kalanı olan faturalar. "Hatırlatmayı kapat" ile elle kapanır.
  - Önbellek: fatura/ödeme değişince temizlenir.
  - Bitti: pencere ve kapanma testleri.

- [ ] **15. Fatura listesinde vade filtreleri**
  - "Vadesi geçen", "Bu hafta", "Bu ay"; gösterge panelinden listeye bağlantı.
  - Bitti: filtre testleri.

- [ ] **16. Yaşlandırma raporu** (isteğe bağlı)
  - Cari bazında kalan: vadesi gelmedi, 0–30, 31–60, 61–90, 90+ gün. Excel/PDF dışa aktarma mevcut altyapıyla.
  - Bitti: rapor testi.

- [ ] **17. Taslak fatura** (K7 değişirse)
  - Taslak cariye/stoğa yazılmaz, ödenemez; "Onayla" ile yazılır.

## D · Stok

- [ ] **18. Stok kalem kartları**
  - `stock_items`: ad, birim (adet, lt, kg), stoklu mu, varsayılan gider kategorisi, varsayılan satış fiyatı, aktif.
  - Ekran (liste/form/detay), menü: Finans altında "Stok". Yetki: `stock-item`.
  - Bitti: CRUD testleri.

- [ ] **19. Alış faturasından stoğa giriş**
  - Fatura satırına kalem seçimi; stoklu kalemde satır stoğa girer (miktar, birim maliyet = satır matrahı / miktar).
  - `StockService::stock(item, tarih)`: kalan miktar, ortalama maliyet, değer (yakıt deposu mantığıyla).
  - Bitti: giriş ve ortalama maliyet testleri.

- [ ] **20. Stok kartı detayı**
  - Kalan, ortalama maliyet, değer; hareketler listesi (giriş/çıkış/sayım, belgeye bağlantı).
  - Bitti: ekran testi.

- [ ] **21a. Satış faturasından stoktan çıkış + maliyet**
  - Stoklu satır satış günündeki ortalama maliyetle düşer; maliyet satıra **kaydedilir** (sonradan değişmez).
  - Bitti: stok düşüşü ve maliyet sabitleme testleri.
- [ ] **21b. Eksi stok engeli + zararına satış uyarısı**
  - Stok eksiye düşemez (o tarih ve sonrası için); maliyetin altında satışta uyarı (K8).
  - Bitti: eksi stok reddi ve uyarı testleri.
- [ ] **21c. Brüt kâr gösterimi**
  - Satış faturası detayında ve listede satış − maliyet.
  - Bitti: kâr hesap testi.

- [ ] **22. Kendi kullanımımız (stok kullanımı)**
  - Belge: kalem, miktar, tarih, şantiye / kendi aracımız, kategori. Cari yok; maliyet gider olarak yazılır.
  - Bitti: stok düşüş ve gider raporu testleri.

- [ ] **23. Sayım**
  - Sayılan miktar girilir; fark stoğu düzeltir, cariye yazılmaz ("sayım farkı").
  - Bitti: fark hesaplama testi.

- [ ] **24. Satıcıya iade faturası**
  - Alış iadesi: stoktan çıkar (alış maliyetiyle), satıcıya borç; açık alış faturasının kalanına düşebilir.
  - Bitti: stok, cari, kalan testleri.

- [ ] **25. Satış iadesi faturası**
  - Mal satıldığı günkü maliyetle stoğa geri girer; müşteriye alacak; açık satış faturasının kalanına düşebilir.
  - Bitti: stok, cari, kalan testleri.

- [ ] **26. Stok düzenleme / silme kuralları**
  - Kullanılmış (satılmış/kullanılmış) alış satırının miktarı kullanılanın altına düşürülemez; stok çıkışı olan alış faturası silinemez.
  - Bitti: engel testleri.

- [ ] **27. Stok denetim kontrolleri**
  - `ledger:audit`: miktar = giriş − çıkış ± sayım, hiçbir gün eksi değil; giriş değeri = çıkış maliyetleri + kalan değer + sayım farkı.
  - Bitti: bozuk veride yakalama testleri.

## E · Plakaya satış

- [ ] **28. Satış faturasında "Kime: Plaka"**
  - Cari yerine plaka seçilir; cari o tarihteki araç sahibi olur, cari satırı plakaya bağlı yazılır (K8, K9).
  - Devirle kapanmış döneme yazılamaz (`assertOpen`). Geçici Cari'deki plakaya satış için kural yakıt fişindeki gibi.
  - Bitti: sahip, devir engeli testleri.

- [ ] **29a. Plaka bakiyesi**
  - `VehicleAccountRepository::balance` plakaya satışı düşer (devir hesaplaşması da bunu kullanır).
  - Bitti: bakiye testi.
- [ ] **29b. Plaka Bakiye raporuna "Malzeme" sütunu**
  - Bitti: rapor = cari testi.
- [ ] **29c. Plaka Sahibi Bakiye raporuna "Malzeme" sütunu**
  - Bitti: rapor = cari testi.
- [ ] **29d. Aylık / dönemsel plaka ve sahip raporları**
  - Plaka Aylık, Plaka Sahibi Aylık, dönemsel raporlarda malzeme satırları.
  - Bitti: rapor testleri.

- [ ] **30. Plakaya satış denetimi**
  - `LedgerAudit`: o tarihteki sahip, plaka raporu = cari, sahip raporu = cari; `ReportChainAudit` aylık zincir.
  - Bitti: `ledger:audit` geçiyor, bozuk veride yakalıyor.

- [ ] **31. Devir ve Geçici Cari aktarımı**
  - Devirde plaka bakiyesi taşınırken plakaya satışlar da hesaba girer; Geçici Cari'den asıl sahibine aktarımda satış faturaları da taşınır.
  - Bitti: devir ve aktarım testleri.

- [ ] **32. Plaka kartında malzeme satırları**
  - Plaka / araç sahibi kartında ve hakediş görünümünde "Malzeme" satırları, faturaya bağlantı.
  - Bitti: ekran testi.

## F · Cari işlemleri yetkisi ve menüsü

> Not: Fatura defteri açılınca (12. iş) firma "kayıt" işi faturaya taşınır; Cari hareketlerinde firma türleri yalnız ödeme/tahsilat olur. Yetki grupları buna göre sadeleşir.

- [ ] **33. Cari işlem grupları**
  - `CariEntryKind` için gruplar: Firma ödeme/tahsilat · Plaka ödeme · Plaka alacak · Araç sahibi ödemesi · Döküm yeri ödemesi · Şantiye hakedişi · Şantiye tahsilat/gider · Kasa hareketi · Hesaplar arası virman · Açılış bakiyesi · Cari virman.
  - Bitti: her türün tam bir grupta olduğunu doğrulayan test.

- [ ] **34. Grup yetkileri**
  - Her gruba görüntüle/ekle/düzenle/sil; `RolePermissionSeeder` ve rol ekranında "Cari işlemleri" başlığı.
  - Mevcut `*-cari-entry` yetkisi olan rollere yeni grupların hepsi verilir (seeder).
  - Bitti: seeder testi.

- [ ] **35a. Yetki: yeni kayıt**
  - Formda yalnız yetkili türler; kaydetme türün grubunun ekleme yetkisini ister.
  - Bitti: "plaka ödemesi yapabilir, firma ödemesi yapamaz" testi.
- [ ] **35b. Yetki: liste + detay**
  - Listede ve filtrede yalnız görme yetkisi olan türler; detay sayfası türün görme yetkisini ister.
  - Bitti: liste ve detay testleri.
- [ ] **35c. Yetki: düzenleme + silme**
  - Türün grubunun düzenle/sil yetkisi; listede butonlar yetkiye göre.
  - Bitti: düzenle ve sil testleri.

- [ ] **36. Menüde Cari işlemleri bağlantıları**
  - Finans altında "Cari işlemleri" alt grubu: her grup için liste bağlantısı (yalnız yetkili olana görünür); "Yeni" o grubun türleriyle açılır.
  - Bitti: menü görünürlük testleri.

- [ ] **37. Yetkinin diğer ekranlara yayılması**
  - Çek/Senet ekranından girilen çek, seçilen grubun ekleme yetkisine bakar.
  - Cari ekstresinde yetkisiz kullanıcı için satır bağlantısı düz yazı olur.
  - Kasa/Banka ekranındaki virman butonları "Hesaplar arası virman" yetkisine bakar.
  - Bitti: üç ekranın yetki testleri.

## G · Load test seed (kullanıcı talimatıyla)

- [ ] **38a. Seed: hesaplar** — kasa / banka / POS / kredi kartı hesapları ve açılış bakiyeleri.
- [ ] **38b. Seed: ödemelerde hesap** — her ödeme, ödeme tipine uygun hesabı seçer.
- [ ] **38c. Seed: çek/senet vadesi** — çekli/senetli ödemelerde vade ve no.
- [ ] **38d. Seed: kategoriler** — kasa kategorisi hareketin yönüne uygun seçilir.
- [ ] **39. Hesaplar arası virman verisi**
  - Bankadan kasaya çekim, kasadan bankaya yatırma (eski "Bankadan çekilen" yerine).
- [ ] **40a. Seed yakıt: fişte alış fiyatı** — istasyonlara alış fiyatı; 15 günlük "akaryakıt faturası" kaldırılır (çift alacak olmasın).
- [ ] **40b. Seed yakıt: firma ödemeleri** — akaryakıt firmasına açık bakiyeden ödeme.
- [ ] **40c. Seed yakıt: saha pompası** — pompa, dolum, pompadan fiş, sayım.
- [ ] **41a. Seed hafriyat: şantiye carisi** — şantiyelere cari + faturalama şekli (sefer başına / hakediş).
- [ ] **41b. Seed hafriyat: sefer başına fiyat** — güzergahlara şantiye fiyatı; fişler şantiyeyi borçlandırır.
- [ ] **41c. Seed hafriyat: hakediş + tahsilat** — aylık şantiye hakedişi, şantiye tahsilatı; eski "Alıcı Cari hakediş" kaldırılır.
- [ ] **42a. Seed nakliye: seferler** — KDV, tevkifat, şoför ücreti, komisyoncu.
- [ ] **42b. Seed nakliye: tahsilat + ödeme** — müşteri tahsilatları, nakliyeci ve komisyoncu ödemeleri.
- [ ] **43a. Seed çek/senet: alınanlar** — tahsil, ciro, birkaç karşılıksız.
- [ ] **43b. Seed çek/senet: verilenler** — vadesinde ödenmesi.
- [ ] **44. Açılış bakiyeleri ve cari virman**
- [ ] **45. Fatura, stok ve plakaya satış verisi** (A–E bittikten sonra)
- [ ] **46. `simulate:day` uyarlaması** (aynı kurallar günlük simülasyonda)
