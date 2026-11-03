<?php

namespace App\Bot;

/**
 * The fixed lists a member picks from. Keys are what the database stores;
 * labels are what the keyboard shows, per language. A pressed button is
 * matched against the labels of every language, so a member who switched
 * language mid-flow is still understood.
 *
 * The Afaan Oromoo (`om`) labels, like the Tigrinya ones, still need a native
 * speaker's review. Places in the UAE keep their UAE spelling; Ethiopian
 * cities take their Oromo names.
 */
final class Choices
{
    public const LANGUAGES = ['am', 'ti', 'om', 'en'];

    public const LANGUAGE_LABELS = [
        'am' => 'አማርኛ',
        'ti' => 'ትግርኛ',
        'om' => 'Afaan Oromoo',
        'en' => 'English',
    ];

    public const EMIRATES = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai', 'om' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah', 'om' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi', 'om' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman', 'om' => 'Ajman'],
        'other' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate', 'om' => 'Imaaraa biraa'],
    ];

    public const TRADES = [
        'domestic' => ['am' => 'የቤት ሥራ', 'ti' => 'ናይ ገዛ ስራሕ', 'en' => 'Domestic work', 'om' => 'Hojii manaa'],
        'driver' => ['am' => 'ሹፌር', 'ti' => 'ሹፌር', 'en' => 'Driver', 'om' => 'Konkolaachisaa'],
        'security' => ['am' => 'ጥበቃ', 'ti' => 'ሓለዋ', 'en' => 'Security', 'om' => 'Eegumsa'],
        'cleaning' => ['am' => 'ጽዳት', 'ti' => 'ጽሬት', 'en' => 'Cleaning', 'om' => 'Qulqulleessuu'],
        'hospitality' => ['am' => 'ሆቴልና ሬስቶራንት', 'ti' => 'ሆቴልን ቤት መግብን', 'en' => 'Hospitality', 'om' => 'Hoteelaa fi mana nyaataa'],
        'salon' => ['am' => 'ሳሎንና ውበት', 'ti' => 'ሳሎንን ጽባቐን', 'en' => 'Salon and beauty', 'om' => 'Saloonii fi bareedina'],
        'retail' => ['am' => 'ሽያጭ', 'ti' => 'መሸጣ', 'en' => 'Retail and sales', 'om' => 'Gurgurtaa'],
        'warehouse' => ['am' => 'መጋዘንና ዴሊቨሪ', 'ti' => 'መኽዘንን ደሊቨሪን', 'en' => 'Warehouse and delivery', 'om' => 'Kuusaa fi raabsa'],
        'nursing' => ['am' => 'ነርስና እንክብካቤ', 'ti' => 'ነርስን ክንክንን', 'en' => 'Nursing and care', 'om' => 'Narsii fi kunuunsa'],
        'office' => ['am' => 'ቢሮ', 'ti' => 'ቤት ጽሕፈት', 'en' => 'Office and admin', 'om' => 'Hojii waajjiraa'],
        'construction' => ['am' => 'ግንባታና ቴክኒክ', 'ti' => 'ህንጻን ቴክኒክን', 'en' => 'Construction and technical', 'om' => 'Ijaarsaa fi teeknikaa'],
        'other' => ['am' => 'ሌላ', 'ti' => 'ካልእ', 'en' => 'Other', 'om' => 'Kan biraa'],
    ];

    public const MAX_TRADES = 3;

    /** Where a job is. Wider than EMIRATES: a listing can be elsewhere in the UAE, abroad, or unstated. */
    public const JOB_EMIRATES = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai', 'om' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah', 'om' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi', 'om' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman', 'om' => 'Ajman'],
        'other_uae' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate', 'om' => 'Imaaraa biraa'],
        'outside_uae' => ['am' => 'ከኢማራት ውጭ', 'ti' => 'ካብ ኢማራት ወጻኢ', 'en' => 'Outside the UAE', 'om' => 'Imaaraatiin ala'],
        'unknown' => ['am' => 'ቦታ አልተገለጸም', 'ti' => 'ቦታ ኣይተገልጸን', 'en' => 'Location not stated', 'om' => 'Bakki hin ibsamne'],
    ];

    /**
     * Kinds of business in the directory. No money exchange or transfer:
     * the community rules keep that out of every surface.
     */
    public const BUSINESS_CATEGORIES = [
        'restaurant' => ['am' => '🍽 ምግብ ቤት', 'ti' => '🍽 ቤት መግቢ', 'en' => '🍽 Restaurant', 'om' => '🍽 Mana nyaataa'],
        'cafe' => ['am' => '☕ ካፌ እና ቡና', 'ti' => '☕ ካፈን ቡንን', 'en' => '☕ Cafe and coffee', 'om' => '☕ Kaafee fi buna'],
        'grocery' => ['am' => '🛒 የሐበሻ ሱቅ', 'ti' => '🛒 ድኳን ሓበሻ', 'en' => '🛒 Habesha shop', 'om' => '🛒 Suuqii Habashaa'],
        'salon' => ['am' => '💇 ሳሎንና ፀጉር ቤት', 'ti' => '💇 ሳሎንን መቖረጺ ጸጉርን', 'en' => '💇 Salon and barber', 'om' => '💇 Saloonii fi rifeensa'],
        'cargo' => ['am' => '📦 ካርጎና መላኪያ', 'ti' => '📦 ካርጎን ምልኣኽን', 'en' => '📦 Cargo and shipping', 'om' => '📦 Kaargoo fi erguu'],
        'travel' => ['am' => '✈️ የጉዞ ወኪል', 'ti' => '✈️ ወኪል ጉዕዞ', 'en' => '✈️ Travel agency', 'om' => '✈️ Ejensii imalaa'],
        'typing' => ['am' => '🧾 ታይፒንግና የመንግሥት አገልግሎት', 'ti' => '🧾 ታይፒንግን ኣገልግሎት መንግስትን', 'en' => '🧾 Typing and government services', 'om' => '🧾 Taayipii fi tajaajila mootummaa'],
        'clinic' => ['am' => '🩺 ጤና', 'ti' => '🩺 ጥዕና', 'en' => '🩺 Health', 'om' => '🩺 Fayyaa'],
        'fashion' => ['am' => '👗 የሐበሻ ልብስና ስፌት', 'ti' => '👗 ክዳን ሓበሻን ስፍየትን', 'en' => '👗 Habesha clothes and tailoring', 'om' => '👗 Uffata Habashaa fi hodhaa'],
        'events' => ['am' => '🎉 ዝግጅትና አዳራሽ', 'ti' => '🎉 ምድላው በዓልን ኣዳራሽን', 'en' => '🎉 Events and halls', 'om' => '🎉 Ayyaanaa fi galma'],
        'worship' => ['am' => '⛪ የአምልኮ ቦታ', 'ti' => '⛪ ቦታ ኣምልኾ', 'en' => '⛪ Places of worship', 'om' => '⛪ Bakka sagadaa'],
        'education' => ['am' => '📚 ትምህርት', 'ti' => '📚 ትምህርቲ', 'en' => '📚 Education and classes', 'om' => '📚 Barnootaa fi leenjii'],
        'services' => ['am' => '🛠 አገልግሎቶች', 'ti' => '🛠 ኣገልግሎታት', 'en' => '🛠 Services', 'om' => '🛠 Tajaajilawwan'],
        'other' => ['am' => '📌 ሌላ', 'ti' => '📌 ካልእ', 'en' => '📌 Other', 'om' => '📌 Kan biraa'],
    ];

    public const QUESTION_CATEGORIES = [
        'visa' => ['am' => '🛂 ቪዛና መኖሪያ', 'ti' => '🛂 ቪዛን መንበሪን', 'en' => '🛂 Visa and residence', 'om' => '🛂 Viizaa fi jireenya'],
        'work' => ['am' => '💼 ሥራና ደሞዝ', 'ti' => '💼 ስራሕን ደሞዝን', 'en' => '💼 Work and salary', 'om' => '💼 Hojii fi mindaa'],
        'complaint' => ['am' => '⚖️ የሥራ ቅሬታ', 'ti' => '⚖️ ጥርዓን ስራሕ', 'en' => '⚖️ Labour complaint', 'om' => '⚖️ Komii hojii'],
        'housing' => ['am' => '🏠 መኖሪያ ቤት', 'ti' => '🏠 መንበሪ ገዛ', 'en' => '🏠 Housing', 'om' => '🏠 Mana jireenyaa'],
        'health' => ['am' => '🩺 ጤና', 'ti' => '🩺 ጥዕና', 'en' => '🩺 Health', 'om' => '🩺 Fayyaa'],
        'banking' => ['am' => '🏦 ባንክና ሰነድ', 'ti' => '🏦 ባንክን ሰነድን', 'en' => '🏦 Banking and papers', 'om' => '🏦 Baankii fi sanadoota'],
        'transport' => ['am' => '🚌 ትራንስፖርት', 'ti' => '🚌 መጓዓዝያ', 'en' => '🚌 Transport', 'om' => '🚌 Geejjiba'],
        'life' => ['am' => '🌍 የዕለት ኑሮ', 'ti' => '🌍 መዓልታዊ ናብራ', 'en' => '🌍 Daily life', 'om' => '🌍 Jireenya guyyaa guyyaa'],
        'other' => ['am' => '📌 ሌላ', 'ti' => '📌 ካልእ', 'en' => '📌 Other', 'om' => '📌 Kan biraa'],
    ];

    /** Languages a business serves customers in, shown on its page. */
    public const SPOKEN_LANGUAGES = [
        'am' => ['am' => 'አማርኛ', 'ti' => 'ኣምሓርኛ', 'en' => 'Amharic', 'om' => 'Afaan Amaaraa'],
        'ti' => ['am' => 'ትግርኛ', 'ti' => 'ትግርኛ', 'en' => 'Tigrinya', 'om' => 'Afaan Tigrinyaa'],
        'om' => ['am' => 'ኦሮምኛ', 'ti' => 'ኦሮምኛ', 'en' => 'Afaan Oromo', 'om' => 'Afaan Oromoo'],
        'so' => ['am' => 'ሶማሊኛ', 'ti' => 'ሶማልኛ', 'en' => 'Somali', 'om' => 'Afaan Somaalee'],
        'ar' => ['am' => 'ዓረብኛ', 'ti' => 'ዓረብ', 'en' => 'Arabic', 'om' => 'Afaan Arabaa'],
        'en' => ['am' => 'እንግሊዝኛ', 'ti' => 'እንግሊዝኛ', 'en' => 'English', 'om' => 'Afaan Ingiliffaa'],
    ];

    /** Goods board: which way a trip or a parcel goes. */
    public const GOODS_DIRECTIONS = [
        'to_et' => ['am' => '🇦🇪 → 🇪🇹 ወደ ኢትዮጵያ', 'ti' => '🇦🇪 → 🇪🇹 ናብ ኢትዮጵያ', 'en' => '🇦🇪 → 🇪🇹 To Ethiopia', 'om' => '🇦🇪 → 🇪🇹 Gara Itoophiyaatti'],
        'to_ae' => ['am' => '🇪🇹 → 🇦🇪 ወደ ኢማራት', 'ti' => '🇪🇹 → 🇦🇪 ናብ ኢማራት', 'en' => '🇪🇹 → 🇦🇪 To the UAE', 'om' => '🇪🇹 → 🇦🇪 Gara Imaaraatitti'],
    ];

    public const GOODS_CITIES_UAE = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai', 'om' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah', 'om' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi', 'om' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman', 'om' => 'Ajman'],
        'other_uae' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate', 'om' => 'Imaaraa biraa'],
    ];

    public const GOODS_CITIES_ET = [
        'addis_ababa' => ['am' => 'አዲስ አበባ', 'ti' => 'ኣዲስ ኣበባ', 'en' => 'Addis Ababa', 'om' => 'Finfinnee'],
        'dire_dawa' => ['am' => 'ድሬ ዳዋ', 'ti' => 'ድሬ ዳዋ', 'en' => 'Dire Dawa', 'om' => 'Dirree Dhawaa'],
        'mekelle' => ['am' => 'መቀሌ', 'ti' => 'መቐለ', 'en' => 'Mekelle', 'om' => 'Maqalee'],
        'bahir_dar' => ['am' => 'ባሕር ዳር', 'ti' => 'ባሕር ዳር', 'en' => 'Bahir Dar', 'om' => 'Baahir Daar'],
        'gondar' => ['am' => 'ጎንደር', 'ti' => 'ጎንደር', 'en' => 'Gondar', 'om' => 'Gondar'],
        'hawassa' => ['am' => 'ሀዋሳ', 'ti' => 'ሃዋሳ', 'en' => 'Hawassa', 'om' => 'Hawaasaa'],
        'adama' => ['am' => 'አዳማ', 'ti' => 'ኣዳማ', 'en' => 'Adama', 'om' => 'Adaamaa'],
        'jimma' => ['am' => 'ጅማ', 'ti' => 'ጅማ', 'en' => 'Jimma', 'om' => 'Jimmaa'],
        'dessie' => ['am' => 'ደሴ', 'ti' => 'ደሴ', 'en' => 'Dessie', 'om' => 'Dessee'],
        'other_et' => ['am' => 'ሌላ ከተማ', 'ti' => 'ካልእ ከተማ', 'en' => 'Other city', 'om' => 'Magaalaa biraa'],
    ];

    /** What a traveller accepts, or what a parcel is. Banned items are refused in the text, not listed here. */
    public const GOODS_CATEGORIES = [
        'documents' => ['am' => '📄 ሰነድና ደብዳቤ', 'ti' => '📄 ሰነድን ደብዳበን', 'en' => '📄 Documents and letters', 'om' => '📄 Sanadaa fi xalayaa'],
        'clothes' => ['am' => '👕 ልብስና ጫማ', 'ti' => '👕 ክዳንን ጫማን', 'en' => '👕 Clothes and shoes', 'om' => '👕 Uffataa fi kophee'],
        'food' => ['am' => '🌶 የታሸገ ምግብና ቅመም', 'ti' => '🌶 ዝተዓሸገ መግብን ቀመምን', 'en' => '🌶 Packed food and spices', 'om' => '🌶 Nyaata qophaa\'aa fi qimama'],
        'cosmetics' => ['am' => '🧴 ኮስሞቲክስ', 'ti' => '🧴 ኮስመቲክስ', 'en' => '🧴 Cosmetics', 'om' => '🧴 Kosmeetiksii'],
        'electronics' => ['am' => '📱 ኤሌክትሮኒክስ', 'ti' => '📱 ኤሌክትሮኒክስ', 'en' => '📱 Electronics', 'om' => '📱 Elektirooniksii'],
        'gifts' => ['am' => '🎁 ስጦታ', 'ti' => '🎁 ህያብ', 'en' => '🎁 Gifts', 'om' => '🎁 Kennaa'],
        'other' => ['am' => '📌 ሌላ', 'ti' => '📌 ካልእ', 'en' => '📌 Other', 'om' => '📌 Kan biraa'],
    ];

    /** Quick kilo buttons; any whole number up to the maximum may be typed instead. */
    public const GOODS_KILOS_TRAVELLER = [2, 5, 10, 15, 20, 30];

    public const GOODS_KILOS_SENDER = [1, 2, 5, 10, 20];

    public const GOODS_MAX_KILOS = 46;

    /** The cities on the side a direction starts from, and the side it ends at. */
    public static function goodsCities(string $direction, bool $from): array
    {
        $uaeSide = ($direction === 'to_et') === $from;

        return $uaeSide ? self::GOODS_CITIES_UAE : self::GOODS_CITIES_ET;
    }

    public static function goodsCity(string $key, string $language): string
    {
        return self::GOODS_CITIES_UAE[$key][$language] ?? self::GOODS_CITIES_ET[$key][$language]
            ?? self::GOODS_CITIES_UAE[$key]['en'] ?? self::GOODS_CITIES_ET[$key]['en'] ?? $key;
    }

    /** Helper ranks by points: shown on the profile, they carry no rights. */
    public const RANKS = [500 => 'elder', 100 => 'trusted', 20 => 'helper'];

    public static function rank(int $points): ?string
    {
        foreach (self::RANKS as $min => $rank) {
            if ($points >= $min) {
                return $rank;
            }
        }

        return null;
    }

    /** Minimum monthly salary choices for job alerts, in AED; 0 = any. */
    public const SALARY_STEPS = [0, 1500, 2500, 4000, 6000];

    public static function languageFromLabel(string $text): ?string
    {
        $text = trim($text);

        foreach (self::LANGUAGE_LABELS as $code => $label) {
            if ($text === $label) {
                return $code;
            }
        }

        return null;
    }

    /**
     * The language picker, two buttons a row: four in one row would cut
     * "Afaan Oromoo" short on a phone.
     *
     * @return array<int, array<int, string>>
     */
    public static function languageRows(): array
    {
        return array_chunk(array_values(self::LANGUAGE_LABELS), 2);
    }

    /** @param array<string, array<string, string>> $list */
    public static function keyFromLabel(array $list, string $text): ?string
    {
        $text = trim($text);

        foreach ($list as $key => $labels) {
            if (in_array($text, $labels, true)) {
                return $key;
            }
        }

        return null;
    }

    /** @param array<string, array<string, string>> $list */
    public static function label(array $list, ?string $key, string $language): string
    {
        return $list[$key][$language] ?? $list[$key]['en'] ?? (string) $key;
    }

    /**
     * @param  array<string, array<string, string>>  $list
     * @return array<int, array<int, string>> keyboard rows, two labels per row
     */
    public static function rows(array $list, string $language, int $perRow = 2): array
    {
        $labels = array_map(fn (array $labels) => $labels[$language] ?? $labels['en'], array_values($list));

        return array_chunk($labels, $perRow);
    }
}
