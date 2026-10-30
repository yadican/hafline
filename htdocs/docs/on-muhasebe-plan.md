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

## Ajan için bağlam (önce bunu oku, keşif yapma)

### Token tasarrufu
- `.ai/rules` yok; AGENTS.md kuralları geçerli. Proje genelinde keşif **yapma**; yalnız bu bölümdeki düzeni ve işin "Örnek al" satırındaki dosyaları oku.
- Büyük dosyayı baştan sona okuma; `rg -n` ile ilgili satırı bul, `Read` ile o aralığı oku.
- Test: önce yalnız işin test dosyası (`php artisan test --compact tests/Feature/...`). Bölüm sonunda (A, B, …) bir kez `tests/Feature/LedgerAuditCommandTest.php tests/Feature/Admin/CariFuzzTest.php`. Tüm paket yalnız bölüm bitince.
- Çıktıyı kısa tut: test çıktısında sadece özet/hata.

### Proje düzeni (kısa)
- **Migration:** `uuid('id')->primary()`, `foreignUuid(...)->constrained()->restrictOnDelete()`, `created_by` → `constrained('users')->nullOnDelete()`, `$table->comment('Türkçe ad')`, `timestamps()`, `softDeletes()`. Kurallar `App\Support\Database\SchemaRules::check / checkIn / uniqueActive`. Örnek: `database/migrations/2026_10_30_151003_create_account_transfers_table.php`, `2026_10_28_225506_create_opening_balances_and_party_transfers_tables.php`.
- **Model:** `#[Fillable([...])]`, `use HasUuids, SoftDeletes;`, `casts()` metodu, ilişkilerde `->withTrashed()`. Cari yazan belge `implements LedgerSource` (`ledgerModule()`, `ledgerSiteId()`) + `ledgerEntries()` morphMany. Örnek: `app/Models/PartyTransfer.php`, `app/Models/Shipment.php`.
- **Cariye yazma:** `app/Services/Ledger/*Ledger.php` → `Ledger::sync($belge, [LedgerLine::debit|credit(partyId, tarih, tutar, açıklama, vehicleId)])`; ödeme ise `->paidBy(method, categoryId, bankId)`. Silmede `Ledger::void`. Morph adı `app/Providers/AppServiceProvider.php` içindeki `Relation::morphMap`'e eklenir. Örnek: `ShipmentLedger.php`.
- **Denetim:** `app/Services/Ledger/LedgerAudit.php` → yeni belge `EXPECTED` SQL listesine ve `postingsCarryTheirModuleAndSite` listesine eklenir; plakaya bağlıysa `workSitsWithTheOwnerOfItsDate`, `ownerBalanceReportMatchesCari`, `plateBalanceReportMatchesCari` de. Aylık zincir: `ReportChainAudit.php`.
- **Servis / repo:** `app/Services/*Service extends Service`, yazmalar `$this->transaction(fn () => ...)`; sorgular `app/Repositories/*Repository extends Repository`. Hata: `OperationRejectedException` (kullanıcıya mesaj).
- **HTTP:** `app/Http/Controllers/Admin/*Controller extends AdminController` + `protected ?string $permissionResource = 'kaynak';` (index/show → view-, create/store → create-, edit/update → edit-, destroy → delete-). Doğrulama `app/Http/Requests/Admin/*Request` (`prepareForValidation`, `after()`); hesap kontrolü `Concerns/ChecksPaymentAccount`. Rotalar `routes/admin.php`.
- **Görünüm:** `resources/views/admin/<kaynak>/{index,create,edit,show}.blade.php` + `partials/`; bileşenler `x-panel.page`, `x-form.group/field/input/select/money`, `x-table.list/actions/pager/empty`, `x-ui.button`. Detay düzeni `docs/detail-page-layout.md`. Menü `resources/views/admin/layouts/sidebar.blade.php` (`@can`). JS `resources/js/*.js` + `@vite`. Örnek ekran seti: `resources/views/admin/cari-entries/`, `resources/views/admin/fuel-stations/`.
- **Yetki:** `database/seeders/RolePermissionSeeder.php` → `permissionNames()` içindeki `$resources` listesine kaynak adı; rol ekranındaki başlık `app/Support/PermissionGroups.php` (`labels()` + `groupKey()`).
- **Test:** `tests/Feature/Admin/*Test.php`, `use RefreshDatabase;`, `User::factory()->yonetici()->create()`, `actingAs(...)->post(route(...), [...])->assertSessionHasNoErrors()`. Hesap: `Bank::factory()->create(['type' => AccountType::Cash])`. Denetim: `$this->artisan('ledger:audit')->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')`. `CariEntryKind`'a tür eklenirse `tests/Feature/Admin/CariFuzzTest.php` içindeki `match`'ler güncellenir.
- **Bitiş:** `vendor/bin/pint --dirty --format agent`; ilgili testler geçer; plan dosyasında işin kutusu `[x]` yapılır.

