/* ============================================================
   app.js — single-page behaviour. No page reload anywhere.
   ============================================================ */
(function () {
'use strict';

/* Bumped with every asset release. index.php checks it and says so plainly if
   assets/app.js on the server is older than the page that loaded it. */
window.__EYC_BUILD = 12;

var CFG = window.APP_CFG || {};
var DB  = window.CAR_DB || {};
/* assets/trims.js — the real trims, model by model. appapi.php parses the
   very same file for the Android and iOS app, so the list the website shows
   and the list the app shows are the same bytes and cannot drift apart. */
var TRIMS = window.CAR_TRIMS || {};
var $  = function (s, r) { return (r || document).querySelector(s); };
var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

/* ---------------- translations ---------------- */
var T = {
  ar: {
    appName:'ثـمـــن مــوتــرك', tagline:'أول خدمة تقييم مجانية في قطر',
    freeBadge:'مجاناً 100%', heroTitle:'كم تساوي مــوتــرك؟',
    heroSub:'صوّر مــوتــرك، أرسل الصور، واحصل على السعر التقديري. بدون رسوم.',
    s1Title:'بيانات السيارة', s1Sub:'الخطوة 1 من 4',
    fMake:'الشركة المصنعة', fClass:'الفئة', fModel:'الموديل / الفئة الفرعية', fYear:'سنة الصنع',
    fKm:'الممشى',
    fReg:'رقم الاستمارة <span class="hint">(اختياري)</span>',
    fVin:'رقم الشاصي / الهيكل <span class="hint">(اختياري)</span>',
    fNotes:'ملاحظات <span class="hint">(اختياري)</span>',
    phNotes:'حوادث، صبغ، إضافات...',
    phMakeOther:'اكتب اسم الشركة', phClassOther:'اكتب اسم الفئة',
    selMake:'— اختر الشركة —', selClass:'— اختر الفئة —', selYear:'— اختر السنة —', other:'أخرى…',
    selTrim:'— اختر الفئة الفرعية —',
    otherYears:'سنوات أخرى',
    s2Title:'حالة السيارة', s2Sub:'الخطوة 2 من 4',
    cmTitle:'حدّد الأجزاء المصبوغة أو المتضررة',
    cmHint:'اضغط على أي جزء مرة = صبغ، مرتين = حادث أو إصلاح، ثلاث مرات = إلغاء.',
    cmClear:'مسح التحديد',
    cmLocked:'اخترت «أصلي بالكامل» — لا يمكن تحديد أي جزء.',
    cpTitle:'حالة الصبغ', ceTitle:'هل الصبغ جزئي أم كامل؟',
    errPaint:'اختر حالة الصبغ أولاً.',
    errExtent:'اختر إن كان الصبغ جزئياً أم كاملاً.',
    cpLockRepaint:'حدّدت جزءاً كـ«حادث / إصلاح» على الرسم، لذلك لا يمكن اختيار «صبغ فقط».',
    cpMoved:'نقلنا اختيارك إلى «صبغ بعد حادث أو إصلاح».',
    sendingTitle:'جارٍ رفع الصور…', sendingWarn:'لا تغلق هذه الصفحة حتى ينتهي الرفع.',
    sendingWork:'جارٍ المعالجة…', sendingWorkSub:'لحظات ونعطيك رقم الطلب.',
    s3Title:'صور السيارة', s3Sub:'الخطوة 3 من 4',
    slotHint:'كل صندوق يوضّح الزاوية المطلوبة. اضغط «كاميرا» للتصوير مباشرة أو «من الجهاز» لاختيار صورة محفوظة.',
    camera:'كاميرا', device:'الجهاز', retake:'تغيير الصورة',
    reqTag:'مطلوب', optTag:'اختياري',
    addVideos:'أضف فيديو (اختياري)',
    videoRule:'حتى مقطعين — دوران حول السيارة وصوت المحرك',
    s4Title:'بياناتك', s4Sub:'الخطوة 4 من 4 — سنرسل رقم طلبك والنتيجة على بريدك',
    fName:'الاسم', fPhone:'رقم الجوال', fEmail:'البريد الإلكتروني',
    fKeep:'كم يوماً نحتفظ بصورك؟', d3:'3 أيام', d7:'7 أيام',
    keepNote:'تُحذف الصور والفيديو تلقائياً بعد هذه المدة.',
    next:'التالي', back:'رجوع', send:'إرسال الطلب', sending:'جارٍ الإرسال…',
    sentTitle:'تم استلام طلبك', sentSub:'احتفظ برقم الطلب — تدخل به بدون كلمة مرور.',
    yourId:'رقم طلبك', sentMail:'أرسلنا الرقم أيضاً إلى بريدك الإلكتروني.',
    junkT:'لم يصلك البريد؟',
    junkB:'تحقق من مجلد «الرسائل غير المرغوب فيها» (Junk / Spam). إن وجدته هناك، اضغط «ليس بريداً مزعجاً» حتى تصلك رسائلنا القادمة في البريد الوارد.',
    junkS:'ما زلت لا تجده؟ راسلنا من صفحة الدعم ←',
    checkNow:'تابع حالة الطلب', another:'تقييم سيارة أخرى',
    stTitle:'حالة طلبك', stSub:'أدخل رقم الطلب فقط — لا حاجة لكلمة مرور.',
    fId:'رقم الطلب', check:'استعلام', checking:'جارٍ الاستعلام…',
    guideTitle:'كيف تعمل الخدمة؟',
    guideSub:'دليل مختصر — الخطوات الخمس، وماذا تحصل عليه، والتطبيقات القادمة.',
    overviewTitle:'نبذة عنا',
    overviewBody:'',
    infoTitle:'كيف تعمل الخدمة؟',
    step1k:'1', step1v:'اختر بيانات موترك من القوائم.',
    step2k:'2', step2v:'صوّر السيارة حسب الصناديق الثمانية.',
    step3k:'3', step3v:'تستلم رقم طلب على الشاشة وعلى بريدك.',
    step4k:'4', step4v:'ضوء أحمر = تحت المراجعة. ضوء أخضر = السعر جاهز.',
    privTitle:'الخصوصية',
    privBody:'صورك وفيديوهاتك تُحذف تلقائياً من الخادم بعد 3 أو 7 أيام حسب اختيارك. لا نشاركها مع أي جهة أخرى.',
    cTitle:'تواصل معنا', cEmail:'البريد', cWeb:'الموقع', devCredit:'تطوير: فاروق',
    termsTitle:'الشروط والأحكام', privacyTitle:'سياسة الخصوصية',
    extraLink1Label:'', extraLink2Label:'',
    navEval:'تقييم', navStatus:'حالة الطلب', navInfo:'معلومات', navSupport:'الدعم',
    supportTitle:'الدعم والاقتراحات',
    supportIntro:'واجهت مشكلة في الموقع؟ عندك اقتراح أو سؤال؟ اكتب لنا مباشرة من هنا وسنرد عليك.',
    supKind:'نوع الرسالة', supMsg:'رسالتك',
    supMsgPh:'اكتب المشكلة بالتفصيل — مثلاً: لم أجد شركة سيارتي في القائمة.',
    supReach:'اترك بريدك أو رقم جوالك حتى نتمكن من الرد عليك — واحد منهما يكفي.',
    supRef:'رقم الطلب <span class="hint">(إن وجد)</span>',
    supSend:'إرسال الرسالة', supSending:'جارٍ الإرسال…',
    supThanksT:'تم إرسال رسالتك',
    supportThanks:'وصلتنا رسالتك، شكراً لك. سنتواصل معك على البريد أو الجوال الذي كتبته.',
    supRefNo:'رقم المتابعة', supAnother:'إرسال رسالة أخرى',
    errSupMsg:'اكتب رسالتك أولاً (١٠ أحرف على الأقل).',
    errSupContact:'اترك بريداً إلكترونياً صحيحاً أو رقم جوال حتى نرد عليك.',
    errSupMany:'أرسلت رسائل كثيرة خلال وقت قصير. حاول بعد قليل.',
    supFollowT:'تابع رسالة سابقة',
    supFollowS:'عندك رقم متابعة؟ أدخله لترى إن كنا قرأنا رسالتك وردّينا عليها.',
    supKeep:'احتفظ بهذا الرقم — تتابع به رسالتك في أي وقت، وأرسلناه أيضاً إلى بريدك.',
    errSupId:'أدخل رقم المتابعة كاملاً.',
    errSupNotFound:'لم نجد هذا الرقم. تأكد منه وحاول مرة أخرى.',
    supStNew:'وصلت — بانتظار القراءة', supStSeen:'تمت القراءة', supStReplied:'تم الرد',
    supYourMsg:'رسالتك', supOurReply:'ردنا', supSentOn:'تاريخ الإرسال',
    pdfDownload:'تنزيل تقرير التقييم (PDF)',
    footer:'ثـمـــن مــوتــرك',
    /* runtime */
    errFields:'يرجى تعبئة الشركة والفئة والموديل وسنة الصنع والممشى.',
    errModel:'يرجى كتابة الموديل / الفئة الفرعية.',
    errKm:'يرجى كتابة الممشى بالأرقام (كم).',
    errMissing:'ناقص: {list}',
    errVideoMax:'الحد الأقصى {n} مقطع فيديو.',
    errBig:'الملف "{f}" كبير جداً (الحد {n} ميجابايت).',
    errName:'أدخل اسمك.', errPhone:'أدخل رقم جوال صحيح.', errEmail:'أدخل بريداً إلكترونياً صحيحاً.',
    errNet:'تعذّر الإرسال. تحقق من الاتصال وحاول مرة أخرى.',
    errId:'أدخل رقم الطلب المكوّن من 6 خانات.',
    errNotFound:'لم نجد هذا الرقم. تأكد منه وحاول مرة أخرى.',
    counterOk:'{a} صور — كل الصور المطلوبة مكتملة ✔',
    counterBad:'{a} من {b} صور مطلوبة',
    underReview:'تحت المراجعة', underReviewSub:'طلبك وصل. سنرسل السعر على بريدك فور جاهزيته.',
    ready:'التقييم جاهز', readySub:'هذا هو السعر التقديري لسيارتك.',
    priceLabel:'السعر التقديري', priceRange:'النطاق السعري التقديري', carLabel:'السيارة', idLabel:'رقم الطلب',
    sentAt:'تاريخ الإرسال', filesUntil:'تُحذف الملفات في', filesCount:'الملفات',
    photosW:'صور', videosW:'فيديو',
    removed:'تم الحذف',
    noteLabel:'ملاحظة من الفريق'
  },
  en: {
    appName:'Evaluate Your Car', tagline:'First free valuation service in Qatar',
    freeBadge:'100% FREE', heroTitle:'What is your car worth?',
    heroSub:'Photograph your car, send the pictures, get an estimated price. No fee.',
    s1Title:'Car details', s1Sub:'Step 1 of 4',
    fMake:'Make', fClass:'Class', fModel:'Model / trim', fYear:'Year',
    fKm:'Mileage',
    fReg:'Registration number <span class="hint">(optional)</span>',
    fVin:'Chassis / VIN <span class="hint">(optional)</span>',
    fNotes:'Notes <span class="hint">(optional)</span>',
    phNotes:'Accidents, repaint, extras…',
    phMakeOther:'Type the make', phClassOther:'Type the class',
    selMake:'— Select make —', selClass:'— Select class —', selYear:'— Select year —', other:'Other…',
    selTrim:'— Select model / trim —',
    otherYears:'Other years',
    s2Title:'Car condition', s2Sub:'Step 2 of 4',
    cmTitle:'Mark the repainted or damaged panels',
    cmHint:'Tap a panel once = repainted, twice = accident or repair, three times = clear.',
    cmClear:'Clear all',
    cmLocked:'You chose “fully original” — no panel can be marked.',
    cpTitle:'Paint status', ceTitle:'Partial or full respray?',
    errPaint:'Please choose the paint status first.',
    errExtent:'Please choose whether the respray was partial or full.',
    cpLockRepaint:'You marked a panel as “accident / repair” on the diagram, so “repainted only” cannot apply.',
    cpMoved:'We moved your answer to “Repainted after an accident / repair”.',
    sendingTitle:'Uploading your photos…', sendingWarn:'Please keep this page open until it finishes.',
    sendingWork:'Processing…', sendingWorkSub:'A moment — your request number is on its way.',
    s3Title:'Car photos', s3Sub:'Step 3 of 4',
    slotHint:'Each box shows the angle we need. Tap “Camera” to shoot it now, or “Gallery” to pick a saved photo.',
    camera:'Camera', device:'Gallery', retake:'Replace photo',
    reqTag:'required', optTag:'optional',
    addVideos:'Add video (optional)',
    videoRule:'Up to 2 clips — a walk-around and the engine sound',
    s4Title:'Your details', s4Sub:'Step 4 of 4 — we email you the request ID and the result',
    fName:'Full name', fPhone:'Mobile number', fEmail:'Email address',
    fKeep:'How long should we keep your files?', d3:'3 days', d7:'7 days',
    keepNote:'Photos and videos are deleted automatically after this period.',
    next:'Next', back:'Back', send:'Send request', sending:'Sending…',
    sentTitle:'Request received', sentSub:'Keep this ID — you log in with it, no password.',
    yourId:'Your request ID', sentMail:'We have also emailed the ID to you.',
    junkT:'Email not arrived?',
    junkB:'Check your Junk or Spam folder. If it is there, press “Not spam” — that way our next messages reach your inbox.',
    junkS:'Still cannot find it? Write to us on the Support page →',
    checkNow:'Check status', another:'Evaluate another car',
    stTitle:'Your request status', stSub:'Enter the request ID only — no password needed.',
    fId:'Request ID', check:'Check', checking:'Checking…',
    guideTitle:'How the service works',
    guideSub:'A short guide — the five steps, what you get, and the apps that are coming.',
    overviewTitle:'Overview',
    overviewBody:'',
    infoTitle:'How it works',
    step1k:'1', step1v:'Pick your car from the dropdowns.',
    step2k:'2', step2v:'Photograph the car following the eight boxes.',
    step3k:'3', step3v:'You get a request ID on screen and by email.',
    step4k:'4', step4v:'Red light = under review. Green light = price ready.',
    privTitle:'Privacy',
    privBody:'Your photos and videos are deleted from the server automatically after 3 or 7 days, whichever you chose. We never share them.',
    cTitle:'Contact us', cEmail:'Email', cWeb:'Website', devCredit:'Developed by Farooq',
    termsTitle:'Terms & Conditions', privacyTitle:'Privacy Policy',
    extraLink1Label:'', extraLink2Label:'',
    navEval:'Evaluate', navStatus:'Status', navInfo:'Info', navSupport:'Support',
    supportTitle:'Support & suggestions',
    supportIntro:'Ran into a problem? Have a suggestion or a question? Write to us here and we will reply.',
    supKind:'What is this about?', supMsg:'Your message',
    supMsgPh:'Describe it in detail — for example: my car make was not in the list.',
    supReach:'Leave an email or a mobile number so we can reply — either one is enough.',
    supRef:'Request ID <span class="hint">(if any)</span>',
    supSend:'Send message', supSending:'Sending…',
    supThanksT:'Your message has been sent',
    supportThanks:'We have your message, thank you. We will get back to you on the email or number you gave.',
    supRefNo:'Reference', supAnother:'Send another message',
    errSupMsg:'Please write your message first (at least 10 characters).',
    errSupContact:'Please leave a valid email or a mobile number so we can reply.',
    errSupMany:'That is a lot of messages in a short time. Please try again later.',
    supFollowT:'Follow up on a message',
    supFollowS:'Have a reference number? Enter it to see whether we have read your message and replied.',
    supKeep:'Keep this number — you can check your message with it any time. We have emailed it to you too.',
    errSupId:'Please enter the full reference number.',
    errSupNotFound:'We could not find that reference. Please check and try again.',
    supStNew:'Received — not read yet', supStSeen:'Read', supStReplied:'Replied',
    supYourMsg:'Your message', supOurReply:'Our reply', supSentOn:'Sent',
    pdfDownload:'Download the valuation report (PDF)',
    footer:'Evaluate Your Car',
    errFields:'Please fill in the make, class, model, year and mileage.',
    errModel:'Please enter the model / trim.',
    errKm:'Please enter the mileage in numbers (km).',
    errMissing:'Still needed: {list}',
    errVideoMax:'Maximum {n} video clips.',
    errBig:'File "{f}" is too large (limit {n} MB).',
    errName:'Please enter your name.', errPhone:'Please enter a valid mobile number.', errEmail:'Please enter a valid email address.',
    errNet:'Could not send. Check your connection and try again.',
    errId:'Enter the 6-character request ID.',
    errNotFound:'We could not find that ID. Please check and try again.',
    counterOk:'{a} photos — all required angles done ✔',
    counterBad:'{a} of {b} required photos',
    underReview:'Under review', underReviewSub:'We have your request. We will email the price as soon as it is ready.',
    ready:'Valuation ready', readySub:'This is the estimated price for your car.',
    priceLabel:'Estimated price', priceRange:'Estimated price range', carLabel:'Car', idLabel:'Request ID',
    sentAt:'Submitted', filesUntil:'Files deleted on', filesCount:'Files',
    photosW:'photos', videosW:'video',
    removed:'Removed',
    noteLabel:'Note from our team'
  }
};

/* ---- words the owner changed in the admin control panel ----
   They arrive from index.php as APP_CFG.i18n and simply replace the
   defaults above, so nothing breaks if a key is missing or blank.   */
var OWNER_KEYS = {};        // keys whose text came from the panel → printed as plain text

(function mergeSupportKinds() {
  var k = CFG.supportKinds;
  if (!k) return;
  Object.keys(k).forEach(function (key) {
    T.ar['sk_' + key] = k[key].ar;
    T.en['sk_' + key] = k[key].en;
    OWNER_KEYS['sk_' + key] = true;
  });
})();

/* ---- option labels for step 2, authored in carmap.php ----
   They arrive as APP_CFG.cond.i18n. Printed as plain text, never as HTML. */
(function mergeConditionText() {
  var o = CFG.cond && CFG.cond.i18n;
  if (!o) return;
  ['ar', 'en'].forEach(function (L) {
    if (!o[L] || !T[L]) return;
    Object.keys(o[L]).forEach(function (k) { T[L][k] = o[L][k]; OWNER_KEYS[k] = true; });
  });
})();

(function mergeOwnerText() {
  var o = CFG.i18n;
  if (!o) return;
  ['ar', 'en'].forEach(function (L) {
    if (!o[L] || !T[L]) return;
    Object.keys(o[L]).forEach(function (k) {
      var v = o[L][k];
      if (typeof v === 'string' && v !== '') { T[L][k] = v; OWNER_KEYS[k] = true; }
    });
  });
})();

var lang = 'ar';
function t(k, vars) {
  var s = (T[lang] && T[lang][k]) || (T.ar[k]) || k;
  if (vars) for (var v in vars) s = s.split('{' + v + '}').join(vars[v]);
  return s;
}
function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; }
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' })[c]; }); }

/* ============================================================
   1. car dropdowns  (Make → Class → Model/trim → Year)
   ============================================================ */
var OTHER = '__other__';

function fillMakes() {
  var sel = $('#makeSel'), cur = sel.value;
  var makes = Object.keys(DB).sort(function (a, b) { return a.localeCompare(b); });
  sel.innerHTML = '<option value="">' + esc(t('selMake')) + '</option>'
    + makes.map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>'; }).join('')
    + '<option value="' + OTHER + '">' + esc(t('other')) + '</option>';
  if (cur) sel.value = cur;
}

function fillClasses() {
  var make = $('#makeSel').value, sel = $('#classSel'), cur = sel.value;
  var list = (make && make !== OTHER && DB[make]) ? Object.keys(DB[make]) : [];
  sel.innerHTML = '<option value="">' + esc(t('selClass')) + '</option>'
    + list.map(function (c) { return '<option value="' + esc(c) + '">' + esc(c) + '</option>'; }).join('')
    + '<option value="' + OTHER + '">' + esc(t('other')) + '</option>';
  if (cur && (list.indexOf(cur) > -1 || cur === OTHER)) sel.value = cur;
  // a free-typed make has no class list — go straight to free text
  if (make === OTHER) { sel.value = OTHER; }
  syncOther();
}

/* ---- the years a model was actually sold ----
   cars.js stores [firstYear, lastYear] per model, lastYear 0 meaning
   "still on sale". Returns null whenever we have nothing reliable —
   a free-typed make or class, or a model missing from the database —
   and then the full year list is shown so nobody is ever blocked. */
var YEAR_FLOOR = 1985;
function newestYear() { return new Date().getFullYear() + 1; }

function classYears(make, cls) {
  if (!make || make === OTHER || !cls || cls === OTHER) return null;
  var m = DB[make];
  if (!m) return null;
  var r = m[cls];
  if (!r || r.length !== 2) return null;
  var top  = newestYear();
  var from = Math.max(YEAR_FLOOR, r[0] || YEAR_FLOOR);
  var to   = r[1] ? Math.min(top, r[1]) : top;
  if (to < from) to = from;
  return [from, to];
}

function fillYears() {
  var sel = $('#yearSel'), cur = sel.value;
  var r   = classYears($('#makeSel').value, $('#classSel').value);
  var top = newestYear();

  function years(hi, lo) {
    var h = '';
    for (var y = hi; y >= lo; y--) h += '<option value="' + y + '">' + y + '</option>';
    return h;
  }

  var html = '<option value="">' + esc(t('selYear')) + '</option>';

  if (r) {
    /* The model's own years come first, under their own heading — but every
       other year stays reachable underneath. Our database is a guide, not an
       authority: if it says a model started in 2014 and the customer's car is
       a 2012, he must still be able to say so. Narrowing the list used to make
       that impossible, and there was no way around it. */
    html += '<optgroup label="' + r[0] + ' – ' + r[1] + '">' + years(r[1], r[0]) + '</optgroup>';
    var rest = '';
    if (r[1] < top)          rest += years(top, r[1] + 1);
    if (r[0] > YEAR_FLOOR)   rest += years(r[0] - 1, YEAR_FLOOR);
    if (rest !== '') html += '<optgroup label="' + esc(t('otherYears')) + '">' + rest + '</optgroup>';
  } else {
    html += years(top, YEAR_FLOOR);
  }
  sel.innerHTML = html;

  // every year is present now, so whatever was chosen always survives
  if (cur) sel.value = cur;

  /* a plain "2021 – 2027" under the box explains why the list is short.
     Numbers only, so it needs no translation. */
  var hint = $('#yearHint');
  if (hint) {
    if (r && (r[0] > YEAR_FLOOR || r[1] < top)) {
      hint.textContent = r[0] + ' – ' + r[1];
      hint.hidden = false;
    } else {
      hint.textContent = '';
      hint.hidden = true;
    }
  }
}

/* ---- the trims a model really has ----
   The list comes from assets/trims.js and from nowhere else. appapi.php
   parses the same file for the app, so a Camry offers the customer the
   same fourteen badges on a phone and in a browser.

   A model that is not in that file has no trims on the source — 233 of the
   730 in cars.js, mostly BMW's X and M and i cars. Those collapse the select
   and leave the plain text box, which is exactly how this field behaved
   before any of this existed. Nothing is invented to fill the gap. */
function fillTrims() {
  var sel = $('#trimSel'), box = $('#car_model');
  if (!sel || !box) return;

  var cls  = $('#classSel').value;
  var list = (cls && cls !== OTHER && TRIMS[cls]) ? TRIMS[cls] : [];

  if (!list.length) {
    sel.innerHTML = '';
    sel.hidden = true;
    box.hidden = false;
    return;
  }

  var cur = sel.value;
  sel.innerHTML = '<option value="">' + esc(t('selTrim')) + '</option>'
    + list.map(function (x) { return '<option value="' + esc(x) + '">' + esc(x) + '</option>'; }).join('')
    + '<option value="' + OTHER + '">' + esc(t('other')) + '</option>';
  sel.hidden = false;

  /* hold on to what the customer already chose — through a language switch,
     a step back, or a re-render — instead of quietly emptying the field */
  if (cur && (list.indexOf(cur) > -1 || cur === OTHER)) sel.value = cur;
  else if (box.value && list.indexOf(box.value) > -1)   sel.value = box.value;
  else if (box.value)                                   sel.value = OTHER;

  syncTrim();
}

/* car_model stays the field that is submitted, whichever way it was filled —
   picked from the list or typed. api.php, the emails, the PDF and the admin
   panel all keep reading the one name they always read. */
function syncTrim() {
  var sel = $('#trimSel'), box = $('#car_model');
  if (!sel || !box) return;
  if (sel.hidden)          { box.hidden = false; return; }
  if (sel.value === OTHER) { box.hidden = false; return; }
  box.hidden = true;
  box.value  = sel.value;          // '' until something is picked, so the
}                                  // step-1 check still catches an empty field

function syncOther() {
  var mo = $('#makeOther'), co = $('#classOther');
  mo.hidden = ($('#makeSel').value !== OTHER);
  co.hidden = ($('#classSel').value !== OTHER);
  $('#classSel').disabled = false;
}

function getMake()  { var v = $('#makeSel').value;  return v === OTHER ? $('#makeOther').value.trim() : v; }
function getClass() { var v = $('#classSel').value; return v === OTHER ? $('#classOther').value.trim() : v; }

$('#makeSel').addEventListener('change', function () {
  $('#classSel').value = ''; fillClasses(); fillYears(); fillTrims(); buzz();
});
$('#classSel').addEventListener('change', function () {
  /* a different model means a different trim list — clear the old choice
     rather than carrying a Camry trim over to a Land Cruiser */
  $('#trimSel').value = ''; $('#car_model').value = '';
  syncOther(); fillYears(); fillTrims(); buzz();  // year and trim both follow the model
});
$('#trimSel').addEventListener('change', function () {
  /* choosing «أخرى» hands over an empty box, not the badge just moved away
     from — only on a real change, so a language switch keeps what was typed */
  if ($('#trimSel').value === OTHER) $('#car_model').value = '';
  syncTrim(); buzz();
  if ($('#trimSel').value === OTHER) $('#car_model').focus();
});

/* ============================================================
   2. the eight photo slots
   ============================================================ */
var SLOTS = CFG.slots || [];
var photoFiles = {};   // { front: File, back: File, ... }

/* small top-view diagrams so the angle is obvious without reading */
function slotSvg(key) {
  var body = '<rect x="13" y="5" width="34" height="70" rx="12" fill="#dde5ec"/>'
           + '<rect x="18" y="15" width="24" height="15" rx="5" fill="#c4d0dc"/>'
           + '<rect x="18" y="50" width="24" height="15" rx="5" fill="#c4d0dc"/>';
  var G = '#c9a227', W = 'stroke="' + G + '" stroke-width="6" stroke-linecap="round" fill="none"';
  var art = {
    front:      body + '<path d="M17 8 H43" ' + W + '/><path d="M30 -1 v0"/>',
    back:       body + '<path d="M17 72 H43" ' + W + '/>',
    left:       body + '<path d="M16 14 V66" ' + W + '/>',
    right:      body + '<path d="M44 14 V66" ' + W + '/>',
    roof:       '<rect x="13" y="5" width="34" height="70" rx="12" fill="' + G + '"/>'
              + '<rect x="18" y="15" width="24" height="15" rx="5" fill="#f0e2b4"/>'
              + '<rect x="18" y="50" width="24" height="15" rx="5" fill="#f0e2b4"/>',
    under:      '<rect x="13" y="5" width="34" height="70" rx="12" fill="none" stroke="' + G + '" stroke-width="4" stroke-dasharray="7 5"/>'
              + '<path d="M30 18 V62" stroke="' + G + '" stroke-width="5" stroke-linecap="round"/>'
              + '<path d="M20 26 H40 M20 54 H40" stroke="' + G + '" stroke-width="5" stroke-linecap="round"/>',
    dashboard:  '<circle cx="30" cy="40" r="22" fill="none" stroke="' + G + '" stroke-width="6"/>'
              + '<circle cx="30" cy="40" r="7" fill="' + G + '"/>'
              + '<path d="M8 40 H23 M37 40 H52 M30 47 V62" stroke="' + G + '" stroke-width="6" stroke-linecap="round"/>',
    rear_seats: '<rect x="8"  y="30" width="19" height="30" rx="6" fill="' + G + '"/>'
              + '<rect x="33" y="30" width="19" height="30" rx="6" fill="' + G + '"/>'
              + '<rect x="8"  y="14" width="19" height="14" rx="5" fill="#dde5ec"/>'
              + '<rect x="33" y="14" width="19" height="14" rx="5" fill="#dde5ec"/>'
  };
  return '<svg viewBox="0 0 60 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' + (art[key] || body) + '</svg>';
}

function buildSlots() {
  var g = $('#slotGrid');
  g.innerHTML = '';
  SLOTS.forEach(function (s) {
    var d = document.createElement('div');
    d.className = 'slot';
    d.dataset.slot = s.key;
    g.appendChild(d);
    // two hidden inputs: one opens the camera, one opens the gallery/files
    var cam = document.createElement('input');
    cam.type = 'file'; cam.accept = 'image/*'; cam.setAttribute('capture', 'environment'); cam.hidden = true;
    var lib = document.createElement('input');
    lib.type = 'file'; lib.accept = 'image/*'; lib.hidden = true;
    d.appendChild(cam); d.appendChild(lib);
    cam.addEventListener('change', function (e) { takeFile(s.key, e.target.files[0]); e.target.value = ''; });
    lib.addEventListener('change', function (e) { takeFile(s.key, e.target.files[0]); e.target.value = ''; });
    d._cam = cam; d._lib = lib;
  });
  renderSlots();
}

function renderSlots() {
  SLOTS.forEach(function (s) {
    var d = $('.slot[data-slot="' + s.key + '"]');
    if (!d) return;
    var f = photoFiles[s.key];
    var label = (lang === 'ar') ? s.ar : s.en;
    var tag = s.req ? '<span class="badge-req">' + esc(t('reqTag')) + '</span>'
                    : '<span class="badge-opt">' + esc(t('optTag')) + '</span>';

    var pic = f
      ? '<div class="pic"><img alt="" src="' + URL.createObjectURL(f) + '"></div>'
      : '<div class="pic">' + slotSvg(s.key) + '</div>';

    var acts = f
      ? '<div class="acts"><button type="button" data-act="lib">' + esc(t('retake')) + '</button></div>'
      : '<div class="acts">'
        + '<button type="button" class="pri" data-act="cam">' + esc(t('camera')) + '</button>'
        + '<button type="button" data-act="lib">' + esc(t('device')) + '</button></div>';

    var del = f ? '<button type="button" class="del" data-act="del">×</button>' : '';

    // keep the two file inputs, replace the rest
    var cam = d._cam, lib = d._lib;
    d.innerHTML = pic + '<h4>' + esc(label) + tag + '</h4>' + acts + del;
    d.appendChild(cam); d.appendChild(lib);
    d.classList.toggle('filled', !!f);
    d.classList.remove('missing');

    $$('[data-act]', d).forEach(function (b) {
      b.addEventListener('click', function () {
        buzz();
        var a = b.dataset.act;
        if (a === 'cam') cam.click();
        else if (a === 'lib') lib.click();
        else if (a === 'del') { delete photoFiles[s.key]; renderSlots(); updateCounter(); toast(t('removed')); }
      });
    });
  });
  updateCounter();
}

function takeFile(key, file) {
  if (!file) return;
  if (file.size > CFG.maxPhotoMB * 1024 * 1024) {
    setErr('#e2', t('errBig', { f: file.name, n: CFG.maxPhotoMB }));
    return;
  }
  setErr('#e2', '');
  photoFiles[key] = file;
  renderSlots();
  buzz(15);
}

function requiredMissing() {
  return SLOTS.filter(function (s) { return s.req && !photoFiles[s.key]; });
}

function updateCounter() {
  var c = $('#cPhotos'); if (!c) return;
  var have = Object.keys(photoFiles).length;
  var need = SLOTS.filter(function (s) { return s.req; }).length;
  var missing = requiredMissing().length;
  c.textContent = missing === 0 ? t('counterOk', { a: have }) : t('counterBad', { a: need - missing, b: need });
  c.className = 'counter' + (missing === 0 ? ' good' : (have ? ' bad' : ''));
}

/* ---------------- videos (unchanged, still optional) ---------------- */
var videos = [];

$('#pickVideos').addEventListener('click', function () { $('#inVideos').click(); });
$('#inVideos').addEventListener('change', function (e) { addVideos(e.target.files); e.target.value = ''; });

function addVideos(list) {
  setErr('#e2', '');
  for (var i = 0; i < list.length; i++) {
    if (videos.length >= CFG.maxVideos) { setErr('#e2', t('errVideoMax', { n: CFG.maxVideos })); break; }
    if (list[i].size > CFG.maxVideoMB * 1024 * 1024) { setErr('#e2', t('errBig', { f: list[i].name, n: CFG.maxVideoMB })); continue; }
    videos.push(list[i]);
  }
  renderVideos(); buzz();
}

function renderVideos() {
  var box = $('#thVideos');
  box.innerHTML = '';
  videos.forEach(function (f, idx) {
    var d = document.createElement('div'); d.className = 'thumb';
    var v = document.createElement('video');
    v.src = URL.createObjectURL(f); v.muted = true; v.playsInline = true; v.preload = 'metadata';
    d.appendChild(v);
    var tag = document.createElement('span');
    tag.className = 'tag'; tag.textContent = Math.round(f.size / 104857.6) / 10 + ' MB';
    d.appendChild(tag);
    var x = document.createElement('button');
    x.type = 'button'; x.className = 'x'; x.textContent = '×';
    x.addEventListener('click', function () { videos.splice(idx, 1); renderVideos(); buzz(); toast(t('removed')); });
    d.appendChild(x);
    box.appendChild(d);
  });
}

/* ============================================================
   2b. step 2 — condition: the panel diagram and the three sliders
   ============================================================ */
var COND        = CFG.cond || { parts: {}, states: {}, order: [] };
var STATE_ORDER = COND.order && COND.order.length ? COND.order : ['painted', 'accident'];
var panels      = {};           /* { hood:'painted', door_fl:'accident', … } */

function partName(key) {
  var p = COND.parts[key];
  return p ? (lang === 'ar' ? p.ar : p.en) : key;
}
function stateName(st) {
  var s = COND.states[st];
  return s ? (lang === 'ar' ? s.ar : s.en) : st;
}

/** ordinary paint work is allowed only once the customer says the car was painted */
function panelsLocked() {
  var el = $('#f').elements['paint_status'];
  return el ? (el.value === 'original') : false;
}

var STATE_FILL = { painted: '#e0a12c', accident: '#d1435b' };
var BLANK_FILL = '#f0eaed';

function syncPanels() {
  $$('#carMap .cm-part').forEach(function (el) {
    var st = panels[el.getAttribute('data-part')];
    if (st) el.setAttribute('data-state', st);
    else    el.removeAttribute('data-state');
    /* app.css owns the colour; this attribute is only the safety net for a
       stale or missing stylesheet, where an unfilled polygon renders black */
    el.setAttribute('fill', (st && STATE_FILL[st]) || BLANK_FILL);
  });

  var f = $('#panelsField');
  if (f) f.value = Object.keys(panels).length ? JSON.stringify(panels) : '';

  syncPaintLock();

  var box = $('#cmPicked');
  if (!box) return;
  var keys = Object.keys(COND.parts).filter(function (k) { return panels[k]; });
  box.innerHTML = keys.map(function (k) {
    return '<span class="s-' + esc(panels[k]) + '">' + esc(partName(k))
         + ' · ' + esc(stateName(panels[k])) + '</span>';
  }).join('');
}

function cyclePanel(key) {
  if (panelsLocked()) { toast(t('cmLocked')); return; }
  var cur = panels[key] || '';
  var i   = STATE_ORDER.indexOf(cur);
  var nxt = STATE_ORDER[i + 1];        /* '' → first → second → undefined → '' */
  if (nxt) panels[key] = nxt; else delete panels[key];
  syncPanels(); buzz();
  toast(partName(key) + (panels[key] ? ' — ' + stateName(panels[key]) : ''));
}

(function bindCarMap() {
  var map = $('#carMap');
  if (!map) return;
  map.addEventListener('click', function (ev) {
    var el = ev.target.closest ? ev.target.closest('.cm-part') : null;
    if (el) cyclePanel(el.getAttribute('data-part'));
  });
  /* keyboard: the panels are focusable, so Enter and Space must work too */
  map.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var el = ev.target.closest ? ev.target.closest('.cm-part') : null;
    if (!el) return;
    ev.preventDefault();
    cyclePanel(el.getAttribute('data-part'));
  });
  var clr = $('#cmClear');
  if (clr) clr.addEventListener('click', function () { panels = {}; syncPanels(); buzz(); });
})();

