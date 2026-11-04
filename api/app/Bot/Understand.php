<?php

namespace App\Bot;

use App\Models\DirectoryCity;
use Illuminate\Support\Collection;

/**
 * What a member meant by what they typed, from words the community really
 * uses, in the four languages: "driver job sharjah", "ሹፌር ዱባይ", "restaurant
 * deira". No AI and no key needed, so it answers at once and costs nothing. A
 * real question ("how do I change my employer?") is left for the assistant, and
 * someone in trouble is always understood, however they word it.
 */
final class Understand
{
    /** Trade key → the words that name it. Keys are those of Choices::TRADES. */
    private const TRADES = [
        'domestic' => ['housemaid', 'maid', 'nanny', 'babysitter', 'domestic', 'የቤት ሰራተኛ', 'የቤት ሠራተኛ', 'የቤት ሥራ', 'ናይ ገዛ', 'hojii manaa'],
        'driver' => ['driver', 'chauffeur', 'ሹፌር', 'konkolaachisaa'],
        'security' => ['security', 'guard', 'ጥበቃ', 'ጠባቂ', 'ሓለዋ', 'eegumsa'],
        'cleaning' => ['cleaner', 'cleaning', 'ጽዳት', 'ፅዳት', 'ጽሬት', 'qulqulleessuu'],
        'hospitality' => ['waiter', 'waitress', 'barista', 'chef', 'cook', 'hotel', 'ሆቴል', 'አስተናጋጅ', 'ኣሳላፊ', 'hoteela'],
        'salon' => ['hairdresser', 'barber', 'beautician', 'ውበት', 'ጽባቐ'],
        'retail' => ['sales', 'salesman', 'saleswoman', 'cashier', 'ሽያጭ', 'መሸጣ', 'gurgurtaa'],
        'warehouse' => ['warehouse', 'delivery', 'rider', 'courier', 'መጋዘን', 'ዴሊቨሪ', 'መኽዘን', 'ደሊቨሪ'],
        'nursing' => ['nurse', 'caregiver', 'nursing', 'ነርስ', 'እንክብካቤ', 'ክንክን', 'narsii'],
        'office' => ['secretary', 'receptionist', 'accountant', 'office', 'ቢሮ', 'ጸሓፊ', 'waajjira'],
        'construction' => ['electrician', 'plumber', 'mason', 'technician', 'construction', 'ግንባታ', 'ቴክኒሻን', 'ህንጻ', 'ijaarsa'],
    ];

    private const JOB_WORDS = ['job', 'work', 'vacancy', 'vacancies', 'hiring', 'employment', 'ስራ', 'ሥራ', 'ስራሕ', 'hojii'];

    /** Kind of place → the words that name it. Keys are those of Choices::BUSINESS_CATEGORIES. */
    private const PLACES = [
        'restaurant' => ['restaurant', 'injera', 'food', 'ምግብ ቤት', 'ምግብ', 'እንጀራ', 'ቤት መግቢ', 'መግቢ', 'mana nyaataa', 'nyaata'],
        'cafe' => ['cafe', 'café', 'coffee', 'bunna', 'buna', 'ካፌ', 'ቡና', 'ቡን'],
        'grocery' => ['grocery', 'shop', 'spices', 'spice', 'berbere', 'teff', 'ሱቅ', 'በርበሬ', 'ጤፍ', 'ድኳን', 'suuqii'],
        'salon' => ['salon', 'hair', 'braids', 'barber', 'hairdresser', 'ሳሎን', 'ፀጉር', 'መቖረጺ', 'saaloonii'],
        'cargo' => ['cargo', 'shipping', 'ካርጎ', 'መላኪያ', 'kaargoo'],
        'travel' => ['ticket', 'airline', 'travel agent', 'ትኬት', 'ቲኬት', 'የጉዞ ወኪል', 'ወኪል ጉዕዞ', 'tikkeetii'],
        'typing' => ['typing', 'ታይፒንግ'],
        'clinic' => ['clinic', 'doctor', 'dentist', 'pharmacy', 'hospital', 'ክሊኒክ', 'ሐኪም', 'ሓኪም', 'ጤና', 'ጥዕና', 'doktora', 'fayyaa'],
        'fashion' => ['tailor', 'kemis', 'ቀሚስ', 'ስፌት', 'ልብስ', 'ክዳን', 'wayyaa'],
        'events' => ['wedding', 'event hall', 'አዳራሽ', 'ሰርግ', 'ኣዳራሽ'],
        'worship' => ['church', 'mosque', 'ቤተ ክርስቲያን', 'ቤተ ክርስትያን', 'መስጊድ', 'mana kadhannaa', 'masjiida'],
        'education' => ['school', 'course', 'tutor', 'ትምህርት', 'ትምህርቲ', 'barnoota'],
    ];

