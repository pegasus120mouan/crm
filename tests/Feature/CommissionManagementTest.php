<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Commission;
use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommissionManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('paiements_commissions');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commandes');
        Schema::dropIfExists('utilisateurs');

        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('prenoms')->nullable();
            $table->string('login')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('commercial_id')->nullable();
            $table->boolean('statut_compte')->default(true);
        });

        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('utilisateur_id')->nullable();
            $table->string('communes')->nullable();
            $table->integer('cout_global')->default(0);
            $table->integer('cout_livraison')->default(0);
            $table->integer('cout_reel')->default(0);
            $table->string('statut')->default('Non Livré');
            $table->date('date_reception')->nullable();
            $table->date('date_livraison')->nullable();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->decimal('taux', 5, 2);
        });

        Schema::create('paiements_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commercial_id');
            $table->date('periode');
            $table->integer('montant')->default(0);
            $table->date('date_paiement')->nullable();
        });
    }

    public function test_guests_cannot_access_commissions_page(): void
    {
        $this->get('/commissions')->assertRedirect(route('login'));
    }

    public function test_commercial_can_view_commissions_page(): void
    {
        $this->actingAsCommercial()
            ->get('/commissions')
            ->assertOk()
            ->assertSee('Gestion des commissions')
            ->assertSee('Gains par mois')
            ->assertSee('défini par l’administrateur')
            ->assertDontSee('Modifier le taux')
            ->assertDontSee('Programmer la commission');
    }

    public function test_dashboard_contains_commissions_menu(): void
    {
        $this->actingAsCommercial()
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Gestion des commissions');
    }

    public function test_commercial_cannot_program_a_commission_rate(): void
    {
        $this->actingAsCommercial()
            ->post('/commissions', [
                'taux' => 10,
            ])
            ->assertMethodNotAllowed();

        $this->assertSame(0, Commission::query()->count());
    }

    public function test_commercial_cannot_update_the_existing_rate(): void
    {
        Commission::query()->create([
            'taux' => 10,
        ]);

        $this->actingAsCommercial()
            ->post('/commissions', [
                'taux' => 12.5,
            ])
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('commissions', [
            'taux' => 10,
        ]);
    }

    public function test_commission_is_calculated_on_delivered_packages_only(): void
    {
        $this->createOwnedCommande('Livré', 25000, 'Bingerville');
        $this->createOwnedCommande('Non Livré', 40000, 'Cocody');
        $this->createForeignDeliveredCommande();

        Commission::query()->create([
            'taux' => 10,
        ]);

        $this->actingAsCommercial()
            ->get('/commissions')
            ->assertOk()
            ->assertSee('Gains par mois')
            ->assertSee('septembre 2026')
            ->assertSee('150 FCFA')
            ->assertDontSee('1 500 FCFA')
            ->assertSee('À payer')
            ->assertDontSee('2 500 FCFA')
            ->assertDontSee('Cocody')
            ->assertDontSee('Yopougon')
            ->assertDontSee('Bingerville');
    }

    public function test_commissions_are_grouped_month_by_month(): void
    {
        $this->createOwnedCommande('Livré', 25000, 'Septembre', '2026-09-19');
        $this->createOwnedCommande('Livré', 18000, 'Aout', '2026-08-10', 2000);

        Commission::query()->create([
            'taux' => 10,
        ]);

        $this->actingAsCommercial()
            ->get('/commissions')
            ->assertOk()
            ->assertSee('septembre 2026')
            ->assertSee('août 2026')
            ->assertSee('150 FCFA')
            ->assertSee('200 FCFA')
            ->assertSee('350 FCFA');
    }

    public function test_commercial_sees_the_global_rate(): void
    {
        Commission::query()->create([
            'taux' => 33,
        ]);

        $this->actingAsCommercial()
            ->get('/commissions')
            ->assertOk()
            ->assertSee('33 %');
    }

    private function createOwnedCommande(
        string $statut = 'Livré',
        int $coutGlobal = 25000,
        string $commune = 'Bingerville',
        string $dateLivraison = '2026-09-19',
        int $coutLivraison = 1500
    ): Commande {
        $client = Utilisateur::query()->create([
            'nom' => 'Yao',
            'prenoms' => 'Fisher',
            'login' => 'yao.fisher.'.$commune,
            'role' => 'clients',
            'commercial_id' => 1,
            'statut_compte' => true,
        ]);

        return Commande::query()->create([
            'utilisateur_id' => $client->id,
            'communes' => $commune,
            'cout_global' => $coutGlobal,
            'cout_livraison' => $coutLivraison,
            'cout_reel' => $coutGlobal - $coutLivraison,
            'statut' => $statut,
            'date_reception' => $dateLivraison,
            'date_livraison' => $statut === 'Livré' ? $dateLivraison : null,
        ]);
    }

    private function createForeignDeliveredCommande(): Commande
    {
        $client = Utilisateur::query()->create([
            'nom' => 'Other',
            'prenoms' => 'Client',
            'login' => 'other.client',
            'role' => 'clients',
            'commercial_id' => 2,
            'statut_compte' => true,
        ]);

        return Commande::query()->create([
            'utilisateur_id' => $client->id,
            'communes' => 'Yopougon',
            'cout_global' => 10000,
            'cout_livraison' => 1500,
            'cout_reel' => 8500,
            'statut' => 'Livré',
            'date_reception' => '2026-09-18',
            'date_livraison' => '2026-09-19',
        ]);
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
