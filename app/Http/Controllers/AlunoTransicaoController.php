<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlunoTransicaoRequest;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Services\AlunoTransicaoService;
use Illuminate\Http\Request;

class AlunoTransicaoController extends Controller
{
    private AlunoTransicaoService $service;

    public function __construct(AlunoTransicaoService $service)
    {
        $this->service = $service;

        // Apenas admin_full pode acessar
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('admin_full')) {
                abort(403, 'Acesso negado. Apenas administradores podem transitar alunos entre anos lectivos.');
            }
            return $next($request);
        });
    }

    /**
     * Passo 1: Formulário de configuração
     */
    public function index()
    {
        // Buscar anos lectivos disponíveis
        $anosDisponiveis = AnoLectivo::orderBy('nome', 'desc')
            ->pluck('nome');

        // Buscar apenas anos novos (estado = 2) ou actual (estado = 1) para destino
        $anosDestino = AnoLectivo::whereIn('estado', [
            AnoLectivo::ESTADO_ACTUAL,
            AnoLectivo::ESTADO_NOVO,
        ])
            ->orderBy('nome', 'desc')
            ->pluck('nome');

        // Sugerir ano actual como origem
        $anoActual = Configuracao::getAnoLectivoActual();

        return view('aluno-transicao.index', compact(
            'anosDisponiveis',
            'anosDestino',
            'anoActual'
        ));
    }

    /**
     * Passo 2: Preview da transição com mapeamento
     */
    public function preview(Request $request)
    {
        $anoOrigem = $request->input('ano_origem');
        $anoDestino = $request->input('ano_destino');

        // Validar
        $errors = $this->service->validateTransicao($anoOrigem, $anoDestino);

        if (!empty($errors)) {
            return redirect()->route('alunos.transicao.index')
                ->withErrors($errors)
                ->withInput();
        }

        // Opções selecionadas
        $opcoes = [
            'transitar_reprovados' => $request->boolean('transitar_reprovados', false),
            'selecao_individual' => $request->boolean('selecao_individual', false),
        ];

        $mapeamentoPromocaoManual = $request->input('mapeamento_promocao');
        $mapeamentoRepetenciaManual = $request->input('mapeamento_repetencia');

        $preview = $this->service->previewTransicao(
            $anoOrigem,
            $anoDestino,
            $mapeamentoPromocaoManual,
            $mapeamentoRepetenciaManual
        );

        return view('aluno-transicao.preview', array_merge($preview, [
            'opcoes' => $opcoes,
        ]));
    }

    /**
     * Passo 3: Executar transição
     */
    public function execute(AlunoTransicaoRequest $request)
    {
        $anoOrigem = $request->input('ano_origem');
        $anoDestino = $request->input('ano_destino');
        $filterMapping = fn ($v) => $v !== null && $v !== '';

        $mapeamentoPromocao = array_filter(
            $request->input('mapeamento_promocao', []),
            $filterMapping
        );
        $mapeamentoRepetencia = array_filter(
            $request->input('mapeamento_repetencia', []),
            $filterMapping
        );

        $transitarReprovados = $request->boolean('transitar_reprovados', false);
        $alunosSelecionados = $request->input('alunos_selecionados');

        if ($alunosSelecionados && is_array($alunosSelecionados)) {
            $alunosSelecionados = array_keys(array_filter($alunosSelecionados));
        } else {
            $alunosSelecionados = null;
        }

        $result = $this->service->executeTransicao(
            $anoOrigem,
            $anoDestino,
            $mapeamentoPromocao,
            $mapeamentoRepetencia,
            $transitarReprovados,
            $alunosSelecionados
        );

        if (!$result['success']) {
            return redirect()->route('alunos.transicao.index')
                ->with('error', $result['message'])
                ->withInput();
        }

        // Registrar auditoria geral
        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'ano_origem' => $anoOrigem,
                'ano_destino' => $anoDestino,
                'stats' => $result['stats'],
                'opcoes' => [
                    'transitar_reprovados' => $transitarReprovados,
                    'selecao_individual' => $alunosSelecionados !== null,
                ],
            ])
            ->log('Transição de alunos executada');

        return view('aluno-transicao.result', [
            'success' => true,
            'stats' => $result['stats'],
            'message' => $result['message'],
            'anoOrigem' => $anoOrigem,
            'anoDestino' => $anoDestino,
        ]);
    }
}