### İş → örnek alınacak dosyalar
| İş | Bak |
|---|---|
| 1 | `app/Models/PartyTransfer.php`, account_transfers migration'ı, `database/factories/BankFactory.php` |
| 2 | `app/Services/Ledger/ShipmentLedger.php`, `LedgerAudit.php` (`EXPECTED`, `postingsCarryTheirModuleAndSite`) |
| 3 | `app/Services/ShipmentService.php` (tutar hesabı, rol atama), `app/Http/Requests/Admin/ShipmentRequest.php` |
| 4a–4c | `resources/views/admin/shipments/` + `ShipmentController.php`, `routes/admin.php` (shipments satırları) |
| 5 | `resources/views/admin/parties/partials/show/entry-link.blade.php` |
| 6 | `app/Models/FinancialInstrumentPhoto.php`, `FinancialInstrumentService::storePhotos/photoFile`, `app/Services/Api/SecureImageIngest.php` (`storeDocument`) |
| 7–11 | `app/Services/CariEntryService.php` (`create/update/delete`), `app/Services/FinancialInstrumentService.php` (`syncEntry`, `releaseEntry` — eşleşme serbest bırakma örneği) |
| 12 | `app/Http/Requests/Admin/CariEntryRequest.php` (`after()`), `resources/views/admin/cari-entries/partials/form/fields.blade.php` |
| 13 | `LedgerAudit.php` (`instrumentsMatchTheirEntries` kalıbı) |
| 14–15 | `app/Services/DashboardService.php` (`financeReminders`), `app/Cache/DashboardCache.php` |
| 16 | `docs/report-export.md`, `app/Services/Reports/CompanyBalanceReportService.php` |
| 18 | `resources/views/admin/cash-categories/` + `CashCategoryController.php` (basit CRUD) |
| 19–27 | `app/Services/FuelTankService.php` (`stock` ortalama maliyet), `app/Repositories/FuelTankRepository.php` (`movements`) |
| 28 | `app/Services/FuelReceiptService.php` (`attributes`: o günün sahibi, `assertOpen`) |
| 29a–29d | `app/Repositories/VehicleAccountRepository.php`, `app/Repositories/Reports/VehicleBalanceReportRepository.php`, `OwnerBalanceReportRepository.php` (yakıt sütunu kalıbı) |
| 30 | `LedgerAudit.php` (plaka kontrolleri), `ReportChainAudit.php` |
| 31 | `app/Services/VehicleOwnershipService.php` (`reassignTemporary`, `carryPreviousBalance`) |
| 33–37 | `app/Enums/CariEntryKind.php`, `app/Http/Controllers/Admin/AdminController.php` (`callAction`), `CariEntryController.php` |
| 38–46 | `database/seeders/LoadTestSeeder.php`, `database/seeders/LoadTest/CariStage.php`, ilgili Stage dosyası |

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
| K7 | Taslak | **Var** (17. iş): `is_draft`; cariye/stoğa yazılmaz, ödenemez; «Onayla» ile cariye yazılır. Fatura no benzersizliği taslakta da aynı (party+direction+no). |
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

- [x] **1. Fatura tabloları ve modeller**
  - `invoices`: yön (alış/satış), cari, fatura no, tarih, vade (boş olabilir), matrah, KDV, toplam, şantiye (boş olabilir), açıklama, oluşturan, zaman damgaları, yumuşak silme.
  - `invoice_lines`: fatura, açıklama, miktar, birim fiyat, KDV %, matrah, KDV, toplam, gider/gelir kategorisi (boş olabilir), sıra.
  - Veritabanı kuralları: tutarlar ≥ 0, toplam = matrah + KDV, aynı cari + yön + fatura no tekil.
  - `Invoice`, `InvoiceLine` modelleri, factory'ler.
  - Bitti: migration dosyası + modeller + factory; testte fabrika ile fatura oluşuyor.

- [x] **2. Faturanın cariye yazılması**
  - `InvoiceLedger`: alış → cariye **alacak** (toplam), satış → cariye **borç** (toplam); modül `general`, şantiye faturadaki şantiye.
  - Morph map'e `invoice` eklenir.
  - `LedgerAudit`: belge eşleşmesi ve modül/şantiye kontrollerine fatura eklenir.
  - Bitti: fatura oluşturma/düzenleme/silme cari satırını doğru yazıyor, ters kayıtla düzeltiyor; `ledger:audit` geçiyor.

