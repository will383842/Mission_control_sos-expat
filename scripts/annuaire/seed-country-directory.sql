-- =============================================
-- SEED COUNTRY_DIRECTORY — Annuaire complet SOS-Expat
-- Table unique: country_directory (Mission Control)
-- Sert de source pour: generation d articles + blog external_links
--
-- Execution: psql -d mission_control -f seed-country-directory.sql
-- =============================================

TRUNCATE country_directory RESTART IDENTITY;

-- =============================================
-- RESSOURCES GLOBALES (tous pays)
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
('XX', 'Global', 'global', 'global', 'ambassade', 'Annuaire ambassades et consulats francais dans le monde', 'https://www.diplomatie.gouv.fr/fr/le-ministere-et-son-reseau/organisation-et-annuaires/ambassades-et-consulats-francais-a-l-etranger/', 'diplomatie.gouv.fr', 'Repertoire officiel de toutes les representations francaises (162 ambassades, 531 consulats)', 95, true, 'ambassades de France dans le monde', 'noopener'),
('XX', 'Global', 'global', 'global', 'ambassade', 'France-Visas — Portail officiel des visas', 'https://france-visas.gouv.fr/en/', 'france-visas.gouv.fr', 'Plateforme officielle pour les demandes de visa pour la France', 95, true, 'France-Visas', 'noopener'),
('XX', 'Global', 'global', 'global', 'sante', 'CFE — Caisse des Francais de l Etranger', 'https://www.cfe.fr/', 'cfe.fr', 'Securite sociale volontaire pour expatries: maladie, maternite, invalidite. 175 000 adherents.', 95, true, 'CFE', 'noopener'),
('XX', 'Global', 'global', 'global', 'sante', 'APRIL International — Assurance expatrie', 'https://www.april-international.com/', 'april-international.com', 'Assurance sante internationale pour expatries et voyageurs longue duree', 80, false, 'APRIL International', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'banque', 'Wise — Transferts internationaux au taux reel', 'https://wise.com/', 'wise.com', 'Transferts d argent internationaux, compte multi-devises dans 50+ devises', 85, false, 'Wise', 'noopener'),
('XX', 'Global', 'global', 'global', 'banque', 'Revolut — Compte bancaire international', 'https://www.revolut.com/', 'revolut.com', 'Banque mobile internationale: change, paiements, crypto', 80, false, 'Revolut', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'banque', 'Numbeo — Cout de la vie mondial', 'https://www.numbeo.com/cost-of-living/', 'numbeo.com', 'Base de donnees collaborative: loyers, alimentation, transports par ville', 80, false, 'Numbeo', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'fiscalite', 'Service-Public.fr — Impots des Francais a l etranger', 'https://www.service-public.fr/particuliers/vosdroits/N31477', 'service-public.fr', 'Obligations fiscales, conventions de non double imposition, CFE', 95, true, 'impots des Francais a l etranger', 'noopener'),
('XX', 'Global', 'global', 'global', 'fiscalite', 'Impots.gouv.fr — Service des non-residents', 'https://www.impots.gouv.fr/international-particulier', 'impots.gouv.fr', 'Declaration, avis d imposition, prelevement a la source pour non-residents', 95, true, 'impots non-residents', 'noopener'),
('XX', 'Global', 'global', 'global', 'juridique', 'Service-Public.fr — Francais a l etranger', 'https://www.service-public.fr/particuliers/vosdroits/N120', 'service-public.fr', 'Toutes les demarches: passeport, election, etat civil, rapatriement', 95, true, 'demarches Francais a l etranger', 'noopener'),
('XX', 'Global', 'global', 'global', 'education', 'AEFE — Enseignement francais a l etranger', 'https://www.aefe.fr/', 'aefe.fr', 'Reseau de 580 etablissements scolaires francais dans 139 pays', 95, true, 'AEFE', 'noopener'),
('XX', 'Global', 'global', 'global', 'emploi', 'France Travail International', 'https://www.francetravail.fr/candidat/vos-recherches/trouver-un-emploi-a-l-etranger.html', 'francetravail.fr', 'Recherche d emploi a l etranger, conseils mobilite internationale', 90, true, 'France Travail International', 'noopener'),
('XX', 'Global', 'global', 'global', 'communaute', 'Expat.com — Forum et guides expatries', 'https://www.expat.com/', 'expat.com', 'Communaute mondiale d expatries: forums, guides, petites annonces par pays', 75, false, 'Expat.com', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'communaute', 'InterNations — Reseau social expatries', 'https://www.internations.org/', 'internations.org', 'Evenements, groupes par ville, guides. 4M+ membres dans 420 villes', 75, false, 'InterNations', 'noopener nofollow'),
('XX', 'Global', 'global', 'global', 'communaute', 'Expatica — Guides expatriation', 'https://www.expatica.com/', 'expatica.com', 'Guides complets par pays: logement, emploi, visa, sante, banque', 75, false, 'Expatica', 'noopener nofollow');

