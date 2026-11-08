<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup automatico da base de dados - diariamente as 02:00
Schedule::command('backup:database --retention=30')
    ->dailyAt('02:00')
    ->appendOutputTo(storage_path('logs/backup.log'))
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Controlo Facilita: heartbeat + sync de alunos (modo school)
// Intervalo configurável via CONTROL_SYNC_INTERVAL (minutos, 1–59; defeito 30)
$controlSyncMinutes = max(1, min(59, (int) config('control.sync_interval_minutes', 30)));
Schedule::command('control:sync')
    ->cron("*/{$controlSyncMinutes} * * * *")
    ->when(fn () => config('control.mode') === 'school')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/control-sync.log'));

// Hub: aplicar avisos/atrasos/bloqueios automáticos das cobranças (a cada minuto)
Schedule::command('control:process-cobranca-prazos')
    ->everyMinute()
    ->when(fn () => config('control.mode') === 'hub')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/control-cobranca-prazos.log'));

// Portal encarregado: resumo semanal (segunda 07:00)
Schedule::command('portal:encarregado-resumo-semanal')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/portal-encarregado-resumo.log'));

// Portal encarregado: alertas proactivos (diário 18:00)
Schedule::command('portal:encarregado-alertas-proactivos')
    ->dailyAt('18:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/portal-encarregado-alertas.log'));

// Retenção: soft-deletes com mais de 90 dias (domingo 03:30)
Schedule::command('facilita:purge-soft-deletes --days=90')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/purge-soft-deletes.log'));

