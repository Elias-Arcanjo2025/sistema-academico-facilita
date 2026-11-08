<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\BoletimAssinatura;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use App\Support\BoletimColunas;
use App\Support\BoletimNotasBuilder;
use App\Support\NotaLancamentoRules;
use Illuminate\Support\Collection;

class ExportService
{
    public function __construct(
        protected PautaService $pautaService
    ) {}

    /**
     * Gera dados do boletim individual de um aluno
     */
    public function gerarDadosBoletim(Aluno $aluno, ?string $anoLectivo = null): array
    {
        $anoLectivo = $anoLectivo ?? \App\Models\Configuracao::getAnoLectivoActual();
        $turma = $aluno->turma;
        $curso = $turma->curso;

        // Buscar disciplinas filtradas por CURSO e CLASSE
        $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($curso) {
                $query->where('cursos.id', $curso->id);
            })
            ->whereHas('classes', function ($query) use ($turma) {
                $query->where('classes.id', $turma->classe_id);
            })
            ->where('activo', true)
            ->ordenadaPorTipo()
            ->get();

        $notasPorDisciplina = [];
        $mediaGeral = null;
        $notasFinais = [];
        $maxReprovacoes = (int) \App\Models\Configuracao::get('max_disciplinas_reprovacao', 2);

        $anoLectivoId = (int) \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        foreach ($disciplinas as $disciplina) {
            // Ano anterior: incluir arquivadas (fecho de transição)
            $notasQuery = Nota::where('aluno_id', $aluno->id)
                ->where('disciplina_id', $disciplina->id)
                ->where('turma_id', $turma->id)
                ->where('ano_lectivo_id', $anoLectivoId);
            NotaLancamentoRules::aplicarVisibilidadeArquivo($notasQuery, $anoLectivoId);

            $notas = $notasQuery->get()
                // Chave composta: tipo + trimestre (para distinguir MT1/MT2/MT3)
                ->groupBy(fn($n) => $n->tipo . '|' . ($n->trimestre ?? '0'))
                ->map(fn($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first())
                // Re-mapear para chaves amigáveis (mt1/mt2/mt3 ou tipo)
                ->mapWithKeys(function ($nota) {
                    $key = $nota->tipo === Nota::TIPO_MT
                        ? 'mt' . $nota->trimestre
                        : $nota->tipo;
                    return [$key => $nota];
                });

            $notaFinal = $notas->get(Nota::TIPO_CFD)?->valor
                ?? $notas->get(Nota::TIPO_CA10)?->valor
                ?? $notas->get(Nota::TIPO_CA11)?->valor
                ?? $notas->get(Nota::TIPO_CA12)?->valor
                ?? $notas->get(Nota::TIPO_CF)?->valor;

            $notasPorDisciplina[] = [
                'disciplina' => $disciplina,
                'mt1' => $notas->get('mt1')?->valor,
                'mt2' => $notas->get('mt2')?->valor,
                'mt3' => $notas->get('mt3')?->valor,
                'nota_final' => $notaFinal,
                'situacao' => $this->determinarSituacao($notaFinal),
            ];

            if ($notaFinal !== null) {
                $notasFinais[] = $notaFinal;
            }
        }

        // Calcular media geral
        if (count($notasFinais) === $disciplinas->count() && count($notasFinais) > 0) {
            $mediaGeral = round(array_sum($notasFinais) / count($notasFinais), 2);
        }

        // Determinar situacao final
        $disciplinasReprovadas = collect($notasPorDisciplina)
            ->where('situacao', 'reprovado')
            ->count();

        $situacaoFinal = 'pendente';
        if (count($notasFinais) === $disciplinas->count()) {
            $situacaoFinal = $disciplinasReprovadas <= $maxReprovacoes ? 'aprovado' : 'reprovado';
        }

        return [
            'aluno' => $aluno,
            'turma' => $turma,
            'curso' => $curso,
            'ano_lectivo' => $anoLectivo,
            'disciplinas' => $notasPorDisciplina,
            'media_geral' => $mediaGeral,
            'situacao_final' => $situacaoFinal,
            'disciplinas_reprovadas' => $disciplinasReprovadas,
            'gerado_em' => now(),
            'assinatura_encarregado' => $this->dadosAssinaturaEncarregado($aluno, $anoLectivo, null),
        ];
    }