- [x] **3. Fatura servisi ve doğrulama**
  - `InvoiceService`: oluştur / düzenle / sil; satır toplamlarını ve KDV'yi hesaplar (K1).
  - `InvoiceRequest`: cari zorunlu, en az bir satır, miktar > 0, vade ≥ tarih, fatura no tekil (K5), sistem carisi (Geçici Cari) seçilemez.
  - Alışta cariye Tedarikçi, satışta Müşteri rolü kendiliğinden verilir.
  - Bitti: servis testleri (toplamlar, KDV 0 ve %20, tekil no, rol atama).

- [x] **4a. Fatura listesi + menü + yetki**
  - Liste: tarih, no, cari, toplam, ödenen, kalan, vade, durum; filtre: yön, cari, tarih.
  - Menü: Finans altında **"Faturalar"**. Yetki: `invoice` (görüntüle/ekle/düzenle/sil) `RolePermissionSeeder`'a.
  - Bitti: yetkisiz yönlendirme ve liste filtresi testleri.
- [x] **4b. Fatura formu (satırlı)**
  - Üst bilgiler + satır ekle/sil (Alpine), KDV % (varsayılan 0, sıfırken sade görünüm), toplamlar canlı; oluştur ve düzenle.
  - Bitti: oluştur, düzenle, doğrulama hatası testleri.
- [x] **4c. Fatura detayı + silme**
  - Bilgiler, satırlar, cari satırına bağlantı; silme onayı.
  - Bitti: detay ve silme testleri.

- [x] **5. Cari ekstresinden faturaya bağlantı**
  - Cari kartı ekstresinde fatura satırı "Alış faturası A-1023" olarak görünür, tıklanınca fatura detayı açılır (`entry-link`).
  - Bitti: cari kartında bağlantı testi.

- [x] **6a. Fatura fotoğrafı**
  - `invoice_attachments` tablosu; detayda yükleme (en fazla 4), önizleme, silme; `SecureImageIngest` ile küçültme; yalnız fatura görme yetkisi olan açar. Listede ataç simgesi.
  - Bitti: yükleme, yetkisiz erişim reddi, geçersiz dosya reddi testleri.
- [x] **6b. Fatura PDF'i + formdan yükleme**
  - PDF tür + boyut kontrolüyle saklanır, detayda "PDF'i aç" (K6). Fatura formunda da dosya alanı.
  - Bitti: PDF kabul ve formdan yükleme testleri.

## B · Ödeme ve ödeme durumu

