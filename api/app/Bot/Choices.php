<?php

namespace App\Bot;

/**
 * The fixed lists a member picks from. Keys are what the database stores;
 * labels are what the keyboard shows, per language. A pressed button is
 * matched against the labels of every language, so a member who switched
 * language mid-flow is still understood.
 */
final class Choices
{
    public const LANGUAGES = ['am', 'ti', 'en'];

    public const LANGUAGE_LABELS = [
        'am' => 'አማርኛ',
        'ti' => 'ትግርኛ',
        'en' => 'English',
    ];

    public const EMIRATES = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman'],
        'other' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate'],
    ];

    public const TRADES = [
        'domestic' => ['am' => 'የቤት ሥራ', 'ti' => 'ናይ ገዛ ስራሕ', 'en' => 'Domestic work'],
        'driver' => ['am' => 'ሹፌር', 'ti' => 'ሹፌር', 'en' => 'Driver'],
        'security' => ['am' => 'ጥበቃ', 'ti' => 'ሓለዋ', 'en' => 'Security'],
        'cleaning' => ['am' => 'ጽዳት', 'ti' => 'ጽሬት', 'en' => 'Cleaning'],
        'hospitality' => ['am' => 'ሆቴልና ሬስቶራንት', 'ti' => 'ሆቴልን ቤት መግብን', 'en' => 'Hospitality'],
        'salon' => ['am' => 'ሳሎንና ውበት', 'ti' => 'ሳሎንን ጽባቐን', 'en' => 'Salon and beauty'],
        'retail' => ['am' => 'ሽያጭ', 'ti' => 'መሸጣ', 'en' => 'Retail and sales'],
        'warehouse' => ['am' => 'መጋዘንና ዴሊቨሪ', 'ti' => 'መኽዘንን ደሊቨሪን', 'en' => 'Warehouse and delivery'],
        'nursing' => ['am' => 'ነርስና እንክብካቤ', 'ti' => 'ነርስን ክንክንን', 'en' => 'Nursing and care'],
        'office' => ['am' => 'ቢሮ', 'ti' => 'ቤት ጽሕፈት', 'en' => 'Office and admin'],
        'construction' => ['am' => 'ግንባታና ቴክኒክ', 'ti' => 'ህንጻን ቴክኒክን', 'en' => 'Construction and technical'],
        'other' => ['am' => 'ሌላ', 'ti' => 'ካልእ', 'en' => 'Other'],
    ];

    public const MAX_TRADES = 3;

    /** Where a job is. Wider than EMIRATES: a listing can be elsewhere in the UAE, abroad, or unstated. */
    public const JOB_EMIRATES = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman'],
        'other_uae' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate'],
        'outside_uae' => ['am' => 'ከኢማራት ውጭ', 'ti' => 'ካብ ኢማራት ወጻኢ', 'en' => 'Outside the UAE'],
        'unknown' => ['am' => 'ቦታ አልተገለጸም', 'ti' => 'ቦታ ኣይተገልጸን', 'en' => 'Location not stated'],
    ];

    /**
     * Kinds of business in the directory. No money exchange or transfer:
     * the community rules keep that out of every surface.
     */
    public const BUSINESS_CATEGORIES = [
        'restaurant' => ['am' => '🍽 ምግብ ቤት', 'ti' => '🍽 ቤት መግቢ', 'en' => '🍽 Restaurant'],
        'cafe' => ['am' => '☕ ካፌ እና ቡና', 'ti' => '☕ ካፈን ቡንን', 'en' => '☕ Cafe and coffee'],
        'grocery' => ['am' => '🛒 የሐበሻ ሱቅ', 'ti' => '🛒 ድኳን ሓበሻ', 'en' => '🛒 Habesha shop'],
        'salon' => ['am' => '💇 ሳሎንና ፀጉር ቤት', 'ti' => '💇 ሳሎንን መቖረጺ ጸጉርን', 'en' => '💇 Salon and barber'],
        'cargo' => ['am' => '📦 ካርጎና መላኪያ', 'ti' => '📦 ካርጎን ምልኣኽን', 'en' => '📦 Cargo and shipping'],
        'travel' => ['am' => '✈️ የጉዞ ወኪል', 'ti' => '✈️ ወኪል ጉዕዞ', 'en' => '✈️ Travel agency'],
        'typing' => ['am' => '🧾 ታይፒንግና የመንግሥት አገልግሎት', 'ti' => '🧾 ታይፒንግን ኣገልግሎት መንግስትን', 'en' => '🧾 Typing and government services'],
        'clinic' => ['am' => '🩺 ጤና', 'ti' => '🩺 ጥዕና', 'en' => '🩺 Health'],
        'fashion' => ['am' => '👗 የሐበሻ ልብስና ስፌት', 'ti' => '👗 ክዳን ሓበሻን ስፍየትን', 'en' => '👗 Habesha clothes and tailoring'],
        'events' => ['am' => '🎉 ዝግጅትና አዳራሽ', 'ti' => '🎉 ምድላው በዓልን ኣዳራሽን', 'en' => '🎉 Events and halls'],
        'worship' => ['am' => '⛪ የአምልኮ ቦታ', 'ti' => '⛪ ቦታ ኣምልኾ', 'en' => '⛪ Places of worship'],
        'education' => ['am' => '📚 ትምህርት', 'ti' => '📚 ትምህርቲ', 'en' => '📚 Education and classes'],
        'services' => ['am' => '🛠 አገልግሎቶች', 'ti' => '🛠 ኣገልግሎታት', 'en' => '🛠 Services'],
        'other' => ['am' => '📌 ሌላ', 'ti' => '📌 ካልእ', 'en' => '📌 Other'],
    ];

    public const QUESTION_CATEGORIES = [
        'visa' => ['am' => '🛂 ቪዛና መኖሪያ', 'ti' => '🛂 ቪዛን መንበሪን', 'en' => '🛂 Visa and residence'],
        'work' => ['am' => '💼 ሥራና ደሞዝ', 'ti' => '💼 ስራሕን ደሞዝን', 'en' => '💼 Work and salary'],
        'complaint' => ['am' => '⚖️ የሥራ ቅሬታ', 'ti' => '⚖️ ጥርዓን ስራሕ', 'en' => '⚖️ Labour complaint'],
        'housing' => ['am' => '🏠 መኖሪያ ቤት', 'ti' => '🏠 መንበሪ ገዛ', 'en' => '🏠 Housing'],
        'health' => ['am' => '🩺 ጤና', 'ti' => '🩺 ጥዕና', 'en' => '🩺 Health'],
        'banking' => ['am' => '🏦 ባንክና ሰነድ', 'ti' => '🏦 ባንክን ሰነድን', 'en' => '🏦 Banking and papers'],
        'transport' => ['am' => '🚌 ትራንስፖርት', 'ti' => '🚌 መጓዓዝያ', 'en' => '🚌 Transport'],
        'life' => ['am' => '🌍 የዕለት ኑሮ', 'ti' => '🌍 መዓልታዊ ናብራ', 'en' => '🌍 Daily life'],
        'other' => ['am' => '📌 ሌላ', 'ti' => '📌 ካልእ', 'en' => '📌 Other'],
    ];

    /** Goods board: which way a trip or a parcel goes. */
    public const GOODS_DIRECTIONS = [
        'to_et' => ['am' => '🇦🇪 → 🇪🇹 ወደ ኢትዮጵያ', 'ti' => '🇦🇪 → 🇪🇹 ናብ ኢትዮጵያ', 'en' => '🇦🇪 → 🇪🇹 To Ethiopia'],
        'to_ae' => ['am' => '🇪🇹 → 🇦🇪 ወደ ኢማራት', 'ti' => '🇪🇹 → 🇦🇪 ናብ ኢማራት', 'en' => '🇪🇹 → 🇦🇪 To the UAE'],
    ];

    public const GOODS_CITIES_UAE = [
        'dubai' => ['am' => 'ዱባይ', 'ti' => 'ዱባይ', 'en' => 'Dubai'],
        'sharjah' => ['am' => 'ሻርጃ', 'ti' => 'ሻርጃ', 'en' => 'Sharjah'],
        'abu_dhabi' => ['am' => 'አቡ ዳቢ', 'ti' => 'ኣቡ ዳቢ', 'en' => 'Abu Dhabi'],
        'ajman' => ['am' => 'አጅማን', 'ti' => 'ዓጅማን', 'en' => 'Ajman'],
        'other_uae' => ['am' => 'ሌላ ኢማራት', 'ti' => 'ካልእ ኢማራት', 'en' => 'Other emirate'],
    ];

    public const GOODS_CITIES_ET = [
        'addis_ababa' => ['am' => 'አዲስ አበባ', 'ti' => 'ኣዲስ ኣበባ', 'en' => 'Addis Ababa'],
        'dire_dawa' => ['am' => 'ድሬ ዳዋ', 'ti' => 'ድሬ ዳዋ', 'en' => 'Dire Dawa'],
        'mekelle' => ['am' => 'መቀሌ', 'ti' => 'መቐለ', 'en' => 'Mekelle'],
        'bahir_dar' => ['am' => 'ባሕር ዳር', 'ti' => 'ባሕር ዳር', 'en' => 'Bahir Dar'],
        'gondar' => ['am' => 'ጎንደር', 'ti' => 'ጎንደር', 'en' => 'Gondar'],
        'hawassa' => ['am' => 'ሀዋሳ', 'ti' => 'ሃዋሳ', 'en' => 'Hawassa'],
        'adama' => ['am' => 'አዳማ', 'ti' => 'ኣዳማ', 'en' => 'Adama'],
        'jimma' => ['am' => 'ጅማ', 'ti' => 'ጅማ', 'en' => 'Jimma'],
        'dessie' => ['am' => 'ደሴ', 'ti' => 'ደሴ', 'en' => 'Dessie'],
        'other_et' => ['am' => 'ሌላ ከተማ', 'ti' => 'ካልእ ከተማ', 'en' => 'Other city'],
    ];

    /** What a traveller accepts, or what a parcel is. Banned items are refused in the text, not listed here. */
    public const GOODS_CATEGORIES = [
        'documents' => ['am' => '📄 ሰነድና ደብዳቤ', 'ti' => '📄 ሰነድን ደብዳበን', 'en' => '📄 Documents and letters'],
        'clothes' => ['am' => '👕 ልብስና ጫማ', 'ti' => '👕 ክዳንን ጫማን', 'en' => '👕 Clothes and shoes'],
        'food' => ['am' => '🌶 የታሸገ ምግብና ቅመም', 'ti' => '🌶 ዝተዓሸገ መግብን ቀመምን', 'en' => '🌶 Packed food and spices'],
        'cosmetics' => ['am' => '🧴 ኮስሞቲክስ', 'ti' => '🧴 ኮስመቲክስ', 'en' => '🧴 Cosmetics'],
        'electronics' => ['am' => '📱 ኤሌክትሮኒክስ', 'ti' => '📱 ኤሌክትሮኒክስ', 'en' => '📱 Electronics'],
        'gifts' => ['am' => '🎁 ስጦታ', 'ti' => '🎁 ህያብ', 'en' => '🎁 Gifts'],
        'other' => ['am' => '📌 ሌላ', 'ti' => '📌 ካልእ', 'en' => '📌 Other'],
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
