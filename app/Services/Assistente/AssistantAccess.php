<?php

namespace App\Services\Assistente;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Support\CargosInstitucionais;
use Illuminate\Support\Facades\Gate;

class AssistantAccess
{
    public const PII_COMPLETO = 'completo';

    public const PII_GESTAO = 'gestao';

    public const PII_PROFESSOR = 'professor';

    public const PII_ALUNO = 'aluno';

    public const PII_ENCARREGADO = 'encarregado';

    public function canUse(User $user): bool
    {
        return $user->can('assistente.usar');
    }

    public function canViewAluno(User $user, Aluno $aluno): bool
    {
        if ($user->hasRole('aluno')) {
            return (int) $user->aluno_id === (int) $aluno->id;
        }

        if ($user->hasRole('encarregado') && $user->encarregado_id) {
            $pivot = $user->encarregado?->alunos()->where('alunos.id', $aluno->id)->first();

            return (bool) $pivot;
        }

        return Gate::forUser($user)->allows('view', $aluno);
    }

    public function canViewNotasAluno(User $user, Aluno $aluno): bool
    {
        if ($user->hasRole('aluno')) {
            return (int) $user->aluno_id === (int) $aluno->id;
        }

        if ($user->hasRole('encarregado') && $user->encarregado_id) {
            $pivot = $user->encarregado?->alunos()->where('alunos.id', $aluno->id)->first();

            return (bool) ($pivot?->pivot?->pode_ver_notas);
        }

        return $this->canViewAluno($user, $aluno);
    }

    public function canViewEstatisticas(User $user): bool
    {
        if ($user->hasRole('aluno')) {
            return false;
        }

        return Gate::forUser($user)->allows('viewStatistics', Aluno::class);
    }

    public function canViewTurma(User $user, Turma $turma): bool
    {
        return in_array($turma->id, $this->getTurmasIdsAcessiveis($user), true);
    }

    public function canViewProfessor(User $user, User $professor): bool
    {
        if ($user->id === $professor->id) {
            return true;
        }

        if ($user->hasAnyRole(CargosInstitucionais::visaoInstitucional())) {
            return true;
        }

        $turmasUtilizador = $this->getTurmasIdsAcessiveis($user);
        if ($turmasUtilizador === []) {
            return false;
        }

        return ProfessorDisciplinaTurma::where('user_id', $professor->id)
            ->where('activo', true)
            ->whereIn('turma_id', $turmasUtilizador)
            ->exists();
    }

    public function nivelPii(User $user): string
    {
        if ($user->hasRole('aluno')) {
            return self::PII_ALUNO;
        }

        if ($user->hasRole('encarregado')) {
            return self::PII_ENCARREGADO;
        }

        if ($user->hasAnyRole(CargosInstitucionais::controloAcademicoTotal())) {
            return self::PII_COMPLETO;
        }

        if ($user->hasAnyRole([CargosInstitucionais::SUBDIRECTOR, 'director_turma', 'coordenador_curso'])) {
            return self::PII_GESTAO;
        }

        return self::PII_PROFESSOR;
    }

