-- =============================================
-- ANNUAIRE GLOBAL SOS-EXPAT
-- Liens officiels et ressources essentielles par pays
-- Objectif: alimenter external_links du blog + generation_source_items de Mission Control
--
-- Structure: 1 INSERT par lien, classe par pays + categorie
-- Categories: ambassade, immigration, sante, banque, logement, emploi, education, transport, telecom, urgences, communaute, fiscalite, juridique
-- =============================================

-- Ensure annuaire category exists in generation_source_categories
INSERT INTO generation_source_categories (slug, name, description, icon, sort_order) VALUES
  ('annuaire', 'Annuaire Pays', 'Liens officiels et ressources essentielles par pays: ambassades, immigration, sante, banque, logement, emploi, urgences', 'book-open', 0)
ON CONFLICT (slug) DO UPDATE SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  icon = EXCLUDED.icon,
  sort_order = EXCLUDED.sort_order;

-- =============================================
-- TABLE TEMPORAIRE pour import propre
-- =============================================
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_annuaire (
  country_name VARCHAR(100) NOT NULL,
  country_slug VARCHAR(100) NOT NULL,
  country_code CHAR(2) NOT NULL,
  continent VARCHAR(50) NOT NULL,
  category VARCHAR(50) NOT NULL,
  title VARCHAR(300) NOT NULL,
  url VARCHAR(1000) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  description TEXT,
  language VARCHAR(10) DEFAULT 'fr',
  trust_score INTEGER DEFAULT 80,
  is_official BOOLEAN DEFAULT true,
  emergency_number VARCHAR(50) DEFAULT NULL
);

-- =============================================
-- RESSOURCES GLOBALES (valables pour tous les pays)
-- =============================================
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score) VALUES
-- Ambassades & Consulats - Repertoire global
('Global', 'global', 'XX', 'global', 'ambassade', 'Annuaire des ambassades et consulats francais', 'https://www.diplomatie.gouv.fr/fr/le-ministere-et-son-reseau/organisation-et-annuaires/ambassades-et-consulats-francais-a-l-etranger/', 'diplomatie.gouv.fr', 'Repertoire officiel du Ministere des Affaires etrangeres de toutes les representations francaises dans le monde', 95),
('Global', 'global', 'XX', 'global', 'ambassade', 'France-Visas - Portail officiel des visas pour la France', 'https://france-visas.gouv.fr/en/', 'france-visas.gouv.fr', 'Plateforme officielle pour les demandes de visa pour la France', 95),
('Global', 'global', 'XX', 'global', 'sante', 'CFE - Caisse des Francais de l Etranger', 'https://www.cfe.fr/', 'cfe.fr', 'Securite sociale pour les expatries francais: maladie, maternite, invalidite, accidents du travail', 95),
('Global', 'global', 'XX', 'global', 'sante', 'APRIL International - Assurance expatrie', 'https://www.april-international.com/', 'april-international.com', 'Assurance sante internationale pour expatries et voyageurs', 80),
('Global', 'global', 'XX', 'global', 'communaute', 'Expat.com - Communaute et guides pays', 'https://www.expat.com/', 'expat.com', 'Forum et guides pour expatries avec communautes par pays', 75),
('Global', 'global', 'XX', 'global', 'communaute', 'InterNations - Reseau social expatries', 'https://www.internations.org/', 'internations.org', 'Reseau social pour expatries: evenements, groupes par ville, guides', 75),
('Global', 'global', 'XX', 'global', 'communaute', 'Expatica - Guides expatriation par pays', 'https://www.expatica.com/', 'expatica.com', 'Guides complets par pays: logement, emploi, visa, sante, banque', 75),
('Global', 'global', 'XX', 'global', 'banque', 'Numbeo - Cout de la vie par pays', 'https://www.numbeo.com/cost-of-living/', 'numbeo.com', 'Base de donnees collaborative du cout de la vie dans le monde: loyers, alimentation, transports', 80),
('Global', 'global', 'XX', 'global', 'banque', 'Wise (ex-TransferWise) - Transferts internationaux', 'https://wise.com/', 'wise.com', 'Transferts d argent internationaux a taux reel, compte multi-devises', 85),
('Global', 'global', 'XX', 'global', 'banque', 'Revolut - Banque internationale', 'https://www.revolut.com/', 'revolut.com', 'Compte bancaire international, change de devises, paiements a l etranger', 80),
('Global', 'global', 'XX', 'global', 'fiscalite', 'Service-Public.fr - Impots des Francais a l etranger', 'https://www.service-public.fr/particuliers/vosdroits/N31477', 'service-public.fr', 'Obligations fiscales des Francais vivant a l etranger: declarations, conventions fiscales', 95),
('Global', 'global', 'XX', 'global', 'fiscalite', 'Impots.gouv.fr - Non-residents', 'https://www.impots.gouv.fr/international-particulier', 'impots.gouv.fr', 'Service des impots des particuliers non-residents', 95),
('Global', 'global', 'XX', 'global', 'juridique', 'Service-Public.fr - Francais a l etranger', 'https://www.service-public.fr/particuliers/vosdroits/N120', 'service-public.fr', 'Toutes les demarches administratives pour les Francais a l etranger', 95),
('Global', 'global', 'XX', 'global', 'emploi', 'Pole Emploi International', 'https://www.pole-emploi.fr/candidat/vos-recherches/trouver-un-emploi-a-l-etranger.html', 'pole-emploi.fr', 'Recherche d emploi a l etranger, conseils, offres', 90),
('Global', 'global', 'XX', 'global', 'education', 'AEFE - Agence pour l enseignement francais a l etranger', 'https://www.aefe.fr/', 'aefe.fr', 'Reseau des 580 etablissements d enseignement francais dans 139 pays', 95);

-- =============================================
-- EUROPE
-- =============================================

