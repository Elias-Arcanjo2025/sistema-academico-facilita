<?php

namespace App\Http\Requests;

use App\Models\Nota;
use App\Support\ConselhoAjusteRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AjustarNotaConselhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conselho = $this->route('conselho');

        if (! $conselho) {
            return false;
        }

        return $this->user()->can('ajustar', $conselho);
    }

    public function rules(): array
    {
        return [
            'nota_id' => ['required', 'exists:notas,id'],
            'novo_valor' => ['required', 'numeric', 'min:0', 'max:20'],
            'justificacao' => ['required', 'string', 'min:10', 'max:1000'],
            'motivo_reducao' => ['nullable', Rule::in(array_keys(ConselhoAjusteRules::motivosReducaoPermitidos()))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $nota = Nota::find($this->input('nota_id'));
            if (! $nota) {
                return;
            }

            try {
                ConselhoAjusteRules::validarAjuste(
                    (float) $nota->valor,
                    (float) $this->input('novo_valor'),
                    $this->input('motivo_reducao'),
                    $this->input('justificacao'),
                );
            } catch (\InvalidArgumentException $e) {
                $campo = ConselhoAjusteRules::isReducao((float) $nota->valor, (float) $this->input('novo_valor'))
                    && ! $this->filled('motivo_reducao')
                    ? 'motivo_reducao'
                    : 'novo_valor';

                $validator->errors()->add($campo, $e->getMessage());
            }
        });
    }

    public function messages(): array
    {
        return [
            'nota_id.required' => 'A nota é obrigatória.',
            'nota_id.exists' => 'A nota seleccionada não existe.',
            'novo_valor.required' => 'O novo valor da nota é obrigatório.',
            'novo_valor.numeric' => 'O novo valor deve ser um número.',
            'novo_valor.min' => 'O novo valor deve ser no mínimo 0.',
            'novo_valor.max' => 'O novo valor deve ser no máximo 20.',
            'justificacao.required' => 'A justificação é obrigatória.',
            'justificacao.min' => 'A justificação deve ter pelo menos 10 caracteres.',
            'justificacao.max' => 'A justificação não pode ter mais de 1000 caracteres.',
            'motivo_reducao.in' => 'O motivo de redução seleccionado é inválido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('novo_valor')) {
            $merge['novo_valor'] = str_replace(',', '.', (string) $this->input('novo_valor'));
        }

        if ($this->has('motivo_reducao') && $this->input('motivo_reducao') === '') {
            $merge['motivo_reducao'] = null;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $conselho = $this->route('conselho');

        throw new HttpResponseException(
            redirect()
                ->route('conselhos.show', $conselho)
                ->withErrors($validator)
                ->withInput()
        );
    }
}
