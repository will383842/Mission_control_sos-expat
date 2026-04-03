-- =============================================
-- LIENS PRATIQUES MONDE COMPLET — PARTIE 2
-- ~120 pays manquants dans import-practical-links.sql
-- Execute APRES import-practical-links.sql
-- 2026-03-31
-- =============================================

-- =============================================
-- EUROPE MANQUANTS
-- =============================================

-- BULGARIE (BG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BG','Bulgarie','bulgarie','europe','immigration',NULL,'MVR — Immigration Bulgarie','https://www.mvr.bg/en','mvr.bg','Ministère de l intérieur: visa, résidence, naturalisation',90,true,'immigration Bulgarie','noopener','112'),
('BG','Bulgarie','bulgarie','europe','emploi',NULL,'Jobs.bg','https://www.jobs.bg/','jobs.bg','1er site emploi en Bulgarie',75,false,'Jobs.bg','noopener nofollow','112'),
('BG','Bulgarie','bulgarie','europe','logement',NULL,'Imot.bg — Immobilier','https://www.imot.bg/','imot.bg','Portail immobilier bulgare',75,false,'Imot.bg','noopener nofollow','112');

-- CROATIE (HR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('HR','Croatie','croatie','europe','immigration',NULL,'MUP — Immigration Croatie','https://mup.gov.hr/en','mup.gov.hr','Ministère intérieur: visa, séjour, résidence',90,true,'immigration Croatie','noopener','112'),
('HR','Croatie','croatie','europe','emploi',NULL,'Moj-posao.net','https://www.moj-posao.net/','moj-posao.net','1er site emploi en Croatie',75,false,'Moj-posao','noopener nofollow','112'),
('HR','Croatie','croatie','europe','logement',NULL,'Njuskalo — Immobilier','https://www.njuskalo.hr/nekretnine','njuskalo.hr','Portail petites annonces et immobilier croate',75,false,'Njuskalo','noopener nofollow','112');

-- ROUMANIE (RO)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('RO','Roumanie','roumanie','europe','immigration',NULL,'IGI — Immigration Roumanie','https://igi.mai.gov.ro/en/','igi.mai.gov.ro','Inspectorat général de l immigration',90,true,'immigration Roumanie','noopener','112'),
('RO','Roumanie','roumanie','europe','emploi',NULL,'eJobs Roumanie','https://www.ejobs.ro/','ejobs.ro','1er site emploi en Roumanie',80,false,'eJobs','noopener nofollow','112'),
('RO','Roumanie','roumanie','europe','logement',NULL,'Imobiliare.ro','https://www.imobiliare.ro/','imobiliare.ro','1er portail immobilier roumain',80,false,'Imobiliare.ro','noopener nofollow','112');

-- UKRAINE (UA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('UA','Ukraine','ukraine','europe','immigration',NULL,'DMSU — Immigration Ukraine','https://dmsu.gov.ua/en/','dmsu.gov.ua','État des migrations: visa, résidence, citoyenneté',90,true,'immigration Ukraine','noopener','112'),
('UA','Ukraine','ukraine','europe','emploi',NULL,'Robota.ua','https://robota.ua/','robota.ua','1er site emploi en Ukraine',80,false,'Robota.ua','noopener nofollow','112'),
('UA','Ukraine','ukraine','europe','logement',NULL,'DOM.RIA','https://dom.ria.com/','dom.ria.com','Portail immobilier ukrainien',75,false,'DOM.RIA','noopener nofollow','112');

-- RUSSIE (RU)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('RU','Russie','russie','europe','immigration',NULL,'MVD — Immigration Russie','https://www.mvd.ru/','mvd.ru','Ministère intérieur Russie (MVD): visa, enregistrement, permis séjour',90,true,'immigration Russie','noopener','112'),
('RU','Russie','russie','europe','emploi',NULL,'HeadHunter (hh.ru)','https://hh.ru/','hh.ru','1er site emploi en Russie: 1M+ offres',85,false,'HeadHunter','noopener nofollow','112'),
('RU','Russie','russie','europe','logement',NULL,'CIAN — Immobilier','https://www.cian.ru/','cian.ru','1er portail immobilier russe',80,false,'CIAN','noopener nofollow','112');

-- SERBIE (RS)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('RS','Serbie','serbie','europe','immigration',NULL,'MUP — Immigration Serbie','https://www.mup.gov.rs/wps/portal/en','mup.gov.rs','Ministère intérieur: visa, séjour, résidence',90,true,'immigration Serbie','noopener','112'),
('RS','Serbie','serbie','europe','emploi',NULL,'Infostud — Posao.infostud','https://poslovi.infostud.com/','infostud.com','1er site emploi en Serbie',75,false,'Infostud','noopener nofollow','112'),
('RS','Serbie','serbie','europe','logement',NULL,'Halo oglasi — Immobilier','https://www.halooglasi.com/','halooglasi.com','Portail petites annonces et immobilier serbe',75,false,'Halo oglasi','noopener nofollow','112');

-- SLOVAQUIE (SK)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SK','Slovaquie','slovaquie','europe','immigration',NULL,'MVSR — Immigration Slovaquie','https://www.minv.sk/?residing-in-sr','minv.sk','Ministère intérieur: résidence, visa, naturalisation',90,true,'immigration Slovaquie','noopener','112'),
('SK','Slovaquie','slovaquie','europe','emploi',NULL,'Profesia.sk','https://www.profesia.sk/','profesia.sk','1er site emploi en Slovaquie',80,false,'Profesia.sk','noopener nofollow','112');

-- SLOVÉNIE (SI)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SI','Slovenie','slovenie','europe','immigration',NULL,'Gov.si — Immigration','https://www.gov.si/en/topics/residence-and-work-permits/','gov.si','Portail officiel: permis séjour et travail',90,true,'immigration Slovenie','noopener','112'),
('SI','Slovenie','slovenie','europe','emploi',NULL,'MojeDelo.com','https://www.mojedelo.com/','mojedelo.com','1er site emploi en Slovénie',75,false,'MojeDelo','noopener nofollow','112');

-- ESTONIE (EE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('EE','Estonie','estonie','europe','immigration',NULL,'PPA — Police et Garde-frontière','https://www.politsei.ee/en/','politsei.ee','Résidence, visa, e-Résidence estonienne',90,true,'immigration Estonie','noopener','112'),
('EE','Estonie','estonie','europe','immigration','eresidence','e-Résidence Estonie','https://e-resident.gov.ee/','e-resident.gov.ee','Résidence numérique pour entrepreneurs monde entier',90,true,'e-Residency Estonie','noopener','112'),
('EE','Estonie','estonie','europe','emploi',NULL,'CV Keskus','https://www.cvkeskus.ee/','cvkeskus.ee','Site emploi estonien',75,false,'CV Keskus','noopener nofollow','112');

-- LETTONIE (LV)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('LV','Lettonie','lettonie','europe','immigration',NULL,'PMLP — Citoyenneté et Migration','https://www.pmlp.gov.lv/en/','pmlp.gov.lv','Séjour, résidence, citoyenneté',90,true,'immigration Lettonie','noopener','112'),
('LV','Lettonie','lettonie','europe','emploi',NULL,'CV Market Latvia','https://www.cvmarket.lv/','cvmarket.lv','Site emploi letton',75,false,'CV Market','noopener nofollow','112');

-- LITUANIE (LT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('LT','Lituanie','lituanie','europe','immigration',NULL,'Migracija.lt — Immigration','https://www.migracija.lt/en/','migracija.lt','Département de l immigration lituanien',90,true,'immigration Lituanie','noopener','112'),
('LT','Lituanie','lituanie','europe','emploi',NULL,'CV Online Lithuania','https://www.cvonline.lt/','cvonline.lt','Site emploi lituanien',75,false,'CV Online','noopener nofollow','112');

-- CHYPRE (CY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('CY','Chypre','chypre','europe','immigration',NULL,'MOI — Immigration Chypre','https://www.moi.gov.cy/moi/crmd/crmd.nsf/index_en/index_en','moi.gov.cy','Dept. des archives et migrations de Chypre',90,true,'immigration Chypre','noopener','112'),
('CY','Chypre','chypre','europe','logement',NULL,'Bazaraki — Immobilier','https://www.bazaraki.com/real-estate/','bazaraki.com','1er portail petites annonces chypriote',75,false,'Bazaraki','noopener nofollow','112');

-- MALTE (MT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MT','Malte','malte','europe','immigration',NULL,'Identita — Immigration Malte','https://identita.gov.mt/','identita.gov.mt','Agence identité et résidence: visa, séjour',90,true,'immigration Malte','noopener','112'),
('MT','Malte','malte','europe','emploi',NULL,'Keepmeposted.com.mt','https://www.keepmeposted.com.mt/','keepmeposted.com.mt','Site emploi maltais',70,false,'Keepmeposted','noopener nofollow','112');

-- ISLANDE (IS)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('IS','Islande','islande','europe','immigration',NULL,'UTL — Immigration Islande','https://utl.is/index.php/en','utl.is','Directorate of Immigration Islande',90,true,'immigration Islande','noopener','112'),
('IS','Islande','islande','europe','emploi',NULL,'Vinnumalastofnun — Emploi','https://www.vmst.is/english/','vmst.is','Direction emploi islandaise',90,true,'emploi Islande','noopener','112');

-- GÉORGIE (GE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('GE','Georgie','georgie','europe','immigration',NULL,'NAPR — Immigration Géorgie','https://www.napr.gov.ge/en','napr.gov.ge','Agence nationale registre public: séjour, résidence',85,true,'immigration Georgie','noopener','112'),
('GE','Georgie','georgie','europe','emploi',NULL,'Jobs.ge','https://www.jobs.ge/','jobs.ge','Site emploi géorgien',70,false,'Jobs.ge','noopener nofollow','112');

-- ARMÉNIE (AM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('AM','Armenie','armenie','europe','immigration',NULL,'Migration — Arménie','https://migration.gov.am/en','migration.gov.am','Service migration: visa, séjour, résidence',85,true,'immigration Armenie','noopener','112'),
('AM','Armenie','armenie','europe','emploi',NULL,'HR.am','https://www.hr.am/','hr.am','Site emploi arménien',70,false,'HR.am','noopener nofollow','112');

-- AZERBAÏDJAN (AZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('AZ','Azerbaidjan','azerbaidjan','europe','immigration',NULL,'e-Visa Azerbaïdjan','https://evisa.gov.az/','evisa.gov.az','Visa électronique officiel pour l Azerbaïdjan',90,true,'e-Visa Azerbaidjan','noopener','112'),
('AZ','Azerbaidjan','azerbaidjan','europe','emploi',NULL,'Jobsearch.az','https://www.jobsearch.az/','jobsearch.az','Site emploi azerbaïdjanais',70,false,'Jobsearch.az','noopener nofollow','112');

-- BIÉLORUSSIE (BY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BY','Bielorussie','bielorussie','europe','immigration',NULL,'MVD — Migration Biélorussie','https://mvd.gov.by/ru/structure/gupim','mvd.gov.by','Dpt. citoyenneté et migration',85,true,'immigration Bielorussie','noopener','112'),
('BY','Bielorussie','bielorussie','europe','emploi',NULL,'Rabota.by','https://www.rabota.by/','rabota.by','Site emploi biélorusse',70,false,'Rabota.by','noopener nofollow','112');

-- BOSNIE-HERZÉGOVINE (BA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BA','Bosnie-Herzegovine','bosnie-herzegovine','europe','immigration',NULL,'SPS — Service protection étrangers','https://www.sps.gov.ba/','sps.gov.ba','Service pour les affaires des étrangers',85,true,'immigration Bosnie','noopener','112'),
('BA','Bosnie-Herzegovine','bosnie-herzegovine','europe','emploi',NULL,'MojPosao.ba','https://www.mojposao.ba/','mojposao.ba','Site emploi bosnien',70,false,'MojPosao','noopener nofollow','112');

-- ALBANIE (AL)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('AL','Albanie','albanie','europe','immigration',NULL,'e-Albania — Portail gouvernemental','https://e-albania.al/','e-albania.al','Portail services publics: visa, séjour, résidence',85,true,'immigration Albanie','noopener','112'),
('AL','Albanie','albanie','europe','emploi',NULL,'Punesia.al','https://www.punesia.al/','punesia.al','Site emploi albanais',70,false,'Punesia.al','noopener nofollow','112');

-- MOLDAVIE (MD)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MD','Moldavie','moldavie','europe','immigration',NULL,'MAI — Immigration Moldavie','https://www.mai.gov.md/en','mai.gov.md','Ministère des affaires intérieures: migration',85,true,'immigration Moldavie','noopener','112'),
('MD','Moldavie','moldavie','europe','emploi',NULL,'Rabota.md','https://rabota.md/','rabota.md','Site emploi moldave',70,false,'Rabota.md','noopener nofollow','112');

-- MONTÉNÉGRO (ME)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('ME','Montenegro','montenegro','europe','immigration',NULL,'GOV.ME — Immigration','https://www.gov.me/en/article/residence-and-work-of-foreign-nationals','gov.me','Séjour et travail des étrangers au Monténégro',85,true,'immigration Montenegro','noopener','112'),
('ME','Montenegro','montenegro','europe','emploi',NULL,'MojPosao.me','https://www.mojposao.me/','mojposao.me','Site emploi monténégrin',70,false,'MojPosao.me','noopener nofollow','112');

-- MACÉDOINE DU NORD (MK)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MK','Macedoine du Nord','macedoine-du-nord','europe','immigration',NULL,'MVR — Immigration Macédoine','https://www.mvr.gov.mk/en','mvr.gov.mk','Ministère intérieur: visa, séjour, résidence',85,true,'immigration Macedoine','noopener','112'),
('MK','Macedoine du Nord','macedoine-du-nord','europe','emploi',NULL,'Vrabotuvanje.com.mk','https://www.vrabotuvanje.com.mk/','vrabotuvanje.com.mk','Site emploi macédonien',70,false,'Vrabotuvanje','noopener nofollow','112');

-- KOSOVO (XK)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('XK','Kosovo','kosovo','europe','immigration',NULL,'Migracioni — Immigration Kosovo','https://www.migracioni.rks-gov.net/en','rks-gov.net','Département migration: visa, résidence',85,true,'immigration Kosovo','noopener','112'),
('XK','Kosovo','kosovo','europe','emploi',NULL,'Kosovajob.com','https://www.kosovajob.com/','kosovajob.com','Site emploi au Kosovo',70,false,'Kosovajob','noopener nofollow','112');

-- ANDORRE (AD)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('AD','Andorre','andorre','europe','immigration',NULL,'Govern.ad — Immigration','https://www.govern.ad/afers-exteriors/immigracio','govern.ad','Résidence, immigration, passeport Andorre',90,true,'immigration Andorre','noopener','112'),
('AD','Andorre','andorre','europe','emploi',NULL,'SAED — Emploi Andorre','https://www.saed.ad/','saed.ad','Service emploi d Andorre',85,true,'emploi Andorre','noopener','112');

-- MONACO (MC)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MC','Monaco','monaco','europe','immigration',NULL,'Monaco.gouv.mc — Séjour','https://monservicepublic.gouv.mc/themes/travailler-et-resider','gouv.mc','Séjour, résidence, travailler à Monaco',95,true,'résidence Monaco','noopener','112'),
('MC','Monaco','monaco','europe','emploi',NULL,'ANPE Monaco','https://www.emploi.gouv.mc/','emploi.gouv.mc','Agence nationale emploi Monaco',90,true,'emploi Monaco','noopener','112');

-- SAINT-MARIN (SM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SM','Saint-Marin','saint-marin','europe','immigration',NULL,'Segreteria di Stato — Séjour','https://www.esteri.sm/en/','esteri.sm','Séjour et résidence à Saint-Marin',90,true,'résidence Saint-Marin','noopener','112');

-- LIECHTENSTEIN (LI)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('LI','Liechtenstein','liechtenstein','europe','immigration',NULL,'LLV — Ausländerbehörde','https://www.llv.li/en/state/offices/amt-fuer-migration-und-passamt','llv.li','Office migration et passeports Liechtenstein',90,true,'immigration Liechtenstein','noopener','112'),
('LI','Liechtenstein','liechtenstein','europe','emploi',NULL,'Jobs.li','https://www.jobs.li/','jobs.li','Site emploi au Liechtenstein',75,false,'Jobs.li','noopener nofollow','112');

-- =============================================
-- AMÉRIQUES MANQUANTS
-- =============================================

-- GUATEMALA (GT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('GT','Guatemala','guatemala','amerique-nord','immigration',NULL,'IGM — Migracion Guatemala','https://igm.gob.gt/','igm.gob.gt','Instituto Guatemalteco de Migración',90,true,'migration Guatemala','noopener','110'),
('GT','Guatemala','guatemala','amerique-nord','emploi',NULL,'Trabajando.com Guatemala','https://www.trabajando.com.gt/','trabajando.com.gt','Site emploi guatémaltèque',70,false,'Trabajando GT','noopener nofollow','110');

-- BELIZE (BZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BZ','Belize','belize','amerique-nord','immigration',NULL,'Immigration Belize','https://immigration.gov.bz/','immigration.gov.bz','Département immigration Belize: visa, résidence',85,true,'immigration Belize','noopener','911');

-- HONDURAS (HN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('HN','Honduras','honduras','amerique-nord','immigration',NULL,'DGME — Migracion Honduras','https://dgme.gob.hn/','dgme.gob.hn','Dirección General de Migración Honduras',85,true,'migration Honduras','noopener','911');

-- EL SALVADOR (SV)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SV','El Salvador','el-salvador','amerique-nord','immigration',NULL,'DGME — Migracion El Salvador','https://www.transparencia.gob.sv/institutions/dgme','gob.sv','Dirección General de Migración El Salvador',85,true,'migration El Salvador','noopener','911');

-- NICARAGUA (NI)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('NI','Nicaragua','nicaragua','amerique-nord','immigration',NULL,'DGM — Migracion Nicaragua','https://www.migob.gob.ni/','migob.gob.ni','Dirección General de Migración Nicaragua',85,true,'migration Nicaragua','noopener','118');

-- PANAMA (PA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('PA','Panama','panama','amerique-nord','immigration',NULL,'SNM — Migracion Panama','https://www.migracion.gob.pa/','migracion.gob.pa','Servicio Nacional de Migración: visa, résidence',90,true,'migration Panama','noopener','911'),
('PA','Panama','panama','amerique-nord','emploi',NULL,'CompuTrabajo Panama','https://www.computrabajo.com.pa/','computrabajo.com.pa','Site emploi au Panama',75,false,'CompuTrabajo PA','noopener nofollow','911');

-- CUBA (CU)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('CU','Cuba','cuba','amerique-nord','immigration',NULL,'MINREX — Visas Cuba','https://www.minrex.gob.cu/en','minrex.gob.cu','Ministère Relations Extérieures: visa, séjour Cuba',85,true,'visa Cuba','noopener','106');

-- JAMAÏQUE (JM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('JM','Jamaique','jamaique','amerique-nord','immigration',NULL,'NIMS — Immigration Jamaïque','https://nims.gov.jm/','nims.gov.jm','National Identification and Registration Authority',85,true,'immigration Jamaique','noopener','119'),
('JM','Jamaique','jamaique','amerique-nord','emploi',NULL,'CaribbeanJobs.com','https://www.caribbeanjobs.com/','caribbeanjobs.com','Offres emploi Jamaïque et Caraïbes',70,false,'CaribbeanJobs','noopener nofollow','119');

-- HAÏTI (HT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('HT','Haiti','haiti','amerique-nord','immigration',NULL,'DGI — Direction Générale Immigration','https://www.dgsn.gouv.ht/','dgsn.gouv.ht','Direction immigration et émigration Haïti',80,true,'immigration Haiti','noopener','114');

-- BAHAMAS (BS)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BS','Bahamas','bahamas','amerique-nord','immigration',NULL,'Immigration Bahamas','https://www.immigration.gov.bs/','immigration.gov.bs','Département immigration Bahamas',85,true,'immigration Bahamas','noopener','919');

-- TRINITÉ-ET-TOBAGO (TT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('TT','Trinite-et-Tobago','trinite-et-tobago','amerique-nord','immigration',NULL,'Ministry of National Security — Immigration','https://www.immigration.gov.tt/','immigration.gov.tt','Immigration Trinité-et-Tobago',85,true,'immigration Trinidad','noopener','999');

-- BARBADE (BB)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BB','Barbade','barbade','amerique-nord','immigration',NULL,'Immigration Barbados','https://www.immigration.gov.bb/','immigration.gov.bb','Département immigration Barbade',85,true,'immigration Barbados','noopener','211');

-- ANTIGUA-ET-BARBUDA (AG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('AG','Antigua-et-Barbuda','antigua-et-barbuda','amerique-nord','immigration',NULL,'Immigration Antigua','https://immigration.gov.ag/','immigration.gov.ag','Immigration Antigua-et-Barbuda',85,true,'immigration Antigua','noopener','911');

-- DOMINIQUE (DM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('DM','Dominique','dominique','amerique-nord','immigration',NULL,'Immigration Dominica','https://immigration.gov.dm/','immigration.gov.dm','Citoyenneté par investissement + résidence',85,true,'immigration Dominica','noopener','999');

-- GRENADE (GD)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('GD','Grenade','grenade','amerique-nord','immigration',NULL,'Immigration Grenada','https://www.immigration.gov.gd/','immigration.gov.gd','Immigration Grenade: visa, résidence, citoyenneté',85,true,'immigration Grenada','noopener','911');

-- SAINT-CHRISTOPHE (KN), SAINTE-LUCIE (LC), SAINT-VINCENT (VC)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('KN','Saint-Christophe-et-Nievès','saint-christophe','amerique-nord','immigration',NULL,'SKN Govt — Immigration','https://www.gov.kn/','gov.kn','Gouvernement Saint-Kitts: immigration, citoyenneté',80,true,'immigration St-Kitts','noopener','911'),
('LC','Sainte-Lucie','sainte-lucie','amerique-nord','immigration',NULL,'Immigration Saint Lucia','https://stlucia.gov.lc/immigration/','stlucia.gov.lc','Immigration Sainte-Lucie: séjour, résidence',80,true,'immigration Ste-Lucie','noopener','999'),
('VC','Saint-Vincent','saint-vincent','amerique-nord','immigration',NULL,'Immigration SVG','https://www.immigration.gov.vc/','immigration.gov.vc','Immigration Saint-Vincent-et-les-Grenadines',80,true,'immigration St-Vincent','noopener','999');

-- BOLIVIE (BO)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BO','Bolivie','bolivie','amerique-sud','immigration',NULL,'SENAMIG — Migracion Bolivia','https://senamig.gob.bo/','senamig.gob.bo','Servicio Nacional de Migración Bolivia',85,true,'migration Bolivie','noopener','110'),
('BO','Bolivie','bolivie','amerique-sud','emploi',NULL,'CompuTrabajo Bolivia','https://www.computrabajo.com.bo/','computrabajo.com.bo','Site emploi bolivien',70,false,'CompuTrabajo BO','noopener nofollow','110');

-- ÉQUATEUR (EC)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('EC','Equateur','equateur','amerique-sud','immigration',NULL,'Cancilleria Ecuador — Visa','https://www.cancilleria.gob.ec/visas/','cancilleria.gob.ec','Ministère relations extérieures: visa, résidence',90,true,'visa Equateur','noopener','911'),
('EC','Equateur','equateur','amerique-sud','emploi',NULL,'Multitrabajos Ecuador','https://www.multitrabajos.com/','multitrabajos.com','Site emploi en Équateur',70,false,'Multitrabajos EC','noopener nofollow','911');

-- PARAGUAY (PY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('PY','Paraguay','paraguay','amerique-sud','immigration',NULL,'DNM — Migraciones Paraguay','https://www.migraciones.gov.py/','migraciones.gov.py','Dirección Nacional de Migraciones',85,true,'migration Paraguay','noopener','911');

-- URUGUAY (UY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('UY','Uruguay','uruguay','amerique-sud','immigration',NULL,'MRREE — Migracion Uruguay','https://www.mrree.gub.uy/frontend/page?1,home','mrree.gub.uy','Ministère relations extérieures: visa, résidence',90,true,'migration Uruguay','noopener','911'),
('UY','Uruguay','uruguay','amerique-sud','emploi',NULL,'BuscoJobs Uruguay','https://www.buscojobs.com.uy/','buscojobs.com.uy','Site emploi en Uruguay',70,false,'BuscoJobs UY','noopener nofollow','911');

-- VENEZUELA (VE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('VE','Venezuela','venezuela','amerique-sud','immigration',NULL,'SAIME — Migracion Venezuela','https://saime.gob.ve/','saime.gob.ve','Servicio Administrativo de Identificación y Migración',80,true,'migration Venezuela','noopener','911'),
('VE','Venezuela','venezuela','amerique-sud','emploi',NULL,'LinkedIn Venezuela','https://www.linkedin.com/jobs/venezuela-jobs/','linkedin.com','Offres emploi au Venezuela via LinkedIn',75,false,'emploi Venezuela','noopener nofollow','911');

-- GUYANA (GY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('GY','Guyana','guyana','amerique-sud','immigration',NULL,'GIS — Immigration Guyana','https://gis.gov.gy/','gis.gov.gy','Guyana Immigration Service',80,true,'immigration Guyana','noopener','999');

-- SURINAME (SR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SR','Suriname','suriname','amerique-sud','immigration',NULL,'Immigratiedienst Suriname','https://www.gov.sr/','gov.sr','Portail gouvernemental Suriname: immigration',80,true,'immigration Suriname','noopener','115');

-- =============================================
-- AFRIQUE MANQUANTS
-- =============================================

-- GHANA (GH)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('GH','Ghana','ghana','afrique','immigration',NULL,'Ghana Immigration Service','https://www.ghanaimmigration.org/','ghanaimmigration.org','Visa, résidence, permis de travail au Ghana',90,true,'immigration Ghana','noopener','191'),
('GH','Ghana','ghana','afrique','emploi',NULL,'Jobberman Ghana','https://www.jobberman.com.gh/','jobberman.com.gh','1er site emploi au Ghana',75,false,'Jobberman GH','noopener nofollow','191');

-- ÉTHIOPIE (ET)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('ET','Ethiopie','ethiopie','afrique','immigration',NULL,'e-Visa Éthiopie','https://www.evisa.gov.et/','evisa.gov.et','Portail officiel e-Visa Éthiopie',90,true,'e-Visa Ethiopie','noopener','911'),
('ET','Ethiopie','ethiopie','afrique','emploi',NULL,'EthioJobs','https://www.ethiojobs.net/','ethiojobs.net','Site emploi en Éthiopie',70,false,'EthioJobs','noopener nofollow','911');

-- TANZANIE (TZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('TZ','Tanzanie','tanzanie','afrique','immigration',NULL,'Immigration Tanzania','https://www.immigration.go.tz/','immigration.go.tz','Visa, permis résidence, permis travail Tanzanie',90,true,'immigration Tanzanie','noopener','112'),
('TZ','Tanzanie','tanzanie','afrique','emploi',NULL,'BrighterMonday Tanzania','https://www.brightermonday.co.tz/','brightermonday.co.tz','Site emploi en Tanzanie',70,false,'BrighterMonday TZ','noopener nofollow','112');

-- OUGANDA (UG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('UG','Ouganda','ouganda','afrique','immigration',NULL,'Directorate of Citizenship — Uganda','https://www.immigration.go.ug/','immigration.go.ug','Visa, résidence, naturalisation Uganda',90,true,'immigration Ouganda','noopener','999'),
('UG','Ouganda','ouganda','afrique','emploi',NULL,'BrighterMonday Uganda','https://www.brightermonday.co.ug/','brightermonday.co.ug','Site emploi en Ouganda',70,false,'BrighterMonday UG','noopener nofollow','999');

-- RWANDA (RW)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('RW','Rwanda','rwanda','afrique','immigration',NULL,'Irembo — e-Visa Rwanda','https://irembo.gov.rw/','irembo.gov.rw','Portail services en ligne: e-Visa, résidence Rwanda',95,true,'e-Visa Rwanda','noopener','112'),
('RW','Rwanda','rwanda','afrique','emploi',NULL,'RwandaJobs','https://www.rwandajob.com/','rwandajob.com','Site emploi au Rwanda',70,false,'RwandaJobs','noopener nofollow','112');

-- ANGOLA (AO)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('AO','Angola','angola','afrique','immigration',NULL,'SME — Serviço de Migração Angola','https://sme.gov.ao/','sme.gov.ao','Serviço de Migração e Estrangeiros Angola',85,true,'immigration Angola','noopener','112'),
('AO','Angola','angola','afrique','emploi',NULL,'Emprego Angola','https://www.emprego.co.ao/','emprego.co.ao','Site emploi en Angola',70,false,'Emprego Angola','noopener nofollow','112');

-- ZAMBIE (ZM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('ZM','Zambie','zambie','afrique','immigration',NULL,'Immigration Zambia','https://www.immigration.gov.zm/','immigration.gov.zm','Department of Immigration Zambie',85,true,'immigration Zambie','noopener','999'),
('ZM','Zambie','zambie','afrique','emploi',NULL,'GoZambiaJobs','https://www.gozambiajobs.com/','gozambiajobs.com','Site emploi en Zambie',70,false,'GoZambiaJobs','noopener nofollow','999');

-- ZIMBABWE (ZW)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('ZW','Zimbabwe','zimbabwe','afrique','immigration',NULL,'Zimbabwe Immigration','https://www.zimimmigration.gov.zw/','zimimmigration.gov.zw','Zimbabwe Immigration Department',85,true,'immigration Zimbabwe','noopener','999'),
('ZW','Zimbabwe','zimbabwe','afrique','emploi',NULL,'Job Zimbabwe','https://www.jobzimbabwe.com/','jobzimbabwe.com','Site emploi au Zimbabwe',70,false,'JobZimbabwe','noopener nofollow','999');

-- BOTSWANA (BW)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BW','Botswana','botswana','afrique','immigration',NULL,'DIS — Immigration Botswana','https://www.gov.bw/departments/department-immigration-citizens','gov.bw','Department of Immigration Botswana',85,true,'immigration Botswana','noopener','999'),
('BW','Botswana','botswana','afrique','emploi',NULL,'Careers Botswana','https://www.careersbotswana.com/','careersbotswana.com','Site emploi au Botswana',70,false,'Careers Botswana','noopener nofollow','999');

-- NAMIBIE (NA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('NA','Namibie','namibie','afrique','immigration',NULL,'Home Affairs Namibia','https://mhaiss.gov.na/','mhaiss.gov.na','Ministry of Home Affairs Namibie: visa, séjour',85,true,'immigration Namibie','noopener','10111'),
('NA','Namibie','namibie','afrique','emploi',NULL,'JobNamibia','https://www.jobnamibia.com/','jobnamibia.com','Site emploi en Namibie',70,false,'JobNamibia','noopener nofollow','10111');

-- MOZAMBIQUE (MZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MZ','Mozambique','mozambique','afrique','immigration',NULL,'SENAMI — Immigration Mozambique','https://www.portaldocidadao.gov.mz/','portaldocidadao.gov.mz','Portail citoyen Mozambique: visa, immigration',80,true,'immigration Mozambique','noopener','119');

-- MALI (ML)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('ML','Mali','mali','afrique','immigration',NULL,'DGME — Migration Mali','https://www.dgme.ml/','dgme.ml','Direction Générale Migration Mali',80,true,'immigration Mali','noopener','17'),
('ML','Mali','mali','afrique','emploi',NULL,'EmploiMali','https://www.emploimali.com/','emploimali.com','Offres emploi au Mali',65,false,'EmploiMali','noopener nofollow','17');

-- BURKINA FASO (BF)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BF','Burkina Faso','burkina-faso','afrique','immigration',NULL,'DCPEF — Migration Burkina','https://www.gouvernement.gov.bf/','gouvernement.gov.bf','Direction Contrôle Police Frontières Burkina Faso',80,true,'immigration Burkina','noopener','17');

-- GABON (GA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('GA','Gabon','gabon','afrique','immigration',NULL,'DGM — Migration Gabon','https://www.immigration.gov.ga/','immigration.gov.ga','Direction Générale Migration Gabon',80,true,'immigration Gabon','noopener','1730'),
('GA','Gabon','gabon','afrique','emploi',NULL,'Gabon Emploi','https://www.gabon-emploi.com/','gabon-emploi.com','Offres emploi au Gabon',65,false,'Gabon Emploi','noopener nofollow','1730');

-- LIBÉRIA (LR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('LR','Liberia','liberia','afrique','immigration',NULL,'Immigration Liberia','https://immigration.gov.lr/','immigration.gov.lr','Bureau of Immigration & Naturalization Liberia',80,true,'immigration Liberia','noopener','911');

-- SIERRA LEONE (SL)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SL','Sierra Leone','sierra-leone','afrique','immigration',NULL,'Immigration Sierra Leone','https://www.immigration.gov.sl/','immigration.gov.sl','Immigration Department Sierra Leone',80,true,'immigration Sierra Leone','noopener','999');

-- LIBYE (LY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('LY','Libye','libye','afrique','immigration',NULL,'DCM — Direction Migration Libye','https://foreignembassy.ly/','foreignembassy.ly','Portail consulaire Libye',70,true,'immigration Libye','noopener','1515');

-- SOUDAN (SD)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SD','Soudan','soudan','afrique','immigration',NULL,'Portail officiel Soudan','https://www.gov.sd/','gov.sd','Portail gouvernemental du Soudan',70,true,'visa Soudan','noopener','999');

-- PAYS AFRICAINS AVEC 1 LIEN MINIMUM
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BJ','Benin','benin','afrique','immigration',NULL,'Gouvernement Bénin','https://www.gouv.bj/','gouv.bj','Portail gouvernemental Bénin: visa, séjour',80,true,'immigration Benin','noopener','117'),
('BI','Burundi','burundi','afrique','immigration',NULL,'Immigration Burundi','https://www.migration.gov.bi/','migration.gov.bi','Service de l immigration Burundi',75,true,'immigration Burundi','noopener','112'),
('CV','Cap-Vert','cap-vert','afrique','immigration',NULL,'DGASP — Cap-Vert','https://www.dgasp.gov.cv/','dgasp.cv','Migration et frontières Cap-Vert',80,true,'immigration Cap-Vert','noopener','132'),
('CF','Rep Centrafricaine','rep-centrafricaine','afrique','immigration',NULL,'Gouvernement RCA','https://www.gouvernement.cf/','gouvernement.cf','Portail gouvernemental RCA',65,true,'visa Centrafrique','noopener','117'),
('TD','Tchad','tchad','afrique','immigration',NULL,'Gouvernement Tchad','https://www.gouvernement.td/','gouvernement.td','Portail gouvernemental Tchad: visa, séjour',65,true,'visa Tchad','noopener','17'),
('KM','Comores','comores','afrique','immigration',NULL,'Gouvernement Comores','https://www.gouvernement.km/','gouvernement.km','Portail officiel des Comores',65,true,'visa Comores','noopener','17'),
('CG','Congo','congo','afrique','immigration',NULL,'Gouvernement Congo','https://www.gouvernement.cg/','gouvernement.cg','Portail gouvernemental Congo Brazzaville',70,true,'visa Congo','noopener','117'),
('CD','RD Congo','rd-congo','afrique','immigration',NULL,'DGM — Migration RDC','https://dgm.gouv.cd/','dgm.gouv.cd','Direction Générale Migration République Démocratique du Congo',80,true,'immigration RD Congo','noopener','112'),
('DJ','Djibouti','djibouti','afrique','immigration',NULL,'Gouvernement Djibouti','https://www.presidence.dj/','presidence.dj','Portail gouvernemental Djibouti',70,true,'visa Djibouti','noopener','17'),
('GQ','Guinee Equatoriale','guinee-equatoriale','afrique','immigration',NULL,'Gouvernement GQ','https://www.guineaecuatorialpress.com/','guineaecuatorialpress.com','Portail gouvernemental Guinée équatoriale',65,true,'visa Guinee eq','noopener','114'),
('ER','Erythree','erythree','afrique','immigration',NULL,'Gouvernement Érythrée','https://www.eritrea.be/','eritrea.be','Ambassade Érythrée: informations consulaires',65,true,'visa Erythree','noopener','114'),
('SZ','Eswatini','eswatini','afrique','immigration',NULL,'Immigration Eswatini','https://www.gov.sz/','gov.sz','Gouvernement Eswatini: visa, séjour',75,true,'immigration Eswatini','noopener','999'),
('GM','Gambie','gambie','afrique','immigration',NULL,'Immigration Gambia','https://www.migrationgambia.org/','migrationgambia.org','Gambia Immigration Dep.: visa, résidence',75,true,'immigration Gambie','noopener','117'),
('GN','Guinee','guinee','afrique','immigration',NULL,'Gouvernement Guinée','https://www.gouvernement.gov.gn/','gouvernement.gov.gn','Portail gouvernemental Guinée: visa',70,true,'immigration Guinee','noopener','17'),
('GW','Guinee-Bissau','guinee-bissau','afrique','immigration',NULL,'Gouvernement Guinée-Bissau','https://www.gov.gw/','gov.gw','Portail gouvernemental Guinée-Bissau',65,true,'visa Guinee-Bissau','noopener','117'),
('LS','Lesotho','lesotho','afrique','immigration',NULL,'Immigration Lesotho','https://www.lesotho.gov.ls/','lesotho.gov.ls','Immigration and Passport Authority Lesotho',75,true,'immigration Lesotho','noopener','123'),
('MW','Malawi','malawi','afrique','immigration',NULL,'Immigration Malawi','https://www.immigration.gov.mw/','immigration.gov.mw','Immigration Department Malawi',80,true,'immigration Malawi','noopener','997'),
('MR','Mauritanie','mauritanie','afrique','immigration',NULL,'ANM — Migration Mauritanie','https://www.agencemigration.gov.mr/','agencemigration.gov.mr','Agence Nationale pour la Migration Mauritanie',75,true,'immigration Mauritanie','noopener','17'),
('NE','Niger','niger','afrique','immigration',NULL,'DAN — Migration Niger','https://www.interieur.gouv.ne/','interieur.gouv.ne','Direction migration Niger',70,true,'immigration Niger','noopener','17'),
('RW','Rwanda','rwanda','afrique','sante',NULL,'Rwanda Biomedical Centre','https://www.rbc.gov.rw/','rbc.gov.rw','Centre biomédical Rwanda: santé publique',85,true,'sante Rwanda','noopener','112'),
('SC','Seychelles','seychelles','afrique','immigration',NULL,'Immigration Seychelles','https://www.ics.gov.sc/','ics.gov.sc','Immigration Control Authority Seychelles',85,true,'immigration Seychelles','noopener','999'),
('SO','Somalie','somalie','afrique','immigration',NULL,'Gouvernement Somalie','https://www.Somalia.gov.so/','somalia.gov.so','Portail gouvernemental Somalie',55,true,'visa Somalie','noopener','888'),
('SS','Soudan du Sud','soudan-du-sud','afrique','immigration',NULL,'Gouvernement Soudan du Sud','https://www.goss.org/','goss.org','Government of South Sudan: immigration',60,true,'immigration Soudan du Sud','noopener','999'),
('TG','Togo','togo','afrique','immigration',NULL,'DGAT — Migration Togo','https://www.dgat.gouv.tg/','dgat.gouv.tg','Direction Générale Affaires Togolaises',75,true,'immigration Togo','noopener','17'),
('ST','Sao Tome-et-Principe','sao-tome-et-principe','afrique','immigration',NULL,'Gouvernement STP','https://www.gov.st/','gov.st','Portail gouvernemental São Tomé-et-Príncipe',70,true,'visa Sao Tome','noopener','112');

-- =============================================
-- ASIE MANQUANTS
-- =============================================

-- ARABIE SAOUDITE (SA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SA','Arabie saoudite','arabie-saoudite','asie','immigration',NULL,'Absher — Visa Arabie Saoudite','https://www.absher.sa/','absher.sa','Portail Absher: visa, iqama, services gouvernementaux',95,true,'immigration Arabie Saoudite','noopener','911'),
('SA','Arabie saoudite','arabie-saoudite','asie','immigration','visa','Visa MOFA Arabie Saoudite','https://visa.mofa.gov.sa/','mofa.gov.sa','Ministère Affaires Étrangères: e-Visa touristique',90,true,'visa Arabie Saoudite','noopener','911'),
('SA','Arabie saoudite','arabie-saoudite','asie','emploi',NULL,'Bayt.com Arabie Saoudite','https://www.bayt.com/en/saudi-arabia/','bayt.com','Emploi au Moyen-Orient: 1er site emploi',80,false,'emploi Arabie Saoudite','noopener nofollow','911'),
('SA','Arabie saoudite','arabie-saoudite','asie','logement',NULL,'Bayut — Immobilier Arabie Saoudite','https://www.bayut.sa/','bayut.sa','Portail immobilier Saudi Arabie',75,false,'logement Arabie Saoudite','noopener nofollow','911');

-- QATAR (QA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('QA','Qatar','qatar','asie','immigration',NULL,'MOI Qatar — Hukoomi','https://hukoomi.gov.qa/en/service/residency-visa','hukoomi.gov.qa','Portail gouvernemental Qatar: résidence, visa, Hayya',95,true,'immigration Qatar','noopener','999'),
('QA','Qatar','qatar','asie','emploi',NULL,'Bayt.com Qatar','https://www.bayt.com/en/qatar/','bayt.com','Site emploi au Qatar',80,false,'emploi Qatar','noopener nofollow','999'),
('QA','Qatar','qatar','asie','logement',NULL,'Qatar Living — Immobilier','https://www.qatarliving.com/','qatarliving.com','Immobilier et communauté expatriés Qatar',75,false,'logement Qatar','noopener nofollow','999');

-- KOWEÏT (KW)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('KW','Koweit','koweit','asie','immigration',NULL,'MOI Kuwait — Residency','https://www.moi.gov.kw/','moi.gov.kw','Ministry of Interior Kuwait: résidence, visa',90,true,'immigration Koweit','noopener','112'),
('KW','Koweit','koweit','asie','emploi',NULL,'Bayt.com Kuwait','https://www.bayt.com/en/kuwait/','bayt.com','Site emploi au Koweït',80,false,'emploi Koweit','noopener nofollow','112');

-- BAHREÏN (BH)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BH','Bahrein','bahrein','asie','immigration',NULL,'NPRA — Immigration Bahreïn','https://www.npra.gov.bh/','npra.gov.bh','National Passport & Residence Affairs Bahreïn',90,true,'immigration Bahrein','noopener','999'),
('BH','Bahrein','bahrein','asie','emploi',NULL,'Bayt.com Bahrain','https://www.bayt.com/en/bahrain/','bayt.com','Site emploi à Bahreïn',75,false,'emploi Bahrein','noopener nofollow','999');

-- OMAN (OM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('OM','Oman','oman','asie','immigration',NULL,'e-Visa Oman — ROP','https://evisa.rop.gov.om/','rop.gov.om','Royal Oman Police: e-Visa, séjour, résidence',90,true,'e-Visa Oman','noopener','9999'),
('OM','Oman','oman','asie','emploi',NULL,'Bayt.com Oman','https://www.bayt.com/en/oman/','bayt.com','Site emploi à Oman',75,false,'emploi Oman','noopener nofollow','9999');

-- JORDANIE (JO)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('JO','Jordanie','jordanie','asie','immigration',NULL,'MOI Jordan — e-Visa','https://www.jordan.com/moi_evisa/','jordan.com','e-Visa Jordanie: touristique, résidence',90,true,'e-Visa Jordanie','noopener','911'),
('JO','Jordanie','jordanie','asie','emploi',NULL,'Bayt.com Jordan','https://www.bayt.com/en/jordan/','bayt.com','Site emploi en Jordanie',75,false,'emploi Jordanie','noopener nofollow','911');

-- LIBAN (LB)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('LB','Liban','liban','asie','immigration',NULL,'General Security Lebanon','https://www.general-security.gov.lb/','general-security.gov.lb','Sûreté générale Liban: visa, séjour, résidence',85,true,'immigration Liban','noopener','140'),
('LB','Liban','liban','asie','emploi',NULL,'Bayt.com Liban','https://www.bayt.com/en/lebanon/','bayt.com','Site emploi au Liban',75,false,'emploi Liban','noopener nofollow','140');

-- IRAK (IQ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('IQ','Irak','irak','asie','immigration',NULL,'MOI Iraq','https://www.moi.gov.iq/','moi.gov.iq','Ministry of Interior Iraq: passeport, séjour',75,true,'immigration Irak','noopener','104');

-- IRAN (IR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('IR','Iran','iran','asie','immigration',NULL,'e-Visa Iran','https://evisa.mfa.ir/','mfa.ir','Visa électronique République islamique d Iran',85,true,'e-Visa Iran','noopener','115');

-- KAZAKHSTAN (KZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('KZ','Kazakhstan','kazakhstan','asie','immigration',NULL,'eVisa Kazakhstan','https://www.evisa.e.gov.kz/','e.gov.kz','Visa électronique Kazakhstan: tourisme, affaires',90,true,'e-Visa Kazakhstan','noopener','112'),
('KZ','Kazakhstan','kazakhstan','asie','emploi',NULL,'HeadHunter Kazakhstan','https://hh.kz/','hh.kz','Site emploi au Kazakhstan (HeadHunter)',80,false,'HeadHunter KZ','noopener nofollow','112');

-- OUZBÉKISTAN (UZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('UZ','Ouzbekistan','ouzbekistan','asie','immigration',NULL,'e-Visa Ouzbékistan','https://e-visa.uz/','e-visa.uz','Visa électronique Ouzbékistan',90,true,'e-Visa Ouzbekistan','noopener','112'),
('UZ','Ouzbekistan','ouzbekistan','asie','emploi',NULL,'HeadHunter Uzbekistan','https://hh.uz/','hh.uz','Site emploi en Ouzbékistan',75,false,'HeadHunter UZ','noopener nofollow','112');

-- KIRGHIZSTAN (KG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('KG','Kirghizstan','kirghizstan','asie','immigration',NULL,'e-Visa Kirghizstan','https://www.evisa.e-gov.kg/','e-gov.kg','Portail officiel e-Visa Kirghizstan',85,true,'e-Visa Kirghizstan','noopener','112');

-- TADJIKISTAN (TJ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('TJ','Tadjikistan','tadjikistan','asie','immigration',NULL,'e-Visa Tadjikistan','https://www.evisa.tj/','evisa.tj','e-Visa officiel Tadjikistan',85,true,'e-Visa Tadjikistan','noopener','112');

-- MONGOLIE (MN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MN','Mongolie','mongolie','asie','immigration',NULL,'e-Visa Mongolia','https://evisa.mn/','evisa.mn','Visa électronique pour la Mongolie',85,true,'e-Visa Mongolie','noopener','102');

-- MYANMAR (MM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MM','Myanmar','myanmar','asie','immigration',NULL,'e-Visa Myanmar','https://evisa.moip.gov.mm/','moip.gov.mm','e-Visa officiel Myanmar (Birmanie)',80,true,'e-Visa Myanmar','noopener','199');

-- NÉPAL (NP)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('NP','Nepal','nepal','asie','immigration',NULL,'Immigration Nepal','https://www.immigration.gov.np/','immigration.gov.np','Department of Immigration Nepal: visa, permis',90,true,'immigration Nepal','noopener','100'),
('NP','Nepal','nepal','asie','emploi',NULL,'MeroJob Nepal','https://www.merojob.com/','merojob.com','Site emploi au Népal',70,false,'MeroJob','noopener nofollow','100');

-- BANGLADESH (BD)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BD','Bangladesh','bangladesh','asie','immigration',NULL,'DIP — Immigration Bangladesh','https://www.dip.gov.bd/','dip.gov.bd','Dept. of Immigration and Passports Bangladesh',85,true,'immigration Bangladesh','noopener','999'),
('BD','Bangladesh','bangladesh','asie','emploi',NULL,'Bdjobs.com','https://www.bdjobs.com/','bdjobs.com','1er site emploi au Bangladesh',75,false,'Bdjobs','noopener nofollow','999');

-- PAKISTAN (PK)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('PK','Pakistan','pakistan','asie','immigration',NULL,'NADRA — e-Visa Pakistan','https://visa.nadra.gov.pk/','nadra.gov.pk','NADRA: visa électronique Pakistan',85,true,'e-Visa Pakistan','noopener','15'),
('PK','Pakistan','pakistan','asie','emploi',NULL,'Rozee.pk','https://www.rozee.pk/','rozee.pk','1er site emploi au Pakistan',75,false,'Rozee.pk','noopener nofollow','15');

-- SRI LANKA (LK)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('LK','Sri Lanka','sri-lanka','asie','immigration',NULL,'ETA Sri Lanka','https://www.eta.gov.lk/','eta.gov.lk','Electronic Travel Authorization Sri Lanka',90,true,'ETA Sri Lanka','noopener','119'),
('LK','Sri Lanka','sri-lanka','asie','emploi',NULL,'TopJobs.lk','https://www.topjobs.lk/','topjobs.lk','Site emploi au Sri Lanka',70,false,'TopJobs.lk','noopener nofollow','119');

-- BHOUTAN (BT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BT','Bhoutan','bhoutan','asie','immigration',NULL,'Tourism Council Bhutan','https://www.bhutan.travel/','bhutan.travel','Visa et visites au Bhoutan: SDF, circuit obligatoire',90,true,'visa Bhoutan','noopener','112');

-- MALDIVES (MV)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('MV','Maldives','maldives','asie','immigration',NULL,'Immigration Maldives','https://www.immigration.gov.mv/','immigration.gov.mv','Dept. of Immigration Maldives: séjour, résidence',85,true,'immigration Maldives','noopener','119');

-- BRUNÉI (BN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('BN','Brunei','brunei','asie','immigration',NULL,'Immigration Brunei','https://www.immigration.gov.bn/','immigration.gov.bn','Immigration Dept. Brunei: visa, résidence, travail',85,true,'immigration Brunei','noopener','991');

-- TIMOR ORIENTAL (TL)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('TL','Timor oriental','timor-oriental','asie','immigration',NULL,'Immigration Timor-Leste','https://immigration.gov.tl/','immigration.gov.tl','Immigration Timor-Leste: visa, résidence',80,true,'immigration Timor','noopener','112');

-- TAÏWAN (TW)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('TW','Taiwan','taiwan','asie','immigration',NULL,'NIA — Immigration Taiwan','https://www.immigration.gov.tw/','immigration.gov.tw','National Immigration Agency Taiwan: visa, résidence',90,true,'immigration Taiwan','noopener','119'),
('TW','Taiwan','taiwan','asie','emploi',NULL,'104.com.tw — Emploi','https://www.104.com.tw/','104.com.tw','1er site emploi à Taïwan',80,false,'104 Taiwan','noopener nofollow','119'),
('TW','Taiwan','taiwan','asie','logement',NULL,'591.com.tw — Immobilier','https://www.591.com.tw/','591.com.tw','Portail immobilier et colocation Taïwan',75,false,'591 Taiwan','noopener nofollow','119');

-- TURKMÉNISTAN (TM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('TM','Turkmenistan','turkmenistan','asie','immigration',NULL,'Gouvernement Turkménistan','https://www.gov.tm/','gov.tm','Portail gouvernemental Turkménistan: visa, séjour',75,true,'immigration Turkmenistan','noopener','112');

-- SYRIE (SY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SY','Syrie','syrie','asie','immigration',NULL,'UNHCR Syrie — Aide humanitaire','https://www.unhcr.org/sy/','unhcr.org','UNHCR Syrie: protection des réfugiés, aide humanitaire',90,true,'UNHCR Syrie','noopener','112');

-- CORÉE DU NORD (KP)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('KP','Coree du Nord','coree-du-nord','asie','immigration',NULL,'KITC — Tourisme RPDC','https://www.kitc.com.kp/','kitc.com.kp','Korea International Travel Company: visites officielles',70,true,'tourisme Coree du Nord','noopener','119');

-- PALESTINE (PS)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('PS','Palestine','palestine','asie','immigration',NULL,'Gouvernement Palestine','https://www.palestineportal.org/','palestineportal.org','Portail Palestine: visa, séjour, résidence',70,true,'visa Palestine','noopener','101');

-- AFGHANISTAN (AF)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('AF','Afghanistan','afghanistan','asie','immigration',NULL,'UNHCR Afghanistan','https://www.unhcr.org/af/','unhcr.org','UNHCR Afghanistan: aide humanitaire et protection',85,true,'UNHCR Afghanistan','noopener','119');

-- YÉMEN (YE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('YE','Yemen','yemen','asie','immigration',NULL,'UNHCR Yémen','https://www.unhcr.org/ye/','unhcr.org','UNHCR Yémen: aide humanitaire et protection',85,true,'UNHCR Yemen','noopener','191');

-- =============================================
-- OCÉANIE MANQUANTS
-- =============================================

-- FIDJI (FJ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('FJ','Fidji','fidji','oceanie','immigration',NULL,'Immigration Fiji','https://www.immigration.gov.fj/','immigration.gov.fj','Immigration Department Fidji: visa, résidence',85,true,'immigration Fidji','noopener','917'),
('FJ','Fidji','fidji','oceanie','emploi',NULL,'Jobsolve Fiji','https://www.jobsolve.com.fj/','jobsolve.com.fj','Site emploi à Fidji',65,false,'emploi Fidji','noopener nofollow','917');

-- PAPOUASIE-NOUVELLE-GUINÉE (PG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('PG','Papouasie-Nouvelle-Guinee','papouasie-nvlle-guinee','oceanie','immigration',NULL,'Immigration Papua New Guinea','https://www.immigration.gov.pg/','immigration.gov.pg','PNG Immigration & Citizenship Service',80,true,'immigration PNG','noopener','000');

-- SAMOA (WS)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('WS','Samoa','samoa','oceanie','immigration',NULL,'Immigration Samoa','https://www.samoaimmigration.gov.ws/','samoaimmigration.gov.ws','Samoa Immigration: visa, résidence',80,true,'immigration Samoa','noopener','994');

-- TONGA (TO)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('TO','Tonga','tonga','oceanie','immigration',NULL,'Immigration Tonga','https://immigration.gov.to/','immigration.gov.to','Immigration Division Tonga: visa, séjour',80,true,'immigration Tonga','noopener','913');

-- VANUATU (VU)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('VU','Vanuatu','vanuatu','oceanie','immigration',NULL,'Immigration Vanuatu','https://immigration.gov.vu/','immigration.gov.vu','Vanuatu Immigration: visa, résidence permanente',80,true,'immigration Vanuatu','noopener','112');

-- ÎLES SALOMON (SB)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('SB','Iles Salomon','iles-salomon','oceanie','immigration',NULL,'Immigration Solomon Islands','https://www.immigration.gov.sb/','immigration.gov.sb','Immigration Solomon Islands: visa, résidence',75,true,'immigration Iles Salomon','noopener','999');

-- PETITES ÎLES DU PACIFIQUE (avec 1 lien gouvernemental)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('KI','Kiribati','kiribati','oceanie','immigration',NULL,'Gouvernement Kiribati','https://www.president.gov.ki/','president.gov.ki','Portail officiel Kiribati: informations visa',70,true,'visa Kiribati','noopener','999'),
('MH','Iles Marshall','iles-marshall','oceanie','immigration',NULL,'RMI Government','https://www.rmiembassyus.org/','rmiembassyus.org','Ambassade Marshall: visa, résidence RMI',70,true,'visa Iles Marshall','noopener','911'),
('FM','Micronesie','micronesie','oceanie','immigration',NULL,'FSM Government','https://www.fsmgov.org/','fsmgov.org','Federated States of Micronesia: visa, séjour',70,true,'visa Micronesie','noopener','911'),
('NR','Nauru','nauru','oceanie','immigration',NULL,'Nauru Government','https://www.naurugov.nr/','naurugov.nr','Gouvernement Nauru: visa, informations',65,true,'visa Nauru','noopener','110'),
('PW','Palaos','palaos','oceanie','immigration',NULL,'Bureau of Immigration Palau','https://www.palaugov.pw/','palaugov.pw','Gouvernement Palaos: visa, résidence',70,true,'visa Palaos','noopener','911'),
('TV','Tuvalu','tuvalu','oceanie','immigration',NULL,'Tuvalu Government','https://www.tuvaluislands.com/','tuvaluislands.com','Portail Tuvalu: informations visa et tourisme',65,false,'informations Tuvalu','noopener nofollow','911');

-- =============================================
-- ENRICHISSEMENT DES PAYS DÉJÀ PARTIELLEMENT COUVERTS
-- =============================================

-- GRÈCE — ajouter immigration (manquante)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('GR','Grece','grece','europe','immigration',NULL,'DIM — Immigration Grèce','https://migration.gov.gr/en/','migration.gov.gr','Ministère migration: séjour, golden visa, ADEM',90,true,'immigration Grece','noopener','112');

-- POLOGNE — ajouter immigration (manquante)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('PL','Pologne','pologne','europe','immigration',NULL,'UDSC — Immigration Pologne','https://udsc.gov.pl/en/','udsc.gov.pl','Office migration et réfugiés: séjour, Karta Polaka',90,true,'immigration Pologne','noopener','112');

-- TCHÉQUIE — enrichir emploi + logement
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('CZ','Republique Tcheque','republique-tcheque','europe','emploi',NULL,'Jobs.cz','https://www.jobs.cz/','jobs.cz','1er site emploi en République Tchèque',80,false,'Jobs.cz','noopener nofollow','112'),
('CZ','Republique Tcheque','republique-tcheque','europe','logement',NULL,'Sreality.cz — Immobilier','https://www.sreality.cz/','sreality.cz','1er portail immobilier tchèque',80,false,'Sreality','noopener nofollow','112');

-- HONGRIE — enrichir emploi + logement
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute, emergency_number) VALUES
('HU','Hongrie','hongrie','europe','emploi',NULL,'Profession.hu','https://www.profession.hu/','profession.hu','1er site emploi en Hongrie',80,false,'Profession.hu','noopener nofollow','112'),
('HU','Hongrie','hongrie','europe','logement',NULL,'Ingatlan.com — Immobilier','https://ingatlan.com/','ingatlan.com','1er portail immobilier hongrois',80,false,'Ingatlan.com','noopener nofollow','112');

-- =============================================
-- VÉRIFICATION FINALE
-- =============================================
SELECT continent, COUNT(DISTINCT country_code) as pays, COUNT(*) as liens
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY continent
ORDER BY continent;

SELECT 'TOTAL' as label, COUNT(*) as entries, COUNT(DISTINCT country_code) as countries
FROM country_directory
WHERE is_active = true;
