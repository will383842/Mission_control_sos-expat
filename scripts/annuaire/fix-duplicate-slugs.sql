-- Fix duplicate country_slug issues
-- US: 'tats-unis' (broken - missing É) → 'etats-unis'
-- SV: 'salvador' (incomplete) → 'el-salvador'
-- CF: 'republique-centrafricaine' (my new entry) → 'rep-centrafricaine' (majority)
-- MU: 'ile-maurice' (correct romanization) → 'maurice' (majority, keep consistent)

BEGIN;

-- 1. US: fix broken slug
UPDATE country_directory
SET country_slug = 'etats-unis'
WHERE country_code = 'US' AND country_slug = 'tats-unis';

-- 2. SV: align my new entry to the majority slug
UPDATE country_directory
SET country_slug = 'el-salvador'
WHERE country_code = 'SV' AND country_slug = 'salvador';

-- 3. CF: align my new ambassade entry to the majority slug
UPDATE country_directory
SET country_slug = 'rep-centrafricaine'
WHERE country_code = 'CF' AND country_slug = 'republique-centrafricaine';

-- 4. MU: standardize to 'ile-maurice' (correct romanization of Île Maurice)
UPDATE country_directory
SET country_slug = 'ile-maurice'
WHERE country_code = 'MU' AND country_slug = 'maurice';

-- Also fix country_name inconsistency for MU if any
UPDATE country_directory
SET country_name = 'Île Maurice'
WHERE country_code = 'MU' AND country_name != 'Île Maurice';

COMMIT;

-- Verify: all 4 countries should now have a single slug
SELECT country_code, country_name, COUNT(DISTINCT country_slug) as slug_count,
       STRING_AGG(DISTINCT country_slug, ' | ') as slugs,
       COUNT(DISTINCT category) as total_cats
FROM country_directory
WHERE country_code IN ('CF','MU','SV','US') AND is_active=true
GROUP BY country_code, country_name
ORDER BY country_code;

-- Check US now has all 13 categories
SELECT country_code, COUNT(DISTINCT category) as cats,
       STRING_AGG(DISTINCT category, ',' ORDER BY category) as categories
FROM country_directory
WHERE country_code = 'US' AND is_active=true
GROUP BY country_code;
