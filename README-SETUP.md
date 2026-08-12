# ثـمـــن مــوتــرك — Evaluate Your Car
### thamanmotorak.com
### تنصيب اور setup کی ہدایات / Installation guide

---

## 1. کیا بنا ہے؟ / What was built

ایک مکمل کام کرنے والا demo — website + installable app (PWA)، عربی اور انگریزی دونوں میں۔

| فائل | کام |
|---|---|
| `index.php` | صارف کا صفحہ — 8 تصویری خانے، بیانات، ID login، red/green light |
| `api.php` | upload اور status کی processing، اور ای میل بھیجنا |
| `gallery.php` | **خالد کے ای میل والا صفحہ** — تصویریں ترتیب سے (اگلا، پچھلا، دائیں، بائیں…) |
| `admin.php` | خالد کا panel — تصویریں دیکھ کر قیمت لگانا |
| `file.php` | تصویریں صرف admin یا signed link سے کھلتی ہیں |
| `cleanup.php` | 3 یا 7 دن بعد فائلیں خودکار حذف |
| `terms.php` | الشروط والأحكام / Terms & Conditions — دو زبانوں میں |
| `privacy.php` | سياسة الخصوصية / Privacy Policy — دو زبانوں میں |
| `content.php` + `data/content.json` | **سائٹ کے سارے الفاظ، لنک اور رابطے** — پینل سے بدلتے ہیں |
| `admin_content.php` | کنٹرول پینل کا «نصوص الموقع» والا حصہ |
| `config.php` | صرف تکنیکی settings (password، SMTP، حدود) |
| `assets/cars.js` | **گاڑیوں کی فہرست — Make اور Class یہاں بڑھائیں** |
| `manifest.php`, `sw.js`, `icons/` | ایپ کی طرح install ہونے کے لیے |

Word document اور آپ کی نئی ہدایات، سب شامل ہیں:

- ✅ **8 واضح خانے**: أمام، خلف، أيسر، أيمن، سقف (مطلوب) + أسفل السيارة، تابلوه/عداد، مقاعد خلفية (اختياری)
- ✅ ہر خانے میں **دو بٹن**: «کاميرا» (سیدھا کیمرہ کھلے گا) اور «الجهاز» (محفوظ تصویر منتخب کریں)
- ✅ ہر خانے پر چھوٹا نقشہ بنا ہے تاکہ صارف کو زاویہ سمجھ آ جائے — پڑھنے کی ضرورت نہیں
- ✅ 2 اختیاری ویڈیو + اختیاری استمارہ نمبر + اختیاری chassis نمبر
- ✅ **Make → Class → Model → Year کے drop-down** (QatarSale کی طرز پر)، ساتھ «أخرى» کا آپشن
- ✅ صارف خود چنتا ہے: فائلیں 3 دن رہیں یا 7 دن — پھر خودکار حذف
- ✅ صرف ID سے login، کوئی password نہیں
- ✅ ID سکرین پر بھی آتی ہے اور ای میل پر بھی
- ✅ سرخ بتی = زیرِ مراجعہ، سبز بتی = قیمت تیار
- ✅ **خالد کو ای میل میں تصویریں خود نظر آتی ہیں** (نام کے ساتھ: Front, Back, Left…) اور ساتھ پورے سائز کی gallery کا لنک
- ✅ عربی + انگریزی، ایک بٹن سے تبدیل
- ✅ WhatsApp کے لیے جگہ تیار ہے (منظوری کے بعد)

### خالد کو ای میل کیسے پہنچتا ہے؟

ای میل میں تصویریں **inline** لگی ہوتی ہیں (چھوٹا سائز، تقریباً 1 MB کل) تاکہ Hotmail کی حد سے نہ ٹکرائے۔ اصل full-size تصویریں سرور پر رہتی ہیں اور ای میل کے سنہری بٹن **«عرض كل الصور بالحجم الكامل»** سے کھلتی ہیں — یہ `gallery.php` ہے، جہاں ہر تصویر اپنی جگہ پر لگی ہوتی ہے، ساتھ Print اور WhatsApp کے بٹن۔

