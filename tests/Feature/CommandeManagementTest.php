<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Commande;
use App\Models\CoutLivraison;
use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommandeManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('commandes');
        Schema::dropIfExists('utilisateurs');
        Schema::dropIfExists('boutiques');
        Schema::dropIfExists('cout_livraison');

        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->boolean('statut')->default(true);
        });

        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('prenoms')->nullable();
            $table->string('login')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->unsignedBigInteger('commercial_id')->nullable();
            $table->boolean('statut_compte')->default(true);
        });

        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('utilisateur_id')->nullable();
            $table->unsignedBigInteger('livreur_id')->nullable();
            $table->string('communes')->nullable();
            $table->integer('cout_global')->default(0);
            $table->integer('cout_livraison')->default(0);
            $table->integer('cout_reel')->default(0);
            $table->string('statut')->default('Non Livré');
            $table->date('date_reception')->nullable();
            $table->date('date_livraison')->nullable();
            $table->date('date_retour')->nullable();
        });

        Schema::create('cout_livraison', function (Blueprint $table) {
            $table->id();
            $table->integer('cout_livraison');
        });

        $this->createCoutLivraison(1500);
        $this->createCoutLivraison(2000);
    }

    public function test_guests_cannot_access_commandes_page(): void
    {
        $this->get('/commandes')->assertRedirect(route('login'));
    }

    public function test_commercial_can_view_commandes_page(): void
    {
        $this->actingAsCommercial()
            ->get('/commandes')
            ->assertOk()
            ->assertSee('Gestion des commandes')
            ->assertSee('Ajouter une commande')
            ->assertSee('Communes')
            ->assertSee('Coût Global')
            ->assertSee('Livraison')
            ->assertSee('Coût réel')
            ->assertSee('Date réception')
            ->assertSee('Date livraison')
            ->assertSee('Date Retour')
            ->assertSee('1 500 FCFA')
            ->assertSee('2 000 FCFA');
    }

    public function test_dashboard_contains_new_menus(): void
    {
        $this->actingAsCommercial()
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Gestion des commandes')
            ->assertSee('Gestion des boutiques');
    }

    public function test_commercial_can_create_a_commande(): void
    {
        $boutique = Boutique::query()->create(['nom' => 'Shop', 'statut' => true]);
        $client = $this->createClient($boutique->id);
        $livreur = $this->createLivreur();

        $this->actingAsCommercial()
            ->post('/commandes', [
                'utilisateur_id' => $client->id,
                'livreur_id' => $livreur->id,
                'communes' => 'Cocody',
                'cout_global' => 8000,
                'cout_livraison' => 1500,
                'date_reception' => '2026-09-18',
            ])
            ->assertRedirect(route('commandes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('commandes', [
            'utilisateur_id' => $client->id,
            'livreur_id' => $livreur->id,
            'communes' => 'Cocody',
            'cout_reel' => 6500,
            'statut' => 'Non Livré',
        ]);
    }

    public function test_commercial_cannot_create_a_commande_for_a_non_client(): void
    {
        $livreur = $this->createLivreur();

        $this->actingAsCommercial()
            ->from('/commandes')
            ->post('/commandes', [
                'utilisateur_id' => $livreur->id,
                'communes' => 'Cocody',
                'cout_global' => 8000,
                'cout_livraison' => 1500,
                'date_reception' => '2026-09-18',
            ])
            ->assertRedirect('/commandes')
            ->assertSessionHasErrors('utilisateur_id');
    }

    public function test_commercial_can_update_a_commande_status(): void
    {
        $client = $this->createClient();
        $commande = Commande::query()->create([
            'utilisateur_id' => $client->id,
            'communes' => 'Cocody',
            'cout_global' => 8000,
            'cout_livraison' => 1500,
            'cout_reel' => 6500,
            'statut' => 'Non Livré',
            'date_reception' => '2026-09-18',
        ]);

        $this->actingAsCommercial()
            ->put("/commandes/{$commande->id}", [
                'utilisateur_id' => $client->id,
                'communes' => 'Plateau',
                'cout_global' => 9000,
                'cout_livraison' => 1500,
                'statut' => 'Livré',
                'date_reception' => '2026-09-18',
            ])
            ->assertRedirect(route('commandes.index'));

        $commande->refresh();

        $this->assertSame('Livré', $commande->statut);
        $this->assertSame('Plateau', $commande->communes);
        $this->assertSame(7500, $commande->cout_reel);
        $this->assertNotNull($commande->date_livraison);
    }

    public function test_commercial_can_assign_a_livreur_to_a_commande(): void
    {
        $client = $this->createClient();
        $livreur = $this->createLivreur();
        $commande = Commande::query()->create([
            'utilisateur_id' => $client->id,
            'communes' => 'Bingerville',
            'cout_global' => 25000,
            'cout_livraison' => 1500,
            'cout_reel' => 23500,
            'statut' => 'Non Livré',
            'date_reception' => '2026-09-18',
        ]);

        $this->actingAsCommercial()
            ->patch("/commandes/{$commande->id}/livreur", [
                'livreur_id' => $livreur->id,
            ])
            ->assertRedirect(route('commandes.index'))
            ->assertSessionHas('success');

        $this->assertSame($livreur->id, $commande->fresh()->livreur_id);
    }

    public function test_commercial_cannot_assign_a_livreur_to_another_commercial_commande(): void
    {
        $livreur = $this->createLivreur();
        $otherClient = Utilisateur::query()->create([
            'nom' => 'Other',
            'prenoms' => 'Client',
            'login' => 'other.client',
            'role' => 'clients',
            'commercial_id' => 2,
            'statut_compte' => true,
        ]);
        $commande = Commande::query()->create([
            'utilisateur_id' => $otherClient->id,
            'communes' => 'Yopougon',
            'cout_global' => 4000,
            'cout_livraison' => 1000,
            'cout_reel' => 3000,
            'statut' => 'Non Livré',
            'date_reception' => '2026-09-18',
        ]);

        $this->actingAsCommercial()
            ->patch("/commandes/{$commande->id}/livreur", [
                'livreur_id' => $livreur->id,
            ])
            ->assertNotFound();

        $this->assertNull($commande->fresh()->livreur_id);
    }

    public function test_commercial_cannot_assign_a_non_livreur(): void
    {
        $client = $this->createClient();
        $commande = Commande::query()->create([
            'utilisateur_id' => $client->id,
            'communes' => 'Cocody',
            'cout_global' => 8000,
            'cout_livraison' => 1500,
            'cout_reel' => 6500,
            'statut' => 'Non Livré',
            'date_reception' => '2026-09-18',
        ]);

        $this->actingAsCommercial()
            ->from('/commandes')
            ->patch("/commandes/{$commande->id}/livreur", [
                'livreur_id' => $client->id,
            ])
            ->assertRedirect('/commandes')
            ->assertSessionHasErrors('livreur_id');
    }

    public function test_commandes_page_shows_assign_livreur_action(): void
    {
        $this->actingAsCommercial()
            ->get('/commandes')
            ->assertOk()
            ->assertSee('Attribuer un livreur');
    }

    public function test_commercial_can_delete_a_commande(): void
    {
        $client = $this->createClient();
        $commande = Commande::query()->create([
            'utilisateur_id' => $client->id,
            'communes' => 'Cocody',
            'cout_global' => 8000,
            'cout_livraison' => 1500,
            'cout_reel' => 6500,
            'statut' => 'Non Livré',
            'date_reception' => '2026-09-18',
        ]);

        $this->actingAsCommercial()
            ->delete("/commandes/{$commande->id}")
            ->assertRedirect(route('commandes.index'));

        $this->assertDatabaseMissing('commandes', ['id' => $commande->id]);
    }

    public function test_commercial_only_sees_own_clients_orders(): void
    {
        $ownClient = $this->createClient();
        $otherClient = Utilisateur::query()->create([
            'nom' => 'Other',
            'prenoms' => 'Client',
            'login' => 'other.client',
            'role' => 'clients',
            'commercial_id' => 2,
            'statut_compte' => true,
        ]);

        Commande::query()->create([
            'utilisateur_id' => $ownClient->id,
            'communes' => 'Cocody',
            'cout_global' => 8000,
            'cout_livraison' => 1500,
            'cout_reel' => 6500,
            'statut' => 'Non Livré',
            'date_reception' => '2026-09-18',
        ]);

        Commande::query()->create([
            'utilisateur_id' => $otherClient->id,
            'communes' => 'Yopougon Secrete',
            'cout_global' => 4000,
            'cout_livraison' => 1000,
            'cout_reel' => 3000,
            'statut' => 'Non Livré',
            'date_reception' => '2026-09-18',
        ]);

        $this->actingAsCommercial()
            ->get('/commandes')
            ->assertOk()
            ->assertSee('Cocody')
            ->assertSee('8 000')
            ->assertSee('1 500')
            ->assertSee('6 500')
            ->assertSee('Pas encore livré')
            ->assertDontSee('Yopougon Secrete');
    }

    public function test_commercial_cannot_create_a_commande_with_unknown_cout_livraison(): void
    {
        $client = $this->createClient();

        $this->actingAsCommercial()
            ->from('/commandes')
            ->post('/commandes', [
                'utilisateur_id' => $client->id,
                'communes' => 'Cocody',
                'cout_global' => 8000,
                'cout_livraison' => 9999,
                'date_reception' => '2026-09-18',
            ])
            ->assertRedirect('/commandes')
            ->assertSessionHasErrors('cout_livraison');
    }

    public function test_commercial_cannot_create_a_commande_for_another_commercial_client(): void
    {
        $otherClient = Utilisateur::query()->create([
            'nom' => 'Other',
            'prenoms' => 'Client',
            'login' => 'other.client',
            'role' => 'clients',
            'commercial_id' => 2,
            'statut_compte' => true,
        ]);

        $this->actingAsCommercial()
            ->from('/commandes')
            ->post('/commandes', [
                'utilisateur_id' => $otherClient->id,
                'communes' => 'Cocody',
                'cout_global' => 8000,
                'cout_livraison' => 1500,
                'date_reception' => '2026-09-18',
            ])
            ->assertRedirect('/commandes')
            ->assertSessionHasErrors('utilisateur_id');
    }

    private function createClient(?int $boutiqueId = null): Utilisateur
    {
        return Utilisateur::query()->create([
            'nom' => 'Kone',
            'prenoms' => 'Awa',
            'login' => 'awa',
            'role' => 'clients',
            'boutique_id' => $boutiqueId,
            'commercial_id' => 1,
            'statut_compte' => true,
        ]);
    }

    private function createCoutLivraison(int $montant): CoutLivraison
    {
        return CoutLivraison::query()->create([
            'cout_livraison' => $montant,
        ]);
    }

    private function createLivreur(): Utilisateur
    {
        return Utilisateur::query()->create([
            'nom' => 'Yao',
            'prenoms' => 'Marc',
            'login' => 'marc',
            'role' => 'livreur',
            'statut_compte' => true,
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
