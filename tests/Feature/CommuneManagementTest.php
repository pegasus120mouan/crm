<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Commune;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommuneManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('boutiques');
        Schema::dropIfExists('communes');

        Schema::create('communes', function (Blueprint $table) {
            $table->increments('commune_id');
            $table->string('nom_commune');
        });

        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->boolean('statut')->default(true);
            $table->unsignedInteger('commune_id')->nullable();
        });
    }

    public function test_guests_cannot_access_communes_page(): void
    {
        $this->get('/communes')->assertRedirect(route('login'));
    }

    public function test_commercial_can_view_communes_page(): void
    {
        $this->actingAsCommercial()
            ->get('/communes')
            ->assertOk()
            ->assertSee('Liste des communes')
            ->assertSee('Ajouter une commune');
    }

    public function test_commercial_can_create_a_commune(): void
    {
        $this->actingAsCommercial()
            ->post('/communes', [
                'nom_commune' => 'Cocody',
            ])
            ->assertRedirect(route('communes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('communes', [
            'nom_commune' => 'Cocody',
        ]);
    }

    public function test_commercial_can_update_a_commune(): void
    {
        $commune = Commune::query()->create([
            'nom_commune' => 'Ancien nom',
        ]);

        $this->actingAsCommercial()
            ->put("/communes/{$commune->commune_id}", [
                'nom_commune' => 'Nouveau nom',
            ])
            ->assertRedirect(route('communes.index'));

        $this->assertDatabaseHas('communes', [
            'commune_id' => $commune->commune_id,
            'nom_commune' => 'Nouveau nom',
        ]);
    }

    public function test_commercial_can_delete_a_commune_without_boutiques(): void
    {
        $commune = Commune::query()->create([
            'nom_commune' => 'Yopougon',
        ]);

        $this->actingAsCommercial()
            ->delete("/communes/{$commune->commune_id}")
            ->assertRedirect(route('communes.index'));

        $this->assertDatabaseMissing('communes', ['commune_id' => $commune->commune_id]);
    }

    public function test_commercial_cannot_delete_a_commune_with_boutiques(): void
    {
        $commune = Commune::query()->create([
            'nom_commune' => 'Plateau',
        ]);

        Boutique::query()->create([
            'nom' => 'Boutique Plateau',
            'statut' => true,
            'commune_id' => $commune->commune_id,
        ]);

        $this->actingAsCommercial()
            ->delete("/communes/{$commune->commune_id}")
            ->assertRedirect(route('communes.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('communes', ['commune_id' => $commune->commune_id]);
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
