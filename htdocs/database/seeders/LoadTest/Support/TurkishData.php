<?php

namespace Database\Seeders\LoadTest\Support;

/**
 * Realistic Turkish names, banks and business titles for load-test seed data.
 */
final class TurkishData
{
    /** @return list<string> */
    public static function banks(): array
    {
        return [
            'T.C. Ziraat Bankası',
            'Türkiye İş Bankası',
            'Garanti BBVA',
            'Yapı Kredi Bankası',
            'Akbank',
            'VakıfBank',
            'Halkbank',
            'DenizBank',
            'QNB Finansbank',
            'TEB',
            'ING Bank',
            'Şekerbank',
            'Alternatif Bank',
            'Anadolubank',
            'Fibabanka',
            'HSBC',
            'ICBC Turkey Bank',
            'Odeabank',
            'Turkish Bank',
            'Burgan Bank',
            'Citibank',
            'Deutsche Bank',
            'JPMorgan Chase Bank',
            'MUFG Bank Turkey',
            'Bank of China Turkey',
            'Intesa Sanpaolo',
            'Rabobank',
            'Société Générale',
            'Standard Chartered',
            'Ziraat Katılım Bankası',
            'Vakıf Katılım',
            'Türkiye Emlak Katılım Bankası',
            'Kuveyt Türk',
            'Albaraka Türk',
            'Türkiye Finans Katılım Bankası',
            'Hayat Finans Katılım Bankası',
            'Dünya Katılım Bankası',
            'Pasha Bank',
            'Golden Global Bank',
            'BankPozitif',
            'Merrill Lynch Yatırım Bankası',
            'Nurol Yatırım Bankası',
            'Diler Yatırım Bankası',
            'GSD Yatırım Bankası',
            'İller Bankası',
            'Türk Eximbank',
            'Türkiye Kalkınma ve Yatırım Bankası',
            'Aktif Bank',
        ];
    }

    /** @return list<string> */
    public static function firstNames(): array
    {
        return [
            'Ahmet', 'Mehmet', 'Mustafa', 'Ali', 'Hasan', 'Hüseyin', 'İbrahim', 'Ömer', 'Yusuf', 'Murat',
            'Emre', 'Burak', 'Can', 'Cem', 'Serkan', 'Onur', 'Tolga', 'Kerem', 'Barış', 'Volkan',
            'Ayşe', 'Fatma', 'Emine', 'Hatice', 'Zeynep', 'Elif', 'Merve', 'Selin', 'Esra', 'Özge',
            'Deniz', 'Ceyda', 'Gül', 'Seda', 'Pınar', 'Derya', 'Burcu', 'Melis', 'İrem', 'Sibel',
            'Halil', 'Ramazan', 'Süleyman', 'Osman', 'Fatih', 'Erkan', 'Hakan', 'Levent', 'Tamer', 'Yavuz',
            'Kemal', 'Recep', 'Şükrü', 'Cengiz', 'Orhan', 'Nihat', 'Taner', 'Uğur', 'Gökhan', 'Ercan',
            'Bekir', 'Adem', 'İsmail', 'Furkan', 'Berkay', 'Emir', 'Efe', 'Arda', 'Kaan', 'Mert',
            'Sevgi', 'Nur', 'Aylin', 'Gizem', 'Büşra', 'Tuğba', 'Gamze', 'Ebru', 'Yasemin', 'Hande',
            'Nazlı', 'İpek', 'Cansu', 'Tuğçe', 'Aslı', 'Beste', 'Damla', 'Ece', 'Fulya', 'Gözde',
            'Rıza', 'Vedat', 'Sinan', 'Alper', 'Berk', 'Doğukan', 'Enes', 'Koray', 'Okan', 'Tayfun',
        ];
    }