    /** A job at a kind of place, when someone names the place and says "job". */
    private const TRADE_OF_PLACE = ['restaurant' => 'hospitality', 'cafe' => 'hospitality', 'salon' => 'salon', 'cargo' => 'warehouse', 'clinic' => 'nursing', 'grocery' => 'retail', 'fashion' => 'retail'];

    private const GOODS_WORDS = ['parcel', 'package', 'carry', 'kilo', 'kg', 'traveller', 'traveler', 'ዕቃ', 'ኪሎ', 'መላክ', 'ኣቕሓ', 'meeshaa', 'kiilo', 'paarsalii'];

    /** Someone in trouble, or asking how to reach those who help. These win over everything else. */
    private const SAFETY_WORDS = [
        'police', 'embassy', 'consulate', 'emergency', 'abuse', 'abused', 'abusing', 'abusive', 'violence', 'beating', 'beaten', 'beats me', 'beat me', 'hit me', 'hits me',
        'rape', 'raped', 'harassed', 'harassing', 'harassment', 'trafficking', 'trafficked', 'protection', 'runaway', 'absconding', 'locked me', 'threatened', 'threatening',
        'ፖሊስ', 'ኤምባሲ', 'ኢምባሲ', 'ቆንስላ', 'ቆንስል', 'ድንገተኛ', 'ደበደበ', 'ተደበደብ', 'ይደበድብ', 'እየደበደብ', 'ጥቃት', 'ተደፈር', 'ደፈረ', 'ዘጋብኝ', 'ዓመጽ', 'ግፍዒ', 'ወቒዑ', 'ሃሪሙ', 'ተዓሚጸ',
        'poolisii', 'imbaasii', 'qoonsilaa', 'reebe', 'reebaa', 'gudeede', 'gudeedame', 'miidhaa',
    ];

    /** A passport is trouble when someone else has it: "he took my passport", not "passport renewal". */
    private const PASSPORT_WORDS = ['passport', 'ፓስፖር', 'paaspoortii', 'paaspoorti'];

    private const HOLDING_WORDS = [
        'took', 'taken', 'takes', 'holding', 'holds', 'held', 'keeps', 'kept', 'withholding', 'refuses', 'refused', 'wont give', 'won t give',
        'ወሰደ', 'ወሰዱ', 'ወስደ', 'ይዞ', 'ይዟል', 'ያዘ', 'ያዙ', 'ነጠቀ', 'ነጠቁ', 'አልሰጥ', 'ከለከለ', 'ወሲዱ', 'ሒዙ', 'መንዚዑ', 'fudhate', 'fudhatan', 'qabe', 'qabate', 'dide',
    ];

    /** Words for guarding work that also mean protection: said alone, the answer offers the safety numbers too. */
    private const GUARD_WORDS = ['ጥበቃ', 'ሓለዋ', 'eegumsa'];

    /**
     * Everyday phrases that hold a word from the lists above without asking for it.
     * They are taken out before reading, so "good job" is not a job search.
     */
    private const NOT_REQUESTS = [
        'of course', 'work permit', 'labour office', 'labor office', 'labour card', 'labor card', 'security deposit',
        'driving licence', 'driving license', 'driver licence', 'driver license', 'food delivery', 'salary package', 'public holiday',
        'የሥራ ፈቃድ', 'የስራ ፈቃድ', 'ስራ ፈቃድ', 'ሥራ ፈቃድ', 'ናይ ስራሕ ፍቓድ', 'hayyama hojii',
    ];

    /** A cry for help with nothing else in it: the safety card, whose title is that word. */
    private const HELP_WORDS = ['help', 'help me', 'እርዳታ', 'እርዱኝ', 'እርዳኝ', 'ሓገዝ', 'ሓግዙኒ', 'gargaarsa', 'na gargaaraa', 'na gargaari'];

    /** Thanks and yes and no at rest: a word back, not a search and not a call to Claude. */
    private const SMALL_TALK = [
        'thanks', 'thank you', 'thx', 'ok', 'okay', 'yes', 'yeah', 'no', 'nope', 'fine', 'great', 'nice', 'good', 'cool', 'good job', 'nice job', 'great job', 'well done', 'very', 'much', 'so',
        'አመሰግናለሁ', 'አመሰግናለው', 'እሺ', 'አዎ', 'አይ', 'ጥሩ', 'ተመስገን', 'የቀንየለይ', 'የቀን የለይ', 'ጽቡቕ', 'ሕራይ', 'እወ', 'ኣይፋል', 'የቐንየለይ', 'galatoomaa', 'galatoomi', 'tole', 'eeyyee', 'lakki', 'gaarii',
    ];

    private const GREETINGS = ['hi', 'hello', 'hey', 'selam', 'salam', 'salaam', 'good morning', 'good afternoon', 'good evening', 'ሰላም', 'አሰላም', 'ሰላም ነው', 'ጤና ይስጥልኝ', 'ጤና ይስጥልን', 'akkam', 'nagaa'];

