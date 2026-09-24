<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * @var list<string>
     */
    private const DEFAULT_AVATARS = [
        'default.jpg',
        'utilisateurs/utilisateurs.png',
        'utilisateurs.png',
    ];

    public function show(): View
    {
        return view('profil.show', [
            'utilisateur' => $this->commercial(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $utilisateur = $this->commercial();

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:4', 'confirmed'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data = [
            'nom' => $validated['nom'],
            'prenoms' => $validated['prenoms'],
            'contact' => $validated['contact'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = hash('sha256', $validated['password']);
        }

        if ($request->hasFile('avatar')) {
            $ancien = $utilisateur->avatar;
            $data['avatar'] = $this->storeAvatar($request->file('avatar'));
            $this->deleteCustomAvatar($ancien);
        }

        $utilisateur->update($data);
        $utilisateur->refresh();

        $session = Session::get('utilisateur', []);
        Session::put('utilisateur', array_merge(is_array($session) ? $session : [], [
            'id' => $utilisateur->id,
            'nom' => $utilisateur->nom,
            'prenoms' => $utilisateur->prenoms,
            'login' => $utilisateur->login,
            'role' => $utilisateur->role,
            'avatar' => $utilisateur->avatar,
            'boutique_id' => $utilisateur->boutique_id,
        ]));

        return redirect()
            ->route('profil.show')
            ->with('success', 'Profil mis à jour.');
    }

    private function commercial(): Utilisateur
    {
        $id = (int) data_get(Session::get('utilisateur'), 'id', 0);
        abort_unless($id > 0, 404);

        $utilisateur = Utilisateur::query()
            ->where('role', 'commercial')
            ->find($id);

        abort_unless($utilisateur, 404);

        return $utilisateur;
    }

    private function storeAvatar(UploadedFile $file): string
    {
        try {
            $path = $file->store('utilisateurs', 'r2');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'avatar' => "Impossible d'envoyer la photo vers Cloudflare (ovl-delivery/utilisateurs).",
            ]);
        }

        if (! is_string($path) || ! str_starts_with($path, 'utilisateurs/')) {
            throw ValidationException::withMessages([
                'avatar' => "L'enregistrement de la photo vers Cloudflare R2 a échoué.",
            ]);
        }

        return $path;
    }

    private function deleteCustomAvatar(?string $key): void
    {
        if (! $key || in_array($key, self::DEFAULT_AVATARS, true)) {
            return;
        }

        try {
            Storage::disk('r2')->delete($key);
        } catch (\Throwable) {
            //
        }
    }
}
