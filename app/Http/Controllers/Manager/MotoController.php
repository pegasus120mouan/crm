<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\MotoDocument;
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
            ->with(['contratActuel.livreur', 'photos', 'documents'])
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

    public function show(Moto $moto)
    {
        $moto->load(['photos', 'documents']);

        return view('manager.motos.show', compact('moto'));
    }

    public function enregistrerPhotos(Request $request, Moto $moto)
    {
        $regles = ['photos' => ['nullable', 'array']];
        $messages = [];

        foreach (MotoPhoto::FACES as $face => $libelle) {
            $regles["photos.{$face}"] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $messages["photos.{$face}.image"] = "La photo « {$libelle} » doit être une image (JPG, PNG ou WEBP).";
            $messages["photos.{$face}.mimes"] = "La photo « {$libelle} » doit être une image (JPG, PNG ou WEBP).";
            $messages["photos.{$face}.max"] = "La photo « {$libelle} » ne doit pas dépasser 5 Mo.";
        }

        $request->validate($regles, $messages);

        $photos = $this->photosRecues($request);
        if ($photos === []) {
            return back()->with('error', 'Choisissez au moins une image à remplacer.');
        }

        $moto->enregistrerPhotos($photos);

        return redirect()
            ->route('manager.motos.show', $moto)
            ->with('success', 'Images de la moto modifiées.');
    }

    public function enregistrerPapiers(Request $request, Moto $moto)
    {
        $existants = $moto->documentsParType();
        $regles = ['documents' => ['nullable', 'array']];
        $messages = [];
        $periodes = [
            MotoDocument::TYPE_ASSURANCE => "de l'assurance",
            MotoDocument::TYPE_VIGNETTE => 'de la vignette',
        ];

        foreach ($periodes as $type => $de) {
            $fichierDejaPresent = filled($existants[$type]->chemin ?? null);
            $periodeObligatoire = fn () => $request->hasFile("documents.{$type}.fichier")
                || $fichierDejaPresent
                || $request->filled("documents.{$type}.date_debut")
                || $request->filled("documents.{$type}.date_fin");

            $regles["documents.{$type}.date_debut"] = ['nullable', 'date', Rule::requiredIf($periodeObligatoire)];
            $regles["documents.{$type}.date_fin"] = ['nullable', 'date', "after_or_equal:documents.{$type}.date_debut", Rule::requiredIf($periodeObligatoire)];
            $regles["documents.{$type}.fichier"] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];
            $messages["documents.{$type}.date_debut.required"] = "La date de début {$de} est obligatoire.";
            $messages["documents.{$type}.date_fin.required"] = "La date de fin {$de} est obligatoire.";
            $messages["documents.{$type}.date_fin.after_or_equal"] = "La date de fin {$de} doit être postérieure ou égale à la date de début.";
            $messages["documents.{$type}.fichier.mimes"] = ucfirst(MotoDocument::TYPES[$type]).' : le fichier doit être une image (JPG, PNG, WEBP) ou un PDF.';
            $messages["documents.{$type}.fichier.max"] = ucfirst(MotoDocument::TYPES[$type]).' : le fichier ne doit pas dépasser 5 Mo.';
        }

        $regles['documents.carte_grise.numero'] = ['nullable', 'string', 'max:50'];
        $regles['documents.carte_grise.fichier'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];
        $messages['documents.carte_grise.numero.max'] = 'Le n° de plaque ne doit pas dépasser 50 caractères.';
        $messages['documents.carte_grise.fichier.mimes'] = 'Carte grise : le fichier doit être une image (JPG, PNG, WEBP) ou un PDF.';
        $messages['documents.carte_grise.fichier.max'] = 'Carte grise : le fichier ne doit pas dépasser 5 Mo.';

        if ($request->exists('documents.carte_grise.numero')) {
            $numero = strtoupper(trim((string) $request->input('documents.carte_grise.numero')));
            $request->merge([
                'documents' => array_merge($request->input('documents', []), [
                    'carte_grise' => array_merge($request->input('documents.carte_grise', []), [
                        'numero' => $numero === '' ? null : $numero,
                    ]),
                ]),
            ]);
        }

        $validated = $request->validate($regles, $messages);
        $documents = $validated['documents'] ?? [];
        $saisies = [];

        foreach (array_keys($periodes) as $type) {
            $dateDebut = $documents[$type]['date_debut'] ?? null;
            $dateFin = $documents[$type]['date_fin'] ?? null;
            if ($dateDebut === null && $dateFin === null && ! $request->hasFile("documents.{$type}.fichier")) {
                continue;
            }

            $saisie = [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ];
            if ($request->hasFile("documents.{$type}.fichier")) {
                $saisie['fichier'] = $request->file("documents.{$type}.fichier");
            }
            $saisies[$type] = $saisie;
        }

        $numeroCarte = $documents['carte_grise']['numero'] ?? null;
        $carteExistante = $existants[MotoDocument::TYPE_CARTE_GRISE] ?? null;
        if ($numeroCarte !== null || $request->hasFile('documents.carte_grise.fichier') || filled($carteExistante?->chemin)) {
            $saisie = ['numero' => $numeroCarte];
            if ($request->hasFile('documents.carte_grise.fichier')) {
                $saisie['fichier'] = $request->file('documents.carte_grise.fichier');
            }
            if ($numeroCarte !== null || isset($saisie['fichier']) || filled($carteExistante?->chemin)) {
                $saisies[MotoDocument::TYPE_CARTE_GRISE] = $saisie;
            }
        }

        if ($saisies === []) {
            return back()->with('error', 'Renseignez au moins une date ou une image.');
        }

        $moto->enregistrerDocuments($saisies);

        return redirect()
            ->route('manager.motos.show', $moto)
            ->with('success', 'Documents enregistrés.');
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
            'prix_achat' => ['nullable', 'integer', 'min:0'],
            'date_acquisition' => ['nullable', 'date'],
            'statut' => [$moto ? 'nullable' : 'required', Rule::in(Moto::STATUTS_MANUELS)],
            'photos' => ['nullable', 'array'],
            ...$reglesPhotos,
        ], [
            'numero_chassis.required_without' => "Le n° de châssis est obligatoire tant que l'immatriculation est en cours.",
            ...$messagesPhotos,
        ]);

        $validated['prix_achat'] = (int) ($validated['prix_achat'] ?? 0);

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
