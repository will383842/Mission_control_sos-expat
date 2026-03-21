# AUDIT COMPLET — Prospects CRM SOS-Expat (mars 2026)

## CONTEXTE

Tu es un panel de **20 experts mondiaux de premier plan**, réunis en brainstorming intensif. Chaque expert analyse l'outil depuis son domaine d'expertise unique. Vous devez produire un audit exhaustif, impitoyable et constructif.

### Les 20 experts

1. **Sarah Chen** — CRM Product Architect (ex-Salesforce, HubSpot) — Design de CRM scalables
2. **Marcus Weber** — Growth Hacking & Outreach Automation (ex-Lemlist, Apollo.io)
3. **Amina Diallo** — UX/UI Design Lead (ex-Notion, Linear) — Interfaces dark-mode, productivité
4. **David Kim** — Full-Stack Laravel Architect (top 1% Laravel contributors)
5. **Elena Petrova** — React Performance Engineer (ex-Meta, Vercel)
6. **James O'Brien** — PostgreSQL & Database Optimization Expert
7. **Fatou Sow** — Expat Community Manager & Partnership Specialist (10+ ans expatriation)
8. **Thomas Müller** — DevOps & Docker Infrastructure (ex-GitLab SRE)
9. **Priya Sharma** — Data Analytics & Business Intelligence (ex-Tableau)
10. **Carlos Rodriguez** — Sales Pipeline & B2B Outreach Strategist
11. **Yuki Tanaka** — API Design & RESTful Architecture Specialist
12. **Sophie Laurent** — RGPD/GDPR & Legal Compliance Expert
13. **Ahmed Hassan** — Search & Filtering Optimization (ex-Algolia, Elasticsearch)
14. **Lisa Chang** — Mobile-First & PWA Specialist
15. **Roberto Silva** — Team Management & Researcher Workflow Optimization
16. **Nadia Kowalski** — Email/WhatsApp Outreach Automation & Templates
17. **Michael Thompson** — Security & Authentication (OWASP, pentest)
18. **Chiara Rossi** — Internationalization (i18n) & Multi-Market Scaling
19. **Alexandre Dupont** — SEO & Content Partnership Strategy pour marketplaces de services
20. **Olga Ivanova** — AI/ML Integration pour CRM (scoring, prédiction, automatisation)

---

## L'OUTIL À ANALYSER

### Mission de l'outil
**Prospects CRM** est un outil interne développé pour **SOS-Expat.com** — une plateforme de mise en relation entre expatriés et prestataires de services (avocats, comptables, assureurs, etc.) dans le monde entier.

L'outil sert à **prospecter, contacter et suivre** 6 types de contacts :
- **Influenceurs** — créateurs de contenu (Instagram, TikTok, YouTube, etc.) pour promouvoir SOS-Expat
- **Admins de groupes** — administrateurs de groupes Facebook/WhatsApp/Telegram d'expatriés
- **Bloggeurs** — blogueurs expatriation qui intègrent un widget SOS-Expat sur leur site
- **Partenaires** — entreprises B2B (assureurs, banques, déménageurs) pour partenariats commerciaux
- **Écoles** — écoles internationales, universités, centres de formation
- **Associations** — associations d'expatriés, chambres de commerce, clubs

**L'objectif business** : recruter massivement ces 6 types de contacts à travers le monde pour développer la visibilité et le réseau commercial de SOS-Expat.

### Qui utilise l'outil ?
- **Admin** (le fondateur) — vue globale, gestion des chercheurs, objectifs, stats
- **Chercheurs (researchers)** — freelances payés pour trouver et contacter des prospects dans des pays/langues/niches spécifiques. Chaque chercheur peut être assigné à un ou plusieurs types de contacts.
- **Membres (members)** — collaborateurs internes avec accès lecture/écriture mais pas d'admin

### Stack technique
- **Backend** : Laravel 12, PHP 8.2+, PostgreSQL 16, Redis 7
- **Frontend** : React 18, TypeScript, Vite, Tailwind CSS (dark theme)
- **Auth** : Laravel Sanctum (cookie-based SPA auth)
- **Infra** : Docker Compose (6 containers : app, postgres, redis, nginx-api, frontend, scheduler, queue)
- **Déploiement** : Hetzner VPS, GitHub Actions CI/CD
- **Pagination** : Cursor-based (30/page, infinite scroll)

