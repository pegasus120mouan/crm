@extends('layout.app')

@section('title', 'Paiements – Location-vente')

@section('content')
  @php($fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA')

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Paiements</h4>
      <p class="text-muted mb-0">Versements des livreurs sur leurs contrats de location-vente</p>
    </div>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPaiement" @disabled($contratsEnCours->isEmpty())>
      <i class="icon-base bx bx-money me-1"></i> Enregistrer un paiement
    </button>
  </div>

  <div class="row mb-4">
    @foreach ([
      ["Encaissé aujourd'hui", $stats['aujourdhui'], 'bx-calendar', 'success'],
      ['Encaissé ce mois', $stats['mois'], 'bx-wallet', 'primary'],
      ['Total encaissé', $stats['total'], 'bx-receipt', 'info'],
    ] as [$libelle, $valeur, $icone, $couleur])
      <div class="col-sm-6 col-xl-4 mb-4">
        <div class="card h-100">
          <div class="card-body d-flex justify-content-between align-items-center">
            <div>
              <span class="d-block mb-1">{{ $libelle }}</span>
              <h4 class="card-title mb-0">{{ $fcfa($valeur) }}</h4>
            </div>
            <span class="avatar-initial rounded bg-label-{{ $couleur }} p-2"><i class="icon-base bx {{ $icone }} icon-md"></i></span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header">
      <form method="GET" action="{{ route('manager.paiements.index') }}" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label">Du</label>
          <input type="date" name="du" class="form-control" value="{{ $filtres['du'] ?? '' }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">Au</label>
          <input type="date" name="au" class="form-control" value="{{ $filtres['au'] ?? '' }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">Livreur</label>
          <select name="livreur_id" class="form-select">
            <option value="">Tous les livreurs</option>
            @foreach ($livreurs as $livreur)
              <option value="{{ $livreur->id }}" @selected(($filtres['livreur_id'] ?? null) == $livreur->id)>{{ $livreur->nomComplet() }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Mode</label>
          <select name="mode" class="form-select">
            <option value="">Tous</option>
            @foreach (\App\Models\LocationVente\Paiement::MODES as $mode)
              <option value="{{ $mode }}" @selected(($filtres['mode'] ?? null) === $mode)>{{ $mode }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-outline-primary">Filtrer</button>
          <a href="{{ route('manager.paiements.index') }}" class="btn btn-outline-secondary">Réinitialiser</a>
        </div>
      </form>
    </div>
    <div class="px-4 pb-2">
      <small class="text-muted">{{ $nombreFiltre }} paiement(s) · total <strong class="text-body">{{ $fcfa($totalFiltre) }}</strong></small>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Livreur</th>
            <th>Contrat / moto</th>
            <th>Type</th>
            <th>Mode</th>
            <th>Référence</th>
            <th class="text-end">Montant</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($paiements as $paiement)
            <tr>
              <td>{{ $paiement->date_paiement->format('d/m/Y') }}</td>
              <td>@include('manager.livreurs._avatar', ['livreur' => $paiement->contrat?->livreur])</td>
              <td>
                <a href="{{ route('manager.contrats.show', $paiement->contrat_id) }}">{{ $paiement->contrat?->reference }}</a>
                <br><small class="text-muted">{{ $paiement->contrat?->moto?->immatriculationAffichee() }}</small>
              </td>
              <td><span class="badge bg-label-{{ $paiement->type === 'apport' ? 'info' : 'primary' }}">{{ $paiement->type === 'apport' ? 'Apport' : 'Échéance' }}</span></td>
              <td>{{ $paiement->mode }}</td>
              <td><small>{{ $paiement->reference ?: '—' }}</small></td>
              <td class="text-end fw-medium">{{ $fcfa($paiement->montant) }}</td>
              <td>
                @if ($paiement->contrat?->statut !== \App\Models\LocationVente\Contrat::STATUT_RESILIE)
                  <button
                    type="button"
                    class="btn btn-sm btn-icon btn-outline-danger"
                    title="Annuler ce paiement"
                    data-bs-toggle="modal"
                    data-bs-target="#modalConfirmation"
                    data-confirm-titre="Annuler un paiement"
                    data-confirm-texte="Annuler le paiement de {{ $fcfa($paiement->montant) }} de {{ $paiement->contrat?->livreur?->nomComplet() }} du {{ $paiement->date_paiement->format('d/m/Y') }} ?"
                    data-confirm-url="{{ route('manager.paiements.destroy', $paiement) }}">
                    <i class="icon-base bx bx-trash"></i>
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4">Aucun paiement trouvé</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-end">
      {{ $paiements->links() }}
    </div>
  </div>

  @include('manager.paiements._modal', ['contrats' => $contratsEnCours])
  @include('manager._confirmation')
@endsection
