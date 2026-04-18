# Meta App Review — Kit de soumission complet

> Pour l'app **SOS-Expat Social** (App ID `807848878792527`)
> Permet d'obtenir l'autorisation de publier sur Facebook Pages + Instagram Business + Threads

---

## 📋 Ce que tu dois préparer

| # | Asset | Format | Temps estimé |
|---|-------|--------|--------------|
| 1 | Description de l'app (use case) | Texte EN à copier dans Meta | 0 min (ci-dessous) |
| 2 | Vidéo démo de l'OAuth + publication | MP4 ~3 min | 30 min de capture |
| 3 | Screenshots de l'utilisation réelle | PNG x6 | 15 min |
| 4 | Descriptions par permission | Texte EN à copier dans Meta | 0 min (ci-dessous) |
| 5 | URLs déjà configurées | Privacy / Terms / Data deletion | ✅ déjà OK |

**Total** : ~1 demi-journée. Puis Meta examine pendant **1-2 semaines**.

---

## 1️⃣ Description du business / use case (à coller dans "App Details")

```
SOS-Expat (https://sos-expat.com) is a French SaaS connecting expatriates with verified lawyers and local experts in 197 countries. We provide on-demand consultations via secure phone calls, with multi-language support and 24/7 emergency assistance.

This Meta App is our internal social media management dashboard called "Mission Control". It is used exclusively by our internal editorial team (3 people) to publish curated educational content on our official Pages, Instagram Business account, and Threads profile. Each post is generated from our blog articles and FAQ database, then scheduled via our editorial calendar.

We do NOT collect, process, or store any data about Meta users beyond what is strictly required for OAuth authentication of our own accounts. We do NOT request any user-facing Facebook Login. The app is used only by SOS-Expat administrators to manage SOS-Expat's own social presence.

Our target audience: French expatriates and aspiring expats worldwide.
Our content topics: visa procedures, international taxation, cross-border legal questions, expat banking, expat insurance, real estate abroad.

The app is built in PHP/Laravel + React, hosted on a private VPS (Hetzner Cloud, EU). Source: https://github.com/will383842/Mission_control_sos-expat
```

---

## 2️⃣ Script de la vidéo démo (à enregistrer)

**Outils recommandés** : Loom (gratuit, le plus simple) ou Windows Game Bar (Win+G) ou OBS Studio.

**Durée cible** : 2 min 30 — 3 min 30 max.
**Langue** : anglais préférable (Meta apprécie). Si pas confiant : français OK avec sous-titres.

### Plan de tournage (timeline)

