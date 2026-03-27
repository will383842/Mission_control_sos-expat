-- =============================================
-- CLASSIFY SOURCES — Peuple generation_source_items a partir des articles/questions scrappes
-- Execute APRES import_templates.sql (qui cree les categories + templates)
--
-- Taxonomie:
--   1. fiches-pays      — Pillar content: 1 fiche aggregee par pays (multi-theme)
--   2. fiches-pratiques  — Guides pratiques individuels par pays+theme
--   3. faq              — Questions populaires pour articles FAQ
--   4. comparatifs      — Donnees Numbeo + CFE pour comparatifs
--   5. longues-traines  — Opportunites non couvertes (content gaps)
--   6. audiences        — Par profil d'audience (retraites, familles, nomads...)
--   7. urgences         — Articles scrapes sur les urgences, arnaques, securite
--   8. titres-variables — Templates de titres a variables
-- =============================================

-- Ensure categories exist (idempotent)
INSERT INTO generation_source_categories (slug, name, description, icon, sort_order) VALUES
  ('fiches-pays',       'Fiches Pays',       'Guide pilier par pays : tout savoir avant de partir ou sur place. 1 fiche par pays, agregation multi-theme.', 'globe', 1),
  ('fiches-pratiques',  'Fiches Pratiques',   'Guides pratiques individuels par pays et theme (visa, sante, logement, etc.)',                               'file-text', 2),
  ('faq',               'FAQ',               'Questions populaires extraites des forums pour generer des articles FAQ',                                     'help-circle', 3),
  ('comparatifs',       'Comparatifs',       'Donnees Numbeo, CFE et statistiques pour articles comparatifs',                                               'bar-chart', 4),
  ('longues-traines',   'Longues Traines',   'Opportunites SEO non couvertes detectees par analyse de content gaps',                                        'search', 5),
  ('audiences',         'Audiences',         'Contenu par profil : retraites, familles, digital nomads, etudiants, entrepreneurs, pvtistes',                'users', 6),
  ('urgences',          'Urgences',          'Articles sur les arnaques, vols, accidents, urgences medicales par pays',                                      'alert-triangle', 7),
  ('titres-variables',  'Titres Variables',  'Templates de titres avec variables {pays}, {annee} pour generation en masse',                                 'type', 8),
  ('temoignages',       'Temoignages',       'Recits et temoignages (templates) : experiences reelles d expatries, voyageurs, avec SOS-Expat',              'book-open', 9)
ON CONFLICT (slug) DO UPDATE SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  icon = EXCLUDED.icon,
  sort_order = EXCLUDED.sort_order;


-- =============================================
-- 1. FICHES PAYS: 1 pillar par pays, agrege TOUS les articles du pays
--    → Chaque pays a exactement 1 entree dans generation_source_items
--    → data_json contient la liste des article_ids sources + couverture thematique
-- =============================================
DELETE FROM generation_source_items WHERE category_slug = 'fiches-pays' AND source_type = 'pillar';

INSERT INTO generation_source_items (
  category_slug, source_type, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status, data_json
)
SELECT
  'fiches-pays',
  'pillar',
  'Fiche Pays : ' || cc.name || ' — Guide complet expatriation',
  cc.name,
  cc.slug,
  'multi-theme',
  'pillar-' || cc.slug,
  COALESCE(mode() WITHIN GROUP (ORDER BY ca.language), 'fr'),
  SUM(ca.word_count),
  CASE
    WHEN COUNT(DISTINCT ca.category) >= 8 THEN 95  -- Couverture 8+ themes = excellent
    WHEN COUNT(DISTINCT ca.category) >= 5 THEN 85  -- 5-7 themes = bon
    WHEN COUNT(DISTINCT ca.category) >= 3 THEN 70  -- 3-4 themes = acceptable
    ELSE 50                                          -- 1-2 themes = minimal
  END,
  true,
  'ready',
  jsonb_build_object(
    'country_slug', cc.slug,
    'country_name', cc.name,
    'continent', cc.continent,
    'article_ids', (SELECT jsonb_agg(sub.id ORDER BY sub.word_count DESC) FROM content_articles sub WHERE sub.country_id = cc.id AND sub.processing_status = 'processed' AND sub.word_count >= 300),
    'categories_covered', (SELECT jsonb_agg(DISTINCT sub.category) FROM content_articles sub WHERE sub.country_id = cc.id AND sub.processing_status = 'processed' AND sub.category IS NOT NULL),
    'categories_count', COUNT(DISTINCT ca.category),
    'total_source_words', SUM(ca.word_count),
    'sources_count', COUNT(ca.id),
    'top_questions', (
      SELECT COALESCE(jsonb_agg(jsonb_build_object('id', q.id, 'title', q.title, 'views', q.views) ORDER BY q.views DESC), '[]'::jsonb)
      FROM (SELECT id, title, views FROM content_questions WHERE country_slug = cc.slug AND views >= 50 ORDER BY views DESC LIMIT 20) q
    )
  )
