<?php

namespace App\Services;

use App\Models\CalendarioProva;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\TurmaCalendarioProvaConfig;
use App\Models\TurmaHorario;
use App\Services\AI\AIServiceInterface;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CalendarioProvaIaGeradorService
{
    public const MAX_PROVAS_POR_DIA = 2;

    public function __construct(
        protected AIServiceInterface $ai,
        protected CalendarioProvaSupervisorService $supervisorService,
    ) {}

    /**
     * @return array{criados: int, conflitos: array<int, string>, turmas: int, dias: int, pares_dificil_facil: int, supervisores: int}
     */
    public function gerar(
        string $dataInicio,
        string $dataFim,
        ?int $turmaId = null,
        ?int $anoLectivoId = null,
        string $periodo = 'Período de Provas',
    ): array {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $inicio = Carbon::parse($dataInicio)->startOfDay();
        $fim = Carbon::parse($dataFim)->startOfDay();

        if ($fim->lt($inicio)) {
            throw new RuntimeException('A data final deve ser posterior à data inicial.');
        }

        $associacoes = ProfessorDisciplinaTurma::query()
            ->with(['disciplina', 'turma', 'professor'])
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->when($turmaId, fn ($q) => $q->where('turma_id', $turmaId))
            ->get();

        if ($associacoes->isEmpty()) {
            throw new RuntimeException('Não existem disciplinas associadas à(s) turma(s) seleccionada(s).');
        }

        $diasUteis = collect(CarbonPeriod::create($inicio, $fim))
            ->filter(fn (Carbon $d) => $d->isWeekday())
            ->values();

        if ($diasUteis->isEmpty()) {
            throw new RuntimeException('O intervalo seleccionado não contém dias úteis.');
        }

        $this->sincronizarConfigsTurno($associacoes, $anoLectivoId);

        $contexto = $this->montarContexto($associacoes, $diasUteis, $periodo, $anoLectivoId, $inicio, $fim);

        $provas = collect();

        try {
            $proposta = $this->ai->chatJson(
                [
                    [
                        'role' => 'system',
                        'content' => 'És um planeador de calendário de provas escolares angolano. Devolve apenas JSON válido. '
                            .'Regras obrigatórias: '
                            .'(1) Distribuir todas as provas entre data_inicio e data_fim (apenas dias úteis). '
                            .'(2) Preferir 1 prova por turma por dia; só usar slot 1 e 2 no mesmo dia se o nº de disciplinas exceder os dias úteis. '
                            .'(3) Máximo 2 provas por turma por dia quando necessário por falta de dias. '
                            .'(3) Nunca agendar duas disciplinas de dificuldade "dificil" no mesmo dia da mesma turma. '
                            .'(4) Se uma disciplina difícil partilhar o dia com outra, a segunda deve ser "facil". '
                            .'(5) Disciplinas difíceis podem ficar sozinhas num dia. '
                            .'(6) O mesmo professor não pode ter provas em turmas diferentes no mesmo dia.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ],
                ],
                [
                    'type' => 'object',
                    'properties' => [
                        'provas' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'turma_id' => ['type' => 'integer'],
                                    'disciplina_id' => ['type' => 'integer'],
                                    'data_prova' => ['type' => 'string'],
                                    'slot' => ['type' => 'integer'],
                                ],
                                'required' => ['turma_id', 'disciplina_id', 'data_prova', 'slot'],
                            ],
                        ],
                    ],
                    'required' => ['provas'],
                ],
                ['temperature' => 0.2]
            );

            $propostaIa = collect($proposta['provas'] ?? []);
            if ($propostaIa->isNotEmpty()) {
                $candidata = $this->enriquecerPropostaIa($propostaIa, $associacoes, $diasUteis, $anoLectivoId);
                if ($this->propostaValida($candidata, $associacoes, $diasUteis)) {
                    $provas = $candidata;
                }
            }
        } catch (\Throwable) {
            // Usa fallback determinístico abaixo.
        }

        if ($provas->isEmpty()) {
            $provas = $this->gerarPorDificuldade($associacoes, $diasUteis, $anoLectivoId);
        }

        $provas = $this->supervisorService->atribuirSupervisores($provas, $anoLectivoId);

        return $this->persistir($provas, $anoLectivoId, $associacoes, $periodo);
    }

    /**
     * @param  Collection<int, ProfessorDisciplinaTurma>  $associacoes
     */
    protected function sincronizarConfigsTurno(Collection $associacoes, int $anoLectivoId): void
    {
        foreach ($associacoes->pluck('turma_id')->unique() as $turmaId) {
            $turma = Turma::with(['turno', 'curso'])->find($turmaId);
            if (! $turma) {
                continue;
            }

            $padrao = TurmaCalendarioProvaConfig::horariosPadraoParaTurno($turma->resolverTurno());

            TurmaCalendarioProvaConfig::updateOrCreate(
                [
                    'turma_id' => $turma->id,
                    'ano_lectivo_id' => $anoLectivoId,
                ],
                $padrao
            );
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $provas
     * @param  Collection<int, ProfessorDisciplinaTurma>  $associacoes
     * @param  Collection<int, Carbon>  $diasUteis
     */
    protected function propostaValida(Collection $provas, Collection $associacoes, Collection $diasUteis): bool
    {
        if (! $this->propostaCompleta($provas, $associacoes)) {
            return false;
        }

        $numDias = $diasUteis->count();

        foreach ($associacoes->groupBy('turma_id') as $turmaId => $items) {
            $total = $items->count();
            $provasTurma = $provas->where('turma_id', (int) $turmaId);

            if ($total <= $numDias) {
                $porDia = $provasTurma->groupBy('data_prova');
                foreach ($porDia as $provasDia) {
                    if ($provasDia->count() > 1) {
                        return false;
                    }
                }
            }

            foreach ($provasTurma->groupBy('data_prova') as $provasDia) {
                $dificuldades = $provasDia->pluck('dificuldade')->filter();
                if ($dificuldades->filter(fn ($d) => $d === Disciplina::DIFICULDADE_DIFICIL)->count() > 1) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $provas
     * @param  Collection<int, ProfessorDisciplinaTurma>  $associacoes
     */
    protected function propostaCompleta(Collection $provas, Collection $associacoes): bool
    {
        $esperadas = $associacoes->groupBy(fn ($a) => $a->turma_id.'-'.$a->disciplina_id)->count();

        return $provas->groupBy(fn ($p) => $p['turma_id'].'-'.$p['disciplina_id'])->count() >= $esperadas;
    }

    /**
     * @param  Collection<int, ProfessorDisciplinaTurma>  $associacoes
     * @param  Collection<int, Carbon>  $diasUteis
     * @return array<string, mixed>
     */
    protected function montarContexto(
        Collection $associacoes,
        Collection $diasUteis,
        string $periodo,
        int $anoLectivoId,
        Carbon $inicio,
        Carbon $fim,
    ): array {
        $turmas = $associacoes->groupBy('turma_id')->map(function ($items, $turmaId) {
            $turma = $items->first()->turma;
            $turma?->loadMissing(['turno', 'curso']);

            return [
                'turma_id' => (int) $turmaId,
                'turma_nome' => $turma?->nome,
                'turma_codigo' => $turma?->codigo,
                'turno' => $turma?->turnoNomeResolvido(),
                'turno_letra' => $turma?->inferirTurnoLetra(),
                'curso' => $turma?->curso?->nome,
                'classe' => $turma?->numeroClasse(),
                'disciplinas' => $items->map(fn ($a) => [
                    'disciplina_id' => $a->disciplina_id,
                    'disciplina_nome' => $a->disciplina?->nome,
                    'nivel_dificuldade' => $a->disciplina?->nivel_dificuldade ?? Disciplina::DIFICULDADE_MEDIO,
                    'professor_id' => $a->user_id,
                    'professor_nome' => $a->professor?->name,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'instrucoes' => 'Distribui uma prova por disciplina no período. Use 1 prova por dia sempre que houver dias úteis suficientes.',
            'periodo' => $periodo,
            'data_inicio' => $inicio->format('Y-m-d'),
            'data_fim' => $fim->format('Y-m-d'),
            'max_provas_por_dia' => self::MAX_PROVAS_POR_DIA,
            'preferir_uma_prova_por_dia' => true,
            'duas_provas_mesmo_dia_apenas_se' => 'número de disciplinas > número de dias úteis no período',
            'regras_dificuldade' => [
                'nunca_duas_dificil_mesmo_dia' => true,
                'dificil_pode_ficar_sozinha' => true,
                'dificil_com_segunda_prova_apenas_facil' => true,
            ],
            'ano_lectivo_id' => $anoLectivoId,
            'datas_disponiveis' => $diasUteis->map(fn (Carbon $d) => [
                'data' => $d->format('Y-m-d'),
                'dia_semana' => TurmaHorario::diasSemana()[$d->dayOfWeekIso] ?? $d->translatedFormat('l'),
            ])->all(),
            'turmas' => $turmas,
        ];
    }

    /**
     * @param  Collection<int, ProfessorDisciplinaTurma>  $associacoes
     * @param  Collection<int, Carbon>  $diasUteis
     * @return Collection<int, array<string, mixed>>
     */
    protected function gerarPorDificuldade(Collection $associacoes, Collection $diasUteis, int $anoLectivoId): Collection
    {
        $provas = collect();
        $professorDiaOcupado = [];
        $configs = [];

        foreach ($associacoes->groupBy('turma_id') as $turmaId => $items) {
            $turmaId = (int) $turmaId;
            $turma = Turma::with(['turno', 'curso'])->find($turmaId);
            $configs[$turmaId] = $turma
                ? TurmaCalendarioProvaConfig::paraTurmaComTurno($turma, $anoLectivoId)
                : TurmaCalendarioProvaConfig::paraTurma($turmaId, $anoLectivoId);

            $pendentes = $items->map(fn ($a) => [
                'assoc' => $a,
                'disciplina_id' => $a->disciplina_id,
                'dificuldade' => $a->disciplina?->nivel_dificuldade ?? Disciplina::DIFICULDADE_MEDIO,
                'peso' => $a->disciplina?->dificuldadePeso() ?? 2,
            ])->sortByDesc('peso')->values();

            /** @var array<string, array{slots: array<int, string|null>, dificuldades: array<int, string|null>}> $estadoDias */
            $estadoDias = [];
            foreach ($diasUteis as $dia) {
                $estadoDias[$dia->format('Y-m-d')] = [
                    'slots' => [1 => null, 2 => null],
                    'dificuldades' => [1 => null, 2 => null],
                ];
            }

            $colocar = function (ProfessorDisciplinaTurma $assoc, string $data, int $slot) use (
                &$estadoDias,
                &$professorDiaOcupado,
                &$provas,
                $turmaId,
                $configs
            ): bool {
                $estado = $estadoDias[$data] ?? null;
                if (! $estado || $estado['slots'][$slot] !== null) {
                    return false;
                }

                $dificuldade = $assoc->disciplina?->nivel_dificuldade ?? Disciplina::DIFICULDADE_MEDIO;
                $outroSlot = $slot === 1 ? 2 : 1;
                $outraDificuldade = $estado['dificuldades'][$outroSlot];

                if (! $this->parDificuldadesPermitido($dificuldade, $outraDificuldade)) {
                    return false;
                }

                $chaveProf = "{$assoc->user_id}-{$data}";
                if (isset($professorDiaOcupado[$chaveProf])) {
                    return false;
                }

                $config = $configs[$turmaId];
                $horarios = $slot === 2
                    ? ['inicio' => $config->hora_2a_inicio, 'fim' => $config->hora_2a_fim]
                    : ['inicio' => $config->hora_1a_inicio, 'fim' => $config->hora_1a_fim];

                $provas->push([
                    'turma_id' => $turmaId,
                    'disciplina_id' => $assoc->disciplina_id,
                    'data_prova' => $data,
                    'slot' => $slot,
                    'hora_inicio' => substr((string) $horarios['inicio'], 0, 5),
                    'hora_fim' => substr((string) $horarios['fim'], 0, 5),
                    'dificuldade' => $dificuldade,
                ]);

                $estadoDias[$data]['slots'][$slot] = (string) $assoc->disciplina_id;
                $estadoDias[$data]['dificuldades'][$slot] = $dificuldade;
                $professorDiaOcupado[$chaveProf] = true;

                return true;
            };

            $totalDisciplinas = $pendentes->count();
            $numDias = $diasUteis->count();
            $precisaDuasPorDia = $totalDisciplinas > $numDias;

            $todas = $pendentes->sortByDesc('peso')->values();
            $agendadas = [];

            foreach ($todas as $item) {
                if (in_array($item['disciplina_id'], $agendadas, true)) {
                    continue;
                }

                $dataEscolhida = $this->escolherMelhorDia(
                    $estadoDias,
                    $diasUteis,
                    $item['dificuldade'],
                    1
                );

                if (! $dataEscolhida || ! $colocar($item['assoc'], $dataEscolhida, 1)) {
                    continue;
                }

                $agendadas[] = $item['disciplina_id'];
            }

            if ($precisaDuasPorDia) {
                $restantes = $todas
                    ->reject(fn ($item) => in_array($item['disciplina_id'], $agendadas, true))
                    ->sortBy(fn ($item) => match ($item['dificuldade']) {
                        Disciplina::DIFICULDADE_FACIL => 0,
                        Disciplina::DIFICULDADE_MEDIO => 1,
                        default => 2,
                    })
                    ->values();

                foreach ($restantes as $item) {
                    $colocado = false;

                    foreach ($this->ordenarDiasParaSegundoSlot($estadoDias, $diasUteis) as $data) {
                        if ($colocar($item['assoc'], $data, 2)) {
                            $agendadas[] = $item['disciplina_id'];
                            $colocado = true;
                            break;
                        }
                    }

                    if (! $colocado) {
                        continue;
                    }
                }
            }
        }

        return $provas;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $propostaIa
     * @param  Collection<int, ProfessorDisciplinaTurma>  $associacoes
     * @param  Collection<int, Carbon>  $diasUteis
     * @return Collection<int, array<string, mixed>>
     */
    protected function enriquecerPropostaIa(
        Collection $propostaIa,
        Collection $associacoes,
        Collection $diasUteis,
        int $anoLectivoId,
    ): Collection {
        $datasValidas = $diasUteis->map(fn (Carbon $d) => $d->format('Y-m-d'))->flip();
        $provas = collect();

        foreach ($propostaIa as $item) {
            $turmaId = (int) ($item['turma_id'] ?? 0);
            $disciplinaId = (int) ($item['disciplina_id'] ?? 0);
            $data = substr((string) ($item['data_prova'] ?? ''), 0, 10);
            $slot = (int) ($item['slot'] ?? 1);

            if (! $turmaId || ! $disciplinaId || ! isset($datasValidas[$data]) || ! in_array($slot, [1, 2], true)) {
                continue;
            }

            $assoc = $associacoes->first(fn ($a) => (int) $a->turma_id === $turmaId
                && (int) $a->disciplina_id === $disciplinaId);

            if (! $assoc) {
                continue;
            }

            $config = TurmaCalendarioProvaConfig::paraTurma($turmaId, $anoLectivoId);
            $horarios = $slot === 2
                ? ['inicio' => $config->hora_2a_inicio, 'fim' => $config->hora_2a_fim]
                : ['inicio' => $config->hora_1a_inicio, 'fim' => $config->hora_1a_fim];

            $provas->push([
                'turma_id' => $turmaId,
                'disciplina_id' => $disciplinaId,
                'data_prova' => $data,
                'slot' => $slot,
                'hora_inicio' => substr((string) $horarios['inicio'], 0, 5),
                'hora_fim' => substr((string) $horarios['fim'], 0, 5),
                'dificuldade' => $assoc->disciplina?->nivel_dificuldade ?? Disciplina::DIFICULDADE_MEDIO,
            ]);
        }

        return $provas;
    }

    /**
     * @param  array<string, array{slots: array<int, string|null>, dificuldades: array<int, string|null>}>  $estadoDias
     * @param  Collection<int, Carbon>  $diasUteis
     * @return array<int, string>
     */
    protected function ordenarDiasPorOcupacao(array $estadoDias, Collection $diasUteis): array
    {
        return $diasUteis
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->sortBy(function (string $data) use ($estadoDias) {
                $estado = $estadoDias[$data] ?? ['slots' => [1 => null, 2 => null]];

                return (int) ($estado['slots'][1] !== null) + (int) ($estado['slots'][2] !== null);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{slots: array<int, string|null>, dificuldades: array<int, string|null>}>  $estadoDias
     * @param  Collection<int, Carbon>  $diasUteis
     * @return array<int, string>
     */
    protected function ordenarDiasParaSegundoSlot(array $estadoDias, Collection $diasUteis): array
    {
        return $diasUteis
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->filter(function (string $data) use ($estadoDias) {
                $estado = $estadoDias[$data] ?? null;

                return $estado
                    && $estado['slots'][1] !== null
                    && $estado['slots'][2] === null;
            })
            ->sortBy(function (string $data) use ($estadoDias) {
                $estado = $estadoDias[$data];

                return $estado['dificuldades'][1] === Disciplina::DIFICULDADE_DIFICIL ? 0 : 1;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{slots: array<int, string|null>, dificuldades: array<int, string|null>}>  $estadoDias
     * @param  Collection<int, Carbon>  $diasUteis
     */
    protected function escolherMelhorDia(
        array $estadoDias,
        Collection $diasUteis,
        string $dificuldade,
        int $slotPreferido,
    ): ?string {
        foreach ($this->ordenarDiasPorOcupacao($estadoDias, $diasUteis) as $data) {
            $estado = $estadoDias[$data];
            if ($estado['slots'][$slotPreferido] !== null) {
                continue;
            }

            $outroSlot = $slotPreferido === 1 ? 2 : 1;
            if ($this->parDificuldadesPermitido($dificuldade, $estado['dificuldades'][$outroSlot])) {
                return $data;
            }
        }

        return null;
    }

    protected function parDificuldadesPermitido(?string $nova, ?string $existente): bool
    {
        if ($existente === null || $nova === null) {
            return true;
        }

        if ($nova === Disciplina::DIFICULDADE_DIFICIL && $existente === Disciplina::DIFICULDADE_DIFICIL) {
            return false;
        }

        if ($nova === Disciplina::DIFICULDADE_DIFICIL && $existente !== Disciplina::DIFICULDADE_FACIL) {
            return false;
        }

        if ($existente === Disciplina::DIFICULDADE_DIFICIL && $nova !== Disciplina::DIFICULDADE_FACIL) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $provas
     * @param  Collection<int, ProfessorDisciplinaTurma>  $associacoes
     * @return array{criados: int, conflitos: array<int, string>, turmas: int, dias: int, pares_dificil_facil: int, supervisores: int}
     */
    protected function persistir(Collection $provas, int $anoLectivoId, Collection $associacoes, string $periodo): array
    {
        $turmaIds = $provas->pluck('turma_id')->unique()->filter()->values();
        $conflitos = [];
        $criados = 0;
        $diasUtilizados = [];
        $paresDificilFacil = 0;
        $supervisoresAtribuidos = 0;

        DB::transaction(function () use ($provas, $anoLectivoId, $turmaIds, &$conflitos, &$criados, &$diasUtilizados, &$paresDificilFacil, &$supervisoresAtribuidos, $periodo, $associacoes) {
            CalendarioProva::where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('turma_id', $turmaIds)
                ->delete();

            $turmaDiaSlot = [];
            $turmaDiaDificuldades = [];
            $professorDia = [];

            foreach ($provas as $item) {
                $turmaId = (int) ($item['turma_id'] ?? 0);
                $disciplinaId = (int) ($item['disciplina_id'] ?? 0);
                $data = substr((string) ($item['data_prova'] ?? ''), 0, 10);
                $slot = (int) ($item['slot'] ?? 1);

                if (! $turmaId || ! $disciplinaId || ! $data || ! in_array($slot, [1, 2], true)) {
                    continue;
                }

                $chaveSlot = "{$turmaId}-{$data}-{$slot}";
                if (isset($turmaDiaSlot[$chaveSlot])) {
                    $conflitos[] = "Turma #{$turmaId} já tem prova no slot {$slot} em {$data}.";

                    continue;
                }

                $assoc = $associacoes->first(fn ($a) => (int) $a->turma_id === $turmaId
                    && (int) $a->disciplina_id === $disciplinaId);

                $dificuldade = $item['dificuldade']
                    ?? $assoc?->disciplina?->nivel_dificuldade
                    ?? Disciplina::DIFICULDADE_MEDIO;

                $chaveDia = "{$turmaId}-{$data}";
                $existentes = $turmaDiaDificuldades[$chaveDia] ?? [];
                foreach ($existentes as $difExistente) {
                    if (! $this->parDificuldadesPermitido($dificuldade, $difExistente)) {
                        $conflitos[] = "Combinação de dificuldade inválida na turma #{$turmaId} em {$data}.";

                        continue 2;
                    }
                }

                if ($assoc) {
                    $chaveProf = "{$assoc->user_id}-{$data}";
                    if (isset($professorDia[$chaveProf])) {
                        $conflitos[] = "Professor {$assoc->professor?->name} com choque em {$data}.";

                        continue;
                    }
                    $professorDia[$chaveProf] = true;
                }

                CalendarioProva::create([
                    'turma_id' => $turmaId,
                    'disciplina_id' => $disciplinaId,
                    'ano_lectivo_id' => $anoLectivoId,
                    'data_prova' => $data,
                    'slot' => $slot,
                    'hora_inicio' => $item['hora_inicio'] ?? '08:30',
                    'hora_fim' => $item['hora_fim'] ?? '10:20',
                    'tipo' => 'normal',
                    'periodo' => $periodo,
                    'supervisor_nome' => $item['supervisor_nome'] ?? null,
                ]);

                if (! empty($item['supervisor_nome'])) {
                    $supervisoresAtribuidos++;
                }

                $turmaDiaSlot[$chaveSlot] = true;
                $turmaDiaDificuldades[$chaveDia][] = $dificuldade;
                $diasUtilizados[$chaveDia] = true;

                if (count($turmaDiaDificuldades[$chaveDia]) === 2) {
                    $vals = $turmaDiaDificuldades[$chaveDia];
                    if (in_array(Disciplina::DIFICULDADE_DIFICIL, $vals, true)
                        && in_array(Disciplina::DIFICULDADE_FACIL, $vals, true)) {
                        $paresDificilFacil++;
                    }
                }

                $criados++;
            }
        });

        return [
            'criados' => $criados,
            'conflitos' => $conflitos,
            'turmas' => $turmaIds->count(),
            'dias' => count($diasUtilizados),
            'pares_dificil_facil' => $paresDificilFacil,
            'supervisores' => $supervisoresAtribuidos,
        ];
    }
}
