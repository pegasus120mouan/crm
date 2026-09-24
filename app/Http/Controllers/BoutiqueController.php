<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Commune;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BoutiqueController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $commercialId = $this->commercialId();

        $boutiques = Boutique::query()
            ->forCommercial($commercialId)
            ->with(['gerant', 'commune'])
            ->withCount('utilisateurs')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nom', 'like', "%{$search}%")
                        ->orWhere('type_articles', 'like', "%{$search}%")
                        ->orWhereHas('commune', function ($query) use ($search) {
                            $query->where('nom_commune', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('nom')
            ->paginate(20)
            ->withQueryString();

        $boutiquesActives = Boutique::query()->forCommercial($commercialId)->where('statut', 1)->count();
        $boutiquesInactives = Boutique::query()->forCommercial($commercialId)->where('statut', 0)->count();
        $clientsTotal = Utilisateur::query()->clients()->forCommercial($commercialId)->whereNotNull('boutique_id')->count();
        $communes = Commune::query()->orderBy('nom_commune')->get();

        return view('boutiques.index', compact(
            'boutiques',
            'boutiquesActives',
            'boutiquesInactives',
            'clientsTotal',
            'communes'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'type_articles' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'commune_id' => ['required', 'integer', 'exists:communes,commune_id'],
        ]);

        Boutique::query()->create([
            'nom' => $validated['nom'],
            'type_articles' => $validated['type_articles'] ?? null,
            'statut' => $validated['statut'] ?? true,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'commune_id' => $validated['commune_id'],
            'commercial_id' => $this->commercialId(),
            'logo' => 'boutiques/default_boutiques.png',
        ]);

        return redirect()->route('boutiques.index')->with('success', 'Boutique ajoutée avec succès');
    }

    public function show(Boutique $boutique)
    {
        $this->authorizeOwnedBoutique($boutique);

        $boutique->load(['gerant', 'commune']);

        $clientsBoutique = Utilisateur::query()
            ->clients()
            ->where('boutique_id', $boutique->id)
            ->get();

        $clients = Utilisateur::query()
            ->clients()
            ->forCommercial($this->commercialId())
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get();

        $clientsTotal = $clientsBoutique->count();
        $clientsActifs = $clientsBoutique->where('statut_compte', true)->count();
        $clientsInactifs = $clientsBoutique->where('statut_compte', false)->count();
        $commandesCount = $boutique->commandes()->count();
        $boutiquesTotal = Boutique::query()->forCommercial($this->commercialId())->count();

        return view('boutiques.show', compact(
            'boutique',
            'clients',
            'clientsTotal',
            'clientsActifs',
            'clientsInactifs',
            'commandesCount',
            'boutiquesTotal'
        ));
    }

    public function update(Request $request, Boutique $boutique)
    {
        $this->authorizeOwnedBoutique($boutique);

        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'type_articles' => ['sometimes', 'nullable', 'string', 'max:255'],
            'statut' => ['sometimes', 'nullable', 'boolean'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'commune_id' => ['sometimes', 'required', 'integer', 'exists:communes,commune_id'],
            'gerant_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('utilisateurs', 'id')->where(function ($query) {
                    $query->where('role', 'clients')->where('commercial_id', $this->commercialId());
                }),
            ],
            'logo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'redirect_to' => ['sometimes', 'nullable', 'string'],
        ]);

        $data = [];

        if (array_key_exists('nom', $validated)) {
            $data['nom'] = $validated['nom'];
        }

        if (array_key_exists('type_articles', $validated)) {
            $data['type_articles'] = $validated['type_articles'];
        }

        if (array_key_exists('statut', $validated)) {
            $data['statut'] = (bool) $validated['statut'];
        }

        if (array_key_exists('latitude', $validated)) {
            $data['latitude'] = $validated['latitude'];
        }

        if (array_key_exists('longitude', $validated)) {
            $data['longitude'] = $validated['longitude'];
        }

        if (array_key_exists('commune_id', $validated)) {
            $data['commune_id'] = $validated['commune_id'];
        }

        if ($request->hasFile('logo')) {
            $ancienLogo = $boutique->logo;
            $data['logo'] = $this->storeBoutiqueLogo($request->file('logo'));
            $this->deleteBoutiqueLogo($ancienLogo);
        }

        if ($data !== []) {
            $boutique->update($data);
        }

        if (! empty($validated['gerant_id'])) {
            $nouveauGerant = Utilisateur::query()
                ->clients()
                ->forCommercial($this->commercialId())
                ->where('id', $validated['gerant_id'])
                ->firstOrFail();

            $ancienGerant = $boutique->gerant()->first();
            if ($ancienGerant && $ancienGerant->id !== $nouveauGerant->id) {
                $ancienGerant->update(['boutique_id' => null]);
            }

            $nouveauGerant->update(['boutique_id' => $boutique->id]);
        }

        if ($request->input('redirect_to') === 'show') {
            return redirect()
                ->route('boutiques.show', $boutique)
                ->with('success', 'Boutique modifiée avec succès');
        }

        return redirect()->route('boutiques.index')->with('success', 'Boutique modifiée avec succès');
    }

    public function destroy(Boutique $boutique)
    {
        $this->authorizeOwnedBoutique($boutique);

        if ($boutique->commandes()->exists()) {
            return redirect()
                ->route('boutiques.index')
                ->with('error', 'Impossible de supprimer cette boutique : des commandes y sont rattachées.');
        }

        DB::transaction(function () use ($boutique) {
            Utilisateur::query()->where('boutique_id', $boutique->id)->update(['boutique_id' => null]);
            $boutique->delete();
        });

        return redirect()->route('boutiques.index')->with('success', 'Boutique supprimée avec succès');
    }

    public function logo(Boutique $boutique)
    {
        $this->authorizeOwnedBoutique($boutique);

        $key = $boutique->logo ?: 'boutiques/default_boutiques.png';
        $contents = $this->readBoutiqueLogo($key);

        if (is_string($contents) && $contents !== '') {
            $mime = 'image/png';
            if (str_starts_with($contents, "\x89PNG")) {
                $mime = 'image/png';
            } elseif (str_starts_with($contents, "\xFF\xD8\xFF")) {
                $mime = 'image/jpeg';
            } elseif (str_starts_with($contents, 'RIFF')) {
                $mime = 'image/webp';
            }

            return response($contents, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        $letter = mb_strtoupper(mb_substr($boutique->nom ?: 'B', 0, 1));
        $letter = htmlspecialchars($letter, ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">
  <circle cx="40" cy="40" r="40" fill="#e7e7ff"/>
  <text x="50%" y="54%" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="32" font-weight="600" fill="#696cff">{$letter}</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    private function readBoutiqueLogo(string $key): ?string
    {
        try {
            $contents = Storage::disk('r2')->get($key);
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        } catch (\Throwable) {
            //
        }

        try {
            $url = Storage::disk('r2')->temporaryUrl($key, now()->addMinutes(5));
            $contents = @file_get_contents($url);
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        } catch (\Throwable) {
            //
        }

        return null;
    }

    private function storeBoutiqueLogo(UploadedFile $logo): string
    {
        try {
            $path = $logo->store('boutiques', 'r2');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'logo' => "Impossible d'envoyer le logo vers Cloudflare (ovl-delivery/boutiques).",
            ]);
        }

        if (! is_string($path) || ! str_starts_with($path, 'boutiques/')) {
            throw ValidationException::withMessages([
                'logo' => "L'enregistrement du logo vers Cloudflare R2 a échoué.",
            ]);
        }

        return $path;
    }

    private function deleteBoutiqueLogo(?string $logoKey): void
    {
        if (! $logoKey || $logoKey === 'boutiques/default_boutiques.png') {
            return;
        }

        try {
            Storage::disk('r2')->delete($logoKey);
        } catch (\Throwable) {
            //
        }
    }
}