/* An accident marked on the car and “repainted only, no accident” cannot both
   be true. The diagram is the more specific statement, so it wins: the second
   answer is locked and the choice moves to the third. */
function hasAccident() {
  for (var k in panels) if (panels[k] === 'accident') return true;
  return false;
}

function syncPaintLock() {
  var acc  = hasAccident();
  var el   = $('#f').elements['paint_status'];
  var row  = $('input[name="paint_status"][value="repaint"]');
  var note = $('#paintLock');
  if (!row) return;

  row.disabled = acc;
  var box = row.closest ? row.closest('.optrow') : null;
  if (box) box.classList.toggle('locked', acc);
  if (note) note.hidden = !acc;

  if (acc && el && el.value === 'repaint') {
    var third = $('input[name="paint_status"][value="accident"]');
    if (third) { third.checked = true; syncPaint(); toast(t('cpMoved')); }
  }
}

/* ---------------- paint status ---------------- */
function syncPaint() {
  var el  = $('#f').elements['paint_status'];
  var val = el ? el.value : '';
  $$('.optrow').forEach(function (l) {
    var i = l.querySelector('input');
    l.classList.toggle('sel', !!(i && i.checked));
  });
  var wrap = $('#extentWrap');
  if (wrap) {
    var show = (val === 'repaint' || val === 'accident');
    wrap.hidden = !show;
    if (!show) setChip('paint_extent', '');
  }
  if (val === 'original' && Object.keys(panels).length) { panels = {}; syncPanels(); }
  var map = $('#carMap');
  if (map) map.style.opacity = (val === 'original') ? '.5' : '';
  setErr('#e2', '');
}
$$('input[name="paint_status"]').forEach(function (i) {
  i.addEventListener('change', function () { syncPaint(); buzz(); });
});

