<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SessaoActivaController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $sessaoActual = $request->session()->getId();

        $sessoes = collect();
        if (config('session.driver') === 'database') {
            $sessoes = DB::table('sessions')
                ->where('user_id', $userId)
                ->orderByDesc('last_activity')
                ->get()
                ->map(function ($s) use ($sessaoActual) {
                    return [
                        'id' => $s->id,
                        'ip' => $s->ip_address,
                        'agente' => 'Navegador web',
                        'actual' => $s->id === $sessaoActual,
                        'ultima' => \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                    ];
                });
        }

        return view('profile.sessoes', compact('sessoes', 'sessaoActual'));
    }

    public function terminar(Request $request, string $sessionId): RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 404);

        $registo = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $registo) {
            return back()->with('error', 'Sessão não encontrada.');
        }

        if ($sessionId === $request->session()->getId()) {
            return back()->with('error', 'Não pode terminar a sessão actual por aqui — use terminar sessão.');
        }

        DB::table('sessions')->where('id', $sessionId)->delete();

        return back()->with('success', 'Sessão terminada.');
    }
}
