<?php

namespace App\Services\Rnpdno;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Resume el listado público del registro nacional en conteos. No conserva
 * nombres ni identificadores: cada registro solo suma uno a la combinación de
 * estado, municipio, mes, sexo, edad y estatus a la que pertenece.
 *
 * El registro mantiene confidenciales cerca de la tercera parte de sus
 * registros; de ellos solo se conoce el estado y el municipio.
 */
class RnpdnoListingAggregator
{
    private const MAXIMUM_AGE = 110;

    /**
     * @var array<string, array{
     *     state: string|null,
     *     municipality: string|null,
     *     month: string|null,
     *     sex: string|null,
     *     age_range: string|null,
     *     status: string|null,
     *     confidential: bool,
     *     total: int,
     * }>
     */
    private array $counts = [];

    /**
     * @param  array<string, mixed>  $fields  Campos del registro tal como los publica el listado.
     */
    public function add(int $stateCode, string $municipality, bool $confidential, array $fields): void
    {
        $confidential = $confidential || $this->isConfidential($fields['Sexo'] ?? null);

        $row = [
            'state' => MexicanState::fromCode($stateCode)?->value,
            'municipality' => $this->municipality($municipality),
            'month' => null,
            'sex' => null,
            'age_range' => null,
            'status' => null,
            'confidential' => $confidential,
        ];

        if (! $confidential) {
            $event = $this->eventDate($fields);
            $row['month'] = $event?->format('Y-m-01');
            $row['sex'] = Sex::fromSource($this->string($fields['Sexo'] ?? null))->value;
            $row['age_range'] = AgeRange::fromAge($this->ageAt($event, $fields['fechanacimiento'] ?? null))?->value;
            $row['status'] = DisappearanceStatus::fromSource($this->string($fields['EstatusVictima'] ?? null))?->value;
        }

        $key = implode('|', array_map(fn (mixed $value): string => (string) $value, $row));

        $this->counts[$key] ??= [...$row, 'total' => 0];
        $this->counts[$key]['total']++;
    }

    /**
     * @return list<array{
     *     state: string|null,
     *     municipality: string|null,
     *     month: string|null,
     *     sex: string|null,
     *     age_range: string|null,
     *     status: string|null,
     *     confidential: bool,
     *     total: int,
     * }>
     */
    public function rows(): array
    {
        return array_values($this->counts);
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function isConfidential(mixed $value): bool
    {
        return Str::of((string) $this->string($value))->squish()->upper()->toString() === 'CONFIDENCIAL';
    }

    private function municipality(string $name): ?string
    {
        $name = Str::squish($name);

        return in_array(Str::of($name)->ascii()->upper()->toString(), ['', 'SIN DATO', 'CONFIDENCIAL', 'SE DESCONOCE'], true)
            ? null
            : $name;
    }

    /**
     * La fecha de los hechos llega ya en formato día/mes/año («ffechahechos»)
     * y, como respaldo, en ISO 8601 con hora UTC («fechahechos»).
     *
     * @param  array<string, mixed>  $fields
     */
    private function eventDate(array $fields): ?DateTimeImmutable
    {
        return $this->parse($this->string($fields['ffechahechos'] ?? null), 'd/m/Y')
            ?? $this->parse(substr((string) $this->string($fields['fechahechos'] ?? null), 0, 10), 'Y-m-d');
    }

    private function parse(?string $value, string $format): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!'.$format, trim($value));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        $year = (int) $date->format('Y');

        return $year >= 1900 && $year <= now()->addYear()->year ? $date : null;
    }

    /**
     * Edad cumplida el día de los hechos; los valores imposibles (fechas de
     * nacimiento posteriores o de hace más de 110 años) se descartan.
     */
    private function ageAt(?DateTimeImmutable $event, mixed $birthDate): ?int
    {
        $birth = $this->parse(substr((string) $this->string($birthDate), 0, 10), 'Y-m-d');

        if ($event === null || $birth === null) {
            return null;
        }

        $difference = $birth->diff($event);

        return $difference->invert === 1 || $difference->y > self::MAXIMUM_AGE ? null : $difference->y;
    }
}
