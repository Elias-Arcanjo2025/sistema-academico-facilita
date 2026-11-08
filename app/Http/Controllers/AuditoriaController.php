<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
    /**
     * Lista de logs de auditoria com filtros
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        // Filtro por período
        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->input('data_inicio'));
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->input('data_fim'));
        }

        // Filtro por utilizador
        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->input('user_id'));
        }

        // Filtro por tipo de acção (log_name)
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        // Filtro por entidade (subject_type)
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        // Filtro por evento
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        // Pesquisa por descrição
        if ($request->filled('pesquisa')) {
            $query->where('description', 'like', '%' . $request->input('pesquisa') . '%');
        }

        $logs = $query->paginate(10)->withQueryString();

        // Dados para os filtros
        $utilizadores = \App\Models\User::orderBy('name')->get();
        $logNames = Activity::distinct()->pluck('log_name')->filter();
        $subjectTypes = Activity::distinct()->pluck('subject_type')->filter()->map(function ($type) {
            return [
                'value' => $type,
                'label' => class_basename($type),
            ];
        });
        $events = Activity::distinct()->pluck('event')->filter();

        return view('auditoria.index', compact(
            'logs',
            'utilizadores',
            'logNames',
            'subjectTypes',
            'events'
        ));
    }

    /**
     * Mostrar detalhes de um registo de log
     */
    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);

        $activity->load(['causer', 'subject']);

        return view('auditoria.show', compact('activity'));
    }

    /**
     * Exportar logs filtrados para CSV
     */
    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        // Aplicar os mesmos filtros da listagem
        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->input('data_inicio'));
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->input('data_fim'));
        }

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->input('user_id'));
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('pesquisa')) {
            $query->where('description', 'like', '%' . $request->input('pesquisa') . '%');
        }

        // Registar a exportação
        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'filtros' => $request->only(['data_inicio', 'data_fim', 'user_id', 'log_name', 'subject_type', 'event', 'pesquisa']),
            ])
            ->log('Exportacao de logs de auditoria');

        $filename = 'auditoria_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Cabeçalho do CSV
            fputcsv($handle, [
                'ID',
                'Data/Hora',
                'Utilizador',
                'Log',
                'Evento',
                'Descricao',
                'Entidade',
                'ID Entidade',
                'Propriedades',
            ], ';');

            // Processar em chunks para evitar problemas de memória
            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('d/m/Y H:i:s'),
                        $log->causer?->name ?? 'Sistema',
                        $log->log_name,
                        $log->event ?? '-',
                        $log->description,
                        $log->subject_type ? class_basename($log->subject_type) : '-',
                        $log->subject_id ?? '-',
                        json_encode($log->properties->toArray(), JSON_UNESCAPED_UNICODE),
                    ], ';');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Estatísticas de auditoria
     */
    public function estatisticas(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $periodo = $request->input('periodo', 30);

        // Actividades por dia nos últimos X dias
        $actividadesPorDia = Activity::selectRaw('DATE(created_at) as data, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($periodo))
            ->groupBy('data')
            ->orderBy('data')
            ->get();

        // Top 10 utilizadores mais activos
        $utilizadoresMaisActivos = Activity::selectRaw('causer_id, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($periodo))
            ->whereNotNull('causer_id')
            ->groupBy('causer_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('causer')
            ->get();

        // Actividades por tipo de evento
        $actividadesPorEvento = Activity::selectRaw('event, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($periodo))
            ->groupBy('event')
            ->orderByDesc('total')
            ->get();

        // Actividades por entidade
        $actividadesPorEntidade = Activity::selectRaw('subject_type, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($periodo))
            ->whereNotNull('subject_type')
            ->groupBy('subject_type')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                $item->entidade = class_basename($item->subject_type);
                return $item;
            });

        return view('auditoria.estatisticas', compact(
            'periodo',
            'actividadesPorDia',
            'utilizadoresMaisActivos',
            'actividadesPorEvento',
            'actividadesPorEntidade'
        ));
    }
}
