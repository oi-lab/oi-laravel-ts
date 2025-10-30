# GitHub Publishing Guide

This guide explains how to publish the OI Laravel TypeScript package on GitHub in your Axo-Conseil organization.

## Prerequisites

- A GitHub account with access to the `Axo-Conseil` organization
- Git installed on your machine
- Repository creation rights in the organization

## Step 1: Initialize Git Repository

```bash
cd packages/oi-lab/oi-laravel-ts
git init
git add .
git commit -m "Initial commit: OI Laravel TypeScript Generator v1.0.0"
```

## Step 2: Create Repository on GitHub

1. Go to GitHub: https://github.com/organizations/Axo-Conseil/repositories/new
2. Configure the repository:
   - **Repository name**: `oi-laravel-ts`
   - **Description**: "Generate TypeScript interfaces from Laravel Eloquent models"
   - **Visibility**: Private or Public (according to your needs)
   - **DO NOT initialize** the repository with README, .gitignore or LICENSE (already created)

## Step 3: Link Local Repository to GitHub Repository

```bash
git remote add origin git@github.com:Axo-Conseil/oi-laravel-ts.git
git branch -M main
git push -u origin main
```

## Step 4: Create a Release (Tag)

```bash
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

Or via the GitHub interface:
1. Go to the "Releases" tab
2. Click "Create a new release"
3. Tag version: `v1.0.0`
4. Release title: `v1.0.0 - Initial Release`
5. Description: Copy the notes from CHANGELOG.md
6. Click "Publish release"

## Step 5: Use the Package in Your Projects

### Option A: Public Repository

If the repository is public, you can install it directly:

```bash
composer require oi-lab/oi-laravel-ts
```

### Option B: Private Repository

If the repository is private, add it to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:Axo-Conseil/oi-laravel-ts.git"
        }
    ],
    "require": {
        "oi-lab/oi-laravel-ts": "^1.0"
    }
}
```

Then:

```bash
composer install
```

**Note**: For a private repository, ensure your SSH key is configured with GitHub.

### Option C: Local Development

For local development, in your main project:

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

## Step 6: Configuration After Installation

In each project using the package:

```bash
# Publish the configuration file
php artisan vendor:publish --tag=oi-laravel-ts-config

# Modify config/oi-laravel-ts.php according to your needs

# Generate TypeScript interfaces
php artisan oi:gen-ts
```

## Step 7: Update the Package

When you make changes:

```bash
# In the package repository
git add .
git commit -m "Description of changes"
git push

# Create a new version
git tag -a v1.1.0 -m "Release version 1.1.0"
git push origin v1.1.0
```

In your projects:

```bash
composer update oi-lab/oi-laravel-ts
```

## Recommended GitHub Configuration

### File .github/workflows/tests.yml (optional)

Create this file for automatic tests:

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

### Main Branch Protection

1. Go to Settings > Branches
2. Add a protection rule for `main`
3. Check "Require pull request reviews before merging"

## Usage in Multiple Projects

Once published, you can use this package in all your Laravel projects:

```bash
# Project 1
cd /path/to/project1
composer require oi-lab/oi-laravel-ts

# Project 2
cd /path/to/project2
composer require oi-lab/oi-laravel-ts

# etc.
```

## Support and Documentation

- **Repository**: https://github.com/Axo-Conseil/oi-laravel-ts
- **Issues**: https://github.com/Axo-Conseil/oi-laravel-ts/issues
- **Documentation**: See README.md

## Security Notes

- **Never** commit API keys or secrets in the code
- Use environment variables for sensitive data
- For a private repository, manage access via GitHub organization permissions