FROM content_countries cc
JOIN content_articles ca ON ca.country_id = cc.id
WHERE ca.processing_status = 'processed'
  AND ca.word_count >= 300
GROUP BY cc.id, cc.name, cc.slug, cc.continent
HAVING COUNT(ca.id) >= 2;


-- =============================================
-- 2. FICHES PRATIQUES: Guides pratiques individuels par pays+theme
--    → 1 entree par article de type "guide" avec categorie connue
--    → sub_category = "{theme}-{country_slug}" pour regrouper par pays
-- =============================================
DELETE FROM generation_source_items WHERE category_slug = 'fiches-pratiques' AND source_type = 'article';

INSERT INTO generation_source_items (
  category_slug, source_type, source_id, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status
)
SELECT
  'fiches-pratiques',
  'article',
  ca.id,
  ca.title,
  cc.name,
  cc.slug,
  COALESCE(ca.category, 'general'),
  COALESCE(ca.category, 'general') || '-' || COALESCE(cc.slug, 'global'),
  ca.language,
  ca.word_count,
  CASE
    WHEN ca.word_count >= 1500 THEN 90
    WHEN ca.word_count >= 800 THEN 70
    ELSE 50
  END,
  true,
  'ready'
FROM content_articles ca
LEFT JOIN content_countries cc ON ca.country_id = cc.id
WHERE ca.processing_status = 'processed'
  AND ca.section = 'guide'
  AND ca.category IS NOT NULL
  AND ca.word_count >= 500
  AND ca.country_id IS NOT NULL;


-- =============================================
-- 3. FAQ: Questions populaires pour articles FAQ
--    → Filtre les vrais questions (patterns interrogatifs)
--    → Exclut les sujets non-pertinents (bistrot, etc.)
--    → sub_category inclut le pays pour ne JAMAIS melanger les pays
-- =============================================
DELETE FROM generation_source_items WHERE category_slug = 'faq' AND source_type = 'question';

INSERT INTO generation_source_items (
  category_slug, source_type, source_id, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status
)
SELECT
  'faq',
  'question',
  q.id,
  q.title,
  q.country,
  q.country_slug,
  CASE
    WHEN q.title ~* '(comment|how|quand|when|pourquoi|why|combien|est-ce que|peut-on|faut-il|quel)' THEN 'question-directe'
    ELSE 'sujet-discussion'
  END,
  -- sub_category includes country to prevent cross-country grouping
  'faq-' || COALESCE(q.country_slug, 'global') || '-' ||
  CASE
    WHEN q.title ~* '(visa|permis|residence|sejour)' THEN 'visa'
    WHEN q.title ~* '(logement|louer|locat|appart|maison|immobilier)' THEN 'logement'
    WHEN q.title ~* '(sante|medecin|hopital|assurance|maladie)' THEN 'sante'
    WHEN q.title ~* '(travail|emploi|salaire|contrat|job)' THEN 'emploi'
    WHEN q.title ~* '(banque|compte|argent|transfert|change)' THEN 'banque'
    WHEN q.title ~* '(impot|taxe|fiscal|declaration)' THEN 'fiscalite'
    WHEN q.title ~* '(ecole|universite|etude|scolarite)' THEN 'education'
    WHEN q.title ~* '(transport|voiture|permis conduire|bus|train)' THEN 'transport'
    WHEN q.title ~* '(arnaque|vol|danger|securite|police)' THEN 'urgences'
    ELSE 'general'
  END,
  q.language,
  0,
  CASE
    WHEN q.views >= 300 AND q.replies >= 5 THEN 85
    ELSE 60
  END,
  true,
  'ready'
