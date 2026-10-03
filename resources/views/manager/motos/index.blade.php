@extends('layout.app')

@section('title', 'Motos – Location-vente')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Liste des motos</h4>
      <p class="text-muted mb-0">Parc de motos proposées en location-vente. Une moto soldée sort du parc et devient la propriété du livreur.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNouveauContrat">
        <i class="icon-base bx bx-user-plus me-1"></i> Attribuer une moto
      </button>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterMoto">
        <i class="icon-base bx bx-plus me-1"></i> Ajouter une moto
      </button>
    </div>
  </div>

  <div class="row mb-4">
    @foreach ([
      ['Dans le parc', $stats['parc'], 'bx-cycling', 'primary', 'parc'],
      ['Disponibles', $stats['disponibles'], 'bx-check-circle', 'success', 'disponible'],
      ['En location-vente', $stats['en_location'], 'bx-transfer', 'info', 'en_location'],
      ['En maintenance', $stats['maintenance'], 'bx-wrench', 'warning', 'maintenance'],
      ['Cédées (hors parc)', $stats['cedees'], 'bx-key', 'secondary', 'cedee'],
    ] as [$libelle, $valeur, $icone, $couleur, $filtre])
      <div class="col-sm-6 col-xl mb-4">
        <a href="{{ route('manager.motos.index', ['statut' => $filtre]) }}" class="card h-100 {{ $statut === $filtre ? 'border border-primary' : '' }}">
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
      <h5 class="mb-0">Motos</h5>
      <form method="GET" action="{{ route('manager.motos.index') }}" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" class="form-control" placeholder="Immatriculation, marque, châssis..." value="{{ request('q') }}">
        <select name="statut" class="form-select" style="width: auto;">
          <option value="">Tous les statuts</option>
          <option value="parc" @selected($statut === 'parc')>Dans le parc</option>
          @foreach (\App\Models\LocationVente\Moto::STATUTS as $valeur => $libelle)
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
            <th>Moto</th>
            <th>Immatriculation</th>
            <th>Châssis</th>
            <th class="text-end">Prix d'achat</th>
            <th>Statut</th>
            <th>Livreur / propriétaire</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($motos as $moto)
            @php($contrat = $moto->statut === \App\Models\LocationVente\Moto::STATUT_EN_LOCATION || $moto->statut === \App\Models\LocationVente\Moto::STATUT_CEDEE ? $moto->contratActuel : null)
            <tr>
              <td>
                <div class="fw-medium">{{ trim($moto->marque.' '.$moto->modele) }}</div>
                <small class="text-muted">{{ collect([$moto->couleur, $moto->annee])->filter()->implode(' · ') ?: '—' }}</small>
              </td>
              <td class="fw-medium">
                @if ($moto->immatriculationEnCours())
                  <span class="badge bg-label-warning">Immat. en cours</span>
                @else
                  {{ $moto->immatriculation }}
                @endif
              </td>
              <td><small>{{ $moto->numero_chassis ?: '—' }}</small></td>
              <td class="text-end">{{ $moto->prix_achat ? number_format($moto->prix_achat, 0, ',', ' ').' FCFA' : '—' }}</td>
              <td><span class="badge bg-label-{{ $moto->couleurStatut() }}">{{ $moto->libelleStatut() }}</span></td>
              <td>
                @if ($contrat)
                  {{ $contrat->livreur?->nomComplet() }}
                  <br>
                  <a href="{{ route('manager.contrats.show', $contrat) }}"><small>{{ $contrat->reference }}</small></a>
                  @if ($contrat->date_solde)
                    <small class="text-muted">· propriétaire depuis le {{ $contrat->date_solde->format('d/m/Y') }}</small>
                  @endif
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-nowrap">
                @if ($moto->statut === \App\Models\LocationVente\Moto::STATUT_DISPONIBLE)
                  <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#modalNouveauContrat" data-moto-id="{{ $moto->id }}" title="Attribuer cette moto à un livreur">
                    <i class="icon-base bx bx-user-plus me-1"></i> Attribuer
                  </button>
                @endif
                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifierMoto{{ $moto->id }}" title="Modifier">
                  <i class="icon-base bx bx-edit"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalConfirmation"
                  data-confirm-titre="Supprimer une moto"
                  data-confirm-texte="Supprimer la moto {{ $moto->libelle() }} ? Une moto liée à un contrat ne peut pas être supprimée."
                  data-confirm-url="{{ route('manager.motos.destroy', $moto) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-4">Aucune moto enregistrée</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">{{ $motos->total() }} moto(s)</small>
      {{ $motos->links() }}
    </div>
  </div>

  <div class="modal fade" id="modalAjouterMoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('manager.motos.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une moto au parc</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            @include('manager.motos._form', ['moto' => null])
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @foreach ($motos as $moto)
    <div class="modal fade" id="modalModifierMoto{{ $moto->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('manager.motos.update', $moto) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier {{ $moto->libelle() }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              @include('manager.motos._form', ['moto' => $moto])
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-primary">Modifier</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endforeach

  @include('manager._confirmation')
  @include('manager.contrats._modal_nouveau')
@endsection
