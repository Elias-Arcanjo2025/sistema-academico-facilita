<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Pedagogico\PedBancoQuestao;
use App\Services\Pedagogico\BancoQuestaoGeneratorService;
use App\Services\Pedagogico\BancoQuestaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BancoQuestaoController extends Controller
{
    public function __construct(
        private BancoQuestaoService $banco,
        private BancoQuestaoGeneratorService $generator,
    ) {
        $this->middleware('can:pedagogico.banco.ver');
        $this->middleware('can:pedagogico.banco.gerir')->only(['create', 'store', 'edit', 'update', 'destroy']);
        $this->middleware('can:pedagogico.ia.gerar')->only(['createIa', 'gerar']);
    }

    public function index(Request $request): View
    {
        $filtros = $request->only(['disciplina_id', 'estado']);
        $questoes = $this->banco->listar($request->user(), $filtros);
        $disciplinas = $this->banco->disciplinasDisponiveis($request->user());

        return view('pedagogico.banco.index', [
            'questoes' => $questoes,
            'filtros' => $filtros,
            'disciplinas' => $disciplinas,
            'tipos' => PedBancoQuestao::tipos(),
            'dificuldades' => PedBancoQuestao::dificuldades(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('pedagogico.banco.create', [
            'disciplinas' => $this->banco->disciplinasDisponiveis($request->user()),
            'tipos' => PedBancoQuestao::tipos(),
            'dificuldades' => PedBancoQuestao::dificuldades(),
        ]);
    }

    public function createIa(Request $request): View
    {
        return view('pedagogico.banco.gerar', [
            'disciplinas' => $this->banco->disciplinasDisponiveis($request->user()),
            'classes' => Classe::queryPermitidas()->get(['id', 'nome']),
            'tipos' => PedBancoQuestao::tipos(),
            'dificuldades' => PedBancoQuestao::dificuldades(),
        ]);
    }

    public function gerar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('pedagogico.ia.gerar'), 403);
        abort_unless($request->user()->can('pedagogico.banco.gerir'), 403);

        $data = $request->validate([
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'classe_id' => ['nullable', 'exists:classes,id'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(PedBancoQuestao::tipos()))],
            'dificuldade' => ['required', 'in:'.implode(',', array_keys(PedBancoQuestao::dificuldades()))],
            'quantidade' => ['required', 'integer', 'min:1', 'max:10'],
            'tema' => ['nullable', 'string', 'max:500'],
            'cotacao' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
        ]);

        try {
            $criadas = $this->generator->gerar($request->user(), $data);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $this->mensagemErroGeracao($e));
        }

        return redirect()
            ->route('pedagogico.banco.index', ['disciplina_id' => $data['disciplina_id']])
            ->with('success', $criadas->count().' questão(ões) gerada(s) com IA e adicionada(s) ao banco.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(PedBancoQuestao::tipos()))],
            'enunciado' => ['required', 'string', 'max:5000'],
            'cotacao' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'dificuldade' => ['required', 'in:'.implode(',', array_keys(PedBancoQuestao::dificuldades()))],
            'tags' => ['nullable', 'string', 'max:255'],
            'opcao_a' => ['nullable', 'string', 'max:500'],
            'opcao_b' => ['nullable', 'string', 'max:500'],
            'opcao_c' => ['nullable', 'string', 'max:500'],
            'opcao_d' => ['nullable', 'string', 'max:500'],
            'resposta' => ['nullable', 'string', 'max:500'],
        ]);

        $opcoes = null;
        if ($data['tipo'] === PedBancoQuestao::TIPO_ESCOLHA) {
            $opcoes = array_filter([
                'A' => $data['opcao_a'] ?? null,
                'B' => $data['opcao_b'] ?? null,
                'C' => $data['opcao_c'] ?? null,
                'D' => $data['opcao_d'] ?? null,
            ], fn ($v) => filled($v));
        }

        $questao = $this->banco->criar($request->user(), [
            'disciplina_id' => $data['disciplina_id'],
            'tipo' => $data['tipo'],
            'enunciado' => $data['enunciado'],
            'cotacao' => $data['cotacao'] ?? 1,
            'dificuldade' => $data['dificuldade'],
            'tags' => $data['tags'] ?? null,
            'opcoes_json' => $opcoes ?: null,
            'resposta_json' => filled($data['resposta'] ?? null) ? ['resposta' => $data['resposta']] : null,
        ]);

        return redirect()->route('pedagogico.banco.index', ['disciplina_id' => $questao->disciplina_id])
            ->with('success', 'Questão adicionada ao banco.');
    }

    public function destroy(Request $request, PedBancoQuestao $questao): RedirectResponse
    {
        if (! $this->banco->podeGerir($request->user(), $questao)) {
            abort(403);
        }

        $questao->update(['estado' => PedBancoQuestao::ESTADO_ARQUIVADA]);
        $questao->delete();

        return back()->with('success', 'Questão arquivada.');
    }

    private function mensagemErroGeracao(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'API key') || str_contains($msg, 'api_key')) {
            return 'Configuração de IA em falta. Contacte o administrador.';
        }

        if (str_contains(strtolower($msg), 'rate') || str_contains($msg, '429')) {
            return 'Limite de pedidos à IA atingido. Tente novamente dentro de alguns minutos.';
        }

        if (str_contains(strtolower($msg), 'json') || str_contains($msg, 'Questão gerada')) {
            return 'A IA devolveu um formato inválido. Tente novamente com outro tema ou menos questões.';
        }

        return 'Erro ao gerar questões: '.Str::limit($msg, 300);
    }
}
