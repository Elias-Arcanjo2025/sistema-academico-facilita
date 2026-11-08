<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IniciarConselhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conselho = $this->route('conselho');

        if (!$conselho) {
            return false;
        }

        return $this->user()->can('iniciar', $conselho);
    }

    public function rules(): array
    {
        // Nenhum campo é necessário para iniciar um conselho
        // A validação de estado é feita no service
        return [];
    }
}
