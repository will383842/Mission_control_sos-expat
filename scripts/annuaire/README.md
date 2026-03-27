# Annuaire SOS-Expat — Country Directory

## Contenu

Base de donnees unique des ressources essentielles pour expatries, classees par pays.

### Source de donnees

- **Ambassades/Consulats** : Dataset officiel data.gouv.fr (Ministere des Affaires Etrangeres)
  - 187 pays, 322+ representations diplomatiques
  - Adresses, telephones, emails, urgences, coordonnees GPS REELLES
  - Source : https://www.data.gouv.fr/en/datasets/coordonnees-des-representations-diplomatiques/

### Fichiers

| Fichier | Description | Ordre |
|---------|-------------|-------|
| `import-ambassades-data-gouv.sql` | 322 ambassades/consulats depuis data.gouv.fr | 1er |
| `fix-continents-and-emergency.sql` | Mapping continent + numeros d'urgence + ressources globales | 2e |
| `import-practical-links.sql` | ~200 liens pratiques pour 50 pays (immigration, sante, logement, emploi, banque, transport, education, fiscalite) | 3e |
| `seed-country-directory.sql` | Version alternative avec coordonnees detaillees pour top 10 pays | Optionnel |
| `annuaire-global.sql` | Version originale etendue | Deprecated |

### Execution

```bash
# 1. Creer la table (migration Laravel)
php artisan migrate

# 2. Importer les ambassades (donnees officielles)
psql -d mission_control -f scripts/annuaire/import-ambassades-data-gouv.sql

# 3. Corriger continents + ajouter numeros d'urgence + ressources globales
psql -d mission_control -f scripts/annuaire/fix-continents-and-emergency.sql

# 4. Ajouter les liens pratiques pour 50 pays (immigration, sante, logement, emploi...)
psql -d mission_control -f scripts/annuaire/import-practical-links.sql
```

### Couverture

| Donnee | Couverture |
|--------|-----------|
| Ambassades/Consulats | **187 pays** (322 representations, donnees data.gouv.fr) |
| Liens pratiques | **~200 liens** pour 50 pays (immigration, sante, logement, emploi, banque, fiscalite, transport, education) |
| Adresses | ~320 (97% des ambassades) |
| Telephones urgence | ~280 (85%) |
| Emails | ~200 (60%) |
| Coordonnees GPS | ~320 (97%) |
| Numeros urgence pays | ~90 pays |
| Continents | 6 continents |
| Ressources globales | 12 liens (CFE, Wise, AEFE, etc.) |
| **TOTAL** | **~530 entrees** dans 187 pays |

### API

```
GET /api/country-directory/countries     — Liste pays avec stats
GET /api/country-directory/country/{code} — Fiche complete d'un pays
GET /api/country-directory/export-blog    — Format external_links pour le blog
GET /api/country-directory/stats          — Stats globales
```

### Usage dans la generation d'articles

```php
// Recuperer les liens officiels pour un pays
$links = CountryDirectory::linksForArticle('DE', 'sante', 5);

// Recuperer tout l'annuaire d'un pays groupe par categorie
$directory = CountryDirectory::forCountry('TH');
```
