<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $turno = $this->route('turno');

        // Para criação
        if (!$turno) {
            return $this->user()->can('turnos.criar');
        }

        // Para edição
        return $this->user()->can('turnos.editar');
    }

    public function rules(): array
    {
        $turnoId = $this->route('turno')?->id;

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('turnos', 'codigo')->ignore($turnoId),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'O código do turno é obrigatório.',
            'codigo.unique' => 'Este código já está a ser utilizado por outro turno.',
            'nome.required' => 'O nome do turno é obrigatório.',
            'hora_inicio.date_format' => 'A hora de início deve estar no formato HH:MM.',
            'hora_fim.date_format' => 'A hora de fim deve estar no formato HH:MM.',
            'hora_fim.after' => 'A hora de fim deve ser depois da hora de início.',
        ];
    }
}
