# 📋 QUICK START CHECKLIST

Use this checklist when setting up a new Drupal site with this Docker template.

## ✅ Initial Setup (5 minutes)

### 1. Copy Template Files
```bash
□ Copy Dockerfile
□ Copy compose.yaml  
□ Copy compose.prod.yaml
□ Copy .dockerignore
□ Copy .env.example
□ Copy .github/workflows/docker-build.yml (optional, for CI/CD)
```

### 2. Create Environment File
```bash
# Standard approach (single file):
□ cp .env.example .env

# OR separate files approach (optional):
□ cp dev.env.example dev.env
□ cp prd.env.example prd.env
```

### 3. Customize .env File (REQUIRED!)

**Essential Changes:**
```bash
□ PROJECT_NAME=__________ (your-project-name, no spaces)
□ DB_NAME=__________ (database name)
□ DB_USER=__________ (database user)
□ DB_PASSWORD=__________ (STRONG password!)
□ DB_ROOT_PASSWORD=__________ (STRONG root password!)
```

**Optional Changes:**
```bash
□ PHP_VERSION=8.3 (or 8.1, 8.2)
□ WEB_PORT=8080 (change if port in use)
□ DB_PORT=3306 (change if port in use)
```

### 4. Start Development
```bash
□ docker-compose up -d --build
□ Open http://localhost:8080 (or your WEB_PORT)
```

---

## 🚀 Deployment Checklist

### For Production Deployment

```bash
□ Review all .env values
□ Change all passwords to strong values
□ Set ENVIRONMENT=production
□ Verify config/ directory contains exported configuration
□ Build: docker-compose -f compose.prod.yaml up -d --build
```

---

## 🔧 GitHub Actions Setup

### If Using CI/CD

```bash
□ Push template files to GitHub repository
□ Enable GitHub Packages in repository settings
□ Add deployment secrets (if deploying):
  □ SSH_PRIVATE_KEY
  □ DEPLOY_HOST
  □ DEPLOY_USER
  □ STAGING_HOST (optional)
□ Customize .github/workflows/docker-build.yml:
  □ Update REGISTRY (if not using ghcr.io)
  □ Update deployment commands
  □ Update deployment paths
```

---

## 📝 What to Change for Each New Site

### Minimum Required:
1. `.env` → PROJECT_NAME, DB_*, passwords
2. That's it! Everything else is optional.

### Optional Customizations:
- `Dockerfile` → Add PHP extensions, packages
- `compose.yaml` → Add volume mounts, services
- `.github/workflows/docker-build.yml` → Deployment steps

---

## 🔍 Quick Commands Reference

### Development
```bash
# Start
docker-compose up -d --build

# Logs
docker-compose logs -f drupal

# Shell access
docker-compose exec drupal bash

# Drush
docker-compose exec drupal drush status

# Stop
docker-compose down
```

### Production
```bash
# Start
docker-compose -f compose.prod.yaml up -d --build

# Logs
docker-compose -f compose.prod.yaml logs -f drupal

# Update
docker-compose -f compose.prod.yaml pull
docker-compose -f compose.prod.yaml up -d

# Stop
docker-compose -f compose.prod.yaml down
```

---

## ⚡ Common Issues

| Issue | Solution |
|-------|----------|
| Port already in use | Change WEB_PORT or DB_PORT in .env |
| Permission denied | `docker-compose exec drupal chmod -R 777 /app/web/sites/default/files` |
| Database connection fails | Check DB_HOST=db in .env |
| Composer fails | `docker-compose exec drupal composer install` |

---

## 📚 Full Documentation

See [DOCKER_SETUP.md](DOCKER_SETUP.md) for complete documentation.

---

**Time to dockerize: ~5 minutes** ⏱️