    /** @return list<string> */
    public static function lastNames(): array
    {
        return [
            'Yılmaz', 'Kaya', 'Demir', 'Çelik', 'Şahin', 'Yıldız', 'Yıldırım', 'Öztürk', 'Aydın', 'Özdemir',
            'Arslan', 'Doğan', 'Kılıç', 'Aslan', 'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek',
            'Erdoğan', 'Güneş', 'Aksoy', 'Polat', 'Bulut', 'Keskin', 'Avcı', 'Tekin', 'Türk', 'Acar',
            'Ünal', 'Güler', 'Çakır', 'Bayram', 'Taş', 'Karaca', 'Duman', 'Sarı', 'Aktaş', 'Erdem',
            'Kaplan', 'Bozkurt', 'Sever', 'Tunç', 'Gül', 'Akın', 'Soylu', 'Karaman', 'Orhan', 'Işık',
            'Ateş', 'Balcı', 'Başaran', 'Bilgin', 'Canpolat', 'Coşkun', 'Dağ', 'Ekinci', 'Erol', 'Genç',
            'Gündüz', 'İnan', 'Korkmaz', 'Kutlu', 'Mutlu', 'Özer', 'Peker', 'Sağlam', 'Tan', 'Toprak',
            'Uçar', 'Uzun', 'Vural', 'Yalçın', 'Yiğit', 'Zengin', 'Alkan', 'Baran', 'Çağlar', 'Demirci',
            'Ergin', 'Fidan', 'Gökçe', 'Hacıoğlu', 'İlhan', 'Kartal', 'Lale', 'Mercan', 'Nalbant', 'Önal',
        ];
    }

    /**
     * Distinctive brand / trade-name words only (no sector or legal form).
     *
     * @return list<string>
     */
    public static function companyPrefixes(): array
    {
        return [
            'KUZEY', 'GÜNEY', 'DOĞU', 'BATI', 'ANADOLU', 'MARMARA', 'EGE', 'AKDENİZ', 'KARADENİZ', 'TRAKYA',
            'BOĞAZİÇİ', 'YILDIZ', 'YILDIRIM', 'GÜÇLÜ', 'HIZLI', 'SAĞLAM', 'ÖZ', 'KARDEŞLER', 'ALTIN', 'GÜMÜŞ',
            'ÇINAR', 'PALMİYE', 'ULUDAĞ', 'KARTAL', 'ASLAN', 'DEMİR', 'TOPRAK', 'KUM', 'ÇAKIL', 'KAYA',
            'ZİRVE', 'UFUK', 'UFUKLAR', 'GÖKYÜZÜ', 'MAVİ', 'YEŞİL', 'KIRMIZI', 'BEYAZ', 'SİYAH', 'GRİ',
            'ATLAS', 'ORION', 'DELTA', 'PİRAMİT', 'KÖPRÜ', 'LİMAN', 'VADİ', 'ORMAN', 'IRMAK', 'GÖL',
            'BOZKURT', 'ŞAHİN', 'DOĞAN', 'PARS', 'KAPLAN', 'GEYİK', 'TURNA', 'AKÇAY', 'BULUT', 'FIRTINA',
            'METROPOL', 'BAŞKENT', 'BOĞAZ', 'KÖRFEZ', 'YARIMADA', 'ADA', 'TEPE', 'OVA', 'SIRT', 'YAYLA',
            'NAR', 'ZEYTİN', 'FINDIK', 'CEVİZ', 'KİRAZ', 'ELMA', 'ARMUT', 'ÜZÜM', 'İNCİR', 'PORTAKAL',
            'AYDIN', 'ÖZGÜR', 'BERK', 'CESUR', 'DURU', 'NET', 'KESİN', 'DOĞRU', 'YENİ', 'MODERN',
            'BİRİNCİ', 'İKİZLER', 'ÜÇYOL', 'DÖRTLÜ', 'BEŞKARDEŞ', 'ALTIYOL', 'YEDİTEPE', 'SEKİZYOL', 'DOKUZLAR', 'ONKAPI',
            'PIONEER', 'GLOBAL', 'STAR', 'PREMIUM', 'MASTER', 'PRO', 'PLUS', 'MAX', 'ULTRA', 'PRIME',
            'TUNA', 'SAKARYA', 'GEDİZ', 'MENDERES', 'KIZILIRMAK', 'YEŞİLIRMAK', 'ÇORUH', 'SEYHAN', 'DALAMAN', 'GÖKSU',
        ];
    }

    /**
     * Optional middle brand words (kept free of sector / legal suffixes).
     *
     * @return list<string>
     */
    public static function companyMids(): array
    {
        return [
            'GRUP', 'HOLDİNG', 'TİCARET', 'SANAYİ', 'MÜHENDİSLİK', 'TEKNİK', 'PROJE', 'ÇÖZÜM',
            'HİZMET', 'OPERASYON', 'FİLO', 'MERKEZ', 'BÖLGE', 'ANADOLU', 'MARMARA', 'EGE',
            'TRAKYA', 'KARDEŞ', 'ORTAK', 'BİRLEŞİK', 'ULUSAL', 'YEREL', 'BÖLGESEL', 'KÜRESEL',
        ];
    }

