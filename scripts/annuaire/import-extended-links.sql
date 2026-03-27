-- =============================================
-- EXTENDED LINKS: pays restants + hopitaux + telecom
-- Execute APRES import-practical-links.sql
-- ~300 liens supplementaires
-- =============================================

-- =============================================
-- IMMIGRATION — Pays restants (Europe de l'Est, Ameriques, Afrique, Asie)
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
-- Europe de l'Est
('RO','Roumanie','roumanie','europe','immigration',NULL,'MAE Roumanie — e-Visa','https://evisa.mae.ro/','evisa.mae.ro','Visa electronique pour la Roumanie',90,true,'e-Visa Roumanie','noopener'),
('BG','Bulgarie','bulgarie','europe','immigration',NULL,'MFA Bulgarie — Visa','https://www.mfa.bg/en/services-travel/consular-services/travel-bulgaria/visa-bulgaria','mfa.bg','Visa et conditions d entree',90,true,'visa Bulgarie','noopener'),
('RS','Serbie','serbie','europe','immigration',NULL,'MFA Serbie — Visa','https://www.mfa.gov.rs/en/citizens/travel-serbia/visa-regime','mfa.gov.rs','Regime de visa pour la Serbie',90,true,'visa Serbie','noopener'),
('HR','Croatie','croatie','europe','immigration',NULL,'MVEP Croatie — Visa','https://mvep.gov.hr/services-for-citizens/consular-information/visas/122','mvep.gov.hr','Visa et entree en Croatie (Schengen)',90,true,'visa Croatie','noopener'),
('UA','Ukraine','ukraine','europe','immigration',NULL,'DMSU — Migration Ukraine','https://dmsu.gov.ua/en-home.html','dmsu.gov.ua','Service des migrations d Ukraine',85,true,'immigration Ukraine','noopener'),
('AL','Albanie','albanie','europe','immigration',NULL,'MFA Albanie — Visa','https://punetejashtme.gov.al/en/services-and-opportunities/visa-regime-for-foreigners/','punetejashtme.gov.al','Regime de visa pour etrangers',85,true,'visa Albanie','noopener'),
('BA','Bosnie-Herzegovine','bosnie-herzegovine','europe','immigration',NULL,'MFA Bosnie — Visa','https://mvp.gov.ba/en/vize','mvp.gov.ba','Informations visa Bosnie',85,true,'visa Bosnie','noopener'),

-- Ameriques restantes
('EC','Equateur','equateur','amerique-sud','immigration',NULL,'Cancilleria Ecuador — Visa','https://www.cancilleria.gob.ec/visa/','cancilleria.gob.ec','Visa et residence en Equateur',85,true,'visa Equateur','noopener'),
('UY','Uruguay','uruguay','amerique-sud','immigration',NULL,'Migraciones Uruguay','https://migracion.minterior.gub.uy/','minterior.gub.uy','Direction nationale des migrations',85,true,'immigration Uruguay','noopener'),
('PY','Paraguay','paraguay','amerique-sud','immigration',NULL,'Migraciones Paraguay','https://migraciones.gov.py/','migraciones.gov.py','Direction generale des migrations',85,true,'immigration Paraguay','noopener'),
('BO','Bolivie','bolivie','amerique-sud','immigration',NULL,'Migracion Bolivia','https://www.migracion.gob.bo/','migracion.gob.bo','Direction generale des migrations',85,true,'immigration Bolivie','noopener'),
('VE','Venezuela','venezuela','amerique-sud','immigration',NULL,'SAIME Venezuela','https://tramites.saime.gob.ve/','saime.gob.ve','Service identification et migration',80,true,'SAIME Venezuela','noopener'),
('CU','Cuba','cuba','amerique-nord','immigration',NULL,'Consulat Cuba — Visa','https://misiones.cubaminrex.cu/','cubaminrex.cu','Missions diplomatiques cubaines',80,true,'visa Cuba','noopener'),
('JM','Jamaique','jamaique','amerique-nord','immigration',NULL,'PICA — Immigration Jamaique','https://www.pica.gov.jm/','pica.gov.jm','Passport Immigration Citizenship Agency',85,true,'immigration Jamaique','noopener'),
('HT','Haiti','haiti','amerique-nord','immigration',NULL,'DNI — Immigration Haiti','https://www.oni.gouv.ht/','oni.gouv.ht','Office national d identification',80,true,'immigration Haiti','noopener'),

-- Afrique restante
('GH','Ghana','ghana','afrique','immigration',NULL,'GIS — Ghana Immigration','https://gis.gov.gh/','gis.gov.gh','Visa, permis de residence, e-Visa',90,true,'immigration Ghana','noopener'),
('ET','Ethiopie','ethiopie','afrique','immigration',NULL,'Ethiopia e-Visa','https://www.evisa.gov.et/','evisa.gov.et','Visa electronique pour l Ethiopie',85,true,'e-Visa Ethiopie','noopener'),
('TZ','Tanzanie','tanzanie','afrique','immigration',NULL,'Immigration Tanzania','https://immigration.go.tz/','immigration.go.tz','Direction des services d immigration',85,true,'immigration Tanzanie','noopener'),
('UG','Ouganda','ouganda','afrique','immigration',NULL,'Uganda e-Immigration','https://www.immigration.go.ug/','immigration.go.ug','Direction de la citoyennete et de l immigration',85,true,'immigration Ouganda','noopener'),
('RW','Rwanda','rwanda','afrique','immigration',NULL,'DGIE Rwanda','https://www.migration.gov.rw/','migration.gov.rw','Direction generale immigration et emigration',85,true,'immigration Rwanda','noopener'),
('MZ','Mozambique','mozambique','afrique','immigration',NULL,'SENAMI Mozambique','https://www.senami.gov.mz/','senami.gov.mz','Service national de migration',80,true,'immigration Mozambique','noopener'),
('GA','Gabon','gabon','afrique','immigration',NULL,'DGDI Gabon — e-Visa','https://evisa.dgdi.ga/','dgdi.ga','Visa electronique pour le Gabon',85,true,'e-Visa Gabon','noopener'),

-- Asie restante
('LB','Liban','liban','asie','immigration',NULL,'GSO — Surete Generale Liban','https://www.general-security.gov.lb/','general-security.gov.lb','Direction generale surete: visa, residence',85,true,'immigration Liban','noopener'),
('JO','Jordanie','jordanie','asie','immigration',NULL,'Jordan e-Visa','https://www.gateway2jordan.gov.jo/','gateway2jordan.gov.jo','Visa electronique pour la Jordanie',85,true,'e-Visa Jordanie','noopener'),
('SA','Arabie Saoudite','arabie-saoudite','asie','immigration',NULL,'Enjaz — Visa Arabie','https://visa.mofa.gov.sa/','mofa.gov.sa','Plateforme officielle de demande de visa',90,true,'visa Arabie Saoudite','noopener'),
('QA','Qatar','qatar','asie','immigration',NULL,'MOI Qatar — Visa','https://www.moi.gov.qa/','moi.gov.qa','Ministere Interieur: visa, residence',90,true,'immigration Qatar','noopener'),
('KW','Koweit','koweit','asie','immigration',NULL,'MOI Koweit','https://www.moi.gov.kw/','moi.gov.kw','Ministere Interieur: visa, residence',85,true,'immigration Koweit','noopener'),
('BH','Bahrein','bahrein','asie','immigration',NULL,'NPRA Bahrein — e-Visa','https://www.evisa.gov.bh/','evisa.gov.bh','Visa electronique pour Bahrein',85,true,'e-Visa Bahrein','noopener'),
('LK','Sri Lanka','sri-lanka','asie','immigration',NULL,'Sri Lanka e-Visa','https://www.srilankaevisa.lk/','srilankaevisa.lk','ETA electronique pour Sri Lanka',85,true,'e-Visa Sri Lanka','noopener'),
('NP','Nepal','nepal','asie','immigration',NULL,'Nepal Immigration','https://immigration.gov.np/','immigration.gov.np','Department of Immigration Nepal',85,true,'immigration Nepal','noopener'),
('MM','Myanmar','myanmar','asie','immigration',NULL,'Myanmar e-Visa','https://evisa.moip.gov.mm/','moip.gov.mm','Visa electronique Myanmar',80,true,'e-Visa Myanmar','noopener'),
('LA','Laos','laos','asie','immigration',NULL,'Laos e-Visa','https://laoevisa.gov.la/','laoevisa.gov.la','Visa electronique pour le Laos',85,true,'e-Visa Laos','noopener'),
('BD','Bangladesh','bangladesh','asie','immigration',NULL,'Bangladesh e-Visa','https://visa.gov.bd/','visa.gov.bd','Visa electronique pour le Bangladesh',85,true,'e-Visa Bangladesh','noopener'),
('PK','Pakistan','pakistan','asie','immigration',NULL,'Pakistan e-Visa','https://visa.nadra.gov.pk/','nadra.gov.pk','Visa electronique pour le Pakistan',85,true,'e-Visa Pakistan','noopener'),
('MN','Mongolie','mongolie','asie','immigration',NULL,'Mongolia e-Visa','https://evisa.mn/','evisa.mn','Visa electronique pour la Mongolie',85,true,'e-Visa Mongolie','noopener'),

-- Oceanie
('FJ','Fidji','fidji','oceanie','immigration',NULL,'Fiji Immigration','https://www.immigration.gov.fj/','immigration.gov.fj','Department of Immigration Fiji',85,true,'immigration Fidji','noopener');


-- =============================================
-- HOPITAUX INTERNATIONAUX (accredites JCI ou de reference)
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
-- Asie
('TH','Thailande','thailande','asie','sante','hopital-jci','Bangkok Hospital','https://www.bangkokhospital.com/','bangkokhospital.com','Reseau de 50 hopitaux en Thailande, accredite JCI',85,false,'Bangkok Hospital','noopener'),
('TH','Thailande','thailande','asie','sante','hopital-jci','Samitivej Hospital','https://www.samitivejhospitals.com/','samitivejhospitals.com','Hopital international Bangkok, accredite JCI',85,false,'Samitivej','noopener'),
('SG','Singapour','singapour','asie','sante','hopital-jci','Mount Elizabeth Hospital','https://www.mountelizabeth.com.sg/','mountelizabeth.com.sg','Hopital prive de reference a Singapour',85,false,'Mount Elizabeth','noopener'),
('SG','Singapour','singapour','asie','sante','hopital-jci','Raffles Hospital','https://www.rafflesmedicalgroup.com/','rafflesmedicalgroup.com','Groupe medical international base a Singapour',85,false,'Raffles Hospital','noopener'),
('AE','Emirats arabes unis','emirats-arabes-unis','asie','sante','hopital-jci','Cleveland Clinic Abu Dhabi','https://www.clevelandclinicabudhabi.ae/','clevelandclinicabudhabi.ae','Hopital de classe mondiale a Abu Dhabi',90,false,'Cleveland Clinic Abu Dhabi','noopener'),
('AE','Emirats arabes unis','emirats-arabes-unis','asie','sante','hopital-jci','Mediclinic Dubai','https://www.mediclinic.ae/','mediclinic.ae','Reseau d hopitaux aux Emirats',80,false,'Mediclinic Dubai','noopener'),
('IN','Inde','inde','asie','sante','hopital-jci','Apollo Hospitals','https://www.apollohospitals.com/','apollohospitals.com','Plus grand reseau hospitalier prive en Inde, accredite JCI',85,false,'Apollo Hospitals','noopener'),
('IN','Inde','inde','asie','sante','hopital-jci','Fortis Healthcare','https://www.fortishealthcare.com/','fortishealthcare.com','Grand reseau hospitalier indien',80,false,'Fortis Healthcare','noopener'),
('JP','Japon','japon','asie','sante','hopital','St. Luke''s International Hospital','https://hospital.luke.ac.jp/eng/','luke.ac.jp','Hopital international de reference a Tokyo',85,false,'St Luke''s Tokyo','noopener'),
('KR','Coree du Sud','coree-du-sud','asie','sante','hopital-jci','Samsung Medical Center','https://www.samsunghospital.com/','samsunghospital.com','Centre medical de reference a Seoul',85,false,'Samsung Medical Center','noopener'),
('TR','Turquie','turquie','asie','sante','hopital-jci','Acibadem Healthcare','https://www.acibadem.com.tr/en/','acibadem.com.tr','Reseau d hopitaux accredites JCI en Turquie',85,false,'Acibadem','noopener'),
('MY','Malaisie','malaisie','asie','sante','hopital-jci','Gleneagles Hospital KL','https://gleneagles.com.my/','gleneagles.com.my','Hopital international a Kuala Lumpur',80,false,'Gleneagles KL','noopener'),
('VN','Vietnam','vietnam','asie','sante','hopital','Vinmec Hospital','https://www.vinmec.com/en/','vinmec.com','Reseau hospitalier international au Vietnam',80,false,'Vinmec','noopener'),
('IL','Israel','israel','asie','sante','hopital-jci','Hadassah Medical Center','https://www.hadassah.org.il/','hadassah.org.il','Centre medical de reference a Jerusalem',85,false,'Hadassah','noopener'),

-- Afrique
('MA','Maroc','maroc','afrique','sante','hopital','Clinique Internationale de Casablanca','https://www.clinique-internationale.com/','clinique-internationale.com','Clinique de reference a Casablanca',75,false,'Clinique Internationale Casa','noopener'),
('TN','Tunisie','tunisie','afrique','sante','hopital','Clinique La Soukra','https://www.cliniquelasoukra.com/','cliniquelasoukra.com','Clinique internationale pres de Tunis',70,false,'Clinique La Soukra','noopener'),
('ZA','Afrique du Sud','afrique-du-sud','afrique','sante','hopital','Netcare Hospitals','https://www.netcare.co.za/','netcare.co.za','Plus grand reseau hospitalier prive d Afrique du Sud',80,false,'Netcare','noopener'),
('KE','Kenya','kenya','afrique','sante','hopital','Aga Khan University Hospital Nairobi','https://hospitals.aku.edu/nairobi/','aku.edu','Hopital universitaire de reference a Nairobi',80,false,'Aga Khan Nairobi','noopener'),
('EG','Egypte','egypte','afrique','sante','hopital','As-Salam International Hospital','https://www.assih.com/','assih.com','Hopital international accredite JCI au Caire',80,false,'As-Salam Hospital','noopener'),
('SN','Senegal','senegal','afrique','sante','hopital','Hopital Principal de Dakar','https://www.hopitalprincipal.sn/','hopitalprincipal.sn','Hopital de reference a Dakar, urgences 24h/24',80,true,'Hopital Principal Dakar','noopener'),
('CI','Cote d Ivoire','cote-d-ivoire','afrique','sante','hopital','Polyclinique Internationale Ste Anne-Marie','https://www.pisam.ci/','pisam.ci','Clinique de reference a Abidjan',75,false,'PISAM Abidjan','noopener'),

-- Ameriques
('MX','Mexique','mexique','amerique-nord','sante','hopital-jci','Hospital Angeles','https://www.hospitalangeles.com/','hospitalangeles.com','Reseau d hopitaux accredites JCI au Mexique',80,false,'Hospital Angeles','noopener'),
('BR','Bresil','bresil','amerique-sud','sante','hopital-jci','Hospital Sirio-Libanes','https://www.hospitalsiriolibanes.org.br/','hospitalsiriolibanes.org.br','Hopital de reference a Sao Paulo, accredite JCI',85,false,'Sirio-Libanes','noopener'),
('BR','Bresil','bresil','amerique-sud','sante','hopital-jci','Hospital Albert Einstein','https://www.einstein.br/','einstein.br','Hopital de classe mondiale a Sao Paulo',85,false,'Albert Einstein','noopener'),
('CO','Colombie','colombie','amerique-sud','sante','hopital-jci','Fundacion Valle del Lili','https://www.valledellili.org/','valledellili.org','Hopital accredite JCI a Cali',80,false,'Valle del Lili','noopener');


-- =============================================
-- TELECOM — Principaux operateurs par pays
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
-- Europe
('DE','Allemagne','allemagne','europe','telecom','mobile','Deutsche Telekom / T-Mobile','https://www.telekom.de/','telekom.de','1er operateur allemand: forfaits, prepaye, fibre',80,false,'Telekom DE','noopener nofollow'),
('DE','Allemagne','allemagne','europe','telecom','mobile','Aldi Talk — Prepaye','https://www.alditalk.de/','alditalk.de','Carte SIM prepayee economique, reseau Telefonica',70,false,'Aldi Talk','noopener nofollow'),
('ES','Espagne','espagne','europe','telecom','mobile','Movistar','https://www.movistar.es/','movistar.es','1er operateur espagnol (Telefonica)',80,false,'Movistar','noopener nofollow'),
('PT','Portugal','portugal','europe','telecom','mobile','Vodafone Portugal','https://www.vodafone.pt/','vodafone.pt','Operateur majeur au Portugal',80,false,'Vodafone PT','noopener nofollow'),
('IT','Italie','italie','europe','telecom','mobile','TIM','https://www.tim.it/','tim.it','1er operateur italien',80,false,'TIM','noopener nofollow'),
('GB','Royaume-Uni','royaume-uni','europe','telecom','mobile','Three UK','https://www.three.co.uk/','three.co.uk','Operateur mobile UK, bon rapport qualite-prix',75,false,'Three UK','noopener nofollow'),
('GB','Royaume-Uni','royaume-uni','europe','telecom','prepaye','giffgaff — Prepaye UK','https://www.giffgaff.com/','giffgaff.com','SIM prepayee populaire pour expatries UK, sans engagement',75,false,'giffgaff','noopener nofollow'),
('BE','Belgique','belgique','europe','telecom','mobile','Proximus','https://www.proximus.be/','proximus.be','1er operateur belge',80,false,'Proximus','noopener nofollow'),
('NL','Pays-Bas','pays-bas','europe','telecom','mobile','KPN','https://www.kpn.com/','kpn.com','1er operateur neerlandais',80,false,'KPN','noopener nofollow'),
('CH','Suisse','suisse','europe','telecom','mobile','Swisscom','https://www.swisscom.ch/','swisscom.ch','1er operateur suisse',80,false,'Swisscom','noopener nofollow'),
('LU','Luxembourg','luxembourg','europe','telecom','mobile','POST Luxembourg','https://www.post.lu/','post.lu','Operateur historique luxembourgeois',80,false,'POST','noopener nofollow'),
('IE','Irlande','irlande','europe','telecom','mobile','Vodafone Ireland','https://n.vodafone.ie/','vodafone.ie','1er operateur irlandais',80,false,'Vodafone IE','noopener nofollow'),
('SE','Suede','suede','europe','telecom','mobile','Telia','https://www.telia.se/','telia.se','1er operateur suedois',80,false,'Telia','noopener nofollow'),
('DK','Danemark','danemark','europe','telecom','mobile','TDC','https://www.tdc.dk/','tdc.dk','1er operateur danois',80,false,'TDC','noopener nofollow'),
('NO','Norvege','norvege','europe','telecom','mobile','Telenor','https://www.telenor.no/','telenor.no','1er operateur norvegien',80,false,'Telenor','noopener nofollow'),
('PL','Pologne','pologne','europe','telecom','mobile','Orange Polska','https://www.orange.pl/','orange.pl','1er operateur polonais',80,false,'Orange PL','noopener nofollow'),
('RO','Roumanie','roumanie','europe','telecom','mobile','Orange Romania','https://www.orange.ro/','orange.ro','1er operateur roumain',80,false,'Orange RO','noopener nofollow'),
('GR','Grece','grece','europe','telecom','mobile','Cosmote','https://www.cosmote.gr/','cosmote.gr','1er operateur grec',80,false,'Cosmote','noopener nofollow'),
('AT','Autriche','autriche','europe','telecom','mobile','Magenta Telekom','https://www.magenta.at/','magenta.at','1er operateur autrichien (T-Mobile)',80,false,'Magenta AT','noopener nofollow'),
('CZ','Republique Tcheque','republique-tcheque','europe','telecom','mobile','T-Mobile CZ','https://www.t-mobile.cz/','t-mobile.cz','1er operateur tcheque',80,false,'T-Mobile CZ','noopener nofollow'),

-- Ameriques
('US','Etats-Unis','etats-unis','amerique-nord','telecom','mobile','T-Mobile US','https://www.t-mobile.com/','t-mobile.com','1er operateur US: 146M abonnes, 5G',80,false,'T-Mobile US','noopener nofollow'),
('US','Etats-Unis','etats-unis','amerique-nord','telecom','prepaye','Mint Mobile — Prepaye','https://www.mintmobile.com/','mintmobile.com','Forfait prepaye economique sur reseau T-Mobile',70,false,'Mint Mobile','noopener nofollow'),
('CA','Canada','canada','amerique-nord','telecom','mobile','Rogers','https://www.rogers.com/','rogers.com','1er operateur canadien',80,false,'Rogers','noopener nofollow'),
('CA','Canada','canada','amerique-nord','telecom','prepaye','Fido — Prepaye Canada','https://www.fido.ca/','fido.ca','Marque prepayee de Rogers, populaire chez les expatries',75,false,'Fido','noopener nofollow'),
('MX','Mexique','mexique','amerique-nord','telecom','mobile','Telcel','https://www.telcel.com/','telcel.com','1er operateur mexicain (America Movil)',80,false,'Telcel','noopener nofollow'),
('BR','Bresil','bresil','amerique-sud','telecom','mobile','Claro Brasil','https://www.claro.com.br/celular','claro.com.br','1er operateur bresilien',80,false,'Claro BR','noopener nofollow'),
('AR','Argentine','argentine','amerique-sud','telecom','mobile','Claro Argentina','https://www.claro.com.ar/','claro.com.ar','1er operateur argentin',80,false,'Claro AR','noopener nofollow'),
('CO','Colombie','colombie','amerique-sud','telecom','mobile','Claro Colombia','https://www.claro.com.co/','claro.com.co','1er operateur colombien',80,false,'Claro CO','noopener nofollow'),

-- Afrique
('MA','Maroc','maroc','afrique','telecom','mobile','Maroc Telecom','https://www.iam.ma/','iam.ma','1er operateur marocain',80,false,'Maroc Telecom','noopener nofollow'),
('MA','Maroc','maroc','afrique','telecom','mobile','inwi','https://www.inwi.ma/','inwi.ma','Operateur marocain, forfaits internet',75,false,'inwi','noopener nofollow'),
('TN','Tunisie','tunisie','afrique','telecom','mobile','Ooredoo Tunisie','https://www.ooredoo.tn/','ooredoo.tn','Operateur tunisien, SIM prepayee',75,false,'Ooredoo TN','noopener nofollow'),
('DZ','Algerie','algerie','afrique','telecom','mobile','Ooredoo Algerie','https://www.ooredoo.dz/','ooredoo.dz','1er operateur algerien',75,false,'Ooredoo DZ','noopener nofollow'),
('SN','Senegal','senegal','afrique','telecom','mobile','Orange Senegal','https://www.orange.sn/','orange.sn','1er operateur senegalais',75,false,'Orange SN','noopener nofollow'),
('CI','Cote d Ivoire','cote-d-ivoire','afrique','telecom','mobile','Orange Cote d Ivoire','https://www.orange.ci/','orange.ci','1er operateur ivoirien',75,false,'Orange CI','noopener nofollow'),
('ZA','Afrique du Sud','afrique-du-sud','afrique','telecom','mobile','Vodacom','https://www.vodacom.co.za/','vodacom.co.za','1er operateur sud-africain',80,false,'Vodacom','noopener nofollow'),
('KE','Kenya','kenya','afrique','telecom','mobile','Safaricom','https://www.safaricom.co.ke/','safaricom.co.ke','1er operateur kenyan, M-Pesa',80,false,'Safaricom','noopener nofollow'),
('NG','Nigeria','nigeria','afrique','telecom','mobile','MTN Nigeria','https://www.mtn.ng/','mtn.ng','1er operateur nigerian',75,false,'MTN Nigeria','noopener nofollow'),
('EG','Egypte','egypte','afrique','telecom','mobile','Vodafone Egypt','https://www.vodafone.com.eg/','vodafone.com.eg','1er operateur egyptien',80,false,'Vodafone EG','noopener nofollow'),
('CM','Cameroun','cameroun','afrique','telecom','mobile','Orange Cameroun','https://www.orange.cm/','orange.cm','1er operateur camerounais',75,false,'Orange CM','noopener nofollow'),
('MG','Madagascar','madagascar','afrique','telecom','mobile','Orange Madagascar','https://www.orange.mg/','orange.mg','1er operateur malgache',75,false,'Orange MG','noopener nofollow'),

-- Asie
('TH','Thailande','thailande','asie','telecom','mobile','AIS','https://www.ais.th/','ais.th','1er operateur thailandais, meilleure couverture',80,false,'AIS','noopener nofollow'),
('TH','Thailande','thailande','asie','telecom','prepaye','DTAC — SIM Tourist','https://www.dtac.co.th/en/','dtac.co.th','SIM touristique populaire en Thailande',70,false,'DTAC','noopener nofollow'),
('JP','Japon','japon','asie','telecom','mobile','NTT Docomo','https://www.docomo.ne.jp/english/','docomo.ne.jp','1er operateur japonais',80,false,'Docomo','noopener nofollow'),
('JP','Japon','japon','asie','telecom','prepaye','Japan Wireless — SIM','https://www.japan-wireless.com/','japan-wireless.com','Location SIM et pocket WiFi pour etrangers',70,false,'Japan Wireless','noopener nofollow'),
('SG','Singapour','singapour','asie','telecom','mobile','Singtel','https://www.singtel.com/','singtel.com','1er operateur singapourien',80,false,'Singtel','noopener nofollow'),
('AE','Emirats arabes unis','emirats-arabes-unis','asie','telecom','mobile','e& (Etisalat)','https://www.etisalat.ae/','etisalat.ae','1er operateur emirati',80,false,'Etisalat','noopener nofollow'),
('VN','Vietnam','vietnam','asie','telecom','mobile','Viettel','https://www.viettel.com.vn/','viettel.com.vn','1er operateur vietnamien',75,false,'Viettel','noopener nofollow'),
('CN','Chine','chine','asie','telecom','mobile','China Mobile','https://www.chinamobileltd.com/','chinamobileltd.com','1er operateur mondial par abonnes (980M+)',80,false,'China Mobile','noopener nofollow'),
('IN','Inde','inde','asie','telecom','mobile','Jio','https://www.jio.com/','jio.com','1er operateur indien, forfaits tres economiques',75,false,'Jio','noopener nofollow'),
('KR','Coree du Sud','coree-du-sud','asie','telecom','prepaye','KT Olleh — SIM Tourist','https://roaming.kt.com/','kt.com','SIM touristique pour la Coree du Sud',70,false,'KT Tourist SIM','noopener nofollow'),
('TR','Turquie','turquie','asie','telecom','mobile','Turkcell','https://www.turkcell.com.tr/','turkcell.com.tr','1er operateur turc',80,false,'Turkcell','noopener nofollow'),
('IL','Israel','israel','asie','telecom','mobile','Partner (Orange)','https://www.partner.co.il/en/','partner.co.il','Operateur israelien, SIM prepayee',75,false,'Partner IL','noopener nofollow'),
('MY','Malaisie','malaisie','asie','telecom','mobile','CelcomDigi','https://www.celcomdigi.com/','celcomdigi.com','1er operateur malaisien',80,false,'CelcomDigi','noopener nofollow'),
('ID','Indonesie','indonesie','asie','telecom','mobile','Telkomsel','https://www.telkomsel.com/','telkomsel.com','1er operateur indonesien (170M+ abonnes)',80,false,'Telkomsel','noopener nofollow'),
('PH','Philippines','philippines','asie','telecom','mobile','Globe Telecom','https://www.globe.com.ph/','globe.com.ph','Operateur philippin majeur',75,false,'Globe Telecom','noopener nofollow'),
('KH','Cambodge','cambodge','asie','telecom','mobile','Smart Axiata','https://www.smart.com.kh/','smart.com.kh','1er operateur cambodgien, SIM facile pour touristes',70,false,'Smart Axiata','noopener nofollow'),

-- Oceanie
('AU','Australie','australie','oceanie','telecom','mobile','Telstra','https://www.telstra.com.au/','telstra.com.au','1er operateur australien, meilleure couverture',80,false,'Telstra','noopener nofollow'),
('AU','Australie','australie','oceanie','telecom','prepaye','Amaysim — Prepaye','https://www.amaysim.com.au/','amaysim.com.au','SIM prepayee populaire chez les backpackers',70,false,'Amaysim','noopener nofollow'),
('NZ','Nouvelle-Zelande','nouvelle-zelande','oceanie','telecom','mobile','Spark NZ','https://www.spark.co.nz/','spark.co.nz','1er operateur neo-zelandais',80,false,'Spark NZ','noopener nofollow');


-- =============================================
-- VERIFICATION FINALE
-- =============================================
SELECT
  continent,
  COUNT(DISTINCT country_code) as pays,
  COUNT(*) as total_liens,
  COUNT(*) FILTER (WHERE category = 'ambassade') as ambassades,
  COUNT(*) FILTER (WHERE category = 'immigration') as immigration,
  COUNT(*) FILTER (WHERE category = 'sante') as sante,
  COUNT(*) FILTER (WHERE category = 'logement') as logement,
  COUNT(*) FILTER (WHERE category = 'emploi') as emploi,
  COUNT(*) FILTER (WHERE category = 'telecom') as telecom,
  COUNT(*) FILTER (WHERE category = 'fiscalite') as fiscalite,
  COUNT(*) FILTER (WHERE category = 'transport') as transport
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY continent
ORDER BY total_liens DESC;

SELECT '=== TOTAL ANNUAIRE ===' as label,
  COUNT(*) as entries,
  COUNT(DISTINCT country_code) as countries,
  COUNT(DISTINCT category) as categories,
  COUNT(*) FILTER (WHERE is_official) as official,
  COUNT(*) FILTER (WHERE NOT is_official) as private,
  COUNT(*) FILTER (WHERE address IS NOT NULL) as with_address,
  COUNT(*) FILTER (WHERE phone IS NOT NULL OR phone_emergency IS NOT NULL) as with_phone,
  COUNT(*) FILTER (WHERE email IS NOT NULL) as with_email,
  COUNT(*) FILTER (WHERE latitude IS NOT NULL) as with_gps
FROM country_directory;