    /**
     * Gera dados do boletim individual de um aluno para um trimestre específico
     */
    public function gerarDadosBoletimTrimestre(Aluno $aluno, int $trimestre, ?string $anoLectivo = null): array
    {
        $anoLectivo = $anoLectivo ?? \App\Models\Configuracao::getAnoLectivoActual();
        $turma = $aluno->turma;
        $curso = $turma->curso;
        $regime = $curso->regime();
        $classeNumero = (int) ($turma->classe->codigo ?? 10);
        $colunas = BoletimColunas::paraTrimestre($trimestre, $regime, $classeNumero);
        $chaveResultado = BoletimColunas::chaveResultado($trimestre, $regime);
        $anoLectivoId = (int) \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $notasQuery = Nota::query()
            ->where('aluno_id', $aluno->id)
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId);
        NotaLancamentoRules::aplicarVisibilidadeArquivo($notasQuery, $anoLectivoId);
        $builder = BoletimNotasBuilder::paraAluno($aluno, $anoLectivo)->comNotas($notasQuery->get());

        $disciplinas = Disciplina::whereHas('cursos', function ($query) use ($curso) {
                $query->where('cursos.id', $curso->id);
            })
            ->whereHas('classes', function ($query) use ($turma) {
                $query->where('classes.id', $turma->classe_id);
            })
            ->where('activo', true)
            ->ordenadaPorTipo()
            ->get();

        $notasPorDisciplina = [];
        $notasResultado = [];
        $aprovadas = 0;
        $reprovadas = 0;
        $notaMinima = (float) \App\Models\Configuracao::get('nota_minima_aprovacao', 10);

        foreach ($disciplinas as $disciplina) {
            $valores = $builder->linhaTrimestre($disciplina, $trimestre, $regime, $classeNumero);
            $valorResultado = $valores[$chaveResultado] ?? null;

            $celulas = [];
            foreach ($colunas as $coluna) {
                $celulas[$coluna['key']] = $valores[$coluna['key']] ?? null;
            }

            $notasPorDisciplina[] = array_merge(
                ['disciplina' => $disciplina->nome, 'disciplina_codigo' => $disciplina->codigo ?? null],
                $celulas,
                ['valor_resultado' => $valorResultado],
            );

            if ($valorResultado !== null) {
                $notasResultado[] = $valorResultado;
                if ((float) $valorResultado >= $notaMinima) {
                    $aprovadas++;
                } else {
                    $reprovadas++;
                }
            }
        }

        $mediaGeral = count($notasResultado) > 0
            ? round(array_sum($notasResultado) / count($notasResultado), 1)
            : null;