    /** @return list<string> */
    public static function companySuffixes(): array
    {
        return [
            'A.Ş.',
            'LTD. ŞTİ.',
            'TİC. LTD. ŞTİ.',
            'SAN. VE TİC. A.Ş.',
            'SAN. TİC. LTD. ŞTİ.',
            'İNŞ. TİC. LTD. ŞTİ.',
        ];
    }

    /** @return list<string> */
    public static function siteNames(): array
    {
        return [
            'HABİPLER MEKAN', 'CENNET KONUTLARI', 'YEŞİLPINAR DOSTLAR', 'ABDULLAH MERTER', 'BAŞAKŞEHİR VADİ',
            'ESENYURT PARK', 'BEYLİKDÜZÜ SAHİL', 'AVCILAR MERKEZ', 'KÜÇÜKÇEKMECE GÖL', 'BAHÇEŞEHİR GÖLET',
            'SULTANGAZİ TOROS', 'GAZİOSMANPAŞA YENİ', 'EYÜPSULTAN RİVA', 'SARIYER UÇARSU', 'BEYOĞLU PROJE',
            'ÜSKÜDAR KISIKLI', 'KADIKÖY FENER', 'ATAŞEHİR FİNANS', 'MALTEPE SAHİL', 'KARTAL VİADÜK',
            'PENDİK KAYNARCA', 'TUZLA OSB', 'ÇEKMEKÖY ORMAN', 'SANCAKTEPE MERKEZ', 'SULTANBEYLİ DOĞU',
            'ÜMRANİYE ÇARŞI', 'BEYKOZ RİVA', 'ŞİLE AĞVA', 'ARNAVUTKÖY HADIMKÖY', 'BÜYÜKÇEKMECE MİMAROBA',
            'SİLİVRİ SELİMPAŞA', 'ÇATALCA İNCİRLİ', 'ZEYTİNBURNU MERTER', 'BAKIRKÖY ATAKÖY', 'BAHÇELİEVLER ŞİRİNEVLER',
            'GÜNGÖREN MERTER', 'BAGCILAR GÜNEŞLİ', 'ESENLER MENDERES', 'BAYRAMPAŞA YENİDOĞAN', 'FATİH AKŞARAY',
            'KAĞITHANE SEYRANTEPE', 'ŞİŞLİ MECİDİYEKÖY', 'BEŞİKTAŞ LEVENT', 'ADALAR HEYBELİ', 'ÇORLU OSB',
            'ÇERKEZKÖY SANAYİ', 'TEKİRDAĞ MARMARA', 'KOCAELİ GEBZE', 'DARICA SAHİL', 'İZMİT MERKEZ',
            'SAKARYA ADAPAZARI', 'YALOVA ÇİFTLİKKÖY', 'BURSA NİLÜFER', 'BALIKESİR BANDIRMA', 'EDİRNE KAPAKLI',
            'KIRKLARELİ LÜLEBURGAZ', 'BOLU MERKEZ', 'DÜZCE AKÇAKOCA', 'ZONGULDAK EREĞLİ', 'KARABÜK SAFRANBOLU',
            'ANKARA ÇANKAYA', 'ANKARA YENİMAHALLE', 'ANKARA ETIMESGUT', 'ANKARA SİNCAN', 'ANKARA PURSAKLAR',
            'ESKİŞEHİR ODUNPAZARI', 'KONYA SELÇUKLU', 'KAYSERİ MELİKGAZİ', 'GAZİANTEP ŞEHİTKAMİL', 'ADANA SEYHAN',
            'MERSİN YENİŞEHİR', 'ANTALYA KEPEZ', 'MUĞLA BODRUM', 'AYDIN EFELER', 'İZMİR BORNOVA',
            'İZMİR KARŞIYAKA', 'İZMİR GAZİEMİR', 'MANİSA YUNUSEMRE', 'DENİZLİ PAMUKKALE', 'ISPARTA MERKEZ',
            'AFYONKARAHİSAR', 'UŞAK MERKEZ', 'KÜTAHYA MERKEZ', 'BİLECİK OSMANGAZİ', 'KIRŞEHİR MERKEZ',
            'NEVŞEHİR ÜRGÜP', 'NİĞDE MERKEZ', 'AKSARAY MERKEZ', 'KARAMAN MERKEZ', 'MERSİN TARSUS',
            'HATAY ANTAKYA', 'OSMANİYE MERKEZ', 'KAHRAMANMARAŞ ONİKİŞUBAT', 'MALATYA YEŞİLYURT', 'ELAZIĞ MERKEZ',
            'DİYARBAKIR BAĞLAR', 'ŞANLIURFA HALİLİYE', 'MARDİN ARTUKLU', 'BATMAN MERKEZ', 'SİİRT MERKEZ',
            'VAN İPEKYOLU', 'ERZURUM YAKUTİYE', 'TRABZON ORTAHİSAR', 'RİZE MERKEZ', 'SAMSUN ATAKUM',
            'ORDU ALTINORDU', 'GİRESUN MERKEZ', 'TOKAT MERKEZ', 'AMASYA MERKEZ', 'ÇORUM MERKEZ',
            'SİVAS MERKEZ', 'YOZGAT MERKEZ', 'ÇANKIRI MERKEZ', 'KASTAMONU MERKEZ', 'SİNOP MERKEZ',
        ];
    }

