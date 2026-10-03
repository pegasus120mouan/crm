<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContratController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $statut = (string) $request->query('statut', '');

        $contrats = Contrat::query()
            ->with(['livreur', 'moto'])
            ->withSum('paiements', 'montant')
            ->when(array_key_exists($statut, Contrat::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('livreur', fn ($query) => $query->where('nom', 'like', "%{$search}%")->orWhere('prenoms', 'like', "%{$search}%"))
                        ->orWhereHas('moto', fn ($query) => $query->where('immatriculation', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw('CASE WHEN statut = ? THEN 0 ELSE 1 END', [Contrat::STATUT_EN_COURS])
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $enCours = Contrat::query()->enCours()->withSum('paiements', 'montant')->get();

        $stats = [
            'en_cours' => $enCours->count(),
            'soldes' => Contrat::where('statut', Contrat::STATUT_SOLDE)->count(),
            'encaisse' => (int) Paiement::sum('montant'),
            'reste' => $enCours->sum(fn (Contrat $contrat) => $contrat->reste()),
            'retard' => $enCours->sum(fn (Contrat $contrat) => $contrat->retard()),
            'en_retard' => $enCours->filter(fn (Contrat $contrat) => $contrat->retard() > 0)->count(),
        ];

        $livreursDisponibles = Livreur::query()->peuventRecevoirUneMoto()->get();
        $motosDisponibles = Moto::query()->disponibles()->get();

        return view('manager.contrats.index', compact('contrats', 'stats', 'statut', 'livreursDisponibles', 'motosDisponibles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'livreur_id' => ['required', 'integer', Rule::exists('location_vente_livreurs', 'id')],
            'moto_id' => ['required', 'integer', Rule::exists('location_vente_motos', 'id')],
            'prix_achat' => ['required', 'integer', 'min:0'],
            'cout_supplementaire' => ['nullable', 'integer', 'min:0'],
            'marge' => ['nullable', 'integer', 'min:0'],
            'apport' => ['nullable', 'integer', 'min:0'],
            'montant_echeance' => ['required', 'integer', 'min:1'],
            'frequence' => ['required', Rule::in(array_keys(Contrat::FREQUENCES))],
            'date_debut' => ['required', 'date'],
            'mode_apport' => [Rule::requiredIf(fn () => (int) $request->input('apport') > 0), 'nullable', Rule::in(Paiement::MODES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['apport'] = (int) ($validated['apport'] ?? 0);
        $validated['cout_supplementaire'] = (int) ($validated['cout_supplementaire'] ?? 0);
        $validated['marge'] = (int) ($validated['marge'] ?? 0);
        $validated['prix_total'] = $validated['prix_achat'] + $validated['cout_supplementaire'] + $validated['marge'];

        if ($validated['prix_total'] < 1) {
            throw ValidationException::withMessages(['prix_achat' => 'Le montant à rembourser doit être supérieur à 0.']);
        }

        if ($validated['apport'] > $validated['prix_total']) {
            throw ValidationException::withMessages(['apport' => "L'apport ne peut pas dépasser le montant à rembourser."]);
        }

        $contrat = DB::transaction(function () use ($validated) {
            $livreur = Livreur::query()->lockForUpdate()->findOrFail($validated['livreur_id']);
            $moto = Moto::query()->lockForUpdate()->findOrFail($validated['moto_id']);

            if (! $livreur->statut) {
                throw ValidationException::withMessages(['livreur_id' => 'Ce livreur est inactif : acceptez son KYC avant de lui attribuer une moto.']);
            }

            if ($livreur->contratEnCours()->exists()) {
                throw ValidationException::withMessages(['livreur_id' => 'Ce livreur a déjà un contrat de location-vente en cours.']);
            }

            if ($moto->statut !== Moto::STATUT_DISPONIBLE) {
                throw ValidationException::withMessages(['moto_id' => "Cette moto n'est pas disponible."]);
            }

            $contrat = Contrat::create([
                'livreur_id' => $livreur->id,
                'moto_id' => $moto->id,
                'prix_achat' => $validated['prix_achat'],
                'cout_supplementaire' => $validated['cout_supplementaire'],
                'marge' => $validated['marge'],
                'prix_total' => $validated['prix_total'],
                'apport' => $validated['apport'],
                'montant_echeance' => $validated['montant_echeance'],
                'frequence' => $validated['frequence'],
                'date_debut' => $validated['date_debut'],
                'statut' => Contrat::STATUT_EN_COURS,
                'notes' => $validated['notes'] ?? null,
            ]);
            $contrat->attribuerReference();
            $moto->update(['statut' => Moto::STATUT_EN_LOCATION]);

            if ($validated['apport'] > 0) {
                $contrat->paiements()->create([
                    'montant' => $validated['apport'],
                    'date_paiement' => $validated['date_debut'],
                    'type' => Paiement::TYPE_APPORT,
                    'mode' => $validated['mode_apport'],
                    'notes' => 'Apport initial',
                    'enregistre_par' => $this->utilisateurConnecteId(),
                ]);
            }

            $contrat->synchroniserStatut($contrat->date_debut);

            return $contrat;
        });

        return redirect()->route('manager.contrats.show', $contrat)->with('success', 'Contrat '.$contrat->reference.' créé : la moto est remise au livreur.');
    }

    public function show(Contrat $contrat)
    {
        $contrat->load(['livreur', 'moto', 'paiements' => fn ($query) => $query->with('auteur')->orderByDesc('date_paiement')->orderByDesc('id')]);

        return view('manager.contrats.show', [
            'contrat' => $contrat,
            'echeancier' => $contrat->echeancier(),
        ]);
    }

    public function resilier(Request $request, Contrat $contrat)
    {
        $validated = $request->validate([
            'date_resiliation' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($contrat->statut !== Contrat::STATUT_EN_COURS) {
            return back()->with('error', 'Seul un contrat en cours peut être résilié.');
        }

        DB::transaction(function () use ($contrat, $validated) {
            $notes = trim(($contrat->notes ? $contrat->notes."\n" : '').($validated['motif'] ? 'Résiliation : '.$validated['motif'] : ''));

            $contrat->update([
                'statut' => Contrat::STATUT_RESILIE,
                'date_resiliation' => $validated['date_resiliation'],
                'notes' => $notes !== '' ? $notes : null,
            ]);
            $contrat->moto()->update(['statut' => Moto::STATUT_DISPONIBLE]);
        });

        return redirect()->route('manager.contrats.show', $contrat)->with('success', 'Contrat résilié : la moto revient dans le parc (disponible).');
    }

    private function utilisateurConnecteId(): ?int
    {
        $id = (int) data_get(session('utilisateur'), 'id', 0);

        return $id > 0 ? $id : null;
    }
}
