<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotaAlteracaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Alteração de nota (requer justificação)
        return $this->user()->can('notas.editar');
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'min:0', 'max:20'],
            'justificacao' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'valor.required' => 'O novo valor da nota é obrigatório.',
            'valor.numeric' => 'O valor da nota deve ser um número.',
            'valor.min' => 'A nota mínima é 0.',
            'valor.max' => 'A nota máxima é 20.',
            'justificacao.required' => 'A justificação é obrigatória para alterar uma nota.',
            'justificacao.min' => 'A justificação deve ter pelo menos 10 caracteres.',
            'justificacao.max' => 'A justificação não pode exceder 500 caracteres.',
        ];
    }
}
