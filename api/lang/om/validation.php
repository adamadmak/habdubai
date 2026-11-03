<?php

// The rules business owners can hit on the website's forms, in Afaan Oromoo.
// Anything not here falls back to English. Written without a native
// reviewer; it needs a native speaker's review. :Attribute is :attribute
// with a capital letter, for the start of a sentence.
return [
    'required' => ':Attribute barbaachisaa dha.',
    'string' => ":Attribute barreeffama ta'uu qaba.",
    'integer' => ":Attribute lakkoofsa ta'uu qaba.",
    'array' => ":Attribute tarree ta'uu qaba.",
    'file' => ":Attribute faayila ta'uu qaba.",
    'min' => [
        'numeric' => ":Attribute yoo xiqqaate :min ta'uu qaba.",
        'array' => 'Yoo xiqqaate :attribute :min filadhaa.',
        'string' => ':Attribute yoo xiqqaate qubee :min qabaachuu qaba.',
    ],
    'max' => [
        'numeric' => ':Attribute :max caaluu hin qabu.',
        'string' => ':Attribute qubee :max caaluu hin qabu.',
        'array' => ":Attribute :max ol ta'uu hin qabu.",
        'file' => ':Attribute kiloobaayitii :max caaluu hin qabu.',
    ],
    'date_format' => ":Attribute guyyaa sirrii ta'uu qaba.",
    'required_unless' => ':Attribute barbaachisaa dha.',
    'not_regex' => 'Barreeffamni :attribute sirrii miti.',
    'in' => ':Attribute filatame sirrii miti.',
    'exists' => ':Attribute filatame sirrii miti.',
    'url' => ":Attribute teessoo marsariitii sirrii ta'uu qaba.",
    'regex' => 'Barreeffamni :attribute sirrii miti.',
    'mimetypes' => ":Attribute suuraa JPEG, PNG ykn WebP ta'uu qaba.",

    'attributes' => [
        'kind' => 'gosa maxxansaa',
        'mode' => 'kaayyoo',
        'direction' => 'kallattii',
        'date' => 'guyyaa',
        'from' => "magaalaa ka'umsaa",
        'to' => "magaalaa ga'umsaa",
        'kilos' => 'kiiloo',
        'categories' => 'gosa meeshaa',
        'categories.*' => 'gosa meeshaa',
        'price' => 'kaffaltii',
        'currency' => 'gosa maallaqaa',
        'name' => 'maqaa daldalaa',
        'category' => 'gosa daldalaa',
        'city_id' => 'magaalaa',
        'area' => 'naannoo',
        'maps_url' => 'liinkii Google Maps',
        'phone' => 'bilbila',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'maqaa Telegram',
        'website' => 'marsariitii',
        'description' => 'ibsa gabaabaa',
        'about' => "waa'ee daldalichaa",
        'hours' => "sa'aatii hojii",
        'languages' => 'afaanota',
        'languages.*' => 'afaan',
        'photo' => 'suuraa',
        'note' => 'ibsa',
    ],
];
