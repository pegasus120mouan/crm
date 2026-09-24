<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('utilisateurs');
        Schema::dropIfExists('boutiques');

        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('logo')->nullable();
            $table->string('type_articles')->nullable();
            $table->boolean('statut')->default(true);
            $table->unsignedBigInteger('commercial_id')->nullable();
        });

        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('prenoms')->nullable();
            $table->string('contact')->nullable();
            $table->string('login')->nullable();
            $table->string('avatar')->nullable();
            $table->string('password')->nullable();
            $table->string('code_pin')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->unsignedBigInteger('commercial_id')->nullable();
            $table->boolean('statut_compte')->default(true);
        });
    }

    public function test_guests_cannot_access_clients_page(): void
    {
        $this->get('/clients')->assertRedirect(route('login'));
    }

    public function test_commercial_can_view_clients_page(): void
    {
        $this->actingAsCommercial()
            ->get('/clients')
            ->assertOk()
            ->assertSee('Gestion des clients')
            ->assertSee('Ajouter un client');
    }

    public function test_dashboard_contains_clients_menu(): void
    {
        $this->actingAsCommercial()
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Gestion des clients');
    }

    public function test_commercial_can_create_a_client(): void
    {
        $boutique = Boutique::query()->create([
            'nom' => 'Boutique Test',
            'statut' => true,
            'commercial_id' => 1,
        ]);

        $this->actingAsCommercial()
            ->post('/clients', [
                'nom' => 'Kone',
                'prenoms' => 'Awa',
                'contact' => '0700000011',
                'login' => 'awa.kone',
                'password' => 'secret',
                'boutique_id' => $boutique->id,
                'statut_compte' => 1,
            ])
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('utilisateurs', [
            'login' => 'awa.kone',
            'role' => 'clients',
            'nom' => 'Kone',
            'boutique_id' => $boutique->id,
            'commercial_id' => 1,
        ]);
    }

    public function test_commercial_cannot_create_a_client_with_duplicate_login(): void
    {
        Utilisateur::query()->create([
            'nom' => 'Exist',
            'prenoms' => 'Client',
            'contact' => '0700000012',
            'login' => 'awa.kone',
            'password' => hash('sha256', 'secret'),
            'role' => 'clients',
            'statut_compte' => true,
        ]);

        $this->actingAsCommercial()
            ->from('/clients')
            ->post('/clients', [
                'nom' => 'Kone',
                'prenoms' => 'Awa',
                'contact' => '0700000011',
                'login' => 'awa.kone',
                'password' => 'secret',
                'statut_compte' => 1,
            ])
            ->assertRedirect('/clients')
            ->assertSessionHasErrors('login');
    }

    public function test_commercial_can_update_a_client(): void
    {
        $client = Utilisateur::query()->create([
            'nom' => 'Kone',
            'prenoms' => 'Awa',
            'contact' => '0700000011',
            'login' => 'awa.kone',
            'password' => hash('sha256', 'secret'),
            'role' => 'clients',
            'commercial_id' => 1,
            'statut_compte' => true,
        ]);

        $this->actingAsCommercial()
            ->put("/clients/{$client->id}", [
                'nom' => 'Traore',
                'prenoms' => 'Awa',
                'contact' => '0700000011',
                'statut_compte' => 1,
            ])
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('utilisateurs', [
            'id' => $client->id,
            'nom' => 'Traore',
            'role' => 'clients',
        ]);
    }

    public function test_commercial_cannot_update_a_non_client_user(): void
    {
        $admin = Utilisateur::query()->create([
            'nom' => 'Admin',
            'prenoms' => 'User',
            'contact' => '0700000099',
            'login' => 'admin-user',
            'password' => hash('sha256', 'secret'),
            'role' => 'admin',
            'statut_compte' => true,
        ]);

        $this->actingAsCommercial()
            ->put("/clients/{$admin->id}", [
                'nom' => 'Hacked',
                'prenoms' => 'User',
                'contact' => '0700000099',
                'statut_compte' => 1,
            ])
            ->assertNotFound();
    }

    public function test_commercial_can_delete_a_client(): void
    {
        $client = Utilisateur::query()->create([
            'nom' => 'Kone',
            'prenoms' => 'Awa',
            'contact' => '0700000011',
            'login' => 'awa.kone',
            'password' => hash('sha256', 'secret'),
            'role' => 'clients',
            'commercial_id' => 1,
            'statut_compte' => true,
        ]);

        $this->actingAsCommercial()
            ->delete("/clients/{$client->id}")
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseMissing('utilisateurs', [
            'id' => $client->id,
        ]);
    }

    public function test_commercial_only_sees_own_clients(): void
    {
        Utilisateur::query()->create([
            'nom' => 'Mine',
            'prenoms' => 'Client',
            'contact' => '0700000011',
            'login' => 'mine.client',
            'password' => hash('sha256', 'secret'),
            'role' => 'clients',
            'commercial_id' => 1,
            'statut_compte' => true,
        ]);

        Utilisateur::query()->create([
            'nom' => 'Other',
            'prenoms' => 'Client',
            'contact' => '0700000022',
            'login' => 'other.client',
            'password' => hash('sha256', 'secret'),
            'role' => 'clients',
            'commercial_id' => 2,
            'statut_compte' => true,
        ]);

        $this->actingAsCommercial()
            ->get('/clients')
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Other');
    }

    public function test_commercial_cannot_update_another_commercial_client(): void
    {
        $client = Utilisateur::query()->create([
            'nom' => 'Other',
            'prenoms' => 'Client',
            'contact' => '0700000022',
            'login' => 'other.client',
            'password' => hash('sha256', 'secret'),
            'role' => 'clients',
            'commercial_id' => 2,
            'statut_compte' => true,
        ]);

        $this->actingAsCommercial()
            ->put("/clients/{$client->id}", [
                'nom' => 'Hacked',
                'prenoms' => 'Client',
                'contact' => '0700000022',
                'statut_compte' => 1,
            ])
            ->assertNotFound();
    }

    private function actingAsCommercial(): self
    {
        return $this->withSession([
            'utilisateur' => [
                'id' => 1,
                'nom' => 'Doe',
                'prenoms' => 'Pauline',
                'login' => 'pauline',
                'role' => 'commercial',
            ],
        ]);
    }
}