یہ لنک ایک signed token سے محفوظ ہے، تو کوئی اجنبی اندازہ لگا کر نہیں کھول سکتا۔

### گاڑیوں کی فہرست بڑھانا

`assets/cars.js` میں قطر کی مارکیٹ کی پوری فہرست ہے — **73 کمپنیاں، 730 موڈل**، اور ہر موڈل کے ساتھ اس کے اپنے سال:

```js
"Toyota": {"Land Cruiser":[1985,0], "Corolla Cross":[2021,0], "FJ Cruiser":[2007,2023]},
```

- پہلا نمبر = پہلا سال جو خلیج میں آیا
- دوسرا نمبر = آخری سال۔ **0 کا مطلب «ابھی تک بک رہی ہے»** — اس لیے سال کی فہرست خود بخود اگلے سال تک بڑھتی رہتی ہے، فائل کو ہاتھ لگانے کی ضرورت نہیں۔

صارف «الفئة» چنتا ہے تو **سال کی فہرست خود سکڑ جاتی ہے** اور نیچے چھوٹا سا «2021 – 2027» لکھا آ جاتا ہے۔ اس سے غلط سال (مثلاً ORA 03 کے لیے 1988) چننا ممکن ہی نہیں رہتا۔

نیا موڈل شامل کرنا: `"Tiggo 9":[2024,0]` — بس۔
کوئی موڈل بند ہو جائے: `0` کی جگہ آخری سال لکھ دیں۔

فہرست میں نہ ہونے کی صورت میں صارف «أخرى» چن کر خود لکھ سکتا ہے (اور تب سال کی پوری فہرست 1985 سے آ جاتی ہے)، تو کوئی صارف کبھی رکتا نہیں۔

> سال تقریبی ہیں — یہ صرف یہ طے کرتے ہیں کہ dropdown میں کون سے سال دکھیں۔

---

## 2. Hosting پر چڑھانا / Upload to the server

سائٹ اب **domain کی جڑ** پر چلتی ہے، کسی `/car/` فولڈر میں نہیں۔

1. Hostinger hPanel → **File Manager** → `public_html` کھولیں
2. `public_html` کے اندر جو پرانی فائلیں ہوں انہیں ہٹا دیں (یا کسی `_old` فولڈر میں رکھ دیں)
3. اس ZIP کی ساری فائلیں سیدھی `public_html/` میں upload کریں — فولڈر structure ویسا ہی رکھیں
   (`assets/`, `icons/`, `fonts/`, `tools/` سب اندر جائیں)
4. Permissions:
   - سب فائلیں: `644`
   - سب فولڈر: `755`
   - `data/` اور `uploads/` فولڈر خود بن جائیں گے۔ اگر نہ بنیں تو ہاتھ سے بنا کر `755` کر دیں
5. کھولیں: **`https://thamanmotorak.com/`**

> **اہم:** ای میل کے لیے Hostinger → Emails میں **`admin@thamanmotorak.com`** mailbox موجود ہو،
> اور **`contact@thamanmotorak.com`** اُس کا alias ہو۔ `config.php` میں SMTP username
> `admin@thamanmotorak.com` ہے (alias سے login نہیں ہوتا)، جبکہ ای میل `contact@` کے نام سے جاتی ہے۔
> اگر بعد میں `contact@` کو الگ mailbox بنا دیں تو `config.php` میں `user` وہی کر دیں۔

---

## 3. سب سے پہلے یہ 3 چیزیں بدلیں / Change these 3 things first

`config.php` کھولیں:

```php
'admin_password'   => 'khalid2026',   // ← فوراً بدلیں
'link_secret'      => '...',          // ← کوئی بھی لمبی random string
```

