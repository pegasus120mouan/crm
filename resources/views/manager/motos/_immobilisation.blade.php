@php
  $panne = $moto->immobilisationEnCours;
  $saisie = (string) old('_moto') === (string) $moto->id;
@endphp

@if ($moto->relationLoaded('immobilisations') && $moto->immobilisations->isNotEmpty())
  <div class="mb-3">
    @foreach ($moto->immobilisations as $immobilisation)
      <div class="d-flex flex-wrap justify-content-between gap-2 {{ $loop->last ? '' : 'mb-2' }}">
        <span class="badge bg-label-{{ $immobilisation->statut === \App\Models\LocationVente\Immobilisation::STATUT_EN_COURS ? 'warning' : 'secondary' }}">{{ $immobilisation->libelleStatut() }}</span>
        <span>{{ $immobilisation->periodeLibelle() }}</span>
      </div>
      @if ($immobilisation->motif)
        <small class="text-muted d-block mb-2">{{ $immobilisation->motif }}</small>
      @endif
    @endforeach
  </div>
@endif

@if ($panne)
  <p class="mb-3">Les paiements sont suspendus {{ $panne->periodeLibelle() }}. Vous pouvez corriger la date de début, puis indiquer le dernier jour immobilisé pour remettre la moto en service.</p>
  <form action="{{ route('manager.motos.panne.modifier', $moto) }}" method="POST" class="mb-4">
    @csrf
    @method('PUT')
    <input type="hidden" name="_moto" value="{{ $moto->id }}">
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label" for="debut-panne-en-cours-{{ $moto->id }}">Date de début de panne</label>
        <input
          type="date"
          id="debut-panne-en-cours-{{ $moto->id }}"
          class="form-control @error('date_debut') is-invalid @enderror"
          name="date_debut"
          value="{{ $saisie ? old('date_debut', $panne->date_debut->format('Y-m-d')) : $panne->date_debut->format('Y-m-d') }}"
          @if ($panne->contrat?->date_debut) min="{{ $panne->contrat->date_debut->format('Y-m-d') }}" @endif
          @if ($panne->date_fin) max="{{ $panne->date_fin->format('Y-m-d') }}" @endif
          required
        >
        @error('date_debut') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-6">
        <label class="form-label" for="motif-panne-en-cours-{{ $moto->id }}">Motif</label>
        <input
          type="text"
          id="motif-panne-en-cours-{{ $moto->id }}"
          class="form-control"
          name="motif"
          value="{{ $saisie ? old('motif', $panne->motif) : $panne->motif }}"
          maxlength="500"
          placeholder="Ex : moteur, accident, atelier"
        >
      </div>
    </div>
    <button type="submit" class="btn btn-outline-primary">Enregistrer la date de panne</button>
  </form>
  <hr class="my-4">
  <form action="{{ route('manager.motos.panne.cloturer', $moto) }}" method="POST">
    @csrf
    @method('PATCH')
    <input type="hidden" name="_moto" value="{{ $moto->id }}">
    <div class="mb-3">
      <label class="form-label" for="fin-panne-{{ $moto->id }}">Dernier jour immobilisé</label>
      <input type="date" id="fin-panne-{{ $moto->id }}" class="form-control @error('date_fin') is-invalid @enderror" name="date_fin" value="{{ $saisie ? old('date_fin', now()->toDateString()) : now()->toDateString() }}" min="{{ $panne->date_debut->format('Y-m-d') }}" required>
      @error('date_fin') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-success">Remettre en service</button>
  </form>
@elseif ($moto->peutEtreDeclareeEnPanne())
  <p class="mb-3">Une moto en panne est immobilisée. Les échéances de la période ne sont pas dues : elles sont reportées après la fin de la panne. Sans date de fin, les paiements restent arrêtés.</p>
  <form action="{{ route('manager.motos.panne', $moto) }}" method="POST">
    @csrf
    <input type="hidden" name="_moto" value="{{ $moto->id }}">
    <div class="mb-3">
      <label class="form-label" for="statut-panne-{{ $moto->id }}">Statut</label>
      <select id="statut-panne-{{ $moto->id }}" class="form-select @error('statut') is-invalid @enderror" name="statut" required>
        @foreach (\App\Models\LocationVente\Immobilisation::STATUTS as $valeur => $libelle)
          <option value="{{ $valeur }}" @selected(($saisie ? old('statut') : \App\Models\LocationVente\Immobilisation::STATUT_EN_COURS) === $valeur)>{{ $libelle }}</option>
        @endforeach
      </select>
      @error('statut') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label" for="debut-panne-{{ $moto->id }}">Date de début</label>
        <input type="date" id="debut-panne-{{ $moto->id }}" class="form-control @error('date_debut') is-invalid @enderror" name="date_debut" value="{{ $saisie ? old('date_debut', now()->toDateString()) : now()->toDateString() }}" required>
        @error('date_debut') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-6">
        <label class="form-label" for="fin-declaration-{{ $moto->id }}">Date de fin</label>
        <input type="date" id="fin-declaration-{{ $moto->id }}" class="form-control @error('date_fin') is-invalid @enderror" name="date_fin" value="{{ $saisie ? old('date_fin') : '' }}">
        @error('date_fin') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="motif-panne-{{ $moto->id }}">Motif</label>
      <input type="text" id="motif-panne-{{ $moto->id }}" class="form-control" name="motif" value="{{ $saisie ? old('motif') : '' }}" maxlength="500" placeholder="Ex : moteur, accident, atelier">
    </div>
    <button type="submit" class="btn btn-warning">Déclarer en panne</button>
  </form>
@endif