-- =============================================
-- ALLEMAGNE
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('DE', 'Allemagne', 'allemagne', 'europe', 'ambassade', 'ambassade', 'Ambassade de France en Allemagne', 'https://de.ambafrance.org/', 'de.ambafrance.org', 'Services consulaires, inscription au registre, elections, etat civil', 'Pariser Platz 5, 10117 Berlin', 'Berlin', '+49 30 590039000', '+49 1608806313', 'consulat.berlin-amba@diplomatie.gouv.fr', 'Lundi-Vendredi 8h30-18h00', 95, true, '112', 'ambassade de France a Berlin', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'ambassade', 'consulat', 'Section consulaire — Berlin', 'https://de.ambafrance.org/-Berlin-Consulat-', 'de.ambafrance.org', 'Passeports, CNI, visas, etat civil, notariat', 'Wilhelmstrasse 69, 10117 Berlin', 'Berlin', '+49 30 91588060', NULL, 'consulat.berlin-amba@diplomatie.gouv.fr', 'Lundi-Vendredi 8h30-12h30', 95, true, NULL, 'consulat de France a Berlin', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'immigration', NULL, 'Auswaertiges Amt — Visas pour l Allemagne', 'https://www.auswaertiges-amt.de/en/visa-service', 'auswaertiges-amt.de', 'Ministere federal des Affaires etrangeres: tous les types de visa, conditions d entree', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'visa Allemagne', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'immigration', NULL, 'BAMF — Office federal des migrations', 'https://www.bamf.de/EN/Startseite/startseite_node.html', 'bamf.de', 'Titre de sejour, naturalisation, cours d integration obligatoires', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'BAMF immigration Allemagne', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'sante', 'assurance-maladie', 'AOK — Assurance maladie publique', 'https://www.aok.de/pk/', 'aok.de', 'Plus grande caisse d assurance maladie publique allemande (gesetzliche Krankenversicherung)', NULL, NULL, NULL, NULL, NULL, NULL, 85, true, NULL, 'AOK assurance maladie', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'sante', 'assurance-maladie', 'TK — Techniker Krankenkasse', 'https://www.tk.de/en', 'tk.de', 'Caisse d assurance maladie publique populaire aupres des expatries, site en anglais', NULL, NULL, NULL, NULL, NULL, NULL, 85, true, NULL, 'TK Krankenkasse', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'banque', NULL, 'N26 — Banque en ligne allemande', 'https://n26.com/', 'n26.com', 'Ouverture de compte en 8 min, IBAN allemand, application mobile', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'N26', 'noopener nofollow'),
('DE', 'Allemagne', 'allemagne', 'europe', 'logement', 'location', 'ImmobilienScout24 — Immobilier', 'https://www.immobilienscout24.de/', 'immobilienscout24.de', 'Premier portail immobilier allemand: 1.8M annonces, location et achat', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'ImmobilienScout24', 'noopener nofollow'),
('DE', 'Allemagne', 'allemagne', 'europe', 'logement', 'colocation', 'WG-Gesucht — Colocation', 'https://www.wg-gesucht.de/', 'wg-gesucht.de', 'Plateforme de colocation (WG) et sous-location en Allemagne', NULL, NULL, NULL, NULL, NULL, NULL, 75, false, NULL, 'WG-Gesucht', 'noopener nofollow'),
('DE', 'Allemagne', 'allemagne', 'europe', 'emploi', 'agence-publique', 'Bundesagentur fuer Arbeit — Emploi', 'https://www.arbeitsagentur.de/', 'arbeitsagentur.de', 'Agence federale pour l emploi: offres, aides, Arbeitslosengeld', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Bundesagentur fur Arbeit', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'emploi', 'portail-officiel', 'Make it in Germany — Portail officiel travail', 'https://www.make-it-in-germany.com/en/', 'make-it-in-germany.com', 'Portail du gouvernement pour les travailleurs etrangers: visa travail, reconnaissance diplomes', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Make it in Germany', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'transport', 'train', 'Deutsche Bahn — Chemins de fer', 'https://www.bahn.de/en', 'bahn.de', 'Trains DB: ICE, IC, RE, S-Bahn. BahnCard, Deutschlandticket 49EUR/mois', NULL, NULL, NULL, NULL, NULL, NULL, 85, true, NULL, 'Deutsche Bahn', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'fiscalite', NULL, 'Bundeszentralamt fuer Steuern — Impots', 'https://www.bzst.de/EN/Home/home_node.html', 'bzst.de', 'Numero fiscal (Steuer-ID), conventions fiscales franco-allemandes', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'impots Allemagne', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'education', 'bourses', 'DAAD — Etudier en Allemagne', 'https://www.daad.de/en/', 'daad.de', 'Office allemand d echanges universitaires: bourses, programmes, universites', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'DAAD', 'noopener'),
('DE', 'Allemagne', 'allemagne', 'europe', 'urgences', NULL, 'Numeros d urgence Allemagne', 'https://de.ambafrance.org/Numeros-utiles-en-Allemagne', 'de.ambafrance.org', 'Police 110, Pompiers/SAMU 112, SOS Medecin, permanence consulaire', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '112', 'urgences Allemagne', 'noopener');

-- =============================================
-- ESPAGNE
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('ES', 'Espagne', 'espagne', 'europe', 'ambassade', 'ambassade', 'Ambassade de France en Espagne', 'https://es.ambafrance.org/', 'es.ambafrance.org', 'Ambassade et consulats generaux a Madrid et Barcelone', NULL, 'Madrid', NULL, NULL, NULL, NULL, 95, true, '112', 'ambassade de France en Espagne', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'ambassade', 'consulat', 'Consulat general de France a Madrid', 'https://es.ambafrance.org/-Consulat-general-a-Madrid-', 'es.ambafrance.org', 'Passeports, CNI, visas, etat civil, notariat', 'Calle Marques de la Ensenada 10, 28004 Madrid', 'Madrid', '+34 912 15 91 00', NULL, NULL, 'Lundi-Vendredi 9h-13h30 (sur RDV)', 95, true, NULL, 'consulat de France a Madrid', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'ambassade', 'consulat', 'Consulat general de France a Barcelone', 'https://barcelone.consulfrance.org/', 'barcelone.consulfrance.org', 'Passeports, CNI, visas, etat civil', 'Ronda Universitat 22B 4e, 08007 Barcelona', 'Barcelone', '+34 93 028 99 20', NULL, NULL, 'Lundi-Vendredi 9h-13h, Lundi-Jeudi 15h-17h', 95, true, NULL, 'consulat de France a Barcelone', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'immigration', NULL, 'Secretaria de Estado de Migraciones', 'https://www.inclusion.gob.es/web/migraciones/', 'inclusion.gob.es', 'Immigration officielle Espagne: NIE, carte de residence, permis travail', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'immigration Espagne', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'immigration', 'nie', 'Policia Nacional — NIE et residence', 'https://www.policia.es/documentacion/extranjeros.html', 'policia.es', 'Demarches NIE (Numero de Identidad de Extranjero), carte de sejour', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'NIE Espagne', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'sante', 'securite-sociale', 'Seguridad Social — Securite sociale', 'https://www.seg-social.es/', 'seg-social.es', 'Affiliation, prestations, couverture maladie publique espagnole', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Seguridad Social', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'logement', 'location', 'Idealista — Immobilier Espagne', 'https://www.idealista.com/', 'idealista.com', 'Premier portail immobilier espagnol: location, achat, colocation', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Idealista', 'noopener nofollow'),
('ES', 'Espagne', 'espagne', 'europe', 'logement', 'location', 'Fotocasa — Immobilier', 'https://www.fotocasa.es/', 'fotocasa.es', 'Deuxieme portail immobilier espagnol: location, achat', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Fotocasa', 'noopener nofollow'),
('ES', 'Espagne', 'espagne', 'europe', 'emploi', 'portail', 'InfoJobs — Emploi Espagne', 'https://www.infojobs.net/', 'infojobs.net', 'Premier site d emploi en Espagne: +200 000 offres', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'InfoJobs', 'noopener nofollow'),
('ES', 'Espagne', 'espagne', 'europe', 'emploi', 'agence-publique', 'SEPE — Service public emploi', 'https://www.sepe.es/', 'sepe.es', 'Inscription chomage, prestations, aide a la recherche d emploi', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'SEPE', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'fiscalite', NULL, 'Agencia Tributaria — Impots', 'https://sede.agenciatributaria.gob.es/', 'agenciatributaria.gob.es', 'Administration fiscale: NIE fiscal, IRPF, conventions fiscales', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Agencia Tributaria', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'transport', 'train', 'Renfe — Trains Espagne', 'https://www.renfe.com/es/en', 'renfe.com', 'Chemins de fer espagnols: AVE haute vitesse, Cercanias, billets', NULL, NULL, NULL, NULL, NULL, NULL, 85, true, NULL, 'Renfe', 'noopener'),
('ES', 'Espagne', 'espagne', 'europe', 'urgences', NULL, 'Numeros d urgence Espagne', 'https://es.ambafrance.org/En-cas-d-urgence', 'es.ambafrance.org', 'Urgences 112, Police nationale 091, Guardia Civil 062, Ambulance 061', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '112', 'urgences Espagne', 'noopener');

-- =============================================
-- ETATS-UNIS
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'ambassade', 'ambassade', 'Ambassade de France aux Etats-Unis', 'https://franceintheus.org/', 'franceintheus.org', 'Ambassade a Washington et 10 consulats generaux', '4101 Reservoir Road NW, Washington DC 20007', 'Washington', '+1 202 944 6000', NULL, NULL, 'Lundi-Vendredi 9h-17h', 95, true, '911', 'ambassade de France aux USA', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'ambassade', 'consulat', 'Consulat general de France a New York', 'https://newyork.consulfrance.org/', 'newyork.consulfrance.org', 'Services consulaires pour la region de New York', '934 Fifth Avenue, New York NY 10021', 'New York', '+1 212 606 3600', NULL, 'info.new-york-fslt@diplomatie.gouv.fr', 'Lundi-Vendredi 9h-16h', 95, true, NULL, 'consulat de France a New York', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'ambassade', 'consulat', 'Consulat general de France a Los Angeles', 'https://losangeles.consulfrance.org/', 'losangeles.consulfrance.org', 'Services consulaires pour la Californie du Sud', '10390 Santa Monica Blvd Suite 410, Los Angeles CA 90025', 'Los Angeles', '+1 310 235 3200', NULL, NULL, 'Lundi-Vendredi 8h45-12h45', 95, true, NULL, 'consulat de France a Los Angeles', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'immigration', 'visa', 'USCIS — Immigration USA', 'https://www.uscis.gov/', 'uscis.gov', 'Service de citoyennete et d immigration: Green Card, naturalisation, visa travail H1B/L1/O1', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'USCIS', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'immigration', 'visa', 'Travel.State.Gov — Visas USA', 'https://travel.state.gov/content/travel/en/us-visas.html', 'travel.state.gov', 'Departement d Etat: tous types de visa (B1/B2, J1, K1, immigrant)', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'visas USA', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'immigration', 'esta', 'ESTA — Autorisation electronique de voyage', 'https://esta.cbp.dhs.gov/', 'esta.cbp.dhs.gov', 'Visa Waiver Program: autorisation pour sejours < 90 jours (21$)', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'ESTA', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'sante', 'assurance', 'Healthcare.gov — Assurance sante', 'https://www.healthcare.gov/', 'healthcare.gov', 'Marketplace ACA (Obamacare): plans sante pour residents', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Healthcare.gov', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'fiscalite', NULL, 'IRS — Internal Revenue Service', 'https://www.irs.gov/', 'irs.gov', 'Impots USA: ITIN pour non-citoyens, FATCA, declarations, Social Security', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'IRS', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'logement', 'location', 'Zillow — Immobilier USA', 'https://www.zillow.com/', 'zillow.com', 'Premier portail immobilier americain: 135M+ proprietes', NULL, NULL, NULL, NULL, NULL, NULL, 85, false, NULL, 'Zillow', 'noopener nofollow'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'logement', 'location', 'Apartments.com — Location', 'https://www.apartments.com/', 'apartments.com', 'Recherche d appartements en location dans toutes les villes US', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Apartments.com', 'noopener nofollow'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'emploi', 'federal', 'USAJobs — Emploi federal', 'https://www.usajobs.gov/', 'usajobs.gov', 'Site officiel des offres d emploi du gouvernement federal', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'USAJobs', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'emploi', 'portail', 'LinkedIn Jobs — Emploi USA', 'https://www.linkedin.com/jobs/', 'linkedin.com', 'Premier reseau professionnel mondial: offres, networking', NULL, NULL, NULL, NULL, NULL, NULL, 85, false, NULL, 'LinkedIn Jobs', 'noopener nofollow'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'education', 'etudier', 'EducationUSA — Etudier aux USA', 'https://educationusa.state.gov/', 'educationusa.state.gov', 'Reseau officiel du Dept. d Etat pour etudiants internationaux', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'EducationUSA', 'noopener'),
('US', 'Etats-Unis', 'etats-unis', 'amerique-nord', 'urgences', NULL, 'Numeros d urgence USA', 'https://franceintheus.org/spip.php?article637', 'franceintheus.org', 'Urgences 911, Poison Control 1-800-222-1222, FBI, permanence consulaire', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '911', 'urgences USA', 'noopener');

-- =============================================
-- CANADA
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('CA', 'Canada', 'canada', 'amerique-nord', 'ambassade', 'ambassade', 'Ambassade de France au Canada', 'https://ca.ambafrance.org/', 'ca.ambafrance.org', 'Ambassade a Ottawa et consulats a Montreal, Toronto, Quebec, Vancouver, Moncton', '42 Promenade Sussex, Ottawa ON K1M 2C9', 'Ottawa', '+1 613 789 1795', NULL, NULL, 'Lundi-Vendredi 9h-12h30 14h-17h', 95, true, '911', 'ambassade de France au Canada', 'noopener'),
('CA', 'Canada', 'canada', 'amerique-nord', 'immigration', 'visa', 'IRCC — Immigration Canada', 'https://www.canada.ca/en/immigration-refugees-citizenship.html', 'canada.ca', 'Residence permanente, visa travail, PVT, Entree Express, citoyennete', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'IRCC', 'noopener'),
('CA', 'Canada', 'canada', 'amerique-nord', 'immigration', 'pvt', 'PVT Canada — Experience internationale', 'https://www.canada.ca/en/immigration-refugees-citizenship/services/work-canada/iec.html', 'canada.ca', 'Programme Vacances-Travail (18-35 ans), permis Jeunes Professionnels', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'PVT Canada', 'noopener'),
('CA', 'Canada', 'canada', 'amerique-nord', 'sante', 'quebec', 'RAMQ — Assurance maladie Quebec', 'https://www.ramq.gouv.qc.ca/', 'ramq.gouv.qc.ca', 'Regie de l assurance maladie du Quebec: carte RAMQ, couverture', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'RAMQ', 'noopener'),
('CA', 'Canada', 'canada', 'amerique-nord', 'logement', 'location', 'Realtor.ca — Immobilier Canada', 'https://www.realtor.ca/', 'realtor.ca', 'Portail immobilier officiel de l Association canadienne de l immeuble', NULL, NULL, NULL, NULL, NULL, NULL, 85, false, NULL, 'Realtor.ca', 'noopener nofollow'),
('CA', 'Canada', 'canada', 'amerique-nord', 'logement', 'petites-annonces', 'Kijiji — Annonces Canada', 'https://www.kijiji.ca/', 'kijiji.ca', 'Petites annonces: logement, meubles, vehicules, emploi', NULL, NULL, NULL, NULL, NULL, NULL, 75, false, NULL, 'Kijiji', 'noopener nofollow'),
('CA', 'Canada', 'canada', 'amerique-nord', 'emploi', 'portail-officiel', 'Guichet-Emplois — Job Bank Canada', 'https://www.jobbank.gc.ca/', 'jobbank.gc.ca', 'Banque d emplois officielle du gouvernement: offres, tendances, salaires', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Guichet-Emplois Canada', 'noopener'),
('CA', 'Canada', 'canada', 'amerique-nord', 'fiscalite', NULL, 'ARC — Agence du revenu du Canada', 'https://www.canada.ca/en/revenue-agency.html', 'canada.ca', 'NAS, declarations d impots, credits, TPS/TVH, accords fiscaux', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'ARC Canada', 'noopener'),
('CA', 'Canada', 'canada', 'amerique-nord', 'urgences', NULL, 'Numeros d urgence Canada', 'https://ca.ambafrance.org/Numeros-utiles', 'ca.ambafrance.org', 'Urgences 911, Info-Sante 811 (Quebec), Telehealth 1-866-797-0000 (Ontario)', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '911', 'urgences Canada', 'noopener');

-- =============================================
-- ROYAUME-UNI
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'ambassade', 'consulat', 'Consulat general de France a Londres', 'https://uk.ambafrance.org/', 'uk.ambafrance.org', 'Services consulaires pour le Royaume-Uni', '21 Cromwell Road, London SW7 2EN', 'Londres', '+44 20 7073 1200', NULL, 'ecrire.londres-fslt@diplomatie.gouv.fr', 'Lundi-Vendredi 9h-17h', 95, true, '999', 'consulat de France a Londres', 'noopener'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'immigration', NULL, 'UK Visas and Immigration — GOV.UK', 'https://www.gov.uk/browse/visas-immigration', 'gov.uk', 'Visa, Settled Status post-Brexit, points-based system, ILR', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'immigration UK', 'noopener'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'sante', NULL, 'NHS — National Health Service', 'https://www.nhs.uk/', 'nhs.uk', 'Systeme de sante public: gratuit pour residents, GP, hopitaux', NULL, NULL, '111', NULL, NULL, NULL, 95, true, NULL, 'NHS', 'noopener'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'logement', 'location', 'Rightmove — Immobilier UK', 'https://www.rightmove.co.uk/', 'rightmove.co.uk', 'Premier portail immobilier britannique: 1M+ proprietes', NULL, NULL, NULL, NULL, NULL, NULL, 85, false, NULL, 'Rightmove', 'noopener nofollow'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'logement', 'colocation', 'SpareRoom — Colocation UK', 'https://www.spareroom.co.uk/', 'spareroom.co.uk', 'Plus grand site de colocation au Royaume-Uni', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'SpareRoom', 'noopener nofollow'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'emploi', 'portail-officiel', 'GOV.UK — Find a Job', 'https://www.gov.uk/find-a-job', 'gov.uk', 'Service public de recherche d emploi', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Find a Job UK', 'noopener'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'emploi', 'portail', 'Reed — Emploi UK', 'https://www.reed.co.uk/', 'reed.co.uk', 'Grand site d emploi britannique: +250 000 offres', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Reed', 'noopener nofollow'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'fiscalite', NULL, 'HMRC — Impots UK', 'https://www.gov.uk/government/organisations/hm-revenue-customs', 'gov.uk', 'National Insurance Number, Self Assessment, conventions fiscales', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'HMRC', 'noopener'),
('GB', 'Royaume-Uni', 'royaume-uni', 'europe', 'urgences', NULL, 'Numeros d urgence UK', 'https://uk.ambafrance.org/En-cas-d-urgence', 'uk.ambafrance.org', 'Urgences 999, Police non-urgente 101, NHS 111', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '999', 'urgences UK', 'noopener');

-- =============================================
-- MAROC
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('MA', 'Maroc', 'maroc', 'afrique', 'ambassade', 'consulat', 'Consulat general de France a Rabat', 'https://ma.ambafrance.org/', 'ma.ambafrance.org', 'Services consulaires Rabat, egalement consulats a Casablanca, Fes, Marrakech, Agadir, Tanger', '1 rue Aguelmane Sidi Ali, BP 139, 10000 Rabat', 'Rabat', '+212 5 20 50 31 34', '+212 6 61 43 34 23', NULL, 'Lundi-Jeudi 8h30-12h30 14h-17h', 95, true, '15', 'consulat de France au Maroc', 'noopener'),
('MA', 'Maroc', 'maroc', 'afrique', 'sante', 'securite-sociale', 'CNSS — Securite sociale Maroc', 'https://www.cnss.ma/', 'cnss.ma', 'Caisse nationale de securite sociale marocaine: affiliation, prestations', NULL, NULL, NULL, NULL, NULL, NULL, 85, true, NULL, 'CNSS Maroc', 'noopener'),
('MA', 'Maroc', 'maroc', 'afrique', 'logement', 'location', 'Mubawab — Immobilier Maroc', 'https://www.mubawab.ma/', 'mubawab.ma', 'Premier portail immobilier marocain: location, achat, neuf', NULL, NULL, NULL, NULL, NULL, NULL, 75, false, NULL, 'Mubawab', 'noopener nofollow'),
('MA', 'Maroc', 'maroc', 'afrique', 'logement', 'petites-annonces', 'Avito — Annonces Maroc', 'https://www.avito.ma/', 'avito.ma', 'Petites annonces: logement, vehicules, emploi, services', NULL, NULL, NULL, NULL, NULL, NULL, 70, false, NULL, 'Avito', 'noopener nofollow'),
('MA', 'Maroc', 'maroc', 'afrique', 'emploi', 'portail', 'Rekrute — Emploi Maroc', 'https://www.rekrute.com/', 'rekrute.com', 'Premier site d emploi au Maroc: +15 000 offres', NULL, NULL, NULL, NULL, NULL, NULL, 75, false, NULL, 'Rekrute', 'noopener nofollow'),
('MA', 'Maroc', 'maroc', 'afrique', 'banque', NULL, 'Bank of Africa (BMCE)', 'https://www.bankofafrica.ma/', 'bankofafrica.ma', 'Banque accessible aux expatries, comptes en dirhams et devises', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Bank of Africa', 'noopener nofollow'),
('MA', 'Maroc', 'maroc', 'afrique', 'urgences', NULL, 'Numeros d urgence Maroc', 'https://ma.ambafrance.org/Numeros-utiles', 'ma.ambafrance.org', 'Police 19, Gendarmerie royale 177, Pompiers 15, SAMU 141, Info routiere 177', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '15', 'urgences Maroc', 'noopener');

-- =============================================
-- THAILANDE
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('TH', 'Thailande', 'thailande', 'asie', 'ambassade', 'ambassade', 'Ambassade de France en Thailande', 'https://th.ambafrance.org/', 'th.ambafrance.org', 'Ambassade et section consulaire a Bangkok', '35 Charoenkrung Soi 36 (Rue de Brest), Bangrak, Bangkok 10500', 'Bangkok', '+66 2 627 21 00', '+66 81 994 49 01', NULL, 'Lundi-Vendredi 8h30-12h30 13h30-17h30', 95, true, '1155', 'ambassade de France en Thailande', 'noopener'),
('TH', 'Thailande', 'thailande', 'asie', 'immigration', 'visa', 'Thai Immigration Bureau', 'https://www.immigration.go.th/', 'immigration.go.th', 'Bureau d immigration: extensions de visa, 90-day report, overstay', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'immigration Thailande', 'noopener'),
('TH', 'Thailande', 'thailande', 'asie', 'immigration', 'evisa', 'Thai e-Visa', 'https://www.thaievisa.go.th/', 'thaievisa.go.th', 'Portail officiel e-Visa: visa touristique, retraite, education, elite', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'e-Visa Thailande', 'noopener'),
('TH', 'Thailande', 'thailande', 'asie', 'sante', 'hopital', 'Bumrungrad International Hospital', 'https://www.bumrungrad.com/', 'bumrungrad.com', 'Hopital international de reference a Bangkok, accredite JCI, 1.1M patients/an', '33 Sukhumvit 3, Bangkok 10110', 'Bangkok', '+66 2 066 8888', NULL, NULL, '24h/24 urgences', 85, false, NULL, 'Bumrungrad Hospital', 'noopener'),
('TH', 'Thailande', 'thailande', 'asie', 'logement', 'location', 'DDproperty — Immobilier Thailande', 'https://www.ddproperty.com/', 'ddproperty.com', 'Portail immobilier: condos, maisons, terrains a Bangkok et dans tout le pays', NULL, NULL, NULL, NULL, NULL, NULL, 75, false, NULL, 'DDproperty', 'noopener nofollow'),
('TH', 'Thailande', 'thailande', 'asie', 'banque', NULL, 'Bangkok Bank — Compte etranger', 'https://www.bangkokbank.com/', 'bangkokbank.com', 'Grande banque thailandaise, ouverte aux etrangers avec visa', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Bangkok Bank', 'noopener nofollow'),
('TH', 'Thailande', 'thailande', 'asie', 'urgences', NULL, 'Numeros d urgence Thailande', 'https://th.ambafrance.org/Numeros-utiles', 'th.ambafrance.org', 'Police touristique 1155, Ambulance 1669, Pompiers 199, Police 191', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '1155', 'urgences Thailande', 'noopener');

-- =============================================
-- SUISSE
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('CH', 'Suisse', 'suisse', 'europe', 'ambassade', 'ambassade', 'Ambassade de France en Suisse', 'https://ch.ambafrance.org/', 'ch.ambafrance.org', 'Ambassade a Berne, consulats a Geneve et Zurich', 'Schosshaldenstrasse 46, 3006 Berne', 'Berne', '+41 31 359 21 11', NULL, 'presse@ambafrance-ch.org', 'Lundi-Vendredi 8h30-12h30 14h-17h30', 95, true, '112', 'ambassade de France en Suisse', 'noopener'),
('CH', 'Suisse', 'suisse', 'europe', 'immigration', NULL, 'SEM — Secretariat d Etat aux migrations', 'https://www.sem.admin.ch/sem/fr/home.html', 'sem.admin.ch', 'Permis B/C/L/G, naturalisation, integration, asile', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'SEM Suisse', 'noopener'),
('CH', 'Suisse', 'suisse', 'europe', 'sante', 'assurance-maladie', 'Comparis — Comparateur assurance', 'https://www.comparis.ch/krankenkassen', 'comparis.ch', 'Comparateur d assurances maladie obligatoires LAMal: primes, franchises', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Comparis assurance', 'noopener nofollow'),
('CH', 'Suisse', 'suisse', 'europe', 'logement', 'location', 'Homegate — Immobilier Suisse', 'https://www.homegate.ch/', 'homegate.ch', 'Portail immobilier suisse: location, achat, estimation', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Homegate', 'noopener nofollow'),
('CH', 'Suisse', 'suisse', 'europe', 'emploi', 'portail', 'jobs.ch — Emploi Suisse', 'https://www.jobs.ch/', 'jobs.ch', 'Premier site d emploi en Suisse, multilingue', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'jobs.ch', 'noopener nofollow'),
('CH', 'Suisse', 'suisse', 'europe', 'transport', 'train', 'CFF — Chemins de fer federaux', 'https://www.sbb.ch/fr', 'sbb.ch', 'Trains suisses: horaires, billets, AG/demi-tarif, SwissPass', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'CFF', 'noopener'),
('CH', 'Suisse', 'suisse', 'europe', 'urgences', NULL, 'Numeros d urgence Suisse', 'https://ch.ambafrance.org/Numeros-utiles', 'ch.ambafrance.org', 'Police 117, Pompiers 118, Ambulance 144, REGA (secours aerien) 1414', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '112', 'urgences Suisse', 'noopener');

-- =============================================
-- AUSTRALIE
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, address, city, phone, phone_emergency, email, opening_hours, trust_score, is_official, emergency_number, anchor_text, rel_attribute) VALUES
('AU', 'Australie', 'australie', 'oceanie', 'ambassade', 'ambassade', 'Ambassade de France en Australie', 'https://au.ambafrance.org/', 'au.ambafrance.org', 'Ambassade a Canberra, consulats a Sydney et Melbourne', '6 Perth Avenue, Yarralumla ACT 2600', 'Canberra', '+61 2 6216 0100', NULL, 'information.canberra-amba@diplomatie.gouv.fr', 'Lundi-Vendredi 9h-12h30 14h-17h', 95, true, '000', 'ambassade de France en Australie', 'noopener'),
('AU', 'Australie', 'australie', 'oceanie', 'immigration', 'visa', 'Home Affairs — Immigration Australie', 'https://immi.homeaffairs.gov.au/', 'homeaffairs.gov.au', 'Visa, residence permanente, citoyennete, points test', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'immigration Australie', 'noopener'),
('AU', 'Australie', 'australie', 'oceanie', 'immigration', 'whv', 'Working Holiday Visa (subclass 417)', 'https://immi.homeaffairs.gov.au/visas/getting-a-visa/visa-listing/work-holiday-417', 'homeaffairs.gov.au', 'Visa Vacances-Travail 18-35 ans, jusqu a 3 ans avec farm work', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'WHV Australie', 'noopener'),
('AU', 'Australie', 'australie', 'oceanie', 'sante', NULL, 'Medicare — Sante publique Australie', 'https://www.servicesaustralia.gov.au/medicare', 'servicesaustralia.gov.au', 'Systeme de sante public: carte Medicare, PBS, accord Franco-Australien', NULL, NULL, NULL, NULL, NULL, NULL, 90, true, NULL, 'Medicare Australie', 'noopener'),
('AU', 'Australie', 'australie', 'oceanie', 'logement', 'location', 'Domain — Immobilier Australie', 'https://www.domain.com.au/', 'domain.com.au', 'Portail immobilier australien: location, achat, estimation', NULL, NULL, NULL, NULL, NULL, NULL, 80, false, NULL, 'Domain', 'noopener nofollow'),
('AU', 'Australie', 'australie', 'oceanie', 'logement', 'colocation', 'Flatmates — Colocation Australie', 'https://flatmates.com.au/', 'flatmates.com.au', 'Recherche de colocation dans toute l Australie', NULL, NULL, NULL, NULL, NULL, NULL, 75, false, NULL, 'Flatmates', 'noopener nofollow'),
('AU', 'Australie', 'australie', 'oceanie', 'emploi', 'portail', 'Seek — Emploi Australie', 'https://www.seek.com.au/', 'seek.com.au', 'Premier site d emploi en Australie: +100 000 offres', NULL, NULL, NULL, NULL, NULL, NULL, 85, false, NULL, 'Seek', 'noopener nofollow'),
('AU', 'Australie', 'australie', 'oceanie', 'fiscalite', NULL, 'ATO — Australian Tax Office', 'https://www.ato.gov.au/', 'ato.gov.au', 'Tax File Number (TFN), declarations, superannuation, conventions fiscales', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, NULL, 'ATO', 'noopener'),
('AU', 'Australie', 'australie', 'oceanie', 'urgences', NULL, 'Numeros d urgence Australie', 'https://au.ambafrance.org/Numeros-utiles', 'au.ambafrance.org', 'Urgences 000, Police non-urgente 131 444, Lifeline 13 11 14', NULL, NULL, NULL, NULL, NULL, NULL, 95, true, '000', 'urgences Australie', 'noopener');

-- =============================================
-- VERIFICATION STATS
-- =============================================
SELECT
  country_name,
  country_code,
  continent,
  COUNT(*) as total_links,
  COUNT(*) FILTER (WHERE address IS NOT NULL) as with_address,
  COUNT(*) FILTER (WHERE phone IS NOT NULL) as with_phone,
  COUNT(*) FILTER (WHERE email IS NOT NULL) as with_email,
  COUNT(DISTINCT category) as categories,
  string_agg(DISTINCT category, ', ' ORDER BY category) as category_list
FROM country_directory
WHERE is_active = true
GROUP BY country_name, country_code, continent
ORDER BY continent, country_name;

SELECT
  'TOTAL' as label,
  COUNT(*) as entries,
  COUNT(DISTINCT country_code) as countries,
  COUNT(DISTINCT category) as categories,
  COUNT(*) FILTER (WHERE is_official) as official,
  COUNT(*) FILTER (WHERE address IS NOT NULL) as with_address,
  COUNT(*) FILTER (WHERE phone IS NOT NULL) as with_phone
FROM country_directory;