-- ALLEMAGNE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Allemagne', 'allemagne', 'DE', 'europe', 'ambassade', 'Ambassade de France en Allemagne', 'https://de.ambafrance.org/', 'de.ambafrance.org', 'Services consulaires, inscription registre, elections', 95, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'immigration', 'Auswaertiges Amt - Visas pour l Allemagne', 'https://www.auswaertiges-amt.de/en/visa-service', 'auswaertiges-amt.de', 'Ministere federal des Affaires etrangeres: types de visa, conditions', 95, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'immigration', 'BAMF - Office federal des migrations', 'https://www.bamf.de/EN/Startseite/startseite_node.html', 'bamf.de', 'Droit de sejour, naturalisation, cours d integration', 95, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'sante', 'AOK - Assurance maladie publique', 'https://www.aok.de/pk/', 'aok.de', 'Plus grande caisse d assurance maladie publique allemande', 85, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'sante', 'TK - Techniker Krankenkasse', 'https://www.tk.de/en', 'tk.de', 'Assurance maladie publique tres populaire aupres des expatries', 85, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'banque', 'N26 - Banque en ligne', 'https://n26.com/', 'n26.com', 'Banque en ligne allemande, ouverture de compte rapide pour expatries', 80, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'logement', 'ImmobilienScout24 - Logement', 'https://www.immobilienscout24.de/', 'immobilienscout24.de', 'Premier portail immobilier allemand: location, achat', 80, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'logement', 'WG-Gesucht - Colocation', 'https://www.wg-gesucht.de/', 'wg-gesucht.de', 'Plateforme de colocation et sous-location en Allemagne', 75, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'emploi', 'Bundesagentur fuer Arbeit - Emploi', 'https://www.arbeitsagentur.de/', 'arbeitsagentur.de', 'Agence federale pour l emploi: offres, aides, formation', 90, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'emploi', 'Make it in Germany - Portail officiel travail', 'https://www.make-it-in-germany.com/en/', 'make-it-in-germany.com', 'Portail officiel du gouvernement pour travailler en Allemagne', 90, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'transport', 'Deutsche Bahn - Trains', 'https://www.bahn.de/en', 'bahn.de', 'Chemins de fer allemands: horaires, billets, abonnements', 85, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'urgences', 'Numeros d urgence Allemagne', 'https://de.ambafrance.org/Numeros-utiles-en-Allemagne', 'de.ambafrance.org', 'Police 110, Pompiers/SAMU 112, SOS Medecin', 95, '112'),
('Allemagne', 'allemagne', 'DE', 'europe', 'fiscalite', 'Bundeszentralamt fuer Steuern - Impots', 'https://www.bzst.de/EN/Home/home_node.html', 'bzst.de', 'Office federal central des impots: numero fiscal, conventions', 90, NULL),
('Allemagne', 'allemagne', 'DE', 'europe', 'education', 'DAAD - Etudier en Allemagne', 'https://www.daad.de/en/', 'daad.de', 'Office allemand d echanges universitaires: bourses, programmes', 90, NULL);

-- ESPAGNE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Espagne', 'espagne', 'ES', 'europe', 'ambassade', 'Ambassade de France en Espagne', 'https://es.ambafrance.org/', 'es.ambafrance.org', 'Services consulaires, inscription registre, elections', 95, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'immigration', 'Extranjeria - Immigration Espagne', 'https://www.inclusion.gob.es/web/migraciones/', 'inclusion.gob.es', 'Secretariat d Etat aux Migrations: permis de sejour, NIE', 90, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'immigration', 'NIE et residence - Policia Nacional', 'https://www.policia.es/documentacion/extranjeros.html', 'policia.es', 'Demarches NIE, carte de residence pour etrangers', 90, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'sante', 'Seguridad Social - Securite sociale espagnole', 'https://www.seg-social.es/', 'seg-social.es', 'Affiliation, prestations, couverture maladie', 90, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'banque', 'Idealista - Logement Espagne', 'https://www.idealista.com/', 'idealista.com', 'Premier portail immobilier espagnol: location, achat, colocation', 80, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'logement', 'Fotocasa - Immobilier Espagne', 'https://www.fotocasa.es/', 'fotocasa.es', 'Portail immobilier espagnol: location, achat', 80, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'emploi', 'InfoJobs - Emploi Espagne', 'https://www.infojobs.net/', 'infojobs.net', 'Premier site d emploi en Espagne', 80, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'emploi', 'SEPE - Service public emploi espagnol', 'https://www.sepe.es/', 'sepe.es', 'Service public d emploi: inscriptions, prestations chomage', 90, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'fiscalite', 'Agencia Tributaria - Impots Espagne', 'https://sede.agenciatributaria.gob.es/', 'agenciatributaria.gob.es', 'Administration fiscale espagnole: declarations, NIE fiscal', 90, NULL),
('Espagne', 'espagne', 'ES', 'europe', 'urgences', 'Numeros d urgence Espagne', 'https://es.ambafrance.org/Numeros-utiles', 'es.ambafrance.org', 'Urgences 112, Police 091, Guardia Civil 062', 95, '112'),
('Espagne', 'espagne', 'ES', 'europe', 'transport', 'Renfe - Trains Espagne', 'https://www.renfe.com/es/en', 'renfe.com', 'Chemins de fer espagnols: AVE, horaires, billets', 85, NULL);

