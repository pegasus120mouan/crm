@extends('layout.app')

@section('title', 'Gestion des commissions')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Gestion des commissions</h4>
      <p class="text-muted mb-0">Le taux est défini par l’administrateur. La commission est un pourcentage du coût de livraison par colis livré.</p>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 col-xl-4 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Taux programmé</span>
          <h4 class="card-title mb-0">{{ $regle ? rtrim(rtrim(number_format((float) $regle->taux, 2, ',', ' '), '0'), ',').' %' : 'Non défini' }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Colis livrés</span>
          <h4 class="card-title mb-0">{{ $stats['colis_livres'] }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Commission totale</span>
          <h4 class="card-title mb-0">{{ number_format($stats['montant_total'], 0, ',', ' ') }} FCFA</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Gains par mois</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Mois</th>
            <th>Colis livrés</th>
            <th>Gain</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($gainsMensuels as $ligne)
            <tr>
              <td>{{ \Carbon\Carbon::parse($ligne['periode'])->locale('fr')->translatedFormat('F Y') }}</td>
              <td>{{ $ligne['colis'] }}</td>
              <td>
                @if ($regle)
                  {{ number_format($ligne['montant'], 0, ',', ' ') }} FCFA
                @else
                  <span class="text-muted">Taux non défini par l’admin</span>
                @endif
              </td>
              <td>
                @if ($ligne['paye'])
                  <span class="badge bg-label-success">Payée</span>
                  @if ($ligne['date_paiement'])
                    <small class="text-muted d-block">{{ $ligne['date_paiement']->format('d/m/Y') }}</small>
                  @endif
                @else
                  <span class="badge bg-label-warning">À payer</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center py-4">Aucun gain mensuel</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
