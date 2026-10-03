<?php

namespace Tests\Feature;

use App\Models\LocationVente\Contrat;
use App\Models\LocationVente\Livreur;
use App\Models\LocationVente\Moto;
use App\Models\LocationVente\Paiement;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocationVenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');

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
        ])->assertRedirect(route('manager.motos.index'));

        $this->assertDatabaseHas('location_vente_livreurs', ['nom' => 'Kone', 'statut' => 0]);
        $this->assertDatabaseHas('location_vente_motos', ['immatriculation' => 'AB 1234 CI', 'statut' => 'disponible']);
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
