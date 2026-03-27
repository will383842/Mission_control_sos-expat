-- =============================================
-- FIX CONTINENTS + NUMEROS D'URGENCE
-- Execute APRES import-ambassades-data-gouv.sql
-- =============================================

-- EUROPE
UPDATE country_directory SET continent = 'europe' WHERE country_code IN ('AD','AL','AT','BA','BE','BG','BY','CH','CY','CZ','DE','DK','EE','ES','FI','FR','GB','GR','HR','HU','IE','IS','IT','XK','LT','LU','LV','MC','MD','ME','MK','MT','NL','NO','PL','PT','RO','RS','RU','SE','SI','SK','SM','UA','VA');

-- AMERIQUE DU NORD
UPDATE country_directory SET continent = 'amerique-nord' WHERE country_code IN ('CA','US','MX','GT','BZ','HN','SV','NI','CR','PA','CU','JM','HT','DO','BS','BB','AG','DM','TT','KN','LC','VC','GD');

-- AMERIQUE DU SUD
UPDATE country_directory SET continent = 'amerique-sud' WHERE country_code IN ('AR','BO','BR','CL','CO','EC','GY','PE','PY','SR','UY','VE');

-- AFRIQUE
UPDATE country_directory SET continent = 'afrique' WHERE country_code IN ('DZ','AO','BJ','BW','BF','BI','CM','CV','CF','TD','KM','CG','CD','CI','DJ','EG','GQ','ER','SZ','ET','GA','GM','GH','GN','GW','KE','LS','LR','LY','MG','MW','ML','MR','MU','MA','MZ','NA','NE','NG','RW','ST','SN','SC','SL','SO','ZA','SS','SD','TZ','TG','TN','UG','ZM','ZW');

-- ASIE
UPDATE country_directory SET continent = 'asie' WHERE country_code IN ('AF','AM','AZ','BH','BD','BT','BN','KH','CN','GE','IN','ID','IR','IQ','IL','JP','JO','KZ','KW','KG','LA','LB','MY','MV','MN','MM','NP','KP','OM','PK','PS','PH','QA','SA','SG','KR','LK','SY','TW','TJ','TH','TL','TR','TM','AE','UZ','VN','YE');

-- OCEANIE
UPDATE country_directory SET continent = 'oceanie' WHERE country_code IN ('AU','NZ','FJ','PG','WS','TO','VU','SB','CK','KI','MH','FM','NR','PW','TV');

-- Fallback: tout ce qui est encore 'global' et n'est pas XX → set continent basé sur la longitude
UPDATE country_directory SET continent = 'autre' WHERE continent = 'global' AND country_code != 'XX';

