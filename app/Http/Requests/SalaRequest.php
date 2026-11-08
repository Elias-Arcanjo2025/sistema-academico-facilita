<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sala = $this->route('sala');

        // Para criação
        if (!$sala) {
            return $this->user()->can('salas.criar');
        }

        // Para edição
        return $this->user()->can('salas.editar');
    }

    public function rules(): array
    {
        $salaId = $this->route('sala')?->id;

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('salas', 'codigo')->ignore($salaId),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'capacidade' => ['nullable', 'integer', 'min:1'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'O código da sala é obrigatório.',
            'codigo.unique' => 'Este código já está a ser utilizado por outra sala.',
            'nome.required' => 'O nome da sala é obrigatório.',
            'capacidade.integer' => 'A capacidade deve ser um número inteiro.',
            'capacidade.min' => 'A capacidade deve ser pelo menos 1.',
        ];
    }
}
