<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $commercialId = $this->commercialId();

        $clients = Utilisateur::query()
            ->with('boutique')
            ->clients()
            ->forCommercial($commercialId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenoms', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhere('login', 'like', "%{$search}%");
                });
            })
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->paginate(20)
            ->withQueryString();

        $clientsActifs = Utilisateur::query()->clients()->forCommercial($commercialId)->where('statut_compte', 1)->count();
        $clientsInactifs = Utilisateur::query()->clients()->forCommercial($commercialId)->where('statut_compte', 0)->count();
        $boutiquesLibres = Boutique::query()
            ->forCommercial($commercialId)
            ->whereDoesntHave('utilisateurs')
            ->orderBy('nom')
            ->get();
        $boutiques = Boutique::query()
            ->forCommercial($commercialId)
            ->withCount('utilisateurs')
            ->orderBy('nom')
            ->get();

        return view('clients.index', compact(
            'clients',
            'clientsActifs',
            'clientsInactifs',
            'boutiquesLibres',
            'boutiques'
        ));
    }

    public function store(Request $request)
    {
        $commercialId = $this->commercialId();

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:15'],
            'login' => ['required', 'string', 'max:255', 'unique:utilisateurs,login'],
            'password' => ['required', 'string', 'min:4'],
            'boutique_id' => ['nullable', 'integer', Rule::exists('boutiques', 'id')->where('commercial_id', $commercialId)],
            'statut_compte' => ['nullable', 'boolean'],
        ]);

        Utilisateur::query()->create([
            'nom' => $validated['nom'],
            'prenoms' => $validated['prenoms'],
            'contact' => $validated['contact'],
            'login' => $validated['login'],
            'password' => hash('sha256', $validated['password']),
            'code_pin' => (string) random_int(100000, 999999),
            'role' => 'clients',
            'boutique_id' => $validated['boutique_id'] ?? null,
            'commercial_id' => $commercialId,
            'statut_compte' => $validated['statut_compte'] ?? true,
            'avatar' => 'utilisateurs/utilisateurs.png',
        ]);

        return redirect()->route('clients.index')->with('success', 'Client ajouté avec succès');
    }

    public function update(Request $request, Utilisateur $client)
    {
        $this->authorizeOwnedClient($client);

        $commercialId = $this->commercialId();

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:15'],
            'boutique_id' => ['nullable', 'integer', Rule::exists('boutiques', 'id')->where('commercial_id', $commercialId)],
            'statut_compte' => ['nullable', 'boolean'],
        ]);

        $client->update([
            'nom' => $validated['nom'],
            'prenoms' => $validated['prenoms'],
            'contact' => $validated['contact'],
            'boutique_id' => $validated['boutique_id'] ?? null,
            'statut_compte' => $validated['statut_compte'] ?? $client->statut_compte,
        ]);

        return redirect()->route('clients.index')->with('success', 'Client modifié avec succès');
    }

    public function destroy(Utilisateur $client)
    {
        $this->authorizeOwnedClient($client);

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client supprimé avec succès');
    }
}
