<?php

namespace App\Http\Requests;

use App\Models\Nota;
use App\Policies\NotaPolicy;
use App\Support\NotaLancamentoRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotaLoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notas = $this->input('notas', []);

        if (! is_array($notas)) {
            return;
        }

        foreach ($notas as $alunoId => $valor) {
            if ($valor === '' || $valor === null) {
                $notas[$alunoId] = null;

                continue;
            }

            if (is_string($valor)) {
                $notas[$alunoId] = str_replace(',', '.', trim($valor));
            }
        }

        $this->merge(['notas' => $notas]);
    }

    public function authorize(): bool
    {
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
            'disciplina_id' => [
                'required',
                'exists:disciplinas,id',
                NotaLancamentoRules::ruleDisciplinaNaTurma($turmaId),
            ],
            'turma_id' => ['required', 'exists:turmas,id'],
            'tipo' => ['required', 'string', Rule::in(array_keys(Nota::getTiposActivos()))],
            'trimestre' => ['required', 'integer', 'min:1', 'max:3'],
            'notas' => [
                'required',
                'array',
                NotaLancamentoRules::ruleAlunosLoteNaTurma($turmaId),
            ],
            'notas.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $trimestre = (int) $this->input('trimestre');
            $tipo = $this->input('tipo');

            if ($tipo === Nota::TIPO_PG && $trimestre !== 3) {
                $validator->errors()->add('tipo', 'A Prova Global (PG) só pode ser lançada no 3.º trimestre.');
            }

            if ($tipo === Nota::TIPO_PT && $trimestre === 3) {
                $validator->errors()->add('tipo', 'No 3.º trimestre não existe PT — use a Prova Global (PG).');
            }

            if ($tipo === Nota::TIPO_RECUPERACAO && $trimestre !== 3) {
                $validator->errors()->add('tipo', 'O exame de recuperação só pode ser lançado no 3.º trimestre.');
            }

            if ($tipo === Nota::TIPO_RECURSO && $trimestre !== 3) {
                $validator->errors()->add('tipo', 'O exame de recurso só pode ser lançado no 3.º trimestre.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'disciplina_id.required' => 'A disciplina é obrigatória.',
            'turma_id.required' => 'A turma é obrigatória.',
            'tipo.required' => 'O tipo de nota é obrigatório.',
            'tipo.in' => 'O tipo de nota seleccionado é inválido.',
            'trimestre.required' => 'O trimestre é obrigatório.',
            'trimestre.min' => 'O trimestre deve ser entre 1 e 3.',
            'trimestre.max' => 'O trimestre deve ser entre 1 e 3.',
            'notas.required' => 'As notas são obrigatórias.',
            'notas.array' => 'Formato de notas inválido.',
            'notas.*.numeric' => 'Todas as notas devem ser numéricas.',
            'notas.*.min' => 'A nota mínima é 0.',
            'notas.*.max' => 'A nota máxima é 20.',
        ];
    }
}
