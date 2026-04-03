-- =============================================
-- FIX QUALITÉ DES DONNÉES — ANNUAIRE MONDIAL
-- Corrige TOUTES les erreurs détectées dans les 23 967 entrées existantes
-- Version complète — 2026-03-31
-- =============================================

-- =============================================
-- 1. CORRECTION COUNTRY_NAME / COUNTRY_SLUG PAR COUNTRY_CODE
--    (country_name ne correspond pas au country_code)
-- =============================================

-- Afrique
UPDATE country_directory SET country_name = 'Lesotho',               country_slug = 'lesotho'               WHERE country_code = 'LS';
UPDATE country_directory SET country_name = 'Malawi',                country_slug = 'malawi'                WHERE country_code = 'MW';
UPDATE country_directory SET country_name = 'Sao Tomé-et-Príncipe', country_slug = 'sao-tome-et-principe'  WHERE country_code = 'ST';
UPDATE country_directory SET country_name = 'Somalie',               country_slug = 'somalie'               WHERE country_code = 'SO';
UPDATE country_directory SET country_name = 'Sainte-Hélène',        country_slug = 'sainte-helene'         WHERE country_code = 'SH';

-- Amériques
UPDATE country_directory SET country_name = 'Belize',                             country_slug = 'belize'                      WHERE country_code = 'BZ';
UPDATE country_directory SET country_name = 'Bahamas',                            country_slug = 'bahamas'                     WHERE country_code = 'BS';
UPDATE country_directory SET country_name = 'Dominique',                          country_slug = 'dominique'                   WHERE country_code = 'DM';
UPDATE country_directory SET country_name = 'Saint-Christophe-et-Niévès',        country_slug = 'saint-christophe'            WHERE country_code = 'KN';
UPDATE country_directory SET country_name = 'Sainte-Lucie',                       country_slug = 'sainte-lucie'                WHERE country_code = 'LC';
UPDATE country_directory SET country_name = 'Saint-Vincent-et-les-Grenadines',   country_slug = 'saint-vincent'               WHERE country_code = 'VC';
UPDATE country_directory SET country_name = 'Haïti',                             country_slug = 'haiti'                       WHERE country_code = 'HT';
UPDATE country_directory SET country_name = 'Jamaïque',                          country_slug = 'jamaique'                    WHERE country_code = 'JM';
UPDATE country_directory SET country_name = 'Brésil',                            country_slug = 'bresil'                      WHERE country_code = 'BR';
UPDATE country_directory SET country_name = 'Pérou',                             country_slug = 'perou'                       WHERE country_code = 'PE';
UPDATE country_directory SET country_name = 'République dominicaine',            country_slug = 'republique-dominicaine'      WHERE country_code = 'DO';
UPDATE country_directory SET country_name = 'Équateur',                          country_slug = 'equateur'                    WHERE country_code = 'EC';
UPDATE country_directory SET country_name = 'Bolivie',                           country_slug = 'bolivie'                     WHERE country_code = 'BO';

-- Asie
UPDATE country_directory SET country_name = 'Bhoutan',              country_slug = 'bhoutan'               WHERE country_code = 'BT';
UPDATE country_directory SET country_name = 'Maldives',             country_slug = 'maldives'              WHERE country_code = 'MV';
UPDATE country_directory SET country_name = 'Palaos',               country_slug = 'palaos'                WHERE country_code = 'PW';
UPDATE country_directory SET country_name = 'Myanmar',              country_slug = 'myanmar'               WHERE country_code = 'MM';
UPDATE country_directory SET country_name = 'Viêt Nam',             country_slug = 'viet-nam'              WHERE country_code = 'VN';
UPDATE country_directory SET country_name = 'Corée du Sud',         country_slug = 'coree-du-sud'          WHERE country_code = 'KR';
UPDATE country_directory SET country_name = 'Arménie',              country_slug = 'armenie'               WHERE country_code = 'AM';
UPDATE country_directory SET country_name = 'Azerbaïdjan',          country_slug = 'azerbaidjan'           WHERE country_code = 'AZ';
UPDATE country_directory SET country_name = 'Géorgie',              country_slug = 'georgie'               WHERE country_code = 'GE';
UPDATE country_directory SET country_name = 'Indonésie',            country_slug = 'indonesie'             WHERE country_code = 'ID';
UPDATE country_directory SET country_name = 'Thaïlande',            country_slug = 'thailande'             WHERE country_code = 'TH';
UPDATE country_directory SET country_name = 'Kazakhstan',           country_slug = 'kazakhstan'            WHERE country_code = 'KZ';
UPDATE country_directory SET country_name = 'Ouzbékistan',          country_slug = 'ouzbekistan'           WHERE country_code = 'UZ';
UPDATE country_directory SET country_name = 'Taïwan',               country_slug = 'taiwan'                WHERE country_code = 'TW';
UPDATE country_directory SET country_name = 'Népal',                country_slug = 'nepal'                 WHERE country_code = 'NP';
UPDATE country_directory SET country_name = 'Émirats arabes unis',  country_slug = 'emirats-arabes-unis'   WHERE country_code = 'AE';
UPDATE country_directory SET country_name = 'Arabie saoudite',      country_slug = 'arabie-saoudite'       WHERE country_code = 'SA';
UPDATE country_directory SET country_name = 'Bahreïn',              country_slug = 'bahrein'               WHERE country_code = 'BH';
UPDATE country_directory SET country_name = 'Koweït',               country_slug = 'koweit'                WHERE country_code = 'KW';
UPDATE country_directory SET country_name = 'Brunéi',               country_slug = 'brunei'                WHERE country_code = 'BN';