    /** A sign that this is a question to be answered, not something to be fetched. "Where" is not one: it wants a place. */
    private const QUESTION_WORDS = ['how', 'what', 'why', 'when', 'should', 'can', 'could', 'would', 'does', 'do', 'is', 'are', 'እንዴት', 'ምንድን', 'ምንድነው', 'ለምን', 'መቼ', 'ይቻላል', 'ስንት', 'ከመይ', 'እንታይ', 'ስለምንታይ', 'መዓስ', 'akkamitti', 'maali', 'maaliif', 'yoom'];

    private const WHERE_WORDS = ['where', 'የት', 'ኣበይ', 'eessa'];

    /** Words that carry nothing: left out when counting and when searching names. */
    private const FILLER = [
        'i', 'im', 'am', 'me', 'my', 'a', 'an', 'the', 'in', 'at', 'on', 'of', 'to', 'for', 'near', 'around', 'please', 'pls', 'need', 'want', 'looking', 'look', 'find', 'any', 'some', 'where', 'there', 'and', 'or', 'show', 'give', 'get', 'hi', 'hello', 'selam',
        'new', 'another', 'እፈልጋለሁ', 'ውስጥ', 'አካባቢ', 'የት', 'አለ', 'ኣበይ', 'ኣሎ', 'እደሊ', 'ኣብ', 'eessa', 'barbaada', 'keessatti', 'jira',
        'ነው', 'ነኝ', 'አለኝ', 'ያስፈልገኛል', 'አዲስ', 'እባክዎ', 'እባክህ', 'እባክሽ', 'ኣለኒ', 'ኣለኹ', 'እየ', 'ሓድሽ', 'nan', 'qaba', 'haaraa',
    ];

    public function read(string $text): Understood
    {
        $lower = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
        // The words as typed decide whether someone is in trouble: nothing is taken out for that.
        $inTrouble = self::hasAny(self::SAFETY_WORDS, $lower) || (self::hasAny(self::PASSPORT_WORDS, $lower) && self::hasAny(self::HOLDING_WORDS, $lower));
        $inTrouble = $inTrouble || self::isOnly(self::HELP_WORDS, $lower);
        $hello = self::isOnly(self::GREETINGS, $lower);
        $smallTalk = self::isOnly(self::SMALL_TALK, $lower);

        $clean = self::without(self::NOT_REQUESTS, $lower);
        $tokens = self::tokens($clean);
        $cities = DirectoryCity::listed();

        $trade = self::firstKey(self::TRADES, $clean);
        $category = self::firstKey(self::PLACES, $clean);
        $emirate = self::place(Choices::JOB_EMIRATES, ['other_uae', 'outside_uae', 'unknown'], $clean);
        $cityKey = self::cityKey($clean, $cities);
        $jobWord = self::hasAny(self::JOB_WORDS, $clean);

        $significant = array_values(array_filter($tokens, fn (string $t): bool => ! self::isFiller($t)));
        $direct = ! self::isQuestion($clean, $tokens) && count($significant) <= 6;

        // Only a plain request is searched with its words, so a long story costs nothing.
        $words = [];
        if ($direct) {
            $known = array_merge(self::JOB_WORDS, self::GOODS_WORDS, self::SAFETY_WORDS, ...array_values(self::TRADES), ...array_values(self::PLACES));
            $words = array_values(array_filter($significant, fn (string $t): bool => mb_strlen($t) >= 2 && ! self::isKnown($t, $known) && ! self::names($t, $emirate, $cityKey, $cities)));
        }

        // "job" with words the bot does not know ("job for my sister", "it doesn't work") is someone talking, not searching.
        $asksForWork = $jobWord && ($trade !== null || $emirate !== null || $words === []);

        $kind = match (true) {
            $inTrouble => 'safety',
            $hello => 'hello',
            $smallTalk => 'thanks',
            // A word for both a trade and a place ("barber") is the place, unless they said it is work.
            $asksForWork || ($trade !== null && ($category === null || $jobWord)) => 'jobs',
            $category !== null => 'places',
            self::hasAny(self::GOODS_WORDS, $clean) => 'goods',
            default => null,
        };

        if ($kind === 'jobs' && $trade === null && $category !== null) {
            $trade = self::TRADE_OF_PLACE[$category] ?? null;
        }

        return new Understood($kind, $trade, $emirate, $category, $cityKey, $words, $direct, ! $jobWord && self::hasAny(self::GUARD_WORDS, $clean));
    }

    /** @return array<int, string> */
    private static function tokens(string $lower): array
    {
        return array_values(array_filter(preg_split('/[^\p{L}\p{M}\p{N}]+/u', $lower) ?: []));
    }

