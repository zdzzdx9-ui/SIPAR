<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Peminjaman;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Peminjaman::where('status', 'Approved')
        ->whereDate('end_date', '<', today())
        ->update(['overdue_flag' => true, 'reminder_flag' => true]);
})->dailyAt('00:05')->name('loans:mark-overdue');
