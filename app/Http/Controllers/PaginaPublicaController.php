<?php

namespace App\Http\Controllers;

use App\Services\ConfiguracaoService;
use App\Support\PaginaPublicaConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaginaPublicaController extends Controller
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('configuracoes.pagina-publica.identidade');
    }

    public function identidade(): View
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        return view('configuracoes.pagina-publica.identidade', $this->dadosView());
    }

    public function textos(): View
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        return view('configuracoes.pagina-publica.textos', $this->dadosView());
    }

    public function carousel(): View
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        return view('configuracoes.pagina-publica.carousel', $this->dadosView());
    }

    public function opcoes(): View
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        return view('configuracoes.pagina-publica.opcoes', $this->dadosView());
    }

    public function updateIdentidade(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $corRule = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        $request->validate([
            'pagina_publica_cor_primaria' => $corRule,
            'pagina_publica_cor_primaria_escura' => $corRule,
            'pagina_publica_cor_secundaria' => $corRule,
        ]);

        $this->configuracaoService->updateMany([
            'pagina_publica_cor_primaria' => $request->input('pagina_publica_cor_primaria'),
            'pagina_publica_cor_primaria_escura' => $request->input('pagina_publica_cor_primaria_escura'),
            'pagina_publica_cor_secundaria' => $request->input('pagina_publica_cor_secundaria'),
        ]);

        return back()->with('success', 'Identidade visual actualizada.');
    }

    public function updateTextos(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $request->validate([
            'pagina_publica_titulo' => 'nullable|string|max:120',
            'pagina_publica_destaque' => 'nullable|string|max:120',
            'pagina_publica_subtitulo' => 'nullable|string|max:500',
            'pagina_publica_badge' => 'nullable|string|max:120',
            'pagina_publica_sobre_titulo' => 'nullable|string|max:160',
            'pagina_publica_sobre_texto' => 'nullable|string|max:2000',
            'pagina_publica_sobre_itens' => 'nullable|string|max:2000',
            'pagina_publica_func_titulo' => 'nullable|string|max:160',
            'pagina_publica_func_subtitulo' => 'nullable|string|max:300',
            'pagina_publica_alunos_titulo' => 'nullable|string|max:160',
            'pagina_publica_alunos_subtitulo' => 'nullable|string|max:400',
            'pagina_publica_cta_titulo' => 'nullable|string|max:200',
            'pagina_publica_cta_texto' => 'nullable|string|max:400',
            'pagina_publica_cta_aluno_titulo' => 'nullable|string|max:120',
            'pagina_publica_cta_aluno_texto' => 'nullable|string|max:300',
            'pagina_publica_cta_escola_titulo' => 'nullable|string|max:120',
            'pagina_publica_cta_escola_texto' => 'nullable|string|max:300',
            'pagina_publica_footer_texto' => 'nullable|string|max:400',
            'pagina_publica_meta_descricao' => 'nullable|string|max:300',
        ]);

        $this->configuracaoService->updateMany([
            'pagina_publica_titulo' => $request->input('pagina_publica_titulo'),
            'pagina_publica_destaque' => $request->input('pagina_publica_destaque'),
            'pagina_publica_subtitulo' => $request->input('pagina_publica_subtitulo'),
            'pagina_publica_badge' => $request->input('pagina_publica_badge'),
            'pagina_publica_sobre_titulo' => $request->input('pagina_publica_sobre_titulo'),
            'pagina_publica_sobre_texto' => $request->input('pagina_publica_sobre_texto'),
            'pagina_publica_sobre_itens' => $request->input('pagina_publica_sobre_itens'),
            'pagina_publica_func_titulo' => $request->input('pagina_publica_func_titulo'),
            'pagina_publica_func_subtitulo' => $request->input('pagina_publica_func_subtitulo'),
            'pagina_publica_alunos_titulo' => $request->input('pagina_publica_alunos_titulo'),
            'pagina_publica_alunos_subtitulo' => $request->input('pagina_publica_alunos_subtitulo'),
            'pagina_publica_cta_titulo' => $request->input('pagina_publica_cta_titulo'),
            'pagina_publica_cta_texto' => $request->input('pagina_publica_cta_texto'),
            'pagina_publica_cta_aluno_titulo' => $request->input('pagina_publica_cta_aluno_titulo'),
            'pagina_publica_cta_aluno_texto' => $request->input('pagina_publica_cta_aluno_texto'),
            'pagina_publica_cta_escola_titulo' => $request->input('pagina_publica_cta_escola_titulo'),
            'pagina_publica_cta_escola_texto' => $request->input('pagina_publica_cta_escola_texto'),
            'pagina_publica_footer_texto' => $request->input('pagina_publica_footer_texto'),
            'pagina_publica_meta_descricao' => $request->input('pagina_publica_meta_descricao'),
        ]);

        return back()->with('success', 'Textos da página pública actualizados.');
    }

    public function updateCarousel(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $rules = [
            'pagina_publica_carousel_activo' => 'nullable|boolean',
            'pagina_publica_carousel_intervalo' => 'nullable|integer|min:2|max:30',
        ];

        foreach (range(1, PaginaPublicaConfig::CAROUSEL_SLOTS) as $i) {
            $rules["carousel_imagem_{$i}"] = 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120';
            $rules["pagina_publica_carousel_{$i}_titulo"] = 'nullable|string|max:120';
            $rules["pagina_publica_carousel_{$i}_texto"] = 'nullable|string|max:300';
            $rules["pagina_publica_carousel_{$i}_largura"] = 'nullable|integer|min:200|max:1400';
            $rules["pagina_publica_carousel_{$i}_altura"] = 'nullable|integer|min:150|max:900';
        }

        $request->validate($rules);

        $activo = $request->boolean('pagina_publica_carousel_activo');

        $dados = [
            'pagina_publica_carousel_activo' => $activo ? '1' : '0',
            'pagina_publica_carousel_intervalo' => (string) ($request->input('pagina_publica_carousel_intervalo') ?: 5),
        ];

        foreach (range(1, PaginaPublicaConfig::CAROUSEL_SLOTS) as $i) {
            $dados["pagina_publica_carousel_{$i}_titulo"] = $request->input("pagina_publica_carousel_{$i}_titulo");
            $dados["pagina_publica_carousel_{$i}_texto"] = $request->input("pagina_publica_carousel_{$i}_texto");
            $dados["pagina_publica_carousel_{$i}_largura"] = $request->input("pagina_publica_carousel_{$i}_largura")
                ?: (string) PaginaPublicaConfig::CAROUSEL_LARGURA_PADRAO;
            $dados["pagina_publica_carousel_{$i}_altura"] = $request->input("pagina_publica_carousel_{$i}_altura")
                ?: (string) PaginaPublicaConfig::CAROUSEL_ALTURA_PADRAO;

            if ($request->hasFile("carousel_imagem_{$i}")) {
                $antigo = $this->configuracaoService->get("pagina_publica_carousel_{$i}_imagem");
                if ($antigo && Storage::disk('public')->exists($antigo)) {
                    Storage::disk('public')->delete($antigo);
                }

                $path = $request->file("carousel_imagem_{$i}")->store('pagina-publica/carousel', 'public');
                $dados["pagina_publica_carousel_{$i}_imagem"] = $path;
            }
        }

        if ($activo) {
            $comImagem = 0;
            foreach (range(1, PaginaPublicaConfig::CAROUSEL_SLOTS) as $i) {
                $path = $dados["pagina_publica_carousel_{$i}_imagem"]
                    ?? $this->configuracaoService->get("pagina_publica_carousel_{$i}_imagem");
                if ($path && Storage::disk('public')->exists($path)) {
                    $comImagem++;
                }
            }
            if ($comImagem < PaginaPublicaConfig::CAROUSEL_MINIMO) {
                return back()->withInput()->with('error',
                    'Para activar o carousel são necessárias pelo menos '.PaginaPublicaConfig::CAROUSEL_MINIMO.' imagens.');
            }
        }

        $this->configuracaoService->updateMany($dados);

        return back()->with('success', 'Carousel actualizado com sucesso.');
    }

    public function updateOpcoes(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $request->validate([
            'pagina_publica_mostrar_ano_lectivo' => 'nullable|boolean',
            'pagina_publica_mostrar_contactos' => 'nullable|boolean',
            'pagina_publica_usar_logo_instituicao' => 'nullable|boolean',
            'pagina_publica_mostrar_registo' => 'nullable|boolean',
            'registo_publico_activo' => 'nullable|boolean',
        ]);

        $this->configuracaoService->updateMany([
            'pagina_publica_mostrar_ano_lectivo' => $request->boolean('pagina_publica_mostrar_ano_lectivo') ? '1' : '0',
            'pagina_publica_mostrar_contactos' => $request->boolean('pagina_publica_mostrar_contactos') ? '1' : '0',
            'pagina_publica_usar_logo_instituicao' => $request->boolean('pagina_publica_usar_logo_instituicao') ? '1' : '0',
            'pagina_publica_mostrar_registo' => $request->boolean('pagina_publica_mostrar_registo') ? '1' : '0',
            'registo_publico_activo' => $request->boolean('registo_publico_activo') ? '1' : '0',
        ]);

        return back()->with('success', 'Opções de apresentação actualizadas.');
    }

    public function removerCarouselImagem(int $slide): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        if ($slide < 1 || $slide > PaginaPublicaConfig::CAROUSEL_SLOTS) {
            abort(404);
        }

        $chave = "pagina_publica_carousel_{$slide}_imagem";
        $path = $this->configuracaoService->get($chave);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $this->configuracaoService->set($chave, '');

        return back()->with('success', "Imagem {$slide} removida.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function dadosView(): array
    {
        $configuracoes = $this->configuracaoService->getAllGrouped();

        return [
            'configuracoes' => $configuracoes,
            'pp' => $configuracoes['pagina_publica'] ?? [],
            'carousel' => $configuracoes['pagina_publica_carousel'] ?? [],
        ];
    }
}
