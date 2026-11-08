<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\DocumentoPedido;
use App\Notifications\StaffAlertaAcademicoNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentoPedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:secretaria.ver');
    }

    public function index(Request $request): View
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $estado = $request->string('estado')->toString();
        $estadosFila = [
            DocumentoPedido::ESTADO_PENDENTE,
            DocumentoPedido::ESTADO_EM_PREPARACAO,
            DocumentoPedido::ESTADO_PRONTO,
        ];

        $query = DocumentoPedido::query()
            ->with([
                'aluno:id,nome,numero_processo,turma_id',
                'aluno.turma:id,codigo,nome',
                'encarregado:id,nome,telefone',
                'solicitadoPor:id,name',
                'atendidoPor:id,name',
            ])
            ->latest();

        if ($estado !== '' && array_key_exists($estado, DocumentoPedido::estados())) {
            $query->where('estado', $estado);
        } else {
            $query->whereIn('estado', $estadosFila);
            $estado = '';
        }

        $pedidos = $query->paginate(25)->withQueryString();
        $contagens = DocumentoPedido::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('secretaria.documentos.pedidos', [
            'pedidos' => $pedidos,
            'estado' => $estado,
            'contagens' => $contagens,
            'tipos' => DocumentoPedido::tipos(),
            'estados' => DocumentoPedido::estados(),
        ]);
    }

    public function actualizar(Request $request, DocumentoPedido $pedido): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $validated = $request->validate([
            'estado' => ['required', 'in:'.implode(',', array_keys(DocumentoPedido::estados()))],
            'nota_secretaria' => ['nullable', 'string', 'max:500'],
        ]);

        $estadoAnterior = $pedido->estado;
        $pedido->estado = $validated['estado'];
        $pedido->nota_secretaria = $validated['nota_secretaria'] ?? $pedido->nota_secretaria;

        if (in_array($validated['estado'], [
            DocumentoPedido::ESTADO_PRONTO,
            DocumentoPedido::ESTADO_ENTREGUE,
            DocumentoPedido::ESTADO_CANCELADO,
        ], true)) {
            $pedido->atendido_por = $request->user()->id;
            $pedido->atendido_em = now();
        } elseif ($validated['estado'] === DocumentoPedido::ESTADO_EM_PREPARACAO) {
            $pedido->atendido_por = $request->user()->id;
        }

        $pedido->save();

        if (
            $estadoAnterior !== DocumentoPedido::ESTADO_PRONTO
            && $pedido->estado === DocumentoPedido::ESTADO_PRONTO
        ) {
            $this->notificarEncarregadoPronto($pedido);
        }

        return back()->with('success', 'Pedido actualizado.');
    }

    private function notificarEncarregadoPronto(DocumentoPedido $pedido): void
    {
        $pedido->loadMissing(['aluno:id,nome', 'encarregado.user']);
        $user = $pedido->encarregado?->user;
        if (! $user) {
            return;
        }

        try {
            $user->notify(new StaffAlertaAcademicoNotification('documento_pedido_pronto', [
                'titulo' => 'Documento pronto para levantar',
                'mensagem' => ($pedido->tipoLabel()).' de '.$pedido->aluno?->nome.' está pronto na secretaria.',
                'url' => route('portal.encarregado.documentos.index'),
                'tipo_evento' => 'documento_pedido',
            ]));
        } catch (\Throwable) {
        }
    }
}
