-- =============================================
-- 3. FICHES PRATIQUES: Guides pratiques par pays
-- =============================================
INSERT INTO generation_source_items (category_slug, source_type, source_id, title, country, country_slug, theme, sub_category, language, word_count, quality_score, is_cleaned, processing_status)
SELECT
  'fiches-pratiques', 'article', ca.id, ca.title, cc.name, cc.slug,
  COALESCE(ca.category, 'general'),
  COALESCE(ca.category, 'general') || '-' || COALESCE(cc.slug, 'global'),
  ca.language, ca.word_count,
  CASE WHEN ca.word_count >= 1500 THEN 90 WHEN ca.word_count >= 800 THEN 70 ELSE 50 END,
  true, 'ready'
FROM content_articles ca
LEFT JOIN content_countries cc ON ca.country_id = cc.id
WHERE ca.processing_status = 'processed' AND ca.section = 'guide'
  AND ca.category IS NOT NULL AND ca.word_count >= 500 AND ca.country_id IS NOT NULL;

-- =============================================
-- 4. FAQ: Questions populaires pour articles FAQ
-- =============================================
INSERT INTO generation_source_items (category_slug, source_type, source_id, title, country, country_slug, theme, sub_category, language, word_count, quality_score, is_cleaned, processing_status)
SELECT
  'faq', 'question', q.id, q.title, q.country, q.country_slug,
  CASE
    WHEN q.title ~* '(comment|how|quand|when|pourquoi|why|combien|est-ce que|peut-on|faut-il|quel)' THEN 'question-directe'
    ELSE 'sujet-discussion'
  END,
  'faq-' || COALESCE(q.country_slug, 'global'),
  q.language, 0,
  CASE WHEN q.views >= 300 AND q.replies >= 5 THEN 85 ELSE 60 END,
  true, 'ready'
FROM content_questions q
WHERE q.views >= 200 AND q.replies >= 3
  AND q.title ~* '(comment|how|quand|when|pourquoi|why|combien|est-ce que|peut-on|faut-il|quel|aide|help|besoin|need|cherche|looking)'
  AND q.title !~* '(bistrot|rions|nouveaux membres)';

-- =============================================
-- 5. COMPARATIFS: Numbeo + CFE
-- =============================================
INSERT INTO generation_source_items (category_slug, source_type, source_id, title, country, country_slug, theme, sub_category, language, word_count, quality_score, is_cleaned, processing_status)
SELECT
  'comparatifs', 'article', ca.id, ca.title, cc.name, cc.slug,
  COALESCE(ca.category, 'general'), 'donnees-' || cs.slug,
  ca.language, ca.word_count, 75, true, 'ready'
FROM content_articles ca
JOIN content_sources cs ON ca.source_id = cs.id
LEFT JOIN content_countries cc ON ca.country_id = cc.id
WHERE ca.processing_status = 'processed' AND cs.slug IN ('numbeo', 'cfe') AND ca.word_count >= 200;

-- =============================================
-- 6. LONGUES TRAINES: Opportunites non couvertes
-- =============================================
INSERT INTO generation_source_items (category_slug, source_type, source_id, title, country, country_slug, theme, sub_category, language, word_count, quality_score, is_cleaned, processing_status)
SELECT
  'longues-traines', 'question', co.question_id, co.question_title, co.country, co.country_slug,
  co.theme, co.theme || '-' || COALESCE(co.country_slug, 'global'),
  'fr', 0,
  CASE WHEN co.priority_score >= 10000 THEN 95 WHEN co.priority_score >= 5000 THEN 80 ELSE 60 END,
  true, 'ready'
FROM content_opportunities co
WHERE co.status = 'opportunity' AND co.question_id IS NOT NULL;

-- =============================================
-- 7. AUDIENCES: Par profil
-- =============================================
INSERT INTO generation_source_items (category_slug, source_type, source_id, title, country, country_slug, theme, sub_category, language, word_count, quality_score, is_cleaned, processing_status)
SELECT
  'audiences', 'question', q.id, q.title, q.country, q.country_slug,
  CASE
    WHEN q.title ~* '(retraite|retire|pension|senior)' THEN 'retraites'
    WHEN q.title ~* '(famille|enfant|child|conjoint|spouse)' THEN 'familles'
    WHEN q.title ~* '(digital nomad|freelance|remote)' THEN 'digital-nomads'
    WHEN q.title ~* '(entrepreneur|business|startup)' THEN 'entrepreneurs'
    WHEN q.title ~* '(pvt|working holiday)' THEN 'pvtistes'
    ELSE 'general'
  END,
  CASE
    WHEN q.title ~* '(retraite|retire|pension)' THEN 'audience-retraites'
    WHEN q.title ~* '(famille|enfant|child)' THEN 'audience-familles'
    WHEN q.title ~* '(digital nomad|freelance|remote)' THEN 'audience-nomads'
    WHEN q.title ~* '(entrepreneur|business|startup)' THEN 'audience-entrepreneurs'
    WHEN q.title ~* '(pvt|working holiday)' THEN 'audience-pvtistes'
    ELSE 'audience-general'
  END,
  q.language, 0, 70, true, 'cleaned'
