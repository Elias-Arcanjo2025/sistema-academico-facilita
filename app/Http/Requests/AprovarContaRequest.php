<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AprovarContaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user && $this->user()?->can('aprovar', $user);
    }

    public function rules(): array
    {
        $roles = Role::whereNotIn('name', ['admin_full', 'aluno'])->pluck('name')->all();

        return [
            'role' => ['required', 'string', Rule::in($roles)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Seleccione o cargo a atribuir ao utilizador.',
            'role.in' => 'O cargo seleccionado não é válido.',
        ];
    }
}