-- PORTUGAL
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Portugal', 'portugal', 'PT', 'europe', 'ambassade', 'Ambassade de France au Portugal', 'https://pt.ambafrance.org/', 'pt.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Portugal', 'portugal', 'PT', 'europe', 'immigration', 'AIMA - Agence pour l integration et les migrations', 'https://www.aima.gov.pt/', 'aima.gov.pt', 'Anciennement SEF: titres de sejour, visa D7, NHR', 90, NULL),
('Portugal', 'portugal', 'PT', 'europe', 'sante', 'SNS - Service national de sante', 'https://www.sns.gov.pt/', 'sns.gov.pt', 'Systeme de sante public portugais', 90, NULL),
('Portugal', 'portugal', 'PT', 'europe', 'logement', 'Idealista Portugal', 'https://www.idealista.pt/', 'idealista.pt', 'Portail immobilier au Portugal: location, achat', 80, NULL),
('Portugal', 'portugal', 'PT', 'europe', 'emploi', 'Net-Empregos - Emploi Portugal', 'https://www.net-empregos.com/', 'net-empregos.com', 'Site d emploi au Portugal', 75, NULL),
('Portugal', 'portugal', 'PT', 'europe', 'fiscalite', 'Portal das Financas - Impots Portugal', 'https://www.portaldasfinancas.gov.pt/', 'portaldasfinancas.gov.pt', 'Portail fiscal portugais: NIF, declarations, statut RNH', 90, NULL),
('Portugal', 'portugal', 'PT', 'europe', 'urgences', 'Numeros d urgence Portugal', 'https://pt.ambafrance.org/Numeros-utiles', 'pt.ambafrance.org', 'Urgences 112, Police PSP 21 765 42 42', 95, '112');

-- ROYAUME-UNI
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'ambassade', 'Ambassade de France au Royaume-Uni', 'https://uk.ambafrance.org/', 'uk.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'immigration', 'UK Visas and Immigration - GOV.UK', 'https://www.gov.uk/browse/visas-immigration', 'gov.uk', 'Immigration officielle UK: visa, settled status, points-based', 95, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'sante', 'NHS - National Health Service', 'https://www.nhs.uk/', 'nhs.uk', 'Systeme de sante public britannique', 95, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'logement', 'Rightmove - Immobilier UK', 'https://www.rightmove.co.uk/', 'rightmove.co.uk', 'Premier portail immobilier britannique: location, achat', 85, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'logement', 'SpareRoom - Colocation UK', 'https://www.spareroom.co.uk/', 'spareroom.co.uk', 'Plateforme de colocation au Royaume-Uni', 80, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'emploi', 'GOV.UK - Find a Job', 'https://www.gov.uk/find-a-job', 'gov.uk', 'Service public de recherche d emploi au UK', 90, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'emploi', 'Reed - Emploi UK', 'https://www.reed.co.uk/', 'reed.co.uk', 'Grand site d emploi britannique', 80, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'fiscalite', 'HMRC - Impots UK', 'https://www.gov.uk/government/organisations/hm-revenue-customs', 'gov.uk', 'Administration fiscale britannique: National Insurance, Self Assessment', 95, NULL),
('Royaume-Uni', 'royaume-uni', 'GB', 'europe', 'urgences', 'Numeros d urgence UK', 'https://uk.ambafrance.org/Numeros-utiles', 'uk.ambafrance.org', 'Urgences 999/112, Police 101, NHS 111', 95, '999');

-- BELGIQUE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Belgique', 'belgique', 'BE', 'europe', 'ambassade', 'Ambassade de France en Belgique', 'https://be.ambafrance.org/', 'be.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Belgique', 'belgique', 'BE', 'europe', 'immigration', 'Office des Etrangers - SPF Interieur', 'https://dofi.ibz.be/', 'dofi.ibz.be', 'Office des etrangers: visa, sejour, asile', 90, NULL),
('Belgique', 'belgique', 'BE', 'europe', 'sante', 'INAMI - Assurance maladie Belgique', 'https://www.inami.fgov.be/', 'inami.fgov.be', 'Institut national d assurance maladie-invalidite', 90, NULL),
('Belgique', 'belgique', 'BE', 'europe', 'logement', 'Immoweb - Immobilier Belgique', 'https://www.immoweb.be/', 'immoweb.be', 'Premier portail immobilier belge: location, achat', 85, NULL),
('Belgique', 'belgique', 'BE', 'europe', 'emploi', 'Forem - Emploi Wallonie', 'https://www.leforem.be/', 'leforem.be', 'Service public de l emploi en Wallonie', 90, NULL),
('Belgique', 'belgique', 'BE', 'europe', 'emploi', 'Actiris - Emploi Bruxelles', 'https://www.actiris.brussels/', 'actiris.brussels', 'Service regional bruxellois de l emploi', 90, NULL),
('Belgique', 'belgique', 'BE', 'europe', 'urgences', 'Numeros d urgence Belgique', 'https://be.ambafrance.org/Numeros-utiles', 'be.ambafrance.org', 'Urgences 112, Police 101, Centre anti-poisons 070 245 245', 95, '112');

-- SUISSE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Suisse', 'suisse', 'CH', 'europe', 'ambassade', 'Ambassade de France en Suisse', 'https://ch.ambafrance.org/', 'ch.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Suisse', 'suisse', 'CH', 'europe', 'immigration', 'SEM - Secretariat d Etat aux migrations', 'https://www.sem.admin.ch/sem/fr/home.html', 'sem.admin.ch', 'Autorisations de sejour, permis B/C/L/G, naturalisation', 95, NULL),
('Suisse', 'suisse', 'CH', 'europe', 'sante', 'Comparis - Comparateur assurance maladie', 'https://www.comparis.ch/krankenkassen', 'comparis.ch', 'Comparateur d assurances maladie obligatoires (LAMal)', 80, NULL),
('Suisse', 'suisse', 'CH', 'europe', 'banque', 'PostFinance - Banque suisse', 'https://www.postfinance.ch/', 'postfinance.ch', 'Banque suisse accessible: ouverture compte pour nouveaux residents', 80, NULL),
('Suisse', 'suisse', 'CH', 'europe', 'logement', 'Homegate - Immobilier Suisse', 'https://www.homegate.ch/', 'homegate.ch', 'Portail immobilier suisse: location, achat', 80, NULL),
('Suisse', 'suisse', 'CH', 'europe', 'emploi', 'jobs.ch - Emploi Suisse', 'https://www.jobs.ch/', 'jobs.ch', 'Premier site d emploi en Suisse', 80, NULL),
('Suisse', 'suisse', 'CH', 'europe', 'transport', 'CFF - Chemins de fer federaux', 'https://www.sbb.ch/fr', 'sbb.ch', 'Trains suisses: horaires, billets, abonnements GA/demi-tarif', 90, NULL),
('Suisse', 'suisse', 'CH', 'europe', 'urgences', 'Numeros d urgence Suisse', 'https://ch.ambafrance.org/Numeros-utiles', 'ch.ambafrance.org', 'Police 117, Pompiers 118, Ambulance 144, REGA 1414', 95, '112');

