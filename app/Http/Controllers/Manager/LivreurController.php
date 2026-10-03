<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\LivreurDocument;
use App\Models\LocationVente\Moto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LivreurController extends Controller
{
    private const CHAMPS_IDENTITE = ['nom', 'prenoms', 'date_naissance', 'type_piece', 'numero_piece', 'date_expiration_piece', 'numero_permis', 'categorie_permis', 'date_expiration_permis'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $kyc = (string) $request->query('kyc', '');

        $livreurs = Livreur::query()
            ->withCount('contrats')
            ->with([
                'documents:id,livreur_id,type',
                'contratEnCours' => fn ($query) => $query->with('moto')->withSum('paiements', 'montant'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenoms', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->boolean('statut')))
            ->when(array_key_exists($kyc, Livreur::KYC_STATUTS), fn ($query) => $query->where('kyc_statut', $kyc))
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Livreur::count(),
            'kyc_valides' => Livreur::where('kyc_statut', Livreur::KYC_VALIDE)->count(),
            'kyc_a_verifier' => Livreur::where('kyc_statut', Livreur::KYC_EN_ATTENTE)->count(),
            'sous_contrat' => Livreur::has('contratEnCours')->count(),
            'proprietaires' => Livreur::whereHas('contrats', fn ($query) => $query->where('statut', Contrat::STATUT_SOLDE))->count(),
        ];

        return view('manager.livreurs.index', compact('livreurs', 'stats', 'kyc'));
    }

    public function show(Livreur $livreur)
    {
        $livreur->load([
            'documents',
            'verificateur',
            'contrats' => fn ($query) => $query->with('moto')->withSum('paiements', 'montant')->orderByDesc('date_debut'),
        ]);

        $peutRecevoirUneMoto = $livreur->peutRecevoirUneMoto();

        return view('manager.livreurs.show', [
            'livreur' => $livreur,
            'documents' => $livreur->documents->keyBy('type'),
            'manquants' => $livreur->elementsKycManquants(),
            'peutRecevoirUneMoto' => $peutRecevoirUneMoto,
            'livreursDisponibles' => $peutRecevoirUneMoto ? collect([$livreur]) : collect(),
            'motosDisponibles' => $peutRecevoirUneMoto ? Moto::query()->disponibles()->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = ['statut' => false] + $this->validated($request);

        $livreur = DB::transaction(function () use ($request, $data) {
            $livreur = Livreur::create($data);
            $this->enregistrerFichiers($request, $livreur);
            $livreur->recalculerKyc();

            return $livreur;
        });

        return redirect()
            ->route('manager.livreurs.show', $livreur)
            ->with('success', 'Livreur '.$livreur->fresh()->code.' ajouté avec succès');
    }

    public function update(Request $request, Livreur $livreur)
    {
        $data = $this->validated($request);
        $data['statut'] = (bool) ($data['statut'] ?? $livreur->statut);

        if ($data['statut'] && ! $livreur->kycAccepte()) {
            throw ValidationException::withMessages([
                'statut' => "Le livreur ne peut pas être actif tant que son KYC n'est pas accepté.",
            ]);
        }

        DB::transaction(function () use ($request, $livreur, $data) {
            $livreur->update($data);
            $identiteModifiee = $livreur->wasChanged(self::CHAMPS_IDENTITE);
            $this->enregistrerFichiers($request, $livreur);
            $livreur->recalculerKyc(documentModifie: $identiteModifiee);
        });

        return back()->with('success', 'Livreur modifié avec succès');
    }

    public function destroy(Livreur $livreur)
    {
        if ($livreur->contrats()->exists()) {
            return redirect()
                ->route('manager.livreurs.index')
                ->with('error', 'Impossible de supprimer ce livreur : il a déjà un contrat de location-vente.');
        }

        $livreur->delete();

        return redirect()->route('manager.livreurs.index')->with('success', 'Livreur supprimé avec succès');
    }

    public function toggleStatut(Livreur $livreur)
    {
        if (! $livreur->statut && ! $livreur->kycAccepte()) {
            return back()->with('error', $livreur->nomComplet()." ne peut pas être activé : son KYC n'est pas accepté.");
        }

        $livreur->update(['statut' => ! $livreur->statut]);

        return back();
    }

    public function photo(Livreur $livreur)
    {
        $contenu = $livreur->photo ? rescue(fn () => Storage::disk(Livreur::DISQUE)->get($livreur->photo), null, report: false) : null;

        if (! is_string($contenu) || $contenu === '') {
            return response()->file(public_path('assets/img/avatars/1.png'), ['Cache-Control' => 'private, max-age=300']);
        }

        return response($contenu, 200, [
            'Content-Type' => Storage::disk(Livreur::DISQUE)->mimeType($livreur->photo) ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function document(Livreur $livreur, LivreurDocument $document)
    {
        abort_unless((int) $document->livreur_id === (int) $livreur->id, 404);

        $contenu = rescue(fn () => Storage::disk(Livreur::DISQUE)->get($document->chemin), null, report: false);
        abort_unless(is_string($contenu) && $contenu !== '', 404);

        $nom = $livreur->code.'-'.$document->type.'.'.pathinfo($document->chemin, PATHINFO_EXTENSION);

        return response($contenu, 200, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$nom.'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeDocument(Request $request, Livreur $livreur)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(LivreurDocument::TYPES))],
            'fichier' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $livreur->enregistrerDocument($validated['type'], $request->file('fichier'));

        return back()->with('success', LivreurDocument::TYPES[$validated['type']].' enregistré(e).');
    }

    public function destroyDocument(Livreur $livreur, LivreurDocument $document)
    {
        abort_unless((int) $document->livreur_id === (int) $livreur->id, 404);

        $livreur->supprimerDocument($document);

        return back()->with('success', $document->libelle().' supprimé(e).');
    }

    public function decisionKyc(Request $request, Livreur $livreur)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['valider', 'rejeter'])],
            'motif' => ['nullable', 'required_if:decision,rejeter', 'string', 'max:255'],
        ]);

        $accepte = $validated['decision'] === 'valider';

        $livreur->update([
            'kyc_statut' => $accepte ? Livreur::KYC_VALIDE : Livreur::KYC_REJETE,
            'kyc_verifie_le' => now(),
            'kyc_verifie_par' => ((int) data_get(session('utilisateur'), 'id', 0)) ?: null,
            'kyc_motif_rejet' => $accepte ? null : $validated['motif'],
            'statut' => $accepte,
        ]);

        return back()->with('success', $accepte
            ? 'KYC accepté : le livreur est maintenant actif.'
            : 'KYC rejeté : le livreur est inactif.');
    }

    private function enregistrerFichiers(Request $request, Livreur $livreur): void
    {
        if ($request->hasFile('photo')) {
            $livreur->enregistrerPhoto($request->file('photo'));
        }

        foreach (array_keys(LivreurDocument::TYPES) as $type) {
            if ($request->hasFile("documents.{$type}")) {
                $livreur->enregistrerDocument($type, $request->file("documents.{$type}"));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['required', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'contact' => ['required', 'string', 'max:30'],
            'contact_urgence' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'type_piece' => ['nullable', Rule::in(array_keys(Livreur::TYPES_PIECE))],
            'numero_piece' => ['nullable', 'string', 'max:50'],
            'date_expiration_piece' => ['nullable', 'date'],
            'numero_permis' => ['nullable', 'string', 'max:50'],
            'categorie_permis' => ['nullable', Rule::in(array_keys(Livreur::CATEGORIES_PERMIS))],
            'date_expiration_permis' => ['nullable', 'date'],
            'statut' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        return collect($validated)->except(['photo', 'documents'])->all();
    }
}
