# Eski hafriyat DB → hafline eşleme belgesi

Bu belge, eski hafriyat veritabanından (MySQL 5.7, `prod.sql`, 56 tablo) yeni hafline şemasına (PostgreSQL) yapılacak **tek seferlik** aktarımın yol haritasıdır. Aktarım komutu yazılırken tek kaynak bu belgedir.

- Kaynak şema: `hafriyat/HAFRİYAT PROGRAMI YEDEK/sakin_silme_hafriyat/hafriyat-docker/data/prod.sql`
- Kaynak kod (davranış referansı): `.../hafriyat-docker/htdocs/` (`eski/` kopyaları ve `fchart/` kapsam dışı)
- Yeni şema: `database/migrations/2026_10_15_0000xx_*.php`
- Rapor karşılıkları: [`reports.md`](reports.md)

## 1. Yaşam döngüsü

1. Canlıya geçiş günü eski sistem dondurulur (yeni kayıt girilmez).
2. Aktarım komutu çalışır. Komut **idempotent** olmalıdır: `legacy_id_maps` üzerinden daha önce aktarılan kayıt atlanır ya da güncellenir, baştan tekrar çalıştırılabilir.
3. Mutabakat (bölüm 9) temiz çıkana kadar eski DB silinmez.
4. Mutabakat sonrası `legacy_id_maps` bir cleanup migration'ı ile kaldırılır, eski DB silinir. Bu belge arşiv olarak kalır.

Delta ya da senkronizasyon yoktur.

## 2. Genel dönüşüm kuralları

| Konu | Kural |
|---|---|
| Birincil anahtar | Her yeni kayıt uuid7 alır. uuid7, kaydın **orijinal tarihinden** üretilir: `Str::uuid7($createdAt)`. Böylece id sırası zaman sırasıyla uyumlu kalır. |
| Eşleme | Her aktarılan kayıt için `legacy_id_maps(legacy_table, legacy_id, new_table, new_id)` yazılır. FK çözümü yalnızca bu tablodan yapılır. |
| PK'si olmayan tablolar | `dokum_yeri_fiyat`, `guzergah_plaka_tipi`, `guzergah_fiyat`: `legacy_id` bileşik yazılır (`"<a>:<b>"`, örn. `"12:7"`). Aynı bileşik anahtar birden çok kez geçiyorsa son satır alınır, diğerleri raporlanır. |
| `created_at` | Varsa `*_VGZ` (kayıt zamanı), yoksa işlem tarihi (`*_Zaman`, `*_Tarih`) 00:00. `updated_at = created_at`. |
| İşlem tarihi | DATETIME kaynaklar (`*_Zaman`) yeni şemada `occurred_on` (DATE) olur; saat atılır. Tek istisna `hauls.hauled_at` (DATETIME korunur). |
| Sıfır tarih | `'0000-00-00'` ve `'0000-00-00 00:00:00'` → `NULL`. Kolon zorunluysa kayıt atlanır ve raporlanır. |
| Sayılar | `float` → `decimal`, aktarımda `round(x, 2)` (birim fiyatlarda 4 hane). `int` tutarlar (`Cks_Tutar`, `Cks_Kredi_Tutar`, `PF_Litre`) olduğu gibi aktarılır. |
| Negatif tutar | Borç/alacak kolonlu tablolarda negatif tutar karşı kolona pozitif yazılır (A −100 → debit 100). Tek `amount` kolonlu tablolarda (`> 0` CHECK) negatif ve sıfır tutarlı kayıt atlanır, raporlanır. |
| Y/N | `'Y'` → `true`, `'N'` → `false`, `''` → `true` (eski varsayılan). |
| Boş metin | `''` → `NULL` (nullable kolonlarda). Metinler `trim()` edilir. |
| Sıfır FK | `0` ya da `NULL` FK → `NULL`. Kolon zorunluysa bölüm 8 (yetim kayıt politikası) uygulanır. |
| Silinmiş kayıt | Eski sistemde soft delete yok; her satır aktif olarak aktarılır (`deleted_at = NULL`). |
| `created_by` | Eski tablolarda kaydı giren kullanıcı tutulmuyor → `NULL`. (`dokumkontrol`, `yakitkontrol`, `notlar` hariç; onlarda `user_id` var.) |

## 3. Borç / alacak bakış açısı

Tüm parasal kolonlar **karşı tarafın hesabı** açısından tutulur:

- **debit (borç):** karşı taraf bize borçlanır ya da biz ona ödeme yaptık.
- **credit (alacak):** biz karşı tarafa borçlanırız (karşı taraf alacaklanır).
- **Bakiye = SUM(debit) − SUM(credit).** Pozitif: karşı taraf bize borçlu. Negatif: biz borçluyuz.

Tek yönlü tablolarda tek bir pozitif `amount` vardır; yönü `ledger_entries` view'i belirler:

| Kaynak | Taraf (`party_type`) | Yön |
|---|---|---|
| `hauls.price` | `vehicle` | credit |
| `hauls.dump_price` | `dump_site` | credit |
| `fuel_receipts.amount` | `vehicle` | debit |
| `vehicle_payments.amount` | `vehicle` | debit |
| `vehicle_receivables.amount` | `vehicle` | credit |
| `vehicle_fines.amount` | `vehicle` | debit, **yalnızca `status = 'paid'`**, tarih `paid_on` (bekleyen ceza bakiyeyi etkilemez) |
| `vehicle_owner_payments.amount` | `vehicle_owner` | debit |
| `dump_site_payments.amount` | `dump_site` | debit |
| `site_transactions` | `site` | kolondan (gider = debit, gelir = credit) |
| `company_transactions` | `company` (+`party_role`) | kolondan |
| `employee_salaries.earned_salary` | `employee` | credit |
| `employee_bonuses.amount` | `employee` | credit |
| `employee_payments.amount` | `employee` | debit |
| `cash_entries` | `cash` | kolondan (giriş = debit, çıkış = credit) |
| `other_payments.amount` | `other` | debit |

