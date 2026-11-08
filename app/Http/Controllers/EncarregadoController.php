<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\Encarregado;
use App\Services\EncarregadoAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EncarregadoController extends Controller
{
    public function __construct(
        private readonly EncarregadoAuthService $authService,
    ) {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (! $user?->temControloAcademicoInstitucional() && ! $user?->temAcessoSecretariaAlunos()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(): View
    {
        $encarregados = Encarregado::query()
            ->withCount('alunos')
            ->orderBy('nome')
            ->paginate(30);

        return view('encarregados.index', compact('encarregados'));
    }

    public function create(): View
    {
        $alunos = Aluno::query()->where('activo', true)->orderBy('nome')->limit(500)->get(['id', 'nome', 'numero_processo']);

        return view('encarregados.create', compact('alunos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'documento' => ['nullable', 'string', 'max:50'],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
            'aluno_ids' => ['nullable', 'array'],
            'aluno_ids.*' => ['integer', 'exists:alunos,id'],
            'criar_conta' => ['nullable', 'boolean'],
        ]);

        $encarregado = Encarregado::query()->create([
            'nome' => $data['nome'],
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'documento' => $data['documento'] ?? null,
            'whatsapp_opt_in' => (bool) ($data['whatsapp_opt_in'] ?? false),
            'whatsapp_opt_in_at' => ! empty($data['whatsapp_opt_in']) ? now() : null,
            'activo' => true,
        ]);

        foreach ($data['aluno_ids'] ?? [] as $i => $alunoId) {
            $encarregado->alunos()->attach($alunoId, [
                'parentesco' => 'encarregado',
                'principal' => $i === 0,
                'pode_ver_notas' => true,
                'pode_assinar_boletim' => true,
            ]);
        }

        $senha = null;
        if (! empty($data['criar_conta'])) {
            $user = $this->authService->criarOuActualizarConta($encarregado);
            $senha = $user->getAttribute('password_plain_once');
        }

        return redirect()
            ->route('encarregados.index')
            ->with('success', 'Encarregado registado.'.($senha ? " Conta criada. Senha temporária: {$senha}" : ''));
    }
}
