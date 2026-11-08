<?php

namespace App\Http\Controllers;

use App\Http\Requests\CursoRequest;
use App\Models\Curso;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CursoController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Curso::class);

        $cursos = Curso::withCount(['disciplinas', 'turmas'])
            ->orderBy('nome')
            ->paginate(10);

        return view('cursos.index', compact('cursos'));
    }

    public function create(): View
    {
        $this->authorize('create', Curso::class);

        return view('cursos.create');
    }

    public function store(CursoRequest $request): RedirectResponse
    {
        $this->authorize('create', Curso::class);

        $curso = Curso::create($request->validated());

        return redirect()
            ->route('cursos.index')
            ->with('success', 'Curso criado com sucesso.');
    }

    public function show(Curso $curso): View
    {
        $this->authorize('view', $curso);

        $curso->load(['disciplinas', 'turmas']);

        return view('cursos.show', compact('curso'));
    }

    public function edit(Curso $curso): View
    {
        $this->authorize('update', $curso);

        // Carregar disciplinas com a pivot (classe_inicio, classe_fim, permite_pg)
        $curso->load(['disciplinas' => fn ($q) => $q->orderBy('nome')]);

        return view('cursos.edit', compact('curso'));
    }

    public function update(CursoRequest $request, Curso $curso): RedirectResponse
    {
        $this->authorize('update', $curso);

        $curso->update($request->validated());

        return redirect()
            ->route('cursos.index')
            ->with('success', 'Curso actualizado com sucesso.');
    }

    /**
     * Actualiza, em massa, os campos da pivot curso_disciplina
     * (classe_inicio, classe_fim, permite_pg) para todas as disciplinas do curso.
     */
    public function actualizarDisciplinas(Request $request, Curso $curso): RedirectResponse
    {
        $this->authorize('update', $curso);

        $classeMax = $curso->classe_max ?? 13;

        $validated = $request->validate([
            'disciplinas' => ['nullable', 'array'],
            'disciplinas.*.classe_inicio' => ['nullable', 'integer', 'min:7', 'max:13'],
            'disciplinas.*.classe_fim'    => ['nullable', 'integer', 'min:7', 'max:13'],
            'disciplinas.*.permite_pg'    => ['nullable', 'boolean'],
            'disciplinas.*.blindada'      => ['nullable', 'boolean'],
        ], [
            'disciplinas.*.classe_inicio.min' => 'classe_inicio deve ser ≥ 7.',
            'disciplinas.*.classe_fim.max'    => 'classe_fim deve ser ≤ 13.',
        ]);

        $disciplinasInput = $validated['disciplinas'] ?? [];
        $alteradas = 0;

        DB::transaction(function () use ($disciplinasInput, $curso, $classeMax, &$alteradas) {
            $discsCurso = $curso->disciplinas()->pluck('disciplinas.id')->all();

            foreach ($disciplinasInput as $discId => $cfg) {
                if (!in_array((int) $discId, $discsCurso, true)) continue;

                $cinicio  = $cfg['classe_inicio'] ?? null;
                $cfim     = $cfg['classe_fim'] ?? null;
                $pg       = isset($cfg['permite_pg']) ? (bool) $cfg['permite_pg'] : true;
                $blindada = isset($cfg['blindada']) ? (bool) $cfg['blindada'] : false;

                // Coerência: classe_fim >= classe_inicio (quando ambos definidos)
                if ($cinicio !== null && $cfim !== null && $cfim < $cinicio) {
                    $cfim = $cinicio;
                }
                // Coerência: classe_fim ≤ classe_max do curso
                if ($cfim !== null && $cfim > $classeMax) {
                    $cfim = $classeMax;
                }

                $curso->disciplinas()->updateExistingPivot($discId, [
                    'classe_inicio' => $cinicio,
                    'classe_fim'    => $cfim,
                    'permite_pg'    => $pg,
                    'blindada'      => $blindada,
                ]);
                $alteradas++;
            }
        });

        return redirect()
            ->route('cursos.edit', $curso)
            ->with('success', "Configuração de {$alteradas} disciplina(s) actualizada com sucesso.");
    }

    public function destroy(Curso $curso): RedirectResponse
    {
        $this->authorize('delete', $curso);

        if ($curso->turmas()->exists()) {
            return redirect()
                ->route('cursos.index')
                ->with('error', 'Não é possível eliminar este curso porque tem turmas associadas.');
        }

        $curso->delete();

        return redirect()
            ->route('cursos.index')
            ->with('success', 'Curso eliminado com sucesso.');
    }
}
