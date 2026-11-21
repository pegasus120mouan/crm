<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Gerant;
use App\Models\LocationVente\GerantPaiement;
use App\Models\LocationVente\Immobilisation;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\MotoDocument;
use App\Models\LocationVente\MotoPhoto;
use App\Models\LocationVente\Paiement;
use App\Models\LocationVente\TypeMoto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MotoController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->validate([
            'modele' => ['nullable', 'string', 'max:100'],
            'immatriculation' => ['nullable', 'string', 'max:50'],
            'chassis' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', 'string', 'max:20'],
            'livreur_id' => ['nullable', 'integer'],
            'acquisition_du' => ['nullable', 'date'],
            'acquisition_au' => ['nullable', 'date'],
        ]);

        $modele = trim((string) ($filtres['modele'] ?? ''));
        $immatriculation = trim((string) ($filtres['immatriculation'] ?? ''));
        $chassis = trim((string) ($filtres['chassis'] ?? ''));
        $statut = (string) ($filtres['statut'] ?? '');
        $filtres['modele'] = $modele;
        $filtres['immatriculation'] = $immatriculation;
        $filtres['chassis'] = $chassis;
        $filtres['statut'] = $statut;

        $motos = Moto::query()
            ->with(['contratActuel.livreur', 'contratEnCours.livreur', 'immobilisationEnCours', 'gerant', 'typeMoto', 'photos', 'documents'])
            ->withSum('depenses', 'montant')
            ->withSum('paiements', 'montant')
            ->when($modele !== '', fn ($query) => $query->where('modele', 'like', $this->contient($modele)))
            ->when($immatriculation !== '', fn ($query) => $query->where('immatriculation', 'like', $this->contient(mb_strtoupper($immatriculation))))
            ->when($chassis !== '', fn ($query) => $query->where('numero_chassis', 'like', $this->contient(mb_strtoupper($chassis))))
            ->when($statut === 'parc', fn ($query) => $query->dansLeParc())
            ->when(array_key_exists($statut, Moto::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when($filtres['livreur_id'] ?? null, function ($query, $livreurId) {
                $query->whereHas('contrats', function ($contrat) use ($livreurId) {
                    $contrat->where('livreur_id', $livreurId)
                        ->whereIn('statut', [Contrat::STATUT_EN_COURS, Contrat::STATUT_SOLDE]);
                });
            })
            ->when($filtres['acquisition_du'] ?? null, fn ($query, $du) => $query->whereDate('date_acquisition', '>=', $du))
            ->when($filtres['acquisition_au'] ?? null, fn ($query, $au) => $query->whereDate('date_acquisition', '<=', $au))
            ->orderBy('marque')
            ->orderBy('immatriculation')
            ->paginate(20)
            ->withQueryString();

        $parStatut = Moto::query()->selectRaw('statut, COUNT(*) as total')->groupBy('statut')->pluck('total', 'statut');

        $stats = [
            'parc' => $parStatut->except(Moto::STATUT_CEDEE)->sum(),
            'disponibles' => (int) ($parStatut[Moto::STATUT_DISPONIBLE] ?? 0),
            'en_location' => (int) ($parStatut[Moto::STATUT_EN_LOCATION] ?? 0),
            'chez_gerant' => (int) ($parStatut[Moto::STATUT_CHEZ_GERANT] ?? 0),
            'maintenance' => (int) ($parStatut[Moto::STATUT_MAINTENANCE] ?? 0),
            'en_panne' => (int) ($parStatut[Moto::STATUT_EN_PANNE] ?? 0),
            'cedees' => (int) ($parStatut[Moto::STATUT_CEDEE] ?? 0),
        ];

        $livreursDisponibles = Livreur::query()->peuventRecevoirUneMoto()->get();
        $motosDisponibles = Moto::query()->disponibles()->with('typeMoto')->withSum('depenses', 'montant')->get();
        $gerantsActifs = Gerant::query()->where('statut', true)->orderBy('nom')->orderBy('prenoms')->get();
        $typesMoto = TypeMoto::query()->orderBy('nom')->get();
        $livreursRecherche = Livreur::query()
            ->whereHas('contrats', fn ($contrat) => $contrat->whereIn('statut', [Contrat::STATUT_EN_COURS, Contrat::STATUT_SOLDE]))
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get();

        return view('manager.motos.index', compact('motos', 'stats', 'statut', 'filtres', 'livreursDisponibles', 'motosDisponibles', 'gerantsActifs', 'livreursRecherche', 'typesMoto'));
    }

    private function contient(string $valeur): string
    {
        return '%'.addcslashes($valeur, '%_\\').'%';
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
        $moto->load([
            'photos',
            'documents',
            'gerant',
            'immobilisations.contrat',
            'depenses' => fn ($query) => $query->with('contrat.livreur')->orderByDesc('date_depense')->orderByDesc('id'),
            'paiements' => fn ($query) => $query->with('contrat.livreur')->orderByDesc('date_paiement')->orderByDesc('location_vente_paiements.id'),
            'paiementsGerant' => fn ($query) => $query->orderByDesc('date_paiement')->orderByDesc('id'),
        ]);
        $bilan = $moto->bilanFinancier();
        $gerantsActifs = Gerant::query()->where('statut', true)->orderBy('nom')->orderBy('prenoms')->get();

        return view('manager.motos.show', compact('moto', 'bilan', 'gerantsActifs'));
    }

    public function confierGerant(Request $request, Moto $moto)
    {
        $data = $request->validate([
            'gerant_id' => ['required', 'integer', 'exists:location_vente_gerants,id'],
        ]);

        if (! $moto->peutEtreConfieeAUnGerant()) {
            throw ValidationException::withMessages([
                'gerant_id' => 'Seule une moto disponible, sans contrat en cours, peut être confiée à un gérant.',
            ]);
        }

        $gerant = Gerant::query()->findOrFail($data['gerant_id']);
        if (! $gerant->estActif()) {
            throw ValidationException::withMessages([
                'gerant_id' => 'Ce gérant est inactif.',
            ]);
        }

        $moto->update([
            'gerant_id' => $gerant->id,
            'statut' => Moto::STATUT_CHEZ_GERANT,
        ]);

        return back()->with('success', 'Moto confiée à '.$gerant->nomComplet().'. Elle n’est plus disponible pour un contrat location-vente.');
    }

    public function restituerGerant(Moto $moto)
    {
        if (! $moto->estChezGerant()) {
            return back()->with('error', 'Cette moto n’est pas confiée à un gérant.');
        }

        $moto->update([
            'gerant_id' => null,
            'statut' => Moto::STATUT_DISPONIBLE,
        ]);

        return back()->with('success', 'Moto restituée : elle est de nouveau disponible.');
    }

    public function enregistrerPaiementGerant(Request $request, Moto $moto)
    {
        if (! $moto->estChezGerant()) {
            return back()->with('error', 'Les paiements gérant ne concernent que les motos confiées.');
        }

        $data = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date', 'before_or_equal:today'],
            'mode' => ['required', Rule::in(Paiement::MODES)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        GerantPaiement::create([
            'gerant_id' => $moto->gerant_id,
            'moto_id' => $moto->id,
            'montant' => $data['montant'],
            'date_paiement' => $data['date_paiement'],
            'mode' => $data['mode'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'enregistre_par' => $request->session()->get('utilisateur.id'),
        ]);

        return back()->with('success', 'Paiement du gérant enregistré.');
    }

    public function declarerPanne(Request $request, Moto $moto)
    {
        $data = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'motif' => ['nullable', 'string', 'max:500'],
            'statut' => ['required', Rule::in(array_keys(Immobilisation::STATUTS))],
        ]);

        $data['date_fin'] = $data['date_fin'] ?? null;
        $data['motif'] = $data['motif'] ?? null;

        if ($moto->statut === Moto::STATUT_CEDEE) {
            throw ValidationException::withMessages([
                'statut' => 'Une moto cédée ne peut pas être déclarée en panne.',
            ]);
        }

        if ($data['statut'] === Immobilisation::STATUT_TERMINEE && blank($data['date_fin'] ?? null)) {
            throw ValidationException::withMessages([
                'date_fin' => 'La date de fin est obligatoire pour clôturer l\'immobilisation.',
            ]);
        }

        if (
            $data['statut'] === Immobilisation::STATUT_EN_COURS
            && filled($data['date_fin'] ?? null)
            && Carbon::parse($data['date_fin'])->startOfDay()->lt(Carbon::today())
        ) {
            throw ValidationException::withMessages([
                'statut' => 'Cette période est déjà terminée : choisissez le statut Terminée.',
            ]);
        }

        $contrat = $moto->contratEnCours;

        if ($contrat && Carbon::parse($data['date_debut'])->startOfDay()->lt($contrat->date_debut->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'date_debut' => 'La panne ne peut pas commencer avant le début du contrat ('.$contrat->date_debut->format('d/m/Y').').',
            ]);
        }

        DB::transaction(function () use ($moto, $contrat, $data) {
            $motoVerrouillee = Moto::query()->lockForUpdate()->findOrFail($moto->id);

            if ($motoVerrouillee->immobilisations()->where('statut', Immobilisation::STATUT_EN_COURS)->exists()) {
                throw ValidationException::withMessages([
                    'statut' => 'Cette moto a déjà une immobilisation en cours.',
                ]);
            }

            $immobilisation = $motoVerrouillee->immobilisations()->create([
                'contrat_id' => $contrat?->id,
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'] ?: null,
                'motif' => $data['motif'] ?? null,
                'statut' => $data['statut'],
            ]);

            if ($immobilisation->statut === Immobilisation::STATUT_EN_COURS) {
                $motoVerrouillee->update(['statut' => Moto::STATUT_EN_PANNE]);
            }

            if ($contrat && $immobilisation->date_fin) {
                $contrat->oublierEcheancier();
                $contrat->reporterEcheancesApresImmobilisation();
            }
        });

        $message = $data['statut'] === Immobilisation::STATUT_EN_COURS
            ? 'Moto déclarée en panne. Les paiements de la période d\'immobilisation sont suspendus.'
            : 'Immobilisation enregistrée. Les échéances de la période sont reportées.';

        return back()->with('success', $message);
    }

    public function modifierPanne(Request $request, Moto $moto)
    {
        $panne = $moto->immobilisationEnCours;

        if (! $panne) {
            return back()->with('error', 'Cette moto n\'a pas d\'immobilisation en cours.');
        }

        $data = $request->validate([
            'date_debut' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        $data['motif'] = $data['motif'] ?? null;
        $contrat = $panne->contrat ?? $moto->contratEnCours;

        if ($contrat && Carbon::parse($data['date_debut'])->startOfDay()->lt($contrat->date_debut->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'date_debut' => 'La panne ne peut pas commencer avant le début du contrat ('.$contrat->date_debut->format('d/m/Y').').',
            ]);
        }

        if (
            $panne->date_fin
            && Carbon::parse($data['date_debut'])->startOfDay()->gt($panne->date_fin->copy()->startOfDay())
        ) {
            throw ValidationException::withMessages([
                'date_debut' => 'La date de début ne peut pas être après la date de fin ('.$panne->date_fin->format('d/m/Y').').',
            ]);
        }

        DB::transaction(function () use ($panne, $contrat, $data) {
            $panne->update([
                'date_debut' => $data['date_debut'],
                'motif' => $data['motif'],
            ]);

            if ($contrat && $contrat->statut === Contrat::STATUT_EN_COURS) {
                $contrat->oublierEcheancier();
                if ($panne->date_fin) {
                    $contrat->reporterEcheancesApresImmobilisation();
                }
            }
        });

        return back()->with('success', 'Date de début de panne mise à jour. Les échéances suspendues ont été recalculées.');
    }

    public function cloturerPanne(Request $request, Moto $moto)
    {
        $panne = $moto->immobilisationEnCours;

        if (! $panne) {
            return back()->with('error', 'Cette moto n\'a pas d\'immobilisation en cours.');
        }

        $data = $request->validate([
            'date_fin' => ['required', 'date', 'after_or_equal:'.$panne->date_debut->toDateString()],
        ]);

        DB::transaction(function () use ($moto, $panne, $data) {
            $panne->update([
                'date_fin' => $data['date_fin'],
                'statut' => Immobilisation::STATUT_TERMINEE,
            ]);

            $moto->update(['statut' => $moto->statutApresPanne()]);

            $contrat = $panne->contrat;
            if ($contrat && $contrat->statut === Contrat::STATUT_EN_COURS) {
                $contrat->oublierEcheancier();
                $contrat->reporterEcheancesApresImmobilisation();
            }
        });

        return back()->with('success', 'Moto remise en service. Les échéances de l\'immobilisation sont reportées.');
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
            'type_moto_id' => ['nullable', 'integer', Rule::exists('location_vente_types_moto', 'id')],
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

        $typeId = $validated['type_moto_id'] ?? null;
        $validated['prix_achat'] = $typeId
            ? (int) TypeMoto::query()->whereKey($typeId)->value('cout_acquisition')
            : 0;

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
