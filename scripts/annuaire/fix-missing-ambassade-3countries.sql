-- Fix: CF, GD, GM missing ambassade entries
INSERT INTO country_directory
    (country_code, country_name, country_slug, continent,
     nationality_code, nationality_name,
     category, title, url, domain, anchor_text, translations,
     emergency_number, trust_score, is_official, rel_attribute, is_active)
VALUES
('CF','Rép. centrafricaine','republique-centrafricaine','afrique',
 'FR','France',
 'ambassade','Ambassade de France — Rép. centrafricaine',
 'https://cf.ambafrance.org','cf.ambafrance.org',
 'ambassade-france-republique-centrafricaine',
 '{"en":{"title":"French Embassy — Central African Republic"},"es":{"title":"Embajada Francesa — Rep. Centroafricana"},"ar":{"title":"السفارة الفرنسية — أفريقيا الوسطى"},"de":{"title":"Französische Botschaft — Zentralafrika"},"pt":{"title":"Embaixada Francesa — Rep. Centro-Africana"},"ch":{"title":"法国大使馆 — 中非共和国"},"hi":{"title":"फ्रांसीसी दूतावास — मध्य अफ्रीका"},"ru":{"title":"Французское посольство — ЦАР"}}',
 '117',90,true,'noopener',true),

('GD','Grenade','grenade','amerique-nord',
 'FR','France',
 'ambassade','Ambassade de France — Grenade',
 'https://gd.ambafrance.org','gd.ambafrance.org',
 'ambassade-france-grenade',
 '{"en":{"title":"French Embassy — Grenada"},"es":{"title":"Embajada Francesa — Granada"},"ar":{"title":"السفارة الفرنسية — غرينادا"},"de":{"title":"Französische Botschaft — Grenada"},"pt":{"title":"Embaixada Francesa — Granada"},"ch":{"title":"法国大使馆 — 格林纳达"},"hi":{"title":"फ्रांसीसी दूतावास — ग्रेनेडा"},"ru":{"title":"Французское посольство — Гренада"}}',
 '911',90,true,'noopener',true),

('GM','Gambie','gambie','afrique',
 'FR','France',
 'ambassade','Ambassade de France — Gambie',
 'https://gm.ambafrance.org','gm.ambafrance.org',
 'ambassade-france-gambie',
 '{"en":{"title":"French Embassy — Gambia"},"es":{"title":"Embajada Francesa — Gambia"},"ar":{"title":"السفارة الفرنسية — غامبيا"},"de":{"title":"Französische Botschaft — Gambia"},"pt":{"title":"Embaixada Francesa — Gâmbia"},"ch":{"title":"法国大使馆 — 冈比亚"},"hi":{"title":"फ्रांसीसी दूतावास — गांबिया"},"ru":{"title":"Французское посольство — Гамбия"}}',
 '117',90,true,'noopener',true)

ON CONFLICT DO NOTHING;

-- Verify all 3 now have 13 categories
SELECT country_code, country_name, COUNT(DISTINCT category) as cat_count
FROM country_directory
WHERE country_code IN ('CF','GD','GM') AND is_active=true
GROUP BY country_code, country_name;