-- ITALIE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Italie', 'italie', 'IT', 'europe', 'ambassade', 'Ambassade de France en Italie', 'https://it.ambafrance.org/', 'it.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Italie', 'italie', 'IT', 'europe', 'immigration', 'Portale Immigrazione - Permis de sejour', 'https://www.portaleimmigrazione.it/', 'portaleimmigrazione.it', 'Portail officiel pour les demandes de permis de sejour', 90, NULL),
('Italie', 'italie', 'IT', 'europe', 'sante', 'SSN - Servizio Sanitario Nazionale', 'https://www.salute.gov.it/', 'salute.gov.it', 'Ministere de la Sante: systeme de sante public italien', 90, NULL),
('Italie', 'italie', 'IT', 'europe', 'logement', 'Immobiliare.it - Immobilier Italie', 'https://www.immobiliare.it/', 'immobiliare.it', 'Premier portail immobilier italien: location, achat', 80, NULL),
('Italie', 'italie', 'IT', 'europe', 'emploi', 'InfoJobs Italia - Emploi Italie', 'https://www.infojobs.it/', 'infojobs.it', 'Grand site d emploi en Italie', 80, NULL),
('Italie', 'italie', 'IT', 'europe', 'fiscalite', 'Agenzia delle Entrate - Impots Italie', 'https://www.agenziaentrate.gov.it/', 'agenziaentrate.gov.it', 'Administration fiscale italienne: codice fiscale, declarations', 90, NULL),
('Italie', 'italie', 'IT', 'europe', 'urgences', 'Numeros d urgence Italie', 'https://it.ambafrance.org/Numeros-utiles', 'it.ambafrance.org', 'Urgences 112, Carabinieri 112, Police 113, Ambulance 118', 95, '112');

-- PAYS-BAS
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Pays-Bas', 'pays-bas', 'NL', 'europe', 'ambassade', 'Ambassade de France aux Pays-Bas', 'https://nl.ambafrance.org/', 'nl.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Pays-Bas', 'pays-bas', 'NL', 'europe', 'immigration', 'IND - Immigration et Naturalisation', 'https://ind.nl/en', 'ind.nl', 'Service d immigration neerlandais: permis de sejour, MVV, 30% ruling', 95, NULL),
('Pays-Bas', 'pays-bas', 'NL', 'europe', 'sante', 'Zorgverzekering - Assurance maladie NL', 'https://www.government.nl/topics/health-insurance', 'government.nl', 'Assurance maladie obligatoire aux Pays-Bas', 90, NULL),
('Pays-Bas', 'pays-bas', 'NL', 'europe', 'logement', 'Funda - Immobilier Pays-Bas', 'https://www.funda.nl/', 'funda.nl', 'Premier portail immobilier neerlandais', 85, NULL),
('Pays-Bas', 'pays-bas', 'NL', 'europe', 'emploi', 'UWV - Service emploi Pays-Bas', 'https://www.uwv.nl/', 'uwv.nl', 'Organisme public emploi et assurance chomage', 90, NULL),
('Pays-Bas', 'pays-bas', 'NL', 'europe', 'urgences', 'Numeros d urgence Pays-Bas', 'https://nl.ambafrance.org/Numeros-utiles', 'nl.ambafrance.org', 'Urgences 112, Police 0900 8844', 95, '112');

-- LUXEMBOURG
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Luxembourg', 'luxembourg', 'LU', 'europe', 'ambassade', 'Ambassade de France au Luxembourg', 'https://lu.ambafrance.org/', 'lu.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Luxembourg', 'luxembourg', 'LU', 'europe', 'immigration', 'Guichet.lu - Immigration Luxembourg', 'https://guichet.public.lu/fr/citoyens/immigration.html', 'guichet.public.lu', 'Portail officiel: visa, titre de sejour, travail au Luxembourg', 95, NULL),
('Luxembourg', 'luxembourg', 'LU', 'europe', 'sante', 'CNS - Caisse Nationale de Sante', 'https://cns.public.lu/', 'cns.public.lu', 'Assurance maladie obligatoire luxembourgeoise', 90, NULL),
('Luxembourg', 'luxembourg', 'LU', 'europe', 'logement', 'Athome.lu - Immobilier Luxembourg', 'https://www.athome.lu/', 'athome.lu', 'Portail immobilier luxembourgeois', 80, NULL),
('Luxembourg', 'luxembourg', 'LU', 'europe', 'emploi', 'ADEM - Emploi Luxembourg', 'https://adem.public.lu/', 'adem.public.lu', 'Agence pour le developpement de l emploi', 90, NULL),
('Luxembourg', 'luxembourg', 'LU', 'europe', 'urgences', 'Numeros d urgence Luxembourg', 'https://lu.ambafrance.org/Numeros-utiles', 'lu.ambafrance.org', 'Urgences 112, Police 113', 95, '112');

-- =============================================
-- AMERIQUES
-- =============================================

