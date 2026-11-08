<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConcluirConselhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conselho = $this->route('conselho');

        if (!$conselho) {
            return false;
        }

        return $this->user()->can('concluir', $conselho);
    }

    public function rules(): array
    {
        return [
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'observacoes.max' => 'As observações não podem ter mais de 1000 caracteres.',
        ];
    }
}
