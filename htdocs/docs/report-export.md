# Rapor dışa aktarma: Excel / PDF / CSV / Yazdır

Bu dosya, raporlarda (ve gerekirse liste sayfalarında) elle yazılmış CSV export'unu ortak dışa aktarma yapısına taşıma işinin planıdır.  
Başka bir agent'a "bu md'yi uygula" diye verilebilir.

Hedef: her rapor yalnızca **ne yazılacağını** tanımlar (kolonlar, satırlar, toplamlar, özet). **Nasıl yazılacağını** ortak katman bilir; aynı tanımdan üç çıktı üretilir:

- **Excel (`.xlsx`):** logo, başlık, filtre özeti; koyu ve sabitlenmiş başlık satırı, otomatik filtre. Tutarlar gerçek sayı (`#,##0.00 "TL"`), tarihler gerçek tarih hücresi. Toplam satırı ve özet bloğu vurgulu; A4'e sığdıran yazdırma ayarı, sayfa numaralı alt bilgi.
- **PDF:** antet ve logo; her sayfada tekrarlanan tablo başlığı, "Sayfa X / Y", oluşturulma zamanı ve oluşturan kullanıcı. Bakiye renkli B/A; geniş raporlar yatay.
- **CSV:** ham veri; `;` ayraçlı, BOM'lu, Türkçe sayı biçimi. `=`, `+`, `-`, `@` ile başlayan metinler formül olarak çalışmasın diye `'` ile etkisizleştirilir.
- **Yazdır:** tarayıcı yazdırması değil; PDF'i yeni sekmede açar (`print=1`). Chrome/Edge yazdırma penceresini kendiliğinden açar, Safari'de Cmd+P.

---

## Hazır olanlar

Ortak katman (`app/Services/Reports/Export/`):

- `ReportDocument`: raporun formattan bağımsız tanımı. Alanlar: `title`, `subtitle`, `filename` (uzantısız), `columns`, `sections`, `details` (başlık altı filtre özeti, etiket => değer), `summary` (tablo altı özet, etiket => `ReportCell|string`), `landscape`, `emptyMessage`.
- `ReportColumn`: kolon tanımı. Kısayollar:
  - `text($key, $label, ?$width)`
  - `date($key, $label)`: değer `Y-m-d` string ya da Carbon'un parse edebildiği her şey.
  - `number($key, $label, $decimals = 0, ?$suffix, ?$width)`: ör. `number('liters', 'Litre', 2, 'lt')`.
  - `money($key, $label, $decimals = 2)`: `TL` birimli.
  - `balance($key, $label)`: cari bakiye; pozitif = borçlu (**B**), negatif = alacaklı (**A**), renkli. Ekrandaki `x-ui.balance` ile aynı kural.
- `ReportRow`: satır. Türleri:
  - `ReportRow::data([...])`: normal hareket satırı (zebra).
  - `ReportRow::opening([...])`: devir / geçmiş dönemler satırı (gri, italik).
  - `ReportRow::total('TOPLAM', [...])`: toplam satırı. Etiket, ilk dolu hücreye kadar olan baştaki boş kolonları birleştirerek yazılır.
  - Hücreler kolon anahtarına göre verilir; boş hücre için anahtarı vermeyin ya da `null` verin.
- `ReportCell`: tek hücre için kolonun ondalık / birim ayarını ezer. Aynı kolonda sefer (tam sayı), litre, saat gibi farklı birimler varsa kullanılır: `new ReportCell(317, 0, 'sefer')`, `new ReportCell(3100.0, 2, 'lt')`. Özet değerlerinde de kullanılır: `ReportCell::money(119711.23)`.
- `ReportSection`: satır grubu, isteğe bağlı başlıkla (`new ReportSection($rows, 'ALPHA TEDARİK')`). Birden çok cari / araç / şantiye listelenen raporlarda her grup bir bölümdür. Bölümde veri satırı yoksa toplam satırından önce `emptyMessage` otomatik yazılır.
- `ReportExporter::download($document, ReportFormat $format, bool $forPrint = false)`: indirme yanıtını üretir.
- `SpreadsheetReportWriter`: Excel çizimi. PDF şablonu: `resources/views/admin/reports/export/pdf.blade.php`.

Diğer parçalar:

- `App\Enums\ReportFormat` (`xlsx`, `pdf`, `csv`): route'ta enum binding ile kullanılır; geçersiz format 404 döner.
- `<x-ui.report-exports route="..." :query="$exportQuery" />`: Excel / PDF / CSV / Yazdır butonları (`resources/views/components/ui/report-exports.blade.php`). Renkleri `reports.css` içindeki `.report-export` kuralında.
- `public/img/logo-print.png`: beyaz kağıt için küçük logo (52 KB). `logo.png` koyu zemin içindir, baskıda kullanma.

Uygulanmış raporlar (örnek olarak bakılabilir):

- [x] **Plaka aylık döküm** (`VehicleMonthlyReportService::exportDocument`): tek bölüm, `ReportCell` ile karışık birimli kolon, devir satırı, toplam, özet bloğu.
- [x] **Firma cari** (`CompanyLedgerReportService::exportDocument`): cari başına bölüm, koşullu kolonlar (yakıt / piyasa), `balance` kolonu, yatay sayfa.

---

## Uygulama adımları (her rapor için)

### 1. Servis: `exportCsv()` yerine `exportDocument()`

`exportCsv()` metodunu sil, yerine `exportDocument(array $filters): ReportDocument` yaz (rapor bulunamayabiliyorsa `?ReportDocument`). Verinin kendisi için **mevcut `report()` metodunu kullan**; sorgu yazma. Ekrandaki tablo neyi gösteriyorsa onu aynı sırayla tanımla.

```php
public function exportDocument(array $filters): ReportDocument
{
    $report = $this->report($filters);

    $rows = [];

    foreach ($report['rows'] as $row) {
        $rows[] = ReportRow::data([
            'date' => $row['date'],
            'description' => $row['description'],
            'debit' => $row['debit'] > 0 ? $row['debit'] : null,
            'credit' => $row['credit'] > 0 ? $row['credit'] : null,
        ]);
    }

    $rows[] = ReportRow::total('TOPLAM', [
        'debit' => $report['total_debit'],
        'credit' => $report['total_credit'],
    ]);

    return new ReportDocument(
        title: $report['title'],
        subtitle: $report['period_label'],
        filename: sprintf('ornek-rapor-%04d-%02d', $report['year'], $report['month']),
        columns: [
            ReportColumn::date('date', 'Tarih'),
            ReportColumn::text('description', 'Açıklama'),
            ReportColumn::money('debit', 'Borç'),
            ReportColumn::money('credit', 'Alacak'),
        ],
        sections: [new ReportSection($rows)],
        details: ['Dönem' => $report['period_label']],
        summary: ['Bakiye' => ReportCell::money($report['balance'])],
    );
}
```

Kurallar:

- **Dosya adı:** eski `exportCsv()` içindeki `sprintf` kalıbını koru, yalnızca `.csv` uzantısını kaldır. Kullanıcıların dosya adı alışkanlığı bozulmasın.
- **Tutarları biçimlendirme:** `number_format` ile string'e çevirip verme; ham `float` ver. Biçimi kolon tipi belirler. Excel'de toplanabilmeleri buna bağlı.
- **Tarihler:** `Y-m-d` string ver; `d.m.Y`'ye çevirme.
- **Sıfır / boş:** ekranda boş görünen hücre için `null` ver (ör. borç 0 ise `null`). Toplam satırında 0 göstermek istiyorsan `0.0` ver.
- **`details`:** filtrede seçilen ama başlıkta görünmeyen her şey (plaka, cari türü, şantiye, dönem).
- **`landscape: true`:** 8 ve üzeri kolon ya da uzun metin kolonları varsa. Karar vermeden önce PDF'i üretip bak.
- Raporda hiç grup yoksa boş bir `new ReportSection([])` ver; boş mesaj otomatik yazılır.

### 2. Controller

`ReportExporter`'ı constructor'a ekle; `export()` metodu format alsın:

```php
public function export(OrnekReportRequest $request, ReportFormat $format): Response
{
    $filters = $request->filters();

    abort_if($filters === null, 404);

    return $this->exporter->download(
        $this->reports->exportDocument($filters),
        $format,
        $request->boolean('print'),
    );
}
```

Dönüş tipi `Symfony\Component\HttpFoundation\Response`; `StreamedResponse` importunu kaldır.

### 3. Route

`routes/admin.php` içinde path'e `{format}` ekle; **route adını değiştirme** (form request'ler `routeIs('...export')` ile kontrol ediyor):

```php
Route::get('reports/ornek/export/{format}', [OrnekReportController::class, 'export'])->name('reports.ornek.export');
```

`rg "reports.ornek.export"` ile route'u kullanan başka yer var mı kontrol et (testler, başka sayfalardaki linkler).

### 4. View

Tablo partial'ındaki Excel ve Yazdır butonlarını tek bileşenle değiştir:

```blade
<div class="panel-liste-bar report-actions">
    <x-ui.report-exports route="admin.reports.ornek.export" :query="$exportQuery" />
