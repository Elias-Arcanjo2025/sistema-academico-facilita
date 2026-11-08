<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Services\BoletimLayoutService;
use App\Services\ConfiguracaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoletimConfigController extends Controller
{
    public function __construct(
        private readonly BoletimLayoutService $layout,
        private readonly ConfiguracaoService $configuracao,
    ) {
        $this->middleware('can:secretaria.ver');
    }

    public function configurar(): View
    {
        $this->assertPodeConfigurar();

        return view('secretaria.boletim.configurar', [
            'config' => $this->layout->getConfig(),
            'modelos' => BoletimLayoutService::MODELOS,
            'instituicao' => $this->configuracao->getInstituicao(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->assertPodeConfigurar();

        $validated = $request->validate([
            'modelo' => ['required', 'in:'.implode(',', array_keys(BoletimLayoutService::MODELOS))],
            'cor_primaria' => ['required', 'string', 'max:20'],
            'cor_secundaria' => ['required', 'string', 'max:20'],
            'cor_tabela' => ['required', 'string', 'max:20'],
        ]);

        $this->layout->guardar($validated);

        return back()->with('success', 'Modelo e cores do boletim guardados. Os próximos PDFs usam esta personalização.');
    }

    private function assertPodeConfigurar(): void
    {
        $user = auth()->user();
        abort_unless(
            $user
            && (
                $user->can('secretaria.cartao.configurar')
                || $user->can('secretaria.documentos.emitir')
                || $user->temControloAcademicoInstitucional()
            ),
            403
        );
    }
}
