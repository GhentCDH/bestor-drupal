# 🔧 Environment Configuration Guide

This template supports flexible environment configuration with multiple approaches to suit different project needs.

## 📋 Available Options

### Option 1: Single `.env` File (Simple)

**Best for:** Simple projects, single developers, similar dev/prod configs

```bash
# Copy template
cp .env.example .env

# Edit as needed
nano .env

# Use for both dev and prod
docker-compose up -d                          # Development
docker-compose -f compose.prod.yaml up -d     # Production
```

**Pros:**
- ✅ Simple - one file to manage
- ✅ Easy to understand
- ✅ Good for most projects

**Cons:**
- ⚠️ Must manually switch settings for dev/prod
- ⚠️ Same credentials used in both environments (unless manually changed)

---

### Option 2: Separate `dev.env` and `prd.env` Files (Advanced)

**Best for:** Complex projects, different dev/prod configs

```bash
# Copy templates
cp dev.env.example dev.env
cp prd.env.example prd.env

# Edit each separately
nano dev.env    # Development settings
nano prd.env    # Production settings

# Use specific env files
docker-compose --env-file dev.env up -d                        # Development
docker-compose -f compose.prod.yaml --env-file prd.env up -d   # Production
```

**Pros:**
- ✅ Clear separation between environments
- ✅ Different credentials/settings for dev vs prod
- ✅ Less risk of using dev settings in prod
- ✅ Team members can have different dev settings

**Cons:**
- ⚠️ More files to manage
- ⚠️ Must remember to specify `--env-file`

---

## 🔐 Security Best Practices

Regardless of which option you choose:

### ✅ DO:
- Use strong, unique passwords for each environment
- Keep `.env`, `dev.env`, `prd.env` out of version control (in .gitignore❗️)
- Use different database passwords for dev vs prod
- Generate unique `DRUPAL_HASH_SALT` for production
- Review all settings before production deployment
- Use secrets management in production (Vault, etc.)

### ❌ DON'T:
- Commit real credentials to git
- Use same passwords in dev and prod
- Share `.env` files online
- Use weak passwords in production
- Leave default/example values in production

---

## 📝 Template Files Provided

| File | Purpose | Copy To |
|------|---------|---------|
| `.env.example` | Universal template | `.env` |
| `dev.env.example` | Development-specific template | `dev.env` |
| `prd.env.example` | Production-specific template | `prd.env` |

---

## 🚀 Quick Start Examples

### Example 1: Simple Setup (Single File)

```bash
# Initial setup
cp .env.example .env
nano .env  # Edit: PROJECT_NAME, DB_*, passwords

# Development
docker-compose up -d

# Production (same file, different compose)
docker-compose -f compose.prod.yaml up -d
```

### Example 2: Advanced Setup (Separate Files)

```bash
# Initial setup
cp dev.env.example dev.env
cp prd.env.example prd.env

# Edit dev.env
nano dev.env
# PROJECT_NAME=mysite
# DB_NAME=mysite_dev
# DB_PASSWORD=dev_password

# Edit prd.env
nano prd.env
# PROJECT_NAME=mysite
# DB_NAME=mysite_prod
# DB_PASSWORD=secure_prod_password_XYZ123

# Development
docker-compose --env-file dev.env up -d

# Production
docker-compose -f compose.prod.yaml --env-file prd.env up -d
```

---

### 🔄 From Single File to Separate Files

```bash
# You have: .env
# Create separate files
cp .env dev.env
cp .env prd.env

# Edit each file for their specific environments
nano dev.env  # Adjust for development
nano prd.env  # Adjust for production

# Use with explicit env-file flag
docker-compose --env-file dev.env up -d
```

---

## 📚 Additional Notes

### Environment Variable Priority

Docker Compose loads environment variables in this order (highest priority first):

1. Variables set in the shell
2. Variables defined in `--env-file`
3. Variables defined in `.env` file
4. Variables defined in `compose.yaml` (environment section)
5. Default values in `compose.yaml` (${VAR:-default})

### Using Interactive Setup Script

The `init-docker.sh` script supports both approaches:

```bash
./init-docker.sh

# Choose option 1 for single .env file
# Choose option 2 for separate dev.env/prd.env files
```

---

For more information, see:
- `DOCKER_SETUP.md` - Complete setup guide
- `QUICK_START.md` - Quick reference
- `.env.example` - All available configuration options
