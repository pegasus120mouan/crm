@extends('layout.app')

@section('title', 'Type de moto')

@section('content')
  @php
    $montant = fn ($valeur) => number_format((int) $valeur, 0, ',', ' ');
    $edition = old('_formulaire') === 'type_moto' && old('_type_id');
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Type de moto</h4>
      <p class="text-muted mb-0">Chaque type a un coût d'acquisition, une marge et un montant journalier.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTypeMoto" id="boutonNouveauType">
      <i class="icon-base bx bx-plus me-1"></i> Enregistrer un type
    </button>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <span class="d-block mb-1 text-body">Total types</span>
            <h4 class="card-title mb-0">{{ $types->count() }}</h4>
          </div>
          <span class="avatar-initial rounded bg-label-primary p-2"><i class="icon-base bx bx-category icon-md"></i></span>
        </div>
      </div>
    </div>
    @foreach ($types as $type)
      @php($couleur = ['success', 'info', 'warning', 'secondary', 'danger'][$loop->index % 5])
      <div class="col-sm-6 col-xl-3 mb-4">
        <div class="card h-100">
          <div class="card-body d-flex justify-content-between align-items-center">
            <div>
              <span class="d-block mb-1 text-body">{{ $type->nom }}</span>
              <h4 class="card-title mb-0">{{ $type->motos_count }}</h4>
              <small class="text-muted">{{ $type->motos_count > 1 ? 'motos' : 'moto' }}</small>
            </div>
            <span class="avatar-initial rounded bg-label-{{ $couleur }} p-2"><i class="icon-base bx bx-cycling icon-md"></i></span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Type</th>
            <th class="text-end">Coût d'acquisition</th>
            <th class="text-end">Marge</th>
            <th class="text-end">Montant journalier</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($types as $type)
            <tr>
              <td class="fw-medium">{{ $type->nom }}</td>
              <td class="text-end">{{ $montant($type->cout_acquisition) }}</td>
              <td class="text-end">{{ $type->marge === null ? '—' : $montant($type->marge) }}</td>
              <td class="text-end">{{ $montant($type->montant_journalier) }}</td>
              <td class="text-end text-nowrap">
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-primary"
                  title="Modifier"
                  data-bs-toggle="modal"
                  data-bs-target="#modalTypeMoto"
                  data-type-id="{{ $type->id }}"
                  data-url="{{ route('manager.types-moto.update', $type) }}"
                  data-nom="{{ $type->nom }}"
                  data-cout="{{ $montant($type->cout_acquisition) }}"
                  data-marge="{{ $type->marge === null ? '' : $montant($type->marge) }}"
                  data-journalier="{{ $montant($type->montant_journalier) }}">
                  <i class="bx bx-edit-alt"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalConfirmation"
                  data-confirm-titre="Supprimer un type de moto"
                  data-confirm-texte="Supprimer le type « {{ $type->nom }} » ? Impossible s'il est encore utilisé par une moto."
                  data-confirm-url="{{ route('manager.types-moto.destroy', $type) }}">
                  <i class="bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Aucun type pour le moment.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="modalTypeMoto" tabindex="-1" aria-labelledby="modalTypeMotoTitre" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="{{ $edition ? route('manager.types-moto.update', old('_type_id')) : route('manager.types-moto.store') }}" id="formTypeMoto">
          @csrf
          <input type="hidden" name="_method" id="typeMotoMethode" value="{{ $edition ? 'PUT' : 'POST' }}">
          <input type="hidden" name="_formulaire" value="type_moto">
          <input type="hidden" name="_type_id" id="typeMotoId" value="{{ old('_type_id') }}">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTypeMotoTitre">{{ $edition ? 'Modifier le type' : 'Enregistrer un type' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="typeMotoNom">Type</label>
              <input type="text" name="nom" id="typeMotoNom" class="form-control @error('nom') is-invalid @enderror" value="{{ old('nom') }}" placeholder="Ex : Scooter 125, Tricycle" maxlength="100" required>
              @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
              <label class="form-label" for="typeMotoCout">Coût d'acquisition (FCFA)</label>
              <input type="text" name="cout_acquisition" id="typeMotoCout" inputmode="numeric" autocomplete="off" class="form-control js-montant @error('cout_acquisition') is-invalid @enderror" value="{{ old('cout_acquisition') }}" placeholder="420 000" required>
              @error('cout_acquisition') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
              <label class="form-label" for="typeMotoMarge">Marge (FCFA)</label>
              <input type="text" name="marge" id="typeMotoMarge" inputmode="numeric" autocomplete="off" class="form-control js-montant @error('marge') is-invalid @enderror" value="{{ old('marge') }}" placeholder="80 000" required>
              @error('marge') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div>
              <label class="form-label" for="typeMotoJournalier">Montant journalier (FCFA)</label>
              <input type="text" name="montant_journalier" id="typeMotoJournalier" inputmode="numeric" autocomplete="off" class="form-control js-montant @error('montant_journalier') is-invalid @enderror" value="{{ old('montant_journalier') }}" placeholder="4 000" required>
              @error('montant_journalier') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

  @include('manager._confirmation')
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalTypeMoto');
    var form = document.getElementById('formTypeMoto');
    var methode = document.getElementById('typeMotoMethode');
    var typeId = document.getElementById('typeMotoId');
    var titre = document.getElementById('modalTypeMotoTitre');
    var nom = document.getElementById('typeMotoNom');
    var cout = document.getElementById('typeMotoCout');
    var marge = document.getElementById('typeMotoMarge');
    var journalier = document.getElementById('typeMotoJournalier');
    var creation = @json(route('manager.types-moto.store'));
    if (!modal || !form) return;

    function formater(valeur) {
      var chiffres = String(valeur == null ? '' : valeur).replace(/\D/g, '');
      return chiffres.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    document.querySelectorAll('.js-montant').forEach(function (champ) {
      champ.addEventListener('input', function () {
        var curseur = champ.selectionStart || 0;
        var chiffresAvant = champ.value.slice(0, curseur).replace(/\D/g, '').length;
        var formate = formater(champ.value);
        champ.value = formate;
        var position = 0;
        var vus = 0;
        while (position < formate.length && vus < chiffresAvant) {
          if (/\d/.test(formate.charAt(position))) vus++;
          position++;
        }
        champ.setSelectionRange(position, position);
      });
    });

    function vider() {
      form.action = creation;
      methode.value = 'POST';
      typeId.value = '';
      titre.textContent = 'Enregistrer un type';
      nom.value = '';
      cout.value = '';
      marge.value = '';
      journalier.value = '';
    }

    modal.addEventListener('show.bs.modal', function (event) {
      var bouton = event.relatedTarget;
      if (!bouton || bouton.id === 'boutonNouveauType') {
        if (bouton) vider();
        return;
      }
      form.action = bouton.dataset.url || creation;
      methode.value = 'PUT';
      typeId.value = bouton.dataset.typeId || '';
      titre.textContent = 'Modifier le type';
      nom.value = bouton.dataset.nom || '';
      cout.value = bouton.dataset.cout || '';
      marge.value = bouton.dataset.marge || '';
      journalier.value = bouton.dataset.journalier || '';
    });

    @if ($errors->any() && old('_formulaire') === 'type_moto')
      bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
  });
</script>
@endpush
