-- =====================================================
-- FILL ALL MISSING CATEGORIES FOR ALL COUNTRIES
-- Uses international organization URLs as fallback
-- Covers all 196 countries × 13 categories
-- Safe: ON CONFLICT DO NOTHING on unique index
-- =====================================================

DO $$
DECLARE
    r RECORD;
    cat TEXT;
    v_url TEXT;
    v_title TEXT;
    v_domain TEXT;
    v_anchor TEXT;
    v_trans JSON;
    v_is_official BOOLEAN;
    v_score SMALLINT;
    v_emerg TEXT;
    cats TEXT[] := ARRAY[
        'banque','communaute','education','emploi','fiscalite',
        'immigration','juridique','logement','sante','telecom','transport'
    ];
BEGIN
    -- Loop through all distinct countries
    FOR r IN
        SELECT DISTINCT ON (country_code)
               country_code, country_name, country_slug, continent,
               (SELECT MIN(NULLIF(TRIM(emergency_number), ''))
                FROM country_directory cd2
                WHERE cd2.country_code = cd.country_code
                  AND emergency_number ~ '^[0-9]+$') AS emergency_number
        FROM country_directory cd
        WHERE country_code NOT IN ('XX', 'EU')
          AND is_active = true
        ORDER BY country_code, id
    LOOP
        v_emerg := COALESCE(r.emergency_number, '112');

        -- ── 11 non-emergency, non-ambassade categories ──
        FOREACH cat IN ARRAY cats LOOP
            CONTINUE WHEN EXISTS (
                SELECT 1 FROM country_directory
                WHERE country_code = r.country_code
                  AND category    = cat
                  AND nationality_code IS NULL
                  AND is_active   = true
            );

            v_is_official := false;
            v_score       := 75;

            CASE cat

            WHEN 'banque' THEN
                v_url    := 'https://www.worldbank.org/en/country/' || lower(r.country_code);
                v_title  := 'Banque Mondiale — ' || r.country_name;
                v_domain := 'worldbank.org';
                v_anchor := 'banque-finance-' || r.country_slug;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'World Bank — ' || r.country_name),
                    'es', json_build_object('title', 'Banco Mundial — ' || r.country_name),
                    'ar', json_build_object('title', 'البنك الدولي — ' || r.country_name),
                    'de', json_build_object('title', 'Weltbank — ' || r.country_name),
                    'pt', json_build_object('title', 'Banco Mundial — ' || r.country_name),
                    'ch', json_build_object('title', '世界银行 — ' || r.country_name),
                    'hi', json_build_object('title', 'विश्व बैंक — ' || r.country_name),
                    'ru', json_build_object('title', 'Мировой банк — ' || r.country_name)
                );

            WHEN 'communaute' THEN
                v_url    := 'https://www.internations.org/' || r.country_slug || '-expats';
                v_title  := 'Communauté Expats — ' || r.country_name;
                v_domain := 'internations.org';
                v_anchor := 'communaute-expat-' || r.country_slug;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Expat Community — ' || r.country_name),
                    'es', json_build_object('title', 'Comunidad Expat — ' || r.country_name),
                    'ar', json_build_object('title', 'مجتمع المغتربين — ' || r.country_name),
                    'de', json_build_object('title', 'Expat-Community — ' || r.country_name),
                    'pt', json_build_object('title', 'Comunidade Expat — ' || r.country_name),
                    'ch', json_build_object('title', '外籍人士社区 — ' || r.country_name),
                    'hi', json_build_object('title', 'प्रवासी समुदाय — ' || r.country_name),
                    'ru', json_build_object('title', 'Сообщество экспатов — ' || r.country_name)
                );

            WHEN 'education' THEN
                v_url    := 'https://uis.unesco.org/country/' || upper(r.country_code);
                v_title  := 'Éducation — ' || r.country_name;
                v_domain := 'uis.unesco.org';
                v_anchor := 'education-scolarite-' || r.country_slug;
                v_is_official := true;
                v_score  := 85;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Education — ' || r.country_name),
                    'es', json_build_object('title', 'Educación — ' || r.country_name),
                    'ar', json_build_object('title', 'التعليم — ' || r.country_name),
                    'de', json_build_object('title', 'Bildung — ' || r.country_name),
                    'pt', json_build_object('title', 'Educação — ' || r.country_name),
                    'ch', json_build_object('title', '教育 — ' || r.country_name),
                    'hi', json_build_object('title', 'शिक्षा — ' || r.country_name),
                    'ru', json_build_object('title', 'Образование — ' || r.country_name)
                );

            WHEN 'emploi' THEN
                v_url    := 'https://ilostat.ilo.org/data/country-profiles/' || lower(r.country_code) || '/';
                v_title  := 'Emploi et Travail — ' || r.country_name;
                v_domain := 'ilostat.ilo.org';
                v_anchor := 'emploi-travail-' || r.country_slug;
                v_is_official := true;
                v_score  := 85;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Employment — ' || r.country_name),
                    'es', json_build_object('title', 'Empleo — ' || r.country_name),
                    'ar', json_build_object('title', 'العمالة — ' || r.country_name),
                    'de', json_build_object('title', 'Beschäftigung — ' || r.country_name),
                    'pt', json_build_object('title', 'Emprego — ' || r.country_name),
                    'ch', json_build_object('title', '就业 — ' || r.country_name),
                    'hi', json_build_object('title', 'रोजगार — ' || r.country_name),
                    'ru', json_build_object('title', 'Занятость — ' || r.country_name)
                );

            WHEN 'fiscalite' THEN
                v_url    := 'https://tradingeconomics.com/' || r.country_slug || '/tax-revenue';
                v_title  := 'Fiscalité et Impôts — ' || r.country_name;
                v_domain := 'tradingeconomics.com';
                v_anchor := 'fiscalite-impots-' || r.country_slug;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Taxation — ' || r.country_name),
                    'es', json_build_object('title', 'Fiscalidad — ' || r.country_name),
                    'ar', json_build_object('title', 'الضرائب — ' || r.country_name),
                    'de', json_build_object('title', 'Steuern — ' || r.country_name),
                    'pt', json_build_object('title', 'Tributação — ' || r.country_name),
                    'ch', json_build_object('title', '税务 — ' || r.country_name),
                    'hi', json_build_object('title', 'कराधान — ' || r.country_name),
                    'ru', json_build_object('title', 'Налогообложение — ' || r.country_name)
                );

            WHEN 'immigration' THEN
                v_url    := 'https://www.iom.int/countries/' || r.country_slug;
                v_title  := 'Immigration et Visas — ' || r.country_name;
                v_domain := 'iom.int';
                v_anchor := 'immigration-visa-' || r.country_slug;
                v_is_official := true;
                v_score  := 85;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Immigration & Visas — ' || r.country_name),
                    'es', json_build_object('title', 'Inmigración y Visas — ' || r.country_name),
                    'ar', json_build_object('title', 'الهجرة والتأشيرات — ' || r.country_name),
                    'de', json_build_object('title', 'Einwanderung & Visa — ' || r.country_name),
                    'pt', json_build_object('title', 'Imigração e Vistos — ' || r.country_name),
                    'ch', json_build_object('title', '移民与签证 — ' || r.country_name),
                    'hi', json_build_object('title', 'आव्रजन और वीजा — ' || r.country_name),
                    'ru', json_build_object('title', 'Иммиграция и визы — ' || r.country_name)
                );

            WHEN 'juridique' THEN
                v_url    := 'https://www.ohchr.org/en/countries/' || r.country_slug;
                v_title  := 'Informations Juridiques — ' || r.country_name;
                v_domain := 'ohchr.org';
                v_anchor := 'juridique-droit-' || r.country_slug;
                v_is_official := true;
                v_score  := 85;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Legal Information — ' || r.country_name),
                    'es', json_build_object('title', 'Información Legal — ' || r.country_name),
                    'ar', json_build_object('title', 'المعلومات القانونية — ' || r.country_name),
                    'de', json_build_object('title', 'Rechtliche Informationen — ' || r.country_name),
                    'pt', json_build_object('title', 'Informação Jurídica — ' || r.country_name),
                    'ch', json_build_object('title', '法律信息 — ' || r.country_name),
                    'hi', json_build_object('title', 'कानूनी जानकारी — ' || r.country_name),
                    'ru', json_build_object('title', 'Юридическая информация — ' || r.country_name)
                );

            WHEN 'logement' THEN
                v_url    := 'https://globalpropertyguide.com/locations/' || r.country_slug;
                v_title  := 'Logement et Immobilier — ' || r.country_name;
                v_domain := 'globalpropertyguide.com';
                v_anchor := 'logement-immobilier-' || r.country_slug;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Housing & Real Estate — ' || r.country_name),
                    'es', json_build_object('title', 'Vivienda e Inmuebles — ' || r.country_name),
                    'ar', json_build_object('title', 'الإسكان والعقارات — ' || r.country_name),
                    'de', json_build_object('title', 'Wohnen & Immobilien — ' || r.country_name),
                    'pt', json_build_object('title', 'Habitação e Imóveis — ' || r.country_name),
                    'ch', json_build_object('title', '住房与房地产 — ' || r.country_name),
                    'hi', json_build_object('title', 'आवास और रियल एस्टेट — ' || r.country_name),
                    'ru', json_build_object('title', 'Жильё и недвижимость — ' || r.country_name)
                );

            WHEN 'sante' THEN
                v_url    := 'https://www.who.int/countries/' || lower(r.country_code);
                v_title  := 'Santé — ' || r.country_name;
                v_domain := 'who.int';
                v_anchor := 'sante-medical-' || r.country_slug;
                v_is_official := true;
                v_score  := 90;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Health — ' || r.country_name),
                    'es', json_build_object('title', 'Salud — ' || r.country_name),
                    'ar', json_build_object('title', 'الصحة — ' || r.country_name),
                    'de', json_build_object('title', 'Gesundheit — ' || r.country_name),
                    'pt', json_build_object('title', 'Saúde — ' || r.country_name),
                    'ch', json_build_object('title', '健康 — ' || r.country_name),
                    'hi', json_build_object('title', 'स्वास्थ्य — ' || r.country_name),
                    'ru', json_build_object('title', 'Здравоохранение — ' || r.country_name)
                );

            WHEN 'telecom' THEN
                v_url    := 'https://www.itu.int/itu-d/ict/country/' || lower(r.country_code) || '/';
                v_title  := 'Télécommunications — ' || r.country_name;
                v_domain := 'itu.int';
                v_anchor := 'telecom-internet-' || r.country_slug;
                v_is_official := true;
                v_score  := 85;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Telecommunications — ' || r.country_name),
                    'es', json_build_object('title', 'Telecomunicaciones — ' || r.country_name),
                    'ar', json_build_object('title', 'الاتصالات — ' || r.country_name),
                    'de', json_build_object('title', 'Telekommunikation — ' || r.country_name),
                    'pt', json_build_object('title', 'Telecomunicações — ' || r.country_name),
                    'ch', json_build_object('title', '电信 — ' || r.country_name),
                    'hi', json_build_object('title', 'दूरसंचार — ' || r.country_name),
                    'ru', json_build_object('title', 'Телекоммуникации — ' || r.country_name)
                );

            WHEN 'transport' THEN
                v_url    := 'https://transportpolicy.net/country/' || r.country_slug;
                v_title  := 'Transport et Mobilité — ' || r.country_name;
                v_domain := 'transportpolicy.net';
                v_anchor := 'transport-mobilite-' || r.country_slug;
                v_trans  := json_build_object(
                    'en', json_build_object('title', 'Transport & Travel — ' || r.country_name),
                    'es', json_build_object('title', 'Transporte y Viajes — ' || r.country_name),
                    'ar', json_build_object('title', 'النقل والسفر — ' || r.country_name),
                    'de', json_build_object('title', 'Transport & Reisen — ' || r.country_name),
                    'pt', json_build_object('title', 'Transporte e Viagens — ' || r.country_name),
                    'ch', json_build_object('title', '交通运输 — ' || r.country_name),
                    'hi', json_build_object('title', 'परिवहन और यात्रा — ' || r.country_name),
                    'ru', json_build_object('title', 'Транспорт и путешествия — ' || r.country_name)
                );

            END CASE;

            INSERT INTO country_directory
                (country_code, country_name, country_slug, continent,
                 nationality_code, nationality_name,
                 category, title, url, domain, anchor_text, translations,
                 emergency_number, trust_score, is_official, rel_attribute, is_active)
            VALUES
                (r.country_code, r.country_name, r.country_slug, r.continent,
                 NULL, NULL,
                 cat, v_title, v_url, v_domain, v_anchor, v_trans,
                 v_emerg, v_score, v_is_official, 'noopener', true)
            ON CONFLICT DO NOTHING;

        END LOOP; -- end foreach cat

        -- ── Add urgences if completely missing ──
        IF NOT EXISTS (
            SELECT 1 FROM country_directory
            WHERE country_code = r.country_code
              AND category = 'urgences'
              AND is_active = true
        ) THEN
            INSERT INTO country_directory
                (country_code, country_name, country_slug, continent,
                 nationality_code, nationality_name,
                 category, title, url, domain, anchor_text, translations,
                 emergency_number, trust_score, is_official, rel_attribute, is_active)
            VALUES
                (r.country_code, r.country_name, r.country_slug, r.continent,
                 NULL, NULL,
                 'urgences',
                 'Urgences (numéro général) — ' || r.country_name,
                 'tel:' || v_emerg, 'tel',
                 'urgences-general-' || r.country_slug,
                 json_build_object(
                     'en', json_build_object('title', 'Emergency Number — ' || r.country_name),
                     'es', json_build_object('title', 'Número de Emergencia — ' || r.country_name),
                     'ar', json_build_object('title', 'رقم الطوارئ — ' || r.country_name),
                     'de', json_build_object('title', 'Notrufnummer — ' || r.country_name),
                     'pt', json_build_object('title', 'Número de Emergência — ' || r.country_name),
                     'ch', json_build_object('title', '紧急电话 — ' || r.country_name),
                     'hi', json_build_object('title', 'आपातकालीन नंबर — ' || r.country_name),
                     'ru', json_build_object('title', 'Номер экстренной службы — ' || r.country_name)
                 ),
                 v_emerg, 95, true, 'noopener', true)
            ON CONFLICT DO NOTHING;
        END IF;

    END LOOP; -- end for r (countries)

    -- ── Special case: LI (Liechtenstein) needs at least one ambassade entry ──
    IF NOT EXISTS (SELECT 1 FROM country_directory WHERE country_code = 'LI' AND category = 'ambassade') THEN
        INSERT INTO country_directory
            (country_code, country_name, country_slug, continent,
             nationality_code, nationality_name,
             category, title, url, domain, anchor_text, translations,
             emergency_number, trust_score, is_official, rel_attribute, is_active)
        VALUES
            ('LI', 'Liechtenstein', 'liechtenstein', 'europe',
             'FR', 'France',
             'ambassade',
             'Consulat de France — Liechtenstein',
             'https://li.ambafrance.org', 'li.ambafrance.org',
             'consulat-france-liechtenstein',
             '{"en":{"title":"French Consulate — Liechtenstein"},"es":{"title":"Consulado Francés — Liechtenstein"},"ar":{"title":"القنصلية الفرنسية — ليختنشتاين"},"de":{"title":"Französisches Konsulat — Liechtenstein"},"pt":{"title":"Consulado Francês — Liechtenstein"},"ch":{"title":"法国领事馆 — 列支敦士登"},"hi":{"title":"फ्रांसीसी वाणिज्य दूतावास — लिक्टेंस्टीन"},"ru":{"title":"Французское консульство — Лихтенштейн"}}',
             '112', 90, true, 'noopener', true)
        ON CONFLICT DO NOTHING;
    END IF;

    -- ── Special case: WS (Samoa) needs at least one ambassade entry ──
    IF NOT EXISTS (SELECT 1 FROM country_directory WHERE country_code = 'WS' AND category = 'ambassade') THEN
        INSERT INTO country_directory
            (country_code, country_name, country_slug, continent,
             nationality_code, nationality_name,
             category, title, url, domain, anchor_text, translations,
             emergency_number, trust_score, is_official, rel_attribute, is_active)
        VALUES
            ('WS', 'Samoa', 'samoa', 'oceanie',
             'FR', 'France',
             'ambassade',
             'Ambassade de France — Samoa',
             'https://ws.ambafrance.org', 'ws.ambafrance.org',
             'ambassade-france-samoa',
             '{"en":{"title":"French Embassy — Samoa"},"es":{"title":"Embajada Francesa — Samoa"},"ar":{"title":"السفارة الفرنسية — ساموا"},"de":{"title":"Französische Botschaft — Samoa"},"pt":{"title":"Ambassade de France — Samoa"},"ch":{"title":"法国大使馆 — 萨摩亚"},"hi":{"title":"फ्रांसीसी दूतावास — समोआ"},"ru":{"title":"Французское посольство — Самоа"}}',
             '994', 90, true, 'noopener', true)
        ON CONFLICT DO NOTHING;
    END IF;

    RAISE NOTICE 'Done. Inserted missing categories for all countries.';
END;
$$;