`financial_instruments` view'e girmez: çek/senet bir ödeme aracıdır, ödemenin kendisi ilgili ödeme tablosunda zaten vardır (çift sayım olmasın).
`haul_dumps` ve `machine_loadings` de view'e girmez: eski bakiye raporlarının hiçbiri bu tabloları okumuyordu.

**Eski işaretle fark:** Eski cari ve plaka bakiye raporları `Alacak − Borç` gösteriyordu (pozitif = biz borçluyuz). Yeni kural bunun tersidir. Arayüz, eski kullanıcı alışkanlığı için bakiyeyi "(B)" / "(A)" etiketiyle gösterebilir.

## 4. Kod (enum) eşlemeleri

### Kesinleşenler

| Eski kolon | Eski değer | Yeni kolon | Yeni değer | Kanıt |
|---|---|---|---|---|
| `cari_fis.CF_Odeme_Turu`, `alicilar_fis.CF_Odeme_Turu`, `petrol_fis.PF_Odeme_Turu`, `piyasalar_fis.piyasalar_Odeme_Turu` | `A` (Alacak) | `company_transactions.credit` | tutar | `rapor_saticilar_bakiye_sonuc.php` (A = Alacak kolonu) |
| aynı | `B` (Borç) | `company_transactions.debit` | tutar | aynı |
| aynı | `''` (boş) | — | **aktarılmaz** | Eski raporların hiçbir toplamına girmiyordu. Aktarım raporunda ayrı listelenir, tek tek karar verilir. |
| `santiye_odeme.Santiye_Odeme_Tur` | `C` (gider) | `site_transactions.debit` | tutar | `cari_oran_sonuc.php` (C = Diğer gider) |
| aynı | `G` (gelir / hakediş) | `site_transactions.credit` | tutar | aynı (G = Hakediş) |
| `nakit_girisi.Nakit_Islem` | `G` (giriş) | `cash_entries.debit` | tutar | `nakit_islemler.php` (Giriş kolonu) |
| aynı | `C` (çıkış) | `cash_entries.credit` | tutar | aynı |
| aynı | `NULL` | — | **aktarılmaz**, raporlanır | Eski toplamlara girmiyordu. |
| `cks_fis.Cks_Hareket` | `G` (giriş, alınan) | `financial_instruments.debit` | `Cks_Tutar` | `cksfis_islemler.php` |
| aynı | `C` (çıkış, verilen) | `financial_instruments.credit` | `Cks_Tutar` | aynı |
| `cks_fis.Cks_Tur` | `C` / `S` / `K` | `kind` | `cheque` / `promissory_note` / `loan` | aynı |
| `cks_fis.Cks_Durum` | `A` / `P` / `T` / `I` | `status` | `active` / `closed` / `collateral` / `unused` | aynı (`I` hiçbir ekranda seçilemiyor) |
| `cks_kredi.Cks_Kredi_Durum` | `A` / `P` | `financial_instrument_installments.status` | `active` / `paid` | aynı |
| `plaka_ceza.Plaka_Ceza_Durum` | `A` / `P` | `vehicle_fines.status` | `pending` / `paid` | ceza ekranı |
| `tasima.Tasima_Vardiya`, `yukleme.Yukleme_Vardiya`, `yakit_fisi.Yakit_Fisi_Vardiya` | `S` / `N` | `shift` | `day` / `night` | `tasima_detay.php:48-49` (S = GUNDUZ, N = GECE) |
| `fisteslim.Fisteslim_Vardiya`, `dokumkontrol.*_Vardiya`, `yakitkontrol.*_Vardiya` | `D` / `N` | `shift` | `day` / `night` | `fisteslim_detay.php:51-52` (D = Gündüz, N = Gece) |
| `yonetici.vardiya` | `0` / diğer | `users.shift` | `night` / `day` | `yonetici_detay.php:42` (0 = GECE, aksi GUNDUZ) |
| Firma kaynak tablosu | `cari_*` / `alicilar_*` / `petrol_*` / `piyasalar_*` | `company_roles.role`, `company_transactions.role` | `supplier` / `customer` / `fuel` / `market` | menü adları |
| `odeme_tipi` | id 3 "Nakit", id 4 "Çek" | `payment_types.kind` | `cash` / `cheque` | `prod.sql` verisi |

Bilinmeyen ya da boş vardiya kodu → `NULL` (kolon nullable ise), zorunlu ise kayıt raporlanır.

### Vardiyanın saklanması

Tüm tablolarda `shift varchar(10)` + `CHECK (shift IN ('day', 'night'))`; PHP tarafında `App\Enums\Shift` backed enum (etiketler Gündüz / Gece). PostgreSQL'in yerel `ENUM` tipi bilinçli olarak kullanılmadı: değer silinemez/yeniden adlandırılamaz, her değişiklik ayrı DDL ister ve testlerdeki sqlite'ta yoktur. CHECK kısıtı aynı veri bütünlüğünü sağlar.

## 5. Aktarım sırası

FK bağımlılığına göre:

1. `cities` → `districts` → `neighborhoods`
2. `payment_types`, `cash_categories`, `banks`, `vehicle_classes`
3. `sites`, `dump_sites` → `fuel_stations` → `vehicle_owners` → `vehicles`
4. `haul_routes` → `route_class_prices`, `route_vehicle_prices`, `dump_site_class_prices`
5. `users` (yonetici)
6. `companies` + `company_roles` → `company_transactions`
7. `employees` → `employee_periods`, `employee_salaries`, `employee_payments`, `employee_bonuses`
8. `hauls` → `haul_dumps`; `machine_loadings`; `fuel_receipts`; `ticket_handovers`, `dump_checks`, `fuel_checks`
9. `dump_site_payments`, `site_transactions`, `vehicle_payments`, `vehicle_receivables`, `vehicle_fines`, `vehicle_owner_payments`, `other_payments`, `cash_entries`
10. `financial_instruments` (önce tümü, sonra ikinci geçişte `endorsed_from_id`) → `financial_instrument_installments`, `financial_instrument_attachments`
11. `notes`

