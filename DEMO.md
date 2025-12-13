# 🎬 SIGEC DEMO - Live Testing Guide

## Quick Start (5 minutes)

### Terminal 1: Start Backend

```bash
cd /workspaces/SIGEC/backend

# Setup database
php artisan migrate --seed

# Start server
php artisan serve
# Output: Server running on http://localhost:8000
```

### Terminal 2: Run Demo Script

```bash
cd /workspaces/SIGEC

chmod +x test-demo.sh
./test-demo.sh
```

### What You'll See

The script will automatically:

1. ✅ **Register** a tenant (Restaurant Africa Demo, Mode B)
   - Creates 3 warehouses: Gros + Détail + POS
   - Returns auth token

2. ✅ **Login** with credentials
   - Email: admin@test.com
   - Password: password123

3. ✅ **Create Purchase**
   - Product 1: 100 units @ 5,000
   - Product 2: 50 units @ 8,000
   - Status: pending

4. ✅ **Confirm Purchase**
   - Transitions to confirmed

5. ✅ **Receive Purchase** (CMP Magic ✨)
   - Calculates Cost Moyen Pondéré (Average Cost)
   - Updates Stock with cost_average
   - Creates StockMovement audit trail
   - Formula: (old_qty × old_cmp + new_qty × new_price) / (old_qty + new_qty)

6. ✅ **Create Transfer Request**
   - Gros → Détail
   - Product 1: 30 units
   - Product 2: 20 units
   - Status: pending

7. ✅ **Approve & Execute Transfer**
   - Deducts from Gros warehouse
   - Adds to Détail warehouse
   - Updates quantities atomically

8. ✅ **Verify Stock Changes**
   - Shows final stock levels in each warehouse
   - Displays cost_average after transfers

9. ✅ **Transfer Statistics**
   - Shows counts by status

---

## Expected Output

```
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 1: REGISTER TENANT (Mode B - POS)
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Tenant créé: 1
✅ Token obtenu: 1|abc123def456...

Warehouses créés:
  • 1: gros (Gros)
  • 2: detail (Détail)
  • 3: pos (POS)

ℹ️  gros_warehouse_id=1, detail_warehouse_id=2, pos_warehouse_id=3

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 2: LOGIN
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Login successful
ℹ️  Using token: 1|abc123def456...

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 3: CREATE PURCHASE
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Purchase créé: 1
ℹ️  Status: pending

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 4: CONFIRM PURCHASE
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Purchase confirmé
ℹ️  Status: confirmed

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 5: RECEIVE PURCHASE (CMP Calculation)
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Purchase reçu
ℹ️  Status: received

Stock après réception:
{
  "product_id": 1,
  "quantity": 100,
  "cost_average": 5000
}
{
  "product_id": 2,
  "quantity": 50,
  "cost_average": 8000
}

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 6: CREATE TRANSFER REQUEST (Gros → Détail)
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Transfer créé: 1
ℹ️  Status: pending

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 7: APPROVE & EXECUTE TRANSFER
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Transfer approuvé et exécuté
ℹ️  Status: approved

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 8: VERIFY STOCK CHANGES
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Stock après transfert:

Warehouse GROS (1):
{
  "product_id": 1,
  "quantity": 70,           ← Was 100, -30 transferred
  "cost_average": 5000
}
{
  "product_id": 2,
  "quantity": 30,           ← Was 50, -20 transferred
  "cost_average": 8000
}

Warehouse DÉTAIL (2):
{
  "product_id": 1,
  "quantity": 30,           ← New: 30 received
  "cost_average": 5000      ← Same cost_average preserved
}
{
  "product_id": 2,
  "quantity": 20,           ← New: 20 received
  "cost_average": 8000
}

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 ÉTAPE 9: TRANSFER STATISTICS
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Statistics:
{
  "pending": 0,
  "approved": 1,
  "executed": 0,
  "cancelled": 0
}

🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔵 SUMMARY - DEMO COMPLETED ✅
🔵 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

All features tested:
  ✅ Auth: Register + Login (Mode B with 3 warehouses)
  ✅ Purchases: Create + Confirm + Receive (with CMP)
  ✅ Stock: Tracked with cost_average (Coût Moyen Pondéré)
  ✅ Transfers: Request + Approve + Execute
  ✅ Stock Movement: Audit trail created
```

---

## Manual Testing (Alternative)

If you prefer curl commands:

```bash
# 1. Register
TOKEN=$(curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "Test Restaurant",
    "name": "Admin",
    "email": "admin@test.com",
    "password": "password123",
    "password_confirmation": "password123",
    "mode_pos": "B"
  }' | jq -r '.token')

echo "Token: $TOKEN"

# 2. Create Purchase
curl -X POST http://localhost:8000/api/purchases \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "supplier_name": "Supplier ABC",
    "supplier_phone": "+229 1234567",
    "items": [
      {"product_id": 1, "quantity": 100, "unit_price": 5000}
    ]
  }' | jq '.'

# 3. Confirm Purchase (replace {id})
curl -X POST http://localhost:8000/api/purchases/1/confirm \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# 4. Receive Purchase
curl -X POST http://localhost:8000/api/purchases/1/receive \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"purchase_item_id": 1, "received_quantity": 100}
    ]
  }' | jq '.'

# 5. Check Stock
curl -X GET http://localhost:8000/api/stocks \
  -H "Authorization: Bearer $TOKEN" | jq '.data[] | {product_id, quantity, cost_average}'
```

---

## Verify Tests Locally

```bash
cd /workspaces/SIGEC/backend

# Run all tests
php artisan test

# Run specific test
php artisan test tests/Feature/PurchaseReceiveTest.php
php artisan test tests/Feature/TransferTest.php

# Expected:
# ✓ PurchaseReceiveTest: 7 tests passing
# ✓ TransferTest: 8 tests passing
```

---

## Database Inspection

```bash
# Open SQLite console
cd /workspaces/SIGEC/backend
sqlite3 database/database.sqlite

# View tables
.tables

# Check Stock table
SELECT * FROM stocks LIMIT 5;

# Check StockMovement audit trail
SELECT * FROM stock_movements ORDER BY created_at DESC LIMIT 10;

# Check Transfers
SELECT * FROM transfers;

# Exit
.quit
```

---

## Next Steps

After running the demo:

1. **Commit current progress**
   ```bash
   cd /workspaces/SIGEC
   git add -A
   git commit -m "feat: add live demo script"
   git push origin feature/sigec-complete
   ```

2. **Itération 3: POS & Sales**
   - Create SaleService with stock deduction
   - Implement PaymentService
   - Add SaleController endpoints
   - Write SaleTests
   - Create POS frontend page

3. **Expected Coverage**
   - Current: 55% (Auth + Purchases + Transfers)
   - After Itération 3: 70% (+ Sales + Payments)

---

## Troubleshooting

**Q: "Connection refused"**  
A: Make sure backend is running: `php artisan serve`

**Q: "401 Unauthorized"**  
A: Check TOKEN is correct and exported

**Q: "Database locked"**  
A: Kill existing processes: `pkill -f "php artisan"`

**Q: "CORS errors"**  
A: Check CORS config in `config/cors.php` allows localhost:5173

**Q: Tests fail**  
A: Run `php artisan migrate:fresh --seed` first

---

**Ready?** Run `./test-demo.sh` now! 🚀
