<?php

namespace App\Http\Requests;

use App\Models\PautaArquivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PautaArquivoRequest extends FormRequest
{
    /**
     * Determina se o utilizador tem autorização para esta requisição.
     */
    public function authorize(): bool
    {
        // Permissão verificada na Policy, aqui apenas verificamos autenticação
        return $this->user() !== null;
    }

    /**
     * Regras de validação para a requisição.
     */
    public function rules(): array
    {
        $rules = [
            'curso_id' => [
                'required',
                'exists:cursos,id',
            ],
            'turma_id' => [
                'nullable',
                'exists:turmas,id',
            ],
            'turma_manual' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sala_id' => [
                'nullable',
                'exists:salas,id',
            ],
            'sala_manual' => [
                'nullable',
                'string',
                'max:255',
            ],
            'disciplina_id' => [
                'nullable',
                'exists:disciplinas,id',
            ],
            'disciplina_manual' => [
                'nullable',
                'string',
                'max:255',
            ],
            'ano_lectivo' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
            ],
            'trimestre' => [
                'required',
                'integer',
                'in:0,1,2,3',
            ],
            'tipo_pauta' => [
                'required',
                Rule::in(array_keys(PautaArquivo::getTiposPauta())),
            ],
            'origem' => [
                'required',
                Rule::in(array_keys(PautaArquivo::getOrigensArquivo())),
            ],
            'descricao' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'activo' => [
                'boolean',
            ],
        ];

        // Upload é obrigatório apenas na criação (POST)
        if ($this->isMethod('POST')) {
            $rules['arquivo'] = [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240', // 10MB em kilobytes
            ];
        }

        return $rules;
    }

    /**
     * Mensagens de validação customizadas.
     */
    public function messages(): array
    {
        return [
            'curso_id.required' => 'O curso é obrigatório.',
            'curso_id.exists' => 'O curso selecionado não existe.',

            'turma_id.exists' => 'A turma selecionada não existe.',

            'sala_id.exists' => 'A sala selecionada não existe.',

            'disciplina_id.exists' => 'A disciplina selecionada não existe.',

            'ano_lectivo.required' => 'O ano lectivo é obrigatório.',
            'ano_lectivo.regex' => 'O ano lectivo deve estar no formato AAAA-AAAA.',

            'trimestre.required' => 'O trimestre é obrigatório.',
            'trimestre.integer' => 'O trimestre deve ser um número inteiro.',
            'trimestre.in' => 'O trimestre deve ser 0 (Anual), 1 (1º Trimestre), 2 (2º Trimestre) ou 3 (3º Trimestre).',

            'tipo_pauta.required' => 'O tipo de pauta é obrigatório.',
            'tipo_pauta.in' => 'O tipo de pauta selecionado é inválido.',

            'origem.required' => 'A origem do arquivo é obrigatória.',
            'origem.in' => 'A origem selecionada é inválida.',

            'descricao.string' => 'A descrição deve ser um texto.',
            'descricao.max' => 'A descrição não pode exceder 1000 caracteres.',

            'activo.boolean' => 'O status activo deve ser verdadeiro ou falso.',

            'arquivo.required' => 'O ficheiro Excel é obrigatório.',
            'arquivo.file' => 'O arquivo enviado é inválido.',
            'arquivo.mimes' => 'O ficheiro deve ser do tipo Excel (.xlsx ou .xls).',
            'arquivo.max' => 'O ficheiro não pode exceder 10MB.',
        ];
    }

    /**
     * Nomes dos atributos para mensagens de erro.
     */
    public function attributes(): array
    {
        return [
            'curso_id' => 'curso',
            'turma_id' => 'turma',
            'sala_id' => 'sala',
            'disciplina_id' => 'disciplina',
            'ano_lectivo' => 'ano lectivo',
            'trimestre' => 'trimestre',
            'tipo_pauta' => 'tipo de pauta',
            'origem' => 'origem',
            'descricao' => 'descrição',
            'activo' => 'status activo',
            'arquivo' => 'ficheiro',
        ];
    }
}
