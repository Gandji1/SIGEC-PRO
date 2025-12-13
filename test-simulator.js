/**
 * SIGEC - Simulateur Web pour Tests
 * Permet de tester toutes les fonctionnalités sans Docker
 * Accès: http://localhost:3000
 */

const express = require('express');
const app = express();
const PORT = process.env.PORT || 8080;

app.use(express.json());
app.use(express.static('public'));

// ============ DONNÉES EN MÉMOIRE ============
const database = {
  tenants: [
    {
      id: 1,
      name: 'Pharmacie Du Coin',
      business_type: 'pharmacy',
      currency: 'USD',
      accounting_setup_complete: true,
    }
  ],
  users: [
    {
      id: 1,
      tenant_id: 1,
      name: 'Admin',
      email: 'admin@demo.local',
      password: 'password',
      role: 'admin',
    }
  ],
  chartOfAccounts: [
    // ACTIFS
    { id: 1, tenant_id: 1, code: '1010', name: 'Caisse', type: 'asset', category: 'current_asset' },
    { id: 2, tenant_id: 1, code: '1020', name: 'Banque', type: 'asset', category: 'current_asset' },
    { id: 3, tenant_id: 1, code: '1030', name: 'Clients Assurance', type: 'asset', category: 'current_asset' },
    { id: 4, tenant_id: 1, code: '1040', name: 'Stock Médicaments', type: 'asset', category: 'current_asset' },
    
    // PASSIFS
    { id: 5, tenant_id: 1, code: '2010', name: 'Fournisseurs', type: 'liability', category: 'current_liability' },
    { id: 6, tenant_id: 1, code: '2020', name: 'TVA à payer', type: 'liability', category: 'current_liability' },
    
    // CAPITAUX PROPRES
    { id: 7, tenant_id: 1, code: '3000', name: 'Capital', type: 'equity', category: 'capital' },
    
    // REVENUS
    { id: 8, tenant_id: 1, code: '4100', name: 'Ventes Médicaments', type: 'revenue', category: 'revenue' },
    { id: 9, tenant_id: 1, code: '4110', name: 'Ventes Accessoires', type: 'revenue', category: 'revenue' },
    
    // DÉPENSES
    { id: 10, tenant_id: 1, code: '5000', name: 'COGS Médicaments', type: 'expense', category: 'cost_of_sales' },
    { id: 11, tenant_id: 1, code: '6000', name: 'Salaires', type: 'expense', category: 'operating_expense' },
    { id: 12, tenant_id: 1, code: '6100', name: 'Loyer', type: 'expense', category: 'operating_expense' },
  ],
  products: [
    { id: 1, tenant_id: 1, name: 'Amoxicilline 500mg', barcode: 'AMX500', price: 5.50, quantity: 150, reorder_level: 50 },
    { id: 2, tenant_id: 1, name: 'Paracétamol 500mg', barcode: 'PAR500', price: 2.00, quantity: 300, reorder_level: 100 },
    { id: 3, tenant_id: 1, name: 'Bandage Stérile', barcode: 'BND001', price: 1.50, quantity: 500, reorder_level: 100 },
    { id: 4, tenant_id: 1, name: 'Seringues 10ml', barcode: 'SYR010', price: 0.50, quantity: 1000, reorder_level: 200 },
  ],
  stocks: [
    { id: 1, product_id: 1, warehouse: 'main', available: 150, reserved: 10, total: 160 },
    { id: 2, product_id: 2, warehouse: 'main', available: 300, reserved: 20, total: 320 },
    { id: 3, product_id: 3, warehouse: 'main', available: 500, reserved: 0, total: 500 },
    { id: 4, product_id: 4, warehouse: 'main', available: 1000, reserved: 0, total: 1000 },
  ],
  sales: [],
  saleItems: [],
  customers: [
    { id: 1, tenant_id: 1, name: 'Client Test', email: 'client@test.com', phone: '+2250123456789', credit_limit: 500, total_purchases: 0, total_payments: 0 },
  ],
  accountingEntries: [],
  auditLogs: [],
};

let salesIdCounter = 1;
let saleItemsIdCounter = 1;
let accountingEntryIdCounter = 1;
let auditLogIdCounter = 1;

// ============ ENDPOINTS API ============

// LOGIN
app.post('/api/login', (req, res) => {
  const { email, password } = req.body;
  const user = database.users.find(u => u.email === email && u.password === password);
  
  if (!user) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }
  
  res.json({
    token: 'test-token-' + user.id,
    user: { id: user.id, name: user.name, email: user.email, role: user.role },
    tenant: database.tenants[0],
  });
});

// DASHBOARD - Statistiques
app.get('/api/dashboard', (req, res) => {
  const sales = database.sales;
  const totalSales = sales.reduce((sum, s) => sum + s.total, 0);
  const completedSales = sales.filter(s => s.status === 'completed').length;
  const lowStockProducts = database.products.filter(p => p.quantity <= p.reorder_level).length;

  res.json({
    total_sales: totalSales,
    completed_sales: completedSales,
    pending_sales: sales.filter(s => s.status === 'pending').length,
    low_stock_products: lowStockProducts,
    total_revenue: totalSales,
    chart_data: {
      sales_7_days: [100, 150, 200, 180, 220, 250, 300],
      dates: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    }
  });
});