</div>
```

`$exportQuery` partial'ın başında zaten tanımlı; olduğu gibi kullan. Eski `data-report-print` butonu kalkar.

### 5. Yazdırma CSS'i (isteğe bağlı temizlik)

`resources/css/reports.css` içindeki `@media print` bloğunda raporun sınıfı (ör. `.ornek-report`) için kurallar var. Yazdır artık PDF açtığı için bunlar gereksiz; rapor taşındıktan sonra o raporun satırlarını silebilirsin. Tüm raporlar taşınınca `resources/js/reports.js` içindeki `bindPrint` / `data-report-print` de kaldırılabilir. **`employee-salaries` hâlâ kullanıyor**, önce ona bak.

### 6. Testler

Raporun test dosyasındaki export testlerini güncelle (`tests/Feature/Admin/VehicleMonthlyReportTest.php` ve `CompanyLedgerReportTest.php` örnek):

- Export route'una `'format' => 'csv'` ekle; CSV beklentilerini yeni kolon sırasına göre düzelt. Boşluk içeren alanlar `fputcsv` tarafından tırnağa alınır (`"Belge No"`).
- Bir **Excel testi:** dosyayı `IOFactory::load` ile aç; en az bir tutar hücresinin `DataType::TYPE_NUMERIC` olduğunu, toplam satırını ve dosya adını (`assertDownload`) doğrula. `loadSpreadsheet()` yardımcısını örnek testten kopyala.
- Bir **PDF testi:** `content-type: application/pdf`, `%PDF-` ile başlayan içerik, dosya adı.
- Validasyon testi (ör. "export plaka ister") varsa route'a format ekle.

Çalıştır: `php artisan test --compact tests/Feature/Admin/OrnekReportTest.php`, ardından `vendor/bin/pint --dirty --format agent`.

### 7. Görsel kontrol

Kullanıcı yapıyor. Yine de PDF'i üretip ilk sayfaya bakmak hızlıdır: veritabanı Docker'da olduğu için `docker exec hafline-app php artisan tinker` ile `exportDocument()` çağrılıp `ReportExporter::download(..., ReportFormat::Pdf)->getContent()` bir dosyaya yazılabilir; macOS'ta `sips -s format png dosya.pdf --out dosya.png` ilk sayfayı resme çevirir. Excel için `qlmanage -t -s 1400 -o . dosya.xlsx` (önizleme logoyu çizmez, dosyada vardır).

---

## Liste sayfalarına uygulama

Aynı yapı liste sayfalarında (araçlar, seferler, cari hareketleri vb.) da kullanılabilir; fark verinin kaynağıdır:

- Listenin **sayfalı** sorgusunu değil, aynı filtrelerle **sayfasız** sorgusunu kullan. Liste sayfasının repository / service'inde filtreyi uygulayan metodu paylaş; `paginate()` yerine `get()` ya da `lazy()` çağır.
- Yeni bir `export/{format}` route'u, liste ile aynı `can:` middleware grubunda olmalı. Liste neyi gösteriyorsa (yetki, şantiye kısıtı, silinmişler hariç) export da onu göstermeli.
- `details` alanına listede uygulanan filtreleri yaz; kullanıcı çıktının hangi aramaya ait olduğunu görmeli.
- Butonu liste araç çubuğuna (`partials/index/toolbar.blade.php`) koy; `:query="request()->query()"` ile mevcut filtreleri geçir (`page` parametresini çıkar).

**Hacim sınırı:** PhpSpreadsheet ve DomPDF tüm belgeyi bellekte kurar. Ölçüm: 180 satırlık (8 sayfa) cari raporu PDF'i 0,42 sn sürüyor. Sınır koymadan önce kabaca:

- PDF birkaç bin satırın üstünde hem yavaşlar hem okunmaz hale gelir. Liste sayfalarında PDF'i satır sayısıyla sınırla (ör. 2.000 üstünde "Excel kullanın" uyarısı) ya da yalnızca Excel / CSV sun.
- Excel on binlerce satırda bellek sınırına (container'da PHP `memory_limit`) yaklaşır. Böyle bir liste varsa önce kullanıcıya sor; CSV'yi akışla (`lazy()`) yazmak gerekebilir.

---

## Dikkat

- **Docker `zip` eklentisi:** `.xlsx` bir zip arşividir. `docker/php/Dockerfile`'a `zip` eklendi; image yeniden derlenmeden container'da Excel export hata verir: `docker compose build app && docker compose up -d app scheduler`. PDF ve CSV bundan etkilenmez.
- **Blade:** PDF şablonunda (ve aynı dosyada blok `@php ... @endphp` olan her şablonda) satır içi `@php(...)` kullanma; Blade derlemesi bozuluyor (`syntax error, unexpected token "@"`). Hep blok form kullan.
- **DomPDF CSS'i sınırlıdır:** flex / grid yok; düzen için tablo kullan. `counter(pages)` çalışmaz; sayfa numarası render sonrası `ReportExporter::stampPageNumbers()` ile basılıyor. Font `DejaVu Sans` (Türkçe karakterler için), değiştirme.
- **Excel'e metin:** metin hücreleri `setCellValueExplicit(..., TYPE_STRING)` ile yazılıyor; `=` ile başlayan açıklama formül olarak çalışmaz. Writer'a doğrudan `setCellValue` ile metin ekleme.
- **Test belleği:** DomPDF'in font önbelleği süreç başına bir kez yükleniyor; bu yüzden `phpunit.xml`'de `memory_limit` 512M. Sızıntı değil: arka arkaya PDF üretiminde bellek sabit kalıyor.
- **Matris raporlar** (`site-exits`, `dump-sites`): kolonlar sabit değil, dönemdeki günlere / döküm sahalarına göre oluşuyor. `columns` dizisini döngüyle kur (`ReportColumn::number("day_{$day}", ...)`); hücre anahtarları aynı adla verilmeli. Kolon sayısı fazlaysa `landscape: true`.
- Ortak katmana rapora özel bir şey ekleme. Bir rapor yeni bir görünüm istiyorsa (ör. yeni satır türü) önce mevcut türlerle çözülüp çözülmediğine bak; gerekiyorsa üç yazıcıya (Excel, PDF, CSV) birden ekle.

---

## Kalan raporlar

Servisteki eski metot ve view'daki partial verildi. Liste, `routes/admin.php` içindeki `export` route'larıdır.

- [x] `vehicle-monthly`: `VehicleMonthlyReportService::exportDocument`
- [x] `company-ledger`: `CompanyLedgerReportService::exportDocument`
- [ ] `monthly-vouchers`: `MonthlyVoucherReportService::exportCsv`, `partials/table.blade.php`
- [ ] `site-exits`: `SiteExitReportService::exportCsv`, `partials/matrix.blade.php` (matris, yukarıya bak)
- [ ] `dump-sites`: `DumpSiteReportService::exportCsv`, `partials/matrix.blade.php` (matris)
- [ ] `dump-site-payments`: `DumpSitePaymentReportService::exportCsv`
- [ ] `fuel-stations`: `FuelStationReportService::exportCsv`
- [ ] `fuel-stations-monthly`: `FuelStationMonthlyReportService::exportCsv`
- [ ] `ticket-control`: `TicketControlReportService::exportCsv`
- [ ] `vehicle-periodic`: `VehiclePeriodicReportService::exportCsv`
- [ ] `vehicle-site-monthly`: `VehicleSiteMonthlyReportService::exportCsv`
- [ ] `vehicle-balance`: `VehicleBalanceReportService::exportCsv` (bakiye kolonu: `balance`)
- [ ] `owner-monthly`: `OwnerMonthlyReportService::exportCsv`
- [ ] `owner-periodic`: `OwnerPeriodicReportService::exportCsv`
- [ ] `owner-balance`: `OwnerBalanceReportService::exportCsv` (`balance`)
- [ ] `vehicle-cost`: `VehicleCostReportService::exportVehicleCsv`, `partials/table.blade.php`
- [ ] `vehicle-cost/owners`: `VehicleCostReportService::exportOwnerSummaryCsv`, `partials/owner-table.blade.php`; controller `VehicleCostOwnerSummaryController`. Aynı serviste iki ayrı `export...Document` metodu olur.
- [ ] `company-balance`: `CompanyBalanceReportService::exportCsv` (`balance`)
- [ ] `site-cari`: `SiteCariReportService::exportCsv` (`balance`)
- [ ] `site-expense`: `SiteExpenseReportService::exportCsv`
- [ ] `cash-movements`: `CashMovementReportService::exportCsv`
- [ ] `employee-monthly`: `EmployeeMonthlyReportService::exportCsv`
- [ ] `employee-annual`: `EmployeeAnnualReportService::exportCsv`

Rapor dışı:

- [ ] `employee-salaries` (`resources/views/admin/employee-salaries/partials/table.blade.php`): export'u yok, yalnızca tarayıcı Yazdır'ı (`data-report-print`) var. Ya bu yapıya taşınır ya da tarayıcı yazdırması olduğu gibi kalır; kullanıcıya sor.
