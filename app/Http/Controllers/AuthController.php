<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if ($this->isAuthenticatedCommercial()) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $utilisateur = Utilisateur::query()
            ->where('login', $request->input('login'))
            ->where('password', hash('sha256', $request->input('password')))
            ->first();

        if (! $utilisateur) {
            return back()->withErrors([
                'login' => 'Login ou mot de passe incorrect.',
            ])->withInput();
        }

        if ($utilisateur->statut_compte == 0) {
            return back()->withErrors([
                'login' => 'Votre compte est désactivé. Contactez l\'administrateur.',
            ])->withInput();
        }

        if ($utilisateur->role !== 'commercial') {
            return back()->withErrors([
                'login' => 'Seuls les commerciaux peuvent se connecter à cet espace.',
            ])->withInput();
        }

        Session::put('utilisateur', [
            'id' => $utilisateur->id,
            'nom' => $utilisateur->nom,
            'prenoms' => $utilisateur->prenoms,
            'login' => $utilisateur->login,
            'role' => $utilisateur->role,
            'avatar' => $utilisateur->avatar,
            'boutique_id' => $utilisateur->boutique_id,
        ]);

        if ($request->boolean('remember')) {
            Session::put('remember', true);
        }

        return redirect()->route('dashboard')->with('success', 'Connexion réussie !');
    }

    public function logout()
    {
        Session::forget('utilisateur');
        Session::flush();

        return redirect()->route('login')->with('success', 'Déconnexion réussie !');
    }

    public function avatar()
    {
        $id = (int) data_get(Session::get('utilisateur'), 'id', 0);
        abort_unless($id > 0, 404);

        $utilisateur = Utilisateur::query()->find($id);
        abort_unless($utilisateur, 404);

        $key = $utilisateur->avatarKey();

        try {
            $contents = Storage::disk('r2')->get($key);
            if (is_string($contents) && $contents !== '') {
                return response($contents, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'private, max-age=60',
                ]);
            }
        } catch (\Throwable) {
            //
        }

        $fallback = public_path('assets/img/avatars/1.png');
        if (is_file($fallback)) {
            return response()->file($fallback);
        }

        abort(404);
    }

    private function isAuthenticatedCommercial(): bool
    {
        $utilisateur = Session::get('utilisateur');

        return is_array($utilisateur) && ($utilisateur['role'] ?? null) === 'commercial';
    }
}
