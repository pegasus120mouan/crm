{{-- Paramètres : $contrats (contrats en cours, avec paiements_sum_montant) ; $contratFixe (optionnel) pour pré-sélectionner un contrat. --}}
<div class="modal fade" id="modalPaiement" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('manager.paiements.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Enregistrer un paiement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Contrat</label>
            @isset($contratFixe)
              <input type="hidden" name="contrat_id" value="{{ $contratFixe->id }}">
              <input type="text" class="form-control" value="{{ $contratFixe->reference }} · {{ $contratFixe->livreur?->nomComplet() }}" disabled>
              <small class="text-muted">Reste à payer : {{ number_format($contratFixe->reste(), 0, ',', ' ') }} FCFA · échéance de {{ number_format($contratFixe->montant_echeance, 0, ',', ' ') }} FCFA</small>
            @else
              <select class="form-select" name="contrat_id" id="paiementContrat" required>
                <option value="">-- Choisir un contrat en cours --</option>
                @foreach ($contrats as $contrat)
                  <option value="{{ $contrat->id }}" data-echeance="{{ $contrat->montant_echeance }}" data-reste="{{ $contrat->reste() }}" @selected(old('contrat_id') == $contrat->id)>
                    {{ $contrat->reference }} · {{ $contrat->livreur?->nomComplet() }} · {{ $contrat->moto?->immatriculationAffichee() }} (reste {{ number_format($contrat->reste(), 0, ',', ' ') }} FCFA)
                  </option>
                @endforeach
              </select>
            @endisset
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Montant (FCFA)</label>
              <input type="number" class="form-control" name="montant" id="paiementMontant" min="1"
                @isset($contratFixe) max="{{ $contratFixe->reste() }}" value="{{ min($contratFixe->montant_echeance, $contratFixe->reste()) }}" @endisset
                required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Date du paiement</label>
              <input type="date" class="form-control" name="date_paiement" value="{{ old('date_paiement', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Mode de paiement</label>
              <select class="form-select" name="mode" required>
                @foreach (\App\Models\LocationVente\Paiement::MODES as $mode)
                  <option value="{{ $mode }}" @selected(old('mode') === $mode)>{{ $mode }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Référence (optionnel)</label>
              <input type="text" class="form-control" name="reference" value="{{ old('reference') }}" placeholder="N° de transaction">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea class="form-control" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-success">Enregistrer le paiement</button>
        </div>
      </form>
    </div>
  </div>
</div>

@unless (isset($contratFixe))
  @push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var select = document.getElementById('paiementContrat');
      var montant = document.getElementById('paiementMontant');
      if (!select || !montant) return;
      select.addEventListener('change', function () {
        var option = select.options[select.selectedIndex];
        var reste = parseInt(option.dataset.reste || '0', 10);
        var echeance = parseInt(option.dataset.echeance || '0', 10);
        montant.max = reste || '';
        montant.value = reste ? Math.min(echeance, reste) : '';
      });
    });
  </script>
  @endpush
@endunless