---

## ARCHITECTURE COMPLÈTE

### Base de données (PostgreSQL)

**Table `influenceurs`** (la table principale, mal nommée historiquement — contient TOUS les types de contacts) :
- `id`, `contact_type` (influenceur/group_admin/blogger/partner/school/association, default 'influenceur')
- `name`, `handle`, `avatar_url`
- `platforms` (JSON array), `primary_platform` — optionnels pour partner/school/association
- `followers`, `followers_secondary` (JSON)
- `niche`, `country`, `language` (code 2 lettres)
- `email`, `phone`, `profile_url`, `profile_url_domain` (indexé, pour détection doublons)
- `status` (prospect/contacted/negotiating/active/refused/inactive)
- `assigned_to` (FK users), `created_by` (FK users)
- `reminder_days` (default 7), `reminder_active` (boolean)
- `last_contact_at`, `partnership_date`, `notes`, `tags` (JSON)
- Indexes : contact_type, assigned_to, created_by, status, primary_platform, last_contact_at, profile_url_domain, [status + reminder_active]
- Soft deletes

**Table `contacts`** (historique des prises de contact / timeline) :
- `id`, `influenceur_id` (FK cascade), `user_id` (FK nullable)
- `date`, `channel` (email/instagram/linkedin/whatsapp/phone/other)
- `result` (sent/replied/refused/registered/no_answer)
- `sender`, `message`, `reply`, `notes`
- Le **rang** (1er contact, 2ème, 3ème...) est calculé côté API à la lecture (index+1)