// PRODUITS - CRUD
app.get('/api/products', (req, res) => {
  res.json(database.products);
});

app.post('/api/products', (req, res) => {
  const product = {
    id: Math.max(...database.products.map(p => p.id), 0) + 1,
    tenant_id: 1,
    ...req.body,
  };
  database.products.push(product);
  
  // Auto-créer stock
  database.stocks.push({
    id: Math.max(...database.stocks.map(s => s.id), 0) + 1,
    product_id: product.id,
    warehouse: 'main',
    available: req.body.quantity || 0,
    reserved: 0,
    total: req.body.quantity || 0,
  });
  
  // Log audit
  logAudit('product_created', 'Product', product.id);
  
  res.status(201).json(product);
});

app.put('/api/products/:id', (req, res) => {
  const product = database.products.find(p => p.id === parseInt(req.params.id));
  if (!product) return res.status(404).json({ error: 'Not found' });
  
  Object.assign(product, req.body);
  logAudit('product_updated', 'Product', product.id);
  res.json(product);
});

app.delete('/api/products/:id', (req, res) => {
  const idx = database.products.findIndex(p => p.id === parseInt(req.params.id));
  if (idx === -1) return res.status(404).json({ error: 'Not found' });
  
  const product = database.products[idx];
  database.products.splice(idx, 1);
  logAudit('product_deleted', 'Product', product.id);
  res.json({ message: 'Deleted' });
});

// STOCKS - Gestion inventaire
app.get('/api/stocks', (req, res) => {
  const stocks = database.stocks.map(s => {
    const product = database.products.find(p => p.id === s.product_id);
    return {
      ...s,
      product_name: product?.name,
      barcode: product?.barcode,
      price: product?.price,
    };
  });
  res.json(stocks);
});

app.get('/api/stocks/summary', (req, res) => {
  const total = database.stocks.reduce((sum, s) => sum + s.total, 0);
  const reserved = database.stocks.reduce((sum, s) => sum + s.reserved, 0);
  const lowStock = database.stocks.filter(s => {
    const product = database.products.find(p => p.id === s.product_id);
    return s.available <= (product?.reorder_level || 50);
  }).length;

  res.json({
    total_items: total,
    reserved_items: reserved,
    available_items: total - reserved,
    low_stock_count: lowStock,
    categories: [
      { name: 'Médicaments', count: 2, value: 450 },
      { name: 'Accessoires', count: 2, value: 1500 },
    ]
  });
});

app.post('/api/stocks/adjust', (req, res) => {
  const { product_id, quantity, reason } = req.body;
  const stock = database.stocks.find(s => s.product_id === product_id);
  if (!stock) return res.status(404).json({ error: 'Stock not found' });

  const oldQty = stock.available;
  stock.available += quantity;
  stock.total = stock.available + stock.reserved;

  logAudit('stock_adjusted', 'Stock', stock.id, { old: oldQty, new: stock.available, reason });
  res.json(stock);
});

// VENTES - POS et CRUD
app.post('/api/sales', (req, res) => {
  const sale = {
    id: salesIdCounter++,
    tenant_id: 1,
    reference: 'SALE-' + Date.now(),
    customer_id: req.body.customer_id || 1,
    items: [],
    subtotal: 0,
    tax: 0,
    total: 0,
    status: 'pending',
    payment_method: req.body.payment_method || 'cash',
    created_at: new Date(),
    ...req.body,
  };
  database.sales.push(sale);
  logAudit('sale_created', 'Sale', sale.id);
  res.status(201).json(sale);
});

app.post('/api/sales/:id/add-item', (req, res) => {
  const sale = database.sales.find(s => s.id === parseInt(req.params.id));
  if (!sale) return res.status(404).json({ error: 'Sale not found' });

  const product = database.products.find(p => p.id === req.body.product_id);
  if (!product) return res.status(404).json({ error: 'Product not found' });

  const item = {
    id: saleItemsIdCounter++,
    sale_id: sale.id,
    product_id: product.id,
    product_name: product.name,
    quantity: req.body.quantity,
    unit_price: product.price,
    subtotal: product.price * req.body.quantity,
  };

  sale.items.push(item);
  database.saleItems.push(item);

  // Recalculer totaux
  calculateSaleTotals(sale);
  logAudit('sale_item_added', 'SaleItem', item.id);
  res.json(sale);
});

app.post('/api/sales/:id/complete', (req, res) => {
  const sale = database.sales.find(s => s.id === parseInt(req.params.id));
  if (!sale) return res.status(404).json({ error: 'Sale not found' });

  sale.status = 'completed';
  sale.completed_at = new Date();

  // Déduire stock automatiquement
  sale.items.forEach(item => {
    const stock = database.stocks.find(s => s.product_id === item.product_id);
    if (stock) {
      stock.available -= item.quantity;
      stock.total -= item.quantity;
    }
  });

  // Créer entrée comptable automatiquement
  createAccountingEntryForSale(sale);

  // Mettre à jour client
  const customer = database.customers.find(c => c.id === sale.customer_id);
  if (customer) {
    customer.total_purchases += sale.total;
  }

  logAudit('sale_completed', 'Sale', sale.id, { total: sale.total, items: sale.items.length });
  res.json(sale);
});