/* ---------------- chip pickers (extent + the three quality scales) ------- */
/* each .chips box names the hidden input it drives, so the same code serves
   the condition step and the support form without knowing about either */
function chipField(group) {
  var box = $('.chips[data-group="' + group + '"]');
  if (!box) return null;
  var id = box.getAttribute('data-field');
  return id ? $('#' + id) : null;
}
function setChip(group, value) {
  var box = $('.chips[data-group="' + group + '"]');
  if (!box) return;
  $$('.chip', box).forEach(function (b) { b.classList.toggle('on', b.dataset.value === value && value !== ''); });
  var f = chipField(group);
  if (f) f.value = value;
}
$$('.chips').forEach(function (box) {
  var group  = box.dataset.group;
  if (group === 'paint_extent') box.addEventListener('click', function () { setErr('#e2', ''); });
  var sticky = box.hasAttribute('data-required');   /* one must always stay chosen */
  box.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('.chip') : null;
    if (!b) return;
    var f   = chipField(group);
    var cur = f ? f.value : '';
    var off = (cur === b.dataset.value && !sticky);
    setChip(group, off ? '' : b.dataset.value);     /* tap again to unset */
    buzz();
  });
});

/* ============================================================
   3. shell: language, views, wizard
   ============================================================ */
function applyLang() {
  var d = document.documentElement;
  d.lang = lang; d.dir = (lang === 'ar') ? 'rtl' : 'ltr';
  $$('[data-i18n]').forEach(function (el) {
    var k = el.getAttribute('data-i18n');
    if (T[lang][k] === undefined) return;
    /* text the owner typed is never treated as HTML */
    if (OWNER_KEYS[k]) el.textContent = T[lang][k];
    else               el.innerHTML   = T[lang][k];
  });
  $$('[data-i18n-ph]').forEach(function (el) {
    var k = el.getAttribute('data-i18n-ph');
    if (T[lang][k] !== undefined) el.placeholder = T[lang][k];
  });
  /* short on purpose: the header now carries the gold mark as well, and a wide
     word here pushed the brand name into an ellipsis on a 360 px phone */
  $('#langBtn').textContent = (lang === 'ar') ? 'EN' : 'ع';
  $('#langBtn').setAttribute('aria-label', (lang === 'ar') ? 'Switch to English' : 'التبديل إلى العربية');
  $('#langField').value = lang;
  fillMakes(); fillClasses(); fillYears(); fillTrims();
  renderSlots();
  syncPanels();
  document.title = T[lang].seoTitle
                 || ((T[lang].appName || '') + (T[lang].tagline ? ' — ' + T[lang].tagline : ''));

  /* Keep the address bar honest: ?lang=en is a real, separately indexed page,
     so a shared or bookmarked link opens in the language the visitor was reading. */
  try {
    var u = new URL(window.location.href);
    if (lang === 'en') u.searchParams.set('lang', 'en'); else u.searchParams.delete('lang');
    if (u.href !== window.location.href) history.replaceState(null, '', u.href);
    var alt = document.querySelector('link[rel="canonical"]');
    if (alt) alt.href = u.origin + u.pathname + (lang === 'en' ? '?lang=en' : '');
  } catch (e) {}
}

