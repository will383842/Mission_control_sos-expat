-- =============================================
-- GENERATION TEMPLATES : Structure complete par type
-- Chaque template_id a sa definition de structure SEO/AEO
-- =============================================

CREATE TABLE IF NOT EXISTS generation_templates (
  id SERIAL PRIMARY KEY,
  template_id VARCHAR(20) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  category_slug VARCHAR(50) REFERENCES generation_source_categories(slug),
  target_word_count_min INTEGER NOT NULL DEFAULT 2000,
  target_word_count_max INTEGER NOT NULL DEFAULT 4000,
  tone VARCHAR(30) NOT NULL DEFAULT 'expert',
  article_structure JSONB NOT NULL DEFAULT '[]',
  seo_config JSONB NOT NULL DEFAULT '{}',
  aeo_config JSONB NOT NULL DEFAULT '{}',
  structured_data JSONB NOT NULL DEFAULT '{}',
  linking_config JSONB NOT NULL DEFAULT '{}',
  languages JSONB NOT NULL DEFAULT '["fr","en","es","de","pt","it","nl","ru","ar"]',
  variables JSONB NOT NULL DEFAULT '[]',
  has_variables BOOLEAN NOT NULL DEFAULT true,
  media_config JSONB NOT NULL DEFAULT '{}',
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW()
);

-- TPL_GUIDE
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_GUIDE', 'Guide Pays Complet', 'Article pilier SEO : guide exhaustif par pays, agrege toutes les sources', 'fiches-pays', 3000, 5000, 'expert',
 '[{"level":"h2","title":"Introduction : pourquoi {pays}","required":true,"word_target":200},{"level":"h2","title":"Visa et immigration","required":true,"word_target":400,"source_theme":"visa"},{"level":"h2","title":"Cout de la vie et budget","required":true,"word_target":400,"source_theme":"banque"},{"level":"h2","title":"Travailler en {pays}","required":true,"word_target":400,"source_theme":"emploi"},{"level":"h2","title":"Se loger","required":true,"word_target":350,"source_theme":"logement"},{"level":"h2","title":"Systeme de sante","required":true,"word_target":350,"source_theme":"sante"},{"level":"h2","title":"Education et scolarite","required":false,"word_target":300,"source_theme":"education"},{"level":"h2","title":"Transports","required":false,"word_target":250,"source_theme":"transport"},{"level":"h2","title":"Telecoms et internet","required":false,"word_target":200,"source_theme":"telecom"},{"level":"h2","title":"Culture et vie quotidienne","required":false,"word_target":250,"source_theme":"culture"},{"level":"h2","title":"Securite et precautions","required":true,"word_target":300},{"level":"h2","title":"Demarches administratives","required":true,"word_target":300,"source_theme":"demarches"},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":10}]',
 '{"meta_title_template":"Guide {pays} {annee} : Visa, Emploi, Logement | SOS-Expat","meta_description_template":"Tout savoir pour vivre en {pays} : visa, cout de la vie, emploi, logement, sante. Guide complet mis a jour {annee}.","slug_template":"guide-complet-{pays-slug}","primary_keyword_template":"guide {pays}","secondary_keywords_templates":["vivre en {pays}","expatrier en {pays}","cout de la vie {pays}"],"semantic_keywords":["expatriation","installation","demarches","visa","logement","emploi"],"keyword_density_target":1.5,"readability_target":"B1-B2"}',
 '{"featured_snippet":true,"snippet_type":"paragraph","faq_count":10,"faq_schema":true,"how_to_schema":false,"summary_position":"top","table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"breadcrumb_template":["Accueil","Guides","Guide {pays}"],"article_type":"Article"}',
 '{"internal_links_min":8,"internal_links_max":15,"internal_link_strategy":"pillar_cluster","external_links_min":3,"external_links_max":8,"external_link_types":["official","reference"],"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":6,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":3,"image_source":"unsplash","infographic":false}');

