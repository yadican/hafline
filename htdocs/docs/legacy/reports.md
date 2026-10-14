# Eski raporlar → yeni rapor kartları

Eski hafriyat sistemindeki raporlar yeni sistemin **referansıdır**. Bu belge 30 rapor ve 2 ekranın her biri için bir kart içerir.

- **Korunur:** raporun adı, başlıkları (sütunları), filtreleri, gruplaması ve çıktı düzeni.
- **Değişir:** formüller yeni şemadan temiz kurulur; eski koddaki hatalar kopyalanmaz. Belirsizlikte **başlığın anlamı** esas alınır.

Eski kaynak: `hafriyat/HAFRİYAT PROGRAMI YEDEK/sakin_silme_hafriyat/hafriyat-docker/htdocs/`. Satır numaraları bu klasöre göredir. Menü adları `user_menu.php` (satır 72–103) dosyasından alınmıştır. Tablo eşlemesi için bkz. [`db-mapping.md`](db-mapping.md).

## Ortak kurallar

### Tarih aralığı
- `hauls.hauled_at` (DATETIME): `hauled_at >= :bas AND hauled_at < :bit + 1 gün`.
- DATE kolonlar (`occurred_on`, `issued_on`, `handed_on`, `dumped_on`, `checked_on`, `period`): `BETWEEN :bas AND :bit`.
- Ay seçilen raporlarda aralık ayın 1'i ile son günüdür.
- Tek bir tarih aralığı bileşeni kullanılır. Eski formlardaki "seçiniz = 2" sentinel'i, Ocak'ta `Ay = 0` olan varsayılan ve doğrulamasız tarihler (31 Şubat gibi) kalkar. Başlangıç > bitiş doğrulanır.

### Bakiye ve devir
- Bakiye = `SUM(debit) − SUM(credit)` (bkz. `db-mapping.md` bölüm 3). Pozitif: karşı taraf bize borçlu; negatif: biz borçluyuz. Eski raporlar çoğunlukla `Alacak − Borç` gösteriyordu; arayüz bakiyeyi "(B)" / "(A)" etiketiyle gösterebilir.
- Devir (açılış) = aynı formülün `occurred_on < :bas` toplamı.
- Yürüyen bakiye = `devir + SUM(debit − credit) OVER (ORDER BY occurred_on, created_at, id)`. Satırın kendisi dahildir. Eski raporlar yürüyen bakiyeyi `*_VGZ` (her güncellemede değişen zaman) ile sıralıyordu.
- Tüm sorgularda `deleted_at IS NULL`.

### Kısaltmalar
`H` = hauls, `F` = fuel_receipts, `VP` = vehicle_payments, `VR` = vehicle_receivables, `VF` = vehicle_fines, `VOP` = vehicle_owner_payments, `CT` = company_transactions, `L` = ledger_entries.

### Plaka sahibi kapsamı
Eski raporların hepsi aracın **güncel** sahibini (`plaka.Plaka_Sahibi_ID`) kullanıyor; snapshot kolonları (`Tasima_Sahibi`, `Yakit_Fisi_Sahibi`, `Plaka_Odeme_Sahibi`) hiçbir raporda okunmuyor. Sonuç: araç el değiştirince eski sahibin geçmişi yeni sahibe yazılıyor.
Yeni şemada bütün araç hareketlerinde `owner_id` snapshot'ı var (`hauls`, `fuel_receipts`, `vehicle_payments`, `vehicle_receivables`, `vehicle_fines`, `machine_loadings`). Kartlarda `SAHİP(x)` şu demektir:
- **Karar (snapshot):** `x.owner_id = :o`. Her kayıt girildiği andaki sahibin raporunda kalır; araç satılınca eski sahibin geçmişi yeni sahibe geçmez.
- Eski davranış (`x.vehicle_id IN (SELECT id FROM vehicles WHERE owner_id = :o)`) kullanılmaz.

### Bilinçli olarak düzeltilen ortak hatalar
- **Bitiş günü düşüyordu:** DATETIME kolonlarda (`Tasima_Zaman`, `*_Odeme_Zaman`, `Nakit_Girisi_Zaman`) `BETWEEN 'Y-m-d' AND 'Y-m-d'` bitiş gününün 00:00 sonrasını dışarıda bırakıyordu.
- **Kayıt zamanına göre sıralama:** yürüyen bakiye ve listeler `*_VGZ` ile sıralanıyordu; düzenlenen kayıt sona kayıyordu. Yeni sıralama `occurred_on, created_at`.
- **Güncel ilişkiler:** açıklamalarda güzergahın güncel şantiye/döküm yeri kullanılıyordu. Yeni sistemde taşımanın kendi snapshot'ı (`H.site_id`, `H.dump_site_id`) kullanılır.
- **Ağırlıksız ortalama birim fiyat:** `AVG(birim_fiyat)` yerine `SUM(amount) / NULLIF(SUM(quantity), 0)`.
- **N+1 sorgu ve sıralamasız listeler:** tek `GROUP BY` sorgusu, plaka/ad sıralı.
- **Pasif kayıtlar:** dönemde hareketi olan pasif araç/şantiye/firma da listelenir.
- **Cezalar:** `plaka_ceza` hiçbir rapora girmiyordu. Karar: ceza kendi tablosunda (`vehicle_fines`) ayrı izlenir; bekleyen ceza bakiyeyi etkilemez, **ödenen ceza ödeme tarihinde (`paid_on`) araca borç yazılır** (`ledger_entries` yalnız `status = 'paid'` satırları okur).

