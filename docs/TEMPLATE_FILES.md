# 📦 TEMPLATE FILES CHECKLIST

## Files to Copy for New Drupal Projects

When setting up a new Drupal site with this Docker template, copy these files:

### ✅ Required Files

| File | Purpose | Must Customize? |
|------|---------|----------------|
| `Dockerfile` | Multi-stage Docker build definition | ❌ No (ready to use) |
| `compose.yaml` | Development environment config | ❌ No (uses .env) |
| `compose.prod.yaml` | Production environment config | ❌ No (uses .env) |
| `.env.example` | Template for environment variables | ✅ Yes → copy to `.env` |
| `dev.env.example` | Development-specific env template (optional) | ⚪ Optional → copy to `dev.env` |
| `prd.env.example` | Production-specific env template (optional) | ⚪ Optional → copy to `prd.env` |
| `.dockerignore` | Exclude files from Docker builds | ❌ No (ready to use) |
| `web/sites/default/settings.local.php` | Local Drupal settings overrides | ⚪ Optional |

### 📄 Documentation Files (Optional)

| File | Purpose |
|------|---------|
| `DOCKER_SETUP.md` | Complete setup and usage guide |
| `QUICK_START.md` | Quick reference checklist |
| `TEMPLATE_FILES.md` | This file |

### 🤖 CI/CD Files (Optional)

| File | Purpose | Must Customize? |
|------|---------|----------------|
| `.github/workflows/docker-build.yml` | GitHub Actions workflow | ✅ Yes (deployment steps) |

### 🔧 Helper Scripts (Optional)

| File | Purpose | Must Customize? |
|------|---------|----------------|
| `init-docker.sh` | Interactive setup script | ❌ No (ready to use) |

---

## 🎯 Minimum Setup (3 files + 1 config)

For the quickest setup, you only need:

```
1. Dockerfile
2. compose.yaml
3. .dockerignore
4. .env (created from .env.example)
5. settings.local.php
```

That's it! Everything else is optional documentation and tooling.

---

## 📋 Copy Commands

### Quick Copy to New Project

```bash
# Navigate to where you want to clone template
cd /path/to/template-parent-folder
# Clone the template
git clone <template-repo-url> docker-drupal-template

# Navigate to your new Drupal project
cd /path/to/new-drupal-site

# Copy essential files from template
cp /path/to/template/Dockerfile .
cp /path/to/template/compose.yaml .
cp /path/to/template/compose.prod.yaml .
cp /path/to/template/.dockerignore .
cp /path/to/template/.env.example .
cp /path/to/template/settings.local.php ./web/sites/default/

# Optional: Copy documentation
cp /path/to/template/DOCKER_SETUP.md .
cp /path/to/template/QUICK_START.md .

# Optional: Copy CI/CD
mkdir -p .github/workflows
cp /path/to/template/.github/workflows/docker-build.yml .github/workflows/

# Optional: Copy init script
cp /path/to/template/init-docker.sh .
chmod +x init-docker.sh
```
---

## 🔧 What Needs Customization?

### For Every New Site:

**Only `.env` file needs customization:**
- `PROJECT_NAME` - Your project identifier
- `DB_NAME` - Database name
- `DB_USER` - Database username  
- `DB_PASSWORD` - Database password
- `DB_ROOT_PASSWORD` - Root password

**Everything else works out of the box!**

### Optional Customizations:

- **Dockerfile**: Add PHP extensions or system packages
- **compose.yaml/compose.prod.yaml**: Add additional services (Redis, Solr, etc.)
- **GitHub Actions**: Update deployment steps and servers

---

## 🚀 After Copying Files

### Method 1: Use Init Script 

```bash
./init-docker.sh
```

This interactive script will:
- Guide you through configuration
- Create your .env file
- Optionally start containers

### Method 2: Manual Setup (Recommended for now)

```bash
# Create environment file
cp .env.example .env

# Edit configuration
nano .env  # or vim, code, etc.

# Start development
docker-compose up -d --build
```

---

## 📊 File Sizes (Reference)

```
Dockerfile           ~4 KB
compose.yaml         ~2 KB
compose.prod.yaml    ~3 KB
.dockerignore        ~1 KB
.env.example         ~3 KB
init-docker.sh       ~5 KB
DOCKER_SETUP.md      ~15 KB
GitHub workflow      ~7 KB
----------------------------
Total (required)     ~10 KB
Total (all)          ~40 KB
```

Very lightweight! 🎈

---

### Versioning & Releases (recommended but optional)

Keep a small VERSION/TEMPLATE_VERSION.txt in the template root to record releases and drive formal releases (tags / GitHub Releases). Prefer having both a one-line summary file for quick reference and a detailed CHANGELOG.md or GitHub release notes for full context.

Recommended simple format (TEMPLATE_VERSION.txt)
- Use semantic versions: vMAJOR.MINOR.PATCH
- One line per release: vX.Y.Z [YYYY-MM-DD] - short summary
- Pick one order (newest-first is common)

Example (newest first):
```text
# TEMPLATE_VERSION.txt
v1.2.0 2025-10-14 - Add init script
v1.1.0 2025-03-22 - Add GitHub Actions support
v1.0.0 2024-06-01 - Initial release
```

Minimal release workflow
1. Update TEMPLATE_VERSION.txt (add new line).
2. Commit:
    git add TEMPLATE_VERSION.txt && git commit -m "chore(release): bump template to vX.Y.Z"
3. Create an annotated Git tag and push:
    git tag -a vX.Y.Z -m "Release vX.Y.Z" && git push origin vX.Y.Z
4. Create a GitHub Release (draft) from the tag, add full release notes, migration steps, and mark as published.

Quick tooling options
- Use GitHub CLI:
  gh release create vX.Y.Z --title "vX.Y.Z" --notes-file RELEASE_NOTES.md
- Automate with Actions: trigger a workflow on push/tag to build artifacts and publish a release.

Notes on versioning
- Follow SemVer: increment MAJOR for breaking changes, MINOR for added features, PATCH for fixes.
- Use pre-release tags (vX.Y.Z-rc.1) when needed.

---

## 📞 Support

For issues or questions:
1. Check `DOCKER_SETUP.md` - Comprehensive documentation
2. Check `QUICK_START.md` - Quick reference
3. Create Github issue

---