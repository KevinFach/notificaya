<?php

use App\Console\Commands\SyncFastSmsStatuses;
use App\Console\Commands\SyncReservaYaAppointments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SyncFastSmsStatuses::class)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(SyncReservaYaAppointments::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping();
