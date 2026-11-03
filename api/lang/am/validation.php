<?php

// The rules business owners can hit on the website's forms, in Amharic.
// Anything not here falls back to English.
return [
    'required' => ':attribute ያስፈልጋል።',
    'string' => ':attribute ጽሑፍ መሆን አለበት።',
    'integer' => ':attribute ቁጥር መሆን አለበት።',
    'array' => ':attribute ዝርዝር መሆን አለበት።',
    'file' => ':attribute ፋይል መሆን አለበት።',
    'min' => [
        'string' => ':attribute ቢያንስ :min ፊደላት ሊኖሩት ይገባል።',
    ],
    'max' => [
        'string' => ':attribute ከ:max ፊደላት መብለጥ የለበትም።',
        'array' => ':attribute ከ:max በላይ መሆን የለበትም።',
        'file' => ':attribute ከ:max ኪሎባይት መብለጥ የለበትም።',
    ],
    'in' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'exists' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'url' => ':attribute ትክክለኛ የድረ-ገጽ አድራሻ መሆን አለበት።',
    'regex' => 'የ:attribute አጻጻፍ ትክክል አይደለም።',
    'mimetypes' => ':attribute JPEG፣ PNG ወይም WebP ፎቶ መሆን አለበት።',

    'attributes' => [
        'name' => 'የንግዱ ስም',
        'category' => 'የንግድ ዓይነት',
        'city_id' => 'ከተማ',
        'area' => 'አካባቢ',
        'maps_url' => 'የGoogle ካርታ አገናኝ',
        'phone' => 'ስልክ',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'የቴሌግራም ስም',
        'website' => 'ድረ-ገጽ',
        'description' => 'አጭር መግለጫ',
        'about' => 'ስለ ንግዱ',
        'hours' => 'የሥራ ሰዓት',
        'languages' => 'ቋንቋዎች',
        'languages.*' => 'ቋንቋ',
        'photo' => 'ፎቶ',
        'note' => 'ማብራሪያ',
    ],
];