-- TPL_FICHE
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_FICHE', 'Fiche Pratique', 'Fiche concise et actionnable : numeros, checklist, ambassades, premiers jours', 'fiches-pratiques', 1500, 2500, 'friendly',
 '[{"level":"h2","title":"Essentiel a retenir","required":true,"type":"summary_box","word_target":150},{"level":"h2","title":"Informations cles","required":true,"word_target":400},{"level":"h2","title":"Etape par etape","required":true,"type":"steps","word_target":500},{"level":"h2","title":"Contacts utiles","required":true,"type":"contact_list","word_target":200},{"level":"h2","title":"Erreurs a eviter","required":false,"word_target":200},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":5}]',
 '{"slug_template":"{slug_url}","keyword_density_target":2.0,"readability_target":"A2-B1"}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":5,"faq_schema":true,"how_to_schema":true,"summary_position":"top","table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["HowTo","FAQPage","BreadcrumbList"],"breadcrumb_template":["Accueil","Fiches pratiques","{pays}","{titre}"],"article_type":"HowTo"}',
 '{"internal_links_min":3,"internal_links_max":8,"internal_link_strategy":"same_country","external_links_min":2,"external_links_max":5,"external_link_types":["official","tool"],"affiliate_links":false,"related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":1,"image_source":"unsplash"}');

-- TPL_FAQ
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_FAQ', 'FAQ Thematique', 'Article de 30-50 questions/reponses optimise pour les rich snippets FAQ Google', 'faq', 2000, 3500, 'friendly',
 '[{"level":"h2","title":"Introduction","required":true,"word_target":150},{"level":"h2","title":"Questions les plus frequentes","required":true,"type":"faq","faq_count":15},{"level":"h2","title":"Questions avancees","required":true,"type":"faq","faq_count":10},{"level":"h2","title":"Questions pratiques","required":false,"type":"faq","faq_count":10},{"level":"h2","title":"Ressources utiles","required":true,"word_target":200}]',
 '{"meta_title_template":"FAQ {theme} {pays} : {nb} Questions Reponses | SOS-Expat","slug_template":"faq-{theme}-{pays-slug}","keyword_density_target":1.0,"readability_target":"A2-B1"}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":35,"faq_schema":true,"summary_position":"top","table_of_contents":true}',
 '{"json_ld_types":["FAQPage","BreadcrumbList"],"breadcrumb_template":["Accueil","FAQ","{pays}","{theme}"],"article_type":"Article"}',
 '{"internal_links_min":5,"internal_links_max":12,"internal_link_strategy":"same_theme","external_links_min":2,"external_links_max":5,"affiliate_links":false,"related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","theme","pays-slug"]', true,
 '{"featured_image":true,"inline_images":0,"image_source":"unsplash"}');

-- TPL_VS
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_VS', 'Comparatif VS', 'Article de conversion : SOS-Expat vs consulat/avocat/Facebook/assurance', 'comparatifs', 1800, 2800, 'expert',
 '[{"level":"h2","title":"Contexte : le probleme","required":true,"word_target":200},{"level":"h2","title":"Tableau comparatif","required":true,"type":"comparison_table","word_target":300},{"level":"h2","title":"Avantages SOS-Expat","required":true,"word_target":400},{"level":"h2","title":"Limites de l alternative","required":true,"word_target":300},{"level":"h2","title":"Temoignage","required":false,"word_target":200},{"level":"h2","title":"Verdict","required":true,"word_target":200,"type":"verdict_box"},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":5}]',
 '{"meta_title_template":"SOS-Expat vs {concurrent} en {pays} | Comparatif {annee}","slug_template":"sos-expat-vs-{concurrent-slug}-{pays-slug}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"table","faq_count":5,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article","rating_schema":true}',
 '{"internal_links_min":3,"internal_links_max":8,"affiliate_links":true,"affiliate_placement":"cta_box","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en"]', '["pays","concurrent","pays-slug","concurrent-slug","annee"]', true,
 '{"featured_image":true,"inline_images":1,"image_source":"unsplash"}');