-- ETATS-UNIS
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'ambassade', 'Ambassade de France aux USA', 'https://franceintheus.org/', 'franceintheus.org', 'Ambassade et 10 consulats generaux', 95, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'immigration', 'USCIS - Immigration USA', 'https://www.uscis.gov/', 'uscis.gov', 'Service de citoyennete et d immigration: green card, visa, naturalisation', 95, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'immigration', 'Travel.State.Gov - Visas USA', 'https://travel.state.gov/content/travel/en/us-visas.html', 'travel.state.gov', 'Departement d Etat: tous les types de visa americains', 95, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'immigration', 'ESTA - Autorisation de voyage', 'https://esta.cbp.dhs.gov/', 'esta.cbp.dhs.gov', 'Autorisation electronique de voyage pour le programme d exemption de visa', 95, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'sante', 'Healthcare.gov - Assurance sante USA', 'https://www.healthcare.gov/', 'healthcare.gov', 'Marche de l assurance sante (Obamacare)', 90, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'banque', 'IRS - Internal Revenue Service', 'https://www.irs.gov/', 'irs.gov', 'Administration fiscale americaine: ITIN, declarations, FATCA', 95, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'logement', 'Zillow - Immobilier USA', 'https://www.zillow.com/', 'zillow.com', 'Premier portail immobilier americain: location, achat', 85, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'logement', 'Apartments.com - Location USA', 'https://www.apartments.com/', 'apartments.com', 'Recherche d appartements en location aux USA', 80, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'emploi', 'USAJobs - Emploi federal', 'https://www.usajobs.gov/', 'usajobs.gov', 'Site officiel des offres d emploi du gouvernement federal', 90, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'emploi', 'LinkedIn - Emploi USA', 'https://www.linkedin.com/jobs/', 'linkedin.com', 'Premier reseau professionnel pour la recherche d emploi', 85, NULL),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'urgences', 'Numeros d urgence USA', 'https://franceintheus.org/spip.php?article637', 'franceintheus.org', 'Urgences 911, FBI, poison control', 95, '911'),
('Etats-Unis', 'etats-unis', 'US', 'amerique-nord', 'education', 'EducationUSA - Etudier aux USA', 'https://educationusa.state.gov/', 'educationusa.state.gov', 'Reseau officiel pour les etudiants internationaux aux USA', 90, NULL);

-- CANADA
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Canada', 'canada', 'CA', 'amerique-nord', 'ambassade', 'Ambassade de France au Canada', 'https://ca.ambafrance.org/', 'ca.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'immigration', 'IRCC - Immigration Canada', 'https://www.canada.ca/en/immigration-refugees-citizenship.html', 'canada.ca', 'Immigration, Refugies et Citoyennete Canada: residence permanente, PVT, visa travail', 95, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'immigration', 'PVT Canada - EIC', 'https://www.canada.ca/en/immigration-refugees-citizenship/services/work-canada/iec.html', 'canada.ca', 'Programme Vacances-Travail et Experience internationale Canada', 95, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'sante', 'RAMQ - Assurance maladie Quebec', 'https://www.ramq.gouv.qc.ca/', 'ramq.gouv.qc.ca', 'Regie de l assurance maladie du Quebec', 90, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'logement', 'Realtor.ca - Immobilier Canada', 'https://www.realtor.ca/', 'realtor.ca', 'Portail immobilier officiel canadien', 85, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'logement', 'Kijiji - Petites annonces Canada', 'https://www.kijiji.ca/', 'kijiji.ca', 'Annonces de location, colocation, meubles au Canada', 75, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'emploi', 'Job Bank Canada - Emploi', 'https://www.jobbank.gc.ca/', 'jobbank.gc.ca', 'Banque d emplois officielle du gouvernement canadien', 90, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'emploi', 'Indeed Canada - Emploi', 'https://ca.indeed.com/', 'indeed.com', 'Moteur de recherche d emploi au Canada', 80, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'fiscalite', 'CRA - Agence du revenu du Canada', 'https://www.canada.ca/en/revenue-agency.html', 'canada.ca', 'Administration fiscale canadienne: NAS, declarations, credits', 95, NULL),
('Canada', 'canada', 'CA', 'amerique-nord', 'urgences', 'Numeros d urgence Canada', 'https://ca.ambafrance.org/Numeros-utiles', 'ca.ambafrance.org', 'Urgences 911, Info-Sante 811 (Quebec)', 95, '911');

-- MEXIQUE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Mexique', 'mexique', 'MX', 'amerique-nord', 'ambassade', 'Ambassade de France au Mexique', 'https://mx.ambafrance.org/', 'mx.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Mexique', 'mexique', 'MX', 'amerique-nord', 'immigration', 'INM - Institut National de Migration', 'https://www.gob.mx/inm', 'gob.mx', 'Immigration officielle: FMM, residence temporaire/permanente', 90, NULL),
('Mexique', 'mexique', 'MX', 'amerique-nord', 'sante', 'IMSS - Securite sociale Mexique', 'https://www.imss.gob.mx/', 'imss.gob.mx', 'Institut Mexicain de la Securite Sociale', 90, NULL),
('Mexique', 'mexique', 'MX', 'amerique-nord', 'urgences', 'Numeros d urgence Mexique', 'https://mx.ambafrance.org/Numeros-utiles', 'mx.ambafrance.org', 'Urgences 911, Cruz Roja 065, Police 060', 95, '911');

-- BRESIL
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Bresil', 'bresil', 'BR', 'amerique-sud', 'ambassade', 'Ambassade de France au Bresil', 'https://br.ambafrance.org/', 'br.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Bresil', 'bresil', 'BR', 'amerique-sud', 'immigration', 'Policia Federal - Immigration Bresil', 'https://www.gov.br/pf/pt-br/assuntos/imigracao', 'gov.br', 'Police federale: visa, residence, CPF etranger', 90, NULL),
('Bresil', 'bresil', 'BR', 'amerique-sud', 'sante', 'SUS - Systeme unique de sante', 'https://www.gov.br/saude/', 'gov.br', 'Systeme de sante public bresilien: gratuit pour tous', 85, NULL),
('Bresil', 'bresil', 'BR', 'amerique-sud', 'urgences', 'Numeros d urgence Bresil', 'https://br.ambafrance.org/Numeros-utiles', 'br.ambafrance.org', 'SAMU 192, Pompiers 193, Police 190', 95, '190');

-- =============================================
-- AFRIQUE
-- =============================================

