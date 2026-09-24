<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Commission;
use App\Models\PaiementCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $commercialId = $this->commercialId();
        $regle = Commission::globale();

        $commandesLivrees = Commande::query()
            ->forCommercial($commercialId)
            ->where('statut', 'Livré')
            ->whereNotNull('date_livraison')
            ->orderByDesc('date_livraison')
            ->get();

        $paiements = collect();
        if (Schema::hasTable('paiements_commissions')) {
            $paiements = PaiementCommission::query()
                ->where('commercial_id', $commercialId)
                ->get()
                ->keyBy(fn (PaiementCommission $paiement) => $paiement->periode->format('Y-m-01'));
        }

        $gainsMensuels = $commandesLivrees
            ->groupBy(fn (Commande $commande) => optional($commande->date_livraison)->format('Y-m-01'))
            ->map(function ($commandes, $periode) use ($regle, $paiements) {
                $base = (int) $commandes->sum('cout_livraison');
                $montant = $regle ? $regle->montantPourBase($base) : 0;
                $paiement = $paiements->get($periode);

                return [
                    'periode' => $periode,
                    'colis' => $commandes->count(),
                    'base' => $base,
                    'montant' => $montant,
                    'paye' => $paiement !== null,
                    'date_paiement' => $paiement?->date_paiement,
                ];
            })
            ->sortKeysDesc()
            ->values();

        $stats = [
            'colis_livres' => $commandesLivrees->count(),
            'taux' => $regle?->taux,
            'montant_total' => (int) $gainsMensuels->sum('montant'),
        ];

        return view('commissions.index', compact('regle', 'gainsMensuels', 'stats'));
    }
}