$('#langBtn').addEventListener('click', function () {
  lang = (lang === 'ar') ? 'en' : 'ar';
  store('eyc_lang', lang);
  applyLang(); buzz();
});

/* ---------------- light / dark ---------------- */
var THEME_COLORS = { light: '#8a1538', dark: '#131013' };

function systemTheme() {
  try { return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; }
  catch (e) { return 'light'; }
}
function currentTheme() {
  var saved = store('eyc_theme');
  return (saved === 'dark' || saved === 'light') ? saved : systemTheme();
}
function applyTheme(th) {
  document.documentElement.setAttribute('data-theme', th);
  var m = document.getElementById('tc');
  if (m) m.setAttribute('content', THEME_COLORS[th] || THEME_COLORS.light);
  var b = $('#themeBtn');
  if (b) {
    b.textContent = (th === 'dark') ? '☀️' : '🌙';
    b.setAttribute('aria-label', th === 'dark' ? 'Light mode' : 'Dark mode');
  }
}
applyTheme(currentTheme());
$('#themeBtn').addEventListener('click', function () {
  var next = (document.documentElement.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
  store('eyc_theme', next);
  applyTheme(next);
  buzz();
});
try {
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
    if (!store('eyc_theme')) applyTheme(systemTheme());
  });
} catch (e) {}