-- Océanie
UPDATE country_directory SET country_name = 'Tonga',                country_slug = 'tonga'                 WHERE country_code = 'TO';
UPDATE country_directory SET country_name = 'Îles Salomon',         country_slug = 'iles-salomon'          WHERE country_code = 'SB';
UPDATE country_directory SET country_name = 'Fidji',                country_slug = 'fidji'                 WHERE country_code = 'FJ';
UPDATE country_directory SET country_name = 'Nouvelle-Zélande',     country_slug = 'nouvelle-zelande'      WHERE country_code = 'NZ';

-- Europe
UPDATE country_directory SET country_name = 'Albanie',              country_slug = 'albanie'               WHERE country_code = 'AL';
UPDATE country_directory SET country_name = 'Algérie',              country_slug = 'algerie'               WHERE country_code = 'DZ';
UPDATE country_directory SET country_name = 'Bosnie-Herzégovine',   country_slug = 'bosnie-herzegovine'    WHERE country_code = 'BA';
UPDATE country_directory SET country_name = 'Macédoine du Nord',    country_slug = 'macedoine-du-nord'     WHERE country_code = 'MK';
UPDATE country_directory SET country_name = 'Moldavie',             country_slug = 'moldavie'              WHERE country_code = 'MD';
UPDATE country_directory SET country_name = 'Monténégro',           country_slug = 'montenegro'            WHERE country_code = 'ME';
UPDATE country_directory SET country_name = 'Norvège',              country_slug = 'norvege'               WHERE country_code = 'NO';
UPDATE country_directory SET country_name = 'Roumanie',             country_slug = 'roumanie'              WHERE country_code = 'RO';
UPDATE country_directory SET country_name = 'Serbie',               country_slug = 'serbie'                WHERE country_code = 'RS';
UPDATE country_directory SET country_name = 'Slovaquie',            country_slug = 'slovaquie'             WHERE country_code = 'SK';
UPDATE country_directory SET country_name = 'Slovénie',             country_slug = 'slovenie'              WHERE country_code = 'SI';
UPDATE country_directory SET country_name = 'Suède',                country_slug = 'suede'                 WHERE country_code = 'SE';
UPDATE country_directory SET country_name = 'Tchéquie',             country_slug = 'tcheque'               WHERE country_code = 'CZ';
UPDATE country_directory SET country_name = 'Ukraine',              country_slug = 'ukraine'               WHERE country_code = 'UA';
UPDATE country_directory SET country_name = 'Grèce',                country_slug = 'grece'                 WHERE country_code = 'GR';
UPDATE country_directory SET country_name = 'Sénégal',              country_slug = 'senegal'               WHERE country_code = 'SN';
UPDATE country_directory SET country_name = 'Côte d''Ivoire',       country_slug = 'cote-d-ivoire'         WHERE country_code = 'CI';
UPDATE country_directory SET country_name = 'Éthiopie',             country_slug = 'ethiopie'              WHERE country_code = 'ET';
UPDATE country_directory SET country_name = 'Égypte',               country_slug = 'egypte'                WHERE country_code = 'EG';
UPDATE country_directory SET country_name = 'Vatican',              country_slug = 'vatican'               WHERE country_code = 'VA';
UPDATE country_directory SET country_name = 'Israël',               country_slug = 'israel'                WHERE country_code = 'IL';

