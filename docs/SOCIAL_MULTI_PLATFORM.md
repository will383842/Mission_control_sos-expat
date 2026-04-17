# Social Multi-Platform Republication — Mission Control SOS-Expat

> Refonte livrée le **2026-04-18** — Branche `feat/social-multi-platform` — PR #1

## Vue d'ensemble

L'outil de republication sociale Mission Control, historiquement 100% LinkedIn, est maintenant une **architecture multi-plateforme** qui pilote 4 réseaux sociaux depuis 1 dashboard, 1 queue, 1 workflow IA :

| Plateforme | Status | OAuth | Driver |
|-----------|--------|-------|--------|
| 💼 **LinkedIn** | ✅ 100% prod (port iso de l'existant) | OK | `LinkedInDriver` |
| 📘 **Facebook** | ✅ Code prod-ready | Bloqué Meta App Review | `FacebookDriver` |
| 🧵 **Threads** | ✅ Code prod-ready | Bloqué Meta App Review | `ThreadsDriver` |
| 📸 **Instagram** | ✅ Code prod-ready | Bloqué Meta App Review | `InstagramDriver` |

Pinterest et Reddit affichent une page "Non planifié" (placeholder dédié).

## Architecture

### Pattern Driver

```
SocialPublishingServiceInterface (15 méthodes)
       │
       ├── AbstractSocialDriver (helpers communs)
       │       ├── LinkedInDriver  (REST API v202601)
       │       ├── FacebookDriver  (Graph API v19)
       │       ├── ThreadsDriver   (2-step container, rate limit 250/24h)
       │       └── InstagramDriver (2-step container, image obligatoire)
       │
       └── SocialDriverManager (singleton, factory + registry)
```

### Stack backend

- **Tables** : `social_posts`, `social_tokens`, `social_post_comments` (PostgreSQL CHECK constraints)
- **API** : `/api/content-gen/social/{platform}/*` (15 endpoints) + `/api/social/{platform}/oauth/{authorize,callback}`
- **Middleware** : `EnsureValidPlatform` (404 unknown / 403 disabled / pass)
- **Jobs** : `GenerateSocialPostJob` + `PostSocialFirstCommentJob` avec queue routing per-platform
- **Commands** : 5 nouvelles `social:*` (auto-publish, fill-calendar, check-comments, check-tokens, backfill-from-linkedin)
- **Schedules** : 4 cron en parallèle de l'ancien `linkedin:*` (no regression)

### Stack frontend

- **Hook** : `useSocialPlatform(platform)` (stats, queue, OAuth status)
- **Pages** : page riche dédiée par plateforme, accessible via sub-menu sidebar
- **OAuth** : bouton "Connecter" dans `RepublicationRS.tsx` qui redirige vers `/api/social/{platform}/oauth/authorize`

## Capability matrix

| Capability | LinkedIn | Facebook | Threads | Instagram |
|-----------|----------|----------|---------|-----------|
| `supportsFirstComment` | ✓ | ✓ | ✗ (skip) | ✓ |
| `supportsHashtags` | ✓ | ✗ (faible signal) | ✓ | ✓ |
| `requiresImage` | ✗ | ✗ | ✗ | ✓ (refuse sinon) |
| `maxContentLength` | 3000 | 63206 | **500** (hard) | 2200 |
| `supportedAccountTypes` | personal, page | page | personal | business |

## Best practices 2026 par plateforme (encodées dans `SocialPromptBuilder`)

### 💼 LinkedIn
- Hook ≤ 140 chars, 1ère personne, tension immédiate
- Body 900-1400 chars, structure 4 actes (ANCRAGE → DOULEUR → INSIGHT → CTA)
- 3-5 hashtags ultra-niche (jamais `#expat #travel`)
- "SOS-Expat" **interdit** dans body — uniquement dans l'URL finale
- URL en TOUTE DERNIÈRE ligne après les hashtags

### 📘 Facebook (algo Meta 2026 conversation-first)
- Hook 50-80 chars (mobile feed coupe à ~80)
- Body 200-1000 chars, storytelling chaleureux
- 1-2 hashtags max (signal faible)
- CTA = **question ouverte** obligatoire (commentaires comptent ×5 vs likes)
- "SOS-Expat" OK 1 fois max (Page Pro)
- L'algo punit liens externes (-40% reach) — préférer storytelling complet

### 🧵 Threads
- **500 chars HARD CAP** (au-delà API rejette le post)
- Hook = 1ère phrase choc (hot take, observation, fait brut)
- 1-2 hashtags (searchable mais pas cliquables)
- Format conversation, **pas** d'éditorial, **pas** de listes
- URL en fin "Plus → [URL]" (compte les chars de l'URL dans la limite)

### 📸 Instagram (Business)
- Caption 138 chars visibles avant "more" → hook crucial
- Body 500-1500 chars, storytelling vertical, 1 phrase = 1 paragraphe
- 3-5 hashtags **ultra-niche** (Meta 2024 a réduit l'efficacité des génériques de 60%)
- Émojis libéraux (4-8 dans tout le post)
- CTA = QUESTION + invite save/share (saves = signal #1)
- "Lien en bio 👉" (URL non cliquable dans caption)
- `first_comment` 140-200 chars avec URL complète (postée 30s après)
- **IMAGE OBLIGATOIRE** (driver throw sinon)

## Sources réutilisées

Le système accepte les **mêmes 14 source_types** que LinkedIn pour toutes les plateformes :

**DB-sourced** (avec dédup par `(platform, source_id)`) :
- `article` → `GeneratedArticle::published()` ordonné par `editorial_score`
- `faq` → `QaEntry::published()` ordonné par `seo_score`
- `sondage` → `Sondage::active|closed` (chiffres réels obligatoires)

**Free-gen** (l'IA génère sans source DB, mais avec article lié pour image/keywords) :
`hot_take`, `myth`, `poll`, `serie`, `reactive`, `milestone`, `partner_story`, `counter_intuition`, `tip`, `news`, `case_study`

## Génération IA — pipeline

```
SocialPost (status=generating)
    │
    ├── 1. Source loading (article/faq/sondage from DB or empty for free types)
    │
    ├── 2. SocialPromptBuilder.build(driver, post, source, lang)
    │       → prompts adaptés best practices 2026 selon driver->platform()
    │
    ├── 3. Quality loop (max 3 attempts)
    │       │
    │       ├── 3a. GPT-4o (level 1)
    │       ├── 3b. Claude Haiku 4.5 (level 2 fallback)
    │       └── 3c. Both unavailable → status='failed' (jamais de template)
    │
    │       Score 0-100 par plateforme :
    │         - hook length (LI=140, FB=80, Threads=80, IG=138)
    │         - body length (LI=900-1700, FB=200-1000, Threads=50-500, IG=400-2200)
    │         - hashtag count (LI/IG=3-5, FB/Threads=1-2)
    │         - voice/tone, brand pollution, commercial cliches
    │
    │       Si score < 80 : critique fed back to AI pour round suivant
    │       Si score < 50 après 3 rounds : status='draft' + Telegram alert
    │
    ├── 4. Image search Unsplash (mandatory si driver->requiresImage())
    │
    └── 5. Save → status='scheduled', auto_scheduled=true
```

## Telegram bot routing per-platform

Constructor `TelegramAlertService(string $bot)` route via match expression :
- `'alerts'` → `services.telegram_alerts`
- `'linkedin'` → `services.telegram_linkedin`
- `'facebook'` → `services.telegram_facebook`
- `'threads'` → `services.telegram_threads`
- `'instagram'` → `services.telegram_instagram`

Fallback Elvis (`?:`) vers alerts si platform-specific token null.

## Cutover procédure (production)

L'ancien pipeline LinkedIn (`LinkedInController`, `GenerateLinkedInPostJob`, `linkedin_*` tables) **continue de tourner en parallèle** du nouveau pendant la transition.

Bascule définitive :

```bash
# 1. Sur le VPS
ssh root@<VPS>
cd /opt/mission-control-laravel
git pull origin master

# 2. Migration DB
php artisan migrate              # crée social_*

# 3. Backfill données legacy
php artisan social:backfill-from-linkedin --dry-run    # vérifier
php artisan social:backfill-from-linkedin              # exécuter (idempotent)

# 4. Caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 5. Restart workers
supervisorctl restart all
```

## Activer Facebook / Instagram / Threads (post Meta App Review)

1. Créer **2 Meta Developer Apps** sur https://developers.facebook.com/apps/ :
   - 1 app pour **Facebook + Instagram** (mêmes scopes Meta)
   - 1 app séparée pour **Threads** (OAuth distinct)

2. Demander les scopes :
   - **Meta (FB+IG)** : `pages_manage_posts`, `pages_read_engagement`, `pages_manage_engagement`, `instagram_content_publish`, `instagram_manage_comments`
   - **Threads** : `threads_basic`, `threads_content_publish`, `threads_manage_replies`

3. Délai approval Meta : **1-2 semaines**

4. Ajouter au `.env` du VPS :
   ```env
   FACEBOOK_CLIENT_ID=...
   FACEBOOK_CLIENT_SECRET=...
   FACEBOOK_PAGE_ID=...                    # ID Page Pro SOS-Expat
   INSTAGRAM_BUSINESS_ACCOUNT_ID=...       # IG Business lié à la Page FB
   THREADS_CLIENT_ID=...
   THREADS_CLIENT_SECRET=...
   SOCIAL_FACEBOOK_ENABLED=true
   SOCIAL_INSTAGRAM_ENABLED=true
   SOCIAL_THREADS_ENABLED=true
   ```

5. Aller sur `/content/republication-rs/{platform}` → bouton "Connecter" → flow OAuth

## Tests automatisés (152 verts au total)

| Catégorie | Score |
|-----------|-------|
| Lint PHP | 30/30 |
| Class resolution (container) | 24/24 |
| Interface conformance (15 méthodes × 4 drivers) | 4/4 |
| Migration ↔ Model alignment | 6/6 |
| Routes (17 social + 18 linkedin préservées) | 2/2 |
| Commands enregistrées | 9/9 |
| Schedules cron | 2/2 |
| Queue routing per-platform | 8/8 |
| Telegram bot routing + fallback | 6/6 |
| Capability matrix | 20/20 |
| IDOR-safe (forPlatform scope) | 3/3 |
| Singleton SocialDriverManager | 1/1 |
| Middleware (404/403/pass) | 3/3 |
| Iso-separation (no LinkedIn imports) | 17/17 |
| Encryption AES roundtrip | 4/4 |
| Token edge cases | 4/4 |
| Callback prefix collision-safe | 4/4 |
| OAuth URL generation per platform | 6/6 |
| Stub graceful (FB publish via mock) | 2/2 |
| PromptBuilder 4 platforms | 4/4 |

## Commandes utiles

```bash
# Voir les commandes social disponibles
php artisan list | grep social:

# Calendrier éditorial 30 jours
php artisan social:fill-calendar --platform=facebook --days=30
php artisan social:fill-calendar --dry-run         # toutes plateformes en dry-run

# Auto-publish manuel (cron toutes les 5 min)
php artisan social:auto-publish --platform=linkedin
php artisan social:auto-publish --dry-run

# Health check tokens (cron quotidien 08:05)
php artisan social:check-tokens

# Sync commentaires (cron toutes les 15 min)
php artisan social:check-comments --platform=facebook

# Backfill cutover
php artisan social:backfill-from-linkedin --dry-run
php artisan social:backfill-from-linkedin --only=tokens
php artisan social:backfill-from-linkedin --chunk=100
```

## Points de vigilance

- **`SocialToken::lookup()`** (pas `find()`) pour éviter de shadow Eloquent's `Model::find($id)`
- **`(string) config()`** obligatoire dans `TelegramAlertService` pour éviter TypeError si env vide
- **Queue routing** : le platform DOIT être passé au constructor du Job (pas juste dans handle), car `onQueue()` se fait au moment du dispatch
- **Threads 500 chars** : limite hard, l'API rejette au-delà — le sanitizer + score le surveille
- **Instagram image** : `requiresImage()` → throw RuntimeException si manquante (fail explicite plutôt que silent)
- **Iso-separation** : les nouveaux fichiers `Social*` n'importent JAMAIS de classes legacy `LinkedIn*` (sauf `BackfillSocialFromLinkedInCommand` qui en a besoin par design)