FROM content_questions q
WHERE q.views >= 200 AND q.replies >= 3
  AND q.title ~* '(comment|how|quand|when|pourquoi|why|combien|est-ce que|peut-on|faut-il|quel|aide|help|besoin|need|cherche|looking)'
  AND q.title !~* '(bistrot|rions|nouveaux membres|jeux|humour|blague)';


-- =============================================
-- 4. COMPARATIFS: Numbeo + CFE + donnees statistiques
--    → Uniquement les sources de donnees (pas les articles edito)
-- =============================================
DELETE FROM generation_source_items WHERE category_slug = 'comparatifs' AND source_type = 'article';

INSERT INTO generation_source_items (
  category_slug, source_type, source_id, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status
)
SELECT
  'comparatifs',
  'article',
  ca.id,
  ca.title,
  cc.name,
  cc.slug,
  COALESCE(ca.category, 'general'),
  'donnees-' || cs.slug || '-' || COALESCE(cc.slug, 'global'),
  ca.language,
  ca.word_count,
  75,
  true,
  'ready'
FROM content_articles ca
JOIN content_sources cs ON ca.source_id = cs.id
LEFT JOIN content_countries cc ON ca.country_id = cc.id
WHERE ca.processing_status = 'processed'
  AND cs.slug IN ('numbeo', 'cfe')
  AND ca.word_count >= 200;


-- =============================================
-- 5. LONGUES TRAINES: Opportunites non couvertes
--    → Issues de l'analyse de content gaps
-- =============================================
DELETE FROM generation_source_items WHERE category_slug = 'longues-traines' AND source_type = 'question';

INSERT INTO generation_source_items (
  category_slug, source_type, source_id, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status
)
SELECT
  'longues-traines',
  'question',
  co.question_id,
  co.question_title,
  co.country,
  co.country_slug,
  co.theme,
  co.theme || '-' || COALESCE(co.country_slug, 'global'),
  'fr',
  0,
  CASE
    WHEN co.priority_score >= 10000 THEN 95
    WHEN co.priority_score >= 5000 THEN 80
    ELSE 60
  END,
  true,
  'ready'
FROM content_opportunities co
WHERE co.status = 'opportunity' AND co.question_id IS NOT NULL;


-- =============================================
-- 6. AUDIENCES: Par profil d'audience
--    → sub_category INCLUT le pays pour ne JAMAIS melanger
--    → Chaque profil (retraites, familles, nomads...) est un segment distinct
-- =============================================
DELETE FROM generation_source_items WHERE category_slug = 'audiences' AND source_type = 'question';

INSERT INTO generation_source_items (
  category_slug, source_type, source_id, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status
)
SELECT
  'audiences',
  'question',
  q.id,
  q.title,
  q.country,
  q.country_slug,
  CASE
    WHEN q.title ~* '(retraite|retire|pension|senior)' THEN 'retraites'
    WHEN q.title ~* '(famille|enfant|child|conjoint|spouse)' THEN 'familles'
    WHEN q.title ~* '(digital nomad|freelance|remote)' THEN 'digital-nomads'
    WHEN q.title ~* '(entrepreneur|business|startup)' THEN 'entrepreneurs'
    WHEN q.title ~* '(pvt|working holiday)' THEN 'pvtistes'
    WHEN q.title ~* '(etudiant|student|universite|bourse)' THEN 'etudiants'
    ELSE 'general'
  END,
  -- sub_category = audience-{profile}-{country} to prevent cross-country grouping
  'audience-' ||
  CASE
    WHEN q.title ~* '(retraite|retire|pension)' THEN 'retraites'
    WHEN q.title ~* '(famille|enfant|child)' THEN 'familles'
    WHEN q.title ~* '(digital nomad|freelance|remote)' THEN 'nomads'
    WHEN q.title ~* '(entrepreneur|business|startup)' THEN 'entrepreneurs'
    WHEN q.title ~* '(pvt|working holiday)' THEN 'pvtistes'
    WHEN q.title ~* '(etudiant|student|universite|bourse)' THEN 'etudiants'
    ELSE 'general'
  END || '-' || COALESCE(q.country_slug, 'global'),
  q.language,
  0,
  70,
  true,
  'cleaned'
FROM content_questions q
WHERE q.views >= 100
  AND q.title ~* '(retraite|retire|pension|senior|famille|enfant|child|conjoint|digital nomad|freelance|remote|entrepreneur|business|startup|pvt|working holiday|etudiant|student|universite|bourse)';


