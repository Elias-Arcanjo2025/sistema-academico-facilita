<?php

namespace App\Http\Middleware;

use App\Models\AnoLectivo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Segurança: Verifica se existe pelo menos um ano lectivo no sistema
 *
 * CRÍTICO: Todos os dados académicos (turmas, alunos, notas, etc.) dependem de um ano lectivo.
 * Este middleware impede a criação de dados órfãos sem contexto de ano lectivo.
 *
 * Aplicado em:
 * - Gestão Académica (Cursos, Disciplinas, Turmas, Alunos, Associações)
 * - Avaliação (Notas, Pautas, Mini-Pautas)
 * - Relatórios e Exportações
 * - Transição de Alunos
 *
 * NÃO aplicado em:
 * - Infraestrutura (Salas, Turnos) - independente de ano lectivo
 * - Configurações - necessário para criar o primeiro ano lectivo
 * - Utilizadores e Auditoria
 */
class CheckAnoLectivoExists
{
    /**
     * Rotas que devem sempre ser acessíveis (mesmo sem ano lectivo)
     */
    protected array $exemptRoutes = [
        'configuracoes.*',
        'users.*',
        'auditoria.*',
        'profile.*',
        'dashboard',
        'logout',
        'encarregados.*',
        'radar.*',
        'pauta-certificada.*',
        'backups.*',
        'queue.*',
        'notificacoes.*',
        'pesquisa.global',
        'regras-avaliacao',
        'executivo-ia.*',
        'salas.*',
        'turnos.*',
        'ocupacao.*',
        'secretaria.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se a rota está nas exceções
        if ($this->isExempt($request)) {
            return $next($request);
        }

        // Verificar se existe pelo menos um ano lectivo
        if (!AnoLectivo::exists()) {
            // Log de tentativa de acesso sem ano lectivo
            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'rota' => $request->path(),
                    'metodo' => $request->method(),
                    'ip' => $request->ip(),
                ])
                ->log('Tentativa de acesso bloqueada: Nenhum ano lectivo registado');

            // Se for requisição AJAX, retornar JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Ano lectivo não encontrado',
                    'message' => 'É necessário registar pelo menos um ano lectivo antes de acessar esta funcionalidade.',
                    'redirect' => route('configuracoes.index'),
                ], 403);
            }

            // Redirecionar para configurações (texto simples — SweetAlert não interpreta HTML)
            return redirect()
                ->route('configuracoes.index')
                ->with('warning', 'Ano lectivo obrigatório: crie pelo menos um ano lectivo em «Gestão de Anos Lectivos» antes de usar esta funcionalidade.')
                ->with('highlight_ano_lectivo', true);
        }

        return $next($request);
    }

    /**
     * Verifica se a rota atual está nas exceções
     */
    protected function isExempt(Request $request): bool
    {
        foreach ($this->exemptRoutes as $route) {
            if ($request->routeIs($route)) {
                return true;
            }
        }

        return false;
    }
}
