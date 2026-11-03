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
        'am' => "👤 {name}\n✅ ከ{since} የተረጋገጠ\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ የረዳት ነጥብ፡ {points}{rank}",
        'ti' => "👤 {name}\n✅ ካብ {since} ዝተረጋገጸ\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ ነጥቢ ሓጋዚ፡ {points}{rank}",
        'en' => "👤 {name}\n✅ Verified since {since}\n📍 {emirate}\n🛠 {trades}\n🌐 {language}\n⭐ Helper points: {points}{rank}",
    ],
    'not_member' => [
        'am' => 'ገና አባል አይደሉም። ለመቀላቀል /start ይላኩ።',
        'ti' => 'ገና ኣባል ኣይኮንኩምን። ንምጽንባር /start ስደዱ።',
        'en' => 'You are not a member yet. Send /start to join.',
    ],
    'help' => [
        'am' => "ማንኛውንም ጥያቄ እዚህ ይጻፉ፤ ረዳቱ ከተረጋገጡ ምንጮች ይመልሳል።\n\nትዕዛዞች፡\n/ask — ማህበረሰቡን መጠየቅ\n/myquestions — የእርስዎ ጥያቄዎች\n/faq — ተደጋግመው የሚጠየቁ\n/jobs — የሥራ ማንቂያዎችና የቅርብ ሥራዎች\n/saved — ያስቀመጧቸው ሥራዎች\n/directory — የሐበሻ ንግድ ማውጫ\n/addbusiness — ንግድ መጠቆም\n/safe — እርዳታና ደህንነት\n/topics — የሚደርስዎ ማስታወቂያ\n/profile — መገለጫ\n/language — ቋንቋ መቀየር\n/leave — ማህበረሰቡን መልቀቅ",
        'ti' => "ዝኾነ ሕቶ ኣብዚ ጸሓፉ፤ ሓጋዚ ካብ ዝተረጋገጹ ምንጭታት ይምልስ።\n\nትእዛዛት፡\n/ask — ንማሕበረሰብ ምሕታት\n/myquestions — ሕቶታትኩም\n/faq — ብተደጋጋሚ ዝሕተቱ\n/jobs — መጠንቀቕታ ስራሕን ናይ ቀረባ ስራሓትን\n/saved — ዝዓቀብኩምዎም ስራሓት\n/directory — መምርሒ ንግዲ ሓበሻ\n/addbusiness — ንግዲ ምምልካት\n/safe — ሓገዝን ድሕነትን\n/topics — ዝበጽሓኩም መወዓውዒ\n/profile — መግለጺ\n/language — ቋንቋ ምቕያር\n/leave — ማሕበረሰብ ምግዳፍ",
        'en' => "Write any question here and the assistant answers from checked sources.\n\nCommands:\n/ask — ask the community\n/myquestions — your questions\n/faq — frequently asked questions\n/jobs — job alerts and the latest jobs\n/saved — jobs you saved\n/directory — Habesha business directory\n/addbusiness — suggest a business\n/safe — help and safety\n/topics — announcements you receive\n/profile — your profile\n/language — change language\n/leave — leave the community",
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
    'topics_menu' => [
        'am' => "📣 <b>በመልዕክት የሚደርስዎ ማስታወቂያ</b>\n\nርዕስ ለማብራት ወይም ለማጥፋት ይጫኑ። ሁሉም በቻናሉም ይገኛል።",
        'ti' => "📣 <b>ብመልእኽቲ ዝበጽሓኩም መወዓውዒ</b>\n\nኣርእስቲ ንምብራህ ወይ ንምጥፋእ ጠውቑ። ኩሉ ኣብ ቻነል እውን ይርከብ።",
        'en' => "📣 <b>Announcements you get as a message</b>\n\nTap a topic to turn it on or off. Everything is also in the channel.",
    ],
];