باقی سب کچھ — بھیجنے والا پتہ، رابطے کا ای میل، فون، Instagram، صفحات کے سارے الفاظ —
اب **admin control panel** سے بدلتا ہے، فائل سے نہیں۔ نیچے سیکشن 3b دیکھیں۔

> ای میل میں تصویریں دکھانے کے لیے **GD extension** چاہیے (تقریباً ہر cPanel پر پہلے سے ہوتا ہے)۔ نہ ہو تو ای میل بغیر تصویروں کے چلا جائے گا مگر gallery کا لنک پھر بھی کام کرے گا۔

Admin panel: **`https://thamanmotorak.com/admin.php`**

---

## 3b. کنٹرول پینل — خالد سائٹ کے سارے الفاظ خود بدل سکتا ہے

**`https://thamanmotorak.com/admin.php` → «نصوص الموقع / Site content»**

پینل خود بھی دو زبانوں میں ہے — اوپر دائیں «العربية / English» بٹن سے بدلے گا، اور یہ انتخاب یاد رہتا ہے۔

سات خانے ہیں، ہر خانے میں ہر جملے کے لیے **عربی اور انگریزی دونوں** کے الگ باکس:

| خانہ | اس میں کیا بدلتا ہے |
|---|---|
| 🏷️ الهوية والاسم | سائٹ کا نام، نیچے والی سطر، Google والا description، footer |
| 🏠 الصفحة الرئيسية | سنہری badge، بڑی سرخی «كم تساوي مــوتــرك؟»، اس کے نیچے کی سطر، splash کا بٹن اور لنک |
| ℹ️ صفحة المعلومات | **«نبذة عنا» (Overview)** کی سرخی اور پورا متن، «كيف تعمل الخدمة» کے چاروں steps، خصوصیت کا خلاصہ، «تواصل معنا» کی سرخی |
| 📞 التواصل والروابط | فون/واتساب، دکھایا جانے والا ای میل، **نئے طلب جس ای میل پر آئیں وہ**، Instagram لنک اور handle، website لنک، اور دو خالی «اضافی لنک» جو کبھی بھی بھرے جا سکتے ہیں |
| 📄 الشروط والأحكام | پوری Terms & Conditions صفحہ (13 دفعات، عربی + انگریزی) |
| 🔒 سياسة الخصوصية | پوری Privacy Policy صفحہ (11 دفعات، عربی + انگریزی) |
| 🏢 بيانات الشركة | تجارتی نام، CR نمبر، پتہ، «آخر تحديث» کی تاریخ — یہ تینوں صفحات قانونی صفحات کے اوپر چھپتے ہیں |

- محفوظ کرتے ہی تبدیلی سائٹ پر آ جاتی ہے — کوئی reload، کوئی cache صاف کرنے کی ضرورت نہیں۔
- کوئی خانہ خالی چھوڑ دیں تو اصل لفظ واپس لگ جاتا ہے، صفحہ کبھی خالی نہیں ہوتا۔
- HTML لکھنے کی اجازت نہیں (حفاظت کے لیے) — متن جیسا لکھیں گے ویسا ہی نظر آئے گا۔
- **Terms اور Privacy لکھنے کا طریقہ:** سطر `## ` سے شروع → سرخی، `- ` سے شروع → نقطہ، خالی سطر → نیا پیراگراف۔
- سب کچھ `data/content.json` میں محفوظ ہوتا ہے۔ نیچے **«استعادة الأصل / Restore defaults»** کا بٹن سب کچھ پہلے جیسا کر دیتا ہے۔

> ⚠️ upload کرتے وقت `data/content.json` کو **overwrite نہ کریں** — خالد کی لکھی ہوئی ساری تحریر اسی میں ہوتی ہے۔

### نئے صفحات

| صفحہ | پتہ |
|---|---|
| الشروط والأحكام / Terms & Conditions | `https://thamanmotorak.com/terms.php` |
| سياسة الخصوصية / Privacy Policy | `https://thamanmotorak.com/privacy.php` |