```
0:00 — 0:15 | INTRO
  Écran : page d'accueil https://sos-expat.com
  Voix-off : "Hi, I'm William, founder of SOS-Expat. We help French
  expats connect with verified lawyers in 197 countries. Today I'll
  show how our internal Mission Control dashboard uses the Meta API
  to publish educational content on our own Pages and Instagram."

0:15 — 0:35 | LOGIN MISSION CONTROL
  Écran : navigation vers https://influenceurs.sos-expat.com
  Action : connexion avec ton compte admin (login + password)
  Voix-off : "This is our internal dashboard, used only by our
  3-person editorial team. You can see we already have a LinkedIn
  publishing pipeline running. Now I'll add Facebook."

0:35 — 1:10 | CONNEXION OAUTH FACEBOOK
  Écran : naviguer vers /content/republication-rs/facebook
  Action : cliquer sur le bouton "Connecter" (OAuth Meta)
  Action : s'identifier avec ton compte Facebook personnel
  Action : Meta affiche le consent screen → autoriser pour la Page
  SOS-Expat
  Action : retour sur le dashboard Mission Control → token OK
  Voix-off : "I authorize Mission Control to publish on the SOS-Expat
  Page. Notice that I'm authenticating as the Page admin — we never
  use this for user-facing login."

1:10 — 1:50 | GÉNÉRATION D'UN POST
  Écran : sélecteur source_type / day_type / language
  Action : choisir source_type=tip, day_type=tuesday, lang=fr
  Action : cliquer "Générer"
  Action : montrer le job en queue → wait quelques sec → post généré
  Voix-off : "Our AI generates a Facebook-optimized post from our
  blog content. Hook of 50 chars, body around 300 chars,
  conversational tone, with an open-ended question to drive
  engagement. The post is then scheduled."

1:50 — 2:20 | PUBLICATION RÉELLE
  Écran : queue → post programmé → cliquer "Publier maintenant"
  Action : voir le post apparaître sur la Page Facebook (ouvrir
  facebook.com/SosExpat dans un autre onglet)
  Voix-off : "The post is now live on our Facebook Page. We use
  pages_manage_posts for this exact action — publishing organic
  content that we authored ourselves."

2:20 — 2:50 | MONITORING DES COMMENTAIRES
  Écran : retour dashboard → onglet "Commentaires"
  Action : montrer un commentaire reçu (réel ou démo)
  Action : montrer la fonctionnalité de réponse rapide
  Voix-off : "When users comment on our posts, we get notifications
  via Telegram. We use pages_manage_engagement to reply directly from
  the dashboard, keeping our community engaged."

2:50 — 3:00 | OUTRO
  Écran : retour sur la dashboard avec stats globales
  Voix-off : "All permissions are used exclusively to manage our own
  Pages, Instagram, and Threads. We never access third-party user
  data. Thank you for reviewing our submission."
```

### Tips pour le tournage

- Curseur visible et lent (Meta veut voir où tu cliques)
- Pas d'autre onglet sensible visible (cache personnel ouvert)
- Voix audible en anglais lent et clair, ou sous-titres si français
- Format : 1080p MP4, max 1 GB
- Si tu utilises Loom : tu peux directement copier le lien dans Meta

---

## 3️⃣ Screenshots requis (PNG ou JPG)

À uploader dans Meta App Review (1 par permission groupe) :

1. **screenshot_dashboard.png** — page d'accueil de Mission Control
2. **screenshot_oauth_consent.png** — Meta consent screen (pendant OAuth)
3. **screenshot_post_editor.png** — formulaire de génération de post
4. **screenshot_facebook_post_live.png** — post réel publié sur Page FB
5. **screenshot_instagram_post_live.png** — post réel publié sur IG (si tu as déjà connecté)
6. **screenshot_threads_post_live.png** — post réel publié sur Threads (si tu as déjà connecté)

⚠️ Note : pour les screenshots 5 et 6, comme tu n'as pas encore activé Instagram et Threads, **utilise des screenshots du mockup UI** (la page Mission Control qui montrerait ces plateformes), Meta accepte ça pour les premières demandes.

---

## 4️⃣ Descriptions par permission (à coller dans chaque "Add Permission" dans Meta)

### 🔵 pages_show_list

**How will your app use this permission?**
```
We use pages_show_list to retrieve the list of Facebook Pages that
the SOS-Expat administrator manages. This is required during the
OAuth flow so that the admin can select the official "SOS-Expat"
Page as the publishing target. Without this permission, we cannot
identify which Page to publish to.
```

**Step-by-step instructions to test:**
```
1. Sign in to https://influenceurs.sos-expat.com (admin credentials
   provided in test-user section)
2. Navigate to /content/republication-rs/facebook
3. Click "Connecter" → Meta OAuth flow
4. After consent, the dashboard automatically detects and selects
   the SOS-Expat Page using pages_show_list
5. The Page name and ID are displayed in the connection panel
```

**Demo video timestamp:** 0:35 - 1:10

---

### 🔵 pages_read_engagement

**How will your app use this permission?**
```
We retrieve engagement metrics (impressions, reactions, clicks) on
posts we've published on our own Page, in order to display
analytics in our internal dashboard. This helps our editorial team
understand which content performs best and refine our editorial
strategy.
```

