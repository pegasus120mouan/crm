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
      ['Chez un gérant', $stats['chez_gerant'], 'bx-user-circle', 'info', 'chez_gerant'],
      ['En panne', $stats['en_panne'], 'bx-error', 'danger', 'en_panne'],
      ['En maintenance', $stats['maintenance'], 'bx-wrench', 'warning', 'maintenance'],
      ['Cédées (hors parc)', $stats['cedees'], 'bx-key', 'secondary', 'cedee'],
    ] as [$libelle, $valeur, $icone, $couleur, $filtre])
      <div class="col-sm-6 col-xl mb-4">
        <a href="{{ route('manager.motos.index', array_merge(request()->except('page'), ['statut' => $filtre])) }}" class="card h-100 {{ $statut === $filtre ? 'border border-primary' : '' }}">
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

  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="avatar-initial rounded bg-label-primary p-2"><i class="icon-base bx bx-filter-alt"></i></span>
        <div>
          <h5 class="mb-0">Recherche</h5>
          <small class="text-muted">Identité de la moto, statut, livreur ou période d'acquisition</small>
        </div>
      </div>
      <form method="GET" action="{{ route('manager.motos.index') }}">
        <div class="row g-3">
          <div class="col-md-6 col-xl-3">
            <label class="form-label" for="filtreModele">Modèle</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-purchase-tag"></i></span>
              <input type="text" name="modele" id="filtreModele" class="form-control" value="{{ $filtres['modele'] ?? '' }}" placeholder="Nom du modèle">
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <label class="form-label" for="filtreImmatriculation">Immatriculation</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-id-card"></i></span>
              <input type="text" name="immatriculation" id="filtreImmatriculation" class="form-control" value="{{ $filtres['immatriculation'] ?? '' }}" placeholder="Numéro de plaque">
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <label class="form-label" for="filtreChassis">N° de châssis</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-barcode"></i></span>
              <input type="text" name="chassis" id="filtreChassis" class="form-control" value="{{ $filtres['chassis'] ?? '' }}" placeholder="Numéro de châssis">
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <label class="form-label" for="filtreStatut">Statut</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-check-circle"></i></span>
              <select name="statut" id="filtreStatut" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="parc" @selected(($filtres['statut'] ?? '') === 'parc')>Dans le parc</option>
                @foreach (\App\Models\LocationVente\Moto::STATUTS as $valeur => $libelle)
                  <option value="{{ $valeur }}" @selected(($filtres['statut'] ?? '') === $valeur)>{{ $libelle }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-6 col-xl-4">
            <label class="form-label" for="filtreLivreur">Livreur / propriétaire</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-user"></i></span>
              <select name="livreur_id" id="filtreLivreur" class="form-select">
                <option value="">Tous</option>
                @foreach ($livreursRecherche as $livreur)
                  <option value="{{ $livreur->id }}" @selected((string) ($filtres['livreur_id'] ?? '') === (string) $livreur->id)>{{ $livreur->nomComplet() }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-6 col-xl-4">
            <label class="form-label" for="filtreAcquisitionDu">Acquisition du</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-calendar"></i></span>
              <input type="date" name="acquisition_du" id="filtreAcquisitionDu" class="form-control" value="{{ $filtres['acquisition_du'] ?? '' }}">
            </div>
          </div>
          <div class="col-md-6 col-xl-4">
            <label class="form-label" for="filtreAcquisitionAu">Acquisition au</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-calendar-check"></i></span>
              <input type="date" name="acquisition_au" id="filtreAcquisitionAu" class="form-control" value="{{ $filtres['acquisition_au'] ?? '' }}">
            </div>
          </div>
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3 pt-3 border-top">
          <a href="{{ route('manager.motos.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-reset me-1"></i> Réinitialiser
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="bx bx-search me-1"></i> Rechercher
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Motos</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle table-motos mb-0">
        <thead>
          <tr>
            <th>Moto</th>
            <th>Immatriculation</th>
            <th>Châssis</th>
            <th>Statut</th>
            <th class="text-center">Écart</th>
            <th class="text-center">Documents</th>
            <th class="text-center">Livreur / propriétaire</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($motos as $moto)
            @php($contrat = match ($moto->statut) {
              \App\Models\LocationVente\Moto::STATUT_CEDEE => $moto->contratActuel,
              \App\Models\LocationVente\Moto::STATUT_EN_LOCATION, \App\Models\LocationVente\Moto::STATUT_EN_PANNE => $moto->contratEnCours,
              default => null,
            })
            <tr>
              <td class="moto-identite">
                <div class="d-flex align-items-center">
                  @if ($principale = $moto->photoPrincipale())
                    <img src="{{ route('manager.motos.photo', [$moto, $principale->face]) }}?v={{ $principale->updated_at?->timestamp }}" alt="" class="rounded me-2 flex-shrink-0" style="width: 48px; height: 36px; object-fit: cover;" loading="lazy">
                  @endif
                  <div>
                    <div class="fw-medium">{{ trim($moto->marque.' '.$moto->modele) }}</div>
                    <small class="text-muted">{{ $moto->caracteristiques() ?: '—' }}</small>
                  </div>
                </div>
              </td>
              <td class="moto-plaque fw-medium">
                @if ($moto->immatriculationEnCours())
                  <span class="badge bg-label-warning">Immat. en cours</span>
                @else
                  {{ $moto->immatriculation }}
                @endif
              </td>
              <td class="moto-chassis">
                @if ($moto->numero_chassis)
                  <a href="{{ route('manager.motos.show', $moto) }}#bilan">{{ $moto->numero_chassis }}</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="moto-statut">
                <span class="badge bg-label-{{ $moto->couleurStatut() }}">{{ $moto->libelleStatut() }}</span>
                @if ($moto->gerant && $moto->statut === \App\Models\LocationVente\Moto::STATUT_CHEZ_GERANT)
                  <small class="moto-statut-detail d-block">{{ $moto->gerant->nomComplet() }}</small>
                @endif
                @if ($panne = $moto->immobilisationEnCours)
                  <small class="moto-statut-detail d-block">{{ $panne->libelleStatut() }} · {{ $panne->periodeLibelle() }}</small>
                  @if ($panne->motif)
                    <small class="d-block text-muted">{{ $panne->motif }}</small>
                  @endif
                @endif
              </td>
              <td class="text-center">
                @php($ecart = $moto->bilanFinancier()['sens'])
                @if ($ecart === 'gain')
                  <svg class="ecart-icone ecart-gain" viewBox="0 0 48 48" width="32" height="32" role="img" aria-label="Gain">
                    <title>Gain</title>
                    <circle cx="24" cy="24" r="24" fill="#2fbe4f"/>
                    <polyline points="11,31 19,21 26,27 36,13" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="28,13 36,13 36,21" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                @elseif ($ecart === 'perte')
                  <svg class="ecart-icone ecart-perte" viewBox="0 0 48 48" width="32" height="32" role="img" aria-label="Perte">
                    <title>Perte</title>
                    <circle cx="24" cy="24" r="24" fill="#e53935"/>
                    <polyline points="11,16 19,26 26,20 36,34" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="28,34 36,34 36,26" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-center">
                @php($documentsMoto = $moto->documentsParType())
                @if ($documentsMoto === [])
                  <span class="text-muted">0</span>
                @else
                  @php($detailDocuments = collect(\App\Models\LocationVente\MotoDocument::TYPES)->map(function (string $libelle, string $type) use ($documentsMoto) {
                      $document = $documentsMoto[$type] ?? null;
                      if (! $document) {
                          return null;
                      }
                      $precision = $document->date_fin?->format('d/m/Y') ?: $document->numero;

                      return e($precision ? $libelle.' · '.$precision : $libelle);
                  })->filter()->implode('<br>'))
                  <button type="button" class="doc-nombre" data-bs-toggle="popover" data-bs-placement="left" data-bs-html="true" data-bs-title="Documents" data-bs-content="{!! $detailDocuments !!}">{{ count($documentsMoto) }}</button>
                @endif
              </td>
              <td class="moto-livreur text-center">
                @if ($contrat?->livreur)
                  @php($livreur = $contrat->livreur)
                  @php($detailLivreur = collect([
                      e($livreur->nomComplet()),
                      $livreur->contact ? e($livreur->contact) : null,
                      e($contrat->reference.' · '.$contrat->libelleStatut()),
                      $contrat->date_debut ? e('Début le '.$contrat->date_debut->format('d/m/Y')) : null,
                      $contrat->date_solde ? e('Propriétaire depuis le '.$contrat->date_solde->format('d/m/Y')) : null,
                  ])->filter()->implode('<br>'))
                  <button type="button" class="livreur-photo" data-bs-toggle="popover" data-bs-placement="left" data-bs-html="true" data-bs-title="Livreur" data-bs-content="{!! $detailLivreur !!}">
                    <img src="{{ route('manager.livreurs.photo', $livreur) }}?v={{ optional($livreur->updated_at)->timestamp }}" alt="{{ $livreur->nomComplet() }}" class="rounded-circle" width="40" height="40" loading="lazy">
                  </button>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>
                <div class="moto-actions">
                @if ($moto->statut === \App\Models\LocationVente\Moto::STATUT_DISPONIBLE)
                  <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalNouveauContrat" data-moto-id="{{ $moto->id }}" title="Attribuer cette moto à un livreur">
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
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4">Aucune moto ne correspond à cette recherche</td>
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <form action="{{ route('manager.motos.store') }}" method="POST" enctype="multipart/form-data">
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
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <form action="{{ route('manager.motos.update', $moto) }}" method="POST" enctype="multipart/form-data">
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

@push('head')
<style>
  .ecart-icone {
    display: inline-block;
    vertical-align: middle;
    filter: drop-shadow(2px 3px 0 rgba(67, 89, 113, .18));
  }
  .table-motos { min-width: 1180px; }
  .table-motos th {
    white-space: nowrap;
    font-size: .75rem;
    letter-spacing: .03em;
  }
  .moto-identite { min-width: 190px; }
  .moto-identite .fw-medium,
  .moto-plaque,
  .moto-chassis,
  .moto-livreur { white-space: nowrap; }
  .moto-chassis { font-size: .8125rem; }
  .moto-statut { min-width: 150px; }
  .moto-statut-detail {
    max-width: 190px;
    margin-top: .25rem;
    color: #a15c07;
    line-height: 1.3;
  }
  .moto-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: .35rem;
    white-space: nowrap;
  }
  .doc-nombre {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.75rem;
    height: 1.75rem;
    padding: 0 .45rem;
    border: 0;
    border-radius: 999px;
    background: #e7e7ff;
    color: #696cff;
    font-weight: 600;
    line-height: 1;
    cursor: default;
  }
  .doc-nombre:hover,
  .doc-nombre:focus { background: #dcdcff; }
  .livreur-photo {
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: transparent;
    line-height: 0;
    cursor: default;
  }
  .livreur-photo img { object-fit: cover; display: block; }
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('change', function (event) {
    var input = event.target;
    if (!input.classList || !input.classList.contains('js-input-photo-moto') || !input.files || !input.files[0]) return;
    var bloc = input.closest('.js-bloc-photo-moto');
    var apercu = bloc.querySelector('.js-apercu-photo-moto');
    apercu.src = URL.createObjectURL(input.files[0]);
    apercu.classList.remove('d-none');
    bloc.querySelector('.js-icone-photo-moto').classList.add('d-none');
    var etat = bloc.querySelector('.js-etat-photo-moto');
    etat.textContent = 'Nouvelle photo';
    etat.className = 'small text-warning fw-medium js-etat-photo-moto';
    bloc.classList.remove('border-danger');
  });

  document.addEventListener('submit', function (event) {
    var manquantes = [];
    event.target.querySelectorAll('.js-input-photo-moto[data-obligatoire]').forEach(function (input) {
      if (input.files && input.files.length) return;
      manquantes.push(input.dataset.libelle);
      input.closest('.js-bloc-photo-moto').classList.add('border-danger');
    });
    if (manquantes.length) {
      event.preventDefault();
      alert('Photos manquantes : ' + manquantes.join(', ') + '.');
    }
  });

  document.querySelectorAll('.doc-nombre, .livreur-photo').forEach(function (element) {
    if (!window.bootstrap || !bootstrap.Popover) return;
    new bootstrap.Popover(element, {
      container: 'body',
      html: true,
      trigger: 'hover focus',
      sanitize: false,
    });
  });
</script>
@endpush
