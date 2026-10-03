@extends('layout.app')

@section('title', $livreur->code.' – '.$livreur->nomComplet())

@section('content')
  @php
    $fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA';
    $types = \App\Models\LocationVente\LivreurDocument::TYPES;
    $obligatoires = \App\Models\LocationVente\LivreurDocument::OBLIGATOIRES;
    $kycValide = $livreur->kyc_statut === \App\Models\LocationVente\Livreur::KYC_VALIDE;
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <a href="{{ route('manager.livreurs.index') }}" class="text-muted"><i class="icon-base bx bx-chevron-left"></i> Livreurs</a>
      <h4 class="mb-0 mt-1">Fiche livreur</h4>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @if ($peutRecevoirUneMoto)
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNouveauContrat" data-livreur-id="{{ $livreur->id }}">
          <i class="icon-base bx bx-cycling me-1"></i> Attribuer une moto
        </button>
      @elseif ($livreur->contratEnCours)
        <a href="{{ route('manager.contrats.show', $livreur->contratEnCours) }}" class="btn btn-outline-primary">
          <i class="icon-base bx bx-file me-1"></i> Contrat en cours
        </a>
      @else
        <span title="Acceptez le KYC pour activer ce livreur et lui attribuer une moto">
          <button type="button" class="btn btn-outline-secondary" disabled>
            <i class="icon-base bx bx-cycling me-1"></i> Attribuer une moto
          </button>
        </span>
      @endif
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalModifierLivreur{{ $livreur->id }}">
        <i class="icon-base bx bx-edit me-1"></i> Modifier
      </button>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-4 mb-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <a href="{{ route('manager.livreurs.photo', $livreur) }}?v={{ optional($livreur->updated_at)->timestamp }}" target="_blank">
            <img src="{{ route('manager.livreurs.photo', $livreur) }}?v={{ optional($livreur->updated_at)->timestamp }}" alt="Photo de {{ $livreur->nomComplet() }}" class="rounded mb-3" style="width: 140px; height: 140px; object-fit: cover;">
          </a>
          <h5 class="mb-1">{{ $livreur->nomComplet() }}</h5>
          <span class="badge bg-label-primary fs-6 mb-2">{{ $livreur->code }}</span>
          <div class="mb-3">
            <span class="badge bg-label-{{ $livreur->couleurKyc() }}">{{ $livreur->libelleKyc() }}</span>
            <span class="badge bg-label-{{ $livreur->statut ? 'success' : 'secondary' }}">{{ $livreur->statut ? 'Actif' : 'Inactif' }}</span>
          </div>
          @unless ($livreur->photo)
            <div class="alert alert-warning small py-2">Aucune photo : ajoutez-la via « Modifier ».</div>
          @endunless
        </div>
        <ul class="list-group list-group-flush small">
          <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Contact</span><span>{{ $livreur->contact }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Contact d'urgence</span><span>{{ $livreur->contact_urgence ?: '—' }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Date de naissance</span><span>{{ optional($livreur->date_naissance)->format('d/m/Y') ?? '—' }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Adresse</span><span class="text-end">{{ $livreur->adresse ?: '—' }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Inscrit le</span><span>{{ optional($livreur->created_at)->format('d/m/Y') }}</span></li>
        </ul>
        @if ($livreur->notes)
          <div class="card-body pt-3"><small class="text-muted" style="white-space: pre-line;">{{ $livreur->notes }}</small></div>
        @endif
      </div>
    </div>

    <div class="col-lg-8 mb-4">
      <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="mb-0">Vérification KYC</h5>
          <div class="d-flex gap-2">
            @if (! $kycValide)
              <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAccepterKyc"><i class="icon-base bx bx-check-circle me-1"></i> Accepter</button>
            @endif
            @if ($livreur->kyc_statut !== \App\Models\LocationVente\Livreur::KYC_REJETE)
              <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRejeterKyc"><i class="icon-base bx bx-x-circle me-1"></i> Rejeter</button>
            @endif
          </div>
        </div>
        <div class="card-body">
          @if ($kycValide)
            <div class="alert alert-success mb-3">
              KYC accepté le {{ $livreur->kyc_verifie_le?->format('d/m/Y à H:i') }}
              @if ($livreur->verificateur) par {{ trim($livreur->verificateur->prenoms.' '.$livreur->verificateur->nom) }} @endif.
            </div>
          @elseif ($livreur->kyc_statut === \App\Models\LocationVente\Livreur::KYC_REJETE)
            <div class="alert alert-danger mb-3">
              KYC rejeté le {{ $livreur->kyc_verifie_le?->format('d/m/Y à H:i') }} : <strong>{{ $livreur->kyc_motif_rejet }}</strong>.
              Corrigez les informations ou remplacez un document : le dossier repassera « à vérifier ».
            </div>
          @elseif ($manquants !== [])
            <div class="alert alert-secondary mb-3">
              Dossier incomplet. Il manque : <strong>{{ implode(', ', $manquants) }}</strong>.
              Vous pouvez compléter le dossier ou décider d'accepter ou de rejeter le KYC.
            </div>
          @else
            <div class="alert alert-warning mb-3">Dossier complet : vérifiez les documents ci-dessous puis acceptez ou rejetez le KYC. Une fois accepté, le livreur devient actif et peut recevoir une moto.</div>
          @endif

          <div class="row g-3">
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <h6 class="mb-2"><i class="icon-base bx bx-id-card me-1"></i> Pièce d'identité</h6>
                <div class="small">
                  <div><span class="text-muted">Type :</span> {{ \App\Models\LocationVente\Livreur::TYPES_PIECE[$livreur->type_piece] ?? '—' }}</div>
                  <div><span class="text-muted">Numéro :</span> {{ $livreur->numero_piece ?: '—' }}</div>
                  <div>
                    <span class="text-muted">Expiration :</span> {{ optional($livreur->date_expiration_piece)->format('d/m/Y') ?? '—' }}
                    @if ($livreur->pieceExpiree()) <span class="badge bg-label-danger ms-1">Expirée</span> @endif
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <h6 class="mb-2"><i class="icon-base bx bx-car me-1"></i> Permis de conduire</h6>
                <div class="small">
                  <div><span class="text-muted">Numéro :</span> {{ $livreur->numero_permis ?: '—' }}</div>
                  <div><span class="text-muted">Catégorie :</span> {{ $livreur->libelleCategoriePermis() ?: '—' }}</div>
                  <div>
                    <span class="text-muted">Expiration :</span> {{ optional($livreur->date_expiration_permis)->format('d/m/Y') ?? '—' }}
                    @if ($livreur->permisExpire()) <span class="badge bg-label-danger ms-1">Expiré</span> @endif
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Documents KYC</h5></div>
        <div class="card-body">
          <div class="row g-3">
            @foreach ($types as $type => $libelle)
              @php($document = $documents->get($type))
              <div class="col-sm-6 col-xl-4">
                <div class="border rounded h-100 d-flex flex-column">
                  <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                    <small class="fw-medium">{{ $libelle }}</small>
                    @if ($document)
                      <span class="badge bg-label-success">Fourni</span>
                    @elseif (in_array($type, $obligatoires, true))
                      <span class="badge bg-label-danger">Manquant</span>
                    @else
                      <span class="badge bg-label-secondary">Optionnel</span>
                    @endif
                  </div>
                  <div class="flex-grow-1 d-flex align-items-center justify-content-center bg-lighter" style="height: 150px; background: #f5f5f9;">
                    @if ($document)
                      <a href="{{ route('manager.livreurs.documents.show', [$livreur, $document]) }}" target="_blank" class="d-block w-100 h-100 text-center">
                        @if ($document->estImage())
                          <img src="{{ route('manager.livreurs.documents.show', [$livreur, $document]) }}" alt="{{ $libelle }}" style="max-width: 100%; max-height: 150px; object-fit: contain;" loading="lazy">
                        @else
                          <span class="d-flex flex-column align-items-center justify-content-center h-100 text-danger">
                            <i class="icon-base bx bx-file" style="font-size: 3rem;"></i>
                            <small>PDF – ouvrir</small>
                          </span>
                        @endif
                      </a>
                    @else
                      <i class="icon-base bx bx-file text-muted" style="font-size: 2.5rem;"></i>
                    @endif
                  </div>
                  <div class="p-2 border-top">
                    <form action="{{ route('manager.livreurs.documents.store', $livreur) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-1">
                      @csrf
                      <input type="hidden" name="type" value="{{ $type }}">
                      <input type="file" name="fichier" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                      <button type="submit" class="btn btn-sm btn-primary" title="{{ $document ? 'Remplacer' : 'Envoyer' }}"><i class="icon-base bx bx-upload"></i></button>
                      @if ($document)
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-danger"
                          title="Supprimer"
                          data-bs-toggle="modal"
                          data-bs-target="#modalConfirmation"
                          data-confirm-titre="Supprimer un document"
                          data-confirm-texte="Supprimer « {{ $libelle }} » du dossier de {{ $livreur->nomComplet() }} ?"
                          data-confirm-url="{{ route('manager.livreurs.documents.destroy', [$livreur, $document]) }}">
                          <i class="icon-base bx bx-trash"></i>
                        </button>
                      @endif
                    </form>
                    @if ($document)
                      <small class="text-muted d-block mt-1 text-truncate" title="{{ $document->nom_original }}">Ajouté le {{ $document->updated_at?->format('d/m/Y') }} · {{ $document->nom_original }}</small>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">Contrats de location-vente</h5></div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Moto</th>
            <th>Début</th>
            <th class="text-end">À rembourser</th>
            <th class="text-end">Payé</th>
            <th class="text-end">Reste</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($livreur->contrats as $contrat)
            <tr>
              <td><a href="{{ route('manager.contrats.show', $contrat) }}" class="fw-medium">{{ $contrat->reference }}</a></td>
              <td>{{ $contrat->moto?->libelle() }}</td>
              <td>{{ $contrat->date_debut->format('d/m/Y') }}</td>
              <td class="text-end">{{ $fcfa($contrat->prix_total) }}</td>
              <td class="text-end">{{ $fcfa($contrat->montantPaye()) }}</td>
              <td class="text-end">{{ $fcfa($contrat->reste()) }}</td>
              <td><span class="badge bg-label-{{ $contrat->couleurStatut() }}">{{ $contrat->retard() > 0 ? 'En retard' : $contrat->libelleStatut() }}</span></td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-4">
                Aucun contrat.
                @if ($kycValide)
                  <a href="{{ route('manager.contrats.index') }}">Créer un contrat</a>
                @endif
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @include('manager.livreurs._modal_modifier', ['livreur' => $livreur])

  @unless ($kycValide)
    <div class="modal fade" id="modalAccepterKyc" tabindex="-1" aria-labelledby="modalAccepterKycTitre" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('manager.livreurs.kyc', $livreur) }}" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="decision" value="valider">
            <div class="modal-header border-bottom">
              <h5 class="modal-title d-flex align-items-center" id="modalAccepterKycTitre">
                <span class="avatar-initial rounded bg-label-success p-2 me-2"><i class="icon-base bx bx-user-check icon-md"></i></span>
                Accepter le KYC
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body pt-4">
              <div class="d-flex align-items-center mb-4">
                <img src="{{ route('manager.livreurs.photo', $livreur) }}?v={{ optional($livreur->updated_at)->timestamp }}" alt="" class="rounded-circle me-3" style="width: 56px; height: 56px; object-fit: cover;">
                <div>
                  <h6 class="mb-0">{{ $livreur->nomComplet() }}</h6>
                  <small class="text-muted">{{ $livreur->code }} · {{ $livreur->contact }}</small>
                </div>
              </div>

              @if ($manquants !== [])
                <div class="alert alert-warning d-flex mb-4" role="alert">
                  <i class="icon-base bx bx-error me-2 mt-1"></i>
                  <div>
                    <h6 class="alert-heading mb-1">Dossier incomplet</h6>
                    <p class="mb-1">Les éléments suivants n'ont pas été fournis :</p>
                    <ul class="mb-0 ps-3">
                      @foreach ($manquants as $manquant)
                        <li>{{ $manquant }}</li>
                      @endforeach
                    </ul>
                  </div>
                </div>
              @else
                <div class="alert alert-success d-flex mb-4" role="alert">
                  <i class="icon-base bx bx-check-shield me-2 mt-1"></i>
                  <div>Toutes les pièces obligatoires ont été fournies.</div>
                </div>
              @endif

              <p class="mb-2 fw-medium">En acceptant ce KYC :</p>
              <ul class="list-unstyled mb-0 small">
                <li class="mb-2"><i class="icon-base bx bx-check text-success me-1"></i> le livreur passe au statut <strong>Actif</strong> ;</li>
                <li class="mb-2"><i class="icon-base bx bx-check text-success me-1"></i> une moto pourra lui être attribuée en location-vente ;</li>
                <li><i class="icon-base bx bx-info-circle text-muted me-1"></i> votre nom et la date de décision seront enregistrés.</li>
              </ul>
            </div>
            <div class="modal-footer border-top pt-3">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-success">
                <i class="icon-base bx bx-check-circle me-1"></i> {{ $manquants !== [] ? 'Accepter quand même' : 'Accepter le KYC' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endunless

  <div class="modal fade" id="modalRejeterKyc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('manager.livreurs.kyc', $livreur) }}" method="POST">
          @csrf
          @method('PATCH')
          <input type="hidden" name="decision" value="rejeter">
          <div class="modal-header border-bottom">
            <h5 class="modal-title d-flex align-items-center">
              <span class="avatar-initial rounded bg-label-danger p-2 me-2"><i class="icon-base bx bx-user-x icon-md"></i></span>
              Rejeter le KYC
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body pt-4">
            <div class="d-flex align-items-center mb-4">
              <img src="{{ route('manager.livreurs.photo', $livreur) }}?v={{ optional($livreur->updated_at)->timestamp }}" alt="" class="rounded-circle me-3" style="width: 56px; height: 56px; object-fit: cover;">
              <div>
                <h6 class="mb-0">{{ $livreur->nomComplet() }}</h6>
                <small class="text-muted">{{ $livreur->code }} · {{ $livreur->contact }}</small>
              </div>
            </div>
            <label class="form-label" for="motifRejetKyc">Motif du rejet <span class="text-danger">*</span></label>
            <textarea class="form-control mb-3" id="motifRejetKyc" name="motif" rows="3" maxlength="255" placeholder="Ex : pièce illisible, permis expiré, photo non conforme..." required></textarea>
            <div class="alert alert-danger d-flex mb-0" role="alert">
              <i class="icon-base bx bx-error-circle me-2 mt-1"></i>
              <div>Le livreur passera au statut <strong>Inactif</strong> : aucune moto ne pourra lui être attribuée tant que son dossier n'est pas accepté.</div>
            </div>
          </div>
          <div class="modal-footer border-top pt-3">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-danger"><i class="icon-base bx bx-x-circle me-1"></i> Rejeter le KYC</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @include('manager._confirmation')
  @include('manager.livreurs._script_photo')
  @if ($peutRecevoirUneMoto)
    @include('manager.contrats._modal_nouveau')
  @endif
@endsection
