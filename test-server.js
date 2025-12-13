const express = require('express');
const cors = require('cors');
const path = require('path');

const app = express();

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static('public'));

// ==================== BASE DE DONNÉES SIMULÉE ====================

const database = {
  tenants: [
    {
      id: 1,
      name: 'Demo Shop',
      business_type: 'retail',
      accounting_setup_complete: true,
      slug: 'demo-shop',
      currency: 'USD',
      status: 'active'
    }
  ],
  users: [
    {
      id: 1,
      tenant_id: 1,
      name: 'Admin User',
      email: 'admin@demo.local',
      password: 'password',
      role: 'admin'
    }
  ],
  chart_of_accounts: [
    // ACTIFS COURANTS
    { id: 1, tenant_id: 1, code: '1010', name: 'Caisse', type: 'asset', category: 'current_asset' },
    { id: 2, tenant_id: 1, code: '1020', name: 'Banque', type: 'asset', category: 'current_asset' },
    { id: 3, tenant_id: 1, code: '1030', name: 'Clients', type: 'asset', category: 'current_asset' },
    { id: 4, tenant_id: 1, code: '1040', name: 'Stock Marchandises', type: 'asset', category: 'current_asset' },
    // ACTIFS FIXES
    { id: 5, tenant_id: 1, code: '1500', name: 'Équipements', type: 'asset', category: 'fixed_asset' },
    // PASSIFS COURANTS
    { id: 6, tenant_id: 1, code: '2010', name: 'Fournisseurs', type: 'liability', category: 'current_liability' },
    { id: 7, tenant_id: 1, code: '2020', name: 'TVA à payer', type: 'liability', category: 'current_liability' },
    // CAPITAUX PROPRES
    { id: 8, tenant_id: 1, code: '3000', name: 'Capital', type: 'equity', category: 'capital' },
    // REVENUS
    { id: 9, tenant_id: 1, code: '4100', name: 'Ventes Marchandises', type: 'revenue', category: 'revenue' },
    { id: 10, tenant_id: 1, code: '4110', name: 'Services', type: 'revenue', category: 'revenue' },
    // DÉPENSES
    { id: 11, tenant_id: 1, code: '5000', name: 'COGS', type: 'expense', category: 'cost_of_sales' },
    { id: 12, tenant_id: 1, code: '6000', name: 'Salaires', type: 'expense', category: 'operating_expense' },
    { id: 13, tenant_id: 1, code: '6100', name: 'Loyer', type: 'expense', category: 'operating_expense' },
  ],
  accounting_entries: [
    { id: 1, tenant_id: 1, account_code: '1020', account_name: 'Banque', account_type: 'asset', debit: 5000, credit: 0, posting_date: '2025-11-20', posted: true },
    { id: 2, tenant_id: 1, account_code: '4100', account_name: 'Ventes Marchandises', account_type: 'revenue', debit: 0, credit: 1500, posting_date: '2025-11-21', posted: true },
    { id: 3, tenant_id: 1, account_code: '5000', account_name: 'COGS', account_type: 'expense', debit: 800, credit: 0, posting_date: '2025-11-21', posted: true },
    { id: 4, tenant_id: 1, account_code: '6000', account_name: 'Salaires', account_type: 'expense', debit: 500, credit: 0, posting_date: '2025-11-22', posted: false },
  ],
  sales: [
    { id: 1, tenant_id: 1, reference: 'SALE-001', customer_id: 1, total: 1500, status: 'completed', items_count: 3, created_at: '2025-11-21' },
    { id: 2, tenant_id: 1, reference: 'SALE-002', customer_id: 2, total: 800, status: 'completed', items_count: 2, created_at: '2025-11-22' },
  ],
  purchases: [
    { id: 1, tenant_id: 1, reference: 'PO-001', supplier_id: 1, total: 2500, status: 'received', items_count: 5, created_at: '2025-11-20' },
    { id: 2, tenant_id: 1, reference: 'PO-002', supplier_id: 2, total: 1200, status: 'confirmed', items_count: 3, created_at: '2025-11-22' },
  ],
  stocks: [
    { id: 1, tenant_id: 1, product_id: 1, warehouse: 'main', available: 450, reserved: 50, total: 500 },
    { id: 2, tenant_id: 1, product_id: 2, warehouse: 'main', available: 200, reserved: 20, total: 220 },
  ],
  products: [
    { id: 1, tenant_id: 1, name: 'Produit A', sku: 'SKU-001', price: 25.00, stock: 450 },
    { id: 2, tenant_id: 1, name: 'Produit B', sku: 'SKU-002', price: 15.00, stock: 200 },
    { id: 3, tenant_id: 1, name: 'Produit C', sku: 'SKU-003', price: 40.00, stock: 120 },
  ],
  customers: [
    { id: 1, tenant_id: 1, name: 'Client ABC', email: 'abc@example.com', total_purchases: 1500, total_payments: 1200 },
    { id: 2, tenant_id: 1, name: 'Client XYZ', email: 'xyz@example.com', total_purchases: 800, total_payments: 800 },
  ],
  suppliers: [
    { id: 1, tenant_id: 1, name: 'Fournisseur 1', email: 'supplier1@example.com', total_purchases: 2500, total_payments: 2000 },
    { id: 2, tenant_id: 1, name: 'Fournisseur 2', email: 'supplier2@example.com', total_purchases: 1200, total_payments: 1200 },
  ]
};

