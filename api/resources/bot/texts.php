<?php

/*
 * Every word the bot says, in Amharic, Tigrinya and English. An admin can
 * override any entry from the dashboard (bot_texts table); this file is the
 * default and the fallback. {placeholders} are filled by the caller.
 *
 * The Tigrinya column was written without a native reviewer and is on the
 * launch checklist for one.
 */
return [
    'welcome' => [
        'am' => "ሰላም! ወደ ሐበሻ ዱባይ እንኳን ደህና መጡ። 🇦🇪🇪🇹\nቋንቋ ይምረጡ።",
        'ti' => "ሰላም! ናብ ሓበሻ ዱባይ እንቋዕ ብደሓን መጻእኩም። 🇦🇪🇪🇹\nቋንቋ ምረጹ።",
        'en' => "Welcome to Habesha Dubai. 🇦🇪🇪🇹\nChoose your language.",
    ],
    'choose_language' => [
        'am' => 'ቋንቋ ይምረጡ።',
        'ti' => 'ቋንቋ ምረጹ።',
        'en' => 'Choose your language.',
    ],
    'language_set' => [
        'am' => 'ቋንቋ ወደ አማርኛ ተቀይሯል።',
        'ti' => 'ቋንቋ ናብ ትግርኛ ተቐይሩ።',
        'en' => 'Language set to English.',
    ],
    'ask_phone' => [
        'am' => 'ለመቀላቀል በዚህ ቴሌግራም መለያ ላይ ያለውን ስልክ ቁጥር ያጋሩ። ከታች ያለውን ቁልፍ ይጫኑ። ቁልፉ ብቻ ነው የሚሰራው፤ በእጅ የተጻፈ ቁጥር አይቀበልም። ቁጥርዎ ለማንም አይታይም።',
        'ti' => 'ንምጽንባር ኣብዚ ቴሌግራም ኣካውንት ዘሎ ቁጽሪ ስልኪ ኣካፍሉ። እታ ኣብ ታሕቲ ዘላ መልጎም ጠውቑ። እታ መልጎም ጥራይ እያ እትሰርሕ፤ ብኢድ ዝተጻሕፈ ቁጽሪ ኣይቅበልን እዩ። ቁጽርኹም ንማንም ኣይርአን እዩ።',
        'en' => 'To join, share the phone number on this Telegram account. Tap the button below. Only the button works: a typed number is not accepted, and your number is never shown to anyone.',
    ],
    'btn_share_phone' => [
        'am' => '📱 ስልክ ቁጥሬን አጋራ',
        'ti' => '📱 ቁጽሪ ስልከይ ኣካፍል',
        'en' => '📱 Share my phone number',
    ],
    'phone_not_own' => [
        'am' => 'ያ አድራሻ የሌላ ሰው ነው። የራስዎን ቁጥር ለማጋራት ቁልፉን ይጫኑ።',
        'ti' => 'እቲ ኣድራሻ ናይ ካልእ ሰብ እዩ። ናይ ባዕልኹም ቁጽሪ ንምክፋል እታ መልጎም ጠውቑ።',
        'en' => 'That contact belongs to someone else. Tap the button to share your own number.',
    ],
    'phone_unreadable' => [
        'am' => 'ቁጥሩ ሊነበብ አልቻለም። እንደገና ቁልፉን ይጫኑ።',
        'ti' => 'እቲ ቁጽሪ ክንበብ ኣይከኣለን። እንደገና እታ መልጎም ጠውቑ።',
        'en' => 'That number could not be read. Tap the button to share your contact again.',
    ],
    'phone_taken' => [
        'am' => 'ይህ ቁጥር በሌላ የቴሌግራም መለያ ተረጋግጧል። ለአንድ ሰው አንድ መለያ ብቻ ነው። ያ መለያ የእርስዎ ከሆነና ከጠፋ ለአስተዳዳሪ ይጻፉ፡ {support}',
        'ti' => 'እዚ ቁጽሪ ኣብ ካልእ ቴሌግራም ኣካውንት ተረጋጊጹ እዩ። ንሓደ ሰብ ሓደ ኣካውንት ጥራይ። እቲ ኣካውንት ናትኩም እንተኾይኑን እንተጠፊኡን ናብ ኣመሓዳሪ ጸሓፉ፡ {support}',
        'en' => 'This number is already verified on another Telegram account. One account per person. If that account is yours and you lost it, write to an admin: {support}',
    ],
    'phone_taken_notice' => [
        'am' => 'ሌላ ሰው ከሌላ የቴሌግራም መለያ የእርስዎን ስልክ ቁጥር ለማረጋገጥ ሞክሯል። እርስዎ ካልሆኑ ምንም አልተቀየረም፤ ቁጥርዎን ማን እንዳለው ይጠንቀቁ።',
        'ti' => 'ካልእ ሰብ ካብ ካልእ ቴሌግራም ኣካውንት ቁጽሪ ስልክኹም ከረጋግጽ ፈቲኑ። ንስኹም እንተዘይኮንኩም ዝተቐየረ የለን፤ ቁጽርኹም መን ከም ዘለዎ ተጠንቀቑ።',
        'en' => 'Someone tried to verify your phone number from another Telegram account. If that was not you, nothing has changed; just be careful who has your number.',
    ],
    'phone_country_review' => [
        'am' => 'ቁጥርዎ ከኤምሬትስና ከኢትዮጵያ ውጭ ነው። መቀጠል ይችላሉ፤ ከመለጠፍዎ በፊት አስተዳዳሪ መለያዎን ያያል።',
        'ti' => 'ቁጽርኹም ካብ ኢማራትን ኢትዮጵያን ወጻኢ እዩ። ክትቅጽሉ ትኽእሉ፤ ቅድሚ ምልጣፍኩም ኣመሓዳሪ ኣካውንትኩም ክርኢ እዩ።',
        'en' => 'Your number is from outside the UAE and Ethiopia. You can continue; an admin will look at your account before you can post.',
    ],
    'phone_ok' => [
        'am' => 'አመሰግናለሁ፣ ቁጥርዎ ተረጋግጧል። ✅',
        'ti' => 'የቐንየለይ፣ ቁጽርኹም ተረጋጊጹ። ✅',
        'en' => 'Thank you, your number is verified. ✅',
    ],
    'ask_name' => [
        'am' => 'ሌሎች አባላት በምን ስም ያዩዎት? ስም ይላኩ ወይም የቴሌግራም ስምዎን ያቆዩ።',
        'ti' => 'ካልኦት ኣባላት ብኸመይ ስም ይርኣዩኹም? ስም ስደዱ ወይ ናይ ቴሌግራም ስምኩም ኣቐምጡ።',
        'en' => 'What name should other members see? Send a name, or keep your Telegram name.',
    ],
    'btn_keep_name' => [
        'am' => '{name} ይቆይ',
        'ti' => '{name} ይቐመጥ',
        'en' => 'Keep {name}',
    ],
    'name_invalid' => [
        'am' => 'ስም ከ2 እስከ 40 ፊደላት ነው፤ ሊንክ ወይም ስልክ ቁጥር አይፈቀድም።',
        'ti' => 'ስም ካብ 2 ክሳብ 40 ፊደላት እዩ፤ ሊንክ ወይ ቁጽሪ ስልኪ ኣይፍቀድን።',
        'en' => 'A name is 2 to 40 letters, no links or phone numbers.',
    ],
    'ask_emirate' => [
        'am' => 'የት ነው የሚኖሩት?',
        'ti' => 'ኣበይ ትነብሩ?',
        'en' => 'Where do you live?',
    ],
    'ask_trades' => [
        'am' => 'ምን ሥራ ይሰራሉ ወይም ይፈልጋሉ? እስከ ሦስት ይምረጡና «ጨርሻለሁ» ይጫኑ።',
        'ti' => 'እንታይ ስራሕ ትሰርሑ ወይ ትደልዩ? ክሳብ ሰለስተ ምረጹ፣ ድሕሪኡ «ወዲአ» ጠውቑ።',
        'en' => 'What work do you do, or look for? Pick up to three, then tap Done.',
    ],
    'trades_picked' => [
        'am' => 'የተመረጠ፡ {trades}። ሌላ ይምረጡ ወይም «ጨርሻለሁ» ይጫኑ።',
        'ti' => 'ተመሪጹ፡ {trades}። ካልእ ምረጹ ወይ «ወዲአ» ጠውቑ።',
        'en' => 'Picked: {trades}. Pick more or tap Done.',
    ],
    'trades_need_one' => [
        'am' => 'ቢያንስ አንድ ይምረጡ።',
        'ti' => 'እንተወሓደ ሓደ ምረጹ።',
        'en' => 'Pick at least one.',
    ],
    'trades_max' => [
        'am' => 'ከሦስት በላይ አይቻልም። «ጨርሻለሁ» ይጫኑ።',
        'ti' => 'ካብ ሰለስተ ንላዕሊ ኣይከኣልን። «ወዲአ» ጠውቑ።',
        'en' => 'Three is the most. Tap Done.',
    ],
    'btn_done' => [
        'am' => '✅ ጨርሻለሁ',
        'ti' => '✅ ወዲአ',
        'en' => '✅ Done',
    ],
    'rules' => [
        'am' => "የዚህ ማህበረሰብ ህጎች፡\n\n1. ገንዘብ የሚጠይቅ ሥራ የለም። ሪፖርት ያድርጉ።\n2. ቪዛ፣ ፈቃድ ወይም ሰነድ መሸጥ የለም።\n3. ስለ ገንዘብ ማስተላለፍ ወይም ምንዛሪ መለጠፍ የለም።\n4. ፖለቲካ፣ የብሔር ስድብ እና የሃይማኖት ክርክር የለም።\n5. የዕቃ ሰሌዳ፡ ጥሬ ገንዘብ፣ ወርቅ፣ ያለ ማዘዣ መድኃኒት ወይም የተከለከሉ ዕቃዎች የሉም።\n6. ለአንድ ሰው አንድ መለያ።\n\nይህ መድረክ የሥራ ወኪል፣ የጭነት ድርጅት ወይም የሕግ አማካሪ አይደለም። ለመቀጠል ይስማሙ።",
        'ti' => "ሕግታት እዚ ማሕበረሰብ፡\n\n1. ገንዘብ ዝሓትት ስራሕ የለን። ሪፖርት ግበሩ።\n2. ቪዛ፣ ፍቓድ ወይ ሰነድ ምሻጥ የለን።\n3. ብዛዕባ ምትሕልላፍ ገንዘብ ወይ ምንዛሪ ምልጣፍ የለን።\n4. ፖለቲካ፣ ናይ ብሄር ጸርፊ፣ ናይ ሃይማኖት ክትዕ የለን።\n5. ሰሌዳ ኣቕሑ፡ ጥረ ገንዘብ፣ ወርቂ፣ ብዘይ ትእዛዝ መድሃኒት ወይ ዝተኸልከሉ ኣቕሑ የለን።\n6. ንሓደ ሰብ ሓደ ኣካውንት።\n\nእዚ መድረኽ ወኪል ስራሕ፣ ትካል ጽዕነት ወይ ኣማኻሪ ሕጊ ኣይኮነን። ንምቕጻል ተሰማምዑ።",
        'en' => "The rules of this community:\n\n1. No job that asks you for money. Report it.\n2. No selling of visas, permits or documents.\n3. No money transfer or exchange-rate posts.\n4. No politics, no ethnic insults, no religious argument.\n5. Goods board: no cash, gold, medicine without prescription, or banned items.\n6. One account per person.\n\nThis platform is not a recruitment agency, a cargo company or a legal adviser. Agree to continue.",
    ],
    'btn_agree' => [
        'am' => '✅ እስማማለሁ',
        'ti' => '✅ እሰማማዕ እየ',
        'en' => '✅ I agree',
    ],
    'done' => [
        'am' => "ገብተዋል፣ {name}። 🎉\n\nቻናሉንና ቡድኖቹን ይቀላቀሉ፡\n{links}\n\n/profile መገለጫዎን ለማየት፣ /language ቋንቋ ለመቀየር፣ /help ለእርዳታ ይላኩ።",
        'ti' => "ኣቲኹም ኣለኹም፣ {name}። 🎉\n\nቻነልን ጉጅለታትን ተጸንበሩ፡\n{links}\n\n/profile መግለጺኹም ንምርኣይ፣ /language ቋንቋ ንምቕያር፣ /help ንሓገዝ ስደዱ።",
        'en' => "You are in, {name}. 🎉\n\nJoin the channel and the groups:\n{links}\n\nSend /profile to see your profile, /language to change language, /help for help.",
    ],
    'links_pending' => [
        'am' => 'የቻናልና የቡድን ሊንኮች ገና አልተዘጋጁም። አስተዳዳሪ ይልክልዎታል።',
        'ti' => 'ናይ ቻነልን ጉጅለን ሊንክታት ገና ኣይተዳለዉን። ኣመሓዳሪ ክሰደልኩም እዩ።',
        'en' => 'The channel and group links are not set yet. An admin will send them.',
    ],
    'link_channel' => [
        'am' => '📣 ቻናል፡ {url}',
        'ti' => '📣 ቻነል፡ {url}',
        'en' => '📣 Channel: {url}',
    ],
    'link_questions' => [
        'am' => '❓ የጥያቄ ቡድን፡ {url}',
        'ti' => '❓ ጉጅለ ሕቶታት፡ {url}',
        'en' => '❓ Questions group: {url}',
    ],
    'link_goods' => [
        'am' => '📦 የዕቃ ሰሌዳ፡ {url}',
        'ti' => '📦 ሰሌዳ ኣቕሑ፡ {url}',
        'en' => '📦 Goods board: {url}',
    ],
    'returning' => [
        'am' => "እንኳን ደህና ተመለሱ፣ {name}። ከ{since} ጀምሮ የተረጋገጠ አባል ነዎት።\n\n/profile፣ /language፣ /help",
        'ti' => "እንቋዕ ብደሓን ተመለስኩም፣ {name}። ካብ {since} ጀሚርኩም ዝተረጋገጸ ኣባል ኢኹም።\n\n/profile፣ /language፣ /help",
        'en' => "Welcome back, {name}. You have been a verified member since {since}.\n\n/profile, /language, /help",
    ],
    'resume' => [
        'am' => 'መለያዎን ማዘጋጀት እንጨርስ።',
        'ti' => 'ኣካውንትኩም ምድላው ንወድእ።',
        'en' => 'Let us finish setting up your account.',
    ],
    'profile' => [
        'am' => "👤 {name}\n✅ ከ{since} የተረጋገጠ\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ የረዳት ነጥብ፡ {points}",
        'ti' => "👤 {name}\n✅ ካብ {since} ዝተረጋገጸ\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ ነጥቢ ሓጋዚ፡ {points}",
        'en' => "👤 {name}\n✅ Verified since {since}\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ Helper points: {points}",
    ],
    'not_member' => [
        'am' => 'ገና አባል አይደሉም። ለመቀላቀል /start ይላኩ።',
        'ti' => 'ገና ኣባል ኣይኮንኩምን። ንምጽንባር /start ስደዱ።',
        'en' => 'You are not a member yet. Send /start to join.',
    ],
    'help' => [
        'am' => "ትዕዛዞች፡\n/start — መቀላቀል\n/profile — መገለጫ\n/language — ቋንቋ መቀየር\n/leave — ማህበረሰቡን መልቀቅ\n\nሥራዎች፣ ጥያቄዎችና የዕቃ ሰሌዳ በቅርቡ ይመጣሉ።",
        'ti' => "ትእዛዛት፡\n/start — ምጽንባር\n/profile — መግለጺ\n/language — ቋንቋ ምቕያር\n/leave — ማሕበረሰብ ምግዳፍ\n\nስራሕ፣ ሕቶታትን ሰሌዳ ኣቕሑን ኣብ ቀረባ ክመጹ እዮም።",
        'en' => "Commands:\n/start — join, or welcome back\n/profile — your profile\n/language — change language\n/leave — leave the community\n\nJobs, questions and the goods board are coming soon.",
    ],
    'unknown' => [
        'am' => 'አልገባኝም። /help ይላኩ።',
        'ti' => 'ኣይተረድኣንን። /help ስደዱ።',
        'en' => 'I did not understand that. Send /help.',
    ],
    'leave_confirm' => [
        'am' => 'ማህበረሰቡን መልቀቅ ይፈልጋሉ? መገለጫዎ ይደበቃል፣ ከቡድኖቹ ይወጣሉ። ለማረጋገጥ ይጫኑ።',
        'ti' => 'ማሕበረሰብ ክትገድፉ ትደልዩ? መግለጺኹም ይሕባእ፣ ካብ ጉጅለታት ትወጹ። ንምርግጋጽ ጠውቑ።',
        'en' => 'Leave the community? Your profile is hidden and you are removed from the groups. Tap to confirm.',
    ],
    'btn_confirm_leave' => [
        'am' => 'አዎ፣ ልቀቅ',
        'ti' => 'እወ፣ ግደፍ',
        'en' => 'Yes, leave',
    ],
    'btn_cancel' => [
        'am' => '❌ ይቅር',
        'ti' => '❌ ኣይፋል',
        'en' => '❌ Cancel',
    ],
    'cancelled' => [
        'am' => 'ተሰርዟል።',
        'ti' => 'ተሰሪዙ።',
        'en' => 'Cancelled.',
    ],
    'left' => [
        'am' => 'ለቀዋል። ለመመለስ በፈለጉ ጊዜ /start ይላኩ።',
        'ti' => 'ገዲፍኩም ኣለኹም። ክትምለሱ እንተደሊኹም /start ስደዱ።',
        'en' => 'You have left. Send /start whenever you want to come back.',
    ],
    'banned' => [
        'am' => 'ይህ መለያ ከማህበረሰቡ ታግዷል። ይግባኝ፡ {support}',
        'ti' => 'እዚ ኣካውንት ካብ ማሕበረሰብ ተኣጊዱ እዩ። ይግባይ፡ {support}',
        'en' => 'This account is banned from the community. Appeals: {support}',
    ],
];
