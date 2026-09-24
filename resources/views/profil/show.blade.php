@extends('layout.app')

@section('title', 'Mon profil')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Mon profil</h4>
      <p class="text-muted mb-0">Photo, identité, contact et mot de passe</p>
    </div>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn btn-outline-secondary">
        <i class="icon-base bx bx-power-off me-1"></i> Déconnexion
      </button>
    </form>
  </div>

  <div class="row">
    <div class="col-md-4 mb-4">
      <div class="card">
        <div class="card-body text-center">
          <img
            id="profilAvatarPreview"
            src="{{ $utilisateur->avatarUrl() }}"
            alt="{{ trim($utilisateur->prenoms.' '.$utilisateur->nom) }}"
            class="rounded-circle mb-3"
            width="120"
            height="120"
            style="object-fit: cover; cursor: pointer; background: #e7e7ff;"
            onerror="this.onerror=null;this.src='{{ route('profil.avatar') }}';">

          <form id="avatarForm" action="{{ route('profil.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="nom" value="{{ $utilisateur->nom }}">
            <input type="hidden" name="prenoms" value="{{ $utilisateur->prenoms }}">
            <input type="hidden" name="contact" value="{{ $utilisateur->contact }}">
            <input type="file" name="avatar" id="profilAvatarInput" accept="image/*" class="d-none">
            <div id="avatarActions" class="d-grid gap-2 mb-3" style="display: none;">
              <button type="submit" class="btn btn-primary">Mettre à jour la photo</button>
              <button type="button" id="cancelAvatarBtn" class="btn btn-outline-secondary">Annuler</button>
            </div>
          </form>

          <h5 class="mb-1">{{ trim($utilisateur->prenoms.' '.$utilisateur->nom) }}</h5>
          <p class="text-muted mb-3">Commercial</p>

          <div class="text-start">
            <div class="d-flex justify-content-between py-2 border-bottom">
              <span class="text-muted">Login</span>
              <span>{{ $utilisateur->login }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
              <span class="text-muted">Contact</span>
              <span>{{ $utilisateur->contact ?: '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-2">
              <span class="text-muted">Statut</span>
              <span>{{ $utilisateur->statut_compte ? 'Actif' : 'Inactif' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-8 mb-4">
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Informations personnelles</h5>
        </div>
        <div class="card-body">
          <form action="{{ route('profil.update') }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label" for="nom">Nom</label>
                <input type="text" class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ old('nom', $utilisateur->nom) }}" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="prenoms">Prénoms</label>
                <input type="text" class="form-control @error('prenoms') is-invalid @enderror" id="prenoms" name="prenoms" value="{{ old('prenoms', $utilisateur->prenoms) }}" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="contact">Contact</label>
              <input type="text" class="form-control @error('contact') is-invalid @enderror" id="contact" name="contact" value="{{ old('contact', $utilisateur->contact) }}" required>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Mot de passe</h5>
        </div>
        <div class="card-body">
          <form action="{{ route('profil.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="nom" value="{{ $utilisateur->nom }}">
            <input type="hidden" name="prenoms" value="{{ $utilisateur->prenoms }}">
            <input type="hidden" name="contact" value="{{ $utilisateur->contact }}">
            <div class="mb-3">
              <label class="form-label" for="password">Nouveau mot de passe</label>
              <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password">
            </div>
            <div class="mb-3">
              <label class="form-label" for="password_confirmation">Confirmer le mot de passe</label>
              <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var preview = document.getElementById('profilAvatarPreview');
  var input = document.getElementById('profilAvatarInput');
  var actions = document.getElementById('avatarActions');
  var cancel = document.getElementById('cancelAvatarBtn');
  var original = preview ? preview.src : '';

  if (preview && input) {
    preview.addEventListener('click', function () {
      input.click();
    });
  }

  if (input) {
    input.addEventListener('change', function () {
      if (!input.files || !input.files[0] || !preview) {
        return;
      }
      preview.src = URL.createObjectURL(input.files[0]);
      if (actions) {
        actions.style.display = 'grid';
      }
    });
  }

  if (cancel) {
    cancel.addEventListener('click', function () {
      input.value = '';
      if (preview) {
        preview.src = original;
      }
      if (actions) {
        actions.style.display = 'none';
      }
    });
  }
});
</script>
@endpush
