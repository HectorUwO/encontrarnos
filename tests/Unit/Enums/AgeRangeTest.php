<?php

namespace Tests\Unit\Enums;

use App\Enums\AgeRange;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgeRangeTest extends TestCase
{
    #[DataProvider('ages')]
    public function test_assigns_each_age_to_its_range(?int $age, ?AgeRange $expected): void
    {
        $this->assertSame($expected, AgeRange::fromAge($age));
    }

    /**
     * @return array<string, array{int|null, AgeRange|null}>
     */
    public static function ages(): array
    {
        return [
            'newborn' => [0, AgeRange::Minor],
            'oldest minor' => [17, AgeRange::Minor],
            'youngest adult' => [18, AgeRange::YoungAdult],
            'end of the youngest range' => [29, AgeRange::YoungAdult],
            'start of the thirties' => [30, AgeRange::Adult],
            'end of the thirties' => [39, AgeRange::Adult],
            'start of the last range' => [40, AgeRange::Mature],
            'very old' => [110, AgeRange::Mature],
            'unknown' => [null, null],
        ];
    }

    public function test_ranges_do_not_overlap_or_leave_gaps(): void
    {
        foreach (range(0, 110) as $age) {
            $matching = array_filter(AgeRange::cases(), function (AgeRange $range) use ($age): bool {
                [$minimum, $maximum] = $range->bounds();

                return $age >= $minimum && ($maximum === null || $age <= $maximum);
            });

            $this->assertCount(1, $matching, "La edad {$age} debe pertenecer a un solo rango.");
        }
    }
}
