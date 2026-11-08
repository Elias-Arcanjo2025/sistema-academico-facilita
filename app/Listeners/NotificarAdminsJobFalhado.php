<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\JobFalhadoNotification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificarAdminsJobFalhado
{
    public function handle(JobFailed $event): void
    {
        $jobName = class_basename($event->job->resolveName());
        $cacheKey = 'job_failed_notify:'.md5($jobName.$event->exception->getMessage());

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes(15));

        Log::error('Job falhou na fila', [
            'job' => $event->job->resolveName(),
            'erro' => $event->exception->getMessage(),
        ]);

        $admins = User::role('admin_full')->get();

        foreach ($admins as $admin) {
            $admin->notify(new JobFalhadoNotification(
                $jobName,
                0,
                $event->exception->getMessage()
            ));
        }
    }
}
