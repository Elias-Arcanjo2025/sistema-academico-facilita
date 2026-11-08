<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnoLectivoTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Apenas admin_full pode transferir
        return $this->user()->hasRole('admin_full');
    }

    public function rules(): array
    {
        return [
            'ano_origem' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
            ],
            'ano_destino' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
                'different:ano_origem',
            ],
            'transferir_associacoes' => ['boolean'],
            'transferir_directores' => ['boolean'],
            'transferir_coordenadores' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ano_origem.required' => 'O ano de origem é obrigatório.',
            'ano_origem.regex' => 'O ano de origem deve estar no formato YYYY-YYYY (ex: 2024-2025).',
            'ano_destino.required' => 'O ano de destino é obrigatório.',
            'ano_destino.regex' => 'O ano de destino deve estar no formato YYYY-YYYY (ex: 2025-2026).',
            'ano_destino.different' => 'O ano de destino deve ser diferente do ano de origem.',
        ];
    }
}