-- TPL_COMP
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_COMP', 'Comparatif Pays/Services', 'Article comparatif pour affiliation : pays vs pays, services, classements', 'comparatifs', 2000, 3000, 'expert',
 '[{"level":"h2","title":"Introduction","required":true,"word_target":200},{"level":"h2","title":"Tableau comparatif detaille","required":true,"type":"comparison_table","word_target":400},{"level":"h2","title":"Analyse point par point","required":true,"word_target":600},{"level":"h2","title":"Pour qui choisir quoi","required":true,"word_target":300,"type":"audience_recommendations"},{"level":"h2","title":"Notre recommandation","required":true,"word_target":200,"type":"verdict_box"},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":6}]',
 '{"slug_template":"{slug_url}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"table","faq_count":6,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article","rating_schema":true}',
 '{"internal_links_min":5,"internal_links_max":12,"affiliate_links":true,"affiliate_placement":"comparison_table","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt"]', '["pays","pays2","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":2,"image_source":"unsplash","infographic":true}');

-- TPL_TOP
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_TOP', 'Top / Classement', 'Article listicle : Top 10 villes, meilleures banques, etc.', 'comparatifs', 2000, 3000, 'friendly',
 '[{"level":"h2","title":"Introduction et methodologie","required":true,"word_target":200},{"level":"h2","title":"Le classement","required":true,"type":"numbered_list","word_target":1500},{"level":"h2","title":"Mentions honorables","required":false,"word_target":300},{"level":"h2","title":"Comment choisir","required":true,"word_target":200},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":5}]',
 '{"slug_template":"{slug_url}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":5,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","ItemList","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":5,"internal_links_max":15,"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":3,"image_source":"unsplash"}');

-- TPL_LT
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_LT', 'Longue Traine', 'Article cible sur une situation precise : arnaque taxi, vol annule, telephone perdu', 'longues-traines', 1200, 2000, 'friendly',
 '[{"level":"h2","title":"Ce qu il faut faire immediatement","required":true,"type":"action_box","word_target":200},{"level":"h2","title":"Comprendre la situation","required":true,"word_target":300},{"level":"h2","title":"Etapes detaillees","required":true,"type":"steps","word_target":400},{"level":"h2","title":"Recours et contacts","required":true,"word_target":200},{"level":"h2","title":"Comment eviter cette situation","required":true,"word_target":200},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":5}]',
 '{"slug_template":"{slug_url}","keyword_density_target":2.0,"readability_target":"A2-B1"}',
 '{"featured_snippet":true,"snippet_type":"how_to","faq_count":5,"faq_schema":true,"how_to_schema":true,"summary_position":"top","table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["HowTo","FAQPage","BreadcrumbList"],"article_type":"HowTo"}',
 '{"internal_links_min":3,"internal_links_max":8,"internal_link_strategy":"same_country","external_links_min":1,"external_links_max":3,"affiliate_links":false,"related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","pays-slug"]', true,
 '{"featured_image":true,"inline_images":1,"image_source":"unsplash"}');

-- TPL_URG
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_URG', 'Urgence', 'Article a ton urgent : arnaques, vols, agressions, accidents', 'urgences', 1500, 2500, 'urgent',
 '[{"level":"h2","title":"URGENCE : les premiers gestes","required":true,"type":"emergency_box","word_target":200},{"level":"h2","title":"Numeros d urgence","required":true,"type":"contact_list","word_target":150},{"level":"h2","title":"Procedure complete","required":true,"type":"steps","word_target":500},{"level":"h2","title":"Vos droits","required":true,"word_target":300},{"level":"h2","title":"Comment se faire rembourser","required":false,"word_target":300},{"level":"h2","title":"Prevention","required":true,"word_target":200},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":5}]',
 '{"slug_template":"{slug_url}","keyword_density_target":2.0,"readability_target":"A1-A2"}',
 '{"featured_snippet":true,"snippet_type":"how_to","faq_count":5,"faq_schema":true,"how_to_schema":true,"summary_position":"top","table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["HowTo","FAQPage","BreadcrumbList"],"article_type":"HowTo"}',
 '{"internal_links_min":3,"internal_links_max":8,"internal_link_strategy":"same_country","external_links_min":2,"external_links_max":5,"affiliate_links":false,"related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","pays-slug"]', true,
 '{"featured_image":true,"inline_images":0,"image_source":"unsplash"}');