-- =============================================
-- NUMEROS D'URGENCE PAR PAYS (numero unique universel)
-- =============================================
-- Europe (112 partout dans l'UE)
UPDATE country_directory SET emergency_number = '112' WHERE country_code IN ('AD','AL','AT','BA','BE','BG','CH','CY','CZ','DE','DK','EE','ES','FI','FR','GB','GR','HR','HU','IE','IS','IT','LT','LU','LV','MC','MD','ME','MK','MT','NL','NO','PL','PT','RO','RS','SE','SI','SK','SM','UA') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code = 'GB' AND emergency_number IS NULL;

-- Ameriques
UPDATE country_directory SET emergency_number = '911' WHERE country_code IN ('US','CA','MX','PA','CR','HN','SV') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911' WHERE country_code = 'AR' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '190' WHERE country_code = 'BR' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '123' WHERE country_code = 'CO' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911' WHERE country_code = 'CL' AND emergency_number IS NULL;

-- Afrique
UPDATE country_directory SET emergency_number = '15' WHERE country_code = 'MA' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '197' WHERE country_code = 'TN' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '17' WHERE country_code = 'SN' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '110' WHERE country_code = 'CI' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '15' WHERE country_code = 'DZ' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'ZA' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code = 'KE' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '122' WHERE country_code = 'EG' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '18' WHERE country_code = 'CM' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '117' WHERE country_code = 'MG' AND emergency_number IS NULL;

-- Asie
UPDATE country_directory SET emergency_number = '1155' WHERE country_code = 'TH' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '110' WHERE country_code = 'JP' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code IN ('SG','MY') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code = 'AE' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '113' WHERE country_code = 'VN' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '110' WHERE country_code = 'CN' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '100' WHERE country_code = 'IN' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119' WHERE country_code = 'KR' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'TR' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'IL' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '117' WHERE country_code = 'KH' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '1669' WHERE country_code = 'LA' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code = 'PK' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code = 'BD' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'ID' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code = 'PH' AND emergency_number IS NULL;

-- Oceanie
UPDATE country_directory SET emergency_number = '000' WHERE country_code = 'AU' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '111' WHERE country_code = 'NZ' AND emergency_number IS NULL;

-- =============================================
-- RESSOURCES GLOBALES (insérées séparément)
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('XX', 'Global', 'global', 'global', 'sante', 'CFE — Caisse des Francais de l Etranger', 'https://www.cfe.fr/', 'cfe.fr', 'Securite sociale volontaire pour expatries: maladie, maternite, invalidite, accidents du travail. 175 000 adherents dans le monde.', 95, true, 'CFE', 'noopener'),
('XX', 'Global', 'global', 'global', 'banque', 'Wise — Transferts internationaux', 'https://wise.com/', 'wise.com', 'Transferts d argent internationaux au taux reel, compte multi-devises 50+ devises', 85, false, 'Wise', 'noopener'),
('XX', 'Global', 'global', 'global', 'banque', 'Revolut — Compte international', 'https://www.revolut.com/', 'revolut.com', 'Banque mobile: change de devises, paiements a l etranger, crypto', 80, false, 'Revolut', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'banque', 'Numbeo — Cout de la vie mondial', 'https://www.numbeo.com/cost-of-living/', 'numbeo.com', 'Base de donnees collaborative du cout de la vie: loyers, alimentation, transports, sante', 80, false, 'Numbeo', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'fiscalite', 'Service-Public.fr — Impots Francais a l etranger', 'https://www.service-public.fr/particuliers/vosdroits/N31477', 'service-public.fr', 'Obligations fiscales, conventions de non double imposition, CFE', 95, true, 'impots Francais a l etranger', 'noopener'),
('XX', 'Global', 'global', 'global', 'fiscalite', 'Impots.gouv.fr — Non-residents', 'https://www.impots.gouv.fr/international-particulier', 'impots.gouv.fr', 'Declarations, avis, prelevement a la source pour non-residents', 95, true, 'impots non-residents', 'noopener'),
('XX', 'Global', 'global', 'global', 'juridique', 'Service-Public.fr — Francais a l etranger', 'https://www.service-public.fr/particuliers/vosdroits/N120', 'service-public.fr', 'Toutes les demarches: passeport, election, etat civil, rapatriement', 95, true, 'Francais a l etranger', 'noopener'),
('XX', 'Global', 'global', 'global', 'education', 'AEFE — Enseignement francais a l etranger', 'https://www.aefe.fr/', 'aefe.fr', 'Reseau de 580 lycees francais dans 139 pays', 95, true, 'AEFE', 'noopener'),
('XX', 'Global', 'global', 'global', 'emploi', 'France Travail International', 'https://www.francetravail.fr/candidat/vos-recherches/trouver-un-emploi-a-l-etranger.html', 'francetravail.fr', 'Recherche d emploi a l etranger, conseils mobilite internationale', 90, true, 'France Travail International', 'noopener'),
('XX', 'Global', 'global', 'global', 'sante', 'APRIL International — Assurance expatrie', 'https://www.april-international.com/', 'april-international.com', 'Assurance sante internationale pour expatries et voyageurs longue duree', 80, false, 'APRIL International', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'communaute', 'Expat.com — Forum expatries', 'https://www.expat.com/', 'expat.com', 'Communaute mondiale d expatries: forums, guides, annonces par pays', 75, false, 'Expat.com', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'communaute', 'InterNations — Reseau social', 'https://www.internations.org/', 'internations.org', 'Evenements, groupes par ville. 4M+ membres dans 420 villes', 75, false, 'InterNations', 'noopener nofollow');

-- =============================================
-- VERIFICATION FINALE
-- =============================================
SELECT continent, COUNT(DISTINCT country_code) as pays, COUNT(*) as liens
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY continent
ORDER BY continent;

SELECT 'TOTAL' as label, COUNT(*) as entries, COUNT(DISTINCT country_code) as countries,
  COUNT(*) FILTER (WHERE address IS NOT NULL) as with_address,
  COUNT(*) FILTER (WHERE phone IS NOT NULL OR phone_emergency IS NOT NULL) as with_phone,
  COUNT(*) FILTER (WHERE email IS NOT NULL) as with_email,
  COUNT(*) FILTER (WHERE latitude IS NOT NULL) as with_gps,
  COUNT(*) FILTER (WHERE emergency_number IS NOT NULL) as with_emergency
FROM country_directory;
