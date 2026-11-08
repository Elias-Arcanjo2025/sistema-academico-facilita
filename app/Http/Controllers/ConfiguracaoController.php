<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Services\ConfiguracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ConfiguracaoController extends Controller
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService
    ) {}

    /**
     * Pagina principal de configuracoes
     */
    public function index(): View
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $configuracoes = $this->configuracaoService->getAllGrouped();

        $anosLectivos = \App\Models\AnoLectivo::query()
            ->withCount(['turmas', 'alunos', 'notas'])
            ->orderByDesc('nome')
            ->get();

        return view('configuracoes.index', [
            'configuracoes' => $configuracoes,
            'anosLectivos' => $anosLectivos,
            'ensinoPerfis' => \App\Support\EnsinoPerfil::perfisActivos(),
            'ensinoRotulos' => \App\Support\EnsinoPerfil::todosRotulos(),
            'ensinoFonte' => \App\Support\EnsinoPerfil::fonte(),
            'ensinoClasses' => \App\Support\EnsinoPerfil::classesPermitidas(),
        ]);
    }

    /**
     * Actualizar perfis de ensino e sincronizar classes activas.
     */
    public function updateEnsino(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $validos = array_keys(config('ensino.mapa', []));

        $validated = $request->validate([
            'perfis' => ['required', 'array', 'min:1'],
            'perfis.*' => ['string', 'in:'.implode(',', $validos)],
        ], [
            'perfis.required' => 'Seleccione pelo menos um perfil de ensino.',
            'perfis.min' => 'Seleccione pelo menos um perfil de ensino.',
            'perfis.*.in' => 'Perfil de ensino inválido.',
        ]);

        $resultado = \App\Support\EnsinoPerfil::gravarESincronizar($validated['perfis']);

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'perfis' => $validated['perfis'],
                'classes' => $resultado['permitidas'],
            ])
            ->log('Perfis de ensino actualizados');

        $lista = implode(', ', array_map(fn ($n) => $n.'ª', $resultado['permitidas']));

        return redirect()->route('configuracoes.index')
            ->with('success', "Perfis de ensino actualizados. Classes activas: {$lista}.");
    }

    /**
     * Atualizar trimestre actual
     */
    public function updateTrimestre(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $request->validate([
            'trimestre_actual' => 'required|integer|min:1|max:3',
        ], [
            'trimestre_actual.required' => 'O trimestre é obrigatório.',
            'trimestre_actual.min' => 'O trimestre deve ser entre 1 e 3.',
            'trimestre_actual.max' => 'O trimestre deve ser entre 1 e 3.',
        ]);

        $this->configuracaoService->set('trimestre_actual', $request->input('trimestre_actual'));

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['trimestre_actual' => $request->input('trimestre_actual')])
            ->log('Trimestre actual actualizado');

        return redirect()->route('configuracoes.index')
            ->with('success', 'Trimestre actual actualizado com sucesso!');
    }

    /**
     * Atualizar configurações do Exame de Recurso (faixa de notas + máximo de disciplinas).
     */
    public function updateRecurso(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $validated = $request->validate([
            'recurso_nota_min'        => 'required|integer|min:0|max:19',
            'recurso_nota_max'        => 'required|integer|min:1|max:19|gte:recurso_nota_min',
            'recurso_max_disciplinas' => 'required|integer|min:1|max:10',
        ], [
            'recurso_nota_max.gte' => 'A nota máxima deve ser ≥ à nota mínima.',
            'recurso_max_disciplinas.min' => 'O máximo de disciplinas deve ser ≥ 1.',
        ]);

        $this->configuracaoService->updateMany($validated);

        activity()
            ->causedBy(auth()->user())
            ->withProperties($validated)
            ->log('Configurações de Exame de Recurso actualizadas');

        return redirect()->route('configuracoes.index')
            ->with('success', 'Configurações de Recurso actualizadas com sucesso!');
    }

    /**
     * Atualizar configurações do Exame de Melhoria de Nota (Art.º 34.º DE 424/25).
     */
    public function updateMelhoria(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $validated = $request->validate([
            'melhoria_nota_min'        => 'required|integer|min:0|max:19',
            'melhoria_nota_max'        => 'required|integer|min:1|max:19|gte:melhoria_nota_min',
            'melhoria_max_disciplinas' => 'required|integer|min:0|max:20',
        ], [
            'melhoria_nota_max.gte' => 'A nota máxima deve ser ≥ à nota mínima.',
            'melhoria_max_disciplinas.min' => 'Use 0 para "sem limite".',
        ]);

        $this->configuracaoService->updateMany($validated);

        activity()
            ->causedBy(auth()->user())
            ->withProperties($validated)
            ->log('Configurações de Exame de Melhoria actualizadas');

        return redirect()->route('configuracoes.index')
            ->with('success', 'Configurações de Melhoria actualizadas com sucesso!');
    }

    /**
     * Atualizar configuracoes de avaliacao
     */
    public function updateAvaliacao(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $request->validate([
            'nota_minima_aprovacao' => 'required|numeric|min:0|max:20',
            'tipo_calculo_media' => 'required|in:aritmetica,ponderada',
            'peso_t1' => 'required_if:tipo_calculo_media,ponderada|numeric|min:0|max:10',
            'peso_t2' => 'required_if:tipo_calculo_media,ponderada|numeric|min:0|max:10',
            'peso_t3' => 'required_if:tipo_calculo_media,ponderada|numeric|min:0|max:10',
            'max_disciplinas_reprovacao' => 'required|integer|min:0|max:30',
            'max_disciplinas_sem_nota_desistente' => 'required|integer|min:0|max:30',
            'resultado_12_max_negativas_recurso' => 'required|integer|min:0|max:30',
            'resultado_max_negativas_recuperacao' => 'required|integer|min:0|max:30',
            'exames_permitir_lancamento_nao_elegivel' => 'nullable|boolean',
        ]);

        $pesos = implode(',', [
            $request->input('peso_t1', 1),
            $request->input('peso_t2', 1),
            $request->input('peso_t3', 1),
        ]);

        $this->configuracaoService->updateMany([
            'nota_minima_aprovacao' => $request->input('nota_minima_aprovacao'),
            'tipo_calculo_media' => $request->input('tipo_calculo_media'),
            'pesos_trimestres' => $pesos,
            'max_disciplinas_reprovacao' => (int) $request->input('max_disciplinas_reprovacao'),
            'max_disciplinas_sem_nota_desistente' => (int) $request->input('max_disciplinas_sem_nota_desistente'),
            'resultado_12_max_negativas_recurso' => (int) $request->input('resultado_12_max_negativas_recurso'),
            'resultado_max_negativas_recuperacao' => (int) $request->input('resultado_max_negativas_recuperacao'),
            'exames_permitir_lancamento_nao_elegivel' => $request->boolean('exames_permitir_lancamento_nao_elegivel') ? '1' : '0',
        ]);

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'nota_minima_aprovacao' => $request->input('nota_minima_aprovacao'),
                'max_disciplinas_reprovacao' => (int) $request->input('max_disciplinas_reprovacao'),
                'max_disciplinas_sem_nota_desistente' => (int) $request->input('max_disciplinas_sem_nota_desistente'),
                'resultado_12_max_negativas_recurso' => (int) $request->input('resultado_12_max_negativas_recurso'),
                'resultado_max_negativas_recuperacao' => (int) $request->input('resultado_max_negativas_recuperacao'),
            ])
            ->log('Configurações de avaliação actualizadas');

        return back()->with('success', 'Configuracoes de avaliacao actualizadas com sucesso.');
    }

    public function updatePortal(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->can('notas.publicar') || $request->user()->hasRole('admin_full'),
            403
        );

        $request->validate([
            'portal_aluno_notas_activo' => 'nullable|boolean',
            'portal_aluno_mensagem_bloqueio' => 'nullable|string|max:1000',
        ]);

        $this->configuracaoService->updateMany([
            'portal_aluno_notas_activo' => $request->boolean('portal_aluno_notas_activo') ? '1' : '0',
            'portal_aluno_mensagem_bloqueio' => $request->input('portal_aluno_mensagem_bloqueio'),
        ]);

        return back()->with('success', 'Publicação de notas no portal actualizada.');
    }

    public function updateSeguranca(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $request->validate([
            'bloqueio_inactividade_activo' => 'nullable|boolean',
            'bloqueio_inactividade_minutos' => 'required|integer|min:1|max:480',
        ], [
            'bloqueio_inactividade_minutos.min' => 'O tempo mínimo é 1 minuto.',
            'bloqueio_inactividade_minutos.max' => 'O tempo máximo é 480 minutos (8 horas).',
        ]);

        $this->configuracaoService->updateMany([
            'bloqueio_inactividade_activo' => $request->boolean('bloqueio_inactividade_activo') ? '1' : '0',
            'bloqueio_inactividade_minutos' => (string) $request->input('bloqueio_inactividade_minutos'),
        ]);

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'bloqueio_inactividade_activo' => $request->boolean('bloqueio_inactividade_activo'),
                'bloqueio_inactividade_minutos' => $request->input('bloqueio_inactividade_minutos'),
            ])
            ->log('Configurações de bloqueio por inactividade actualizadas');

        return back()->with('success', 'Configurações de segurança actualizadas.');
    }

    /**
     * Atualizar periodos de lancamento de notas
     */
    public function updatePeriodosLancamento(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $request->validate([
            'lancamento_t1_inicio' => 'nullable|date',
            'lancamento_t1_fim' => 'nullable|date|after_or_equal:lancamento_t1_inicio',
            'lancamento_t2_inicio' => 'nullable|date',
            'lancamento_t2_fim' => 'nullable|date|after_or_equal:lancamento_t2_inicio',
            'lancamento_t3_inicio' => 'nullable|date',
            'lancamento_t3_fim' => 'nullable|date|after_or_equal:lancamento_t3_inicio',
        ]);

        $this->configuracaoService->updateMany([
            'lancamento_t1_inicio' => $request->input('lancamento_t1_inicio'),
            'lancamento_t1_fim' => $request->input('lancamento_t1_fim'),
            'lancamento_t2_inicio' => $request->input('lancamento_t2_inicio'),
            'lancamento_t2_fim' => $request->input('lancamento_t2_fim'),
            'lancamento_t3_inicio' => $request->input('lancamento_t3_inicio'),
            'lancamento_t3_fim' => $request->input('lancamento_t3_fim'),
        ]);

        return back()->with('success', 'Periodos de lancamento actualizados com sucesso.');
    }

    /**
     * Atualizar dados da instituicao
     */
    public function updateInstituicao(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $request->validate([
            'instituicao_nome' => 'required|string|max:255',
            'instituicao_logo' => 'nullable|image|mimes:jpeg,png,gif|max:2048',
            'instituicao_endereco' => 'nullable|string|max:500',
            'instituicao_telefone' => 'nullable|string|max:50',
            'instituicao_email' => 'nullable|email|max:255',
            'instituicao_website' => 'nullable|url|max:255',
            'director_geral_nome' => 'nullable|string|max:255',
        ]);

        $dados = [
            'instituicao_nome' => $request->input('instituicao_nome'),
            'instituicao_endereco' => $request->input('instituicao_endereco'),
            'instituicao_telefone' => $request->input('instituicao_telefone'),
            'instituicao_email' => $request->input('instituicao_email'),
            'instituicao_website' => $request->input('instituicao_website'),
            'director_geral_nome' => $request->input('director_geral_nome'),
        ];

        // Upload do logo
        if ($request->hasFile('instituicao_logo')) {
            $logoAntigo = $this->configuracaoService->get('instituicao_logo');
            if ($logoAntigo && Storage::disk('public')->exists($logoAntigo)) {
                Storage::disk('public')->delete($logoAntigo);
            }

            $path = $request->file('instituicao_logo')->store('logos', 'public');
            $dados['instituicao_logo'] = $path;
        }

        $this->configuracaoService->updateMany($dados);

        return back()->with('success', 'Dados da instituicao actualizados com sucesso.');
    }

    /**
     * Atualizar configurações de layout de PDF
     */
    public function updatePdf(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $validated = $request->validate([
            // Margens (0-50mm)
            'pdf_margin_top' => 'required|integer|min:0|max:50',
            'pdf_margin_right' => 'required|integer|min:0|max:50',
            'pdf_margin_bottom' => 'required|integer|min:0|max:50',
            'pdf_margin_left' => 'required|integer|min:0|max:50',

            // Fontes
            'pdf_font_family' => 'required|string|in:DejaVu Sans,Helvetica,Times-Roman,Courier',
            'pdf_font_size_body' => 'required|integer|min:6|max:16',
            'pdf_font_size_header' => 'required|integer|min:8|max:24',
            'pdf_font_size_table' => 'required|numeric|min:6|max:14',

            // Cores (hexadecimal)
            'pdf_color_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'pdf_color_table_header' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // Papel
            'pdf_paper_size' => 'required|in:A4,Letter',
            'pdf_paper_orientation' => 'required|in:portrait,landscape',

            // Logo
            'pdf_logo_width' => 'required|integer|min:30|max:150',
        ], [
            'pdf_margin_*.required' => 'Todas as margens são obrigatórias.',
            'pdf_margin_*.min' => 'A margem mínima é 0mm.',
            'pdf_margin_*.max' => 'A margem máxima é 50mm.',
            'pdf_font_family.in' => 'Fonte não suportada pelo DomPDF.',
            'pdf_color_*.regex' => 'A cor deve estar no formato hexadecimal (#000000 a #FFFFFF).',
        ]);

        $this->configuracaoService->updateMany($validated);

        return back()->with('success', 'Configurações de PDF actualizadas com sucesso.');
    }

    /**
     * Remover logo da instituicao
     */
    public function removerLogo(): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $logo = $this->configuracaoService->get('instituicao_logo');
        if ($logo && Storage::disk('public')->exists($logo)) {
            Storage::disk('public')->delete($logo);
        }

        $this->configuracaoService->set('instituicao_logo', null);

        return back()->with('success', 'Logo removido com sucesso.');
    }

    /**
     * Limpar cache de configuracoes
     */
    public function limparCache(): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $this->configuracaoService->clearCache();

        activity()
            ->causedBy(auth()->user())
            ->log('Cache de configuracoes limpo');

        return back()->with('success', 'Cache limpo com sucesso.');
    }

    /**
     * Criar novo ano lectivo
     */
    public function storeAnoLectivo(Request $request): RedirectResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $validated = $request->validate([
            'nome' => ['required', 'regex:/^\d{4}-\d{4}$/', 'unique:anos_lectivos,nome'],
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after:data_inicio',
        ], [
            'nome.required' => 'O nome do ano lectivo é obrigatório.',
            'nome.regex' => 'O formato deve ser YYYY-YYYY (ex: 2025-2026).',
            'nome.unique' => 'Este ano lectivo já existe.',
            'data_fim.after' => 'A data de fim deve ser posterior à data de início.',
        ]);

        // Criar com estado = NOVO (2)
        AnoLectivo::create([
            ...$validated,
            'estado' => AnoLectivo::ESTADO_NOVO,
        ]);

        activity()
            ->causedBy(auth()->user())
            ->withProperties($validated)
            ->log('Novo ano lectivo criado');

        return redirect()->route('configuracoes.index')
            ->with('success', 'Ano lectivo criado com sucesso!');
    }

    /**
     * Activar ano lectivo
     */
    public function activarAnoLectivo(Request $request): JsonResponse
    {
        $this->authorize('gerirConfiguracoes', \App\Models\User::class);

        $validated = $request->validate([
            'nome' => 'required|exists:anos_lectivos,nome',
        ]);

        // Buscar ano que será ativado
        $anoParaAtivar = AnoLectivo::where('nome', $validated['nome'])->first();

        if (!$anoParaAtivar) {
            return response()->json([
                'success' => false,
                'message' => 'Ano lectivo não encontrado.'
            ], 404);
        }

        // Verificar se já é o ano actual
        if ($anoParaAtivar->isActual()) {
            return response()->json([
                'success' => false,
                'message' => 'Este ano lectivo já está activo.'
            ], 422);
        }

        // Verificar ano actual e coletar estatísticas
        $anoActual = AnoLectivo::getActual();
        $avisos = [];

        if ($anoActual) {
            $turmasActuais = $anoActual->turmas()->count();
            $alunosActuais = \App\Models\Aluno::whereHas('turma', function($q) use ($anoActual) {
                $q->where('ano_lectivo_id', $anoActual->id);
            })->count();
            $notasActuais = $anoActual->notas()->count();

            if ($turmasActuais > 0 || $alunosActuais > 0 || $notasActuais > 0) {
                $avisos[] = "Ano actual ({$anoActual->nome}) possui: {$turmasActuais} turmas, {$alunosActuais} alunos, {$notasActuais} notas.";
            }
        }

        try {
            AnoLectivo::setActual($validated['nome']);

            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'ano_novo' => $validated['nome'],
                    'ano_anterior' => $anoActual?->nome,
                    'avisos' => $avisos,
                ])
                ->log('Ano lectivo activado');

            return response()->json([
                'success' => true,
                'message' => 'Ano lectivo activado com sucesso!',
                'avisos' => $avisos,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao activar ano lectivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao activar ano lectivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
