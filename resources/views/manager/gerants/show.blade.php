@extends('layout.app')

@section('title', 'Gérant '.$gerant->code)

@section('content')
  @php($fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA')

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <a href="{{ route('manager.gerants.index') }}" class="text-muted"><i class="icon-base bx bx-chevron-left"></i> Gérants</a>
      <h4 class="mb-1 mt-1">{{ $gerant->nomComplet() }} <span class="badge bg-label-{{ $gerant->statut ? 'success' : 'secondary' }}">{{ $gerant->code }}</span></h4>
      <p class="text-muted mb-0">{{ $gerant->contact ?: 'Sans contact' }}</p>
    </div>
    <button
      type="button"
      class="btn btn-outline-danger"
      data-bs-toggle="modal"
      data-bs-target="#modalConfirmation"
      data-confirm-titre="Supprimer un gérant"
      data-confirm-texte="Supprimer {{ $gerant->nomComplet() }} ({{ $gerant->code }}) ? Impossible s'il a encore des motos confiées."
      data-confirm-url="{{ route('manager.gerants.destroy', $gerant) }}">
      <i class="icon-base bx bx-trash me-1"></i> Supprimer
    </button>
  </div>

  <div class="row">
    <div class="col-lg-5 mb-4">
      <div class="card h-100">
        <div class="card-header"><h6 class="mb-0">Fiche</h6></div>
        <div class="card-body">
          <form action="{{ route('manager.gerants.update', $gerant) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="text-center mb-3">
              <img
                src="{{ route('manager.gerants.photo', $gerant) }}?v={{ optional($gerant->updated_at)->timestamp }}"
                class="rounded mb-2 js-apercu-photo-gerant"
                style="width: 120px; height: 120px; object-fit: cover;"
                alt="Photo de {{ $gerant->nomComplet() }}"
              >
              <input type="file" class="form-control form-control-sm js-input-photo-gerant @error('photo') is-invalid @enderror" name="photo" accept="image/jpeg,image/png,image/webp">
              <small class="text-muted d-block mt-1">Changer la photo (JPG, PNG, WEBP, 4 Mo max)</small>
              @error('photo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
              @unless ($gerant->photo)
                <div class="alert alert-warning small py-2 mt-2 mb-0">Aucune photo enregistrée.</div>
              @endunless
            </div>
            <div class="mb-3">
              <label class="form-label">Nom</label>
              <input type="text" name="nom" class="form-control" value="{{ old('nom', $gerant->nom) }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Prénoms</label>
              <input type="text" name="prenoms" class="form-control" value="{{ old('prenoms', $gerant->prenoms) }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact</label>
              <input type="text" name="contact" class="form-control" value="{{ old('contact', $gerant->contact) }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Adresse</label>
              <input type="text" name="adresse" class="form-control" value="{{ old('adresse', $gerant->adresse) }}">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3">{{ old('notes', $gerant->notes) }}</textarea>
            </div>
            <div class="form-check mb-3">
              <input type="hidden" name="statut" value="0">
              <input class="form-check-input" type="checkbox" name="statut" value="1" id="gerant-actif" @checked(old('statut', $gerant->statut))>
              <label class="form-check-label" for="gerant-actif">Gérant actif</label>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7 mb-4">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Motos confiées</h6>
        </div>
        <div class="card-body">
          @if ($motosDisponibles->isNotEmpty() && $gerant->statut)
            <form action="#" method="GET" class="mb-3" onsubmit="return false;">
              <p class="small text-muted mb-2">Pour confier une moto, ouvrez sa fiche puis utilisez « Confier à un gérant ».</p>
            </form>
          @endif
          @forelse ($gerant->motos->where('statut', \App\Models\LocationVente\Moto::STATUT_CHEZ_GERANT) as $moto)
            <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
              <div>
                <a href="{{ route('manager.motos.show', $moto) }}" class="fw-medium">{{ $moto->libelle() }}</a>
                <div class="small text-muted">{{ $moto->libelleStatut() }}</div>
              </div>
              <form action="{{ route('manager.motos.gerant.restituer', $moto) }}" method="POST" onsubmit="return confirm('Restituer cette moto au parc ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-success">Restituer</button>
              </form>
            </div>
          @empty
            <p class="text-muted mb-0">Aucune moto confiée actuellement.</p>
          @endforelse
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h6 class="mb-0">Derniers paiements</h6></div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Moto</th>
                <th>Mode</th>
                <th class="text-end">Montant</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($gerant->paiements as $paiement)
                <tr>
                  <td>{{ $paiement->date_paiement->format('d/m/Y') }}</td>
                  <td>{{ $paiement->moto?->libelle() ?? '—' }}</td>
                  <td>{{ $paiement->mode }}</td>
                  <td class="text-end">{{ $fcfa($paiement->montant) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-3">Aucun paiement enregistré.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  @include('manager._confirmation')
@endsection

@push('scripts')
  <script>
    document.addEventListener('change', function (event) {
      var input = event.target;
      if (!input.classList || !input.classList.contains('js-input-photo-gerant') || !input.files || !input.files[0]) return;
      var apercu = input.closest('.card-body, .modal-body').querySelector('.js-apercu-photo-gerant');
      if (apercu) apercu.src = URL.createObjectURL(input.files[0]);
    });
  </script>
@endpush