-- MAROC
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Maroc', 'maroc', 'MA', 'afrique', 'ambassade', 'Ambassade de France au Maroc', 'https://ma.ambafrance.org/', 'ma.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Maroc', 'maroc', 'MA', 'afrique', 'immigration', 'Consulat du Maroc - Titre de sejour', 'https://www.consulat.ma/', 'consulat.ma', 'Demarches de titre de sejour au Maroc pour etrangers', 85, NULL),
('Maroc', 'maroc', 'MA', 'afrique', 'sante', 'CNSS - Securite sociale Maroc', 'https://www.cnss.ma/', 'cnss.ma', 'Caisse nationale de securite sociale marocaine', 85, NULL),
('Maroc', 'maroc', 'MA', 'afrique', 'logement', 'Mubawab - Immobilier Maroc', 'https://www.mubawab.ma/', 'mubawab.ma', 'Portail immobilier marocain: location, achat', 75, NULL),
('Maroc', 'maroc', 'MA', 'afrique', 'logement', 'Avito - Annonces Maroc', 'https://www.avito.ma/', 'avito.ma', 'Petites annonces Maroc: logement, vehicules, emploi', 70, NULL),
('Maroc', 'maroc', 'MA', 'afrique', 'emploi', 'Rekrute - Emploi Maroc', 'https://www.rekrute.com/', 'rekrute.com', 'Premier site d emploi au Maroc', 75, NULL),
('Maroc', 'maroc', 'MA', 'afrique', 'banque', 'BMCE Bank of Africa', 'https://www.bankofafrica.ma/', 'bankofafrica.ma', 'Banque accessible aux expatries au Maroc', 80, NULL),
('Maroc', 'maroc', 'MA', 'afrique', 'urgences', 'Numeros d urgence Maroc', 'https://ma.ambafrance.org/Numeros-utiles', 'ma.ambafrance.org', 'Police 19, Gendarmerie 177, Pompiers 15, SAMU 141', 95, '15');

-- TUNISIE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Tunisie', 'tunisie', 'TN', 'afrique', 'ambassade', 'Ambassade de France en Tunisie', 'https://tn.ambafrance.org/', 'tn.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Tunisie', 'tunisie', 'TN', 'afrique', 'sante', 'CNAM - Securite sociale Tunisie', 'https://www.cnam.nat.tn/', 'cnam.nat.tn', 'Caisse nationale d assurance maladie tunisienne', 85, NULL),
('Tunisie', 'tunisie', 'TN', 'afrique', 'logement', 'Tayara - Annonces Tunisie', 'https://www.tayara.tn/', 'tayara.tn', 'Petites annonces Tunisie: logement, emploi', 70, NULL),
('Tunisie', 'tunisie', 'TN', 'afrique', 'urgences', 'Numeros d urgence Tunisie', 'https://tn.ambafrance.org/Numeros-utiles', 'tn.ambafrance.org', 'Police 197, SAMU 190, Pompiers 198', 95, '197');

-- SENEGAL
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Senegal', 'senegal', 'SN', 'afrique', 'ambassade', 'Ambassade de France au Senegal', 'https://sn.ambafrance.org/', 'sn.ambafrance.org', 'Services consulaires Dakar, inscription registre', 95, NULL),
('Senegal', 'senegal', 'SN', 'afrique', 'sante', 'Hopital Principal de Dakar', 'https://www.hopitalprincipal.sn/', 'hopitalprincipal.sn', 'Hopital de reference a Dakar, urgences 24h/24', 80, NULL),
('Senegal', 'senegal', 'SN', 'afrique', 'urgences', 'Numeros d urgence Senegal', 'https://sn.ambafrance.org/Numeros-utiles', 'sn.ambafrance.org', 'Police 17, Pompiers 18, SAMU 1515', 95, '17');

-- COTE D IVOIRE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Cote d Ivoire', 'cote-d-ivoire', 'CI', 'afrique', 'ambassade', 'Ambassade de France en Cote d Ivoire', 'https://ci.ambafrance.org/', 'ci.ambafrance.org', 'Services consulaires Abidjan, inscription registre', 95, NULL),
('Cote d Ivoire', 'cote-d-ivoire', 'CI', 'afrique', 'sante', 'CHU Cocody - Hopital universitaire Abidjan', 'https://www.chucocody.ci/', 'chucocody.ci', 'Centre hospitalier universitaire de reference', 75, NULL),
('Cote d Ivoire', 'cote-d-ivoire', 'CI', 'afrique', 'urgences', 'Numeros d urgence Cote d Ivoire', 'https://ci.ambafrance.org/Numeros-utiles', 'ci.ambafrance.org', 'Police 110/111, Pompiers 180, SAMU 185', 95, '110');

-- =============================================
-- ASIE
-- =============================================

-- THAILANDE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Thailande', 'thailande', 'TH', 'asie', 'ambassade', 'Ambassade de France en Thailande', 'https://th.ambafrance.org/', 'th.ambafrance.org', 'Services consulaires Bangkok, inscription registre', 95, NULL),
('Thailande', 'thailande', 'TH', 'asie', 'immigration', 'Thai Immigration Bureau', 'https://www.immigration.go.th/', 'immigration.go.th', 'Bureau d immigration: visa, extensions, 90-day report', 90, NULL),
('Thailande', 'thailande', 'TH', 'asie', 'immigration', 'Thai e-Visa', 'https://www.thaievisa.go.th/', 'thaievisa.go.th', 'Portail officiel de visa electronique pour la Thailande', 90, NULL),
('Thailande', 'thailande', 'TH', 'asie', 'sante', 'Bumrungrad Hospital Bangkok', 'https://www.bumrungrad.com/', 'bumrungrad.com', 'Hopital international de reference a Bangkok, accredite JCI', 85, NULL),
('Thailande', 'thailande', 'TH', 'asie', 'logement', 'DDproperty - Immobilier Thailande', 'https://www.ddproperty.com/', 'ddproperty.com', 'Portail immobilier thailandais: condos, maisons', 75, NULL),
('Thailande', 'thailande', 'TH', 'asie', 'logement', 'Hipflat - Location Thailande', 'https://www.hipflat.co.th/', 'hipflat.co.th', 'Recherche de condos et appartements en Thailande', 70, NULL),
('Thailande', 'thailande', 'TH', 'asie', 'banque', 'Bangkok Bank - Compte etranger', 'https://www.bangkokbank.com/', 'bangkokbank.com', 'Grande banque thailandaise ouverte aux etrangers', 80, NULL),
('Thailande', 'thailande', 'TH', 'asie', 'urgences', 'Numeros d urgence Thailande', 'https://th.ambafrance.org/Numeros-utiles', 'th.ambafrance.org', 'Police touristique 1155, Ambulance 1669, Pompiers 199', 95, '1155');

