<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DisappearanceStatus;
use App\Enums\Sex;
use App\Models\PersonRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonRecordFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function folios(array $query, ?User $as = null): array
    {
        $response = ($as ? $this->actingAs($as) : $this)->get(route('records', $query));

        return collect($response->viewData('page')['props']['records']['data'])->pluck('folio')->all();
    }

    private function seedRecords(): void
    {
        PersonRecord::factory()->create([
            'folio' => 'EN-000001', 'name' => 'ANA RUIZ', 'sex' => Sex::Female, 'age' => 15,
            'disappearance_status' => DisappearanceStatus::Disappeared, 'municipality' => 'TEPIC',
            'authority' => 'FISCALIA DE NAYARIT', 'nationality' => 'MEXICANA', 'photo_path' => 'imagenes/a.jpg',
            'event_date' => '2026-03-10', 'has_disability' => true, 'registry_publish' => 'SI', 'published_at' => '2026-09-01',
        ]);
        PersonRecord::factory()->create([
            'folio' => 'EN-000002', 'name' => 'LUIS PEREZ', 'sex' => Sex::Male, 'age' => 40,
            'disappearance_status' => DisappearanceStatus::NotLocated, 'municipality' => 'ZAPOPAN',
            'authority' => 'FISCALIA DE JALISCO', 'nationality' => 'ESTADOUNIDENSE', 'photo_path' => null,
            'event_date' => '2024-01-05', 'has_disability' => false, 'registry_publish' => 'SIN DATO', 'published_at' => '2026-09-03',
        ]);
        PersonRecord::factory()->create([
            'folio' => 'EN-000003', 'name' => 'BEATRIZ SOTO', 'sex' => Sex::Female, 'age' => null,
            'disappearance_status' => DisappearanceStatus::Disappeared, 'municipality' => 'TEPIC',
            'authority' => 'FISCALIA DE NAYARIT', 'nationality' => 'MEXICANA', 'photo_path' => null,
            'event_date' => null, 'has_disability' => null, 'registry_publish' => 'NO', 'published_at' => '2026-09-02',
        ]);
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function filters(): array
    {
        return [
            'sex' => [['sex' => 'female'], ['EN-000001', 'EN-000003']],
            'status' => [['status' => 'not_located'], ['EN-000002']],
            'only with photo' => [['photo' => 1], ['EN-000001']],
            'age from' => [['age_from' => 20], ['EN-000002']],
            'age to' => [['age_to' => 20], ['EN-000001']],
            'age between' => [['age_from' => 10, 'age_to' => 50, 'sort' => 'age_asc'], ['EN-000001', 'EN-000002']],
            'date from' => [['from' => '2025-01-01'], ['EN-000001']],
            'date to' => [['to' => '2025-01-01'], ['EN-000002']],
            'municipality (partial, any case)' => [['municipality' => 'tep'], ['EN-000001', 'EN-000003']],
            'authority (partial)' => [['authority' => 'jalisco'], ['EN-000002']],
            'nationality (exact)' => [['nationality' => 'ESTADOUNIDENSE'], ['EN-000002']],
            'with disability' => [['disability' => 1], ['EN-000001']],
            'combined' => [['sex' => 'female', 'municipality' => 'tepic', 'photo' => 1], ['EN-000001']],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $expected
     */
    #[DataProvider('filters')]
    public function test_filters_the_catalog(array $query, array $expected): void
    {
        $this->seedRecords();

        $this->assertSame($expected, $this->folios($query));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function sorts(): array
    {
        return [
            'recent (default) puts records without a date last' => ['recent', ['EN-000001', 'EN-000002', 'EN-000003']],
            'oldest also puts records without a date last' => ['oldest', ['EN-000002', 'EN-000001', 'EN-000003']],
            'name' => ['name', ['EN-000001', 'EN-000003', 'EN-000002']],
            'age ascending puts records without age last' => ['age_asc', ['EN-000001', 'EN-000002', 'EN-000003']],
            'age descending puts records without age last' => ['age_desc', ['EN-000002', 'EN-000001', 'EN-000003']],
            'recently added' => ['added', ['EN-000002', 'EN-000003', 'EN-000001']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('sorts')]
    public function test_sorts_the_catalog(string $sort, array $expected): void
    {
        $this->seedRecords();

        $this->assertSame($expected, $this->folios(['sort' => $sort]));
    }

    public function test_the_registry_filter_does_not_exist_for_guests_or_regular_users(): void
    {
        $this->seedRecords();

        $this->assertCount(3, $this->folios(['registry' => 'NO']));
        $this->assertCount(3, $this->folios(['registry' => 'NO'], User::factory()->create()));
    }

    public function test_admins_can_filter_by_what_the_registry_said_about_publishing(): void
    {
        $this->seedRecords();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertSame(['EN-000003'], $this->folios(['registry' => 'NO'], $admin));
        $this->assertSame(['EN-000002'], $this->folios(['registry' => 'SIN DATO'], $admin));
        $this->assertSame(['EN-000001'], $this->folios(['registry' => 'SI'], $admin));
    }

    public function test_the_registry_option_and_active_filter_are_only_sent_to_admins(): void
    {
        $this->seedRecords();

        $this->get(route('records', ['registry' => 'NO']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('options.registry', null)
                ->where('filters.registry', null));

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('records', ['registry' => 'NO']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.registry', 3)
                ->where('filters.registry', 'NO'));
    }

    public function test_offers_the_options_for_the_advanced_filters(): void
    {
        $this->seedRecords();

        $this->get(route('records'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.sexes', 3)
                ->has('options.statuses', 2)
                ->has('options.sorts', 6)
                ->where('options.nationalities.0.value', 'MEXICANA')
                ->where('options.nationalities.0.label', 'Mexicana')
                ->has('options.nationalities', 2));
    }

    public function test_the_active_filters_are_echoed_back(): void
    {
        $this->seedRecords();

        $this->get(route('records', ['sex' => 'male', 'age_from' => 30, 'photo' => 1, 'sort' => 'name', 'municipality' => ' zapopan ']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sex', 'male')
                ->where('filters.age_from', 30)
                ->where('filters.photo', true)
                ->where('filters.sort', 'name')
                ->where('filters.municipality', 'zapopan')
                ->where('filters.disability', null));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'age to before age from' => [['age_from' => 40, 'age_to' => 10], 'age_to'],
            'age out of range' => [['age_from' => 500], 'age_from'],
            'unknown sex' => [['sex' => 'otro'], 'sex'],
            'unknown status' => [['status' => 'perdida'], 'status'],
            'bad date' => [['from' => '31/12/2025'], 'from'],
            'end before start' => [['from' => '2026-05-01', 'to' => '2026-01-01'], 'to'],
            'unknown sort' => [['sort' => 'random'], 'sort'],
            'unknown registry value' => [['registry' => 'TAL VEZ'], 'registry'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[DataProvider('invalidFilters')]
    public function test_rejects_invalid_filters(array $query, string $field): void
    {
        $this->from(route('records'))->get(route('records', $query))->assertRedirect(route('records'))->assertSessionHasErrors($field);
    }

    public function test_personal_data_still_never_appears_in_the_listing_with_the_new_filters(): void
    {
        config(['services.records_sensitive' => 'all']);
        PersonRecord::factory()->create(['street' => 'CALLE UNO', 'birth_date' => '1990-05-08', 'sex' => Sex::Female]);

        $response = $this->get(route('records', ['sex' => 'female', 'sort' => 'name']));

        $this->assertStringNotContainsString('CALLE UNO', $response->getContent());
        $this->assertStringNotContainsString('1990-05-08', $response->getContent());
    }
}
