<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\AlunoAvaliacaoDiaria;
use App\Models\AlunoPresenca;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AvaliacaoDiariaController extends Controller
{
    private const CRITERIOS = [
        'participacao',
        'empenho',
        'comportamento',
        'realizacao_actividades',
        'compreensao',
    ];

    public function __construct()
    {
        $this->middleware('can:presencas.lancar');
    }

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'turma_id' => ['nullable', 'integer', 'min:1'],
            'disciplina_id' => ['nullable', 'integer', 'min:1'],
            'data' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $anoLectivo = Configuracao::getAnoLectivoActual();
        $associacoes = $this->associacoesActivas($user, $anoLectivoId, $anoLectivo);

        $turmaIds = (clone $associacoes)->distinct()->pluck('turma_id');
        $turmas = Turma::query()
            ->whereIn('id', $turmaIds)
            ->with('curso')
            ->orderBy('nome')
            ->get();

        $turmaId = isset($filtros['turma_id']) ? (int) $filtros['turma_id'] : null;
        $disciplinaId = isset($filtros['disciplina_id']) ? (int) $filtros['disciplina_id'] : null;
        $turma = null;
        $disciplinas = collect();
        $disciplina = null;
        $alunos = collect();
        $data = $filtros['data'] ?? today()->toDateString();

        if ($turmaId) {
            abort_unless($turmas->contains('id', $turmaId), 403, 'Não está associado a esta turma.');
            $turma = $turmas->firstWhere('id', $turmaId);

            $disciplinaIds = (clone $associacoes)
                ->where('turma_id', $turmaId)
                ->pluck('disciplina_id');
            $disciplinas = Disciplina::query()
                ->where('activo', true)
                ->whereIn('id', $disciplinaIds)
                ->orderBy('nome')
                ->get();
        }

        if ($disciplinaId) {
            abort_unless(
                $turmaId && $disciplinas->contains('id', $disciplinaId),
                403,
                'Não está associado a esta disciplina nesta turma.'
            );
            $disciplina = $disciplinas->firstWhere('id', $disciplinaId);

            $alunos = Aluno::query()
                ->where('turma_id', $turmaId)
                ->where('activo', true)
                ->matriculados()
                ->orderBy('numero_ordem')
                ->orderBy('nome')
                ->get();

            $presencas = AlunoPresenca::query()
                ->where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->whereDate('data', $data)
                ->get()
                ->keyBy('aluno_id');
            $avaliacoes = AlunoAvaliacaoDiaria::query()
                ->where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->whereDate('data', $data)
                ->get()
                ->keyBy('aluno_id');
        } else {
            $presencas = collect();
            $avaliacoes = collect();
        }

        return view('avaliacao-diaria.index', compact(
            'turmas',
            'disciplinas',
            'turma',
            'disciplina',
            'turmaId',
            'disciplinaId',
            'data',
            'alunos',
            'presencas',
            'avaliacoes'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'turma_id' => ['required', 'integer', 'exists:turmas,id'],
            'disciplina_id' => ['required', 'integer', 'exists:disciplinas,id'],
            'data' => ['required', 'date_format:Y-m-d'],
            'presencas' => ['sometimes', 'array'],
            'presencas.*' => ['array'],
            'presencas.*.estado' => ['nullable', Rule::in(array_keys(AlunoPresenca::ESTADOS))],
            'presencas.*.observacao' => ['nullable', 'string', 'max:500'],
            'avaliacoes' => ['sometimes', 'array'],
            'avaliacoes.*' => ['array'],
            'avaliacoes.*.participacao' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'avaliacoes.*.empenho' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'avaliacoes.*.comportamento' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'avaliacoes.*.realizacao_actividades' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'avaliacoes.*.compreensao' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'avaliacoes.*.observacao_geral' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $turmaId = (int) $validated['turma_id'];
        $disciplinaId = (int) $validated['disciplina_id'];
        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $anoLectivo = Configuracao::getAnoLectivoActual();

        abort_unless(
            $this->associacoesActivas($user, $anoLectivoId, $anoLectivo)
                ->where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->exists(),
            403,
            'Não está associado a esta disciplina nesta turma.'
        );

        $alunosIds = Aluno::query()
            ->where('turma_id', $turmaId)
            ->where('activo', true)
            ->matriculados()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values()
            ->all();
        $presencas = $validated['presencas'] ?? [];
        $avaliacoes = $validated['avaliacoes'] ?? [];
        $idsEnviados = collect(array_merge(array_keys($presencas), array_keys($avaliacoes)))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (array_diff($idsEnviados, $alunosIds) !== []) {
            throw ValidationException::withMessages([
                'presencas' => 'O registo contém alunos que não pertencem a esta turma.',
            ]);
        }

        DB::transaction(function () use ($validated, $presencas, $avaliacoes, $idsEnviados, $user, $turmaId, $disciplinaId) {
            foreach ($idsEnviados as $alunoId) {
                $chave = [
                    'aluno_id' => $alunoId,
                    'turma_id' => $turmaId,
                    'disciplina_id' => $disciplinaId,
                    'data' => $validated['data'],
                ];
                $presenca = $presencas[$alunoId] ?? null;
                $presencaRegistada = AlunoPresenca::query()
                    ->where($this->semData($chave))
                    ->whereDate('data', $validated['data'])
                    ->first();

                if ($presenca !== null) {
                    $temPresenca = filled($presenca['estado'] ?? null)
                        || filled($presenca['observacao'] ?? null);
                    if ($temPresenca) {
                        $presencaRegistada ??= new AlunoPresenca($chave);
                        $presencaRegistada->fill([
                            'professor_id' => $user->id,
                            'estado' => $presenca['estado'] ?? null,
                            'observacao' => $presenca['observacao'] ?? null,
                        ])->save();
                    } else {
                        $presencaRegistada?->delete();
                    }
                }

                if (! array_key_exists($alunoId, $avaliacoes)) {
                    continue;
                }

                $avaliacao = $avaliacoes[$alunoId];
                $temDados = collect(self::CRITERIOS)->contains(
                    fn ($criterio) => filled($avaliacao[$criterio] ?? null)
                ) || filled($avaliacao['observacao_geral'] ?? null);
                $avaliacaoRegistada = AlunoAvaliacaoDiaria::query()
                    ->where($this->semData($chave))
                    ->whereDate('data', $validated['data'])
                    ->first();

                if ($temDados) {
                    $avaliacaoRegistada ??= new AlunoAvaliacaoDiaria($chave);
                    $avaliacaoRegistada->fill([
                        'professor_id' => $user->id,
                        ...collect(self::CRITERIOS)
                            ->mapWithKeys(fn ($criterio) => [$criterio => $avaliacao[$criterio] ?? null])
                            ->all(),
                        'observacao_geral' => $avaliacao['observacao_geral'] ?? null,
                    ])->save();
                } else {
                    $avaliacaoRegistada?->delete();
                }
            }
        });

        return redirect()
            ->route('avaliacao-diaria.index', [
                'turma_id' => $turmaId,
                'disciplina_id' => $disciplinaId,
                'data' => $validated['data'],
            ])
            ->with('success', 'Presenças e avaliações diárias guardadas com sucesso.');
    }

    private function semData(array $chave): array
    {
        unset($chave['data']);

        return $chave;
    }

    private function associacoesActivas(User $user, ?int $anoLectivoId, ?string $anoLectivo): Builder
    {
        return ProfessorDisciplinaTurma::query()
            ->where('user_id', $user->id)
            ->where('activo', true)
            ->where(function ($query) use ($anoLectivoId, $anoLectivo) {
                if ($anoLectivoId) {
                    $query->where('ano_lectivo_id', $anoLectivoId);
                    if ($anoLectivo) {
                        $query->orWhere('ano_lectivo', $anoLectivo);
                    }
                } elseif ($anoLectivo) {
                    $query->where('ano_lectivo', $anoLectivo);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->whereHas('turma', function ($query) use ($anoLectivoId, $anoLectivo) {
                $query->where('activo', true);
                if ($anoLectivoId) {
                    $query->where('ano_lectivo_id', $anoLectivoId);
                } elseif ($anoLectivo) {
                    $query->where('ano_lectivo', $anoLectivo);
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
    }
}