## 6. Tablo ve kolon eşlemesi

Her tabloda ortak kolonlar (`id`, `created_at`, `updated_at`, `deleted_at`) bölüm 2'ye göre doldurulur ve aşağıda tekrar edilmez.

### 6.1 Lokasyon

**`sehirler` → `cities`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `sehir_id` | `legacy_id_maps` | — |
| `sehir_adi` | `name` | trim |

**`ilceler` → `districts`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `ilce_id` | `legacy_id_maps` | — |
| `ilce_sehir` | `city_id` | zorunlu; yoksa atla |
| `ilce_adi` | `name` | trim |

**`semtler` → `neighborhoods`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `semt_id` | `legacy_id_maps` | — |
| `semt_ilce` | `district_id` | zorunlu; yoksa atla |
| `semt_adi` | `name` | trim |

### 6.2 Sabit tablolar

**`odeme_tipi` → `payment_types`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Odeme_Tipi_ID` | `legacy_id_maps` | — |
| `Odeme_Tipi_Adi` | `name` | trim |
| — | `kind` | "Nakit" → `cash`, "Çek" → `cheque`, diğer → `other` (aktarım sırasında elle teyit) |
| `Odeme_Tipi_Aktif` | `active` | Y/N |

**`cari_kategori` → `cash_categories`**: `cari_kategori_id` → map, `cari_kategori_ad` → `name`, `active = true`.

**`cks_banka` → `banks`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Banka_Id` | `legacy_id_maps` | — |
| `Banka_Adi` | `name` | trim |
| `Banka_Temsilci` | `representative` | `''` → null |
| `Banka_Telefon` | `phone` | `'0'` → null |
| `Banka_Hesap` | `account_no` | `''` → null |

**`plaka_tipi` → `vehicle_classes`**: `PT_ID` → map, `PT_Adi` → `name`, `PT_Aktif` → `active`. (Canlı veride id 7–11.)

### 6.3 Filo ve güzergah

**`santiye` → `sites`**: `Santiye_ID` → map, `Santiye_Adi` → `name`, `Santiye_Aktif` → `active`.

**`dokum_yeri` → `dump_sites`**: `DY_ID` → map, `DY_Adi` → `name`, `DY_Aktif` → `active`.

**`yakit_yeri` → `fuel_stations`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Yakit_Yeri_ID` | `legacy_id_maps` | — |
| `Yakit_Yeri_Adi` | `name` | trim |
| `Yakit_Yeri_Birim_Fiyati` | `unit_price` | round 4 |
| `yer` | `site_id` | varchar içinde bir `santiye` id'si; sayısal ve eşlenebiliyorsa site, değilse null + rapor |
| `Yakit_Yeri_Aktif` | `active` | Y/N |

**`plaka_sahibi` → `vehicle_owners`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Plaka_Sahibi_ID` | `legacy_id_maps` | — |
| `Plaka_Adi` / `Plaka_Soyadi` | `first_name` / `last_name` | trim; ikisi de boşsa `first_name = '(isimsiz)'` + rapor |
| `Plaka_Tel` | `phone` | `''` → null |
| `Plaka_Gsm` | `mobile` | `''` → null |
| `Plaka_Adres` | `address` | `''` → null |
| `Plaka_Sehir` (varchar!) | `city_id` | Metin `cities.name` ile Türkçe büyük/küçük harf duyarsız eşleşirse id, yoksa null + rapor |
| `Plaka_Ilce` | `district_id` | map |
| `Plaka_Semt` | `neighborhood_id` | map |
| `Plaka_Zaman` | `created_at` | — |

**`plaka` → `vehicles`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Plaka_ID` | `legacy_id_maps` | — |
| `Plaka_No` | `plate` (+ `plate_key`) | Model setter ile normalize. Aynı `plate_key` iki kez geçerse ikincisi atlanır, eski id'si birinciye map'lenir + rapor. |
| `Plaka_Sahibi_ID` | `owner_id` | map |
| `Plaka_Plaka_Tipi` | `vehicle_class_id` | map |
| — | `driver_name` | null (eski plakada şoför yok) |
| `Plaka_Aktif` | `active` | Y/N |

**`guzergahlar` → `haul_routes`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Guzergah_ID` | `legacy_id_maps` | — |
| `Guzergah_Santiye_ID` | `site_id` | zorunlu |
| `Guzergah_DY_ID` | `dump_site_id` | zorunlu |
| `Guzergah_Fiyat` | `price` | round 2 |
| `Guzergah_Prim` | `bonus` | round 2 |
| `Guzergah_Aktif` | `active` | Y/N |

Aynı (şantiye, döküm yeri) çifti için birden fazla aktif güzergah varsa (yeni şemada partial unique): ilki aktarılır, diğerlerinin eski id'leri ilkine map'lenir + rapor. Fiyat farkı varsa raporda gösterilir.

**`guzergah_plaka_tipi` → `route_class_prices`**: `GPT_Guzergah` → `route_id`, `GPT_Plaka_Tipi` → `vehicle_class_id`, `GPT_Fiyat` → `price`. `legacy_id = "GPT_Guzergah:GPT_Plaka_Tipi"`.

**`guzergah_fiyat` → `route_vehicle_prices`**: `GF_Guzergah` → `route_id`, `GF_Plaka` → `vehicle_id`, `GF_Fiyat` → `price`. `legacy_id = "GF_Guzergah:GF_Plaka"`.

