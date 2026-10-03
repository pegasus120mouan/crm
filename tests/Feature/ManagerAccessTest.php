<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagerAccessTest extends TestCase
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
            $table->string('code_commercial')->nullable();
            $table->string('avatar')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->unsignedBigInteger('commercial_id')->nullable();
            $table->boolean('statut_compte')->default(true);
        });

        Schema::dropIfExists('boutiques');
        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->boolean('statut')->default(true);
            $table->unsignedBigInteger('commercial_id')->nullable();
        });

        Schema::dropIfExists('commandes');
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('utilisateur_id');
            $table->string('communes')->nullable();
            $table->integer('cout_livraison')->default(0);
            $table->string('statut')->nullable();
            $table->date('date_reception')->nullable();
            $table->date('date_livraison')->nullable();
        });
    }

    public function test_manager_can_log_in_and_lands_on_manager_dashboard(): void
    {
        $this->createUtilisateur(['login' => 'marc', 'role' => 'manager']);

        $this->from('/')
            ->post('/login', [
                'login' => 'marc',
                'password' => 'secret',
            ])
            ->assertRedirect(route('manager.dashboard'))
            ->assertSessionHas('utilisateur.role', 'manager');
    }

    public function test_disabled_manager_cannot_log_in(): void
    {
        $this->createUtilisateur(['login' => 'marc', 'role' => 'manager', 'statut_compte' => false]);

        $this->from('/')
            ->post('/login', [
                'login' => 'marc',
                'password' => 'secret',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('utilisateur');
    }

    public function test_manager_dashboard_shows_team_figures(): void
    {
        $commercial = $this->createUtilisateur(['login' => 'pauline', 'role' => 'commercial', 'code_commercial' => 'COM-001']);
        $client = $this->createUtilisateur(['login' => 'boutique', 'role' => 'clients', 'commercial_id' => $commercial->id]);
        DB::table('boutiques')->insert(['nom' => 'Uniko', 'statut' => 1, 'commercial_id' => $commercial->id]);
        DB::table('commandes')->insert([
            ['utilisateur_id' => $client->id, 'communes' => 'Cocody', 'cout_livraison' => 1500, 'statut' => 'Livré', 'date_reception' => now()->toDateString(), 'date_livraison' => now()->toDateString()],
            ['utilisateur_id' => $client->id, 'communes' => 'Yopougon', 'cout_livraison' => 2000, 'statut' => 'Retour', 'date_reception' => now()->toDateString(), 'date_livraison' => null],
        ]);

        $this->withSession($this->sessionDe('manager'))
            ->get('/manager/dashboard')
            ->assertOk()
            ->assertSee('Performance des commerciaux')
            ->assertSee('Pauline Doe')
            ->assertSee('COM-001')
            ->assertSee('50%');
    }

    public function test_manager_cannot_open_commercial_pages(): void
    {
        $this->withSession($this->sessionDe('manager'))
            ->get('/dashboard')
            ->assertRedirect(route('manager.dashboard'));
    }

    public function test_commercial_cannot_open_manager_dashboard(): void
    {
        $this->withSession($this->sessionDe('commercial'))
            ->get('/manager/dashboard')
            ->assertRedirect(route('dashboard'));
    }

    public function test_manager_dashboard_requires_a_session(): void
    {
        $this->get('/manager/dashboard')->assertRedirect(route('login'));
    }

    public function test_manager_can_log_out(): void
    {
        $this->withSession($this->sessionDe('manager'))
            ->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('utilisateur');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sessionDe(string $role): array
    {
        return [
            'utilisateur' => [
                'id' => 99,
                'nom' => 'Kouassi',
                'prenoms' => 'Marc',
                'login' => 'marc',
                'role' => $role,
            ],
        ];
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
