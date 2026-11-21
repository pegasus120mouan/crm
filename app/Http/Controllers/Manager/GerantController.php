<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Gerant;
use App\Models\LocationVente\Moto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GerantController extends Controller
{
    public function index()
    {
        $gerants = Gerant::query()
            ->withCount(['motos' => fn ($query) => $query->where('statut', Moto::STATUT_CHEZ_GERANT)])
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get();

        return view('manager.gerants.index', compact('gerants'));
    }

    public function store(Request $request)
    {
        $data = $this->valider($request, photoObligatoire: true);
        $gerant = Gerant::create($data);

        if ($request->hasFile('photo')) {
            $gerant->enregistrerPhoto($request->file('photo'));
        }

        return redirect()
            ->route('manager.gerants.show', $gerant)
            ->with('success', 'Gérant ajouté.');
    }

    public function show(Gerant $gerant)
    {
        $gerant->load([
            'motos' => fn ($query) => $query->orderBy('marque')->orderBy('modele'),
            'paiements' => fn ($query) => $query->with('moto')->orderByDesc('date_paiement')->orderByDesc('id')->limit(50),
        ]);

        $motosDisponibles = Moto::query()->disponibles()->get();

        return view('manager.gerants.show', compact('gerant', 'motosDisponibles'));
    }

    public function photo(Gerant $gerant)
    {
        $contenu = $gerant->photo
            ? rescue(fn () => Storage::disk(Gerant::DISQUE)->get($gerant->photo), null, report: false)
            : null;

        if (! is_string($contenu) || $contenu === '') {
            return response()->file(public_path('assets/img/avatars/1.png'), ['Cache-Control' => 'private, max-age=300']);
        }

        return response($contenu, 200, [
            'Content-Type' => Storage::disk(Gerant::DISQUE)->mimeType($gerant->photo) ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function update(Request $request, Gerant $gerant)
    {
        $gerant->update($this->valider($request, photoObligatoire: false));

        if ($request->hasFile('photo')) {
            $gerant->enregistrerPhoto($request->file('photo'));
        }

        return redirect()
            ->route('manager.gerants.show', $gerant)
            ->with('success', 'Gérant modifié.');
    }

    public function destroy(Gerant $gerant)
    {
        if ($gerant->motos()->where('statut', Moto::STATUT_CHEZ_GERANT)->exists()) {
            throw ValidationException::withMessages([
                'gerant' => 'Impossible de supprimer ce gérant : des motos lui sont encore confiées.',
            ]);
        }

        $gerant->motos()->update(['gerant_id' => null]);
        $gerant->delete();

        return redirect()
            ->route('manager.gerants.index')
            ->with('success', 'Gérant supprimé.');
    }

    /**
     * @return array{nom: string, prenoms: string, contact: string, adresse: ?string, notes: ?string, statut: bool}
     */
    private function valider(Request $request, bool $photoObligatoire): array
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:100'],
            'contact' => ['required', 'string', 'max:30'],
            'photo' => [$photoObligatoire ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'statut' => ['nullable', 'boolean'],
        ]);

        unset($data['photo']);
        $data['adresse'] = $data['adresse'] ?? null;
        $data['notes'] = $data['notes'] ?? null;
        $data['statut'] = $request->boolean('statut', true);

        return $data;
    }
}