**`dokum_yeri_fiyat` → `dump_site_class_prices`**: `DYF_DY` → `dump_site_id`, `DYF_Plaka_Tipi` → `vehicle_class_id`, `DYF_Fiyat` → `price`. `legacy_id = "DYF_DY:DYF_Plaka_Tipi"`.

### 6.4 Firmalar (birleşik)

Dört eski firma tablosu tek `companies` tablosunda birleşir. **Aynı isimli firmalar birleştirilmez**: her eski firma satırı ayrı bir yeni firma olur ve tek bir `company_roles` satırı alır. `legacy_table` kaynak tablonun adıdır, bu yüzden eski id'ler çakışmaz.

| Eski tablo | `legacy_table` | `company_roles.role` |
|---|---|---|
| `cari_firma` | `cari_firma` | `supplier` |
| `alicilar_firma` | `alicilar_firma` | `customer` |
| `petrol_firma` | `petrol_firma` | `fuel` |
| `piyasalar_firma` | `piyasalar_firma` | `market` |

Kolonlar (önek `cari_` / `alicilar_` / `Petrol_` / `piyasalar_`):

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `*_ID` | `legacy_id_maps` | — |
| `*_Firma_Adi` | `name` | trim |
| `*_Adres` | `address` | `''` → null |
| `*_Telefon` | `phone` | `''` → null |
| `*_Fax` | `fax` | `''` → null |
| `*_VD` | `tax_office` | `''` → null |
| `*_VN` | `tax_number` | `''` → null; 20 karakterden uzunsa rapor |
| `*_Sehir` / `*_Ilce` / `*_Semt` | `city_id` / `district_id` / `neighborhood_id` | map |
| `*_Aktif` | `active` | Y/N |
| — | `company_group_id` | null (gruplar aktarım sonrası elle atanır) |

### 6.5 Firma fişleri (birleşik) → `company_transactions`

| Eski tablo | `legacy_table` | `role` |
|---|---|---|
| `cari_fis` | `cari_fis` | `supplier` |
| `alicilar_fis` | `alicilar_fis` | `customer` |
| `petrol_fis` | `petrol_fis` | `fuel` |
| `piyasalar_fis` | `piyasalar_fis` | `market` |

| Eski (`cari_fis` / `alicilar_fis` adları) | `petrol_fis` | `piyasalar_fis` | Yeni | Dönüşüm |
|---|---|---|---|---|
| `CF_ID` | `PF_ID` | `piyasalar_ID` | `legacy_id_maps` | — |
| `CF_Firma` | `PF_Firma` | `piyasalar_Firma` | `company_id` | aynı rolün firma tablosundan map; zorunlu |
| `CF_Miktar` + `CF_Odeme_Turu` | `PF_Miktar` + `PF_Odeme_Turu` | `piyasalar_Miktar` + `piyasalar_Odeme_Turu` | `debit` / `credit` | A → credit, B → debit, `''` → aktarılmaz (bölüm 4) |
| `CF_Odeme_Zaman` | `PF_Odeme_Zaman` | `piyasalar_Odeme_Zaman` | `occurred_on` | DATE |
| `CF_Odeme_Tipi` | `PF_Odeme_Tipi` | `piyasalar_Odeme_Tipi` | `payment_type_id` | map |
| `CF_Belge_No` | `PF_Belge_No` | `piyasalar_Belge_No` | `document_no` | `''` → null |
| `CF_Aciklama` | `PF_Aciklama` | `piyasalar_Aciklama` | `description` | `''` → null |
| `CF_Yakit_Yeri` | `PF_Yakit_Yeri` | — | `fuel_station_id` | map (market rolünde CHECK gereği null) |
| — | `PF_Litre` | — | `liters` | yalnız fuel; `0` → null |
| — | `PF_Birim_Fiyat` | — | `unit_price` | yalnız fuel; `0` → null |
| — | — | `piyasalar_plaka` | `vehicle_id` | yalnız market |
| — | — | `piyasalar_sahibi` | `vehicle_owner_id` | yalnız market |
| `CF_VGZ` | `PF_VGZ` | `piyasalar_VGZ` | `created_at` | — |

### 6.6 Personel

