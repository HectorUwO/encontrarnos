<?php

namespace App\Services\Rnpdno;

use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use App\Enums\Sex;
use App\Models\PersonRecord;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Convierte los campos de una ficha del registro nacional en atributos de
 * PersonRecord.
 *
 * Se guarda todo lo que trae el registro, incluidos los datos personales
 * (nacimiento y domicilio): quién los puede ver se decide en la aplicación
 * (habilidad `view-sensitive-record-data`), no aquí. Los identificadores del
 * registro también se guardan, pero nunca salen de la aplicación.
 */
class RnpdnoRecordMapper
{
    /**
     * Valores que el registro usa para indicar «sin información».
     */
    private const MISSING_VALUES = ['', 'SIN DATO', '*', 'SE DESCONOCE', 'NO APLICA', 'NO ESPECIFICADO'];

    private const MAXIMUM_AGE = 110;

    /**
     * @param  array<string, mixed>  $fields  Clave => valor ya decodificado.
     * @return array<string, mixed> Atributos listos para un upsert (`traits` va como JSON).
     */
    public function map(array $fields): array
    {
        $traits = $this->traits($fields['MediaFiliacion'] ?? null);
        $clothing = $this->text($fields['PrendasDeVestir'] ?? null);
        $marks = $this->text($fields['SanaParticular'] ?? null);

        return [
            'type' => RecordType::MissingPerson->value,
            'disappearance_status' => DisappearanceStatus::fromSource($this->string($fields['EstatusVictima'] ?? null))?->value,
            'sex' => Sex::fromSource($this->string($fields['Sexo'] ?? null))->value,
            'name' => $this->name($fields),
            'age' => $this->age($fields['edadHechos'] ?? null) ?? $this->age($fields['edadanios'] ?? null),
            'current_age' => $this->age($fields['edadActual'] ?? null),
            'state' => MexicanState::fromSource($this->string($fields['estadoHecho'] ?? $fields['estado'] ?? null))?->value,
            'municipality' => $this->text($fields['municipioHecho'] ?? $fields['municipio'] ?? null),
            'event_date' => $this->date($fields['ffechahechos'] ?? null, 'd/m/Y')
                ?? $this->date($fields['fechahechos'] ?? null, 'n/j/Y'),
            'description' => $this->description($traits, $marks, $clothing),
            'traits' => $traits === [] ? null : json_encode($traits, JSON_UNESCAPED_UNICODE),
            'clothing' => $clothing,
            'distinguishing_marks' => $marks,
            'authority' => $this->text($fields['PertenenciaDependenicaOrigen'] ?? null),
            'registry_publish' => $this->registryPublish($fields['PublicarFicha'] ?? null),
            'origin' => $this->text($fields['Inicio'] ?? null),
            'search_only' => $this->boolean($fields['SoloBusqueda'] ?? null),
            'referred_to' => $this->referrals($fields['PertenenciaPorCanalizacion'] ?? null),
            'migration_file' => $this->text($fields['archivomigracion'] ?? null),
            'noticed_date' => $this->flexibleDate($fields['ffechapercato'] ?? null, true)
                ?? $this->flexibleDate($fields['fechapercato'] ?? null),
            'registered_date' => $this->flexibleDate($fields['fechacaptura'] ?? null),
            'source_updated_at' => $this->dateTime($fields['fechaAct'] ?? null),
            'registered_age_years' => $this->age($fields['edadanios'] ?? null),
            'registered_age_months' => $this->smallNumber($fields['edadmeses'] ?? null, 11),
            'registered_age_days' => $this->smallNumber($fields['edaddias'] ?? null, 31),
            'nationality' => $this->text($fields['Nacionalidad'] ?? null),
            'speaks_spanish' => $this->boolean($fields['hablaespaniol'] ?? null),
            'has_disability' => $this->boolean($fields['TieneDiscapacidad'] ?? null),
            'disability_type' => $this->text($fields['TipoDiscapacidad'] ?? null),
            'birth_date' => $this->flexibleDate($fields['fechanacimiento'] ?? null),
            'birth_state' => $this->text($fields['estadonacimiento'] ?? null),
            'birth_place' => $this->text($fields['lugarnacimiento'] ?? null),
            'street' => $this->text($fields['calle'] ?? null),
            'exterior_number' => $this->text($fields['noexterior'] ?? null),
            'interior_number' => $this->text($fields['nointerior'] ?? null),
            'postal_code' => $this->text($fields['codigopostal'] ?? null),
            'neighborhood' => $this->text($fields['nombreasentamiento'] ?? null),
            'source_authority_id' => $this->smallNumber($fields['iddependenciaorigen'] ?? null, 4294967295),
        ];
    }

