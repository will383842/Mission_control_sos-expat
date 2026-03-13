#!/usr/bin/env node
/**
 * Script de migration des données du fichier suivi-influenceurs.html
 * vers la base MySQL via l'API Laravel.
 *
 * Usage :
 *   1. Exporter les données depuis Chrome DevTools → Console :
 *      copy(JSON.parse(localStorage.getItem('influenceurs')))
 *   2. Coller dans scripts/localstorage-export.json
 *   3. node scripts/migrate-localstorage.js
 */

const fs = require('fs');
const path = require('path');

// NOTE: Ce script nécessite un token API Sanctum (pas de session cookie en Node.js).
// Le backend utilise Sanctum SPA (session) par défaut — le login /api/login ne retourne PAS de token.
// Générer un token Sanctum personnel avant de lancer ce script :
//   cd laravel-api
//   php artisan tinker
//   >>> User::first()->createToken('migration')->plainTextToken
// Puis lancer :
//   ADMIN_TOKEN=xxx node scripts/migrate-localstorage.js
// Ou sous Windows (PowerShell) :
//   $env:ADMIN_TOKEN="xxx"; node scripts/migrate-localstorage.js

const API_URL     = process.env.API_URL      ?? 'http://localhost:8002/api';
const ADMIN_TOKEN = process.env.ADMIN_TOKEN  ?? null;

// Fallback login par email/mot de passe (ne fonctionne qu'avec un endpoint retournant un token).
const EMAIL    = process.env.ADMIN_EMAIL    ?? 'williams@sos-expat.com';
const PASSWORD = process.env.ADMIN_PASSWORD ?? 'Admin2025!';

const EXPORT_FILE = path.join(__dirname, 'localstorage-export.json');

// Mapping statuts FR → EN
const STATUS_MAP = {
  'Prospect': 'prospect', 'prospect': 'prospect',
  'Contacté': 'contacted', 'contacted': 'contacted',
  'En négociation': 'negotiating', 'negotiating': 'negotiating',
  'Actif': 'active', 'active': 'active',
  'Refusé': 'refused', 'refused': 'refused',
  'Inactif': 'inactive', 'inactive': 'inactive',
};

const RESULT_MAP = {
  'Envoyé': 'sent', 'sent': 'sent',
  'Répondu': 'replied', 'replied': 'replied',
  'Refusé': 'refused', 'refused': 'refused',
  'Signé': 'registered', 'registered': 'registered',
  'Sans réponse': 'no_answer', 'no_answer': 'no_answer',
};

const CHANNEL_MAP = {
  'email': 'email', 'instagram': 'instagram', 'linkedin': 'linkedin',
  'whatsapp': 'whatsapp', 'phone': 'phone', 'other': 'other',
  'Instagram': 'instagram', 'Email': 'email', 'LinkedIn': 'linkedin',
  'WhatsApp': 'whatsapp', 'Téléphone': 'phone',
};

async function request(method, path, body, token) {
  const res = await fetch(`${API_URL}${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`${method} ${path} → ${res.status}: ${text}`);
  }
  return res.json().catch(() => null);
}

async function main() {
  if (!fs.existsSync(EXPORT_FILE)) {
    console.error(`❌ Fichier introuvable : ${EXPORT_FILE}`);
    console.error('Exporte les données depuis Chrome DevTools console :');
    console.error("  copy(JSON.parse(localStorage.getItem('influenceurs')))");
    console.error(`Puis colle dans ${EXPORT_FILE}`);
    process.exit(1);
  }

  const raw = JSON.parse(fs.readFileSync(EXPORT_FILE, 'utf-8'));
  console.log(`📦 ${raw.length} influenceurs trouvés dans l'export.`);

  // Authentification
  let token = ADMIN_TOKEN;
  if (token) {
    console.log('Utilisation du token ADMIN_TOKEN fourni.');
  } else {
    // Tentative de login par email/mot de passe (retourne un token seulement si le backend
    // a été configuré pour ça — par défaut Laravel Sanctum SPA utilise les sessions).
    const loginRes = await request('POST', '/login', { email: EMAIL, password: PASSWORD });
    token = loginRes?.token ?? null;
    if (!token) {
      console.error('Erreur : le login ne retourne pas de token Bearer.');
      console.error('Générer un token via : php artisan tinker');
      console.error(">>> User::first()->createToken('migration')->plainTextToken");
      console.error('Puis : ADMIN_TOKEN=xxx node scripts/migrate-localstorage.js');
      process.exit(1);
    }
  }
  console.log('Connecté en tant qu\'admin.');

  let ok = 0, errors = 0;

  for (const item of raw) {
    try {
      const platforms = item.plateforme
        ? [item.plateforme.toLowerCase()]
        : (item.platforms ?? ['other']);

      const payload = {
        name: item.nom ?? item.name ?? 'Inconnu',
        handle: item.handle ?? null,
        platforms,
        primary_platform: platforms[0],
        followers: item.followers ?? null,
        status: STATUS_MAP[item.statut ?? item.status] ?? 'prospect',
        notes: item.notes ?? null,
        tags: item.tags ?? null,
      };

      const created = await request('POST', '/influenceurs', payload, token);

      // Contacts
      const contacts = item.contacts ?? [];
      for (const c of contacts) {
        await request('POST', `/influenceurs/${created.id}/contacts`, {
          date: c.date ?? new Date().toISOString().split('T')[0],
          channel: CHANNEL_MAP[c.canal ?? c.channel] ?? 'other',
          result: RESULT_MAP[c.resultat ?? c.result] ?? 'sent',
          sender: c.expediteur ?? c.sender ?? null,
          message: c.message ?? null,
          reply: c.reponse ?? c.reply ?? null,
          notes: c.notes ?? null,
        }, token);
      }

      console.log(`  ✅ ${payload.name} (${contacts.length} contact(s))`);
      ok++;
    } catch (e) {
      console.error(`  ❌ ${item.nom ?? item.name}: ${e.message}`);
      errors++;
    }
  }

  console.log(`\n📊 Migration terminée : ${ok} réussis, ${errors} erreurs.`);
}

main().catch(console.error);
