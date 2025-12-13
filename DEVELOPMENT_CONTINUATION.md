# 🛠️ GUIDE CONTINUATION DÉVELOPPEMENT - SIGEC

## Objectif
Ce guide décrit comment continuer le développement du système SIGEC de manière cohérente avec l'architecture actuelle.

---

## 📋 PATTERNS ÉTABLIS

### 1. Structure Backend

```
app/
├── Http/Controllers/Api/    ← Nouveaux contrôleurs ici
├── Models/                  ← Nouveaux modèles
├── Domains/*/Services/      ← Business logic
├── Events/                  ← Événements
├── Listeners/               ← Listeners
├── Policies/                ← Autorisation
└── Providers/               ← Configuration
```

**À Suivre:**
- Un contrôleur par ressource
- Un modèle par table
- Services pour logique métier
- Events pour automatisations
- Policies pour autorisation

### 2. Création d'une Nouvelle Ressource

#### Exemple: Resource "Invoice"

**Étape 1: Migration**
```php
// database/migrations/2024_01_01_000018_create_invoices_table.php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
    $table->string('number')->unique();
    $table->decimal('total', 12, 2);
    $table->enum('status', ['draft', 'sent', 'paid', 'cancelled'])->default('draft');
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

**Étape 2: Modèle**
```php
// app/Models/Invoice.php
class Invoice extends Model
{
    protected $fillable = ['tenant_id', 'sale_id', 'number', 'total', 'status'];
    
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
}
```

**Étape 3: Contrôleur**
```php
// app/Http/Controllers/Api/InvoiceController.php
class InvoiceController extends Controller
{
    public function index(Request $request) { /* ... */ }
    public function store(Request $request) { /* ... */ }
    public function show($id) { /* ... */ }
    public function update(Request $request, $id) { /* ... */ }
    public function destroy($id) { /* ... */ }
}
```

**Étape 4: Routes**
```php
// routes/api.php
Route::apiResource('invoices', InvoiceController::class);
```

**Étape 5: Policy (si nécessaire)**
```php
// app/Policies/InvoicePolicy.php
class InvoicePolicy { /* ... */ }
```

**Étape 6: Event (si nécessaire)**
```php
// app/Events/InvoiceSent.php
class InvoiceSent { /* ... */ }

// app/Listeners/SendInvoiceEmail.php
class SendInvoiceEmail implements ShouldQueue { /* ... */ }
```

---

## 🎨 PATTERNS FRONTEND

### Structure
```
frontend/src/
├── pages/          ← Nouvelle page ici
├── components/     ← Composants réutilisables
├── services/       ← Services API
├── stores/         ← Zustand stores
└── App.jsx         ← Routes
```

### Créer une Nouvelle Page

**Étape 1: Page Component**
```jsx
// src/pages/InvoicesPage.jsx
export default function InvoicesPage() {
  const [invoices, setInvoices] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchInvoices();
  }, []);

  const fetchInvoices = async () => {
    try {
      const response = await apiClient.get('/invoices');
      setInvoices(response.data.data);
    } catch (error) {
      console.error('Error:', error);
    }
  };

  return (
    <div className="space-y-6">
      {/* Content */}
    </div>
  );
}
```

**Étape 2: Route**
```jsx
// App.jsx
import InvoicesPage from './pages/InvoicesPage';

// Dans Routes:
<Route path="/invoices" element={<PrivateRoute><InvoicesPage /></PrivateRoute>} />
```

**Étape 3: Navigation**
```jsx
// Layout.jsx
<NavLink href="/invoices" label="Factures" icon="📄" />
```

---

## 🔄 AUTOMATISATIONS (Events/Listeners)

### Quand Créer un Event?
- Quand une action est "importante" (sale completed, payment received)
- Quand plusieurs systèmes doivent réagir
- Quand vous voulez découpler la logique

### Exemple: Payment Received

**Événement**
```php
namespace App\Events;

class PaymentReceived {
    public function __construct(public Payment $payment) {}
}
```

**Listeners**
```php
// 1. Record audit
class RecordPaymentAudit { /* ... */ }

// 2. Send email
class SendPaymentConfirmation { /* ... */ }

// 3. Update customer balance
class UpdateCustomerBalance { /* ... */ }
```

**Registration**
```php
// EventServiceProvider.php
protected $listen = [
    PaymentReceived::class => [
        RecordPaymentAudit::class,
        SendPaymentConfirmation::class,
        UpdateCustomerBalance::class,
    ],
];
```

**Trigger**
```php
// Dans le contrôleur
PaymentReceived::dispatch($payment);
```

---

## 🔐 AUTHORIZATION

### Quand Créer une Policy?

Chaque fois que vous avez une action sensible:
- `view` - Peut voir la ressource?
- `create` - Peut créer?
- `update` - Peut modifier?
- `delete` - Peut supprimer?

### Exemple

```php
// app/Policies/InvoicePolicy.php
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->tenant_id === $invoice->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }
}

