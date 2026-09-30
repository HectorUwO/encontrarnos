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
 * Solo se toman los datos que muestra la ficha pública. El domicilio, la fecha
 * y el lugar de nacimiento y el resto de datos personales que trae el registro
 * se dejan fuera a propósito; cuando se decida qué más publicar basta con
 * agregarlos aquí.
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
}
