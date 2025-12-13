# SIGEC - Complete Setup & Development Guide

## 🎯 Quick Start

### Prerequisites
- Docker & Docker Compose
- Git
- Node.js 20+ (for frontend development)
- PHP 8.2+ (for backend development)

### Launch (3 steps)
```bash
# 1. Clone and enter directory
git clone <repo-url>
cd SIGEC

# 2. Start all services
docker-compose up -d

# 3. Access applications
# Frontend: http://localhost:5173
# Backend API: http://localhost:8000
# pgAdmin: http://localhost:5050
```

## 📊 Project Structure

```
SIGEC/
├── backend/              # Laravel 11 API
│   ├── app/
│   │   ├── Domains/     # Domain-driven design
│   │   ├── Models/      # Eloquent models
│   │   └── Http/        # Controllers & middleware
│   ├── database/
│   │   ├── migrations/  # Database structure
│   │   └── factories/   # Test data generators
│   └── routes/          # API routes
├── frontend/             # React 18 + Vite
│   ├── src/
│   │   ├── pages/       # Page components
│   │   ├── components/  # Reusable components
│   │   ├── services/    # API & offline sync
│   │   └── stores/      # Zustand state management
│   └── index.html       # Entry point
└── infra/
    └── docker-compose.yml  # Container orchestration
```

## 🛠️ Backend Development

### Database Setup
```bash
# Run migrations
docker-compose exec app php artisan migrate

# Seed sample data
docker-compose exec app php artisan db:seed

# Reset database
docker-compose exec app php artisan migrate:fresh --seed
```

### Running Tests
```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test file
docker-compose exec app php artisan test tests/Feature/AuthTest.php

# Run with coverage
docker-compose exec app php artisan test --coverage
```

### Common Commands
```bash
# Create new migration
docker-compose exec app php artisan make:migration create_table_name

# Create model with migration
docker-compose exec app php artisan make:model ModelName -m

# Create controller
docker-compose exec app php artisan make:controller Api/ControllerName

# Create service class
docker-compose exec app php artisan make:class Domains/Domain/Services/ServiceName

# Clear cache
docker-compose exec app php artisan cache:clear
```

### API Documentation

#### Authentication
- **POST** `/api/register` - Register new business
- **POST** `/api/login` - User login
- **GET** `/api/me` - Get current user
- **POST** `/api/logout` - User logout
- **POST** `/api/change-password` - Change password

#### Products
- **GET** `/api/products` - List all products
- **POST** `/api/products` - Create product
- **GET** `/api/products/{id}` - Get product details
- **PUT** `/api/products/{id}` - Update product
- **DELETE** `/api/products/{id}` - Delete product
- **GET** `/api/products/low-stock` - Get low stock items
- **GET** `/api/products/barcode/{barcode}` - Search by barcode

#### Sales
- **GET** `/api/sales` - List all sales
- **POST** `/api/sales` - Create sale
- **GET** `/api/sales/{id}` - Get sale details
- **POST** `/api/sales/{id}/complete` - Complete sale
- **POST** `/api/sales/{id}/cancel` - Cancel sale
- **GET** `/api/sales/report` - Sales report

#### Exports
- **GET** `/api/export/sales/excel` - Export sales to Excel
- **GET** `/api/export/sales/pdf` - Export sales to PDF
- **GET** `/api/export/sales/{id}/invoice` - Generate invoice
- **GET** `/api/export/sales/{id}/receipt` - Generate receipt

#### Payments (Stripe)
- **POST** `/api/payments/intent` - Create payment intent
- **POST** `/api/payments/confirm` - Confirm payment
- **POST** `/api/payments/refund` - Refund payment

## 🎨 Frontend Development

### Starting Dev Server
```bash
# The dev server is already running in Docker on port 5173
# Direct access: http://localhost:5173

# Or run locally
cd frontend
npm install
npm run dev
```

### Building for Production
```bash
cd frontend
npm run build
# Output: dist/ folder
```

### Project Structure

**Pages** (`src/pages/`)
- `LoginPage.jsx` - Authentication (login/register)
- `DashboardPage.jsx` - Main dashboard with stats
- `POSPage.jsx` - Point of Sale interface
- `ProductsPage.jsx` - Product management (to create)
- `SalesPage.jsx` - Sales history (to create)
- `PurchasesPage.jsx` - Purchase orders (to create)
- `ReportsPage.jsx` - Analytics & reports (to create)

