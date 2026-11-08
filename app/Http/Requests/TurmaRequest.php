<?php

namespace App\Http\Requests;

use App\Models\Turma;
use App\Support\EnsinoPerfil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TurmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $turma = $this->route('turma');

        // Para criação
        if (!$turma) {
            return $this->user()->can('create', Turma::class);
        }

        return $this->user()->can('update', $turma);
    }

    public function rules(): array
    {
        $turma = $this->route('turma');
        $turmaId = $turma?->id;
        $anoLectivoId = $this->input('ano_lectivo_id', $turma?->ano_lectivo_id);

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('turmas', 'codigo')
                    ->where(fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                    ->ignore($turmaId),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'curso_id' => ['required', 'exists:cursos,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'sala_id' => ['nullable', 'exists:salas,id'],
            'turno_id' => ['nullable', 'exists:turnos,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'O código da turma é obrigatório.',
            'codigo.unique' => 'Este código já está a ser utilizado por outra turma.',
            'nome.required' => 'O nome da turma é obrigatório.',
            'curso_id.required' => 'O curso é obrigatório.',
            'curso_id.exists' => 'O curso seleccionado não existe.',
            'classe_id.required' => 'A classe é obrigatória.',
            'classe_id.exists' => 'A classe seleccionada não existe.',
            'sala_id.exists' => 'A sala seleccionada não existe.',
            'turno_id.exists' => 'O turno seleccionado não existe.',
            'ano_lectivo_id.required' => 'O ano lectivo é obrigatório.',
            'ano_lectivo_id.exists' => 'O ano lectivo seleccionado não existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $classeId = (int) $this->input('classe_id');
            if ($classeId && ! EnsinoPerfil::permiteClasseId($classeId)) {
                $validator->errors()->add(
                    'classe_id',
                    'Esta classe não está disponível no perfil de ensino desta instalação.'
                );
            }
        });
    }
}
