@extends('layout.app')

@section('title', 'Gestion des boutiques')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Gestion des boutiques</h4>
      <p class="text-muted mb-0">Liste des boutiques et ajout de nouvelles boutiques</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterBoutique">
      <i class="icon-base bx bx-plus me-1"></i> Ajouter une boutique
    </button>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 col-xl-4 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Total boutiques</span>
          <h4 class="card-title mb-0">{{ $boutiques->total() }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Boutiques actives</span>
          <h4 class="card-title mb-0 text-success">{{ $boutiquesActives }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Clients rattachés</span>
          <h4 class="card-title mb-0">{{ $clientsTotal }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0">Boutiques</h5>
      <form method="GET" action="{{ route('boutiques.index') }}" class="d-flex gap-2">
        <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="{{ request('q') }}">
        <button type="submit" class="btn btn-outline-primary">Rechercher</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Logo</th>
            <th>Nom</th>
            <th>Type d'articles</th>
            <th>Commune</th>
            <th>Gérant</th>
            <th>Coordonnées</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($boutiques as $boutique)
            <tr>
              <td>
                <a href="{{ route('boutiques.show', $boutique) }}" title="Voir la fiche">
                  <img src="{{ $boutique->logoUrl() }}" alt="" class="rounded-circle" width="40" height="40" style="object-fit: cover; background: #e7e7ff;" onerror="this.onerror=null;this.src='{{ route('boutiques.logo', $boutique) }}';">
                </a>
              </td>
              <td>
                <a href="{{ route('boutiques.show', $boutique) }}" class="text-heading">{{ $boutique->nom }}</a>
              </td>
              <td>{{ $boutique->type_articles ?: 'N/A' }}</td>
              <td>{{ $boutique->commune?->nom_commune ?: 'Non renseignée' }}</td>
              <td>
                @if ($boutique->gerant)
                  {{ $boutique->gerant->nom }} {{ $boutique->gerant->prenoms }}
                @else
                  <span class="text-muted">Aucun</span>
                @endif
              </td>
              <td>
                @if (! is_null($boutique->latitude) && ! is_null($boutique->longitude))
                  {{ $boutique->latitude }}, {{ $boutique->longitude }}
                @else
                  <span class="text-muted">Non renseignées</span>
                @endif
              </td>
              <td>
                @if ($boutique->statut)
                  <span class="badge bg-label-success">Actif</span>
                @else
                  <span class="badge bg-label-danger">Inactif</span>
                @endif
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifier{{ $boutique->id }}" title="Modifier">
                  <i class="icon-base bx bx-edit"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalSupprimerBoutique"
                  data-item-name="{{ $boutique->nom }}"
                  data-delete-url="{{ route('boutiques.destroy', $boutique) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4">Aucune boutique trouvée</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">
        Affichage de {{ $boutiques->firstItem() ?? 0 }} à {{ $boutiques->lastItem() ?? 0 }} sur {{ $boutiques->total() }} boutiques
      </small>
      {{ $boutiques->links() }}
    </div>
  </div>

  <div class="modal fade" id="modalAjouterBoutique" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('boutiques.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une boutique</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="nom">Nom</label>
              <input type="text" class="form-control" id="nom" name="nom" value="{{ old('nom') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="type_articles">Type d'articles</label>
              <input type="text" class="form-control" id="type_articles" name="type_articles" value="{{ old('type_articles') }}" placeholder="Ex: vêtements, chaussures...">
            </div>
            <div class="mb-3">
              <label class="form-label" for="commune_id">Commune</label>
              <select class="form-select" id="commune_id" name="commune_id" required>
                <option value="">Sélectionner une commune</option>
                @foreach ($communes as $commune)
                  <option value="{{ $commune->commune_id }}" @selected((string) old('commune_id') === (string) $commune->commune_id)>{{ $commune->nom_commune }}</option>
                @endforeach
              </select>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label" for="latitude">Latitude</label>
                <input type="number" class="form-control" id="latitude" name="latitude" value="{{ old('latitude') }}" step="any" min="-90" max="90" placeholder="Optionnel">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="longitude">Longitude</label>
                <input type="number" class="form-control" id="longitude" name="longitude" value="{{ old('longitude') }}" step="any" min="-180" max="180" placeholder="Optionnel">
              </div>
            </div>
            <div class="mb-0">
              <label class="form-label" for="statut">Statut</label>
              <select class="form-select" id="statut" name="statut">
                <option value="1" @selected(old('statut', '1') == '1')>Actif</option>
                <option value="0" @selected(old('statut') === '0')>Inactif</option>
              </select>
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

  @foreach ($boutiques as $boutique)
    <div class="modal fade" id="modalModifier{{ $boutique->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('boutiques.update', $boutique) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier la boutique</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" class="form-control" name="nom" value="{{ $boutique->nom }}" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Type d'articles</label>
                <input type="text" class="form-control" name="type_articles" value="{{ $boutique->type_articles }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Commune</label>
                <select class="form-select" name="commune_id" required>
                  <option value="">Sélectionner une commune</option>
                  @foreach ($communes as $commune)
                    <option value="{{ $commune->commune_id }}" @selected((string) $boutique->commune_id === (string) $commune->commune_id)>{{ $commune->nom_commune }}</option>
                  @endforeach
                </select>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Latitude</label>
                  <input type="number" class="form-control" name="latitude" value="{{ $boutique->latitude }}" step="any" min="-90" max="90" placeholder="Optionnel">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Longitude</label>
                  <input type="number" class="form-control" name="longitude" value="{{ $boutique->longitude }}" step="any" min="-180" max="180" placeholder="Optionnel">
                </div>
              </div>
              <div class="mb-0">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut">
                  <option value="1" @selected($boutique->statut)>Actif</option>
                  <option value="0" @selected(! $boutique->statut)>Inactif</option>
                </select>
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

  <div class="modal fade" id="modalSupprimerBoutique" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Supprimer une boutique</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-1">Vous êtes sur le point de supprimer la boutique :</p>
          <p class="fw-bold mb-0" id="supprimerItemNom"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <form id="formSupprimerItem" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Supprimer</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalSupprimerBoutique');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;
      var nameEl = document.getElementById('supprimerItemNom');
      if (nameEl) nameEl.textContent = button.getAttribute('data-item-name') || '';
      var form = document.getElementById('formSupprimerItem');
      var deleteUrl = button.getAttribute('data-delete-url') || '';
      if (form && deleteUrl) form.setAttribute('action', deleteUrl);
    });
  });
</script>
@endpush