app.delete('/api/sales/:id/items/:itemId', (req, res) => {
  const sale = database.sales.find(s => s.id === parseInt(req.params.id));
  if (!sale) return res.status(404).json({ error: 'Sale not found' });

  const itemIdx = sale.items.findIndex(i => i.id === parseInt(req.params.itemId));
  if (itemIdx === -1) return res.status(404).json({ error: 'Item not found' });

  const item = sale.items[itemIdx];
  sale.items.splice(itemIdx, 1);
  calculateSaleTotals(sale);
  logAudit('sale_item_removed', 'SaleItem', item.id);
  res.json(sale);
});

// COMPTABILITÉ
app.get('/api/accounting/ledger', (req, res) => {
  res.json({
    entries: database.accountingEntries,
    total: database.accountingEntries.length,
  });
});

app.get('/api/accounting/trial-balance', (req, res) => {
  const balance = {};
  database.accountingEntries.forEach(entry => {
    if (!balance[entry.account_code]) {
      balance[entry.account_code] = { debit: 0, credit: 0 };
    }
    balance[entry.account_code].debit += entry.debit;
    balance[entry.account_code].credit += entry.credit;
  });

  res.json({ balance, total_debit: Object.values(balance).reduce((s, b) => s + b.debit, 0) });
});

app.get('/api/accounting/income-statement', (req, res) => {
  const revenues = database.accountingEntries
    .filter(e => database.chartOfAccounts.find(a => a.id === e.account_id)?.type === 'revenue')
    .reduce((sum, e) => sum + e.credit, 0);

  const expenses = database.accountingEntries
    .filter(e => database.chartOfAccounts.find(a => a.id === e.account_id)?.type === 'expense')
    .reduce((sum, e) => sum + e.debit, 0);

  res.json({
    revenues,
    expenses,
    net_income: revenues - expenses,
  });
});

// PLAN COMPTABLE
app.get('/api/chart-of-accounts', (req, res) => {
  res.json(database.chartOfAccounts);
});

app.post('/api/chart-of-accounts/initialize', (req, res) => {
  const { business_type } = req.body;
  const tenant = database.tenants[0];
  
  if (tenant.accounting_setup_complete) {
    return res.json({ message: 'Already initialized', accounts_count: database.chartOfAccounts.length });
  }

  tenant.business_type = business_type;
  tenant.accounting_setup_complete = true;
  logAudit('chart_initialized', 'ChartOfAccounts', 1);

  res.status(201).json({
    message: 'Chart of accounts initialized',
    accounts_count: database.chartOfAccounts.length,
  });
});

// AUDIT LOGS
app.get('/api/audit-logs', (req, res) => {
  res.json(database.auditLogs.slice(-50).reverse());
});

// ============ FONCTIONS UTILITAIRES ============

function calculateSaleTotals(sale) {
  sale.subtotal = sale.items.reduce((sum, item) => sum + item.subtotal, 0);
  sale.tax = sale.subtotal * 0.10; // 10% TVA
  sale.total = sale.subtotal + sale.tax;
}

function createAccountingEntryForSale(sale) {
  // Débit Banque/Caisse, Crédit Ventes
  const revenueAccount = database.chartOfAccounts.find(a => a.code === '4100');
  const cashAccount = database.chartOfAccounts.find(a => a.code === '1010');

  if (revenueAccount && cashAccount) {
    database.accountingEntries.push({
      id: accountingEntryIdCounter++,
      tenant_id: 1,
      account_id: cashAccount.id,
      account_code: cashAccount.code,
      account_name: cashAccount.name,
      debit: sale.total,
      credit: 0,
      posting_date: new Date(),
      posted: true,
      description: 'Vente #' + sale.id,
    });

    database.accountingEntries.push({
      id: accountingEntryIdCounter++,
      tenant_id: 1,
      account_id: revenueAccount.id,
      account_code: revenueAccount.code,
      account_name: revenueAccount.name,
      debit: 0,
      credit: sale.total,
      posting_date: new Date(),
      posted: true,
      description: 'Vente #' + sale.id,
    });
  }
}

function logAudit(action, resourceType, resourceId, changes = {}) {
  database.auditLogs.push({
    id: auditLogIdCounter++,
    tenant_id: 1,
    user_id: 1,
    action,
    resource_type: resourceType,
    resource_id: resourceId,
    changes,
    created_at: new Date(),
  });
}

// ============ DÉMARRAGE ============

app.listen(PORT, () => {
  console.log(`✅ SIGEC Simulator started at http://localhost:${PORT}`);
  console.log(`\n📱 Interface: http://localhost:${PORT}`);
  console.log(`🔌 API: http://localhost:${PORT}/api\n`);
});
