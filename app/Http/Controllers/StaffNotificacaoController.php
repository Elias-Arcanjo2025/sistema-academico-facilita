<?php

namespace App\Http\Controllers;

use App\Support\NotificationDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class StaffNotificacaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if ($request->user()?->hasRole('aluno')) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $query = $request->user()->notifications()->latest();

        if ($request->get('estado') === 'nao_lidas') {
            $query->whereNull('read_at');
        }

        if ($tipo = $request->get('tipo')) {
            $query->where('data->tipo', $tipo);
        }

        $notificacoes = $query->paginate(20)->withQueryString();

        return view('notificacoes.index', [
            'notificacoes' => $notificacoes,
            'notificacoesNaoLidas' => $request->user()->unreadNotifications()->count(),
            'filtroEstado' => $request->get('estado', 'todas'),
            'filtroTipo' => $request->get('tipo'),
        ]);
    }

    public function abrir(Request $request, string $id): RedirectResponse
    {
        $notification = $this->findNotification($request, $id);
        $notification->markAsRead();

        return redirect(NotificationDestination::url($notification, route('dashboard'), 'staff'));
    }

    public function marcarLida(Request $request, string $id): RedirectResponse
    {
        $this->findNotification($request, $id)->markAsRead();

        return back()->with('success', 'Notificação marcada como lida.');
    }

    public function marcarTodasLidas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    /**
     * Polling leve para toast quando chega mensagem / alerta.
     */
    public function polling(Request $request): JsonResponse
    {
        $user = $request->user();
        $desde = (int) $request->query('desde', 0);

        $query = $user->unreadNotifications()->latest();
        if ($desde > 0) {
            $query->where('created_at', '>', \Illuminate\Support\Carbon::createFromTimestamp($desde));
        }

        $recentes = $query->limit(5)->get()->map(function (DatabaseNotification $n) {
            $data = $n->data ?? [];

            return [
                'id' => $n->id,
                'tipo' => $data['tipo'] ?? $data['tipo_evento'] ?? 'info',
                'titulo' => $data['titulo'] ?? 'Notificação',
                'mensagem' => $data['mensagem'] ?? '',
                'url' => NotificationDestination::url($n, route('notificacoes.index'), 'staff'),
                'created_at' => $n->created_at?->timestamp,
            ];
        });

        return response()->json([
            'nao_lidas' => $user->unreadNotifications()->count(),
            'agora' => now()->timestamp,
            'itens' => $recentes,
        ]);
    }

    private function findNotification(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();

        return $notification;
    }
}
