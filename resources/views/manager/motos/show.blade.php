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
