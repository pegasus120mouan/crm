<?php

namespace Tests\Feature;

use App\Models\LocationVente\Moto;
use App\Models\LocationVente\MotoDocument;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MotoDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
        Storage::fake(Moto::DISQUE);

        Schema::dropIfExists('utilisateurs');
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('prenoms')->nullable();
            $table->string('login')->nullable();
            $table->string('avatar')->nullable();
            $table->string('role')->nullable();
            $table->boolean('statut_compte')->default(true);
        });

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_document_is_associated_to_a_moto_from_the_documents_page(): void
    {
        $moto = Moto::create(['marque' => 'KTM', 'modele' => 'Duke', 'numero_chassis' => 'TMBY123', 'statut' => 'disponible']);

        $this->asManager()->get('/manager/motos')
            ->assertOk()
            ->assertSee(route('manager.documents.index'), false);

        $this->asManager()->get('/manager/documents')
            ->assertOk()
            ->assertSee('Associer un document')
            ->assertSee($moto->libelle());

        $this->asManager()->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'vignette',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'fichier' => UploadedFile::fake()->image('vignette.jpg'),
        ])->assertRedirect(route('manager.documents.index'));

        $this->asManager()->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'assurance',
            'date_debut' => '2026-10-01',
            'date_fin' => '2026-10-20',
        ])->assertSessionHasNoErrors();

        $this->asManager()->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'carte_grise',
            'numero' => 'ab 1234 ci',
            'fichier' => UploadedFile::fake()->createWithContent('carte-grise.pdf', '%PDF-1.4 carte grise'),
        ])->assertSessionHasNoErrors();

        $documents = $moto->fresh()->documentsParType();
        $this->assertSame('2026-12-31', $documents['vignette']->date_fin->toDateString());
        $this->assertTrue($documents['assurance']->expireBientot());
        $this->assertSame('AB 1234 CI', $documents['carte_grise']->numero);
        $this->assertSame(3, MotoDocument::count());

        $this->asManager()->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'vignette',
            'date_debut' => '2026-02-01',
            'date_fin' => '2026-11-30',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, MotoDocument::count());
        $frais = $moto->fresh();
        $this->assertSame('2026-11-30', $frais->documentsParType()['vignette']->date_fin->toDateString());
        $this->assertSame(58, $frais->documentsParType()['vignette']->joursRestants());
        $this->assertSame(17, $frais->documentsParType()['assurance']->joursRestants());

        $this->asManager()->get('/manager/documents')
            ->assertOk()
            ->assertSee("Plaque d'immatriculation", false)
            ->assertSee('Vignette Debut')
            ->assertSee('Assurance Debut')
            ->assertSee('Nombre de jour restants')
            ->assertSee('30-11-26')
            ->assertSee('20-10-26')
            ->assertSee('58 jours')
            ->assertSee('17 jours')
            ->assertSee(route('manager.motos.show', $moto), false)
            ->assertSee('TMBY123')
            ->assertSee('AB 1234 CI')
            ->assertSee('Disponible');

        $this->asManager()->get(route('manager.documents.fichier', $documents['carte_grise']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_clicking_the_chassis_opens_the_engin_page(): void
    {
        $moto = Moto::create([
            'marque' => 'KTM',
            'annee' => 1999,
            'couleur' => 'Noir',
            'numero_chassis' => 'LKXXCHL34G9610990',
            'immatriculation' => '1206KT01',
            'statut' => Moto::STATUT_MAINTENANCE,
            'date_acquisition' => '2024-06-11',
        ]);

        $this->asManager()->get(route('manager.motos.show', $moto))
            ->assertOk()
            ->assertSee('Infos engin')
            ->assertSee('LKXXCHL34G9610990')
            ->assertSee('En Panne')
            ->assertSee('1999')
            ->assertSee('1206KT01')
            ->assertSee('Noir')
            ->assertSee('2024-06-11')
            ->assertSee('Modifier les images de la moto')
            ->assertSee('Enregistrer les documents')
            ->assertSee('Date début')
            ->assertSee('Assurance')
            ->assertSee('Vignette')
            ->assertSee('Carte grise');

        $this->asManager()->from(route('manager.motos.show', $moto))
            ->post(route('manager.motos.papiers', $moto), [
                'documents' => [
                    'vignette' => [
                        'date_debut' => '2026-03-01',
                        'fichier' => UploadedFile::fake()->image('vignette.jpg'),
                    ],
                ],
            ])->assertSessionHasErrors('documents.vignette.date_fin');
        $this->assertSame(0, $moto->documents()->count());

        $this->asManager()->post(route('manager.motos.photos', $moto), [
            'photos' => ['droite' => UploadedFile::fake()->image('droite.jpg')],
        ])->assertRedirect(route('manager.motos.show', $moto));

        $photo = $moto->fresh()->photosParFace()['droite'];
        Storage::disk(Moto::DISQUE)->assertExists($photo->chemin);
        $this->asManager()->get(route('manager.motos.photo', [$moto, 'droite']))->assertOk();

        $this->asManager()->post(route('manager.motos.papiers', $moto), [
            'documents' => [
                'assurance' => [
                    'date_debut' => '2026-01-01',
                    'date_fin' => '2026-12-31',
                    'fichier' => UploadedFile::fake()->image('assurance.jpg'),
                ],
                'vignette' => [
                    'date_debut' => '2026-03-01',
                    'date_fin' => '2027-02-28',
                ],
                'carte_grise' => [
                    'numero' => 'ab-1234',
                    'fichier' => UploadedFile::fake()->image('carte.jpg'),
                ],
            ],
        ])->assertRedirect(route('manager.motos.show', $moto))
            ->assertSessionHas('success');

        $papiers = $moto->fresh()->documentsParType();
        $assurance = $papiers['assurance'];
        Storage::disk(Moto::DISQUE)->assertExists($assurance->chemin);
        $this->assertStringContainsString('/documents/', $assurance->chemin);
        Storage::disk(Moto::DISQUE)->assertExists($papiers['carte_grise']->chemin);
        $this->assertSame('AB-1234', $papiers['carte_grise']->numero);
        $this->assertSame('2026-03-01', $papiers['vignette']->date_debut->toDateString());

        $this->asManager()->get(route('manager.motos.show', $moto))
            ->assertOk()
            ->assertSee(route('manager.documents.fichier', $assurance), false)
            ->assertSee('01-01-26')
            ->assertSee('AB-1234');

        $this->asManager()->from(route('manager.motos.show', $moto))
            ->post(route('manager.motos.photos', $moto))
            ->assertSessionHas('error');

        $this->flushSession();
        $this->get(route('manager.motos.show', $moto))->assertRedirect(route('login'));
    }

    public function test_a_validity_period_must_be_complete_and_ordered(): void
    {
        $moto = Moto::create(['marque' => 'KTM', 'numero_chassis' => 'TMBY123', 'statut' => 'disponible']);

        $this->asManager()->from('/manager/documents')->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'vignette',
            'date_debut' => '2026-12-01',
            'date_fin' => '2026-01-01',
        ])->assertSessionHasErrors('date_fin');

        $this->asManager()->from('/manager/documents')->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'assurance',
            'date_debut' => '2026-01-01',
            'fichier' => UploadedFile::fake()->image('assurance.jpg'),
        ])->assertSessionHasErrors('date_fin');

        $this->asManager()->from('/manager/documents')->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'carte_grise',
        ])->assertSessionHasErrors('numero');

        $this->assertSame(0, MotoDocument::count());
        $this->assertSame([], Storage::disk(Moto::DISQUE)->allFiles());
    }

    public function test_updating_replaces_the_file_and_deleting_removes_only_that_document(): void
    {
        $moto = Moto::create(['marque' => 'KTM', 'numero_chassis' => 'TMBY123', 'statut' => 'disponible']);

        $this->asManager()->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'assurance',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'fichier' => UploadedFile::fake()->image('assurance.jpg'),
        ])->assertSessionHasNoErrors();
        $this->asManager()->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'carte_grise',
            'numero' => 'AB 1234 CI',
        ])->assertSessionHasNoErrors();

        $assurance = $moto->fresh()->documentsParType()['assurance'];

        $this->asManager()->put(route('manager.documents.update', $assurance), [
            'date_debut' => '2026-02-01',
            'date_fin' => '2026-09-01',
            'fichier' => UploadedFile::fake()->image('assurance-2.jpg'),
        ])->assertSessionHasNoErrors();

        $documents = $moto->fresh()->documentsParType();
        $this->assertSame('2026-09-01', $documents['assurance']->date_fin->toDateString());
        $this->assertSame('AB 1234 CI', $documents['carte_grise']->numero);
        $this->assertNotSame($assurance->chemin, $documents['assurance']->chemin);
        Storage::disk(Moto::DISQUE)->assertMissing($assurance->chemin);
        Storage::disk(Moto::DISQUE)->assertExists($documents['assurance']->chemin);

        $this->asManager()->from(route('manager.documents.index'))->put(route('manager.documents.update', $documents['assurance']), [
            'date_debut' => '',
            'date_fin' => '',
        ])->assertSessionHasErrors(['date_debut', 'date_fin']);
        $this->assertSame('2026-09-01', $moto->fresh()->documentsParType()['assurance']->date_fin->toDateString());

        $this->asManager()->delete(route('manager.documents.destroy', $documents['carte_grise']));
        $this->assertNull($moto->fresh()->documentsParType()['carte_grise'] ?? null);
        $this->assertNotNull($moto->fresh()->documentsParType()['assurance'] ?? null);
    }

    public function test_a_document_file_is_private_and_removed_with_the_moto(): void
    {
        $moto = Moto::create(['marque' => 'KTM', 'numero_chassis' => 'TMBY123', 'statut' => 'disponible']);
        $this->asManager()->post('/manager/documents', [
            'moto_id' => $moto->id,
            'type' => 'carte_grise',
            'numero' => 'AB 1234 CI',
            'fichier' => UploadedFile::fake()->createWithContent('carte-grise.pdf', '%PDF-1.4 carte grise'),
        ])->assertSessionHasNoErrors();

        $document = $moto->fresh()->documentsParType()['carte_grise'];

        $this->flushSession();
        $this->get('/manager/documents')->assertRedirect(route('login'));
        $this->get(route('manager.documents.fichier', $document))->assertRedirect(route('login'));

        $this->withSession(['utilisateur' => ['id' => 2, 'login' => 'pauline', 'role' => 'commercial']])
            ->get('/manager/documents')
            ->assertRedirect(route('dashboard'));

        $this->asManager()->delete(route('manager.motos.destroy', $moto));

        $this->assertSame(0, MotoDocument::count());
        $this->assertSame([], Storage::disk(Moto::DISQUE)->allFiles());
    }

    public function test_an_expired_document_is_marked_as_such(): void
    {
        $moto = Moto::create(['marque' => 'KTM', 'immatriculation' => 'AB 1234 CI', 'statut' => 'disponible']);
        $document = $moto->documents()->create([
            'type' => MotoDocument::TYPE_VIGNETTE,
            'date_debut' => '2025-01-01',
            'date_fin' => '2026-10-02',
        ]);

        $this->assertTrue($document->fresh()->estExpire());
        $this->assertSame(0, $document->fresh()->joursRestants());

        $this->asManager()->get('/manager/documents')
            ->assertOk()
            ->assertSee('bg-danger', false)
            ->assertSee('0 jours')
            ->assertSee('02-10-26');
    }

    private function asManager(): static
    {
        return $this->withSession(['utilisateur' => [
            'id' => 1,
            'nom' => 'Kouassi',
            'prenoms' => 'Marc',
            'login' => 'marc',
            'role' => 'manager',
        ]]);
    }
}
