<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\User;
use App\Services\AI\AIServiceInterface;
use App\Services\Assistente\AssistantContextBuilder;
use App\Services\Assistente\AssistantDataService;
use Illuminate\Support\Facades\Cache;

/**
 * Resumo executivo do sistema adaptado ao perfil do utilizador.
 */
class ExecutivoIaService
{
    public function __construct(
        private AssistantContextBuilder $contextBuilder,
        private AssistantDataService $data,
        private AIServiceInterface $ai,
        private SaudeTurmasService $saudeTurmas,
        private SaudeEscolaService $saudeEscola,
        private SaudeAlunoTurmaService $saudeAlunoTurma,
    ) {}

    /**
     * @return array{resumo:string, fonte:string, perfil:string, gerado_em:string, sem_ano_lectivo:bool}
     */
    public function gerarResumo(User $user): array
    {
        $cacheKey = 'executivo_ia.'.$user->id.'.'.today()->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $perfil = $this->perfilRotulo($user);
            $semAno = ! Configuracao::getAnoLectivoActualId();

            if ($semAno) {
                return [
                    'resumo' => $this->resumoSetupInicial($user, $perfil),
                    'fonte' => 'setup',
                    'perfil' => $perfil,
                    'gerado_em' => now()->format('d/m/Y H:i'),
                    'sem_ano_lectivo' => true,
                ];
            }

            $prompt = $this->promptExecutivo($user, $perfil);

            if (config('assistente.enabled', true)) {
                try {
                    $contexto = $this->contextBuilder->build($user, $prompt, []);
                    $resposta = $this->ai->chat([
                        ['role' => 'system', 'content' => $this->systemPrompt($perfil)],
                        ['role' => 'user', 'content' => "DADOS (JSON):\n".json_encode($contexto, JSON_UNESCAPED_UNICODE)],
                        ['role' => 'user', 'content' => $prompt],
                    ], [
                        'model' => config('assistente.model'),
                        'max_tokens' => 1200,
                        'temperature' => 0.35,
                    ]);

                    $texto = trim($resposta->content);
                    if ($texto !== '') {
                        return [
                            'resumo' => $texto,
                            'fonte' => 'ia',
                            'perfil' => $perfil,
                            'gerado_em' => now()->format('d/m/Y H:i'),
                            'sem_ano_lectivo' => false,
                        ];
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return [
                'resumo' => $this->resumoLocal($user, $perfil),
                'fonte' => 'local',
                'perfil' => $perfil,
                'gerado_em' => now()->format('d/m/Y H:i'),
                'sem_ano_lectivo' => false,
            ];
        });
    }

    public function invalidarCache(User $user): void
    {
        Cache::forget('executivo_ia.'.$user->id.'.'.today()->format('Y-m-d'));
    }

    private function perfilRotulo(User $user): string
    {
        if ($user->hasRole('aluno')) {
            return 'Aluno';
        }
        if ($user->hasRole('admin_full')) {
            return 'Administrador';
        }
        if ($user->hasRole('directora_geral')) {
            return 'Directora Geral';
        }
        if ($user->hasRole('subdiretor_pedagogico')) {
            return 'Subdirector Pedagógico';
        }
        if ($user->hasRole('coordenador_curso')) {
            return 'Coordenador de Curso';
        }
        if ($user->hasRole('director_turma')) {
            return 'Director de Turma';
        }
        if ($user->hasRole('professor')) {
            return 'Professor';
        }

        return 'Utilizador';
    }

    private function systemPrompt(string $perfil): string
    {
        return "És o Executivo IA do sistema Facilita (gestão escolar angolana). "
            ."Gera um resumo executivo em português de Portugal para um utilizador com perfil «{$perfil}». "
            .'Usa markdown simples (## títulos, listas). Máximo 350 palavras. '
            .'Foca no que é relevante para ESTE perfil — não repitas dados irrelevantes. '
            .'Inclui: situação actual, prioridades, alertas e 2–3 acções recomendadas. '
            .'Não inventes números que não estejam no JSON.';
    }

    private function promptExecutivo(User $user, string $perfil): string
    {
        return "Gera o resumo executivo do sistema Facilita para mim como {$perfil}. "
            .'Considera ano lectivo, trimestre, turmas/disciplinas do meu escopo e indicadores de saúde académica.';
    }

    /**
     * Resumo de setup quando ainda não há ano lectivo na BD.
     */
    private function resumoSetupInicial(User $user, string $perfil): string
    {
        $escopo = $this->data->getEscopoResumo($user);
        $instituicao = $escopo['instituicao']['nome'] ?? null;
        $sugestaoAno = $this->sugerirNomeAnoLectivo();

        $linhas = ["## Configuração inicial — {$perfil}", ''];

        if ($instituicao) {
            $linhas[] = '**Instituição:** '.$instituicao;
            $linhas[] = '';
        }

        $linhas[] = 'O Facilita está pronto, mas ainda **não existe nenhum ano lectivo registado**.';
        $linhas[] = '';
        $linhas[] = 'Sem ano lectivo activo não há turmas, matrículas, notas, pautas nem indicadores para analisar.';
        $linhas[] = '';
        $linhas[] = '### Próximo passo';
        $linhas[] = '1. Abra **Configurações** → **Gestão de Anos Lectivos**';
        $linhas[] = "2. Crie o ano **{$sugestaoAno}** (ou o período correcto da escola)";
        $linhas[] = '3. Active o ano criado';
        $linhas[] = '';
        $linhas[] = '### Depois disso';
        $linhas[] = '- Cadastrar cursos, classes e turmas';
        $linhas[] = '- Matricular alunos e associar professores';
        $linhas[] = '- Voltar aqui para o resumo executivo com dados reais';

        return implode("\n", $linhas);
    }

    private function sugerirNomeAnoLectivo(): string
    {
        $mes = (int) date('m');
        $ano = (int) date('Y');

        return $mes >= 9
            ? $ano.'-'.($ano + 1)
            : ($ano - 1).'-'.$ano;
    }

    private function resumoLocal(User $user, string $perfil): string
    {
        $escopo = $this->data->getEscopoResumo($user);
        $linhas = ["## Resumo Executivo — {$perfil}", ''];

        $ano = $escopo['ano_lectivo'] ?? null;
        $trimestre = $escopo['trimestre_actual'] ?? null;

        $linhas[] = '**Instituição:** '.($escopo['instituicao']['nome'] ?? '—');
        $linhas[] = '**Ano lectivo:** '.($ano ?: '—')
            .' · **Trimestre:** '.($trimestre !== null ? $trimestre.'º' : '—');
        $linhas[] = '';

        if (! empty($escopo['sem_ano_lectivo'])) {
            return $this->resumoSetupInicial($user, $perfil);
        }

        if ($user->hasRole('aluno') && ! empty($escopo['sessao_aluno'])) {
            $s = $escopo['sessao_aluno'];
            $aluno = $user->aluno;
            $saudeAluno = $aluno ? $this->saudeAlunoTurma->indicadores($aluno) : [];
            $linhas[] = '### A sua situação';
            $linhas[] = '- Turma: '.($s['turma']['nome'] ?? $saudeAluno['turma'] ?? '—');
            $neg = $s['analise_desempenho']['negativas'] ?? $s['desempenho_resumo']['negativas'] ?? ($saudeAluno['negativas_aluno'] ?? null);
            if ($neg !== null) {
                $linhas[] = '- Disciplinas negativas: '.$neg;
            }
            if (! empty($saudeAluno['score'])) {
                $linhas[] = '- Saúde da turma: '.$saudeAluno['score'].'/100 — '.($saudeAluno['score_label'] ?? '');
            }
            if (! empty($saudeAluno['resultado_transicao'])) {
                $linhas[] = '- Transição T3: '.$saudeAluno['resultado_transicao'];
            }
            $linhas[] = '';
            $linhas[] = '### Recomendações';
            $linhas[] = '- Consulte as notas e o horário no portal.';
            if (($saudeAluno['negativas_aluno'] ?? 0) > 0) {
                $linhas[] = '- Reforce as disciplinas com notas abaixo do mínimo.';
            }
            $linhas[] = '- Em caso de recuperação/recurso, confirme elegibilidade com a escola.';
        } else {
            $saude = $user->temControloAcademicoInstitucional() || $user->hasRole('admin_full')
                ? $this->saudeEscola->indicadores()
                : $this->saudeTurmas->indicadores($user);

            $linhas[] = '### Escopo';
            $linhas[] = '- '.$escopo['escopo'];
            $linhas[] = '- Turmas acessíveis: '.($escopo['turmas_acessiveis'] ?? count($saude['turmas'] ?? []));
            $linhas[] = '';
            $linhas[] = '### Saúde académica';
            $linhas[] = '- Score: '.($saude['score'] ?? '—').'/100 — '.($saude['score_label'] ?? '');
            if (! empty($saude['lancamento']['cobertura_pct'])) {
                $linhas[] = '- Cobertura de lançamento: '.$saude['lancamento']['cobertura_pct'].'%';
            }
            if (! empty($saude['transicao']['total_alunos'])) {
                $t = $saude['transicao'];
                $linhas[] = '- Transição T3: '.$t['transita'].' transita · '.$t['nao_transita'].' n/trans.';
            }
            $linhas[] = '';
            $linhas[] = '### Prioridades';
            if (($saude['transicao']['incompletos'] ?? 0) > 0) {
                $linhas[] = '- Completar pautas com lançamento incompleto.';
            }
            if (($saude['lancamento']['cobertura_pct'] ?? 100) < 80) {
                $linhas[] = '- Aumentar cobertura de lançamento de notas.';
            }
            $linhas[] = '- Rever elegibilidade a exames se aplicável.';
        }

        $linhas[] = '';
        $linhas[] = '_Resumo gerado localmente (IA indisponível)._';

        return implode("\n", $linhas);
    }
}
