<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reports:run')->daily();
Schedule::command('backup:database')
    ->dailyAt('02:15')
    ->when(fn (): bool => config('database.default') === 'mysql');
