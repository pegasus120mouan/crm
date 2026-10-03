@extends('layout.app')

@section('title', 'Tableau de bord manager')

@push('head')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
@endpush

@section('content')
  @php
    $welcomeName = trim(($utilisateur['prenoms'] ?? '').' '.($utilisateur['nom'] ?? ''));
    if ($welcomeName === '') {
      $welcomeName = $utilisateur['login'] ?? '';
    }
    $fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA';
  @endphp

  <div class="row">
    <div class="col-xxl-6 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
              <span class="text-heading">Espace manager</span>
              <h4 class="mb-1">Bienvenue{{ $welcomeName !== '' ? ' '.$welcomeName : '' }}</h4>
              <h2 class="text-danger mb-1">{{ $fcfa($stats['reste_a_payer']) }}</h2>
              <small class="text-body-secondary">Commissions restant à payer à l'équipe commerciale</small>
              <div class="d-flex flex-wrap gap-6 mt-4">
                <div>
                  <small class="d-block text-body-secondary">Commissions dues</small>
                  <span class="fw-medium">{{ $fcfa($stats['commission_due']) }}</span>
                </div>
                <div>
                  <small class="d-block text-body-secondary">Commissions payées</small>
                  <span class="fw-medium">{{ $fcfa($stats['commission_payee']) }}</span>
                </div>
                <div>
                  <small class="d-block text-body-secondary">Taux de commission</small>
                  <span class="fw-medium">{{ $stats['taux'] ? rtrim(rtrim(number_format((float) $stats['taux'], 2, ',', ' '), '0'), ',').' %' : 'non défini' }}</span>
                </div>
              </div>
            </div>
            <div class="avatar avatar-xl">
              <span class="avatar-initial rounded-circle bg-label-primary">
                <i class="icon-base bx bx-briefcase icon-lg"></i>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-sm-4 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-primary"><i class="icon-base bx bx-group"></i></span>
          </div>
          <h4 class="mt-3 mb-1">{{ number_format($stats['commerciaux_total'], 0, ',', ' ') }}</h4>
          <p class="mb-0">Commerciaux</p>
          <small class="text-body-secondary">{{ $stats['commerciaux_actifs'] }} actifs</small>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-sm-4 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success"><i class="icon-base bx bx-user"></i></span>
          </div>
          <h4 class="mt-3 mb-1">{{ number_format($stats['clients_total'], 0, ',', ' ') }}</h4>
          <p class="mb-0">Clients</p>
          <small class="text-body-secondary">{{ $stats['clients_actifs'] }} actifs</small>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-sm-4 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-info"><i class="icon-base bx bx-store"></i></span>
          </div>
          <h4 class="mt-3 mb-1">{{ number_format($stats['boutiques_total'], 0, ',', ' ') }}</h4>
          <p class="mb-0">Boutiques</p>
          <small class="text-body-secondary">{{ $stats['boutiques_actives'] }} actives</small>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12 mb-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Performance des commerciaux</h5>
          <small class="text-body-secondary">{{ $stats['taux_livraison'] }}% de colis livrés au global</small>
        </div>
        <div class="card-body">
          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="d-flex align-items-center p-3 rounded" style="background: #eef2ff;">
                <span class="badge bg-primary rounded-pill me-3"><i class="bx bx-package"></i></span>
                <div>
                  <small class="d-block">Total colis</small>
                  <strong>{{ number_format($stats['commandes_total'], 0, ',', ' ') }}</strong>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="d-flex align-items-center p-3 rounded" style="background: #e8f5e9;">
                <span class="badge bg-success rounded-pill me-3"><i class="bx bx-check"></i></span>
                <div>
                  <small class="d-block">Livrés</small>
                  <strong>{{ number_format($stats['commandes_livrees'], 0, ',', ' ') }}</strong>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="d-flex align-items-center p-3 rounded" style="background: #fde8e8;">
                <span class="badge bg-danger rounded-pill me-3"><i class="bx bx-time"></i></span>
                <div>
                  <small class="d-block">Non livrés</small>
                  <strong>{{ number_format($stats['commandes_non_livrees'], 0, ',', ' ') }}</strong>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="d-flex align-items-center p-3 rounded" style="background: #fff8e1;">
                <span class="badge bg-warning rounded-pill me-3"><i class="bx bx-error"></i></span>
                <div>
                  <small class="d-block">Retours</small>
                  <strong>{{ number_format($stats['commandes_retours'], 0, ',', ' ') }}</strong>
                </div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Commercial</th>
                  <th class="text-center">Clients</th>
                  <th class="text-center">Boutiques</th>
                  <th class="text-center">Colis</th>
                  <th class="text-center">Livrés</th>
                  <th style="min-width: 140px;">Livraison</th>
                  <th class="text-end">Commission due</th>
                  <th class="text-end">Payée</th>
                  <th class="text-end">Reste</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($parCommercial as $ligne)
                  <tr>
                    <td>
                      <div class="fw-medium">{{ $ligne->nom }}</div>
                      <small class="text-body-secondary">
                        {{ $ligne->code ?: '—' }}
                        @unless ($ligne->actif)
                          · <span class="text-danger">inactif</span>
                        @endunless
                      </small>
                    </td>
                    <td class="text-center">{{ number_format($ligne->clients, 0, ',', ' ') }}</td>
                    <td class="text-center">{{ number_format($ligne->boutiques, 0, ',', ' ') }}</td>
                    <td class="text-center">{{ number_format($ligne->commandes, 0, ',', ' ') }}</td>
                    <td class="text-center text-success">{{ number_format($ligne->livrees, 0, ',', ' ') }}</td>
                    <td>
                      <div class="d-flex align-items-center">
                        <div class="progress w-100 me-2" style="height: 6px;">
                          <div class="progress-bar bg-primary" style="width: {{ min($ligne->taux_livraison, 100) }}%"></div>
                        </div>
                        <small>{{ $ligne->taux_livraison }}%</small>
                      </div>
                    </td>
                    <td class="text-end">{{ $fcfa($ligne->commission_due) }}</td>
                    <td class="text-end text-success">{{ $fcfa($ligne->commission_payee) }}</td>
                    <td class="text-end {{ $ligne->reste > 0 ? 'text-danger fw-medium' : '' }}">{{ $fcfa($ligne->reste) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="9" class="text-center text-body-secondary py-3">Aucun commercial pour le moment</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-7 mb-6">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0">Colis livrés par mois</h5>
          <small class="text-body-secondary">Clients de toute l'équipe, sur 7 mois</small>
        </div>
        <div class="card-body">
          <div id="livraisonsChart"></div>
        </div>
      </div>
    </div>

    <div class="col-md-5 mb-6">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0">Dernières commandes</h5>
        </div>
        <div class="card-body">
          @forelse ($dernieresCommandes as $commande)
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <div class="fw-medium">{{ $commande->client->boutique->nom ?? trim(($commande->client->nom ?? '').' '.($commande->client->prenoms ?? '')) ?: 'Commande #'.$commande->id }}</div>
                <small class="text-body-secondary">
                  {{ trim(($commande->client->commercial->prenoms ?? '').' '.($commande->client->commercial->nom ?? '')) ?: '—' }}
                  · {{ $commande->communes }} · {{ optional($commande->date_reception)->format('d/m/Y') }}
                </small>
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
      const el = document.querySelector('#livraisonsChart');
      if (!el || typeof ApexCharts === 'undefined') {
        return;
      }

      new ApexCharts(el, {
        chart: { height: 260, type: 'bar', toolbar: { show: false }, parentHeightOffset: 0 },
        series: [{ name: 'Colis livrés', data: @json($chartData) }],
        colors: [config.colors.primary],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: config.colors.borderColor, strokeDashArray: 8 },
        xaxis: {
          categories: @json($chartLabels),
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: { style: { colors: config.colors.textMuted, fontSize: '12px' } }
        },
        yaxis: { labels: { style: { colors: config.colors.textMuted } }, min: 0, forceNiceScale: true },
        tooltip: { y: { formatter: value => new Intl.NumberFormat('fr-FR').format(value) + ' colis' } }
      }).render();
    });
  </script>
@endpush
