@extends('layout.app')

@php
  $photos = $moto->photosParFace();
  $plaque = $moto->numeroPlaque();
  $papiers = $moto->documentsParType();
  $documentsAffiches = [
      \App\Models\LocationVente\MotoDocument::TYPE_ASSURANCE,
      \App\Models\LocationVente\MotoDocument::TYPE_VIGNETTE,
      \App\Models\LocationVente\MotoDocument::TYPE_CARTE_GRISE,
  ];
@endphp

@section('title', 'Infos engin')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h4 class="mb-0">Infos engin</h4>
    <nav class="small">
      <a href="{{ route('manager.dashboard') }}">Accueil</a>
      <span class="text-muted"> / Infos engin</span>
    </nav>
  </div>

  @php
    $fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA';
    $libelleResultat = match ($bilan['sens']) {
        'gain' => 'Gain',
        'perte' => 'Perte',
        default => 'Équilibre',
    };
    $couleurResultat = match ($bilan['sens']) {
        'gain' => 'success',
        'perte' => 'danger',
        default => 'secondary',
    };
  @endphp

  <div class="card mb-4" id="bilan">
    <div class="card-header"><h5 class="mb-0">Bilan</h5></div>
    <div class="card-body">
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="border rounded p-3 h-100">
            <span class="d-block text-muted mb-1">Prix d'acquisition</span>
            <div class="fw-medium">{{ $fcfa($bilan['prix_achat']) }}</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="border rounded p-3 h-100">
            <span class="d-block text-muted mb-1">Dépenses</span>
            <div class="fw-medium">{{ $fcfa($bilan['total_depenses']) }}</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="border rounded p-3 h-100">
            <span class="d-block text-muted mb-1">Paiements encaissés</span>
            <div class="fw-medium">{{ $fcfa($bilan['total_paiements']) }}</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="border rounded p-3 h-100">
            <span class="d-block text-muted mb-1">Ratio</span>
            <div class="fw-medium text-{{ $couleurResultat }}">{{ $libelleResultat }} {{ $fcfa(abs($bilan['resultat'])) }}</div>
            @if ($bilan['ratio'] !== null)
              <small class="text-muted">{{ $bilan['ratio'] }} % des dépenses couvertes</small>
            @endif
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-6">
          <h6 class="mb-3">Dépenses</h6>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Libellé</th>
                  <th>Livreur</th>
                  <th class="text-end">Montant</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($moto->depenses as $depense)
                  <tr>
                    <td>{{ $depense->date_depense->format('d/m/Y') }}</td>
                    <td>{{ $depense->libelle }}</td>
                    <td>{{ $depense->contrat?->livreur?->nomComplet() ?: '—' }}</td>
                    <td class="text-end">{{ $fcfa($depense->montant) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">Aucune dépense</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="col-lg-6">
          <h6 class="mb-3">Paiements</h6>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Contrat</th>
                  <th>Livreur</th>
                  <th class="text-end">Montant</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($moto->paiements as $paiement)
                  <tr>
                    <td>{{ $paiement->date_paiement->format('d/m/Y') }}</td>
                    <td>
                      @if ($paiement->contrat)
                        <a href="{{ route('manager.contrats.show', $paiement->contrat) }}">{{ $paiement->contrat->reference }}</a>
                      @else
                        —
                      @endif
                    </td>
                    <td>{{ $paiement->contrat?->livreur?->nomComplet() ?: '—' }}</td>
                    <td class="text-end">{{ $fcfa($paiement->montant) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">Aucun paiement</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-body">
          <h6 class="text-center text-primary mb-4">Engin</h6>
          <div class="d-flex flex-wrap justify-content-center gap-4">
            @foreach (\App\Models\LocationVente\MotoPhoto::FACES as $face => $libelle)
              @php($photo = $photos[$face] ?? null)
              <div class="text-center" style="width: 110px;">
                @if ($photo)
                  <a href="{{ route('manager.motos.photo', [$moto, $face]) }}" target="_blank" class="d-block">
                    <img src="{{ route('manager.motos.photo', [$moto, $face]) }}?v={{ $photo->updated_at?->timestamp }}" alt="{{ $libelle }}" class="rounded border mb-2" style="width: 96px; height: 72px; object-fit: cover;" loading="lazy">
                  </a>
                @else
                  <div class="rounded border bg-lighter d-flex align-items-center justify-content-center mb-2 mx-auto" style="width: 96px; height: 72px;">
                    <i class="icon-base bx bx-image text-muted"></i>
                  </div>
                @endif
                <a href="{{ $photo ? route('manager.motos.photo', [$moto, $face]) : '#' }}" @if ($photo) target="_blank" @endif class="small">{{ $libelle }}</a>
              </div>
            @endforeach
          </div>
          <hr class="my-4">
          <div class="d-flex flex-wrap justify-content-center gap-4">
            @foreach ($documentsAffiches as $type)
              @php($document = $papiers[$type] ?? null)
              <div class="text-center" style="width: 160px;">
                @if ($document?->chemin && $document->estImage())
                  <a href="{{ route('manager.documents.fichier', $document) }}" target="_blank" class="d-block">
                    <img src="{{ route('manager.documents.fichier', $document) }}?v={{ $document->updated_at?->timestamp }}" alt="{{ $document->libelle() }}" class="rounded border mb-2" style="width: 150px; height: 110px; object-fit: cover;" loading="lazy">
                  </a>
                @elseif ($document?->chemin)
                  <a href="{{ route('manager.documents.fichier', $document) }}" target="_blank" class="d-block rounded border bg-lighter d-flex align-items-center justify-content-center mb-2 mx-auto text-decoration-none" style="width: 150px; height: 110px;">
                    <i class="icon-base bx bxs-file-pdf icon-lg text-danger"></i>
                  </a>
                @else
                  <div class="rounded border bg-lighter d-flex align-items-center justify-content-center mb-2 mx-auto" style="width: 150px; height: 110px;">
                    <i class="icon-base bx bx-image text-muted"></i>
                  </div>
                @endif
                <div class="small fw-medium">{{ \App\Models\LocationVente\MotoDocument::TYPES[$type] }}</div>
                @if ($type === \App\Models\LocationVente\MotoDocument::TYPE_CARTE_GRISE)
                  <div class="small text-muted">{{ $document?->numero ?: 'N° à renseigner' }}</div>
                @elseif ($document?->date_debut && $document?->date_fin)
                  <div class="small text-muted">{{ $document->date_debut->format('d-m-y') }} → {{ $document->date_fin->format('d-m-y') }}</div>
                @else
                  <div class="small text-muted">Dates à renseigner</div>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">Modifier les images de l'engin</h6></div>
        <div class="card-body">
          <form action="{{ route('manager.motos.photos', $moto) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @foreach (\App\Models\LocationVente\MotoPhoto::FACES as $face => $libelle)
              <div class="mb-2">
                <input type="file" class="form-control" name="photos[{{ $face }}]" accept="image/jpeg,image/png,image/webp" aria-label="{{ $libelle }}">
              </div>
            @endforeach
            <button type="submit" class="btn btn-info text-white mt-2">Modifier les images de la moto</button>
          </form>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">Assurance, vignette et carte grise</h6></div>
        <div class="card-body">
          <form action="{{ route('manager.motos.papiers', $moto) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @foreach ([
                \App\Models\LocationVente\MotoDocument::TYPE_ASSURANCE => 'Assurance',
                \App\Models\LocationVente\MotoDocument::TYPE_VIGNETTE => 'Vignette',
            ] as $type => $libelle)
              @php($document = $papiers[$type] ?? null)
              <div class="fw-medium mb-2">{{ $libelle }}</div>
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label small mb-1" for="date-debut-{{ $type }}">Date début</label>
                  <input type="date" id="date-debut-{{ $type }}" class="form-control" name="documents[{{ $type }}][date_debut]" value="{{ old('documents.'.$type.'.date_debut', $document?->date_debut?->format('Y-m-d')) }}">
                </div>
                <div class="col-6">
                  <label class="form-label small mb-1" for="date-fin-{{ $type }}">Date fin</label>
                  <input type="date" id="date-fin-{{ $type }}" class="form-control" name="documents[{{ $type }}][date_fin]" value="{{ old('documents.'.$type.'.date_fin', $document?->date_fin?->format('Y-m-d')) }}">
                </div>
                <div class="col-12">
                  <label class="form-label small mb-1" for="fichier-{{ $type }}">Image</label>
                  <input type="file" id="fichier-{{ $type }}" class="form-control" name="documents[{{ $type }}][fichier]" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Image {{ $libelle }}">
                  @if ($document?->chemin)
                    <div class="form-text">Image déjà enregistrée. Un nouveau fichier la remplace.</div>
                  @endif
                </div>
              </div>
            @endforeach

            @php($carteGrise = $papiers[\App\Models\LocationVente\MotoDocument::TYPE_CARTE_GRISE] ?? null)
            <div class="fw-medium mb-2">Carte grise</div>
            <div class="mb-2">
              <label class="form-label small mb-1" for="numero-carte-grise">N° de plaque</label>
              <input type="text" id="numero-carte-grise" class="form-control" name="documents[carte_grise][numero]" value="{{ old('documents.carte_grise.numero', $carteGrise?->numero) }}" maxlength="50">
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1" for="fichier-carte-grise">Image</label>
              <input type="file" id="fichier-carte-grise" class="form-control" name="documents[carte_grise][fichier]" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Image carte grise">
              @if ($carteGrise?->chemin)
                <div class="form-text">Image déjà enregistrée. Un nouveau fichier la remplace.</div>
              @endif
            </div>
            <button type="submit" class="btn btn-info text-white">Enregistrer les documents</button>
          </form>
        </div>
      </div>

      @if ($moto->peutEtreConfieeAUnGerant() || $moto->estChezGerant())
        <div class="card mb-4">
          <div class="card-header">
            <h6 class="mb-0">Gérant</h6>
          </div>
          <div class="card-body">
            @include('manager.motos._gerant', ['moto' => $moto, 'gerantsActifs' => $gerantsActifs ?? collect()])
          </div>
        </div>
      @endif

      @if ($moto->peutEtreDeclareeEnPanne() || $moto->immobilisations->isNotEmpty())
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Immobilisation</h6>
            <a href="{{ route('manager.depenses.index', ['moto' => $moto->id]) }}" class="btn btn-sm btn-outline-primary">Dépense</a>
          </div>
          <div class="card-body">
            @include('manager.motos._immobilisation', ['moto' => $moto])
          </div>
        </div>
      @endif

      <div class="card">
        <div class="px-3 py-2 text-white" style="background: #6c757d;">
          <div class="fw-bold">{{ $moto->numero_chassis ?: '—' }}</div>
          <div class="small">{{ $moto->libelleStatutDocuments() }}</div>
        </div>
        <div class="card-body p-0">
          <h6 class="text-center border-bottom py-3 mb-0">Informations</h6>
          <table class="table table-striped mb-0">
            <tbody>
              <tr>
                <th class="w-50">Type</th>
                <td>Moto</td>
              </tr>
              <tr>
                <th>Marque</th>
                <td>{{ trim($moto->marque.' '.$moto->modele) ?: '—' }}</td>
              </tr>
              <tr>
                <th>Année</th>
                <td>{{ $moto->annee ?: '—' }}</td>
              </tr>
              <tr>
                <th>Plaque</th>
                <td>{{ $plaque ?: 'En cours !!' }}</td>
              </tr>
              <tr>
                <th>Couleur</th>
                <td>{{ $moto->couleur ?: '—' }}</td>
              </tr>
              <tr>
                <th>Date ajout</th>
                <td>{{ ($moto->date_acquisition ?? $moto->created_at)?->format('Y-m-d') ?: '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
