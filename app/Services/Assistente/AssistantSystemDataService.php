<?php

namespace App\Services\Assistente;

use App\Models\CalendarioProva;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\User;
use App\Services\ConfiguracaoService;
use App\Services\HorarioEscolarService;

/**
 * Ferramentas de consulta à BD real para o assistente — sempre com escopo via AssistantAccess.
 */
class AssistantSystemDataService
{
    public function __construct(
        protected AssistantAccess $access,
        protected ConfiguracaoService $configuracao,
        protected HorarioEscolarService $horarioService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function horarioTurma(User $user, ?Turma $turma = null): ?array
    {
        $turma = $turma ?? $this->resolverTurmaUtilizador($user);

        if (! $turma || ! $this->access->canViewTurma($user, $turma)) {
            return null;
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $dados = $this->horarioService->prepararDadosPdf($turma, $anoId);

        $aulas = collect($dados['linhas'] ?? [])
            ->where('tipo', 'aula')
            ->map(function (array $linha) {
                $celulas = [];
                foreach ($linha['celulas'] ?? [] as $dia => $celula) {
                    if (! empty($celula['disciplina'])) {
                        $celulas[$dia] = [
                            'disciplina' => $celula['disciplina'],
                            'professor' => $celula['professor'] ?? null,
                        ];
                    }
                }

                return [
                    'hora' => $linha['hora'] ?? null,
                    'aulas' => $celulas,
                ];
            })
            ->filter(fn (array $l) => $l['aulas'] !== [])
            ->values()
            ->all();

        return [
            'turma' => $turma->codigo ?? $turma->nome,
            'total_aulas' => TurmaHorario::where('turma_id', $turma->id)->where('ano_lectivo_id', $anoId)->count(),
            'grade_resumida' => array_slice($aulas, 0, 20),
            'publicado' => $aulas !== [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function provasTurma(User $user, ?Turma $turma = null): ?array
    {
        $turma = $turma ?? $this->resolverTurmaUtilizador($user);

        if (! $turma || ! $this->access->canViewTurma($user, $turma)) {
            return null;
        }

        $anoId = Configuracao::getAnoLectivoActualId();

        $provas = CalendarioProva::with('disciplina')
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoId)
            ->orderBy('data_prova')
            ->orderBy('hora_inicio')
            ->limit(30)
            ->get()
            ->map(fn (CalendarioProva $p) => [
                'disciplina' => $p->disciplina?->nome,
                'data' => $p->data_prova?->format('Y-m-d'),
                'hora' => $p->hora_inicio ? substr((string) $p->hora_inicio, 0, 5) : null,
                'tipo' => $p->tipo,
                'periodo' => $p->periodo,
            ])
            ->values()
            ->all();

        return [
            'turma' => $turma->codigo ?? $turma->nome,
            'total' => count($provas),
            'provas' => $provas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function modulosSistema(User $user): array
    {
        return [
            'ano_lectivo' => Configuracao::getAnoLectivoActual(),
            'trimestre_actual' => $this->configuracao->getTrimestreActual(),
            'portal_aluno_notas' => $this->configuracao->get('portal_aluno_notas_activo', '1') === '1',
            'bloqueio_inactividade' => $this->configuracao->bloqueioInactividadeActivo(),
            'assistente_ia' => (bool) config('assistente.enabled'),
            'modulos' => [
                'notas_e_pautas', 'boletins_pdf', 'horarios', 'calendario_provas',
                'portal_aluno', 'conselho_notas', 'fecho_ano', 'assistente_ia',
                'planificacao_pedagogica', 'pagina_publica',
            ],
            'perfil_utilizador' => $this->access->perfilResposta($user),
            'escopo' => $this->access->descricaoEscopo($user),
            'instrucao' => 'Os dados académicos vêm da base de dados em tempo real no bloco dados_consultados. '
                .'Responda com base nesses dados; não invente notas, horários ou provas.',
        ];
    }

    /**
     * @return list<string>
     */
    public function detectarIntencoes(string $mensagem, array $historico = []): array
    {
        $texto = mb_strtolower($mensagem.' '.$this->combinarHistorico($historico));

        $intencoes = [];

        if (preg_match('/\b(hor[aá]rio|aulas?|que horas|segunda|ter[cç]a|quarta|quinta|sexta)\b/u', $texto)) {
            $intencoes[] = 'horario';
        }
        if (preg_match('/\b(provas?|exames?|calend[aá]rio|testes?|avalia[cç][aã]o)\b/u', $texto)) {
            $intencoes[] = 'provas';
        }
        if (preg_match('/\b(notas?|m[eé]dia|mac|pp|pt|cfd|negativas?|aprovad)\b/u', $texto)) {
            $intencoes[] = 'notas';
        }
        if (preg_match('/\b(ranking|melhores?|piores?|top|comparar|desempenho)\b/u', $texto)) {
            $intencoes[] = 'rankings';
        }
        if (preg_match('/\b(pendente|falta|lan[cç]ar|em falta)\b/u', $texto)) {
            $intencoes[] = 'pendencias';
        }

        return array_values(array_unique($intencoes));
    }

    /**
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     */
    protected function combinarHistorico(array $historico): string
    {
        return collect($historico)
            ->take(-3)
            ->pluck('conteudo')
            ->implode(' ');
    }

    protected function resolverTurmaUtilizador(User $user): ?Turma
    {
        if ($user->hasRole('aluno') && $user->aluno_id) {
            return Turma::query()->find($user->aluno_id ? \App\Models\Aluno::find($user->aluno_id)?->turma_id : null);
        }

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user);

        if ($turmasIds === []) {
            return null;
        }

        return Turma::query()->find($turmasIds[0]);
    }
}