-- TPL_TEM
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_TEM', 'Temoignage', 'Recit d experience reelle : ton storytelling, preuve sociale', 'temoignages', 1000, 1800, 'storytelling',
 '[{"level":"h2","title":"La situation de depart","required":true,"word_target":250},{"level":"h2","title":"Le probleme rencontre","required":true,"word_target":250},{"level":"h2","title":"Comment SOS-Expat a aide","required":true,"word_target":300},{"level":"h2","title":"Le resultat","required":true,"word_target":150,"type":"result_box"},{"level":"h2","title":"Conseils pour eviter cette situation","required":true,"word_target":200},{"level":"h2","title":"FAQ","required":false,"type":"faq","faq_count":3}]',
 '{"slug_template":"{slug_url}","keyword_density_target":1.0}',
 '{"featured_snippet":false,"faq_count":3,"faq_schema":true,"table_of_contents":false}',
 '{"json_ld_types":["Article","BreadcrumbList"],"breadcrumb_template":["Accueil","Temoignages","{pays}"],"article_type":"Article"}',
 '{"internal_links_min":2,"internal_links_max":5,"affiliate_links":false,"related_articles_count":3,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en"]', '["pays","ville","pays-slug"]', true,
 '{"featured_image":true,"inline_images":1,"image_source":"ai_generated"}');

-- TPL_EXPAT
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_EXPAT', 'Audience Expatries', 'Guide specialise pour les expatries : droits, demarches, erreurs, retour', 'audiences', 2000, 3000, 'expert',
 '[{"level":"h2","title":"Avant de partir","required":true,"word_target":300},{"level":"h2","title":"Les demarches essentielles","required":true,"type":"steps","word_target":400},{"level":"h2","title":"Droits et obligations","required":true,"word_target":400},{"level":"h2","title":"Vie quotidienne","required":true,"word_target":300},{"level":"h2","title":"Erreurs a eviter","required":true,"word_target":250},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":8}]',
 '{"slug_template":"{slug_url}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":8,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":5,"internal_links_max":12,"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":2,"image_source":"unsplash"}');

-- TPL_NOMAD
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_NOMAD', 'Audience Digital Nomads', 'Guide pour digital nomads : visa nomade, coworking, internet, budget', 'audiences', 1800, 2800, 'friendly',
 '[{"level":"h2","title":"Visa nomade digital","required":true,"word_target":300},{"level":"h2","title":"Internet et coworking","required":true,"word_target":300},{"level":"h2","title":"Budget mensuel","required":true,"type":"budget_table","word_target":300},{"level":"h2","title":"Meilleurs quartiers/villes","required":true,"word_target":300},{"level":"h2","title":"Communaute et networking","required":false,"word_target":200},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":6}]',
 '{"slug_template":"digital-nomad-{pays-slug}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":6,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":4,"internal_links_max":10,"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":2,"image_source":"unsplash"}');

-- TPL_RETRAITE
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_RETRAITE', 'Audience Retraites', 'Guide retraite a l etranger : pension, cout de vie, sante, fiscalite', 'audiences', 2000, 3000, 'expert',
 '[{"level":"h2","title":"Pourquoi prendre sa retraite en {pays}","required":true,"word_target":250},{"level":"h2","title":"Visa et droit de sejour","required":true,"word_target":300},{"level":"h2","title":"Pension et fiscalite","required":true,"word_target":400},{"level":"h2","title":"Cout de la vie detaille","required":true,"type":"budget_table","word_target":350},{"level":"h2","title":"Sante et assurance","required":true,"word_target":300},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":8}]',
 '{"slug_template":"retraite-{pays-slug}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"paragraph","faq_count":8,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":5,"internal_links_max":12,"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":2,"image_source":"unsplash"}');

