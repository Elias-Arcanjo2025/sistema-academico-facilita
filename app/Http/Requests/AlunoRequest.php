<?php

namespace App\Http\Requests;

use App\Models\Aluno;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;

class AlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aluno = $this->route('aluno');

        // Para criação
        if (!$aluno) {
            return $this->user()->can('alunos.criar')
                || $this->user()->can('secretaria.matriculas.gerir');
        }

        // Para edição
        return $this->user()->can('alunos.editar');
    }

    public function rules(): array
    {
        $alunoId = $this->route('aluno')?->id;

        return [
            'numero_processo' => [
                'required',
                'string',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail) use ($alunoId): void {
                    $turmaId = $this->input('turma_id');
                    $anoLectivoId = Turma::find($turmaId)?->ano_lectivo_id;

                    if (! $anoLectivoId) {
                        return;
                    }

                    $duplicado = Aluno::where('numero_processo', $value)
                        ->when($alunoId, fn ($q) => $q->where('id', '!=', $alunoId))
                        ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                        ->exists();

                    if ($duplicado) {
                        $fail('Este número de processo já está a ser utilizado neste ano lectivo.');
                    }
                },
            ],
            'nome' => ['required', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'max:2048'],
            'data_nascimento' => ['nullable', 'date', 'before:today'],
            'genero' => ['nullable', 'string', 'in:M,F'],
            'bi_numero' => ['nullable', 'string', 'max:50'],
            'pai_nome' => ['nullable', 'string', 'max:255'],
            'pai_telefone' => ['nullable', 'string', 'max:40'],
            'mae_nome' => ['nullable', 'string', 'max:255'],
            'mae_telefone' => ['nullable', 'string', 'max:40'],
            'educacao_especial' => ['nullable', 'boolean'],
            'necessidades_especificas' => ['nullable', 'string', 'max:2000', 'required_if:educacao_especial,1'],
            'atleta_alta_competicao' => ['nullable', 'boolean'],
            'faltou_exame_normal' => ['nullable', 'boolean'],
            'turma_id' => ['required', 'exists:turmas,id'],
            'activo' => ['boolean'],
            'consulta_notas_portal' => ['nullable', 'boolean'],
            'motivo_bloqueio_portal' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'educacao_especial' => $this->boolean('educacao_especial'),
            'atleta_alta_competicao' => $this->boolean('atleta_alta_competicao'),
            'faltou_exame_normal' => $this->boolean('faltou_exame_normal'),
            'consulta_notas_portal' => $this->boolean('consulta_notas_portal'),
        ]);
    }

    public function messages(): array
    {
        return [
            'numero_processo.required' => 'O número de processo é obrigatório.',
            'numero_processo.unique' => 'Este número de processo já está a ser utilizado.',
            'nome.required' => 'O nome do aluno é obrigatório.',
            'data_nascimento.before' => 'A data de nascimento deve ser anterior a hoje.',
            'genero.in' => 'O género deve ser M (Masculino) ou F (Feminino).',
            'necessidades_especificas.required_if' => 'Quando o aluno é público-alvo da Educação Especial, é obrigatório descrever as necessidades específicas (Art.º 29 do DE 424/25).',
            'turma_id.required' => 'A turma é obrigatória.',
            'turma_id.exists' => 'A turma seleccionada não existe.',
        ];
    }
}