    /** @return list<string> */
    public static function dumpSiteNames(): array
    {
        return [
            'BOLLUCA', 'AĞAÇLI', 'ALK', 'DÜNYA AĞAÇLI', 'KİRAZLI', 'GÖKTÜRK', 'KEMERBURGAZ', 'OYUMCA',
            'ÇATALCA DÖKÜM', 'HADIMKÖY SAHA', 'SİLİVRİ ALAN', 'ÇORLU DÖKÜM', 'GEBZE SAHA', 'DARICA ALAN',
            'İZMİT DÖKÜM', 'ADAPAZARI SAHA', 'YALOVA ALAN', 'NİLÜFER DÖKÜM', 'BANDIRMA SAHA', 'EDİRNE ALAN',
            'LÜLEBURGAZ', 'BOLU SAHA', 'DÜZCE ALAN', 'EREĞLİ DÖKÜM', 'ANKARA GÖLBAŞI', 'SİNCAN SAHA',
            'ESKİŞEHİR ALAN', 'KONYA SAHA', 'KAYSERİ DÖKÜM', 'GAZİANTEP SAHA', 'ADANA ALAN', 'MERSİN SAHA',
            'ANTALYA DÖKÜM', 'İZMİR TORBALI', 'MANİSA SAHA', 'DENİZLİ ALAN', 'HATAY DÖKÜM', 'MALATYA SAHA',
            'DİYARBAKIR ALAN', 'ŞANLIURFA SAHA', 'VAN DÖKÜM', 'ERZURUM SAHA', 'TRABZON ALAN', 'SAMSUN DÖKÜM',
            'ORDU SAHA', 'TOKAT ALAN', 'SİVAS DÖKÜM', 'ÇORUM SAHA', 'KASTAMONU ALAN', 'RİZE DÖKÜM',
            'MUDANYA SAHA', 'GEMLİK ALAN', 'İNEGÖL DÖKÜM', 'ORHANGAZİ SAHA', 'KARACABEY ALAN', 'MUSTAFAKEMALPAŞA',
            'TEKİRDAĞ SAHA', 'MURATLI ALAN', 'SARAY DÖKÜM', 'HAYRABOLU SAHA', 'ŞARKÖY ALAN', 'MALKARA DÖKÜM',
        ];
    }

    /** @return list<string> */
    public static function fuelBrandNames(): array
    {
        return [
            'ÖZ', 'YILDIZ', 'GÜÇLÜ', 'MARMARA', 'ANADOLU', 'BOĞAZ', 'KARDEŞLER', 'ALTIN', 'HIZLI', 'SAĞLAM',
            'PETROL', 'ENERJİ', 'AKARYAKIT', 'YAKIT', 'İSTASYON', 'ZİRVE', 'UFUK', 'ATLAS', 'DELTA', 'STAR',
            'MAVİ', 'YEŞİL', 'KÖRFEZ', 'LİMAN', 'METRO', 'VADİ', 'TEPE', 'IRMAK', 'OVA', 'NAR',
        ];
    }

