<?php

namespace Tests\Feature\Models;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Models\RegistryCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistryCountTest extends TestCase
{
    use RefreshDatabase;

    private function createMonths(): void
    {
        foreach (['2019-12-01', '2020-01-01', '2021-06-01', '2022-12-01', '2023-01-01'] as $month) {
            RegistryCount::factory()->create(['month' => $month, 'total' => 1]);
        }

        RegistryCount::factory()->undated()->create(['total' => 1]);
        RegistryCount::factory()->confidential()->create(['total' => 1]);
    }

    /**
     * @return list<string>
     */
    private function monthsWithin(?int $from, ?int $to): array
    {
        return RegistryCount::query()
            ->withinYears($from, $to)
            ->whereNotNull('month')
            ->orderBy('month')
            ->get()
            ->map(fn (RegistryCount $count): string => $count->month->format('Y-m'))
            ->all();
    }

    #[DataProvider('periods')]
    public function test_within_years_includes_both_ends_of_the_period(?int $from, ?int $to, array $expected): void
    {
        $this->createMonths();

        $this->assertSame($expected, $this->monthsWithin($from, $to));
    }

    /**
     * @return array<string, array{int|null, int|null, list<string>}>
     */
    public static function periods(): array
    {
        return [
            'both ends' => [2020, 2022, ['2020-01', '2021-06', '2022-12']],
            'only a start' => [2022, null, ['2022-12', '2023-01']],
            'only an end' => [null, 2020, ['2019-12', '2020-01']],
            'a single year' => [2021, 2021, ['2021-06']],
        ];
    }

    public function test_a_period_excludes_undated_and_confidential_records(): void
    {
        $this->createMonths();

        $this->assertSame(5, RegistryCount::query()->withinYears(1900, 2100)->count());
    }

    public function test_without_a_period_every_record_is_included(): void
    {
        $this->createMonths();

        $this->assertSame(7, RegistryCount::query()->withinYears(null, null)->count());
    }

    public function test_in_state_scope_filters_by_state_and_ignores_null(): void
    {
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create();
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->create();

        $this->assertSame(1, RegistryCount::query()->inState(MexicanState::Jalisco)->count());
        $this->assertSame(2, RegistryCount::query()->inState(null)->count());
    }

    public function test_casts_its_columns_to_enums_dates_and_booleans(): void
    {
        $count = RegistryCount::factory()->inState(MexicanState::Nayarit)->create([
            'month' => '2024-02-01',
            'sex' => Sex::Female,
            'age_range' => AgeRange::Adult,
            'status' => DisappearanceStatus::NotLocated,
            'confidential' => false,
            'total' => 7,
        ])->refresh();

        $this->assertSame(MexicanState::Nayarit, $count->state);
        $this->assertSame('2024-02-01', $count->month->toDateString());
        $this->assertSame(Sex::Female, $count->sex);
        $this->assertSame(AgeRange::Adult, $count->age_range);
        $this->assertSame(DisappearanceStatus::NotLocated, $count->status);
        $this->assertFalse($count->confidential);
        $this->assertSame(7, $count->total);
    }
}