**`personel` → `employees`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Personel_ID` | `legacy_id_maps` | — |
| `Personel_Adi` / `Personel_Soyadi` | `first_name` / `last_name` | trim |
| `Personel_Isleme_Baslama_Tarihi` | `started_on` | sıfır tarih → null |
| `Personel_Adres` | `address` | `''` → null |
| `Personel_Sehir` / `_Ilce` / `_Semt` | `city_id` / `district_id` / `neighborhood_id` | map |
| `Personel_Telefon` / `Personel_Cep` | `phone` / `mobile` | `''` → null |
| `Personel_Arac` | `vehicle_info` | serbest metin |
| `Personel_Meslek` | `occupation` | `''` → null |
| `Personel_Maas` | `salary` | round 2 |
| `Personel_Hesap_No` | `bank_account_no` | `''` → null; 100 karakterden uzunsa rapor |
| `Personel_Aktif` | `active` | Y/N |

**`personel_giris_cikis` → `employee_periods`**: `PGC_Personel` → `employee_id` (zorunlu), `PGC_Giris` → `started_on`, `PGC_Cikis` → `ended_on` (sıfır tarih → null). `ended_on < started_on` ise (pgsql CHECK) rapor + atla.

**`personel_maas` → `employee_salaries`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `PM_ID` | `legacy_id_maps` | — |
| `PM_Personel` | `employee_id` | zorunlu |
| `PM_Donem` | `period` | ayın 1'ine normalize |
| `PM_Donem_Maas` | `base_salary` | round 2 |
| `PM_Calisilan_Gun` | `worked_days` | — |
| `PM_Hakedilen_Maas` | `earned_salary` | round 2 |

Aynı (personel, dönem) için birden fazla kart varsa (partial unique): en yüksek `PM_ID` aktarılır, diğerleri raporlanır. Eski raporlar bu durumda `LIMIT 1` ile rastgele birini okuyordu.

**`personel_odeme` → `employee_payments`**: `PO_Personel` → `employee_id`, `PO_Miktar` → `amount`, `PO_Odeme_Tipi` → `payment_type_id`, `PO_Aciklama` → `description`, `PO_Donem` → `period` (ayın 1'i), `PO_VGZ` → `occurred_on` (tarih kısmı; VGZ null ise `period`) ve `created_at`.

**`personel_pirim` → `employee_bonuses`**: `PP_Personel` → `employee_id`, `PP_Pirim` → `amount`, `PP_Tarih` → `occurred_on`, `PP_Aciklama` → `description`, `PP_VGZ` → `created_at`.

### 6.7 Operasyon

**`tasima` → `hauls`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Tasima_ID` | `legacy_id_maps` | — |
| `Tasima_Fisno` | `ticket_no` | trim; boşsa `'L-' . Tasima_ID` + rapor |
| `Tasima_Plaka_ID` | `vehicle_id` | zorunlu |
| `Tasima_Sahibi` | `owner_id` | map; boşsa aracın sahibi |
| `Tasima_Guzergah_ID` | `route_id` | zorunlu (bkz. not) |
| `Tasima_Santiye_ID` | `site_id` | map; boşsa güzergahın şantiyesi |
| `Tasima_DY_ID` | `dump_site_id` | map; boşsa güzergahın döküm yeri |
| `Tasima_Aktif_Sofor` | `driver_name` | serbest metin |
| `Tasima_Ton` | `tonnage` | — |
| `Tasima_Fiyat` | `price` | **olduğu gibi** (yeniden hesaplanmaz) |
| `Tasima_Dokum_Fiyat` | `dump_price` | **olduğu gibi** |
| `Tasima_Vardiya` | `shift` | `S` → `day`, `N` → `night` (bölüm 4) |
| `Tasima_Zaman` | `hauled_at` | DATETIME korunur |
| `Tasima_VGZ` | `created_at` | sıfırsa `Tasima_Zaman` |

Not: `route_id` NULL olan ya da eşlenemeyen taşımalar zorunlu FK yüzünden atlanır. Bu taşımalar plaka sahibinin alacağını taşıdığı için **atlanan her kaydın tutarı mutabakat raporunda araç bazında ayrıca gösterilir**. Sayı anlamlıysa aktarım öncesi eksik güzergahlar eski DB'de tamamlanır.

Not: `site_id` / `dump_site_id` güzergahınkiyle uyuşmuyorsa taşımanın kendi değerleri korunur, tutarsızlık raporlanır (eski giriş ekranı bunları güzergahtan bağımsız gönderebiliyordu).

**`tasima_dokum` → `haul_dumps`**: `TD_Tasima` → `haul_id` (nullable), `TD_Plaka` → `vehicle_id`, `TD_DY` → `dump_site_id`, `TD_Fiyat` → `price`, `TD_Ton` → `tonnage`, `TD_Zaman` → `dumped_on`, `TD_VGZ` → `created_at`.

**`yukleme` → `machine_loadings`**: `Yukleme_Fisno` → `ticket_no`, `Yukleme_Plaka_ID` → `vehicle_id`, `Yukleme_Sahibi` → `owner_id`, `Yukleme_Santiye_ID` → `site_id`, `Yukleme_Fiyat` → `price`, `Yukleme_Dokum_Fiyat` → `dump_price`, `Yukleme_Aktif_Sofor` → `driver_name`, `Yukleme_Vardiya` → `shift`, `Yukleme_Saat` → `hours`, `Yukleme_Zaman` → `loaded_on`, `Yukleme_VGZ` → `created_at`.