دونوں کے لنک ہر صفحے کے footer میں اور «معلومات» سکرین پر موجود ہیں، اور دونوں ایک ہی بٹن سے عربی/انگریزی میں بدلتے ہیں۔

> **قانونی نوٹ:** یہ متن قطر کے عام کاروباری عرف کے مطابق لکھا گیا ہے، وکیل کی رائے نہیں ہے۔
> خالد چاہے تو اپنے وکیل سے دکھوا کر پینل ہی سے ہر لفظ بدل سکتا ہے۔

---

## 3c. ای میل نہ پہنچے تو — سب سے اہم صفحہ

**`https://thamanmotorak.com/mailtest.php`** (وہی admin password)

یہ صفحہ بتا دیتا ہے کہ ای میل کیوں نہیں جا رہی:

- کیا `mail()` سرور پر چالو ہے
- GD اور OpenSSL موجود ہیں یا نہیں
- کسی بھی پتے پر **ٹیسٹ ای میل** بھیجتا ہے اور mail server کا **اصل جواب** کالے خانے میں دکھاتا ہے
- آخری 60 لائنیں `data/log.txt` کی

### سب سے عام وجہ

Hotmail اور Gmail عام PHP `mail()` والی ای میل خاموشی سے پھینک دیتے ہیں کیونکہ SPF میچ نہیں کرتا۔ **مستقل حل SMTP ہے:**

`config.php` میں (thamanmotorak.com **Hostinger** پر ہے، اس لیے یہی صحیح settings ہیں):

```php
'mail_method' => 'smtp',
'smtp' => [
    'host'   => 'smtp.hostinger.com',
    'port'   => 465,
    'secure' => 'ssl',
    'user'   => 'admin@thamanmotorak.com',   // alias سے login نہیں ہوتا
    'pass'   => 'اس mailbox کا اصل password',
],
```

cPanel والی hosting پر: `mail.YOURDOMAIN.com` ، port 587 ، secure `tls`.

اس کے بعد ای میل اصل mailbox سے نکلتی ہے، SPF پاس ہو جاتا ہے، اور Hotmail قبول کر لیتا ہے۔ کوئی library یا Composer کی ضرورت نہیں — `smtp.php` خود لکھا ہوا ہے۔

`mailtest.php` کا **Domain records** خانہ SPF، DMARC، MX اور SMTP host کو براہِ راست DNS سے پڑھ کر دکھا دیتا ہے — اندازہ لگانے کی ضرورت نہیں۔

Hostinger پر domain لیتے وقت یہ تینوں خود بن جاتے ہیں — `mailtest.php` سے تصدیق کر لیں:
`v=spf1 include:_spf.mail.hostinger.com ~all` · DKIM · `v=DMARC1; p=none`

> اور ہاں — پہلے **junk / spam** فولڈر ضرور دیکھ لیں۔

### کون سی ای میلیں جاتی ہیں؟

| کب | کس کو | کیا |
|---|---|---|
| تصویریں اپلوڈ ہوتے ہی | **خالد** | تصویریں ای میل میں + gallery کا لنک |
| تصویریں اپلوڈ ہوتے ہی | **صارف** | رمز التأكيد + اُس کے سارے درج کردہ بیانات |
| تصویریں اپلوڈ ہوتے ہی | **خالد** (نقل) | صارف والی ای میل کی کاپی — `copy_owner` سے بند کر سکتے ہیں |
| قیمت ڈال کر «Save & send» | **صارف** | قیمت + خالد کے نوٹ + پوری تفصیل دوبارہ |

---

## 4. خودکار حذف چالو کرنا / Enable auto-delete

cPanel → **Cron Jobs** → روزانہ ایک بار:

```
/usr/local/bin/php /home/USERNAME/public_html/cleanup.php
```

(`USERNAME` کی جگہ اپنا cPanel username لکھیں۔)

Cron نہ ہو تو براؤزر سے بھی چل جاتا ہے:
`https://thamanmotorak.com/cleanup.php?key=ADMIN_PASSWORD`