-- TPL_VOYAGEUR
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_VOYAGEUR', 'Audience Voyageurs', 'Guide voyage : preparer son voyage, sur place, securite, budget', 'audiences', 1800, 2500, 'friendly',
 '[{"level":"h2","title":"Preparer son voyage","required":true,"type":"checklist","word_target":300},{"level":"h2","title":"Visa et entree","required":true,"word_target":250},{"level":"h2","title":"Budget et moyens de paiement","required":true,"word_target":300},{"level":"h2","title":"Securite et sante","required":true,"word_target":300},{"level":"h2","title":"Se deplacer sur place","required":true,"word_target":250},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":6}]',
 '{"slug_template":"guide-voyage-{pays-slug}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":6,"faq_schema":true,"how_to_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":4,"internal_links_max":10,"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":2,"image_source":"unsplash"}');

-- TPL_ETUDIANT
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_ETUDIANT', 'Audience Etudiants', 'Guide etudes a l etranger : universites, bourses, visa etudiant, budget', 'audiences', 1800, 2800, 'friendly',
 '[{"level":"h2","title":"Universites et programmes","required":true,"word_target":300},{"level":"h2","title":"Visa etudiant","required":true,"word_target":300},{"level":"h2","title":"Bourses et financement","required":true,"word_target":300},{"level":"h2","title":"Logement etudiant","required":true,"word_target":250},{"level":"h2","title":"Budget mensuel","required":true,"type":"budget_table","word_target":250},{"level":"h2","title":"Vie etudiante et jobs","required":false,"word_target":200},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":6}]',
 '{"slug_template":"etudier-{pays-slug}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":6,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":4,"internal_links_max":10,"affiliate_links":false,"related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":2,"image_source":"unsplash"}');

-- TPL_FAMILLE
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_FAMILLE', 'Audience Familles', 'Guide expatriation en famille : ecoles, sante enfants, logement familial', 'audiences', 2000, 3000, 'friendly',
 '[{"level":"h2","title":"Scolarite et ecoles","required":true,"word_target":400},{"level":"h2","title":"Sante et pediatres","required":true,"word_target":300},{"level":"h2","title":"Logement adapte aux familles","required":true,"word_target":250},{"level":"h2","title":"Activites et loisirs","required":true,"word_target":250},{"level":"h2","title":"Budget familial","required":true,"type":"budget_table","word_target":250},{"level":"h2","title":"Demarches administratives","required":true,"word_target":250},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":6}]',
 '{"slug_template":"famille-{pays-slug}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"paragraph","faq_count":6,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":5,"internal_links_max":12,"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":2,"image_source":"unsplash"}');

-- TPL_VACANCIER
INSERT INTO generation_templates (template_id, name, description, category_slug, target_word_count_min, target_word_count_max, tone, article_structure, seo_config, aeo_config, structured_data, linking_config, languages, variables, has_variables, media_config) VALUES
('TPL_VACANCIER', 'Audience Vacanciers', 'Guide vacances : plages, activites, bons plans, securite', 'audiences', 1500, 2200, 'friendly',
 '[{"level":"h2","title":"Meilleure periode pour partir","required":true,"word_target":200},{"level":"h2","title":"Visa et formalites","required":true,"word_target":200},{"level":"h2","title":"Hebergement","required":true,"word_target":250},{"level":"h2","title":"Activites incontournables","required":true,"type":"numbered_list","word_target":300},{"level":"h2","title":"Budget vacances","required":true,"type":"budget_table","word_target":200},{"level":"h2","title":"Securite et sante","required":true,"word_target":200},{"level":"h2","title":"FAQ","required":true,"type":"faq","faq_count":5}]',
 '{"slug_template":"vacances-{pays-slug}","keyword_density_target":1.5}',
 '{"featured_snippet":true,"snippet_type":"list","faq_count":5,"faq_schema":true,"table_of_contents":true,"tldr":true}',
 '{"json_ld_types":["Article","FAQPage","BreadcrumbList"],"article_type":"Article"}',
 '{"internal_links_min":3,"internal_links_max":8,"affiliate_links":true,"affiliate_placement":"contextual","related_articles_count":4,"related_articles_strategy":"same_country_same_language"}',
 '["fr","en","es","de","pt","it","nl","ru","ar"]', '["pays","annee","pays-slug"]', true,
 '{"featured_image":true,"inline_images":3,"image_source":"unsplash"}');
