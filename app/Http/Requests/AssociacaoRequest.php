<?php

namespace App\Http\Requests;

use App\Models\ProfessorDisciplinaTurma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gestão de associações professor-disciplina-turma
        return $this->user()->can('associacoes.gerir');
    }

    public function rules(): array
    {
        $associacaoId = $this->route('associacao')?->id;

        // Para edição, usa turma_id singular
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'user_id' => ['required', 'exists:users,id'],
                'disciplina_id' => ['required', 'exists:disciplinas,id'],
                'turma_id' => ['required', 'exists:turmas,id'],
                'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
                'activo' => ['boolean'],
            ];
        }

        // Para criação, usa turma_ids plural (array)
        return [
            'user_id' => ['required', 'exists:users,id'],
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'turma_ids' => ['required', 'array', 'min:1'],
            'turma_ids.*' => ['required', 'exists:turmas,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O professor é obrigatório.',
            'user_id.exists' => 'O professor selecionado não existe.',
            'disciplina_id.required' => 'A disciplina é obrigatória.',
            'disciplina_id.exists' => 'A disciplina selecionada não existe.',
            'turma_id.required' => 'A turma é obrigatória.',
            'turma_id.exists' => 'A turma selecionada não existe.',
            'turma_ids.required' => 'Pelo menos uma turma é obrigatória.',
            'turma_ids.array' => 'As turmas devem ser enviadas como array.',
            'turma_ids.min' => 'Selecione pelo menos uma turma.',
            'turma_ids.*.required' => 'Todas as turmas são obrigatórias.',
            'turma_ids.*.exists' => 'Uma das turmas selecionadas não existe.',
            'ano_lectivo_id.required' => 'O ano lectivo é obrigatório.',
            'ano_lectivo_id.exists' => 'O ano lectivo selecionado não existe.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->any()) {
                $disciplina = \App\Models\Disciplina::find($this->disciplina_id);

                // Para edição (turma_id singular)
                if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                    $turma = \App\Models\Turma::find($this->turma_id);

                    if ($turma && $disciplina) {
                        $pertenceAoCurso = $disciplina->cursos()->where('cursos.id', $turma->curso_id)->exists();
                        if (!$pertenceAoCurso) {
                            $validator->errors()->add('disciplina_id', 'A disciplina deve pertencer ao curso da turma.');
                        }
                    }

                    // Verificar se já existe associação duplicada
                    $associacaoId = $this->route('associacao')?->id;
                    $existe = ProfessorDisciplinaTurma::where('user_id', $this->user_id)
                        ->where('disciplina_id', $this->disciplina_id)
                        ->where('turma_id', $this->turma_id)
                        ->where('ano_lectivo_id', $this->ano_lectivo_id)
                        ->when($associacaoId, fn($q) => $q->where('id', '!=', $associacaoId))
                        ->exists();

                    if ($existe) {
                        $validator->errors()->add('user_id', 'Esta associação já existe para este ano lectivo.');
                    }
                }
                // Para criação (turma_ids plural/array)
                else {
                    $turmaIds = $this->turma_ids ?? [];
                    $turmas = \App\Models\Turma::whereIn('id', $turmaIds)->get();

                    if ($turmas->isNotEmpty() && $disciplina) {
                        // Verificar se todas as turmas são do mesmo curso
                        $cursoIds = $turmas->pluck('curso_id')->unique();
                        if ($cursoIds->count() > 1) {
                            $validator->errors()->add('turma_ids', 'Todas as turmas devem ser do mesmo curso.');
                            return;
                        }

                        // Verificar se a disciplina pertence ao curso
                        $cursoId = $cursoIds->first();
                        $pertenceAoCurso = $disciplina->cursos()->where('cursos.id', $cursoId)->exists();
                        if (!$pertenceAoCurso) {
                            $validator->errors()->add('disciplina_id', 'A disciplina deve pertencer ao curso das turmas.');
                        }

                        // Verificar se já existe associação duplicada para alguma turma
                        foreach ($turmaIds as $turmaId) {
                            $existe = ProfessorDisciplinaTurma::where('user_id', $this->user_id)
                                ->where('disciplina_id', $this->disciplina_id)
                                ->where('turma_id', $turmaId)
                                ->where('ano_lectivo_id', $this->ano_lectivo_id)
                                ->exists();

                            if ($existe) {
                                $turmaNome = $turmas->firstWhere('id', $turmaId)->nome ?? "ID $turmaId";
                                $validator->errors()->add('turma_ids', "Já existe uma associação para a turma {$turmaNome} neste ano lectivo.");
                                break;
                            }
                        }
                    }
                }
            }
        });
    }
}
