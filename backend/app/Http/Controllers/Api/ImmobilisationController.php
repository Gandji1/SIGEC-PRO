<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur pour la gestion des immobilisations
 * Conforme au SYSCOHADA Révisé
 */
class ImmobilisationController extends Controller
{
    /**
     * Liste des immobilisations
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = DB::table('immobilisations')
            ->where('tenant_id', $tenantId);
        
        if ($request->has('category')) {
            $query->where('category_code', 'like', $request->category . '%');
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $immobilisations = $query->orderBy('date_acquisition', 'desc')->get();
        
        // Calculer VNC pour chaque immobilisation
        $immobilisations = $immobilisations->map(function ($immo) {
            $immo->vnc = $immo->valeur_acquisition - $immo->cumul_amortissement;
            return $immo;
        });
        
        return response()->json(['data' => $immobilisations]);
    }

    /**
     * Créer une immobilisation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:255',
            'category_code' => 'required|string|max:10',
            'date_acquisition' => 'required|date',
            'valeur_acquisition' => 'required|numeric|min:0',
            'valeur_residuelle' => 'nullable|numeric|min:0',
            'duree_vie' => 'required|integer|min:1|max:50',
            'methode_amortissement' => 'required|in:lineaire,degressif,unites_production',
            'numero_serie' => 'nullable|string|max:100',
            'fournisseur' => 'nullable|string|max:255',
            'localisation' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        
        $tenantId = auth()->user()->tenant_id;
        
        // Catégories SYSCOHADA
        $categories = [
            '21' => 'Immobilisations incorporelles',
            '22' => 'Terrains',
            '23' => 'Bâtiments',
            '24' => 'Matériel et outillage',
            '244' => 'Matériel et mobilier de bureau',
            '245' => 'Matériel de transport',
            '246' => 'Matériel informatique',
            '25' => 'Avances et acomptes sur immobilisations',
        ];
        
        $categoryLabel = $categories[$validated['category_code']] ?? 'Autre';
        
        $id = DB::table('immobilisations')->insertGetId([
            'tenant_id' => $tenantId,
            'designation' => $validated['designation'],
            'category_code' => $validated['category_code'],
            'category_label' => $categoryLabel,
            'date_acquisition' => $validated['date_acquisition'],
            'valeur_acquisition' => $validated['valeur_acquisition'],
            'valeur_residuelle' => $validated['valeur_residuelle'] ?? 0,
            'duree_vie' => $validated['duree_vie'],
            'methode_amortissement' => $validated['methode_amortissement'],
            'numero_serie' => $validated['numero_serie'] ?? null,
            'fournisseur' => $validated['fournisseur'] ?? null,
            'localisation' => $validated['localisation'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'cumul_amortissement' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Immobilisation créée',
            'data' => DB::table('immobilisations')->find($id)
        ], 201);
    }

    /**
     * Mettre à jour une immobilisation
     */
    public function update(Request $request, $id): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $immo = DB::table('immobilisations')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();
        
        if (!$immo) {
            return response()->json(['message' => 'Immobilisation non trouvée'], 404);
        }
        
        $validated = $request->validate([
            'designation' => 'sometimes|string|max:255',
            'category_code' => 'sometimes|string|max:10',
            'date_acquisition' => 'sometimes|date',
            'valeur_acquisition' => 'sometimes|numeric|min:0',
            'valeur_residuelle' => 'sometimes|numeric|min:0',
            'duree_vie' => 'sometimes|integer|min:1|max:50',
            'methode_amortissement' => 'sometimes|in:lineaire,degressif,unites_production',
            'numero_serie' => 'nullable|string|max:100',
            'fournisseur' => 'nullable|string|max:255',
            'localisation' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:active,ceded,scrapped',
        ]);
        
        $validated['updated_at'] = now();
        
        DB::table('immobilisations')
            ->where('id', $id)
            ->update($validated);
        
        return response()->json([
            'message' => 'Immobilisation mise à jour',
            'data' => DB::table('immobilisations')->find($id)
        ]);
    }

    /**
     * Supprimer une immobilisation
     */
    public function destroy($id): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $deleted = DB::table('immobilisations')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->delete();
        
        if (!$deleted) {
            return response()->json(['message' => 'Immobilisation non trouvée'], 404);
        }
        
        return response()->json(['message' => 'Immobilisation supprimée']);
    }

    /**
     * Calculer et enregistrer les amortissements
     */
    public function calculateAmortissement(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $year = $request->input('year', date('Y'));
        
        $immobilisations = DB::table('immobilisations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
        
        $totalAmortissement = 0;
        
        foreach ($immobilisations as $immo) {
            $base = $immo->valeur_acquisition - $immo->valeur_residuelle;
            $annuite = $base / $immo->duree_vie;
            
            // Vérifier si pas déjà totalement amorti
            if ($immo->cumul_amortissement < $base) {
                $newCumul = min($immo->cumul_amortissement + $annuite, $base);
                
                DB::table('immobilisations')
                    ->where('id', $immo->id)
                    ->update([
                        'cumul_amortissement' => $newCumul,
                        'updated_at' => now(),
                    ]);
                
                $totalAmortissement += $annuite;
            }
        }
        
        return response()->json([
            'message' => 'Amortissements calculés',
            'total_amortissement' => $totalAmortissement,
            'year' => $year,
        ]);
    }

    /**
     * Tableau d'amortissement d'une immobilisation
     */
    public function tableauAmortissement($id): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $immo = DB::table('immobilisations')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();
        
        if (!$immo) {
            return response()->json(['message' => 'Immobilisation non trouvée'], 404);
        }
        
        $tableau = [];
        $base = $immo->valeur_acquisition - $immo->valeur_residuelle;
        $annuite = $base / $immo->duree_vie;
        $cumul = 0;
        $startYear = date('Y', strtotime($immo->date_acquisition));
        
        for ($i = 0; $i < $immo->duree_vie; $i++) {
            $cumul += $annuite;
            $tableau[] = [
                'annee' => $startYear + $i,
                'base_amortissable' => $base,
                'annuite' => round($annuite, 2),
                'cumul_amortissement' => round($cumul, 2),
                'vnc' => round($immo->valeur_acquisition - $cumul, 2),
            ];
        }
        
        return response()->json([
            'data' => [
                'immobilisation' => $immo,
                'tableau' => $tableau,
            ]
        ]);
    }
}
