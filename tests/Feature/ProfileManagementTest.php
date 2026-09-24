<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('utilisateurs');
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('prenoms')->nullable();
            $table->string('contact')->nullable();
            $table->string('login')->nullable();
            $table->string('avatar')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->boolean('statut_compte')->default(true);
        });
    }

    public function test_profile_page_opens_from_the_photo_link(): void
    {
        $utilisateur = $this->createCommercial();

        $this->withSession($this->sessionFor($utilisateur))
            ->get(route('profil.show'))
            ->assertOk()
            ->assertSee('Mon profil')
            ->assertSee('Pauline')
            ->assertSee('Doe')
            ->assertSee('0700000000')
            ->assertSee(route('profil.show'), false);
    }

    public function test_commercial_can_update_name_and_contact(): void
    {
        $utilisateur = $this->createCommercial();

        $this->withSession($this->sessionFor($utilisateur))
            ->put(route('profil.update'), [
                'nom' => 'Kouassi',
                'prenoms' => 'Awa',
                'contact' => '0102030405',
            ])
            ->assertRedirect(route('profil.show'));

        $utilisateur->refresh();

        $this->assertSame('Kouassi', $utilisateur->nom);
        $this->assertSame('Awa', $utilisateur->prenoms);
        $this->assertSame('0102030405', $utilisateur->contact);
        $this->assertSame('Kouassi', session('utilisateur.nom'));
        $this->assertSame('Awa', session('utilisateur.prenoms'));
    }

    public function test_commercial_can_change_password(): void
    {
        $utilisateur = $this->createCommercial();

        $this->withSession($this->sessionFor($utilisateur))
            ->put(route('profil.update'), [
                'nom' => $utilisateur->nom,
                'prenoms' => $utilisateur->prenoms,
                'contact' => $utilisateur->contact,
                'password' => 'nouveau',
                'password_confirmation' => 'nouveau',
            ])
            ->assertRedirect(route('profil.show'));

        $utilisateur->refresh();

        $this->assertSame(hash('sha256', 'nouveau'), $utilisateur->password);
    }

    public function test_guest_cannot_open_profile(): void
    {
        $this->get(route('profil.show'))->assertRedirect(route('login'));
    }

    private function createCommercial(): Utilisateur
    {
        return Utilisateur::query()->create([
            'nom' => 'Doe',
            'prenoms' => 'Pauline',
            'contact' => '0700000000',
            'login' => 'pauline',
            'avatar' => 'default.jpg',
            'password' => hash('sha256', 'secret'),
            'role' => 'commercial',
            'statut_compte' => true,
        ]);
    }

    /**
     * @return array{utilisateur: array<string, mixed>}
     */
    private function sessionFor(Utilisateur $utilisateur): array
    {
        return [
            'utilisateur' => [
                'id' => $utilisateur->id,
                'nom' => $utilisateur->nom,
                'prenoms' => $utilisateur->prenoms,
                'login' => $utilisateur->login,
                'role' => 'commercial',
                'avatar' => $utilisateur->avatar,
            ],
        ];
    }
}