**Components** (`src/components/`)
- `Layout.jsx` - Main layout wrapper
- Other components TBD

**Services** (`src/services/`)
- `apiClient.js` - Axios HTTP client with interceptors
- `offlineSync.js` - IndexedDB offline synchronization

**Stores** (`src/stores/`)
- `tenantStore.js` - Zustand state management

### Adding New Page
```jsx
// src/pages/NewPage.jsx
import React from 'react';
import Layout from '../components/Layout';

export default function NewPage() {
  return (
    <Layout>
      <div className="p-6">
        {/* Your content */}
      </div>
    </Layout>
  );
}
```

Then add route in `App.jsx`:
```jsx
<Route
  path="/new-page"
  element={
    <PrivateRoute>
      <NewPage />
    </PrivateRoute>
  }
/>
```

### Styling with Tailwind
The project uses Tailwind CSS. Add classes directly to elements:
```jsx
<div className="bg-blue-600 text-white p-6 rounded-lg">
  Content
</div>
```

## 📱 Offline Features

The frontend supports offline POS operations using IndexedDB:

```javascript
import { OfflineSyncService } from './services/offlineSync';

// Save sale when offline
await OfflineSyncService.savePendingSale({
  items: [...],
  total: 50000,
  payment_method: 'cash'
});

// Sync when back online
OfflineSyncService.onOnlineStatusChange(async (isOnline) => {
  if (isOnline) {
    await OfflineSyncService.syncPendingSales(apiClient);
  }
});
```

## 🧪 Testing

### Backend Tests
```bash
# Run all tests
docker-compose exec app php artisan test

# Run feature tests
docker-compose exec app php artisan test tests/Feature/

# Watch mode
docker-compose exec app php artisan test --watch
```

### Frontend Tests (to implement)
```bash
cd frontend
npm install
npm run test
```

## 🚀 Deployment

### Development Environment
```bash
docker-compose up -d
```

### Production Deployment

#### Using Docker
```bash
# Build images
docker build -t sigec-backend ./backend
docker build -t sigec-frontend ./frontend

# Use docker-compose with prod settings
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

#### Using VPS (Linux/macOS)
```bash
./scripts/deploy.sh
```

#### Using VPS (Windows)
```powershell
.\scripts\deploy.ps1
```

See `docs/deployment-vps.md` for detailed VPS setup.

## 📚 Database Schema

### Core Tables
- `tenants` - Business accounts (multi-tenancy)
- `users` - System users
- `products` - Product catalog
- `stocks` - Inventory management
- `sales` - Sales transactions
- `sale_items` - Sale line items
- `purchases` - Purchase orders
- `purchase_items` - Purchase line items
- `transfers` - Stock transfers
- `transfer_items` - Transfer line items
- `accounting_entries` - General ledger
- `audit_logs` - System audit trail

## 🔐 Security

### API Protection
- Sanctum token-based authentication
- Automatic tenant isolation (X-Tenant-ID header)
- Role-based access control (admin, manager, staff)
- CORS configuration

### Environment Variables
Create `.env` file in backend root:
```
APP_NAME=SIGEC
APP_ENV=local
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=sigec
DB_USERNAME=postgres
DB_PASSWORD=password
STRIPE_SECRET=sk_test_...
```

## 📈 Monitoring & Logs

### Backend Logs
```bash
docker-compose exec app tail -f storage/logs/laravel.log
```

### Docker Logs
```bash
docker-compose logs -f app        # Backend
docker-compose logs -f frontend   # Frontend
docker-compose logs -f postgres   # Database
```

## 🆘 Troubleshooting

### Port Already in Use
```bash
# Find process using port 8000
lsof -i :8000

# Kill process
kill -9 <PID>
```

### Database Connection Error
```bash
# Check PostgreSQL is running
docker-compose ps

# Restart database
docker-compose restart postgres
```

### Frontend Not Loading
```bash
# Clear cache
docker-compose exec frontend npm cache clean --force

# Restart frontend
docker-compose restart frontend
```

## 📞 Support & Contact

For issues or questions:
1. Check `docs/TROUBLESHOOTING.md`
2. Check `FAQ.md`
3. Open GitHub issue

---

**Last Updated**: December 2024  
**Version**: 1.0.0-beta.1  
**License**: MIT
