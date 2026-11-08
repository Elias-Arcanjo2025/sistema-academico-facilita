<?php

namespace App\Http\Requests;

use App\Models\Nota;
use App\Policies\NotaPolicy;
use App\Support\NotaLancamentoRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $nota = $this->route('nota');

        if ($nota) {
            return $this->user()->can('update', $nota);
        }

        if (! $this->user()->can('notas.lancar')) {
            return false;
        }

        $disciplinaId = (int) $this->input('disciplina_id');
        $turmaId = (int) $this->input('turma_id');

        if (! $disciplinaId || ! $turmaId) {
            return true;
        }

        return app(NotaPolicy::class)->lancar($this->user(), $disciplinaId, $turmaId);
    }

    public function rules(): array
    {
        $turmaId = (int) $this->input('turma_id');

        return [
            'aluno_id' => [
                'required',
                'exists:alunos,id',
                NotaLancamentoRules::ruleAlunoNaTurma($turmaId),
            ],
            'disciplina_id' => [
                'required',
                'exists:disciplinas,id',
                NotaLancamentoRules::ruleDisciplinaNaTurma($turmaId),
            ],
            'turma_id' => ['required', 'exists:turmas,id'],
            'tipo' => ['required', 'string', Rule::in(array_keys(Nota::getTiposActivos()))],
            'valor' => ['required', 'numeric', 'min:0', 'max:20'],
            'trimestre' => ['nullable', 'integer', 'min:1', 'max:3'],
            'ano_lectivo' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'aluno_id.required' => 'O aluno é obrigatório.',
            'aluno_id.exists' => 'O aluno seleccionado não existe.',
            'disciplina_id.required' => 'A disciplina é obrigatória.',
            'disciplina_id.exists' => 'A disciplina seleccionada não existe.',
            'turma_id.required' => 'A turma é obrigatória.',
            'turma_id.exists' => 'A turma seleccionada não existe.',
            'tipo.required' => 'O tipo de nota é obrigatório.',
            'tipo.in' => 'O tipo de nota seleccionado é inválido.',
            'valor.required' => 'O valor da nota é obrigatório.',
            'valor.numeric' => 'O valor da nota deve ser um número.',
            'valor.min' => 'A nota mínima é 0.',
            'valor.max' => 'A nota máxima é 20.',
            'trimestre.min' => 'O trimestre deve ser entre 1 e 3.',
            'trimestre.max' => 'O trimestre deve ser entre 1 e 3.',
        ];
    }
}
