<!doctype html>
<html
  lang="fr"
  class="layout-wide customizer-hide"
  data-assets-path="{{ asset('assets/') }}"
  data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Connexion | OVL CRM</title>
    <meta name="description" content="Espace commercial OVL CRM" />
    <link rel="icon" type="image/png" href="{{ asset('img/logo/logo.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}" />
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
  </head>
  <body>
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
          <div class="card px-sm-6 px-0">
            <div class="card-body">
              <div class="app-brand justify-content-center mb-4">
                <a href="{{ route('login') }}" class="app-brand-link">
                  <img src="{{ asset('img/logo/logo.png') }}" alt="OVL CRM" style="height: 88px; width: auto;" />
                </a>
              </div>
              <h4 class="mb-1">Bienvenue sur votre espace</h4>
              <p class="mb-6">Connectez-vous avec votre compte commercial</p>

              @if (session('success'))
                <div class="alert alert-success" role="alert">{{ session('success') }}</div>
              @endif

              @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
              @endif

              <form id="formAuthentication" class="mb-6" action="{{ route('login.post') }}" method="POST">
                @csrf
                <div class="mb-6">
                  <label for="login" class="form-label">Login</label>
                  <input
                    type="text"
                    class="form-control @error('login') is-invalid @enderror"
                    id="login"
                    name="login"
                    placeholder="Entrez votre login"
                    value="{{ old('login') }}"
                    required
                    autofocus
                    autocomplete="username" />
                </div>
                <div class="mb-6 form-password-toggle">
                  <label class="form-label" for="password">Mot de passe</label>
                  <div class="input-group input-group-merge">
                    <input
                      type="password"
                      id="password"
                      class="form-control @error('password') is-invalid @enderror"
                      name="password"
                      placeholder="••••••••"
                      aria-describedby="password"
                      required
                      autocomplete="current-password" />
                    <span class="input-group-text cursor-pointer"><i class="icon-base bx bx-hide"></i></span>
                  </div>
                </div>
                <div class="mb-8">
                  <div class="d-flex justify-content-between">
                    <div class="form-check mb-0">
                      <input class="form-check-input" type="checkbox" id="remember-me" name="remember" value="1" />
                      <label class="form-check-label" for="remember-me"> Se souvenir de moi </label>
                    </div>
                  </div>
                </div>
                <div class="mb-2">
                  <button class="btn btn-primary d-grid w-100" type="submit">Connexion</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
  </body>
</html>
