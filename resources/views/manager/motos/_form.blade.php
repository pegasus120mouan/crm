@php($statutManuel = ! $moto || in_array($moto->statut, \App\Models\LocationVente\Moto::STATUTS_MANUELS, true))
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Marque</label>
    <input type="text" class="form-control" name="marque" value="{{ $moto->marque ?? old('marque') }}" placeholder="Ex : Haojue, TVS, Yamaha" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Modèle</label>
    <input type="text" class="form-control" name="modele" value="{{ $moto->modele ?? old('modele') }}">
  </div>
  <div class="col-md-6">
    <label class="form-label">Immatriculation</label>
    <input type="text" class="form-control text-uppercase" name="immatriculation" value="{{ $moto->immatriculation ?? old('immatriculation') }}" placeholder="Laisser vide si en cours">
    <small class="text-muted">Moto neuve : laissez vide, la moto apparaîtra « Immat. en cours ».</small>
  </div>
  <div class="col-md-6">
    <label class="form-label">N° de châssis</label>
    <input type="text" class="form-control text-uppercase" name="numero_chassis" value="{{ $moto->numero_chassis ?? old('numero_chassis') }}">
    <small class="text-muted">Obligatoire si l'immatriculation est en cours.</small>
  </div>
  <div class="col-md-4">
    <label class="form-label">Couleur</label>
    <input type="text" class="form-control" name="couleur" value="{{ $moto->couleur ?? old('couleur') }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Année</label>
    <input type="number" class="form-control" name="annee" value="{{ $moto->annee ?? old('annee') }}" min="1990" max="{{ now()->year + 1 }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Statut</label>
    @if ($statutManuel)
      <select class="form-select" name="statut">
        @foreach (\App\Models\LocationVente\Moto::STATUTS_MANUELS as $valeur)
          <option value="{{ $valeur }}" @selected(($moto->statut ?? 'disponible') === $valeur)>{{ \App\Models\LocationVente\Moto::STATUTS[$valeur] }}</option>
        @endforeach
      </select>
    @else
      <input type="text" class="form-control" value="{{ $moto->libelleStatut() }}" disabled>
      <small class="text-muted">Géré par le contrat</small>
    @endif
  </div>
  <div class="col-md-6">
    <label class="form-label">Prix d'achat (FCFA)</label>
    <input type="number" class="form-control" name="prix_achat" value="{{ $moto->prix_achat ?? old('prix_achat') }}" min="0">
  </div>
  <div class="col-md-6">
    <label class="form-label">Date d'acquisition</label>
    <input type="date" class="form-control" name="date_acquisition" value="{{ optional($moto?->date_acquisition)->format('Y-m-d') ?? old('date_acquisition') }}">
  </div>
  <div class="col-12">
    <label class="form-label">Notes</label>
    <textarea class="form-control" name="notes" rows="2">{{ $moto->notes ?? old('notes') }}</textarea>
  </div>
</div>
