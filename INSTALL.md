# Boucherie Express - Installation

## Prérequis

- Docker
- Docker Compose

## Option 1 : Docker (Recommandé)

### 1. Construire et lancer les conteneurs

```bash
cd /Users/mac/Documents/GitHub/boucherie_express_backend/boucherie-laravel
docker-compose up -d
```

### 2. Installer les dépendances dans le conteneur

```bash
docker-compose exec laravel.app composer install
```

### 3. Configurer l'environnement

```bash
docker-compose exec laravel.app cp .env.example .env
docker-compose exec laravel.app php artisan key:generate
```

### 4. Launcher les migrations

```bash
docker-compose exec laravel.app php artisan migrate
```

### 5. Seed les données

```bash
docker-compose exec laravel.app php artisan db:seed
```

### 6. Créer l'utilisateur admin

```bash
docker-compose exec laravel.app php artisan tinker
```

Puis entrer :
```php
App\Models\User::create(['name' => 'Admin', 'email' => 'admin@boucherie-express.fr', 'password' => bcrypt('admin123')]);
```

## Accès

| URL | Description |
|-----|-------------|
| `http://localhost:8000` | Application Laravel |
| `http://localhost:8000/admin` | Panneau d'administration Filament |
| `http://localhost:3306` | MySQL |
| `http://localhost:6379` | Redis |
| `http://localhost:1025` | Mailpit ( SMTP) |
| `http://localhost:8025` | Mailpit Dashboard |

## Commandes Docker utiles

```bash
# Voir les logs
docker-compose logs -f laravel.app

# Arrêter les conteneurs
docker-compose down

# Reconstruire les conteneurs
docker-compose build --no-cache
```

## Option 2 : Installation locale

### 1. Installer les dépendances

```bash
cd /Users/mac/Documents/GitHub/boucherie_express_backend/boucherie-laravel
composer install
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Créer la base de données

```bash
mysql -u root -p -e "CREATE DATABASE boucherie_express;"
```

### 4. Configurer la connexion MySQL

Éditer le fichier `.env` et configurer :

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=boucherie_express
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe
```

### 5. Lancer les migrations

```bash
php artisan migrate
```

### 6. Seed les données (optionnel)

```bash
php artisan db:seed
```

### 7. Créer un utilisateur admin

```bash
php artisan tinker
```

Puis entrer :
```php
App\Models\User::create(['name' => 'Admin', 'email' => 'admin@boucherie-express.fr', 'password' => bcrypt('admin123')]);
```

### 8. Lancer le serveur

```bash
php artisan serve
```

## Accès

| URL | Description |
|-----|-------------|
| `http://localhost:8000` | Page d'accueil API |
| `http://localhost:8000/api/v1/products` | Liste des produits |
| `http://localhost:8000/api/v1/categories` | Liste des catégories |
| `http://localhost:8000/admin` | Panneau d'administration Filament |

## Identifiants

- **Email**: `admin@boucherie-express.fr`
- **Mot de passe**: `admin123`

## Commandes utiles

```bash
# Voir les routes
php artisan route:list

# Mettre à jour les dépendances
composer update

# Vider le cache
php artisan cache:clear

# Mode développement avec hot reload
composer run dev
```

## API Endpoints

### Public

- `POST /api/v1/auth/register` - Inscription utilisateur
- `POST /api/v1/auth/login` - Connexion
- `GET /api/v1/products` - Liste produits
- `GET /api/v1/categories` - Liste catégories

### Authentifié (Sanctum)

- `GET /api/v1/auth/me` - Profil utilisateur
- `POST /api/v1/auth/logout` - Déconnexion
- `GET /api/v1/orders` - Liste commandes
- `POST /api/v1/orders` - Créer commande
- `PUT /api/v1/orders/{id}/status` - Mettre à jour statut
- `GET /api/v1/addresses` - Liste adresses
- `POST /api/v1/addresses` - Ajouter adresse