// Dans le contrôleur
$this->authorize('view', $invoice);
```

---

## 📊 BONNES PRATIQUES

### 1. Validation
```php
$validated = $request->validate([
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email,'.$user->id,
    'amount' => 'required|numeric|min:0.01',
]);
```

### 2. Transactions
```php
try {
    DB::beginTransaction();
    // Opérations
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    return response()->json(['error' => $e->getMessage()], 400);
}
```

### 3. Pagination
```php
$items = Item::where('tenant_id', $tenantId)
    ->paginate(20);

return response()->json($items);
```

### 4. Filtering
```php
$query = Item::where('tenant_id', $tenantId);

if ($request->has('search')) {
    $search = $request->query('search');
    $query->where('name', 'like', "%$search%");
}

$items = $query->paginate(20);
```

### 5. Error Handling
```php
try {
    // Logic
    return response()->json($data, 200);
} catch (ModelNotFoundException $e) {
    return response()->json(['error' => 'Not found'], 404);
} catch (\Exception $e) {
    return response()->json(['error' => $e->getMessage()], 400);
}
```

---

## 🧪 TESTING

### Factory
```php
// database/factories/InvoiceFactory.php
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sale_id' => Sale::factory(),
            'number' => $this->faker->unique()->numerify('INV-####'),
            'total' => $this->faker->numberBetween(100, 10000),
            'status' => 'draft',
        ];
    }
}
```

### Test
```php
// tests/Feature/InvoiceTest.php
class InvoiceTest extends TestCase
{
    public function test_can_create_invoice()
    {
        $invoice = Invoice::factory()->create();
        $this->assertNotNull($invoice->id);
    }
}
```

---

## 📱 FRONTEND - PATTERNS

### Fetcher Data
```jsx
const [data, setData] = useState([]);
const [loading, setLoading] = useState(false);

useEffect(() => {
    fetchData();
}, []);

const fetchData = async () => {
    setLoading(true);
    try {
        const response = await apiClient.get('/endpoint');
        setData(response.data.data);
    } catch (error) {
        console.error('Error:', error);
    } finally {
        setLoading(false);
    }
};
```

### Forms
```jsx
const [formData, setFormData] = useState({
    name: '',
    email: '',
});

const handleSubmit = async (e) => {
    e.preventDefault();
    try {
        await apiClient.post('/endpoint', formData);
        // Success
    } catch (error) {
        console.error('Error:', error);
    }
};
```

### Lists
```jsx
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {items.map(item => (
            <tr key={item.id}>
                <td>{item.name}</td>
                <td>
                    <button onClick={() => edit(item)}>Edit</button>
                    <button onClick={() => delete(item.id)}>Delete</button>
                </td>
            </tr>
        ))}
    </tbody>
</table>
```

---

## 🚀 DÉPLOIEMENT

### Local
```bash
docker-compose up -d
php artisan migrate
```

### Staging
```bash
git push origin develop
# GitHub Actions runs tests
# Deploy to staging.sigec.app
```

### Production
```bash
git tag v1.0.1
git push origin v1.0.1
# GitHub Actions runs tests + deploys
# Deploy to sigec.app
```

---

## 📈 PERFORMANCE

### Optimizations Déjà Faites
- ✅ Database indices sur `tenant_id`, `status`, dates
- ✅ Eager loading des relations (with)
- ✅ Pagination des listes
- ✅ Query optimization

### À Considérer
- [ ] Caching (Redis)
- [ ] Query optimization (profiles)
- [ ] API rate limiting
- [ ] CDN pour assets

---

## 🐛 DEBUGGING

### Backend
```php
// Log
\Log::info('Message', ['data' => $data]);

// Dump
dd($data);

// Tinker
php artisan tinker
> $invoice = Invoice::first();
> $invoice->sale;
```

### Frontend
```javascript
// Console
console.log('Data:', data);
console.error('Error:', error);

// React DevTools extension
// Check component state, props

// Network tab
// Verify API calls, payloads
```

---

## 📚 RESSOURCES

- **Laravel Docs:** https://laravel.com/docs
- **React Docs:** https://react.dev
- **REST Best Practices:** https://restfulapi.net
- **Database Design:** https://dbdiagram.io

---

## ✅ CHECKLIST PRÉ-COMMIT

Avant de commiter:
- [ ] Code formaté (PSR-12 pour PHP)
- [ ] Tests passent (`php artisan test`)
- [ ] Pas d'erreurs (linting)
- [ ] Migrations testées
- [ ] Documentation à jour
- [ ] Commit message clair

---

## 🎯 CONVENTIONS

### Naming
- Tables: plural, snake_case (`invoices`, `invoice_items`)
- Models: singular, PascalCase (`Invoice`, `InvoiceItem`)
- Columns: snake_case (`created_at`, `total_amount`)
- Variables: camelCase (`invoiceTotal`, `itemCount`)

### Commits
```
feat: add invoice system
fix: correct payment calculation
docs: update API documentation
test: add invoice tests
refactor: simplify payment logic
```

---

**📌 Suivi ces patterns et le projet restera maintenable et scalable!**