- [x] **7. Ödeme ↔ fatura eşleşmesi**
  - `invoice_payments` tablosu: fatura, ödeme belgesi (cari hareketi), tutar.
  - Fatura: ödenen = eşleşmeler toplamı, kalan = toplam − ödenen, durum = Ödenmedi / Kısmen / Ödendi (+ vadesi geçti etiketi) **hesaplanır** (K3).
  - Kurallar: eşleşme faturanın kalanını ve ödemenin tutarını aşamaz.
  - Bitti: durum hesaplama ve sınır testleri.
  - Not (8–13 için): ödeme belgesi morph (`payment_type`/`payment_id`); eşleşme yalnız `InvoicePaymentService` ile yazılır (`match`, `unmatch`, `paymentAmount`, `unmatchedAmount`, `assertInvoiceKeepsItsPayments`). Ödeme tutarı belgenin faturanın carisindeki canlı, ödeme tipli cari satırlarından okunur (alışta borç, satışta alacak). Kalkan eşleşme yumuşak silinir (10b'de geri getirilebilir).

- [x] **8. Fatura detayından "Öde" / "Tahsil et"**
  - Buton küçük form açar: tutar (varsayılan kalan), tarih, ödeme tipi, hesap (çek/senette no + vade).
  - Kaydedince mevcut cari hareketi yazılır (Satıcı Cari borç / Alıcı Cari alacak) + eşleşme. Çek/senet portföye girer.
  - Bitti: kısmi ödeme, tam ödeme, çekle ödeme, hesap bakiyesi testleri.

- [x] **9a. Fatura girişinde "Ödendi"**
  - Formda "Ödeme durumu" alanı: Ödenmedi (vade alanı) / Ödendi (ödeme tipi + hesap). Fatura ve ödeme tek işlemde yazılır.
  - Bitti: cari, kasa ve durum testleri.
  - Not (9b için): seçenekler `InvoicePaymentStatus::atEntry()` (+ `entryOptions()` yöne göre etiket); form `payment_status` + `payment.*` (`partials/entry-payment.blade.php`, Alpine `paymentStatus`/`paymentMethod`). `InvoiceRequest::entryPaymentRules()` match'ine `Partial => invoicePaymentRules('payment.')` (tutar dahil), `InvoiceService::payAtEntry()` match'ine `Partial => $data['payment']['amount']`; `after()` zaten `checkInvoicePayment($validator, $total, 'payment.')` ile tutar ≤ toplamı denetliyor. Vade alanı `paymentStatus !== 'paid'` iken görünür; tutar alanı `x-form.money name="payment.amount"`.
- [x] **9b. Fatura girişinde "Kısmen ödendi"**
  - Ödenen tutar + ödeme tipi + hesap + kalan için vade; ödenen tutar faturadan büyük olamaz, eşitse "Ödendi" sayılır.
  - Bitti: kısmi tutar, sınır ve durum testleri.
  - Not (10a–13 için): girişte ödeme yalnız `InvoiceService::payAtEntry()` → `pay()` ile yazılır (Ödendi = toplam, Kısmen = `payment.amount`); yani girişte yazılan ödeme de 8'deki "Öde" ile aynı cari hareketi + `invoice_payments` eşleşmesidir, 10a/10b/13 için ayrı bir yol yok. Girişte seçilen durum saklanmaz; durum hep eşleşmelerden hesaplanır.

- [x] **10a. Ödeme silinince / tutarı düşünce fatura geri açılır**
  - Ödeme silinirse eşleşmesi kalkar; tutar eşleşmenin altına düşürülürse eşleşme de düşer. Eşleşmesi olan fatura silinemez.
  - Bitti: silme, tutar düşürme, silme engeli testleri.
  - Not (10b–13 için): `InvoicePaymentService::releasePayment($payment)` ödemenin tüm canlı eşleşmelerini yumuşak siler ve kaldırılanları döner; `fitPayment($payment)` eşleşmeleri güncel `paymentAmount`'a kırpar (en eski eşleşme en uzun korunur, sıfıra inen silinir) ve değişenleri döner. İkisi de ödeme satırını ve eşleşmeleri kilitler. `CariEntryService::delete` → `releasePayment`, `update` → `fitPayment`. `InvoicePaymentRepository::forPayment` en eskiden yeniye sıralar. Fatura silme `assertInvoiceHasNoPayments` ile reddedilir; detayda ve listede ödemeli faturanın Sil butonu görünmez. 10b: karşılıksızda `releasePayment` dönen eşleşmeleri saklamadan (yumuşak silinmiş) "geri al"da `InvoicePayment::onlyTrashed()` ile bulup `restore()` et, sonra `fitPayment` ile sığdır (arada fatura başka ödemeyle kapanmış olabilir; kalanı aşma kontrolü gerekir). Araç sahibi aktarımı (`VehicleOwnershipService`) yalnız `VehiclePayment` taşır, eşleşmeye dokunmaz; 11'de araç ödemeleri bağlanabilir olursa orada da `fitPayment` çağrılmalı.
- [x] **10b. Karşılıksız çek faturayı yeniden açar**
  - Çek "Karşılıksız" olunca kapattığı faturanın eşleşmesi düşer; "geri al" ile geri gelir.
  - Bitti: karşılıksız ve geri alma testleri.
  - Not (11–13 için): `invoice_payments.released_by_movement_id` (migration `2026_10_30_205440_…`) karşılıksız adımıyla kalkan eşleşmeyi işaretler; `FinancialInstrumentService::record` (Bounce) → `releasePayment($payment, $movement)` (kaynak cari hareketi + karşılıksızdan önceki son adım ciroysa ciro hareketi), `undoLast` (Bounce) → `restoreBounced($movement)`: yalnız işaretli ve faturası silinmemiş eşleşmeler, en eskiden, faturanın kalanına ve ödemenin bağlanmamış tutarına kırpılarak geri gelir (sığmayan yumuşak silinmiş kalır). `paymentAmount` karşılıksız çekin ödemesini (cari hareketi ya da ciro hareketi; `FinancialInstrumentRepository::bouncedFor`) **0** sayar: karşılıksız ters kaydı kendi adımına yazıyor, ödemenin satırları canlı kalıyor. 11'de bağlanmamış ödeme listesi `unmatchedAmount > 0` ile süzülürse karşılıksız çekler kendiliğinden çıkar. 13'te "eşleşme ≤ ödeme" kontrolü karşılıksız çekli ödemenin canlı eşleşmesini hata saymalı; yumuşak silinmiş eşleşmeler denetime girmez.

- [x] **11. Bağlanmamış ödemeyi faturaya bağlama**
  - Fatura detayında o carinin faturaya bağlanmamış ödemeleri listelenir; "Bağla" ile eşleşir. "En eskiden otomatik dağıt" seçeneği.
  - Bitti: avans sonradan faturaya bağlanıyor, fazla ödeme bekliyor testleri.
  - Not (12–13 için): liste `InvoicePaymentRepository::unmatchedPayments` (canlı, ödeme tipli cari satırları − eşleşmeler; `unmatchedAmount ≥ 0.01`, en eskiden; karşılıksız çekler `paymentAmount = 0` ile düşer). Detayda kalan > 0 ve `edit-invoice` iken `partials/show/unmatched-payments` ("Bağla" → `matches.store` / `match`, "En eskiden otomatik dağıt" → `matches.distribute` / `distribute`). Bağlantı kaldırma `matches.destroy` / `unmatch` (ödeme silinmez, boşta kalır). 12 fatura eşleşmesine dokunmaz. 13'te "eşleşme ≤ ödeme" canlı eşleşmeleri sayar; bağlanmamış tutar denetim konusu değil.

- [x] **12. Cari hareketlerinde vadeli fatura kaydını kapatma** (K5)
  - Firma türlerinde ödeme tipi zorunlu olur; formda "Fatura için Faturalar ekranını kullanın" notu.
  - Bitti: ödeme tipsiz firma kaydı reddi testi.
  - Not (13 için): firma türleri (`supplier`/`customer`/`fuel`/`market`) `CariEntryKind::requiresPaymentMethod()`; `CariEntryRequest` `payment_method` için `requiredIf` + mesaj "Fatura için Faturalar ekranını kullanın; burada ödeme tipi zorunludur.". Form notu Faturalar linkiyle. Servis katmanı hâlâ ödeme tipsiz yazabilir (eski veri / test yardımcıları); denetim canlı HTTP yolunu kapsamaz. 13'te "eşleşme ≤ ödeme" zaten `paymentAmount` üzerinden (ödeme tipsiz belge ödeme sayılmaz).

- [x] **13. Fatura denetim kontrolleri**
  - `ledger:audit`: fatura toplamı = satırlar; cari satırı = toplam; eşleşmeler ≤ fatura ve ≤ ödeme; durum ödemelerle tutarlı.
  - Bitti: bozuk veride kontrolün yakaladığı testler.
  - Not (14–15 için): `LedgerAudit::invoicesMatchTheirLinesAndPayments` — başlık = satır toplamları; canlı eşleşme toplamı ≤ fatura ve ≤ `paymentAmount` (karşılıksız çek = 0, yumuşak silinmiş eşleşme yok sayılır); cari satırı = toplam zaten `postingsMatchDocuments` / EXPECTED. Kontrol adı: "Faturalar satırlar ve ödemelerle tutarlı".

## C · Vade hatırlatması

- [x] **14. Gösterge panelinde Alacaklar / Borçlar**
  - Mevcut çek/senet hatırlatıcısının yanına iki başlık: vadesi geçen, 7 gün içinde, 30 gün içinde (K2).
  - Kaynak: vadesi girilmiş ve kalanı olan faturalar. "Hatırlatmayı kapat" ile elle kapanır.
  - Önbellek: fatura/ödeme değişince temizlenir.
  - Bitti: pencere ve kapanma testleri.

- [x] **15. Fatura listesinde vade filtreleri**
  - "Vadesi geçen", "Bu hafta", "Bu ay"; gösterge panelinden listeye bağlantı.
  - Bitti: filtre testleri.

- [x] **16. Yaşlandırma raporu** (isteğe bağlı)
  - Cari bazında kalan: vadesi gelmedi, 0–30, 31–60, 61–90, 90+ gün. Excel/PDF dışa aktarma mevcut altyapıyla.
  - Bitti: rapor testi.
  - Not: `admin.reports.invoice-aging` — yön filtresi Alacaklar (satış) / Borçlar (alış). Kalan > 0 faturalar (taslaklar hariç); vadesi boş veya bugünden sonra → «Vadesi gelmedi»; aksi halde gecikme gününe göre dilimler. Soft-deleted fatura/eşleşme yok sayılır. Yetki: `view-report-invoice-aging`.

- [x] **17. Taslak fatura**
  - Taslak cariye/stoğa yazılmaz, ödenemez; "Onayla" ile yazılır.
  - Not: `is_draft` boolean. Taslak `InvoiceLedger::sync` / ödeme / eşleşme / girişte ödeme yok; silmede void yok. Onay `edit-invoice`. Fatura no: canlı ile aynı unique (party+direction+no). Hatırlatma / vade filtreleri / yaşlandırma taslakları dışlar. `LedgerAudit` EXPECTED `is_draft = 0`.

## D · Stok

- [x] **18. Stok kalem kartları**
  - `stock_items`: ad, birim (adet, lt, kg), stoklu mu, varsayılan gider kategorisi, varsayılan satış fiyatı, aktif.
  - Ekran (liste/form/detay), menü: Finans altında "Stok". Yetki: `stock-item`.
  - Bitti: CRUD testleri.

- [x] **19. Alış faturasından stoğa giriş**
  - Fatura satırına kalem seçimi; stoklu kalemde satır stoğa girer (miktar, birim maliyet = satır matrahı / miktar).
  - `StockService::stock(item, tarih)`: kalan miktar, ortalama maliyet, değer (yakıt deposu mantığıyla).
  - Bitti: giriş ve ortalama maliyet testleri.

- [x] **20. Stok kartı detayı**
  - Kalan, ortalama maliyet, değer; hareketler listesi (giriş/çıkış/sayım, belgeye bağlantı).
  - Bitti: ekran testi.
  - Not: Detayda `StockService::stock` + `StockRepository::movements` (yeniden eskiye); belge linki `invoice_id`/`invoice_no`. `withoutTrashed` zorunlu (`InvoiceLine::invoice` withTrashed). Takipsiz kalemde stok/hareket blokları yok. Çıkış/sayım satırları 21+/22/23.

- [x] **21a. Satış faturasından stoktan çıkış + maliyet**
  - Stoklu satır satış günündeki ortalama maliyetle düşer; maliyet satıra **kaydedilir** (sonradan değişmez).
  - Bitti: stok düşüşü ve maliyet sabitleme testleri.
  - Not: `invoice_lines.unit_cost`; `StockRepository::movements` satış (`kind: sale`, eksi miktar); aynı gün alış önce. Taslak satış stoğa yazılmaz; onayda maliyet donar.
- [x] **21b. Eksi stok engeli + zararına satış uyarısı**
  - Stok eksiye düşemez (o tarih ve sonrası için); maliyetin altında satışta uyarı (K8).
  - Bitti: eksi stok reddi ve uyarı testleri.
  - Not: `StockService::assertSaleKeepsStockNonNegative` (satış günü + sonrası yürüyüş); canlı create/update/approve. Zararına satış `session('info')` toast; engellemez.
- [x] **21c. Brüt kâr gösterimi**
  - Satış faturası detayında ve listede satış − maliyet.
  - Bitti: kâr hesap testi.
  - Not: `Invoice::costAmount` / `grossProfit` = matrah − Σ(unit_cost×qty); alış / taslak / maliyetsiz satırda null; liste + detay.

- [x] **22. Kendi kullanımımız (stok kullanımı)**
  - Belge: kalem, miktar, tarih, şantiye / kendi aracımız, kategori. Cari yok; maliyet gider olarak yazılır.
  - Bitti: stok düşüş ve gider raporu testleri.
  - Not: `stock_usages` (unit_cost donar); `StockRepository::movements` kind `usage`; eksi engeli `assertUsageKeepsStockNonNegative`. Şantiye gider raporuna debit; cari/LedgerSource yok. Yetki `stock-usage`.

- [x] **23. Sayım**
  - Sayılan miktar girilir; fark stoğu düzeltir, cariye yazılmaz ("sayım farkı").
  - Bitti: fark hesaplama testi.
  - Not: `stock_counts` (quantity=sayılan, difference=sayılan−defter); `StockRepository::movements` kind `count` (qty=difference, aynı gün en sonda); ortalama: artı → mevcut ortalama (boşsa 0), eksi → kullanım gibi. Eksi engeli `assertCountKeepsStockNonNegative`. Cari/LedgerSource yok. Yetki `stock-count`.

- [x] **24. Satıcıya iade faturası**
  - Alış iadesi: stoktan çıkar (alış maliyetiyle), satıcıya borç; açık alış faturasının kalanına düşebilir.
  - Bitti: stok, cari, kalan testleri.
  - Not: `InvoiceDirection::PurchaseReturn`; `returns_invoice_id` (isteğe bağlı) açık alışın kalanına sayılır (`paidAmount` = ödemeler + canlı iadeler). Stok kind `purchase_return` (alış maliyeti = satır matrahı, ortalama güncellenir; aynı gün alışlardan sonra, sayımdan önce). Eksi engeli `assertPurchaseReturnKeepsStockNonNegative`. Cari: satıcıya borç. Ödeme/tahsilat UI yok (`acceptsPayment` false).

- [x] **25. Satış iadesi faturası**
  - Mal satıldığı günkü maliyetle stoğa geri girer; müşteriye alacak; açık satış faturasının kalanına düşebilir.
  - Bitti: stok, cari, kalan testleri.
  - Not: `InvoiceDirection::SaleReturn`; `returns_invoice_id` (isteğe bağlı) açık satışın kalanına sayılır (`paidAmount` = ödemeler + canlı iadeler). Stok kind `sale_return` (giriş = satışın donmuş `unit_cost`; aynı gün alışlarla birlikte, çıkışlardan önce). Cari: müşteriye alacak. Ödeme UI yok (`acceptsPayment` false).

- [x] **26. Stok düzenleme / silme kuralları**
  - Kullanılmış (satılmış/kullanılmış) alış satırının miktarı kullanılanın altına düşürülemez; stok çıkışı olan alış faturası silinemez.
  - Bitti: engel testleri.
  - Not: `StockService::assertPurchaseKeepsUsedStock` (alış hariç yürüyüş + önerilen giriş; satış iadesi stoku tekrar kullanılabilir sayar). Canlı alış `InvoiceService::update` / `delete`. Başka alış çıkışı karşılıyorsa silinebilir. Mesajlar: miktar → «kullanılmış stokun altına…», silme → «Stok çıkışı olan alış faturası silinemez.»

- [x] **27. Stok denetim kontrolleri**
  - `ledger:audit`: miktar = giriş − çıkış ± sayım, hiçbir gün eksi değil; giriş değeri = çıkış maliyetleri + kalan değer + sayım farkı.
  - Bitti: bozuk veride yakalama testleri.

## E · Plakaya satış

- [x] **28. Satış faturasında "Kime: Plaka"**
  - Cari yerine plaka seçilir; cari o tarihteki araç sahibi olur, cari satırı plakaya bağlı yazılır (K8, K9).
  - Devirle kapanmış döneme yazılamaz (`assertOpen`). Geçici Cari'deki plakaya satış için kural yakıt fişindeki gibi.
  - Bitti: sahip, devir engeli testleri.

- [x] **29a. Plaka bakiyesi**
  - `VehicleAccountRepository::balance` plakaya satışı düşer (devir hesaplaşması da bunu kullanır).
  - Bitti: bakiye testi.
- [x] **29b. Plaka Bakiye raporuna "Malzeme" sütunu**
  - Bitti: rapor = cari testi.
- [x] **29c. Plaka Sahibi Bakiye raporuna "Malzeme" sütunu**
  - Bitti: rapor = cari testi.
- [x] **29d. Aylık / dönemsel plaka ve sahip raporları**
  - Plaka Aylık, Plaka Sahibi Aylık, dönemsel raporlarda malzeme satırları.
  - Bitti: rapor testleri.

- [x] **30. Plakaya satış denetimi**
  - `LedgerAudit`: o tarihteki sahip, plaka raporu = cari, sahip raporu = cari; `ReportChainAudit` aylık zincir.
  - Bitti: `ledger:audit` geçiyor, bozuk veride yakalıyor.

- [x] **31. Devir ve Geçici Cari aktarımı**
  - Devirde plaka bakiyesi taşınırken plakaya satışlar da hesaba girer; Geçici Cari'den asıl sahibine aktarımda satış faturaları da taşınır.
  - Bitti: devir ve aktarım testleri.

- [x] **32. Plaka kartında malzeme satırları**
  - Plaka / araç sahibi kartında ve hakediş görünümünde "Malzeme" satırları, faturaya bağlantı.
  - Bitti: ekran testi.

## F · Cari işlemleri yetkisi ve menüsü

> Not: Fatura defteri açılınca (12. iş) firma "kayıt" işi faturaya taşınır; Cari hareketlerinde firma türleri yalnız ödeme/tahsilat olur. Yetki grupları buna göre sadeleşir.

- [x] **33. Cari işlem grupları**
  - `CariEntryKind` için gruplar: Firma ödeme/tahsilat · Plaka ödeme · Plaka alacak · Araç sahibi ödemesi · Döküm yeri ödemesi · Şantiye hakedişi · Şantiye tahsilat/gider · Kasa hareketi · Hesaplar arası virman · Açılış bakiyesi · Cari virman.
  - Bitti: her türün tam bir grupta olduğunu doğrulayan test.

- [x] **34. Grup yetkileri**
  - Her gruba görüntüle/ekle/düzenle/sil; `RolePermissionSeeder` ve rol ekranında "Cari işlemleri" başlığı.
  - Mevcut `*-cari-entry` yetkisi olan rollere yeni grupların hepsi verilir (seeder).
  - Bitti: seeder testi.

- [x] **35a. Yetki: yeni kayıt**
  - Formda yalnız yetkili türler; kaydetme türün grubunun ekleme yetkisini ister.
  - Bitti: "plaka ödemesi yapabilir, firma ödemesi yapamaz" testi.
- [x] **35b. Yetki: liste + detay**
  - Listede ve filtrede yalnız görme yetkisi olan türler; detay sayfası türün görme yetkisini ister.
  - Bitti: liste ve detay testleri.
- [x] **35c. Yetki: düzenleme + silme**
  - Türün grubunun düzenle/sil yetkisi; listede butonlar yetkiye göre.
  - Bitti: düzenle ve sil testleri.

- [x] **36. Menüde Cari işlemleri bağlantıları**
  - Finans altında "Cari işlemleri" alt grubu: her grup için liste bağlantısı (yalnız yetkili olana görünür); "Yeni" o grubun türleriyle açılır.
  - Bitti: menü görünürlük testleri.

- [x] **37. Yetkinin diğer ekranlara yayılması**
  - Çek/Senet ekranından girilen çek, seçilen grubun ekleme yetkisine bakar.
  - Cari ekstresinde yetkisiz kullanıcı için satır bağlantısı düz yazı olur.
  - Kasa/Banka ekranındaki virman butonları "Hesaplar arası virman" yetkisine bakar.
  - Bitti: üç ekranın yetki testleri.

## G · Load test seed (kullanıcı talimatıyla)

- [x] **38a. Seed: hesaplar** — kasa / banka / POS / kredi kartı hesapları ve açılış bakiyeleri.
- [x] **38b. Seed: ödemelerde hesap** — her ödeme, ödeme tipine uygun hesabı seçer.
- [x] **38c. Seed: çek/senet vadesi** — çekli/senetli ödemelerde vade ve no.
- [x] **38d. Seed: kategoriler** — kasa kategorisi hareketin yönüne uygun seçilir.
- [x] **39. Hesaplar arası virman verisi**
  - Bankadan kasaya çekim, kasadan bankaya yatırma (eski "Bankadan çekilen" yerine).
- [x] **40a. Seed yakıt: fişte alış fiyatı** — istasyonlara alış fiyatı; 15 günlük "akaryakıt faturası" kaldırılır (çift alacak olmasın).
- [x] **40b. Seed yakıt: firma ödemeleri** — akaryakıt firmasına açık bakiyeden ödeme.
- [x] **40c. Seed yakıt: saha pompası** — pompa, dolum, pompadan fiş, sayım.
- [x] **41a. Seed hafriyat: şantiye carisi** — şantiyelere cari + faturalama şekli (sefer başına / hakediş).
- [x] **41b. Seed hafriyat: sefer başına fiyat** — güzergahlara şantiye fiyatı; fişler şantiyeyi borçlandırır.
- [x] **41c. Seed hafriyat: hakediş + tahsilat** — aylık şantiye hakedişi, şantiye tahsilatı; eski "Alıcı Cari hakediş" kaldırılır.
- [x] **42a. Seed nakliye: seferler** — KDV, tevkifat, şoför ücreti, komisyoncu.
- [x] **42b. Seed nakliye: tahsilat + ödeme** — müşteri tahsilatları, nakliyeci ve komisyoncu ödemeleri.
- [x] **43a. Seed çek/senet: alınanlar** — tahsil, ciro, birkaç karşılıksız.
- [x] **43b. Seed çek/senet: verilenler** — vadesinde ödenmesi.
- [x] **44. Açılış bakiyeleri ve cari virman**
- [x] **45. Fatura, stok ve plakaya satış verisi** (A–E bittikten sonra)
  - `CatalogStage`: stok kalemleri; `InvoiceStage`: alış/satış/`InvoiceService`, stok kullanım/sayım, plakaya satış; eski `queueSuppliers` sahte faturaları kaldırıldı (K5); ödemeler 38b hesap kurallarıyla; kasa fazından önce.
- [x] **46. `simulate:day` uyarlaması** (aynı kurallar günlük simülasyonda)
  - `DailyStage`: alış/satış/plaka `InvoiceService`; stok kullanım/sayım; eksi stok yok; sahte tedarikçi fatura cari kaldırıldı; kasa banka↔kasa virman (39); yakıt `purchase_price` (40a); şantiye hakediş/tahsilat (41c).
  - Bitti: `SimulateDayCommandTest` + bölüm sonu audit/fuzz.