**Step-by-step instructions to test:**
```
1. After OAuth (see pages_show_list test)
2. Navigate to /content/republication-rs/facebook → tab "Stats"
3. The dashboard fetches insights for the past 30 days using
   pages_read_engagement
4. Metrics displayed: total reach, average engagement rate, top
   performing day
```

**Demo video timestamp:** 2:50 - 3:00

---

### 🔵 pages_manage_posts

**How will your app use this permission?**
```
This is our core permission. We use pages_manage_posts to publish
educational content (text + image) on the SOS-Expat Page on a
schedule (typically 4 posts per week: Mon/Wed/Fri/Sat). Content is
generated by our internal AI pipeline from our blog articles
(https://sos-expat.com/vie-a-letranger) and reviewed by our
editorial team before publication.
```

**Step-by-step instructions to test:**
```
1. After OAuth (see pages_show_list test)
2. Navigate to /content/republication-rs/facebook
3. Click "Générer" → fill source_type=tip, lang=fr
4. Wait ~30 sec for the AI to generate the post
5. The post appears in the queue with status "scheduled"
6. Click "Publier maintenant" to publish immediately via
   pages_manage_posts
7. Verify the post is live on https://facebook.com/SosExpat
```

**Demo video timestamp:** 1:10 - 2:20

---

### 🔵 pages_manage_engagement

**How will your app use this permission?**
```
We use pages_manage_engagement to reply to comments left by users
on our published posts. When a comment is detected (via polling
every 15 minutes), our system generates 3 contextual reply
suggestions using GPT, and our editorial team chooses the best
one or writes a custom reply. This permission is used to post
those replies as the Page.
```

**Step-by-step instructions to test:**
```
1. After publishing a post (see pages_manage_posts test)
2. Have a test user comment on the post (or wait for a real
   comment)
3. The dashboard polls comments every 15 min using
   pages_read_engagement
4. New comments appear in the "Commentaires" tab with 3 AI-suggested
   replies
5. Click "Reply with this variant" → the reply is posted on Facebook
   using pages_manage_engagement
```

**Demo video timestamp:** 2:20 - 2:50

---

### 🟣 instagram_basic

**How will your app use this permission?**
```
We use instagram_basic to retrieve our Instagram Business account
profile information (username, profile picture, follower count) and
to verify that the account is correctly linked to our Facebook
Page during OAuth.
```

---

### 🟣 instagram_content_publish

**How will your app use this permission?**
```
We use instagram_content_publish to publish photos with captions on
our Instagram Business account (@sos.expat). Each post requires
an image (mandatory on Instagram) and a caption optimized for
Instagram (138 chars hook, 500-1500 chars body, 3-5 niche hashtags).
Posts are generated by our AI from our blog articles and scheduled
via our editorial calendar.
```

**Step-by-step instructions to test:**
```
1. Sign in to Mission Control
2. Navigate to /content/republication-rs/instagram
3. Click "Connecter" → Meta OAuth (same as Facebook)
4. Generate a post: source_type=tip, lang=fr (image is fetched
   from Unsplash automatically)
5. Click "Publier maintenant" → 2-step container publish (create +
   publish via instagram_content_publish)
6. Verify on https://instagram.com/sos.expat
```

---

### 🟣 instagram_manage_comments

**How will your app use this permission?**
```
We use instagram_manage_comments to reply to comments on our own
Instagram posts. Same flow as Facebook: comments are polled every
15 min, AI generates reply suggestions, our team chooses the best
one and posts it via this permission.
```

---

### 🟢 threads_basic + threads_content_publish + threads_manage_replies

**How will your app use these permissions?**
```
We use the Threads API to publish short-form opinion content (max
500 chars) on our @sos.expat Threads account. Threads is used for
"hot takes" on expat-related news (visa changes, tax updates,
cross-border legal questions). The 2-step container API
(create then publish) is implemented per Meta documentation.

Replies on our Threads posts are managed via threads_manage_replies,
following the same AI-suggested reply pattern as Facebook and
Instagram.
```

---

## 5️⃣ Réponses aux questions standards Meta

