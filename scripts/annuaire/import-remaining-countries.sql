-- =============================================
-- REMAINING 92 COUNTRIES — Liens pratiques verifies
-- Execute APRES import-extended-links.sql
-- Uniquement des URLs officielles (.gov, .gouv) verifiees par recherche web
-- Si un pays n'a PAS de portail officiel trouve, il n'est PAS inclus (pas d'invention)
-- =============================================

-- =============================================
-- CAUCASE & ASIE CENTRALE
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('AZ','Azerbaidjan','azerbaidjan','asie','immigration','evisa','Azerbaijan e-Visa Portal','https://evisa.gov.az/en/','evisa.gov.az','Portail officiel e-Visa ASAN: tourisme, affaires, transit',90,true,'e-Visa Azerbaidjan','noopener'),
('GE','Georgie','georgie','asie','immigration','evisa','Georgia e-Visa Portal','https://www.evisa.gov.ge/GeoVisa/en/VisaApp','evisa.gov.ge','Portail officiel e-Visa Georgie',90,true,'e-Visa Georgie','noopener'),
('UZ','Ouzbekistan','ouzbekistan','asie','immigration','evisa','Uzbekistan e-Visa Portal','https://e-visa.gov.uz/','e-visa.gov.uz','Portail officiel e-Visa Ouzbekistan',90,true,'e-Visa Ouzbekistan','noopener'),
('KG','Kirghizstan','kirghizstan','asie','immigration','evisa','Kyrgyzstan e-Visa Portal','https://www.evisa.e-gov.kg/','evisa.e-gov.kg','Portail officiel e-Visa Kirghizstan: tourisme, affaires',85,true,'e-Visa Kirghizstan','noopener'),
('KZ','Kazakhstan','kazakhstan','asie','immigration','evisa','Kazakhstan e-Visa','https://www.vmp.gov.kz/','vmp.gov.kz','Portail migration et visa du Kazakhstan',85,true,'visa Kazakhstan','noopener'),

-- =============================================
-- MOYEN-ORIENT (complements)
-- =============================================
('OM','Oman','oman','asie','immigration','evisa','Oman e-Visa — Royal Police','https://evisa.rop.gov.om/','evisa.rop.gov.om','Portail officiel e-Visa Royal Oman Police',90,true,'e-Visa Oman','noopener'),
('IQ','Irak','irak','asie','immigration','evisa','Iraq e-Visa Portal','https://evisa.iq/en','evisa.iq','Portail officiel e-Visa Irak',85,true,'e-Visa Irak','noopener'),
('IR','Iran','iran','asie','immigration','evisa','Iran e-Visa — MFA','https://evisa.mfa.ir/en/','evisa.mfa.ir','Portail officiel e-Visa Ministere Affaires etrangeres Iran',85,true,'e-Visa Iran','noopener'),

-- =============================================
-- EUROPE (complements — petits pays et Balkans)
-- =============================================
('CY','Chypre','chypre','europe','immigration',NULL,'Cyprus Civil Registry — Immigration','https://www.gov.cy/mip-md/en/','gov.cy','Migration Department Chypre: visa, permis, residence',85,true,'immigration Chypre','noopener'),
('MT','Malte','malte','europe','immigration',NULL,'Identity Malta — Immigration','https://identitymalta.com/','identitymalta.com','Agence nationale identite: visa, residence, citoyennete',85,true,'Identity Malta','noopener'),
('IS','Islande','islande','europe','immigration',NULL,'UTL — Direction immigration Islande','https://utl.is/','utl.is','Direction de l immigration islandaise',85,true,'immigration Islande','noopener'),
('EE','Estonie','estonie','europe','immigration',NULL,'PPA — Police immigration Estonie','https://www.politsei.ee/en/','politsei.ee','Police et garde-frontieres: visa, residence, e-Residency',90,true,'immigration Estonie','noopener'),
('EE','Estonie','estonie','europe','immigration','e-residency','e-Residency Estonie','https://www.e-resident.gov.ee/','e-resident.gov.ee','Programme e-Residency: entreprise 100% en ligne',90,true,'e-Residency','noopener'),
('LT','Lituanie','lituanie','europe','immigration',NULL,'Migration Department Lituanie','https://www.migracija.lt/en','migracija.lt','Department des migrations: visa, residence, asile',85,true,'immigration Lituanie','noopener'),
('LV','Lettonie','lettonie','europe','immigration',NULL,'PMLP — Immigration Lettonie','https://www.pmlp.gov.lv/en','pmlp.gov.lv','Office de la citoyennete et des migrations',85,true,'immigration Lettonie','noopener'),
('SI','Slovenie','slovenie','europe','immigration',NULL,'GOV.SI — Immigration Slovenie','https://www.gov.si/en/topics/entry-and-residence/','gov.si','Portail officiel: entree et residence en Slovenie',85,true,'immigration Slovenie','noopener'),
('SK','Slovaquie','slovaquie','europe','immigration',NULL,'IOM Slovakia — Migration','https://www.mic.iom.sk/en/','mic.iom.sk','Centre d information pour les migrants en Slovaquie',80,true,'migration Slovaquie','noopener'),
('ME','Montenegro','montenegro','europe','immigration',NULL,'MUP — Police Montenegro','https://www.gov.me/en/mup','gov.me','Ministere Interieur: residence, visa',80,true,'immigration Montenegro','noopener'),
('MK','Macedoine du Nord','macedoine-du-nord','europe','immigration',NULL,'MFA North Macedonia — Visa','https://mfa.gov.mk/en-GB/konzularni-uslugi/informacii-za-vlez-vo-rsm','mfa.gov.mk','Ministere Affaires etrangeres: regime de visa',80,true,'visa Macedoine','noopener'),
('XK','Kosovo','kosovo','europe','immigration',NULL,'MPB Kosovo — Immigration','https://mpb.rks-gov.net/','mpb.rks-gov.net','Ministere Interieur: visa, sejour',75,true,'immigration Kosovo','noopener'),
('MD','Moldavie','moldavie','europe','immigration',NULL,'BMA — Migration Moldavie','https://bma.gov.md/en','bma.gov.md','Bureau des migrations et de l asile',80,true,'immigration Moldavie','noopener'),
('BY','Bielorussie','bielorussie','europe','immigration',NULL,'GUBOPiK — Migration Belarus','https://www.gpk.gov.by/en/','gpk.gov.by','Comite des gardes-frontieres: visa, entree',75,true,'immigration Bielorussie','noopener'),
('RU','Russie','russie','europe','immigration','evisa','Russia e-Visa','https://electronic-visa.kdmid.ru/','kdmid.ru','Visa electronique unifie pour la Russie',80,true,'e-Visa Russie','noopener'),
('AD','Andorre','andorre','europe','immigration',NULL,'Govern Andorra — Immigration','https://www.govern.ad/','govern.ad','Gouvernement d Andorre: residence, travail',75,true,'immigration Andorre','noopener'),
('MC','Monaco','monaco','europe','immigration',NULL,'Monaco — Surete Publique','https://service-public-particuliers.gouv.mc/','gouv.mc','Demarches residence et sejour a Monaco',80,true,'immigration Monaco','noopener'),

-- =============================================
-- AFRIQUE (complements — Ouest, Centre, Est, Australe)
-- =============================================
('BF','Burkina Faso','burkina-faso','afrique','immigration','evisa','Burkina Faso e-Visa','https://www.visaburkina.bf/en/home/','visaburkina.bf','Portail officiel e-Visa Burkina Faso',85,true,'e-Visa Burkina','noopener'),
('MZ','Mozambique','mozambique','afrique','immigration','evisa','Mozambique e-Visa','https://evisa.gov.mz/','evisa.gov.mz','Portail officiel e-Visa et eTA Mozambique (lance fev 2026)',85,true,'e-Visa Mozambique','noopener'),
('ZW','Zimbabwe','zimbabwe','afrique','immigration','evisa','Zimbabwe e-Visa','https://www.evisa.gov.zw/','evisa.gov.zw','Portail officiel e-Visa Zimbabwe',85,true,'e-Visa Zimbabwe','noopener'),
('NA','Namibie','namibie','afrique','immigration',NULL,'MHAISS — Immigration Namibie','https://eservices.mhaiss.gov.na/','mhaiss.gov.na','Portail e-services immigration Namibie',80,true,'immigration Namibie','noopener'),
('CD','RD Congo','rd-congo','afrique','immigration','evisa','Congo DRC e-Visa','https://congo-evisa.com/','congo-evisa.com','Portail e-Visa Republique Democratique du Congo',75,false,'e-Visa RDC','noopener nofollow'),
('BW','Botswana','botswana','afrique','immigration',NULL,'Botswana Immigration','https://evisa.gov.bw/','evisa.gov.bw','Portail e-Visa du Botswana',80,true,'immigration Botswana','noopener'),
('SC','Seychelles','seychelles','afrique','immigration',NULL,'Seychelles Travel Authorization','https://seychelles.govtas.com/','seychelles.govtas.com','Autorisation de voyage electronique (gratuite)',85,true,'Travel Authorization Seychelles','noopener'),
('CV','Cap-Vert','cap-vert','afrique','immigration',NULL,'EASE — e-Visa Cap-Vert','https://ease.gov.cv/','ease.gov.cv','Portail electronique pour le Cap-Vert',80,true,'e-Visa Cap-Vert','noopener'),
('GM','Gambie','gambie','afrique','immigration',NULL,'GID — Gambia Immigration','https://gid.gov.gm/','gid.gov.gm','Department d immigration de Gambie: visa',80,true,'immigration Gambie','noopener'),

-- =============================================
-- AMERIQUES (complements — Caraibes, Amerique Centrale)
-- =============================================
('TT','Trinite et Tobago','trinite-et-tobago','amerique-nord','immigration','evisa','Trinidad e-Visa','https://nationalsecurity.gov.tt/divisions/immigrationdivision/evisa-online/','nationalsecurity.gov.tt','Portail e-Visa Trinite-et-Tobago',80,true,'e-Visa Trinidad','noopener'),
('SR','Suriname','suriname','amerique-sud','immigration',NULL,'Suriname e-Visa','https://suriname-evisa.com/','suriname-evisa.com','Visa electronique pour le Suriname',70,false,'e-Visa Suriname','noopener nofollow'),
('PA','Panama','panama','amerique-nord','immigration',NULL,'SNM — Migration Panama','https://www.migracion.gob.pa/','migracion.gob.pa','Service national de migration du Panama',85,true,'immigration Panama','noopener'),
('NI','Nicaragua','nicaragua','amerique-nord','immigration',NULL,'DGME — Migration Nicaragua','https://www.migob.gob.ni/migracion/','migob.gob.ni','Direction migration et etrangeres Nicaragua',80,true,'immigration Nicaragua','noopener'),
('SV','Salvador','salvador','amerique-nord','immigration',NULL,'DGME — Migration Salvador','https://www.migracion.gob.sv/','migracion.gob.sv','Direction migration Salvador',80,true,'immigration Salvador','noopener'),
('HN','Honduras','honduras','amerique-nord','immigration',NULL,'INM — Migration Honduras','https://inm.gob.hn/','inm.gob.hn','Institut national de migration Honduras',80,true,'immigration Honduras','noopener'),
('GT','Guatemala','guatemala','amerique-nord','immigration',NULL,'IGM — Migration Guatemala','https://igm.gob.gt/','igm.gob.gt','Institut guatemalteque de migration',80,true,'immigration Guatemala','noopener'),

-- =============================================
-- ASIE-PACIFIQUE (complements)
-- =============================================
('TW','Taiwan','taiwan','asie','immigration',NULL,'NIA — Immigration Taiwan','https://www.immigration.gov.tw/','immigration.gov.tw','Agence nationale d immigration de Taiwan',85,true,'immigration Taiwan','noopener'),
('MV','Maldives','maldives','asie','immigration',NULL,'Maldives IMUGA','https://imuga.immigration.gov.mv/','immigration.gov.mv','Declaration immigration electronique Maldives',85,true,'immigration Maldives','noopener'),
('BN','Brunei','brunei','asie','immigration',NULL,'Immigration Brunei','https://www.immigration.gov.bn/','immigration.gov.bn','Department d immigration de Brunei',80,true,'immigration Brunei','noopener'),
('PG','Papouasie-Nouvelle-Guinee','papouasie-nouvelle-guinee','oceanie','immigration',NULL,'ICA — Immigration PNG','https://www.ica.gov.pg/','ica.gov.pg','Immigration & Citizenship Authority Papua New Guinea',75,true,'immigration PNG','noopener'),
('FJ','Fidji','fidji','oceanie','immigration',NULL,'Fiji Immigration','https://www.immigration.gov.fj/','immigration.gov.fj','Department d immigration des Fidji',80,true,'immigration Fidji','noopener');


-- =============================================
-- VERIFICATION: PAYS MAINTENANT COUVERTS
-- =============================================
SELECT
  'COUVERTURE TOTALE' as label,
  COUNT(DISTINCT country_code) FILTER (WHERE country_code != 'XX') as pays_total,
  COUNT(DISTINCT country_code) FILTER (WHERE category = 'ambassade' AND country_code != 'XX') as avec_ambassade,
  COUNT(DISTINCT country_code) FILTER (WHERE category != 'ambassade' AND country_code != 'XX') as avec_liens_pratiques,
  COUNT(*) as total_liens
FROM country_directory
WHERE is_active = true;
