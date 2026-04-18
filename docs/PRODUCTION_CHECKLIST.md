# Checklist finale — Activer Facebook / Threads / Instagram / Pinterest

> Tout le code est déjà déployé en production sur le VPS Hetzner.
> Cette checklist liste les **3 actions externes** qui restent (Meta Apps + Pinterest App + .env).

---

## Étape 1 — Créer 1 app Meta (Facebook + Threads + Instagram)

### Pourquoi 1 seule app ?
Meta possède Facebook, Threads et Instagram. Tu peux créer **1 SEULE Meta Developer App** et y activer 3 produits différents. Ça te fait **1 seule paire de credentials** (`FACEBOOK_CLIENT_ID` + `FACEBOOK_CLIENT_SECRET`) qui sert pour les 3 plateformes.

### Marche à suivre

1. Va sur https://developers.facebook.com/apps/
2. Bouton **"Create App"**
3. Type : **"Business"** (pour publier sur des Pages)
4. Nom : `SOS-Expat Social` (ou ce que tu veux)
5. Une fois créée, va dans **"App Settings → Basic"** et note :
   - **App ID** → ce sera ton `FACEBOOK_CLIENT_ID`
   - **App Secret** → ce sera ton `FACEBOOK_CLIENT_SECRET`
6. Dans la section **"Add Products"** (sidebar gauche), active **3 produits** :
   - ✅ **Facebook Login for Business**
   - ✅ **Instagram API with Instagram Login** (PAS l'ancien "Instagram Basic Display")
   - ✅ **Threads API**

### Configuration OAuth Redirect URIs

Pour chaque produit, ajoute dans les "Valid OAuth Redirect URIs" :

```
https://<TON_DOMAINE_PROD>/api/social/facebook/oauth/callback
https://<TON_DOMAINE_PROD>/api/social/instagram/oauth/callback
https://<TON_DOMAINE_PROD>/api/social/threads/oauth/callback
```

Remplace `<TON_DOMAINE_PROD>` par le domaine où tourne Mission Control.

### Demander les scopes (App Review)

Dans **"App Review → Permissions and Features"**, demande :

**Pour Facebook Pages** :
- `pages_show_list`
- `pages_read_engagement`
- `pages_manage_posts`
- `pages_manage_engagement`
- `pages_read_user_content`

**Pour Instagram** :
- `instagram_basic`
- `instagram_content_publish`
- `instagram_manage_comments`

**Pour Threads** :
- `threads_basic`
- `threads_content_publish`
- `threads_manage_replies`
- `threads_read_replies`

### Pour la review, Meta demande :

1. Une **vidéo démo** (≤ 5 min) qui montre :
   - Comment l'utilisateur arrive sur Mission Control
   - Le bouton "Connecter Facebook"
   - Le flow OAuth (consent screen Meta)
   - Comment l'utilisateur publie un post via ton outil
2. Une **Privacy Policy URL** publique (par exemple `https://sos-expat.com/privacy`)
3. Une **Terms of Service URL**
4. Une **Data Deletion URL** (instructions pour supprimer ses données)

**Délai** : entre 3 jours et 2 semaines selon la charge de Meta.

### Récupérer le Page ID + Instagram Business Account ID

Une fois l'app approuvée :

1. **FACEBOOK_PAGE_ID** : va sur ta Page Pro Facebook → "À propos" → tout en bas tu vois "Identifiant de la Page"
2. **INSTAGRAM_BUSINESS_ACCOUNT_ID** : ton compte Instagram doit être **converti en compte Business** ET **lié à ta Page Facebook**. L'ID s'obtient via :
   ```
   GET https://graph.facebook.com/v19.0/{FACEBOOK_PAGE_ID}?fields=instagram_business_account&access_token=...
   ```
   Ou plus simplement : Mission Control le récupère AUTOMATIQUEMENT lors du premier OAuth Instagram. Tu peux laisser `INSTAGRAM_BUSINESS_ACCOUNT_ID` vide.

---

## Étape 2 — Créer 1 app Pinterest

### Marche à suivre

1. Va sur https://developers.pinterest.com/apps/
2. Bouton **"Connect app"** ou **"Create app"**
3. Nom : `SOS-Expat Pinterest`
4. Description : `Outil interne de republication de contenus expat`
5. Une fois créée, note :
   - **App ID** → `PINTEREST_CLIENT_ID`
   - **App Secret** → `PINTEREST_CLIENT_SECRET`
6. Configure le **Redirect URI** :
   ```
   https://<TON_DOMAINE_PROD>/api/social/pinterest/oauth/callback
   ```
7. Demande les scopes :
   - `pins:read`, `pins:write`
   - `boards:read`
   - `user_accounts:read`
8. Soumets pour review

**Délai** : 3-5 jours (beaucoup plus rapide que Meta).

### Pinterest Board ID

Tu publieras sur 1 board précis (ex: "Conseils Expat"). Pour récupérer son ID :
- Va sur ta Page Pinterest, ouvre le board
- L'URL contient l'ID : `pinterest.com/USERNAME/BOARD-NAME/` → l'ID est dans la response API
- Ou laisse `PINTEREST_BOARD_ID` vide : Mission Control prendra le **premier board automatiquement** lors du premier OAuth

---

## Étape 3 — Configurer le .env du VPS

Une fois les 2 apps approuvées, connecte-toi en SSH :

```bash
ssh root@<TON_VPS_IP>
cd /opt/influenceurs-tracker  # ou ton path exact
nano .env.production
```

Ajoute à la fin :

```env
# ── META (Facebook + Threads + Instagram — 1 seule app) ──────────────
FACEBOOK_CLIENT_ID=<ton App ID Meta>
FACEBOOK_CLIENT_SECRET=<ton App Secret Meta>
FACEBOOK_REDIRECT_URI=https://<TON_DOMAINE>/api/social/facebook/oauth/callback
FACEBOOK_PAGE_ID=<ID de ta Page Pro Facebook>

# Instagram et Threads tombent automatiquement sur les credentials Facebook
# (mais redirect URIs séparés) :
INSTAGRAM_REDIRECT_URI=https://<TON_DOMAINE>/api/social/instagram/oauth/callback
INSTAGRAM_BUSINESS_ACCOUNT_ID=         # laisser vide, auto-détecté

THREADS_REDIRECT_URI=https://<TON_DOMAINE>/api/social/threads/oauth/callback

# ── PINTEREST (app séparée) ──────────────────────────────────────────
PINTEREST_CLIENT_ID=<ton App ID Pinterest>
PINTEREST_CLIENT_SECRET=<ton App Secret Pinterest>
PINTEREST_REDIRECT_URI=https://<TON_DOMAINE>/api/social/pinterest/oauth/callback
PINTEREST_BOARD_ID=                    # laisser vide, auto-détecté

# ── ACTIVATION ───────────────────────────────────────────────────────
SOCIAL_FACEBOOK_ENABLED=true
SOCIAL_INSTAGRAM_ENABLED=true
SOCIAL_THREADS_ENABLED=true
SOCIAL_PINTEREST_ENABLED=true

# ── (Optionnel) Telegram bots dédiés par plateforme ──────────────────
# Si non set, fallback sur TELEGRAM_ALERT_BOT_TOKEN / CHAT_ID :
# TELEGRAM_FACEBOOK_BOT_TOKEN=
# TELEGRAM_FACEBOOK_CHAT_ID=
# TELEGRAM_THREADS_BOT_TOKEN=
# TELEGRAM_THREADS_CHAT_ID=
# TELEGRAM_INSTAGRAM_BOT_TOKEN=
# TELEGRAM_INSTAGRAM_CHAT_ID=
# TELEGRAM_PINTEREST_BOT_TOKEN=
# TELEGRAM_PINTEREST_CHAT_ID=
```

Sauvegarde (`Ctrl+O`, `Enter`, `Ctrl+X`), puis :

```bash
docker exec inf-app php artisan config:clear
docker exec inf-app php artisan cache:clear
docker exec inf-app php artisan route:clear
```

---

## Étape 4 — Connecter chaque plateforme depuis le dashboard

Va sur ton dashboard Mission Control, sidebar → **Republication RS** :

1. **💼 LinkedIn** → déjà connecté, rien à faire
2. **📘 Facebook** → bouton **"Connecter"** → flow OAuth Meta → autorise → revient sur dashboard
3. **🧵 Threads** → bouton **"Connecter"** → flow OAuth Threads → autorise
4. **📸 Instagram** → bouton **"Connecter"** → flow OAuth Meta (même que FB) → IG business auto-détecté
5. **📌 Pinterest** → bouton **"Connecter"** → flow OAuth Pinterest → board auto-détecté

À ce stade, le statut OAuth de chaque plateforme passe de `(non connecté)` à `✓ connecté (60j)` (ou similaire selon la durée du token).

---

## Étape 5 — Premier post de test par plateforme

Sur chaque page plateforme :

1. Choisis un **source_type** (par exemple `tip`)
2. Choisis un **day_type** (par exemple aujourd'hui)
3. Clique **"Générer"**
4. Le job IA tourne en queue (~30 sec - 2 min selon GPT-4o)
5. Le post passe en statut `scheduled`
6. À l'heure programmée, il publie automatiquement
7. Tu reçois une notif Telegram de confirmation

Si erreur : check les logs :
```bash
docker exec inf-app tail -200 storage/logs/laravel.log
```

---

## Délai total estimé

| Étape | Délai |
|-------|-------|
| Créer + soumettre Meta App | 30 min |
| Meta App Review | **1-2 semaines** ⏳ |
| Créer + soumettre Pinterest App | 15 min |
| Pinterest Developer Review | **3-5 jours** ⏳ |
| Configurer .env + clear cache | 5 min |
| Tester chaque plateforme | 30 min |

**Total = 1 demi-journée de travail actif + 2 semaines d'attente Meta (la plus longue).**

Pinterest sera probablement utilisable AVANT Meta (3-5j vs 1-2 sem), donc tu peux activer Pinterest en premier.

---

## En cas de souci

- **OAuth refuse** : vérifier que `FACEBOOK_REDIRECT_URI` correspond EXACTEMENT à ce que tu as déclaré dans la console Meta (case-sensitive, slash final compris)
- **403 sur le dashboard** : vérifier `SOCIAL_*_ENABLED=true` puis `php artisan config:clear`
- **Post reste en "generating"** : vérifier que les workers tournent (`docker exec inf-app supervisorctl status`)
- **Erreur 190 (Meta token expired)** : reconnecter via le bouton "Connecter" sur la plateforme concernée

Pour tout autre problème, lance moi avec `gh run list --workflow=deploy.yml` ou ouvre les logs Telegram.
