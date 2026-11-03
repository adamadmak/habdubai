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
        'am' => "👤 {name}\n✅ ከ{since} የተረጋገጠ\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ የረዳት ነጥብ፡ {points}{rank}{goods}",
        'ti' => "👤 {name}\n✅ ካብ {since} ዝተረጋገጸ\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ ነጥቢ ሓጋዚ፡ {points}{rank}{goods}",
        'en' => "👤 {name}\n✅ Verified since {since}\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ Helper points: {points}{rank}{goods}",
    ],
    'not_member' => [
        'am' => 'ገና አባል አይደሉም። ለመቀላቀል /start ይላኩ።',
        'ti' => 'ገና ኣባል ኣይኮንኩምን። ንምጽንባር /start ስደዱ።',
        'en' => 'You are not a member yet. Send /start to join.',
    ],
    'help' => [
        'am' => "ማንኛውንም ጥያቄ እዚህ ይጻፉ፤ ረዳቱ ከተረጋገጡ ምንጮች ይመልሳል።\n\nትዕዛዞች፡\n/ask — ማህበረሰቡን መጠየቅ\n/myquestions — የእርስዎ ጥያቄዎች\n/faq — ተደጋግመው የሚጠየቁ\n/jobs — የሥራ ማንቂያዎችና የቅርብ ሥራዎች\n/saved — ያስቀመጧቸው ሥራዎች\n/directory — የሐበሻ ንግድ ማውጫ\n/addbusiness — ንግድ መጠቆም\n/goods — የዕቃ ሰሌዳ (ኢማራት ⇄ ኢትዮጵያ)\n/travel — እጓዛለሁ፣ ቦታ አለኝ\n/send — የሚላክ ዕቃ አለኝ\n/mygoods — የዕቃ ልጥፎቼና ስምምነቶቼ\n/safe — እርዳታና ደህንነት\n/topics — የሚደርስዎ ማስታወቂያ\n/profile — መገለጫ\n/language — ቋንቋ መቀየር\n/leave — ማህበረሰቡን መልቀቅ",
        'ti' => "ዝኾነ ሕቶ ኣብዚ ጸሓፉ፤ ሓጋዚ ካብ ዝተረጋገጹ ምንጭታት ይምልስ።\n\nትእዛዛት፡\n/ask — ንማሕበረሰብ ምሕታት\n/myquestions — ሕቶታትኩም\n/faq — ብተደጋጋሚ ዝሕተቱ\n/jobs — መጠንቀቕታ ስራሕን ናይ ቀረባ ስራሓትን\n/saved — ዝዓቀብኩምዎም ስራሓት\n/directory — መምርሒ ንግዲ ሓበሻ\n/addbusiness — ንግዲ ምምልካት\n/goods — ሰሌዳ ኣቕሑ (ኢማራት ⇄ ኢትዮጵያ)\n/travel — ክጉዓዝ እየ፣ ቦታ ኣለኒ\n/send — ዝስደድ ኣቕሓ ኣለኒ\n/mygoods — ልጥፋት ኣቕሑን ስምምዓትን\n/safe — ሓገዝን ድሕነትን\n/topics — ዝበጽሓኩም መወዓውዒ\n/profile — መግለጺ\n/language — ቋንቋ ምቕያር\n/leave — ማሕበረሰብ ምግዳፍ",
        'en' => "Write any question here and the assistant answers from checked sources.\n\nCommands:\n/ask — ask the community\n/myquestions — your questions\n/faq — frequently asked questions\n/jobs — job alerts and the latest jobs\n/saved — jobs you saved\n/directory — Habesha business directory\n/addbusiness — suggest a business\n/goods — goods board between the UAE and Ethiopia\n/travel — I am travelling and have space\n/send — I need something carried\n/mygoods — your goods posts and deals\n/safe — help and safety\n/topics — announcements you receive\n/profile — your profile\n/language — change language\n/leave — leave the community",
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

    // ---- jobs: channel card ---------------------------------------------
    'job_salary_none' => [
        'am' => 'ደሞዝ አልተገለጸም',
        'ti' => 'ደሞዝ ኣይተገልጸን',
        'en' => 'Salary not stated',
    ],
    'job_accommodation_yes' => [
        'am' => '🏠 መኖሪያ ይሰጣል',
        'ti' => '🏠 መንበሪ ይወሃብ',
        'en' => '🏠 Accommodation provided',
    ],
    'job_visa_yes' => [
        'am' => '🛂 ቪዛ ይሰጣል',
        'ti' => '🛂 ቪዛ ይወሃብ',
        'en' => '🛂 Visa provided',
    ],
    'job_warning' => [
        'am' => '⚠️ ይጠንቀቁ፡ {reason}። ሥራ ለማግኘት ገንዘብ አይክፈሉ።',
        'ti' => '⚠️ ተጠንቀቑ፡ {reason}። ስራሕ ንምርካብ ገንዘብ ኣይትኽፈሉ።',
        'en' => '⚠️ Be careful: {reason}. Never pay to get a job.',
    ],
    'job_source' => [
        'am' => 'ምንጭ፡ {source}',
        'ti' => 'ምንጪ፡ {source}',
        'en' => 'Source: {source}',
    ],
    'job_closed_tag' => [
        'am' => '🔴 ተዘግቷል',
        'ti' => '🔴 ተዓጽዩ',
        'en' => '🔴 Closed',
    ],
    'btn_job_open' => [
        'am' => '🔗 ማስታወቂያውን ክፈት',
        'ti' => '🔗 መወዓውዒ ክፈት',
        'en' => '🔗 Open the listing',
    ],
    'btn_job_save' => [
        'am' => '💾 አስቀምጥ',
        'ti' => '💾 ዓቅብ',
        'en' => '💾 Save',
    ],
    'btn_job_report' => [
        'am' => '🚩 ሐሰት ነው',
        'ti' => '🚩 ሓሶት እዩ',
        'en' => '🚩 Report fake',
    ],

    // ---- jobs: buttons pressed ------------------------------------------
    'job_saved' => [
        'am' => 'ተቀምጧል። በ /saved ያገኙታል።',
        'ti' => 'ተዓቂቡ። ብ /saved ክትረኽብዎ ኢኹም።',
        'en' => 'Saved. Find it with /saved.',
    ],
    'job_unsaved' => [
        'am' => 'ከተቀመጡት ተወግዷል።',
        'ti' => 'ካብ ዝተዓቀቡ ተኣልዩ።',
        'en' => 'Removed from your saved jobs.',
    ],
    'job_reported' => [
        'am' => 'አመሰግናለሁ፤ ሪፖርትዎ ደርሷል። አወያዮች ያዩታል።',
        'ti' => 'የቐንየለይ፤ ሪፖርትኹም በጺሑ። ኣወያየቲ ክርእይዎ እዮም።',
        'en' => 'Thank you, your report is in. Moderators will look at it.',
    ],
    'job_already_reported' => [
        'am' => 'ይህን ሥራ ቀድመው ሪፖርት አድርገዋል።',
        'ti' => 'ነዚ ስራሕ ቀደም ሪፖርት ገይርኩሞ ኢኹም።',
        'en' => 'You already reported this job.',
    ],
    'job_gone' => [
        'am' => 'ይህ ሥራ ከእንግዲህ የለም።',
        'ti' => 'እዚ ስራሕ ደጊም የለን።',
        'en' => 'This job is no longer available.',
    ],
    'job_join_first' => [
        'am' => 'ለማስቀመጥ ወይም ሪፖርት ለማድረግ መጀመሪያ ቦቱን ይቀላቀሉ።',
        'ti' => 'ንምዕቃብ ወይ ሪፖርት ንምግባር ቅድሚ ኩሉ ነቲ ቦት ተጸንበሩ።',
        'en' => 'Join through the bot first to save or report jobs.',
    ],
    'job_forward_added' => [
        'am' => 'ወደ ሥራ ዝርዝሩ ተጨምሯል።',
        'ti' => 'ናብ ዝርዝር ስራሕ ተወሲኹ።',
        'en' => 'Added to the job queue.',
    ],
    'job_forward_known' => [
        'am' => 'ይህ ማስታወቂያ ቀድሞ አለ።',
        'ti' => 'እዚ መወዓውዒ ድሮ ኣሎ።',
        'en' => 'We already have this listing.',
    ],
    'job_forward_too_short' => [
        'am' => 'ይህ እንደ ሥራ ማስታወቂያ በጣም አጭር ነው።',
        'ti' => 'እዚ ከም መወዓውዒ ስራሕ ኣዝዩ ሓጺር እዩ።',
        'en' => 'That is too short to be a job listing.',
    ],

    // ---- jobs: /jobs filters --------------------------------------------
    'jobs_menu' => [
        'am' => "💼 <b>የሥራ ማንቂያዎች</b>\n\nዘርፍ፡ {categories}\nቦታ፡ {emirates}\nዝቅተኛ ደሞዝ፡ {salary}\nቁልፍ ቃል፡ {keyword}\nማንቂያዎች፡ {alerts}\n\nየሚስማማ አዲስ ሥራ ሲመጣ መልዕክት እልክልዎታለሁ፤ በ15 ደቂቃ ከአንድ መልዕክት አይበልጥም።",
        'ti' => "💼 <b>መጠንቀቕታ ስራሕ</b>\n\nዓውዲ፡ {categories}\nቦታ፡ {emirates}\nዝተሓተ ደሞዝ፡ {salary}\nመፍትሕ ቃል፡ {keyword}\nመጠንቀቕታ፡ {alerts}\n\nዝሰማማዕ ሓድሽ ስራሕ ምስ መጸ መልእኽቲ ክሰደልኩም እየ፤ ኣብ 15 ደቒቕ ካብ ሓደ መልእኽቲ ኣይበዝሕን።",
        'en' => "💼 <b>Job alerts</b>\n\nWork: {categories}\nWhere: {emirates}\nMinimum salary: {salary}\nKeyword: {keyword}\nAlerts: {alerts}\n\nI message you when a new job matches, at most one message every 15 minutes.",
    ],
    'jobs_all' => [
        'am' => 'ሁሉም',
        'ti' => 'ኩሉ',
        'en' => 'All',
    ],
    'jobs_any' => [
        'am' => 'ማንኛውም',
        'ti' => 'ዝኾነ',
        'en' => 'Any',
    ],
    'jobs_none' => [
        'am' => 'የለም',
        'ti' => 'የለን',
        'en' => 'None',
    ],
    'jobs_on' => [
        'am' => 'በርቷል',
        'ti' => 'ይሰርሕ',
        'en' => 'On',
    ],
    'jobs_off' => [
        'am' => 'ጠፍቷል',
        'ti' => 'ጠፊኡ',
        'en' => 'Off',
    ],
    'btn_jobs_categories' => [
        'am' => '🛠 ዘርፍ',
        'ti' => '🛠 ዓውዲ',
        'en' => '🛠 Work',
    ],
    'btn_jobs_emirates' => [
        'am' => '📍 ቦታ',
        'ti' => '📍 ቦታ',
        'en' => '📍 Where',
    ],
    'btn_jobs_salary' => [
        'am' => '💰 ደሞዝ',
        'ti' => '💰 ደሞዝ',
        'en' => '💰 Salary',
    ],
    'btn_jobs_keyword' => [
        'am' => '🔎 ቁልፍ ቃል',
        'ti' => '🔎 መፍትሕ ቃል',
        'en' => '🔎 Keyword',
    ],
    'btn_jobs_alerts_on' => [
        'am' => '🔔 ማንቂያ አብራ',
        'ti' => '🔔 መጠንቀቕታ ኣብርህ',
        'en' => '🔔 Turn alerts on',
    ],
    'btn_jobs_alerts_off' => [
        'am' => '🔕 ማንቂያ አጥፋ',
        'ti' => '🔕 መጠንቀቕታ ኣጥፍእ',
        'en' => '🔕 Turn alerts off',
    ],
    'btn_jobs_latest' => [
        'am' => '📋 የቅርብ ሥራዎች',
        'ti' => '📋 ናይ ቀረባ ስራሓት',
        'en' => '📋 Latest jobs',
    ],
    'btn_jobs_saved' => [
        'am' => '💾 የተቀመጡ',
        'ti' => '💾 ዝተዓቀቡ',
        'en' => '💾 Saved',
    ],
    'btn_jobs_back' => [
        'am' => '⬅️ ተመለስ',
        'ti' => '⬅️ ተመለስ',
        'en' => '⬅️ Back',
    ],
    'btn_jobs_clear_keyword' => [
        'am' => '✖️ ቁልፍ ቃሉን አጥፋ',
        'ti' => '✖️ መፍትሕ ቃል ኣጥፍእ',
        'en' => '✖️ Clear keyword',
    ],
    'jobs_pick_categories' => [
        'am' => 'የሚፈልጉትን ዘርፍ ይምረጡ። ምንም ካልመረጡ ሁሉም ይላካል።',
        'ti' => 'ዝደለኹምዎ ዓውዲ ምረጹ። ዋላ ሓደ እንተዘይመሪጽኩም ኩሉ ይለኣኽ።',
        'en' => 'Pick the work you want. With none picked, you get all of it.',
    ],
    'jobs_pick_emirates' => [
        'am' => 'ቦታ ይምረጡ። ምንም ካልመረጡ ሁሉም ቦታ ይላካል።',
        'ti' => 'ቦታ ምረጹ። ዋላ ሓደ እንተዘይመሪጽኩም ኩሉ ቦታ ይለኣኽ።',
        'en' => 'Pick where. With none picked, you get every place.',
    ],
    'jobs_pick_salary' => [
        'am' => 'ዝቅተኛ ወርሃዊ ደሞዝ ይምረጡ። ደሞዝ ያልገለጹ ሥራዎችም ይላካሉ።',
        'ti' => 'ዝተሓተ ወርሓዊ ደሞዝ ምረጹ። ደሞዝ ዘይገለጹ ስራሓት እውን ይለኣኹ።',
        'en' => 'Pick a minimum monthly salary. Jobs that do not state a salary still come through.',
    ],
    'jobs_ask_keyword' => [
        'am' => 'በሥራው ርዕስ ወይም መግለጫ ውስጥ መኖር ያለበትን አንድ ቃል ይላኩ፣ ለምሳሌ «Deira» ወይም «forklift»።',
        'ti' => 'ኣብ ኣርእስቲ ወይ መግለጺ ስራሕ ክህሉ ዘለዎ ሓደ ቃል ስደዱ፣ ንኣብነት «Deira» ወይ «forklift»።',
        'en' => 'Send one word that must appear in the job title or description, for example "Deira" or "forklift".',
    ],
    'jobs_keyword_invalid' => [
        'am' => 'ቁልፍ ቃል ከ2 እስከ 40 ፊደላት ነው።',
        'ti' => 'መፍትሕ ቃል ካብ 2 ክሳብ 40 ፊደላት እዩ።',
        'en' => 'A keyword is 2 to 40 letters.',
    ],
    'jobs_none_found' => [
        'am' => 'አሁን የሚስማማ ክፍት ሥራ የለም። አዲስ ሲመጣ እነግርዎታለሁ።',
        'ti' => 'ሕጂ ዝሰማማዕ ክፉት ስራሕ የለን። ሓድሽ ምስ መጸ ክሕብረኩም እየ።',
        'en' => 'No open job matches right now. I will tell you when one comes in.',
    ],
    'jobs_saved_none' => [
        'am' => 'እስካሁን ያስቀመጡት ሥራ የለም። በቻናሉ ካርድ ላይ «አስቀምጥ» ይጫኑ።',
        'ti' => 'ክሳብ ሕጂ ዝዓቀብኩምዎ ስራሕ የለን። ኣብ ካርድ ቻነል «ዓቅብ» ጠውቑ።',
        'en' => 'You have not saved any jobs yet. Tap Save on a card in the channel.',
    ],
    'jobs_list_header' => [
        'am' => '💼 <b>{title}</b>',
        'ti' => '💼 <b>{title}</b>',
        'en' => '💼 <b>{title}</b>',
    ],
    'jobs_latest_title' => [
        'am' => 'የሚስማሙ የቅርብ ሥራዎች',
        'ti' => 'ዝሰማምዑ ናይ ቀረባ ስራሓት',
        'en' => 'Latest matching jobs',
    ],
    'jobs_saved_title' => [
        'am' => 'ያስቀመጧቸው ሥራዎች',
        'ti' => 'ዝዓቀብኩምዎም ስራሓት',
        'en' => 'Your saved jobs',
    ],
    'jobs_alert_title' => [
        'am' => 'አዲስ ሥራ ለእርስዎ',
        'ti' => 'ሓድሽ ስራሕ ንዓኹም',
        'en' => 'New jobs for you',
    ],
    'jobs_alert_footer' => [
        'am' => 'ማንቂያዎችን ለመቀየር /jobs ይላኩ።',
        'ti' => 'መጠንቀቕታ ንምቕያር /jobs ስደዱ።',
        'en' => 'Send /jobs to change your alerts.',
    ],
    'jobs_closed' => [
        'am' => '(ተዘግቷል)',
        'ti' => '(ተዓጽዩ)',
        'en' => '(closed)',
    ],

    // ---- business directory: browsing -----------------------------------
    'dir_pick_city' => [
        'am' => "🏪 <b>የሐበሻ ንግድ ማውጫ</b>\n\nከተማ ይምረጡ።",
        'ti' => "🏪 <b>መምርሒ ንግዲ ሓበሻ</b>\n\nከተማ ምረጹ።",
        'en' => "🏪 <b>Habesha business directory</b>\n\nPick a city.",
    ],
    'dir_pick_category' => [
        'am' => "🏪 <b>{city}</b>\n\nምን ይፈልጋሉ?",
        'ti' => "🏪 <b>{city}</b>\n\nእንታይ ትደልዩ?",
        'en' => "🏪 <b>{city}</b>\n\nWhat are you looking for?",
    ],
    'dir_city_empty' => [
        'am' => "🏪 <b>{city}</b>\n\nበዚህ ከተማ እስካሁን የተመዘገበ ንግድ የለም። የሚያውቁትን ንግድ ይጠቁሙ።",
        'ti' => "🏪 <b>{city}</b>\n\nኣብዚ ከተማ ክሳብ ሕጂ ዝተመዝገበ ንግዲ የለን። እትፈልጥዎ ንግዲ ኣመልክቱ።",
        'en' => "🏪 <b>{city}</b>\n\nNo businesses are listed in this city yet. Suggest one you know.",
    ],
    'dir_list_title' => [
        'am' => '{category} · {city}',
        'ti' => '{category} · {city}',
        'en' => '{category} · {city}',
    ],
    'dir_list_footer' => [
        'am' => 'ለካርታና ለዝርዝር ቁጥሩን ይጫኑ።',
        'ti' => 'ንካርታን ዝርዝርን ቁጽሪ ጠውቑ።',
        'en' => 'Tap a number for the map and details.',
    ],
    'dir_verified' => [
        'am' => '✅ የተረጋገጠ',
        'ti' => '✅ ዝተረጋገጸ',
        'en' => '✅ Verified',
    ],
    'dir_search_ask' => [
        'am' => 'የንግዱን ስም ወይም የስሙን ክፍል ይላኩ።',
        'ti' => 'ስም ንግዲ ወይ ክፋል ስሙ ስደዱ።',
        'en' => 'Send the name of the business, or part of it.',
    ],
    'dir_search_none' => [
        'am' => '«{query}» የሚል ንግድ አልተገኘም።',
        'ti' => '«{query}» ዝብል ንግዲ ኣይተረኽበን።',
        'en' => 'No business matches "{query}".',
    ],
    'dir_search_title' => [
        'am' => 'የፍለጋ ውጤት፡ «{query}»',
        'ti' => 'ውጽኢት ምድላይ፡ «{query}»',
        'en' => 'Results for "{query}"',
    ],
    'dir_search_short' => [
        'am' => 'ቢያንስ 2 ፊደላት ይላኩ።',
        'ti' => 'እንተወሓደ 2 ፊደላት ስደዱ።',
        'en' => 'Send at least 2 letters.',
    ],
    'btn_dir_search' => [
        'am' => '🔍 በስም ፈልግ',
        'ti' => '🔍 ብስም ድለ',
        'en' => '🔍 Search by name',
    ],
    'btn_dir_add' => [
        'am' => '➕ ንግድ ጠቁም',
        'ti' => '➕ ንግዲ ኣመልክት',
        'en' => '➕ Suggest a business',
    ],
    'btn_dir_cities' => [
        'am' => '🏙 ከተሞች',
        'ti' => '🏙 ከተማታት',
        'en' => '🏙 Cities',
    ],
    'btn_dir_back' => [
        'am' => '⬅️ ተመለስ',
        'ti' => '⬅️ ተመለስ',
        'en' => '⬅️ Back',
    ],
    'btn_dir_prev' => [
        'am' => '◀️ ቀዳሚ',
        'ti' => '◀️ ዝሓለፈ',
        'en' => '◀️ Previous',
    ],
    'btn_dir_next' => [
        'am' => 'ቀጣይ ▶️',
        'ti' => 'ዝቕጽል ▶️',
        'en' => 'Next ▶️',
    ],
    'btn_dir_map' => [
        'am' => '🗺 ካርታ',
        'ti' => '🗺 ካርታ',
        'en' => '🗺 Map',
    ],
    'btn_dir_report' => [
        'am' => '🚩 መረጃው ስህተት ነው',
        'ti' => '🚩 ሓበሬታ ጌጋ እዩ',
        'en' => '🚩 Wrong information',
    ],
    'dir_reported' => [
        'am' => 'አመሰግናለሁ፤ አወያዮች መረጃውን ያያሉ።',
        'ti' => 'የቐንየለይ፤ ኣወያየቲ ነቲ ሓበሬታ ክርእይዎ እዮም።',
        'en' => 'Thank you. Moderators will check the details.',
    ],
    'dir_already_reported' => [
        'am' => 'ይህን ንግድ ቀድመው ሪፖርት አድርገዋል።',
        'ti' => 'ነዚ ንግዲ ቀደም ሪፖርት ገይርኩሞ ኢኹም።',
        'en' => 'You already reported this business.',
    ],
    'dir_gone' => [
        'am' => 'ይህ ንግድ ከማውጫው ተወግዷል።',
        'ti' => 'እዚ ንግዲ ካብ መምርሒ ተኣልዩ።',
        'en' => 'This business is no longer listed.',
    ],

    // ---- business directory: suggesting a business ----------------------
    'biz_ask_name' => [
        'am' => "➕ <b>ንግድ መጠቆሚያ</b>\n\nየንግዱ ስም ማን ነው?",
        'ti' => "➕ <b>ምምልካት ንግዲ</b>\n\nስም እቲ ንግዲ እንታይ እዩ?",
        'en' => "➕ <b>Suggest a business</b>\n\nWhat is the business called?",
    ],
    'biz_name_invalid' => [
        'am' => 'ስም ከ2 እስከ 80 ፊደላት ነው፤ ሊንክ አይፈቀድም።',
        'ti' => 'ስም ካብ 2 ክሳብ 80 ፊደላት እዩ፤ ሊንክ ኣይፍቀድን።',
        'en' => 'A name is 2 to 80 letters, with no links.',
    ],
    'biz_ask_city' => [
        'am' => 'በየትኛው ከተማ ነው?',
        'ti' => 'ኣብ ኣየናይ ከተማ እዩ?',
        'en' => 'Which city is it in?',
    ],
    'biz_ask_category' => [
        'am' => 'ምን ዓይነት ንግድ ነው?',
        'ti' => 'እንታይ ዓይነት ንግዲ እዩ?',
        'en' => 'What kind of business is it?',
    ],
    'biz_ask_area' => [
        'am' => 'የት ነው ያለው? ሰፈሩን ወይም መንገዱን ይጻፉ፣ ለምሳሌ «ዴይራ፣ አል ሪጋ ሜትሮ አጠገብ»።',
        'ti' => 'ኣበይ እዩ ዘሎ? ከባቢኡ ወይ ጽርግያኡ ጸሓፉ፣ ንኣብነት «ዴይራ፣ ጥቓ ሜትሮ ኣል ሪጋ»።',
        'en' => 'Where is it? Type the area or street, for example "Deira, near Al Rigga metro".',
    ],
    'biz_area_invalid' => [
        'am' => 'ቦታው ከ3 እስከ 150 ፊደላት ነው።',
        'ti' => 'እቲ ቦታ ካብ 3 ክሳብ 150 ፊደላት እዩ።',
        'en' => 'The location is 3 to 150 letters.',
    ],
    'biz_ask_pin' => [
        'am' => 'የካርታ ምልክት ይላኩ (📎 → Location፣ ቦታውን ይምረጡ) ወይም የGoogle Maps ሊንክ ይለጥፉ። ከሌለ «ዝለል» ይጫኑ።',
        'ti' => 'ምልክት ካርታ ስደዱ (📎 → Location፣ ቦታ ምረጹ) ወይ ሊንክ Google Maps ለጥፉ። እንተዘየለ «ሕለፍ» ጠውቑ።',
        'en' => 'Send the map pin (📎 → Location, then move the pin to the place) or paste a Google Maps link. If you do not have one, tap Skip.',
    ],
    'biz_pin_invalid' => [
        'am' => 'ይህ የካርታ ምልክት ወይም የGoogle Maps ሊንክ አይደለም። እንደገና ይሞክሩ ወይም «ዝለል» ይጫኑ።',
        'ti' => 'እዚ ምልክት ካርታ ወይ ሊንክ Google Maps ኣይኮነን። እንደገና ፈትኑ ወይ «ሕለፍ» ጠውቑ።',
        'en' => 'That is not a map pin or a Google Maps link. Try again, or tap Skip.',
    ],
    'biz_ask_phone' => [
        'am' => 'የንግዱ ስልክ ወይም ዋትስአፕ ቁጥር? ከሌለ «ዝለል» ይጫኑ።',
        'ti' => 'ቁጽሪ ስልኪ ወይ ዋትስኣፕ ናይቲ ንግዲ? እንተዘየለ «ሕለፍ» ጠውቑ።',
        'en' => 'The business phone or WhatsApp number? If you do not know it, tap Skip.',
    ],
    'biz_phone_invalid' => [
        'am' => 'ይህ የስልክ ቁጥር አይመስልም። እንደገና ይጻፉ ወይም «ዝለል» ይጫኑ።',
        'ti' => 'እዚ ቁጽሪ ስልኪ ኣይመስልን። እንደገና ጸሓፉ ወይ «ሕለፍ» ጠውቑ።',
        'en' => 'That does not look like a phone number. Type it again, or tap Skip.',
    ],
    'biz_ask_description' => [
        'am' => 'ስለ ንግዱ አንድ መስመር (ለምሳሌ «የሐበሻ ምግብ፣ ቅዳሜ ቡና ሥነ ሥርዓት»)። ወይም «ዝለል»።',
        'ti' => 'ብዛዕባ እቲ ንግዲ ሓደ መስመር (ንኣብነት «መግቢ ሓበሻ፣ ቀዳም ጀበና ቡን»)። ወይ «ሕለፍ»።',
        'en' => 'One line about the business (for example "Habesha food, coffee ceremony on Saturdays"). Or Skip.',
    ],
    'biz_description_invalid' => [
        'am' => 'መግለጫው ከ250 ፊደላት መብለጥ የለበትም፤ ሊንክ አይፈቀድም።',
        'ti' => 'መግለጺ ካብ 250 ፊደላት ክበዝሕ የብሉን፤ ሊንክ ኣይፍቀድን።',
        'en' => 'The description is at most 250 letters, with no links.',
    ],
    'biz_confirm' => [
        'am' => "እባክዎ ያረጋግጡ፡\n\n{card}\n\nትክክል ከሆነ «ላክ» ይጫኑ።",
        'ti' => "በጃኹም ኣረጋግጹ፡\n\n{card}\n\nቅኑዕ እንተኾይኑ «ስደድ» ጠውቑ።",
        'en' => "Please check:\n\n{card}\n\nIf it is right, tap Send.",
    ],
    'biz_submitted' => [
        'am' => 'አመሰግናለሁ! አወያይ ካረጋገጠው በኋላ በማውጫው ይታያል።',
        'ti' => 'የቐንየለይ! ኣወያዪ ምስ ኣረጋገጾ ኣብ መምርሒ ክርአ እዩ።',
        'en' => 'Thank you! It appears in the directory once a moderator has checked it.',
    ],
    'biz_too_many' => [
        'am' => 'ቀድመው የጠቆሟቸው {count} ንግዶች እየተረጋገጡ ነው። እስኪያልቁ ይጠብቁ።',
        'ti' => 'ቀደም ዘመልከትኩምዎም {count} ንግድታት ይረጋገጹ ኣለዉ። ክሳብ ዝውድኡ ተጸበዩ።',
        'en' => 'You already have {count} suggestions waiting for a moderator. Please wait until they are checked.',
    ],
    'biz_duplicate' => [
        'am' => 'ይህ ንግድ በዚህ ከተማ ቀድሞ አለ ወይም እየተረጋገጠ ነው።',
        'ti' => 'እዚ ንግዲ ኣብዚ ከተማ ድሮ ኣሎ ወይ ይረጋገጽ ኣሎ።',
        'en' => 'This business is already listed in this city, or waiting to be checked.',
    ],
    'btn_skip' => [
        'am' => '⏭ ዝለል',
        'ti' => '⏭ ሕለፍ',
        'en' => '⏭ Skip',
    ],
    'btn_send' => [
        'am' => '✅ ላክ',
        'ti' => '✅ ስደድ',
        'en' => '✅ Send',
    ],

    // ---- questions --------------------------------------------------------
    'ask_question' => [
        'am' => "❓ <b>ማህበረሰቡን ይጠይቁ</b>\n\nጥያቄዎን በማንኛውም ቋንቋ ይጻፉ። ጠቃሚ ዝርዝሮችን ያካትቱ (ኢማራት፣ የቪዛ ዓይነት፣ ምን ያህል ጊዜ)።",
        'ti' => "❓ <b>ንማሕበረሰብ ሕተቱ</b>\n\nሕቶኹም ብዝኾነ ቋንቋ ጸሓፉ። ኣገደስቲ ዝርዝራት ኣእትዉ (ኢማራት፣ ዓይነት ቪዛ፣ ክንደይ ግዜ)።",
        'en' => "❓ <b>Ask the community</b>\n\nWrite your question in any language. Include the details that matter (emirate, visa type, how long).",
    ],
    'ask_too_short' => [
        'am' => 'ጥያቄው ከ15 እስከ 2000 ፊደላት ነው።',
        'ti' => 'ሕቶ ካብ 15 ክሳብ 2000 ፊደላት እዩ።',
        'en' => 'A question is 15 to 2000 letters.',
    ],
    'ask_pick_category' => [
        'am' => 'ጥያቄው ስለ ምንድን ነው?',
        'ti' => 'ሕቶ ብዛዕባ እንታይ እዩ?',
        'en' => 'What is the question about?',
    ],
    'ask_anonymous' => [
        'am' => 'በስምዎ ይለጠፍ ወይስ ስም ሳይጠቀስ?',
        'ti' => 'ብስምኩም ይለጠፍ ወይስ ስም ከይተጠቕሰ?',
        'en' => 'Post it with your name, or anonymously?',
    ],
    'btn_ask_named' => [
        'am' => '👤 በስሜ',
        'ti' => '👤 ብስመይ',
        'en' => '👤 With my name',
    ],
    'btn_ask_anonymous' => [
        'am' => '🙈 ስም ሳይጠቀስ',
        'ti' => '🙈 ስም ከይተጠቕሰ',
        'en' => '🙈 Anonymously',
    ],
    'ask_posted' => [
        'am' => "ጥያቄዎ ተለጥፏል። መልስ ሲመጣ መልዕክት እልክልዎታለሁ።\n\nመልሱ ሲፈታልዎ ከጥያቄዎ ስር «✅ ተፈቷል» ይጫኑ ወይም /myquestions ይላኩ።",
        'ti' => "ሕቶኹም ተለጢፉ። መልሲ ምስ መጸ መልእኽቲ ክሰደልኩም እየ።\n\nመልሲ ምስ ፈትሓልኩም ኣብ ትሕቲ ሕቶኹም «✅ ተፈቲሑ» ጠውቑ ወይ /myquestions ስደዱ።",
        'en' => "Your question is posted. I will message you when someone answers.\n\nWhen an answer solves it, tap ✅ Mark solved under your question, or send /myquestions.",
    ],
    'ask_no_group' => [
        'am' => 'የጥያቄ ቡድኑ ገና አልተዘጋጀም። አስተዳዳሪ በቅርቡ ይከፍተዋል።',
        'ti' => 'ጉጅለ ሕቶታት ገና ኣይተዳለወን። ኣመሓዳሪ ኣብ ቀረባ ክኸፍቶ እዩ።',
        'en' => 'The questions group is not set up yet. An admin will open it soon.',
    ],
    'ask_limit' => [
        'am' => 'ዛሬ {count} ጥያቄዎችን ጠይቀዋል። ነገ እንደገና ይሞክሩ።',
        'ti' => 'ሎሚ {count} ሕቶታት ሓቲትኩም። ጽባሕ እንደገና ፈትኑ።',
        'en' => 'You have asked {count} questions today. Try again tomorrow.',
    ],
    'q_group_post' => [
        'am' => "❓ <b>{title}</b>\n\n{body}\n\n{asker} · #{category}",
        'ti' => "❓ <b>{title}</b>\n\n{body}\n\n{asker} · #{category}",
        'en' => "❓ <b>{title}</b>\n\n{body}\n\n{asker} · #{category}",
    ],
    'q_anonymous_asker' => [
        'am' => '🙈 አንድ አባል',
        'ti' => '🙈 ሓደ ኣባል',
        'en' => '🙈 A member',
    ],
    'q_solved_tag' => [
        'am' => '✅ ተፈቷል',
        'ti' => '✅ ተፈቲሑ',
        'en' => '✅ Solved',
    ],
    'btn_q_solve' => [
        'am' => '✅ ተፈቷል',
        'ti' => '✅ ተፈቲሑ',
        'en' => '✅ Mark solved',
    ],
    'q_new_answer' => [
        'am' => "💬 «{title}» ለሚለው ጥያቄዎ {name} መልስ ሰጥቷል፡\n\n{excerpt}",
        'ti' => "💬 ንሕቶኹም «{title}» {name} መልሲ ሂቡ፡\n\n{excerpt}",
        'en' => "💬 {name} answered your question \"{title}\":\n\n{excerpt}",
    ],
    'q_pick_answer' => [
        'am' => '«{title}» የሚለውን የፈታው የትኛው መልስ ነው?',
        'ti' => 'ነቲ «{title}» ዝፈትሖ ኣየናይ መልሲ እዩ?',
        'en' => 'Which answer solved "{title}"?',
    ],
    'q_no_answers' => [
        'am' => 'እስካሁን ማንም አልመለሰም።',
        'ti' => 'ክሳብ ሕጂ ዝመለሰ የለን።',
        'en' => 'No one has answered yet.',
    ],
    'q_check_dm' => [
        'am' => 'መልሱን እንዲመርጡ በግል መልዕክት ልኬልዎታለሁ።',
        'ti' => 'መልሲ ክትመርጹ ብውልቂ መልእኽቲ ሰዲደልኩም።',
        'en' => 'I sent you a private message to pick the answer.',
    ],
    'q_not_allowed' => [
        'am' => 'ተፈቷል ማለት የሚችለው የጠየቀው ሰው ወይም አወያይ ብቻ ነው።',
        'ti' => 'ተፈቲሑ ክብል ዝኽእል ዝሓተተ ሰብ ወይ ኣወያዪ ጥራይ እዩ።',
        'en' => 'Only the person who asked, or a moderator, can mark this solved.',
    ],
    'q_already_solved' => [
        'am' => 'ይህ ጥያቄ ቀድሞ ተፈቷል።',
        'ti' => 'እዚ ሕቶ ድሮ ተፈቲሑ።',
        'en' => 'This question is already solved.',
    ],
    'q_solved_dm' => [
        'am' => 'ተፈቷል ተብሎ ተመዝግቧል። አመሰግናለሁ!',
        'ti' => 'ተፈቲሑ ተባሂሉ ተመዝጊቡ። የቐንየለይ!',
        'en' => 'Marked as solved. Thank you!',
    ],
    'q_solved_group' => [
        'am' => '✅ ተፈቷል፤ የ{name} መልስ ረድቷል።',
        'ti' => '✅ ተፈቲሑ፤ መልሲ {name} ሓጊዙ።',
        'en' => '✅ Solved: the answer by {name} helped.',
    ],
    'q_points_dm' => [
        'am' => '⭐ «{title}» ለሚለው ጥያቄ የሰጡት መልስ መፍትሔ ሆኗል። +{points} የረዳት ነጥብ (አሁን {total})።',
        'ti' => '⭐ ንሕቶ «{title}» ዝሃብኩምዎ መልሲ መፍትሒ ኮይኑ። +{points} ነጥቢ ሓጋዚ (ሕጂ {total})።',
        'en' => '⭐ Your answer to "{title}" was marked as the solution. +{points} helper points (now {total}).',
    ],
    'myq_title' => [
        'am' => 'የእርስዎ ጥያቄዎች',
        'ti' => 'ሕቶታትኩም',
        'en' => 'Your questions',
    ],
    'myq_none' => [
        'am' => 'እስካሁን ጥያቄ አልጠየቁም። ለመጠየቅ /ask ይላኩ።',
        'ti' => 'ክሳብ ሕጂ ሕቶ ኣይሓተትኩምን። ንምሕታት /ask ስደዱ።',
        'en' => 'You have not asked anything yet. Send /ask to ask.',
    ],
    'myq_answers' => [
        'am' => '{count} መልስ',
        'ti' => '{count} መልሲ',
        'en' => '{count} answers',
    ],
    'rank_helper' => [
        'am' => 'ረዳት',
        'ti' => 'ሓጋዚ',
        'en' => 'Helper',
    ],
    'rank_trusted' => [
        'am' => 'የታመነ',
        'ti' => 'እሙን',
        'en' => 'Trusted',
    ],
    'rank_elder' => [
        'am' => 'ሽማግሌ',
        'ti' => 'ሽማግለ',
        'en' => 'Elder',
    ],

    // ---- FAQ ----------------------------------------------------------------
    'faq_pick_category' => [
        'am' => "📚 <b>ተደጋግመው የሚጠየቁ ጥያቄዎች</b>\n\nርዕስ ይምረጡ፣ ወይም ለመፈለግ «/faq ቃል» ይላኩ።",
        'ti' => "📚 <b>ብተደጋጋሚ ዝሕተቱ ሕቶታት</b>\n\nኣርእስቲ ምረጹ፣ ወይ ንምድላይ «/faq ቃል» ስደዱ።",
        'en' => "📚 <b>Frequently asked questions</b>\n\nPick a topic, or send \"/faq word\" to search.",
    ],
    'faq_empty' => [
        'am' => 'እስካሁን የተዘጋጀ ጥያቄና መልስ የለም። ማህበረሰቡን በ /ask ይጠይቁ።',
        'ti' => 'ክሳብ ሕጂ ዝተዳለወ ሕቶን መልስን የለን። ንማሕበረሰብ ብ /ask ሕተቱ።',
        'en' => 'There are no FAQ entries yet. Ask the community with /ask.',
    ],
    'faq_search_none' => [
        'am' => '«{query}» የሚል አልተገኘም። ማህበረሰቡን በ /ask ይጠይቁ።',
        'ti' => '«{query}» ዝብል ኣይተረኽበን። ንማሕበረሰብ ብ /ask ሕተቱ።',
        'en' => 'Nothing matches "{query}". Ask the community with /ask.',
    ],
    'btn_faq_topics' => [
        'am' => '📚 ርዕሶች',
        'ti' => '📚 ኣርእስትታት',
        'en' => '📚 Topics',
    ],

    // ---- assistant --------------------------------------------------------
    'ai_footer' => [
        'am' => 'ጉዳይዎ የተለየ ከሆነ ማህበረሰቡን ይጠይቁ።',
        'ti' => 'ጉዳይኩም ፍሉይ እንተኾይኑ ንማሕበረሰብ ሕተቱ።',
        'en' => 'Ask the group if your case is different.',
    ],
    'ai_sources' => [
        'am' => 'ምንጭ፡',
        'ti' => 'ምንጪ፡',
        'en' => 'Sources:',
    ],
    'btn_ai_ask_group' => [
        'am' => '❓ ማህበረሰቡን ጠይቅ',
        'ti' => '❓ ንማሕበረሰብ ሕተት',
        'en' => '❓ Ask the community',
    ],
    'ai_limit' => [
        'am' => 'ዛሬ ረዳቱን {limit} ጊዜ ጠይቀዋል። ማህበረሰቡን በ /ask ይጠይቁ ወይም ነገ ይሞክሩ።',
        'ti' => 'ሎሚ ንሓጋዚ {limit} ግዜ ሓቲትኩም። ንማሕበረሰብ ብ /ask ሕተቱ ወይ ጽባሕ ፈትኑ።',
        'en' => 'You have asked the assistant {limit} times today. Ask the community with /ask, or try again tomorrow.',
    ],
    'ai_unavailable' => [
        'am' => 'ረዳቱ አሁን መመለስ አልቻለም። ማህበረሰቡን በ /ask ይጠይቁ።',
        'ti' => 'ሓጋዚ ሕጂ ክምልስ ኣይከኣለን። ንማሕበረሰብ ብ /ask ሕተቱ።',
        'en' => 'The assistant cannot answer right now. Ask the community with /ask.',
    ],
    'ai_faq_matches' => [
        'am' => 'ጥያቄና መልሱ እንዲህ ይላል፡',
        'ti' => 'ሕቶን መልስን ከምዚ ይብል፡',
        'en' => 'Here is what the FAQ says:',
    ],
    'ai_no_match' => [
        'am' => 'ለዚህ መልስ የለኝም። ማህበረሰቡን ይጠይቁ፤ የሚያውቁ ሰዎች አሉ።',
        'ti' => 'ነዚ መልሲ የብለይን። ንማሕበረሰብ ሕተቱ፤ ዝፈልጡ ሰባት ኣለዉ።',
        'en' => 'I do not have an answer for that. Ask the community; people there will know.',
    ],

    // ---- safety desk ------------------------------------------------------
    'safe_card' => [
        'am' => "🆘 <b>እርዳታና ደህንነት</b>\n\n🚓 የፖሊስ አስቸኳይ ጥሪ፡ 999\n🚓 የዱባይ ፖሊስ (አስቸኳይ ያልሆነ)፡ 901\n{extra}\n<b>መብትዎን ይወቁ</b>\n• የቪዛና የምልመላ ወጪ የአሠሪው ነው፤ ሠራተኛው አይከፍልም።\n• አሠሪ ፓስፖርትዎን መያዝ አይፈቀድለትም።\n• ያልተከፈለ ደሞዝን ለሰው ኃይል ሚኒስቴር (MoHRE) ማመልከት ይችላሉ።\n\nአሠሪ እየበደለዎት ወይም ደሞዝ ካልከፈለዎት ለአወያዮች በግል መናገር ይችላሉ።",
        'ti' => "🆘 <b>ሓገዝን ድሕነትን</b>\n\n🚓 ህጹጽ ፖሊስ፡ 999\n🚓 ፖሊስ ዱባይ (ህጹጽ ዘይኮነ)፡ 901\n{extra}\n<b>መሰልኩም ፍለጡ</b>\n• ወጻኢ ቪዛን ምልመላን ናይ ኣስራሒ እዩ፤ ሰራሕተኛ ኣይከፍልን።\n• ኣስራሒ ፓስፖርትኹም ክሕዝ ኣይፍቀደሉን።\n• ዘይተኸፍለ ደሞዝ ናብ ሚኒስትሪ ሓይሊ ሰብ (MoHRE) ከተመልክቱ ትኽእሉ።\n\nኣስራሒ እንተበደለኩም ወይ ደሞዝ እንተዘይከፊሉኩም ንኣወያየቲ ብውልቂ ክትነግሩ ትኽእሉ።",
        'en' => "🆘 <b>Help and safety</b>\n\n🚓 Police emergency: 999\n🚓 Dubai Police (not urgent): 901\n{extra}\n<b>Know your rights</b>\n• Your employer pays for your visa and recruitment, not you.\n• Your employer may not keep your passport.\n• You can complain about unpaid salary to the Ministry of Human Resources (MoHRE).\n\nIf an employer is mistreating you or not paying you, you can tell our moderators privately.",
    ],
    'safe_line_consulate' => [
        'am' => '🇪🇹 የኢትዮጵያ ቆንስላ፡ {phone}',
        'ti' => '🇪🇹 ቆንስላ ኢትዮጵያ፡ {phone}',
        'en' => '🇪🇹 Ethiopian consulate: {phone}',
    ],
    'safe_line_mohre' => [
        'am' => '🏛 MoHRE፡ {phone}',
        'ti' => '🏛 MoHRE፡ {phone}',
        'en' => '🏛 MoHRE: {phone}',
    ],
    'btn_safe_report' => [
        'am' => '🚩 አሠሪን በግል ሪፖርት አድርግ',
        'ti' => '🚩 ኣስራሒ ብውልቂ ሪፖርት ግበር',
        'en' => '🚩 Report an employer privately',
    ],
    'safe_ask_employer' => [
        'am' => 'የአሠሪው ስም (ሰው ወይም ድርጅት) ማን ነው? እዚህ የሚልኩት ምንም ነገር የትም አይለጠፍም።',
        'ti' => 'ስም ኣስራሒ (ሰብ ወይ ትካል) መን እዩ? ኣብዚ እትሰዱዎ ዝኾነ ነገር ኣበይ ኣይለጠፍን።',
        'en' => 'What is the employer\'s name (a person or a company)? Nothing you send here is posted anywhere.',
    ],
    'safe_ask_what' => [
        'am' => 'ምን ሆነ? የፈለጉትን ያህል ይጻፉ።',
        'ti' => 'እንታይ ኮይኑ? ዝደለኹምዎ ክሳብ ጸሓፉ።',
        'en' => 'What happened? Write as much as you want.',
    ],
    'safe_ask_contact' => [
        'am' => 'አወያይ ስለዚህ ጉዳይ እንዲያነጋግርዎት ይፈቅዳሉ?',
        'ti' => 'ኣወያዪ ብዛዕባ እዚ ጉዳይ ከዘራርበኩም ትፈቕዱ ዶ?',
        'en' => 'May a moderator contact you about it?',
    ],
    'btn_yes' => [
        'am' => 'አዎ',
        'ti' => 'እወ',
        'en' => 'Yes',
    ],
    'btn_no' => [
        'am' => 'አይ',
        'ti' => 'ኣይፋል',
        'en' => 'No',
    ],
    'safe_thanks' => [
        'am' => 'አመሰግናለሁ። ይህንን የሚያዩት አወያዮች ብቻ ናቸው። አደጋ ላይ ከሆኑ አሁኑኑ 999 ይደውሉ።',
        'ti' => 'የቐንየለይ። እዚ ዝርእይዎ ኣወያየቲ ጥራይ እዮም። ኣብ ሓደጋ እንተሃሊኹም ሕጂ 999 ደውሉ።',
        'en' => 'Thank you. Only moderators see this. If you are in danger, call 999 now.',
    ],
    'safe_too_short' => [
        'am' => 'እባክዎ ትንሽ ተጨማሪ ይጻፉ።',
        'ti' => 'በጃኹም ቁሩብ ወሲኽኩም ጸሓፉ።',
        'en' => 'Please write a little more.',
    ],

    // ---- announcements ----------------------------------------------------
    'ann_no_rights' => [
        'am' => 'ማስታወቂያ መለጠፍ የሚችሉት የማህበረሰብ መሪዎች ናቸው። ቤተ ክርስቲያን፣ መስጊድ ወይም ማህበር የሚመሩ ከሆነ አስተዳዳሪውን ይጠይቁ።',
        'ti' => 'መወዓውዒ ክለጥፉ ዝኽእሉ መራሕቲ ማሕበረሰብ እዮም። ቤተ ክርስትያን፣ መስጊድ ወይ ማሕበር እንተመሪሕኩም ንኣመሓዳሪ ሕተቱ።',
        'en' => 'Only community leaders can post announcements. If you run a church, mosque or association, ask an admin.',
    ],
    'ann_pick_topic' => [
        'am' => '📣 ማስታወቂያው ለየትኛው ርዕስ ነው?',
        'ti' => '📣 እቲ መወዓውዒ ንኣየናይ ኣርእስቲ እዩ?',
        'en' => '📣 Which topic is this announcement for?',
    ],
    'ann_ask_text' => [
        'am' => 'ማስታወቂያውን በአንድ ቋንቋ ይጻፉ። ስሞች፣ ቀኖችና ቦታዎች እንዳሉ ይቆያሉ፤ ቦቱ ወደ ሌሎቹ ሁለት ቋንቋዎች ይተረጉማል።',
        'ti' => 'መወዓውዒ ብሓደ ቋንቋ ጸሓፉ። ኣስማት፣ ዕለታትን ቦታታትን ከም ዘለዉ ይጸንሑ፤ ቦት ናብቶም ካልኦት ክልተ ቋንቋታት ይትርጉም።',
        'en' => 'Write the announcement in one language. Names, dates and places stay exactly as written; the bot translates it into the other two.',
    ],
    'ann_text_invalid' => [
        'am' => 'ማስታወቂያ ከ10 እስከ 2000 ፊደላት ነው።',
        'ti' => 'መወዓውዒ ካብ 10 ክሳብ 2000 ፊደላት እዩ።',
        'en' => 'An announcement is 10 to 2000 letters.',
    ],
    'ann_ask_photo' => [
        'am' => 'አንድ ፎቶ ይላኩ፣ ወይም «ዝለል» ይጫኑ።',
        'ti' => 'ሓደ ስእሊ ስደዱ፣ ወይ «ሕለፍ» ጠውቑ።',
        'en' => 'Send one photo for it, or tap Skip.',
    ],
    'ann_ask_date' => [
        'am' => 'ዝግጅት ነው? ቀኑን እንደ 2026-11-20 ይላኩ፣ ወይም «ዝለል» ይጫኑ።',
        'ti' => 'ምድላው ድዩ? ዕለት ከም 2026-11-20 ስደዱ፣ ወይ «ሕለፍ» ጠውቑ።',
        'en' => 'Is it an event? Send the date like 2026-11-20, or tap Skip.',
    ],
    'ann_date_invalid' => [
        'am' => 'ቀኑ ዛሬ ወይም ወደፊት፣ እንደ 2026-11-20 መሆን አለበት።',
        'ti' => 'ዕለት ሎሚ ወይ ንቕድሚት፣ ከም 2026-11-20 ክኸውን ኣለዎ።',
        'en' => 'The date must be today or later, written like 2026-11-20.',
    ],
    'ann_ask_place' => [
        'am' => 'የት ነው? የቦታውን ስም ይጻፉ፣ ወይም «ዝለል» ይጫኑ።',
        'ti' => 'ኣበይ እዩ? ስም ቦታ ጸሓፉ፣ ወይ «ሕለፍ» ጠውቑ።',
        'en' => 'Where is it? Type the place, or tap Skip.',
    ],
    'ann_preview' => [
        'am' => "ቅድመ እይታ፡\n\n{card}\n\n{note}«ለጥፍ» ሲጫኑ በቻናሉ ይለጠፋል፣ ለተመዘገቡ አባላትም ይላካል።",
        'ti' => "ቅድመ ርእይቶ፡\n\n{card}\n\n{note}«ለጥፍ» ምስ ጠወቕኩም ኣብ ቻነል ይለጠፍ፣ ንዝተመዝገቡ ኣባላት እውን ይለኣኽ።",
        'en' => "Preview:\n\n{card}\n\n{note}Tap Publish to post it in the channel and send it to subscribers.",
    ],
    'ann_needs_approval_note' => [
        'am' => "ይህ ርዕስ ከመለጠፉ በፊት በአወያይ ይታያል።\n\n",
        'ti' => "እዚ ኣርእስቲ ቅድሚ ምልጣፉ ብኣወያዪ ይረአ።\n\n",
        'en' => "This topic is checked by a moderator before it is posted.\n\n",
    ],
    'btn_publish' => [
        'am' => '📣 ለጥፍ',
        'ti' => '📣 ለጥፍ',
        'en' => '📣 Publish',
    ],
    'ann_published' => [
        'am' => 'ተለጥፏል። {count} አባላት በመልዕክት ይደርሳቸዋል።',
        'ti' => 'ተለጢፉ። {count} ኣባላት ብመልእኽቲ ክበጽሖም እዩ።',
        'en' => 'Posted. {count} members will get it as a message.',
    ],
    'ann_pending' => [
        'am' => 'አመሰግናለሁ። አወያይ ካረጋገጠው በኋላ ይለጠፋል።',
        'ti' => 'የቐንየለይ። ኣወያዪ ምስ ኣረጋገጾ ክለጠፍ እዩ።',
        'en' => 'Thank you. A moderator will check it before it is posted.',
    ],
    'ann_approved_dm' => [
        'am' => '✅ ማስታወቂያዎ ጸድቆ ተለጥፏል።',
        'ti' => '✅ መወዓውዒኹም ጸዲቑ ተለጢፉ።',
        'en' => '✅ Your announcement was approved and posted.',
    ],
    'ann_rejected_dm' => [
        'am' => 'ማስታወቂያዎ አልተለጠፈም። {note}',
        'ti' => 'መወዓውዒኹም ኣይተለጠፈን። {note}',
        'en' => 'Your announcement was not posted. {note}',
    ],
    'ann_card' => [
        'am' => "{emoji} <b>{topic}</b>\n\n{text}{event}\n\n— {poster}",
        'ti' => "{emoji} <b>{topic}</b>\n\n{text}{event}\n\n— {poster}",
        'en' => "{emoji} <b>{topic}</b>\n\n{text}{event}\n\n— {poster}",
    ],
    'ann_event_line' => [
        'am' => "\n\n📅 {date}{place}",
        'ti' => "\n\n📅 {date}{place}",
        'en' => "\n\n📅 {date}{place}",
    ],
    'ann_fundraiser_note' => [
        'am' => "\n\n⚠️ መድረኩ ገንዘብ አይሰበስብም። የሚያውቋቸው ሰዎች ብቻ ይስጡ።",
        'ti' => "\n\n⚠️ እቲ መድረኽ ገንዘብ ኣይእክብን። ንትፈልጥዎም ሰባት ጥራይ ሃቡ።",
        'en' => "\n\n⚠️ The platform does not collect money. Give only to people you know.",
    ],
    'btn_ann_delete' => [
        'am' => '🗑 ሰርዝ',
        'ti' => '🗑 ደምስስ',
        'en' => '🗑 Delete',
    ],
    'ann_deleted' => [
        'am' => 'ከቻናሉ ተሰርዟል።',
        'ti' => 'ካብ ቻነል ተደምሲሱ።',
        'en' => 'Deleted from the channel.',
    ],
    'ann_reminder' => [
        'am' => "⏰ <b>ማሳሰቢያ፡ ነገ</b>\n\n{card}",
        'ti' => "⏰ <b>መዘኻኸሪ፡ ጽባሕ</b>\n\n{card}",
        'en' => "⏰ <b>Reminder: tomorrow</b>\n\n{card}",
    ],
    // ---- group moderation -------------------------------------------------
    'mod_removed_link' => [
        'am' => 'በቡድኑ የላኩት መልዕክት ተወግዷል፤ አዲስ አባላት በመጀመሪያዎቹ 7 ቀናት ሊንክ ወይም ስልክ ቁጥር መለጠፍ አይችሉም። ይህ አጭበርባሪዎችን ለመከላከል ነው።',
        'ti' => 'ኣብ ጉጅለ ዝሰደድኩምዎ መልእኽቲ ተኣልዩ፤ ሓደስቲ ኣባላት ኣብ ቀዳሞት 7 መዓልታት ሊንክ ወይ ቁጽሪ ስልኪ ክለጥፉ ኣይኽእሉን። እዚ ንመታለልቲ ንምክልኻል እዩ።',
        'en' => 'Your message in the group was removed: new members cannot post links or phone numbers in their first 7 days. This keeps scammers out.',
    ],
    'mod_removed_phrase' => [
        'am' => 'በቡድኑ የላኩት መልዕክት የታወቀ የማጭበርበሪያ አገላለጽ ስለያዘ ተወግዷል። ስህተት ከሆነ ለአወያይ ይንገሩ።',
        'ti' => 'ኣብ ጉጅለ ዝሰደድኩምዎ መልእኽቲ ፍሉጥ ናይ ምትላል ኣበሃህላ ስለዝሓዘ ተኣልዩ። ጌጋ እንተኾይኑ ንኣወያዪ ንገሩ።',
        'en' => 'Your message in the group was removed because it matched a known scam phrase. If this is a mistake, tell a moderator.',
    ],
    'mod_removed_item' => [
        'am' => 'የዕቃ መልዕክትዎ ተወግዷል፤ ጥሬ ገንዘብ፣ ወርቅ፣ ጌጣጌጥ፣ መድሃኒትና የተከለከሉ ዕቃዎችን በሰሌዳው ማጓጓዝ አይቻልም።',
        'ti' => 'መልእኽቲ ኣቕሑኹም ተኣልዩ፤ ጥረ ገንዘብ፣ ወርቂ፣ ስልማት፣ መድሃኒትን ዝተኸልከሉ ኣቕሑን ብሰሌዳ ምጽዓን ኣይከኣልን።',
        'en' => 'Your goods post was removed: cash, gold, jewellery, medicine and banned items cannot be carried through the board.',
    ],
    'mod_muted' => [
        'am' => 'ተደጋጋሚ መልዕክቶች ስለተወገዱ ለ{hours} ሰዓታት በቡድኖቹ መለጠፍ አይችሉም።',
        'ti' => 'ተደጋጋሚ መልእኽትታት ስለዝተኣልዩ ን{hours} ሰዓታት ኣብ ጉጅለታት ክትለጥፉ ኣይትኽእሉን።',
        'en' => 'You cannot post in the groups for {hours} hours after repeated removals.',
    ],
    'mod_warning' => [
        'am' => "⚠️ አወያይ አስጠንቅቆዎታል፡ {reason}\n\nእባክዎ የማህበረሰቡን ህጎች ይከተሉ። በ90 ቀናት ውስጥ ሦስት ማስጠንቀቂያ ለ7 ቀናት መለጠፍ ያስከለክላል።",
        'ti' => "⚠️ ኣወያዪ ኣጠንቂቑኩም፡ {reason}\n\nበጃኹም ሕግታት ማሕበረሰብ ተኸተሉ። ኣብ 90 መዓልታት ሰለስተ መጠንቀቕታ ን7 መዓልታት ካብ ምልጣፍ የኽልክል።",
        'en' => "⚠️ A moderator warned you: {reason}\n\nPlease follow the community rules. Three warnings in 90 days mean 7 days without posting.",
    ],
    'mod_report_need_reply' => [
        'am' => 'መልዕክት ሪፖርት ለማድረግ ለዚያ መልዕክት በ /report ይመልሱ።',
        'ti' => 'መልእኽቲ ሪፖርት ንምግባር ነቲ መልእኽቲ ብ /report መልሱሉ።',
        'en' => 'To report a message, reply to it with /report.',
    ],
    'mod_report_thanks' => [
        'am' => 'አመሰግናለሁ፤ ሪፖርትዎ ደርሷል። አወያዮች ያዩታል።',
        'ti' => 'የቐንየለይ፤ ሪፖርትኹም በጺሑ። ኣወያየቲ ክርእይዎ እዮም።',
        'en' => 'Thank you, your report is in. Moderators will look at it.',
    ],

    'topics_menu' => [
        'am' => "📣 <b>በመልዕክት የሚደርስዎ ማስታወቂያ</b>\n\nርዕስ ለማብራት ወይም ለማጥፋት ይጫኑ። ሁሉም በቻናሉም ይገኛል።",
        'ti' => "📣 <b>ብመልእኽቲ ዝበጽሓኩም መወዓውዒ</b>\n\nኣርእስቲ ንምብራህ ወይ ንምጥፋእ ጠውቑ። ኩሉ ኣብ ቻነል እውን ይርከብ።",
        'en' => "📣 <b>Announcements you get as a message</b>\n\nTap a topic to turn it on or off. Everything is also in the channel.",
    ],

    // ---- goods board ---------------------------------------------------------

    'goods_menu' => [
        'am' => "📦 <b>የዕቃ ሰሌዳ</b>\n\nትርፍ ኪሎ ያላቸው ተጓዦች በኢማራትና በኢትዮጵያ መካከል ዕቃ ማስላክ ከሚፈልጉ አባላት ጋር ይገናኛሉ። ገንዘብ በእኛ በኩል አያልፍም፤ እርስ በርሳችሁ ትስማማላችሁ፣ ቦቱ መዝገቡንና ደረጃውን ይይዛል።\n\n/travel — እጓዛለሁ፣ ቦታ አለኝ\n/send — የሚላክ ዕቃ አለኝ\n/mygoods — የእኔ ልጥፎችና ስምምነቶች\n\nክፍት የሆኑትን ይመልከቱ፡",
        'ti' => "📦 <b>ሰሌዳ ኣቕሑ</b>\n\nተረፍ ኪሎ ዘለዎም ገያሾ ኣብ መንጎ ኢማራትን ኢትዮጵያን ኣቕሓ ክሰዱ ምስ ዝደልዩ ኣባላት ይራኸቡ። ገንዘብ ብኣና ኣይሓልፍን፤ ብሓባር ትሰማምዑ፣ ቦት መዝገብን ደረጃን ይሕዝ።\n\n/travel — ክጉዓዝ እየ፣ ቦታ ኣለኒ\n/send — ዝስደድ ኣቕሓ ኣለኒ\n/mygoods — ልጥፋተይን ስምምዓተይን\n\nክፉታት ርኣዩ፡",
        'en' => "📦 <b>Goods board</b>\n\nTravellers with spare kilos meet members who need something carried between the UAE and Ethiopia. No money goes through us: you agree between yourselves, and the bot keeps the record and the ratings.\n\n/travel — I am travelling and have space\n/send — I need something carried\n/mygoods — my posts and deals\n\nBrowse what is open:",
    ],
    'btn_goods_browse_traveller_to_et' => [
        'am' => '🧳 ተጓዦች → ኢትዮጵያ',
        'ti' => '🧳 ገያሾ → ኢትዮጵያ',
        'en' => '🧳 Travellers → Ethiopia',
    ],
    'btn_goods_browse_traveller_to_ae' => [
        'am' => '🧳 ተጓዦች → ኢማራት',
        'ti' => '🧳 ገያሾ → ኢማራት',
        'en' => '🧳 Travellers → UAE',
    ],
    'btn_goods_browse_sender_to_et' => [
        'am' => '📦 ዕቃዎች → ኢትዮጵያ',
        'ti' => '📦 ኣቕሑ → ኢትዮጵያ',
        'en' => '📦 Parcels → Ethiopia',
    ],
    'btn_goods_browse_sender_to_ae' => [
        'am' => '📦 ዕቃዎች → ኢማራት',
        'ti' => '📦 ኣቕሑ → ኢማራት',
        'en' => '📦 Parcels → UAE',
    ],
    'goods_browse_empty' => [
        'am' => 'እዚህ ገና ክፍት ልጥፍ የለም።',
        'ti' => 'ኣብዚ ገና ክፉት ልጥፍ የለን።',
        'en' => 'Nothing open here yet.',
    ],
    'goods_blocked' => [
        'am' => 'በዚህ ዓመት ሁለት ስምምነቶች ስለከሸፉ የዕቃ ሰሌዳውን መጠቀም አይችሉም። ስህተት ነው ብለው ካሰቡ አወያይን ያነጋግሩ።',
        'ti' => 'ኣብዚ ዓመት ክልተ ስምምዓት ስለዝፈሸሉ ሰሌዳ ኣቕሑ ክትጥቀሙ ኣይትኽእሉን። ጌጋ እዩ እንተኢልኩም ንኣወያዪ ተዘራረቡ።',
        'en' => 'You cannot use the goods board after two failed deals this year. If you think this is wrong, talk to a moderator.',
    ],
    'goods_max_open' => [
        'am' => 'የዚህ ዓይነት {max} ክፍት ልጥፎች አሉዎት። መጀመሪያ በ /mygoods አንዱን ይዝጉ።',
        'ti' => 'ናይዚ ዓይነት {max} ክፉታት ልጥፋት ኣለዉኹም። መጀመርታ ብ /mygoods ሓደ ዕጸዉ።',
        'en' => 'You already have {max} open posts of this kind. Close one in /mygoods first.',
    ],
    'goods_ask_direction' => [
        'am' => 'ወዴት?',
        'ti' => 'ናበይ?',
        'en' => 'Which way?',
    ],
    'goods_ask_date_traveller' => [
        'am' => 'የበረራዎ ቀን መቼ ነው? እንደ 20/11/2026 ይጻፉ።',
        'ti' => 'ዕለት በረራኹም መዓስ እዩ? ከም 20/11/2026 ጸሓፉ።',
        'en' => 'What date is your flight? Write it like 20/11/2026.',
    ],
    'goods_ask_date_sender' => [
        'am' => 'በግምት በየትኛው ቀን መሄድ አለበት? እንደ 20/11/2026 ይጻፉ። በሦስት ቀን ውስጥ የሚጓዙ ተጓዦች ይገናኛሉ።',
        'ti' => 'ብግምት ኣበየናይ ዕለት ክኸይድ ኣለዎ? ከም 20/11/2026 ጸሓፉ። ኣብ ውሽጢ ሰለስተ መዓልቲ ዝጓዓዙ ገያሾ ክራኸቡ እዮም።',
        'en' => 'Around what date should it go? Write it like 20/11/2026. Travellers within three days of it will match.',
    ],
    'goods_date_invalid' => [
        'am' => 'ከዛሬ ጀምሮ እስከ አራት ወር ድረስ ያለ ቀን እንደ 20/11/2026 ይጻፉ።',
        'ti' => 'ካብ ሎሚ ጀሚሩ ክሳብ ኣርባዕተ ወርሒ ዘሎ ዕለት ከም 20/11/2026 ጸሓፉ።',
        'en' => 'Write a date from today up to four months ahead, like 20/11/2026.',
    ],
    'goods_ask_from' => [
        'am' => 'ከየትኛው ከተማ?',
        'ti' => 'ካብ ኣየናይ ከተማ?',
        'en' => 'From which city?',
    ],
    'goods_ask_to' => [
        'am' => 'ወደ የትኛው ከተማ?',
        'ti' => 'ናብ ኣየናይ ከተማ?',
        'en' => 'To which city?',
    ],
    'goods_ask_kilos_traveller' => [
        'am' => 'ለሌሎች ስንት ኪሎ መያዝ ይችላሉ? ቁጥር ይጫኑ ወይም ይጻፉ።',
        'ti' => 'ንኻልኦት ክንደይ ኪሎ ክትሕዙ ትኽእሉ? ቁጽሪ ጠውቑ ወይ ጸሓፉ።',
        'en' => 'How many kilos can you carry for others? Tap a number or write one.',
    ],
    'goods_ask_kilos_sender' => [
        'am' => 'በግምት ክብደቱ ስንት ነው? የኪሎ ቁጥር ይጫኑ ወይም ይጻፉ።',
        'ti' => 'ብግምት ክብደቱ ክንደይ እዩ? ቁጽሪ ኪሎ ጠውቑ ወይ ጸሓፉ።',
        'en' => 'About how heavy is it? Tap a number of kilos or write one.',
    ],
    'goods_kilos_invalid' => [
        'am' => 'ከ1 እስከ {max} ያለ ሙሉ የኪሎ ቁጥር ይጻፉ።',
        'ti' => 'ካብ 1 ክሳብ {max} ዘሎ ምሉእ ቁጽሪ ኪሎ ጸሓፉ።',
        'en' => 'Write a whole number of kilos from 1 to {max}.',
    ],
    'btn_goods_kilos' => [
        'am' => '{kilos} ኪሎ',
        'ti' => '{kilos} ኪሎ',
        'en' => '{kilos} kg',
    ],
    'goods_ask_categories_traveller' => [
        'am' => 'ምን ዓይነት ዕቃ ይይዛሉ? የሚቀበሉትን እያንዳንዱን ይጫኑ፣ ከዚያ ጨርሻለሁ ይበሉ።',
        'ti' => 'እንታይ ዓይነት ኣቕሓ ክትሕዙ ኢኹም? ዝቕበሉዎ ነፍሲ ወከፍ ጠውቑ፣ ድሕሪኡ ወዲአ በሉ።',
        'en' => 'What will you carry? Tap each kind you accept, then Done.',
    ],
    'goods_ask_categories_sender' => [
        'am' => 'ምን ዓይነት ዕቃ ነው? የሚመለከተውን ሁሉ ይጫኑ፣ ከዚያ ጨርሻለሁ ይበሉ።',
        'ti' => 'እንታይ ዓይነት ኣቕሓ እዩ? ዝምልከቶ ኩሉ ጠውቑ፣ ድሕሪኡ ወዲአ በሉ።',
        'en' => 'What kind of thing is it? Tap each that applies, then Done.',
    ],
    'goods_categories_picked' => [
        'am' => 'የተመረጡ፡ {list}። ተጨማሪ ይጫኑ ወይም ጨርሻለሁ ይበሉ።',
        'ti' => 'ዝተመርጹ፡ {list}። ተወሳኺ ጠውቑ ወይ ወዲአ በሉ።',
        'en' => 'Picked: {list}. Tap more, or Done.',
    ],
    'goods_categories_need_one' => [
        'am' => 'ቢያንስ አንድ ይምረጡ።',
        'ti' => 'እንተወሓደ ሓደ ምረጹ።',
        'en' => 'Pick at least one.',
    ],
    'goods_ask_terms' => [
        'am' => 'ለላኪዎች ቅድመ ሁኔታ አለዎት? ለምሳሌ፡ ክፍት ቦርሳ ብቻ፣ እስከ 18 ቀን ድረስ ዴይራ ላይ ማስረከብ። ወይም ዝለል ይጫኑ።',
        'ti' => 'ንሰደድቲ ቅድመ ኩነት ኣለኩም? ንኣብነት፡ ክፉት ቦርሳ ጥራይ፣ ክሳብ 18 ዕለት ኣብ ዴይራ ምርካብ። ወይ ሕለፍ ጠውቑ።',
        'en' => 'Any conditions for senders? For example: open bags only, hand over in Deira by the 18th. Or tap Skip.',
    ],
    'goods_ask_description' => [
        'am' => 'ውስጡ ያለውን በአንድ ወይም በሁለት መስመር ይግለጹ። ተጓዡ ከመስማማቱ በፊት ይህን ያነባል።',
        'ti' => 'ኣብ ውሽጡ ዘሎ ብሓደ ወይ ብኽልተ መስመር ግለጹ። እቲ ገያሻይ ቅድሚ ምስምማዑ እዚ የንብብ።',
        'en' => 'Describe what is inside in one or two lines. The traveller reads this before agreeing.',
    ],
    'goods_text_invalid' => [
        'am' => 'ከ5 እስከ 300 ፊደላት ይጻፉ።',
        'ti' => 'ካብ 5 ክሳብ 300 ፊደላት ጸሓፉ።',
        'en' => 'Write between 5 and 300 characters.',
    ],
    'goods_banned_item' => [
        'am' => 'ይህ በሰሌዳው ሊላክ አይችልም፤ ጥሬ ገንዘብ፣ ወርቅ፣ ጌጣጌጥ፣ መድኃኒት፣ አደንዛዥ ዕፅ፣ መሳሪያ፣ ሲጋራ፣ አልኮልና ጫት በፍጹም አይላኩም። ያለ እነሱ እንደገና ይጻፉ።',
        'ti' => 'እዚ ብሰሌዳ ክስደድ ኣይክእልን፤ ጥረ ገንዘብ፣ ወርቂ፣ ስልማት፣ መድሃኒት፣ ኣደንዛዚ ዕጸ፣ ኣጽዋር፣ ሽጋራ፣ ኣልኮልን ጫትን ፈጺሞም ኣይስደዱን። ብዘይካኦም እንደገና ጸሓፉ።',
        'en' => 'That cannot go through the board: cash, gold, jewellery, medicine, drugs, weapons, cigarettes, alcohol and khat are never carried. Write it again without them.',
    ],
    'goods_preview' => [
        'am' => "ልጥፍዎ እንዲህ ይታያል፡\n\n{card}\n\nይለጠፍ?",
        'ti' => "ልጥፍኩም ከምዚ ይርአ፡\n\n{card}\n\nይለጠፍ?",
        'en' => "This is how your post will look:\n\n{card}\n\nPost it?",
    ],
    'btn_goods_post' => [
        'am' => '📦 ለጥፍ',
        'ti' => '📦 ለጥፍ',
        'en' => '📦 Post it',
    ],
    'goods_posted' => [
        'am' => '✅ ተለጥፏል።{group}',
        'ti' => '✅ ተለጢፉ።{group}',
        'en' => '✅ Posted.{group}',
    ],
    'goods_posted_group' => [
        'am' => ' በዕቃ ሰሌዳ ቡድኑም ላይ ወጥቷል።',
        'ti' => ' ኣብ ጉጅለ ሰሌዳ ኣቕሑ እውን ወጺኡ።',
        'en' => ' It is on the goods board group too.',
    ],
    'goods_matches_title' => [
        'am' => 'እነዚህ ልጥፎች ከእርስዎ ጋር ይገጥማሉ። ለመጠየቅ ፍላጎት አለኝ ይጫኑ፡',
        'ti' => 'እዞም ልጥፋት ምስ ናትኩም ይሰማምዑ። ንምሕታት ድሌት ኣለኒ ጠውቑ፡',
        'en' => 'These posts match yours. Tap Interested to ask:',
    ],
    'goods_no_matches' => [
        'am' => 'ገና የሚገጥም የለም። ሲለጠፍ መልዕክት እንልክልዎታለን።',
        'ti' => 'ገና ዝሰማማዕ የለን። ምስ ተለጠፈ መልእኽቲ ክንሰደልኩም ኢና።',
        'en' => 'No match yet. We will message you when one is posted.',
    ],
    'goods_new_match' => [
        'am' => "💡 አዲስ ልጥፍ ከእርስዎ ጋር ይገጥማል፡\n\n{card}",
        'ti' => "💡 ሓድሽ ልጥፍ ምስ ናትኩም ይሰማማዕ፡\n\n{card}",
        'en' => "💡 A new post matches yours:\n\n{card}",
    ],
    'btn_goods_interested' => [
        'am' => '🤝 ፍላጎት አለኝ',
        'ti' => '🤝 ድሌት ኣለኒ',
        'en' => '🤝 Interested',
    ],
    'goods_need_post_traveller' => [
        'am' => 'ይህን ለመያዝ ለማቅረብ መጀመሪያ ጉዞዎን ይለጥፉ። ልክ እንደለጠፉ ጥያቄዎ ይላካል።',
        'ti' => 'ነዚ ክትሕዙ ንምቕራብ መጀመርታ ጉዕዞኹም ለጥፉ። ልክዕ ምስ ለጠፍኩም ሕቶኹም ይለኣኽ።',
        'en' => 'To offer to carry this, post your trip first. Your request goes out the moment you post.',
    ],
    'goods_need_post_sender' => [
        'am' => 'ይህን ተጓዥ ለመጠየቅ መጀመሪያ የሚልኩትን ይለጥፉ። ልክ እንደለጠፉ ጥያቄዎ ይላካል።',
        'ti' => 'ነዚ ገያሻይ ንምሕታት መጀመርታ እትሰዱዎ ለጥፉ። ልክዕ ምስ ለጠፍኩም ሕቶኹም ይለኣኽ።',
        'en' => 'To ask this traveller, first post what you are sending. Your request goes out the moment you post.',
    ],
    'goods_interest_mismatch' => [
        'am' => 'አዲሱ ልጥፍዎ ከዚያኛው ጋር አይገጥምም (ቀኑ ወይም ኪሎው ይለያያል)፣ ስለዚህ ጥያቄ አልተላከም።',
        'ti' => 'ሓድሽ ልጥፍኩም ምስቲ ካልእ ኣይሰማማዕን (ዕለቱ ወይ ኪሎኡ ይፈላለ)፣ ስለዚ ሕቶ ኣይተላእከን።',
        'en' => 'Your new post does not fit that one (the dates or the kilos differ), so no request was sent.',
    ],
    'goods_post_closed' => [
        'am' => 'ይህ ልጥፍ ከአሁን በኋላ ክፍት አይደለም።',
        'ti' => 'እዚ ልጥፍ ድሕሪ ሕጂ ክፉት ኣይኮነን።',
        'en' => 'This post is no longer open.',
    ],
    'goods_own_post' => [
        'am' => 'ይህ የራስዎ ልጥፍ ነው።',
        'ti' => 'እዚ ናይ ባዕልኹም ልጥፍ እዩ።',
        'en' => 'This is your own post.',
    ],
    'goods_already_asked' => [
        'am' => 'ስለዚህ ልጥፍ አስቀድመው ጠይቀዋል።',
        'ti' => 'ብዛዕባ እዚ ልጥፍ ኣቐዲምኩም ሓቲትኩም ኢኹም።',
        'en' => 'You have already asked about this post.',
    ],
    'goods_requests_limit' => [
        'am' => 'ዛሬ {count} ጥያቄዎችን ልከዋል። ነገ እንደገና ይሞክሩ።',
        'ti' => 'ሎሚ {count} ሕቶታት ሰዲድኩም። ጽባሕ እንደገና ፈትኑ።',
        'en' => 'You have sent {count} requests today. Try again tomorrow.',
    ],
    'goods_request_sent' => [
        'am' => 'ጥያቄ ለ{name} ተልኳል። ሲመልሱ እንነግርዎታለን።',
        'ti' => 'ሕቶ ን{name} ተላኢኹ። ምስ መለሱ ክንነግረኩም ኢና።',
        'en' => 'Request sent to {name}. We will tell you when they answer.',
    ],
    'goods_request' => [
        'am' => "🤝 <b>{name}</b> ልጥፍዎን ይፈልጋሉ።\n\nየእነሱ ልጥፍ፡\n{card}\n\nአድራሻ ለመለዋወጥ ይቀበሉ። ካልተቀበሉ ምንም አይጋራም።",
        'ti' => "🤝 <b>{name}</b> ልጥፍኩም ይደልዩ።\n\nልጥፎም፡\n{card}\n\nኣድራሻ ንምልውዋጥ ተቐበሉ። እንተዘይተቐበልኩም ዋላ ሓንቲ ኣይክፈልን።",
        'en' => "🤝 <b>{name}</b> is interested in your post.\n\nTheir post:\n{card}\n\nAccept to swap contacts. Nothing is shared unless you accept.",
    ],
    'btn_goods_accept' => [
        'am' => '✅ ተቀበል',
        'ti' => '✅ ተቐበል',
        'en' => '✅ Accept',
    ],
    'btn_goods_decline' => [
        'am' => '✖️ አይሆንም',
        'ti' => '✖️ ኣይፋል',
        'en' => '✖️ No thanks',
    ],
    'goods_request_accepted' => [
        'am' => '✅ {name}ን ተቀብለዋል።',
        'ti' => '✅ ን{name} ተቐቢልኩም።',
        'en' => '✅ You accepted {name}.',
    ],
    'goods_request_unreachable' => [
        'am' => '{name} አሁን ሊገኙ አይችሉም። ሌላ ልጥፍ ይሞክሩ።',
        'ti' => '{name} ሕጂ ክርከቡ ኣይክእሉን። ካልእ ልጥፍ ፈትኑ።',
        'en' => '{name} cannot be reached right now. Try another post.',
    ],
    'goods_declined' => [
        'am' => '{name} ይህን መቀበል አይችሉም። ሌሎች ልጥፎችን በ /goods ይመልከቱ።',
        'ti' => '{name} ነዚ ክቕበሉ ኣይክእሉን። ካልኦት ልጥፋት ብ /goods ርኣዩ።',
        'en' => '{name} cannot take this one. See other posts with /goods.',
    ],
    'goods_declined_owner' => [
        'am' => 'ውድቅ ተደርጓል። በትህትና አሳውቀናቸዋል።',
        'ti' => 'ተነጺጉ። ብትሕትና ኣፍሊጥናዮም።',
        'en' => 'Declined. We let them know politely.',
    ],
    'goods_introduced' => [
        'am' => "🤝 ከ{name} ጋር ተገናኝተዋል።\n{badge}\n\n{contact}\n\nዝርዝሩን በመካከላችሁ ተስማሙ፡ የት እንደሚሰጥ፣ በትክክል ምን እንደሚላክ፣ በሌላኛው ወገን ማን እንደሚቀበል። ሁሉንም አብራችሁ ፈትሹ፤ ሲታሸግ ያላዩትን ዕቃ በፍጹም አይያዙ።\n\nስትስማሙ ሁለታችሁም ተስማምተናል ይጫኑ።",
        'ti' => "🤝 ምስ {name} ተራኺብኩም።\n{badge}\n\n{contact}\n\nዝርዝር ኣብ መንጎኹም ተሰማምዑ፡ ኣበይ ከም ዝወሃብ፣ ብልክዕ እንታይ ከም ዝኸይድ፣ ኣብ ካልእ ወገን መን ከም ዝቕበሎ። ኩሉ ብሓባር መርምሩ፤ ክዕሸግ ዘይረኣኹምዎ ኣቕሓ ፈጺምኩም ኣይትሓዙ።\n\nምስ ተሰማማዕኩም ክልቴኹም ተሰማሚዕና ጠውቑ።",
        'en' => "🤝 You are connected with {name}.\n{badge}\n\n{contact}\n\nAgree the details between you: where to hand over, exactly what goes, and who collects it at the other end. Check everything together, and never carry a parcel you did not see packed.\n\nWhen you have agreed, both press We agreed.",
    ],
    'goods_contact' => [
        'am' => "መልዕክት ይላኩላቸው፡ {link}\nስሙ ውይይት ካልከፈተ መለያቸው የግል ነው፤ ስምዎ አላቸው፣ መልዕክት ይልኩልዎታል።",
        'ti' => "መልእኽቲ ስደዱሎም፡ {link}\nእቲ ስም ዕላል እንተዘይከፊቱ ኣካውንቶም ብሕታዊ እዩ፤ ስምኩም ኣለዎም፣ መልእኽቲ ክሰዱልኩም እዮም።",
        'en' => "Message them: {link}\nIf the name does not open a chat, their account is private: they have your name and will message you.",
    ],
    'btn_goods_agree' => [
        'am' => '🤝 ተስማምተናል',
        'ti' => '🤝 ተሰማሚዕና',
        'en' => '🤝 We agreed',
    ],
    'btn_goods_drop' => [
        'am' => '✖️ አልቀጥልም',
        'ti' => '✖️ ኣይቕጽልን',
        'en' => '✖️ Not going ahead',
    ],
    'goods_agree_waiting' => [
        'am' => 'ተመዝግቧል። {name} ተስማምተናል እስኪጫኑ እየጠበቅን ነው።',
        'ti' => 'ተመዝጊቡ። {name} ተሰማሚዕና ክሳብ ዝጥውቑ ንጽበ ኣለና።',
        'en' => 'Noted. Waiting for {name} to press We agreed too.',
    ],
    'goods_agree_nudge' => [
        'am' => '{name} ተስማምተናል ተጭነዋል። እርስዎም ስትስማሙ ይጫኑ።',
        'ti' => '{name} ተሰማሚዕና ጠዊቖም። ንስኹም እውን ምስ ተሰማማዕኩም ጠውቑ።',
        'en' => '{name} pressed We agreed. Press it too once you have agreed.',
    ],
    'goods_agreed' => [
        'am' => "✅ ከ{name} ጋር ተስማምተዋል፡ {kilos} ኪሎ፣ {route}፣ {date}። በ{checkin} መድረሱን ሁለታችሁንም እንጠይቃለን።\n\nበሕዝብ ቦታ ይስጡ፣ አብራችሁ ከፍታችሁ ፈትሹ፣ ለተገዛለት ማንኛውም ነገር ደረሰኝ ያስቀምጡ።",
        'ti' => "✅ ምስ {name} ተሰማሚዕኩም፡ {kilos} ኪሎ፣ {route}፣ {date}። ብ{checkin} ምብጻሑ ንኽልቴኹም ክንሓትት ኢና።\n\nኣብ ህዝባዊ ቦታ ሃቡ፣ ብሓባር ከፊትኩም መርምሩ፣ ንዝተገዝኣሉ ዝኾነ ነገር ቅብሊት ዓቕቡ።",
        'en' => "✅ Agreed with {name}: {kilos} kg, {route}, {date}. On {checkin} we will ask you both whether it arrived.\n\nHand over in a public place, open and check it together, and keep receipts for anything bought for it.",
    ],
    'goods_no_room' => [
        'am' => 'ተጓዡ ለዚህ ዕቃ የቀረው ቦታ የለም።',
        'ti' => 'እቲ ገያሻይ ነዚ ኣቕሓ ዝተረፎ ቦታ የብሉን።',
        'en' => 'The traveller has no room left for this parcel.',
    ],
    'goods_dropped' => [
        'am' => 'ተፈጽሟል፤ ይህ ግንኙነት ተሰርዟል።',
        'ti' => 'ተፈጺሙ፤ እዚ ርክብ ተሰሪዙ።',
        'en' => 'Done: this match is cancelled.',
    ],
    'goods_dropped_other' => [
        'am' => '{name} በግንኙነታችሁ አልቀጠሉም ({route}፣ {date})። ልጥፍዎ ክፍት ሆኖ ይቆያል።',
        'ti' => '{name} ብርክብኩም ኣይቀጸሉን ({route}፣ {date})። ልጥፍኩም ክፉት ኮይኑ ይጸንሕ።',
        'en' => '{name} is not going ahead with your match ({route}, {date}). Your post stays open.',
    ],
    'goods_match_closed' => [
        'am' => 'ይህ ግንኙነት ተዘግቷል።',
        'ti' => 'እዚ ርክብ ተዓጽዩ እዩ።',
        'en' => 'This match is already closed.',
    ],
    'goods_checkin' => [
        'am' => '📦 ከ{name} ጋር የነበረው ዕቃ ({route}፣ {date}) በሰላም ደረሰ?',
        'ti' => '📦 ምስ {name} ዝነበረ ኣቕሓ ({route}፣ {date}) ብሰላም በጺሑ ድዩ?',
        'en' => '📦 Did the parcel with {name} ({route}, {date}) arrive safely?',
    ],
    'btn_goods_ci_yes' => [
        'am' => '✅ አዎ፣ ደርሷል',
        'ti' => '✅ እወ፣ በጺሑ',
        'en' => '✅ Yes, it arrived',
    ],
    'btn_goods_ci_no' => [
        'am' => '❌ አይ፣ ችግር አለ',
        'ti' => '❌ ኣይፋል፣ ጸገም ኣሎ',
        'en' => '❌ No, there is a problem',
    ],
    'btn_goods_ci_wait' => [
        'am' => '⏳ ገና ነው',
        'ti' => '⏳ ገና እዩ',
        'en' => '⏳ Not yet',
    ],
    'goods_checkin_thanks' => [
        'am' => 'አመሰግናለሁ፣ ተመዝግቧል።',
        'ti' => 'የቐንየለይ፣ ተመዝጊቡ።',
        'en' => 'Thank you, noted.',
    ],
    'goods_checkin_wait' => [
        'am' => 'እሺ። ከሁለት ቀን በኋላ እንደገና እንጠይቃለን።',
        'ti' => 'ሕራይ። ድሕሪ ክልተ መዓልቲ እንደገና ክንሓትት ኢና።',
        'en' => 'OK. We will ask again in two days.',
    ],
    'goods_completed' => [
        'am' => '🎉 ደርሷል። {name} እንዴት ነበሩ? እነሱም እስኪሰጡዎ ወይም ለአንድ ሳምንት ኮከቦችዎ ተደብቀው ይቆያሉ።',
        'ti' => '🎉 በጺሑ። {name} ከመይ ነይሮም? ንሳቶም እውን ክሳብ ዝህቡኹም ወይ ንሓደ ሰሙን ኮኾባትኩም ተሓቢኦም ይጸንሑ።',
        'en' => '🎉 Delivered. How was {name}? Your stars stay hidden until they rate you too, or for a week.',
    ],
    'goods_rated' => [
        'am' => 'አመሰግናለሁ። ደረጃዎ ተቀምጧል።',
        'ti' => 'የቐንየለይ። ደረጃኹም ተዓቂቡ።',
        'en' => 'Thank you. Your rating is saved.',
    ],
    'goods_already_rated' => [
        'am' => 'ይህን ግንኙነት አስቀድመው ገምግመዋል።',
        'ti' => 'ነዚ ርክብ ኣቐዲምኩም ገምጊምኩም።',
        'en' => 'You have already rated this match.',
    ],
    'goods_disputed' => [
        'am' => 'እናዝናለን። አወያይ ሁለታችሁንም ያነጋግራል። መልዕክቶቻችሁንና ደረሰኞችን ያስቀምጡ።',
        'ti' => 'ንጠዓስ። ኣወያዪ ንኽልቴኹም ክዛረበኩም እዩ። መልእኽትታትኩምን ቅብሊታትን ዓቕቡ።',
        'en' => 'We are sorry. A moderator will contact you both. Keep your messages and any receipts.',
    ],
    'goods_dispute_alert' => [
        'am' => '⚠️ የዕቃ ክርክር #{id}፡ {route}፣ {date}፣ በ{traveller} (ተጓዥ) እና በ{sender} (ላኪ) መካከል። በአስተዳዳሪው የዕቃ ገጽ ላይ ይወስኑ።',
        'ti' => '⚠️ ናይ ኣቕሓ ክርክር #{id}፡ {route}፣ {date}፣ ኣብ መንጎ {traveller} (ገያሻይ) ን {sender} (ሰዳዲ) ን። ኣብ ገጽ ኣቕሑ ናይ ኣመሓዳሪ ወስኑ።',
        'en' => '⚠️ Goods dispute #{id}: {route}, {date}, between {traveller} (traveller) and {sender} (sender). Decide on the Goods page of the admin.',
    ],
    'goods_resolved' => [
        'am' => 'አወያይ ግንኙነትዎን ዘግቷል ({route}፣ {date})፡ {outcome}',
        'ti' => 'ኣወያዪ ርክብኩም ዓጽዩ ({route}፣ {date})፡ {outcome}',
        'en' => 'A moderator closed your match ({route}, {date}): {outcome}',
    ],
    'goods_outcome_completed' => [
        'am' => 'እንደደረሰ ይቆጠራል።',
        'ti' => 'ከም ዝበጽሐ ይቑጸር።',
        'en' => 'it counts as delivered.',
    ],
    'goods_outcome_failed' => [
        'am' => 'አልተሳካም፤ በጥፋተኛው አባል መዝገብ ላይ ሰፍሯል።',
        'ti' => 'ኣይተዓወተን፤ ኣብ መዝገብ እቲ ጌጋ ዝገበረ ኣባል ሰፊሩ።',
        'en' => 'it failed, and it is on the record of the member at fault.',
    ],
    'goods_outcome_cancelled' => [
        'am' => 'ማንም ጥፋተኛ ሳይሆን ተዘግቷል።',
        'ti' => 'ዋላ ሓደ ጌጋ ከይገበረ ተዓጽዩ።',
        'en' => 'it is closed with nobody at fault.',
    ],
    'goods_posts_hidden' => [
        'am' => 'በዚህ ዓመት ሁለት ስምምነቶች ስለከሸፉ የዕቃ ልጥፎችዎ ተደብቀዋል። ስህተት ነው ብለው ካሰቡ አወያይን ያነጋግሩ።',
        'ti' => 'ኣብዚ ዓመት ክልተ ስምምዓት ስለዝፈሸሉ ልጥፋት ኣቕሑኹም ተሓቢኦም። ጌጋ እዩ እንተኢልኩም ንኣወያዪ ተዘራረቡ።',
        'en' => 'Your goods posts are hidden after two failed deals this year. If you think this is wrong, talk to a moderator.',
    ],
    'goods_post_removed' => [
        'am' => 'አወያይ የዕቃ ልጥፍዎን አስወግዷል ({route}፣ {date})፡ {reason}',
        'ti' => 'ኣወያዪ ልጥፍ ኣቕሑኹም ኣልዩ ({route}፣ {date})፡ {reason}',
        'en' => 'A moderator removed your goods post ({route}, {date}): {reason}',
    ],
    'goods_mine_none' => [
        'am' => 'ክፍት ልጥፍ ወይም ስምምነት የለዎትም። በ /travel ወይም /send ይጀምሩ።',
        'ti' => 'ክፉት ልጥፍ ወይ ስምምዕ የብልኩምን። ብ /travel ወይ /send ጀምሩ።',
        'en' => 'You have no open posts or deals. Start with /travel or /send.',
    ],
    'goods_mine_title' => [
        'am' => '📦 <b>የእርስዎ ልጥፎችና ስምምነቶች</b>',
        'ti' => '📦 <b>ልጥፋትኩምን ስምምዓትኩምን</b>',
        'en' => '📦 <b>Your posts and deals</b>',
    ],
    'goods_mine_posts' => [
        'am' => '<b>ክፍት ልጥፎች</b>',
        'ti' => '<b>ክፉታት ልጥፋት</b>',
        'en' => '<b>Open posts</b>',
    ],
    'goods_mine_deals' => [
        'am' => '<b>ስምምነቶች</b>',
        'ti' => '<b>ስምምዓት</b>',
        'en' => '<b>Deals</b>',
    ],
    'btn_goods_close' => [
        'am' => '🔒 {n}ን ዝጋ',
        'ti' => '🔒 {n} ዕጸው',
        'en' => '🔒 Close {n}',
    ],
    'goods_closed' => [
        'am' => 'ልጥፉ ተዘግቷል።',
        'ti' => 'ልጥፍ ተዓጽዩ።',
        'en' => 'Post closed.',
    ],
    'goods_state_requested' => [
        'am' => 'መልስ በመጠባበቅ ላይ',
        'ti' => 'መልሲ ይጽበ',
        'en' => 'waiting for an answer',
    ],
    'goods_state_introduced' => [
        'am' => 'ተገናኝተዋል፣ ገና አልተስማሙም',
        'ti' => 'ተራኺብኩም፣ ገና ኣይተሰማማዕኩምን',
        'en' => 'connected, not agreed yet',
    ],
    'goods_state_agreed' => [
        'am' => 'ተስማምተዋል',
        'ti' => 'ተሰማሚዕኩም',
        'en' => 'agreed',
    ],
    'goods_state_disputed' => [
        'am' => 'በአወያይ እየታየ',
        'ti' => 'ኣብ ኣወያዪ',
        'en' => 'with a moderator',
    ],
    'goods_card_traveller' => [
        'am' => "🧳 <b>ተጓዥ · {route}</b>\n📅 {date} · 🎒 {kilos} ኪሎ ቦታ\n📦 {categories}{notes}\n👤 {badge}",
        'ti' => "🧳 <b>ገያሻይ · {route}</b>\n📅 {date} · 🎒 {kilos} ኪሎ ቦታ\n📦 {categories}{notes}\n👤 {badge}",
        'en' => "🧳 <b>Traveller · {route}</b>\n📅 {date} · 🎒 {kilos} kg free\n📦 {categories}{notes}\n👤 {badge}",
    ],
    'goods_card_sender' => [
        'am' => "📦 <b>ዕቃ · {route}</b>\n📅 በግምት {date} · ⚖️ {kilos} ኪሎ\n🏷 {categories}{notes}\n👤 {badge}",
        'ti' => "📦 <b>ኣቕሓ · {route}</b>\n📅 ብግምት {date} · ⚖️ {kilos} ኪሎ\n🏷 {categories}{notes}\n👤 {badge}",
        'en' => "📦 <b>Parcel · {route}</b>\n📅 around {date} · ⚖️ {kilos} kg\n🏷 {categories}{notes}\n👤 {badge}",
    ],
    'goods_card_rules' => [
        'am' => '⚠️ ጥሬ ገንዘብ፣ ወርቅ፣ መድኃኒት ወይም የታሸገ ዕቃ የለም። ሁሉንም አብራችሁ ፈትሹ።',
        'ti' => '⚠️ ጥረ ገንዘብ፣ ወርቂ፣ መድሃኒት ወይ ዝተዓሸገ ኣቕሓ የለን። ኩሉ ብሓባር መርምሩ።',
        'en' => '⚠️ No cash, gold, medicine or sealed parcels. Check everything together.',
    ],
    'goods_tag_matched' => [
        'am' => '✅ ተገናኝቷል',
        'ti' => '✅ ተራኺቡ',
        'en' => '✅ Matched',
    ],
    'goods_tag_expired' => [
        'am' => '⌛ ቀኑ አልፏል',
        'ti' => '⌛ ዕለቱ ሓሊፉ',
        'en' => '⌛ Date passed',
    ],
    'goods_tag_closed' => [
        'am' => '🔒 ተዘግቷል',
        'ti' => '🔒 ተዓጽዩ',
        'en' => '🔒 Closed',
    ],
    'goods_badge_deals' => [
        'am' => 'ስምምነቶች፡ {count}',
        'ti' => 'ስምምዓት፡ {count}',
        'en' => 'deals: {count}',
    ],
    'goods_badge_new' => [
        'am' => 'በሰሌዳው አዲስ',
        'ti' => 'ኣብ ሰሌዳ ሓድሽ',
        'en' => 'new on the board',
    ],
    'goods_profile_line' => [
        'am' => "\n🧳 የዕቃ ሰሌዳ ስምምነቶች፡ {deals} · {rating}",
        'ti' => "\n🧳 ስምምዓት ሰሌዳ ኣቕሑ፡ {deals} · {rating}",
        'en' => "\n🧳 Goods board deals: {deals} · {rating}",
    ],
    'goods_no_rating' => [
        'am' => 'ገና ደረጃ የለም',
        'ti' => 'ገና ደረጃ የለን',
        'en' => 'no ratings yet',
    ],

    // ---- website sign-in and business owners ------------------------------------

    'login_prompt' => [
        'am' => "🔐 <b>ወደ ሐበሻ ዱባይ ድረ-ገጽ ይግቡ?</b>\n\nድረ-ገጹ ይህን ኮድ ያሳያል፡ <b>{code}</b>\n\nአሁን እርስዎ ራስዎ በ{site} ላይ “በቴሌግራም ይቀጥሉ” ከተጫኑና ኮዱ ከተመሳሰለ ብቻ ያረጋግጡ። መግባት የንግድ ዝርዝሮችን ለመጨመርና ለማስተዳደር ያስችላል፤ የቴሌግራም ስምዎ ብቻ ይጋራል፣ ስልክ ቁጥርዎ አይጋራም።",
        'ti' => "🔐 <b>ናብ መርበብ ሓበሻ ዱባይ ክትኣትዉ?</b>\n\nእቲ መርበብ ነዚ ኮድ የርኢ፡ <b>{code}</b>\n\nሕጂ ባዕልኹም ኣብ {site} “ብቴሌግራም ቀጽሉ” እንተጠዊቕኩምን እቲ ኮድ እንተተሰማሚዑን ጥራይ ኣረጋግጹ። ምእታው ዝርዝራት ንግዲ ንምውሳኽን ንምምሕዳርን የኽእል፤ ስም ቴሌግራምኩም ጥራይ ይካፈል፣ ቁጽሪ ስልክኹም ኣይካፈልን።",
        'en' => "🔐 <b>Sign in to the Habesha Dubai website?</b>\n\nThe website shows this code: <b>{code}</b>\n\nConfirm only if you just pressed “Continue with Telegram” on {site} yourself and the code matches. Signing in lets you add and manage business listings. It shares your Telegram name, never your phone number.",
    ],
    'login_site_fallback' => [
        'am' => 'ድረ-ገጹ',
        'ti' => 'እቲ መርበብ',
        'en' => 'the website',
    ],
    'btn_login_yes' => [
        'am' => '✅ አዎ፣ አስገባኝ',
        'ti' => '✅ እወ፣ ኣእትወኒ',
        'en' => '✅ Yes, sign me in',
    ],
    'btn_login_no' => [
        'am' => '✖️ እኔ አይደለሁም',
        'ti' => '✖️ ኣነ ኣይኮንኩን',
        'en' => '✖️ It was not me',
    ],
    'login_expired' => [
        'am' => 'ይህ የመግቢያ ሊንክ ጊዜው አልፏል። በድረ-ገጹ ላይ እንደገና ይጀምሩ።',
        'ti' => 'እዚ ናይ ምእታው ሊንክ ግዜኡ ሓሊፉ። ኣብቲ መርበብ እንደገና ጀምሩ።',
        'en' => 'This sign-in link has expired. Start again on the website.',
    ],
    'login_done' => [
        'am' => '✅ ገብተዋል። ወደ ድረ-ገጹ ይመለሱ፤ በራሱ ይቀጥላል።',
        'ti' => '✅ ኣቲኹም። ናብቲ መርበብ ተመለሱ፤ ባዕሉ ክቕጽል እዩ።',
        'en' => '✅ You are signed in. Go back to the website; it continues by itself.',
    ],
    'login_declined' => [
        'am' => 'እሺ፣ ማንም አልገባም። ይህን ያልጀመሩት እርስዎ ከሆኑ ሊንኩን ለማንም አያጋሩ።',
        'ti' => 'ሕራይ፣ ዋላ ሓደ ኣይኣተወን። እዚ ዘይጀመርኩምዎ እንተኾይኑ ሊንኩ ንማንም ኣይተካፍሉ።',
        'en' => 'OK, nobody was signed in. If you did not start this, do not share such links with anyone.',
    ],
    'dir_channel_new' => [
        'am' => '🏪 በንግድ ማውጫው አዲስ',
        'ti' => '🏪 ኣብ መምርሒ ንግዲ ሓድሽ',
        'en' => '🏪 New in the business directory',
    ],
    'btn_job_on_site' => [
        'am' => '🌐 በሦስት ቋንቋ',
        'ti' => '🌐 ብሰለስተ ቋንቋ',
        'en' => '🌐 In three languages',
    ],
    'btn_dir_open_site' => [
        'am' => '🌐 በድረ-ገጹ ይክፈቱ',
        'ti' => '🌐 ኣብ መርበብ ክፈቱ',
        'en' => '🌐 Open on the website',
    ],
    'owner_listing_live' => [
        'am' => '✅ <b>{name}</b> ጸድቋል፤ አሁን በድረ-ገጹና በቻናሉ ላይ ይታያል።{link}',
        'ti' => '✅ <b>{name}</b> ጸዲቑ፤ ሕጂ ኣብ መርበብን ቻነልን ይርአ።{link}',
        'en' => '✅ <b>{name}</b> is approved and now live on the website and the channel.{link}',
    ],
    'owner_listing_rejected' => [
        'am' => "<b>{name}</b> አልጸደቀም። ምክንያት፡ {note}\n\nበድረ-ገጹ ላይ አስተካክለው እንደገና መላክ ይችላሉ።",
        'ti' => "<b>{name}</b> ኣይጸደቐን። ምኽንያት፡ {note}\n\nኣብ መርበብ ኣዐሪኹም እንደገና ክትሰዱ ትኽእሉ።",
        'en' => "<b>{name}</b> was not approved. Reason: {note}\n\nYou can fix it on the website and send it again.",
    ],
    'owner_changes_live' => [
        'am' => '✅ በ<b>{name}</b> ላይ ያደረጓቸው ለውጦች ጸድቀው ቀጥታ ወጥተዋል።{link}',
        'ti' => '✅ ኣብ <b>{name}</b> ዝገበርኩምዎም ለውጥታት ጸዲቖም ወጺኦም።{link}',
        'en' => '✅ Your changes to <b>{name}</b> are approved and live.{link}',
    ],
    'owner_changes_rejected' => [
        'am' => "በ<b>{name}</b> ላይ ያደረጓቸው ለውጦች አልጸደቁም። ምክንያት፡ {note}\nዝርዝሩ እንደነበረው ይቀጥላል።",
        'ti' => "ኣብ <b>{name}</b> ዝገበርኩምዎም ለውጥታት ኣይጸደቑን። ምኽንያት፡ {note}\nእቲ ዝርዝር ከም ዝነበሮ ይቕጽል።",
        'en' => "Your changes to <b>{name}</b> were not approved. Reason: {note}\nThe listing stays as it was.",
    ],
    'owner_claim_approved' => [
        'am' => "✅ <b>{name}</b> አሁን የእርስዎ ነው፤ በድረ-ገጹ ላይ ማስተዳደር ይችላሉ።\n{link}",
        'ti' => "✅ <b>{name}</b> ሕጂ ናትኩም እዩ፤ ኣብ መርበብ ከተመሓድርዎ ትኽእሉ።\n{link}",
        'en' => "✅ <b>{name}</b> is now yours to manage on the website.\n{link}",
    ],
    'owner_claim_rejected' => [
        'am' => 'የ<b>{name}</b> ባለቤትነት ጥያቄዎ አልጸደቀም። ምክንያት፡ {note}',
        'ti' => 'ናይ <b>{name}</b> ወናንነት ሕቶኹም ኣይጸደቐን። ምኽንያት፡ {note}',
        'en' => 'Your claim to <b>{name}</b> was not approved. Reason: {note}',
    ],
];
