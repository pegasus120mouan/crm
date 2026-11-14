@extends('layout.app')

@section('title', 'Documents – Location-vente')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Documents</h4>
      <p class="text-muted mb-0">Une ligne par moto : châssis, plaque, vignette et assurance.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAssocierDocument">
      <i class="icon-base bx bx-plus me-1"></i> Associer un document
    </button>
  </div>

  <div class="row mb-4">
    @foreach ([
      ['Documents', $stats['total'], 'bx-folder', 'primary'],
      ['Expirés', $stats['expires'], 'bx-error', 'danger'],
      ['Expirent sous 30 jours', $stats['bientot'], 'bx-time', 'warning'],
    ] as [$libelle, $valeur, $icone, $couleur])
      <div class="col-sm-6 col-xl-4 mb-4">
        <div class="card h-100">
          <div class="card-body d-flex justify-content-between align-items-center">
            <div>
              <span class="d-block mb-1 text-body">{{ $libelle }}</span>
              <h4 class="card-title mb-0">{{ $valeur }}</h4>
            </div>
            <span class="avatar-initial rounded bg-label-{{ $couleur }} p-2"><i class="icon-base bx {{ $icone }} icon-md"></i></span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0">Motos</h5>
      <form method="GET" action="{{ route('manager.documents.index') }}" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" class="form-control" placeholder="Moto, plaque, châssis..." value="{{ $search }}">
        <button type="submit" class="btn btn-outline-primary">Rechercher</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th>Avatar</th>
            <th>Numéro châssis</th>
            <th>Plaque d'immatriculation</th>
            <th>Statut</th>
            <th>Vignette Debut</th>
            <th>Vignette Fin</th>
            <th>Nombre de jour restants</th>
            <th>Assurance Debut</th>
            <th>Assurance Fin</th>
            <th>Nombre de jour restants</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($motos as $moto)
            @php($papiers = $moto->documentsParType())
            @php($assurance = $papiers[\App\Models\LocationVente\MotoDocument::TYPE_ASSURANCE] ?? null)
            @php($vignette = $papiers[\App\Models\LocationVente\MotoDocument::TYPE_VIGNETTE] ?? null)
            @php($photo = $moto->photoPrincipale())
            <tr>
              <td>
                @if ($photo)
                  <img src="{{ route('manager.motos.photo', [$moto, $photo->face]) }}?v={{ $photo->updated_at?->timestamp }}" alt="" class="rounded" style="width: 36px; height: 36px; object-fit: cover;" loading="lazy">
                @else
                  <span class="avatar-initial rounded bg-label-secondary d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;"><i class="icon-base bx bx-user"></i></span>
                @endif
              </td>
              <td>
                @if ($moto->numero_chassis)
                  <a href="{{ route('manager.motos.show', $moto) }}">{{ $moto->numero_chassis }}</a>
                @else
                  -
                @endif
              </td>
              <td>
                @if ($plaque = $moto->numeroPlaque())
                  {{ $plaque }}
                @else
                  <span class="text-danger fw-medium">En cours !!</span>
                @endif
              </td>
              <td><span class="badge {{ $moto->classeStatutDocuments() }}">{{ $moto->libelleStatutDocuments() }}</span></td>
              <td>{{ $vignette?->date_debut?->format('d-m-y') ?: '-' }}</td>
              <td>{{ $vignette?->date_fin?->format('d-m-y') ?: '-' }}</td>
              <td class="text-center {{ $vignette?->joursRestants() === 0 ? 'bg-danger text-white' : '' }}">
                {{ $vignette?->date_fin ? $vignette->joursRestants().' jours' : '-' }}
              </td>
              <td>{{ $assurance?->date_debut?->format('d-m-y') ?: '-' }}</td>
              <td>{{ $assurance?->date_fin?->format('d-m-y') ?: '-' }}</td>
              <td class="text-center {{ $assurance?->joursRestants() === 0 ? 'bg-danger text-white' : '' }}">
                {{ $assurance?->date_fin ? $assurance->joursRestants().' jours' : '-' }}
              </td>
              <td class="text-nowrap">
                @foreach ([\App\Models\LocationVente\MotoDocument::TYPE_ASSURANCE, \App\Models\LocationVente\MotoDocument::TYPE_VIGNETTE, \App\Models\LocationVente\MotoDocument::TYPE_CARTE_GRISE] as $type)
                  @if ($document = $papiers[$type] ?? null)
                    @if ($document->chemin)
                      <a href="{{ route('manager.documents.fichier', $document) }}" target="_blank" class="btn btn-sm btn-icon btn-outline-secondary" title="Voir {{ $document->libelle() }}">
                        <i class="icon-base bx bx-show"></i>
                      </a>
                    @endif
                    <button type="button" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifierDocument{{ $document->id }}" title="Modifier {{ $document->libelle() }}">
                      <i class="icon-base bx bx-edit"></i>
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-icon btn-outline-danger"
                      title="Retirer {{ $document->libelle() }}"
                      data-bs-toggle="modal"
                      data-bs-target="#modalConfirmation"
                      data-confirm-titre="Retirer un document"
                      data-confirm-texte="Retirer {{ $document->libelle() }} de {{ $moto->libelle() }} ?"
                      data-confirm-url="{{ route('manager.documents.destroy', $document) }}">
                      <i class="icon-base bx bx-trash"></i>
                    </button>
                  @endif
                @endforeach
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="11" class="text-center py-4">Aucune moto dans le parc.</td>
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

  <div class="modal fade" id="modalAssocierDocument" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('manager.documents.store') }}" method="POST" enctype="multipart/form-data" class="js-formulaire-document">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Associer un document à une moto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Moto</label>
                <select name="moto_id" class="form-select" required>
                  <option value="">Choisir une moto</option>
                  @foreach ($choixMotos as $moto)
                    <option value="{{ $moto->id }}" @selected((string) old('moto_id') === (string) $moto->id)>{{ $moto->libelle() }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Type</label>
                <select name="type" class="form-select js-type-document" required>
                  <option value="">Choisir</option>
                  @foreach (\App\Models\LocationVente\MotoDocument::TYPES as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(old('type') === $valeur)>{{ $libelle }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 js-document-periode">
                <label class="form-label">Date début</label>
                <input type="date" class="form-control" name="date_debut" value="{{ old('date_debut') }}">
              </div>
              <div class="col-md-6 js-document-periode">
                <label class="form-label">Date fin</label>
                <input type="date" class="form-control" name="date_fin" value="{{ old('date_fin') }}">
              </div>
              <div class="col-12 js-document-numero d-none">
                <label class="form-label">N° de plaque</label>
                <input type="text" class="form-control text-uppercase" name="numero" value="{{ old('numero') }}" placeholder="Ex : AB 1234 CI">
              </div>
              <div class="col-12">
                <label class="form-label">Fichier <small class="text-muted">(optionnel, JPG, PNG, WEBP ou PDF, 5 Mo)</small></label>
                <input type="file" class="form-control" name="fichier" accept="image/jpeg,image/png,image/webp,application/pdf">
                <small class="text-muted">Une moto n'a qu'une vignette, une assurance et une carte grise. Un nouvel enregistrement du même type remplace le précédent.</small>
              </div>
            </div>
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
    @foreach ($moto->documents as $document)
    <div class="modal fade" id="modalModifierDocument{{ $document->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('manager.documents.update', $document) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier {{ $document->libelle() }} · {{ $document->moto?->libelle() }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                @if ($document->aUneValidite())
                  <div class="col-md-6">
                    <label class="form-label">Date début</label>
                    <input type="date" class="form-control" name="date_debut" value="{{ $document->date_debut?->format('Y-m-d') }}" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Date fin</label>
                    <input type="date" class="form-control" name="date_fin" value="{{ $document->date_fin?->format('Y-m-d') }}" required>
                  </div>
                @else
                  <div class="col-12">
                    <label class="form-label">N° de plaque</label>
                    <input type="text" class="form-control text-uppercase" name="numero" value="{{ $document->numero }}" placeholder="Ex : AB 1234 CI">
                  </div>
                @endif
                <div class="col-12">
                  <label class="form-label">Remplacer le fichier <small class="text-muted">(optionnel)</small></label>
                  <input type="file" class="form-control" name="fichier" accept="image/jpeg,image/png,image/webp,application/pdf">
                  @if ($document->chemin)
                    <a href="{{ route('manager.documents.fichier', $document) }}" target="_blank" class="small">Voir le document actuel</a>
                  @endif
                </div>
              </div>
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
  @endforeach

  @include('manager._confirmation')
@endsection

@push('scripts')
<script>
  function afficherChampsDocument(formulaire) {
    var type = formulaire.querySelector('.js-type-document');
    if (!type) return;
    var periode = type.value === 'vignette' || type.value === 'assurance';
    formulaire.querySelectorAll('.js-document-periode').forEach(function (bloc) {
      bloc.classList.toggle('d-none', !periode);
    });
    formulaire.querySelectorAll('.js-document-numero').forEach(function (bloc) {
      bloc.classList.toggle('d-none', type.value !== 'carte_grise');
    });
  }

  document.querySelectorAll('.js-formulaire-document').forEach(function (formulaire) {
    afficherChampsDocument(formulaire);
    formulaire.querySelector('.js-type-document').addEventListener('change', function () {
      afficherChampsDocument(formulaire);
    });
  });
</script>
@endpush
