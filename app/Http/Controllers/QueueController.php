<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QueueController extends Controller
{
    /**
     * Exibir status das filas
     */
    public function index(): View
    {
        $this->authorize('queue.viewAny');

        // Jobs pendentes por fila
        $jobsPendentes = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->get();

        // Total de jobs pendentes
        $totalPendentes = DB::table('jobs')->count();

        // Jobs falhados
        $jobsFalhados = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'job_class' => $payload['displayName'] ?? 'Desconhecido',
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                ];
            });

        // Total de jobs falhados
        $totalFalhados = DB::table('failed_jobs')->count();

        // Jobs recentes (últimos 24h)
        $jobsRecentes = \Spatie\Activitylog\Models\Activity::where('description', 'like', '%via fila%')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        // Estatísticas por tipo de job
        $estatisticasPorTipo = \Spatie\Activitylog\Models\Activity::where('description', 'like', '%via fila%')
            ->where('created_at', '>=', now()->subWeek())
            ->get()
            ->groupBy(function ($activity) {
                if (str_contains($activity->description, 'Boletim')) return 'Boletins';
                if (str_contains($activity->description, 'Pauta')) return 'Pautas';
                return 'Outros';
            })
            ->map->count();

        return view('configuracoes.queue.index', compact(
            'jobsPendentes',
            'totalPendentes',
            'jobsFalhados',
            'totalFalhados',
            'jobsRecentes',
            'estatisticasPorTipo'
        ));
    }

    /**
     * Limpar jobs falhados
     */
    public function clearFailed(): RedirectResponse
    {
        $this->authorize('queue.delete');

        try {
            Artisan::call('queue:flush');

            activity()
                ->causedBy(auth()->user())
                ->log('Jobs falhados limpos');

            return redirect()
                ->route('queue.index')
                ->with('success', 'Jobs falhados limpos com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->route('queue.index')
                ->with('error', 'Erro ao limpar jobs: ' . $e->getMessage());
        }
    }

    /**
     * Retentar job falhado específico
     */
    public function retryFailed(string $id): RedirectResponse
    {
        $this->authorize('queue.create');

        try {
            Artisan::call('queue:retry', ['id' => $id]);

            activity()
                ->causedBy(auth()->user())
                ->withProperties(['job_id' => $id])
                ->log('Job falhado reenfileirado');

            return redirect()
                ->route('queue.index')
                ->with('success', 'Job reenfileirado com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->route('queue.index')
                ->with('error', 'Erro ao retentar job: ' . $e->getMessage());
        }
    }

    /**
     * Retentar todos os jobs falhados
     */
    public function retryAll(): RedirectResponse
    {
        $this->authorize('queue.create');

        try {
            Artisan::call('queue:retry', ['id' => 'all']);

            activity()
                ->causedBy(auth()->user())
                ->log('Todos os jobs falhados reenfileirados');

            return redirect()
                ->route('queue.index')
                ->with('success', 'Todos os jobs falhados foram reenfileirados!');
        } catch (\Exception $e) {
            return redirect()
                ->route('queue.index')
                ->with('error', 'Erro ao retentar jobs: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar job falhado específico
     */
    public function deleteFailed(string $id): RedirectResponse
    {
        $this->authorize('queue.delete');

        try {
            Artisan::call('queue:forget', ['id' => $id]);

            activity()
                ->causedBy(auth()->user())
                ->withProperties(['job_id' => $id])
                ->log('Job falhado eliminado');

            return redirect()
                ->route('queue.index')
                ->with('success', 'Job eliminado com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->route('queue.index')
                ->with('error', 'Erro ao eliminar job: ' . $e->getMessage());
        }
    }
}
