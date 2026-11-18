<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Depense;
use App\Models\LocationVente\Moto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepenseController extends Controller
{
    public function index(Request $request)
    {
        $motoId = $request->integer('moto') ?: null;

        $depenses = Depense::query()
            ->with(['moto', 'contrat.livreur'])
            ->orderByDesc('date_depense')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => (int) Depense::query()->sum('montant'),
            'mois' => (int) Depense::query()
                ->whereBetween('date_depense', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('montant'),
        ];

        $motos = Moto::query()
            ->with('contratEnCours.livreur')
            ->dansLeParc()
            ->orderBy('marque')
            ->orderBy('immatriculation')
            ->get();

        $motosRecherche = $motos->map(function (Moto $moto) {
            $contrat = $moto->contratEnCours;
            $livreur = $contrat?->livreur?->nomComplet();
            $titre = trim($moto->marque.' '.$moto->modele) ?: $moto->immatriculationAffichee();
            $plaque = $moto->immatriculationEnCours() ? 'Immat. en cours' : $moto->immatriculation;

            return [
                'id' => $moto->id,
                'titre' => $titre,
                'plaque' => $plaque,
                'livreur' => $livreur,
                'reference' => $contrat?->reference,
                'texte' => collect([$titre, $livreur, $contrat?->reference])->filter()->implode(' · '),
                'recherche' => mb_strtolower(implode(' ', array_filter([
                    $moto->marque,
                    $moto->modele,
                    $moto->immatriculation,
                    $moto->numero_chassis,
                    $livreur,
                    $contrat?->reference,
                ]))),
            ];
        })->values();

        return view('manager.depenses.index', compact('depenses', 'stats', 'motosRecherche', 'motoId'));
    }

    public function store(Request $request)
    {
        $montant = preg_replace('/\D+/', '', (string) $request->input('montant', ''));
        $request->merge([
            'montant' => $montant === '' ? null : (int) $montant,
        ]);

        $data = $request->validate([
            'moto_id' => ['required', 'integer', Rule::exists('location_vente_motos', 'id')],
            'date_depense' => ['required', 'date'],
            'montant' => ['required', 'integer', 'min:1'],
            'libelle' => ['required', 'string', 'max:255'],
        ]);

        $depense = DB::transaction(function () use ($data) {
            $contrat = Contrat::query()
                ->where('moto_id', $data['moto_id'])
                ->where('statut', Contrat::STATUT_EN_COURS)
                ->lockForUpdate()
                ->latest('date_debut')
                ->latest('id')
                ->first();

            $depense = Depense::query()->create([
                'moto_id' => $data['moto_id'],
                'contrat_id' => $contrat?->id,
                'date_depense' => $data['date_depense'],
                'montant' => $data['montant'],
                'libelle' => $data['libelle'],
            ]);

            if ($contrat) {
                $contrat->integrerDepense((int) $data['montant']);
                $depense->setRelation('contrat', $contrat->fresh());
            }

            return $depense;
        });

        if ($depense->contrat) {
            $fin = $depense->contrat->date_fin?->format('d/m/Y');
            $message = number_format($depense->montant, 0, ',', ' ').' FCFA ajoutés au montant à rembourser du contrat '.$depense->contrat->reference.'.';
            if ($fin) {
                $message .= ' Nouvelle date de fin : '.$fin.'.';
            }
        } else {
            $message = 'Dépense enregistrée. Cette moto n\'a pas de contrat en cours : le montant n\'est pas ajouté à un remboursement.';
        }

        return redirect()->route('manager.depenses.index')->with('success', $message);
    }

    public function destroy(Depense $depense)
    {
        DB::transaction(function () use ($depense) {
            $contrat = $depense->contrat_id
                ? Contrat::query()->lockForUpdate()->find($depense->contrat_id)
                : null;

            if ($contrat && $contrat->statut !== Contrat::STATUT_EN_COURS) {
                throw ValidationException::withMessages([
                    'montant' => 'Cette dépense est liée à un contrat qui n\'est plus en cours.',
                ]);
            }

            if ($contrat) {
                $contrat->retirerDepense((int) $depense->montant);
            }

            $depense->delete();
        });

        return back()->with('success', 'Dépense annulée. Le montant à rembourser a été recalculé.');
    }
}
