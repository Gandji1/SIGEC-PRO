<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Service de calcul des amortissements
 * Conforme au SYSCOHADA Révisé
 */
class AmortissementService
{
    /**
     * Calculer l'amortissement annuel selon la méthode
     */
    public function calculerAnnuite(object $immo, int $annee): float
    {
        $base = $immo->valeur_acquisition - ($immo->valeur_residuelle ?? 0);
        $dateAcquisition = Carbon::parse($immo->date_acquisition);
        $anneeAcquisition = $dateAcquisition->year;
        $moisAcquisition = $dateAcquisition->month;
        
        // Nombre d'années écoulées depuis l'acquisition
        $anneesEcoulees = $annee - $anneeAcquisition;
        
        if ($anneesEcoulees < 0 || $anneesEcoulees >= $immo->duree_vie) {
            return 0;
        }

        switch ($immo->methode_amortissement) {
            case 'lineaire':
                return $this->amortissementLineaire($base, $immo->duree_vie, $annee, $anneeAcquisition, $moisAcquisition);
            
            case 'degressif':
                return $this->amortissementDegressif($base, $immo->duree_vie, $anneesEcoulees, $immo->cumul_amortissement ?? 0);
            
            case 'unites_production':
                return $this->amortissementUnitesProduction($base, $immo);
            
            default:
                return $this->amortissementLineaire($base, $immo->duree_vie, $annee, $anneeAcquisition, $moisAcquisition);
        }
    }

    /**
     * Amortissement linéaire (constant)
     * Prorata temporis la première année
     */
    private function amortissementLineaire(float $base, int $dureeVie, int $annee, int $anneeAcquisition, int $moisAcquisition): float
    {
        $annuite = $base / $dureeVie;
        
        // Prorata temporis la première année
        if ($annee === $anneeAcquisition) {
            $moisRestants = 12 - $moisAcquisition + 1;
            return ($annuite * $moisRestants) / 12;
        }
        
        // Dernière année : complément
        $anneesEcoulees = $annee - $anneeAcquisition;
        if ($anneesEcoulees === $dureeVie) {
            $moisRestants = 12 - $moisAcquisition + 1;
            return ($annuite * (12 - $moisRestants)) / 12;
        }
        
        return $annuite;
    }

    /**
     * Amortissement dégressif (SYSCOHADA)
     * Coefficient selon durée de vie:
     * - 3-4 ans: 1.5
     * - 5-6 ans: 2
     * - > 6 ans: 2.5
     */
    private function amortissementDegressif(float $base, int $dureeVie, int $anneesEcoulees, float $cumulAmortissement): float
    {
        // Coefficient dégressif SYSCOHADA
        $coefficient = match(true) {
            $dureeVie <= 4 => 1.5,
            $dureeVie <= 6 => 2.0,
            default => 2.5,
        };
        
        $tauxLineaire = 100 / $dureeVie;
        $tauxDegressif = $tauxLineaire * $coefficient;
        
        // Valeur nette comptable
        $vnc = $base - $cumulAmortissement;
        
        // Amortissement dégressif
        $amortDegressif = $vnc * ($tauxDegressif / 100);
        
        // Amortissement linéaire sur la durée restante
        $dureeRestante = $dureeVie - $anneesEcoulees;
        $amortLineaire = $dureeRestante > 0 ? $vnc / $dureeRestante : 0;
        
        // Prendre le plus élevé (bascule vers linéaire quand plus avantageux)
        return max($amortDegressif, $amortLineaire);
    }

    /**
     * Amortissement par unités de production
     */
    private function amortissementUnitesProduction(float $base, object $immo): float
    {
        $unitesTotales = $immo->unites_totales ?? 1;
        $unitesAnnee = $immo->unites_annee ?? 0;
        
        if ($unitesTotales <= 0) {
            return 0;
        }
        
        return ($base / $unitesTotales) * $unitesAnnee;
    }