-- =============================================
-- 2. STANDARDISATION DES NOMS EN DOUBLON
--    (variantes sans accents → formes accentuées canoniques)
-- =============================================

-- Afrique du Nord / Moyen-Orient
UPDATE country_directory SET country_name = 'Algérie'    WHERE country_code = 'DZ' AND country_name IN ('Algerie','ALGERIE');
UPDATE country_directory SET country_name = 'Égypte'     WHERE country_code = 'EG' AND country_name IN ('Egypte','EGYPTE');
UPDATE country_directory SET country_name = 'Éthiopie'   WHERE country_code = 'ET' AND country_name IN ('Ethiopie','ETHIOPIE');

-- Afrique subsaharienne
UPDATE country_directory SET country_name = 'Sénégal'            WHERE country_code = 'SN' AND country_name IN ('Senegal','SENEGAL');
UPDATE country_directory SET country_name = 'Côte d''Ivoire'     WHERE country_code = 'CI' AND country_name IN ('Cote d''Ivoire','Cote dIvoire','Côte dIvoire');

-- Amériques
UPDATE country_directory SET country_name = 'Brésil'             WHERE country_code = 'BR' AND country_name IN ('Bresil','BRESIL');
UPDATE country_directory SET country_name = 'Pérou'              WHERE country_code = 'PE' AND country_name IN ('Perou','PEROU');
UPDATE country_directory SET country_name = 'Équateur'           WHERE country_code = 'EC' AND country_name IN ('Equateur','EQUATEUR');
UPDATE country_directory SET country_name = 'Haïti'              WHERE country_code = 'HT' AND country_name IN ('Haiti','HAITI');
UPDATE country_directory SET country_name = 'Jamaïque'           WHERE country_code = 'JM' AND country_name IN ('Jamaique','JAMAIQUE');
UPDATE country_directory SET country_name = 'République dominicaine' WHERE country_code = 'DO' AND country_name IN ('Republique dominicaine','Republique Dominicaine');
UPDATE country_directory SET country_name = 'États-Unis'         WHERE country_code = 'US' AND country_name IN ('Etats-Unis','Etats Unis','États Unis','USA');

-- Asie - Proche/Moyen-Orient
UPDATE country_directory SET country_name = 'Bahreïn'            WHERE country_code = 'BH' AND country_name IN ('Bahrein','BAHREIN');
UPDATE country_directory SET country_name = 'Émirats arabes unis' WHERE country_code = 'AE' AND country_name IN ('Emirats arabes unis','Emirats Arabes Unis','EAU');
UPDATE country_directory SET country_name = 'Arabie saoudite'    WHERE country_code = 'SA' AND country_name IN ('Arabie Saoudite','ARABIE SAOUDITE');
UPDATE country_directory SET country_name = 'Koweït'             WHERE country_code = 'KW' AND country_name IN ('Koweit','KOWEIT');
UPDATE country_directory SET country_name = 'Israël'             WHERE country_code = 'IL' AND country_name IN ('Israel','ISRAEL');

-- Asie - Sud/Sud-Est
UPDATE country_directory SET country_name = 'Myanmar'            WHERE country_code = 'MM' AND country_name IN ('Birmanie','birmanie','MYANMAR');
UPDATE country_directory SET country_name = 'Brunéi'             WHERE country_code = 'BN' AND country_name IN ('Brunei','BRUNEI');
UPDATE country_directory SET country_name = 'Corée du Sud'       WHERE country_code = 'KR' AND country_name IN ('Coree du Sud','Coree du sud','COREE DU SUD');
UPDATE country_directory SET country_name = 'Indonésie'          WHERE country_code = 'ID' AND country_name IN ('Indonesie','INDONESIE');
UPDATE country_directory SET country_name = 'Israël'             WHERE country_code = 'IL' AND country_name IN ('Israel','ISRAEL');
UPDATE country_directory SET country_name = 'Népal'              WHERE country_code = 'NP' AND country_name IN ('Nepal','NEPAL');
UPDATE country_directory SET country_name = 'Ouzbékistan'        WHERE country_code = 'UZ' AND country_name IN ('Ouzbekistan','OUZBEKISTAN');
UPDATE country_directory SET country_name = 'Taïwan'             WHERE country_code = 'TW' AND country_name IN ('Taiwan','TAIWAN');
UPDATE country_directory SET country_name = 'Thaïlande'          WHERE country_code = 'TH' AND country_name IN ('Thailande','THAILANDE');
UPDATE country_directory SET country_name = 'Viêt Nam'           WHERE country_code = 'VN' AND country_name IN ('Vietnam','Viet Nam','VIET NAM','VIETNAM');

