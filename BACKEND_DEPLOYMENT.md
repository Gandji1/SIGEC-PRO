# SIGEC Backend Deployment Guide

## Problem
- Frontend (Vite) deployed on Vercel ✅
- Backend (Laravel) NOT deployed ❌
- APIs unreachable → features broken

## Solution: Deploy Backend

### Option 1: Railway (Recommended - Simplest)
1. Go to https://railway.app
2. Sign in with GitHub
3. New Project → Deploy from GitHub
4. Select SIGEC repo
5. Configure environment:
   - SERVICE_DIR: `backend`
   - Start command: `php artisan serve`
6. Add PostgreSQL plugin
7. Set env vars (from .env.production)
8. Deploy

Railway auto-scales, has free tier credits.

### Option 2: Fly.io
1. Install flyctl: `curl -L https://fly.io/install.sh | sh`
2. `fly auth login`
3. Create fly.toml in backend/:

```toml
app = "sigec-api"
primary_region = "cdg"  # Paris

[env]
NODE_ENV = "production"

[build]
builder = "dockerfile"

[http_service]
internal_port = 8000

[[services]]
ports = [{ handlers = ["http"], port = 80 }]
ports = [{ handlers = ["tls", "http"], port = 443 }]
```

4. `fly deploy`

### Option 3: Docker + Heroku
```bash
# Build image
docker build -t sigec-api backend/

# Deploy to Heroku
heroku container:push web --app sigec-api
heroku container:release web --app sigec-api
```

### Environment Variables (All Options)
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sigec-api.railway.app
DB_CONNECTION=pgsql
DB_HOST=your-postgres-host
DB_PORT=5432
DB_DATABASE=sigec_prod
DB_USERNAME=postgres
DB_PASSWORD=xxx
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_FROM_ADDRESS=noreply@sigec.app
```

## Local Testing (Before Deploy)

```bash
cd /workspaces/SIGEC/backend

# Start Laravel dev server
php artisan serve

# In another terminal, test
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Test User",
    "email":"test@example.com", 
    "password":"password",
    "password_confirmation":"password"
  }'
```

## Frontend Configuration

Update `frontend/src/services/apiClient.js`:

```javascript
const API_BASE_URL = process.env.REACT_APP_API_URL || 
  (window.location.hostname === 'localhost' 
    ? 'http://localhost:8000/api'
    : 'https://sigec-api.railway.app/api');
```

## Monitoring
- Railway Dashboard: https://railway.app/dashboard
- Fly Dashboard: https://fly.io/dashboard
- Backend Health: GET /health

---

**PRIORITY: Deploy backend FIRST before building RBAC**
