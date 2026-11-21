@extends('layout.app')

@section('title', 'Liste des gérants – Location-vente')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Liste des gérants</h4>
      <p class="text-muted mb-0">Personnes à qui des motos peuvent être confiées (hors contrat location-vente)</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterGerant">
      <i class="icon-base bx bx-plus me-1"></i> Nouveau gérant
    </button>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Gérants</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Photo</th>
            <th>Code</th>
            <th>Nom</th>
            <th>Prénoms</th>
            <th>Contact</th>
            <th>Motos confiées</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($gerants as $gerant)
            <tr>
              <td>
                <a href="{{ route('manager.gerants.show', $gerant) }}">
                  <img
                    src="{{ route('manager.gerants.photo', $gerant) }}?v={{ optional($gerant->updated_at)->timestamp }}"
                    alt="{{ $gerant->nomComplet() }}"
                    class="rounded-circle"
                    style="width: 40px; height: 40px; object-fit: cover;"
                    loading="lazy"
                  >
                </a>
              </td>
              <td><a href="{{ route('manager.gerants.show', $gerant) }}">{{ $gerant->code }}</a></td>
              <td>
                <a href="{{ route('manager.gerants.show', $gerant) }}" class="fw-medium">{{ $gerant->nom }}</a>
              </td>
              <td>{{ $gerant->prenoms ?: '—' }}</td>
              <td>{{ $gerant->contact ?: '—' }}</td>
              <td>{{ $gerant->motos_count }}</td>
              <td>
                <span class="badge bg-label-{{ $gerant->statut ? 'success' : 'secondary' }}">
                  {{ $gerant->statut ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="text-nowrap text-end">
                <a href="{{ route('manager.gerants.show', $gerant) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalConfirmation"
                  data-confirm-titre="Supprimer un gérant"
                  data-confirm-texte="Supprimer {{ $gerant->nomComplet() }} ({{ $gerant->code }}) ? Impossible s'il a encore des motos confiées."
                  data-confirm-url="{{ route('manager.gerants.destroy', $gerant) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Aucun gérant pour le moment.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="modalAjouterGerant" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form action="{{ route('manager.gerants.store') }}" method="POST" enctype="multipart/form-data" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nouveau gérant</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-3">
            <img
              src="{{ asset('assets/img/avatars/1.png') }}"
              class="rounded mb-2 js-apercu-photo-gerant"
              style="width: 110px; height: 110px; object-fit: cover;"
              alt="Photo"
            >
            <input type="file" class="form-control form-control-sm js-input-photo-gerant @error('photo') is-invalid @enderror" name="photo" accept="image/jpeg,image/png,image/webp" required>
            <small class="text-muted d-block mt-1">Photo (JPG, PNG, WEBP, 4 Mo max)</small>
            @error('photo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="mb-3">
            <label class="form-label" for="gerant-nom">Nom <span class="text-danger">*</span></label>
            <input type="text" id="gerant-nom" name="nom" class="form-control @error('nom') is-invalid @enderror" value="{{ old('nom') }}" required>
            @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="mb-3">
            <label class="form-label" for="gerant-prenoms">Prénoms <span class="text-danger">*</span></label>
            <input type="text" id="gerant-prenoms" name="prenoms" class="form-control @error('prenoms') is-invalid @enderror" value="{{ old('prenoms') }}" required>
            @error('prenoms') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="mb-0">
            <label class="form-label" for="gerant-contact">Contact <span class="text-danger">*</span></label>
            <input type="text" id="gerant-contact" name="contact" class="form-control @error('contact') is-invalid @enderror" value="{{ old('contact') }}" required>
            @error('contact') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <input type="hidden" name="statut" value="1">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('change', function (event) {
      var input = event.target;
      if (!input.classList || !input.classList.contains('js-input-photo-gerant') || !input.files || !input.files[0]) return;
      var apercu = input.closest('.modal-body, .card-body').querySelector('.js-apercu-photo-gerant');
      if (apercu) apercu.src = URL.createObjectURL(input.files[0]);
    });
  </script>
  @if ($errors->any())
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('modalAjouterGerant');
        if (modal && window.bootstrap) {
          window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
      });
    </script>
  @endif
@endpush
