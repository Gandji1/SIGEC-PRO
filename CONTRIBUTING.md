# Guide de Contribution SIGEC

Merci de contribuer à SIGEC! Ce guide vous aidera à comprendre notre processus de contribution.

## 📋 Code de Conduite

Tous les contributeurs doivent respecter notre [Code de Conduite](./CODE_OF_CONDUCT.md).

## 🚀 Démarrer

### 1. Fork & Clone

```bash
# Forker sur GitHub
# Puis cloner votre fork
git clone https://github.com/VOTRE_USERNAME/SIGEC.git
cd SIGEC

# Ajouter upstream
git remote add upstream https://github.com/gandji1/SIGEC.git
```

### 2. Créer Branch

```bash
# Mettre à jour main
git fetch upstream
git rebase upstream/main

# Créer feature branch
git checkout -b feature/ma-fonctionnalite
```

### 3. Développer

```bash
# Démarrer services
docker-compose up -d

# Développer...
# Tests...
# Linting...

# Commit
git commit -m "feat: description claire de la modification"
```

## 📝 Standards de Code

### Backend (PHP/Laravel)

```php
// ✅ Utiliser type hints
public function create(CreateProductRequest $request): Product
{
    return Product::create($request->validated());
}

// ❌ Pas de type hints
public function create($request)
{
    return Product::create($request->all());
}

// ✅ Utiliser dependency injection
use Illuminate\Database\QueryException;

public function store(StockService $service): Response
{
    return $service->transfer($data);
}

// ✅ Valider input
$validated = $request->validate([
    'product_id' => 'required|exists:products,id',
    'quantity' => 'required|integer|min:1',
]);

// ✅ Utiliser transactions
DB::transaction(function () {
    Stock::create($data);
    StockMovement::create($movement);
});
```

### Frontend (React/JavaScript)

```jsx
// ✅ Functional components avec hooks
export function ProductList({ products }) {
    const [filter, setFilter] = useState('');
    
    return (
        <div>
            {products.map(p => <ProductCard key={p.id} product={p} />)}
        </div>
    );
}

// ✅ Prop types ou TypeScript
interface ProductProps {
    id: number;
    name: string;
    price: number;
}

// ✅ Custom hooks pour logique réutilisable
function useProducts() {
    const [products, setProducts] = useState([]);
    useEffect(() => {
        fetch('/api/products').then(res => setProducts(res.json()));
    }, []);
    return products;
}

// ❌ Props sans validation
function Product(props) {
    return <div>{props.product.name}</div>;
}

// ❌ State dans localStorage directement
const [user, setUser] = useState(JSON.parse(localStorage.getItem('user')));

// ✅ Utiliser store (Zustand)
const user = useTenantStore(state => state.user);
```

## 🧪 Tests

### Avant de commit:

```bash
# Backend - PHPUnit
docker-compose exec app php artisan test

# Frontend - Jest
docker-compose exec frontend npm test

# Linting
docker-compose exec app php artisan pint
docker-compose exec frontend npm run lint
```

### Écrire tests:

**Backend:**
```php
// tests/Feature/StockServiceTest.php
class StockServiceTest extends TestCase
{
    public function test_transfer_reduces_source_stock()
    {
        $source = Warehouse::factory()->create();
        $dest = Warehouse::factory()->create();
        $product = Product::factory()->create();
        
        Stock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $source->id,
            'quantity' => 100,
        ]);
        
        (new StockService)->transfer($product, $source, $dest, 50);
        
        $this->assertEquals(50, Stock::where('warehouse_id', $source->id)->first()->quantity);
    }
}
```

**Frontend:**
```jsx
// src/components/__tests__/ProductCard.test.jsx
import { render, screen } from '@testing-library/react';
import ProductCard from '../ProductCard';

describe('ProductCard', () => {
    it('displays product name', () => {
        const product = { id: 1, name: 'Apple', price: 100 };
        render(<ProductCard product={product} />);
        expect(screen.getByText('Apple')).toBeInTheDocument();
    });
});
```

## 📤 Pull Request

### Avant de créer PR:

```bash
# Mettre à jour depuis upstream
git fetch upstream
git rebase upstream/main

# Squash commits si nécessaire
git rebase -i HEAD~3

# Push
git push origin feature/ma-fonctionnalite
```

### Créer PR sur GitHub:

1. Titre clair: `feat: Ajouter gestion stocks offline`
2. Description:
   ```markdown
   ## Description
   Implémente la synchronisation offline pour stocks.
   
   ## Type de changement
   - [x] New feature
   - [ ] Bug fix
   - [ ] Breaking change
   
   ## Tests
   - [x] Unit tests ajoutés
   - [x] Tests manuels effectués
   
   ## Screenshots (si applicable)
   [Ajouter screenshots]
   ```

3. Lier issue: `Closes #123`

## 📋 Checklist PR

- [ ] Code suit style guide du projet
- [ ] Tests ajoutés/passent
- [ ] Documentation mise à jour
- [ ] Pas de console.log/dd/var_dump
- [ ] Pas de secrets commitées (.env)
- [ ] Messages commit clairs
- [ ] Pas de breaking changes (ou documenté)

## 🐛 Rapporter Bugs

Créer issue avec template:

```markdown
## Description du bug
Description brève du problème.

## Étapes de reproduction
1. Aller à...
2. Cliquer sur...
3. Voir l'erreur

## Comportement attendu
Qu'est-ce qui devrait se passer?

## Logs/Screenshots
[Ajouter logs, errors, screenshots]

## Environnement
- OS: Windows/macOS/Linux
- Version: 1.0.0-beta
- Browser: Chrome 120
```

## 🎯 Types Commits

```bash
feat:     # Nouvelle fonctionnalité
fix:      # Correction bug
docs:     # Documentation
style:    # Formatage, missing semicolons
refactor: # Restructuration code
perf:     # Performance improvements
test:     # Tests
chore:    # Build, deps, etc
```

## 📚 Convention de Nommage

### Backend
```php
// Controllers - verbe + Entity
class ProductController { }
class SaleController { }

// Services - verb + Entity + Service
class StockService { }
class AccountingService { }

// Models - singulier, PascalCase
class Product { }
class Stock { }
class StockMovement { }

// Migrations - descriptif
2024_01_15_create_products_table.php

// Méthodes - camelCase, verbe
public function calculateCMP()
public function transferStock()
```

### Frontend
```javascript
// Components - PascalCase
function ProductCard() { }
function POSCart() { }

// Hooks - use + name
function useProducts() { }
function useTenantStore() { }

// Utils/Services - camelCase
function formatPrice() { }
function validateEmail() { }

// Files - kebab-case
product-card.jsx
use-products.js
api-client.js
```

## 🔄 Review Process

1. **Automatic checks** (GitHub Actions)
   - Tests pass
   - Lint pass
   - Code coverage

2. **Code Review** (Maintainers)
   - Design review
   - Implementation review
   - Security check

3. **Merge**
   - Rebase & squash
   - Auto-deployed

## 📞 Besoin d'aide?

- 💬 Discussion: GitHub Discussions
- 📧 Email: dev@sigec.local
- 🐛 Issues: GitHub Issues
- 📚 Docs: [docs/](../docs/)

---

Merci pour votre contribution! 🙏
