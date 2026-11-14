<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\MotoDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MotoDocumentController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $motos = Moto::query()
            ->with(['documents', 'photos'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('immatriculation', 'like', "%{$search}%")
                        ->orWhere('marque', 'like', "%{$search}%")
                        ->orWhere('modele', 'like', "%{$search}%")
                        ->orWhere('numero_chassis', 'like', "%{$search}%")
                        ->orWhereHas('documents', fn ($query) => $query->where('numero', 'like', "%{$search}%"));
                });
            })
            ->orderBy('marque')
            ->orderBy('immatriculation')
            ->paginate(20)
            ->withQueryString();

        $aujourdhui = now()->toDateString();
        $dansTrenteJours = now()->addDays(30)->toDateString();
        $stats = [
            'total' => MotoDocument::query()->count(),
            'expires' => MotoDocument::query()->whereNotNull('date_fin')->whereDate('date_fin', '<', $aujourdhui)->count(),
            'bientot' => MotoDocument::query()->whereNotNull('date_fin')->whereDate('date_fin', '>=', $aujourdhui)->whereDate('date_fin', '<=', $dansTrenteJours)->count(),
        ];

        $choixMotos = Moto::query()->orderBy('marque')->orderBy('immatriculation')->get();

        return view('manager.documents.index', compact('motos', 'choixMotos', 'stats', 'search'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $moto = Moto::query()->findOrFail($data['moto_id']);
        $existait = $moto->documents()->where('type', $data['type'])->exists();

        $moto->enregistrerDocuments([
            $data['type'] => $this->saisie($request, $data),
        ]);

        $libelle = MotoDocument::TYPES[$data['type']];

        return redirect()
            ->route('manager.documents.index')
            ->with('success', $existait
                ? "{$libelle} mise à jour pour {$moto->libelle()}."
                : "{$libelle} associée à {$moto->libelle()}.");
    }

    public function update(Request $request, MotoDocument $document)
    {
        $data = $this->validated($request, $document);

        $document->moto->enregistrerDocuments([
            $document->type => $this->saisie($request, $data),
        ]);

        return redirect()
            ->route('manager.documents.index')
            ->with('success', $document->libelle().' modifiée.');
    }

    public function fichier(MotoDocument $document)
    {
        $contenu = $document->chemin
            ? rescue(fn () => Storage::disk(Moto::DISQUE)->get($document->chemin), null, report: false)
            : null;

        abort_unless(is_string($contenu) && $contenu !== '', 404);

        $nom = $document->type.'.'.pathinfo((string) $document->chemin, PATHINFO_EXTENSION);

        return response($contenu, 200, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$nom.'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(MotoDocument $document)
    {
        $libelle = $document->libelle();

        if ($document->chemin) {
            rescue(fn () => Storage::disk(Moto::DISQUE)->delete($document->chemin), report: false);
        }

        $document->delete();

        return redirect()
            ->route('manager.documents.index')
            ->with('success', "{$libelle} retirée.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?MotoDocument $document = null): array
    {
        $request->merge([
            'numero' => $request->filled('numero') ? strtoupper(trim((string) $request->input('numero'))) : null,
        ]);

        if ($document) {
            $request->merge([
                'moto_id' => $document->moto_id,
                'type' => $document->type,
            ]);
        }

        $avecValidite = in_array($request->input('type'), MotoDocument::AVEC_VALIDITE, true);

        $validated = $request->validate([
            'moto_id' => ['required', 'integer', Rule::exists('location_vente_motos', 'id')],
            'type' => ['required', Rule::in(array_keys(MotoDocument::TYPES))],
            'date_debut' => ['nullable', 'date', Rule::requiredIf($avecValidite)],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut', Rule::requiredIf($avecValidite)],
            'numero' => ['nullable', 'string', 'max:50'],
            'fichier' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'moto_id.required' => 'Choisissez une moto.',
            'moto_id.exists' => 'Cette moto n\'existe pas.',
            'type.required' => 'Choisissez un type de document.',
            'type.in' => 'Ce type de document n\'est pas reconnu.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'numero.max' => 'Le n° d\'immatriculation ne doit pas dépasser 50 caractères.',
            'fichier.mimes' => 'Le fichier doit être une image (JPG, PNG, WEBP) ou un PDF.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ]);

        $cheminExistant = $document?->chemin;
        if ($document === null) {
            $cheminExistant = MotoDocument::query()
                ->where('moto_id', $validated['moto_id'])
                ->where('type', $validated['type'])
                ->value('chemin');
        }

        if ($validated['type'] === MotoDocument::TYPE_CARTE_GRISE
            && blank($validated['numero'] ?? null)
            && ! $request->hasFile('fichier')
            && blank($cheminExistant)) {
            throw ValidationException::withMessages([
                'numero' => 'Indiquez le n° d\'immatriculation ou joignez le fichier de la carte grise.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{date_debut?: ?string, date_fin?: ?string, numero?: ?string, fichier?: \Illuminate\Http\UploadedFile}
     */
    private function saisie(Request $request, array $data): array
    {
        $saisie = [];

        if (in_array($data['type'], MotoDocument::AVEC_VALIDITE, true)) {
            $saisie['date_debut'] = $data['date_debut'];
            $saisie['date_fin'] = $data['date_fin'];
        }

        if ($data['type'] === MotoDocument::TYPE_CARTE_GRISE) {
            $saisie['numero'] = $data['numero'] ?? null;
        }

        if ($request->hasFile('fichier')) {
            $saisie['fichier'] = $request->file('fichier');
        }

        return $saisie;
    }
}
