<?php

/**
 * Email provider classification used by EmailDomainMatchService.
 *
 * - generic:   Free/consumer providers — never considered as "domain mismatch"
 *              because a school, association, freelance etc. can legitimately
 *              use gmail.com with a professional website.
 *
 * - junk:      Clearly non-real emails (placeholders, dev domains, disposable
 *              services). These get cleared from contacts on detection.
 *
 * Lists are grouped by region for easier maintenance. Add new providers by
 * editing the appropriate section — no code change needed.
 *
 * @see app/Services/EmailDomainMatchService.php
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Generic consumer email providers
    |--------------------------------------------------------------------------
    |
    | When a contact's email domain is in this list, the service considers
    | the email valid regardless of the website domain. This handles the
    | very common case of "SMB uses a free email provider".
    |
    */
    'generic' => [

        // ─── Global / multi-country ───
        'gmail.com', 'googlemail.com',
        'yahoo.com', 'ymail.com', 'rocketmail.com',
        'hotmail.com', 'outlook.com', 'live.com', 'msn.com', 'passport.com',
        'icloud.com', 'me.com', 'mac.com',
        'aol.com',
        'mail.com', 'email.com',
        'protonmail.com', 'proton.me', 'pm.me',
        'tutanota.com', 'tutanota.de', 'tuta.io', 'tutamail.com',
        'fastmail.com', 'fastmail.fm',
        'zoho.com', 'zohomail.com',
        'yandex.com', 'yandex.ru', 'ya.ru',

        // ─── France ───
        'yahoo.fr', 'hotmail.fr', 'outlook.fr', 'live.fr', 'msn.fr',
        'free.fr', 'orange.fr', 'sfr.fr', 'laposte.net',
        'wanadoo.fr', 'numericable.fr', 'club-internet.fr',
        'bbox.fr', 'bouyguestelecom.fr', 'neuf.fr', 'aliceadsl.fr',
        'cegetel.net', 'noos.fr', '9online.fr',
        'gmx.fr',

        // ─── UK / Ireland ───
        'yahoo.co.uk', 'hotmail.co.uk', 'outlook.co.uk', 'live.co.uk',
        'btinternet.com', 'btopenworld.com',
        'virginmedia.com', 'blueyonder.co.uk',
        'sky.com', 'ntlworld.com', 'talktalk.net',
        'mail.co.uk',

        // ─── Germany / Austria / Switzerland ───
        'gmx.com', 'gmx.de', 'gmx.at', 'gmx.ch', 'gmx.net',
        'web.de', 't-online.de', 'freenet.de',
        'posteo.de', 'mailbox.org',
        'bluewin.ch', 'sunrise.ch',

        // ─── Italy ───
        'libero.it', 'tiscali.it', 'virgilio.it',
        'alice.it', 'tin.it', 'fastwebnet.it',
        'yahoo.it', 'hotmail.it', 'outlook.it', 'live.it',

        // ─── Spain / Portugal ───
        'yahoo.es', 'hotmail.es', 'outlook.es', 'live.es',
        'telefonica.net', 'terra.es',
        'sapo.pt', 'iol.pt',

        // ─── Netherlands / Belgium ───
        'kpnmail.nl', 'planet.nl', 'home.nl', 'ziggo.nl', 'xs4all.nl',
        'skynet.be', 'telenet.be', 'scarlet.be',

        // ─── Nordics ───
        'yahoo.se', 'hotmail.se', 'live.se',
        'spray.se', 'comhem.se', 'telia.com',
        'hotmail.dk', 'yahoo.dk',

        // ─── Eastern Europe ───
        'mail.ru', 'bk.ru', 'inbox.ru', 'list.ru', 'internet.ru',
        'rambler.ru', 'lenta.ru',
        'seznam.cz', 'centrum.cz', 'post.cz',
        'wp.pl', 'o2.pl', 'onet.pl', 'interia.pl',
        'abv.bg', 'mail.bg',

        // ─── Asia: China ───
        'qq.com', '163.com', '126.com', '139.com',
        'sina.com', 'sina.cn', 'sohu.com',
        'foxmail.com', 'aliyun.com',

        // ─── Asia: Japan / Korea ───
        'yahoo.co.jp', 'nifty.com', 'ezweb.ne.jp', 'docomo.ne.jp',
        'naver.com', 'daum.net', 'hanmail.net', 'hotmail.co.kr',

        // ─── Asia: India / SEA ───
        'rediffmail.com', 'rediff.com',
        'yahoo.co.in', 'hotmail.co.in',
        'yahoo.com.sg', 'hotmail.com.sg',
        'yahoo.com.ph', 'yahoo.com.vn',

        // ─── Middle East / Africa ───
        'yahoo.com.tr', 'hotmail.com.tr', 'mynet.com',
        'yahoo.com.au', 'bigpond.com', 'optusnet.com.au',

        // ─── Americas ───
        'yahoo.com.br', 'hotmail.com.br', 'uol.com.br', 'bol.com.br',
        'terra.com.br', 'ig.com.br', 'globo.com',
        'yahoo.com.mx', 'hotmail.com.mx', 'live.com.mx',
        'yahoo.ca', 'hotmail.ca', 'rogers.com', 'sympatico.ca',

    ],

    /*
    |--------------------------------------------------------------------------
    | Junk / disposable / placeholder domains
    |--------------------------------------------------------------------------
    |
    | Emails with these domains are treated as invalid and the service
    | proactively clears them from contacts.
    |
    */
    'junk' => [
        // Dev / localhost
        'flywheel.local', 'localhost', 'local.dev', 'lvh.me',
        // Placeholder / example
        'example.com', 'example.org', 'example.net',
        'test.com', 'test.org', 'test.net',
        'domain.com', 'email.com', 'yoursite.com', 'yourdomain.com',
        'monsite.fr', 'votresite.fr', 'monmail.fr',
        // Hosted platform artifacts (when WordPress/Wix generate a fake admin@...)
        'sentry.io',
        'wixpress.com', 'wix.com',
        'squarespace.com',
        'wordpress.com',
        // Known disposable / temporary email services
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'temp-mail.org',
        'throwaway.email', '10minutemail.com', 'trashmail.com',
        'yopmail.com', 'maildrop.cc', 'sharklasers.com',
        'getnada.com', 'dispostable.com', 'mintemail.com',
    ],

];