var toastT;
function toast(msg) {
  var el = $('#toast');
  el.textContent = msg; el.classList.add('on');
  clearTimeout(toastT);
  toastT = setTimeout(function () { el.classList.remove('on'); }, 2600);
}
function buzz(ms) { try { if (navigator.vibrate) navigator.vibrate(ms || 10); } catch (e) {} }

function showView(name) {
  $$('.view').forEach(function (v) { v.classList.toggle('on', v.id === 'view-' + name); });
  $$('nav.bottom button').forEach(function (b) { b.classList.toggle('on', b.dataset.view === name); });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
$$('nav.bottom button').forEach(function (b) {
  b.addEventListener('click', function () { buzz(); showView(b.dataset.view); });
});
/* anything else that wants to move the visitor to another view */
$$('[data-goview]').forEach(function (b) {
  b.addEventListener('click', function () { buzz(); showView(b.dataset.goview); });
});

/* highlight the chosen retention box (older browsers have no :has()) */
function syncRetention() {
  $$('.opt label').forEach(function (l) {
    var i = l.querySelector('input');
    l.classList.toggle('sel', !!(i && i.checked));
  });
}
$$('.opt input').forEach(function (i) {
  i.addEventListener('change', function () { syncRetention(); buzz(); });
});

var step = 1;
function goStep(n) {
  if (n > step && !validateStep(step)) return;
  step = n;
  $$('.step').forEach(function (s) { s.hidden = (+s.dataset.step !== n); });
  $$('#steps i').forEach(function (i, idx) {
    i.className = (idx + 1 < n) ? 'done' : (idx + 1 === n ? 'now' : '');
  });
  window.scrollTo({ top: 0, behavior: 'smooth' });
  buzz();
}
$$('[data-go]').forEach(function (b) {
  b.addEventListener('click', function () { goStep(+b.dataset.go); });
});

function setErr(id, msg) { var el = $(id); if (el) el.textContent = msg || ''; }

function validateStep(n) {
  if (n === 1) {
    setErr('#e1', '');
    if (!getMake() || !getClass() || !$('#yearSel').value) { setErr('#e1', t('errFields')); return false; }
    /* model / trim and mileage are required as well */
    var mdl = $('#car_model');
    if (!mdl || mdl.value.trim() === '') {
      setErr('#e1', t('errModel'));
      /* the box is hidden while a trim list is on screen — send the customer
         to the dropdown they actually have to answer, not to a hidden field */
      var mfoc = (mdl && mdl.hidden) ? $('#trimSel') : mdl;
      if (mfoc) { mfoc.focus(); mfoc.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      return false;
    }
    var kmEl = $('#mileage');
    var kmVal = kmEl ? kmEl.value.replace(/[^0-9]/g, '') : '';
    if (kmVal === '') {
      setErr('#e1', t('errKm'));
      if (kmEl) { kmEl.focus(); kmEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      return false;
    }
    return true;
  }
  if (n === 2) {
    /* the paint answer is the only thing on this step we insist on —
       the diagram and the three quality scales stay optional */
    setErr('#e2', '');
    var ps = $('#f').elements['paint_status'];
    if (!ps || !ps.value) {
      setErr('#e2', t('errPaint'));
      var first = $('.optrow');
      if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return false;
    }
    /* “repainted” without saying how much of it is only half an answer, and the
       difference between a wing and the whole car matters to the price */
    if (ps.value === 'repaint' || ps.value === 'accident') {
      var ex = $('#extentField');
      if (!ex || !ex.value) {
        setErr('#e2', t('errExtent'));
        var w = $('#extentWrap');
        if (w) w.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
      }
    }
    return true;
  }
  if (n === 3) {
    setErr('#e3', '');
    var miss = requiredMissing();
    if (miss.length) {
      setErr('#e3', t('errMissing', { list: miss.map(function (s) { return lang === 'ar' ? s.ar : s.en; }).join('، ') }));
      miss.forEach(function (s) { var d = $('.slot[data-slot="' + s.key + '"]'); if (d) d.classList.add('missing'); });
      return false;
    }
    return true;
  }
  if (n === 4) {
    setErr('#e4', '');
    if ($('#cname').value.trim().length < 2) { setErr('#e4', t('errName')); return false; }
    if ($('#cphone').value.replace(/[^0-9]/g, '').length < 7) { setErr('#e4', t('errPhone')); return false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test($('#cemail').value.trim())) { setErr('#e4', t('errEmail')); return false; }
    return true;
  }
  return true;
}

/* ============================================================
   4. submit
   ============================================================ */
$('#f').addEventListener('submit', function (ev) {
  ev.preventDefault();
  if (!validateStep(1)) { goStep(1); return; }
  if (!validateStep(2)) { goStep(2); return; }
  if (!validateStep(3)) { goStep(3); return; }
  if (!validateStep(4)) return;

  var form = $('#f');
  var fd = new FormData();
  fd.append('lang', lang);
  fd.append('car_make',  getMake());
  fd.append('car_class', getClass());
  fd.append('car_model', $('#car_model').value.trim());
  fd.append('car_year',  $('#yearSel').value);
  fd.append('mileage',   $('#mileage').value.trim());
  ['registration','chassis','notes','name','phone','email'].forEach(function (k) {
    var el = form.elements[k];
    if (el) fd.append(k, el.value.trim());
  });
  fd.append('retention', (form.querySelector('input[name=retention]:checked') || {}).value || '3');

  /* step 2 — condition */
  fd.append('paint_status', (form.elements['paint_status'] || {}).value || '');
  fd.append('paint_extent', ($('#extentField') || {}).value || '');
  fd.append('panels', Object.keys(panels).length ? JSON.stringify(panels) : '');
  ['interior', 'engine', 'gearbox'].forEach(function (k) {
    var el = $('#qField_' + k);
    fd.append('q_' + k, el ? el.value : '');
  });

  SLOTS.forEach(function (s) {
    var f = photoFiles[s.key];
    if (f) fd.append('photo_' + s.key, f, s.key + '.' + (f.name.split('.').pop() || 'jpg').toLowerCase());
  });
  videos.forEach(function (f) { fd.append('videos[]', f, f.name); });

  var btn = $('#sendBtn');
  btn.disabled = true; btn.textContent = t('sending');
  sendingShow(0);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'api.php?do=submit', true);
  xhr.upload.onprogress = function (e) {
    if (e.lengthComputable) sendingShow(e.loaded / e.total);
  };
  /* the bytes are gone but the server is still saving and emailing — say so,
     rather than leaving a full ring sitting there looking stuck */
  xhr.upload.onload = function () { sendingWorking(); };
  xhr.onload = function () {
    btn.disabled = false; btn.textContent = t('send'); sendingHide();
    var r = null;
    try { r = JSON.parse(xhr.responseText); } catch (e) {}
    if (!r || !r.ok) {
      var m = t('errNet');
      if (r && r.error === 'fields') m = t('errFields');
      if (r && r.error === 'car_model') { setErr('#e1', t('errModel')); goStep(1); return; }
      if (r && r.error === 'mileage')   { setErr('#e1', t('errKm'));    goStep(1); return; }
      if (r && r.error === 'missing_slot') {
        var s = SLOTS.filter(function (x) { return x.key === r.slot; })[0];
        m = t('errMissing', { list: s ? (lang === 'ar' ? s.ar : s.en) : r.slot });
      }
      if (r && r.error === 'paint_status') { setErr('#e2', t('errPaint'));  goStep(2); return; }
      if (r && r.error === 'paint_extent') { setErr('#e2', t('errExtent')); goStep(2); return; }
      setErr('#e4', m); return;
    }
    $('#sentId').textContent = r.id;
    lastId = r.id; submitted = true;
    showView('sent'); buzz(30);
  };
  xhr.onerror = function () {
    btn.disabled = false; btn.textContent = t('send'); sendingHide();
    setErr('#e4', t('errNet'));
  };
  xhr.onabort = function () { btn.disabled = false; btn.textContent = t('send'); sendingHide(); };
  xhr.send(fd);
});

var lastId = '';
var submitted = false;

$('#goStatus').addEventListener('click', function () {
  $('#idInput').value = lastId; showView('status'); checkStatus();
});
$('#againBtn').addEventListener('click', function () {
  photoFiles = {}; videos = []; panels = {};
  renderSlots(); renderVideos();
  $('#f').reset(); $('#langField').value = lang;
  fillMakes(); fillClasses(); fillYears(); fillTrims(); syncOther();
  ['paint_extent', 'q_interior', 'q_engine', 'q_gearbox'].forEach(function (g) { setChip(g, ''); });
  syncPanels(); syncPaint();
  goStep(1); showView('form');
});

/* ---- the uploading screen ----
   RING is 2πr for r = 19, the circle drawn in index.php. */
var RING = 2 * Math.PI * 19;
var sending = null;

function sendingShow(frac) {
  sending = sending || $('#sending');
  if (!sending) return;
  sending.hidden = false;
  sending.classList.remove('work');
  var p = Math.max(0, Math.min(1, frac || 0));
  var c = $('.sring .p', sending);
  if (c) c.style.strokeDashoffset = String(RING * (1 - p));
  var pct = $('#sPct');
  if (pct) pct.textContent = Math.round(p * 100) + '%';
  var h = $('#sTitle');
  if (h) h.textContent = t('sendingTitle');
  var sub = $('#sending p');
  if (sub) sub.textContent = t('sendingWarn');
}

function sendingWorking() {
  if (!sending) return;
  sending.classList.add('work');
  var h = $('#sTitle');   if (h) h.textContent = t('sendingWork');
  var sub = $('#sending p'); if (sub) sub.textContent = t('sendingWorkSub');
}

function sendingHide() {
  if (sending) { sending.hidden = true; sending.classList.remove('work'); }
}

/* ============================================================
   4b. support — “tell us what went wrong”
   ============================================================ */
(function support() {
  var form = $('#supForm');
  if (!form) return;

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    setErr('#eSup', '');

    var msg   = $('#sMsg').value.trim();
    var email = $('#sEmail').value.trim();
    var phone = $('#sPhone').value.replace(/[^0-9]/g, '');
    var okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);

    if (msg.length < 10)            { setErr('#eSup', t('errSupMsg')); $('#sMsg').focus(); return; }
    if (!okEmail && phone.length < 7) { setErr('#eSup', t('errSupContact')); $('#sEmail').focus(); return; }

    var fd = new FormData();
    fd.append('lang', lang);
    fd.append('sup_kind', ($('#supKind') || {}).value || 'problem');
    fd.append('s_msg',   msg);
    fd.append('s_name',  $('#sName').value.trim());
    fd.append('s_email', email);
    fd.append('s_phone', $('#sPhone').value.trim());
    fd.append('s_ref',   $('#sRef').value.trim());
    fd.append('eyc_hp', $('#sHoney').value);
    /* what the visitor was looking at — turns a vague report into a fixable one */
    fd.append('s_page', location.pathname + location.search
                      + ' | step ' + step
                      + ' | make=' + (getMake() || '-') + ' class=' + (getClass() || '-')
                      + ' | ' + screen.width + 'x' + screen.height);

    var btn = $('#supBtn');
    btn.disabled = true; btn.textContent = t('supSending');

    fetch('api.php?do=support', { method: 'POST', body: fd })
      .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
      .then(function (res) {
        btn.disabled = false; btn.textContent = t('supSend');
        if (!res.j || !res.j.ok) {
          var m = t('errNet');
          if (res.j && res.j.error === 'short')    m = t('errSupMsg');
          if (res.j && res.j.error === 'contact')  m = t('errSupContact');
          if (res.s === 429)                       m = t('errSupMany');
          setErr('#eSup', m); return;
        }
        $('#supId').textContent = res.j.id || '—';
        $('#supCard').hidden = true;
        $('#supDone').hidden = false;
        if (res.j.id) idIn.value = res.j.id;
        buzz(30);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch(function () {
        btn.disabled = false; btn.textContent = t('supSend');
        setErr('#eSup', t('errNet'));
      });
  });

  /* ---- follow up with the reference number ---- */
  var idIn = $('#supIdInput');
  idIn.addEventListener('input', function (e) {
    e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
  });
  idIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); findSupport(); } });
  $('#supFindBtn').addEventListener('click', findSupport);

  function findSupport() {
    var id = idIn.value.trim().toUpperCase();
    setErr('#eSupFind', '');
    if (id.length < 6) { setErr('#eSupFind', t('errSupId')); return; }

    var box = $('#supFound');
    box.hidden = false;
    box.innerHTML = '<div class="skeleton" style="width:55%"></div><div class="skeleton"></div>';

    fetch('api.php?do=support_status&id=' + encodeURIComponent(id))
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.ok) { box.hidden = true; setErr('#eSupFind', t('errSupNotFound')); return; }
        var replied = !!(r.reply && r.reply.trim());
        var state   = replied ? t('supStReplied') : (r.seen ? t('supStSeen') : t('supStNew'));
        var h = '<div class="light"><div class="lamp ' + (replied ? 'green' : 'red') + '">'
              + (replied ? '🟢' : '🔴') + '</div><h3>' + esc(state) + '</h3></div>'
              + '<table class="kv"><tr><td>' + t('supRefNo') + '</td>'
              + '<td><b class="ltr" style="letter-spacing:3px">' + esc(r.id) + '</b></td></tr>'
              + '<tr><td>' + t('supKind') + '</td><td>' + esc(lang === 'ar' ? r.kind_ar : r.kind_en) + '</td></tr>'
              + '<tr><td>' + t('supSentOn') + '</td><td><span dir="ltr">' + fmtDate(r.created) + '</span></td></tr>'
              + '</table>'
              + '<div class="note" style="margin-top:14px"><div class="hd">' + esc(t('supYourMsg'))
              + '</div><p style="white-space:pre-line" dir="auto">' + esc(r.msg) + '</p></div>';
        if (replied) {
          h += '<div class="note" style="margin-top:12px"><div class="hd">💬 ' + esc(t('supOurReply'))
             + '</div><p style="white-space:pre-line" dir="auto">' + esc(r.reply) + '</p></div>';
        }
        box.innerHTML = h;
        buzz(20);
      })
      .catch(function () { box.hidden = true; setErr('#eSupFind', t('errNet')); });
  }

  $('#supAgain').addEventListener('click', function () {
    form.reset();
    setChip('sup_kind', 'problem');
    setErr('#eSup', '');
    $('#supDone').hidden = true;
    $('#supCard').hidden = false;
  });
})();