let authToken = 'test-token-12345';

// ==================== ROUTES AUTHENTIFICATION ====================

app.post('/api/login', (req, res) => {
  const { email, password } = req.body;
  const user = database.users.find(u => u.email === email && u.password === password);
  
  if (!user) {
    return res.status(401).json({ error: 'Identifiants invalides' });
  }
  
  const tenant = database.tenants.find(t => t.id === user.tenant_id);
  
  res.json({
    token: authToken,
    user: {
      id: user.id,
      name: user.name,
      email: user.email,
      tenant_id: user.tenant_id
    },
    tenant: {
      id: tenant.id,
      name: tenant.name,
      business_type: tenant.business_type,
      accounting_setup_complete: tenant.accounting_setup_complete
    }
  });
});

app.post('/api/register', (req, res) => {
  const { business_name, email, password, business_type } = req.body;
  
  const newTenant = {
    id: database.tenants.length + 1,
    name: business_name,
    business_type: business_type || 'retail',
    accounting_setup_complete: false,
    slug: business_name.toLowerCase().replace(/\s+/g, '-'),
    currency: 'USD',
    status: 'active'
  };
  
  const newUser = {
    id: database.users.length + 1,
    tenant_id: newTenant.id,
    name: email.split('@')[0],
    email: email,
    password: password,
    role: 'admin'
  };
  
  database.tenants.push(newTenant);
  database.users.push(newUser);
  
  res.status(201).json({
    message: 'Enregistrement réussi',
    token: authToken,
    tenant: newTenant
  });
});

// ==================== ROUTES PLAN COMPTABLE ====================

app.post('/api/chart-of-accounts/initialize', (req, res) => {
  const { business_type } = req.body;
  const tenant = database.tenants[0]; // Pour démo
  
  if (tenant.accounting_setup_complete) {
    return res.status(400).json({ error: 'Plan comptable déjà initialisé' });
  }
  
  tenant.accounting_setup_complete = true;
  tenant.business_type = business_type;
  
  res.status(201).json({
    message: 'Plan comptable initialisé avec succès',
    business_type: business_type,
    accounts_count: database.chart_of_accounts.filter(a => a.tenant_id === tenant.id).length
  });
});

app.get('/api/chart-of-accounts', (req, res) => {
  const type = req.query.type;
  let accounts = database.chart_of_accounts.filter(a => a.tenant_id === 1);
  
  if (type) {
    accounts = accounts.filter(a => a.type === type);
  }
  
  res.json({
    data: accounts,
    total: accounts.length
  });
});

app.get('/api/chart-of-accounts/by-type/:type', (req, res) => {
  const accounts = database.chart_of_accounts.filter(
    a => a.tenant_id === 1 && a.type === req.params.type
  );
  
  res.json(accounts);
});

app.get('/api/chart-of-accounts/summary', (req, res) => {
  const accounts = database.chart_of_accounts.filter(a => a.tenant_id === 1);
  
  res.json({
    total_accounts: accounts.length,
    assets: accounts.filter(a => a.type === 'asset').length,
    liabilities: accounts.filter(a => a.type === 'liability').length,
    equity: accounts.filter(a => a.type === 'equity').length,
    revenues: accounts.filter(a => a.type === 'revenue').length,
    expenses: accounts.filter(a => a.type === 'expense').length
  });
});

