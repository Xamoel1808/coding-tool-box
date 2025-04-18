# Coding Tool Box

Une application web de gestion de groupes pédagogiques, développée avec le framework Laravel.

## Caractéristiques principales

- Gestion des écoles, cohortes et étudiants
- Création et attribution automatique de groupes
- Rétrospectives interactives (Retro)
- Système de permissions et politiques d'accès

## Prérequis

- PHP >= 8.2
- Composer
- Node.js & npm
- Base de données (MySQL, PostgreSQL, SQLite, etc.)

## Installation

1. Cloner le dépôt :
   ```bash
   git clone https://votre.repo/coding-tool-box.git
   cd coding-tool-box
   ```

2. Installer les dépendances PHP avec Composer :
   ```bash
   composer install
   ```

3. Copier le fichier d'environnement et générer la clé d'application :
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configurer les variables d'environnement dans `.env` :
   ```dotenv
   # Base de données
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nom_de_votre_bd
   DB_USERNAME=votre_utilisateur
   DB_PASSWORD=votre_mot_de_passe

   # Pusher (broadcasting en temps réel)
   PUSHER_APP_ID=votre_app_id
   PUSHER_KEY=votre_pusher_key
   PUSHER_SECRET=votre_pusher_secret
   PUSHER_CLUSTER=votre_cluster
   MIX_PUSHER_APP_KEY=${PUSHER_KEY}
   MIX_PUSHER_APP_CLUSTER=${PUSHER_CLUSTER}

   # Gemini / AI
   AI_API_URL=https://generativelanguage.googleapis.com/v1beta
   AI_API_KEY=votre_clé_api_google_gemini
   AI_MODEL=gemini-2.0-flash
   ```

5. Exécuter les migrations et les seeders (si nécessaire) :
   ```bash
   php artisan migrate --seed
   ```

6. Installer les dépendances front-end et compiler les assets :
   ```bash
   npm install
   npm run dev
   ```

## Utilisation

- Démarrer le serveur de développement :
  ```bash
  php artisan serve
  ```
  L'application est accessible sur `http://localhost:8000`.

- Tâches utilitaires :
  - Surveillance des files d'attente : `php artisan queue:listen`
  - Exécution des tests : `php artisan test`

## Structure du projet

```
app/
├── Http/
│   ├── Controllers/
│   └── Middleware/
├── Models/
├── Policies/
├── Services/
database/
├── migrations/
└── seeders/
public/
resources/
├── js/
└── views/
routes/
├── web.php
└── api.php
```