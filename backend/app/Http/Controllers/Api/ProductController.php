<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié', 'data' => []], 401);
        }
        
        $tenant_id = $user->tenant_id;
        if (!$tenant_id) {
            return response()->json(['error' => 'Tenant non trouvé', 'data' => []], 400);
        }

        $category = $request->query('category', '');
        $status = $request->query('status', '');
        $search = $request->query('search', '');
        $perPage = min($request->query('per_page', 50), 200);
        $page = $request->query('page', 1);

        // Cache pour les requêtes sans recherche (les plus fréquentes) - TTL 5 minutes
        $cacheKey = "products_{$tenant_id}_{$category}_{$status}_{$perPage}_{$page}";
        
        if (empty($search)) {
            $products = Cache::remember($cacheKey, 300, function () use ($tenant_id, $category, $status, $perPage) {
                $query = Product::where('tenant_id', $tenant_id);
                
                if ($category) $query->where('category', $category);
                if ($status) $query->where('status', $status);
                
                return $query->select('id', 'code', 'name', 'category', 'selling_price', 'purchase_price', 'unit', 'status', 'image')
                    ->orderBy('name')
                    ->paginate($perPage);
            });
        } else {
            // Pas de cache pour les recherches
            $query = Product::where('tenant_id', $tenant_id);
            
            if ($category) $query->where('category', $category);
            if ($status) $query->where('status', $status);
            
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%$search%")
                  ->orWhere('code', 'ilike', "%$search%")
                  ->orWhere('barcode', 'ilike', "%$search%");
            });
            
            $products = $query->select('id', 'code', 'name', 'category', 'selling_price', 'purchase_price', 'unit', 'status', 'image')
                ->orderBy('name')
                ->paginate($perPage);
        }

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        $validated = $request->validate([
            'code' => 'required|string',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'barcode' => 'nullable|string',
            'tax_percent' => 'nullable|numeric|min:0',
            'track_stock' => 'nullable|boolean',
        ]);

        // Vérifier si un produit avec ce code existe (actif)
        $existingActive = Product::where('tenant_id', $tenantId)
            ->where('code', $validated['code'])
            ->first();
            
        if ($existingActive) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => ['code' => ['Ce code produit existe déjà.']]
            ], 422);
        }

        // Vérifier si un produit soft-deleted existe avec ce code
        $existingDeleted = Product::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('code', $validated['code'])
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingDeleted) {
            // Restaurer et mettre à jour le produit supprimé
            $existingDeleted->restore();
            $existingDeleted->update([
                ...$validated,
                'status' => 'active',
            ]);
            $existingDeleted->calculateMargin();
            $existingDeleted->save();
            
            AuditLog::log('restore', 'product', $existingDeleted->id, $validated, 'Product restored and updated');
            return response()->json($existingDeleted, 201);
        }

        // Vérifier barcode unique si fourni
        if (!empty($validated['barcode'])) {
            $existingBarcode = Product::where('tenant_id', $tenantId)
                ->where('barcode', $validated['barcode'])
                ->first();
            if ($existingBarcode) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => ['barcode' => ['Ce code-barres existe déjà.']]
                ], 422);
            }
        }

        $product = Product::create([
            'tenant_id' => $tenantId,
            ...$validated,
            'status' => 'active',
        ]);

        $product->calculateMargin();
        $product->save();

        AuditLog::log('create', 'product', $product->id, $validated, 'Product created');

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        if ($product->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($product->load('stocks'));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        if ($product->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'purchase_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'tax_percent' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive,discontinued',
        ]);

        $product->update($validated);

        if (isset($validated['purchase_price']) || isset($validated['selling_price'])) {
            $product->calculateMargin();
            $product->save();
        }

        AuditLog::log('update', 'product', $product->id, $validated, 'Product updated');

        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product->delete();
        AuditLog::log('delete', 'product', $product->id, [], 'Product deleted');

        return response()->json(null, 204);
    }

    public function lowStock(): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;

        $products = Product::where('tenant_id', $tenant_id)
            ->where('track_stock', true)
            ->whereHas('stocks', function ($q) {
                $q->where('available', '<=', 10);
            })
            ->with('stocks')
            ->get();

        return response()->json($products);
    }

    public function byBarcode(string $barcode): JsonResponse
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;

        $product = Product::where('tenant_id', $tenant_id)
            ->where('barcode', $barcode)
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($product->load('stocks'));
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        if ($product->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        // Supprimer l'ancienne image si elle existe
        if ($product->image && \Storage::exists($product->image)) {
            \Storage::delete($product->image);
        }

        // Sauvegarder la nouvelle image
        $path = $request->file('image')->store('products', 'public');

        $product->update(['image' => $path]);

        AuditLog::log('update', 'product', $product->id, ['image' => $path], 'Product image uploaded');

        return response()->json([
            'success' => true,
            'image_url' => \Storage::url($path),
            'product' => $product,
        ]);
    }

    public function deleteImage(Product $product): JsonResponse
    {
        if ($product->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($product->image && \Storage::exists($product->image)) {
            \Storage::delete($product->image);
        }

        $product->update(['image' => null]);

        AuditLog::log('update', 'product', $product->id, ['image' => null], 'Product image deleted');

        return response()->json(['success' => true, 'message' => 'Image deleted']);
    }
}
