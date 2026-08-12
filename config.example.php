<?php
/* ============================================================
   config.example.php

   Copy this to config.php on the server and fill in the three
   placeholders. config.php itself is deliberately NOT in git:
   it holds the mailbox password and the link signing secret in
   clear text, and a secret committed once stays in the history
   for ever.
   ============================================================ */
/* ============================================================
   ثـمـــن مــوتــرك  /  Evaluate Your Car
   thamanmotorak.com
   config.php — technical settings only.

   Everything the client sees (page text, links, phone, email,
   Terms, Privacy) is edited from the admin control panel:
        admin.php  →  لوحة المحتوى / Site content
   and is stored in data/content.json — not here.
   ============================================================ */

return [

    /* --- Fallback only. The real notification address is set in the
           control panel (Contact & links → “Email that receives new
           requests”). This value is used if that one is ever blank. --- */
    'owner_email'      => 'contact@thamanmotorak.com',
    'owner_whatsapp'   => '+97430322225',   // phase 2 (WhatsApp Business API) — NOT used by
                                           // any customer-facing page: the number
                                           // shown to customers is contactPhone in
                                           // the control panel, and clearing it there
                                           // removes it everywhere.

    /* --- The address the site sends mail FROM.
           It must exist on the host (mailbox or alias). --- */
    'from_email'       => 'contact@thamanmotorak.com',
    'from_name'        => 'ثـمـــن مــوتــرك | Evaluate Your Car',

    /* ============================================================
       HOW MAIL IS SENT
       'php'  = PHP's built-in mail() — easiest, but Hotmail/Gmail
                often drop it silently because SPF does not match.
       'smtp' = log in to the real mailbox and send properly.
                RECOMMENDED. Fill in the smtp block below.
       Test either one at:  /mailtest.php
       ============================================================ */
    'mail_method'      => 'smtp',

    'smtp' => [
        /* Hostinger:  smtp.hostinger.com   port 465   ssl
           IMPORTANT — 'user' must be a REAL mailbox, not an alias.
           contact@thamanmotorak.com is an alias of admin@thamanmotorak.com,
           so we log in as admin@ and send as contact@ ('from_email' above).
           If contact@ is later created as its own mailbox, put it here. */
        'host'   => 'smtp.hostinger.com',
        'port'   => 465,
        'secure' => 'ssl',                  // tls | ssl | none
        'user'   => 'admin@thamanmotorak.com',
        'pass'   => 'PUT-THE-MAILBOX-PASSWORD-HERE',
        'timeout'=> 12,                     // seconds; if SMTP stalls we fall back to mail()
    ],

    /* Show the technical mail-health warning inside the owner's panel.
       Leave this false: it is a developer's message and it alarms Khalid.
       The same warning is always visible on mailtest.php and dnscheck.php. */
    'tech_banner'      => false,

    /* Send a copy of every customer email to the owner as well (bcc-style). */
    'copy_owner'       => false,   // was true. It sent the owner a second, nearly
                                   // identical copy of the customer's confirmation —
                                   // duplicate messages score badly, and the owner
                                   // already gets the full new-request notification.

    /* ============================================================
       ADMIN SIGN-IN
       These two are only the STARTING values. The moment the password
       is changed from the panel (🔐 Security) the real one is stored
       hashed in data/admin.json and the line below stops being used.
       Delete data/admin.json to fall back to these again — that is the
       way back in if everything else is lost.
       ============================================================ */
    'admin_user'       => 'admin',
    'admin_password'   => 'change-me-on-first-login',

    /* Where “I forgot the password” sends its one-time link.
       Editable from the panel; these are the fallback. */
    'admin_recovery'   => [
        'Khalid5535@hotmail.com',
        'admin@thamanmotorak.com',
    ],

    /* --- Secret used to sign the gallery links sent by email.
           Change it once; changing it later invalidates old links. --- */
    'link_secret'      => 'PUT-A-LONG-RANDOM-STRING-HERE',

    /* --- Upload rules --- */
    'min_photos'       => 5,
    'max_photos'       => 8,
    'email_photo_px'   => 1000,   // inline copies in the email are resized to this
    'email_photo_q'    => 70,
    'max_videos'       => 2,
    'max_photo_mb'     => 12,
    'max_video_mb'     => 60,

    /* --- Retention options offered to the customer (days) --- */
    'retention_days'   => [3, 7],

    /* --- Currency shown with the valuation --- */
    'currency_ar'      => 'ريال قطري',
    'currency_en'      => 'QAR',

    /* --- Free now, paid later. Flip to true when the client approves. --- */
    'paid_mode'        => false,
    'price_ar'         => '100 ريال',
    'price_en'         => 'QAR 100',

    /* --- Local time used on the admin screens --- */
    'timezone'         => 'Asia/Qatar',

    /* --- Search engines. This is now only the fallback: the live switch
           is in the panel under 🔎 Google & search. --- */
    'noindex'          => false,
];
