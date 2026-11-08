<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AlunoAuthService
{
    /** @var list<string> */
    private const PERMISSOES_ALUNO = [
        'portal.aluno.ver',
        'assistente.usar',
    ];

    public function ensureAlunoRoleExists(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSOES_ALUNO as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);
        $role->syncPermissions(self::PERMISSOES_ALUNO);
    }

    public function criarOuActualizarConta(Aluno $aluno): User
    {
        $this->ensureAlunoRoleExists();

        $processo = trim((string) $aluno->numero_processo);
        if ($processo === '') {
            throw new \InvalidArgumentException('Aluno sem número de processo.');
        }

        $user = User::query()
            ->where('aluno_id', $aluno->id)
            ->orWhere('email', $processo)
            ->first();

        $dados = [
            'name' => $aluno->nome,
            'email' => $processo,
            'aluno_id' => $aluno->id,
            'estado' => $aluno->activo ? 'activo' : 'inactivo',
        ];

        if (! $user) {
            $temporaria = $this->gerarPalavraPasseTemporaria();
            $user = User::create(array_merge($dados, [
                'password' => $temporaria,
                'must_change_password' => true,
                'password_changed_at' => null,
                'email_verified_at' => now(),
            ]));
            $user->assignRole('aluno');
            $user->setAttribute('password_plain_once', $temporaria);

            return $user;
        }

        $user->update($dados);

        if (! $user->hasRole('aluno')) {
            $user->assignRole('aluno');
        }

        return $user->fresh() ?? $user;
    }

    public function desactivarConta(Aluno $aluno): void
    {
        User::query()
            ->where('aluno_id', $aluno->id)
            ->update(['estado' => 'inactivo']);
    }

    /**
     * @return array{criados:int, actualizados:int, erros:int}
     */
    public function sincronizarTodos(?int $anoLectivoId = null): array
    {
        $query = Aluno::query()->where('activo', true);

        if ($anoLectivoId) {
            $query->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId));
        }

        $criados = 0;
        $actualizados = 0;
        $erros = 0;

        foreach ($query->cursor() as $aluno) {
            try {
                $existente = User::where('aluno_id', $aluno->id)->exists();
                $this->criarOuActualizarConta($aluno);
                $existente ? $actualizados++ : $criados++;
            } catch (\Throwable) {
                $erros++;
            }
        }

        return compact('criados', 'actualizados', 'erros');
    }

    public function gerarPalavraPasseTemporaria(): string
    {
        return Str::password(10, letters: true, numbers: true, symbols: false);
    }
}
