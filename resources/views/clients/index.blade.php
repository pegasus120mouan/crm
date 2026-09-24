@extends('layout.app')

@section('title', 'Gestion des clients')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h4 class="mb-1">Gestion des clients</h4>
      <p class="text-muted mb-0">Liste des clients et ajout de nouveaux comptes</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterClient">
      <i class="icon-base bx bx-user-plus me-1"></i> Ajouter un client
    </button>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Total clients</span>
          <h4 class="card-title mb-0">{{ $clients->total() }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Clients actifs</span>
          <h4 class="card-title mb-0 text-success">{{ $clientsActifs }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Clients inactifs</span>
          <h4 class="card-title mb-0 text-danger">{{ $clientsInactifs }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1">Boutiques</span>
          <h4 class="card-title mb-0">{{ $boutiques->count() }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h5 class="mb-0">Clients</h5>
      <form method="GET" action="{{ route('clients.index') }}" class="d-flex gap-2">
        <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="{{ request('q') }}">
        <button type="submit" class="btn btn-outline-primary">Rechercher</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Logo</th>
            <th>Boutique</th>
            <th>Nom</th>
            <th>Prénoms</th>
            <th>Contact</th>
            <th>Login</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($clients as $client)
            <tr>
              <td>
                @if ($client->boutique)
                  <a href="{{ route('boutiques.show', $client->boutique) }}" title="{{ $client->boutique->nom }}">
                    <img src="{{ $client->boutique->logoUrl() }}" alt="" class="rounded-circle" width="40" height="40" style="object-fit: cover; background: #e7e7ff;" onerror="this.onerror=null;this.src='{{ route('boutiques.logo', $client->boutique) }}';">
                  </a>
                @else
                  <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-label-secondary" style="width: 40px; height: 40px;">—</span>
                @endif
              </td>
              <td>
                @if ($client->boutique)
                  <a href="{{ route('boutiques.show', $client->boutique) }}" class="badge bg-label-info">{{ $client->boutique->nom }}</a>
                @else
                  <span class="badge bg-label-secondary">Aucune</span>
                @endif
              </td>
              <td>{{ $client->nom }}</td>
              <td>{{ $client->prenoms }}</td>
              <td>{{ $client->contact }}</td>
              <td>{{ $client->login }}</td>
              <td>
                @if ($client->statut_compte)
                  <span class="badge bg-label-success">Actif</span>
                @else
                  <span class="badge bg-label-danger">Inactif</span>
                @endif
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalModifier{{ $client->id }}" title="Modifier">
                  <i class="icon-base bx bx-edit"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-danger"
                  title="Supprimer"
                  data-bs-toggle="modal"
                  data-bs-target="#modalSupprimerClient"
                  data-client-name="{{ $client->nom }} {{ $client->prenoms }}"
                  data-delete-url="{{ route('clients.destroy', $client) }}">
                  <i class="icon-base bx bx-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4">Aucun client trouvé</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">
        Affichage de {{ $clients->firstItem() ?? 0 }} à {{ $clients->lastItem() ?? 0 }} sur {{ $clients->total() }} clients
      </small>
      {{ $clients->links() }}
    </div>
  </div>

  <div class="modal fade" id="modalAjouterClient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('clients.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Ajouter un client</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="nom">Nom</label>
              <input type="text" class="form-control" id="nom" name="nom" value="{{ old('nom') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="prenoms">Prénoms</label>
              <input type="text" class="form-control" id="prenoms" name="prenoms" value="{{ old('prenoms') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="contact">Contact</label>
              <input type="text" class="form-control" id="contact" name="contact" value="{{ old('contact') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="login">Login</label>
              <input type="text" class="form-control" id="login" name="login" value="{{ old('login') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="password">Mot de passe</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="boutique_id">Boutique</label>
              <select class="form-select" id="boutique_id" name="boutique_id">
                <option value="">Aucune</option>
                @foreach ($boutiquesLibres as $boutique)
                  <option value="{{ $boutique->id }}" @selected(old('boutique_id') == $boutique->id)>{{ $boutique->nom }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-0">
              <label class="form-label" for="statut_compte">Statut</label>
              <select class="form-select" id="statut_compte" name="statut_compte">
                <option value="1" @selected(old('statut_compte', '1') == '1')>Actif</option>
                <option value="0" @selected(old('statut_compte') === '0')>Inactif</option>
              </select>
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

  @foreach ($clients as $client)
    <div class="modal fade" id="modalModifier{{ $client->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="{{ route('clients.update', $client) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier le client</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" class="form-control" name="nom" value="{{ $client->nom }}" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Prénoms</label>
                <input type="text" class="form-control" name="prenoms" value="{{ $client->prenoms }}" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Contact</label>
                <input type="text" class="form-control" name="contact" value="{{ $client->contact }}" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Boutique</label>
                <select class="form-select" name="boutique_id">
                  <option value="">Aucune</option>
                  @foreach ($boutiques as $boutique)
                    @if (($boutique->utilisateurs_count ?? 0) == 0 || $client->boutique_id == $boutique->id)
                      <option value="{{ $boutique->id }}" @selected($client->boutique_id == $boutique->id)>{{ $boutique->nom }}</option>
                    @endif
                  @endforeach
                </select>
              </div>
              <div class="mb-0">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut_compte">
                  <option value="1" @selected($client->statut_compte)>Actif</option>
                  <option value="0" @selected(! $client->statut_compte)>Inactif</option>
                </select>
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

  <div class="modal fade" id="modalSupprimerClient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Supprimer un client</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-1">Vous êtes sur le point de supprimer le client :</p>
          <p class="fw-bold mb-0" id="supprimerClientNom"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <form id="formSupprimerClient" method="POST">
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
    var modal = document.getElementById('modalSupprimerClient');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;

      var nameEl = document.getElementById('supprimerClientNom');
      if (nameEl) nameEl.textContent = button.getAttribute('data-client-name') || '';

      var form = document.getElementById('formSupprimerClient');
      var deleteUrl = button.getAttribute('data-delete-url') || '';
      if (form && deleteUrl) form.setAttribute('action', deleteUrl);
    });
  });
</script>
@endpush
