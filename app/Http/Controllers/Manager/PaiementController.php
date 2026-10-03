<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Paiement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaiementController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
            'livreur_id' => ['nullable', 'integer'],
            'mode' => ['nullable', 'string'],
        ]);

        $query = Paiement::query()
            ->when($filtres['du'] ?? null, fn ($query, $du) => $query->whereDate('date_paiement', '>=', $du))
            ->when($filtres['au'] ?? null, fn ($query, $au) => $query->whereDate('date_paiement', '<=', $au))
            ->when($filtres['mode'] ?? null, fn ($query, $mode) => $query->where('mode', $mode))
            ->when($filtres['livreur_id'] ?? null, fn ($query, $livreurId) => $query->whereHas('contrat', fn ($query) => $query->where('livreur_id', $livreurId)));

        $totalFiltre = (int) (clone $query)->sum('montant');
        $nombreFiltre = (clone $query)->count();

        $paiements = $query
            ->with(['contrat.livreur', 'contrat.moto', 'auteur'])
            ->orderByDesc('date_paiement')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'aujourdhui' => (int) Paiement::whereDate('date_paiement', today())->sum('montant'),
            'mois' => (int) Paiement::whereBetween('date_paiement', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('montant'),
            'total' => (int) Paiement::sum('montant'),
        ];

        $contratsEnCours = Contrat::query()
            ->enCours()
            ->with(['livreur', 'moto'])
            ->withSum('paiements', 'montant')
            ->orderBy('reference')
            ->get();

        $livreurs = Livreur::query()->has('contrats')->orderBy('nom')->orderBy('prenoms')->get();

        return view('manager.paiements.index', compact('paiements', 'stats', 'totalFiltre', 'nombreFiltre', 'contratsEnCours', 'livreurs', 'filtres'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contrat_id' => ['required', 'integer', Rule::exists('location_vente_contrats', 'id')],
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date', 'before_or_equal:today'],
            'mode' => ['required', Rule::in(Paiement::MODES)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $contrat = DB::transaction(function () use ($validated) {
            $contrat = Contrat::query()->lockForUpdate()->findOrFail($validated['contrat_id']);

            if ($contrat->statut !== Contrat::STATUT_EN_COURS) {
                throw ValidationException::withMessages(['contrat_id' => "Ce contrat n'est plus en cours : aucun paiement ne peut être ajouté."]);
            }

            if ($validated['montant'] > $contrat->reste()) {
                throw ValidationException::withMessages(['montant' => 'Le montant dépasse le reste à payer ('.number_format($contrat->reste(), 0, ',', ' ').' FCFA).']);
            }

            $contrat->paiements()->create([
                'montant' => $validated['montant'],
                'date_paiement' => $validated['date_paiement'],
                'type' => Paiement::TYPE_ECHEANCE,
                'mode' => $validated['mode'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'enregistre_par' => ((int) data_get(session('utilisateur'), 'id', 0)) ?: null,
            ]);

            $contrat->synchroniserStatut(Carbon::parse($validated['date_paiement']));

            return $contrat;
        });

        $message = $contrat->statut === Contrat::STATUT_SOLDE
            ? 'Paiement enregistré. Contrat soldé : la moto sort du parc et devient la propriété de '.$contrat->livreur->nomComplet().'.'
            : 'Paiement enregistré. Reste à payer : '.number_format($contrat->reste(), 0, ',', ' ').' FCFA.';

        return back()->with('success', $message);
    }

    public function destroy(Paiement $paiement)
    {
        $contrat = $paiement->contrat;

        if ($contrat->statut === Contrat::STATUT_RESILIE) {
            return back()->with('error', "Ce contrat est résilié : ses paiements ne peuvent plus être annulés.");
        }

        DB::transaction(function () use ($paiement, $contrat) {
            $paiement->delete();
            $contrat->synchroniserStatut();
        });

        $message = $contrat->wasChanged('statut')
            ? 'Paiement annulé. Le contrat repasse en cours et la moto revient en location-vente.'
            : 'Paiement annulé.';

        return back()->with('success', $message);
    }
}
