<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Commune;
use App\Models\Utilisateur;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoutiqueManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('commandes');
        Schema::dropIfExists('utilisateurs');
        Schema::dropIfExists('boutiques');
        Schema::dropIfExists('communes');

        Schema::create('communes', function (Blueprint $table) {
            $table->increments('commune_id');
            $table->string('nom_commune');
        });

        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('logo')->nullable();
            $table->string('type_articles')->nullable();
            $table->boolean('statut')->default(true);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedInteger('commune_id')->nullable();
            $table->unsignedBigInteger('commercial_id')->nullable();
        });

        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('prenoms')->nullable();
            $table->string('contact')->nullable();
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
        });
    }

    public function test_guests_cannot_access_boutiques_page(): void
    {
        $this->get('/boutiques')->assertRedirect(route('login'));
    }

    public function test_commercial_can_view_boutiques_page(): void
    {
        $this->createCommune('Cocody');

        $this->actingAsCommercial()
            ->get('/boutiques')
            ->assertOk()
            ->assertSee('Gestion des boutiques')
            ->assertSee('Ajouter une boutique')
            ->assertSee('Commune')
            ->assertSee('Cocody');
    }

    public function test_commercial_can_create_a_boutique(): void
    {
        $commune = $this->createCommune();

        $this->actingAsCommercial()
            ->post('/boutiques', [
                'nom' => 'Boutique Cocody',
                'type_articles' => 'Vêtements',
                'statut' => 1,
                'commune_id' => $commune->commune_id,
            ])
            ->assertRedirect(route('boutiques.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('boutiques', [
            'nom' => 'Boutique Cocody',
            'type_articles' => 'Vêtements',
            'latitude' => null,
            'longitude' => null,
            'commune_id' => $commune->commune_id,
            'commercial_id' => 1,
        ]);
    }

    public function test_commercial_cannot_create_a_boutique_without_commune(): void
    {
        $this->actingAsCommercial()
            ->post('/boutiques', [
                'nom' => 'Boutique sans commune',
                'type_articles' => 'Vêtements',
                'statut' => 1,
            ])
            ->assertSessionHasErrors('commune_id');
    }

    public function test_commercial_can_create_a_boutique_with_coordinates(): void
    {
        $commune = $this->createCommune();

        $this->actingAsCommercial()
            ->post('/boutiques', [
                'nom' => 'Boutique Plateau',
                'type_articles' => 'Électronique',
                'statut' => 1,
                'latitude' => '5.33640000',
                'longitude' => '-4.02670000',
                'commune_id' => $commune->commune_id,
            ])
            ->assertRedirect(route('boutiques.index'));

        $boutique = Boutique::query()->where('nom', 'Boutique Plateau')->first();

        $this->assertNotNull($boutique);
        $this->assertEquals(5.3364, (float) $boutique->latitude);
        $this->assertEquals(-4.0267, (float) $boutique->longitude);
    }

    public function test_commercial_can_update_a_boutique(): void
    {
        $commune = $this->createCommune('Marcory');
        $boutique = Boutique::query()->create([
            'nom' => 'Ancien nom',
            'statut' => true,
            'commune_id' => $commune->commune_id,
            'commercial_id' => 1,
        ]);

        $this->actingAsCommercial()
            ->put("/boutiques/{$boutique->id}", [
                'nom' => 'Nouveau nom',
                'type_articles' => 'Chaussures',
                'statut' => 1,
                'commune_id' => $commune->commune_id,
            ])
            ->assertRedirect(route('boutiques.index'));

        $this->assertDatabaseHas('boutiques', [
            'id' => $boutique->id,
            'nom' => 'Nouveau nom',
            'type_articles' => 'Chaussures',
        ]);
    }

    public function test_commercial_can_delete_a_boutique_without_orders(): void
    {
        $boutique = Boutique::query()->create([
            'nom' => 'Boutique Test',
            'statut' => true,
            'commercial_id' => 1,
        ]);

        $this->actingAsCommercial()
            ->delete("/boutiques/{$boutique->id}")
            ->assertRedirect(route('boutiques.index'));

        $this->assertDatabaseMissing('boutiques', ['id' => $boutique->id]);
    }

    public function test_commercial_cannot_delete_a_boutique_with_orders(): void
    {
        $boutique = Boutique::query()->create([
            'nom' => 'Boutique Protégée',
            'statut' => true,
            'commercial_id' => 1,
        ]);

        $client = Utilisateur::query()->create([
            'nom' => 'Kone',
            'prenoms' => 'Awa',
            'login' => 'awa',
            'role' => 'clients',
            'boutique_id' => $boutique->id,
            'statut_compte' => true,
        ]);

        Commande::query()->create([
            'utilisateur_id' => $client->id,
            'communes' => 'Cocody',
            'cout_global' => 5000,
            'cout_livraison' => 1500,
            'cout_reel' => 3500,
            'statut' => 'Non Livré',
            'date_reception' => now()->toDateString(),
        ]);

        $this->actingAsCommercial()
            ->delete("/boutiques/{$boutique->id}")
            ->assertRedirect(route('boutiques.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('boutiques', ['id' => $boutique->id]);
    }

    public function test_commercial_sees_boutique_logo_from_r2(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('boutiques/dragonion.png', 'fake-logo-bytes');

        $commune = $this->createCommune();
        $boutique = Boutique::query()->create([
            'nom' => 'Dragonion',
            'logo' => 'boutiques/dragonion.png',
            'statut' => true,
            'commune_id' => $commune->commune_id,
            'commercial_id' => 1,
        ]);

        $this->actingAsCommercial()
            ->get('/boutiques')
            ->assertOk()
            ->assertSee('boutiques/dragonion.png', false);

        $this->actingAsCommercial()
            ->get(route('boutiques.logo', $boutique))
            ->assertOk()
            ->assertSee('fake-logo-bytes', false);
    }

    public function test_boutique_logo_falls_back_to_an_initial_when_missing(): void
    {
        Storage::fake('r2');

        $boutique = Boutique::query()->create([
            'nom' => 'Dragonion',
            'logo' => 'boutiques/missing.png',
            'statut' => true,
            'commercial_id' => 1,
        ]);

        $this->actingAsCommercial()
            ->get(route('boutiques.logo', $boutique))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('D', false);
    }

    public function test_commercial_can_open_boutique_profile_from_logo(): void
    {
        Storage::fake('r2');

        $commune = $this->createCommune();
        $boutique = Boutique::query()->create([
            'nom' => 'Dragonion',
            'type_articles' => 'Parfums',
            'statut' => true,
            'commune_id' => $commune->commune_id,
            'commercial_id' => 1,
        ]);

        $this->actingAsCommercial()
            ->get(route('boutiques.show', $boutique))
            ->assertOk()
            ->assertSee('Dragonion')
            ->assertSee('Changer le nom de la boutique')
            ->assertSee('Changer le Gérant')
            ->assertSee('Cliquez sur le logo');
    }

    public function test_commercial_can_update_boutique_logo_from_profile(): void
    {
        Storage::fake('r2');

        $boutique = Boutique::query()->create([
            'nom' => 'Dragonion',
            'logo' => 'boutiques/default_boutiques.png',
            'statut' => true,
            'commercial_id' => 1,
        ]);

        $this->actingAsCommercial()
            ->put(route('boutiques.update', $boutique), [
                'logo' => UploadedFile::fake()->image('nouveau-logo.png', 80, 80),
                'redirect_to' => 'show',
            ])
            ->assertRedirect(route('boutiques.show', $boutique));

        $boutique->refresh();
        $this->assertStringStartsWith('boutiques/', $boutique->logo);
        $this->assertNotSame('boutiques/default_boutiques.png', $boutique->logo);
        Storage::disk('r2')->assertExists($boutique->logo);
    }

    public function test_commercial_cannot_open_another_commercial_boutique_profile(): void
    {
        $boutique = Boutique::query()->create([
            'nom' => 'Boutique Etrangere',
            'statut' => true,
            'commercial_id' => 2,
        ]);

        $this->actingAsCommercial()
            ->get(route('boutiques.show', $boutique))
            ->assertNotFound();
    }

    public function test_commercial_only_sees_own_boutiques(): void
    {
        $commune = $this->createCommune();

        Boutique::query()->create([
            'nom' => 'Ma Boutique',
            'statut' => true,
            'commune_id' => $commune->commune_id,
            'commercial_id' => 1,
        ]);

        Boutique::query()->create([
            'nom' => 'Boutique Etrangere',
            'statut' => true,
            'commune_id' => $commune->commune_id,
            'commercial_id' => 2,
        ]);

        $this->actingAsCommercial()
            ->get('/boutiques')
            ->assertOk()
            ->assertSee('Ma Boutique')
            ->assertDontSee('Boutique Etrangere');
    }

    public function test_commercial_cannot_update_another_commercial_boutique(): void
    {
        $commune = $this->createCommune();
        $boutique = Boutique::query()->create([
            'nom' => 'Boutique Etrangere',
            'statut' => true,
            'commune_id' => $commune->commune_id,
            'commercial_id' => 2,
        ]);

        $this->actingAsCommercial()
            ->put("/boutiques/{$boutique->id}", [
                'nom' => 'Hacked',
                'statut' => 1,
                'commune_id' => $commune->commune_id,
            ])
            ->assertNotFound();
    }

    private function createCommune(string $name = 'Cocody'): Commune
    {
        return Commune::query()->create([
            'nom_commune' => $name,
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
