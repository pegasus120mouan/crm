@if ($livreur)
  <a href="{{ route('manager.livreurs.show', $livreur) }}" class="d-flex align-items-center text-body">
    <img src="{{ route('manager.livreurs.photo', $livreur) }}?v={{ optional($livreur->updated_at)->timestamp }}" alt="{{ $livreur->nomComplet() }}" class="rounded-circle me-2 flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;" loading="lazy">
    <span>
      <span class="d-block fw-medium">{{ $livreur->nomComplet() }}</span>
      <small class="text-muted">{{ $livreur->code }}</small>
    </span>
  </a>
@else
  <span class="text-muted">—</span>
@endif
