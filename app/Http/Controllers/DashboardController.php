<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Commission;
use App\Models\PaiementCommission;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    public function index()
    {
        $utilisateur = Session::get('utilisateur', []);
        $commercialId = (int) data_get($utilisateur, 'id', 0);
        $regle = Schema::hasTable('commissions') ? Commission::globale() : null;

        $clientsTotal = 0;
        $clientsActifs = 0;
        $clientsInactifs = 0;
        if (Schema::hasTable('utilisateurs') && $commercialId > 0) {
            $clientsQuery = Utilisateur::query()->clients()->forCommercial($commercialId);
            $clientsTotal = (clone $clientsQuery)->count();
            $clientsActifs = (clone $clientsQuery)->where('statut_compte', 1)->count();
            $clientsInactifs = (clone $clientsQuery)->where('statut_compte', 0)->count();
        }

        $boutiquesTotal = 0;
        $boutiquesActives = 0;
        $colisParBoutique = collect();
        if (Schema::hasTable('boutiques') && $commercialId > 0) {
            $boutiquesQuery = Boutique::query()->forCommercial($commercialId);
            $boutiquesTotal = (clone $boutiquesQuery)->count();
            $boutiquesActives = (clone $boutiquesQuery)->where('statut', 1)->count();
        }

        $commandesTotal = 0;
        $commandesLivrees = 0;
        $commandesNonLivrees = 0;
        $commandesRetours = 0;
        $montantDu = 0;
        $montantPaye = 0;
        $montantMois = 0;
        $chartLabels = [];
        $chartData = [];
        $dernieresCommandes = collect();

        if (Schema::hasTable('commandes') && $commercialId > 0) {
            $commandesQuery = Commande::query()->forCommercial($commercialId);
            $commandesTotal = (clone $commandesQuery)->count();
            $commandesLivrees = (clone $commandesQuery)->where('statut', 'Livré')->count();
            $commandesNonLivrees = (clone $commandesQuery)->where('statut', 'Non Livré')->count();
            $commandesRetours = (clone $commandesQuery)->where('statut', 'Retour')->count();

            $livrees = (clone $commandesQuery)
                ->where('statut', 'Livré')
                ->whereNotNull('date_livraison')
                ->get(['id', 'utilisateur_id', 'cout_livraison', 'date_livraison', 'statut', 'communes']);

            $montantDu = $regle ? $regle->montantPourBase((int) $livrees->sum('cout_livraison')) : 0;

            $debutMois = Carbon::now()->startOfMonth()->toDateString();
            $finMois = Carbon::now()->endOfMonth()->toDateString();
            $baseMois = (int) $livrees
                ->filter(fn (Commande $commande) => $commande->date_livraison
                    && $commande->date_livraison->toDateString() >= $debutMois
                    && $commande->date_livraison->toDateString() <= $finMois)
                ->sum('cout_livraison');
            $montantMois = $regle ? $regle->montantPourBase($baseMois) : 0;

            for ($i = 6; $i >= 0; $i--) {
                $mois = Carbon::now()->subMonths($i)->startOfMonth();
                $cle = $mois->format('Y-m');
                $chartLabels[] = $mois->format('m/Y');
                $base = (int) $livrees
                    ->filter(fn (Commande $commande) => $commande->date_livraison?->format('Y-m') === $cle)
                    ->sum('cout_livraison');
                $chartData[] = $regle ? $regle->montantPourBase($base) : 0;
            }

            $dernieresCommandes = Commande::query()
                ->forCommercial($commercialId)
                ->with(['client.boutique'])
                ->orderByDesc('date_reception')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            if (Schema::hasTable('boutiques')) {
                $boutiques = Boutique::query()
                    ->forCommercial($commercialId)
                    ->orderBy('nom')
                    ->get();

                $clientIdsParBoutique = Utilisateur::query()
                    ->clients()
                    ->forCommercial($commercialId)
                    ->whereNotNull('boutique_id')
                    ->get(['id', 'boutique_id'])
                    ->groupBy('boutique_id');

                $statsCommandes = Commande::query()
                    ->forCommercial($commercialId)
                    ->selectRaw('utilisateur_id, statut, COUNT(*) as total')
                    ->groupBy('utilisateur_id', 'statut')
                    ->get()
                    ->groupBy('utilisateur_id');

                $colisParBoutique = $boutiques->map(function (Boutique $boutique) use ($clientIdsParBoutique, $statsCommandes) {
                    $clientIds = $clientIdsParBoutique->get($boutique->id, collect())->pluck('id');
                    $livrees = 0;
                    $nonLivrees = 0;
                    $retours = 0;

                    foreach ($clientIds as $clientId) {
                        foreach ($statsCommandes->get($clientId, collect()) as $row) {
                            $total = (int) $row->total;
                            if ($row->statut === 'Livré') {
                                $livrees += $total;
                            } elseif ($row->statut === 'Retour') {
                                $retours += $total;
                            } else {
                                $nonLivrees += $total;
                            }
                        }
                    }

                    $total = $livrees + $nonLivrees + $retours;

                    return (object) [
                        'id' => $boutique->id,
                        'nom' => $boutique->nom,
                        'livrees' => $livrees,
                        'non_livrees' => $nonLivrees,
                        'retours' => $retours,
                        'total' => $total,
                        'taux' => $total > 0 ? (int) round(($livrees / $total) * 100) : 0,
                    ];
                });
            }
        }

        if (Schema::hasTable('paiements_commissions') && $commercialId > 0) {
            $montantPaye = (int) PaiementCommission::query()
                ->where('commercial_id', $commercialId)
                ->sum('montant');
        }

        $stats = [
            'clients_total' => $clientsTotal,
            'clients_actifs' => $clientsActifs,
            'clients_inactifs' => $clientsInactifs,
            'boutiques_total' => $boutiquesTotal,
            'boutiques_actives' => $boutiquesActives,
            'commandes_total' => $commandesTotal,
            'commandes_livrees' => $commandesLivrees,
            'commandes_non_livrees' => $commandesNonLivrees,
            'commandes_retours' => $commandesRetours,
            'montant_du' => $montantDu,
            'montant_paye' => $montantPaye,
            'reste_a_payer' => max(0, $montantDu - $montantPaye),
            'montant_mois' => $montantMois,
            'taux' => $regle?->taux,
        ];

        return view('dashboard', compact(
            'utilisateur',
            'stats',
            'colisParBoutique',
            'dernieresCommandes',
            'chartLabels',
            'chartData'
        ));
    }
}
