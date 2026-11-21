@php
  $chezGerant = $moto->estChezGerant();
  $peutConfier = $moto->peutEtreConfieeAUnGerant();
@endphp

@if ($chezGerant)
  <p class="mb-3">
    Cette moto est <strong>confiée à un gérant</strong> :
    <a href="{{ route('manager.gerants.show', $moto->gerant) }}">{{ $moto->gerant->nomComplet() }}</a>
    ({{ $moto->gerant->code }}). Elle n’est pas disponible pour un contrat location-vente, mais les paiements du gérant peuvent être enregistrés ici.
  </p>
  <form action="{{ route('manager.motos.gerant.paiements', $moto) }}" method="POST" class="mb-4">
    @csrf
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="montant-gerant-{{ $moto->id }}">Montant</label>
        <input type="number" id="montant-gerant-{{ $moto->id }}" name="montant" class="form-control @error('montant') is-invalid @enderror" min="1" value="{{ old('montant') }}" required>
        @error('montant') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-4">
        <label class="form-label" for="date-gerant-{{ $moto->id }}">Date</label>
        <input type="date" id="date-gerant-{{ $moto->id }}" name="date_paiement" class="form-control @error('date_paiement') is-invalid @enderror" value="{{ old('date_paiement', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
        @error('date_paiement') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-4">
        <label class="form-label" for="mode-gerant-{{ $moto->id }}">Mode</label>
        <select id="mode-gerant-{{ $moto->id }}" name="mode" class="form-select @error('mode') is-invalid @enderror" required>
          @foreach (\App\Models\LocationVente\Paiement::MODES as $mode)
            <option value="{{ $mode }}" @selected(old('mode') === $mode)>{{ $mode }}</option>
          @endforeach
        </select>
        @error('mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-6">
        <label class="form-label" for="ref-gerant-{{ $moto->id }}">Référence</label>
        <input type="text" id="ref-gerant-{{ $moto->id }}" name="reference" class="form-control" value="{{ old('reference') }}" maxlength="100">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="notes-gerant-{{ $moto->id }}">Notes</label>
        <input type="text" id="notes-gerant-{{ $moto->id }}" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="1000">
      </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3">Enregistrer un paiement</button>
  </form>

  @if ($moto->paiementsGerant->isNotEmpty())
    <div class="table-responsive mb-4">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Date</th>
            <th>Mode</th>
            <th class="text-end">Montant</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($moto->paiementsGerant as $paiement)
            <tr>
              <td>{{ $paiement->date_paiement->format('d/m/Y') }}</td>
              <td>{{ $paiement->mode }}{{ $paiement->reference ? ' · '.$paiement->reference : '' }}</td>
              <td class="text-end">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <form action="{{ route('manager.motos.gerant.restituer', $moto) }}" method="POST" onsubmit="return confirm('Restituer cette moto au parc disponible ?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-success">Restituer la moto</button>
  </form>
@elseif ($peutConfier)
  <p class="mb-3">Confier cette moto à un gérant la retire des disponibles. Aucun contrat location-vente ne sera créé : seuls les paiements du gérant seront suivis ici.</p>
  @if (($gerantsActifs ?? collect())->isEmpty())
    <p class="text-muted mb-0">Aucun gérant actif. <a href="{{ route('manager.gerants.index') }}">Créer un gérant</a>.</p>
  @else
    <form action="{{ route('manager.motos.gerant.confier', $moto) }}" method="POST">
      @csrf
      <div class="mb-3">
        <label class="form-label" for="gerant-moto-{{ $moto->id }}">Gérant</label>
        <select id="gerant-moto-{{ $moto->id }}" name="gerant_id" class="form-select @error('gerant_id') is-invalid @enderror" required>
          <option value="">Choisir…</option>
          @foreach ($gerantsActifs as $gerant)
            <option value="{{ $gerant->id }}" @selected((string) old('gerant_id') === (string) $gerant->id)>
              {{ $gerant->nomComplet() }} ({{ $gerant->code }})
            </option>
          @endforeach
        </select>
        @error('gerant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
      <button type="submit" class="btn btn-info text-white">Confier à un gérant</button>
    </form>
  @endif
@endif