-- JAPON
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Japon', 'japon', 'JP', 'asie', 'ambassade', 'Ambassade de France au Japon', 'https://jp.ambafrance.org/', 'jp.ambafrance.org', 'Services consulaires Tokyo, inscription registre', 95, NULL),
('Japon', 'japon', 'JP', 'asie', 'immigration', 'Immigration Services Agency of Japan', 'https://www.moj.go.jp/isa/', 'moj.go.jp', 'Agence des services d immigration: visa, residence, carte zairyu', 90, NULL),
('Japon', 'japon', 'JP', 'asie', 'immigration', 'MOFA - Visas Japon', 'https://www.mofa.go.jp/j_info/visit/visa/', 'mofa.go.jp', 'Ministere des Affaires etrangeres: types de visa', 90, NULL),
('Japon', 'japon', 'JP', 'asie', 'sante', 'NHI - Assurance sante nationale Japon', 'https://www.japan-guide.com/e/e2202.html', 'japan-guide.com', 'Guide de l assurance sante nationale obligatoire au Japon', 75, NULL),
('Japon', 'japon', 'JP', 'asie', 'logement', 'GaijinPot Apartment - Location Japon', 'https://apartments.gaijinpot.com/', 'gaijinpot.com', 'Recherche de logements pour etrangers au Japon', 75, NULL),
('Japon', 'japon', 'JP', 'asie', 'emploi', 'GaijinPot Jobs - Emploi Japon', 'https://jobs.gaijinpot.com/', 'gaijinpot.com', 'Site d emploi specialise pour les etrangers au Japon', 75, NULL),
('Japon', 'japon', 'JP', 'asie', 'urgences', 'Numeros d urgence Japon', 'https://jp.ambafrance.org/Numeros-utiles', 'jp.ambafrance.org', 'Police 110, Pompiers/Ambulance 119', 95, '110');

-- SINGAPOUR
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Singapour', 'singapour', 'SG', 'asie', 'ambassade', 'Ambassade de France a Singapour', 'https://sg.ambafrance.org/', 'sg.ambafrance.org', 'Services consulaires, inscription registre', 95, NULL),
('Singapour', 'singapour', 'SG', 'asie', 'immigration', 'ICA - Immigration Singapour', 'https://www.ica.gov.sg/', 'ica.gov.sg', 'Autorite d immigration: permis de travail, PR, visa', 95, NULL),
('Singapour', 'singapour', 'SG', 'asie', 'immigration', 'MOM - Ministere du Travail Singapour', 'https://www.mom.gov.sg/', 'mom.gov.sg', 'Permis de travail: EP, S Pass, Work Permit', 95, NULL),
('Singapour', 'singapour', 'SG', 'asie', 'logement', 'PropertyGuru - Immobilier Singapour', 'https://www.propertyguru.com.sg/', 'propertyguru.com.sg', 'Portail immobilier a Singapour: location, achat', 80, NULL),
('Singapour', 'singapour', 'SG', 'asie', 'urgences', 'Numeros d urgence Singapour', 'https://sg.ambafrance.org/Numeros-utiles', 'sg.ambafrance.org', 'Police 999, Ambulance/Pompiers 995', 95, '999');

-- EMIRATS ARABES UNIS
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Emirats Arabes Unis', 'emirats-arabes-unis', 'AE', 'asie', 'ambassade', 'Ambassade de France aux EAU', 'https://ae.ambafrance.org/', 'ae.ambafrance.org', 'Services consulaires Abu Dhabi et Dubai', 95, NULL),
('Emirats Arabes Unis', 'emirats-arabes-unis', 'AE', 'asie', 'immigration', 'UAE Government - Visa et Emirates ID', 'https://u.ae/en/information-and-services/visa-and-emirates-id', 'u.ae', 'Portail officiel: tous types de visa, Emirates ID, Golden Visa', 95, NULL),
('Emirats Arabes Unis', 'emirats-arabes-unis', 'AE', 'asie', 'immigration', 'GDRFA Dubai - Residence', 'https://gdrfad.gov.ae/en', 'gdrfad.gov.ae', 'Direction generale de la residence et des affaires etrangeres Dubai', 90, NULL),
('Emirats Arabes Unis', 'emirats-arabes-unis', 'AE', 'asie', 'logement', 'Bayut - Immobilier EAU', 'https://www.bayut.com/', 'bayut.com', 'Portail immobilier aux Emirats: location, achat Dubai/Abu Dhabi', 80, NULL),
('Emirats Arabes Unis', 'emirats-arabes-unis', 'AE', 'asie', 'emploi', 'Bayt - Emploi Moyen-Orient', 'https://www.bayt.com/', 'bayt.com', 'Premier site d emploi au Moyen-Orient', 80, NULL),
('Emirats Arabes Unis', 'emirats-arabes-unis', 'AE', 'asie', 'urgences', 'Numeros d urgence EAU', 'https://ae.ambafrance.org/Numeros-utiles', 'ae.ambafrance.org', 'Police 999, Ambulance 998, Pompiers 997', 95, '999');