    /** @return list<string> */
    public static function cityAreas(): array
    {
        return [
            'İstanbul', 'Ankara', 'İzmir', 'Bursa', 'Antalya', 'Adana', 'Konya', 'Gaziantep', 'Şanlıurfa', 'Kocaeli',
            'Mersin', 'Diyarbakır', 'Hatay', 'Manisa', 'Kayseri', 'Samsun', 'Balıkesir', 'Kahramanmaraş', 'Van', 'Aydın',
            'Tekirdağ', 'Sakarya', 'Denizli', 'Muğla', 'Eskişehir', 'Trabzon', 'Erzurum', 'Ordu', 'Afyonkarahisar', 'Malatya',
            'Sivas', 'Çorum', 'Tokat', 'Elazığ', 'Zonguldak', 'Kütahya', 'Osmaniye', 'Çanakkale', 'Şırnak', 'Giresun',
            'Isparta', 'Aksaray', 'Yozgat', 'Edirne', 'Kastamonu', 'Düzce', 'Uşak', 'Kırıkkale', 'Niğde', 'Yalova',
            'Bolu', 'Karabük', 'Kırklareli', 'Amasya', 'Rize', 'Nevşehir', 'Batman', 'Kars', 'Mardin', 'Kırşehir',
            'Bitlis', 'Siirt', 'Bingöl', 'Hakkari', 'Artvin', 'Gümüşhane', 'Bayburt', 'Ardahan', 'Iğdır', 'Kilis',
            'Bartın', 'Sinop', 'Çankırı', 'Karaman', 'Bilecik', 'Burdur', 'Tunceli', 'Muş', 'Ağrı', 'Adıyaman',
        ];
    }

    /**
     * Turkish province plate codes (01–81).
     *
     * @return list<string>
     */
    public static function plateCityCodes(): array
    {
        return [
            '01', '02', '03', '04', '05', '06', '07', '08', '09', '10',
            '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
            '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
            '31', '32', '33', '34', '35', '36', '37', '38', '39', '40',
            '41', '42', '43', '44', '45', '46', '47', '48', '49', '50',
            '51', '52', '53', '54', '55', '56', '57', '58', '59', '60',
            '61', '62', '63', '64', '65', '66', '67', '68', '69', '70',
            '71', '72', '73', '74', '75', '76', '77', '78', '79', '80',
            '81',
        ];
    }

    public static function personName(): string
    {
        return fake()->randomElement(self::firstNames()).' '.fake()->randomElement(self::lastNames());
    }

    public static function companyTitle(string $sector = ''): string
    {
        $prefix = fake()->randomElement(self::companyPrefixes());
        $mid = fake()->boolean(45) ? fake()->randomElement(self::companyMids()).' ' : '';
        $sectorPart = $sector !== '' ? $sector.' ' : '';
        $suffix = fake()->randomElement(self::companySuffixes());

        // Avoid accidental "FOO FOO" when mid equals prefix or sector.
        $parts = array_values(array_filter([
            $prefix,
            trim($mid),
            trim($sectorPart),
        ], static fn (string $part): bool => $part !== ''));

        $unique = [];
        foreach ($parts as $part) {
            if ($unique === [] || end($unique) !== $part) {
                $unique[] = $part;
            }
        }

        return implode(' ', $unique).' '.$suffix;
    }

    public static function plate(?string $cityCode = null): string
    {
        $cityCode ??= fake()->randomElement(self::plateCityCodes());

        $lettersPool = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'U', 'V', 'Y', 'Z'];

        // Mix formats so the fleet does not look like one series.
        $format = fake()->randomElement([1, 1, 2, 2, 2, 3, 3, 3]);

        $letters = '';
        for ($i = 0; $i < $format; $i++) {
            $letters .= fake()->randomElement($lettersPool);
        }

        // Prefer non-round, spread-out digit blocks (avoid 1000/2000 clusters).
        $digits = match ($format) {
            1 => self::jaggedDigits(1000, 9999),
            2 => fake()->boolean(55)
                ? self::jaggedDigits(1000, 9999)
                : self::jaggedDigits(100, 999),
            default => fake()->boolean(60)
                ? self::jaggedDigits(100, 999)
                : self::jaggedDigits(10, 99),
        };

        return sprintf('%s %s %d', $cityCode, $letters, $digits);
    }

    private static function jaggedDigits(int $min, int $max): int
    {
        for ($i = 0; $i < 8; $i++) {
            $value = fake()->numberBetween($min, $max);
            $asString = (string) $value;

            // Skip tidy sequences (1111, 1234, 1000, 2500…).
            if (preg_match('/^(\d)\1+$/', $asString)) {
                continue;
            }

            if (in_array($value % 100, [0, 50], true) && fake()->boolean(70)) {
                continue;
            }

            return $value;
        }

        return fake()->numberBetween($min, $max);
    }
}