-- =============================================
-- 7. URGENCES: Articles sur arnaques, vols, accidents, urgences
--    → Extraits des articles scrapes ayant un theme urgence/securite
--    → sub_category inclut le pays
-- =============================================
DELETE FROM generation_source_items WHERE category_slug = 'urgences' AND source_type = 'article';

INSERT INTO generation_source_items (
  category_slug, source_type, source_id, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status
)
SELECT
  'urgences',
  'article',
  ca.id,
  ca.title,
  cc.name,
  cc.slug,
  CASE
    WHEN ca.title ~* '(arnaque|scam|fraud|escroqu)' THEN 'arnaques'
    WHEN ca.title ~* '(vol|theft|cambriol|pickpocket)' THEN 'vols'
    WHEN ca.title ~* '(accident|urgence|emergency|hopital)' THEN 'accidents'
    WHEN ca.title ~* '(agress|violence|danger|insecurite)' THEN 'agressions'
    ELSE 'securite-generale'
  END,
  'urgences-' || COALESCE(cc.slug, 'global') || '-' ||
  CASE
    WHEN ca.title ~* '(arnaque|scam|fraud)' THEN 'arnaques'
    WHEN ca.title ~* '(vol|theft|cambriol)' THEN 'vols'
    ELSE 'general'
  END,
  ca.language,
  ca.word_count,
  CASE WHEN ca.word_count >= 800 THEN 80 ELSE 60 END,
  true,
  'ready'
FROM content_articles ca
LEFT JOIN content_countries cc ON ca.country_id = cc.id
WHERE ca.processing_status = 'processed'
  AND (
    ca.title ~* '(arnaque|scam|fraud|escroqu|vol|theft|cambriol|pickpocket|accident|urgence|emergency|agress|violence|danger|insecurite|police|securite|safety)'
    OR ca.category IN ('securite', 'urgences', 'safety')
  )
  AND ca.word_count >= 300;

-- Also add urgence-related questions from forums
INSERT INTO generation_source_items (
  category_slug, source_type, source_id, title, country, country_slug, theme, sub_category,
  language, word_count, quality_score, is_cleaned, processing_status
)
SELECT
  'urgences',
  'question',
  q.id,
  q.title,
  q.country,
  q.country_slug,
  CASE
    WHEN q.title ~* '(arnaque|scam|fraud|escroqu)' THEN 'arnaques'
    WHEN q.title ~* '(vol|theft|cambriol|pickpocket)' THEN 'vols'
    ELSE 'securite-generale'
  END,
  'urgences-' || COALESCE(q.country_slug, 'global'),
  q.language,
  0,
  CASE WHEN q.views >= 200 THEN 80 ELSE 60 END,
  true,
  'ready'
FROM content_questions q
WHERE q.views >= 100
  AND q.title ~* '(arnaque|scam|fraud|escroqu|vol|theft|cambriol|pickpocket|agress|danger|insecurite|urgence|emergency|police)'
  AND q.title !~* '(bistrot|rions|humour)';


-- =============================================
-- 8. TITRES VARIABLES: Templates de titres avec variables
--    → Gardes tels quels (inseres manuellement ou par import_templates.sql)
--    → On ne les re-genere PAS ici, uniquement les articles/questions
-- =============================================
-- (Titres variables sont geres par import_templates.sql — ne pas toucher ici)


-- =============================================
-- STATS de verification apres execution
-- =============================================
SELECT
  gsc.slug,
  gsc.name,
  COUNT(gsi.id) as total_items,
  COUNT(gsi.id) FILTER (WHERE gsi.source_type = 'pillar') as pillars,
  COUNT(gsi.id) FILTER (WHERE gsi.source_type = 'article') as articles,
  COUNT(gsi.id) FILTER (WHERE gsi.source_type = 'question') as questions,
  COUNT(gsi.id) FILTER (WHERE gsi.source_type = 'template') as templates,
  COUNT(DISTINCT gsi.country_slug) as countries,
  COUNT(DISTINCT gsi.theme) as themes
FROM generation_source_categories gsc
LEFT JOIN generation_source_items gsi ON gsi.category_slug = gsc.slug
GROUP BY gsc.slug, gsc.name, gsc.sort_order
ORDER BY gsc.sort_order;
