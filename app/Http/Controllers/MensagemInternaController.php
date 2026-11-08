<?php

namespace App\Http\Controllers;

use App\Models\StaffConversa;
use App\Models\User;
use App\Services\Portal\StaffMensagemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MensagemInternaController extends Controller
{
    public function __construct(
        private StaffMensagemService $mensagens,
    ) {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            abort_unless($request->user() && $this->mensagens->podeUsar($request->user()), 403);

            return $next($request);
        });
    }

    public function iniciar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:20'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'corpo' => ['required', 'string', 'max:2000'],
        ], [
            'user_ids.required' => 'Seleccione pelo menos um colaborador.',
            'user_ids.min' => 'Seleccione pelo menos um colaborador.',
        ]);

        $autor = $request->user();
        $enviadas = 0;
        $ultima = null;

        foreach ($data['user_ids'] as $userId) {
            if ((int) $userId === (int) $autor->id) {
                continue;
            }
            $destinatario = User::findOrFail($userId);
            $conversa = $this->mensagens->obterOuCriar($autor, $destinatario);
            $this->mensagens->enviar($autor, $conversa, $data['corpo']);
            $enviadas++;
            $ultima = $conversa;
        }

        if ($enviadas === 0) {
            return back()->withInput()->with('error', 'Seleccione um colaborador válido.');
        }

        if ($enviadas === 1 && $ultima) {
            return redirect()
                ->route('mensagens-internas.show', $ultima)
                ->with('success', 'Conversa iniciada.');
        }

        return redirect()
            ->route('mensagens-encarregados.index')
            ->with('success', "Mensagem enviada a {$enviadas} colaboradores.");
    }

    public function show(Request $request, StaffConversa $conversa): View
    {
        abort_unless($this->mensagens->podeAceder($request->user(), $conversa), 403);

        $conversa->load(['userLow.roles', 'userHigh.roles']);
        $this->mensagens->marcarLidasPara($request->user(), $conversa);

        $outro = $conversa->outroParticipante($request->user());
        $mensagens = $conversa->mensagens()
            ->with('autor')
            ->orderBy('created_at')
            ->get();

        return view('mensagens-internas.show', [
            'conversa' => $conversa,
            'mensagens' => $mensagens,
            'outro' => $outro,
            'cargoOutro' => $outro ? $this->mensagens->cargoLabel($outro) : '—',
        ]);
    }

    public function store(Request $request, StaffConversa $conversa): RedirectResponse
    {
        abort_unless($this->mensagens->podeAceder($request->user(), $conversa), 403);

        $data = $request->validate([
            'corpo' => ['required', 'string', 'max:2000'],
        ]);

        $this->mensagens->enviar($request->user(), $conversa, $data['corpo']);

        return back()->with('success', 'Mensagem enviada.');
    }
}
