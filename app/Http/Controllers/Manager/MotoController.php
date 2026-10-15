<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\MotoPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MotoController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $statut = (string) $request->query('statut', '');

        $motos = Moto::query()
            ->with(['contratActuel.livreur', 'photos'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('immatriculation', 'like', "%{$search}%")
                        ->orWhere('marque', 'like', "%{$search}%")
                        ->orWhere('modele', 'like', "%{$search}%")
                        ->orWhere('numero_chassis', 'like', "%{$search}%");
                });
            })
            ->when($statut === 'parc', fn ($query) => $query->dansLeParc())
            ->when(array_key_exists($statut, Moto::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->orderBy('marque')
            ->orderBy('immatriculation')
            ->paginate(20)
            ->withQueryString();

        $parStatut = Moto::query()->selectRaw('statut, COUNT(*) as total')->groupBy('statut')->pluck('total', 'statut');

        $stats = [
            'parc' => $parStatut->except(Moto::STATUT_CEDEE)->sum(),
            'disponibles' => (int) ($parStatut[Moto::STATUT_DISPONIBLE] ?? 0),
            'en_location' => (int) ($parStatut[Moto::STATUT_EN_LOCATION] ?? 0),
            'maintenance' => (int) ($parStatut[Moto::STATUT_MAINTENANCE] ?? 0),
            'cedees' => (int) ($parStatut[Moto::STATUT_CEDEE] ?? 0),
        ];

        $livreursDisponibles = Livreur::query()->peuventRecevoirUneMoto()->get();
        $motosDisponibles = Moto::query()->disponibles()->get();

        return view('manager.motos.index', compact('motos', 'stats', 'statut', 'livreursDisponibles', 'motosDisponibles'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data) {
            Moto::create($data)->enregistrerPhotos($this->photosRecues($request));
        });

        return redirect()->route('manager.motos.index')->with('success', 'Moto ajoutée au parc');
    }

    public function update(Request $request, Moto $moto)
    {
        $data = $this->validated($request, $moto);

        if (! in_array($moto->statut, Moto::STATUTS_MANUELS, true)) {
            unset($data['statut']);
        }

        DB::transaction(function () use ($request, $moto, $data) {
            $moto->update($data);
            $moto->enregistrerPhotos($this->photosRecues($request));
        });

        return redirect()->route('manager.motos.index')->with('success', 'Moto modifiée avec succès');
    }

    public function photo(Moto $moto, string $face)
    {
        $photo = $moto->photosParFace()[$face] ?? null;
        $contenu = $photo ? rescue(fn () => Storage::disk(Moto::DISQUE)->get($photo->chemin), null, report: false) : null;

        abort_unless(is_string($contenu) && $contenu !== '', 404);

        return response($contenu, 200, [
            'Content-Type' => $photo->mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function destroy(Moto $moto)
    {
        if ($moto->contrats()->exists()) {
            return redirect()
                ->route('manager.motos.index')
                ->with('error', 'Impossible de supprimer cette moto : elle est liée à un contrat de location-vente.');
        }

        $moto->delete();

        return redirect()->route('manager.motos.index')->with('success', 'Moto supprimée avec succès');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Moto $moto = null): array
    {
        $request->merge([
            'immatriculation' => $request->filled('immatriculation') ? strtoupper(trim((string) $request->input('immatriculation'))) : null,
            'numero_chassis' => $request->filled('numero_chassis') ? strtoupper(trim((string) $request->input('numero_chassis'))) : null,
        ]);

        $reglesPhotos = [];
        $messagesPhotos = [];
        foreach (MotoPhoto::FACES as $face => $libelle) {
            $reglesPhotos["photos.{$face}"] = [$moto ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $messagesPhotos["photos.{$face}.required"] = "La photo « {$libelle} » est obligatoire.";
            $messagesPhotos["photos.{$face}.image"] = "La photo « {$libelle} » doit être une image (JPG, PNG ou WEBP).";
            $messagesPhotos["photos.{$face}.mimes"] = "La photo « {$libelle} » doit être une image (JPG, PNG ou WEBP).";
            $messagesPhotos["photos.{$face}.max"] = "La photo « {$libelle} » ne doit pas dépasser 5 Mo.";
        }

        $validated = $request->validate([
            'marque' => ['required', 'string', 'max:100'],
            'modele' => ['nullable', 'string', 'max:100'],
            'immatriculation' => ['nullable', 'string', 'max:50', Rule::unique('location_vente_motos', 'immatriculation')->ignore($moto?->id)],
            'numero_chassis' => ['nullable', 'required_without:immatriculation', 'string', 'max:100', Rule::unique('location_vente_motos', 'numero_chassis')->ignore($moto?->id)],
            'couleur' => ['nullable', 'string', 'max:50'],
            'annee' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'carburant' => ['nullable', Rule::in(array_keys(Moto::CARBURANTS))],
            'cylindree' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'date_acquisition' => ['nullable', 'date'],
            'statut' => [$moto ? 'nullable' : 'required', Rule::in(Moto::STATUTS_MANUELS)],
            'photos' => ['nullable', 'array'],
            ...$reglesPhotos,
        ], [
            'numero_chassis.required_without' => "Le n° de châssis est obligatoire tant que l'immatriculation est en cours.",
            ...$messagesPhotos,
        ]);

        return collect($validated)->except('photos')->all();
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function photosRecues(Request $request): array
    {
        $photos = [];
        foreach (array_keys(MotoPhoto::FACES) as $face) {
            if ($request->hasFile("photos.{$face}")) {
                $photos[$face] = $request->file("photos.{$face}");
            }
        }

        return $photos;
    }
}
