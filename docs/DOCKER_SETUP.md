# 🐳 Drupal Docker Template

A production-ready, reusable Docker template for Drupal 9/10/11 sites with separate development and production configurations.

## 📋 Table of Contents

- [Features](#-features)
- [Quick Start](#-quick-start)
- [Project Structure](#-project-structure)
- [Environment Configuration](#-environment-configuration)
- [Usage](#-usage)
- [GitHub Actions CI/CD](#-github-actions-cicd)
- [Customization Guide](#-customization-guide)
- [Troubleshooting](#-troubleshooting)

## ✨ Features

- 🎯 **Multi-stage Dockerfile** - Separate development and production builds
- 🔄 **Hot Reloading** - Development mode with mounted volumes for instant file changes
- 🔒 **Security Hardened** - Production mode with read-only filesystems and restricted permissions
- 📦 **Template-Ready** - Copy, configure, and deploy in minutes
- 🚀 **GitHub Actions** - Automated build and deployment workflows
- ⚡ **Performance Optimized** - OPcache enabled, composer optimized for production
- 🔧 **Fully Configurable** - All settings controlled via environment variables

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose installed
- A Drupal 9/10/11 site with standard structure (`web/`, `config/`, `composer.json`)

### Setup Steps

1. **Copy template files to your Drupal project:**
   ```bash
   # Copy these files to your Drupal project root
   - Dockerfile
   - compose.yaml
   - compose.prod.yaml
   - .dockerignore
   - .env.example
   - .github/workflows/docker-build.yml (if using GitHub Actions)
   ```

2. **Create your environment file:**
   ```bash
   # Copy and customize the environment template
   cp .env.example .env
   
   # OPTIONAL: Use separate files for dev/prod
   cp dev.env.example dev.env
   cp prd.env.example prd.env
   ```

3. **Configure your environment variables:**
   ```bash
   # Edit .env and change AT MINIMUM:
   - PROJECT_NAME=your-project-name
   - DB_NAME=your_database_name
   - DB_USER=your_db_user
   - DB_PASSWORD=strong_password_here
   - DB_ROOT_PASSWORD=strong_root_password_here
   ```

4. **Start development environment:**
   ```bash
   docker-compose up -d --build
   ```

5. **Access your site:**
   - Web: http://localhost:8080
   - Database: localhost:3306

## 📁 Project Structure

```
your-drupal-project/
├── .env                          # Your environment configuration (NOT in git)
├── .env.example                  # Template for environment variables
├── .dockerignore                 # Exclude files from Docker build
├── Dockerfile                    # Multi-stage Docker build
├── compose.yaml                  # Development environment
├── compose.prod.yaml             # Production environment
├── composer.json                 # PHP dependencies
├── composer.lock                 # Locked dependencies
├── config/                       # Drupal configuration files
│   └── sync/                     # Configuration sync directory
├── web/                          # Drupal web root
│   ├── index.php
│   ├── sites/
│   ├── modules/
│   │   └── custom/              # Your custom modules
│   └── themes/
│       └── custom/              # Your custom themes
└── .github/
    └── workflows/
        └── docker-build.yml     # CI/CD workflow
```

## 🔧 Environment Configuration

### Essential Variables (MUST CHANGE)

```bash
# Project identifier (no spaces or special characters)
PROJECT_NAME=mysite

# Database credentials (CHANGE THESE!)
DB_NAME=mydatabase
DB_USER=myuser
DB_PASSWORD=secure_password_123
DB_ROOT_PASSWORD=secure_root_password_456
```

### Optional Variables

```bash
# PHP Version (8.1, 8.2, 8.3)
PHP_VERSION=8.3

# Port Configuration (change if ports are in use)
WEB_PORT=8080
DB_PORT=3306

# PHP Memory Limits
PHP_MEMORY_LIMIT_DEV=512M
PHP_MEMORY_LIMIT_PROD=256M
```

See `.env.example` for complete list of configurable variables.

## 💻 Usage

### Development Environment

**Start containers:**
```bash
docker-compose up -d --build
```

**View logs:**
```bash
docker-compose logs -f drupal
```

**Run Drush commands:**
```bash
docker-compose exec drupal drush status
docker-compose exec drupal drush cache:rebuild
docker-compose exec drupal drush config:import
```

**Access container shell:**
```bash
docker-compose exec drupal bash
```

**Stop containers:**
```bash
docker-compose down
```

**Stop and remove volumes:**
```bash
docker-compose down -v
```

### Production Environment

**Build and start:**
```bash
docker-compose -f compose.prod.yaml up -d --build
```

**Update running containers:**
```bash
docker-compose -f compose.prod.yaml pull
docker-compose -f compose.prod.yaml up -d
```

**View production logs:**
```bash
docker-compose -f compose.prod.yaml logs -f drupal
```

## 🚀 GitHub Actions CI/CD

### Setup GitHub Actions

1. **Enable GitHub Container Registry:**
   - Go to your repository settings
   - Enable "Packages" in features

2. **Configure Secrets (if deploying):**
   - Go to Settings → Secrets → Actions
   - Add required secrets:
     ```
     SSH_PRIVATE_KEY      # SSH key for deployment
     DEPLOY_HOST          # Production server hostname
     DEPLOY_USER          # SSH username
     STAGING_HOST         # Staging server hostname (optional)
     ```

3. **Customize Workflow:**
   - Edit `.github/workflows/docker-build.yml`
   - Update `REGISTRY` and deployment steps to match your infrastructure

### Workflow Triggers

- **Push to main branch** → Build and deploy to production
- **Push to develop branch** → Build and deploy to staging
- **Pull Request** → Build and test only
- **Tag with v*** → Build and deploy to production with version tag

### Using Pre-built Images

To use images built by GitHub Actions instead of building locally:

```yaml
# In compose.prod.yaml, comment out build section and use:
services:
  drupal:
    image: ghcr.io/your-username/your-repo:latest
    # build:
    #   context: .
    #   ...
```

## 🎨 Customization Guide

### For Each New Drupal Site

**Minimum changes required:**

1. **Copy template files** to your Drupal project
2. **Edit `.env`:**
   ```bash
   PROJECT_NAME=your-new-site
   DB_NAME=new_database
   DB_USER=new_user
   DB_PASSWORD=new_secure_password
   ```
3. **Run:** `docker-compose up -d --build`

### PHP Version Changes

Change in `.env`:
```bash
PHP_VERSION=8.2
```

### Additional PHP Extensions

Edit `Dockerfile` base stage:
```dockerfile
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    mariadb-client \
    # ADD YOUR PACKAGES HERE
    imagemagick \
    libmagickwand-dev \
    && docker-php-ext-install imagick \
    && rm -rf /var/lib/apt/lists/*
```

### Custom Volume Mounts

Add to `compose.yaml`:
```yaml
volumes:
  - ./custom-directory:/app/custom-directory:rw
```

### Different Database Versions

Change in `compose.yaml` and `compose.prod.yaml`:
```yaml
db:
  image: mysql:8.0  # or mariadb:10.11, postgres:15
```

## 🔍 Troubleshooting

### Port Already in Use

Change ports in `.env`:
```bash
WEB_PORT=8081
DB_PORT=3307
```

### Permission Denied Errors (Development)

```bash
# Fix file permissions
docker-compose exec drupal chown -R application:application /app/web/sites/default/files
docker-compose exec drupal chmod -R 777 /app/web/sites/default/files
```

### Composer Install Fails

```bash
# Run composer install manually
docker-compose exec drupal composer install
```

### Database Connection Errors

Check your `.env` file:
```bash
DB_HOST=db              # Must match service name in compose.yaml
DB_PORT_INTERNAL=3306   # Internal port (always 3306 for MySQL)
```

### Clear All Docker Data (Fresh Start)

```bash
# Stop everything
docker-compose down -v

# Remove all project containers and volumes
docker-compose rm -f
docker volume prune -f

# Rebuild from scratch
docker-compose up -d --build
```

### View Container Environment Variables

```bash
docker-compose exec drupal env
```

### Check PHP Configuration

```bash
docker-compose exec drupal php -i | grep memory_limit
docker-compose exec drupal php -i | grep opcache
```

## 📚 Advanced Usage

### Using with Drush

Install Drush in your project:
```bash
composer require drush/drush
```

Then use it:
```bash
docker-compose exec drupal vendor/bin/drush status
docker-compose exec drupal vendor/bin/drush cache:rebuild
```

### Database Backups

**Export database:**
```bash
docker-compose exec db mysqldump -u${DB_USER} -p${DB_PASSWORD} ${DB_NAME} > backup.sql
```

**Import database:**
```bash
docker-compose exec -T db mysql -u${DB_USER} -p${DB_PASSWORD} ${DB_NAME} < backup.sql
```

### Multi-site Setup

1. Add additional volume mounts for each site
2. Configure Drupal's `sites/sites.php`
3. Adjust `DRUPAL_TRUSTED_HOSTS` in `.env`

## 🤝 Contributing

This is a template repository. Feel free to:
- Fork for your own projects
- Customize for your specific needs
- Share improvements back to the community

## 📄 License

This template is provided as-is for use in your projects. Modify as needed.

## 🔗 Resources

- [Drupal Documentation](https://www.drupal.org/docs)
- [Docker Documentation](https://docs.docker.com/)
- [Webdevops Docker Images](https://dockerfile.readthedocs.io/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)

---

**Ready to dockerize your next Drupal site?** Copy, configure, and deploy! 🚀
