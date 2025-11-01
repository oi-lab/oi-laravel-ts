# Guide de Publication sur GitHub

Ce guide vous explique comment publier le package OI Laravel TypeScript sur GitHub dans votre organisation oi-lab.

## Prérequis

- Un compte GitHub avec accès à l'organisation `oi-lab`
- Git installé sur votre machine
- Droits de création de dépôt dans l'organisation

## Étape 1 : Initialiser le Dépôt Git

```bash
cd packages/oi-lab/oi-laravel-ts
git init
git add .
git commit -m "Initial commit: OI Laravel TypeScript Generator v1.0.0"
```

## Étape 2 : Créer le Dépôt sur GitHub

1. Allez sur GitHub : https://github.com/organizations/oi-lab/repositories/new
2. Configurez le dépôt :
   - **Repository name**: `oi-laravel-ts`
   - **Description**: "Generate TypeScript interfaces from Laravel Eloquent models"
   - **Visibility**: Private ou Public (selon vos besoins)
   - **N'initialisez PAS** le dépôt avec README, .gitignore ou LICENSE (déjà créés)

## Étape 3 : Lier le Dépôt Local au Dépôt GitHub

```bash
git remote add origin git@github.com:oi-lab/oi-laravel-ts.git
git branch -M main
git push -u origin main
```

## Étape 4 : Créer une Release (Tag)

```bash
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

Ou via l'interface GitHub :
1. Allez dans l'onglet "Releases"
2. Cliquez sur "Create a new release"
3. Tag version: `v1.0.0`
4. Release title: `v1.0.0 - Initial Release`
5. Description: Copiez les notes du CHANGELOG.md
6. Cliquez sur "Publish release"

## Étape 5 : Utiliser le Package dans vos Projets

### Option A : Dépôt Public

Si le dépôt est public, vous pouvez l'installer directement :

```bash
composer require oi-lab/oi-laravel-ts
```

### Option B : Dépôt Privé

Si le dépôt est privé, ajoutez-le dans le `composer.json` de votre projet :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:oi-lab/oi-laravel-ts.git"
        }
    ],
    "require": {
        "oi-lab/oi-laravel-ts": "^1.0"
    }
}
```

Puis :

```bash
composer install
```

**Note** : Pour un dépôt privé, assurez-vous que votre clé SSH est configurée avec GitHub.

### Option C : Développement Local

Pour le développement local, dans votre projet principal :

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/oi-lab/oi-laravel-ts",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "oi-lab/oi-laravel-ts": "@dev"
    }
}
```

## Étape 6 : Configuration après Installation

Dans chaque projet utilisant le package :

```bash
# Publier le fichier de configuration
php artisan vendor:publish --tag=oi-laravel-ts-config

# Modifier config/oi-laravel-ts.php selon vos besoins

# Générer les interfaces TypeScript
php artisan oi:gen-ts
```

## Étape 7 : Mettre à Jour le Package

Lorsque vous faites des modifications :

```bash
# Dans le dépôt du package
git add .
git commit -m "Description des changements"
git push

# Créer une nouvelle version
git tag -a v1.1.0 -m "Release version 1.1.0"
git push origin v1.1.0
```

Dans vos projets :

```bash
composer update oi-lab/oi-laravel-ts
```

## Configuration GitHub Recommandée

### Fichier .github/workflows/tests.yml (optionnel)

Créez ce fichier pour les tests automatiques :

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [8.2, 8.3, 8.4]
        laravel: [11.*, 12.*]

    name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip
          coverage: none

      - name: Install dependencies
        run: |
          composer require "illuminate/support:${{ matrix.laravel }}" --no-interaction --no-update
          composer update --prefer-stable --prefer-dist --no-interaction

      - name: Execute tests
        run: vendor/bin/pest
```

### Protection de la Branche Main

1. Allez dans Settings > Branches
2. Ajoutez une règle de protection pour `main`
3. Cochez "Require pull request reviews before merging"

## Utilisation dans Plusieurs Projets

Une fois publié, vous pouvez utiliser ce package dans tous vos projets Laravel :

```bash
# Projet 1
cd /path/to/project1
composer require oi-lab/oi-laravel-ts

# Projet 2
cd /path/to/project2
composer require oi-lab/oi-laravel-ts

# etc.
```

## Support et Documentation

- **Repository** : https://github.com/oi-lab/oi-laravel-ts
- **Issues** : https://github.com/oi-lab/oi-laravel-ts/issues
- **Documentation** : Voir README.md

## Notes de Sécurité

- Ne committez **jamais** de clés API ou secrets dans le code
- Utilisez des variables d'environnement pour les données sensibles
- Pour un dépôt privé, gérez les accès via les permissions GitHub de l'organisation
