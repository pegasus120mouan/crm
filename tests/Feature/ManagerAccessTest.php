<?php

namespace Tests\Feature;

use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\Paiement;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagerAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_manager_dashboard_shows_lease_payments_before_commercial_performance(): void
    {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->migrateLocationVente();

        $enRetard = $this->contratLocationVente([
            'date_debut' => '2026-09-01',
            'montant_echeance' => 10000,
            'prix_total' => 200000,
        ]);
        $this->paiementLocationVente($enRetard, 20000, '2026-10-15');
        $this->paiementLocationVente($enRetard, 15000, '2026-11-02');

        $autreRetard = $this->contratLocationVente([
            'date_debut' => '2026-10-01',
            'montant_echeance' => 5000,
            'prix_total' => 100000,
        ]);

        $aJour = $this->contratLocationVente([
            'date_debut' => '2026-11-03',
            'montant_echeance' => 10000,
            'prix_total' => 200000,
        ]);
        $this->paiementLocationVente($aJour, 25000, '2026-11-03');
        $this->paiementLocationVente($aJour, 40000, '2025-12-20');

        $enRetard->unsetRelation('paiements');
        $autreRetard->unsetRelation('paiements');
        unset($enRetard->paiements_sum_montant, $autreRetard->paiements_sum_montant);

        $this->withSession($this->sessionDe('manager'))
            ->get('/manager/dashboard')
            ->assertOk()
            ->assertSeeTextInOrder([
                'Location-vente',
                'Payé ce mois',
                '40 000 FCFA',
                'Payé cette année',
                '60 000 FCFA',
                'Retards de paiement',
                '2',
                number_format($enRetard->fresh()->retard() + $autreRetard->fresh()->retard(), 0, ',', ' ').' FCFA',
                'Performance des commerciaux',
            ]);
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

    private function migrateLocationVente(): void
    {
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_10_03_000001_create_location_vente_tables.php',
            'database/migrations/2026_10_03_000002_add_kyc_to_location_vente_livreurs.php',
            'database/migrations/2026_10_03_000003_set_livreur_statut_default_inactif.php',
            'database/migrations/2026_10_03_000004_make_moto_immatriculation_nullable.php',
            'database/migrations/2026_10_03_000005_add_price_breakdown_to_location_vente_contrats.php',
            'database/migrations/2026_10_15_000006_add_photo_carburant_cylindree_to_location_vente_motos.php',
            'database/migrations/2026_10_15_000007_add_duree_and_date_fin_to_location_vente_contrats.php',
            'database/migrations/2026_11_13_192510_add_credentials_to_location_vente_livreurs.php',
            'database/migrations/2026_11_14_133750_create_location_vente_moto_documents_table.php',
        ]]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function contratLocationVente(array $overrides = []): Contrat
    {
        $rang = (Livreur::query()->count() + 1);
        $livreur = Livreur::query()->create([
            'nom' => 'Kone',
            'prenoms' => 'Ibrahim',
            'contact' => '070102030'.$rang,
            'statut' => true,
        ]);
        $moto = Moto::query()->create([
            'marque' => 'Haojue',
            'modele' => 'HJ125',
            'immatriculation' => 'AB 100'.$rang.' CI',
            'statut' => Moto::STATUT_EN_LOCATION,
        ]);

        $contrat = Contrat::query()->create(array_merge([
            'livreur_id' => $livreur->id,
            'moto_id' => $moto->id,
            'prix_total' => 100000,
            'apport' => 0,
            'montant_echeance' => 10000,
            'frequence' => Contrat::FREQUENCE_HEBDOMADAIRE,
            'date_debut' => '2026-10-01',
            'statut' => Contrat::STATUT_EN_COURS,
        ], $overrides));
        $contrat->attribuerReference();

        return $contrat;
    }

    private function paiementLocationVente(Contrat $contrat, int $montant, string $date): void
    {
        Paiement::query()->create([
            'contrat_id' => $contrat->id,
            'montant' => $montant,
            'date_paiement' => $date,
            'type' => Paiement::TYPE_ECHEANCE,
            'mode' => 'Espèces',
        ]);
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
