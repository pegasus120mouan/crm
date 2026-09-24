@extends('layout.app')

@section('title', 'Tableau de bord')

@push('head')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
@endpush

@section('content')
  @php
    $welcomeName = trim(($utilisateur['prenoms'] ?? '').' '.($utilisateur['nom'] ?? ''));
    if ($welcomeName === '') {
      $welcomeName = $utilisateur['login'] ?? '';
    }
  @endphp

  <div class="row">
    <div class="col-xxl-8 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
              <span class="text-heading">Solde actuel</span>
              <h4 class="mb-1">Bienvenue{{ $welcomeName !== '' ? ' '.$welcomeName : '' }}</h4>
              <h2 class="text-danger mb-1">{{ number_format($stats['reste_a_payer'], 0, ',', ' ') }} FCFA</h2>
              <small class="text-body-secondary">Reste à payer / solde disponible</small>
              <div class="d-flex flex-wrap gap-6 mt-4">
                <div>
                  <small class="d-block text-body-secondary">Montant payé</small>
                  <span class="fw-medium">{{ number_format($stats['montant_paye'], 0, ',', ' ') }} FCFA</span>
                </div>
                <div>
                  <small class="d-block text-body-secondary">Montant dû</small>
                  <span class="fw-medium">{{ number_format($stats['montant_du'], 0, ',', ' ') }} FCFA</span>
                </div>
              </div>
            </div>
            <div class="avatar avatar-xl">
              <span class="avatar-initial rounded-circle bg-label-primary">
                <i class="icon-base bx bx-desktop icon-lg"></i>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-sm-6 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div class="avatar">
              <span class="avatar-initial rounded bg-label-success"><i class="icon-base bx bx-user"></i></span>
            </div>
          </div>
          <h4 class="mt-3 mb-1">{{ number_format($stats['clients_total'], 0, ',', ' ') }}</h4>
          <p class="mb-0">Nombre de clients</p>
          <small class="text-body-secondary">{{ $stats['clients_actifs'] }} actifs</small>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-sm-6 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div class="avatar">
              <span class="avatar-initial rounded bg-label-info"><i class="icon-base bx bx-store"></i></span>
            </div>
          </div>
          <h4 class="mt-3 mb-1">{{ number_format($stats['boutiques_total'], 0, ',', ' ') }}</h4>
          <p class="mb-0">Boutiques</p>
          <small class="text-body-secondary">{{ $stats['boutiques_actives'] }} actives</small>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xxl-8 mb-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Colis par boutique</h5>
          <a href="{{ route('boutiques.index') }}" class="btn btn-sm btn-outline-primary">Gestion des boutiques</a>
        </div>
        <div class="card-body">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="d-flex align-items-center p-3 rounded" style="background: #e8f5e9;">
                <span class="badge bg-success rounded-pill me-3"><i class="bx bx-check"></i></span>
                <div>
                  <small class="d-block">Total livrés</small>
                  <strong>{{ number_format($stats['commandes_livrees'], 0, ',', ' ') }}</strong>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="d-flex align-items-center p-3 rounded" style="background: #fde8e8;">
                <span class="badge bg-danger rounded-pill me-3"><i class="bx bx-time"></i></span>
                <div>
                  <small class="d-block">Non livrés</small>
                  <strong>{{ number_format($stats['commandes_non_livrees'], 0, ',', ' ') }}</strong>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="d-flex align-items-center p-3 rounded" style="background: #eef2ff;">
                <span class="badge bg-primary rounded-pill me-3"><i class="bx bx-package"></i></span>
                <div>
                  <small class="d-block">Total colis</small>
                  <strong>{{ number_format($stats['commandes_total'], 0, ',', ' ') }}</strong>
                </div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-borderless mb-0">
              <thead>
                <tr>
                  <th>Boutique</th>
                  <th>Livrés</th>
                  <th>En cours</th>
                  <th>Total</th>
                  <th>Livraison</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($colisParBoutique as $ligne)
                  <tr>
                    <td>
                      <a href="{{ route('boutiques.show', $ligne->id) }}">{{ $ligne->nom }}</a>
                    </td>
                    <td class="text-success">{{ number_format($ligne->livrees, 0, ',', ' ') }}</td>
                    <td>{{ number_format($ligne->non_livrees, 0, ',', ' ') }}</td>
                    <td>{{ number_format($ligne->total, 0, ',', ' ') }}</td>
                    <td>
                      <div class="d-flex align-items-center">
                        <div class="progress w-100 me-2" style="height: 6px;">
                          <div class="progress-bar {{ $ligne->taux >= 100 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ min($ligne->taux, 100) }}%"></div>
                        </div>
                        <small>{{ $ligne->taux }}%</small>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-body-secondary py-3">Aucune boutique pour le moment</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-4">
      <div class="row">
        <div class="col-sm-6 mb-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="avatar mb-3">
                <span class="avatar-initial rounded bg-label-warning"><i class="icon-base bx bx-package"></i></span>
              </div>
              <h4 class="mb-1">{{ number_format($stats['commandes_non_livrees'], 0, ',', ' ') }}</h4>
              <p class="mb-0">Commandes en attente</p>
              <small class="text-body-secondary">Non livrées</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 mb-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="avatar mb-3">
                <span class="avatar-initial rounded bg-label-success"><i class="icon-base bx bx-wallet"></i></span>
              </div>
              <h4 class="mb-1">{{ number_format($stats['montant_mois'], 0, ',', ' ') }} FCFA</h4>
              <p class="mb-0">Commission du mois</p>
              <small class="text-body-secondary">Taux {{ $stats['taux'] ? rtrim(rtrim(number_format((float) $stats['taux'], 2, ',', ' '), '0'), ',').' %' : 'non défini' }}</small>
            </div>
          </div>
        </div>
        <div class="col-12 mb-6">
          <div class="card h-100" style="background: #fff8e1;">
            <div class="card-body d-flex justify-content-between align-items-center">
              <div>
                <div class="avatar mb-2">
                  <span class="avatar-initial rounded bg-label-warning"><i class="icon-base bx bx-error"></i></span>
                </div>
                <h4 class="mb-1">{{ number_format($stats['commandes_retours'], 0, ',', ' ') }}</h4>
                <p class="mb-0">Retours en attente</p>
                <small class="text-body-secondary">sur {{ number_format($stats['commandes_total'], 0, ',', ' ') }} colis au total</small>
              </div>
              <a href="{{ route('commandes.index', ['statut' => 'Retour']) }}" class="btn btn-sm btn-warning">Voir</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4 mb-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-0">Statistiques clients</h5>
            <small class="text-body-secondary">Répartition des comptes</small>
          </div>
          <a href="{{ route('clients.index') }}" class="btn btn-sm btn-outline-primary">Gestion des clients</a>
        </div>
        <div class="card-body">
          <h2 class="mb-4">{{ number_format($stats['clients_total'], 0, ',', ' ') }}</h2>
          <div class="d-flex align-items-center mb-3">
            <div class="avatar avatar-sm me-3">
              <span class="avatar-initial rounded-circle bg-label-primary"><i class="bx bx-user"></i></span>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between">
                <span>Actifs</span>
                <strong>{{ $stats['clients_actifs'] }}</strong>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center">
            <div class="avatar avatar-sm me-3">
              <span class="avatar-initial rounded-circle bg-label-secondary"><i class="bx bx-user-x"></i></span>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between">
                <span>Inactifs</span>
                <strong>{{ $stats['clients_inactifs'] }}</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Commissions</h5>
          <a href="{{ route('commissions.index') }}" class="btn btn-sm btn-outline-primary">Gestion des commissions</a>
        </div>
        <div class="card-body">
          <div id="commissionsChart"></div>
          <div class="text-center mt-2">
            <small class="text-body-secondary">Évolution sur 7 mois</small>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Dernières commandes</h5>
          <a href="{{ route('commandes.index') }}" class="btn btn-sm btn-outline-primary">Gestion des commandes</a>
        </div>
        <div class="card-body">
          @forelse ($dernieresCommandes as $commande)
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <div class="fw-medium">{{ $commande->client->boutique->nom ?? trim(($commande->client->nom ?? '').' '.($commande->client->prenoms ?? '')) ?: 'Commande #'.$commande->id }}</div>
                <small class="text-body-secondary">{{ $commande->communes }} · {{ optional($commande->date_reception)->format('d/m/Y') }}</small>
              </div>
              <span class="badge {{ $commande->statut === 'Livré' ? 'bg-label-success' : ($commande->statut === 'Retour' ? 'bg-label-warning' : 'bg-label-secondary') }}">
                {{ $commande->statut }}
              </span>
            </div>
          @empty
            <div class="text-center text-body-secondary py-4">
              <i class="bx bx-package mb-2" style="font-size: 2rem;"></i>
              <p class="mb-0">Aucune commande</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const el = document.querySelector('#commissionsChart');
      if (!el || typeof ApexCharts === 'undefined') {
        return;
      }

      const labels = @json($chartLabels);
      const data = @json($chartData);
      const maxValue = Math.max(10, ...data);

      new ApexCharts(el, {
        chart: { height: 220, type: 'area', toolbar: { show: false }, parentHeightOffset: 0 },
        series: [{ name: 'Commission', data }],
        colors: [config.colors.primary],
        dataLabels: { enabled: false },
        stroke: { width: 3, curve: 'smooth' },
        fill: {
          type: 'gradient',
          gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] }
        },
        grid: { borderColor: config.colors.borderColor, strokeDashArray: 8, padding: { top: -20, left: 0, right: 8 } },
        xaxis: {
          categories: labels,
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: { style: { colors: config.colors.textMuted, fontSize: '12px' } }
        },
        yaxis: { labels: { show: false }, min: 0, max: maxValue, tickAmount: 4 },
        tooltip: { y: { formatter: value => new Intl.NumberFormat('fr-FR').format(value) + ' FCFA' } }
      }).render();
    });
  </script>
@endpush