    /**
     * El registro marca cada ficha como publicable o no; cuando no lo indica
     * («SIN DATO») se trata como no publicable.
     *
     * @param  array<string, mixed>  $fields
     */
    public function isPublishable(array $fields): bool
    {
        return $this->normalize($this->string($fields['PublicarFicha'] ?? null)) === 'SI';
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function normalize(?string $value): string
    {
        return Str::of((string) $value)->squish()->ascii()->upper()->toString();
    }

    /**
     * Texto limpio: sin etiquetas HTML, con espacios normalizados y `null`
     * cuando el registro no trae información.
     */
    private function text(mixed $value): ?string
    {
        $text = $this->string($value);

        if ($text === null) {
            return null;
        }

        $text = preg_replace('/<br\s*\/?>/i', ' ', $text) ?? $text;
        $text = Str::squish(html_entity_decode(strip_tags($text)));

        return in_array($this->normalize($text), self::MISSING_VALUES, true) ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function name(array $fields): ?string
    {
        $parts = collect(['nombre', 'primerapellido', 'segundoapellido'])
            ->map(fn (string $key): ?string => $this->text($fields[$key] ?? null))
            ->filter()
            ->all();

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * La edad llega como número, como texto numérico o dentro de una frase
     * («Edad capturada en el sistema 15»); los valores imposibles se descartan.
     */
    private function age(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $age = (int) $value;
        } elseif (is_string($value) && preg_match('/(\d{1,4})\s*$/', $value, $matches) === 1) {
            $age = (int) $matches[1];
        } else {
            return null;
        }

        return $age >= 0 && $age <= self::MAXIMUM_AGE ? $age : null;
    }

    private function date(mixed $value, string $format): ?string
    {
        $text = $this->string($value);

        if ($text === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!'.$format, trim($text));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        $year = (int) $date->format('Y');

        return $year >= 1900 && $year <= now()->addYear()->year ? $date->format('Y-m-d') : null;
    }

    /**
     * La media filiación llega como líneas «ETIQUETA: valor» separadas por
     * `<br>` o, en algunas fichas, por comas.
     *
     * @return array<string, string>
     */
    private function traits(mixed $value): array
    {
        $text = $this->string($value);

        if ($text === null) {
            return [];
        }

        $lines = preg_split('/(?i:<br\s*\/?>)|\R|,\s*(?=[A-ZÁÉÍÓÚÜÑ ]{3,}:)/u', $text) ?: [];
        $traits = [];

        foreach ($lines as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$label, $detail] = explode(':', $line, 2);
            $detail = $this->text($detail);
            $key = Str::slug($label, '_');

            if ($key !== '' && $detail !== null) {
                $traits[$key] = $detail;
            }
        }

        return $traits;
    }

    /**
     * Resumen en texto corrido para tarjetas y búsquedas.
     *
     * @param  array<string, string>  $traits
     */
    private function description(array $traits, ?string $marks, ?string $clothing): ?string
    {
        $sentences = [];

        foreach ($traits as $key => $detail) {
            $sentences[] = PersonRecord::traitLabel($key).': '.Str::lower($detail).'.';
        }

        if ($marks !== null) {
            $sentences[] = 'Señas particulares: '.Str::lower($marks).'.';
        }

        if ($clothing !== null) {
            $sentences[] = 'Prendas de vestir: '.Str::lower($clothing).'.';
        }

        return $sentences === [] ? null : implode(' ', $sentences);
    }

    /**
     * «SI» / «NO»; cualquier otra cosa («*», «SIN DATO») es desconocido.
     */
    private function boolean(mixed $value): ?bool
    {
        return match ($this->normalize($this->string($value))) {
            'SI' => true,
            'NO' => false,
            default => null,
        };
    }

    private function smallNumber(mixed $value, int $maximum): ?int
    {
        if (! is_scalar($value) || preg_match('/^\s*(\d{1,10})\s*$/', (string) $value, $matches) !== 1) {
            return null;
        }

        $number = (int) $matches[1];

        return $number <= $maximum ? $number : null;
    }

    /**
     * Las autoridades a las que se remitió el caso llegan separadas por «|».
     *
     * @return string|null JSON con la lista, o null si no hay
     */
    private function referrals(mixed $value): ?string
    {
        $items = collect(explode('|', (string) $this->string($value)))
            ->map(fn (string $item): ?string => $this->text($item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items === [] ? null : json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Fecha en cualquiera de los formatos del registro: ISO, «06/04/2024»
     * (día primero) o «4/6/2024» (mes primero). Si un número mayor a 12 lo
     * aclara se respeta; si no, se usa el orden que indica `$dayFirst`.
     */
    private function flexibleDate(mixed $value, bool $dayFirst = false): ?string
    {
        $text = trim((string) $this->string($value));

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $text, $iso) === 1) {
            return $this->validDate((int) $iso[1], (int) $iso[2], (int) $iso[3]);
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $text, $parts) !== 1) {
            return null;
        }

        [$first, $second, $year] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        $isDayFirst = $first > 12 ? true : ($second > 12 ? false : $dayFirst);

        return $isDayFirst
            ? $this->validDate($year, $second, $first)
            : $this->validDate($year, $first, $second);
    }

    private function validDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year) || $year < 1900 || $year > now()->addYear()->year) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * «28/9/2026, 19:15:21» (día primero, como lo escribe el registro).
     */
    private function dateTime(mixed $value): ?string
    {
        $text = trim((string) $this->string($value));

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4}),?\s+(\d{1,2}):(\d{2})(?::(\d{2}))?#', $text, $parts) !== 1) {
            return null;
        }

        $date = $this->flexibleDate($parts[1].'/'.$parts[2].'/'.$parts[3], true);

        if ($date === null || (int) $parts[4] > 23 || (int) $parts[5] > 59) {
            return null;
        }

        return sprintf('%s %02d:%02d:%02d', $date, (int) $parts[4], (int) $parts[5], (int) ($parts[6] ?? 0));
    }

    /**
     * Lo que dice el registro sobre publicar la ficha: SI, NO o SIN DATO.
     */
    private function registryPublish(mixed $value): ?string
    {
        return match ($this->normalize($this->string($value))) {
            'SI' => 'SI',
            'NO' => 'NO',
            '' => null,
            default => 'SIN DATO',
        };
    }
}
