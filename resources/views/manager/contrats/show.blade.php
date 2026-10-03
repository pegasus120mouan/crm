@extends('layout.app')

@section('title', 'Contrat '.$contrat->reference)

@section('content')
  @php
    $fcfa = fn ($montant) => number_format((int) $montant, 0, ',', ' ').' FCFA';
    $enCours = $contrat->statut === \App\Models\LocationVente\Contrat::STATUT_EN_COURS;
    $libellesEcheance = [
      'payee' => ['Payée', 'success'],
      'partielle' => ['Partiellement payée', 'info'],
      'en_retard' => ['En retard', 'danger'],
      'a_venir' => ['À venir', 'secondary'],
      'annulee' => ['Annulée', 'secondary'],
    ];
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <a href="{{ route('manager.contrats.index') }}" class="text-muted"><i class="icon-base bx bx-chevron-left"></i> Contrats</a>
      <h4 class="mb-1 mt-1">
        Contrat {{ $contrat->reference }}
        <span class="badge bg-label-{{ $contrat->couleurStatut() }} ms-2">{{ $contrat->retard() > 0 ? 'En retard' : $contrat->libelleStatut() }}</span>
      </h4>
      <p class="text-muted mb-0">{{ $contrat->livreur->nomComplet() }} · {{ $contrat->moto->libelle() }}</p>
    </div>
    <div class="d-flex gap-2">
      @if ($enCours)
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPaiement">
          <i class="icon-base bx bx-money me-1"></i> Enregistrer un paiement
        </button>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalResilier">
          <i class="icon-base bx bx-block me-1"></i> Résilier
        </button>
      @endif
    </div>
  </div>

  @if ($contrat->statut === \App\Models\LocationVente\Contrat::STATUT_SOLDE)
    <div class="alert alert-success d-flex align-items-center">
      <i class="icon-base bx bx-key me-2"></i>
      <div>Contrat soldé le <strong>{{ $contrat->date_solde?->format('d/m/Y') }}</strong> : la moto {{ $contrat->moto->immatriculationAffichee() }} est sortie du parc et appartient désormais à <strong>{{ $contrat->livreur->nomComplet() }}</strong>.</div>
    </div>
  @elseif ($contrat->statut === \App\Models\LocationVente\Contrat::STATUT_RESILIE)
    <div class="alert alert-secondary">
      Contrat résilié le <strong>{{ $contrat->date_resiliation?->format('d/m/Y') }}</strong>. La moto est revenue dans le parc.
    </div>
  @elseif ($contrat->retard() > 0)
    <div class="alert alert-danger">
      Le livreur a <strong>{{ $fcfa($contrat->retard()) }}</strong> de retard, soit environ {{ $contrat->echeancesEnRetard() }} échéance(s) impayée(s).
    </div>
  @endif

  <div class="row mb-4">
    @foreach ([
      ['Montant à rembourser', $fcfa($contrat->prix_total), 'primary'],
      ['Montant payé', $fcfa($contrat->montantPaye()), 'success'],
      ['Reste à payer', $fcfa($contrat->reste()), 'warning'],
      ['Retard', $fcfa($contrat->retard()), $contrat->retard() > 0 ? 'danger' : 'secondary'],
    ] as [$libelle, $valeur, $couleur])
      <div class="col-sm-6 col-xl-3 mb-4">
        <div class="card h-100 border-start border-3 border-{{ $couleur }}">
          <div class="card-body">
            <span class="d-block mb-1">{{ $libelle }}</span>
            <h5 class="card-title mb-0 text-{{ $couleur }}">{{ $valeur }}</h5>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row">
    <div class="col-lg-4 mb-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Conditions du contrat</h5></div>
        <div class="card-body">
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
              <small>Progression</small>
              <small class="fw-medium">{{ $contrat->progression() }}%</small>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar bg-{{ $contrat->statut === 'solde' ? 'success' : 'primary' }}" style="width: {{ $contrat->progression() }}%"></div>
            </div>
          </div>
          <dl class="row mb-0 small">
            <dt class="col-6 fw-normal text-muted">Livreur</dt>
            <dd class="col-6">{{ $contrat->livreur->nomComplet() }}<br>{{ $contrat->livreur->contact }}</dd>
            <dt class="col-6 fw-normal text-muted">Moto</dt>
            <dd class="col-6">{{ trim($contrat->moto->marque.' '.$contrat->moto->modele) }}<br>{{ $contrat->moto->immatriculationAffichee() }}</dd>
            <dt class="col-6 fw-normal text-muted">Prix d'achat</dt>
            <dd class="col-6">{{ $fcfa($contrat->prix_achat) }}</dd>
            <dt class="col-6 fw-normal text-muted">Coût supplémentaire</dt>
            <dd class="col-6">{{ $fcfa($contrat->cout_supplementaire) }}</dd>
            <dt class="col-6 fw-normal text-muted">Marge</dt>
            <dd class="col-6">{{ $fcfa($contrat->marge) }}</dd>
            <dt class="col-6 fw-medium">Total à rembourser</dt>
            <dd class="col-6 fw-medium">{{ $fcfa($contrat->prix_total) }}</dd>
            <dt class="col-6 fw-normal text-muted">Apport initial</dt>
            <dd class="col-6">{{ $fcfa($contrat->apport) }}</dd>
            <dt class="col-6 fw-normal text-muted">Échéance</dt>
            <dd class="col-6">{{ $fcfa($contrat->montant_echeance) }} / {{ $contrat->uniteFrequence() }}</dd>
            <dt class="col-6 fw-normal text-muted">Fréquence</dt>
            <dd class="col-6">{{ $contrat->libelleFrequence() }}</dd>
            <dt class="col-6 fw-normal text-muted">Nombre d'échéances</dt>
            <dd class="col-6">{{ $contrat->nombreEcheances() }}</dd>
            <dt class="col-6 fw-normal text-muted">Remise de la moto</dt>
            <dd class="col-6">{{ $contrat->date_debut->format('d/m/Y') }}</dd>
            <dt class="col-6 fw-normal text-muted">Fin prévue</dt>
            <dd class="col-6">{{ $contrat->dateFinPrevue()->format('d/m/Y') }}</dd>
            @if ($enCours)
              <dt class="col-6 fw-normal text-muted">Prochaine échéance</dt>
              <dd class="col-6">{{ optional($contrat->prochaineEcheance())->format('d/m/Y') ?? '—' }}</dd>
            @endif
          </dl>
          @if ($contrat->notes)
            <hr>
            <small class="text-muted d-block" style="white-space: pre-line;">{{ $contrat->notes }}</small>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-8 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Paiements reçus</h5>
          <small class="text-muted">{{ $contrat->paiements->count() }} paiement(s)</small>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Mode</th>
                <th>Référence</th>
                <th class="text-end">Montant</th>
                <th>Enregistré par</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($contrat->paiements as $paiement)
                <tr>
                  <td>{{ $paiement->date_paiement->format('d/m/Y') }}</td>
                  <td><span class="badge bg-label-{{ $paiement->type === 'apport' ? 'info' : 'primary' }}">{{ $paiement->type === 'apport' ? 'Apport' : 'Échéance' }}</span></td>
                  <td>{{ $paiement->mode }}</td>
                  <td><small>{{ $paiement->reference ?: '—' }}</small></td>
                  <td class="text-end fw-medium">{{ $fcfa($paiement->montant) }}</td>
                  <td><small>{{ $paiement->auteur ? trim($paiement->auteur->prenoms.' '.$paiement->auteur->nom) : '—' }}</small></td>
                  <td>
                    @if ($contrat->statut !== \App\Models\LocationVente\Contrat::STATUT_RESILIE)
                      <button
                        type="button"
                        class="btn btn-sm btn-icon btn-outline-danger"
                        title="Annuler ce paiement"
                        data-bs-toggle="modal"
                        data-bs-target="#modalConfirmation"
                        data-confirm-titre="Annuler un paiement"
                        data-confirm-texte="Annuler le paiement de {{ $fcfa($paiement->montant) }} du {{ $paiement->date_paiement->format('d/m/Y') }} ?{{ $contrat->statut === 'solde' ? ' Le contrat repassera en cours et la moto reviendra en location-vente.' : '' }}"
                        data-confirm-url="{{ route('manager.paiements.destroy', $paiement) }}">
                        <i class="icon-base bx bx-trash"></i>
                      </button>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4">Aucun paiement reçu</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Échéancier</h5>
      <small class="text-muted">{{ $contrat->nombreEcheances() }} échéance(s) après l'apport · {{ $contrat->libelleFrequence() }}</small>
    </div>
    <div class="table-responsive" style="max-height: 480px;">
      <table class="table table-sm table-hover mb-0">
        <thead class="sticky-top bg-body">
          <tr>
            <th>N°</th>
            <th>Date</th>
            <th class="text-end">Montant</th>
            <th class="text-end">Cumul attendu</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($echeancier as $ligne)
            <tr>
              <td>{{ $ligne['numero'] }}</td>
              <td>{{ $ligne['date']->format('d/m/Y') }}</td>
              <td class="text-end">{{ $fcfa($ligne['montant']) }}</td>
              <td class="text-end">{{ $fcfa($ligne['cumul']) }}</td>
              <td><span class="badge bg-label-{{ $libellesEcheance[$ligne['statut']][1] }}">{{ $libellesEcheance[$ligne['statut']][0] }}</span></td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center py-4">Moto payée intégralement à l'apport : aucune échéance.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if ($enCours)
    @include('manager.paiements._modal', ['contratFixe' => $contrat, 'contrats' => collect()])

    <div class="modal fade" id="modalResilier" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('manager.contrats.resilier', $contrat) }}" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-header">
              <h5 class="modal-title">Résilier le contrat {{ $contrat->reference }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-warning">
                La moto {{ $contrat->moto->immatriculationAffichee() }} sera récupérée et redeviendra <strong>disponible</strong> dans le parc.
                Les {{ $fcfa($contrat->montantPaye()) }} déjà versés restent enregistrés.
              </div>
              <div class="mb-3">
                <label class="form-label">Date de résiliation</label>
                <input type="date" class="form-control" name="date_resiliation" value="{{ now()->toDateString() }}" required>
              </div>
              <div class="mb-0">
                <label class="form-label">Motif</label>
                <textarea class="form-control" name="motif" rows="2" placeholder="Ex : retards répétés, moto restituée..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-danger">Résilier le contrat</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif

  @include('manager._confirmation')
@endsection
