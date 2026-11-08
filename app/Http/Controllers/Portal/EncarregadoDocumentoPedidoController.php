<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\DocumentoPedido;
use App\Services\Portal\EncarregadoPortalAcessoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EncarregadoDocumentoPedidoController extends Controller
{
    public function __construct(
        private readonly EncarregadoPortalAcessoService $acesso,
    ) {
        $this->middleware(function ($request, $next) {
            if (! $request->user()?->hasRole('encarregado') || ! $request->user()->encarregado_id) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        $filhos = $this->acesso->filhos($encarregado);
        $pedidos = DocumentoPedido::query()
            ->with(['aluno:id,nome,turma_id', 'aluno.turma:id,codigo,nome'])
            ->where('encarregado_id', $encarregado->id)
            ->latest()
            ->paginate(20);

        return view('portal.encarregado.documentos', [
            'encarregado' => $encarregado,
            'filhos' => $filhos,
            'pedidos' => $pedidos,
            'tipos' => DocumentoPedido::tipos(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());

        $validated = $request->validate([
            'aluno_id' => ['required', 'integer', 'exists:alunos,id'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(DocumentoPedido::tipos()))],
            'observacao' => ['nullable', 'string', 'max:500'],
        ]);

        $aluno = Aluno::findOrFail($validated['aluno_id']);
        $this->acesso->assertPodeVer($encarregado, $aluno);

        $duplicado = DocumentoPedido::query()
            ->where('aluno_id', $aluno->id)
            ->where('encarregado_id', $encarregado->id)
            ->where('tipo', $validated['tipo'])
            ->whereIn('estado', [
                DocumentoPedido::ESTADO_PENDENTE,
                DocumentoPedido::ESTADO_EM_PREPARACAO,
            ])
            ->exists();

        if ($duplicado) {
            return back()
                ->withInput()
                ->with('error', 'Já tem um pedido deste documento em curso para este educando.');
        }

        DocumentoPedido::create([
            'aluno_id' => $aluno->id,
            'encarregado_id' => $encarregado->id,
            'solicitado_por' => $request->user()->id,
            'tipo' => $validated['tipo'],
            'estado' => DocumentoPedido::ESTADO_PENDENTE,
            'observacao' => $validated['observacao'] ?? null,
        ]);

        return redirect()
            ->route('portal.encarregado.documentos.index')
            ->with('success', 'Pedido enviado à secretaria. Acompanhe o estado nesta página.');
    }

    public function cancelar(Request $request, DocumentoPedido $pedido): RedirectResponse
    {
        $encarregado = $this->acesso->encarregadoDoUser($request->user());
        abort_unless((int) $pedido->encarregado_id === (int) $encarregado->id, 403);
        abort_unless($pedido->podeCancelar(), 422, 'Este pedido já não pode ser cancelado.');

        $pedido->update([
            'estado' => DocumentoPedido::ESTADO_CANCELADO,
            'atendido_em' => now(),
        ]);

        return back()->with('success', 'Pedido cancelado.');
    }
}