    /** @param array<int, string> $terms  Does the message hold these and nothing that carries meaning? */
    private static function isOnly(array $terms, string $lower): bool
    {
        // Longest first, so "good job" goes as one before "good" alone leaves "job" behind.
        usort($terms, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        $rest = self::without($terms, $lower);

        return $rest !== $lower && array_filter(self::tokens($rest), fn (string $t): bool => ! self::isFiller($t)) === [];
    }

    /** A word that only says someone is looking: "እየፈለግኩ", "ፈልጌ", "ደልየ", "barbaadaa", in any of their endings too. */
    private static function isFiller(string $token): bool
    {
        return in_array($token, self::FILLER, true) || preg_match('/^(?:(?:እየ|እ|ይ)?ፈ[ልለላ]|(?:እ|ይ)?ደል|ደሊ|barbaad)/u', $token) === 1;
    }

    /** @param array<int, string> $terms */
    private static function without(array $terms, string $lower): string
    {
        foreach ($terms as $term) {
            $lower = preg_replace(self::pattern($term), ' ', $lower) ?? $lower;
        }

        return $lower;
    }

    /** @param array<string, array<int, string>> $vocabulary */
    private static function firstKey(array $vocabulary, string $lower): ?string
    {
        foreach ($vocabulary as $key => $terms) {
            if (self::hasAny($terms, $lower)) {
                return $key;
            }
        }

        return null;
    }

    /** @param array<int, string> $terms */
    private static function hasAny(array $terms, string $lower): bool
    {
        foreach ($terms as $term) {
            if (self::has($lower, $term)) {
                return true;
            }
        }

        return false;
    }

    private static function has(string $lower, string $term): bool
    {
        return preg_match(self::pattern($term), $lower) === 1;
    }

    /**
     * Ge'ez words take endings freely and a few small words as prefixes ("በዱባይ",
     * "የሥራ", "ሥራዎች"), but they start where a word starts: "አስራ" (ten) is not "ስራ"
     * (work). Latin ones match as whole words, plurals included, so that "hair" is
     * not found in "chair".
     */
    private static function pattern(string $term): string
    {
        $quoted = preg_quote(mb_strtolower($term), '/');

        if (preg_match('/\p{Ethiopic}/u', $term)) {
            return '/(?<!\p{Ethiopic})(?:የ|ለ|በ|ከ|ስለ|እንደ|ወደ|ብ|ን|ካብ|ናብ|ኣብ|ና|ክ)?'.$quoted.'/u';
        }

        return '/(?<![\p{L}\p{N}])'.$quoted.'(?:s|es)?(?![\p{L}\p{N}])/u';
    }

    /**
     * @param  array<string, array<string, string>>  $list
     * @param  array<int, string>  $except
     */
    private static function place(array $list, array $except, string $lower): ?string
    {
        foreach ($list as $key => $labels) {
            if (! in_array($key, $except, true) && self::hasAny(array_values($labels), $lower)) {
                return $key;
            }
        }

        return null;
    }

    /** @param Collection<int, DirectoryCity> $cities */
    private static function cityKey(string $lower, Collection $cities): ?string
    {
        foreach ($cities as $city) {
            if (self::hasAny(array_map(fn (string $language) => $city->name($language), Choices::LANGUAGES), $lower)) {
                return $city->key;
            }
        }

        return null;
    }

    /** @param array<int, string> $tokens */
    private static function isQuestion(string $lower, array $tokens): bool
    {
        // Whole words: "የትኛው" (which) starts like "የት" (where) and is a question.
        if (array_intersect($tokens, self::WHERE_WORDS) !== []) {
            return false;
        }

        return str_contains($lower, '?') || str_contains($lower, '፧') || array_intersect($tokens, self::QUESTION_WORDS) !== []
            || self::hasAny(array_filter(self::QUESTION_WORDS, fn (string $w) => (bool) preg_match('/\p{Ethiopic}/u', $w)), $lower);
    }

    /** @param array<int, string> $known */
    private static function isKnown(string $token, array $known): bool
    {
        foreach ($known as $term) {
            if (self::has($token, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this word the name of the emirate or city already read?
     *
     * @param  Collection<int, DirectoryCity>  $cities
     */
    private static function names(string $token, ?string $emirate, ?string $cityKey, Collection $cities): bool
    {
        foreach (array_filter([$emirate ? (Choices::JOB_EMIRATES[$emirate] ?? null) : null]) as $labels) {
            if (self::isKnown($token, array_values($labels))) {
                return true;
            }
        }

        if ($cityKey !== null) {
            $city = $cities->firstWhere('key', $cityKey);
            if ($city && self::isKnown($token, array_map(fn (string $language) => $city->name($language), Choices::LANGUAGES))) {
                return true;
            }
        }

        return false;
    }
}
