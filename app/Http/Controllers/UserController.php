<?php

namespace App\Http\Controllers;

use App\Http\Requests\AprovarContaRequest;
use App\Models\Aluno;
use App\Models\User;
use App\Notifications\ContaAprovadaNotification;
use App\Notifications\ContaRejeitadaNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin_full|directora_geral');
    }

    /**
     * Lista todos os utilizadores
     */
    public function index(Request $request): View
    {
        $estado = $request->input('estado');
        $pesquisa = $request->input('pesquisa');
        $tipo = $request->input('tipo', 'funcionario');

        if (! in_array($tipo, ['funcionario', 'aluno', 'encarregado', 'todos'], true)) {
            $tipo = 'funcionario';
        }

        $query = User::with([
            'roles',
            'aluno' => fn ($q) => $q->with('turma.curso')->withCount('notas'),
            'encarregado',
        ]);

        if ($estado) {
            $query->where('estado', $estado);
        }

        if ($tipo === 'aluno') {
            $query->utilizadoresAlunos();
        } elseif ($tipo === 'encarregado') {
            $query->utilizadoresEncarregados();
        } elseif ($tipo === 'funcionario') {
            $query->funcionariosEscola();
        }

        if ($pesquisa) {
            $query->where(function ($q) use ($pesquisa) {
                $q->where('name', 'like', "%{$pesquisa}%")
                  ->orWhere('email', 'like', "%{$pesquisa}%");
            });
        }

        if ($tipo === 'todos') {
            $query->orderByRaw('CASE
                    WHEN aluno_id IS NOT NULL THEN 3
                    WHEN encarregado_id IS NOT NULL THEN 2
                    ELSE 1
                END')
                ->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        $users = $query->paginate(15)->withQueryString();

        $estados = [
            'pendente' => 'Pendente',
            'activo' => 'Activo',
            'rejeitado' => 'Rejeitado',
            'inactivo' => 'Inactivo',
        ];

        $contagens = [
            'total' => User::count(),
            'pendente' => User::where('estado', 'pendente')->count(),
            'activo' => User::where('estado', 'activo')->count(),
            'rejeitado' => User::where('estado', 'rejeitado')->count(),
            'inactivo' => User::where('estado', 'inactivo')->count(),
            'alunos' => User::utilizadoresAlunos()->count(),
            'encarregados' => User::utilizadoresEncarregados()->count(),
            'funcionarios' => User::funcionariosEscola()->count(),
        ];

        return view('users.index', compact('users', 'estados', 'contagens', 'estado', 'pesquisa', 'tipo'));
    }

    /**
     * Lista contas pendentes de aprovacao
     */
    public function pendentes(): View
    {
        $users = User::where('estado', 'pendente')
            ->orderBy('created_at', 'asc')
            ->paginate(10);

        $rolesAprovacao = Role::whereNotIn('name', ['admin_full', 'aluno'])
            ->orderBy('name')
            ->get(['name']);

        return view('users.pendentes', compact('users', 'rolesAprovacao'));
    }

    /**
     * Aprovar uma conta
     */
    public function aprovar(AprovarContaRequest $request, User $user): RedirectResponse
    {
        if ($user->estado !== 'pendente') {
            return back()->with('error', 'Esta conta nao esta pendente de aprovacao.');
        }

        $role = $request->validated('role');

        $user->update(['estado' => 'activo']);
        $user->syncRoles([$role]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['estado_anterior' => 'pendente', 'estado_novo' => 'activo'])
            ->log('Conta aprovada');

        $user->notify(new ContaAprovadaNotification());

        return back()->with('success', "Conta de {$user->name} aprovada como {$role}.");
    }

    /**
     * Formulario para rejeitar uma conta
     */
    public function formRejeitar(User $user): View
    {
        if ($user->estado !== 'pendente') {
            abort(404, 'Esta conta nao esta pendente.');
        }

        return view('users.rejeitar', compact('user'));
    }

    /**
     * Rejeitar uma conta
     */
    public function rejeitar(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'motivo_rejeicao' => 'required|string|min:10|max:500',
        ], [
            'motivo_rejeicao.required' => 'O motivo da rejeicao e obrigatorio.',
            'motivo_rejeicao.min' => 'O motivo deve ter pelo menos 10 caracteres.',
        ]);

        if ($user->estado !== 'pendente') {
            return back()->with('error', 'Esta conta nao esta pendente de aprovacao.');
        }

        $user->update([
            'estado' => 'rejeitado',
            'motivo_rejeicao' => $request->input('motivo_rejeicao'),
        ]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties([
                'estado_anterior' => 'pendente',
                'estado_novo' => 'rejeitado',
                'motivo' => $request->input('motivo_rejeicao'),
            ])
            ->log('Conta rejeitada');

        $user->notify(new ContaRejeitadaNotification($request->input('motivo_rejeicao')));

        return redirect()->route('users.pendentes')
            ->with('success', "Conta de {$user->name} rejeitada.");
    }

    /**
     * Ver detalhes de um utilizador
     */
    public function show(User $user): View
    {
        $user->load([
            'roles',
            'aluno' => fn ($q) => $q->with('turma.curso')->withCount('notas'),
        ]);

        $roles = Role::all();

        $actividades = \Spatie\Activitylog\Models\Activity::where('causer_id', $user->id)
            ->orWhere(function ($q) use ($user) {
                $q->where('subject_type', User::class)
                  ->where('subject_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('users.show', compact('user', 'roles', 'actividades'));
    }

    public function reporPalavraPasse(User $user): RedirectResponse
    {
        if (! auth()->user()->can('utilizadores.editar')) {
            abort(403);
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Não pode repor a sua própria palavra-passe desta forma.');
        }

        if ($user->hasRole('aluno')) {
            return back()->with('error', 'Para alunos, use a opção na ficha do aluno.');
        }

        $temporaria = app(\App\Services\PasswordResetService::class)->reporStaff($user);

        return back()->with('success', "Palavra-passe reposta. Palavra-passe temporária: {$temporaria} — o utilizador deve alterá-la no próximo acesso.");
    }

    /**
     * Alterar estado de um utilizador
     */
    public function alterarEstado(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'estado' => 'required|in:activo,inactivo',
        ]);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Não pode alterar o estado da sua própria conta.');
        }

        if (! in_array($user->estado, ['activo', 'inactivo'], true)) {
            return back()->with('error', 'Só é possível desactivar ou reactivar contas activas ou inactivas.');
        }

        $estadoAnterior = $user->estado;
        $novoEstado = $request->input('estado');

        if ($estadoAnterior === $novoEstado) {
            return back()->with('error', 'O utilizador já se encontra neste estado.');
        }

        $user->update(['estado' => $novoEstado]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties([
                'estado_anterior' => $estadoAnterior,
                'estado_novo' => $novoEstado,
            ])
            ->log("Estado alterado de {$estadoAnterior} para {$novoEstado}");

        return back()->with('success', "Estado de {$user->name} alterado para {$novoEstado}.");
    }

    /**
     * Atribuir cargo a um utilizador
     */
    public function atribuirCargo(Request $request, User $user): RedirectResponse
    {
        $this->authorize('gerirCargos', $user);

        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $role = $request->input('role');

        if ($erro = $this->validarCargoSensivel($request->user(), $role)) {
            return back()->with('error', $erro);
        }

        if ($user->hasRole($role)) {
            return back()->with('error', "O utilizador já tem o cargo de {$role}.");
        }

        $user->assignRole($role);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['cargo' => $role])
            ->log("Cargo {$role} atribuído");

        return back()->with('success', "Cargo {$role} atribuído a {$user->name}.");
    }

    /**
     * Remover cargo de um utilizador
     */
    public function removerCargo(Request $request, User $user): RedirectResponse
    {
        $this->authorize('gerirCargos', $user);

        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $role = $request->input('role');

        if ($role === 'admin_full' && ! $request->user()->hasRole('admin_full')) {
            return back()->with('error', 'Não pode remover o cargo de administrador do sistema.');
        }

        if (!$user->hasRole($role)) {
            return back()->with('error', "O utilizador não tem o cargo de {$role}.");
        }

        // Nao permitir remover o ultimo cargo
        if ($user->roles->count() <= 1) {
            return back()->with('error', 'O utilizador deve ter pelo menos um cargo.');
        }

        $user->removeRole($role);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['cargo' => $role])
            ->log("Cargo {$role} removido");

        return back()->with('success', "Cargo {$role} removido de {$user->name}.");
    }

    /**
     * Sincronizar cargos de um utilizador
     */
    public function syncCargos(Request $request, User $user): RedirectResponse
    {
        $this->authorize('gerirCargos', $user);

        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ], [
            'roles.required' => 'Deve seleccionar pelo menos um cargo.',
            'roles.min' => 'Deve seleccionar pelo menos um cargo.',
        ]);

        $cargosAnteriores = $user->roles->pluck('name')->toArray();
        $novosCargos = $request->input('roles');

        foreach ($novosCargos as $role) {
            if ($erro = $this->validarCargoSensivel($request->user(), $role)) {
                return back()->with('error', $erro);
            }
        }

        // Directora não pode retirar admin_full de uma conta que já o tenha
        if (in_array('admin_full', $cargosAnteriores, true)
            && ! in_array('admin_full', $novosCargos, true)
            && ! $request->user()->hasRole('admin_full')) {
            return back()->with('error', 'Não pode remover o cargo de administrador do sistema.');
        }

        $user->syncRoles($novosCargos);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties([
                'cargos_anteriores' => $cargosAnteriores,
                'cargos_novos' => $novosCargos,
            ])
            ->log('Cargos actualizados');

        return back()->with('success', "Cargos de {$user->name} actualizados.");
    }

    /**
     * Impede atribuição de admin_full por quem não é admin_full.
     */
    private function validarCargoSensivel(User $actor, string $role): ?string
    {
        if ($role === 'admin_full' && ! $actor->hasRole('admin_full')) {
            return 'Apenas o administrador do sistema pode atribuir o cargo admin_full.';
        }

        return null;
    }

    /**
     * Atribuir permissão extra a um utilizador
     */
    public function atribuirPermissao(Request $request, User $user): RedirectResponse
    {
        // Autorização: apenas admin_full e directora_geral
        if (!auth()->user()->hasAnyRole(['admin_full', 'directora_geral'])) {
            abort(403, 'Acesso negado.');
        }

        $request->validate([
            'permission' => 'required|exists:permissions,name',
        ], [
            'permission.required' => 'Deve seleccionar uma permissão.',
            'permission.exists' => 'Permissão inválida.',
        ]);

        $permission = $request->input('permission');

        // Verificar se já tem a permissão
        if ($user->hasPermissionTo($permission)) {
            if ($user->isExtraPermission($permission)) {
                return back()->with('error', "O utilizador já tem a permissão extra '{$permission}'.");
            } else {
                return back()->with('error', "O utilizador já tem a permissão '{$permission}' através do cargo.");
            }
        }

        // Atribuir permissão direta
        $user->givePermissionTo($permission);

        // Registar na auditoria
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['permissao' => $permission])
            ->log("Permissão extra '{$permission}' atribuída");

        return back()->with('success', "Permissão '{$permission}' atribuída a {$user->name}.");
    }

    /**
     * Remover permissão extra de um utilizador
     */
    public function removerPermissao(Request $request, User $user): RedirectResponse
    {
        // Autorização: apenas admin_full e directora_geral
        if (!auth()->user()->hasAnyRole(['admin_full', 'directora_geral'])) {
            abort(403, 'Acesso negado.');
        }

        $request->validate([
            'permission' => 'required|exists:permissions,name',
        ]);

        $permission = $request->input('permission');

        // Verificar se tem a permissão como extra (direta)
        if (!$user->isExtraPermission($permission)) {
            return back()->with('error', "O utilizador não tem a permissão extra '{$permission}'.");
        }

        // Remover permissão direta
        $user->revokePermissionTo($permission);

        // Registar na auditoria
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['permissao' => $permission])
            ->log("Permissão extra '{$permission}' removida");

        return back()->with('success', "Permissão '{$permission}' removida de {$user->name}.");
    }

    /**
     * Sincronizar permissões extras de um utilizador
     */
    public function syncPermissoes(Request $request, User $user): RedirectResponse
    {
        // Autorização: apenas admin_full e directora_geral
        if (!auth()->user()->hasAnyRole(['admin_full', 'directora_geral'])) {
            abort(403, 'Acesso negado.');
        }

        $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $permissoesAnteriores = $user->getDirectPermissions()->pluck('name')->toArray();
        $novasPermissoes = $request->input('permissions', []);

        // Sincronizar apenas permissões diretas (não afeta permissões dos roles)
        $user->syncPermissions($novasPermissoes);

        // Registar na auditoria
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties([
                'permissoes_anteriores' => $permissoesAnteriores,
                'permissoes_novas' => $novasPermissoes,
            ])
            ->log('Permissões extras actualizadas');

        return back()->with('success', "Permissões extras de {$user->name} actualizadas.");
    }

    /**
     * Elimina permanentemente um utilizador-aluno e todas as suas avaliações.
     * Apenas admin_full pode forçar esta operação.
     */
    public function eliminarAluno(Request $request, User $user): RedirectResponse
    {
        if (! auth()->user()->hasRole('admin_full')) {
            abort(403, 'Apenas o administrador do sistema pode eliminar alunos com avaliações.');
        }

        if (! $user->eUtilizadorAluno()) {
            return back()->with('error', 'Este utilizador não é um aluno. Funcionários não podem ser eliminados por esta acção.');
        }

        if ($user->hasRole('admin_full')) {
            return back()->with('error', 'Não é possível eliminar uma conta de administrador.');
        }

        if ((int) $user->id === (int) auth()->id()) {
            return back()->with('error', 'Não pode eliminar a sua própria conta.');
        }

        $request->validate([
            'confirmar_eliminacao' => 'required|accepted',
        ], [
            'confirmar_eliminacao.accepted' => 'Deve confirmar que compreende que a eliminação é irreversível.',
        ]);

        $nome = $user->name;
        $aluno = $user->aluno_id ? Aluno::find($user->aluno_id) : null;
        $notasCount = 0;

        DB::transaction(function () use ($user, $aluno, &$notasCount) {
            if ($aluno) {
                $notasCount = $aluno->eliminarPermanentementeComAvaliacoes();
            }

            $user->roles()->detach();
            $user->permissions()->detach();
            $user->delete();
        });

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'utilizador_eliminado' => $nome,
                'aluno_id' => $aluno?->id,
                'notas_eliminadas' => $notasCount,
            ])
            ->log('Aluno e conta eliminados permanentemente (com avaliações)');

        return redirect()->route('users.index', ['tipo' => 'aluno'])
            ->with('success', "Aluno {$nome} eliminado permanentemente."
                .($notasCount > 0 ? " Foram removidas {$notasCount} avaliação(ões)." : ''));
    }
}