**Table `objectives`** (objectifs assignés aux chercheurs par l'admin) :
- `id`, `user_id` (FK), `contact_type` (nullable = tous les types)
- `continent`, `countries` (JSONB array), `language`, `niche`
- `target_count`, `deadline`, `is_active`
- `created_by` (FK)

**Table `users`** :
- `id`, `name`, `email`, `password`, `role` (admin/member/researcher)
- `contact_types` (JSONB nullable — types assignés au chercheur, null = tous)
- `is_active`, `last_login_at`

**Table `reminders`** :
- `id`, `influenceur_id` (FK), `due_date`, `status` (pending/dismissed/done)
- `dismissed_by`, `dismissed_at`, `notes`

**Table `activity_logs`** :
- `id`, `user_id`, `influenceur_id`, `action`, `details` (JSON), `created_at`

### Backend Laravel — Controllers

**InfluenceurController** (~370 lignes) :
- `index()` : Cursor pagination, researcher scoping (created_by + contact_types auto-filter), filtres : contact_type, status, platform, assigned_to, has_reminder, country, language, search (name/handle LIKE)
- `store()` : Validation, platforms optionnels, researcher type authorization, duplicate detection via profile_url_domain (normalisation intelligente TikTok/YouTube/Instagram/LinkedIn/Facebook/X), activity log
- `update()` : Auto-set partnership_date on status→active, domain re-extraction on URL change
- `destroy()` : Admin any, researcher own, member denied
- `remindersPending()` : Influenceurs avec rappels pending, sorted by days_elapsed
- `normalizeProfileUrl()` : Extraction intelligente username/channel depuis URL vidéo/post (TikTok @user, YouTube @channel, Instagram username, etc.)

**ContactController** (~120 lignes) :
- CRUD contacts par influenceur, validation date ≤ today, activity logging
- Researcher scoping via created_by sur influenceur parent

**ObjectiveController** (~185 lignes) :
- CRUD objectives (admin only pour write)
- `progress()` : Calcule current_count par objectif via query Influenceur.validForObjective() + filtres (contact_type, countries, language, niche), retourne percentage + days_remaining

**StatsController** (~420 lignes) :
- `index()` : total, byStatus, byContactType, responseRate, conversionRate, newThisMonth, contactsEvolution (12 semaines), byPlatform, responseByPlatform, teamActivity, funnel, recentActivity
- `researcherStats()` : Par chercheur — total_created, valid_count, objectives avec progression
- `coverage()` : Par pays, langue, continent (300+ mappings pays→continent FR+EN)

**TeamController** (~80 lignes) :
- CRUD users, validation contact_types (JSON array), prevent last admin deactivation

**Routes API** : Sanctum protected, middleware role:admin sur écriture objectives/team/exports/stats avancés, throttle sur login (6/min) et exports (10/min)

### Frontend React — Pages

**Dashboard** (admin/member) :
- 6 KPIs status bars, taux réponse/conversion/nouveaux ce mois
- Section "Par type de contact" avec badges + compteurs
- Couverture mondiale (admin only) : pays/langues/continents couverts, progress bar mondiale, tabs par continent/pays/langue avec barres + top 5
- Derniers rappels + activité récente

**ResearcherDashboard** :
- Cercle SVG progression globale (current/target)
- Cards objectifs avec progress bars colorées (vert ≥80%, amber ≥50%, rouge <50% + ≤3j)
- Critères de validation (4 étapes)
- Derniers 10 ajouts avec statut validation (valid/manquant)

**Contacts (ex-Influenceurs)** :
- Vue cards ou tableau, toggle
- FilterSidebar : type de contact, statut, plateforme, pays, langue, assigné à, rappels
- Formulaire création : type de contact (6 options), nom, handle, email, phone, followers, pays, langue, niche, plateformes (conditionnel si influenceur/blogger/group_admin), URL profil, statut, notes
- Infinite scroll (30/page cursor-based)
- Export CSV/Excel (admin only)

**ContactDetail** :
- Fiche complète avec édition inline
- Badge type de contact + plateforme + statut
- Infos : email, phone, pays, langue, niche, assignation, rappels auto
- Timeline des contacts (numérotée, canal emoji, résultat coloré, message/réponse/notes)
- Formulaire ajout contact (date, canal, résultat, expéditeur, message, réponse, notes)

**AdminConsole** :
- Liste chercheurs avec stats (total créés, valides, ratio)
- Objectifs par chercheur : type de contact, continent/pays, langue, niche, cible, progression, deadline
- Formulaire création objectif : type, continent (avec country picker multi-select par continent), langue, niche, quantité, deadline
- Résumé global + info doublons

**Équipe** :
- CRUD membres, rôle (admin/member/researcher)
- Multi-select types de contacts assignés (checkboxes) pour les chercheurs
- Badges types assignés dans le tableau

**À Relancer** :
- Liste rappels pending avec nom, statut, plateforme, jours écoulés, assignation
- Actions : ajouter relance, reporter (avec note), marquer traité

**Statistiques** :
- KPIs : total, taux réponse, taux conversion, actifs
- Charts Recharts : évolution contacts 12 semaines (AreaChart), répartition statuts (PieChart), contacts par plateforme (BarChart), taux réponse par plateforme, funnel conversion, activité équipe

### Infrastructure Docker
- 6 containers : postgres, redis, app (PHP-FPM), nginx-api (reverse proxy), frontend (nginx static), scheduler (cron), queue (Redis queue worker)
- Healthchecks sur tous les containers
- Volumes persistants : postgres_data, redis_data, app_storage, public_shared
- CI/CD : GitHub Actions → SSH deploy sur Hetzner VPS

---

## VOTRE MISSION

Chaque expert doit analyser l'outil **EN PROFONDEUR** depuis son domaine d'expertise. Produisez :

### 1. POINTS POSITIFS (ce qui est bien fait)
Pour chaque point, citez le fichier/composant concerné et expliquez POURQUOI c'est bien.

### 2. POINTS NÉGATIFS / FAIBLESSES (ce qui pose problème)
Pour chaque point :
- Quel est le problème exact ?
- Quel impact concret sur l'utilisation/performance/sécurité/scalabilité ?
- Quelle est la sévérité ? (critique / majeur / mineur)

### 3. RECOMMANDATIONS DÉTAILLÉES
Pour chaque recommandation :
- Quoi faire exactement (description technique précise)
- Pourquoi (quel problème ça résout, quel bénéfice concret)
- Priorité (P0 = urgent, P1 = important, P2 = souhaitable, P3 = nice-to-have)
- Effort estimé (XS = quelques heures, S = 1 jour, M = 2-3 jours, L = 1 semaine, XL = 2+ semaines)
- Impact business (faible / moyen / fort / critique)

### 4. FONCTIONNALITÉS MANQUANTES ESSENTIELLES
Listez les fonctionnalités que tout CRM de prospection professionnel en 2026 DOIT avoir et qui manquent ici. Classez-les par priorité.

### 5. PLAN D'ACTION PRIORISÉ
Produisez un plan d'action en 5 sprints (2 semaines chacun) avec les améliorations les plus impactantes en premier.

---

## AXES D'ANALYSE OBLIGATOIRES

Chaque expert DOIT couvrir ces points dans son domaine :

### Architecture & Scalabilité
- La table `influenceurs` est-elle bien nommée/structurée pour 6 types de contacts ?
- Le modèle de données scale-t-il à 100K+ contacts ? 1M+ ?
- Le cursor-based pagination est-il optimal ?
- La détection de doublons par URL normalisée est-elle robuste ?
- Le scope `validForObjective()` avec ses queries par objectif est-il performant ?

### UX/UI & Productivité
- Le flow prospect → 1er contact → 2ème → 3ème est-il fluide ?
- La FilterSidebar est-elle efficace pour trouver rapidement ?
- Le formulaire de création est-il trop long ? Trop court ?
- Le researcher dashboard motive-t-il les chercheurs ?
- La navigation entre types de contacts est-elle intuitive ?

### Outreach & Sales Pipeline
- Les 6 statuts (prospect→contacted→negotiating→active→refused→inactive) couvrent-ils tous les cas ?
- Manque-t-il des statuts ? (ex: "pending_response", "warm_lead", "lost")
- Le système de rappels automatiques est-il suffisant ?
- Comment optimiser le taux de conversion prospect→active ?

### Spécifique SOS-Expat
- L'outil est-il adapté aux VRAIS besoins de prospection de SOS-Expat ?
- Les 6 types de contacts correspondent-ils à la réalité terrain ?
- Comment tracker le ROI de chaque contact recruté (combien d'appels/clients il apporte) ?
- L'outil s'intègre-t-il avec le reste de l'écosystème SOS-Expat (Firebase, Telegram Engine, etc.) ?

### Sécurité & RGPD
- L'auth Sanctum est-elle suffisante ?
- Le RBAC (admin/member/researcher) est-il complet ?
- Quid du consentement RGPD pour stocker emails/téléphones de prospects ?
- Les données personnelles sont-elles correctement protégées ?

### Performance & Monitoring
- Y a-t-il des N+1 queries ?
- Le StatsController avec ses multiples COUNT est-il performant ?
- Manque-t-il du caching (Redis est là mais est-il utilisé) ?
- Y a-t-il du monitoring/alerting ?

### Automatisation & AI (2026)
- Quelles tâches pourraient être automatisées ?
- Où l'IA pourrait-elle aider ? (scoring prospects, génération messages, détection doublons, enrichissement données)
- Le scraping de contacts est-il envisageable ?

### Mobile & Accessibilité
- L'outil est-il vraiment utilisable sur mobile ?
- Le responsive est-il complet ?
- Faut-il une PWA ?

---

## FORMAT DE SORTIE ATTENDU

Structurez votre réponse ainsi :

```
## SYNTHÈSE EXÉCUTIVE (10 lignes max)

## ANALYSE PAR EXPERT

### Expert 1 : [Nom] — [Domaine]
**Points positifs :**
- ...

**Points négatifs :**
- ...

**Recommandations :**
| # | Quoi | Pourquoi | Priorité | Effort | Impact |
|---|------|----------|----------|--------|--------|

[Répéter pour les 20 experts]

## FONCTIONNALITÉS MANQUANTES (classées par priorité)

## PLAN D'ACTION — 5 SPRINTS

### Sprint 1 (semaines 1-2) : [Thème]
- [ ] Tâche 1
- [ ] Tâche 2
...

### Sprint 2 (semaines 3-4) : [Thème]
...
```

---

## RAPPEL IMPORTANT

- Soyez **impitoyables mais constructifs** — l'objectif est de faire de cet outil le meilleur CRM de prospection possible pour SOS-Expat
- Donnez des recommandations **concrètes et actionnables**, pas des généralités
- Pensez **best practices 2026** : IA intégrée, automatisation, mobile-first, real-time
- Cet outil doit pouvoir gérer **des dizaines de chercheurs** prospectant **dans 195 pays** en parallèle
- Le budget est **limité** (1 développeur, VPS Hetzner) — priorisez le ROI
- L'outil doit rester **simple** pour des chercheurs freelance non-techniques
