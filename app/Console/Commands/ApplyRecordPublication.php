<?php

namespace App\Console\Commands;

use App\Models\PersonRecord;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('records:publication {mode? : all para publicar todas, registry para solo las que autoriza el registro (por omisión, el valor de RECORDS_PUBLISH)}')]
#[Description('Publica u oculta fichas según lo que autoriza el registro, sin volver a importar')]
class ApplyRecordPublication extends Command
{
    public function handle(): int
    {
        $mode = $this->argument('mode') ?? config('services.records_publish');

        if (! in_array($mode, ['all', 'registry'], true)) {
            $this->components->error('El modo debe ser «all» o «registry».');

            return self::FAILURE;
        }

        $now = now();

        if ($mode === 'all') {
            $changed = PersonRecord::query()->whereNull('published_at')->update(['published_at' => $now]);
            $this->components->info(number_format($changed).' fichas publicadas; ahora se muestran todas.');
        } else {
            $hidden = PersonRecord::query()
                ->where(fn ($query) => $query->whereNull('registry_publish')->orWhere('registry_publish', '!=', 'SI'))
                ->whereNotNull('published_at')
                ->update(['published_at' => null]);
            $shown = PersonRecord::query()
                ->where('registry_publish', 'SI')
                ->whereNull('published_at')
                ->update(['published_at' => $now]);
            $this->components->info(sprintf('%s fichas ocultas y %s publicadas: solo se muestran las que autoriza el registro.', number_format($hidden), number_format($shown)));
        }

        Cache::forget(PersonRecord::PUBLISHED_COUNT_KEY);

        if (config('services.records_search') === 'meilisearch') {
            $this->call('records:index');
        }

        $this->components->twoColumnDetail('Públicas', number_format(PersonRecord::query()->published()->count()));
        $this->components->twoColumnDetail('Ocultas', number_format(PersonRecord::query()->whereNull('published_at')->count()));

        return self::SUCCESS;
    }
}
