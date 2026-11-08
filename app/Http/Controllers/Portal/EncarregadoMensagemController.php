<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Services\Portal\EncarregadoPortalAcessoService;
use App\Services\Portal\PortalConversaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EncarregadoMensagemController extends Controller
{
    public function __construct(
        private EncarregadoPortalAcessoService $acesso,
        private PortalConversaService $conversas,
    ) {
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->hasRole('encarregado') || ! $request->user()->encarregado_id) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View|RedirectResponse
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $filhos = $encarregado->alunos()
            ->with(['turma'])
            ->where('alunos.activo', true)
            ->orderBy('nome')
            ->get();

        if ($filhos->count() === 1) {
            return redirect()->route('portal.encarregado.mensagens.show', $filhos->first());
        }

        return view('portal.encarregado.mensagens-index', [
            'encarregado' => $encarregado,
            'filhos' => $filhos,
        ]);
    }

    public function show(Request $request, Aluno $aluno): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $this->acesso->assertPodeVer($encarregado, $aluno);

        $conversa = $this->conversas->obterOuCriarParaEncarregado($request->user(), $aluno);
        $this->conversas->marcarLidasPara($request->user(), $conversa);

        $mensagens = $conversa->mensagens()
            ->with('autor')
            ->orderBy('created_at')
            ->get();

        $directores = $this->conversas->directoresDaTurmaDoAluno($aluno);
        $equipa = $this->conversas->staffDaTurmaDoAluno($aluno);

        return view('portal.encarregado.mensagens', [
            'aluno' => $aluno->load('turma'),
            'conversa' => $conversa,
            'mensagens' => $mensagens,
            'directores' => $directores,
            'equipa' => $equipa,
        ]);
    }

    public function store(Request $request, Aluno $aluno): RedirectResponse
    {
        $data = $request->validate([
            'corpo' => ['required', 'string', 'max:2000'],
        ]);

        $conversa = $this->conversas->obterOuCriarParaEncarregado($request->user(), $aluno);
        $this->conversas->enviar($request->user(), $conversa, $data['corpo']);

        return redirect()
            ->route('portal.encarregado.mensagens.show', $aluno)
            ->with('success', 'Mensagem enviada ao director de turma.');
    }
}
