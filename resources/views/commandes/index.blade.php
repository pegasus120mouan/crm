@extends('layout.app')

@section('title', 'Gestion des commandes')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Gestion des commandes</h4>
      <p class="text-muted mb-0">Liste des commandes et création de nouvelles commandes</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterCommande">
      <i class="icon-base bx bx-plus me-1"></i> Ajouter une commande
    </button>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Total</span>
          <h4 class="card-title mb-0">{{ $stats['total'] }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Livrées</span>
          <h4 class="card-title mb-0 text-success">{{ $stats['livrees'] }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Non livrées</span>
          <h4 class="card-title mb-0 text-warning">{{ $stats['non_livrees'] }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Retours</span>
          <h4 class="card-title mb-0 text-danger">{{ $stats['retours'] }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0">Commandes</h5>
      <form method="GET" action="{{ route('commandes.index') }}" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="{{ request('q') }}">
        <select name="statut" class="form-select">
          <option value="">Tous les statuts</option>
          <option value="Non Livré" @selected($statut === 'Non Livré')>Non Livré</option>
          <option value="Livré" @selected($statut === 'Livré')>Livré</option>
          <option value="Retour" @selected($statut === 'Retour')>Retour</option>
        </select>
        <button type="submit" class="btn btn-outline-primary">Filtrer</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Communes</th>
            <th>Coût Global</th>
            <th>Livraison</th>
            <th>Coût réel</th>
            <th>Boutique</th>
            <th>Livreur</th>
            <th>Statut</th>
            <th>Date réception</th>
            <th>Date livraison</th>
            <th>Date Retour</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($commandes as $commande)
            @php
              $livreurNom = trim(($commande->livreur->nom ?? '').' '.($commande->livreur->prenoms ?? ''));
            @endphp
            <tr>
              <td>{{ $commande->communes }}</td>
              <td>{{ number_format((int) $commande->cout_global, 0, ',', ' ') }}</td>
              <td>{{ number_format((int) $commande->cout_livraison, 0, ',', ' ') }}</td>
              <td>{{ number_format((int) $commande->cout_reel, 0, ',', ' ') }}</td>
              <td>
                @if ($commande->client?->boutique)
                  <span class="d-inline-flex align-items-center gap-1">
                    <img src="{{ $commande->client->boutique->logoUrl() }}" alt="" class="rounded-circle" width="24" height="24" style="object-fit: cover; background: #e7e7ff;" onerror="this.onerror=null;this.src='{{ route('boutiques.logo', $commande->client->boutique) }}';">
                    {{ $commande->client->boutique->nom }}
                  </span>
                @else
                  N/A
                @endif
              </td>
              <td>
                @if ($livreurNom !== '')
                  {{ $livreurNom }}
                @else
                  <span class="badge bg-label-warning">Pas de livreur attribué</span>
                @endif
              </td>
              <td>
                @if ($commande->statut === 'Livré')
                  <i class="icon-base bx bx-check-circle text-success" style="font-size: 1.5rem;" title="Livré"></i>
                @elseif ($commande->statut === 'Retour')
                  <i class="icon-base bx bx-undo text-info" style="font-size: 1.5rem;" title="Retour"></i>
                @else
                  <i class="icon-base bx bx-x-circle text-danger" style="font-size: 1.5rem;" title="Non Livré"></i>
                @endif
              </td>
              <td>{{ optional($commande->date_reception)->format('d-m-Y') ?: 'N/A' }}</td>
              <td>
                @if ($commande->date_livraison)
                  {{ $commande->date_livraison->format('d-m-Y') }}
                @else
                  <span class="badge bg-label-secondary">Pas encore livré</span>
                @endif
              </td>
              <td>{{ optional($commande->date_retour)->format('d-m-Y') ?: 'N/A' }}</td>
              <td>
                <button type="button" class="btn btn-sm btn-icon btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalDetails{{ $commande->id }}" title="Détails">
                  <i class="icon-base bx bx-show"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-info"
                  title="Attribuer un livreur"
                  data-bs-toggle="modal"
                  data-bs-target="#modalAttribuerLivreur"
                  data-assign-url="{{ route('commandes.assign-livreur', $commande) }}"
                  data-livreur-id="{{ $commande->livreur_id }}"
                  data-commande-label="#{{ $commande->id }} — {{ $commande->communes }}">
                  <i class="icon-base bx bx-user-plus"></i>
                </button>
                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifier{{ $commande->id }}" title="Modifier">
                  <i class="icon-base bx bx-edit"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalSupprimerCommande"
                  data-item-name="#{{ $commande->id }} — {{ $commande->communes }}"
                  data-delete-url="{{ route('commandes.destroy', $commande) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="11" class="text-center py-4">Aucune commande trouvée</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">
        Affichage de {{ $commandes->firstItem() ?? 0 }} à {{ $commandes->lastItem() ?? 0 }} sur {{ $commandes->total() }} commandes
      </small>
      {{ $commandes->links() }}
    </div>
  </div>

  <div class="modal fade" id="modalAjouterCommande" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form action="{{ route('commandes.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une commande</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label" for="utilisateur_id">Client</label>
                <select class="form-select" id="utilisateur_id" name="utilisateur_id" required>
                  <option value="">Sélectionner un client</option>
                  @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('utilisateur_id') == $client->id)>
                      {{ $client->nom }} {{ $client->prenoms }} ({{ $client->boutique->nom ?? 'Sans boutique' }})
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="livreur_id">Livreur</label>
                <select class="form-select" id="livreur_id" name="livreur_id">
                  <option value="">Non assigné</option>
                  @foreach ($livreurs as $livreur)
                    <option value="{{ $livreur->id }}" @selected(old('livreur_id') == $livreur->id)>
                      {{ $livreur->nom }} {{ $livreur->prenoms }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="communes">Destination</label>
                <input type="text" class="form-control" id="communes" name="communes" value="{{ old('communes') }}" placeholder="Ex: Cocody" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="date_reception">Date de réception</label>
                <input type="date" class="form-control" id="date_reception" name="date_reception" value="{{ old('date_reception', date('Y-m-d')) }}" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="cout_global">Coût global (FCFA)</label>
                <input type="number" class="form-control" id="cout_global" name="cout_global" value="{{ old('cout_global', 0) }}" min="0" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="cout_livraison">Coût livraison (FCFA)</label>
                <select class="form-select" id="cout_livraison" name="cout_livraison" required @disabled($coutsLivraison->isEmpty())>
                  <option value="">Sélectionner un coût</option>
                  @foreach ($coutsLivraison as $cout)
                    <option value="{{ $cout->cout_livraison }}" @selected((string) old('cout_livraison') === (string) $cout->cout_livraison)>
                      {{ number_format((int) $cout->cout_livraison, 0, ',', ' ') }} FCFA
                    </option>
                  @endforeach
                </select>
                @if ($coutsLivraison->isEmpty())
                  <div class="form-text text-danger">Aucun coût de livraison n’est défini en base.</div>
                @endif
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @foreach ($commandes as $commande)
    <div class="modal fade" id="modalDetails{{ $commande->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Détails de la commande #{{ $commande->id }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <small class="text-muted d-block">Commune</small>
                <strong>{{ $commande->communes }}</strong>
              </div>
              <div class="col-md-6 mb-3">
                <small class="text-muted d-block">Boutique</small>
                @if ($commande->client?->boutique)
                  <strong class="d-inline-flex align-items-center gap-2">
                    <img src="{{ $commande->client->boutique->logoUrl() }}" alt="" class="rounded-circle" width="28" height="28" style="object-fit: cover; background: #e7e7ff;" onerror="this.onerror=null;this.src='{{ route('boutiques.logo', $commande->client->boutique) }}';">
                    {{ $commande->client->boutique->nom }}
                  </strong>
                @else
                  <strong>N/A</strong>
                @endif
              </div>
              <div class="col-md-4 mb-3">
                <small class="text-muted d-block">Coût Global</small>
                <strong>{{ number_format((int) $commande->cout_global, 0, ',', ' ') }} FCFA</strong>
              </div>
              <div class="col-md-4 mb-3">
                <small class="text-muted d-block">Coût Livraison</small>
                <strong>{{ number_format((int) $commande->cout_livraison, 0, ',', ' ') }} FCFA</strong>
              </div>
              <div class="col-md-4 mb-3">
                <small class="text-muted d-block">Coût Réel</small>
                <strong>{{ number_format((int) $commande->cout_reel, 0, ',', ' ') }} FCFA</strong>
              </div>
              <div class="col-md-6 mb-3">
                <small class="text-muted d-block">Livreur</small>
                <strong>{{ trim(($commande->livreur->nom ?? '').' '.($commande->livreur->prenoms ?? '')) ?: 'Pas de livreur attribué' }}</strong>
              </div>
              <div class="col-md-6 mb-3">
                <small class="text-muted d-block">Statut</small>
                <strong>{{ $commande->statut }}</strong>
              </div>
              <div class="col-md-4 mb-3">
                <small class="text-muted d-block">Date réception</small>
                <strong>{{ optional($commande->date_reception)->format('d-m-Y') ?: 'N/A' }}</strong>
              </div>
              <div class="col-md-4 mb-3">
                <small class="text-muted d-block">Date livraison</small>
                <strong>{{ optional($commande->date_livraison)->format('d-m-Y') ?: 'Pas encore livré' }}</strong>
              </div>
              <div class="col-md-4 mb-3">
                <small class="text-muted d-block">Date retour</small>
                <strong>{{ optional($commande->date_retour)->format('d-m-Y') ?: 'N/A' }}</strong>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modalModifier{{ $commande->id }}">Modifier</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalModifier{{ $commande->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form action="{{ route('commandes.update', $commande) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier la commande</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Client</label>
                  <select class="form-select" name="utilisateur_id" required>
                    @foreach ($clients as $client)
                      <option value="{{ $client->id }}" @selected($commande->utilisateur_id == $client->id)>
                        {{ $client->nom }} {{ $client->prenoms }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Livreur</label>
                  <select class="form-select" name="livreur_id">
                    <option value="">Non assigné</option>
                    @foreach ($livreurs as $livreur)
                      <option value="{{ $livreur->id }}" @selected($commande->livreur_id == $livreur->id)>
                        {{ $livreur->nom }} {{ $livreur->prenoms }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Destination</label>
                  <input type="text" class="form-control" name="communes" value="{{ $commande->communes }}" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Date de réception</label>
                  <input type="date" class="form-control" name="date_reception" value="{{ optional($commande->date_reception)->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Coût global (FCFA)</label>
                  <input type="number" class="form-control" name="cout_global" value="{{ $commande->cout_global }}" min="0" required>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Coût livraison (FCFA)</label>
                  <select class="form-select" name="cout_livraison" required>
                    <option value="">Sélectionner un coût</option>
                    @forelse ($coutsLivraison as $cout)
                      <option value="{{ $cout->cout_livraison }}" @selected((int) $commande->cout_livraison === (int) $cout->cout_livraison)>
                        {{ number_format((int) $cout->cout_livraison, 0, ',', ' ') }} FCFA
                      </option>
                    @empty
                      @if ($commande->cout_livraison !== null)
                        <option value="{{ $commande->cout_livraison }}" selected>
                          {{ number_format((int) $commande->cout_livraison, 0, ',', ' ') }} FCFA
                        </option>
                      @endif
                    @endforelse
                    @if ($coutsLivraison->isNotEmpty() && $commande->cout_livraison !== null && ! $coutsLivraison->contains('cout_livraison', (int) $commande->cout_livraison))
                      <option value="{{ $commande->cout_livraison }}" selected>
                        {{ number_format((int) $commande->cout_livraison, 0, ',', ' ') }} FCFA
                      </option>
                    @endif
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Statut</label>
                  <select class="form-select" name="statut" required>
                    <option value="Non Livré" @selected($commande->statut === 'Non Livré')>Non Livré</option>
                    <option value="Livré" @selected($commande->statut === 'Livré')>Livré</option>
                    <option value="Retour" @selected($commande->statut === 'Retour')>Retour</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-primary">Modifier</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endforeach

  <div class="modal fade" id="modalAttribuerLivreur" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="formAttribuerLivreur" method="POST">
          @csrf
          @method('PATCH')
          <div class="modal-header">
            <h5 class="modal-title">Attribuer un livreur</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-3">Commande : <strong id="attribuerCommandeLabel"></strong></p>
            <label class="form-label" for="attribuer_livreur_id">Livreur</label>
            <select class="form-select" id="attribuer_livreur_id" name="livreur_id" required @disabled($livreurs->isEmpty())>
              <option value="">Sélectionner un livreur</option>
              @foreach ($livreurs as $livreur)
                <option value="{{ $livreur->id }}">{{ $livreur->nom }} {{ $livreur->prenoms }}</option>
              @endforeach
            </select>
            @if ($livreurs->isEmpty())
              <div class="form-text text-danger">Aucun livreur actif n’est disponible pour le moment.</div>
            @endif
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Attribuer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalSupprimerCommande" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Supprimer une commande</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-1">Vous êtes sur le point de supprimer la commande :</p>
          <p class="fw-bold mb-0" id="supprimerItemNom"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <form id="formSupprimerItem" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Supprimer</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var assignModal = document.getElementById('modalAttribuerLivreur');
    if (assignModal) {
      assignModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) return;
        var labelEl = document.getElementById('attribuerCommandeLabel');
        if (labelEl) labelEl.textContent = button.getAttribute('data-commande-label') || '';
        var form = document.getElementById('formAttribuerLivreur');
        var assignUrl = button.getAttribute('data-assign-url') || '';
        if (form && assignUrl) form.setAttribute('action', assignUrl);
        var select = document.getElementById('attribuer_livreur_id');
        if (select) select.value = button.getAttribute('data-livreur-id') || '';
      });
    }

    var modal = document.getElementById('modalSupprimerCommande');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;
      var nameEl = document.getElementById('supprimerItemNom');
      if (nameEl) nameEl.textContent = button.getAttribute('data-item-name') || '';
      var form = document.getElementById('formSupprimerItem');
      var deleteUrl = button.getAttribute('data-delete-url') || '';
      if (form && deleteUrl) form.setAttribute('action', deleteUrl);
    });
  });
</script>
@endpush
