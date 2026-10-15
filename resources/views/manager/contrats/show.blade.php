@extends('layout.app')

@section('title', 'Contrat '.$contrat->reference)

@section('content')
  @php
    $fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA';
    $enCours = $contrat->statut === \App\Models\LocationVente\Contrat::STATUT_EN_COURS;
    $photoMoto = $contrat->moto->photoPrincipale();
    $nombreEcheances = $contrat->nombreEcheances();
    $libellesEcheance = [
      'payee' => ['Payée', 'success'],
      'partielle' => ['Partiellement payée', 'info'],
      'en_retard' => ['En retard', 'danger'],
      'a_venir' => ['À venir', 'secondary'],
      'annulee' => ['Annulée', 'secondary'],
    ];
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <a href="{{ route('manager.contrats.index') }}" class="text-muted"><i class="icon-base bx bx-chevron-left"></i> Contrats</a>
      <h4 class="mb-1 mt-1">
        Contrat {{ $contrat->reference }}
        <span class="badge bg-label-{{ $contrat->couleurStatut() }} ms-2">{{ $contrat->retard() > 0 ? 'En retard' : $contrat->libelleStatut() }}</span>
      </h4>
      <p class="text-muted mb-0">{{ $contrat->livreur->nomComplet() }} · {{ $contrat->moto->libelle() }}</p>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifierContrat">
        <i class="icon-base bx bx-edit me-1"></i> Modifier
      </button>
      @if ($enCours)
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalResilier">
          <i class="icon-base bx bx-block me-1"></i> Résilier
        </button>
      @endif
    </div>
  </div>

  @if ($contrat->statut === \App\Models\LocationVente\Contrat::STATUT_SOLDE)
    <div class="alert alert-success d-flex align-items-center">
      <i class="icon-base bx bx-key me-2"></i>
      <div>Contrat soldé le <strong>{{ $contrat->date_solde?->format('d/m/Y') }}</strong> : la moto {{ $contrat->moto->immatriculationAffichee() }} est sortie du parc et appartient désormais à <strong>{{ $contrat->livreur->nomComplet() }}</strong>.</div>
    </div>
  @elseif ($contrat->statut === \App\Models\LocationVente\Contrat::STATUT_RESILIE)
    <div class="alert alert-secondary">
      Contrat résilié le <strong>{{ $contrat->date_resiliation?->format('d/m/Y') }}</strong>. La moto est revenue dans le parc.
    </div>
  @elseif ($contrat->retard() > 0)
    <div class="alert alert-danger">
      Le livreur a <strong>{{ $fcfa($contrat->retard()) }}</strong> de retard, soit environ {{ $contrat->echeancesEnRetard() }} échéance(s) impayée(s).
    </div>
  @endif

  <div class="row mb-4">
    @foreach ([
      ["Montant d'acquisition", $fcfa($contrat->prixAcquisitionMoto()), 'info'],
      ['Montant à rembourser', $fcfa($contrat->prix_total), 'primary'],
      ['Marge', $fcfa($contrat->notreMarge()), $contrat->notreMarge() >= 0 ? 'success' : 'danger'],
      ['Nombre de versements', (string) $nombreEcheances, 'secondary'],
    ] as [$libelle, $valeur, $couleur])
      <div class="col-sm-6 col-xl-3 mb-4">
        <div class="card h-100 border-start border-3 border-{{ $couleur }}">
          <div class="card-body">
            <span class="d-block mb-1">{{ $libelle }}</span>
            <h5 class="card-title mb-0 text-{{ $couleur }}">{{ $valeur }}</h5>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row mb-4">
    @foreach ([
      ['Montant payé', $fcfa($contrat->montantPaye()), 'success'],
      ['Reste à payer', $fcfa($contrat->reste()), 'warning'],
      ['Retard', $fcfa($contrat->retard()), $contrat->retard() > 0 ? 'danger' : 'secondary'],
    ] as [$libelle, $valeur, $couleur])
      <div class="col-sm-6 col-xl-4 mb-4">
        <div class="card h-100 border-start border-3 border-{{ $couleur }}">
          <div class="card-body py-3">
            <span class="d-block mb-1 small">{{ $libelle }}</span>
            <h6 class="card-title mb-0 text-{{ $couleur }}">{{ $valeur }}</h6>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row">
    <div class="col-lg-4 mb-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Conditions du contrat</h5></div>
        <div class="card-body">
          @if ($photoMoto)
            <div class="mb-3 rounded overflow-hidden bg-lighter">
              <img
                src="{{ route('manager.motos.photo', [$contrat->moto, $photoMoto->face]) }}?v={{ $photoMoto->updated_at?->timestamp }}"
                alt="{{ $contrat->moto->libelle() }}"
                class="w-100"
                style="max-height: 180px; object-fit: cover;">
            </div>
          @endif
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
              <small>Progression</small>
              <small class="fw-medium">{{ $contrat->progression() }}%</small>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar bg-{{ $contrat->statut === 'solde' ? 'success' : 'primary' }}" style="width: {{ $contrat->progression() }}%"></div>
            </div>
          </div>
          <dl class="row mb-0 small">
            <dt class="col-6 fw-normal text-muted">Livreur</dt>
            <dd class="col-6">{{ $contrat->livreur->nomComplet() }}<br>{{ $contrat->livreur->contact }}</dd>
            <dt class="col-6 fw-normal text-muted">Moto</dt>
            <dd class="col-6">{{ trim($contrat->moto->marque.' '.$contrat->moto->modele) }}<br>{{ $contrat->moto->immatriculationAffichee() }}</dd>
            <dt class="col-6 fw-normal text-muted">Prix d'acquisition</dt>
            <dd class="col-6">{{ $fcfa($contrat->prixAcquisitionMoto()) }}</dd>
            <dt class="col-6 fw-normal text-muted">Coût supplémentaire</dt>
            <dd class="col-6">{{ $fcfa($contrat->cout_supplementaire) }}</dd>
            <dt class="col-6 fw-medium text-muted">Notre marge</dt>
            <dd class="col-6 fw-medium text-success">
              {{ $fcfa($contrat->notreMarge()) }}
              @if ($nombreEcheances > 0)
                <small class="text-muted d-block fw-normal">
                  {{ $fcfa($contrat->notreMargeParEcheance()) }} / échéance × {{ $nombreEcheances }}
                </small>
              @endif
            </dd>
            <dt class="col-6 fw-medium">Total à rembourser</dt>
            <dd class="col-6 fw-medium">{{ $fcfa($contrat->prix_total) }}</dd>
            <dt class="col-6 fw-normal text-muted">Apport initial</dt>
            <dd class="col-6">{{ $fcfa($contrat->apport) }}</dd>
            <dt class="col-6 fw-normal text-muted">Échéance</dt>
            <dd class="col-6">{{ $fcfa($contrat->montant_echeance) }} / {{ $contrat->uniteFrequence() }}</dd>
            <dt class="col-6 fw-normal text-muted">Fréquence</dt>
            <dd class="col-6">{{ $contrat->libelleFrequence() }}</dd>
            <dt class="col-6 fw-normal text-muted">Nombre d'échéances</dt>
            <dd class="col-6">{{ $nombreEcheances }}</dd>
            <dt class="col-6 fw-normal text-muted">Date de début</dt>
            <dd class="col-6">{{ $contrat->date_debut->format('d/m/Y') }}</dd>
            <dt class="col-6 fw-normal text-muted">Durée</dt>
            <dd class="col-6">{{ $contrat->duree_mois ? $contrat->duree_mois.' mois' : '—' }}</dd>
            <dt class="col-6 fw-normal text-muted">Date de fin</dt>
            <dd class="col-6">
              @if ($contrat->date_fin)
                {{ $contrat->date_fin->format('d/m/Y') }}
              @else
                {{ $contrat->dateFinPrevue()->format('d/m/Y') }}
                <small class="text-muted d-block">estimée selon les échéances</small>
              @endif
            </dd>
            @if ($enCours)
              <dt class="col-6 fw-normal text-muted">Prochaine échéance</dt>
              <dd class="col-6">{{ optional($contrat->prochaineEcheance())->format('d/m/Y') ?? '—' }}</dd>
            @endif
          </dl>
          @if ($contrat->notes)
            <hr>
            <small class="text-muted d-block" style="white-space: pre-line;">{{ $contrat->notes }}</small>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-8 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h5 class="mb-0">Versements à effectuer</h5>
            <small class="text-muted">{{ $nombreEcheances }} versement(s) · {{ $contrat->libelleFrequence() }}</small>
          </div>
        </div>
        <div class="table-responsive" style="max-height: 560px;">
          <table class="table table-hover mb-0">
            <thead class="sticky-top bg-body">
              <tr>
                <th>N°</th>
                <th>Date prévue</th>
                <th class="text-end">Montant</th>
                <th>Statut</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($echeancier as $ligne)
                @php
                  $peutPayer = $enCours && ! in_array($ligne['statut'], ['payee', 'annulee'], true) && $contrat->reste() > 0;
                  $montantAPayer = $ligne['statut'] === 'partielle'
                    ? max(0, $ligne['cumul'] - $contrat->montantPaye())
                    : min($ligne['montant'], $contrat->reste());
                @endphp
                <tr>
                  <td>{{ $ligne['numero'] }}</td>
                  <td>{{ $ligne['date']->format('d/m/Y') }}</td>
                  <td class="text-end fw-medium">{{ $fcfa($ligne['montant']) }}</td>
                  <td><span class="badge bg-label-{{ $libellesEcheance[$ligne['statut']][1] }}">{{ $libellesEcheance[$ligne['statut']][0] }}</span></td>
                  <td class="text-end">
                    @if ($peutPayer && $montantAPayer > 0)
                      <button
                        type="button"
                        class="btn btn-sm btn-success js-payer-echeance"
                        data-bs-toggle="modal"
                        data-bs-target="#modalPaiement"
                        data-montant="{{ $montantAPayer }}"
                        data-date="{{ min($ligne['date']->toDateString(), now()->toDateString()) }}">
                        <i class="icon-base bx bx-money me-1"></i> Payer
                      </button>
                    @elseif ($ligne['statut'] === 'payee')
                      <span class="text-success small"><i class="icon-base bx bx-check"></i></span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center py-4">Aucun versement à effectuer.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-0">Historique des paiements</h5>
        <small class="text-muted">{{ $contrat->paiements->count() }} paiement(s) · un paiement peut être annulé tant que le contrat n'est pas résilié</small>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Mode</th>
            <th>Référence</th>
            <th class="text-end">Montant</th>
            <th>Enregistré par</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($contrat->paiements as $paiement)
            <tr>
              <td>{{ $paiement->date_paiement->format('d/m/Y') }}</td>
              <td><span class="badge bg-label-{{ $paiement->type === 'apport' ? 'info' : 'primary' }}">{{ $paiement->type === 'apport' ? 'Apport' : 'Échéance' }}</span></td>
              <td>{{ $paiement->mode }}</td>
              <td><small>{{ $paiement->reference ?: '—' }}</small></td>
              <td class="text-end fw-medium">{{ $fcfa($paiement->montant) }}</td>
              <td><small>{{ $paiement->auteur ? trim($paiement->auteur->prenoms.' '.$paiement->auteur->nom) : '—' }}</small></td>
              <td class="text-end">
                @if ($contrat->statut !== \App\Models\LocationVente\Contrat::STATUT_RESILIE)
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#modalConfirmation"
                    data-confirm-titre="Annuler un paiement"
                    data-confirm-texte="Annuler le paiement de {{ $fcfa($paiement->montant) }} du {{ $paiement->date_paiement->format('d/m/Y') }} ?{{ $contrat->statut === 'solde' ? ' Le contrat repassera en cours et la moto reviendra en location-vente.' : '' }}"
                    data-confirm-url="{{ route('manager.paiements.destroy', $paiement) }}">
                    <i class="icon-base bx bx-undo me-1"></i> Annuler
                  </button>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-4">Aucun paiement enregistré</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="modalModifierContrat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <form action="{{ route('manager.contrats.update', $contrat) }}" method="POST" id="formModifierContrat">
          @csrf
          @method('PUT')
          <input type="hidden" name="_formulaire" value="modifier_contrat">
          <div class="modal-header">
            <h5 class="modal-title">Modifier · {{ $contrat->reference }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            @php
              $joursSemaine = \App\Models\LocationVente\Contrat::JOURS_TRAVAILLES_PAR_SEMAINE;
              $montantAVerserInitial = old('montant_a_verser', $contrat->montantAVerser());
            @endphp
            <p class="text-muted small mb-3">
              Montant à verser (ex. 4&nbsp;000 / jour) saisi à la main.
              Fréquence journalier ou hebdomadaire (×&nbsp;{{ $joursSemaine }}), durée en mois :
              nombre d'échéances, total à rembourser et notre marge se calculent automatiquement.
            </p>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="editPrixAcquisition">Prix d'acquisition (FCFA)</label>
                <input type="number" class="form-control js-edit-calcul" name="prix_acquisition" id="editPrixAcquisition" min="0"
                  value="{{ old('prix_acquisition', $contrat->prixAcquisitionMoto()) }}" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="editCout">Coût supplémentaire (FCFA)</label>
                <input type="number" class="form-control js-edit-calcul" name="cout_supplementaire" id="editCout" min="0"
                  value="{{ old('cout_supplementaire', $contrat->cout_supplementaire) }}">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editApport">Apport initial (FCFA)</label>
                <input type="number" class="form-control js-edit-calcul" name="apport" id="editApport" min="0"
                  value="{{ old('apport', $contrat->apport) }}">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editMontantAVerser">Montant à verser (FCFA)</label>
                <input type="number" class="form-control js-edit-calcul" name="montant_a_verser" id="editMontantAVerser" min="1"
                  value="{{ $montantAVerserInitial }}" required>
                <small class="text-muted">Saisi manuellement — base journalière.</small>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editFrequence">Fréquence</label>
                <select class="form-select js-edit-calcul" name="frequence" id="editFrequence" required>
                  @foreach (\App\Models\LocationVente\Contrat::FREQUENCES as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(old('frequence', $contrat->frequence) === $valeur)>{{ $libelle }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editDateDebut">Date de début</label>
                <input type="date" class="form-control js-edit-calcul" name="date_debut" id="editDateDebut"
                  value="{{ old('date_debut', $contrat->date_debut->toDateString()) }}" required>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editDureeMois">Durée (mois)</label>
                <input type="number" class="form-control js-edit-calcul" name="duree_mois" id="editDureeMois" min="1" max="120"
                  value="{{ old('duree_mois', $contrat->duree_mois) }}" placeholder="Ex : 6" required>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editDateFin">Date de fin</label>
                <input type="date" class="form-control" id="editDateFin" value="" readonly>
                <small class="text-muted">Calculée : début + durée.</small>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editEcheance">Montant de chaque échéance (FCFA)</label>
                <input type="text" class="form-control" id="editEcheance" value="" readonly>
                <small class="text-muted">Journalier = montant ; hebdo = × {{ $joursSemaine }}.</small>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editNombreEcheances">Nombre d'échéances</label>
                <input type="text" class="form-control" id="editNombreEcheances" value="" readonly>
                <small class="text-muted">Auto selon durée et fréquence (6j/7).</small>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="editPrixTotal">Total à rembourser (FCFA)</label>
                <input type="text" class="form-control" id="editPrixTotal" value="" readonly>
                <small class="text-muted">Apport + échéance × nombre.</small>
              </div>
              <div class="col-12">
                <div class="alert alert-success mb-0 py-2" id="editMargeApercu">Notre marge : —</div>
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

  @push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var form = document.getElementById('formModifierContrat');
      var modal = document.getElementById('modalModifierContrat');
      if (!form || !modal) return;

      var acquisition = document.getElementById('editPrixAcquisition');
      var cout = document.getElementById('editCout');
      var total = document.getElementById('editPrixTotal');
      var apport = document.getElementById('editApport');
      var montantAVerser = document.getElementById('editMontantAVerser');
      var frequence = document.getElementById('editFrequence');
      var echeance = document.getElementById('editEcheance');
      var nombre = document.getElementById('editNombreEcheances');
      var debut = document.getElementById('editDateDebut');
      var duree = document.getElementById('editDureeMois');
      var fin = document.getElementById('editDateFin');
      var apercu = document.getElementById('editMargeApercu');
      var JOURS = {{ $joursSemaine }};
      var format = function (n) { return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'; };

      function parseIso(iso) {
        var parts = iso.split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
      }

      function formatIso(d) {
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var j = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + j;
      }

      function ajouterMois(iso, mois) {
        if (!iso || mois < 1) return '';
        var d = parseIso(iso);
        var jour = d.getDate();
        d.setMonth(d.getMonth() + mois);
        if (d.getDate() < jour) {
          d.setDate(0);
        }
        return formatIso(d);
      }

      function dateEcheance(debutIso, freq, numero) {
        var d = parseIso(debutIso);
        if (freq === 'hebdomadaire') {
          d.setDate(d.getDate() + 7 * numero);
          return d;
        }
        var restant = numero;
        while (restant > 0) {
          d.setDate(d.getDate() + 1);
          if (d.getDay() !== 0) restant--;
        }
        return d;
      }

      function nombreSurPeriode(debutIso, mois, freq) {
        var finIso = ajouterMois(debutIso, mois);
        if (!finIso) return 0;
        var finDate = parseIso(finIso);
        var n = 0;
        for (var i = 1; i <= 10000; i++) {
          if (dateEcheance(debutIso, freq, i) > finDate) break;
          n = i;
        }
        return n;
      }

      function recalculer() {
        var a = parseInt(acquisition.value, 10) || 0;
        var c = parseInt(cout.value, 10) || 0;
        var ap = parseInt(apport.value, 10) || 0;
        var v = parseInt(montantAVerser.value, 10) || 0;
        var e = frequence.value === 'hebdomadaire' ? v * JOURS : v;
        var mois = parseInt(duree.value, 10) || 0;
        var n = debut.value && mois > 0 ? nombreSurPeriode(debut.value, mois, frequence.value) : 0;
        var t = ap + (e * n);
        var marge = t - a;
        fin.value = debut.value && mois > 0 ? ajouterMois(debut.value, mois) : '';
        echeance.value = e > 0 ? format(e) : '—';
        nombre.value = n > 0 ? String(n) : '—';
        total.value = t > 0 ? format(t) : '—';
        apercu.className = 'alert ' + (marge >= 0 ? 'alert-success' : 'alert-danger') + ' mb-0 py-2';
        apercu.innerHTML = 'Notre marge : <strong>' + format(marge) + '</strong>' +
          (n > 0 ? ' · ' + format(Math.trunc(marge / n)) + ' / échéance × ' + n : '') +
          (c > 0 ? ' <span class="text-muted">(dont coût supp. ' + format(c) + ')</span>' : '');
      }

      form.querySelectorAll('.js-edit-calcul').forEach(function (el) {
        el.addEventListener('input', recalculer);
        el.addEventListener('change', recalculer);
      });
      recalculer();

      @if ($errors->any() && old('_formulaire') === 'modifier_contrat')
        bootstrap.Modal.getOrCreateInstance(modal).show();
      @endif
    });
  </script>
  @endpush

  @if ($enCours)
    @include('manager.paiements._modal', ['contratFixe' => $contrat, 'contrats' => collect()])

    @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('modalPaiement');
        var montant = document.getElementById('paiementMontant');
        var date = document.getElementById('paiementDate');
        if (!modal || !montant || !date) return;

        modal.addEventListener('show.bs.modal', function (event) {
          var bouton = event.relatedTarget;
          if (!bouton || !bouton.classList.contains('js-payer-echeance')) return;
          var m = parseInt(bouton.getAttribute('data-montant') || '0', 10);
          var d = bouton.getAttribute('data-date') || '';
          if (m > 0) {
            montant.value = m;
          }
          if (d) {
            date.value = d;
          }
        });
      });
    </script>
    @endpush

    <div class="modal fade" id="modalResilier" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('manager.contrats.resilier', $contrat) }}" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-header">
              <h5 class="modal-title">Résilier le contrat {{ $contrat->reference }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-warning">
                La moto {{ $contrat->moto->immatriculationAffichee() }} sera récupérée et redeviendra <strong>disponible</strong> dans le parc.
                Les {{ $fcfa($contrat->montantPaye()) }} déjà versés restent enregistrés.
              </div>
              <div class="mb-3">
                <label class="form-label">Date de résiliation</label>
                <input type="date" class="form-control" name="date_resiliation" value="{{ now()->toDateString() }}" required>
              </div>
              <div class="mb-0">
                <label class="form-label">Motif</label>
                <textarea class="form-control" name="motif" rows="2" placeholder="Ex : retards répétés, moto restituée..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-danger">Résilier le contrat</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif

  @include('manager._confirmation')
@endsection
