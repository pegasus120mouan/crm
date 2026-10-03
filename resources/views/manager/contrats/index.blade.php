@extends('layout.app')

@section('title', 'Contrats – Location-vente')

@section('content')
  @php($fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA')

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Contrats de location-vente</h4>
      <p class="text-muted mb-0">Le livreur prend une moto, paie régulièrement et en devient propriétaire une fois le prix soldé.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauContrat">
      <i class="icon-base bx bx-plus me-1"></i> Nouveau contrat
    </button>
  </div>

  <div class="row mb-4">
    @foreach ([
      ['Contrats en cours', $stats['en_cours'], 'bx-file', 'primary'],
      ['Soldés (motos cédées)', $stats['soldes'], 'bx-key', 'success'],
      ['Total encaissé', $fcfa($stats['encaisse']), 'bx-wallet', 'info'],
      ['Reste à encaisser', $fcfa($stats['reste']), 'bx-time', 'warning'],
      ['En retard', $fcfa($stats['retard']).' · '.$stats['en_retard'].' contrat(s)', 'bx-error', 'danger'],
    ] as [$libelle, $valeur, $icone, $couleur])
      <div class="col-sm-6 col-xl mb-4">
        <div class="card h-100">
          <div class="card-body">
            <span class="avatar-initial rounded bg-label-{{ $couleur }} p-2 d-inline-block mb-2"><i class="icon-base bx {{ $icone }} icon-md"></i></span>
            <span class="d-block mb-1">{{ $libelle }}</span>
            <h5 class="card-title mb-0">{{ $valeur }}</h5>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0">Contrats</h5>
      <form method="GET" action="{{ route('manager.contrats.index') }}" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" class="form-control" placeholder="Référence, livreur, immatriculation..." value="{{ request('q') }}">
        <select name="statut" class="form-select" style="width: auto;">
          <option value="">Tous les statuts</option>
          @foreach (\App\Models\LocationVente\Contrat::STATUTS as $valeur => $libelle)
            <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-outline-primary">Rechercher</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Livreur</th>
            <th>Moto</th>
            <th>Échéances</th>
            <th class="text-end">À rembourser</th>
            <th style="min-width: 160px;">Payé</th>
            <th class="text-end">Reste</th>
            <th>Prochaine échéance</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($contrats as $contrat)
            <tr>
              <td><a href="{{ route('manager.contrats.show', $contrat) }}" class="fw-medium">{{ $contrat->reference }}</a></td>
              <td>@include('manager.livreurs._avatar', ['livreur' => $contrat->livreur])</td>
              <td>
                {{ trim($contrat->moto?->marque.' '.$contrat->moto?->modele) }}
                <br><small class="text-muted">{{ $contrat->moto?->immatriculationAffichee() }}</small>
              </td>
              <td>
                {{ $fcfa($contrat->montant_echeance) }}
                <br><small class="text-muted">par {{ $contrat->uniteFrequence() }} · {{ $contrat->nombreEcheances() }} échéance(s)</small>
              </td>
              <td class="text-end">{{ $fcfa($contrat->prix_total) }}</td>
              <td>
                <small>{{ $fcfa($contrat->montantPaye()) }}</small>
                <div class="d-flex align-items-center">
                  <div class="progress w-100 me-2" style="height: 6px;">
                    <div class="progress-bar bg-{{ $contrat->statut === 'solde' ? 'success' : 'primary' }}" style="width: {{ $contrat->progression() }}%"></div>
                  </div>
                  <small>{{ $contrat->progression() }}%</small>
                </div>
              </td>
              <td class="text-end">
                {{ $fcfa($contrat->reste()) }}
                @if ($contrat->retard() > 0)
                  <br><small class="text-danger">Retard {{ $fcfa($contrat->retard()) }}</small>
                @endif
              </td>
              <td>{{ optional($contrat->prochaineEcheance())->format('d/m/Y') ?? '—' }}</td>
              <td><span class="badge bg-label-{{ $contrat->couleurStatut() }}">{{ $contrat->retard() > 0 ? 'En retard' : $contrat->libelleStatut() }}</span></td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center py-4">Aucun contrat pour le moment</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">{{ $contrats->total() }} contrat(s)</small>
      {{ $contrats->links() }}
    </div>
  </div>

  @include('manager.contrats._modal_nouveau')
@endsection
