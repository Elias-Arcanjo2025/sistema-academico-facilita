<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\NotificationDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class PortalNotificacaoController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->hasRole('aluno') || ! $request->user()->aluno_id) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $notificacoes = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('portal.aluno.notificacoes', [
            'notificacoes' => $notificacoes,
            'notificacoesNaoLidas' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function abrir(Request $request, string $id): RedirectResponse
    {
        $notification = $this->findNotification($request, $id);
        $notification->markAsRead();

        return redirect(NotificationDestination::url($notification, route('portal.aluno.dashboard'), 'portal'));
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

    private function findNotification(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();

        return $notification;
    }
}
