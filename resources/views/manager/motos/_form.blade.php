@php
  $statutManuel = ! $moto || in_array($moto->statut, \App\Models\LocationVente\Moto::STATUTS_MANUELS, true);
  $photosExistantes = $moto ? $moto->photosParFace() : [];
@endphp
<div class="row g-3">
  <div class="col-12">
    <label class="form-label mb-1">Photos de la moto {!! $moto ? '' : '<span class="text-danger">*</span>' !!}</label>
    <p class="text-muted small mb-2">
      {{ $moto ? 'Choisissez une nouvelle photo uniquement pour la face à remplacer.' : 'Les 4 faces sont obligatoires : côté droit, côté gauche, avant et arrière.' }}
      JPG, PNG ou WEBP, 5 Mo maximum par photo.
    </p>
    <div class="row g-2">
      @foreach (\App\Models\LocationVente\MotoPhoto::FACES as $face => $libelle)
        @php($existante = $photosExistantes[$face] ?? null)
        <div class="col-6 col-md-3">
          <label class="d-block border rounded p-2 text-center h-100 js-bloc-photo-moto" style="cursor: pointer;">
            <div class="rounded bg-lighter d-flex align-items-center justify-content-center overflow-hidden mb-2" style="height: 90px;">
              <img src="{{ $existante ? route('manager.motos.photo', [$moto, $face]).'?v='.$existante->updated_at?->timestamp : '' }}"
                class="w-100 h-100 js-apercu-photo-moto {{ $existante ? '' : 'd-none' }}" style="object-fit: cover;" alt="{{ $libelle }}">
              <i class="icon-base bx bx-camera icon-lg text-muted js-icone-photo-moto {{ $existante ? 'd-none' : '' }}"></i>
            </div>
            <span class="small fw-medium d-block">{{ $libelle }}</span>
            <span class="small {{ $existante ? 'text-success' : 'text-muted' }} js-etat-photo-moto">{{ $existante ? 'Enregistrée · toucher pour remplacer' : 'Prendre ou choisir' }}</span>
            <input type="file" class="d-none js-input-photo-moto" name="photos[{{ $face }}]" accept="image/jpeg,image/png,image/webp" data-libelle="{{ $libelle }}" @if (! $moto) data-obligatoire="1" @endif>
          </label>
        </div>
      @endforeach
    </div>
  </div>
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
  <div class="col-md-4">
    <label class="form-label">Carburant</label>
    <select class="form-select" name="carburant">
      <option value="">--</option>
      @foreach (\App\Models\LocationVente\Moto::CARBURANTS as $valeur => $libelle)
        <option value="{{ $valeur }}" @selected(($moto->carburant ?? old('carburant')) === $valeur)>{{ $libelle }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Cylindrée (cc)</label>
    <input type="number" class="form-control" name="cylindree" value="{{ $moto->cylindree ?? old('cylindree') }}" min="1" max="3000" placeholder="Ex : 125">
  </div>
  <div class="col-md-4">
    <label class="form-label">Date d'acquisition</label>
    <input type="date" class="form-control" name="date_acquisition" value="{{ optional($moto?->date_acquisition)->format('Y-m-d') ?? old('date_acquisition') }}">
    <small class="text-muted">Entrée de la moto dans le parc — distincte de la date de début du contrat.</small>
  </div>
  <div class="col-md-4">
    <label class="form-label">Prix d'acquisition (FCFA)</label>
    <input
      type="number"
      class="form-control"
      name="prix_achat"
      value="{{ old('prix_achat', $moto?->prix_achat ?? 0) }}"
      min="0"
      step="1"
      placeholder="0">
    <small class="text-muted">Coût d'achat de la moto pour le parc. Par défaut 0.</small>
  </div>
</div>
