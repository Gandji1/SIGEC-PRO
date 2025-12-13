// Simple Node.js API server to mock the Laravel backend
// Run with: node mock-api.js

const http = require('http');
const url = require('url');

// Mock data
const mockData = {
  users: {
    'demo@sigec.com': {
      id: 1,
      email: 'demo@sigec.com',
      name: 'Demo User',
      token: 'mock_token_12345'
    }
  },
  sales: [
    { id: 1, client: 'Client A', amount: 1250.00, items: 5, status: 'Complété', date: '24/11/2025', invoice: 'INV-001' },
    { id: 2, client: 'Client B', amount: 850.50, items: 3, status: 'Brouillon', date: '24/11/2025', invoice: 'INV-002' },
    { id: 3, client: 'Client C', amount: 2150.75, items: 8, status: 'Complété', date: '23/11/2025', invoice: 'INV-003' }
  ],
  purchases: [
    { id: 1, supplier: 'Supplier A', amount: 5000, status: 'Reçu', cmp: 45.50, items: 110 },
    { id: 2, supplier: 'Supplier B', amount: 3200, status: 'Confirmé', cmp: 32.00, items: 100 }
  ],
  transfers: [
    { id: 1, from: 'Warehouse A', to: 'Warehouse B', items: 50, status: 'Complété' },
    { id: 2, from: 'Warehouse C', to: 'Warehouse A', items: 30, status: 'En attente' }
  ],
  inventory: [
    { product: 'Produit A', sku: 'SKU-001', stock: 45, min: 10, warehouse: 'WH-A', value: 2250 },
    { product: 'Produit B', sku: 'SKU-002', stock: 8, min: 15, warehouse: 'WH-B', value: 600 },
    { product: 'Produit C', sku: 'SKU-003', stock: 120, min: 20, warehouse: 'WH-A', value: 14400 }
  ],
  products: [
    { id: 1, name: 'Produit A', price: 50, stock: 45 },
    { id: 2, name: 'Produit B', price: 75, stock: 8 },
    { id: 3, name: 'Produit C', price: 120, stock: 120 },
    { id: 4, name: 'Produit D', price: 90, stock: 30 }
  ],
  stats: {
    totalSales: 125450,
    stockValue: 89230,
    revenue: 36220,
    transactions: 1245
  }
};

// Create the server
const server = http.createServer((req, res) => {
  // Enable CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  res.setHeader('Content-Type', 'application/json');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  const parsedUrl = url.parse(req.url, true);
  const pathname = parsedUrl.pathname;
  const query = parsedUrl.query;

  // Routes
  if (pathname === '/api/login' && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk);
    req.on('end', () => {
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        user: mockData.users['demo@sigec.com'],
        message: 'Login successful'
      }));
    });
  }

  else if (pathname === '/api/sales' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      success: true,
      data: mockData.sales
    }));
  }

  else if (pathname === '/api/sales' && req.method === 'POST') {
    res.writeHead(201);
    res.end(JSON.stringify({
      success: true,
      message: 'Sale created successfully',
      id: mockData.sales.length + 1
    }));
  }

  else if (pathname === '/api/purchases' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      success: true,
      data: mockData.purchases
    }));
  }

  else if (pathname === '/api/transfers' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      success: true,
      data: mockData.transfers
    }));
  }

  else if (pathname === '/api/inventory' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      success: true,
      data: mockData.inventory
    }));
  }

  else if (pathname === '/api/products' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      success: true,
      data: mockData.products
    }));
  }

  else if (pathname === '/api/stats' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      success: true,
      data: mockData.stats
    }));
  }

  else if (pathname === '/api/health' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      success: true,
      message: 'Mock API Server is running'
    }));
  }

  else {
    res.writeHead(404);
    res.end(JSON.stringify({
      success: false,
      message: 'Route not found'
    }));
  }
});

const PORT = process.env.PORT || 8000;
server.listen(PORT, () => {
  console.log(`\n✅ Mock API Server running on http://localhost:${PORT}`);
  console.log(`\nAvailable endpoints:`);
  console.log('  POST   /api/login       - User authentication');
  console.log('  GET    /api/sales       - List sales');
  console.log('  POST   /api/sales       - Create sale');
  console.log('  GET    /api/purchases   - List purchases');
  console.log('  GET    /api/transfers   - List transfers');
  console.log('  GET    /api/inventory   - List inventory');
  console.log('  GET    /api/products    - List products');
  console.log('  GET    /api/stats       - Get stats');
  console.log('  GET    /api/health      - Health check');
  console.log('\n');
});
