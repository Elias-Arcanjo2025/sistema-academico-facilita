<?php

namespace App\Http\Requests;

use App\Support\EnsinoPerfil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $disciplina = $this->route('disciplina');

        // Para criação
        if (!$disciplina) {
            return $this->user()->can('disciplinas.criar');
        }

        // Para edição
        return $this->user()->can('disciplinas.editar');
    }

    public function rules(): array
    {
        $disciplinaId = $this->route('disciplina')?->id;

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('disciplinas', 'codigo')->ignore($disciplinaId),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'tipo' => ['required', 'string', Rule::in(['geral', 'cientifica', 'tecnica', 'projeto_estagio'])],
            'nivel_dificuldade' => ['required', 'string', Rule::in(['facil', 'medio', 'dificil'])],
            'ordem_apresentacao' => ['required', 'integer', 'min:1', 'max:999'],
            'cursos' => ['required', 'array', 'min:1'],
            'cursos.*' => ['exists:cursos,id'],
            'classes' => ['nullable', 'array'],
            'classes.*' => ['exists:classes,id'],
            'activo' => ['boolean'],
            // @deprecated — terminalidade migrada para curso_disciplina.classe_fim (Plano B).
            // Campos mantidos por compatibilidade; aceites mas não exigidos.
            'terminal' => ['nullable', 'boolean'],
            'classe_terminal_id' => ['nullable', 'integer', 'exists:classes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'O código da disciplina é obrigatório.',
            'codigo.unique' => 'Este código já está a ser utilizado por outra disciplina.',
            'nome.required' => 'O nome da disciplina é obrigatório.',
            'tipo.required' => 'Seleccione o tipo de disciplina.',
            'tipo.in' => 'O tipo de disciplina seleccionado é inválido.',
            'ordem_apresentacao.required' => 'A ordem de apresentação é obrigatória.',
            'ordem_apresentacao.integer' => 'A ordem de apresentação deve ser um número inteiro.',
            'ordem_apresentacao.min' => 'A ordem de apresentação deve ser no mínimo 1.',
            'ordem_apresentacao.max' => 'A ordem de apresentação deve ser no máximo 999.',
            'cursos.required' => 'Seleccione pelo menos um curso.',
            'cursos.min' => 'Seleccione pelo menos um curso.',
            'cursos.*.exists' => 'Um dos cursos seleccionados não existe.',
            'classes.*.exists' => 'Uma das classes seleccionadas não existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('classes', []) as $classeId) {
                if ($classeId && ! EnsinoPerfil::permiteClasseId((int) $classeId)) {
                    $validator->errors()->add(
                        'classes',
                        'Uma das classes seleccionadas não está disponível no perfil de ensino desta instalação.'
                    );
                    break;
                }
            }
        });
    }
}
