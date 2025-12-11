# Régie des Eaux

Application Laravel pour la gestion des demandes et documents de la régie des eaux.

## Prérequis

- PHP 8.2+
- Composer
- Node.js 18+ (pour le développement local uniquement)
- SQLite ou MySQL

## Installation

### En local (développement)

```bash
# 1. Cloner le repository
git clone https://github.com/mGuerino/regiedeseaux.git
cd regiedeseaux

# 2. Installer les dépendances PHP
composer install

# 3. Installer les dépendances Node.js
npm install

# 4. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 5. Créer la base de données
php artisan migrate --seed

# 6. Démarrer le serveur de développement
php artisan serve
# Dans un autre terminal:
npm run dev
```

### Sur le serveur (production)

**Note:** Les assets frontend (CSS/JS) sont **pré-compilés et inclus dans Git**, donc **Node.js n'est PAS requis** sur le serveur de production.

```bash
# 1. Cloner le repository
git clone https://github.com/mGuerino/regiedeseaux.git
cd regiedeseaux

# 2. Installer les dépendances PHP uniquement
composer install --no-dev --optimize-autoloader

# 3. Configurer l'environnement
cp .env.example .env
php artisan key:generate
# Éditer .env pour configurer la base de données, mail, etc.

# 4. Migrer la base de données
php artisan migrate --force

# 5. Optimiser pour la production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Configurer les permissions
chmod -R 775 storage bootstrap/cache
```

## Déploiement

### Workflow de déploiement

**En local (avant de push):**
```bash
# 1. Faire vos modifications de code
# ...

# 2. Si vous avez modifié CSS/JS, rebuild les assets
npm run build

# 3. Commit TOUT (code + assets buildés)
git add .
git commit -m "Votre message de commit"
git push
```

**Sur le serveur:**
```bash
# 1. Récupérer les dernières modifications
cd /chemin/vers/regiedeseaux
git pull

# 2. Mettre à jour les dépendances si nécessaire
composer install --no-dev --optimize-autoloader

# 3. Migrer la base de données si nécessaire
php artisan migrate --force

# 4. Vider les caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Important

- **Les assets compilés (public/build/) sont versionnés dans Git** pour permettre le déploiement sans Node.js sur le serveur
- **Toujours lancer `npm run build` avant de commiter** si vous avez modifié des fichiers CSS/JS
- Les assets Vite sont automatiquement versionnés avec des hash pour éviter les problèmes de cache

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://packagist.org/packages/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