    public function incluirDadosPessoais(User $user, ?Aluno $aluno = null): bool
    {
        $nivel = $this->nivelPii($user);

        if ($nivel === self::PII_ALUNO) {
            return false;
        }

        if (in_array($nivel, [self::PII_COMPLETO, self::PII_GESTAO], true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int>
     */
    public function getTurmasIdsAcessiveis(User $user, ?int $anoLectivoId = null): array
    {
        if ($user->hasRole('aluno') && $user->aluno_id) {
            $turmaId = $user->aluno?->turma_id;

            return $turmaId ? [(int) $turmaId] : [];
        }

        if ($user->hasRole('encarregado') && $user->encarregado_id) {
            return $user->encarregado?->alunos()
                ->where('alunos.activo', true)
                ->whereNotNull('turma_id')
                ->pluck('turma_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all() ?? [];
        }

        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        if (! $anoLectivoId) {
            return [];
        }

        if ($user->hasAnyRole(CargosInstitucionais::visaoInstitucional())) {
            return Turma::query()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('activo', true)
                ->pluck('id')
                ->all();
        }

        $ids = collect();

        if ($user->hasRole('coordenador_curso')) {
            $cursoIds = $user->coordenadorCursos()
                ->where('activo', true)
                ->pluck('curso_id');

            $ids = $ids->merge(
                Turma::query()
                    ->whereIn('curso_id', $cursoIds)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->where('activo', true)
                    ->pluck('id')
            );
        }

        if ($user->hasRole('director_turma')) {
            $ids = $ids->merge(
                $user->turmasComoDirector()
                    ->wherePivot('activo', true)
                    ->where('turmas.ano_lectivo_id', $anoLectivoId)
                    ->pluck('turmas.id')
            );
        }

        $ids = $ids->merge(
            $user->turmasComoProfessor()
                ->wherePivot('activo', true)
                ->where('turmas.ano_lectivo_id', $anoLectivoId)
                ->pluck('turma_id')
        );

        return $ids->unique()->values()->all();
    }

    public function descricaoEscopo(User $user): string
    {
        $roles = $user->getRoleNames()->implode(', ');

        return match ($this->nivelPii($user)) {
            self::PII_COMPLETO => "Visão institucional completa ({$roles}).",
            self::PII_GESTAO => "Visão de gestão académica — turmas/cursos sob sua responsabilidade ({$roles}).",
            self::PII_PROFESSOR => "Visão docente — turmas e disciplinas que lecciona ({$roles}).",
            self::PII_ENCARREGADO => 'Portal do encarregado — apenas educandos associados à sua conta.',
            default => 'Portal do aluno — apenas o próprio desempenho e orientação sobre o Facilita.',
        };
    }

    /**
     * Perfil de resposta do assistente (determina tom e limites).
     */
    public function perfilResposta(User $user): string
    {
        return match ($this->nivelPii($user)) {
            self::PII_COMPLETO => 'completo',
            self::PII_GESTAO => 'gestao',
            self::PII_PROFESSOR => 'professor',
            self::PII_ENCARREGADO => 'encarregado',
            default => 'aluno',
        };
    }

    /**
     * Regras estruturadas injectadas no contexto e no prompt do sistema.
     *
     * @return array<string, mixed>
     */
    public function regrasResposta(User $user): array
    {
        if ($user->hasRole(CargosInstitucionais::GESTOR_ACADEMICO)) {
            return [
                'perfil' => 'gestor_academico',
                'tom' => 'institucional e operacional; foco em todos os cursos e turmas',
                'pode' => [
                    'Consultar e gerir notas, mini-pautas e pautas de qualquer curso/turma',
                    'Desempenho académico de qualquer aluno da instituição',
                    'Turmas, alunos, cursos e disciplinas (no âmbito académico)',
                    'Conselhos, boletins, recuperação e recurso',
                ],
                'nao_pode' => [
                    'Gestão de utilizadores e cargos do sistema',
                    'Alterar configurações técnicas do sistema',
                    'Credenciais ou dados técnicos internos',
                ],
                'mensagem_recusa' => 'Essa acção está fora do âmbito académico ou requer perfil de administração do sistema.',
            ];
        }

        return match ($this->perfilResposta($user)) {
            'aluno' => [
                'perfil' => 'aluno',
                'tom' => 'encorajador e pedagógico; foco no próprio aluno',
                'pode' => [
                    'Consultar e interpretar as suas notas e desempenho (incluindo comparação entre trimestres)',
                    'Indicar disciplinas a melhorar e melhores notas com base em analise_desempenho',
                    'Saber professores, director de turma, coordenador e dados públicos da escola (instituicao, lideranca)',
                    'Ver directores das turmas do seu curso (gestao_institucional)',
                    'Entender MAC, PP, PT, MT, exame de melhoria/recurso e regras de aprovação no Facilita',
                ],
                'nao_pode' => [
                    'Notas, médias ou dados de colegas ou outros alunos',
                    'Listas de alunos da turma ou estatísticas da turma/escola',
                    'Dados pessoais de terceiros (contactos, moradas, etc.)',
                    'Instruções para lançar, alterar ou apagar notas',
                    'Assuntos administrativos internos da escola',
                    'Temas gerais sem relação com o Facilita',
                ],
                'mensagem_recusa' => 'Como aluno, só posso ajudar com o seu próprio desempenho e com o uso do portal Facilita. '
                    .'Para questões sobre colegas, alteração de notas ou assuntos administrativos, fale com o seu professor ou a secretaria.',
            ],
            'encarregado' => [
                'perfil' => 'encarregado',
                'tom' => 'claro e respeitoso; foco nos educandos associados',
                'pode' => [
                    'Consultar e interpretar notas dos educandos com permissão pode_ver_notas',
                    'Situação académica, turma, professores e gestão da turma dos educandos',
                    'Explicar MAC, PP, PT, MT, CFD e regras de aprovação do Facilita',
                    'Orientar sobre o uso do portal do encarregado',
                ],
                'nao_pode' => [
                    'Dados de alunos que não estão associados à conta',
                    'Notas de educandos sem permissão pode_ver_notas',
                    'Rankings, listas da turma ou dados de colegas dos educandos',
                    'Alterar notas ou dados administrativos',
                    'Credenciais ou contactos privados de professores',
                ],
                'mensagem_recusa' => 'Como encarregado, só posso ajudar com os educandos associados à sua conta e com o uso do portal Facilita.',
            ],
            'professor' => [
                'perfil' => 'professor',
                'tom' => 'técnico-operacional; referência a menus e fluxos do Facilita',
                'pode' => [
                    'Turmas e disciplinas que lecciona (turmas_que_lecciona)',
                    'Alunos sem MAC/PP lançados no trimestre actual (pendencias_lancamento)',
                    'Desempenho académico dos alunos das suas turmas (analise_desempenho)',
                    'Comparações entre alunos da mesma turma, melhores alunos, notas baixas',
                    'Contagens de alunos, professores por turma, coordenadores e directores',
                    'Exame de melhoria e recomendações pedagógicas com base nos dados',
                ],
                'nao_pode' => [
                    'Dados pessoais sensíveis de alunos (nascimento, necessidades especiais, etc.)',
                    'Alunos ou turmas fora do seu escopo de leccionação',
                    'Emails ou credenciais de outros utilizadores',
                ],
                'mensagem_recusa' => 'Não tenho acesso a esses dados no seu perfil de professor ou a informação não está no Facilita para o seu escopo.',
            ],
            'gestao' => [
                'perfil' => 'gestao',
                'tom' => 'analítico; indicadores académicos no âmbito de gestão',
                'pode' => [
                    'Alunos, turmas e cursos sob sua responsabilidade',
                    'Rankings, comparações, contagens e estatísticas no âmbito',
                    'Coordenadores, directores de turma, subdirector e liderança',
                    'Notas baixas, melhorias e aproveitamento por trimestre',
                ],
                'nao_pode' => [
                    'Turmas ou cursos fora do seu âmbito',
                    'Credenciais ou dados técnicos do sistema',
                ],
                'mensagem_recusa' => 'Essa consulta está fora do seu âmbito de gestão no Facilita.',
            ],
            default => [
                'perfil' => 'completo',
                'tom' => 'institucional e sintético',
                'pode' => [
                    'Visão global da instituição, rankings e estatísticas',
                    'Qualquer dado académico presente no contexto do sistema',
                    'Contagens, melhores alunos, comparações e equipa pedagógica',
                ],
                'nao_pode' => [
                    'Credenciais, chaves API ou dados técnicos internos',
                ],
                'mensagem_recusa' => 'Não tenho essa informação disponível no contexto actual do Facilita.',
            ],
        };
    }

    /**
     * Texto das regras para o prompt do sistema.
     */
    public function regrasRespostaTexto(User $user): string
    {
        $regras = $this->regrasResposta($user);
        $linhas = [
            'REGRAS DO SEU PERFIL ('.strtoupper($regras['perfil']).')',
            'Tom: '.$regras['tom'],
            'Pode responder sobre:',
        ];

        foreach ($regras['pode'] as $item) {
            $linhas[] = '  • '.$item;
        }

        $linhas[] = 'NÃO pode responder sobre:';
        foreach ($regras['nao_pode'] as $item) {
            $linhas[] = '  • '.$item;
        }

        $linhas[] = 'Se a pergunta violar estas regras, recuse educadamente usando esta orientação: '.$regras['mensagem_recusa'];

        return implode("\n", $linhas);
    }

    /**
     * Validação server-side: bloqueia perguntas claramente fora do âmbito do aluno.
     *
     * @return array{permitida: bool, resposta?: string}
     */
    public function avaliarPergunta(User $user, string $mensagem): array
    {
        if ($user->hasRole('encarregado')) {
            return $this->avaliarPerguntaEncarregado($user, $mensagem);
        }

        if (! $user->hasRole('aluno')) {
            return ['permitida' => true];
        }

        $msg = mb_strtolower(trim($mensagem));

        if ($this->perguntaSobreOutroAluno($user, $mensagem)) {
            return [
                'permitida' => false,
                'resposta' => 'Como aluno, só posso mostrar o seu próprio desempenho académico, notas e turma. '
                    .'Não tenho permissão para consultar informação de colegas ou outros alunos.',
            ];
        }

        $bloqueios = [
            [
                'padrao' => '/\b(colega|colegas|outro\s+aluno|outra\s+aluna|outros\s+alunos|alunos\s+da\s+turma|lista\s+(de\s+)?alunos|quem\s+s[aã]o\s+os\s+alunos)\b/u',
                'resposta' => 'No portal do aluno não posso mostrar informação sobre colegas ou listas de alunos. '
                    .'Posso ajudar apenas com as suas notas, a sua turma e os seus professores no Facilita.',
            ],
            [
                // Só bloqueia pedidos explícitos a outra pessoa (não disciplinas/trimestres).
                'padrao' => '/\b(notas?|m[eé]dias?|desempenho|classifica[cç][aã]o)\s+(acad[eé]mico\s+)?(do|da|de)\s+(aluno|aluna|colega|estudante)\b/u',
                'resposta' => 'Só posso consultar as suas notas e o seu desempenho. '
                    .'Não tenho permissão para ver dados de outros alunos.',
            ],
            [
                'padrao' => '/\b(ranking|top\s+\d|comparar.{0,40}colega|posi[cç][aã]o\s+na\s+turma|quem\s+tem\s+as?\s+melhores?|classifica[cç][aã]o\s+(da|de)\s+turma|melhores?\s+alunos?\s+(da|de)\s+turma)\b/u',
                'resposta' => 'No portal do aluno não posso comparar o seu desempenho com colegas nem mostrar rankings da turma. '
                    .'Posso analisar o seu desempenho, comparar os seus trimestres e indicar onde pode melhorar.',
            ],
            [
                'padrao' => '/\b(quantos\s+alunos|total\s+de\s+alunos|estat[ií]stica\s+da\s+turma|melhores\s+da\s+turma)\b/u',
                'resposta' => 'Estatísticas e rankings da turma não estão disponíveis no portal do aluno. '
                    .'Posso ajudar com as suas notas, os seus professores e informação da escola.',
            ],
            [
                // Bloqueia intenção de alterar/lançar a própria nota; permite «como funciona o lançamento».
                'padrao' => '/\b((quero|posso|como\s+posso|vou|ajuda[- ]me\s+a)\s+)?(alterar|modificar|apagar|eliminar|inserir|mudar)\s+(a\s+)?(minha\s+|minhas\s+)?(nota|notas)\b/u',
                'resposta' => 'Alunos não podem lançar nem alterar notas no Facilita — isso é feito pelos professores. '
                    .'Se achar que há um erro, fale directamente com o professor da disciplina.',
            ],
            [
                'padrao' => '/\b(quero|posso|como\s+posso|vou)\s+lan[cç]ar\s+(a\s+|as\s+)?(minha\s+|minhas\s+)?(nota|notas)\b/u',
                'resposta' => 'Alunos não podem lançar nem alterar notas no Facilita — isso é feito pelos professores. '
                    .'Se achar que há um erro, fale directamente com o professor da disciplina.',
            ],
            [
                'padrao' => '/\b(palavra.?passe|password|credencial|email\s+do\s+professor|telefone\s+do\s+(professor|director|coordenador)|morada\s+do|dados\s+pessoais\s+(do|da|de))\b/u',
                'resposta' => 'Não posso partilhar credenciais nem dados pessoais de terceiros. '
                    .'Para questões de acesso à conta, use o menu de perfil ou contacte a secretaria.',
            ],
        ];

        foreach ($bloqueios as $bloqueio) {
            if (preg_match($bloqueio['padrao'], $msg)) {
                return [
                    'permitida' => false,
                    'resposta' => $bloqueio['resposta'],
                ];
            }
        }

        return ['permitida' => true];
    }

    /**
     * Termos académicos/disciplinas — não confundir com nomes de outras pessoas.
     *
     * @return list<string>
     */
    protected function stopWordsAcademicasAluno(): array
    {
        return [
            'quero', 'saber', 'como', 'qual', 'quais', 'facilita', 'notas', 'desempenho',
            'academico', 'académico', 'trimestre', 'trimestres', 'disciplina', 'disciplinas',
            'turma', 'professor', 'professores', 'mostre', 'mostra', 'diga', 'informe', 'processo',
            'matematica', 'matemática', 'fisica', 'física', 'quimica', 'química', 'biologia',
            'historia', 'história', 'geografia', 'portugues', 'português', 'ingles', 'inglês',
            'empreendedorismo', 'informatica', 'informática', 'tic', 'educacao', 'educação',
            'primeiro', 'segundo', 'terceiro', 'periodo', 'período', 'anual', 'final',
            'continua', 'contínua', 'parcial', 'global', 'recuperacao', 'recuperação', 'recurso',
            'positivas', 'negativas', 'aprovacao', 'aprovação', 'media', 'média', 'cada',
            'todas', 'todos', 'esta', 'este', 'neste', 'deste', 'desta', 'comparar', 'comparacao',
            'comparação', 'explicar', 'explica', 'melhorar', 'melhoria', 'situacao', 'situação',
        ];
    }

    /**
     * Detecta se um aluno está a pedir dados académicos de outra pessoa.
     */
    protected function perguntaSobreOutroAluno(User $user, string $mensagem): bool
    {
        if (! $user->hasRole('aluno')) {
            return false;
        }

        $msg = mb_strtolower($mensagem);

        // Referência clara ao próprio aluno → nunca tratar como pedido sobre terceiros.
        if (preg_match('/\b(minhas?|meus?|meu|minha|eu\b|pr[oó]pri[oa]s?|a\s+mim)\b/u', $msg)) {
            return false;
        }

        $contextoAcademico = (bool) preg_match(
            '/\b(notas?|desempenho|m[eé]dias?|classifica[cç][aã]o|aproveitamento|cfd|mac|pp|mt|positivas?|negativas?|processo|turma)\b/u',
            $msg
        );

        if (! $contextoAcademico) {
            return false;
        }

        if (preg_match('/\b(?:aluno|aluna|estudante|colega)\s+[A-ZÀÁÂÃÉÈÊÍÌÎÓÒÔÕÚÙÛÇ]/u', $mensagem)) {
            return true;
        }

        // «notas do João» / «desempenho da Maria» — pessoa, não período/disciplina.
        if (preg_match(
            '/\b(?:desempenho|notas?|m[eé]dias?|classifica[cç][aã]o|aproveitamento)\s+(?:acad[eé]mico\s+)?(?:do|da|de)\s+([a-zàáâãéèêíìîóòôõúùûç]{3,})/u',
            $msg,
            $m
        )) {
            $seguinte = $m[1] ?? '';
            $permitidos = array_merge($this->stopWordsAcademicasAluno(), [
                'mim', 'minha', 'minhas', 'meu', 'meus', 'cada', 'todas', 'todos',
                '1º', '2º', '3º', '1o', '2o', '3o',
            ]);

            if ($seguinte !== '' && ! in_array($seguinte, $permitidos, true)) {
                // Nome próprio típico (não termo académico conhecido).
                if (! preg_match('/^(trimestre|disciplina|turma|ano|curso|classe|periodo|período)$/u', $seguinte)) {
                    return true;
                }
            }
        }

        $nomeProprio = $user->aluno?->nome ?: $user->name;
        if (! $nomeProprio) {
            return false;
        }

        $stopNomes = $this->stopWordsAcademicasAluno();
        $partesNome = array_filter(explode(' ', mb_strtolower($nomeProprio)));

        if (preg_match_all('/\b([A-ZÀÁÂÃÉÈÊÍÌÎÓÒÔÕÚÙÛÇ][\p{L}]{2,})\b/u', $mensagem, $matches)) {
            foreach ($matches[1] as $palavra) {
                $p = mb_strtolower($palavra);
                if (in_array($p, $stopNomes, true) || in_array($p, $partesNome, true)) {
                    continue;
                }
                // Exige indício de pedido sobre pessoa (aluno/notas de X), não só disciplina capitalizada.
                if (mb_strlen($p) >= 4 && preg_match(
                    '/\b(aluno|aluna|estudante|colega|notas?|desempenho|m[eé]dias?)\s+(?:do|da|de)\s+'.preg_quote($p, '/').'\b/u',
                    $msg
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{permitida: bool, resposta?: string}
     */
    protected function avaliarPerguntaEncarregado(User $user, string $mensagem): array
    {
        $msg = mb_strtolower(trim($mensagem));

        $bloqueios = [
            [
                'padrao' => '/\b(ranking|melhores?\s+aluno|top\s+\d|lista\s+(de\s+)?alunos|colegas?\s+do)\b/u',
                'resposta' => 'No portal do encarregado não posso mostrar rankings nem listas de colegas. '
                    .'Posso ajudar com os educandos associados à sua conta.',
            ],
            [
                'padrao' => '/\b(alterar|modificar|apagar|lan[cç]ar)\s+(a\s+)?(nota|notas)\b/u',
                'resposta' => 'Encarregados não podem alterar notas. Se encontrar um erro, contacte o professor ou a secretaria.',
            ],
            [
                'padrao' => '/\b(palavra.?passe|password|credencial|email\s+do\s+professor)\b/u',
                'resposta' => 'Não posso partilhar credenciais nem contactos privados. Use o portal ou contacte a secretaria.',
            ],
        ];

        foreach ($bloqueios as $bloqueio) {
            if (preg_match($bloqueio['padrao'], $msg)) {
                return [
                    'permitida' => false,
                    'resposta' => $bloqueio['resposta'],
                ];
            }
        }

        return ['permitida' => true];
    }
}