---

## 4b. Android / Samsung پر screen نہ چلے تو

اگر کبھی Samsung Internet یا کسی پرانے Android browser میں صفحہ ٹچ سے **حرکت ہی نہ کرے**، وجہ تقریباً ہمیشہ یہ ہوتی ہے کہ CSS میں `touch-action`، `user-select` یا `overscroll-behavior` کو `html` یا `body` پر لگا دیا گیا ہو۔ Samsung Internet پورے صفحے کے scroller کی `touch-action` سیدھی `<html>` سے پڑھتا ہے، اور بعض versions اُسے `none` سمجھ کر scroll ہی بند کر دیتے ہیں۔

اس لیے `app.css` میں یہ خصوصیات اب صرف بٹنوں اور خانوں پر لگی ہیں، `html`/`body` پر نہیں۔ **آئندہ بھی وہاں مت لگائیں** — فائل میں اس جگہ تنبیہ لکھی ہوئی ہے۔

ساتھ ہی پرانے Samsung Internet کے لیے fallback بھی ڈال دیے ہیں: `100dvh` کے ساتھ `100vh`، `aspect-ratio` کے ساتھ `min-height`، اور `:has()` کی جگہ JS سے class لگتی ہے۔

> نئی فائلیں چڑھانے کے بعد فون پر صفحہ ایک بار **زبردستی refresh** کریں (یا Home Screen والا icon ہٹا کر دوبارہ Add کریں) — service worker کا cache version بڑھا دیا ہے، مگر پہلی بار صاف ہونے میں چند سیکنڈ لگتے ہیں۔

---

## 5. Demo دکھاتے وقت احتیاط / While demoing

`config.php` میں `'noindex' => true` رکھا ہے — Google اس صفحے کو index نہیں کرے گا۔
منظوری کے بعد اسے `false` کر دیں اور `.htaccess` سے `X-Robots-Tag` والی 3 لائنیں نکال دیں۔

---

## 6. ایپ کی طرح install کرنا / Install as an app (ابھی، مفت)

- **Android:** Chrome میں صفحہ کھولیں → مینو → *Add to Home screen* / *Install app*
- **iPhone:** Safari میں کھولیں → Share بٹن → *Add to Home Screen*

Icon بالکل ایپ جیسا آئے گا، address bar غائب، کوئی browser نظر نہیں آئے گا۔
یہ دکھانے کے لیے کافی ہے کہ "ایپ بن سکتی ہے"۔

---

## 7. Play Store اور App Store پر لے جانا / Real store apps

Client کو دونوں stores پر چاہیے — یہی code دوبارہ لکھے بغیر native app بن جاتا ہے۔

### تیاری (ایک بار)

```bash
npm init -y
npm i @capacitor/core @capacitor/cli @capacitor/camera @capacitor/push-notifications
npx cap init "ثـمـــن مــوتــرك" com.thamanmotorak.app --web-dir=www
```

`www/` میں اسی صفحے کی HTML/CSS/JS نقل رکھیں (server والا حصہ `thamanmotorak.com` پر ہی رہے گا؛ ایپ صرف `api.php` کو call کرے گی)۔

### Android → Play Store

```bash
npm i @capacitor/android && npx cap add android && npx cap sync android
npx cap open android      # Android Studio کھلے گا → Build → Signed App Bundle (.aab)
```

- Google Play developer account: **$25 ایک بار**
- ⚠️ نئے **personal** account پر production سے پہلے **12 testers × 14 دن** closed test لازمی ہے۔
  خالد کے کاروباری رجسٹریشن (CR) پر **organization account** بنوائیں تو یہ شرط لاگو نہیں ہوتی — یہی مشورہ دیں۔

### iOS → App Store

```bash
npm i @capacitor/ios && npx cap add ios && npx cap sync ios
npx cap open ios          # Xcode — اس کے لیے Mac ضروری ہے
```

