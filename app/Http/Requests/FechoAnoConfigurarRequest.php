<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FechoAnoConfigurarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin_full') ?? false;
    }

    public function rules(): array
    {
        return [
            'ano_origem' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'ano_destino' => ['required', 'regex:/^\d{4}-\d{4}$/', 'different:ano_origem'],
        ];
    }

    public function messages(): array
    {
        return [
            'ano_origem.required' => 'O ano de origem é obrigatório.',
            'ano_destino.required' => 'O ano de destino é obrigatório.',
            'ano_destino.different' => 'O ano de destino deve ser diferente do ano de origem.',
        ];
    }
}