// ==================== ROUTES COMPTABILITÉ ====================

app.get('/api/accounting/ledger', (req, res) => {
  const entries = database.accounting_entries.filter(e => e.tenant_id === 1);
  
  res.json({
    data: entries,
    pagination: {
      total: entries.length,
      per_page: 50,
      current_page: 1
    }
  });
});

app.get('/api/accounting/trial-balance', (req, res) => {
  const entries = database.accounting_entries.filter(e => e.tenant_id === 1 && e.posted);
  
  const balance = {};
  entries.forEach(entry => {
    const code = entry.account_code;
    if (!balance[code]) {
      balance[code] = {
        account_code: code,
        account_name: entry.account_name,
        account_type: entry.account_type,
        debit: 0,
        credit: 0
      };
    }
    balance[code].debit += entry.debit;
    balance[code].credit += entry.credit;
    balance[code].balance = balance[code].debit - balance[code].credit;
  });
  
  const accounts = Object.values(balance);
  const totalDebit = accounts.reduce((sum, a) => sum + a.debit, 0);
  const totalCredit = accounts.reduce((sum, a) => sum + a.credit, 0);
  
  res.json({
    start_date: '2025-11-01',
    end_date: '2025-11-22',
    accounts: accounts,
    total_debit: totalDebit,
    total_credit: totalCredit
  });
});

app.get('/api/accounting/income-statement', (req, res) => {
  const entries = database.accounting_entries.filter(e => e.tenant_id === 1 && e.posted);
  
  const revenue = entries
    .filter(e => e.account_type === 'revenue')
    .reduce((sum, e) => sum + e.credit, 0);
  
  const expenses = entries
    .filter(e => e.account_type === 'expense')
    .reduce((sum, e) => sum + e.debit, 0);
  
  const netIncome = revenue - expenses;
  
  res.json({
    period: {
      start_date: '2025-11-01',
      end_date: '2025-11-22'
    },
    revenue: revenue,
    expenses: expenses,
    net_income: netIncome,
    profit_margin: revenue > 0 ? (netIncome / revenue * 100).toFixed(2) : 0
  });
});

app.get('/api/accounting/balance-sheet', (req, res) => {
  const entries = database.accounting_entries.filter(e => e.tenant_id === 1 && e.posted);
  
  const assets = {};
  const liabilities = {};
  const equity = {};
  
  entries.forEach(entry => {
    const balance = entry.debit - entry.credit;
    
    if (entry.account_type === 'asset') {
      assets[entry.account_code] = (assets[entry.account_code] || 0) + balance;
    } else if (entry.account_type === 'liability') {
      liabilities[entry.account_code] = (liabilities[entry.account_code] || 0) + balance;
    } else if (entry.account_type === 'equity') {
      equity[entry.account_code] = (equity[entry.account_code] || 0) + balance;
    }
  });
  
  const totalAssets = Object.values(assets).reduce((a, b) => a + b, 0);
  const totalLiabilities = Object.values(liabilities).reduce((a, b) => a + b, 0);
  const totalEquity = Object.values(equity).reduce((a, b) => a + b, 0);
  
  res.json({
    as_of_date: '2025-11-22',
    assets: assets,
    total_assets: totalAssets,
    liabilities: liabilities,
    total_liabilities: totalLiabilities,
    equity: equity,
    total_equity: totalEquity,
    total_liabilities_and_equity: totalLiabilities + totalEquity,
    is_balanced: Math.abs(totalAssets - (totalLiabilities + totalEquity)) < 0.01
  });
});

// ==================== ROUTES VENTES ====================

app.get('/api/sales', (req, res) => {
  const sales = database.sales.filter(s => s.tenant_id === 1);
  
  res.json({
    data: sales,
    pagination: {
      total: sales.length,
      per_page: 20,
      current_page: 1
    }
  });
});

app.get('/api/sales/report', (req, res) => {
  const sales = database.sales.filter(s => s.tenant_id === 1);
  
  const totalRevenue = sales.reduce((sum, s) => sum + s.total, 0);
  const totalTransactions = sales.length;
  const averageSale = totalTransactions > 0 ? totalRevenue / totalTransactions : 0;
  
  res.json({
    total_revenue: totalRevenue,
    total_transactions: totalTransactions,
    average_sale: averageSale,
    sales_by_status: {
      completed: sales.filter(s => s.status === 'completed').length,
      pending: sales.filter(s => s.status === 'pending').length,
      cancelled: sales.filter(s => s.status === 'cancelled').length
    }
  });
});

