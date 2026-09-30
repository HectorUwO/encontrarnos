<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Models\PersonRecord;
use App\Models\User;
use App\Services\Photos\PhotoCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonRecordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_lists_only_published_records_with_the_newest_event_first(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'event_date' => '2026-01-10']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'event_date' => '2026-03-05']);
        PersonRecord::factory()->unpublished()->create(['folio' => 'EN-000003', 'event_date' => '2026-04-01']);

        $this->get(route('records'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/BaseDeDatos')
                ->where('records.meta.total', 2)
                ->where('records.data.0.folio', 'EN-000002')
                ->where('records.data.1.folio', 'EN-000001'));
    }

    public function test_paginates_twelve_records_per_page(): void
    {
        PersonRecord::factory()->count(13)->create();

        $this->get(route('records'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 12)
                ->where('records.meta.total', 13));

        $this->get(route('records', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
    }

    public function test_search_matches_words_in_any_order(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'MARIA LUCERO PAREDES']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'name' => 'MARIA FERNANDA RUIZ']);

        $this->get(route('records', ['q' => 'lucero maria']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.folio', 'EN-000001')
                ->where('filters.q', 'lucero maria'));
    }

    public function test_filters_by_state(): void
    {
        PersonRecord::factory()->inState(MexicanState::Jalisco)->create(['folio' => 'EN-000001']);
        PersonRecord::factory()->inState(MexicanState::Sinaloa)->create(['folio' => 'EN-000002']);

        $this->get(route('records', ['state' => MexicanState::Jalisco->value]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.folio', 'EN-000001')
                ->where('filters.state', 'jalisco'));
    }

    #[DataProvider('ageRanges')]
    public function test_filters_by_age_range_including_its_limits(AgeRange $range, int $age, bool $isListed): void
    {
        PersonRecord::factory()->ofAge($age)->create(['folio' => 'EN-000001']);

        $this->get(route('records', ['age' => $range->value]))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', $isListed ? 1 : 0));
    }

    /**
     * @return array<string, array{AgeRange, int, bool}>
     */
    public static function ageRanges(): array
    {
        return [
            'oldest minor' => [AgeRange::Minor, 17, true],
            'first adult is not a minor' => [AgeRange::Minor, 18, false],
            'youngest adult' => [AgeRange::YoungAdult, 18, true],
            'upper limit of 18 to 29' => [AgeRange::YoungAdult, 29, true],
            'thirty is out of 18 to 29' => [AgeRange::YoungAdult, 30, false],
            'forty and above' => [AgeRange::Mature, 85, true],
        ];
    }

    public function test_rejects_an_unknown_state_filter(): void
    {
        $this->from(route('records'))
            ->get(route('records', ['state' => 'atlantis']))
            ->assertRedirect(route('records'))
            ->assertInvalid('state');
    }

    public function test_shows_the_ficha_of_a_public_record_without_source_identifiers(): void
    {
        PersonRecord::factory()->create([
            'folio' => 'EN-000321',
            'name' => 'MARIA LOPEZ',
            'authority' => 'Fiscalía de Jalisco',
            'source_victim_id' => 'BE74EF5E-38B4-4D37-84E0-E49B45504C10',
            'source_report_id' => 7,
            'source_agency_id' => 44,
        ]);

        $response = $this->get(route('records.show', 'EN-000321'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/FichaDesaparecido')
            ->where('record.data.folio', 'EN-000321')
            ->where('record.data.name', 'MARIA LOPEZ')
            ->where('record.data.url', '/base-de-datos/EN-000321')
            ->where('record.data.authority', 'Fiscalía de Jalisco')
            ->missing('record.data.source_victim_id'));
        $this->assertStringNotContainsString('BE74EF5E', $response->getContent());
    }

    public function test_the_ficha_shows_the_registry_data_but_never_the_source_identifiers(): void
    {
        PersonRecord::factory()->create([
            'folio' => 'EN-000500',
            'nationality' => 'MEXICANA',
            'speaks_spanish' => true,
            'origin' => 'CARGA MASIVA',
            'referred_to' => ['FISCALIA A', 'FISCALIA B'],
            'registered_age_years' => 17,
            'registered_date' => '2024-05-15',
            'source_victim_id' => 'BE74EF5E-38B4-4D37-84E0-E49B45504C10',
            'source_report_id' => 7,
            'source_agency_id' => 44,
            'source_authority_id' => 57,
        ]);

        $response = $this->get(route('records.show', 'EN-000500'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('record.data.nationality', 'MEXICANA')
            ->where('record.data.speaks_spanish', true)
            ->where('record.data.origin', 'CARGA MASIVA')
            ->where('record.data.referred_to', ['FISCALIA A', 'FISCALIA B'])
            ->where('record.data.registered_age.years', 17)
            ->where('record.data.registered_date_label', '15 de mayo de 2024')
            ->missingAll([
                'record.data.source_victim_id', 'record.data.source_report_id',
                'record.data.source_agency_id', 'record.data.source_authority_id',
            ]));
        $this->assertStringNotContainsString('BE74EF5E', $response->getContent());
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function sensitiveVisibility(): array
    {
        return [
            'admins only: guest' => ['admins', 'guest', false],
            'admins only: regular user' => ['admins', 'user', false],
            'admins only: admin' => ['admins', 'admin', true],
            'authenticated: guest' => ['authenticated', 'guest', false],
            'authenticated: regular user' => ['authenticated', 'user', true],
            'all: guest' => ['all', 'guest', true],
        ];
    }

    #[DataProvider('sensitiveVisibility')]
    public function test_personal_data_follows_the_sensitive_visibility_setting(string $setting, string $who, bool $visible): void
    {
        config(['services.records_sensitive' => $setting]);
        PersonRecord::factory()->create([
            'folio' => 'EN-000600',
            'street' => 'CALLE UNO',
            'birth_date' => '1990-05-08',
        ]);

        $user = match ($who) {
            'admin' => User::factory()->create(['is_admin' => true]),
            'user' => User::factory()->create(),
            default => null,
        };

        $response = ($user ? $this->actingAs($user) : $this)->get(route('records.show', 'EN-000600'));

        if ($visible) {
            $response->assertInertia(fn (Assert $page) => $page
                ->where('record.data.sensitive_restricted', false)
                ->where('record.data.sensitive.street', 'CALLE UNO')
                ->where('record.data.sensitive.birth_date_label', '8 de mayo de 1990'));
            $this->assertStringContainsString('CALLE UNO', $response->getContent());
        } else {
            $response->assertInertia(fn (Assert $page) => $page
                ->where('record.data.sensitive_restricted', true)
                ->where('record.data.sensitive', null));
            $this->assertStringNotContainsString('CALLE UNO', $response->getContent());
        }
    }

    public function test_what_the_registry_said_about_publishing_is_only_sent_to_admins(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000700', 'registry_publish' => 'SIN DATO']);

        $this->get(route('records.show', 'EN-000700'))
            ->assertInertia(fn (Assert $page) => $page->missing('record.data.registry_publish'));

        $this->actingAs(User::factory()->create())->get(route('records.show', 'EN-000700'))
            ->assertInertia(fn (Assert $page) => $page->missing('record.data.registry_publish'));

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('records.show', 'EN-000700'))
            ->assertInertia(fn (Assert $page) => $page->where('record.data.registry_publish', 'SIN DATO'));
    }

    public function test_personal_data_never_appears_in_the_catalog_listing(): void
    {
        config(['services.records_sensitive' => 'all']);
        PersonRecord::factory()->create(['street' => 'CALLE UNO', 'birth_date' => '1990-05-08', 'neighborhood' => 'CENTRO']);

        $response = $this->get(route('records'));

        $this->assertStringNotContainsString('CALLE UNO', $response->getContent());
        $this->assertStringNotContainsString('1990-05-08', $response->getContent());
    }

    public function test_an_unpublished_or_unknown_ficha_is_not_found(): void
    {
        PersonRecord::factory()->unpublished()->create(['folio' => 'EN-000999']);

        $this->get(route('records.show', 'EN-000999'))->assertNotFound();
        $this->get(route('records.show', 'EN-404404'))->assertNotFound();
    }

    public function test_exposes_only_the_public_fields_of_a_record(): void
    {
        PersonRecord::factory()->create([
            'source_victim_id' => 'BE74EF5E-38B4-4D37-84E0-E49B45504C10',
            'source_report_id' => 1,
            'source_agency_id' => 44,
        ]);

        $this->get(route('records'))
            ->assertInertia(fn (Assert $page) => $page->has('records.data.0', fn (Assert $record) => $record
                ->hasAll([
                    'folio', 'name', 'type', 'type_label', 'status_label', 'sex', 'sex_label', 'age', 'current_age', 'state',
                    'state_label', 'municipality', 'event_date', 'event_date_label', 'description', 'traits',
                    'clothing', 'distinguishing_marks', 'authority', 'has_photo', 'published_at_label', 'updated_at_label', 'url', 'portrait', 'portrait_large',
                ])
                ->missingAll(['id', 'source_victim_id', 'source_report_id', 'source_agency_id', 'photo_path', 'photo_sha256', 'published_at'])));
    }

    public function test_uses_a_silhouette_when_the_record_has_no_photo_and_the_photo_route_when_it_has_one(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'sex' => Sex::Female, 'event_date' => '2026-01-03']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'sex' => Sex::Male, 'event_date' => '2026-01-02']);
        $withPhoto = PersonRecord::factory()->withPhoto()->create(['folio' => 'EN-000003', 'event_date' => '2026-01-01']);
        $version = PhotoCache::version($withPhoto->photo_path);

        $this->get(route('records'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.data.0.portrait', '/placeholder-woman.webp')
                ->where('records.data.1.portrait', '/placeholder-man.webp')
                ->where('records.data.0.portrait_large', '/placeholder-woman.webp')
                ->where('records.data.2.portrait', "/base-de-datos/EN-000003/foto?size=thumb&v={$version}")
                ->where('records.data.2.portrait_large', "/base-de-datos/EN-000003/foto?size=medium&v={$version}")
                ->where('records.data.2.has_photo', true));
    }

    public function test_reports_how_many_records_are_published_whatever_the_filters(): void
    {
        PersonRecord::factory()->count(3)->inState(MexicanState::Jalisco)->create();
        PersonRecord::factory()->inState(MexicanState::Sinaloa)->create();
        PersonRecord::factory()->unpublished()->create();

        $this->get(route('records', ['state' => 'sinaloa']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.meta.total', 1)
                ->where('totalPublished', 4));
    }

    public function test_lists_the_search_results_in_the_order_meilisearch_gives_them(): void
    {
        config([
            'services.records_search' => 'meilisearch',
            'services.meilisearch.host' => 'http://meili.test:7700',
            'services.meilisearch.index' => 'fichas',
        ]);
        $older = PersonRecord::factory()->create(['folio' => 'EN-000001', 'event_date' => '2026-01-01']);
        $newer = PersonRecord::factory()->create(['folio' => 'EN-000002', 'event_date' => '2026-05-01']);
        PersonRecord::factory()->create(['folio' => 'EN-000003']);
        Http::fake(['meili.test:7700/*' => Http::response([
            'hits' => [['id' => $older->id], ['id' => $newer->id]],
            'totalHits' => 40,
        ])]);

        $this->get(route('records', ['q' => 'hernandes', 'state' => 'jalisco', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 2)
                ->where('records.data.0.folio', 'EN-000001')
                ->where('records.data.1.folio', 'EN-000002')
                ->where('records.meta.total', 40)
                ->where('records.meta.current_page', 2)
                ->where('filters.q', 'hernandes'));

        Http::assertSent(fn (Request $request): bool => $request['q'] === 'hernandes'
            && $request['filter'] === ["state = 'jalisco'"]
            && $request['page'] === 2);
    }

    public function test_keeps_answering_when_meilisearch_is_down(): void
    {
        config(['services.records_search' => 'meilisearch']);
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'MARIA LOPEZ']);
        Http::fake(['*' => Http::response([], 503)]);

        $this->get(route('records', ['q' => 'maria']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.folio', 'EN-000001'));
    }

    public function test_limits_how_many_times_a_client_can_search(): void
    {
        RateLimiter::for('records', fn () => Limit::perMinute(2));

        $this->get(route('records'))->assertOk();
        $this->get(route('records'))->assertOk();
        $this->get(route('records'))->assertTooManyRequests();
    }

    public function test_provides_the_options_for_the_filters(): void
    {
        $this->get(route('records'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.states', 32)
                ->has('options.ageRanges', 4)
                ->missing('options.types'));
    }
}
