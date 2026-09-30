<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('faces:index --prune --limit=1000')
    ->hourly()
    ->withoutOverlapping()
    ->when(fn (): bool => config('services.compreface.auto_index')
        && config('services.compreface.enabled') && filled(config('services.compreface.key')));
