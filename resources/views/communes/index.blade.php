@extends('layout.app')

@section('title', 'Liste des communes')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Liste des communes</h4>
      <p class="text-muted mb-0">Communes utilisées pour localiser les boutiques</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterCommune">
      <i class="icon-base bx bx-plus me-1"></i> Ajouter une commune
    </button>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 col-xl-4 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Total communes</span>
          <h4 class="card-title mb-0">{{ $communes->total() }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0">Communes</h5>
      <form method="GET" action="{{ route('communes.index') }}" class="d-flex gap-2">
        <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="{{ request('q') }}">
        <button type="submit" class="btn btn-outline-primary">Rechercher</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Commune</th>
            <th>Boutiques</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($communes as $commune)
            <tr>
              <td>{{ $commune->nom_commune }}</td>
              <td>{{ $commune->boutiques_count }}</td>
              <td>
                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifier{{ $commune->commune_id }}" title="Modifier">
                  <i class="icon-base bx bx-edit"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalSupprimerCommune"
                  data-item-name="{{ $commune->nom_commune }}"
                  data-delete-url="{{ route('communes.destroy', $commune) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="text-center py-4">Aucune commune trouvée</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">
        Affichage de {{ $communes->firstItem() ?? 0 }} à {{ $communes->lastItem() ?? 0 }} sur {{ $communes->total() }} communes
      </small>
      {{ $communes->links() }}
    </div>
  </div>

  <div class="modal fade" id="modalAjouterCommune" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('communes.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une commune</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-0">
              <label class="form-label" for="nom_commune">Nom de la commune</label>
              <input type="text" class="form-control" id="nom_commune" name="nom_commune" value="{{ old('nom_commune') }}" required>
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

  @foreach ($communes as $commune)
    <div class="modal fade" id="modalModifier{{ $commune->commune_id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('communes.update', $commune) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier la commune</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-0">
                <label class="form-label">Nom de la commune</label>
                <input type="text" class="form-control" name="nom_commune" value="{{ $commune->nom_commune }}" required>
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

  <div class="modal fade" id="modalSupprimerCommune" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Supprimer une commune</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-1">Vous êtes sur le point de supprimer la commune :</p>
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
    var modal = document.getElementById('modalSupprimerCommune');
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
