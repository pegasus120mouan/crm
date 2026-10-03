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

class ManagerDashboardController extends Controller
{
    public function index()
    {
        $utilisateur = Session::get('utilisateur', []);
        $regle = Schema::hasTable('commissions') ? Commission::globale() : null;

        $commerciaux = Utilisateur::query()
            ->commerciaux()
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get(['id', 'nom', 'prenoms', 'code_commercial', 'statut_compte']);

        $clients = Utilisateur::query()
            ->clients()
            ->whereNotNull('commercial_id')
            ->selectRaw('commercial_id, COUNT(*) as total, SUM(CASE WHEN statut_compte = 1 THEN 1 ELSE 0 END) as actifs')
            ->groupBy('commercial_id')
            ->get()
            ->keyBy('commercial_id');

        $boutiques = Boutique::query()
            ->whereNotNull('commercial_id')
            ->selectRaw('commercial_id, COUNT(*) as total, SUM(CASE WHEN statut = 1 THEN 1 ELSE 0 END) as actives')
            ->groupBy('commercial_id')
            ->get()
            ->keyBy('commercial_id');

        $commandes = Commande::query()
            ->join('utilisateurs as clients', 'clients.id', '=', 'commandes.utilisateur_id')
            ->whereNotNull('clients.commercial_id')
            ->selectRaw('clients.commercial_id as commercial_id, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN commandes.statut = ? THEN 1 ELSE 0 END) as livrees', ['Livré'])
            ->selectRaw('SUM(CASE WHEN commandes.statut = ? THEN 1 ELSE 0 END) as retours', ['Retour'])
            ->selectRaw('SUM(CASE WHEN commandes.statut = ? AND commandes.date_livraison IS NOT NULL THEN commandes.cout_livraison ELSE 0 END) as base_livree', ['Livré'])
            ->groupBy('clients.commercial_id')
            ->get()
            ->keyBy('commercial_id');

        $paiements = Schema::hasTable('paiements_commissions')
            ? PaiementCommission::query()
                ->selectRaw('commercial_id, SUM(montant) as total')
                ->groupBy('commercial_id')
                ->pluck('total', 'commercial_id')
            : collect();

        $parCommercial = $commerciaux->map(function (Utilisateur $commercial) use ($clients, $boutiques, $commandes, $paiements, $regle) {
            $statsCommandes = $commandes->get($commercial->id);
            $total = (int) ($statsCommandes->total ?? 0);
            $livrees = (int) ($statsCommandes->livrees ?? 0);
            $retours = (int) ($statsCommandes->retours ?? 0);
            $commissionDue = $regle ? $regle->montantPourBase((int) ($statsCommandes->base_livree ?? 0)) : 0;
            $commissionPayee = (int) ($paiements->get($commercial->id) ?? 0);

            return (object) [
                'id' => $commercial->id,
                'nom' => trim($commercial->prenoms.' '.$commercial->nom),
                'code' => $commercial->code_commercial,
                'actif' => (bool) $commercial->statut_compte,
                'clients' => (int) ($clients->get($commercial->id)->total ?? 0),
                'clients_actifs' => (int) ($clients->get($commercial->id)->actifs ?? 0),
                'boutiques' => (int) ($boutiques->get($commercial->id)->total ?? 0),
                'boutiques_actives' => (int) ($boutiques->get($commercial->id)->actives ?? 0),
                'commandes' => $total,
                'livrees' => $livrees,
                'non_livrees' => max(0, $total - $livrees - $retours),
                'retours' => $retours,
                'taux_livraison' => $total > 0 ? (int) round(($livrees / $total) * 100) : 0,
                'commission_due' => $commissionDue,
                'commission_payee' => $commissionPayee,
                'reste' => max(0, $commissionDue - $commissionPayee),
            ];
        });

        $stats = [
            'commerciaux_total' => $commerciaux->count(),
            'commerciaux_actifs' => $commerciaux->where('statut_compte', true)->count(),
            'clients_total' => $parCommercial->sum('clients'),
            'clients_actifs' => $parCommercial->sum('clients_actifs'),
            'boutiques_total' => $parCommercial->sum('boutiques'),
            'boutiques_actives' => $parCommercial->sum('boutiques_actives'),
            'commandes_total' => $parCommercial->sum('commandes'),
            'commandes_livrees' => $parCommercial->sum('livrees'),
            'commandes_non_livrees' => $parCommercial->sum('non_livrees'),
            'commandes_retours' => $parCommercial->sum('retours'),
            'commission_due' => $parCommercial->sum('commission_due'),
            'commission_payee' => $parCommercial->sum('commission_payee'),
            'reste_a_payer' => $parCommercial->sum('reste'),
            'taux' => $regle?->taux,
        ];
        $stats['taux_livraison'] = $stats['commandes_total'] > 0
            ? (int) round(($stats['commandes_livrees'] / $stats['commandes_total']) * 100)
            : 0;

        $debut = Carbon::now()->subMonths(6)->startOfMonth();
        $livreesParMois = Commande::query()
            ->join('utilisateurs as clients', 'clients.id', '=', 'commandes.utilisateur_id')
            ->whereNotNull('clients.commercial_id')
            ->where('commandes.statut', 'Livré')
            ->whereDate('commandes.date_livraison', '>=', $debut->toDateString())
            ->pluck('commandes.date_livraison')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        $chartLabels = [];
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $mois = Carbon::now()->subMonths($i)->startOfMonth();
            $chartLabels[] = $mois->format('m/Y');
            $chartData[] = (int) ($livreesParMois->get($mois->format('Y-m')) ?? 0);
        }

        $dernieresCommandes = Commande::query()
            ->whereHas('client', fn ($query) => $query->whereNotNull('commercial_id'))
            ->with(['client.boutique', 'client.commercial'])
            ->orderByDesc('date_reception')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('manager.dashboard', compact(
            'utilisateur',
            'stats',
            'parCommercial',
            'chartLabels',
            'chartData',
            'dernieresCommandes'
        ));
    }
}