### "Will your app use the data on a third-party platform?"
```
No. All data retrieved via the Meta API is used exclusively within
our internal Mission Control dashboard, hosted on our private VPS.
No data is shared with any third party.
```

### "Will end users use your app?"
```
No. The app is used internally by 3 SOS-Expat editorial team
members (admins). End users (expatriates visiting our website
sos-expat.com) never interact with the app directly. They only see
the published content on our Facebook/Instagram/Threads accounts.
```

### "Where is the app hosted?"
```
Hetzner Cloud (Germany). Backend: Laravel 11 + PHP 8.2 in Docker
on a private VPS. Frontend: React 18 + Vite served via Nginx on the
same VPS. Database: PostgreSQL. All data stays in the EU (GDPR
compliant).
```

### "Do you have a Privacy Policy?"
```
Yes: https://sos-expat.com/fr-fr/politique-confidentialite
Available in 9 languages (fr/en/es/de/ru/pt/zh/hi/ar).
```

### "Do you have data deletion instructions?"
```
Yes: https://sos-expat.com/fr-fr/suppression-donnees
Following GDPR Article 17 (right to erasure), with 3 deletion
methods (in-app self-service, email to privacy@sos-expat.com,
postal mail). Available in 9 languages.
```

---

## 6️⃣ Test User credentials (à fournir à Meta pour qu'ils testent eux-mêmes)

Meta peut demander un compte de test pour vérifier ton app. Crée un compte admin dédié :

```
URL:       https://influenceurs.sos-expat.com
Username:  meta-reviewer@sos-expat.com (ou similar)
Password:  <génère un mot de passe fort, à fournir UNIQUEMENT à Meta>
Role:      Admin (en lecture/écriture sur le dashboard)
```

Note : tu devras créer ce compte dans Mission Control AVANT de soumettre la review.

---

## 7️⃣ Checklist finale avant de cliquer "Submit for Review"

- [ ] App icon 1024x1024 uploadée
- [ ] App display name = `SOS-Expat`
- [ ] App domain = `sos-expat.com`
- [ ] Privacy Policy URL = `https://sos-expat.com/fr-fr/politique-confidentialite`
- [ ] Terms of Service URL = `https://sos-expat.com/fr-fr/cgu-clients`
- [ ] Data deletion URL = `https://sos-expat.com/fr-fr/suppression-donnees`
- [ ] Category = `Business and Pages`
- [ ] Use cases activés : Threads + Pages + Instagram
- [ ] Redirect URIs configurés pour les 3 plateformes
- [ ] Vidéo démo enregistrée (~3 min) et uploadée
- [ ] 6 screenshots uploadés
- [ ] Description app collée dans "App Details"
- [ ] Pour chaque permission demandée : description + steps + timestamp
- [ ] Test user créé sur Mission Control
- [ ] Test user credentials renseignés dans la review

→ Bouton **"Submit for Review"** → attente 1-2 semaines.

---

## 8️⃣ Pendant l'attente (1-2 semaines)

Tu peux :
- ✅ Continuer à utiliser LinkedIn normalement (déjà fonctionnel)
- ✅ Créer en parallèle ton app Pinterest (review 3-5 jours, beaucoup plus rapide)
- ✅ Tester les flux OAuth en mode "Development" (Meta autorise les test users sans review)
- ❌ Ne PAS publier publiquement sur FB/IG/Threads (interdit jusqu'à approbation)

---

## 9️⃣ Si Meta refuse (ça arrive ~30% des cas)

Meta envoie un email avec la raison du refus. Causes courantes :
- **"Use case not clear"** → améliorer la description anglaise
- **"Video does not show the permission in action"** → re-tourner la vidéo en montrant chaque permission
- **"Privacy policy missing X"** → ajouter une section spécifique Meta dans ta privacy policy

Dans tous les cas : on corrige et on resoumet. Pas de pénalité.

---

**Bonne chance avec la review ! Si tu as besoin d'aide à n'importe quelle étape, demande.**
