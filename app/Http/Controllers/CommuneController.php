<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use Illuminate\Http\Request;

class CommuneController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $communes = Commune::query()
            ->withCount('boutiques')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('nom_commune', 'like', "%{$search}%");
            })
            ->orderBy('nom_commune')
            ->paginate(20)
            ->withQueryString();

        return view('communes.index', compact('communes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_commune' => ['required', 'string', 'max:255', 'unique:communes,nom_commune'],
        ]);

        Commune::query()->create($validated);

        return redirect()->route('communes.index')->with('success', 'Commune ajoutée avec succès');
    }

    public function update(Request $request, Commune $commune)
    {
        $validated = $request->validate([
            'nom_commune' => ['required', 'string', 'max:255', 'unique:communes,nom_commune,'.$commune->commune_id.',commune_id'],
        ]);

        $commune->update($validated);

        return redirect()->route('communes.index')->with('success', 'Commune modifiée avec succès');
    }

    public function destroy(Commune $commune)
    {
        if ($commune->boutiques()->exists()) {
            return redirect()
                ->route('communes.index')
                ->with('error', 'Impossible de supprimer cette commune : des boutiques y sont rattachées.');
        }

        $commune->delete();

        return redirect()->route('communes.index')->with('success', 'Commune supprimée avec succès');
    }
}
