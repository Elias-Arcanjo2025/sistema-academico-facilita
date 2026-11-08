<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\PortalConversa;
use App\Services\Portal\PortalConversaService;
use App\Services\Portal\StaffMensagemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MensagemEncarregadoController extends Controller
{
    public function __construct(
        private PortalConversaService $conversas,
        private StaffMensagemService $mensagensInternas,
    ) {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            abort_unless($user && $this->conversas->podeUsarInboxStaff($user), 403);

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $conversas = $this->conversas->listarParaStaff($user);
        $conversasInternas = $this->mensagensInternas->listar($user)->map(function ($c) use ($user) {
            $outro = $c->outroParticipante($user);

            return (object) [
                'conversa' => $c,
                'outro' => $outro,
                'cargo' => $outro ? $this->mensagensInternas->cargoLabel($outro) : '—',
                'last_message_at' => $c->last_message_at,
            ];
        });

        return view('mensagens-encarregados.index', compact('conversas', 'conversasInternas'));
    }

    public function create(Request $request): View
    {
        $tipo = $request->string('tipo')->toString();
        if (! in_array($tipo, ['familia', 'interna'], true)) {
            $tipo = old('tipo', 'familia');
        }

        $alunos = $this->conversas->alunosContactaveis($request->user());
        $colaboradores = $this->mensagensInternas->colaboradoresContactaveis($request->user());

        $alunoIds = collect(old('aluno_ids', []))
            ->when($request->filled('aluno_id'), fn ($c) => $c->push($request->integer('aluno_id')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $userIds = collect(old('user_ids', []))
            ->when($request->filled('user_id'), fn ($c) => $c->push($request->integer('user_id')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('mensagens-encarregados.create', [
            'tipo' => $tipo,
            'alunos' => $alunos,
            'colaboradores' => $colaboradores,
            'q' => $request->string('q')->toString(),
            'alunoIds' => $alunoIds,
            'userIds' => $userIds,
            'mensagensInternas' => $this->mensagensInternas,
        ]);
    }

    public function iniciar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aluno_ids' => ['required', 'array', 'min:1', 'max:30'],
            'aluno_ids.*' => ['integer', 'distinct', 'exists:alunos,id'],
            'corpo' => ['required', 'string', 'max:2000'],
        ], [
            'aluno_ids.required' => 'Seleccione pelo menos um aluno.',
            'aluno_ids.min' => 'Seleccione pelo menos um aluno.',
        ]);

        $user = $request->user();
        $enviadas = 0;
        $ultimaConversa = null;

        foreach ($data['aluno_ids'] as $alunoId) {
            $aluno = Aluno::query()->with(['turma', 'encarregados.user'])->findOrFail($alunoId);
            $conversa = $this->conversas->obterOuCriarParaStaff($user, $aluno);
            $this->conversas->enviar($user, $conversa, $data['corpo']);
            $enviadas++;
            $ultimaConversa = $conversa;
        }

        if ($enviadas === 1 && $ultimaConversa) {
            return redirect()
                ->route('mensagens-encarregados.show', $ultimaConversa)
                ->with('success', 'Conversa iniciada. O encarregado foi notificado.');
        }

        return redirect()
            ->route('mensagens-encarregados.index')
            ->with('success', "Mensagem enviada a {$enviadas} encarregados.");
    }

    public function show(Request $request, PortalConversa $conversa): View
    {
        abort_unless($this->conversas->podeAcederComoStaff($request->user(), $conversa), 403);

        $conversa->load(['aluno.turma', 'encarregadoUser']);
        $this->conversas->marcarLidasPara($request->user(), $conversa);

        $mensagens = $conversa->mensagens()
            ->with('autor')
            ->orderBy('created_at')
            ->get();

        return view('mensagens-encarregados.show', compact('conversa', 'mensagens'));
    }

    public function store(Request $request, PortalConversa $conversa): RedirectResponse
    {
        abort_unless($this->conversas->podeAcederComoStaff($request->user(), $conversa), 403);

        $data = $request->validate([
            'corpo' => ['required', 'string', 'max:2000'],
        ]);

        $this->conversas->enviar($request->user(), $conversa, $data['corpo']);

        return back()->with('success', 'Mensagem enviada.');
    }
}