**`yakit_fisi` → `fuel_receipts`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Yakit_Fisi_ID` | `legacy_id_maps` | — |
| `Yakit_Fisi_Fisno` | `ticket_no` | `''` → null |
| `Yakit_Fisi_Plaka_ID` | `vehicle_id` | zorunlu |
| `Yakit_Fisi_Sahibi` | `owner_id` | map; boşsa aracın sahibi |
| `Yakit_Fisi_Yakit_Yeri_ID` | `fuel_station_id` | map (nullable) |
| `Yakit_Fisi_Santiye_ID` | `site_id` | **Eski PHP bu kolonu hiç yazmıyor (boş).** Doluysa map; boşsa yakıt yerinin şantiyesi (`yakit_yeri.yer` → `fuel_stations.site_id`). |
| `Yakit_Fisi_Miktar` | `quantity` | round 2 |
| `Yakit_Fisi_Birim_Fiyati` | `unit_price` | round 4 |
| — | `amount` | generated (`round(quantity * unit_price, 2)`), yazılmaz |
| `Yakit_Fisi_Zaman` | `issued_on` | DATE |
| `Yakit_Fisi_Km` | `km` | — |
| `Yakit_Fisi_Alan` | `received_by` | serbest metin |
| `Yakit_Fisi_Vardiya` | `shift` | bölüm 4 |
| `Yakit_Fisi_Tasima_Fis_Adet` | `haul_ticket_count` | — |
| `Yakit_Fisi_VGZ` | `created_at` | — |

**`fisteslim` → `ticket_handovers`**: `Fisteslim_Plaka` → `vehicle_id`, `Fisteslim_Guzergah` → `route_id`, `Fisteslim_Hareketadet` → `haul_count`, `Fisteslim_Yakitadet` → `fuel_count`, `Fisteslim_Aciklama` → `description`, `Fisteslim_Tarih` → `handed_on` (null ise VGZ tarihi, o da yoksa atla), `Fisteslim_Vardiya` → `shift`, `Fisteslim_VGZ` → `created_at`.

**`dokumkontrol` → `dump_checks`**: `Dokumkontrol_Guzergah` → `route_id`, `Dokumkontrol_Hareketadet` → `haul_count`, `Dokumkontrol_Aciklama` → `description`, `Dokumkontrol_Tarih` → `checked_on`, `Dokumkontrol_Vardiya` → `shift`, `Dokumkontrol_Kullanici` → `user_id`, `Dokumkontrol_VGZ` → `created_at`.

**`yakitkontrol` → `fuel_checks`**: `Yakitkontrol_Tanker` → `fuel_station_id`, `Yakitkontrol_Hareketadet` → `haul_count`, `Yakitkontrol_Yakitadet` → `fuel_count`, `Yakitkontrol_Aciklama` → `description`, `Yakitkontrol_Tarih` → `checked_on`, `Yakitkontrol_Vardiya` → `shift`, `Yakitkontrol_Kullanici` → `user_id`, `Yakitkontrol_VGZ` → `created_at`.

### 6.8 Ödemeler

| Eski tablo | Yeni tablo | Kolonlar |
|---|---|---|
| `dokum_odeme` | `dump_site_payments` | `Dokum_Odeme_DY_ID` → `dump_site_id`, `Dokum_Odeme_Tipi` → `payment_type_id`, `Dokum_Odeme_Miktar` → `amount`, `Dokum_Odeme_Zaman` → `occurred_on`, `Dokum_Cek_No` → `cheque_no`, `Dokum_Odeme_Aciklama` → `description`, `Dokum_Odeme_VGZ` → `created_at` |
| `santiye_odeme` | `site_transactions` | `Santiye_Odeme_Santiye_ID` → `site_id`, `Santiye_Odeme_Tipi` → `payment_type_id`, `Santiye_Odeme_Miktar` + `Santiye_Odeme_Tur` → `debit` (C) / `credit` (G), `Santiye_Odeme_Zaman` → `occurred_on`, `Santiye_Odeme_Aciklama` → `description` |
| `plaka_odeme` | `vehicle_payments` | `Plaka_Odeme_Plaka_ID` → `vehicle_id`, `Plaka_Odeme_Sahibi` → `owner_id` (boşsa aracın sahibi), `Plaka_Odeme_Tipi` → `payment_type_id`, `Plaka_Odeme_Miktar` → `amount`, `Plaka_Odeme_Zaman` → `occurred_on`, `Plaka_Odeme_Aciklama` → `description`, `Plaka_Odeme_Piyasa_ID` → `company_transaction_id` (`piyasalar_fis` map'i) |
| `plaka_alacak` | `vehicle_receivables` | `Plaka_Alacak_Plaka_ID` → `vehicle_id`, aracın o anki sahibi → `owner_id`, `Plaka_Alacak_Tipi` → `payment_type_id`, `Plaka_Alacak_Miktar` → `amount`, `Plaka_Alacak_Zaman` → `occurred_on`, `Plaka_Alacak_Aciklama` → `description`, `Plaka_Alacak_Piyasa_ID` → `company_transaction_id` |
| `plaka_ceza` | `vehicle_fines` | `Plaka_Ceza_Plaka_ID` → `vehicle_id`, aracın o anki sahibi → `owner_id`, `Plaka_Ceza_Guzergah` → `route_id`, `Plaka_Ceza_Miktar` → `amount`, `Plaka_Ceza_Zaman` → `occurred_on`, `Plaka_Ceza_Belge_No` → `document_no` (`0` → null), `Plaka_Ceza_Sofor` → `driver_name`, `Plaka_Ceza_Durum` → `status`, `Plaka_Ceza_Aciklama` → `description`; `P` ise `paid_on` = `date(Plaka_Ceza_VGZ)` (kolon `ON UPDATE CURRENT_TIMESTAMP`, yani ödendi işaretlendiği an; boşsa `Plaka_Ceza_Zaman`), `A` ise `paid_on` = null. Eski sistemde ödeme tipi tutulmuyordu → `payment_type_id` null. |
| `sofor_odeme` | `vehicle_owner_payments` | `Sofor_Odeme_Sofor_ID` → `vehicle_owner_id` (**`plaka_sahibi` id'si**, adı yanıltıcı), `Sofor_Odeme_Tipi` → `payment_type_id`, `Sofor_Odeme_Miktar` → `amount`, `Sofor_Odeme_Zaman` → `occurred_on`, `Sofor_Odeme_Aciklama` → `description`, `Sofor_Odeme_Piyasa_ID` → `company_transaction_id` |
| `diger_odeme` | `other_payments` | `Diger_Odeme_Odeme_Tipi` → `payment_type_id`, `Diger_Odeme_Miktar` → `amount`, `Diger_Odeme_Zaman` → `occurred_on`, `Diger_Odeme_Aciklama` → `description` |
| `nakit_girisi` | `cash_entries` | `Nakit_Kalem` → `cash_category_id`, `Nakit_Banka` → `bank_id`, `Nakit_Girisi_Miktar` + `Nakit_Islem` → `debit` (G) / `credit` (C), `Nakit_Girisi_Zaman` → `occurred_on`, `Nakit_Girisi_Aciklama` → `description` |

Hepsinde `*_VGZ` → `created_at`.

### 6.9 Çek / senet / kredi

**`cks_fis` → `financial_instruments`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `Cks_Id` | `legacy_id_maps` | — |
| `Cks_Tur` | `kind` | bölüm 4 |
| `Cks_Durum` | `status` | bölüm 4 |
| `Cks_Tutar` + `Cks_Hareket` | `debit` (G) / `credit` (C) | `Cks_Tutar` int; NULL ya da 0 ise atla + rapor |
| `Cks_Duzenlenme_Tarih` | `issued_on` | sıfır tarih → null |
| `Cks_Vade_Tarih` | `due_on` | sıfır tarih → null |
| `Cks_Firma` | `company_name` | serbest metin; firmaya bağlanmaz |
| `Cks_Ilgili` | `related_party` | serbest metin |
| `Cks_Aciklama` | `description` | `''` → null |
| `Cks_Taksit_Sayi` | `installment_count` | — |
| `Cks_Banka` | `bank_id` | map |
| `Cks_Hesap_No` | `account_no` | `''` → null |
| `Cks_Belge_No` | `document_no` | `0` → null; partial unique |
| `Cks_Alinan_No` | `received_no` | partial unique |
| `Cks_Verilen_No` | `given_no` | partial unique |
| `Cks_Cari_Kategori` | `cash_category_id` | map |
| `Cks_Bag_Tablo` + `Cks_Bag_Kayit_Id` | `dump_site_payment_id` | yalnızca `Cks_Bag_Tablo = 'dokum_odeme'` ise `dokum_odeme` map'i; başka değer varsa rapor |
| `Cks_Alinan_No` | `endorsed_from_id` | ikinci geçiş: `Cks_Belge_No = bu.Cks_Alinan_No` olan kayıt (ciro zinciri) |
| `Cks_VGZ` | `created_at` | — |

**`cks_kredi` → `financial_instrument_installments`**: `Cks` → `financial_instrument_id` (zorunlu), `Cks_Kredi_Tutar` → `amount`, `Cks_Kredi_Odeme_Tarih` → `due_on`, `Cks_Kredi_Odendigi_Tarih` → `paid_on`, `Cks_Kredi_Durum` → `status`, `Cks_Makbuz_No` → `receipt_no` (`''` → null).

**`cks_resim` → `financial_instrument_attachments`**: `resim_data` → `financial_instrument_id`, `resim_dosya` → `path`, `resim_dosya1` → `secondary_path`. Dosyaların kendisi eski sunucudan `storage/app/legacy/cks/` altına kopyalanır, `path` yeni konuma göre yazılır.

### 6.10 Diğer

**`yonetici` → `users`**

| Eski | Yeni | Dönüşüm |
|---|---|---|
| `yonetici_id` | `legacy_id_maps` | — |
| `adi_soyadi` | `name` | trim |
| `kullanici` | `username` | trim; partial unique |
| `sifre` | `password` | **Eski şifre düz metin.** `Hash::make()` ile hash'lenir; ilk girişte değiştirme zorunlu tutulmalı. |
| — | `email` | Eski tabloda yok → `NULL`. Giriş alanı e-posta **veya** kullanıcı adı kabul eder (`User::credentials`); `users_login_check` en az birinin dolu olmasını zorunlu tutar. |
| `yonetici_aktif` | `active` | Y/N; pasif kullanıcı giriş yapamaz |
| `santiye` | `site_id` | map |
| `vardiya` | `shift` | `0` → `night`, diğer → `day` (bölüm 4) |
| `yetki_*` ve diğer Y/N yetki kolonları (45 adet) | izin (permission) atamaları | Her Y kolonu aynı adlı bir izne çevrilir. İzin altyapısı (spatie/laravel-permission) kurulduğunda bu tablo genişletilecek. |

**`notlar` → `notes`**: `not_yonetici` → `user_id`, `not_metin` → `body` (null ya da boşsa atla), `not_tarih` → `noted_on` (sıfırsa VGZ tarihi), `not_VGZ` → `created_at`.

**`hafriyatlog`**: **aktarılmaz.** Yeni sistem kendi `audit_logs` tablosunu kullanır.

## 7. Kapsam dışı tablolar

| Tablo | Neden |
|---|---|
| `tasima_copy`, `personel_maas_copy`, `personel_odeme_copy`, `petrol_fis_copy` | Yedek kopyalar; kodda kullanılmıyor. |
| `personel_giris_cikis_santiye` | Kodda kullanılmıyor. |
| `cekcari_fis` | Kodu bozuk (ölü). Aktarımda satır sayısı kontrol edilir; boş değilse rapora yazılır. |
| `hafriyatlog` | Yeni sistemde `audit_logs` var. |
| `fchart/` (klasör) | Grafikler, kapsam dışı. |

## 8. Yetim ve hatalı kayıt politikası

- **Nullable FK** eşlenemezse → `NULL` yazılır, rapora "boşaltılan FK" olarak eklenir.
- **Zorunlu FK** eşlenemezse → kayıt **atlanır**, rapora "atlanan kayıt" olarak eklenir. Parasal kayıtlarda tutar da raporlanır.
- **CHECK ihlali** (negatif/sıfır tutar, tutarsız tarih, geçersiz kod) → kayıt atlanır, raporlanır.
- **Unique ihlali** (aynı plaka, aynı güzergah çifti, aynı maaş dönemi, aynı belge no) → ilk kayıt aktarılır, diğerlerinin eski id'leri ilk kayda map'lenir (FK'ler çözülsün diye), raporlanır.

Aktarım raporu tablo bazında şu sayıları verir: kaynak satır, aktarılan, atlanan, boşaltılan FK, birleştirilen. Ayrıca atlanan satırların listesini verir.

## 9. Mutabakat (kabul kriteri)

Eski raporların çıktısıyla değil, **ham toplamlarla** yapılır (eski raporlardaki hatalar bilinçli olarak düzeltildiği için). Tolerans 0,01 TL. Atlanan kayıtların tutarı, farkı açıklamak için ayrıca gösterilir.

| Kontrol | Eski (MySQL) | Yeni (PostgreSQL) |
|---|---|---|
| Firma bakiyesi (rol başına) | `SUM(IF(Odeme_Turu='B', Miktar, 0))`, `SUM(IF(Odeme_Turu='A', Miktar, 0))`, firma bazında, 4 fiş tablosu ayrı ayrı | `SELECT party_id, SUM(debit), SUM(credit) FROM ledger_entries WHERE party_type='company' AND party_role=:rol GROUP BY party_id` (firma `legacy_id_maps` ile eşlenir) |
| Araç toplamları | araç bazında `SUM(Tasima_Fiyat)`, `SUM(Yakit_Fisi_Miktar*Yakit_Fisi_Birim_Fiyati)`, `SUM(Plaka_Odeme_Miktar)`, `SUM(Plaka_Alacak_Miktar)`, `SUM(Plaka_Ceza_Miktar) WHERE Plaka_Ceza_Durum='P'` | `ledger_entries WHERE party_type='vehicle' GROUP BY party_id, source_table` |
| Plaka sahibi ödemeleri | `SUM(Sofor_Odeme_Miktar)` sahip bazında | `party_type='vehicle_owner'` |
| Döküm yeri | `SUM(Tasima_Dokum_Fiyat)` ve `SUM(Dokum_Odeme_Miktar)` döküm yeri bazında | `party_type='dump_site'` debit/credit |
| Şantiye | `SUM(Miktar)` Tur = C / G ayrı, şantiye bazında | `party_type='site'` debit/credit |
| Personel | `SUM(PM_Hakedilen_Maas)`, `SUM(PP_Pirim)`, `SUM(PO_Miktar)` personel bazında | `party_type='employee'` kaynak bazında |
| Kasa nakit | `SUM(Miktar)` G / C ayrı | `party_type='cash'` debit/credit |
| Çek/senet | `SUM(Cks_Tutar)` tür × durum × hareket | `financial_instruments` aynı gruplama |
| Satır sayıları | her kaynak tablo `COUNT(*)` | hedef tablo `COUNT(*)` + atlanan sayısı |

Yakıt tutarı fiş bazında (`quantity * unit_price`, 2 hane) hesaplandığı için eski `SUM(miktar*fiyat)` ile kuruş düzeyinde yuvarlama farkı çıkabilir; fark fiş sayısı × 0,005 TL'yi geçmemelidir.

## 10. Eski veride bilinen tuhaflıklar

- **Düz metin şifreler** (`yonetici.sifre`) → aktarımda hash'lenir.
- **Sabit ödeme tipi id'leri:** `odeme_tipi` tablosunda yalnızca id 3 (Nakit) ve 4 (Çek) var. Kod ise çeki `"2"`, nakiti `"1"` olarak kontrol ediyor (`dokumodeme_ekle.php:25`, `dokumodeme_islemler.php:12/19`, `functions.php:639`). Bu yüzden döküm ödemesinden otomatik çek kaydı hiç oluşmamış; `cks_fis.Cks_Bag_Tablo` pratikte boş. Yeni sistem `payment_types.kind` kullanır.
- **Serbest metin ilişkiler:** `cks_fis.Cks_Firma`, `tasima.Tasima_Aktif_Sofor`, `yukleme.Yukleme_Aktif_Sofor`, `plaka_ceza.Plaka_Ceza_Sofor`, `yakit_fisi.Yakit_Fisi_Alan` serbest metindir; FK'ye çevrilmez, metin olarak taşınır.
- **`yakit_yeri.yer`** varchar(10) içinde bir şantiye id'si saklıyor → `fuel_stations.site_id`.
- **`yakit_fisi.Yakit_Fisi_Santiye_ID`** şemada var ama eski PHP hiç yazmıyor → yakıt yerinin şantiyesinden doldurulur.
- **Bayat döküm fiyatı:** taşıma düzenlenince `tasima.Tasima_Dokum_Fiyat` güncellenmiyor, yalnızca `tasima_dokum.TD_Fiyat` güncelleniyor (`tasima_islemler.php:159-178`). Ayrıca araç bazlı fiyat varken `dump_price` 0 kaydedilen bir hata var. Karar: `dump_price` **olduğu gibi** aktarılır. Aktarım raporu `Tasima_Dokum_Fiyat ≠ TD_Fiyat` olan taşımaları listeler; düzeltme gerekirse aktarım sonrası yapılır.
- **Taşıma saatleri:** yeni girişte saat:dakika yazılıyor, düzenlemede saat 00:00'a çekiliyor. `hauled_at` olduğu gibi taşınır.
- **Güzergahtan bağımsız şantiye/döküm yeri:** `tasima.Tasima_Santiye_ID` / `Tasima_DY_ID` güzergahınkinden farklı olabilir (bkz. 6.7).
- **Plaka sahibi şehri metin:** `plaka_sahibi.Plaka_Sehir` varchar; diğer tablolarda şehir id.
- **Kuruşsuz çek tutarları:** `Cks_Tutar` ve `Cks_Kredi_Tutar` int.
- **Personel raporlarının eski kopyası:** `sakin_silme_hafriyat/htdocs/` altındaki kopya olmayan `PM_Maas` / `PM_Odeme` kolonlarını okuyor; docker kopyası `PM_Donem_Maas` okuyor. Referans docker kopyasıdır.
- **Piyasa fişinden otomatik plaka ödemesi:** plakalı her piyasa fişi için `plaka_odeme` satırı açılıyor (`piyasalarfis_islemler.php:16-24`, `Plaka_Odeme_Piyasa_ID`). Yeni şemada bu bağ `vehicle_payments.company_transaction_id` ile korunur; raporlarda aynı tutar iki kez sayılmamalıdır.

## 11. Açık kararlar

1. ~~Vardiya kodları~~ → kesinleşti (bölüm 4).
2. ~~`users.email`~~ → nullable; giriş e-posta veya kullanıcı adı + şifre.
3. `Odeme_Turu = ''` olan firma fişleri ve `Nakit_Islem = NULL` olan nakit satırları için tek tek karar (aktarım raporundan).
4. Güzergahı olmayan taşımalar: eski DB'de güzergah tamamlanacak mı, yoksa atlanıp tutarları elle mi düzeltilecek?
5. `payment_types.kind` için "Nakit" ve "Çek" dışındaki tiplerin sınıflandırılması.
