<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $curso = $this->route('curso');

        // Para criação
        if (!$curso) {
            return $this->user()->can('cursos.criar');
        }

        // Para edição
        return $this->user()->can('cursos.editar');
    }

    public function rules(): array
    {
        $cursoId = $this->route('curso')?->id;

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cursos', 'codigo')->ignore($cursoId),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'classe_max' => ['nullable', 'integer', 'min:7', 'max:13'],
            'regime_calculo' => ['required', Rule::in([
                \App\Models\Curso::REGIME_POLITECNICO,
                \App\Models\Curso::REGIME_ADMINISTRACAO,
            ])],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'O código do curso é obrigatório.',
            'codigo.unique' => 'Este código já está a ser utilizado por outro curso.',
            'codigo.max' => 'O código não pode ter mais de 20 caracteres.',
            'nome.required' => 'O nome do curso é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'classe_max.integer' => 'A classe máxima deve ser um número (ex: 12).',
            'classe_max.min' => 'A classe máxima deve ser ≥ 7.',
            'classe_max.max' => 'A classe máxima deve ser ≤ 13.',
            'regime_calculo.required' => 'Tem de seleccionar o regime de cálculo da pauta.',
            'regime_calculo.in' => 'Regime de cálculo inválido.',
        ];
    }
}