FROM content_questions q
WHERE q.views >= 100
  AND q.title ~* '(retraite|retire|pension|senior|famille|enfant|child|conjoint|digital nomad|freelance|remote|entrepreneur|business|startup|pvt|working holiday)';

-- =============================================
-- 8. TITRES AVEC VARIABLES
-- =============================================
INSERT INTO generation_source_items (category_slug, source_type, title, theme, sub_category, language, quality_score, is_cleaned, processing_status, data_json) VALUES
  ('titres-variables', 'template', 'Vivre en {pays} : guide complet pour les expatries', 'general', 'guide-pays', 'fr', 95, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Travailler en {pays} : emploi, salaire et marche du travail', 'emploi', 'guide-emploi', 'fr', 95, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Se loger en {pays} : location, achat et conseils pratiques', 'logement', 'guide-logement', 'fr', 95, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Visa {pays} : demarches, types et conditions', 'visa', 'guide-visa', 'fr', 95, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Systeme de sante en {pays} : assurance, medecins et hopitaux', 'sante', 'guide-sante', 'fr', 95, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Ouvrir un compte bancaire en {pays} : guide pratique', 'banque', 'guide-banque', 'fr', 95, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Cout de la vie en {pays} vs France : comparatif detaille', 'cout_vie', 'comparatif', 'fr', 90, true, 'ready', '{"variables": ["pays"], "type": "comparatif"}'),
  ('titres-variables', 'template', 'Scolarite en {pays} : ecoles, universites et systeme educatif', 'education', 'guide-education', 'fr', 90, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Transports en {pays} : voiture, bus, train et alternatives', 'transport', 'guide-transport', 'fr', 85, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Internet et telephone en {pays} : forfaits et operateurs', 'telecom', 'guide-telecom', 'fr', 85, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Impots en {pays} : fiscalite pour les expatries francais', 'fiscalite', 'guide-fiscalite', 'fr', 90, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Retraite en {pays} : pension, cout de la vie et formalites', 'retraite', 'guide-retraite', 'fr', 90, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', '{pays} vs {pays2} : ou s expatrier ? Comparatif complet', 'general', 'comparatif-pays', 'fr', 90, true, 'ready', '{"variables": ["pays", "pays2"], "type": "comparatif"}'),
  ('titres-variables', 'template', 'Top 10 des villes pour s expatrier en {pays}', 'general', 'top-villes', 'fr', 85, true, 'ready', '{"variables": ["pays"], "type": "listicle"}'),
  ('titres-variables', 'template', 'Demarches administratives pour s installer en {pays}', 'demarches', 'guide-demarches', 'fr', 90, true, 'ready', '{"variables": ["pays"], "type": "guide"}'),
  ('titres-variables', 'template', 'Digital nomad en {pays} : visa, coworking et vie quotidienne', 'general', 'audience-nomads', 'fr', 85, true, 'ready', '{"variables": ["pays"], "type": "guide", "audience": "digital-nomads"}'),
  ('titres-variables', 'template', 'PVT {pays} : conditions, demarches et budget', 'visa', 'audience-pvt', 'fr', 85, true, 'ready', '{"variables": ["pays"], "type": "guide", "audience": "pvtistes"}'),
  ('titres-variables', 'template', 'Creer une entreprise en {pays} : statuts, fiscalite et couts', 'emploi', 'audience-entrepreneurs', 'fr', 85, true, 'ready', '{"variables": ["pays"], "type": "guide", "audience": "entrepreneurs"}'),
  ('titres-variables', 'template', 'Etudier en {pays} : universites, bourses et vie etudiante', 'education', 'audience-etudiants', 'fr', 85, true, 'ready', '{"variables": ["pays"], "type": "guide", "audience": "etudiants"}');