- Apple Developer Program: **$99 سالانہ**
- ⚠️ Apple صرف website لپیٹنے والی ایپ **Guideline 4.2** کے تحت reject کر دیتا ہے۔
  اسی لیے `@capacitor/camera` (native کیمرہ) اور `@capacitor/push-notifications` شامل کیے ہیں — ان سے ایپ "asli native" شمار ہوتی ہے۔
- Mac نہ ہو تو **MacInCloud** جیسی سروس (~$25/مہینہ) سے کام چل جاتا ہے۔

### تخمینی وقت

| مرحلہ | وقت |
|---|---|
| PWA (تیار ہے) | ✅ |
| Android build + Play Store submission | 2–3 دن + review |
| iOS build + App Store submission | 3–5 دن + review (پہلی بار زیادہ سختی) |

---

## 8. اگلا مرحلہ / Phase 2 (منظوری کے بعد)

- **WhatsApp:** WhatsApp Business API (Twilio / 360dialog) — upload ہوتے ہی خالد کے `+974 3032 2225` پر خودکار پیغام، اور صارف کو قیمت WhatsApp پر۔ `config.php` میں نمبر پہلے سے موجود ہے۔
- **ادائیگی:** `config.php` میں `'paid_mode' => true` کر دیں تو ادائیگی والا مرحلہ فعال کرنے کی جگہ تیار ہے۔
  ⚠️ اگر ادائیگی App Store کے **اندر** ہو تو Apple 15–30% کمیشن مانگے گا۔ WhatsApp/bank transfer سے باہر رکھیں تو یہ مسئلہ نہیں۔
- **MySQL:** ابھی ڈیٹا `data/requests.json` میں محفوظ ہے (کسی database کی ضرورت نہیں)۔ سینکڑوں روزانہ requests پر MySQL میں منتقل کرنا آسان ہے۔

---

## 9. جانچ کی فہرست / Test checklist

- [ ] `https://thamanmotorak.com/` کھلتا ہے
- [ ] موبائل پر صفحہ دائیں بائیں **نہیں** گھومتا (360px پر test شدہ ✅)
- [ ] عربی/English بٹن سے پورا صفحہ بدلتا ہے، reload نہیں ہوتا
- [ ] Make/Class/Year کی drop-down کام کرتی ہیں، «أخرى» پر ٹیکسٹ باکس کھلتا ہے
- [ ] GWM → ORA 03 چنیں تو سال صرف 2022–2027 آئیں اور نیچے «2022 – 2027» لکھا ہو
- [ ] 5 مطلوبہ خانے بھرے بغیر «التالي» نہیں چلتا (ناقص خانوں کے نام دکھاتا ہے)
- [ ] «کاميرا» بٹن موبائل پر سیدھا کیمرہ کھولتا ہے
- [ ] بھیجنے پر 6 خانوں کی ID ملتی ہے
- [ ] خالد کے ای میل میں **تصویریں نظر آتی ہیں** اور gallery کا لنک کھلتا ہے
- [ ] `admin.php` میں تصویریں اپنے نام کے ساتھ نظر آتی ہیں
- [ ] قیمت ڈال کر "Save & send" → بتی سبز، صارف کو ای میل
- [ ] `uploads/` فولڈر براؤزر سے براہِ راست نہیں کھلتا (403 آنا چاہیے)
- [ ] `terms.php` اور `privacy.php` کھلتے ہیں اور زبان کا بٹن دونوں پر چلتا ہے
- [ ] footer اور «معلومات» سکرین سے دونوں قانونی صفحات کے لنک کام کرتے ہیں
- [ ] admin → «نصوص الموقع» میں کوئی جملہ بدل کر save کریں → سائٹ پر فوراً نظر آئے
- [ ] admin پینل کا «العربية / English» بٹن پورا پینل بدل دیتا ہے
- [ ] «معلومات» سکرین پر Instagram اور website کے لنک کھلتے ہیں
