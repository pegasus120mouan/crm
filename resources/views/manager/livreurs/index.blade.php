@extends('layout.app')

@section('title', 'Livreurs – Location-vente')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Liste des livreurs</h4>
      <p class="text-muted mb-0">Livreurs inscrits au programme de location-vente de motos</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterLivreur">
      <i class="icon-base bx bx-plus me-1"></i> Ajouter un livreur
    </button>
  </div>

  <div class="row mb-4">
    @foreach ([
      ['Total livreurs', $stats['total'], 'bx-id-card', 'primary', null],
      ['KYC acceptés', $stats['kyc_valides'], 'bx-user-check', 'success', 'valide'],
      ['KYC à vérifier', $stats['kyc_a_verifier'], 'bx-time', 'warning', 'en_attente'],
      ['Sous contrat en cours', $stats['sous_contrat'], 'bx-cycling', 'info', null],
      ['Devenus propriétaires', $stats['proprietaires'], 'bx-key', 'secondary', null],
    ] as [$libelle, $valeur, $icone, $couleur, $filtreKyc])
      <div class="col-sm-6 col-xl mb-4">
        <a href="{{ $filtreKyc ? route('manager.livreurs.index', ['kyc' => $filtreKyc]) : route('manager.livreurs.index') }}" class="card h-100 {{ $filtreKyc && $kyc === $filtreKyc ? 'border border-primary' : '' }}">
          <div class="card-body d-flex justify-content-between align-items-center">
            <div>
              <span class="d-block mb-1 text-body">{{ $libelle }}</span>
              <h4 class="card-title mb-0">{{ $valeur }}</h4>
            </div>
            <span class="avatar-initial rounded bg-label-{{ $couleur }} p-2"><i class="icon-base bx {{ $icone }} icon-md"></i></span>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0">Livreurs</h5>
      <form method="GET" action="{{ route('manager.livreurs.index') }}" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" class="form-control" placeholder="Code, nom, contact..." value="{{ request('q') }}">
        <select name="kyc" class="form-select" style="width: auto;">
          <option value="">Tous les KYC</option>
          @foreach (\App\Models\LocationVente\Livreur::KYC_STATUTS as $valeur => [$libelle])
            <option value="{{ $valeur }}" @selected($kyc === $valeur)>{{ $libelle }}</option>
          @endforeach
        </select>
        <select name="statut" class="form-select" style="width: auto;">
          <option value="">Actifs et inactifs</option>
          <option value="1" @selected(request('statut') === '1')>Actifs</option>
          <option value="0" @selected(request('statut') === '0')>Inactifs</option>
        </select>
        <button type="submit" class="btn btn-outline-primary">Rechercher</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Photo</th>
            <th>Nom</th>
            <th>Prénoms</th>
            <th>Contact</th>
            <th>Contrat en cours</th>
            <th class="text-end">Reste à payer</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($livreurs as $livreur)
            @php($contrat = $livreur->contratEnCours)
            <tr>
              <td>
                <a href="{{ route('manager.livreurs.show', $livreur) }}">
                  <img src="{{ route('manager.livreurs.photo', $livreur) }}?v={{ optional($livreur->updated_at)->timestamp }}" alt="{{ $livreur->nomComplet() }}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" loading="lazy">
                </a>
              </td>
              <td>
                <a href="{{ route('manager.livreurs.show', $livreur) }}" class="fw-medium text-body">{{ $livreur->nom }}</a>
              </td>
              <td>{{ $livreur->prenoms }}</td>
              <td>{{ $livreur->contact }}</td>
              <td>
                @if ($contrat)
                  <a href="{{ route('manager.contrats.show', $contrat) }}" class="fw-medium">{{ $contrat->reference }}</a>
                  <br><small class="text-muted">{{ $contrat->moto?->libelle() }}</small>
                @else
                  <span class="text-muted">Aucun</span>
                  @if ($livreur->contrats_count)
                    <br><small class="text-muted">{{ $livreur->contrats_count }} contrat(s) terminé(s)</small>
                  @endif
                @endif
              </td>
              <td class="text-end">
                @if ($contrat)
                  <span class="fw-medium {{ $contrat->retard() > 0 ? 'text-danger' : '' }}">{{ number_format($contrat->reste(), 0, ',', ' ') }} FCFA</span>
                  @if ($contrat->retard() > 0)
                    <br><small class="text-danger">Retard {{ number_format($contrat->retard(), 0, ',', ' ') }} FCFA</small>
                  @endif
                @else
                  —
                @endif
              </td>
              <td>
                <form action="{{ route('manager.livreurs.toggle-statut', $livreur) }}" method="POST">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-sm btn-{{ $livreur->statut ? 'success' : 'secondary' }}" style="min-width: 80px;"
                    @if (! $livreur->statut && ! $livreur->kycAccepte()) disabled title="Acceptez le KYC pour activer ce livreur" @endif>
                    {{ $livreur->statut ? 'Actif' : 'Inactif' }}
                  </button>
                </form>
              </td>
              <td class="text-nowrap">
                <a href="{{ route('manager.livreurs.show', $livreur) }}" class="btn btn-sm btn-icon btn-outline-info" title="Fiche et KYC">
                  <i class="icon-base bx bx-show"></i>
                </a>
                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifierLivreur{{ $livreur->id }}" title="Modifier">
                  <i class="icon-base bx bx-edit"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalConfirmation"
                  data-confirm-titre="Supprimer un livreur"
                  data-confirm-texte="Supprimer {{ $livreur->nomComplet() }} ({{ $livreur->code }}) et ses documents KYC ? Un livreur ayant déjà eu un contrat ne peut pas être supprimé."
                  data-confirm-url="{{ route('manager.livreurs.destroy', $livreur) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4">Aucun livreur trouvé</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">{{ $livreurs->total() }} livreur(s)</small>
      {{ $livreurs->links() }}
    </div>
  </div>

  <div class="modal fade" id="modalAjouterLivreur" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <form action="{{ route('manager.livreurs.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Ajouter un livreur</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small">Le code du livreur est généré automatiquement. Les documents KYC peuvent aussi être ajoutés plus tard depuis sa fiche.</p>
            @include('manager.livreurs._form', ['livreur' => null])
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @foreach ($livreurs as $livreur)
    @include('manager.livreurs._modal_modifier', ['livreur' => $livreur])
  @endforeach

  @include('manager._confirmation')
  @include('manager.livreurs._script_photo')
@endsection
