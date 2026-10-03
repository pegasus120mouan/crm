{{-- Paramètres : $livreursDisponibles, $motosDisponibles. Un bouton peut présélectionner via data-moto-id / data-livreur-id. --}}
<div class="modal fade" id="modalNouveauContrat" tabindex="-1" aria-labelledby="modalNouveauContratTitre" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('manager.contrats.store') }}" method="POST" id="formNouveauContrat">
        @csrf
        <input type="hidden" name="_formulaire" value="nouveau_contrat">
        <div class="modal-header border-bottom">
          <h5 class="modal-title d-flex align-items-center" id="modalNouveauContratTitre">
            <span class="avatar-initial rounded bg-label-primary p-2 me-2"><i class="icon-base bx bx-cycling icon-md"></i></span>
            Attribuer une moto à un livreur
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body pt-4">
          <p class="text-muted small">L'attribution crée un contrat de location-vente : le livreur paie régulièrement et la moto devient sa propriété une fois le prix soldé.</p>
          @if ($livreursDisponibles->isEmpty() || $motosDisponibles->isEmpty())
            <div class="alert alert-warning">
              @if ($livreursDisponibles->isEmpty())
                Aucun livreur actif sans contrat en cours. Un livreur devient actif quand son KYC est accepté. <a href="{{ route('manager.livreurs.index') }}">Voir les livreurs</a>.<br>
              @endif
              @if ($motosDisponibles->isEmpty())
                Aucune moto disponible dans le parc. <a href="{{ route('manager.motos.index') }}">Ajouter une moto</a>.
              @endif
            </div>
          @endif
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="contratLivreur">Livreur</label>
              <select class="form-select" name="livreur_id" id="contratLivreur" required>
                <option value="">-- Choisir un livreur --</option>
                @foreach ($livreursDisponibles as $livreur)
                  <option value="{{ $livreur->id }}" @selected(old('livreur_id') == $livreur->id)>
                    {{ $livreur->code }} · {{ $livreur->nomComplet() }} · {{ $livreur->contact }}{{ $livreur->kyc_statut !== 'valide' ? ' (KYC non validé)' : '' }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted">Seuls les livreurs actifs (KYC accepté) sans contrat en cours.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="contratMoto">Moto</label>
              <select class="form-select" name="moto_id" id="contratMoto" required>
                <option value="">-- Choisir une moto disponible --</option>
                @foreach ($motosDisponibles as $moto)
                  <option value="{{ $moto->id }}" data-prix="{{ $moto->prix_achat }}" @selected(old('moto_id') == $moto->id)>{{ $moto->libelle() }}</option>
                @endforeach
              </select>
              <small class="text-muted">Seules les motos au statut « Disponible ».</small>
            </div>
            <div class="col-12">
              <div class="border rounded p-3 bg-lighter">
                <h6 class="mb-3"><i class="icon-base bx bx-calculator me-1"></i> Montant à rembourser par le livreur</h6>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label" for="contratPrixAchat">Prix d'achat de la moto (FCFA)</label>
                    <input type="number" class="form-control js-calcul" name="prix_achat" id="contratPrixAchat" min="0" value="{{ old('prix_achat') }}" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="contratCout">Coût supplémentaire (FCFA)</label>
                    <input type="number" class="form-control js-calcul" name="cout_supplementaire" id="contratCout" min="0" value="{{ old('cout_supplementaire', 0) }}">
                    <small class="text-muted">Immatriculation, assurance, transport...</small>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="contratMarge">Notre marge (FCFA)</label>
                    <input type="number" class="form-control js-calcul" name="marge" id="contratMarge" min="0" value="{{ old('marge', 0) }}">
                    <small class="text-muted" id="contratMargePourcent">&nbsp;</small>
                  </div>
                </div>
                <div class="d-flex justify-content-between align-items-center border-top mt-3 pt-3">
                  <span class="fw-medium">Total à rembourser</span>
                  <span class="fs-5 fw-bold text-primary" id="contratTotal">0 FCFA</span>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="contratApport">Apport initial (FCFA)</label>
              <input type="number" class="form-control js-calcul" name="apport" id="contratApport" min="0" value="{{ old('apport', 0) }}">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="contratModeApport">Mode de paiement de l'apport</label>
              <select class="form-select" name="mode_apport" id="contratModeApport">
                <option value="">--</option>
                @foreach (\App\Models\LocationVente\Paiement::MODES as $mode)
                  <option value="{{ $mode }}" @selected(old('mode_apport') === $mode)>{{ $mode }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="contratEcheance">Montant de chaque échéance (FCFA)</label>
              <input type="number" class="form-control js-calcul" name="montant_echeance" id="contratEcheance" min="1" value="{{ old('montant_echeance') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="contratFrequence">Fréquence des paiements</label>
              <select class="form-select js-calcul" name="frequence" id="contratFrequence" required>
                @foreach (\App\Models\LocationVente\Contrat::FREQUENCES as $valeur => $libelle)
                  <option value="{{ $valeur }}" @selected(old('frequence', 'journalier') === $valeur)>{{ $libelle }}</option>
                @endforeach
              </select>
              <small class="text-muted">Hebdomadaire = les 6 paiements journaliers regroupés en un seul.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="contratDebut">Date de remise de la moto</label>
              <input type="date" class="form-control js-calcul" name="date_debut" id="contratDebut" value="{{ old('date_debut', now()->toDateString()) }}" required>
            </div>
            <div class="col-12">
              <div class="alert alert-primary mb-0" id="contratApercu">Renseignez le prix et le montant des échéances pour voir la durée du contrat.</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="contratNotes">Notes</label>
              <textarea class="form-control" name="notes" id="contratNotes" rows="2">{{ old('notes') }}</textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top pt-3">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary"><i class="icon-base bx bx-check-circle me-1"></i> Attribuer la moto</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalNouveauContrat');
    var prixAchat = document.getElementById('contratPrixAchat');
    var cout = document.getElementById('contratCout');
    var marge = document.getElementById('contratMarge');
    var margePourcent = document.getElementById('contratMargePourcent');
    var total = document.getElementById('contratTotal');
    var apport = document.getElementById('contratApport');
    var echeance = document.getElementById('contratEcheance');
    var frequence = document.getElementById('contratFrequence');
    var debut = document.getElementById('contratDebut');
    var modeApport = document.getElementById('contratModeApport');
    var apercu = document.getElementById('contratApercu');
    var moto = document.getElementById('contratMoto');
    var livreur = document.getElementById('contratLivreur');
    if (!modal || !prixAchat || !apercu) return;

    var format = function (n) { return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'; };
    var JOURS = {{ \App\Models\LocationVente\Contrat::JOURS_TRAVAILLES_PAR_SEMAINE }};
    var unites = { journalier: 'jour (sauf dimanche)', hebdomadaire: 'semaine' };

    function ajouter(date, frequenceValeur, n) {
      var d = new Date(date.getTime());
      if (frequenceValeur === 'hebdomadaire') {
        d.setDate(d.getDate() + 7 * n);
        return d;
      }
      if (n <= 0) return d;
      var semaines = Math.floor((n - 1) / JOURS);
      d.setDate(d.getDate() + 7 * semaines);
      for (var restant = n - semaines * JOURS; restant > 0;) {
        d.setDate(d.getDate() + 1);
        if (d.getDay() !== 0) restant--;
      }
      return d;
    }

    function calculer() {
      var achat = parseInt(prixAchat.value, 10) || 0;
      var revient = achat + (parseInt(cout.value, 10) || 0);
      var m = parseInt(marge.value, 10) || 0;
      var p = revient + m;
      var a = parseInt(apport.value, 10) || 0;
      total.textContent = format(p);
      margePourcent.innerHTML = revient > 0 && m > 0
        ? 'Soit ' + (m * 100 / revient).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' % du coût de revient'
        : '&nbsp;';
      var e = parseInt(echeance.value, 10) || 0;
      modeApport.required = a > 0;

      if (p <= 0 || e <= 0) {
        apercu.className = 'alert alert-primary mb-0';
        apercu.textContent = 'Renseignez le prix d\'achat et le montant des échéances pour voir la durée du contrat.';
        return;
      }
      if (a > p) {
        apercu.className = 'alert alert-danger mb-0';
        apercu.textContent = "L'apport ne peut pas dépasser le montant à rembourser.";
        return;
      }

      var base = p - a;
      var n = Math.ceil(base / e);
      var derniere = base - (n - 1) * e;
      var fin = debut.value ? ajouter(new Date(debut.value + 'T00:00:00'), frequence.value, n) : null;

      var equivalent = frequence.value === 'hebdomadaire'
        ? ' (soit ' + format(Math.round(e / JOURS)) + ' par jour travaillé)'
        : ' (soit ' + format(e * JOURS) + ' par semaine)';

      apercu.className = 'alert alert-primary mb-0';
      apercu.innerHTML = 'Reste à financer : <strong>' + format(base) + '</strong> en <strong>' + n + ' échéance(s)</strong> de ' + format(e) +
        ' par ' + unites[frequence.value] + equivalent + (n > 1 && derniere !== e ? ', dernière échéance : ' + format(derniere) : '') +
        (fin ? '.<br>Fin prévue le <strong>' + fin.toLocaleDateString('fr-FR') + '</strong> : la moto deviendra alors la propriété du livreur.' : '.');
    }

    function prixDeLaMoto(forcer) {
      var option = moto.options[moto.selectedIndex];
      if (option && option.dataset.prix && (forcer || !prixAchat.value)) {
        prixAchat.value = option.dataset.prix;
      }
      calculer();
    }

    document.querySelectorAll('#formNouveauContrat .js-calcul').forEach(function (el) {
      el.addEventListener('input', calculer);
      el.addEventListener('change', calculer);
    });
    moto.addEventListener('change', function () { prixDeLaMoto(true); });

    var frequencePrecedente = frequence.value;
    frequence.addEventListener('change', function () {
      var e = parseInt(echeance.value, 10) || 0;
      if (e > 0 && frequencePrecedente !== frequence.value) {
        echeance.value = frequence.value === 'hebdomadaire' ? e * JOURS : Math.round(e / JOURS);
      }
      frequencePrecedente = frequence.value;
      calculer();
    });

    modal.addEventListener('show.bs.modal', function (event) {
      var bouton = event.relatedTarget;
      if (!bouton) return;
      if (bouton.dataset.motoId) {
        moto.value = bouton.dataset.motoId;
        prixDeLaMoto(true);
      }
      if (bouton.dataset.livreurId) {
        livreur.value = bouton.dataset.livreurId;
      }
    });

    calculer();

    @if ($errors->any() && old('_formulaire') === 'nouveau_contrat')
      bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
  });
</script>
@endpush
