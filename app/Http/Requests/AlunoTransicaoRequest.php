<?php

namespace App\Http\Requests;

use App\Models\AnoLectivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlunoTransicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Apenas admin_full pode transitar alunos
        return $this->user()->hasRole('admin_full');
    }

    public function rules(): array
    {
        $anoDestinoId = AnoLectivo::getIdByNome($this->input('ano_destino'));

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
            'mapeamento_promocao' => ['required', 'array'],
            'mapeamento_promocao.*' => [
                'nullable',
                Rule::exists('turmas', 'id')->where(
                    fn ($q) => $anoDestinoId ? $q->where('ano_lectivo_id', $anoDestinoId) : $q
                ),
            ],
            'mapeamento_repetencia' => ['required', 'array'],
            'mapeamento_repetencia.*' => [
                'nullable',
                Rule::exists('turmas', 'id')->where(
                    fn ($q) => $anoDestinoId ? $q->where('ano_lectivo_id', $anoDestinoId) : $q
                ),
            ],
            'transitar_reprovados' => ['boolean'],
            'alunos_selecionados' => ['nullable', 'array'],
            'alunos_selecionados.*' => ['boolean'],
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
            'mapeamento_promocao.required' => 'O mapeamento de promoção é obrigatório.',
            'mapeamento_promocao.*.exists' => 'Uma das turmas de destino (promoção) não existe.',
            'mapeamento_repetencia.required' => 'O mapeamento de repetência é obrigatório.',
            'mapeamento_repetencia.*.exists' => 'Uma das turmas de destino (repetência) não existe.',
        ];
    }
}
