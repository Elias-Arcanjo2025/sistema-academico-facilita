<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesNotasTurma;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Turma;
use App\Services\PautaService;
use App\Support\NotaLancamentoRules;
use App\Support\TurmaExameRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Lançamento manual de CAs e CFDs de anos anteriores (escolas sem histórico no sistema).
 *
 * Disciplinas contínuas (ex.: LP 10.ª–11.ª, MAT 10.ª–12.ª): lançar CA-10ª / CA-11ª.
 * Disciplinas terminais solo-classe (ex.: TIC 10.ª–10.ª, FAI 11.ª–11.ª): lançar CFD directamente.
 */
class CfdAnteriorController extends Controller
{
    use AuthorizesNotasTurma;

    public function index(Request $request): View|RedirectResponse
    {
        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());

        if ($request->filled('turma_id')) {
            $redirect = TurmaExameRedirect::paraLancamento(
                $request,
                'cfds-anteriores.editar',
                [11, 12],
            );
            if ($redirect) {
                return $redirect;
            }
        }

        $turmas = Turma::with(['classe', 'curso'])
            ->whereHas('classe', function ($q) {
                $q->where('codigo', 'like', '11%')
                  ->orWhere('codigo', 'like', '12%')
                  ->orWhere('nome', 'like', '11%')
                  ->orWhere('nome', 'like', '12%');
            })
            ->where('activo', true)
            ->orderBy('nome')
            ->get();

