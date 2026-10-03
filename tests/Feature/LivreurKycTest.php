<?php

namespace Tests\Feature;

use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\LivreurDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LivreurKycTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

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
        ]]);
    }

    public function test_new_livreur_gets_a_code_and_a_complete_kyc_is_waiting_for_review(): void
    {
        $this->asManager()->post('/manager/livreurs', $this->donneesCompletes())
            ->assertRedirect(route('manager.livreurs.show', 1));

        $livreur = Livreur::firstOrFail();

        $this->assertSame('LVR-0001', $livreur->code);
        $this->assertSame(Livreur::KYC_EN_ATTENTE, $livreur->kyc_statut);
        $this->assertSame([], $livreur->elementsKycManquants());
        Storage::disk('r2')->assertExists($livreur->photo);
        $this->assertSame(4, $livreur->documents()->count());
        foreach ($livreur->documents as $document) {
            $this->assertStringStartsWith('location-vente/livreurs/1/', $document->chemin);
            Storage::disk('r2')->assertExists($document->chemin);
        }
    }

    public function test_missing_documents_keep_the_kyc_incomplete(): void
    {
        $this->asManager()->post('/manager/livreurs', [
            'nom' => 'Kone',
            'prenoms' => 'Ibrahim',
            'contact' => '0701020304',
            'statut' => 1,
            'numero_piece' => 'CI0012345',
        ]);

        $livreur = Livreur::firstOrFail();

        $this->assertSame(Livreur::KYC_INCOMPLET, $livreur->kyc_statut);
        $this->assertContains('Photo du livreur', $livreur->elementsKycManquants());
        $this->assertContains('Permis de conduire – recto', $livreur->elementsKycManquants());
        $this->assertNotContains("Numéro de la pièce d'identité", $livreur->elementsKycManquants());
    }

    public function test_new_livreur_is_inactive_by_default(): void
    {
        $livreur = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505']);

        $this->assertFalse($livreur->fresh()->statut);
        $this->asManager()->get('/manager/livreurs')
            ->assertOk()
            ->assertSeeInOrder(['Photo', 'Nom', 'Prénoms', 'Contact', 'Contrat en cours', 'Reste à payer', 'Statut'])
            ->assertSee('Inactif');
    }

    public function test_licence_can_cover_all_categories(): void
    {
        $this->asManager()->post('/manager/livreurs', [
            'nom' => 'Kone',
            'prenoms' => 'Ibrahim',
            'contact' => '0701020304',
            'statut' => 0,
            'categorie_permis' => 'TOUTES',
        ])->assertSessionHasNoErrors();

        $livreur = Livreur::firstOrFail();

        $this->assertSame('TOUTES', $livreur->categorie_permis);
        $this->asManager()->get(route('manager.livreurs.show', $livreur))
            ->assertOk()
            ->assertSee('Toutes les catégories');
    }

    public function test_manager_decides_to_accept_a_kyc_even_if_incomplete(): void
    {
        $incomplet = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505']);
        $this->asManager()->from('/manager/livreurs/'.$incomplet->id)
            ->patch('/manager/livreurs/'.$incomplet->id.'/kyc', ['decision' => 'valider'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Livreur::KYC_VALIDE, $incomplet->fresh()->kyc_statut);
        $this->assertTrue($incomplet->fresh()->statut);

        $this->asManager()->post('/manager/livreurs', $this->donneesCompletes());
        $livreur = Livreur::where('nom', 'Kone')->firstOrFail();
        $this->assertFalse($livreur->statut);

        $this->asManager()->from('/manager/livreurs/'.$livreur->id)
            ->patch('/manager/livreurs/'.$livreur->id.'/kyc', ['decision' => 'valider'])
            ->assertSessionHasNoErrors();

        $livreur->refresh();
        $this->assertSame(Livreur::KYC_VALIDE, $livreur->kyc_statut);
        $this->assertTrue($livreur->statut);
        $this->assertSame(1, $livreur->kyc_verifie_par);
        $this->assertNotNull($livreur->kyc_verifie_le);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $livreur = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505', 'statut' => true]);

        $this->asManager()->from('/manager/livreurs/'.$livreur->id)
            ->patch('/manager/livreurs/'.$livreur->id.'/kyc', ['decision' => 'rejeter'])
            ->assertSessionHasErrors('motif');

        $this->asManager()->from('/manager/livreurs/'.$livreur->id)
            ->patch('/manager/livreurs/'.$livreur->id.'/kyc', ['decision' => 'rejeter', 'motif' => 'Pièce illisible']);

        $this->assertSame(Livreur::KYC_REJETE, $livreur->fresh()->kyc_statut);
        $this->assertSame('Pièce illisible', $livreur->fresh()->kyc_motif_rejet);
        $this->assertFalse($livreur->fresh()->statut);
    }

    public function test_livreur_cannot_be_activated_before_kyc_is_accepted(): void
    {
        $livreur = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505']);

        $this->asManager()->patch(route('manager.livreurs.toggle-statut', $livreur))
            ->assertSessionHas('error');
        $this->assertFalse($livreur->fresh()->statut);

        $this->asManager()->put(route('manager.livreurs.update', $livreur), [
            'nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505', 'statut' => 1,
        ])->assertSessionHasErrors('statut');
        $this->assertFalse($livreur->fresh()->statut);

        $livreur->update(['kyc_statut' => Livreur::KYC_VALIDE]);
        $this->asManager()->patch(route('manager.livreurs.toggle-statut', $livreur));
        $this->assertTrue($livreur->fresh()->statut);
    }

    public function test_inactive_livreur_cannot_receive_a_moto(): void
    {
        $livreur = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505']);
        $moto = \App\Models\LocationVente\Moto::create(['marque' => 'Haojue', 'immatriculation' => 'AB 1234 CI', 'statut' => 'disponible']);

        $this->asManager()->post(route('manager.contrats.store'), [
            'livreur_id' => $livreur->id,
            'moto_id' => $moto->id,
            'prix_achat' => 900000,
            'montant_echeance' => 30000,
            'frequence' => 'hebdomadaire',
            'date_debut' => now()->toDateString(),
        ])->assertSessionHasErrors('livreur_id');

        $this->assertSame(0, \App\Models\LocationVente\Contrat::count());
        $this->assertSame('disponible', $moto->fresh()->statut);
    }

    public function test_replacing_a_document_of_a_validated_kyc_requires_a_new_review(): void
    {
        $this->asManager()->post('/manager/livreurs', $this->donneesCompletes());
        $livreur = Livreur::firstOrFail();
        $livreur->update(['kyc_statut' => Livreur::KYC_VALIDE, 'kyc_verifie_le' => now(), 'statut' => true]);
        $ancien = $livreur->documents()->where('type', 'piece_recto')->value('chemin');

        $this->asManager()->from('/manager/livreurs/'.$livreur->id)->post('/manager/livreurs/'.$livreur->id.'/documents', [
            'type' => 'piece_recto',
            'fichier' => UploadedFile::fake()->image('nouvelle-cni.jpg'),
        ])->assertSessionHasNoErrors();

        $livreur->refresh();
        $this->assertSame(Livreur::KYC_EN_ATTENTE, $livreur->kyc_statut);
        $this->assertNull($livreur->kyc_verifie_le);
        $this->assertFalse($livreur->statut);
        Storage::disk('r2')->assertMissing($ancien);
        $this->assertSame(1, $livreur->documents()->where('type', 'piece_recto')->count());
    }

    public function test_deleting_a_required_document_makes_the_kyc_incomplete(): void
    {
        $this->asManager()->post('/manager/livreurs', $this->donneesCompletes());
        $livreur = Livreur::firstOrFail();
        $document = $livreur->documents()->where('type', 'permis_verso')->firstOrFail();

        $this->asManager()->from('/manager/livreurs/'.$livreur->id)
            ->delete('/manager/livreurs/'.$livreur->id.'/documents/'.$document->id)
            ->assertSessionHas('success');

        Storage::disk('r2')->assertMissing($document->chemin);
        $this->assertSame(Livreur::KYC_INCOMPLET, $livreur->fresh()->kyc_statut);
    }

    public function test_documents_are_served_only_through_their_livreur(): void
    {
        $this->asManager()->post('/manager/livreurs', $this->donneesCompletes());
        $livreur = Livreur::firstOrFail();
        $autre = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505', 'statut' => true]);
        $document = $livreur->documents()->where('type', 'piece_recto')->firstOrFail();

        $this->asManager()->get('/manager/livreurs/'.$livreur->id.'/documents/'.$document->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->asManager()->get('/manager/livreurs/'.$autre->id.'/documents/'.$document->id)->assertNotFound();
        $this->flushSession();
        $this->get('/manager/livreurs/'.$livreur->id.'/documents/'.$document->id)->assertRedirect(route('login'));
    }

    public function test_photo_route_falls_back_to_a_default_avatar(): void
    {
        $livreur = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505', 'statut' => true]);

        $this->asManager()->get('/manager/livreurs/'.$livreur->id.'/photo')->assertOk();
    }

    public function test_unsupported_files_are_refused(): void
    {
        $livreur = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505', 'statut' => true]);

        $this->asManager()->from('/manager/livreurs/'.$livreur->id)->post('/manager/livreurs/'.$livreur->id.'/documents', [
            'type' => 'piece_recto',
            'fichier' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('fichier');

        $this->assertSame(0, LivreurDocument::count());
    }

    public function test_deleting_a_livreur_removes_his_files(): void
    {
        $this->asManager()->post('/manager/livreurs', $this->donneesCompletes());
        $livreur = Livreur::firstOrFail();
        $chemins = $livreur->documents()->pluck('chemin')->push($livreur->photo)->all();

        $this->asManager()->delete('/manager/livreurs/'.$livreur->id)->assertRedirect(route('manager.livreurs.index'));

        foreach ($chemins as $chemin) {
            Storage::disk('r2')->assertMissing($chemin);
        }
        $this->assertSame(0, LivreurDocument::count());
    }

    public function test_livreur_pages_are_displayed(): void
    {
        $this->asManager()->post('/manager/livreurs', $this->donneesCompletes());
        $livreur = Livreur::firstOrFail();

        $this->asManager()->get('/manager/livreurs')->assertOk()->assertSee('LVR-0001')->assertSee('KYC à vérifier');
        $this->asManager()->get('/manager/livreurs/'.$livreur->id)
            ->assertOk()
            ->assertSee('Accepter')
            ->assertSee('Rejeter')
            ->assertSee('CI0012345')
            ->assertSee("Pièce d'identité – recto");
    }

    private function asManager(): static
    {
        return $this->withSession(['utilisateur' => ['id' => 1, 'nom' => 'Kouassi', 'prenoms' => 'Marc', 'login' => 'marc', 'role' => 'manager']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesCompletes(): array
    {
        return [
            'nom' => 'Kone',
            'prenoms' => 'Ibrahim',
            'contact' => '0701020304',
            'statut' => 1,
            'type_piece' => 'CNI',
            'numero_piece' => 'CI0012345',
            'date_expiration_piece' => '2030-01-01',
            'numero_permis' => 'P-998877',
            'categorie_permis' => 'A',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'documents' => [
                'piece_recto' => UploadedFile::fake()->image('cni-recto.jpg'),
                'piece_verso' => UploadedFile::fake()->image('cni-verso.jpg'),
                'permis_recto' => UploadedFile::fake()->create('permis-recto.pdf', 50, 'application/pdf'),
                'permis_verso' => UploadedFile::fake()->image('permis-verso.png'),
            ],
        ];
    }
}
