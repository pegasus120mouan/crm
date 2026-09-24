<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialLoginTest extends TestCase
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

    public function test_login_page_is_displayed(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Bienvenue sur votre espace');
    }

    public function test_commercial_can_log_in(): void
    {
        $this->createUtilisateur();

        $this->from('/')
            ->post('/login', [
                'login' => 'pauline',
                'password' => 'secret',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('utilisateur.role', 'commercial');
    }

    public function test_admin_cannot_log_in_to_crm(): void
    {
        $this->createUtilisateur([
            'login' => 'admin',
            'role' => 'admin',
        ]);

        $this->from('/')
            ->post('/login', [
                'login' => 'admin',
                'password' => 'secret',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('utilisateur');
    }

    public function test_gestionnaire_cannot_log_in_to_crm(): void
    {
        $this->createUtilisateur([
            'login' => 'flash',
            'role' => 'gestionnaire',
        ]);

        $this->from('/')
            ->post('/login', [
                'login' => 'flash',
                'password' => 'secret',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('utilisateur');
    }

    public function test_disabled_commercial_cannot_log_in(): void
    {
        $this->createUtilisateur([
            'statut_compte' => false,
        ]);

        $this->from('/')
            ->post('/login', [
                'login' => 'pauline',
                'password' => 'secret',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('utilisateur');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->createUtilisateur();

        $this->from('/')
            ->post('/login', [
                'login' => 'pauline',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('utilisateur');
    }

    public function test_dashboard_requires_a_commercial_session(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_commercial_can_access_dashboard(): void
    {
        $this->withSession([
            'utilisateur' => [
                'id' => 1,
                'nom' => 'Doe',
                'prenoms' => 'Pauline',
                'login' => 'pauline',
                'role' => 'commercial',
            ],
        ])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_commercial_can_log_out(): void
    {
        $this->withSession([
            'utilisateur' => [
                'id' => 1,
                'login' => 'pauline',
                'role' => 'commercial',
            ],
        ])
            ->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('utilisateur');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUtilisateur(array $overrides = []): Utilisateur
    {
        return Utilisateur::query()->create(array_merge([
            'nom' => 'Doe',
            'prenoms' => 'Pauline',
            'contact' => '0700000000',
            'login' => 'pauline',
            'avatar' => 'default.jpg',
            'password' => hash('sha256', 'secret'),
            'role' => 'commercial',
            'statut_compte' => true,
        ], $overrides));
    }
}
