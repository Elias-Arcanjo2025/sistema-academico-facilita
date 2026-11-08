<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Turma;
use App\Models\TurmaCalendarioProvaConfig;
use App\Models\TurmaHorario;
use App\Models\TurmaHorarioConfig;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HorarioEscolarService
{
    /**
     * @return array{
     *   periodos: array<int, array<string, mixed>>,
     *   legenda: array<int, array{abrev: string, designacao: string, professor: string}>,
     *   dias_colunas: array<int, string>
     * }
     */
    public function montarGradePdf(Collection $horarios, ?TurmaHorarioConfig $config = null): array
    {
        $diasColunas = [
            1 => '2ª feira',
            2 => '3ª feira',
            3 => '4ª feira',
            4 => '5ª feira',
            5 => '6ª feira',
        ];

        $slots = $horarios
            ->groupBy(fn ($h) => substr((string) $h->hora_inicio, 0, 5).'|'.substr((string) $h->hora_fim, 0, 5))
            ->keys()
            ->map(function ($key) {
                [$inicio, $fim] = explode('|', $key);

                return ['inicio' => $inicio, 'fim' => $fim];
            })
            ->sortBy('inicio')
            ->values();

        $grade = [];
        foreach ($horarios as $h) {
            $inicio = substr((string) $h->hora_inicio, 0, 5);
            $grade[$inicio][$h->dia_semana] = $h;
        }

        $periodos = [];
        $numero = 0;
        $totalSlots = $slots->count();

        foreach ($slots as $slot) {
            $numero++;
            $inicio = $slot['inicio'];
            $celulas = [];

            foreach (array_keys($diasColunas) as $dia) {
                $aula = $grade[$inicio][$dia] ?? null;
                $celulas[$dia] = $aula
                    ? strtoupper($aula->disciplina?->codigo ?? mb_substr($aula->disciplina?->nome ?? '?', 0, 4))
                    : '';
            }

            $periodos[] = [
                'numero' => $numero,
                'hora' => $this->formatarHoraPdf($inicio, $slot['fim']),
                'dias' => $celulas,
                'intervalo' => false,
            ];

            if ($this->deveInserirIntervalo($config, $numero, $totalSlots)) {
                $periodos[] = [
                    'intervalo' => true,
                    'texto' => $config->textoIntervalo(),
                ];
            }
        }

        $legenda = $horarios
            ->unique('disciplina_id')
            ->sortBy(fn ($h) => $h->disciplina?->nome)
            ->map(fn ($h) => [
                'abrev' => strtoupper($h->disciplina?->codigo ?? mb_substr($h->disciplina?->nome ?? '-', 0, 6)),
                'designacao' => mb_strtoupper($h->disciplina?->nome ?? '-'),
                'professor' => $h->professor?->name ?? '',
            ])
            ->values()
            ->all();

        $diasUsados = $horarios->pluck('dia_semana')->unique()->sort()->values();
        if ($diasUsados->contains(6)) {
            $diasColunas[6] = 'Sábado';
        }

        return [
            'periodos' => $periodos,
            'legenda' => $legenda,
            'dias_colunas' => $diasColunas,
        ];
    }

    /**
     * Linhas ordenadas para a grade web/PDF (aulas + intervalo configurado).
     *
     * @return array<int, array<string, mixed>>
     */
    public function montarLinhasGrade(Collection $horarios, ?TurmaHorarioConfig $config = null): array
    {
        $slots = $horarios
            ->groupBy(fn ($h) => substr((string) $h->hora_inicio, 0, 5).'|'.substr((string) $h->hora_fim, 0, 5))
            ->keys()
            ->map(function ($key) {
                [$inicio, $fim] = explode('|', $key);

                return ['inicio' => $inicio, 'fim' => $fim];
            })
            ->sortBy('inicio')
            ->values();

        $porInicio = [];
        foreach ($horarios as $h) {
            $porInicio[substr((string) $h->hora_inicio, 0, 5)][$h->dia_semana] = $h;
        }

        $linhas = [];
        $numero = 0;
        $total = $slots->count();

        foreach ($slots as $slot) {
            $numero++;
            $inicio = $slot['inicio'];
            $linhas[] = [
                'tipo' => 'aula',
                'numero' => $numero,
                'hora' => substr($inicio, 0, 5).' – '.substr($slot['fim'], 0, 5),
                'celulas' => $porInicio[$inicio] ?? [],
            ];

            if ($this->deveInserirIntervalo($config, $numero, $total)) {
                $linhas[] = [
                    'tipo' => 'intervalo',
                    'texto' => $config->textoIntervalo(),
                ];
            }
        }

        return $linhas;
    }

    public function prepararDadosPdf(Turma $turma, ?int $anoLectivoId = null): array
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $turma->load(['curso', 'classe', 'sala', 'turno', 'anoLectivo']);

        $horarios = TurmaHorario::with(['disciplina', 'professor'])
            ->where('turma_id', $turma->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->orderBy('hora_inicio')
            ->orderBy('dia_semana')
            ->get();

        $config = TurmaHorarioConfig::paraTurma($turma->id, $anoLectivoId);
        $grade = $this->montarGradePdf($horarios, $config);

        $anoInicio = $turma->ano_lectivo;
        if (is_numeric($anoInicio)) {
            $anoLabel = "{$anoInicio}/".((int) $anoInicio + 1);
        } else {
            $anoLabel = Configuracao::getAnoLectivoActual();
        }

        $instituicao = app(ConfiguracaoService::class)->getInstituicao();

        return array_merge($grade, [
            'turma' => $turma,
            'curso' => $turma->curso,
            'horarios' => $horarios,
            'horario_config' => $config,
            'ano_lectivo_label' => $anoLabel,
            'instituicao' => $instituicao,
            'gerado_em' => Carbon::now(),
            'classe_label' => ($turma->numeroClasse() ?: $turma->classe?->nome ?? '-').'ª CLASSE',
            'turma_label' => 'TURMA '.mb_strtoupper($turma->nome),
            'sala_label' => 'SALA '.mb_strtoupper(preg_replace('/\D/', '', $turma->sala?->nome ?? '') ?: ($turma->sala?->nome ?? '-')),
            'turno_label' => mb_strtoupper($turma->turno?->nome ?? '-'),
            'curso_label' => mb_strtoupper($turma->curso?->nome ?? '-'),
        ]);
    }

    /**
     * @param  Collection<int, \App\Models\CalendarioProva>  $provas
     * @return array<string, mixed>
     */
    public function prepararDadosPdfCalendarioProvas(Turma $turma, Collection $provas, ?int $anoLectivoId = null): array
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $turma->load(['curso', 'classe', 'sala', 'turno']);

        $provas = $provas->loadMissing('disciplina');
        $config = TurmaCalendarioProvaConfig::paraTurma($turma->id, $anoLectivoId);
        $blocos = $this->montarBlocosCalendarioProvas($provas, $turma->id, $anoLectivoId);

        $anoInicio = $turma->ano_lectivo;
        $anoLabel = is_numeric($anoInicio)
            ? "{$anoInicio}/".((int) $anoInicio + 1)
            : Configuracao::getAnoLectivoActual();

        $instituicao = app(ConfiguracaoService::class)->getInstituicao();
        $numClasse = $turma->numeroClasse() ?: preg_replace('/\D/', '', $turma->classe?->nome ?? '') ?: '-';

        return [
            'turma' => $turma,
            'provas' => $provas,
            'blocos' => $blocos,
            'calendario_config' => $config,
            'ano_lectivo_label' => $anoLabel,
            'instituicao' => $instituicao,
            'gerado_em' => Carbon::now(),
            'classe_label' => $numClasse.'ª CLASSE',
            'classe_titulo' => 'CALENDÁRIO '.$numClasse.'ª CLASSE',
            'turma_label' => mb_strtoupper($turma->nome),
            'sala_label' => 'SALA: '.mb_strtoupper(preg_replace('/\D/', '', $turma->sala?->nome ?? '') ?: ($turma->sala?->nome ?? '-')),
            'turno_label' => 'PERÍODO DA '.mb_strtoupper($turma->turnoNomeResolvido()),
            'curso_label' => 'CURSO: '.mb_strtoupper($turma->curso?->nome ?? '-'),
        ];
    }

    /**
     * Grelha tipo institucional: até 10 dias úteis por bloco (2 semanas).
     *
     * @param  Collection<int, \App\Models\CalendarioProva>  $provas
     * @return array<int, array{colunas: array<int, array<string, string>>}>
     */
    public function montarBlocosCalendarioProvas(Collection $provas, int $turmaId, int $anoLectivoId): array
    {
        $diasSemanaCurtos = [
            1 => '2ª Feira',
            2 => '3ª Feira',
            3 => '4ª Feira',
            4 => '5ª Feira',
            5 => '6ª Feira',
            6 => 'Sábado',
        ];

        $porData = $provas
            ->sortBy(fn ($p) => $p->data_prova->format('Y-m-d').'-'.($p->slot ?? 1))
            ->groupBy(fn ($p) => $p->data_prova->format('Y-m-d'));

        $colunas = [];
        foreach ($porData->keys()->sort() as $dataStr) {
            $data = Carbon::parse($dataStr);
            $itens = $porData[$dataStr];

            $slot1 = $itens->first(fn ($p) => (int) ($p->slot ?? 1) === 1) ?? $itens->first();
            $slot2 = $itens->first(fn ($p) => (int) ($p->slot ?? 1) === 2);

            $colunas[] = [
                'dia_semana' => $diasSemanaCurtos[$data->dayOfWeekIso] ?? $data->translatedFormat('D'),
                'dia' => $data->format('d'),
                'prova_1' => $slot1 ? $slot1->nomeDisciplinaCalendario() : '',
                'supervisor_1' => $slot1?->supervisor_nome ?? '',
                'prova_2' => $slot2 ? $slot2->nomeDisciplinaCalendario() : '',
                'supervisor_2' => $slot2?->supervisor_nome ?? '',
            ];
        }

        if ($colunas === []) {
            return [];
        }

        $blocos = [];
        foreach (array_chunk($colunas, 10) as $chunk) {
            while (count($chunk) < 10) {
                $chunk[] = [
                    'dia_semana' => '',
                    'dia' => '',
                    'prova_1' => '',
                    'supervisor_1' => '',
                    'prova_2' => '',
                    'supervisor_2' => '',
                ];
            }
            $blocos[] = ['colunas' => $chunk];
        }

        return $blocos;
    }

    protected function deveInserirIntervalo(?TurmaHorarioConfig $config, int $numeroPeriodo, int $totalPeriodos): bool
    {
        if (! $config || ! $config->intervalo_activo) {
            return false;
        }

        $apos = max(1, (int) $config->intervalo_apos_periodo);

        return $numeroPeriodo === $apos && $totalPeriodos > $apos;
    }

    protected function formatarHoraPdf(string $inicio, string $fim): string
    {
        $fmt = fn (string $h) => str_replace(':', 'H:', substr($h, 0, 5));

        return $fmt($inicio).' – '.$fmt($fim);
    }
}
