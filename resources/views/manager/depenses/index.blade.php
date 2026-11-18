@extends('layout.app')

@section('title', 'Dépenses moto')

@section('content')
  @php($fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA')
  @php($montantSaisi = old('montant'))
  @php($montantAffiche = ($montantSaisi !== null && $montantSaisi !== '') ? number_format((int) preg_replace('/\D+/', '', (string) $montantSaisi), 0, ',', ' ') : '')

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Dépenses moto</h4>
      <p class="text-muted mb-0">Réparations et autres frais. Sur un contrat en cours, la somme s'ajoute au montant à rembourser et peut allonger la date de fin.</p>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 mb-4">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <span class="d-block mb-1">Dépensé ce mois</span>
            <h4 class="card-title mb-0">{{ $fcfa($stats['mois']) }}</h4>
          </div>
          <span class="avatar-initial rounded bg-label-warning p-2"><i class="icon-base bx bx-wrench icon-md"></i></span>
        </div>
      </div>
    </div>
    <div class="col-sm-6 mb-4">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <span class="d-block mb-1">Total des dépenses</span>
            <h4 class="card-title mb-0">{{ $fcfa($stats['total']) }}</h4>
          </div>
          <span class="avatar-initial rounded bg-label-primary p-2"><i class="icon-base bx bx-receipt icon-md"></i></span>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Nouvelle dépense</h5></div>
    <div class="card-body">
      <form method="POST" action="{{ route('manager.depenses.store') }}">
        @csrf
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="depenseMotoRecherche">Moto</label>
            <div class="position-relative">
              <input type="hidden" name="moto_id" id="depenseMoto" value="{{ old('moto_id', $motoId) }}">
              <input type="text" id="depenseMotoRecherche" class="form-control @error('moto_id') is-invalid @enderror" placeholder="Nom du livreur, n° de contrat ou plaque" autocomplete="off" required>
              <div id="depenseMotoResultats" class="depense-moto-resultats d-none"></div>
            </div>
            @error('moto_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="depenseLibelle">Libellé</label>
            <input type="text" name="libelle" id="depenseLibelle" class="form-control @error('libelle') is-invalid @enderror" value="{{ old('libelle') }}" placeholder="Ex : Réparation moteur" maxlength="255" required>
            @error('libelle') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-3">
            <label class="form-label" for="depenseMontant">Montant (FCFA)</label>
            <input type="text" name="montant" id="depenseMontant" inputmode="numeric" autocomplete="off" class="form-control @error('montant') is-invalid @enderror" value="{{ $montantAffiche }}" placeholder="30 000" required>
            @error('montant') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-3">
            <label class="form-label" for="depenseDate">Date</label>
            <div class="depense-date position-relative">
              <input type="date" name="date_depense" id="depenseDate" class="form-control pe-5 @error('date_depense') is-invalid @enderror" value="{{ old('date_depense', now()->toDateString()) }}" required>
              <button type="button" class="depense-date-bouton" id="depenseDateCalendrier" aria-label="Ouvrir le calendrier">
                <i class="bx bx-calendar"></i>
              </button>
              <input type="date" id="depenseDateNative" data-date-fr="1" class="depense-date-native" tabindex="-1" aria-hidden="true" value="{{ old('date_depense', now()->toDateString()) }}">
            </div>
            @error('date_depense') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
        </div>
        <div class="d-flex justify-content-end mt-3">
          <button type="submit" class="btn btn-primary">Enregistrer la dépense</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">Historique</h5></div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Moto</th>
            <th>Livreur</th>
            <th>Libellé</th>
            <th>Contrat</th>
            <th class="text-end">Montant</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($depenses as $depense)
            <tr>
              <td>{{ $depense->date_depense->format('d/m/Y') }}</td>
              <td>
                <a href="{{ route('manager.motos.show', $depense->moto) }}#bilan">{{ $depense->moto->numero_chassis ?: '—' }}</a>
              </td>
              <td>
                @if ($depense->contrat?->livreur)
                  {{ $depense->contrat->livreur->nomComplet() }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>{{ $depense->libelle }}</td>
              <td>
                @if ($depense->contrat)
                  <a href="{{ route('manager.contrats.show', $depense->contrat) }}">{{ $depense->contrat->reference }}</a>
                @else
                  <span class="text-muted">Hors contrat</span>
                @endif
              </td>
              <td class="text-end fw-medium">{{ $fcfa($depense->montant) }}</td>
              <td class="text-end">
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Annuler"
                  data-bs-toggle="modal"
                  data-bs-target="#modalConfirmation"
                  data-confirm-titre="Annuler une dépense"
                  data-confirm-texte="Annuler {{ $depense->libelle }} ({{ $fcfa($depense->montant) }}) ? Le montant sera retiré du contrat en cours."
                  data-confirm-url="{{ route('manager.depenses.destroy', $depense) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">Aucune dépense enregistrée</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($depenses->hasPages())
      <div class="card-footer">{{ $depenses->links() }}</div>
    @endif
  </div>

  @include('manager._confirmation')
@endsection

@push('head')
<style>
  .depense-moto-resultats {
    position: absolute;
    z-index: 30;
    top: calc(100% + .35rem);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow: auto;
    background: #fff;
    border: 1px solid #e4e6e8;
    border-radius: .5rem;
    box-shadow: 0 .5rem 1.25rem rgba(67, 89, 113, .15);
  }
  .depense-moto-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas: "titre reference" "details reference";
    column-gap: .75rem;
    align-items: center;
    width: 100%;
    padding: .65rem .9rem;
    text-align: left;
    font: inherit;
    color: inherit;
    cursor: pointer;
    background: #fff;
    border: 0;
    border-bottom: 1px solid #f0f2f4;
  }
  .depense-moto-item:last-child { border-bottom: 0; }
  .depense-moto-item:hover,
  .depense-moto-item:focus { background: #f5f7fa; }
  .depense-moto-titre {
    grid-area: titre;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .depense-moto-details {
    grid-area: details;
    color: #8a93a2;
    font-size: .8125rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .depense-moto-item .badge { grid-area: reference; }
  .depense-moto-vide { padding: .75rem .9rem; color: #8a93a2; }
  .depense-date-bouton {
    position: absolute;
    top: 0;
    right: 0;
    width: 2.5rem;
    height: 100%;
    border: 0;
    background: transparent;
    color: #697a8d;
  }
  .depense-date-native {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
    border: 0;
    padding: 0;
  }
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var motos = @json($motosRecherche);
    var champ = document.getElementById('depenseMotoRecherche');
    var identifiant = document.getElementById('depenseMoto');
    var resultats = document.getElementById('depenseMotoResultats');
    if (!champ || !identifiant || !resultats) return;

    function normaliser(valeur) {
      return (valeur || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function choisir(id) {
      var moto = motos.find(function (item) { return String(item.id) === String(id); });
      identifiant.value = moto ? moto.id : '';
      champ.value = moto ? moto.texte : '';
      champ.setCustomValidity('');
      resultats.classList.add('d-none');
    }

    function ligne(moto) {
      var bouton = document.createElement('button');
      bouton.type = 'button';
      bouton.className = 'depense-moto-item';

      var entete = document.createElement('span');
      entete.className = 'depense-moto-titre';
      entete.textContent = moto.titre;

      var details = document.createElement('span');
      details.className = 'depense-moto-details';
      details.textContent = [moto.livreur, moto.plaque].filter(Boolean).join(' · ') || 'Sans contrat';

      bouton.appendChild(entete);
      if (moto.reference) {
        var badge = document.createElement('span');
        badge.className = 'badge bg-label-primary';
        badge.textContent = moto.reference;
        bouton.appendChild(badge);
      }
      bouton.appendChild(details);
      bouton.addEventListener('mousedown', function (event) {
        event.preventDefault();
        choisir(moto.id);
      });

      return bouton;
    }

    function afficher(liste) {
      resultats.innerHTML = '';
      if (liste.length === 0) {
        var vide = document.createElement('div');
        vide.className = 'depense-moto-vide';
        vide.textContent = 'Aucune moto';
        resultats.appendChild(vide);
      }
      liste.forEach(function (moto) {
        resultats.appendChild(ligne(moto));
      });
      resultats.classList.remove('d-none');
    }

    function filtrer() {
      var terme = normaliser(champ.value.trim());
      var liste = terme === ''
        ? motos.slice(0, 8)
        : motos.filter(function (moto) { return normaliser(moto.recherche).indexOf(terme) !== -1; }).slice(0, 12);
      afficher(liste);
    }

    champ.addEventListener('focus', filtrer);
    champ.addEventListener('input', function () {
      identifiant.value = '';
      filtrer();
    });
    champ.addEventListener('blur', function () {
      window.setTimeout(function () { resultats.classList.add('d-none'); }, 150);
    });
    champ.form.addEventListener('submit', function (event) {
      if (!identifiant.value) {
        event.preventDefault();
        champ.setCustomValidity('Choisissez une moto dans la liste.');
        champ.reportValidity();
      }
    });

    if (identifiant.value) {
      choisir(identifiant.value);
    }

    var montant = document.getElementById('depenseMontant');
    if (montant) {
      montant.addEventListener('input', function () {
        var curseur = montant.selectionStart || 0;
        var chiffresAvant = montant.value.slice(0, curseur).replace(/\D/g, '').length;
        var chiffres = montant.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        var formate = chiffres.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        montant.value = formate;
        var position = 0;
        var vus = 0;
        while (position < formate.length && vus < chiffresAvant) {
          if (/\d/.test(formate.charAt(position))) {
            vus++;
          }
          position++;
        }
        montant.setSelectionRange(position, position);
      });
    }

    var calendrier = document.getElementById('depenseDateCalendrier');
    var natif = document.getElementById('depenseDateNative');
    var champDate = document.getElementById('depenseDate');
    if (calendrier && natif && champDate) {
      calendrier.addEventListener('click', function () {
        if (champDate.value) {
          natif.value = champDate.value;
        }
        if (typeof natif.showPicker === 'function') {
          try {
            natif.showPicker();
            return;
          } catch (e) {}
        }
        natif.focus();
      });
      natif.addEventListener('change', function () {
        champDate.value = natif.value;
      });
    }
  });
</script>
@endpush
