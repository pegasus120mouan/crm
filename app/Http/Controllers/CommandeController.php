<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\CoutLivraison;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $statut = (string) $request->query('statut', '');
        $commercialId = $this->commercialId();

        $commandes = Commande::query()
            ->forCommercial($commercialId)
            ->with(['client.boutique', 'livreur'])
            ->when($statut !== '', fn ($query) => $query->where('statut', $statut))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('communes', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($query) use ($search) {
                            $query->where('nom', 'like', "%{$search}%")
                                ->orWhere('prenoms', 'like', "%{$search}%")
                                ->orWhere('login', 'like', "%{$search}%")
                                ->orWhereHas('boutique', function ($query) use ($search) {
                                    $query->where('nom', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('livreur', function ($query) use ($search) {
                            $query->where('nom', 'like', "%{$search}%")
                                ->orWhere('prenoms', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('date_reception')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $clients = Utilisateur::query()
            ->clients()
            ->forCommercial($commercialId)
            ->with('boutique')
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get();
        $livreurs = Utilisateur::query()
            ->livreurs()
            ->actifs()
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get();
        $coutsLivraison = CoutLivraison::query()
            ->orderBy('cout_livraison')
            ->get();

        $statsQuery = Commande::query()->forCommercial($commercialId);
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'livrees' => (clone $statsQuery)->where('statut', 'Livré')->count(),
            'non_livrees' => (clone $statsQuery)->where('statut', 'Non Livré')->count(),
            'retours' => (clone $statsQuery)->where('statut', 'Retour')->count(),
        ];

        return view('commandes.index', compact('commandes', 'clients', 'livreurs', 'coutsLivraison', 'stats', 'statut'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'utilisateur_id' => ['required', $this->ownedClientRule()],
            'livreur_id' => ['nullable', $this->livreurRule()],
            'communes' => ['required', 'string', 'max:255'],
            'cout_global' => ['required', 'integer', 'min:0'],
            'cout_livraison' => ['required', $this->coutLivraisonRule()],
            'date_reception' => ['required', 'date'],
        ]);

        Commande::query()->create([
            'utilisateur_id' => $validated['utilisateur_id'],
            'livreur_id' => $validated['livreur_id'] ?? null,
            'communes' => $validated['communes'],
            'cout_global' => $validated['cout_global'],
            'cout_livraison' => $validated['cout_livraison'],
            'cout_reel' => $validated['cout_global'] - $validated['cout_livraison'],
            'statut' => 'Non Livré',
            'date_reception' => $validated['date_reception'],
        ]);

        return redirect()->route('commandes.index')->with('success', 'Commande ajoutée avec succès');
    }

    public function update(Request $request, Commande $commande)
    {
        $this->authorizeOwnedCommande($commande);

        $validated = $request->validate([
            'utilisateur_id' => ['required', $this->ownedClientRule()],
            'livreur_id' => ['nullable', $this->livreurRule()],
            'communes' => ['required', 'string', 'max:255'],
            'cout_global' => ['required', 'integer', 'min:0'],
            'cout_livraison' => ['required', $this->coutLivraisonRule()],
            'statut' => ['required', Rule::in(['Non Livré', 'Livré', 'Retour'])],
            'date_reception' => ['required', 'date'],
        ]);

        $data = [
            'utilisateur_id' => $validated['utilisateur_id'],
            'livreur_id' => $validated['livreur_id'] ?? null,
            'communes' => $validated['communes'],
            'cout_global' => $validated['cout_global'],
            'cout_livraison' => $validated['cout_livraison'],
            'cout_reel' => $validated['cout_global'] - $validated['cout_livraison'],
            'statut' => $validated['statut'],
            'date_reception' => $validated['date_reception'],
        ];

        if ($validated['statut'] === 'Livré' && ! $commande->date_livraison) {
            $data['date_livraison'] = now()->toDateString();
        }

        if ($validated['statut'] === 'Retour' && ! $commande->date_retour) {
            $data['date_retour'] = now()->toDateString();
        }

        if ($validated['statut'] === 'Non Livré') {
            $data['date_livraison'] = null;
            $data['date_retour'] = null;
        }

        $commande->update($data);

        return redirect()->route('commandes.index')->with('success', 'Commande modifiée avec succès');
    }

    public function assignLivreur(Request $request, Commande $commande)
    {
        $this->authorizeOwnedCommande($commande);

        $validated = $request->validate([
            'livreur_id' => ['required', $this->livreurRule()],
        ]);

        $commande->update([
            'livreur_id' => $validated['livreur_id'],
        ]);

        return redirect()->route('commandes.index')->with('success', 'Livreur attribué avec succès');
    }

    public function destroy(Commande $commande)
    {
        $this->authorizeOwnedCommande($commande);

        $commande->delete();

        return redirect()->route('commandes.index')->with('success', 'Commande supprimée avec succès');
    }

    private function ownedClientRule(): Exists
    {
        return Rule::exists('utilisateurs', 'id')
            ->where('role', 'clients')
            ->where('commercial_id', $this->commercialId());
    }

    private function livreurRule(): Exists
    {
        return Rule::exists('utilisateurs', 'id')
            ->where('role', 'livreur')
            ->where('statut_compte', 1);
    }

    private function coutLivraisonRule(): Exists
    {
        return Rule::exists('cout_livraison', 'cout_livraison');
    }
}
