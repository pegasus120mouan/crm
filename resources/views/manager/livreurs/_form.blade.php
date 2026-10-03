@php
  $suffixe = $livreur?->id ?? 'nouveau';
  $docsExistants = $livreur ? $livreur->documents->pluck('type')->all() : [];
@endphp

<h6 class="text-uppercase text-muted small mb-3">Identité</h6>
<div class="row g-3 mb-4">
  <div class="col-md-3 text-center">
    <img src="{{ $livreur ? route('manager.livreurs.photo', $livreur).'?v='.optional($livreur->updated_at)->timestamp : asset('assets/img/avatars/1.png') }}"
      class="rounded mb-2 js-apercu-photo" style="width: 110px; height: 110px; object-fit: cover;" alt="Photo">
    <input type="file" class="form-control form-control-sm js-input-photo" name="photo" accept="image/jpeg,image/png,image/webp" id="photo-{{ $suffixe }}">
    <small class="text-muted">Photo (JPG, PNG, 4 Mo max)</small>
  </div>
  <div class="col-md-9">
    <div class="row g-3">
      @if ($livreur)
        <div class="col-md-4">
          <label class="form-label">Code</label>
          <input type="text" class="form-control" value="{{ $livreur->code }}" disabled>
        </div>
      @endif
      <div class="col-md-{{ $livreur ? 4 : 6 }}">
        <label class="form-label">Nom</label>
        <input type="text" class="form-control" name="nom" value="{{ $livreur->nom ?? old('nom') }}" required>
      </div>
      <div class="col-md-{{ $livreur ? 4 : 6 }}">
        <label class="form-label">Prénoms</label>
        <input type="text" class="form-control" name="prenoms" value="{{ $livreur->prenoms ?? old('prenoms') }}" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Date de naissance</label>
        <input type="date" class="form-control" name="date_naissance" value="{{ optional($livreur?->date_naissance)->format('Y-m-d') ?? old('date_naissance') }}" max="{{ now()->subDay()->toDateString() }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Contact</label>
        <input type="text" class="form-control" name="contact" value="{{ $livreur->contact ?? old('contact') }}" maxlength="30" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Contact d'urgence</label>
        <input type="text" class="form-control" name="contact_urgence" value="{{ $livreur->contact_urgence ?? old('contact_urgence') }}" maxlength="30">
      </div>
      <div class="col-md-8">
        <label class="form-label">Adresse</label>
        <input type="text" class="form-control" name="adresse" value="{{ $livreur->adresse ?? old('adresse') }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Statut</label>
        @if ($livreur && $livreur->kycAccepte())
          <select class="form-select" name="statut">
            <option value="0" @selected(! $livreur->statut)>Inactif</option>
            <option value="1" @selected($livreur->statut)>Actif</option>
          </select>
        @else
          <input type="hidden" name="statut" value="0">
          <input type="text" class="form-control" value="Inactif" readonly>
          <small class="text-muted">Devient actif quand le KYC est accepté.</small>
        @endif
      </div>
    </div>
  </div>
</div>

<h6 class="text-uppercase text-muted small mb-3">KYC – pièce d'identité</h6>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <label class="form-label">Type de pièce</label>
    <select class="form-select" name="type_piece">
      <option value="">--</option>
      @foreach (\App\Models\LocationVente\Livreur::TYPES_PIECE as $valeur => $libelle)
        <option value="{{ $valeur }}" @selected(($livreur->type_piece ?? old('type_piece')) === $valeur)>{{ $libelle }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Numéro de la pièce</label>
    <input type="text" class="form-control" name="numero_piece" value="{{ $livreur->numero_piece ?? old('numero_piece') }}" maxlength="50">
  </div>
  <div class="col-md-4">
    <label class="form-label">Date d'expiration</label>
    <input type="date" class="form-control" name="date_expiration_piece" value="{{ optional($livreur?->date_expiration_piece)->format('Y-m-d') ?? old('date_expiration_piece') }}">
  </div>
  @foreach (['piece_recto', 'piece_verso'] as $type)
    <div class="col-md-6">
      <label class="form-label">{{ \App\Models\LocationVente\LivreurDocument::TYPES[$type] }} @if (in_array($type, $docsExistants, true))<span class="badge bg-label-success ms-1">déjà fourni</span>@endif</label>
      <input type="file" class="form-control" name="documents[{{ $type }}]" accept="image/jpeg,image/png,image/webp,application/pdf">
    </div>
  @endforeach
</div>

<h6 class="text-uppercase text-muted small mb-3">KYC – permis de conduire</h6>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <label class="form-label">Numéro du permis</label>
    <input type="text" class="form-control" name="numero_permis" value="{{ $livreur->numero_permis ?? old('numero_permis') }}" maxlength="50">
  </div>
  <div class="col-md-4">
    <label class="form-label">Catégorie</label>
    <select class="form-select" name="categorie_permis">
      <option value="">--</option>
      @foreach (\App\Models\LocationVente\Livreur::CATEGORIES_PERMIS as $categorie => $libelleCategorie)
        <option value="{{ $categorie }}" @selected(($livreur->categorie_permis ?? old('categorie_permis')) === $categorie)>{{ $libelleCategorie }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Date d'expiration</label>
    <input type="date" class="form-control" name="date_expiration_permis" value="{{ optional($livreur?->date_expiration_permis)->format('Y-m-d') ?? old('date_expiration_permis') }}">
  </div>
  @foreach (['permis_recto', 'permis_verso'] as $type)
    <div class="col-md-6">
      <label class="form-label">{{ \App\Models\LocationVente\LivreurDocument::TYPES[$type] }} @if (in_array($type, $docsExistants, true))<span class="badge bg-label-success ms-1">déjà fourni</span>@endif</label>
      <input type="file" class="form-control" name="documents[{{ $type }}]" accept="image/jpeg,image/png,image/webp,application/pdf">
    </div>
  @endforeach
</div>

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">{{ \App\Models\LocationVente\LivreurDocument::TYPES['justificatif_domicile'] }} <small class="text-muted">(optionnel)</small> @if (in_array('justificatif_domicile', $docsExistants, true))<span class="badge bg-label-success ms-1">déjà fourni</span>@endif</label>
    <input type="file" class="form-control" name="documents[justificatif_domicile]" accept="image/jpeg,image/png,image/webp,application/pdf">
  </div>
  <div class="col-md-6">
    <label class="form-label">Notes</label>
    <textarea class="form-control" name="notes" rows="1">{{ $livreur->notes ?? old('notes') }}</textarea>
  </div>
  <div class="col-12">
    <small class="text-muted">Documents acceptés : JPG, PNG, WEBP ou PDF, 5 Mo max chacun. Un nouveau fichier remplace l'ancien.</small>
  </div>
</div>