### Açık kararlar
1. ~~Sahip kapsamı~~ → snapshot (`x.owner_id`). Örnek: 34ABC Mart'a kadar Ahmet'in, sonra Mehmet'in; Ocak–Şubat kayıtları Ahmet'in raporunda kalır.
2. ~~Cezalar~~ → ayrı izlenir, ödenince `paid_on` tarihinde araca borç (yukarıda).
3. ~~Kasa kapsamı~~ → kasa şirketin kasasıdır; kasadan nakit çıkan/giren her hareket girer: nakit girişleri, araç, **plaka sahibi** (eski adı "şoför ödemesi", `sofor_odeme.Sofor_Odeme_Sofor_ID` = `plaka_sahibi` id'si), şantiye, döküm yeri, personel, diğer ödemeler ve nakit ödenen cezalar. Yalnız `payment_types.kind = 'cash'`. Firma fişleri katılmaz (eski fişlerde her satırda ödeme tipi vardı, faturalar dahil).


**Rapor yazılırken bakılacak:** 4–7 numaralı kararlar şimdi verilmeyecek. İlgili rapor ekranı yazılırken eski raporun çıktısı ve gerçek veri üzerinden kullanıcıyla birlikte netleştirilecek.

4. **Bankalı nakit satırları** (`cash_entries.bank_id` dolu): kasaya dahil mi, ayrı filtre mi?
5. **Araç Maliyet "Piyasa":** market fişlerinin `credit` tarafı mı, `credit − debit` mi?
6. **Boş kolonlar:** "Damper Ölçü" ve "PZ" eski raporlarda hep boştu: kaldırılsın mı, tanımlansın mı?
7. **Şantiye Cari Yakıt:** Nakliye brüt tutar ve kamyon yakıtı sahibin hakedişinden düştüğü için Durum kamyon yakıtını iki kez sayar. İleride Yakıt "şirket aracı" ve "sahibe yansıyan" diye ayrılabilir.

## Envanter

| Grup | Rapor | Eski dosya |
|---|---|---|
| Araç | Araç Aylık Hareket | `rapor_arac` |
| Araç | Plaka Bakiye | `rapor_arac2` |
| Araç | Araç Şantiye Aylık Hareket | `rapor_arac3` |
| Araç | Şöför Aylık Hareket | `rapor_arac4` |
| Araç | Araç Dönemsel Hareket | `rapor_arac5` |
| Araç | Şöför Dönemsel Hareket | `rapor_arac6` |
| Araç | Şöför Bakiye | `rapor_arac7` |
| Araç | Araç Maliyet | `arac_maaliyet_rapor` |
| Operasyon | Aylık Koçanlar -1- / -2- | `rapor_aylik_kocanlar`, `rapor_aylik_kocanlar2` |
| Operasyon | Dökümyeri Raporları -1- / -2- | `rapor_dokumyeri_santiye`, `rapor_dokumyeri_santiye2` |
| Operasyon | Şantiye Çıkış Raporları -1- / -2- | `rapor_santiye_dokumyeri`, `rapor_santiye_dokumyeri2` |
| Operasyon | Dökümyeri Ödeme Raporları | `rapor_dokumyeri2` |
| Operasyon | Hareket Fişi Kontrol | `kontrol_hareket_guzergah` |
| Operasyon | Yakıt Yeri Raporları | `rapor_yakityeri` |
| Operasyon | Yakıt Yeri Raporları Aylık | `rapor_aylik_yakit` |
| Operasyon | Şantiye Cari Rapor | `cari_oran` |
| Finans | Kasa Hareketleri | `rapor_kasa` |
| Finans | Şantiye Gider | `rapor_santiyegider` |
| Finans | Satıcılar Cari / Alıcılar Cari / Satıcılar Petrol / Piyasalar | `rapor_carifis`, `rapor_alicilarfis`, `rapor_petrolfis`, `rapor_piyasalarfis` |
| Finans | Satıcılar / Alıcılar / Piyasalar Bakiye | `rapor_*_bakiye` |
| Personel | Personel Aylık Döküm | `rapor_personel_aylik` |
| Personel | Personel Yıllık Ödeme Raporu | `rapor_personelodeme` |
| Ekran | Çek Senet Kredi | `cksfis_islemler` |
| Ekran | Kasa Nakit İşlemler | `nakit_islemler` |

---

# Araç / plaka sahibi raporları

Eski "Şöför" kelimesi bu raporlarda **plaka sahibi** demektir.

## Araç Aylık Hareket

- **Eski dosyalar:** `rapor_arac.php`, `rapor_arac_sonuc.php`. Menü `user_menu.php:90`, yetki `arac_aylik_hareket`. Sonuç başlığı "Aylık Plaka Raporu".
- **Filtreler:** Plaka (metin, otomatik tamamlama), Yıl, Ay (varsayılan önceki ay).
- **Satır birimi:** dört blok + alt satırlar:
  1. Taşıma: güzergah başına bir satır (şantiye – döküm yeri adına göre sıralı).
  2. Yakıt: yakıt yeri başına bir satır.
  3. Plaka ödeme: kayıt başına bir satır.
  4. Plaka alacak: kayıt başına bir satır.
  - Alt satırlar: "Geçmiş Dönemler" (devir), TOPLAM, Mazot (litre / fiş), Alacak / Borç / Bakiye.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Sıra | PHP sayacı | satır no |
| Tarih | hep boş | detay satırlarında `occurred_on` |
| Açıklama | taşıma: güzergahın `Santiye_Adi - DY_Adi`; yakıt: `Yakit_Yeri_Adi`; ödeme/alacak: açıklama | taşıma: `sites.name - dump_sites.name` (`H.site_id`, `H.dump_site_id`); yakıt: `fuel_stations.name`; diğer: `description` |
| Damper Ölçü | hep boş | açık karar 6 |
| Toplam | taşıma `COUNT`, yakıt `SUM(litre)` | taşıma `COUNT(H)`, yakıt `SUM(F.quantity)` (birim "lt" yazılır) |
| Dönem Ortalama Birim Fiyatı | `AVG(Tasima_Fiyat)`, `AVG(Yakit_Fisi_Birim_Fiyati)` | `SUM(H.price)/COUNT(H)`, `SUM(F.amount)/NULLIF(SUM(F.quantity),0)` |
| PZ | hep boş | açık karar 6 |
| Borç | yakıt tutarı, ödeme tutarı, devir | `L.debit` (`party_type='vehicle'`): `F.amount`, `VP.amount`, ödenmiş `VF.amount` (`paid_on` tarihinde) |
| Alacak | taşıma tutarı, alacak tutarı | `L.credit`: `H.price`, `VR.amount` |
| Mazot (alt) | `SUM(litre)` / `SUM(Tasima_Fis_Adet)` | `SUM(F.quantity)` / `COUNT(F)` / `SUM(F.haul_ticket_count)` |
| Geçmiş Dönemler | `(Σtaşıma + Σalacak) − abs(Σödeme + Σyakıt)`, ay başı öncesi | `SUM(L.debit − L.credit) WHERE occurred_on < :bas` |
| Alacak / Borç / Bakiye (alt) | — | `Σcredit` / `Σdebit` / `devir + Σdebit − Σcredit` |

**Farklar:**
- Devir her işarette Borç kolonuna basılıp pozitifse Alacak toplamına ekleniyordu (satır 150, 161); `abs()` iadeleri pozitif sayıyordu (satır 149). Düzeltildi.
- Güzergahı NULL olan taşımalar toplamdan düşüyordu (satır 37–48); silinmiş yakıt yeri NULL tutar veriyordu (satır 67–71).
- "Mazot / fiş" aslında hareket fişi adedi; yeni raporda yakıt fişi ve hareket fişi ayrı.
- Ödeme ve alacak satırlarında tarih gösteriliyor; bloklar sıralı. Plaka bulunamazsa hata gösterilir.

## Plaka Bakiye

- **Eski dosyalar:** `rapor_arac2.php`, `rapor_arac_sonuc2.php`. Menü `user_menu.php:97`, yetki `plaka_bakiye`.
- **Filtreler:** başlangıç ve bitiş tarihi (isteğe bağlı; boşsa tüm zamanlar), Şantiye (yalnız taşımaya), Yakıt Yeri (yalnız yakıta).
- **Satır birimi:** araç (eskide yalnız aktif); dört tutarı da sıfır olan satırlar gizli; altta Toplam.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| Alacak | `SUM(Tasima_Fiyat)` [+ şantiye] | `SUM(H.price)` [+ `H.site_id`] |
| Elden | `SUM(Plaka_Alacak_Miktar)` | `SUM(VR.amount)` |
| Borç | `SUM(Plaka_Odeme_Miktar) + SUM(yakıt tutarı)` [+ yakıt yeri] | `SUM(VP.amount) + SUM(F.amount)` [+ `F.fuel_station_id`] + `SUM(VF.amount WHERE status='paid')` (`paid_on` ile) |
| Bakiye | `(Elden + Alacak) − Borç` | `SUM(debit) − SUM(credit)` |
| Toplam (alt) | kolon toplamları | aynı |

**Farklar:**
- Taşıma bitiş günü düşüyordu; ödeme ve alacak ise `00:00–23:59` kullanıyordu (aynı raporda tutarsız sınır, satır 27–41).
- Şantiye ve yakıt yeri filtreleri yalnız bir kolonu daraltıp Bakiye'yi anlamsızlaştırıyordu. Yeni raporda filtre varken Bakiye kolonu "filtreli net" olarak etiketlenir ya da şantiye filtresi `F.site_id` ile yakıta da uygulanır.
- Bakiyeli pasif araçlar dahil; plakaya göre sıralı.

## Araç Şantiye Aylık Hareket

- **Eski dosyalar:** `rapor_arac3.php`, `rapor_arac3_sonuc.php`. Menü `user_menu.php:95`, yetki `arac_santiye_aylik_hareket`.
- **Filtreler:** Plaka, Şantiye (yeni sistemde zorunlu), Yıl, Ay.
- **Satır birimi:** seçili şantiyedeki güzergah satırları + o şantiyenin yakıt yeri satırları. Ödeme, alacak ve devir blokları eskide yorum satırıydı.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Sıra, Tarih, Açıklama, Damper Ölçü, Toplam, Dönem Ort. Birim Fiyatı, PZ | Araç Aylık Hareket ile aynı | aynı |
| Borç | yalnız yakıt tutarı | `SUM(F.amount) WHERE F.vehicle_id=:v AND F.site_id=:s`, `GROUP BY fuel_station_id` |
| Alacak | yalnız taşıma tutarı | `SUM(H.price) WHERE H.vehicle_id=:v AND H.site_id=:s`, `GROUP BY route_id` |
| Alacak / Borç / Bakiye (alt) | `Σtaşıma`, `Σyakıt`, fark | `ΣH.price`, `ΣF.amount`; "Bakiye" yerine **"Net (Taşıma − Yakıt)"** (gerçek cari bakiye değil) |

**Farklar:**
- Şantiye seçilmezse `yakit_yeri.yer = ''` şantiyesiz tüm istasyonların yakıtını getiriyordu (satır 41, 72).
- Şantiye eşlemesi güzergah ve `yakit_yeri.yer` üzerinden yapılıyordu; yeni sistemde `H.site_id` ve `F.site_id` snapshot'ı.

## Şöför Aylık Hareket

- **Eski dosyalar:** `rapor_arac4.php`, `rapor_arac4_sonuc.php`. Menü `user_menu.php:91`, yetki `arac_aylik_hareket`.
- **Filtreler:** Plaka Sahibi, Yıl, Ay.
- **Satır birimi:** sahibin araçları; her araç için Araç Aylık Hareket blokları ve araç alt toplamı ("Sefer:X Yakıt:Ylt Z yakıt fişi W hareket fişi"). Ardından sahip ödemesi satırları, tek "Geçmiş Dönemler", Mazot, TOPLAM Sefer, Alacak / Borç / Bakiye.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Araç blokları (9 kolon) | Araç Aylık Hareket ile aynı | aynı; araç kümesi `SAHİP(x)` |
| Sahip ödemesi satırı | `Sofor_Odeme_Aciklama`, Borç `Sofor_Odeme_Miktar` | `VOP WHERE vehicle_owner_id=:o` (`L.party_type='vehicle_owner'`), Borç = `amount` |
| Araç alt toplamı | `COUNT(taşıma)`, `SUM(litre)`, `COUNT(yakıt fişi)`, `SUM(Tasima_Fis_Adet)` | `COUNT(H)`, `SUM(F.quantity)`, `COUNT(F)`, `SUM(F.haul_ticket_count)`; `Σdebit` / `Σcredit` |
| Geçmiş Dönemler | araçların devri − `Σsofor_odeme`, ay başı öncesi | `SAHİP` kapsamındaki araç kayıtları + `VOP` için `SUM(debit − credit) WHERE occurred_on < :bas` |
| Alacak / Borç / Bakiye | — | `devir + Σdebit − Σcredit` |

**Farklar:**
- Devir kolonu ve `abs()` hatası Araç Aylık Hareket'teki gibi düzeltildi (satır 191, 225, 236).
- Sahip ödemeleri son aracın tablosunun altına düşüyordu (satır 195–214); yeni raporda ayrı "Sahip" bölümü.
- Yalnız hareketi olan araçlar, plakaya göre sıralı. Excel adı (satır 281) düzeltildi.

## Araç Dönemsel Hareket

- **Eski dosyalar:** `rapor_arac5.php`, `rapor_arac5_sonuc.php`. Menü `user_menu.php:92`. Başlık "Plaka Dönemsel Raporu".
- **Filtreler:** Plaka, başlangıç ve bitiş tarihi (eski varsayılan "ayın 1'i – ayın 1'i"; yeni varsayılan ay başı – bugün).
- **Satır birimi:** güzergah satırları + yakıt yeri satırları + Toplam. Para yok; iş/miktar raporu.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Sıra | `$i` (taşımada artmıyordu) | satır no |
| Açıklama | güzergahın şantiye – DY / yakıt yeri adı | `sites.name - dump_sites.name` (snapshot) / `fuel_stations.name` |
| Döküm Sayısı | `COUNT(Tasima_ID)` | `COUNT(H) WHERE H.vehicle_id=:v`, `GROUP BY route_id, site_id, dump_site_id` |
| Toplam Lt | `SUM(Yakit_Fisi_Miktar)` | `SUM(F.quantity)`, `GROUP BY fuel_station_id` |
| Toplam Ton | `SUM(Tasima_Ton)` | `SUM(H.tonnage)` |
| Toplam (alt) | Σsayı, Σlitre, Σton | aynı |

**Farklar:** bitiş günü dahil (eski varsayılan aralıkta rapor pratikte boştu, satır 31, 37); sıra numarası düzeltildi (satır 35–50); NULL güzergah kaybı yok.

## Şöför Dönemsel Hareket

- **Eski dosyalar:** `rapor_arac6.php`, `rapor_arac6_sonuc.php`. Menü `user_menu.php:93`. Başlık "Şöför Dönemsel Raporu" (eskide kelime tekrarlı).
- **Filtreler:** Plaka Sahibi, başlangıç ve bitiş tarihi.
- **Satır birimi:** sahibin her aracı için plaka başlığı, güzergah ve yakıt yeri satırları, araç Toplam'ı; en altta Genel Toplam.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Sıra, Açıklama, Döküm Sayısı, Toplam Lt, Toplam Ton | Araç Dönemsel Hareket ile aynı | aynı; kayıt kümesi `SAHİP(H)` ve `SAHİP(F)`; `GROUP BY vehicle_id → route` ve `vehicle_id → fuel_station_id` |
| Toplam / Genel Toplam | araç ve genel toplam | aynı |

**Farklar:** bitiş günü dahil; hareketsiz araçlar gizli, plakaya göre sıralı; sıra numarası tutarlı; başlıkta tanımsız `$_POST["Plaka_No"]` (satır 17, 119) kaldırıldı.

## Şöför Bakiye

- **Eski dosyalar:** `rapor_arac7.php`, `rapor_arac7_sonuc.php`. Menü `user_menu.php:98`, yetki `plaka_bakiye`.
- **Filtreler:** Plaka Bakiye ile aynı (tarih aralığı, Şantiye, Yakıt Yeri).
- **Satır birimi:** plaka sahibi (ad soyad sıralı); sıfır bakiyeli sahipler yeni raporda gizli; altta Toplam.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka (aslında sahip adı) | `Plaka_Adi + Plaka_Soyadi` | `vehicle_owners.first_name || ' ' || last_name`; başlık **"Plaka Sahibi"** |
| Alacak | aktif araçlarda `ΣTasima_Fiyat` [+ şantiye] | `SUM(H.price) WHERE SAHİP(H)` [+ `H.site_id`] |
| Elden | `ΣPlaka_Alacak_Miktar` | `SUM(VR.amount) WHERE SAHİP(VR)` |
| Borç | `Σ(plaka_odeme + yakıt) + ΣSofor_Odeme_Miktar` | `SUM(VP.amount) + SUM(F.amount) [+ istasyon] + SUM(VOP.amount WHERE vehicle_owner_id=:o)` + ödenmiş `VF` |
| Bakiye | `(Elden + Alacak) − Borç` | `SUM(debit) − SUM(credit)` |

**Farklar:** taşımada bitiş günü düşüyordu, ödemelerde düşmüyordu (satır 40–43, 82); pasif araçlar sahip bakiyesinden düşüyordu (satır 44, 59); yanlış kolon başlığı düzeltildi.

## Araç Maliyet

- **Eski dosyalar:** `arac_maaliyet_rapor.php`, `arac_maaliyet_rapor_sonuc.php`. Menü `user_menu.php:101`, yetki `santiye_cari_rapor` (yeni sistemde araç raporlarına taşınması önerilir).
- **Filtreler:** Plaka Sahibi, başlangıç ve bitiş tarihi.
- **Satır birimi:** sahibin araçları; altta "N araç" toplamı.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| Getiri | `SUM(Tasima_Fiyat)` | `SUM(H.price)` |
| Yakıt | `SUM(Yakit_Fisi_Miktar) × Yakit_Fisi_Birim_Fiyati` (**rastgele satırın fiyatı**) | `SUM(F.amount)` |
| Piyasa | `SUM(piyasalar_Miktar)` (A ve B karışık) | `SUM(CT.credit) WHERE CT.role='market' AND CT.vehicle_id=:v` (açık karar 5) |
| Durum | `Getiri − (Yakıt + Piyasa)` | aynı (brüt kârlılık, cari bakiye değil) |

**Farklar:** yakıt fiş bazında `quantity × unit_price` (eskide `ONLY_FULL_GROUP_BY` kapalı olduğu için rastgele fiyat, satır 9); Piyasa'da A ve B ayrımı (satır 10); bitiş günü dahil; 2 ondalık ve Excel çıktısı.

---

# Operasyon raporları

## Aylık Koçanlar -1- (Şantiye – Döküm Yeri)

- **Eski dosyalar:** `rapor_aylik_kocanlar.php`, `rapor_aylik_kocanlar_sonuc.php`. Yetki `aylik_kocanlar`.
- **Filtreler:** Mevki (güzergah, "Şantiye – DY"), Yıl, Ay, Gün -1- (boşsa 1), Gün -2- (boşsa ayın son günü), Plaka Tipi (boş = tümü).
- **Satır birimi:** matris; satır = plaka, sütun = gün; hücre = o gün o güzergahtaki taşıma sayısı ("-" = 0). Sağda satır toplamı, altta gün toplamları.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` (sıralı) |
| 1 … 31 | `COUNT(*) FROM tasima WHERE plaka, güzergah, gün` | `COUNT(*) FROM hauls WHERE route_id=:r AND hauled_at ∈ [gün aralığı] [AND vehicles.vehicle_class_id=:c] GROUP BY vehicle_id, date(hauled_at)` |
| Toplam | satır toplamı | `COUNT(*) GROUP BY vehicle_id` |
| Toplam (alt satır) | gün toplamları | `COUNT(*) GROUP BY date(hauled_at)` |

**Farklar:** satırlar gün aralığına göre seçiliyor (eskide tüm ay → boş satırlar); gün sütunları ayın gerçek gün sayısıyla sınırlı; pasif güzergahın geçmiş ayı da seçilebilir; tek sorgu.

## Aylık Koçanlar -2- (Döküm Yeri – Şantiye)

- **Eski dosyalar:** `rapor_aylik_kocanlar2.php`, `rapor_aylik_kocanlar_sonuc2.php`. Yetki `aylik_kocanlar`.
- **Filtreler:** -1- ile aynı; güzergah listesi "DY – Şantiye" etiketli ve DY adına göre sıralı.
- **Satır birimi:** -1- ile aynı matris + "Araç Sınıfı" sütunu.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| Araç Sınıfı | `plaka_tipi.PT_Adi` | `vehicle_classes.name` (NULL → "—") |
| 1 … 31, Toplam, alt Toplam | -1- ile aynı | -1- ile aynı; sıralama `vehicle_classes.name, plate` |

**Farklar:** plaka tipi filtresi eskide fiilen zorunluydu (boşsa `= ''` → boş rapor, satır 25–27); yeni sistemde boş = tümü, sınıfsız araçlar dahil. -1- ve -2- tek rapor + "sınıf sütunu / sıralama" seçeneği olarak birleştirilebilir.

## Dökümyeri Raporları -1-

- **Eski dosyalar:** `rapor_dokumyeri_santiye.php`, `rapor_dokumyeri_santiye_sonuc.php`. Yetki `santiye_cikis_raporlari_1`.
- **Filtreler:** Döküm Yeri, Yıl, Ay, Plaka Tipi.
- **Satır birimi:** matris; satır = plaka, sütun = o döküm yerine giden güzergahlar ("DY / Şantiye"); hücre = aylık taşıma sayısı. Satır ve sütun toplamları.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| [DY / Şantiye] sütunları | sütun `Guzergah_DY_ID`, satır `Tasima_DY_ID`, hücre `Tasima_Guzergah_ID` | `COUNT(*) FROM hauls WHERE dump_site_id=:dy AND hauled_at ∈ ay [sınıf] GROUP BY vehicle_id, route_id`; sütun etiketi `dump_sites.name / sites.name`, `ORDER BY sites.name` |
| Toplam | satır toplamı | `GROUP BY vehicle_id` |
| (etiketsiz alt satır) | güzergah toplamları | **"Toplam"**, `GROUP BY route_id` |

**Farklar:** satır, sütun ve hücre tek kaynaktan (`hauls.dump_site_id`); sınıf filtresi sütunlara da uygulanıyor (eskide boş sütunlar); başlıkta döküm yeri adı `dump_sites`'tan.

## Dökümyeri Raporları -2-

- **Eski dosyalar:** `rapor_dokumyeri_santiye2.php`, `rapor_dokumyeri_santiye_sonuc2.php`. Yetki `santiye_cikis_raporlari_2`.
- **Filtreler:** Yıl, Ay, Plaka Tipi (döküm yeri seçimi yok).
- **Satır birimi:** satır = ayda taşıması olan plakalar, sütun = ayda taşıması olan tüm güzergahlar (`DY / Şantiye`, DY adına göre).

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| [DY / Şantiye] sütunları | `COUNT(*) FROM tasima` plaka × güzergah | `COUNT(*) FROM hauls WHERE hauled_at ∈ ay [sınıf] GROUP BY vehicle_id, route_id`; `ORDER BY dump_sites.name, sites.name` |
| Toplam, alt toplam | satır / sütun | aynı |

**Farklar:** sınıf filtresi sütunlara da uygulanıyor; ikincil sıralama; tek sorgu. Not: veri Şantiye Çıkış -2- ile aynı, yalnız etiket ve sıralama farklı.

## Şantiye Çıkış Raporları -1-

- **Eski dosyalar:** `rapor_santiye_dokumyeri.php`, `rapor_santiye_dokumyeri_sonuc.php`. Yetki `santiye_cikis_raporlari_1`.
- **Filtreler:** Şantiye (kullanıcının şantiye kısıtı varsa yalnız o), Yıl, Ay, Plaka Tipi.
- **Satır birimi:** satır = plaka, sütun = o şantiyeden çıkan güzergahlar ("Şantiye / DY").

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| [Şantiye / DY] sütunları | sütun `Guzergah_Santiye_ID`, satır `Tasima_Santiye_ID`, hücre `Tasima_Guzergah_ID` | `COUNT(*) FROM hauls WHERE site_id=:s AND hauled_at ∈ ay [sınıf] GROUP BY vehicle_id, route_id`; etiket `sites.name / dump_sites.name`, `ORDER BY dump_sites.name` |
| Toplam, alt toplam | satır / sütun | aynı; alt satır "Toplam" |

**Farklar:** tek kaynak (`hauls.site_id`); sınıf filtresi sütunlara da uygulanıyor; sıralı.

## Şantiye Çıkış Raporları -2-

- **Eski dosyalar:** `rapor_santiye_dokumyeri2.php`, `rapor_santiye_dokumyeri_sonuc2.php`. Yetki `santiye_cikis_raporlari_2`.
- **Filtreler:** Yıl, Ay, Plaka Tipi.
- **Satır birimi:** tüm plakalar × tüm güzergahlar ("Şantiye / DY", şantiye adına göre).

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| [Şantiye / DY] sütunları | `COUNT(*) FROM tasima` | `COUNT(*) FROM hauls WHERE hauled_at ∈ ay [sınıf] [AND site_id = kullanıcının şantiyesi] GROUP BY vehicle_id, route_id`; `ORDER BY sites.name, dump_sites.name` |
| Toplam, alt toplam | satır / sütun | aynı |

**Farklar:** eskide şantiye kısıtlı kullanıcı tüm şantiyeleri görüyordu; yeni sistemde kısıt uygulanır.

## Dökümyeri Ödeme Raporları

- **Eski dosyalar:** `rapor_dokumyeri2.php`, `rapor_dokumyeri2_sonuc.php`. Yetki `santiye_cikis_raporlari_1`. Başlık "<DY> Döküm Yeri Ödeme Raporu".
- **Filtreler:** Döküm Yeri, başlangıç ve bitiş tarihi.
- **Satır birimi:**
  1. Döküm bölümü: araç tipi başına bir satır + "Toplam Döküm".
  2. Ödeme bölümü: ödeme kaydı başına bir satır + "Toplam Ödeme".
  3. "Devir" satırı (Döküm : X, Ödeme : Y).
  4. "Bakiye" satırı.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Araç Tipi | `plaka_tipi.PT_Adi` | `vehicle_classes.name` (NULL → **"Sınıfsız"**) |
| Sefer Sayısı | `COUNT(Tasima_ID)` tipli plakalar üzerinden | `COUNT(H) WHERE H.dump_site_id=:dy AND H.hauled_at ∈ [bas, bit] GROUP BY vehicles.vehicle_class_id` |
| Tonaj | `SUM(Tasima_Ton)` | `SUM(H.tonnage)` |
| Toplam Fiyat | `SUM(Tasima_Dokum_Fiyat)` | `SUM(H.dump_price)` |
| Ödeme Açıklama | `Dokum_Odeme_Aciklama` | `dump_site_payments.description` |
| Ödeme Tarih | `Dokum_Odeme_Zaman` | `occurred_on` (sıralı) |
| Ödeme Tipi | `Odeme_Tipi_Adi` | `payment_types.name` (+ `cheque_no`) |
| Ödeme Miktarı | `Dokum_Odeme_Miktar` | `amount` |
| Devir | `Σdöküm(< bas) − Σödeme(< bas)` | `L WHERE party_type='dump_site' AND party_id=:dy AND occurred_on < :bas`: Döküm = `SUM(credit)`, Ödeme = `SUM(debit)`, Devir = `SUM(credit) − SUM(debit)` |
| Bakiye | `(dönem döküm − dönem ödeme) + devir` | `−(SUM(debit) − SUM(credit))`, `occurred_on <= :bit` (rapor eski işareti korur: pozitif = döküm yerine borçluyuz) |

**Farklar:**
- Tipi olmayan ya da silinmiş araçların taşımaları hem dönemde hem devirde atlanıyordu (satır 23, 56). Yeni raporda "Sınıfsız" satırında.
- Devir asimetrikti (döküm tipli plakalar, ödeme tümü). Düzeltildi.
- Ödemeler giriş zamanına göre sıralanıyordu (`VGZ desc`); yeni raporda tarihe göre.
- Bitiş günü dahil.

## Hareket Fişi Kontrol

- **Eski dosyalar:** `kontrol_hareket_guzergah.php`, `rapor_hareket_guzergah_sonuc.php` (`lib/rapor_hareket_guzergah_sonuc.php` ölü kod, taşınmaz). Yetki `hareket_fis_kontrol`.
- **Filtreler:** Şantiye (0 = tümü), Döküm Yeri (0 = tümü), Şoför (plaka sahibi), Plaka, Yıl, Ay, Gruplandır (yok / Şantiye / Şoför / Döküm Yeri).
- **Satır birimi:** yan yana üç tablo (Fiş Teslim, Hareket, Yakıt); her tabloda grup başına bir satır + Toplam.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Fiş Teslim | `SUM(Fisteslim_Hareketadet)`; şantiye/DY güzergah üzerinden | `SUM(ticket_handovers.haul_count) WHERE handed_on ∈ ay`; şantiye/DY `haul_routes` üzerinden; sahip `vehicles.owner_id`; araç `vehicle_id` |
| Hareket | `COUNT(*) FROM tasima`; `Tasima_Santiye_ID`, `Tasima_DY_ID`, güncel sahip | `COUNT(H) WHERE hauled_at ∈ ay`; `H.site_id`, `H.dump_site_id`, `SAHİP(H)`, `H.vehicle_id` |
| Yakıt | `SUM(Yakit_Fisi_Tasima_Fis_Adet)`; şantiye `yakit_yeri.yer` | `SUM(F.haul_ticket_count) WHERE issued_on ∈ ay`; `F.site_id`, `SAHİP(F)`, `F.vehicle_id` |
| Grup etiketi | şantiye / sahip **adı** / DY | `sites.name` / sahip ad soyad (`GROUP BY owner_id`) / `dump_sites.name` |
| Toplam | grupsuz toplam | aynı |
| (yeni, isteğe bağlı) | — | `SUM(ticket_handovers.fuel_count)` |

**Farklar:**
- Bulunamayan plaka sessizce 0 veriyordu (satır 52–59); yeni sistemde doğrulama hatası. `lib/` kopyasında plaka filtresi tamamen bozuktu.
- Döküm yeri filtresi ve gruplaması Yakıt'a uygulanmıyordu (satır 22–25, 50); yakıtın döküm yeri kavramı yok → Yakıt tablosunda "uygulanamaz" gösterilir.
- Sahip gruplaması yalnız ada göreydi (aynı adlı sahipler birleşiyordu, satır 18–20); yeni sistemde id'ye göre.

## Yakıt Yeri Raporları

- **Eski dosyalar:** `rapor_yakityeri.php`, `rapor_yakityeri_sonuc.php`. Yetki `yakit_yeri_raporlari`.
- **Filtreler:** Yakıt Yeri (zorunlu), Yıl, Ay, Araç Tipi.
- **Satır birimi:** o yakıt yerinden ayda fişi olan plaka; altta Toplam.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Sıra | `$i` | satır no |
| Plaka / Araç | `Plaka_No` | `vehicles.plate` (sıralı) |
| Litre | `SUM(Yakit_Fisi_Miktar)` | `SUM(F.quantity) WHERE F.fuel_station_id=:st AND issued_on ∈ ay [sınıf] GROUP BY vehicle_id` |
| Birim Fiyat | `AVG(Yakit_Fisi_Birim_Fiyati)` | `SUM(F.amount) / NULLIF(SUM(F.quantity), 0)` |
| Tutarı | `SUM(birim × miktar)` | `SUM(F.amount)` |
| Toplam (alt) | litre, tutar | `SUM(quantity)`, `SUM(amount)` (+ ağırlıklı ortalama) |

**Farklar:** ağırlıklı birim fiyat (eskide Litre × Birim Fiyat ≠ Tutar, satır 22, 31); silinmiş plakaya ait fişler kaybolmuyor; Excel adı düzgün (satır 71).

## Yakıt Yeri Raporları Aylık

- **Eski dosyalar:** `rapor_aylik_yakit.php`, `rapor_aylik_yakit_sonuc.php`. Yetki `yakit_yeri_raporlari_aylik`. Eski panel başlığı yanıltıcı biçimde "Aylık Koçanlar".
- **Filtreler:** Mevki (yakıt yeri), Yıl, Ay, Plaka Tipi.
- **Satır birimi:** matris; satır = plaka, sütun = gün; hücre = o gün alınan **litre**.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Plaka | `Plaka_No` | `vehicles.plate` |
| 1 … 31 | `SUM(Yakit_Fisi_Miktar)` gün bazında | `SUM(F.quantity) WHERE fuel_station_id=:st AND issued_on ∈ ay [sınıf] GROUP BY vehicle_id, issued_on` |
| Toplam | satır litre toplamı | `GROUP BY vehicle_id` |
| Toplam (alt) | gün toplamları | `GROUP BY issued_on` |

**Farklar:** gün sütunu sayısı ayın gerçek uzunluğu; decimal biçimli litre (eskide float artıkları, satır 48); başlık "Yakıt Yeri Aylık"; isteğe bağlı "fiş adedi" modu (`COUNT(*)`).

## Şantiye Cari Rapor

- **Eski dosyalar:** `cari_oran.php`, `cari_oran_sonuc.php` (grafik sayfaları kapsam dışı). Yetki `santiye_cari_rapor`.
- **Filtreler:** başlangıç ve bitiş tarihi.
- **Satır birimi:** şantiye (aktif + dönemde hareketi olan pasif), ada göre sıralı; yeni sistemde altta Toplam satırı. Kullanıcının şantiye kısıtı uygulanır.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Şantiye | `Santiye_Adi` | `sites.name` |
| Döküm | `SUM(Tasima_Dokum_Fiyat) WHERE Tasima_Santiye_ID` | `SUM(H.dump_price) WHERE H.site_id=:s AND hauled_at ∈ [bas, bit]` |
| Nakliye | `SUM(Tasima_Fiyat)` | `SUM(H.price)` |
| Nak. Fiş | `COUNT(Tasima_ID)` | `COUNT(H)` |
| Yakıt | `SUM(miktar × fiyat) WHERE yakit_yeri.yer = şantiye AND Plaka_Plaka_Tipi = '5'` | `SUM(F.amount) WHERE F.site_id=:s AND issued_on ∈ [bas, bit]` (tüm araçlar) |
| Yak. Fiş | `COUNT(Yakit_Fisi_Id)` aynı koşul | `COUNT(F)` aynı koşul |
| Diğer | `SUM(Santiye_Odeme_Miktar) WHERE Tur='C'` | `SUM(site_transactions.debit) WHERE site_id=:s AND occurred_on ∈ [bas, bit]` |
| Hakediş | `SUM(...) WHERE Tur='G'` | `SUM(site_transactions.credit)` |
| Durum | `Hakediş − (Döküm + Nakliye + Yakıt + Diğer)` | aynı |

**Farklar:**
- Yakıt ve Yak. Fiş sabit `Plaka_Plaka_Tipi = '5'` filtresi yüzünden (satır 9–10; canlı tipler 7–11) **hep 0** çıkıyordu. Başlığa göre yorumlandı: şantiyeye yazılan tüm yakıt fişleri.
- Şantiye eşlemesi `yakit_yeri.yer` (varchar) yerine `fuel_receipts.site_id`.
- Taşıma ve şantiye ödemelerinde bitiş günü düşüyordu, yakıtta düşmüyordu (aynı satırda tutarsızlık). Düzeltildi.
- Tutarlar kuruşlu gösterilir (eskide `number_format(…, 0)`).
- Açık karar 7 (kamyon yakıtının çift sayımı).

---

# Finans raporları

## Kasa Hareketleri

- **Eski dosyalar:** `rapor_kasa.php`, `rapor_kasa_sonuc.php`. Yetki `kasa_hareketleri`.
- **Filtreler:** başlangıç ve bitiş tarihi.
- **Satır birimi:** eskide 4 kaynaktan (`nakit_girisi`, `plaka_odeme`, `santiye_odeme`, `diger_odeme`) birleşik satırlar, tarihe göre; tek Toplam. Yeni raporda en üstte Devir, yürüyen bakiye ve kapanış.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| # | sıra | satır no |
| İşlem Tipi | "Kasa Nakit Girişi" / "<plaka> Plaka Ödeme" / "<şantiye> Ödeme" / "Diğer Ödemeler" | `source_table` etiketi + taraf adı |
| Açıklama | `*_Aciklama` | `description` |
| Nakit Miktarı | `*_Miktar` (hep pozitif) | iki kolon: **Giriş** = `cash_entries.debit` + kaynak satırın `credit`'i; **Çıkış** = `cash_entries.credit` + kaynak satırın `debit`'i. Kaynak satırlar yalnız `payment_types.kind = 'cash'` |
| Ödeme Tipi | "Nakit" ya da `Odeme_Tipi_Adi` | `payment_types.name` ("Nakit"; `bank_id` doluysa banka adı) |
| Zaman | `*_Zaman` | `occurred_on` |
| Toplam | `ΣMiktar` | Toplam Giriş, Toplam Çıkış, Net, Devir, Kapanış |
| (yeni) Bakiye | — | yürüyen bakiye |

Taslak sorgu:

```sql
WITH kasa AS (
  SELECT 'cash_entries' AS src, id, occurred_on, created_at, description,
         debit AS giris, credit AS cikis
  FROM cash_entries WHERE deleted_at IS NULL
  UNION ALL
  SELECT le.source_table, le.source_id, le.occurred_on, le.created_at, le.description,
         le.credit AS giris, le.debit AS cikis
  FROM ledger_entries le
  JOIN payment_types pt ON pt.id = le.payment_type_id AND pt.kind = 'cash'
  WHERE le.source_table IN ('vehicle_payments', 'site_transactions', 'other_payments',
                            'vehicle_owner_payments', 'dump_site_payments', 'employee_payments',
                            'vehicle_fines')
)
-- devir: SUM(giris - cikis) WHERE occurred_on < :bas
-- satırlar: occurred_on BETWEEN :bas AND :bit ORDER BY occurred_on, created_at, id
```

**Farklar:**
- Yön ve ödeme tipi yok sayılıp tüm satırlar toplanıyordu: nakit çıkışları, şantiye gelir/gideri ve çekle yapılan ödemeler aynı toplamdaydı (satır 11–19, 37–51, 91). Yeni raporda Giriş/Çıkış ayrı, yalnız nakit.
- Bitiş günü dahil; devir ve yürüyen bakiye eklendi.
- Kaynak listesi karar 3'e göre genişletildi (plaka sahibi, döküm yeri, personel, ceza); bankalı satırlar açık karar 4.
- Piyasa fişinden otomatik açılan araç ödemeleri (`vehicle_payments.company_transaction_id IS NOT NULL`) bir kez sayılır.

## Şantiye Gider

- **Eski dosyalar:** `rapor_santiyegider.php`, `rapor_santiyegider_sonuc.php`. Yetki `santiye_gider`. (Menü adı "Şantiye Gelir/Gider" olabilir.)
- **Filtreler:** Şantiye, tarih aralığı (eskide ay modu / aralık modu).
- **Satır birimi:** şantiye hareketi başına bir satır; Toplam, Devir bakiye.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Şantiye Adı (üst bilgi) | id + ad birleşik (satır 17) | `sites.name` |
| # | sıra | satır no |
| Zaman | `Santiye_Odeme_Zaman` | `occurred_on` |
| Belge No | hep boş | kaldırılır |
| Açıklama | `Santiye_Odeme_Aciklama` | `description` |
| Borç | **tüm** tutarlar | `debit` (gider) |
| Alacak | hep boş | `credit` (gelir / hakediş) |
| Toplam | Σborç, 0 | `SUM(debit)`, `SUM(credit)` |
| Devir bakiye | dönem toplamı | devir + `SUM(debit − credit)` |
| (yeni) Ödeme Tipi, Bakiye | — | `payment_types.name`; yürüyen bakiye; üstte Devir |

**Farklar:** `Santiye_Odeme_Tur` (C/G) hiç okunmuyordu, gelirler de Borç'a yazılıyordu (satır 71–75); gerçek devir yoktu; `VGZ` sıralaması kalktı; bitiş günü dahil.

## Satıcılar Cari / Alıcılar Cari / Satıcılar Petrol / Piyasalar

Dört rapor aynı yapıdadır; yalnız rol ve ek kolonlar farklıdır.

| Rapor | Eski dosyalar | Yetki | Rol |
|---|---|---|---|
| Satıcılar Cari | `rapor_carifis*.php` | `saticilar_cari` | `supplier` |
| Alıcılar Cari | `rapor_alicilarfis*.php` | `alicilar_cari` | `customer` |
| Satıcılar Petrol | `rapor_petrolfis*.php` | `saticilar_petrol` | `fuel` |
| Piyasalar | `rapor_piyasalarfis*.php` | `piyasalar` | `market` |

- **Filtreler:** Firma (yalnız ilgili role sahip firmalar: `company_roles.role = :rol`), tarih aralığı (Satıcılar Petrol eskide yalnız tek ay).
- **Satır birimi:** fiş başına bir satır; üstte firma bilgi bloğu; altta Toplam ve Devir bakiye.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Firma Adı / Adresi / Telefon – Fax / Vergi Dairesi – No (üst bilgi) | firma tablosu + şehir/ilçe/semt | `companies` + `cities` / `districts` / `neighborhoods` |
| # | sıra | satır no |
| Zaman | `*_Odeme_Zaman` (DATE) | `CT.occurred_on` |
| Belge No | `*_Belge_No` | `CT.document_no` (detay linki) |
| Açıklama | `*_Aciklama` | `CT.description` |
| Litre (yalnız Petrol) | `PF_Litre` | `CT.liters` (toplamda birim "L"; eskide "TL" yazıyordu) |
| Borç | `Odeme_Turu = 'B'` ise tutar | `CT.debit` |
| Alacak | `Odeme_Turu = 'A'` ise tutar | `CT.credit` |
| Devir (yürüyen; Satıcılar ve Alıcılar) | `alacak − borç`, satırın kendisi hariç, `VGZ` sıralı | `devir + SUM(debit − credit) OVER (...)` |
| Toplam | Σborç, Σalacak | `SUM(debit)`, `SUM(credit)` |
| Devir bakiye | dönem içi `A − B` (Piyasalar'da `B − A`) | devir + `SUM(debit − credit)` (kapanış) |
| (yeni, Petrol) Birim Fiyat, Yakıt Yeri | — | `CT.unit_price`, `fuel_stations.name` |
| (yeni, Piyasalar) Plaka, Araç Sahibi | — | `vehicles.plate`, `vehicle_owners` adı |

Sorgu: `company_transactions WHERE company_id = :c AND role = :rol AND occurred_on BETWEEN :bas AND :bit ORDER BY occurred_on, created_at, id`.

**Farklar:**
- Yürüyen devir bir satır gerideydi (katı `<`) ve gösterim sırasıyla farklı bir sırayla (`VGZ`) hesaplanıyordu (satır 55–57, 69–71).
- Açılış devri yoktu; "Devir bakiye" yalnız dönem netiydi.
- İşaret tutarsızdı: Piyasalar fiş raporu `B − A` (satır 101), Piyasalar bakiye raporu `A − B`. Yeni sistemde tek kural.
- Satıcılar Petrol ve Piyasalar `VGZ DESC` sıralıydı, devir yoktu.
- "2" sentinel'li tarih formları kalktı.

## Satıcılar Bakiye / Alıcılar Bakiye / Piyasalar Bakiye

| Rapor | Eski dosyalar | Yetki | Rol |
|---|---|---|---|
| Satıcılar Bakiye | `rapor_saticilar_bakiye*.php` | `saticilar_bakiye` | `supplier` |
| Alıcılar Bakiye | `rapor_alicilar_bakiye*.php` | `alicilar_bakiye` | `customer` |
| Piyasalar Bakiye | `rapor_piyasalar_bakiye*.php` | `piyasalar_bakiye` | `market` |

- **Filtreler:** tarih aralığı (isteğe bağlı). Yeni: Firma Grubu (`company_groups`, alt gruplar dahil).
- **Satır birimi:** firma (ada göre); hareketsiz firmalar gizli; altta Toplam. Yeni: isteğe bağlı grup ara toplamları.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Firma | `*_Firma_Adi` | `companies.name` |
| Alacak | `SUM(Miktar) WHERE Odeme_Turu = 'A'` | dönem `SUM(CT.credit)` |
| Borç | `SUM(Miktar) WHERE Odeme_Turu = 'B'` | dönem `SUM(CT.debit)` |
| Bakiye | `alacak − borç` (yalnız dönem) | bitiş tarihi itibarıyla `SUM(debit − credit) WHERE occurred_on <= :bit` (devir dahil) |
| Toplam | kolon toplamları | aynı |

```sql
SELECT c.name,
       SUM(ct.credit) FILTER (WHERE ct.occurred_on BETWEEN :bas AND :bit) AS donem_alacak,
       SUM(ct.debit)  FILTER (WHERE ct.occurred_on BETWEEN :bas AND :bit) AS donem_borc,
       SUM(ct.debit - ct.credit) FILTER (WHERE ct.occurred_on < :bas)      AS devir,
       SUM(ct.debit - ct.credit) FILTER (WHERE ct.occurred_on <= :bit)     AS bakiye
FROM company_transactions ct
JOIN companies c ON c.id = ct.company_id
WHERE ct.role = :rol AND ct.deleted_at IS NULL
GROUP BY c.id, c.name
HAVING SUM(ct.debit) <> 0 OR SUM(ct.credit) <> 0
ORDER BY c.name;
```

Gruplu cari raporu: aynı sorgu `companies.company_group_id → company_groups` (ve `parent_id`) ile gruplanır. Rol filtresi kaldırılırsa firmanın tüm rollerdeki toplam bakiyesi çıkar.

**Farklar:** bitiş ayı Şubat seçilince rapor sessizce tüm zamanlara düşüyordu (satır 15, "2" sentinel'i); "Bakiye" yalnız dönem hareketiydi, devir yoktu; Excel adı yanlıştı (satır 64); tek işaret kuralı.

---

# Personel raporları

## Personel Aylık Döküm

- **Eski dosyalar:** `rapor_personel_aylik.php`, `rapor_personel_aylik_sonuc.php`. Yetki `personel_yillik_odeme_raporu`.
- **Filtreler:** Yıl, Ay (dönem).
- **Satır birimi:** personel (ad soyad sıralı); yeni raporda dönemde maaş kartı, ödemesi ya da devri olan herkes; altta Toplam.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Personel Adı | `Personel_Adi + Soyadi` | `employees.first_name || ' ' || last_name` |
| Dönem Maaşı | `PM_Donem_Maas` (`LIMIT 1`) | `employee_salaries.earned_salary WHERE period = :P` (taban maaş ve gün ayrıca gösterilebilir) |
| (yeni) Pirim | — | `SUM(employee_bonuses.amount) WHERE occurred_on ∈ ay` |
| Önceki Devir | `ΣPM_Donem_Maas(< P) − ΣPO_Miktar(< P)` | `Σearned_salary(period < P) + Σbonuses(occurred_on < P) − Σpayments(period < P)` |
| Dönem İçi Ödenen | `ΣPO_Miktar WHERE PO_Donem = P` | `SUM(employee_payments.amount) WHERE period = :P` |
| Kalan | `maaş + devir − ödeme` | `Dönem Maaşı + Pirim + Önceki Devir − Ödenen` (= ledger'da `credit − debit`; pozitif = personele borçluyuz) |

**Farklar:** kazanılan maaş yerine taban maaş okunuyordu (satır 23; maaş kartı ekranıyla tutmuyordu); prim hiç hesaba katılmıyordu; maaş kartı olmayan ama ödemesi olan personel gizleniyordu (satır 34).

## Personel Yıllık Ödeme Raporu

- **Eski dosyalar:** `rapor_personelodeme.php`, `rapor_personelodeme_sonuc.php`. Yetki `personel_yillik_odeme_raporu`.
- **Filtreler:** Personel (zorunlu), Yıl.
- **Satır birimi:** üst bilgi bloğu (Adı Soyadı, İşe Başlama, Adres, Telefon, Cep, Meslek, Araç); "Geçmiş Yıllar Devir" satırı; ay ay maaş satırı (+), o ayın ödemeleri (−) ve primleri; yürüyen Devir. Yeni: alt toplamlar.

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| Tarih | ödemede `PO_VGZ` (kayıt zamanı) | ödeme `employee_payments.occurred_on`; prim `employee_bonuses.occurred_on` |
| Dönem | `PM_Donem` / `PO_Donem` | `period` (prim için `occurred_on` ayı) |
| Açıklama | "<Ay Yıl> Maaşı" / `PO_Aciklama` | aynı / `description` / "Pirim" |
| Maaşı | `PM_Donem_Maas` | `employee_salaries.earned_salary` (prim ayrı satır) |
| Ödeme | `PO_Miktar` | `employee_payments.amount` |
| Devir | devir + maaş − ödeme | açılış + `SUM(credit − debit) OVER (ORDER BY period, tür, occurred_on, created_at)` |
| Geçmiş Yıllar Devir | `ΣPM_Donem_Maas − ΣPO_Miktar`, Y öncesi | `Σearned_salary + Σbonuses − Σpayments`, Y öncesi |
| (yeni) alt toplam | — | Toplam Maaş, Toplam Pirim, Toplam Ödeme, Kapanış |

**Farklar:** maaş kartı olmayan aydaki ödemeler hem listeden hem devirden düşüyordu (satır 93–98); aynı aya iki kart varsa ödemeler iki kez düşülüyordu; Tarih kayıt zamanıydı (satır 104); prim yoktu.

---

# Ekranlar

## Çek Senet Kredi

- **Eski dosyalar:** `cksfis.php` (arama), `cksfis_islemler.php` (liste ve işlemler); yardımcılar `cksfis_ekle.php`, `cksfis_detay.php`, `cektopluode.php`, `cks_kredi_*.php`, `ckskredi_vade_tarih.php`, `cks_resim.php`. Menü: Kasa İşlemleri → "Çek Senet Kredi", yetki `cek_senet`.
- **Filtreler:** Durum (yeni: `unused` dahil), Tür, Banka, Vade aralığı, Hareket (yeni: isteğe bağlı), Belge No (`=` / içerir / arasında), Konusu (`cash_categories`), Firma (metin), Sıralama (yeni: isteğe bağlı).
- **Satır birimi:** çek/senet başına bir satır; krediler (`kind = 'loan'`) yerine **her taksit için bir satır**. Altta Çek, Senet, Kredi Taksit, Kalan Kredi toplamları.

| Başlık | Eski kaynak (çek / taksit) | Yeni formül |
|---|---|---|
| # | sıra | satır no |
| Belge No | `Cks_Belge_No` | `financial_instruments.document_no` |
| Hareket | G → Giriş, C → Çıkış | `debit > 0` → Giriş, `credit > 0` → Çıkış |
| Banka | `Banka_Adi` | `banks.name` |
| Hesap No | `Cks_Hesap_No` | `account_no` |
| Düzenlenme | `Cks_Duzenlenme_Tarih` | `issued_on` |
| Vade | `Cks_Vade_Tarih` / `Cks_Kredi_Odeme_Tarih` | `due_on` / `installments.due_on` |
| Tutar | `Cks_Tutar` / `Cks_Kredi_Tutar` | `debit + credit` / `installments.amount` |
| Taksit | `Cks_Taksit_Sayi` / "N / sıra / kalan TL" | `installment_count` / `installment_count || ' / ' || ROW_NUMBER() OVER (PARTITION BY financial_instrument_id ORDER BY due_on, id) || ' / ' || Σamount(status = 'active')` |
| Firma | `Cks_Firma` (metin) | `company_name` |
| Tür | ham kod C/S/K | Çek / Senet / Kredi |
| Kategori | `cari_kategori_ad` | `cash_categories.name` |
| Durum | ham kod A/P/T/I | Aktif / Kapandı / Teminat / Kullanılmadı; taksitte Aktif / Ödendi |
| İşlem | Öde, Düzenle, Sil, Resim, Taksitler | aşağıda |

| Alt toplam | Eski | Yeni |
|---|---|---|
| Çek / Senet | listelenen satırlar, Giriş ve Çıkış karışık, ödenmiş dahil | `kind` başına, **Giriş / Çıkış ayrı**, aktif ve kapalı ayrı |
| Kredi Taksit | gösterilen taksitler | `SUM(installments.amount)` (filtrelenmiş) |
| Kalan Kredi | vade filtresi yok sayılıyordu | `SUM(amount) WHERE status = 'active'` (filtrelenmiş krediler) |

**İşlemler:**
- **Öde (çek/senet):** `status = 'closed'`. İsteğe bağlı nakit/banka hareketi açılması karar bekliyor.
- **Öde (taksit):** `installments.status = 'paid'`, `paid_on`, `receipt_no`. Aktif taksit kalmayınca kredi `closed` olur (tekli ve toplu ödemede aynı kural; eskide toplu ödeme krediyi kapatmıyordu).
- **Devret / ciro:** yeni kayıt `endorsed_from_id = eski.id`, `received_no = eski.document_no`; eski kayıt `status = 'closed'`, `given_no = yeni.document_no`. Yön ve kategori korunur (eskide kayboluyordu, satır 57–74).
- **Vade düzenle (taksit):** `installments.due_on`.
- **Sil:** soft delete; taksitler ve ekler birlikte (eskide yetim kalıyordu, satır 194–211); `dump_site_payment_id` bağı çözülür.
- **Resim:** `financial_instrument_attachments`.
- **Kredi ekleme:** eşit taksit; ay sonu taşması güvenli (eskide 31 Şubat → `0000-00-00`).

**Farklar:** Hareket filtresi zorunluydu (boş seçenek yok, satır 253); "içerir" `%` olmadan çalışıyordu (satır 243); krediler `OR cks_id IN (...)` ile tüm filtreleri atlıyordu (satır 272–275); sayfalama ve toplamlar yalnız o sayfaya göreydi (satır 295–311); ödeme tipi sabit id'lerle kontrol ediliyordu (bkz. `db-mapping.md` bölüm 10).

## Kasa Nakit İşlemler

- **Eski dosyalar:** `nakit.php` (arama), `nakit_islemler.php`, `nakit_ekle.php`, `nakit_detay.php`. Menü: Kasa İşlemleri → "Kasa Nakit İşlemler", yetki `kasa_nakit_girisi`.
- **Filtreler:** Cari Kategori, Banka, İşlem (Giriş / Çıkış), Açıklama (içerir), tarih aralığı, Sıralama.
- **Satır birimi:** nakit hareketi başına bir satır; altta Giriş / Çıkış / Bakiye; üst bilgi "Kasa: <bakiye>".

| Başlık | Eski kaynak | Yeni formül |
|---|---|---|
| # | sıra | satır no |
| Cari Kategori | `cari_kategori_ad` | `cash_categories.name` |
| Banka | `Banka_Adi` | `banks.name` |
| Açıklama | `Nakit_Girisi_Aciklama` | `description` |
| Giriş | `Nakit_Islem = 'G'` ise tutar | `cash_entries.debit` |
| Çıkış | `Nakit_Islem = 'C'` ise tutar | `cash_entries.credit` |
| Zaman | `Nakit_Girisi_Zaman` (saat dahil) | `occurred_on` (saat yok; sıra `created_at`) |
| İşlem | Düzenle, Sil | Düzenle, Sil (soft delete) |
| Giriş / Çıkış / Bakiye (alt) | **yalnız o sayfanın** toplamı | filtre sonucunun tamamı: `SUM(debit)`, `SUM(credit)`, `SUM(debit − credit)` |
| Kasa: (üst) | tüm tablo `ΣG − ΣC` | tüm `cash_entries` için `SUM(debit − credit)` ("nakit defteri bakiyesi") ya da Kasa Hareketleri kapanışı (karar 3 kapsamıyla) |
| (yeni) Bakiye | — | yürüyen bakiye + aralık filtresinde Devir satırı |

**Farklar:** alt toplamlar sayfa bazlıydı, üst "Kasa:" tüm zamanlar; iki rakam farklı şeyler gösteriyordu (satır 110–116, 135–155). Bitiş günü dahil; sıralama isteğe bağlı; sayfalama filtreleri korur. Bankalı satırlar açık karar 4.