-- Asie - Caucase
UPDATE country_directory SET country_name = 'Arménie'            WHERE country_code = 'AM' AND country_name IN ('Armenie','ARMENIE');
UPDATE country_directory SET country_name = 'Azerbaïdjan'        WHERE country_code = 'AZ' AND country_name IN ('Azerbaidjan','AZERBAIDJAN');
UPDATE country_directory SET country_name = 'Géorgie'            WHERE country_code = 'GE' AND country_name IN ('Georgie','GEORGIE');

-- Europe
UPDATE country_directory SET country_name = 'Bosnie-Herzégovine' WHERE country_code = 'BA' AND country_name IN ('Bosnie-Herzegovine','Bosnie Herzegovine');
UPDATE country_directory SET country_name = 'Grèce'              WHERE country_code = 'GR' AND country_name IN ('Grece','GRECE');
UPDATE country_directory SET country_name = 'Macédoine du Nord'  WHERE country_code = 'MK' AND country_name IN ('Macedoine du Nord','Macedoine');
UPDATE country_directory SET country_name = 'Monténégro'         WHERE country_code = 'ME' AND country_name IN ('Montenegro','MONTENEGRO');
UPDATE country_directory SET country_name = 'Norvège'            WHERE country_code = 'NO' AND country_name IN ('Norvege','NORVEGE');
UPDATE country_directory SET country_name = 'Slovénie'           WHERE country_code = 'SI' AND country_name IN ('Slovenie','SLOVENIE');
UPDATE country_directory SET country_name = 'Suède'              WHERE country_code = 'SE' AND country_name IN ('Suede','SUEDE');
UPDATE country_directory SET country_name = 'Tchéquie'           WHERE country_code = 'CZ' AND country_name IN ('Tcheque','Republique tcheque','République tchèque','TCHEQUE');

-- Océanie
UPDATE country_directory SET country_name = 'Fidji'              WHERE country_code = 'FJ' AND country_name IN ('Fidji','FIDJI','Fiji');
UPDATE country_directory SET country_name = 'Nouvelle-Zélande'   WHERE country_code = 'NZ' AND country_name IN ('Nouvelle-Zelande','Nouvelle Zelande','NOUVELLE-ZELANDE');
UPDATE country_directory SET country_name = 'Îles Salomon'       WHERE country_code = 'SB' AND country_name IN ('Iles Salomon','iles-salomon','ILES SALOMON');

-- Autres
UPDATE country_directory SET country_name = 'Vatican'            WHERE country_code = 'VA' AND country_name IN ('VA VA','Saint-Siège','Saint Siege');

-- =============================================
-- 3. CORRECTION DES CONTINENTS
-- =============================================

-- Égypte est en AFRIQUE (parfois classée Asie)
UPDATE country_directory SET continent = 'afrique' WHERE country_code = 'EG';

-- Arménie, Géorgie, Azerbaïdjan — standardiser en Europe (convention WikidataService)
UPDATE country_directory SET continent = 'europe' WHERE country_code IN ('AM','GE','AZ');

-- Afrique subsaharienne et Afrique du Nord
UPDATE country_directory SET continent = 'afrique'
WHERE country_code IN ('TZ','CI','SN','NG','ET','GH','KE','ZA','MA','DZ','TN',
                       'AO','BI','BF','BJ','BW','CD','CF','CG','CM','CV','DJ',
                       'ER','GA','GM','GN','GQ','GW','KM','LR','LS','LY','MG',
                       'ML','MR','MU','MW','MZ','NA','NE','RW','SC','SD','SL',
                       'SO','SS','ST','SZ','TD','TG','UG','ZM','ZW','SH');

-- Asie (normalisation)
UPDATE country_directory SET continent = 'asie'
WHERE country_code IN ('TH','VN','KH','MY','SG','JP','CN','IN','ID','PH','KR',
                       'TW','MN','MM','BD','NP','LK','PK','BN','TL','AF','IQ',
                       'IR','JO','KW','LB','OM','PS','QA','SA','SY','AE','YE',
                       'BH','IL','KZ','KG','TJ','TM','UZ','BT','MV','PW');

