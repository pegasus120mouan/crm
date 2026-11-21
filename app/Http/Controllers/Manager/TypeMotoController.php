<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\TypeMoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TypeMotoController extends Controller
{
    public function index(): View
    {
        $types = TypeMoto::query()
            ->withCount('motos')
            ->orderBy('nom')
            ->get();

        return view('manager.types-moto.index', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = TypeMoto::query()->create([
            'nom' => $this->nomValide($request),
            'cout_acquisition' => $this->montantValide($request, 'cout_acquisition'),
            'marge' => $this->montantValide($request, 'marge'),
            'montant_journalier' => $this->montantValide($request, 'montant_journalier'),
        ]);

        return redirect()
            ->route('manager.types-moto.index')
            ->with('success', 'Type « '.$type->nom.' » ajouté.');
    }

    public function update(Request $request, TypeMoto $typeMoto): RedirectResponse
    {
        $donnees = ['nom' => $this->nomValide($request, $typeMoto)];

        if ($request->exists('cout_acquisition')) {
            $donnees['cout_acquisition'] = $this->montantValide($request, 'cout_acquisition');
        }

        if ($request->exists('marge')) {
            $donnees['marge'] = $this->montantValide($request, 'marge');
        }

        if ($request->exists('montant_journalier')) {
            $donnees['montant_journalier'] = $this->montantValide($request, 'montant_journalier');
        }

        $typeMoto->update($donnees);
        $this->alignerContratsDuType($typeMoto);

        return redirect()
            ->route('manager.types-moto.index')
            ->with('success', 'Type modifié.');
    }

    public function destroy(TypeMoto $typeMoto): RedirectResponse
    {
        if ($typeMoto->motos()->exists()) {
            throw ValidationException::withMessages([
                'nom' => 'Ce type est utilisé par des motos. Retirez-le de ces motos avant de le supprimer.',
            ]);
        }

        $nom = $typeMoto->nom;
        $typeMoto->delete();

        return redirect()
            ->route('manager.types-moto.index')
            ->with('success', 'Type « '.$nom.' » supprimé.');
    }

    private function nomValide(Request $request, ?TypeMoto $type = null): string
    {
        $nom = trim((string) $request->input('nom'));
        $request->merge(['nom' => $nom]);

        $request->validate([
            'nom' => ['required', 'string', 'max:100'],
        ]);

        $doublon = TypeMoto::query()
            ->when($type, fn ($query) => $query->whereKeyNot($type->id))
            ->whereRaw('lower(nom) = lower(?)', [$nom])
            ->exists();

        if ($doublon) {
            throw ValidationException::withMessages([
                'nom' => 'Ce type de moto existe déjà.',
            ]);
        }

        return $nom;
    }

    private function alignerContratsDuType(TypeMoto $typeMoto): void
    {
        Contrat::query()
            ->where('statut', Contrat::STATUT_EN_COURS)
            ->whereHas('moto', fn ($query) => $query->where('type_moto_id', $typeMoto->id))
            ->with('moto.typeMoto')
            ->get()
            ->each(fn (Contrat $contrat) => $contrat->alignerSurLeType());
    }

    private function montantValide(Request $request, string $champ): int
    {
        $montant = preg_replace('/\D+/', '', (string) $request->input($champ, ''));
        $request->merge([
            $champ => $montant === '' ? null : (int) $montant,
        ]);

        $request->validate([
            $champ => ['required', 'integer', 'min:0'],
        ], [
            $champ.'.required' => match ($champ) {
                'marge' => 'Indiquez la marge.',
                'montant_journalier' => 'Indiquez le montant journalier.',
                default => 'Indiquez le coût d\'acquisition.',
            },
        ]);

        return (int) $request->input($champ);
    }
}