        return view('cfds-anteriores.index', compact('turmas', 'anoLectivo'));
    }

    public function editar(Request $request, Turma $turma): View
    {
        $this->assertAcessoTurma($turma);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);
        $classeNumero = self::classeNumeroTurma($turma);
        $podeEditar = Configuracao::podeEditarAnoLectivoById($anoLectivoId ? (int) $anoLectivoId : null);

        $alunos = NotaLancamentoRules::queryAlunosDaTurmaParaNotas(
            (int) $turma->id,
            $anoLectivoId ? (int) $anoLectivoId : (int) $turma->ano_lectivo_id,
        )->get();

        if ($classeNumero < 11) {
            return view('cfds-anteriores.editar', [
                'turma' => $turma,
                'anoLectivo' => $anoLectivo,
                'gruposCa' => [],
                'gruposCfd' => [],
                'alunos' => $alunos,
                'casAnteriores' => [],
                'cfdsAnteriores' => [],
                'podeEditar' => $podeEditar,
                'aviso' => 'Esta turma é da 10.ª classe — não precisa de CAs do ano anterior.',
            ]);
        }

        $rowsCas = PautaService::disciplinasParaCasAnteriores($turma->curso_id, $classeNumero);
        $rowsCfd = PautaService::disciplinasParaCfdsAnteriores($turma->curso_id, $classeNumero);
        $gruposCa = self::montarGruposCa($rowsCas, $classeNumero, $anoLectivoId);
        $gruposCfd = self::montarGruposCfd($rowsCfd, $classeNumero, $anoLectivoId);

        $alunosIds = $alunos->pluck('id');
        $casAnteriores = PautaService::carregarCasAnterioresPorDisciplinas(
            $rowsCas,
            $alunosIds,
            $classeNumero,
            $anoLectivoId
        );
        $cfdsAnteriores = PautaService::carregarCfdsAnterioresPorDisciplinas(
            $rowsCfd,
            $alunosIds,
            $classeNumero,
            $anoLectivoId
        );

        $aviso = null;
        if ($gruposCa === [] && $gruposCfd === []) {
            $aviso = 'Não foram encontradas disciplinas que precisem de CAs ou CFDs do ano anterior. '
                . 'Verifique o plano curricular do curso (classe_inicio / classe_fim).';
        }

        return view('cfds-anteriores.editar', compact(
            'turma',
            'anoLectivo',
            'gruposCa',
            'gruposCfd',
            'alunos',
            'casAnteriores',
            'cfdsAnteriores',
            'aviso',
            'podeEditar',
        ));
    }

    public function guardar(Request $request, Turma $turma)
    {
        $this->authorizeLancamentoNotasTurma($turma);

        $anoLectivo = $request->input('ano_lectivo', Configuracao::getAnoLectivoActual());
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);

        if (! Configuracao::podeEditarAnoLectivoById($anoLectivoId ? (int) $anoLectivoId : null)) {
            return back()->with('error', 'Apenas o administrador pode alterar CAs/CFDs de anos lectivos anteriores.');
        }

        $classeNumero = self::classeNumeroTurma($turma);

        $validated = $request->validate([
            'cas_anteriores' => ['nullable', 'array'],
            'cas_anteriores.*' => ['nullable', 'array'],
            'cas_anteriores.*.*' => ['nullable', 'array'],
            'cas_anteriores.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'cfds_terminais' => ['nullable', 'array'],
            'cfds_terminais.*' => ['nullable', 'array'],
            'cfds_terminais.*.*' => ['nullable', 'array'],
            'cfds_terminais.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        $alunosIds = NotaLancamentoRules::queryAlunosDaTurmaParaNotas(
            (int) $turma->id,
            $anoLectivoId ? (int) $anoLectivoId : (int) $turma->ano_lectivo_id,
        )->pluck('id')->all();
        $rowsCas = PautaService::disciplinasParaCasAnteriores($turma->curso_id, $classeNumero);
        $rowsCfd = PautaService::disciplinasParaCfdsAnteriores($turma->curso_id, $classeNumero);
        $discsPorTipoCa = self::disciplinasPorTipoCa($rowsCas);
        $discsPorClasseTerminal = self::disciplinasPorClasseTerminal($rowsCfd);

        $criadas = 0;
        $atualizadas = 0;
        $apagadas = 0;
        $valoresSubmetidos = 0;

        DB::transaction(function () use (
            $validated,
            $alunosIds,
            $turma,
            $anoLectivoId,
            $classeNumero,
            $discsPorTipoCa,
            $discsPorClasseTerminal,
            &$criadas,
            &$atualizadas,
            &$apagadas,
            &$valoresSubmetidos
        ) {
            foreach ($validated['cas_anteriores'] ?? [] as $tipoCa => $porAluno) {
                if (! in_array($tipoCa, [Nota::TIPO_CA10, Nota::TIPO_CA11], true)) {
                    continue;
                }

                $discsValidas = $discsPorTipoCa[$tipoCa] ?? [];
                $classeCa = (int) substr($tipoCa, 2);
                $anoCaId = PautaService::anoLectivoIdParaCaHistorico($classeNumero, $classeCa, $anoLectivoId);

                if (! $anoCaId) {
                    continue;
                }

                foreach ($porAluno as $alunoId => $casDisc) {
                    if (! in_array((int) $alunoId, $alunosIds, true)) {
                        continue;
                    }

                    foreach ($casDisc as $discId => $valor) {
                        if (! in_array((int) $discId, $discsValidas, true)) {
                            continue;
                        }

                        if ($valor !== null && $valor !== '') {
                            $valoresSubmetidos++;
                        }

                        $stats = self::persistirCa(
                            (int) $alunoId,
                            (int) $discId,
                            $turma->id,
                            $anoCaId,
                            $tipoCa,
                            $valor
                        );
                        $criadas += $stats['criadas'];
                        $atualizadas += $stats['actualizadas'];
                        $apagadas += $stats['apagadas'];
                    }
                }
            }

            foreach ($validated['cfds_terminais'] ?? [] as $classeTerminal => $porAluno) {
                $classeTerm = (int) $classeTerminal;
                $discsValidas = $discsPorClasseTerminal[$classeTerm] ?? [];
                $anoCfdId = PautaService::anoLectivoIdParaCaHistorico($classeNumero, $classeTerm, $anoLectivoId);

                if (! $anoCfdId || $discsValidas === []) {
                    continue;
                }

                foreach ($porAluno as $alunoId => $cfdsDisc) {
                    if (! in_array((int) $alunoId, $alunosIds, true)) {
                        continue;
                    }

                    foreach ($cfdsDisc as $discId => $valor) {
                        if (! in_array((int) $discId, $discsValidas, true)) {
                            continue;
                        }

                        if ($valor !== null && $valor !== '') {
                            $valoresSubmetidos++;
                        }

                        $stats = self::persistirCfdTerminal(
                            (int) $alunoId,
                            (int) $discId,
                            $turma->id,
                            $anoCfdId,
                            $anoLectivoId,
                            $classeTerm,
                            $valor
                        );
                        $criadas += $stats['criadas'];
                        $atualizadas += $stats['actualizadas'];
                        $apagadas += $stats['apagadas'];
                    }
                }
            }

            // Após lançar CAs, calcular e gravar CFD das disciplinas multi-ano
            // já terminadas (ex.: LP 10.ª–11.ª → (CA10+CA11)/2) para a pauta 12.ª.
            self::sincronizarCfdTerminaisMultiAno(
                $turma,
                $anoLectivoId,
                $classeNumero,
                $alunosIds,
                $criadas,
                $atualizadas,
                $apagadas,
            );
        });

        if ($valoresSubmetidos > 0 && $criadas === 0 && $atualizadas === 0) {
            return redirect()
                ->route('cfds-anteriores.editar', ['turma' => $turma, 'ano_lectivo' => $anoLectivo])
                ->with('error', 'Nenhuma nota foi guardada. Verifique o ano lectivo actual e o plano curricular da turma.');
        }

        return redirect()
            ->route('cfds-anteriores.editar', ['turma' => $turma, 'ano_lectivo' => $anoLectivo])
            ->with('success', "Notas guardadas com sucesso. Criadas: {$criadas} | Atualizadas: {$atualizadas} | Apagadas: {$apagadas}.");
    }

    /**
     * Calcula e persiste CFD carry-over para disciplinas multi-ano já terminadas
     * (ex.: LP 10.ª–11.ª) a partir das CAs lançadas.
     *
     * @param  array<int>  $alunosIds
     */
    private static function sincronizarCfdTerminaisMultiAno(
        Turma $turma,
        int $anoLectivoId,
        int $classeNumero,
        array $alunosIds,
        int &$criadas,
        int &$atualizadas,
        int &$apagadas,
    ): void {
        $terminais = PautaService::disciplinasTerminaisAnteriores($turma->curso_id, $classeNumero);
        if ($terminais->isEmpty() || $alunosIds === []) {
            return;
        }

        $turma->loadMissing('curso');
        $cursoClasseMax = (int) ($turma->curso?->classe_max ?? 12);
        $multiAno = $terminais
            ->map(fn ($r) => [
                'disciplina_id' => (int) $r->disciplina_id,
                'classe_inicio' => (int) ($r->classe_inicio ?? $r->classe_numero),
                'classe_numero' => (int) $r->classe_numero,
            ])
            ->filter(function (array $dt) use ($cursoClasseMax) {
                $inicio = $dt['classe_inicio'];
                $fim = $dt['classe_numero'];

                return ! PautaService::cfdIgualCaDoAno($inicio, $fim, $fim, $cursoClasseMax);
            })
            ->values();

        if ($multiAno->isEmpty()) {
            return;
        }

        $cas = PautaService::carregarCasHistoricasTerminais(
            $multiAno,
            collect($alunosIds),
            $classeNumero,
            $anoLectivoId,
        );

        foreach ($multiAno as $dt) {
            $discId = $dt['disciplina_id'];
            $fim = $dt['classe_numero'];
            $anoCfdId = PautaService::anoLectivoIdParaCaHistorico($classeNumero, $fim, $anoLectivoId);
            if (! $anoCfdId) {
                continue;
            }

            foreach ($alunosIds as $alunoId) {
                $alunoCas = $cas[$alunoId][$discId] ?? [];
                $cfd = PautaService::calcularCfdDeCasPorIntervalo(
                    $dt['classe_inicio'],
                    $fim,
                    isset($alunoCas['ca10']) ? (float) $alunoCas['ca10'] : null,
                    isset($alunoCas['ca11']) ? (float) $alunoCas['ca11'] : null,
                    isset($alunoCas['ca12']) ? (float) $alunoCas['ca12'] : null,
                );

                $stats = self::persistirCfd(
                    (int) $alunoId,
                    $discId,
                    $turma->id,
                    $anoCfdId,
                    $cfd,
                    'CFD calculada a partir das CAs (carry-over)'
                );
                $criadas += $stats['criadas'];
                $atualizadas += $stats['actualizadas'];
                $apagadas += $stats['apagadas'];

                // Replica no ano actual para as colunas CFD da pauta 12.ª
                if ($anoCfdId !== $anoLectivoId) {
                    $statsLegado = self::persistirCfd(
                        (int) $alunoId,
                        $discId,
                        $turma->id,
                        $anoLectivoId,
                        $cfd,
                        'CFD calculada a partir das CAs (carry-over pauta)'
                    );
                    $criadas += $statsLegado['criadas'];
                    $atualizadas += $statsLegado['actualizadas'];
                    $apagadas += $statsLegado['apagadas'];
                }
            }
        }
    }

    private static function classeNumeroTurma(Turma $turma): int
    {
        return (int) preg_replace('/\D/', '', $turma->classe?->codigo ?? $turma->classe?->nome ?? '');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int, tipos_ca:array<int, string>}>  $rowsCas
     * @return array<int, array<string, mixed>>
     */
    private static function montarGruposCa($rowsCas, int $classeNumero, ?int $anoLectivoIdTurma): array
    {
        $grupos = [];

        $tipos = $classeNumero >= 12
            ? [Nota::TIPO_CA10 => 'CA-10ª', Nota::TIPO_CA11 => 'CA-11ª']
            : [Nota::TIPO_CA10 => 'CA-10ª'];

        foreach ($tipos as $tipo => $titulo) {
            $discIds = $rowsCas
                ->filter(fn ($r) => in_array($tipo, $r->tipos_ca ?? [], true))
                ->pluck('disciplina_id')
                ->all();

            if ($discIds === []) {
                continue;
            }

            $disciplinas = Disciplina::whereIn('id', $discIds)
                ->ordenadaPorTipo()
                ->get()
                ->map(function ($d) use ($rowsCas) {
                    $row = $rowsCas->firstWhere('disciplina_id', $d->id);
                    $d->classe_inicio_curso = (int) ($row->classe_inicio ?? 0);
                    $d->classe_fim_curso = (int) ($row->classe_fim ?? 0);

                    return $d;
                })
                ->values();

            $classeCa = (int) substr($tipo, 2);
            $anoNome = null;
            if ($anoLectivoIdTurma && ($anoId = PautaService::anoLectivoIdParaCaHistorico($classeNumero, $classeCa, $anoLectivoIdTurma))) {
                $anoNome = AnoLectivo::getNomeById($anoId);
            }

            $grupos[] = [
                'tipo' => $tipo,
                'titulo' => $titulo,
                'ano_referencia' => $anoNome,
                'disciplinas' => $disciplinas,
                'corFundo' => $tipo === Nota::TIPO_CA10 ? '#E8F8EE' : '#E8F4FC',
            ];
        }

        return $grupos;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int, classe_terminal:int}>  $rowsCfd
     * @return array<int, array<string, mixed>>
     */
    private static function montarGruposCfd($rowsCfd, int $classeNumero, ?int $anoLectivoIdTurma): array
    {
        $grupos = [];

        foreach ($rowsCfd->groupBy('classe_terminal')->sortKeys() as $classeTerm => $rows) {
            $discIds = $rows->pluck('disciplina_id')->all();

            if ($discIds === []) {
                continue;
            }

            $disciplinas = Disciplina::whereIn('id', $discIds)
                ->ordenadaPorTipo()
                ->get()
                ->map(function ($d) use ($rows) {
                    $row = $rows->firstWhere('disciplina_id', $d->id);
                    $d->classe_inicio_curso = (int) ($row->classe_inicio ?? 0);
                    $d->classe_fim_curso = (int) ($row->classe_fim ?? 0);

                    return $d;
                })
                ->values();

            $classeTerminal = (int) $classeTerm;
            $anoNome = null;
            if ($anoLectivoIdTurma && ($anoId = PautaService::anoLectivoIdParaCaHistorico($classeNumero, $classeTerminal, $anoLectivoIdTurma))) {
                $anoNome = AnoLectivo::getNomeById($anoId);
            }

            $grupos[] = [
                'classe_terminal' => $classeTerminal,
                'titulo' => 'CFD — terminais na '.$classeTerminal.'ª',
                'ano_referencia' => $anoNome,
                'disciplinas' => $disciplinas,
                'corFundo' => '#C6EFCE',
            ];
        }

        return $grupos;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int, tipos_ca:array<int, string>}>  $rowsCas
     * @return array<string, array<int, int>>
     */
    private static function disciplinasPorTipoCa($rowsCas): array
    {
        $map = [Nota::TIPO_CA10 => [], Nota::TIPO_CA11 => []];

        foreach ($rowsCas as $row) {
            foreach ($row->tipos_ca ?? [] as $tipo) {
                $map[$tipo][] = (int) $row->disciplina_id;
            }
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{disciplina_id:int, classe_terminal:int}>  $rowsCfd
     * @return array<int, array<int, int>>
     */
    private static function disciplinasPorClasseTerminal($rowsCfd): array
    {
        $map = [];

        foreach ($rowsCfd as $row) {
            $classe = (int) ($row->classe_terminal ?? 0);
            $map[$classe][] = (int) $row->disciplina_id;
        }

        return $map;
    }

    /**
     * Grava CFD de disciplina terminal solo-classe e replica no ano actual (colunas carry-over).
     *
     * @return array{criadas:int, actualizadas:int, apagadas:int}
     */
    private static function persistirCfdTerminal(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $anoNotaId,
        ?int $anoLectivoIdTurma,
        int $classeTerminal,
        mixed $valor
    ): array {
        $tipoCa = PautaService::tipoCaParaClasse($classeTerminal);

        $stats = self::persistirCfd(
            $alunoId,
            $disciplinaId,
            $turmaId,
            $anoNotaId,
            $valor,
            'CFD terminal (lançamento manual)'
        );

        if ($valor !== null && $valor !== '') {
            $caExistente = Nota::where('aluno_id', $alunoId)
                ->where('disciplina_id', $disciplinaId)
                ->where('tipo', $tipoCa)
                ->where('ano_lectivo_id', $anoNotaId)
                ->first();

            if ($caExistente) {
                $caExistente->delete();
                $stats['apagadas']++;
            }
        }

        if ($anoLectivoIdTurma && $anoLectivoIdTurma !== $anoNotaId) {
            $statsLegado = self::persistirCfd(
                $alunoId,
                $disciplinaId,
                $turmaId,
                $anoLectivoIdTurma,
                $valor,
                'CFD terminal (carry-over pauta)'
            );
            $stats['criadas'] += $statsLegado['criadas'];
            $stats['actualizadas'] += $statsLegado['actualizadas'];
            $stats['apagadas'] += $statsLegado['apagadas'];
        }

        return $stats;
    }

    /**
     * @return array{criadas:int, actualizadas:int, apagadas:int}
     */
    private static function persistirCa(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $anoLectivoId,
        string $tipoCa,
        mixed $valor
    ): array {
        $stats = ['criadas' => 0, 'actualizadas' => 0, 'apagadas' => 0];

        $existing = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('tipo', $tipoCa)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->first();

        if ($valor === null || $valor === '') {
            if ($existing) {
                $existing->delete();
                $stats['apagadas']++;
            }

            return $stats;
        }

        if ($existing) {
            $existing->valor = (float) $valor;
            $existing->save();
            $stats['actualizadas']++;

            return $stats;
        }

        Nota::create([
            'aluno_id' => $alunoId,
            'disciplina_id' => $disciplinaId,
            'turma_id' => $turmaId,
            'ano_lectivo' => AnoLectivo::find($anoLectivoId)?->nome,
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => null,
            'tipo' => $tipoCa,
            'valor' => (float) $valor,
            'professor_id' => auth()->id(),
        ]);
        $stats['criadas']++;

        return $stats;
    }

    /**
     * @return array{criadas:int, actualizadas:int, apagadas:int}
     */
    private static function persistirCfd(
        int $alunoId,
        int $disciplinaId,
        int $turmaId,
        int $anoLectivoId,
        mixed $valor,
        ?string $observacoes = null
    ): array {
        $stats = ['criadas' => 0, 'actualizadas' => 0, 'apagadas' => 0];

        $existing = Nota::where('aluno_id', $alunoId)
            ->where('disciplina_id', $disciplinaId)
            ->where('tipo', Nota::TIPO_CFD)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->first();

        if ($valor === null || $valor === '') {
            if ($existing) {
                $existing->delete();
                $stats['apagadas']++;
            }

            return $stats;
        }

        if ($existing) {
            $existing->valor = (float) $valor;
            $existing->save();
            $stats['actualizadas']++;

            return $stats;
        }

        Nota::create([
            'aluno_id' => $alunoId,
            'disciplina_id' => $disciplinaId,
            'turma_id' => $turmaId,
            'ano_lectivo' => AnoLectivo::find($anoLectivoId)?->nome,
            'ano_lectivo_id' => $anoLectivoId,
            'trimestre' => null,
            'tipo' => Nota::TIPO_CFD,
            'valor' => (float) $valor,
            'professor_id' => auth()->id(),
            'observacoes' => $observacoes,
        ]);
        $stats['criadas']++;

        return $stats;
    }
}
