{{-- Modale de confirmation partagée : les boutons portent data-confirm-url, data-confirm-method, data-confirm-titre, data-confirm-texte. --}}
<div class="modal fade" id="modalConfirmation" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmationTitre">Confirmer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="confirmationTexte"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <form id="confirmationForm" method="POST">
          @csrf
          <input type="hidden" name="_method" id="confirmationMethode" value="DELETE">
          <button type="submit" class="btn btn-danger">Confirmer</button>
        </form>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalConfirmation');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;
      document.getElementById('confirmationTitre').textContent = button.getAttribute('data-confirm-titre') || 'Confirmer';
      document.getElementById('confirmationTexte').textContent = button.getAttribute('data-confirm-texte') || '';
      document.getElementById('confirmationMethode').value = button.getAttribute('data-confirm-method') || 'DELETE';
      document.getElementById('confirmationForm').setAttribute('action', button.getAttribute('data-confirm-url') || '#');
    });
  });
</script>
@endpush
