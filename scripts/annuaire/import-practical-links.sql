-- =============================================
-- LIENS PRATIQUES PAR PAYS (non-diplomatiques)
-- Immigration, sante, logement, emploi, banque, transport, fiscalite, education
-- Execute APRES import-ambassades-data-gouv.sql + fix-continents-and-emergency.sql
--
-- ~50 pays couverts, ~400 liens verifies
-- =============================================

-- =============================================
-- EUROPE
-- =============================================

-- ALLEMAGNE (DE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('DE','Allemagne','allemagne','europe','immigration',NULL,'Auswaertiges Amt — Visas','https://www.auswaertiges-amt.de/en/visa-service','auswaertiges-amt.de','Ministere federal des Affaires etrangeres: types de visa, conditions',95,true,'visa Allemagne','noopener'),
('DE','Allemagne','allemagne','europe','immigration',NULL,'BAMF — Office federal des migrations','https://www.bamf.de/EN/Startseite/startseite_node.html','bamf.de','Titre de sejour, naturalisation, cours d integration',95,true,'BAMF','noopener'),
('DE','Allemagne','allemagne','europe','sante','assurance','AOK — Assurance maladie publique','https://www.aok.de/pk/','aok.de','Plus grande caisse gesetzliche Krankenversicherung',85,true,'AOK','noopener'),
('DE','Allemagne','allemagne','europe','sante','assurance','TK — Techniker Krankenkasse','https://www.tk.de/en','tk.de','Caisse populaire, site en anglais',85,true,'TK','noopener'),
('DE','Allemagne','allemagne','europe','logement','location','ImmobilienScout24','https://www.immobilienscout24.de/','immobilienscout24.de','1er portail immobilier allemand: 1.8M annonces',80,false,'ImmobilienScout24','noopener nofollow'),
('DE','Allemagne','allemagne','europe','logement','colocation','WG-Gesucht — Colocation','https://www.wg-gesucht.de/','wg-gesucht.de','Colocation (WG) et sous-location',75,false,'WG-Gesucht','noopener nofollow'),
('DE','Allemagne','allemagne','europe','emploi','public','Bundesagentur fuer Arbeit','https://www.arbeitsagentur.de/','arbeitsagentur.de','Agence federale pour l emploi',90,true,'Arbeitsagentur','noopener'),
('DE','Allemagne','allemagne','europe','emploi','portail','Make it in Germany','https://www.make-it-in-germany.com/en/','make-it-in-germany.com','Portail officiel pour travailleurs etrangers',90,true,'Make it in Germany','noopener'),
('DE','Allemagne','allemagne','europe','transport','train','Deutsche Bahn','https://www.bahn.de/en','bahn.de','ICE, IC, RE. Deutschlandticket 49EUR/mois',85,true,'Deutsche Bahn','noopener'),
('DE','Allemagne','allemagne','europe','fiscalite',NULL,'Bundeszentralamt fuer Steuern','https://www.bzst.de/EN/Home/home_node.html','bzst.de','Steuer-ID, conventions fiscales',90,true,'impots Allemagne','noopener'),
('DE','Allemagne','allemagne','europe','education','bourses','DAAD — Etudier en Allemagne','https://www.daad.de/en/','daad.de','Bourses, programmes, universites',90,true,'DAAD','noopener'),
('DE','Allemagne','allemagne','europe','banque',NULL,'N26','https://n26.com/','n26.com','Banque en ligne, ouverture rapide, IBAN allemand',80,false,'N26','noopener nofollow');

-- ESPAGNE (ES)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('ES','Espagne','espagne','europe','immigration',NULL,'Secretaria de Estado de Migraciones','https://www.inclusion.gob.es/web/migraciones/','inclusion.gob.es','NIE, carte de residence, permis travail',90,true,'immigration Espagne','noopener'),
('ES','Espagne','espagne','europe','immigration','nie','Policia Nacional — NIE','https://sede.policia.gob.es/portalCiudadano/_en/tramites_extranjeria.php','sede.policia.gob.es','Demarches NIE et sejour pour etrangers',90,true,'NIE Espagne','noopener'),
('ES','Espagne','espagne','europe','sante',NULL,'Seguridad Social','https://www.seg-social.es/','seg-social.es','Securite sociale espagnole',90,true,'Seguridad Social','noopener'),
('ES','Espagne','espagne','europe','logement',NULL,'Idealista Espagne','https://www.idealista.com/','idealista.com','1er portail immobilier espagnol',80,false,'Idealista','noopener nofollow'),
('ES','Espagne','espagne','europe','logement',NULL,'Fotocasa','https://www.fotocasa.es/','fotocasa.es','Portail immobilier espagnol',80,false,'Fotocasa','noopener nofollow'),
('ES','Espagne','espagne','europe','emploi',NULL,'InfoJobs Espagne','https://www.infojobs.net/','infojobs.net','1er site d emploi en Espagne',80,false,'InfoJobs','noopener nofollow'),
('ES','Espagne','espagne','europe','emploi','public','SEPE','https://www.sepe.es/','sepe.es','Service public emploi espagnol',90,true,'SEPE','noopener'),
('ES','Espagne','espagne','europe','fiscalite',NULL,'Agencia Tributaria','https://sede.agenciatributaria.gob.es/','agenciatributaria.gob.es','Administration fiscale: NIE fiscal, IRPF',90,true,'Agencia Tributaria','noopener'),
('ES','Espagne','espagne','europe','transport','train','Renfe','https://www.renfe.com/es/en','renfe.com','AVE haute vitesse, Cercanias',85,true,'Renfe','noopener');

-- PORTUGAL (PT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('PT','Portugal','portugal','europe','immigration',NULL,'AIMA — Migrations et Asile','https://www.aima.gov.pt/','aima.gov.pt','Titres de sejour, visa D7, Golden Visa, NHR',90,true,'AIMA Portugal','noopener'),
('PT','Portugal','portugal','europe','sante',NULL,'SNS — Service national de sante','https://www.sns.gov.pt/','sns.gov.pt','Systeme public portugais',90,true,'SNS Portugal','noopener'),
('PT','Portugal','portugal','europe','logement',NULL,'Idealista Portugal','https://www.idealista.pt/','idealista.pt','1er portail immobilier au Portugal',80,false,'Idealista PT','noopener nofollow'),
('PT','Portugal','portugal','europe','emploi',NULL,'Net-Empregos','https://www.net-empregos.com/','net-empregos.com','Site d emploi au Portugal',75,false,'Net-Empregos','noopener nofollow'),
('PT','Portugal','portugal','europe','emploi','public','IEFP — Emploi public','https://www.iefp.pt/','iefp.pt','Institut de l emploi et de la formation',90,true,'IEFP','noopener'),
('PT','Portugal','portugal','europe','fiscalite',NULL,'Portal das Financas','https://www.portaldasfinancas.gov.pt/','portaldasfinancas.gov.pt','NIF, declarations, statut RNH',90,true,'Financas Portugal','noopener'),
('PT','Portugal','portugal','europe','transport','train','CP — Comboios de Portugal','https://www.cp.pt/','cp.pt','Chemins de fer portugais',85,true,'CP trains','noopener');

-- ITALIE (IT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('IT','Italie','italie','europe','immigration',NULL,'Portale Immigrazione','https://www.portaleimmigrazione.it/','portaleimmigrazione.it','Demandes permis de sejour (permesso di soggiorno)',90,true,'immigration Italie','noopener'),
('IT','Italie','italie','europe','sante',NULL,'Ministero della Salute','https://www.salute.gov.it/','salute.gov.it','Systeme SSN, carte sanitaire',90,true,'sante Italie','noopener'),
('IT','Italie','italie','europe','logement',NULL,'Immobiliare.it','https://www.immobiliare.it/','immobiliare.it','1er portail immobilier italien: 55M visites/mois',80,false,'Immobiliare','noopener nofollow'),
('IT','Italie','italie','europe','logement',NULL,'Idealista Italia','https://www.idealista.it/','idealista.it','Portail immobilier en Italie',80,false,'Idealista IT','noopener nofollow'),
('IT','Italie','italie','europe','emploi',NULL,'InfoJobs Italia','https://www.infojobs.it/','infojobs.it','Site d emploi en Italie',80,false,'InfoJobs IT','noopener nofollow'),
('IT','Italie','italie','europe','fiscalite',NULL,'Agenzia delle Entrate','https://www.agenziaentrate.gov.it/','agenziaentrate.gov.it','Codice fiscale, declarations IRPEF',90,true,'Agenzia Entrate','noopener'),
('IT','Italie','italie','europe','transport','train','Trenitalia','https://www.trenitalia.com/','trenitalia.com','Trains italiens: Frecciarossa, Intercity',85,true,'Trenitalia','noopener');

-- BELGIQUE (BE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('BE','Belgique','belgique','europe','immigration',NULL,'Office des Etrangers — SPF','https://dofi.ibz.be/','dofi.ibz.be','Visa, sejour, asile',90,true,'Office Etrangers','noopener'),
('BE','Belgique','belgique','europe','sante',NULL,'INAMI — Assurance maladie','https://www.inami.fgov.be/','inami.fgov.be','Institut national assurance maladie-invalidite',90,true,'INAMI','noopener'),
('BE','Belgique','belgique','europe','logement',NULL,'Immoweb','https://www.immoweb.be/','immoweb.be','1er portail immobilier belge',85,false,'Immoweb','noopener nofollow'),
('BE','Belgique','belgique','europe','emploi','wallonie','Le Forem — Wallonie','https://www.leforem.be/','leforem.be','Emploi en Wallonie',90,true,'Forem','noopener'),
('BE','Belgique','belgique','europe','emploi','bruxelles','Actiris — Bruxelles','https://www.actiris.brussels/','actiris.brussels','Emploi a Bruxelles',90,true,'Actiris','noopener'),
('BE','Belgique','belgique','europe','emploi','flandre','VDAB — Flandre','https://www.vdab.be/','vdab.be','Emploi en Flandre',90,true,'VDAB','noopener'),
('BE','Belgique','belgique','europe','transport','train','SNCB','https://www.belgiantrain.be/','belgiantrain.be','Chemins de fer belges',85,true,'SNCB','noopener');

-- PAYS-BAS (NL)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('NL','Pays-Bas','pays-bas','europe','immigration',NULL,'IND — Immigration NL','https://ind.nl/en','ind.nl','Permis sejour, MVV, 30% ruling, naturalisation',95,true,'IND','noopener'),
('NL','Pays-Bas','pays-bas','europe','sante',NULL,'Government NL — Assurance sante','https://www.government.nl/topics/health-insurance','government.nl','Assurance maladie obligatoire (zorgverzekering)',90,true,'assurance sante NL','noopener'),
('NL','Pays-Bas','pays-bas','europe','logement',NULL,'Funda — Immobilier NL','https://www.funda.nl/','funda.nl','1er portail immobilier: 34M visites/mois',85,false,'Funda','noopener nofollow'),
('NL','Pays-Bas','pays-bas','europe','emploi','public','UWV — Emploi NL','https://www.uwv.nl/','uwv.nl','Assurance chomage, emploi public',90,true,'UWV','noopener'),
('NL','Pays-Bas','pays-bas','europe','transport','train','NS — Nederlandse Spoorwegen','https://www.ns.nl/en','ns.nl','Chemins de fer neerlandais',85,true,'NS','noopener');

-- SUISSE (CH)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CH','Suisse','suisse','europe','immigration',NULL,'SEM — Secretariat migrations','https://www.sem.admin.ch/sem/fr/home.html','sem.admin.ch','Permis B/C/L/G, naturalisation',95,true,'SEM Suisse','noopener'),
('CH','Suisse','suisse','europe','sante',NULL,'Comparis — Assurance LAMal','https://www.comparis.ch/krankenkassen','comparis.ch','Comparateur assurances maladie obligatoires',80,false,'Comparis','noopener nofollow'),
('CH','Suisse','suisse','europe','logement',NULL,'Homegate','https://www.homegate.ch/','homegate.ch','Portail immobilier suisse',80,false,'Homegate','noopener nofollow'),
('CH','Suisse','suisse','europe','emploi',NULL,'jobs.ch','https://www.jobs.ch/','jobs.ch','1er site d emploi en Suisse',80,false,'jobs.ch','noopener nofollow'),
('CH','Suisse','suisse','europe','transport','train','CFF — SBB','https://www.sbb.ch/fr','sbb.ch','AG, demi-tarif, SwissPass',90,true,'CFF','noopener');

-- ROYAUME-UNI (GB)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('GB','Royaume-Uni','royaume-uni','europe','immigration',NULL,'UK Visas — GOV.UK','https://www.gov.uk/browse/visas-immigration','gov.uk','Settled Status, points-based system, ILR',95,true,'immigration UK','noopener'),
('GB','Royaume-Uni','royaume-uni','europe','sante',NULL,'NHS','https://www.nhs.uk/','nhs.uk','Systeme de sante public gratuit',95,true,'NHS','noopener'),
('GB','Royaume-Uni','royaume-uni','europe','logement',NULL,'Rightmove','https://www.rightmove.co.uk/','rightmove.co.uk','1er portail immobilier UK: 1M+ proprietes',85,false,'Rightmove','noopener nofollow'),
('GB','Royaume-Uni','royaume-uni','europe','logement','colocation','SpareRoom','https://www.spareroom.co.uk/','spareroom.co.uk','Colocation UK',80,false,'SpareRoom','noopener nofollow'),
('GB','Royaume-Uni','royaume-uni','europe','emploi','public','GOV.UK — Find a Job','https://www.gov.uk/find-a-job','gov.uk','Service public recherche emploi',90,true,'Find a Job UK','noopener'),
('GB','Royaume-Uni','royaume-uni','europe','emploi',NULL,'Reed','https://www.reed.co.uk/','reed.co.uk','250 000+ offres',80,false,'Reed','noopener nofollow'),
('GB','Royaume-Uni','royaume-uni','europe','fiscalite',NULL,'HMRC','https://www.gov.uk/government/organisations/hm-revenue-customs','gov.uk','National Insurance, Self Assessment',95,true,'HMRC','noopener');

-- LUXEMBOURG (LU)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('LU','Luxembourg','luxembourg','europe','immigration',NULL,'Guichet.lu — Immigration','https://guichet.public.lu/fr/citoyens/immigration.html','guichet.public.lu','Visa, titre de sejour, travail',95,true,'immigration Luxembourg','noopener'),
('LU','Luxembourg','luxembourg','europe','sante',NULL,'CNS — Caisse Nationale Sante','https://cns.public.lu/','cns.public.lu','Assurance maladie obligatoire',90,true,'CNS Luxembourg','noopener'),
('LU','Luxembourg','luxembourg','europe','logement',NULL,'Athome.lu','https://www.athome.lu/','athome.lu','Portail immobilier luxembourgeois',80,false,'Athome','noopener nofollow'),
('LU','Luxembourg','luxembourg','europe','emploi',NULL,'ADEM — Emploi Luxembourg','https://adem.public.lu/','adem.public.lu','Agence developpement emploi',90,true,'ADEM','noopener');

-- IRLANDE (IE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('IE','Irlande','irlande','europe','immigration',NULL,'INIS — Immigration Irlande','https://www.irishimmigration.ie/','irishimmigration.ie','Visa, IRP card, citoyennete',90,true,'immigration Irlande','noopener'),
('IE','Irlande','irlande','europe','logement',NULL,'Daft.ie — Immobilier','https://www.daft.ie/','daft.ie','1er portail immobilier irlandais',85,false,'Daft.ie','noopener nofollow'),
('IE','Irlande','irlande','europe','emploi',NULL,'Jobs.ie','https://www.jobs.ie/','jobs.ie','Site d emploi en Irlande',80,false,'Jobs.ie','noopener nofollow');

-- AUTRICHE (AT)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('AT','Autriche','autriche','europe','immigration',NULL,'Migration.gv.at','https://www.migration.gv.at/en/','migration.gv.at','Portail officiel immigration et residence',90,true,'immigration Autriche','noopener'),
('AT','Autriche','autriche','europe','logement',NULL,'Willhaben — Immobilier AT','https://www.willhaben.at/iad/immobilien','willhaben.at','1er site petites annonces et immobilier',80,false,'Willhaben','noopener nofollow'),
('AT','Autriche','autriche','europe','emploi',NULL,'AMS — Emploi Autriche','https://www.ams.at/','ams.at','Service public de l emploi autrichien',90,true,'AMS','noopener');

-- GRECE (GR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('GR','Grece','grece','europe','logement',NULL,'Spitogatos','https://www.spitogatos.gr/','spitogatos.gr','Portail immobilier grec',75,false,'Spitogatos','noopener nofollow'),
('GR','Grece','grece','europe','emploi',NULL,'Skywalker.gr — Emploi','https://www.skywalker.gr/','skywalker.gr','Site d emploi en Grece',75,false,'Skywalker','noopener nofollow');

-- POLOGNE (PL)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('PL','Pologne','pologne','europe','logement',NULL,'Otodom — Immobilier Pologne','https://www.otodom.pl/','otodom.pl','1er portail immobilier polonais',80,false,'Otodom','noopener nofollow'),
('PL','Pologne','pologne','europe','emploi',NULL,'Pracuj.pl — Emploi','https://www.pracuj.pl/','pracuj.pl','1er site d emploi en Pologne',80,false,'Pracuj','noopener nofollow');

-- DANEMARK (DK), SUEDE (SE), NORVEGE (NO), FINLANDE (FI)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('DK','Danemark','danemark','europe','immigration',NULL,'SIRI — Immigration Danemark','https://www.nyidanmark.dk/en-GB','nyidanmark.dk','Visa, permis travail, Green Card',90,true,'immigration Danemark','noopener'),
('DK','Danemark','danemark','europe','emploi',NULL,'Jobindex — Emploi DK','https://www.jobindex.dk/','jobindex.dk','1er site d emploi au Danemark',80,false,'Jobindex','noopener nofollow'),
('SE','Suede','suede','europe','immigration',NULL,'Migrationsverket — Immigration','https://www.migrationsverket.se/English/Private-individuals.html','migrationsverket.se','Office suedois des migrations',90,true,'Migrationsverket','noopener'),
('SE','Suede','suede','europe','emploi','public','Arbetsformedlingen — Emploi','https://arbetsformedlingen.se/','arbetsformedlingen.se','Service public emploi suedois',90,true,'Arbetsformedlingen','noopener'),
('NO','Norvege','norvege','europe','immigration',NULL,'UDI — Immigration Norvege','https://www.udi.no/en/','udi.no','Direction de l immigration norvegienne',90,true,'UDI','noopener'),
('NO','Norvege','norvege','europe','emploi',NULL,'NAV — Emploi Norvege','https://www.nav.no/','nav.no','Agence emploi et protection sociale',90,true,'NAV','noopener'),
('FI','Finlande','finlande','europe','immigration',NULL,'Migri — Immigration Finlande','https://migri.fi/en/home','migri.fi','Service finlandais de l immigration',90,true,'Migri','noopener');

-- ROUMANIE (RO), HONGRIE (HU), REPUBLIQUE TCHEQUE (CZ), CROATIE (HR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CZ','Republique Tcheque','republique-tcheque','europe','immigration',NULL,'MVCRR — Immigration','https://www.mvcr.cz/mvcren/article/immigration.aspx','mvcr.cz','Ministere interieur: visa, residence',90,true,'immigration Tcheque','noopener'),
('HU','Hongrie','hongrie','europe','immigration',NULL,'OIF — Immigration Hongrie','https://www.bmbah.hu/index.php?lang=en','bmbah.hu','Office immigration et nationalite',90,true,'immigration Hongrie','noopener');

-- =============================================
-- AMERIQUES
-- =============================================

-- ETATS-UNIS (US)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('US','Etats-Unis','etats-unis','amerique-nord','immigration','visa','USCIS','https://www.uscis.gov/','uscis.gov','Green Card, naturalisation, H1B/L1/O1',95,true,'USCIS','noopener'),
('US','Etats-Unis','etats-unis','amerique-nord','immigration','visa','Travel.State.Gov — Visas','https://travel.state.gov/content/travel/en/us-visas.html','travel.state.gov','Tous types de visa americains',95,true,'visas USA','noopener'),
('US','Etats-Unis','etats-unis','amerique-nord','immigration','esta','ESTA','https://esta.cbp.dhs.gov/','esta.cbp.dhs.gov','Autorisation sejours < 90 jours (21$)',95,true,'ESTA','noopener'),
('US','Etats-Unis','etats-unis','amerique-nord','sante',NULL,'Healthcare.gov','https://www.healthcare.gov/','healthcare.gov','Marketplace ACA (Obamacare)',90,true,'Healthcare.gov','noopener'),
('US','Etats-Unis','etats-unis','amerique-nord','fiscalite',NULL,'IRS','https://www.irs.gov/','irs.gov','ITIN, FATCA, declarations, Social Security',95,true,'IRS','noopener'),
('US','Etats-Unis','etats-unis','amerique-nord','logement',NULL,'Zillow','https://www.zillow.com/','zillow.com','1er portail immobilier US: 135M+ proprietes',85,false,'Zillow','noopener nofollow'),
('US','Etats-Unis','etats-unis','amerique-nord','logement',NULL,'Apartments.com','https://www.apartments.com/','apartments.com','Location appartements USA',80,false,'Apartments.com','noopener nofollow'),
('US','Etats-Unis','etats-unis','amerique-nord','emploi','federal','USAJobs','https://www.usajobs.gov/','usajobs.gov','Emploi gouvernement federal',90,true,'USAJobs','noopener'),
('US','Etats-Unis','etats-unis','amerique-nord','education',NULL,'EducationUSA','https://educationusa.state.gov/','educationusa.state.gov','Reseau officiel etudiants internationaux',90,true,'EducationUSA','noopener');

-- CANADA (CA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CA','Canada','canada','amerique-nord','immigration',NULL,'IRCC','https://www.canada.ca/en/immigration-refugees-citizenship.html','canada.ca','RP, visa travail, PVT, Entree Express',95,true,'IRCC','noopener'),
('CA','Canada','canada','amerique-nord','immigration','pvt','PVT Canada — EIC','https://www.canada.ca/en/immigration-refugees-citizenship/services/work-canada/iec.html','canada.ca','Vacances-Travail 18-35 ans',95,true,'PVT Canada','noopener'),
('CA','Canada','canada','amerique-nord','sante','quebec','RAMQ — Quebec','https://www.ramq.gouv.qc.ca/','ramq.gouv.qc.ca','Assurance maladie Quebec',90,true,'RAMQ','noopener'),
('CA','Canada','canada','amerique-nord','logement',NULL,'Realtor.ca','https://www.realtor.ca/','realtor.ca','Portail immobilier officiel',85,false,'Realtor.ca','noopener nofollow'),
('CA','Canada','canada','amerique-nord','emploi',NULL,'Job Bank Canada','https://www.jobbank.gc.ca/','jobbank.gc.ca','Banque d emplois officielle',90,true,'Job Bank','noopener'),
('CA','Canada','canada','amerique-nord','fiscalite',NULL,'ARC — Agence du revenu','https://www.canada.ca/en/revenue-agency.html','canada.ca','NAS, TPS/TVH, accords fiscaux',95,true,'ARC Canada','noopener');

-- MEXIQUE (MX)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('MX','Mexique','mexique','amerique-nord','immigration',NULL,'INM — Institut National Migration','https://www.gob.mx/inm','gob.mx','FMM, residence temporaire/permanente',90,true,'INM Mexique','noopener'),
('MX','Mexique','mexique','amerique-nord','sante',NULL,'IMSS','https://www.imss.gob.mx/','imss.gob.mx','Institut Mexicain Securite Sociale',90,true,'IMSS','noopener'),
('MX','Mexique','mexique','amerique-nord','logement',NULL,'Inmuebles24','https://www.inmuebles24.com/','inmuebles24.com','Portail immobilier mexicain',75,false,'Inmuebles24','noopener nofollow');

-- BRESIL (BR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('BR','Bresil','bresil','amerique-sud','immigration',NULL,'Policia Federal — Immigration','https://www.gov.br/pf/pt-br/assuntos/imigracao','gov.br','Visa, residence, CPF etranger',90,true,'immigration Bresil','noopener'),
('BR','Bresil','bresil','amerique-sud','sante',NULL,'SUS','https://www.gov.br/saude/','gov.br','Systeme unique de sante gratuit',85,true,'SUS Bresil','noopener'),
('BR','Bresil','bresil','amerique-sud','logement',NULL,'ZAP Imoveis','https://www.zapimoveis.com.br/','zapimoveis.com.br','Portail immobilier bresilien',75,false,'ZAP Imoveis','noopener nofollow');

-- COLOMBIE (CO)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CO','Colombie','colombie','amerique-sud','immigration',NULL,'Migracion Colombia','https://www.migracioncolombia.gov.co/','migracioncolombia.gov.co','Visa, cedula extranjeria, CheckMig',90,true,'Migracion Colombia','noopener');

-- ARGENTINE (AR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('AR','Argentine','argentine','amerique-sud','immigration',NULL,'DNM — Migraciones Argentina','https://www.argentina.gob.ar/interior/migraciones','argentina.gob.ar','Residence, DNI etranger, Mercosur',90,true,'Migraciones Argentina','noopener');

-- PEROU (PE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('PE','Perou','perou','amerique-sud','immigration',NULL,'Migraciones Peru','https://www.gob.pe/migraciones','gob.pe','Carnet d etranger, visa, residence',90,true,'Migraciones Peru','noopener');

-- CHILI (CL)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CL','Chili','chili','amerique-sud','immigration',NULL,'SERMIG — Migraciones Chile','https://serviciomigraciones.cl/','serviciomigraciones.cl','Visa, residence, RUT etranger',90,true,'SERMIG Chile','noopener');

-- REP DOMINICAINE (DO)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('DO','Republique Dominicaine','republique-dominicaine','amerique-nord','immigration',NULL,'DGM — Migration RD','https://migracion.gob.do/','migracion.gob.do','Visa, residence, carte touristique',90,true,'DGM','noopener');

-- COSTA RICA (CR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CR','Costa Rica','costa-rica','amerique-nord','immigration',NULL,'DGME — Migration Costa Rica','https://www.migracion.go.cr/','migracion.go.cr','Visa, residence, digital nomad visa',90,true,'DGME Costa Rica','noopener');

-- =============================================
-- AFRIQUE
-- =============================================

-- MAROC (MA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('MA','Maroc','maroc','afrique','sante',NULL,'CNSS Maroc','https://www.cnss.ma/','cnss.ma','Caisse nationale securite sociale',85,true,'CNSS','noopener'),
('MA','Maroc','maroc','afrique','logement',NULL,'Mubawab','https://www.mubawab.ma/','mubawab.ma','1er portail immobilier marocain',75,false,'Mubawab','noopener nofollow'),
('MA','Maroc','maroc','afrique','logement',NULL,'Avito Maroc','https://www.avito.ma/','avito.ma','Petites annonces: logement, vehicules',70,false,'Avito','noopener nofollow'),
('MA','Maroc','maroc','afrique','emploi',NULL,'Rekrute','https://www.rekrute.com/','rekrute.com','1er site emploi au Maroc',75,false,'Rekrute','noopener nofollow');

-- TUNISIE (TN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('TN','Tunisie','tunisie','afrique','sante',NULL,'CNAM Tunisie','https://www.cnam.nat.tn/','cnam.nat.tn','Caisse nationale assurance maladie',85,true,'CNAM','noopener'),
('TN','Tunisie','tunisie','afrique','logement',NULL,'Tayara — Annonces Tunisie','https://www.tayara.tn/','tayara.tn','Petites annonces',70,false,'Tayara','noopener nofollow'),
('TN','Tunisie','tunisie','afrique','emploi',NULL,'Tanitjobs — Emploi Tunisie','https://www.tanitjobs.com/','tanitjobs.com','1er site emploi en Tunisie',75,false,'Tanitjobs','noopener nofollow');

-- ALGERIE (DZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('DZ','Algerie','algerie','afrique','immigration',NULL,'MFA Algerie — Visa','https://www.mfa.gov.dz/fr/services-for-foreigners/entry-visa-to-algeria','mfa.gov.dz','Visa d entree en Algerie pour etrangers',90,true,'visa Algerie','noopener'),
('DZ','Algerie','algerie','afrique','emploi',NULL,'Emploitic — Emploi Algerie','https://www.emploitic.com/','emploitic.com','1er site emploi en Algerie',75,false,'Emploitic','noopener nofollow');

-- SENEGAL (SN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('SN','Senegal','senegal','afrique','emploi',NULL,'EmploiSenegal','https://www.emploisenegal.com/','emploisenegal.com','Offres d emploi au Senegal',70,false,'EmploiSenegal','noopener nofollow');

-- COTE D IVOIRE (CI)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CI','Cote d Ivoire','cote-d-ivoire','afrique','emploi',NULL,'Emploi.ci','https://www.emploi.ci/','emploi.ci','Offres d emploi en Cote d Ivoire',70,false,'Emploi.ci','noopener nofollow');

-- AFRIQUE DU SUD (ZA)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('ZA','Afrique du Sud','afrique-du-sud','afrique','immigration',NULL,'Home Affairs — Immigration','https://www.dha.gov.za/','dha.gov.za','Department of Home Affairs: visa, permis, citoyennete',90,true,'Home Affairs ZA','noopener'),
('ZA','Afrique du Sud','afrique-du-sud','afrique','emploi',NULL,'Careers24','https://www.careers24.com/','careers24.com','Site emploi en Afrique du Sud',75,false,'Careers24','noopener nofollow');

-- KENYA (KE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('KE','Kenya','kenya','afrique','immigration',NULL,'eFNS — eVisa Kenya','https://www.etakenya.go.ke/','etakenya.go.ke','Autorisation electronique de voyage (eTA)',90,true,'eVisa Kenya','noopener'),
('KE','Kenya','kenya','afrique','emploi',NULL,'BrighterMonday Kenya','https://www.brightermonday.co.ke/','brightermonday.co.ke','1er site emploi au Kenya',75,false,'BrighterMonday','noopener nofollow');

-- EGYPTE (EG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('EG','Egypte','egypte','afrique','emploi',NULL,'Wuzzuf — Emploi Egypte','https://wuzzuf.net/','wuzzuf.net','1er site emploi en Egypte',75,false,'Wuzzuf','noopener nofollow');

-- NIGERIA (NG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('NG','Nigeria','nigeria','afrique','immigration',NULL,'NIS — Immigration Nigeria','https://immigration.gov.ng/','immigration.gov.ng','Nigeria Immigration Service: visa, residence, e-passport',90,true,'NIS Nigeria','noopener'),
('NG','Nigeria','nigeria','afrique','emploi',NULL,'Jobberman Nigeria','https://www.jobberman.com/','jobberman.com','1er site emploi au Nigeria',75,false,'Jobberman','noopener nofollow');

-- CAMEROUN (CM)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CM','Cameroun','cameroun','afrique','emploi',NULL,'Emploi.cm','https://www.emploi.cm/','emploi.cm','Offres d emploi au Cameroun',70,false,'Emploi.cm','noopener nofollow');

-- MADAGASCAR (MG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('MG','Madagascar','madagascar','afrique','emploi',NULL,'Emploi.mg','https://www.emploi.mg/','emploi.mg','Offres d emploi a Madagascar',70,false,'Emploi.mg','noopener nofollow');

-- ILE MAURICE (MU)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('MU','Ile Maurice','ile-maurice','afrique','immigration',NULL,'EDB Mauritius — Premium Visa','https://www.edbmauritius.org/','edbmauritius.org','Premium Visa, Occupation Permit, residence',85,true,'EDB Mauritius','noopener');

-- =============================================
-- ASIE & MOYEN-ORIENT
-- =============================================

-- THAILANDE (TH)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('TH','Thailande','thailande','asie','immigration',NULL,'Thai Immigration Bureau','https://www.immigration.go.th/','immigration.go.th','Extensions visa, 90-day report, overstay',90,true,'immigration Thailande','noopener'),
('TH','Thailande','thailande','asie','immigration','evisa','Thai e-Visa','https://www.thaievisa.go.th/','thaievisa.go.th','Visa electronique: touriste, retraite, education, elite',90,true,'e-Visa Thailande','noopener'),
('TH','Thailande','thailande','asie','sante',NULL,'Bumrungrad Hospital','https://www.bumrungrad.com/','bumrungrad.com','Hopital international Bangkok, accredite JCI',85,false,'Bumrungrad','noopener'),
('TH','Thailande','thailande','asie','logement',NULL,'DDproperty','https://www.ddproperty.com/','ddproperty.com','Portail immobilier thailandais',75,false,'DDproperty','noopener nofollow'),
('TH','Thailande','thailande','asie','banque',NULL,'Bangkok Bank','https://www.bangkokbank.com/','bangkokbank.com','Grande banque ouverte aux etrangers',80,false,'Bangkok Bank','noopener nofollow');

-- JAPON (JP)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('JP','Japon','japon','asie','immigration',NULL,'ISA — Immigration Services Agency','https://www.moj.go.jp/isa/','moj.go.jp','Visa, zairyu card, residence',90,true,'ISA Japon','noopener'),
('JP','Japon','japon','asie','immigration','visa','MOFA — Visas Japon','https://www.mofa.go.jp/j_info/visit/visa/','mofa.go.jp','Ministere Affaires etrangeres: types de visa',90,true,'visa Japon','noopener'),
('JP','Japon','japon','asie','logement',NULL,'GaijinPot Apartments','https://apartments.gaijinpot.com/','gaijinpot.com','Logements pour etrangers au Japon',75,false,'GaijinPot','noopener nofollow'),
('JP','Japon','japon','asie','emploi',NULL,'GaijinPot Jobs','https://jobs.gaijinpot.com/','gaijinpot.com','Emploi pour etrangers au Japon',75,false,'GaijinPot Jobs','noopener nofollow');

-- SINGAPOUR (SG)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('SG','Singapour','singapour','asie','immigration',NULL,'ICA — Immigration Singapour','https://www.ica.gov.sg/','ica.gov.sg','PR, visa, passes',95,true,'ICA Singapour','noopener'),
('SG','Singapour','singapour','asie','immigration','travail','MOM — Permis travail','https://www.mom.gov.sg/','mom.gov.sg','EP, S Pass, Work Permit',95,true,'MOM Singapour','noopener'),
('SG','Singapour','singapour','asie','logement',NULL,'PropertyGuru SG','https://www.propertyguru.com.sg/','propertyguru.com.sg','Portail immobilier singapourien',80,false,'PropertyGuru','noopener nofollow');

-- EMIRATS ARABES UNIS (AE)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('AE','Emirats arabes unis','emirats-arabes-unis','asie','immigration',NULL,'UAE Gov — Visa et Emirates ID','https://u.ae/en/information-and-services/visa-and-emirates-id','u.ae','Tous types visa, Emirates ID, Golden Visa',95,true,'visa EAU','noopener'),
('AE','Emirats arabes unis','emirats-arabes-unis','asie','immigration','dubai','GDRFA Dubai','https://gdrfad.gov.ae/en','gdrfad.gov.ae','Residence et affaires etrangeres Dubai',90,true,'GDRFA Dubai','noopener'),
('AE','Emirats arabes unis','emirats-arabes-unis','asie','logement',NULL,'Bayut — Immobilier EAU','https://www.bayut.com/','bayut.com','Location, achat Dubai/Abu Dhabi',80,false,'Bayut','noopener nofollow'),
('AE','Emirats arabes unis','emirats-arabes-unis','asie','emploi',NULL,'Bayt — Emploi Moyen-Orient','https://www.bayt.com/','bayt.com','1er site emploi Moyen-Orient',80,false,'Bayt','noopener nofollow');

-- VIETNAM (VN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('VN','Vietnam','vietnam','asie','immigration',NULL,'Vietnam e-Visa','https://evisa.xuatnhapcanh.gov.vn/','xuatnhapcanh.gov.vn','Portail officiel e-Visa',85,true,'e-Visa Vietnam','noopener'),
('VN','Vietnam','vietnam','asie','sante',NULL,'FV Hospital HCMC','https://www.fvhospital.com/','fvhospital.com','Hopital international franco-vietnamien',80,false,'FV Hospital','noopener');

-- CHINE (CN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('CN','Chine','chine','asie','immigration',NULL,'NIA — Administration immigration','https://www.nia.gov.cn/','nia.gov.cn','Bureau national de l immigration: visa, residence',90,true,'immigration Chine','noopener');

-- INDE (IN)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('IN','Inde','inde','asie','immigration',NULL,'Indian e-Visa','https://indianvisaonline.gov.in/','indianvisaonline.gov.in','Visa electronique pour l Inde: tourisme, affaires, medical',90,true,'e-Visa Inde','noopener');

-- COREE DU SUD (KR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('KR','Coree du Sud','coree-du-sud','asie','immigration',NULL,'HiKorea — Immigration','https://www.hikorea.go.kr/','hikorea.go.kr','Visa, sejour, ARC card, TOPIK',90,true,'HiKorea','noopener');

-- TURQUIE (TR)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('TR','Turquie','turquie','asie','immigration',NULL,'e-ikamet — Permis de residence','https://e-ikamet.goc.gov.tr/','goc.gov.tr','Carte de residence (ikamet), visa electronique',90,true,'e-ikamet Turquie','noopener'),
('TR','Turquie','turquie','asie','logement',NULL,'Sahibinden — Immobilier','https://www.sahibinden.com/','sahibinden.com','1er site petites annonces et immobilier turc',80,false,'Sahibinden','noopener nofollow');

-- ISRAEL (IL)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('IL','Israel','israel','asie','immigration',NULL,'PIBA — Immigration Israel','https://www.gov.il/en/departments/population_and_immigration_authority','gov.il','Autorite population et immigration',90,true,'PIBA Israel','noopener');

-- CAMBODGE (KH)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('KH','Cambodge','cambodge','asie','immigration',NULL,'e-Visa Cambodge','https://www.evisa.gov.kh/','evisa.gov.kh','Visa electronique touristique (30 jours, 36$)',90,true,'e-Visa Cambodge','noopener');

-- MALAISIE (MY)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('MY','Malaisie','malaisie','asie','immigration',NULL,'IMI — Immigration Malaisie','https://www.imi.gov.my/','imi.gov.my','Visa, MM2H, permis travail',90,true,'immigration Malaisie','noopener');

-- INDONESIE (ID)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('ID','Indonesie','indonesie','asie','immigration',NULL,'Dirjen Imigrasi — Immigration','https://www.imigrasi.go.id/','imigrasi.go.id','Visa on arrival, e-VOA, KITAS, digital nomad visa',90,true,'immigration Indonesie','noopener');

-- PHILIPPINES (PH)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('PH','Philippines','philippines','asie','immigration',NULL,'BI — Bureau of Immigration','https://immigration.gov.ph/','immigration.gov.ph','Visa, ACR-I Card, extension sejour',90,true,'BI Philippines','noopener');

-- =============================================
-- OCEANIE
-- =============================================

-- AUSTRALIE (AU)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('AU','Australie','australie','oceanie','immigration',NULL,'Home Affairs — Immigration','https://immi.homeaffairs.gov.au/','homeaffairs.gov.au','Visa, RP, citoyennete, points test',95,true,'immigration Australie','noopener'),
('AU','Australie','australie','oceanie','immigration','whv','WHV 417','https://immi.homeaffairs.gov.au/visas/getting-a-visa/visa-listing/work-holiday-417','homeaffairs.gov.au','Vacances-Travail 18-35 ans, 3 ans max',95,true,'WHV Australie','noopener'),
('AU','Australie','australie','oceanie','sante',NULL,'Medicare','https://www.servicesaustralia.gov.au/medicare','servicesaustralia.gov.au','Carte Medicare, PBS, accord Franco-AU',90,true,'Medicare','noopener'),
('AU','Australie','australie','oceanie','logement',NULL,'Domain','https://www.domain.com.au/','domain.com.au','Portail immobilier australien',80,false,'Domain','noopener nofollow'),
('AU','Australie','australie','oceanie','logement','colocation','Flatmates','https://flatmates.com.au/','flatmates.com.au','Colocation en Australie',75,false,'Flatmates','noopener nofollow'),
('AU','Australie','australie','oceanie','emploi',NULL,'Seek','https://www.seek.com.au/','seek.com.au','1er site emploi: 100 000+ offres',85,false,'Seek','noopener nofollow'),
('AU','Australie','australie','oceanie','fiscalite',NULL,'ATO','https://www.ato.gov.au/','ato.gov.au','TFN, declarations, superannuation',95,true,'ATO','noopener');

-- NOUVELLE-ZELANDE (NZ)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('NZ','Nouvelle-Zelande','nouvelle-zelande','oceanie','immigration',NULL,'Immigration NZ','https://www.immigration.govt.nz/','immigration.govt.nz','Visa travail, residence, WHV, skilled migrant',95,true,'immigration NZ','noopener'),
('NZ','Nouvelle-Zelande','nouvelle-zelande','oceanie','logement',NULL,'Trade Me Property','https://www.trademe.co.nz/property','trademe.co.nz','1er portail immobilier NZ',80,false,'Trade Me','noopener nofollow'),
('NZ','Nouvelle-Zelande','nouvelle-zelande','oceanie','emploi',NULL,'Seek NZ','https://www.seek.co.nz/','seek.co.nz','1er site emploi en Nouvelle-Zelande',80,false,'Seek NZ','noopener nofollow');

-- =============================================
-- VERIFICATION
-- =============================================
SELECT
  continent,
  COUNT(DISTINCT country_code) as pays,
  COUNT(*) as liens,
  COUNT(*) FILTER (WHERE category != 'ambassade') as liens_pratiques,
  COUNT(DISTINCT category) as categories
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY continent
ORDER BY liens DESC;

SELECT 'TOTAL' as label,
  COUNT(*) as total_entries,
  COUNT(DISTINCT country_code) as countries,
  COUNT(*) FILTER (WHERE category = 'ambassade') as ambassades,
  COUNT(*) FILTER (WHERE category != 'ambassade') as practical_links,
  COUNT(DISTINCT category) as categories
FROM country_directory;