    /**
     * Générer le tableau d'amortissement complet
     */
    public function genererTableau(object $immo): array
    {
        $tableau = [];
        $base = $immo->valeur_acquisition - ($immo->valeur_residuelle ?? 0);
        $dateAcquisition = Carbon::parse($immo->date_acquisition);
        $anneeDebut = $dateAcquisition->year;
        $cumul = 0;
        
        // Pour le dégressif, on doit calculer séquentiellement
        $vnc = $base;
        
        for ($i = 0; $i <= $immo->duree_vie; $i++) {
            $annee = $anneeDebut + $i;
            
            // Créer un objet temporaire avec le cumul actuel pour le calcul dégressif
            $immoTemp = clone $immo;
            $immoTemp->cumul_amortissement = $cumul;
            
            $annuite = $this->calculerAnnuite($immoTemp, $annee);
            
            // Ne pas dépasser la base amortissable
            if ($cumul + $annuite > $base) {
                $annuite = $base - $cumul;
            }
            
            if ($annuite <= 0) {
                break;
            }
            
            $cumul += $annuite;
            $vnc = $immo->valeur_acquisition - $cumul;
            
            $tableau[] = [
                'annee' => $annee,
                'base_amortissable' => round($base, 2),
                'taux' => round((100 / $immo->duree_vie), 2),
                'annuite' => round($annuite, 2),
                'cumul_amortissement' => round($cumul, 2),
                'vnc' => round(max(0, $vnc), 2),
            ];
        }
        
        return $tableau;
    }

    /**
     * Calculer les dotations aux amortissements pour une période
     */
    public function calculerDotationsPeriode(int $tenantId, int $annee): array
    {
        $immobilisations = DB::table('immobilisations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
        
        $dotations = [];
        $totalDotation = 0;
        
        foreach ($immobilisations as $immo) {
            $annuite = $this->calculerAnnuite($immo, $annee);
            
            if ($annuite > 0) {
                $dotations[] = [
                    'immobilisation_id' => $immo->id,
                    'designation' => $immo->designation,
                    'category_code' => $immo->category_code,
                    'valeur_acquisition' => $immo->valeur_acquisition,
                    'methode' => $immo->methode_amortissement,
                    'dotation' => round($annuite, 2),
                ];
                
                $totalDotation += $annuite;
            }
        }
        
        return [
            'annee' => $annee,
            'dotations' => $dotations,
            'total_dotation' => round($totalDotation, 2),
            'compte_debit' => '681', // Dotations aux amortissements d'exploitation
            'compte_credit' => '28', // Amortissements des immobilisations
        ];
    }

    /**
     * Enregistrer les amortissements de l'année
     */
    public function enregistrerAmortissements(int $tenantId, int $annee): array
    {
        $dotations = $this->calculerDotationsPeriode($tenantId, $annee);
        $enregistres = 0;
        
        foreach ($dotations['dotations'] as $dotation) {
            $immo = DB::table('immobilisations')->find($dotation['immobilisation_id']);
            
            if (!$immo) continue;
            
            $base = $immo->valeur_acquisition - ($immo->valeur_residuelle ?? 0);
            $nouveauCumul = min($immo->cumul_amortissement + $dotation['dotation'], $base);
            
            // Vérifier si déjà enregistré cette année
            $dejaEnregistre = DB::table('amortissement_history')
                ->where('immobilisation_id', $immo->id)
                ->where('annee', $annee)
                ->exists();
            
            if (!$dejaEnregistre && $dotation['dotation'] > 0) {
                // Mettre à jour le cumul
                DB::table('immobilisations')
                    ->where('id', $immo->id)
                    ->update([
                        'cumul_amortissement' => $nouveauCumul,
                        'updated_at' => now(),
                    ]);
                
                // Historique
                DB::table('amortissement_history')->insert([
                    'tenant_id' => $tenantId,
                    'immobilisation_id' => $immo->id,
                    'annee' => $annee,
                    'dotation' => $dotation['dotation'],
                    'cumul_avant' => $immo->cumul_amortissement,
                    'cumul_apres' => $nouveauCumul,
                    'created_at' => now(),
                ]);
                
                $enregistres++;
                
                // Marquer comme totalement amorti si nécessaire
                if ($nouveauCumul >= $base) {
                    DB::table('immobilisations')
                        ->where('id', $immo->id)
                        ->update(['status' => 'fully_depreciated']);
                }
            }
        }
        
        return [
            'annee' => $annee,
            'total_dotation' => $dotations['total_dotation'],
            'immobilisations_traitees' => $enregistres,
            'message' => "Amortissements {$annee} enregistrés pour {$enregistres} immobilisation(s)",
        ];
    }
}
