<?php

namespace Tests\Feature;

use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\MotoPhoto;
use App\Models\LocationVente\Paiement;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocationVenteTest extends TestCase
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
        ]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manager_can_add_a_livreur_and_a_moto(): void
    {
        $this->asManager()->post('/manager/livreurs', [
            'nom' => 'Kone',
            'prenoms' => 'Ibrahim',
            'contact' => '0701020304',
            'statut' => 1,
        ])->assertRedirect(route('manager.livreurs.show', 1));

        $this->asManager()->post('/manager/motos', [
            'marque' => 'Haojue',
            'modele' => 'HJ125',
            'immatriculation' => 'ab 1234 ci',
            'statut' => 'disponible',
            'photos' => $this->photosMoto(),
        ])->assertRedirect(route('manager.motos.index'));

        $this->assertDatabaseHas('location_vente_livreurs', ['nom' => 'Kone', 'statut' => 0]);
        $this->assertDatabaseHas('location_vente_motos', [
            'immatriculation' => 'AB 1234 CI',
            'statut' => 'disponible',
            'prix_achat' => 0,
        ]);

        $moto = Moto::firstOrFail();
        $this->asManager()->put(route('manager.motos.update', $moto), [
            'marque' => 'Haojue',
            'modele' => 'HJ125',
            'immatriculation' => 'AB 1234 CI',
            'prix_achat' => 850000,
            'statut' => 'disponible',
        ])->assertSessionHasNoErrors();
        $this->assertSame(850000, $moto->fresh()->prix_achat);
    }

    public function test_moto_photos_of_every_face_fuel_and_engine_size_are_saved(): void
    {
        $this->asManager()->post('/manager/motos', [
            'marque' => 'KTM',
            'modele' => 'Duke 200',
            'numero_chassis' => 'tmby123',
            'carburant' => 'essence',
            'cylindree' => 200,
            'statut' => 'disponible',
            'photos' => $this->photosMoto(),
        ])->assertSessionHasNoErrors();

        $moto = Moto::firstOrFail();
        $this->assertSame(['essence', 200], [$moto->carburant, $moto->cylindree]);
        $this->assertSame('Essence · 200 cc', $moto->caracteristiques());

        $photos = $moto->photosParFace();
        $this->assertEqualsCanonicalizing(array_keys(MotoPhoto::FACES), array_keys($photos));
        foreach ($photos as $face => $photo) {
            Storage::disk(Moto::DISQUE)->assertExists($photo->chemin);
            $this->asManager()->get(route('manager.motos.photo', [$moto, $face]))->assertOk();
        }
        $this->assertSame('droite', $moto->photoPrincipale()->face);

        $this->asManager()->from('/manager/motos')->post('/manager/motos', [
            'marque' => 'TVS',
            'numero_chassis' => 'tvs1',
            'carburant' => 'kerosene',
            'statut' => 'disponible',
            'photos' => $this->photosMoto(),
        ])->assertSessionHasErrors('carburant');
    }

    public function test_every_face_is_required_when_adding_a_moto(): void
    {
        $photos = $this->photosMoto();
        unset($photos['arriere']);

        $this->asManager()->from('/manager/motos')->post('/manager/motos', [
            'marque' => 'KTM',
            'numero_chassis' => 'tmby123',
            'statut' => 'disponible',
            'photos' => $photos,
        ])->assertSessionHasErrors(['photos.arriere' => 'La photo « Face arrière » est obligatoire.']);

        $this->assertSame(0, Moto::count());
        $this->assertSame([], Storage::disk(Moto::DISQUE)->allFiles());
    }

    public function test_editing_a_moto_replaces_only_the_new_faces(): void
    {
        $this->asManager()->post('/manager/motos', [
            'marque' => 'KTM',
            'numero_chassis' => 'tmby123',
            'statut' => 'disponible',
            'photos' => $this->photosMoto(),
        ])->assertSessionHasNoErrors();
        $moto = Moto::firstOrFail();
        $avant = $moto->photosParFace();

        $this->asManager()->put(route('manager.motos.update', $moto), [
            'marque' => 'KTM',
            'numero_chassis' => 'tmby123',
            'photos' => ['avant' => UploadedFile::fake()->image('avant-2.jpg')],
        ])->assertSessionHasNoErrors();

        $apres = $moto->fresh()->photosParFace();
        $this->assertNotSame($avant['avant']->chemin, $apres['avant']->chemin);
        $this->assertSame($avant['droite']->chemin, $apres['droite']->chemin);
        Storage::disk(Moto::DISQUE)->assertMissing($avant['avant']->chemin);
        Storage::disk(Moto::DISQUE)->assertExists($apres['avant']->chemin);

        $this->asManager()->delete(route('manager.motos.destroy', $moto));
        $this->assertSame([], Storage::disk(Moto::DISQUE)->allFiles());
    }

    public function test_assign_buttons_open_the_contract_form_with_the_right_choices(): void
    {
        $actif = Livreur::create(['nom' => 'Kone', 'prenoms' => 'Ibrahim', 'contact' => '0701', 'statut' => true, 'kyc_statut' => Livreur::KYC_VALIDE]);
        $inactif = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505']);
        $disponible = Moto::create(['marque' => 'Haojue', 'immatriculation' => 'AB 1234 CI', 'statut' => 'disponible']);
        $enMaintenance = Moto::create(['marque' => 'TVS', 'immatriculation' => 'CD 5678 CI', 'statut' => 'maintenance']);

        $this->asManager()->get('/manager/motos')
            ->assertOk()
            ->assertSee('data-moto-id="'.$disponible->id.'"', false)
            ->assertDontSee('data-moto-id="'.$enMaintenance->id.'"', false)
            ->assertSee('Attribuer la moto')
            ->assertSee($actif->nomComplet())
            ->assertDontSee($inactif->code.' · ');

        $this->asManager()->get(route('manager.livreurs.show', $actif))
            ->assertOk()
            ->assertSee('data-livreur-id="'.$actif->id.'"', false)
            ->assertSee('Attribuer la moto');

        $this->asManager()->get(route('manager.livreurs.show', $inactif))
            ->assertOk()
            ->assertDontSee('data-livreur-id="'.$inactif->id.'"', false)
            ->assertDontSee('id="modalNouveauContrat"', false);
    }

    public function test_new_moto_can_be_added_while_registration_is_pending(): void
    {
        $this->asManager()->from('/manager/motos')->post('/manager/motos', [
            'marque' => 'KTM',
            'statut' => 'disponible',
        ])->assertSessionHasErrors('numero_chassis');

        foreach (['lbm100', 'lbm200'] as $chassis) {
            $this->asManager()->post('/manager/motos', [
                'marque' => 'KTM',
                'numero_chassis' => $chassis,
                'statut' => 'disponible',
                'photos' => $this->photosMoto(),
            ])->assertSessionHasNoErrors();
        }

        $moto = Moto::where('numero_chassis', 'LBM100')->firstOrFail();
        $this->assertNull($moto->immatriculation);
        $this->assertSame('KTM · Immat. en cours (châssis LBM100)', $moto->libelle());

        $this->asManager()->get('/manager/motos')->assertOk()->assertSee('Immat. en cours');

        $this->asManager()->put(route('manager.motos.update', $moto), [
            'marque' => 'KTM',
            'numero_chassis' => 'LBM100',
            'immatriculation' => 'ef 9012 ci',
        ])->assertSessionHasNoErrors();
        $this->assertSame('EF 9012 CI', $moto->fresh()->immatriculation);
    }

    public function test_creating_a_contract_hands_over_the_moto_and_records_the_deposit(): void
    {
        [$livreur, $moto] = $this->livreurEtMoto();

        $this->asManager()->post('/manager/contrats', [
            'livreur_id' => $livreur->id,
            'moto_id' => $moto->id,
            'prix_achat' => 450000,
            'cout_supplementaire' => 50000,
            'marge' => 100000,
            'prix_total' => 1,
            'apport' => 100000,
            'mode_apport' => 'Wave',
            'montant_echeance' => 5000,
            'frequence' => 'journalier',
            'date_debut' => '2026-10-01',
        ])->assertRedirect();

        $contrat = Contrat::firstOrFail();

        $this->assertSame('LV-2026-0001', $contrat->reference);
        $this->assertSame(600000, $contrat->prix_total);
        $this->assertSame([450000, 50000, 100000], [$contrat->prix_achat, $contrat->cout_supplementaire, $contrat->marge]);
        $this->assertSame(Contrat::STATUT_EN_COURS, $contrat->statut);
        $this->assertSame(Moto::STATUT_EN_LOCATION, $moto->fresh()->statut);
        $this->assertSame(100000, $contrat->montantPaye());
        $this->assertSame(500000, $contrat->reste());
        $this->assertSame(100, $contrat->nombreEcheances());
        $this->assertSame('2027-01-26', $contrat->dateFinPrevue()->toDateString());
        $this->assertDatabaseHas('location_vente_paiements', ['contrat_id' => $contrat->id, 'type' => Paiement::TYPE_APPORT, 'montant' => 100000]);
    }

    public function test_deposit_cannot_exceed_the_amount_to_repay(): void
    {
        [$livreur, $moto] = $this->livreurEtMoto();

        $this->asManager()->from('/manager/contrats')->post('/manager/contrats', [
            'livreur_id' => $livreur->id,
            'moto_id' => $moto->id,
            'prix_achat' => 300000,
            'cout_supplementaire' => 20000,
            'marge' => 30000,
            'apport' => 350001,
            'mode_apport' => 'Wave',
            'montant_echeance' => 5000,
            'frequence' => 'journalier',
            'date_debut' => '2026-10-01',
        ])->assertSessionHasErrors('apport');

        $this->assertSame(0, Contrat::count());
    }

    public function test_deposit_requires_a_payment_method(): void
    {
        [$livreur, $moto] = $this->livreurEtMoto();

        $this->asManager()->from('/manager/contrats')->post('/manager/contrats', [
            'livreur_id' => $livreur->id,
            'moto_id' => $moto->id,
            'prix_achat' => 600000,
            'apport' => 100000,
            'montant_echeance' => 5000,
            'frequence' => 'journalier',
            'date_debut' => '2026-10-01',
        ])->assertSessionHasErrors('mode_apport');
    }

    public function test_a_livreur_cannot_have_two_contracts_and_a_rented_moto_cannot_be_reused(): void
    {
        $contrat = $this->contrat();
        $autreMoto = Moto::create(['marque' => 'TVS', 'immatriculation' => 'CD 5678 CI', 'statut' => 'disponible']);
        $autreLivreur = Livreur::create(['nom' => 'Yao', 'prenoms' => 'Paul', 'contact' => '0505', 'statut' => true]);

        $this->asManager()->from('/manager/contrats')->post('/manager/contrats', $this->conditions($contrat->livreur_id, $autreMoto->id))
            ->assertSessionHasErrors('livreur_id');

        $this->asManager()->from('/manager/contrats')->post('/manager/contrats', $this->conditions($autreLivreur->id, $contrat->moto_id))
            ->assertSessionHasErrors('moto_id');

        $this->assertSame(1, Contrat::count());
    }

    public function test_payment_reduces_the_balance_and_overpayment_is_refused(): void
    {
        $contrat = $this->contrat();

        $this->asManager()->from('/manager/paiements')->post('/manager/paiements', $this->paiement($contrat, 20000))
            ->assertSessionHasNoErrors();
        $this->assertSame(80000, $contrat->fresh()->reste());

        $this->asManager()->from('/manager/paiements')->post('/manager/paiements', $this->paiement($contrat, 90000))
            ->assertSessionHasErrors('montant');
        $this->assertSame(80000, $contrat->fresh()->reste());
    }

    public function test_last_payment_settles_the_contract_and_the_moto_leaves_the_fleet(): void
    {
        $contrat = $this->contrat();

        $this->asManager()->from('/manager/paiements')->post('/manager/paiements', $this->paiement($contrat, 100000))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'propriété de Ibrahim Kone'));

        $contrat->refresh();
        $this->assertSame(Contrat::STATUT_SOLDE, $contrat->statut);
        $this->assertSame('2026-10-03', $contrat->date_solde->toDateString());
        $this->assertSame(Moto::STATUT_CEDEE, $contrat->moto->statut);
        $this->assertSame(0, Moto::dansLeParc()->count());

        $this->asManager()->from('/manager/paiements')->post('/manager/paiements', $this->paiement($contrat, 1000))
            ->assertSessionHasErrors('contrat_id');
    }

    public function test_cancelling_a_payment_reopens_a_settled_contract(): void
    {
        $contrat = $this->contrat();
        $this->asManager()->from('/manager/paiements')->post('/manager/paiements', $this->paiement($contrat, 100000));
        $paiement = Paiement::firstOrFail();

        $this->asManager()->from('/manager/paiements')->delete('/manager/paiements/'.$paiement->id)
            ->assertSessionHas('success');

        $contrat->refresh();
        $this->assertSame(Contrat::STATUT_EN_COURS, $contrat->statut);
        $this->assertNull($contrat->date_solde);
        $this->assertSame(Moto::STATUT_EN_LOCATION, $contrat->moto->statut);
        $this->assertSame(100000, $contrat->reste());
    }

    public function test_terminating_a_contract_returns_the_moto_to_the_fleet(): void
    {
        $contrat = $this->contrat();

        $this->asManager()->patch('/manager/contrats/'.$contrat->id.'/resilier', [
            'date_resiliation' => '2026-10-03',
            'motif' => 'Retards répétés',
        ])->assertRedirect(route('manager.contrats.show', $contrat));

        $contrat->refresh();
        $this->assertSame(Contrat::STATUT_RESILIE, $contrat->statut);
        $this->assertSame(Moto::STATUT_DISPONIBLE, $contrat->moto->statut);
        $this->assertStringContainsString('Retards répétés', $contrat->notes);
    }

    public function test_contract_conditions_can_be_updated(): void
    {
        $contrat = $this->contrat([
            'date_debut' => '2026-10-01',
            'prix_total' => 100000,
            'montant_echeance' => 10000,
            'apport' => 0,
            'frequence' => 'hebdomadaire',
        ]);
        $contrat->moto->update(['prix_achat' => 50000]);

        $nombre = Contrat::nombreEcheancesSurPeriode('2026-10-15', 6, 'hebdomadaire');
        $this->assertSame(26, $nombre);

        $this->asManager()->put(route('manager.contrats.update', $contrat), [
            'prix_acquisition' => 380000,
            'cout_supplementaire' => 100000,
            'apport' => 0,
            'montant_a_verser' => 4000,
            'frequence' => 'hebdomadaire',
            'date_debut' => '2026-10-15',
            'duree_mois' => 6,
        ])->assertRedirect(route('manager.contrats.show', $contrat));

        $contrat->refresh()->load('moto');
        $this->assertSame(380000, $contrat->moto->prix_achat);
        $this->assertSame(380000, $contrat->prix_achat);
        $this->assertSame(100000, $contrat->cout_supplementaire);
        $this->assertSame(24000, $contrat->montant_echeance);
        $this->assertSame(4000, $contrat->montantAVerser());
        $this->assertSame(26, $contrat->nombreEcheances());
        $this->assertSame(624000, $contrat->prix_total);
        $this->assertSame(144000, $contrat->marge);
        $this->assertSame('hebdomadaire', $contrat->frequence);
        $this->assertSame('2026-10-15', $contrat->date_debut->toDateString());
        $this->assertSame(6, $contrat->duree_mois);
        $this->assertSame('2027-04-15', $contrat->date_fin->toDateString());
        $this->assertSame(244000, $contrat->notreMarge());
    }

    public function test_daily_installments_count_working_days_over_duration(): void
    {
        $nombre = Contrat::nombreEcheancesSurPeriode('2026-10-15', 6, 'journalier');
        $this->assertGreaterThan(140, $nombre);
        $this->assertLessThan(160, $nombre);

        $contrat = $this->contrat(['prix_total' => 100000, 'montant_echeance' => 1000, 'frequence' => 'journalier']);
        $contrat->moto->update(['prix_achat' => 380000]);

        $this->asManager()->put(route('manager.contrats.update', $contrat), [
            'prix_acquisition' => 380000,
            'cout_supplementaire' => 0,
            'apport' => 0,
            'montant_a_verser' => 4000,
            'frequence' => 'journalier',
            'date_debut' => '2026-10-15',
            'duree_mois' => 6,
        ])->assertRedirect(route('manager.contrats.show', $contrat));

        $contrat->refresh()->load('moto');
        $this->assertSame(4000, $contrat->montant_echeance);
        $this->assertSame($nombre, $contrat->nombreEcheances());
        $this->assertSame(4000 * $nombre, $contrat->prix_total);
        $this->assertSame((4000 * $nombre) - 380000, $contrat->notreMarge());
    }

    public function test_contract_total_cannot_be_lower_than_amount_already_paid(): void
    {
        $contrat = $this->contrat(['prix_total' => 100000, 'montant_echeance' => 10000, 'frequence' => 'hebdomadaire']);
        $contrat->paiements()->create(['montant' => 40000, 'date_paiement' => '2026-10-02', 'mode' => 'Espèces']);

        $this->asManager()->from(route('manager.contrats.show', $contrat))
            ->put(route('manager.contrats.update', $contrat), [
                'prix_acquisition' => 0,
                'cout_supplementaire' => 0,
                'apport' => 0,
                'montant_a_verser' => 1000,
                'frequence' => 'hebdomadaire',
                'date_debut' => '2026-10-01',
                'duree_mois' => 1,
            ])
            ->assertSessionHasErrors('montant_a_verser');

        $this->assertSame(100000, $contrat->fresh()->prix_total);
    }

    public function test_creating_a_contract_stores_duration_and_end_date(): void
    {
        [$livreur, $moto] = $this->livreurEtMoto();

        $this->asManager()->post('/manager/contrats', [
            'livreur_id' => $livreur->id,
            'moto_id' => $moto->id,
            'prix_achat' => 400000,
            'apport' => 0,
            'montant_echeance' => 5000,
            'frequence' => 'journalier',
            'date_debut' => '2026-10-01',
            'duree_mois' => 6,
        ])->assertRedirect();

        $contrat = Contrat::firstOrFail();
        $this->assertSame(6, $contrat->duree_mois);
        $this->assertSame('2027-04-01', $contrat->date_fin->toDateString());
    }

    public function test_arrears_follow_the_payment_schedule(): void
    {
        $contrat = $this->contrat(['date_debut' => '2026-09-23', 'frequence' => 'journalier', 'montant_echeance' => 1000, 'prix_total' => 50000]);
        $contrat->paiements()->create(['montant' => 3000, 'date_paiement' => '2026-09-25', 'mode' => 'Espèces']);

        // Du jeudi 24/09 au samedi 03/10 : 10 jours dont un dimanche (27/09) => 9 échéances échues.
        $this->assertSame(9, $contrat->echeancesEchues());
        $this->assertSame(9000, $contrat->montantAttendu());
        $this->assertSame(6000, $contrat->retard());
        $this->assertSame(6, $contrat->echeancesEnRetard());
        $this->assertSame('2026-09-28', $contrat->prochaineEcheance()->toDateString());

        $statuts = array_count_values(array_column($contrat->echeancier(), 'statut'));
        $this->assertSame(3, $statuts['payee']);
        $this->assertSame(6, $statuts['en_retard']);
        $this->assertSame(41, $statuts['a_venir']);
    }

    public function test_daily_schedule_skips_sundays(): void
    {
        $contrat = $this->contrat(['date_debut' => '2026-10-03', 'frequence' => 'journalier', 'montant_echeance' => 1000, 'prix_total' => 300000]);

        $this->assertSame('2026-10-05', $contrat->dateEcheance(1)->toDateString());
        $this->assertSame('2026-10-10', $contrat->dateEcheance(6)->toDateString());
        $this->assertSame('2026-10-12', $contrat->dateEcheance(7)->toDateString());

        foreach (['2026-10-03', '2026-10-04'] as $debut) {
            $contrat->date_debut = $debut;
            $precedente = $contrat->dateEcheance(0);
            for ($numero = 1; $numero <= 300; $numero++) {
                $date = $contrat->dateEcheance($numero);
                $this->assertFalse($date->isSunday(), "Échéance {$numero} un dimanche ({$date->toDateString()})");
                $this->assertSame($precedente->copy()->addDay()->isSunday() ? 2 : 1, (int) $precedente->diffInDays($date));
                $precedente = $date;
            }
        }

        $contrat->fill(['date_debut' => '2026-10-03', 'frequence' => 'hebdomadaire', 'montant_echeance' => 6000, 'prix_total' => 60000]);
        $this->assertSame('2026-10-10', $contrat->dateEcheance(1)->toDateString());
        $this->assertSame(10, $contrat->nombreEcheances());
    }

    public function test_only_daily_and_weekly_frequencies_are_accepted(): void
    {
        [$livreur, $moto] = $this->livreurEtMoto();

        $this->asManager()->from('/manager/contrats')
            ->post('/manager/contrats', ['frequence' => 'mensuel'] + $this->conditions($livreur->id, $moto->id))
            ->assertSessionHasErrors('frequence');
    }

    public function test_manager_pages_are_displayed(): void
    {
        $contrat = $this->contrat();
        $contrat->paiements()->create(['montant' => 5000, 'date_paiement' => '2026-10-02', 'mode' => 'Wave']);

        foreach (['/manager/livreurs', '/manager/motos', '/manager/contrats', '/manager/contrats/'.$contrat->id, '/manager/paiements'] as $url) {
            $this->asManager()->get($url)->assertOk()->assertSee('Ibrahim Kone');
        }

        foreach (['/manager/contrats', '/manager/paiements'] as $url) {
            $this->asManager()->get($url)->assertSee(route('manager.livreurs.photo', $contrat->livreur_id), false);
        }
    }

    public function test_contract_show_displays_moto_photo_and_our_margin(): void
    {
        $contrat = $this->contrat([
            'prix_total' => 600000,
            'montant_echeance' => 24000,
            'apport' => 0,
            'frequence' => 'hebdomadaire',
        ]);
        $contrat->moto->update(['prix_achat' => 380000]);
        $contrat->moto->enregistrerPhotos($this->photosMoto());

        $this->assertSame(380000, $contrat->fresh()->prixAcquisitionMoto());
        $this->assertSame(220000, $contrat->fresh()->load('moto')->notreMarge());
        $this->assertSame(25, $contrat->nombreEcheances());
        $this->assertSame(8800, $contrat->fresh()->load('moto')->notreMargeParEcheance());

        $this->asManager()
            ->get(route('manager.contrats.show', $contrat))
            ->assertOk()
            ->assertSee('Montant d&#039;acquisition', false)
            ->assertSee('Montant à rembourser')
            ->assertSee('Nombre de versements')
            ->assertSee('Notre marge')
            ->assertSee('220 000 FCFA')
            ->assertSee('8 800 FCFA / échéance × 25', false)
            ->assertSee(route('manager.motos.photo', [$contrat->moto, 'droite']), false);
    }

    public function test_commercial_cannot_access_location_vente(): void
    {
        $this->withSession(['utilisateur' => ['id' => 2, 'login' => 'pauline', 'role' => 'commercial']])
            ->get('/manager/motos')
            ->assertRedirect(route('dashboard'));
    }

    private function asManager(): static
    {
        return $this->withSession(['utilisateur' => ['id' => 1, 'nom' => 'Kouassi', 'prenoms' => 'Marc', 'login' => 'marc', 'role' => 'manager']]);
    }

    /**
     * @return array{0: Livreur, 1: Moto}
     */
    private function livreurEtMoto(): array
    {
        return [
            Livreur::create(['nom' => 'Kone', 'prenoms' => 'Ibrahim', 'contact' => '0701020304', 'statut' => true]),
            Moto::create(['marque' => 'Haojue', 'modele' => 'HJ125', 'immatriculation' => 'AB 1234 CI', 'statut' => 'disponible']),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function contrat(array $overrides = []): Contrat
    {
        [$livreur, $moto] = $this->livreurEtMoto();
        $moto->update(['statut' => Moto::STATUT_EN_LOCATION]);

        $contrat = Contrat::create(array_merge([
            'livreur_id' => $livreur->id,
            'moto_id' => $moto->id,
            'prix_total' => 100000,
            'apport' => 0,
            'montant_echeance' => 10000,
            'frequence' => 'hebdomadaire',
            'date_debut' => '2026-10-01',
            'statut' => Contrat::STATUT_EN_COURS,
        ], $overrides));
        $contrat->attribuerReference();

        return $contrat;
    }

    /**
     * @return array<string, mixed>
     */
    private function conditions(int $livreurId, int $motoId): array
    {
        return [
            'livreur_id' => $livreurId,
            'moto_id' => $motoId,
            'prix_achat' => 500000,
            'apport' => 0,
            'montant_echeance' => 5000,
            'frequence' => 'journalier',
            'date_debut' => '2026-10-03',
        ];
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function photosMoto(): array
    {
        return collect(array_keys(MotoPhoto::FACES))
            ->mapWithKeys(fn (string $face) => [$face => UploadedFile::fake()->image("{$face}.jpg", 640, 480)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function paiement(Contrat $contrat, int $montant): array
    {
        return [
            'contrat_id' => $contrat->id,
            'montant' => $montant,
            'date_paiement' => '2026-10-03',
            'mode' => 'Orange Money',
        ];
    }
}
