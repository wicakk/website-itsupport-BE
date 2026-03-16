<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\PollServerMetrics;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PollServerMetrics)->everyFiveSeconds();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
