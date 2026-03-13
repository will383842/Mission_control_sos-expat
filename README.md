# Influenceurs Tracker — SOS-Expat

CRM de gestion des influenceurs pour SOS-Expat.com.
Projet **100% standalone** — base de données et backend indépendants.

## Stack

| Composant | Technologie | Port |
|-----------|-------------|------|
| Backend API | Laravel 11 + PHP 8.2 | 8002 |
| Frontend | React 18 + Vite + TypeScript | 5175 (dev) / 82 (prod) |
| Base de données | MySQL 8 | 3309 |

---

## Installation locale (XAMPP)

### Prérequis
- PHP 8.2+, Composer 2+
- Node 18+, npm
- XAMPP avec MySQL sur le port **3309** (dédié — différent du port 3306 par défaut)

### 1. Base de données

Dans phpMyAdmin ou MySQL CLI :
```sql
CREATE DATABASE influenceurs_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'inf_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
GRANT ALL ON influenceurs_tracker.* TO 'inf_user'@'localhost';
```

### 2. Backend Laravel

```bash
cd laravel-api
composer install
cp .env.example .env   # ou éditer .env directement
php artisan key:generate
# Configurer DB_HOST, DB_PORT=3309, DB_DATABASE, DB_USERNAME, DB_PASSWORD dans .env
php artisan migrate --seed
php artisan serve --port=8002
```

Compte admin créé par le seeder : `williamsjullin@gmail.com` / `MJMJsblanc19522008/*%$`

### 3. Frontend React

```bash
cd react-dashboard
npm install
# Vérifier VITE_API_URL=http://localhost:8002 dans .env
npm run dev
```

Ouvrir : http://localhost:5175

### 4. Scheduler (rappels automatiques)

```bash
cd laravel-api
php artisan schedule:work
```

---

## Déploiement VPS Hetzner

### Laravel (Nginx + PHP-FPM)

```nginx
server {
    listen 443 ssl;
    server_name api.influenceurs.sos-expat.com;
    root /var/www/influenceurs-tracker/laravel-api/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/api.influenceurs.sos-expat.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.influenceurs.sos-expat.com/privkey.pem;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
server { listen 80; server_name api.influenceurs.sos-expat.com; return 301 https://$host$request_uri; }
```

### React (Nginx static)

```bash
cd react-dashboard
npm run build
# Copier dist/ dans /var/www/influenceurs-tracker/react-dashboard/dist/
```

### Cron (rappels automatiques)

```cron
* * * * * cd /var/www/influenceurs-tracker/laravel-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## Migration des données localStorage

```bash
# 1. Exporter depuis Chrome DevTools → Console :
#    copy(JSON.parse(localStorage.getItem('influenceurs')))
# 2. Coller dans scripts/localstorage-export.json
# 3. Lancer :
node scripts/migrate-localstorage.js
```

---

## Tests

### Backend (Pest)
```bash
cd laravel-api
php artisan test
```

### Frontend (Vitest)
```bash
cd react-dashboard
npm test
```

---

## Comptes par défaut

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| williamsjullin@gmail.com | MJMJsblanc19522008/*%$ | admin |

**Changer le mot de passe en production** depuis la page Équipe.
