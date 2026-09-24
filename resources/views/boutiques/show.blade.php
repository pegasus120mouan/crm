@extends('layout.app')

@section('title', $boutique->nom)

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">{{ $boutique->nom }}</h4>
      <p class="text-muted mb-0">Fiche boutique</p>
    </div>
    <a href="{{ route('boutiques.index') }}" class="btn btn-outline-secondary">
      <i class="icon-base bx bx-arrow-back me-1"></i> Retour
    </a>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Total clients</span>
          <h4 class="card-title mb-0">{{ $clientsTotal }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Clients actifs</span>
          <h4 class="card-title mb-0 text-success">{{ $clientsActifs }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Clients inactifs</span>
          <h4 class="card-title mb-0">{{ $clientsInactifs }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Boutiques</span>
          <h4 class="card-title mb-0">{{ $boutiquesTotal }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4 mb-4">
      <div class="card">
        <div class="card-body text-center">
          <img
            id="boutiqueLogoPreview"
            src="{{ $boutique->logoUrl() }}"
            alt=""
            class="rounded-circle mb-3"
            width="110"
            height="110"
            style="object-fit: cover; cursor: pointer; background: #e7e7ff;"
            onerror="this.onerror=null;this.src='{{ route('boutiques.logo', $boutique) }}';">

          <form id="logoForm" action="{{ route('boutiques.update', $boutique) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="redirect_to" value="show">
            <input type="file" name="logo" id="boutiqueLogoInput" accept="image/*" class="d-none">
            <div id="logoActions" class="d-grid gap-2 mb-3" style="display: none;">
              <button type="submit" class="btn btn-primary">Mettre à jour le logo</button>
              <button type="button" id="cancelLogoBtn" class="btn btn-outline-secondary">Annuler</button>
            </div>
          </form>

          <h5 class="mb-1">{{ $boutique->nom }}</h5>
          <p class="text-muted">{{ $boutique->type_articles ?: 'N/A' }}</p>

          <div class="text-start">
            <div class="d-flex justify-content-between py-2 border-bottom">
              <span class="fw-medium">Gérant</span>
              <span>
                @if ($boutique->gerant)
                  {{ $boutique->gerant->nom }} {{ $boutique->gerant->prenoms }}
                  @if ($boutique->gerant->contact)
                    ({{ $boutique->gerant->contact }})
                  @endif
                @else
                  N/A
                @endif
              </span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
              <span class="fw-medium">Commandes</span>
              <span>{{ $commandesCount }}</span>
            </div>
            <div class="d-flex justify-content-between py-2">
              <span class="fw-medium">Type d'articles</span>
              <span>{{ $boutique->type_articles ?: 'N/A' }}</span>
            </div>
          </div>

          <a href="{{ route('boutiques.index') }}" class="btn btn-primary w-100 mt-3">Retour</a>
        </div>
      </div>
    </div>

    <div class="col-md-8 mb-4">
      <div class="card">
        <div class="card-header">
          <ul class="nav nav-pills card-header-pills" role="tablist">
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-nom" type="button">Changer le nom de la boutique</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-gerant" type="button">Changer le Gérant</button>
            </li>
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-articles" type="button">Types d'articles</button>
            </li>
          </ul>
        </div>
        <div class="card-body">
          <div class="tab-content">
            <div class="tab-pane fade" id="tab-nom">
              <form action="{{ route('boutiques.update', $boutique) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect_to" value="show">
                <div class="mb-3">
                  <label class="form-label" for="nom">Nom de la boutique</label>
                  <input type="text" class="form-control" id="nom" name="nom" value="{{ $boutique->nom }}" required>
                </div>
                <button type="submit" class="btn btn-primary">Valider</button>
              </form>
            </div>

            <div class="tab-pane fade" id="tab-gerant">
              <form action="{{ route('boutiques.update', $boutique) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect_to" value="show">
                <div class="mb-3">
                  <label class="form-label" for="gerant_id">Gérant</label>
                  <select class="form-select" id="gerant_id" name="gerant_id" required>
                    <option value="">-- Sélectionner un client --</option>
                    @foreach ($clients as $client)
                      <option value="{{ $client->id }}" @selected($boutique->gerant && $boutique->gerant->id === $client->id)>
                        {{ $client->nom }} {{ $client->prenoms }} ({{ $client->contact }})
                      </option>
                    @endforeach
                  </select>
                </div>
                <button type="submit" class="btn btn-primary">Valider</button>
              </form>
            </div>

            <div class="tab-pane fade show active" id="tab-articles">
              <form action="{{ route('boutiques.update', $boutique) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect_to" value="show">
                <div class="mb-3">
                  <label class="form-label" for="type_articles">Types d'articles</label>
                  <input type="text" class="form-control" id="type_articles" name="type_articles" value="{{ $boutique->type_articles }}">
                </div>
                <button type="submit" class="btn btn-primary">Modifier</button>
              </form>
              <hr>
              <p class="text-muted mb-0">Cliquez sur le logo (à gauche) pour le changer.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var preview = document.getElementById('boutiqueLogoPreview');
    var input = document.getElementById('boutiqueLogoInput');
    var actions = document.getElementById('logoActions');
    var cancelBtn = document.getElementById('cancelLogoBtn');
    if (!preview || !input || !actions || !cancelBtn) return;

    var originalSrc = preview.getAttribute('src');

    preview.addEventListener('click', function () {
      input.click();
    });

    input.addEventListener('change', function () {
      if (!input.files || !input.files[0]) {
        actions.style.display = 'none';
        preview.setAttribute('src', originalSrc);
        return;
      }

      var reader = new FileReader();
      reader.onload = function (e) {
        preview.setAttribute('src', e.target.result);
        actions.style.display = 'grid';
      };
      reader.readAsDataURL(input.files[0]);
    });

    cancelBtn.addEventListener('click', function () {
      input.value = '';
      actions.style.display = 'none';
      preview.setAttribute('src', originalSrc);
    });
  });
</script>
@endpush