-- Océanie
UPDATE country_directory SET continent = 'oceanie'
WHERE country_code IN ('AU','NZ','FJ','PG','SB','VU','WS','TO','KI','TV','MH',
                       'FM','PW','NR','CK','NU');

-- Europe
UPDATE country_directory SET continent = 'europe'
WHERE country_code IN ('AL','AD','AT','BA','BE','BG','BY','CH','CY','CZ','DE',
                       'DK','EE','ES','FI','FR','GB','GR','HR','HU','IE','IS',
                       'IT','LI','LT','LU','LV','MC','MD','ME','MK','MT','NL',
                       'NO','PL','PT','RO','RS','RU','SE','SI','SK','SM','TR',
                       'UA','VA','XK');

-- =============================================
-- 4. CORRECTION DES NUMÉROS D'URGENCE ERRONÉS
--    (valeurs incorrectes → valeurs officielles)
-- =============================================

-- Yémen: 194 → 191
UPDATE country_directory SET emergency_number = '191' WHERE country_code = 'YE';

-- Arabie Saoudite: 999 → 911 (passage à 911 en 2016)
UPDATE country_directory SET emergency_number = '911' WHERE country_code = 'SA';

-- Israël: 100 → 112 (112 numéro unifié)
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'IL';

-- Tanzanie: 111 → 112
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'TZ';

-- Côte d'Ivoire: 111 → 110
UPDATE country_directory SET emergency_number = '110' WHERE country_code = 'CI';

-- Laos: 191 → 199
UPDATE country_directory SET emergency_number = '199' WHERE country_code = 'LA';

-- Trinité-et-Tobago: 999 → 990
UPDATE country_directory SET emergency_number = '990' WHERE country_code = 'TT';

-- Fidji: 911 → 917
UPDATE country_directory SET emergency_number = '917' WHERE country_code = 'FJ';

-- Guatemala: 110 → 911
UPDATE country_directory SET emergency_number = '911' WHERE country_code = 'GT';

-- Iran: 110 → 115
UPDATE country_directory SET emergency_number = '115' WHERE country_code = 'IR';

-- Arménie: 911 → 112
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'AM';

-- Soudan du Sud: 777 → 999
UPDATE country_directory SET emergency_number = '999' WHERE country_code = 'SS';

-- Ghana: 191 (police) → 112 (unifié)
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'GH';

-- =============================================
-- 5. AJOUT DES NUMÉROS D'URGENCE MANQUANTS
--    (utilise AND emergency_number IS NULL pour ne pas écraser)
-- =============================================

-- EUROPE (manquants)
UPDATE country_directory SET emergency_number = '112' WHERE country_code IN
  ('BY','RU','XK','AM','AZ','AL','BA','GR','ME','MK','MD','SI','SK','RS','RO',
   'UA','AT','BE','BG','CH','CY','CZ','DE','DK','EE','ES','FI','FR','GB','HR',
   'HU','IE','IS','IT','LI','LT','LU','LV','MC','MT','NL','NO','PL','PT','SE',
   'SM','TR','VA','AD')
  AND emergency_number IS NULL;

-- AMÉRIQUES (manquants)
UPDATE country_directory SET emergency_number = '110'  WHERE country_code = 'GT'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '118'  WHERE country_code = 'NI'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '106'  WHERE country_code = 'CU'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119'  WHERE country_code = 'JM'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '114'  WHERE country_code = 'HT'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '115'  WHERE country_code = 'SR'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '211'  WHERE country_code = 'BB'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '919'  WHERE country_code = 'BS'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911'  WHERE country_code IN
  ('BZ','DO','AG','GD','KN','BO','EC','PY','UY','VE','TT','MX','CO','CL','AR',
   'CR','SV','HN','PA','MX')
  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code IN ('DM','LC','VC','GY') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911'  WHERE country_code = 'US'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911'  WHERE country_code = 'CA'  AND emergency_number IS NULL;

