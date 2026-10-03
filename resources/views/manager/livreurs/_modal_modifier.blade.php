<div class="modal fade" id="modalModifierLivreur{{ $livreur->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form action="{{ route('manager.livreurs.update', $livreur) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title">Modifier {{ $livreur->nomComplet() }} ({{ $livreur->code }})</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          @if ($livreur->kyc_statut === \App\Models\LocationVente\Livreur::KYC_VALIDE)
            <div class="alert alert-warning small">Ce KYC est accepté : modifier l'identité, la photo ou un document le repassera « à vérifier » et le livreur redeviendra inactif.</div>
          @endif
          @include('manager.livreurs._form', ['livreur' => $livreur])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Modifier</button>
        </div>
      </form>
    </div>
  </div>
</div>