/* ============================================================
   5. status
   ============================================================ */
$('#idInput').addEventListener('input', function (e) {
  e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
});
$('#idInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); checkStatus(); } });
$('#checkBtn').addEventListener('click', checkStatus);

function checkStatus() {
  var id = $('#idInput').value.trim().toUpperCase();
  setErr('#eStatus', '');
  if (id.length !== 6) { setErr('#eStatus', t('errId')); return; }

  var btn = $('#checkBtn');
  btn.disabled = true; btn.textContent = t('checking');
  $('#resultCard').hidden = false;
  $('#resultBody').innerHTML = '<div class="skeleton" style="width:60%"></div><div class="skeleton"></div><div class="skeleton" style="width:80%"></div>';

  fetch('api.php?do=status&id=' + encodeURIComponent(id))
    .then(function (r) { return r.json(); })
    .then(function (r) {
      btn.disabled = false; btn.textContent = t('check');
      if (!r.ok) { $('#resultCard').hidden = true; setErr('#eStatus', t('errNotFound')); return; }
      renderResult(r); buzz(20);
    })
    .catch(function () {
      btn.disabled = false; btn.textContent = t('check');
      $('#resultCard').hidden = true; setErr('#eStatus', t('errNet'));
    });
}

function fmtDate(iso) {
  try {
    return new Date(iso).toLocaleDateString(lang === 'ar' ? 'ar-QA' : 'en-GB',
      { year: 'numeric', month: 'short', day: 'numeric' });
  } catch (e) { return iso; }
}

function renderResult(r) {
  var done = (r.status === 'done');
  var note = lang === 'ar' ? (r.note_ar || '') : (r.note_en || r.note_ar || '');
  var h = '<div class="light">'
        + '<div class="lamp ' + (done ? 'green' : 'red') + '">' + (done ? '🟢' : '🔴') + '</div>'
        + '<h3>' + t(done ? 'ready' : 'underReview') + '</h3>'
        + '<p>' + t(done ? 'readySub' : 'underReviewSub') + '</p></div>';

  if (done && (r.price_text || r.price)) {
    var money = r.price_text || r.price;
    h += '<div class="pricebox"><small>' + t(r.price_range ? 'priceRange' : 'priceLabel') + '</small><b'
       + (r.price_range ? ' class="rng"' : '') + '>'
       + '<span class="ltr">' + esc(money) + '</span> <span style="font-size:17px;font-weight:600">'
       + esc(CFG.currency[lang]) + '</span></b></div>';
  }
  if (note) {
    h += '<div class="note"><div class="hd">💬 ' + esc(t('noteLabel')) + '</div><p>' + esc(note) + '</p></div>';
  }

  /* The report, reachable with the code alone. Someone who mistyped his email
     address — or whose mail was filed as spam — can still get it from here. */
  if (done && r.pdf) {
    h += '<p style="margin:16px 0 0"><a class="btn gold" style="display:block;text-align:center;text-decoration:none"'
       + ' href="' + esc(r.pdf) + '">📄 ' + esc(t('pdfDownload')) + '</a></p>';
  }

  h += '<table class="kv" style="margin-top:16px">'
     + '<tr><td>' + t('idLabel')    + '</td><td><b class="ltr" style="letter-spacing:3px">' + esc(r.id) + '</b></td></tr>'
     + '<tr><td>' + t('carLabel')   + '</td><td><span dir="ltr">' + esc(r.car) + '</span></td></tr>'
     + '<tr><td>' + t('sentAt')     + '</td><td><span dir="ltr">' + fmtDate(r.created) + '</span>' + '</td></tr>'
     + '<tr><td>' + t('filesCount') + '</td><td><span class="ltr">' + r.photos + '</span> ' + t('photosW') + (r.videos ? ' · ' + r.videos + ' ' + t('videosW') : '') + '</td></tr>'
     + '<tr><td>' + t('filesUntil') + '</td><td><span dir="ltr">' + fmtDate(r.expires) + '</span>' + '</td></tr>'
     + '</table>';

  $('#resultBody').innerHTML = h;
}

/* ============================================================
   6. boot
   ============================================================ */
/* The language the server already rendered wins — it came from ?lang= in the
   address, which is what a shared link, a search result and a crawler all use.
   Only when the address says nothing do we fall back to the last choice. */
if (CFG.lang === 'en' || CFG.lang === 'ar') {
  lang = CFG.lang;
} else {
  var saved = store('eyc_lang');
  if (saved === 'en' || saved === 'ar') lang = saved;
}

buildSlots();
applyLang();
syncOther();
syncRetention();
syncPaint();
syncPaintLock();
setChip('sup_kind', 'problem');

if (CFG.prefillSupport && CFG.prefillSupport.length >= 6) {
  var si = $('#supIdInput');
  if (si) { si.value = CFG.prefillSupport; showView('support'); $('#supFindBtn').click(); }
} else if (CFG.prefillId && CFG.prefillId.length === 6) {
  $('#idInput').value = CFG.prefillId;
  showView('status');
  checkStatus();
}

window.addEventListener('beforeunload', function (e) {
  var uploading = sending && !sending.hidden;
  if (uploading || (Object.keys(photoFiles).length && !submitted)) { e.preventDefault(); e.returnValue = ''; }
});

if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); });
}
})();