        return [
            'aluno' => $aluno,
            'turma' => $turma,
            'curso' => $curso,
            'ano_lectivo' => $anoLectivo,
            'trimestre' => $trimestre,
            'colunas' => $colunas,
            'chave_resultado' => $chaveResultado,
            'notas' => $notasPorDisciplina,
            'resumo' => [
                'aprovadas' => $aprovadas,
                'reprovadas' => $reprovadas,
                'media_geral' => $mediaGeral,
            ],
            'observacoes' => null,
            'gerado_em' => now(),
            'assinatura_encarregado' => $this->dadosAssinaturaEncarregado($aluno, $anoLectivo, $trimestre),
        ];
    }

    /**
     * Assinatura digital do encarregado no portal (confirmação de leitura).
     *
     * @return array{nome: string, assinado_em: \Carbon\Carbon, hash_curto: string}|null
     */
    public function dadosAssinaturaEncarregado(Aluno $aluno, string $anoLectivo, ?int $trimestre): ?array
    {
        $query = BoletimAssinatura::query()
            ->with('encarregado')
            ->where('aluno_id', $aluno->id)
            ->where('ano_lectivo', $anoLectivo);

        if ($trimestre !== null) {
            $query->where('trimestre', $trimestre);
        }

        $assinatura = $query->latest('assinado_em')->first();
        if (! $assinatura) {
            return null;
        }

        return [
            'nome' => $assinatura->encarregado?->nome ?? 'Encarregado de Educação',
            'assinado_em' => $assinatura->assinado_em,
            'hash_curto' => strtoupper(substr($assinatura->hash_documento, 0, 12)),
        ];
    }

    /**
     * Gera dados da mini-pauta para exportacao
     */
    public function gerarDadosMiniPauta(
        int $turmaId,
        int $disciplinaId,
        int $trimestre,
        ?string $anoLectivo = null
    ): array {
        $anoLectivo = $anoLectivo ?? \App\Models\Configuracao::getAnoLectivoActual();

        $turma = Turma::with(['curso', 'classe'])->findOrFail($turmaId);
        $disciplina = Disciplina::findOrFail($disciplinaId);
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        $miniPauta = $this->pautaService->gerarMiniPauta(
            $turmaId,
            $disciplinaId,
            $trimestre,
            $anoLectivo
        );

        $estatisticas = $this->pautaService->getEstatisticasMiniPauta($miniPauta);
        $meta = $this->pautaService->metaMiniPauta($turmaId, $trimestre);

        // Professor da disciplina nesta turma
        $professorNome = \DB::table('professor_disciplina_turma')
            ->join('users', 'professor_disciplina_turma.user_id', '=', 'users.id')
            ->where('professor_disciplina_turma.turma_id', $turmaId)
            ->where('professor_disciplina_turma.disciplina_id', $disciplinaId)
            ->where('professor_disciplina_turma.activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('professor_disciplina_turma.ano_lectivo_id', $anoLectivoId))
            ->value('users.name');

        // Director de turma
        $directorTurma = \DB::table('director_turma')
            ->join('users', 'director_turma.user_id', '=', 'users.id')
            ->where('director_turma.turma_id', $turmaId)
            ->where('director_turma.activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('director_turma.ano_lectivo_id', $anoLectivoId))
            ->value('users.name');

        // Coordenador do curso
        $coordenadorCurso = \DB::table('coordenador_curso')
            ->join('users', 'coordenador_curso.user_id', '=', 'users.id')
            ->where('coordenador_curso.curso_id', $turma->curso_id)
            ->where('coordenador_curso.activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('coordenador_curso.ano_lectivo_id', $anoLectivoId))
            ->value('users.name');

        // Director geral (config ou role)
        $directorGeral = \App\Models\Configuracao::get('director_geral_nome');
        if (empty($directorGeral)) {
            $directorGeral = \DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.name', ['directora_geral', 'director_geral'])
                ->where('model_has_roles.model_type', \App\Models\User::class)
                ->value('users.name');
        }

        // Data do conselho de turma
        $conselho = \App\Models\ConselhoTrimestre::where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->first();

        return [
            'turma' => $turma,
            'disciplina' => $disciplina,
            'trimestre' => $trimestre,
            'ano_lectivo' => $anoLectivo,
            'classe_numero' => (int) preg_replace('/\D/', '', (string) ($turma->classe->codigo ?? $turma->classe->nome ?? '10')) ?: 10,
            'alunos' => $miniPauta,
            'estatisticas' => $estatisticas,
            'professor_nome' => $professorNome,
            'director_turma_nome' => $directorTurma,
            'coordenador_curso_nome' => $coordenadorCurso,
            'director_geral_nome' => $directorGeral,
            'data_conselho' => $conselho?->data_conclusao?->format('d/m/Y'),
            'conselho_estado' => $conselho?->estado,
            'gerado_em' => now(),
            'colunas' => $meta['colunas'],
            'chave_resultado' => $meta['chave_resultado'],
            'usa_prova_trimestral' => $turma->curso?->regime()->usaProvaTrimestral($trimestre) ?? true,
            'divisor_mt' => $turma->curso?->regime()->divisorMediaTrimestral($trimestre) ?? 3,
        ];
    }

    /**
     * Mini-pauta anual: 1.º + 2.º + 3.º trimestre no mesmo documento.
     *
     * @return array{
     *     turma: Turma,
     *     disciplina: Disciplina,
     *     ano_lectivo: string,
     *     gerado_em: \Illuminate\Support\Carbon,
     *     professor_nome: ?string,
     *     director_turma_nome: ?string,
     *     trimestres: array<int, array<string, mixed>>
     * }
     */
    public function gerarDadosMiniPautaAnual(
        int $turmaId,
        int $disciplinaId,
        ?string $anoLectivo = null
    ): array {
        $trimestres = [];
        foreach ([1, 2, 3] as $trimestre) {
            $trimestres[$trimestre] = $this->gerarDadosMiniPauta(
                $turmaId,
                $disciplinaId,
                $trimestre,
                $anoLectivo
            );
        }

        $primeiro = $trimestres[1];
        $turma = $primeiro['turma'];
        $turma->loadMissing(['curso', 'classe', 'sala', 'turno']);

        // Colunas da folha anual (lado a lado), alinhadas ao modelo oficial
        $colunasAnuais = [
            1 => [
                ['key' => 'mac', 'header' => 'MAC'],
                ['key' => 'pp', 'header' => 'PP'],
                ['key' => 'pt', 'header' => 'PT'],
                ['key' => 'mt', 'header' => 'MT1', 'resultado' => true],
            ],
            2 => [
                ['key' => 'mac', 'header' => 'MAC'],
                ['key' => 'pp', 'header' => 'PP'],
                ['key' => 'pt', 'header' => 'PT'],
                ['key' => 'mt', 'header' => 'MT2', 'resultado' => true],
            ],
            3 => [
                ['key' => 'mac', 'header' => 'MAC'],
                ['key' => 'pp', 'header' => 'PP'],
                ['key' => 'mt', 'header' => 'MT3', 'resultado' => true],
                ['key' => 'cf', 'header' => 'CF'],
                ['key' => 'ca', 'header' => 'CA'],
            ],
        ];

        // Linhas unificadas pelo nº de ordem / nome (base = 1.º trimestre)
        $linhas = [];
        $porOrdem = [];
        foreach ([1, 2, 3] as $t) {
            foreach ($trimestres[$t]['alunos'] as $aluno) {
                $chave = (string) ($aluno['numero_ordem'] ?? '').'|'.mb_strtolower((string) ($aluno['nome'] ?? ''));
                $porOrdem[$t][$chave] = $aluno;
            }
        }

        foreach ($trimestres[1]['alunos'] as $base) {
            $chave = (string) ($base['numero_ordem'] ?? '').'|'.mb_strtolower((string) ($base['nome'] ?? ''));
            $linhas[] = [
                'numero_ordem' => $base['numero_ordem'] ?? null,
                'nome' => $base['nome'] ?? '',
                'genero' => $base['genero'] ?? ($base['aluno']->genero ?? null),
                'situacao' => $base['situacao'] ?? null,
                't1' => $porOrdem[1][$chave] ?? $base,
                't2' => $porOrdem[2][$chave] ?? null,
                't3' => $porOrdem[3][$chave] ?? null,
            ];
        }

        return [
            'turma' => $turma,
            'disciplina' => $primeiro['disciplina'],
            'ano_lectivo' => $primeiro['ano_lectivo'],
            'gerado_em' => now(),
            'professor_nome' => $primeiro['professor_nome'] ?? null,
            'director_turma_nome' => $primeiro['director_turma_nome'] ?? null,
            'trimestres' => $trimestres,
            'colunas_anuais' => $colunasAnuais,
            'linhas' => $linhas,
        ];
    }

    /**
     * Gera dados da pauta final por disciplina para exportacao
     */
    public function gerarDadosPautaFinalDisciplina(
        int $turmaId,
        int $disciplinaId,
        ?string $anoLectivo = null
    ): array {
        $anoLectivo = $anoLectivo ?? \App\Models\Configuracao::getAnoLectivoActual();

        $turma = Turma::with('curso')->findOrFail($turmaId);
        $disciplina = Disciplina::findOrFail($disciplinaId);

        $pautaFinal = $this->pautaService->gerarPautaFinalDisciplina(
            $turmaId,
            $disciplinaId,
            $anoLectivo
        );

        $estatisticas = $this->pautaService->getEstatisticasPautaFinal($pautaFinal);

        return [
            'turma' => $turma,
            'disciplina' => $disciplina,
            'ano_lectivo' => $anoLectivo,
            'alunos' => $pautaFinal,
            'estatisticas' => $estatisticas,
            'gerado_em' => now(),
        ];
    }

    /**
     * Gera dados da pauta final completa da turma
     */
    public function gerarDadosPautaFinalTurma(int $turmaId, ?string $anoLectivo = null): array
    {
        $dados = $this->pautaService->gerarPautaFinalTurma($turmaId, $anoLectivo);
        $dados['gerado_em'] = now();

        return $dados;
    }

    /**
     * Gera dados da pauta final completa detalhada
     */
    public function gerarDadosPautaFinalCompleta(int $turmaId, ?string $anoLectivo = null): array
    {
        $dados = $this->pautaService->gerarPautaFinalCompletaDetalhada($turmaId, $anoLectivo);
        $dados['gerado_em'] = now();

        return $dados;
    }

    /**
     * Gera dados completos da pauta trimestral (1 trimestre, todas disciplinas)
     * incluindo nomes de professores por disciplina, director de turma,
     * coordenador do curso e subdirector pedagógico.
     */
    public function gerarDadosPautaTrimestralCompleta(
        int $turmaId,
        int $trimestre,
        ?string $anoLectivo = null,
        ?string $momentoConselho = null,
    ): array {
        $dados = $this->pautaService->gerarPautaTrimestralCompleta(
            $turmaId,
            $trimestre,
            $anoLectivo,
            $momentoConselho,
        );

        $turma = $dados['turma'];
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($dados['ano_lectivo']);

        // Nome do professor por disciplina (associação activa)
        $professores = [];
        foreach ($dados['disciplinas'] as $disciplina) {
            $assoc = \DB::table('professor_disciplina_turma')
                ->join('users', 'professor_disciplina_turma.user_id', '=', 'users.id')
                ->where('professor_disciplina_turma.turma_id', $turmaId)
                ->where('professor_disciplina_turma.disciplina_id', $disciplina->id)
                ->where('professor_disciplina_turma.activo', true)
                ->when($anoLectivoId, fn ($q) => $q->where('professor_disciplina_turma.ano_lectivo_id', $anoLectivoId))
                ->select('users.name')
                ->first();
            $professores[$disciplina->id] = $assoc?->name;
        }

        // Director de turma
        $directorTurma = \DB::table('director_turma')
            ->join('users', 'director_turma.user_id', '=', 'users.id')
            ->where('director_turma.turma_id', $turmaId)
            ->where('director_turma.activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('director_turma.ano_lectivo_id', $anoLectivoId))
            ->value('users.name');

        // Coordenador do curso
        $coordenadorCurso = \DB::table('coordenador_curso')
            ->join('users', 'coordenador_curso.user_id', '=', 'users.id')
            ->where('coordenador_curso.curso_id', $turma->curso_id)
            ->where('coordenador_curso.activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('coordenador_curso.ano_lectivo_id', $anoLectivoId))
            ->value('users.name');

        // Subdirector pedagógico (por role)
        $subdirector = \DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'subdiretor_pedagogico')
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->value('users.name');

        // Director geral / Director da instituição (configurável)
        $directorGeral = \App\Models\Configuracao::get('director_geral_nome');
        if (empty($directorGeral)) {
            $directorGeral = \DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.name', ['directora_geral', 'director_geral'])
                ->where('model_has_roles.model_type', \App\Models\User::class)
                ->value('users.name');
        }

        // Data do conselho de turma (preenchida quando o conselho é concluído)
        $conselho = \App\Models\ConselhoTrimestre::where('turma_id', $turmaId)
            ->where('trimestre', $trimestre)
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->first();

        $dados['professores'] = $professores;
        $dados['director_turma_nome'] = $directorTurma;
        $dados['coordenador_curso_nome'] = $coordenadorCurso;
        $dados['subdirector_nome'] = $subdirector;
        $dados['director_geral_nome'] = $directorGeral;
        $dados['data_conselho'] = $conselho?->data_conclusao?->format('d/m/Y');
        $dados['conselho_estado'] = $conselho?->estado;
        $dados['gerado_em'] = now();

        return $dados;
    }

    /**
     * Gera dados da lista de alunos por turma
     */
    public function gerarDadosListaAlunos(int $turmaId): array
    {
        $turma = Turma::with([
            'curso',
            'alunos' => function ($query) {
                $query->where('activo', true)->orderBy('numero_ordem');
            }
        ])->findOrFail($turmaId);

        return [
            'turma' => $turma,
            'curso' => $turma->curso,
            'alunos' => $turma->alunos,
            'total_alunos' => $turma->alunos->count(),
            'gerado_em' => now(),
        ];
    }

    /**
     * Determina a situacao de uma nota
     */
    protected function determinarSituacao(?float $nota): string
    {
        if ($nota === null) {
            return 'pendente';
        }

        $notaMinima = (float) \App\Models\Configuracao::get('nota_minima_aprovacao', 10);

        return $nota >= $notaMinima ? 'aprovado' : 'reprovado';
    }
}