-- AFRIQUE (manquants)
UPDATE country_directory SET emergency_number = '112'  WHERE country_code IN
  ('AO','BI','CD','RW','ST','TZ','MA','TN')
  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '17'   WHERE country_code IN
  ('BF','CF','TD','KM','CG','DJ','GN','GW','ML','MR','NE')
  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '117'  WHERE country_code IN ('BJ','GM','TG') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '1730' WHERE country_code = 'GA'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code IN
  ('BW','SZ','SC','SL','SO','SD','UG','ZM','ZW','MU','NG')
  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911'  WHERE country_code IN ('ET','LR') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '123'  WHERE country_code = 'LS'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '1515' WHERE country_code = 'LY'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '997'  WHERE country_code = 'MW'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119'  WHERE country_code = 'MZ'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '10111' WHERE country_code = 'NA' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '132'  WHERE country_code = 'CV'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '199'  WHERE country_code = 'NG'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '114'  WHERE country_code IN ('GQ','ER') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '15'   WHERE country_code = 'DZ'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '180'  WHERE country_code = 'KE'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '1'    WHERE country_code = 'CM'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code = 'ZA'  AND emergency_number IS NULL;

-- ASIE (manquants)
UPDATE country_directory SET emergency_number = '119'  WHERE country_code IN ('AF','MV','LK','TW','KP') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code IN ('BH','QA','KW') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '9999' WHERE country_code = 'OM'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112'  WHERE country_code IN
  ('BT','KZ','KG','SY','TJ','TL','TM','UZ','GE','AZ')
  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '104'  WHERE country_code = 'IQ'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '115'  WHERE country_code = 'IR'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911'  WHERE country_code IN ('JO','SA') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '140'  WHERE country_code = 'LB'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '102'  WHERE country_code = 'MN'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '199'  WHERE country_code = 'MM'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '100'  WHERE country_code = 'NP'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '991'  WHERE country_code = 'BN'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '101'  WHERE country_code = 'PS'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '191'  WHERE country_code = 'YE'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '110'  WHERE country_code = 'CN'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112'  WHERE country_code = 'IN'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119'  WHERE country_code = 'JP'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119'  WHERE country_code = 'KR'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code = 'MY'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code = 'SG'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '191'  WHERE country_code = 'TH'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '113'  WHERE country_code = 'VN'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119'  WHERE country_code = 'PH'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '115'  WHERE country_code = 'KH'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '1999' WHERE country_code = 'PK'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code = 'BD'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '110'  WHERE country_code = 'ID'  AND emergency_number IS NULL;

-- OCÉANIE (manquants)
UPDATE country_directory SET emergency_number = '917'  WHERE country_code = 'FJ'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '000'  WHERE country_code = 'PG'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '994'  WHERE country_code = 'WS'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '913'  WHERE country_code = 'TO'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112'  WHERE country_code = 'VU'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999'  WHERE country_code IN ('SB','KI','TV') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911'  WHERE country_code IN ('MH','FM','PW') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '110'  WHERE country_code = 'NR'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '000'  WHERE country_code = 'AU'  AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '111'  WHERE country_code = 'NZ'  AND emergency_number IS NULL;

-- =============================================
-- 6. PROPAGATION DES NUMÉROS D'URGENCE
--    (toutes les lignes d'un même pays_code doivent avoir le même numéro)
-- =============================================

UPDATE country_directory cd1
SET emergency_number = (
  SELECT emergency_number
  FROM country_directory cd2
  WHERE cd2.country_code = cd1.country_code
    AND cd2.emergency_number IS NOT NULL
  LIMIT 1
)
WHERE cd1.emergency_number IS NULL
  AND cd1.country_code != 'XX'
  AND EXISTS (
    SELECT 1 FROM country_directory cd3
    WHERE cd3.country_code = cd1.country_code
      AND cd3.emergency_number IS NOT NULL
  );

-- =============================================
-- 7. VÉRIFICATION POST-FIX
-- =============================================

-- Noms en doublon restants
SELECT country_code, COUNT(DISTINCT country_name) as nb_noms, array_agg(DISTINCT country_name) as noms
FROM country_directory
WHERE country_code != 'XX'
GROUP BY country_code
HAVING COUNT(DISTINCT country_name) > 1
ORDER BY country_code;

-- Résumé par continent
SELECT
  continent,
  COUNT(DISTINCT country_code) as pays,
  COUNT(*) as total_liens,
  COUNT(*) FILTER (WHERE emergency_number IS NOT NULL) as avec_urgence
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY continent
ORDER BY continent;

-- Total global
SELECT 'TOTAL' as label,
  COUNT(*) as entries,
  COUNT(DISTINCT country_code) as pays,
  COUNT(DISTINCT nationality_code) as nationalites,
  COUNT(*) FILTER (WHERE emergency_number IS NULL) as sans_urgence
FROM country_directory WHERE is_active = true AND country_code != 'XX';
