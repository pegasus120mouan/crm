<!doctype html>
<html
  lang="fr"
  class="layout-menu-fixed layout-compact"
  data-assets-path="{{ asset('assets/') }}"
  data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>@yield('title', 'OVL CRM')</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo/logo.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
    @stack('head')
  </head>
  <body>
    @php
      $sessionUser = $utilisateur ?? session('utilisateur', []);
      $sessionUserName = trim(($sessionUser['prenoms'] ?? '') . ' ' . ($sessionUser['nom'] ?? ''));
      if ($sessionUserName === '') {
        $sessionUserName = $sessionUser['login'] ?? 'Commercial';
      }
      $estManager = ($sessionUser['role'] ?? null) === 'manager';
      $routeAccueil = \App\Models\Utilisateur::routeAccueil($sessionUser['role'] ?? null);
      $sessionAvatarUrl = asset('assets/img/avatars/1.png');
      $sessionUserId = (int) ($sessionUser['id'] ?? 0);
      if ($sessionUserId > 0 && \Illuminate\Support\Facades\Schema::hasTable('utilisateurs')) {
          $sessionCommercial = \App\Models\Utilisateur::query()->find($sessionUserId);
          if ($sessionCommercial) {
              $sessionAvatarUrl = $sessionCommercial->avatarUrl();
          }
      }
    @endphp
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
          <div class="app-brand demo">
            <a href="{{ route($routeAccueil) }}" class="app-brand-link">
              <img src="{{ asset('img/logo/logo.png') }}" alt="OVL CRM" class="app-brand-logo" style="height: 40px; width: auto;" />
            </a>
            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
            </a>
          </div>
          <div class="menu-divider mt-0"></div>
          <div class="menu-inner-shadow"></div>
          <ul class="menu-inner py-1">
            @if ($estManager)
            <li class="menu-item {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
              <a href="{{ route('manager.dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate">Tableau de bord</div>
              </a>
            </li>
            <li class="menu-header small text-uppercase">
              <span class="menu-header-text">Location-vente</span>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.livreurs.*') ? 'active' : '' }}">
              <a href="{{ route('manager.livreurs.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-id-card"></i>
                <div class="text-truncate">Liste des livreurs</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.gerants.*') ? 'active' : '' }}">
              <a href="{{ route('manager.gerants.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user-circle"></i>
                <div class="text-truncate">Liste des gérants</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.motos.*') ? 'active' : '' }}">
              <a href="{{ route('manager.motos.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cycling"></i>
                <div class="text-truncate">Liste des motos</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.types-moto.*') ? 'active' : '' }}">
              <a href="{{ route('manager.types-moto.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-category"></i>
                <div class="text-truncate">Type de moto</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.depenses.*') ? 'active' : '' }}">
              <a href="{{ route('manager.depenses.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-wrench"></i>
                <div class="text-truncate">Dépenses moto</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.documents.*') ? 'active' : '' }}">
              <a href="{{ route('manager.documents.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-folder"></i>
                <div class="text-truncate">Documents</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.contrats.*') ? 'active' : '' }}">
              <a href="{{ route('manager.contrats.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-file"></i>
                <div class="text-truncate">Contrats</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('manager.paiements.*') ? 'active' : '' }}">
              <a href="{{ route('manager.paiements.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-money"></i>
                <div class="text-truncate">Paiements</div>
              </a>
            </li>
            @else
            <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
              <a href="{{ route('dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate">Tableau de bord</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">
              <a href="{{ route('clients.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div class="text-truncate">Mes clients</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('commandes.*') ? 'active' : '' }}">
              <a href="{{ route('commandes.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-package"></i>
                <div class="text-truncate">Liste des commandes</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('commissions.*') ? 'active' : '' }}">
              <a href="{{ route('commissions.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-wallet"></i>
                <div class="text-truncate">Mes commissions</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('boutiques.*') ? 'active' : '' }}">
              <a href="{{ route('boutiques.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-store"></i>
                <div class="text-truncate">Gestion des boutiques</div>
              </a>
            </li>
            <li class="menu-item {{ request()->routeIs('communes.*') ? 'active' : '' }}">
              <a href="{{ route('communes.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-map"></i>
                <div class="text-truncate">Liste des communes</div>
              </a>
            </li>
            @endif
          </ul>
        </aside>

        <div class="layout-page">
          <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                <i class="icon-base bx bx-menu icon-md"></i>
              </a>
            </div>
            <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
              <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a class="nav-link p-0" href="{{ route('profil.show') }}" title="Mon profil">
                    <div class="avatar avatar-online" style="width: 40px; height: 40px; overflow: hidden;">
                      <img src="{{ $sessionAvatarUrl }}" alt="{{ $sessionUserName }}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null;this.src='{{ asset('assets/img/avatars/1.png') }}';" />
                    </div>
                  </a>
                </li>
                <li class="nav-item ms-2">
                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link btn btn-link p-0 border-0" title="Déconnexion">
                      <i class="icon-base bx bx-power-off icon-md"></i>
                    </button>
                  </form>
                </li>
              </ul>
            </div>
          </nav>

          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              @if (session('success'))
                <div class="alert alert-success alert-dismissible" role="alert">
                  {{ session('success') }}
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              @endif
              @if (session('error'))
                <div class="alert alert-danger alert-dismissible" role="alert">
                  {{ session('error') }}
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              @endif
              @if ($errors->any())
                <div class="alert alert-danger alert-dismissible" role="alert">
                  {{ $errors->first() }}
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              @endif
              @yield('content')
            </div>
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl">
                <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                  <div class="mb-2 mb-md-0">© {{ date('Y') }} OVL CRM</div>
                </div>
              </div>
            </footer>
            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script>
      document.querySelectorAll('input[type="date"]').forEach(function (champ) {
        if (champ.dataset.dateFr) {
          return;
        }
        champ.dataset.dateFr = '1';

        var isoInitial = champ.value;
        var minimum = champ.getAttribute('min') || '';
        var maximum = champ.getAttribute('max') || '';
        var affichage = document.createElement('input');
        affichage.type = 'text';
        affichage.className = champ.className;
        affichage.placeholder = 'dd/mm/yyyy';
        affichage.inputMode = 'numeric';
        affichage.autocomplete = 'off';
        affichage.maxLength = 10;
        affichage.setAttribute('aria-label', champ.getAttribute('aria-label') || '');
        if (champ.required) {
          affichage.required = true;
          champ.required = false;
        }
        if (champ.readOnly) {
          affichage.readOnly = true;
        }
        if (champ.disabled) {
          affichage.disabled = true;
        }
        if (champ.id) {
          affichage.id = champ.id + '-fr';
          var label = document.querySelector('label[for="' + champ.id + '"]');
          if (label) {
            label.htmlFor = affichage.id;
          }
        }

        var proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

        function versAffichage(iso) {
          var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
          affichage.value = match ? match[3] + '/' + match[2] + '/' + match[1] : '';
        }

        function versIso(texte) {
          var match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec((texte || '').trim());
          if (!match) {
            return '';
          }
          var jour = Number(match[1]);
          var mois = Number(match[2]);
          var annee = Number(match[3]);
          var date = new Date(annee, mois - 1, jour);
          if (date.getFullYear() !== annee || date.getMonth() !== mois - 1 || date.getDate() !== jour) {
            return '';
          }
          return annee + '-' + String(mois).padStart(2, '0') + '-' + String(jour).padStart(2, '0');
        }

        champ.type = 'hidden';
        proto.set.call(champ, isoInitial);
        versAffichage(isoInitial);
        champ.before(affichage);

        Object.defineProperty(champ, 'value', {
          configurable: true,
          get: function () {
            return proto.get.call(champ);
          },
          set: function (valeur) {
            proto.set.call(champ, valeur);
            versAffichage(valeur);
          },
        });

        function publier(iso) {
          var precedent = proto.get.call(champ);
          proto.set.call(champ, iso);
          if (iso === precedent) {
            return;
          }
          champ.dispatchEvent(new Event('input', { bubbles: true }));
          champ.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function synchroniser() {
          if (affichage.value.trim() === '') {
            affichage.setCustomValidity('');
            publier('');
            return;
          }
          var iso = versIso(affichage.value);
          if (!iso) {
            affichage.setCustomValidity('Utilisez le format dd/mm/yyyy.');
            publier('');
            return;
          }
          if ((minimum && iso < minimum) || (maximum && iso > maximum)) {
            affichage.setCustomValidity('Cette date est hors de la période autorisée.');
            publier('');
            return;
          }
          affichage.setCustomValidity('');
          publier(iso);
        }

        affichage.addEventListener('paste', function (event) {
          var pressePapiers = event.clipboardData || window.clipboardData;
          var iso = /^(\d{4})-(\d{2})-(\d{2})/.exec((pressePapiers.getData('text') || '').trim());
          if (!iso) {
            return;
          }
          event.preventDefault();
          affichage.value = iso[3] + '/' + iso[2] + '/' + iso[1];
          synchroniser();
        });
        affichage.addEventListener('input', function () {
          var chiffres = affichage.value.replace(/\D/g, '').slice(0, 8);
          var texte = chiffres;
          if (chiffres.length > 4) {
            texte = chiffres.slice(0, 2) + '/' + chiffres.slice(2, 4) + '/' + chiffres.slice(4);
          } else if (chiffres.length > 2) {
            texte = chiffres.slice(0, 2) + '/' + chiffres.slice(2);
          }
          affichage.value = texte;
          if (texte.length === 10 || texte === '') {
            synchroniser();
          } else {
            affichage.setCustomValidity('');
          }
        });
        affichage.addEventListener('blur', synchroniser);
        if (champ.form) {
          champ.form.addEventListener('submit', function (event) {
            synchroniser();
            if (!affichage.checkValidity()) {
              event.preventDefault();
              affichage.reportValidity();
            }
          });
        }
      });
    </script>
    @stack('scripts')
  </body>
</html>
