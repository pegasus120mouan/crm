@push('scripts')
<script>
  document.addEventListener('change', function (event) {
    var input = event.target;
    if (!input.classList || !input.classList.contains('js-input-photo') || !input.files || !input.files[0]) return;
    var apercu = input.closest('.col-md-3').querySelector('.js-apercu-photo');
    if (apercu) apercu.src = URL.createObjectURL(input.files[0]);
  });
</script>
@endpush