-- VIETNAM
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Vietnam', 'vietnam', 'VN', 'asie', 'ambassade', 'Ambassade de France au Vietnam', 'https://vn.ambafrance.org/', 'vn.ambafrance.org', 'Services consulaires Hanoi et Ho Chi Minh', 95, NULL),
('Vietnam', 'vietnam', 'VN', 'asie', 'immigration', 'Vietnam e-Visa', 'https://evisa.xuatnhapcanh.gov.vn/', 'xuatnhapcanh.gov.vn', 'Portail officiel e-Visa Vietnam', 85, NULL),
('Vietnam', 'vietnam', 'VN', 'asie', 'sante', 'FV Hospital Ho Chi Minh', 'https://www.fvhospital.com/', 'fvhospital.com', 'Hopital international franco-vietnamien', 80, NULL),
('Vietnam', 'vietnam', 'VN', 'asie', 'urgences', 'Numeros d urgence Vietnam', 'https://vn.ambafrance.org/Numeros-utiles', 'vn.ambafrance.org', 'Police 113, Pompiers 114, Ambulance 115', 95, '113');

-- =============================================
-- OCEANIE
-- =============================================

-- AUSTRALIE
INSERT INTO tmp_annuaire (country_name, country_slug, country_code, continent, category, title, url, domain, description, trust_score, emergency_number) VALUES
('Australie', 'australie', 'AU', 'oceanie', 'ambassade', 'Ambassade de France en Australie', 'https://au.ambafrance.org/', 'au.ambafrance.org', 'Services consulaires Canberra et Sydney', 95, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'immigration', 'Home Affairs - Immigration Australie', 'https://immi.homeaffairs.gov.au/', 'homeaffairs.gov.au', 'Departement de l Interieur: visa, residence permanente, citoyennete', 95, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'immigration', 'Working Holiday Visa Australie (417)', 'https://immi.homeaffairs.gov.au/visas/getting-a-visa/visa-listing/work-holiday-417', 'homeaffairs.gov.au', 'Visa Vacances-Travail pour l Australie', 95, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'sante', 'Medicare - Assurance sante Australie', 'https://www.servicesaustralia.gov.au/medicare', 'servicesaustralia.gov.au', 'Systeme de sante public australien', 90, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'logement', 'Domain - Immobilier Australie', 'https://www.domain.com.au/', 'domain.com.au', 'Portail immobilier australien: location, achat', 80, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'logement', 'Flatmates - Colocation Australie', 'https://flatmates.com.au/', 'flatmates.com.au', 'Recherche de colocation en Australie', 75, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'emploi', 'Seek - Emploi Australie', 'https://www.seek.com.au/', 'seek.com.au', 'Premier site d emploi en Australie', 85, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'fiscalite', 'ATO - Impots Australie', 'https://www.ato.gov.au/', 'ato.gov.au', 'Administration fiscale australienne: TFN, declarations, super', 95, NULL),
('Australie', 'australie', 'AU', 'oceanie', 'urgences', 'Numeros d urgence Australie', 'https://au.ambafrance.org/Numeros-utiles', 'au.ambafrance.org', 'Urgences 000, Police non-urgente 131 444', 95, '000');

-- =============================================
-- INSERTION dans generation_source_items (Mission Control)
-- 1 item pillar "annuaire" par pays avec tous les liens en data_json
-- =============================================
INSERT INTO generation_source_items (
  category_slug, source_type, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status, data_json
)
SELECT
  'annuaire',
  'directory',
  'Annuaire ' || t.country_name || ' : liens officiels et ressources essentielles',
  t.country_name,
  t.country_slug,
  'multi-theme',
  'annuaire-' || t.country_slug,
  'fr',
  0,
  90,
  true,
  'ready',
  jsonb_build_object(
    'country_code', t.country_code,
    'country_name', t.country_name,
    'continent', t.continent,
    'links_count', COUNT(*),
    'categories', (SELECT jsonb_agg(DISTINCT sub.category) FROM tmp_annuaire sub WHERE sub.country_slug = t.country_slug),
    'links', jsonb_agg(
      jsonb_build_object(
        'category', t.category,
        'title', t.title,
        'url', t.url,
        'domain', t.domain,
        'description', t.description,
        'trust_score', t.trust_score,
        'is_official', t.is_official,
        'emergency_number', t.emergency_number
      ) ORDER BY t.category, t.trust_score DESC
    )
  )
FROM tmp_annuaire t
WHERE t.country_slug != 'global'
GROUP BY t.country_name, t.country_slug, t.country_code, t.continent;

-- Insert global resources as a separate item
INSERT INTO generation_source_items (
  category_slug, source_type, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status, data_json
)
SELECT
  'annuaire',
  'directory',
  'Annuaire Global : ressources internationales pour expatries',
  'Global',
  'global',
  'multi-theme',
  'annuaire-global',
  'fr',
  0,
  95,
  true,
  'ready',
  jsonb_build_object(
    'country_code', 'XX',
    'country_name', 'Global',
    'continent', 'global',
    'links_count', COUNT(*),
    'categories', (SELECT jsonb_agg(DISTINCT sub.category) FROM tmp_annuaire sub WHERE sub.country_slug = 'global'),
    'links', jsonb_agg(
      jsonb_build_object(
        'category', t.category,
        'title', t.title,
        'url', t.url,
        'domain', t.domain,
        'description', t.description,
        'trust_score', t.trust_score
      ) ORDER BY t.category, t.trust_score DESC
    )
  )
FROM tmp_annuaire t
WHERE t.country_slug = 'global';


-- =============================================
-- STATS de verification
-- =============================================
SELECT
  country_name,
  country_code,
  continent,
  COUNT(*) as total_links,
  COUNT(DISTINCT category) as categories_covered,
  string_agg(DISTINCT category, ', ' ORDER BY category) as categories_list
FROM tmp_annuaire
GROUP BY country_name, country_code, continent
ORDER BY continent, country_name;

DROP TABLE IF EXISTS tmp_annuaire;