// ==================== ROUTES ACHATS ====================

app.get('/api/purchases', (req, res) => {
  const purchases = database.purchases.filter(p => p.tenant_id === 1);
  
  res.json({
    data: purchases,
    pagination: {
      total: purchases.length,
      per_page: 20,
      current_page: 1
    }
  });
});

app.get('/api/purchases/report', (req, res) => {
  const purchases = database.purchases.filter(p => p.tenant_id === 1);
  
  const totalPurchases = purchases.reduce((sum, p) => sum + p.total, 0);
  const totalTransactions = purchases.length;
  const averagePurchase = totalTransactions > 0 ? totalPurchases / totalTransactions : 0;
  
  res.json({
    total_purchases: totalPurchases,
    total_transactions: totalTransactions,
    average_purchase: averagePurchase,
    purchases_by_status: {
      received: purchases.filter(p => p.status === 'received').length,
      confirmed: purchases.filter(p => p.status === 'confirmed').length,
      pending: purchases.filter(p => p.status === 'pending').length
    }
  });
});

// ==================== ROUTES STOCKS ====================

app.get('/api/stocks', (req, res) => {
  const stocks = database.stocks.filter(s => s.tenant_id === 1);
  
  res.json({
    data: stocks,
    pagination: {
      total: stocks.length,
      per_page: 20,
      current_page: 1
    }
  });
});

app.get('/api/stocks/summary', (req, res) => {
  const stocks = database.stocks.filter(s => s.tenant_id === 1);
  
  const totalAvailable = stocks.reduce((sum, s) => sum + s.available, 0);
  const totalReserved = stocks.reduce((sum, s) => sum + s.reserved, 0);
  const totalStock = stocks.reduce((sum, s) => sum + s.total, 0);
  
  res.json({
    total_products: stocks.length,
    total_available: totalAvailable,
    total_reserved: totalReserved,
    total_stock: totalStock,
    low_stock_count: stocks.filter(s => s.available < 100).length
  });
});

// ==================== ROUTES CLIENTS ====================

app.get('/api/customers', (req, res) => {
  const customers = database.customers.filter(c => c.tenant_id === 1);
  
  res.json({
    data: customers,
    pagination: {
      total: customers.length,
      per_page: 20,
      current_page: 1
    }
  });
});

// ==================== ROUTES FOURNISSEURS ====================

app.get('/api/suppliers', (req, res) => {
  const suppliers = database.suppliers.filter(s => s.tenant_id === 1);
  
  res.json({
    data: suppliers,
    pagination: {
      total: suppliers.length,
      per_page: 20,
      current_page: 1
    }
  });
});

// ==================== ROUTES PRODUITS ====================

app.get('/api/products', (req, res) => {
  const products = database.products.filter(p => p.tenant_id === 1);
  
  res.json({
    data: products,
    pagination: {
      total: products.length,
      per_page: 20,
      current_page: 1
    }
  });
});

// ==================== ROUTE DASHBOARD ====================

app.get('/api/dashboard', (req, res) => {
  const sales = database.sales.filter(s => s.tenant_id === 1);
  const purchases = database.purchases.filter(p => p.tenant_id === 1);
  const stocks = database.stocks.filter(s => s.tenant_id === 1);
  
  res.json({
    stats: {
      total_sales: sales.reduce((sum, s) => sum + s.total, 0),
      total_purchases: purchases.reduce((sum, p) => sum + p.total, 0),
      total_revenue: sales.reduce((sum, s) => sum + s.total, 0),
      low_stock_items: stocks.filter(s => s.available < 100).length,
      pending_purchases: purchases.filter(p => p.status === 'pending').length
    },
    recent_sales: sales.slice(-5),
    recent_purchases: purchases.slice(-5)
  });
});

// Start server
const PORT = process.env.PORT || 3001;
app.listen(PORT, () => {
  console.log(`\n🚀 Serveur de test SIGEC lancé sur http://localhost:${PORT}`);
  console.log(`📊 API disponible sur http://localhost:${PORT}/api`);
  console.log(`🌐 Interface Web: http://localhost:${PORT}\n`);
  console.log('Identifiants de test:');
  console.log('  Email: admin@demo.local');
  console.log('  Mot de passe: password\n');
});
