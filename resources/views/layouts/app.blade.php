<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Facilita') . ' - Sistema Académico')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/facilitaicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/facilitaicon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Custom CSS (extracted from inline styles for better maintainability) -->
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">

    <!-- App CSS (pre-compiled) -->
    <link href="{{ asset('css/app-compiled.css') }}" rel="stylesheet">
    <link href="{{ asset('css/design-system.css') }}" rel="stylesheet">
    @include('layouts.partials.pwa-head')
    @include('layouts.partials.tema-instituicao')
    @stack('styles')

    @include('layouts.partials.theme-init')
</head>
<body @auth data-pesquisa-url="{{ route('pesquisa.global') }}" @endauth>
    @auth
        @include('layouts.partials.pwa-install-staff')
        @include('layouts.partials.command-palette')
    @endauth
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="{{ route('dashboard') }}" class="sidebar-brand">
                <img src="{{ asset('images/facilita.png') }}" alt="Facilita" class="sidebar-brand-logo sidebar-brand-logo--full">
                <img src="{{ asset('images/facilita2.png') }}" alt="Facilita" class="sidebar-brand-logo sidebar-brand-logo--collapsed">
                <img src="{{ asset('images/facilitaicon.png') }}" alt="Facilita" class="sidebar-brand-logo sidebar-brand-logo--mobile">
            </a>
            <button class="sidebar-toggle-btn" id="sidebarToggle" title="Minimizar/Maximizar">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>

        <nav class="sidebar-menu" id="sidebarMenu">
            @auth
                @php
                    $user = auth()->user();
                    $hasAnoLectivo = \App\Models\AnoLectivo::exists();
                    $criarAnoLectivoUrl = route('configuracoes.index') . '#anos-lectivos-section';
                    $menuRecuperacaoExame = \App\Support\NotaLancamentoRules::utilizadorTemMenuExamePorClasse(
                        $user,
                        \App\Services\RecuperacaoService::CLASSES_RECUPERACAO,
                    );
                    $menuRecursoExame = \App\Support\NotaLancamentoRules::utilizadorTemMenuExamePorClasse($user, [12]);
                @endphp

                @if($user->hasRole('aluno'))
                    <a href="{{ route('portal.aluno.dashboard') }}" class="menu-item {{ request()->routeIs('portal.aluno.*') ? 'active' : '' }}">
                        <i class="bi bi-house-door"></i>
                        <span>Portal do Aluno</span>
                    </a>
                    <a href="{{ route('portal.aluno.calendario') }}" class="menu-item {{ request()->routeIs('portal.aluno.calendario') || request()->routeIs('portal.aluno.provas') ? 'active' : '' }}">
                        <i class="bi bi-calendar3"></i>
                        <span>Calendário</span>
                    </a>
                    <a href="{{ route('portal.aluno.materiais') }}" class="menu-item {{ request()->routeIs('portal.aluno.materiais*') ? 'active' : '' }}">
                        <i class="bi bi-folder2-open"></i>
                        <span>Materiais</span>
                    </a>
                    <a href="{{ route('portal.aluno.assiduidade') }}" class="menu-item {{ request()->routeIs('portal.aluno.assiduidade') || request()->routeIs('portal.aluno.ocorrencias') ? 'active' : '' }}">
                        <i class="bi bi-person-check"></i>
                        <span>Assiduidade</span>
                    </a>
                    <a href="{{ route('portal.aluno.perfil') }}" class="menu-item {{ request()->routeIs('portal.aluno.perfil') ? 'active' : '' }}">
                        <i class="bi bi-person-gear"></i>
                        <span>Perfil</span>
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('regras-avaliacao') }}" class="menu-item {{ request()->routeIs('regras-avaliacao') ? 'active' : '' }}">
                        <i class="bi bi-journal-bookmark"></i>
                        <span>Regras de Avaliação</span>
                    </a>
                    @can('assistente.usar')
                    <a href="#" class="menu-item menu-item-assistente" id="menuAssistenteIa" onclick="event.preventDefault(); window.dispatchEvent(new Event('assistente:open'));" title="Ctrl+Shift+A">
                        <i class="bi bi-stars"></i>
                        <span>Assistente IA</span>
                    </a>
                    @endcan
                    @php
                        $podeMsgsMenu = $user->can('turma.comunicar')
                            || $user->temVisaoInstitucional()
                            || $user->getRoleNames()->intersect([
                                'director_turma', 'coordenador_curso', 'coordenador_disciplina',
                                'subdiretor_pedagogico', 'professor', 'secretaria', 'inspetor',
                            ])->isNotEmpty();
                    @endphp
                    @if($podeMsgsMenu)
                        <a href="{{ route('mensagens-encarregados.index') }}"
                           class="menu-item {{ request()->routeIs('mensagens-encarregados.*') || request()->routeIs('mensagens-internas.*') ? 'active' : '' }}"
                           title="Mensagens com encarregados e colaboradores">
                            <i class="bi bi-chat-dots"></i>
                            <span>Mensagens</span>
                        </a>
                    @endif
                    <button type="button" class="menu-item menu-item-atalhos" id="menuAtalhosTeclado" title="Ctrl+/ — ver atalhos de teclado">
                        <i class="bi bi-keyboard"></i>
                        <span>Atalhos de teclado</span>
                    </button>
                    @can('assistente.usar')
                    <a href="{{ route('executivo-ia.index') }}" class="menu-item {{ request()->routeIs('executivo-ia.*') ? 'active' : '' }}">
                        <i class="bi bi-robot"></i>
                        <span>Executivo IA</span>
                    </a>
                    @endcan
                @endif

                @unless($user->hasRole('aluno'))
                {{-- ====================================== --}}
                {{-- AVALIAÇÃO (com submenus) --}}
                {{-- ====================================== --}}
                @canany(['notas.ver', 'notas.lancar', 'mini-pautas.ver', 'pautas.ver', 'conselhos.ver', 'notas.supervisionar', 'presencas.lancar'])
                @php
                    $avaliacaoActive = request()->routeIs('notas.*')
                        || request()->routeIs('mini-pautas.*')
                        || request()->routeIs('pautas.*')
                        || request()->routeIs('conselhos.*')
                        || request()->routeIs('avaliacao-diaria.*');
                @endphp

                <div class="menu-section">
                    <div class="menu-label menu-collapse"
                         data-bs-toggle="collapse"
                         data-bs-target="#menuAvaliacao"
                         aria-expanded="{{ $avaliacaoActive ? 'true' : 'false' }}">
                        <span>
                            <i class="bi bi-clipboard-check me-2"></i>
                            Avaliação
                            @if(!$hasAnoLectivo)
                                <i class="bi bi-lock-fill text-danger ms-2" title="Requer ano lectivo"></i>
                            @endif
                        </span>
                        <i class="bi bi-chevron-right dropdown-icon"></i>
                    </div>
                    <div class="collapse submenu {{ $avaliacaoActive ? 'show' : '' }}" id="menuAvaliacao">
                        @can('presencas.lancar')
                        <a href="{{ route('avaliacao-diaria.index') }}"
                           class="menu-item submenu-item {{ request()->routeIs('avaliacao-diaria.*') ? 'active' : '' }}">
                            <i class="bi bi-person-check"></i>
                            <span>Presenças e avaliações</span>
                        </a>
                        @endcan

                        @can('notas.lancar')
                        <a href="{{ $hasAnoLectivo ? route('notas.lancar') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('notas.lancar') || request()->routeIs('notas.lancamento-lote*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                           @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                            <i class="bi bi-pencil-square"></i>
                            <span>Lançar Notas</span>
                            @if(!$hasAnoLectivo)
                                <i class="bi bi-lock-fill text-warning ms-auto"></i>
                            @endif
                        </a>
                        @endcan
                        @canany(['notas.ver', 'notas.supervisionar'])
                        <a href="{{ $hasAnoLectivo ? route('notas.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('notas.index') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                           @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                            <i class="bi bi-list-check"></i>
                            <span>Consultar Notas</span>
                            @if(!$hasAnoLectivo)
                                <i class="bi bi-lock-fill text-warning ms-auto"></i>
                            @endif
                        </a>
                        @endcanany

                        @can('notas.lancar')
                            {{-- Importação Excel: cargos de gestão (DT acompanha, não importa) --}}
                            @if(auth()->user()->hasAnyRole(['admin_full', 'directora_geral', 'gestor_academico', 'coordenador_curso']))
                                <a href="{{ $hasAnoLectivo ? route('notas.importacao') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('notas.importacao*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-file-earmark-excel text-success"></i>
                                    <span>Importação Excel</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endif
                        @endcan

                        @can('mini-pautas.ver')
                            <a href="{{ $hasAnoLectivo ? route('mini-pautas.index') : $criarAnoLectivoUrl }}"
                               class="menu-item submenu-item {{ request()->routeIs('mini-pautas.index') || request()->routeIs('mini-pautas.show') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                               @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                <i class="bi bi-file-text"></i>
                                <span>Mini-Pautas</span>
                                @if(!$hasAnoLectivo)
                                    <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                @endif
                            </a>

                            @can('notas.lancar')
                                <a href="{{ $hasAnoLectivo ? route('mini-pautas.importacao') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('mini-pautas.importacao*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-file-earmark-excel text-success"></i>
                                    <span>Importação Mini-Pauta</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                        @endcan

                        @can('notas.lancar')
                            <a href="{{ $hasAnoLectivo ? route('exames.elegibilidade') : $criarAnoLectivoUrl }}"
                               class="menu-item submenu-item {{ request()->routeIs('exames.elegibilidade') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                               @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                <i class="bi bi-list-check"></i>
                                <span>Elegibilidade Exames</span>
                                @if(!$hasAnoLectivo)
                                    <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                @endif
                            </a>
                            @if($menuRecuperacaoExame)
                            <a href="{{ $hasAnoLectivo ? route('recuperacoes.index') : $criarAnoLectivoUrl }}"
                               class="menu-item submenu-item {{ request()->routeIs('recuperacoes.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                               @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                <i class="bi bi-bandaid"></i>
                                <span>Recuperação (10.ª/11.ª)</span>
                                @if(!$hasAnoLectivo)
                                    <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                @endif
                            </a>
                            @endif
                            @if($menuRecursoExame)
                            <a href="{{ $hasAnoLectivo ? route('recursos.index') : $criarAnoLectivoUrl }}"
                               class="menu-item submenu-item {{ request()->routeIs('recursos.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                               @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <span>Recurso (12.ª)</span>
                                @if(!$hasAnoLectivo)
                                    <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                @endif
                            </a>
                            @endif
                        @endcan

                        @can('pautas.ver')
                            <a href="{{ $hasAnoLectivo ? route('pautas.index') : $criarAnoLectivoUrl }}"
                               class="menu-item submenu-item {{ request()->routeIs('pautas.*') && !request()->routeIs('pautas.mini-pauta') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                               @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                <i class="bi bi-file-earmark-text"></i>
                                <span>Pautas</span>
                                @if(!$hasAnoLectivo)
                                    <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                @endif
                            </a>
                        @endcan

                        @can('conselhos.ver')
                            <div class="submenu-divider"></div>
                            <a href="{{ $hasAnoLectivo ? route('conselhos.index') : $criarAnoLectivoUrl }}"
                               class="menu-item submenu-item {{ request()->routeIs('conselhos.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                               @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                <i class="bi bi-people-fill"></i>
                                <span>Conselhos de Classe</span>
                                @if(!$hasAnoLectivo)
                                    <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                @endif
                            </a>
                        @endcan
                    </div>
                </div>
                @endcanany

                @if($user->can('pedagogico.ver') || $user->can('materiais.ver') || $user->can('conteudos.ver'))
                @php
                    $pedagogicoActive = request()->routeIs('pedagogico.*') || request()->routeIs('materiais.*');
                @endphp
                <div class="menu-section">
                    <div class="menu-label menu-collapse"
                         data-bs-toggle="collapse"
                         data-bs-target="#menuPedagogico"
                         aria-expanded="{{ $pedagogicoActive ? 'true' : 'false' }}">
                        <span>
                            <i class="bi bi-mortarboard me-2"></i>
                            Pedagógico
                            @if(!$hasAnoLectivo)
                                <i class="bi bi-lock-fill text-danger ms-2" title="Requer ano lectivo"></i>
                            @endif
                        </span>
                        <i class="bi bi-chevron-right dropdown-icon"></i>
                    </div>
                    <div class="collapse submenu {{ $pedagogicoActive ? 'show' : '' }}" id="menuPedagogico">
                        @can('pedagogico.ver')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.planificacoes.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.planificacoes.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-calendar-week"></i>
                            <span>Planificações</span>
                        </a>
                        @endcan
                        @can('pedagogico.dashboard.ver')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.dashboard.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.dashboard.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-graph-up"></i>
                            <span>Dashboard</span>
                        </a>
                        @endcan
                        @if(\App\Services\Pedagogico\PedagogicoAccess::podeAcederCoordenacaoDisciplina(auth()->user(), 'notas.supervisionar'))
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.coordenacao.lancamentos') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.coordenacao.lancamentos') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-clipboard-check"></i>
                            <span>Lançamentos</span>
                        </a>
                        @endif
                        @if(\App\Services\Pedagogico\PedagogicoAccess::podeAcederCoordenacaoDisciplina(auth()->user(), 'relatorios.disciplina'))
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.coordenacao.relatorio') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.coordenacao.relatorio') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-bar-chart"></i>
                            <span>Rendimento</span>
                        </a>
                        @endif
                        @can('orientacoes.ver')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.orientacoes.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.orientacoes.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-journal-bookmark"></i>
                            <span>Orientações</span>
                        </a>
                        @endcan
                        @if(\App\Services\Pedagogico\PedagogicoAccess::podeAcederCoordenacaoDisciplina(auth()->user(), 'disciplina.avisar'))
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.coordenacao.avisos') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.coordenacao.avisos*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-megaphone"></i>
                            <span>Aviso disciplina</span>
                        </a>
                        @endif
                        @can('pedagogico.aprovar')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.aprovacoes.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.aprovacoes.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-clipboard-check"></i>
                            <span>Aprovações</span>
                        </a>
                        @endcan
                        @can('pedagogico.banco.ver')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.banco.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.banco.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-collection"></i>
                            <span>Banco de questões</span>
                        </a>
                        @endcan
                        @can('pedagogico.ia.gerar')
                        @can('materiais.gerir')
                        <a href="{{ $hasAnoLectivo ? route('materiais.gerar-ia') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('materiais.gerar-ia*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-magic"></i>
                            <span>Material IA</span>
                        </a>
                        @endcan
                        @endcan
                        @can('materiais.ver')
                        <a href="{{ $hasAnoLectivo ? route('materiais.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('materiais.index') || request()->routeIs('materiais.create') || request()->routeIs('materiais.destroy') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-folder2-open"></i>
                            <span>Materiais</span>
                        </a>
                        @endcan
                        @can('conteudos.ver')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.conteudos.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.conteudos.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-list-check"></i>
                            <span>Conteúdos mínimos</span>
                        </a>
                        @endcan
                        @can('pedagogico.ver')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.provas.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.provas.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Instrumentos de prova</span>
                        </a>
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.exercicios.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.exercicios.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-pencil-square"></i>
                            <span>Exercícios</span>
                        </a>
                        @endcan
                        @can('pedagogico.correcao.usar')
                        <a href="{{ $hasAnoLectivo ? route('pedagogico.correcoes.index') : $criarAnoLectivoUrl }}"
                           class="menu-item submenu-item {{ request()->routeIs('pedagogico.correcoes.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                            <i class="bi bi-check2-square"></i>
                            <span>Correcções</span>
                        </a>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- ====================================== --}}
                {{-- ACADÉMICO (com submenus) --}}
                {{-- ====================================== --}}
                @if($user->can('cursos.ver') || $user->can('disciplinas.ver') || $user->can('turmas.ver') || $user->can('alunos.ver') || $user->can('associacoes.ver') || $user->can('horarios.ver') || $user->can('agenda.ver'))
                    @php
                        $academicoActive = request()->routeIs('cursos.*') || request()->routeIs('disciplinas.*') || request()->routeIs('turmas.*') || request()->routeIs('alunos.*') || request()->routeIs('associacoes.*') || request()->routeIs('directores-turma.*') || request()->routeIs('coordenadores-curso.*') || request()->routeIs('coordenadores-disciplina.*') || request()->routeIs('horarios.*') || request()->routeIs('calendario-provas.*') || request()->routeIs('agenda.*');
                    @endphp

                    <div class="menu-section">
                        <div class="menu-label menu-collapse"
                             data-bs-toggle="collapse"
                             data-bs-target="#menuAcademico"
                             aria-expanded="{{ $academicoActive ? 'true' : 'false' }}">
                            <span>
                                <i class="bi bi-book me-2"></i>
                                Académico
                                @if(!$hasAnoLectivo)
                                    <i class="bi bi-lock-fill text-danger ms-2" title="Requer ano lectivo"></i>
                                @endif
                            </span>
                            <i class="bi bi-chevron-right dropdown-icon"></i>
                        </div>
                        <div class="collapse submenu {{ $academicoActive ? 'show' : '' }}" id="menuAcademico">
                            @can('cursos.ver')
                                <a href="{{ $hasAnoLectivo ? route('cursos.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('cursos.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-journals"></i>
                                    <span>Cursos</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('disciplinas.ver')
                                <a href="{{ $hasAnoLectivo ? route('disciplinas.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('disciplinas.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-bookmark"></i>
                                    <span>Disciplinas</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('turmas.ver')
                                <a href="{{ $hasAnoLectivo ? route('turmas.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('turmas.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-people"></i>
                                    <span>Turmas</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('alunos.ver')
                                <a href="{{ $hasAnoLectivo ? route('alunos.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('alunos.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-person-badge"></i>
                                    <span>Alunos</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('associacoes.ver')
                                <a href="{{ $hasAnoLectivo ? route('associacoes.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('associacoes.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-link-45deg"></i>
                                    <span>Associações</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('viewAny', App\Models\DirectorTurma::class)
                                <a href="{{ $hasAnoLectivo ? route('directores-turma.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('directores-turma.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-person-badge-fill"></i>
                                    <span>Directores de Turma</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('viewAny', App\Models\CoordenadorCurso::class)
                                <a href="{{ $hasAnoLectivo ? route('coordenadores-curso.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('coordenadores-curso.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-mortarboard-fill"></i>
                                    <span>Coordenadores de Curso</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('viewAny', App\Models\CoordenadorDisciplina::class)
                                <a href="{{ $hasAnoLectivo ? route('coordenadores-disciplina.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('coordenadores-disciplina.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}"
                                   @if(!$hasAnoLectivo) title="Crie um ano lectivo em Configurações para desbloquear" @endif>
                                    <i class="bi bi-journal-bookmark"></i>
                                    <span>Coord. de Disciplina</span>
                                    @if(!$hasAnoLectivo)
                                        <i class="bi bi-lock-fill text-warning ms-auto"></i>
                                    @endif
                                </a>
                            @endcan
                            @can('horarios.ver')
                                <a href="{{ $hasAnoLectivo ? route('horarios.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('horarios.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                                    <i class="bi bi-calendar-week"></i>
                                    <span>Horários Anuais</span>
                                </a>
                            @endcan
                            @can('calendario_provas.ver')
                                <a href="{{ $hasAnoLectivo ? route('calendario-provas.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('calendario-provas.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                                    <i class="bi bi-calendar-event"></i>
                                    <span>Calendário de avaliações</span>
                                </a>
                            @endcan
                            @can('agenda.ver')
                                <a href="{{ $hasAnoLectivo ? route('agenda.index') : $criarAnoLectivoUrl }}"
                                   class="menu-item submenu-item {{ request()->routeIs('agenda.*') ? 'active' : '' }} {{ !$hasAnoLectivo ? 'disabled' : '' }}">
                                    <i class="bi bi-calendar3"></i>
                                    <span>Agenda escolar</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endif

                {{-- Menu Secretaria --}}
                @can('secretaria.ver')
                    @php
                        $secretariaActive = request()->routeIs('secretaria.*');
                    @endphp
                    <div class="menu-section">
                        <div class="menu-label menu-collapse"
                             data-bs-toggle="collapse"
                             data-bs-target="#menuSecretaria"
                             aria-expanded="{{ $secretariaActive ? 'true' : 'false' }}">
                            <span><i class="bi bi-building-check me-2"></i>Secretaria</span>
                            <i class="bi bi-chevron-right dropdown-icon"></i>
                        </div>
                        <div class="collapse submenu {{ $secretariaActive ? 'show' : '' }}" id="menuSecretaria">
                            <a href="{{ route('secretaria.dashboard') }}"
                               class="menu-item submenu-item {{ request()->routeIs('secretaria.dashboard') ? 'active' : '' }}">
                                <i class="bi bi-speedometer2"></i>
                                <span>Painel</span>
                            </a>
                            @can('secretaria.matriculas.gerir')
                                <a href="{{ route('secretaria.matriculas.index') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.matriculas.*') && ! request()->routeIs('secretaria.matriculas.import*') ? 'active' : '' }}">
                                    <i class="bi bi-person-vcard"></i>
                                    <span>Matrículas</span>
                                </a>
                                <a href="{{ route('secretaria.matriculas.import') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.matriculas.import*') ? 'active' : '' }}">
                                    <i class="bi bi-file-earmark-excel"></i>
                                    <span>Importar matrículas</span>
                                </a>
                                <a href="{{ route('secretaria.distribuicao.create') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.distribuicao.*') ? 'active' : '' }}">
                                    <i class="bi bi-arrows-move"></i>
                                    <span>Distribuir alunos</span>
                                </a>
                            @endcan
                            @can('secretaria.campanhas.enviar')
                                <a href="{{ route('secretaria.campanhas.index') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.campanhas.*') ? 'active' : '' }}">
                                    <i class="bi bi-megaphone"></i>
                                    <span>Campanhas</span>
                                </a>
                            @endcan
                            @can('secretaria.documentos.emitir')
                                <a href="{{ route('secretaria.documentos.index') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.documentos.index') || request()->routeIs('secretaria.documentos.emitir') ? 'active' : '' }}">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <span>Documentos</span>
                                </a>
                                <a href="{{ route('secretaria.documentos.pedidos') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.documentos.pedidos*') ? 'active' : '' }}">
                                    <i class="bi bi-inbox"></i>
                                    <span>Pedidos do portal</span>
                                </a>
                                <a href="{{ route('secretaria.documentos.configurar') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.documentos.configurar*') ? 'active' : '' }}">
                                    <i class="bi bi-sliders"></i>
                                    <span>Personalizar documentos</span>
                                </a>
                                <a href="{{ route('secretaria.cartao.emitir') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.cartao.emitir*') ? 'active' : '' }}">
                                    <i class="bi bi-credit-card-2-front"></i>
                                    <span>Cartões</span>
                                </a>
                            @endcan
                            @can('secretaria.cartao.configurar')
                                <a href="{{ route('secretaria.cartao.configurar') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('secretaria.cartao.configurar') || request()->routeIs('secretaria.cartao.guardar') ? 'active' : '' }}">
                                    <i class="bi bi-palette"></i>
                                    <span>Personalizar cartão</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcan

                {{-- Menu Assiduidade & Disciplina --}}
                @if($user->can('presencas.ver') || $user->can('ocorrencias.ver') || $user->hasRole('director_turma') || $user->temVisaoAssiduidade() || $user->hasRole('inspetor'))
                    @php
                        $assiduidadeActive = request()->routeIs('presencas.*')
                            || request()->routeIs('ocorrencias.*')
                            || request()->routeIs('inspetor.*');
                    @endphp
                    <div class="menu-section">
                        <div class="menu-label menu-collapse"
                             data-bs-toggle="collapse"
                             data-bs-target="#menuAssiduidade"
                             aria-expanded="{{ $assiduidadeActive ? 'true' : 'false' }}">
                            <span><i class="bi bi-clipboard-check me-2"></i>Assiduidade</span>
                            <i class="bi bi-chevron-right dropdown-icon"></i>
                        </div>
                        <div class="collapse submenu {{ $assiduidadeActive ? 'show' : '' }}" id="menuAssiduidade">
                            @can('presencas.ver')
                                <a href="{{ route('presencas.index') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('presencas.*') ? 'active' : '' }}">
                                    <i class="bi bi-person-check"></i>
                                    <span>Presenças</span>
                                </a>
                            @endcan
                            @can('ocorrencias.ver')
                                <a href="{{ route('ocorrencias.index') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('ocorrencias.*') ? 'active' : '' }}">
                                    <i class="bi bi-exclamation-octagon"></i>
                                    <span>Ocorrências</span>
                                </a>
                            @endcan
                            @if($user->temVisaoAssiduidade() || $user->hasRole('inspetor'))
                                <a href="{{ route('inspetor.painel') }}"
                                   class="menu-item submenu-item {{ request()->routeIs('inspetor.*') ? 'active' : '' }}">
                                    <i class="bi bi-shield-check"></i>
                                    <span>Painel Inspetor</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Menu Infraestrutura --}}
                @if($user->can('salas.ver') || $user->can('turnos.ver'))
                    @php
                        $infraActive = request()->routeIs('salas.*') || request()->routeIs('turnos.*') || request()->routeIs('ocupacao.*');
                    @endphp

                    <div class="menu-section">
                        <div class="menu-label menu-collapse"
                             data-bs-toggle="collapse"
                             data-bs-target="#menuInfra"
                             aria-expanded="{{ $infraActive ? 'true' : 'false' }}">
                            <span><i class="bi bi-building me-2"></i>Infraestrutura</span>
                            <i class="bi bi-chevron-right dropdown-icon"></i>
                        </div>
                        <div class="collapse submenu {{ $infraActive ? 'show' : '' }}" id="menuInfra">
                            @can('salas.ver')
                                <a href="{{ route('salas.index') }}" class="menu-item submenu-item {{ request()->routeIs('salas.*') ? 'active' : '' }}">
                                    <i class="bi bi-door-open"></i>
                                    <span>Salas</span>
                                </a>
                            @endcan
                            @can('turnos.ver')
                                <a href="{{ route('turnos.index') }}" class="menu-item submenu-item {{ request()->routeIs('turnos.*') ? 'active' : '' }}">
                                    <i class="bi bi-clock"></i>
                                    <span>Turnos</span>
                                </a>
                            @endcan
                            @if($user->can('salas.ver') || $user->can('turnos.ver'))
                                <a href="{{ route('ocupacao.index') }}" class="menu-item submenu-item {{ request()->routeIs('ocupacao.*') ? 'active' : '' }}">
                                    <i class="bi bi-grid-3x3-gap"></i>
                                    <span>Ocupação</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Menu Estatísticas / Relatório Académico --}}
                @if(auth()->user()->can('viewStatistics', App\Models\Aluno::class) || auth()->user()->can('relatorios.academico') || auth()->user()->temVisaoInstitucional())
                    <div class="menu-label">Análise</div>
                    @if(auth()->user()->temVisaoInstitucional())
                        <a href="{{ route('radar.index') }}" class="menu-item {{ request()->routeIs('radar.*') ? 'active' : '' }}">
                            <i class="bi bi-radar"></i>
                            <span>Radar Pedagógico</span>
                        </a>
                        <a href="{{ route('pauta-certificada.index') }}" class="menu-item {{ request()->routeIs('pauta-certificada.*') ? 'active' : '' }}">
                            <i class="bi bi-patch-check-fill"></i>
                            <span>Pauta Certificada</span>
                        </a>
                    @endif
                    @can('viewStatistics', App\Models\Aluno::class)
                        <a href="{{ route('estatisticas.index') }}" class="menu-item {{ request()->routeIs('estatisticas.*') ? 'active' : '' }}">
                            <i class="bi bi-graph-up"></i>
                            <span>Estatísticas</span>
                        </a>
                    @endcan
                    @can('relatorios.academico')
                        <a href="{{ route('relatorios.academico') }}" class="menu-item {{ request()->routeIs('relatorios.academico*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-bar-graph-fill"></i>
                            <span>Relatório Académico</span>
                        </a>
                    @endcan
                @endif

                {{-- Menu Relatórios --}}
                @can('boletins.ver')
                    <div class="menu-label">Relatórios</div>
                    <a href="{{ route('relatorios.boletins') }}" class="menu-item {{ request()->routeIs('relatorios.boletins*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Boletim de Notas</span>
                    </a>
                @endcan

                {{-- Menu Arquivos --}}
                @can('pauta_arquivos.ver')
                    <div class="menu-label">Ficheiros</div>
                    <a href="{{ route('pauta-arquivos.index') }}" class="menu-item {{ request()->routeIs('pauta-arquivos.*') ? 'active' : '' }}">
                        <i class="bi bi-folder-fill"></i>
                        <span>Ficheiros de Pautas</span>
                    </a>
                @endcan

                {{-- Menu Administração --}}
                @if($user->can('utilizadores.ver') || $user->can('auditoria.ver') || $user->can('configuracoes.ver') || $user->hasRole('admin_full'))
                    @php
                        $adminActive = request()->routeIs('users.*') || request()->routeIs('fecho-ano.*') || request()->routeIs('ano-lectivo-transfer.*') || request()->routeIs('alunos.transicao.*') || request()->routeIs('auditoria.*') || request()->routeIs('assistente.auditoria') || request()->routeIs('configuracoes.*') || request()->routeIs('control.hub.*') || request()->routeIs('encarregados.*') || request()->routeIs('whatsapp.*');
                    @endphp

                    <div class="menu-section">
                        <div class="menu-label menu-collapse"
                             data-bs-toggle="collapse"
                             data-bs-target="#menuAdmin"
                             aria-expanded="{{ $adminActive ? 'true' : 'false' }}">
                            <span><i class="bi bi-gear-fill me-2"></i>Administração</span>
                            <i class="bi bi-chevron-right dropdown-icon"></i>
                        </div>
                        <div class="collapse submenu {{ $adminActive ? 'show' : '' }}" id="menuAdmin">
                            @can('utilizadores.ver')
                                <a href="{{ route('users.index') }}" class="menu-item submenu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Utilizadores</span>
                                </a>
                            @endcan
                            @if(auth()->user()->temControloAcademicoInstitucional())
                                <a href="{{ route('encarregados.index') }}" class="menu-item submenu-item {{ request()->routeIs('encarregados.*') ? 'active' : '' }}">
                                    <i class="bi bi-person-hearts"></i>
                                    <span>Encarregados</span>
                                </a>
                                <a href="{{ route('whatsapp.index') }}" class="menu-item submenu-item {{ request()->routeIs('whatsapp.*') ? 'active' : '' }}">
                                    <i class="bi bi-whatsapp"></i>
                                    <span>WhatsApp (teste)</span>
                                </a>
                            @endif

                            @if($user->hasRole('admin_full'))
                                <a href="{{ route('fecho-ano.index') }}" class="menu-item submenu-item {{ request()->routeIs('fecho-ano.*') ? 'active' : '' }}">
                                    <i class="bi bi-calendar2-check"></i>
                                    <span>Fecho de Ano</span>
                                </a>
                                <a href="{{ route('ano-lectivo-transfer.index') }}" class="menu-item submenu-item {{ request()->routeIs('ano-lectivo-transfer.*') ? 'active' : '' }}">
                                    <i class="bi bi-arrow-left-right"></i>
                                    <span>Transferir Estrutura</span>
                                </a>
                                <a href="{{ route('alunos.transicao.index') }}" class="menu-item submenu-item {{ request()->routeIs('alunos.transicao.*') ? 'active' : '' }}">
                                    <i class="bi bi-arrow-right-circle"></i>
                                    <span>Transição de Alunos</span>
                                </a>
                            @endif

                            @can('auditoria.ver')
                                <a href="{{ route('assistente.auditoria') }}" class="menu-item submenu-item {{ request()->routeIs('assistente.auditoria') ? 'active' : '' }}">
                                    <i class="bi bi-robot"></i>
                                    <span>Auditoria Assistente</span>
                                </a>
                                <a href="{{ route('auditoria.index') }}" class="menu-item submenu-item {{ request()->routeIs('auditoria.*') ? 'active' : '' }}">
                                    <i class="bi bi-shield-check"></i>
                                    <span>Auditoria</span>
                                </a>
                            @endcan
                            @can('configuracoes.ver')
                                <a href="{{ route('configuracoes.index') }}" class="menu-item submenu-item {{ request()->routeIs('configuracoes.index') || request()->routeIs('configuracoes.update-*') || request()->routeIs('configuracoes.anos-*') ? 'active' : '' }}">
                                    <i class="bi bi-gear"></i>
                                    <span>Configurações</span>
                                </a>
                                <a href="{{ route('configuracoes.pagina-publica.identidade') }}" class="menu-item submenu-item {{ request()->routeIs('configuracoes.pagina-publica.*') ? 'active' : '' }}">
                                    <i class="bi bi-globe2"></i>
                                    <span>Página Pública</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endif
                @endunless
            @endauth
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="navbar-left">
                <button class="sidebar-toggle" id="sidebarToggleMobile">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title">
                    @isset($pageTitle)
                        {{ $pageTitle }}
                    @elseif(isset($header))
                        @php
                            $headerTitle = '';
                            if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $header, $headerMatch)) {
                                $headerTitle = trim(strip_tags($headerMatch[1]));
                            } else {
                                $headerTitle = trim(preg_replace('/\s+/', ' ', strip_tags($header)));
                            }
                        @endphp
                        {{ $headerTitle }}
                    @else
                        @yield('page-title', 'Facilita')
                    @endisset
                </h1>
            </div>

            <div class="navbar-right">
                <button class="theme-toggle" id="themeToggle" title="Alternar modo escuro/claro">
                    <i class="bi bi-moon-fill" id="themeIcon"></i>
                </button>

                @auth
                    @unless(auth()->user()->hasRole('aluno') || auth()->user()->hasRole('encarregado'))
                    <button class="btn btn-link nav-icon-btn text-decoration-none"
                            type="button"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#glossarioNotas"
                            title="Glossário de notas (MAC, MT, CFD…)"
                            aria-label="Glossário de notas">
                        <i class="bi bi-question-circle fs-5"></i>
                    </button>
                    <button class="btn btn-link nav-icon-btn position-relative text-decoration-none"
                            type="button"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#notificacoesStaff"
                            title="Notificações{{ ($notificacoesNaoLidas ?? 0) > 0 ? ' ('.$notificacoesNaoLidas.' por ler)' : '' }}"
                            aria-label="Notificações">
                        <i class="bi bi-bell fs-5"></i>
                        @if(($notificacoesNaoLidas ?? 0) > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">
                                {{ ($notificacoesNaoLidas ?? 0) > 9 ? '9+' : $notificacoesNaoLidas }}
                            </span>
                        @endif
                    </button>
                    @endunless
                @endauth

                @auth
                    <div class="dropdown">
                        <div class="user-menu" data-bs-toggle="dropdown">
                            <div class="user-info d-none d-md-block">
                                <div class="user-name">{{ Auth::user()->name }}</div>
                                @php
                                    $papelPrincipal = Auth::user()->roles->sortByDesc(fn ($r) => match ($r->name) {
                                        'admin_full' => 100,
                                        'directora_geral' => 90,
                                        'subdiretor_pedagogico' => 80,
                                        'coordenador_curso' => 70,
                                        'director_turma' => 60,
                                        'professor' => 50,
                                        'secretaria' => 40,
                                        'inspetor' => 35,
                                        'coordenador_disciplina' => 30,
                                        'encarregado' => 20,
                                        'aluno' => 10,
                                        default => 5,
                                    })->first();
                                    $extraPapeis = max(0, Auth::user()->roles->count() - 1);
                                @endphp
                                <div class="user-role" title="{{ Auth::user()->roles->map(fn ($r) => \App\Support\CargosInstitucionais::rotulo($r->name))->implode(' · ') }}">
                                    @if($papelPrincipal)
                                        {{ \App\Support\CargosInstitucionais::rotulo($papelPrincipal->name) }}
                                        @if($extraPapeis > 0)
                                            <span class="text-muted">+{{ $extraPapeis }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="user-avatar">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-modern">
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person me-2"></i>Meu Perfil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Terminar Sessão
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endauth
            </div>
        </header>

        <!-- Page Content -->
        <main class="page-content">
            @auth
                @php
                    $bannerSemAno = ! \App\Models\AnoLectivo::exists()
                        && ! request()->routeIs('configuracoes.*')
                        && auth()->user()->can('configuracoes.ver');
                @endphp
                @if($bannerSemAno)
                    <div class="alert alert-warning border-0 rounded-0 mb-3 d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-calendar-plus mt-1"></i>
                        <div class="flex-grow-1">
                            <strong>Ainda não existe ano lectivo.</strong>
                            Crie o primeiro ano lectivo para desbloquear turmas, alunos, notas e pautas.
                            <a href="{{ route('onboarding.escola-nova') }}" class="alert-link ms-1">Guia de configuração</a>
                            ·
                            <a href="{{ route('configuracoes.index') }}#anos-lectivos-section" class="alert-link">Criar ano lectivo</a>
                        </div>
                    </div>
                @endif
            @endauth
            @isset($controlLicenseWarning)
                @if($controlLicenseWarning)
                    <div class="alert alert-{{ $controlLicenseWarning['level'] === 'danger' ? 'danger' : 'warning' }} border-0 rounded-0 mb-3 d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>{{ $controlLicenseWarning['level'] === 'danger' ? 'Pagamento em atraso' : 'Cobrança Facilita' }}</strong>
                            <div>{{ $controlLicenseWarning['message'] }}</div>
                            @if(!empty($controlLicenseWarning['cobranca']['referencia']))
                                <div class="small mt-1 opacity-75">
                                    Ref. {{ $controlLicenseWarning['cobranca']['referencia'] }}
                                    @if(isset($controlLicenseWarning['cobranca']['valor_total']))
                                        · {{ number_format((float) $controlLicenseWarning['cobranca']['valor_total'], 2, ',', '.') }}
                                    @endif
                                    @if(!empty($controlLicenseWarning['cobranca']['vencimento_em']))
                                        · vence {{ \Illuminate\Support\Carbon::parse($controlLicenseWarning['cobranca']['vencimento_em'])->format('d/m/Y') }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endisset

            <!-- Flash Messages (SSR visível + toast JS) -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
                <div data-notification
                     data-notification-type="success"
                     data-notification-message="{{ session('success') }}"
                     style="display: none;"></div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
                <div data-notification
                     data-notification-type="error"
                     data-notification-message="{{ session('error') }}"
                     style="display: none;"></div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
                <div data-notification
                     data-notification-type="warning"
                     data-notification-message="{{ session('warning') }}"
                     style="display: none;"></div>
            @endif

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                    {{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
                <div data-notification
                     data-notification-type="info"
                     data-notification-message="{{ session('info') }}"
                     style="display: none;"></div>
            @endif

            <!-- Page Header - Buttons and Title appear here -->
            @isset($header)
                <div class="page-header mb-4">
                    {{ $header }}
                </div>
            @endisset

            @hasSection('header')
                <div class="page-header mb-4">
                    @yield('header')
                </div>
            @endif

            @yield('content', $slot ?? '')
        </main>

        <!-- Footer -->
        <footer class="main-footer">
            &copy; {{ date('Y') }} Facilita - Sistema Académico. Todos os direitos reservados.
        </footer>
    </div>

    <!-- jQuery (required for Select2) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SweetAlert2 Helper Functions -->
    <script>
        // Configurações de cores (Bootstrap matching)
        const SwalColors = {
            success: '#198754',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#0dcaf0'
        };

        // 1. Notificação padrão
        window.showNotification = function(type, message, title = null) {
            const titles = {
                success: 'Sucesso!',
                error: 'Erro!',
                warning: 'Atenção!',
                info: 'Informação'
            };

            Swal.fire({
                icon: type,
                title: title || titles[type],
                text: message,
                iconColor: SwalColors[type],
                confirmButtonColor: SwalColors[type],
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: true
            });
        };

        // 2. Toast (notificação discreta)
        window.showToast = function(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            Toast.fire({
                icon: type,
                title: message,
                iconColor: SwalColors[type]
            });
        };

        // 3. Confirmação de exclusão
        window.confirmDelete = async function(itemName = 'este registo') {
            const result = await Swal.fire({
                title: 'Tem a certeza?',
                html: `Deseja realmente eliminar <strong>${itemName}</strong>?<br><small class="text-muted">Esta ação não pode ser revertida.</small>`,
                icon: 'warning',
                iconColor: SwalColors.warning,
                showCancelButton: true,
                confirmButtonColor: SwalColors.error,
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> Sim, eliminar',
                cancelButtonText: '<i class="bi bi-x-lg"></i> Cancelar',
                reverseButtons: true,
                focusCancel: true
            });
            return result.isConfirmed;
        };

        // 4. Confirmação genérica
        window.confirmAction = async function(options = {}) {
            const defaults = {
                title: 'Confirmar ação',
                text: 'Deseja continuar com esta ação?',
                html: null,
                icon: 'question',
                confirmText: 'Sim, continuar',
                cancelText: 'Cancelar',
                confirmColor: '#0d6efd',
                dangerMode: false
            };

            const config = { ...defaults, ...options };

            const result = await Swal.fire({
                title: config.title,
                text: config.html ? null : config.text,
                html: config.html,
                icon: config.icon,
                showCancelButton: true,
                confirmButtonColor: config.dangerMode ? SwalColors.error : config.confirmColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: config.confirmText,
                cancelButtonText: config.cancelText,
                reverseButtons: true,
                focusCancel: true
            });

            return result.isConfirmed;
        };

        // 5. Loading state
        window.showLoading = function(message = 'A processar...') {
            Swal.fire({
                title: message,
                html: 'Por favor aguarde...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        };

        // 6. Hide loading
        window.hideLoading = function() {
            Swal.close();
        };

        // 7. Erros de validação
        window.showValidationErrors = function(errors) {
            let errorList = '<ul class="text-start mb-0">';

            if (Array.isArray(errors)) {
                errors.forEach(error => {
                    errorList += `<li>${error}</li>`;
                });
            } else if (typeof errors === 'object') {
                Object.values(errors).forEach(errorArray => {
                    if (Array.isArray(errorArray)) {
                        errorArray.forEach(error => {
                            errorList += `<li>${error}</li>`;
                        });
                    } else {
                        errorList += `<li>${errorArray}</li>`;
                    }
                });
            }

            errorList += '</ul>';

            Swal.fire({
                icon: 'error',
                title: 'Erros de validação',
                html: `<strong>Por favor corrija os seguintes erros:</strong>${errorList}`,
                iconColor: SwalColors.error,
                confirmButtonColor: SwalColors.error,
                confirmButtonText: 'OK',
                customClass: {
                    htmlContainer: 'text-start'
                }
            });
        };

        // Auto-inicializar notificações de sessão
        document.addEventListener('DOMContentLoaded', () => {
            const notificationElements = document.querySelectorAll('[data-notification]');

            notificationElements.forEach(element => {
                const type = element.dataset.notificationType;
                const message = element.dataset.notificationMessage;

                if (type && message) {
                    showNotification(type, message);
                }
            });

            @php
                $rolesPoll = auth()->user()?->getRoleNames() ?? collect();
                $pollNotificacoes = ! $rolesPoll->contains('aluno') && ! $rolesPoll->contains('encarregado');
            @endphp
            @if($pollNotificacoes)
            (function () {
                var pollUrl = @json(route('notificacoes.polling'));
                var desde = Math.floor(Date.now() / 1000);
                var seen = {};
                setInterval(function () {
                    fetch(pollUrl + '?desde=' + desde, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
                        if (!data) return;
                        desde = data.agora || desde;
                        (data.itens || []).forEach(function (item) {
                            if (seen[item.id]) return;
                            seen[item.id] = true;
                            if (item.tipo === 'mensagem_portal' && typeof showToast === 'function') {
                                showToast('info', (item.titulo || 'Nova mensagem') + ': ' + (item.mensagem || ''));
                            }
                        });
                    }).catch(function () {});
                }, 25000);
            })();
            @endif

            // Formulários com confirmação SweetAlert2: data-confirm / data-confirm-title
            document.querySelectorAll('form[data-confirm]').forEach((form) => {
                form.addEventListener('submit', async (e) => {
                    if (form.dataset.swalConfirmed === '1') {
                        return;
                    }
                    e.preventDefault();
                    const ok = await confirmAction({
                        title: form.dataset.confirmTitle || 'Confirmar',
                        text: form.dataset.confirm,
                        html: form.dataset.confirmHtml || null,
                        icon: form.dataset.confirmIcon || 'question',
                        confirmText: form.dataset.confirmOk || 'Sim, continuar',
                        cancelText: form.dataset.confirmCancel || 'Cancelar',
                        dangerMode: form.dataset.confirmDanger === '1',
                        confirmColor: form.dataset.confirmColor || '#0d6efd',
                    });
                    if (!ok) {
                        return;
                    }
                    form.dataset.swalConfirmed = '1';
                    form.requestSubmit();
                });
            });

            // Rejeição com motivo via SweetAlert2
            document.querySelectorAll('form[data-confirm-prompt]').forEach((form) => {
                form.addEventListener('submit', async (e) => {
                    if (form.dataset.swalConfirmed === '1') {
                        return;
                    }
                    e.preventDefault();
                    const field = form.dataset.confirmPromptField || 'motivo_rejeicao';
                    const result = await Swal.fire({
                        title: form.dataset.confirmTitle || 'Indique o motivo',
                        input: 'textarea',
                        inputLabel: form.dataset.confirmPrompt || 'Motivo',
                        inputPlaceholder: form.dataset.confirmPlaceholder || 'Descreva o motivo…',
                        inputAttributes: { maxlength: 500 },
                        showCancelButton: true,
                        confirmButtonText: form.dataset.confirmOk || 'Confirmar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: form.dataset.confirmDanger === '1' ? SwalColors.error : '#0d6efd',
                        reverseButtons: true,
                        inputValidator: (value) => {
                            if (!value || !String(value).trim()) {
                                return 'O motivo é obrigatório.';
                            }
                        },
                    });
                    if (!result.isConfirmed) {
                        return;
                    }
                    let input = form.querySelector(`[name="${field}"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = field;
                        form.appendChild(input);
                    }
                    input.value = String(result.value).trim();
                    form.dataset.swalConfirmed = '1';
                    form.requestSubmit();
                });
            });
        });
    </script>

    <script>
        // Sidebar Toggle Mobile (botão hamburger no topo)
        const sidebar = document.getElementById('sidebar');
        const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        // Abrir/Fechar sidebar em mobile
        if (sidebarToggleMobile) {
            sidebarToggleMobile.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });
        }

        // Fechar sidebar ao clicar no overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });
        }

        // Fechar sidebar em mobile ao clicar num link
        sidebar.querySelectorAll('.menu-item:not(.menu-collapse)').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) {
                    setTimeout(() => {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }, 200);
                }
            });
        });

        // Auto-scroll to active menu item and expand parent
        document.addEventListener('DOMContentLoaded', function() {
            const activeItem = document.querySelector('.sidebar-menu .menu-item.active');

            if (activeItem) {
                // Scroll to active item with smooth behavior
                setTimeout(() => {
                    activeItem.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 100);

                // If active item is in a submenu, ensure parent is expanded
                const parentCollapse = activeItem.closest('.collapse');
                if (parentCollapse && !parentCollapse.classList.contains('show')) {
                    const bsCollapse = new bootstrap.Collapse(parentCollapse, {
                        show: true
                    });
                }
            }

            // Sincronizar aria-expanded com Bootstrap Collapse (abordagem limpa)
            document.querySelectorAll('.collapse').forEach(collapseEl => {
                const trigger = document.querySelector(`[data-bs-target="#${collapseEl.id}"]`);

                if (trigger) {
                    // Sincronizar quando abre
                    collapseEl.addEventListener('show.bs.collapse', function() {
                        trigger.setAttribute('aria-expanded', 'true');
                    });

                    // Sincronizar quando fecha
                    collapseEl.addEventListener('hide.bs.collapse', function() {
                        trigger.setAttribute('aria-expanded', 'false');
                    });
                }
            });

            // Toggle Sidebar (Minimizar/Maximizar)
            const sidebarToggleBtn = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            let tooltips = [];

            // Função para gerenciar tooltips
            function updateTooltips(isCollapsed) {
                // Destruir tooltips existentes
                tooltips.forEach(tooltip => tooltip.dispose());
                tooltips = [];

                if (isCollapsed) {
                    // Criar tooltips para todos os menu-items quando collapsed
                    const menuItems = sidebar.querySelectorAll('.menu-item');
                    menuItems.forEach(item => {
                        const textSpan = item.querySelector('span');
                        if (textSpan) {
                            const text = textSpan.textContent.trim();
                            item.setAttribute('data-bs-toggle', 'tooltip');
                            item.setAttribute('data-bs-placement', 'right');
                            item.setAttribute('title', text);

                            const tooltip = new bootstrap.Tooltip(item);
                            tooltips.push(tooltip);
                        }
                    });

                    // Tooltips para menu-collapse (labels de seção)
                    const menuCollapses = sidebar.querySelectorAll('.menu-collapse');
                    menuCollapses.forEach(collapse => {
                        const textSpan = collapse.querySelector('span');
                        if (textSpan) {
                            const text = textSpan.textContent.trim();
                            collapse.setAttribute('data-bs-toggle', 'tooltip');
                            collapse.setAttribute('data-bs-placement', 'right');
                            collapse.setAttribute('title', text);

                            const tooltip = new bootstrap.Tooltip(collapse);
                            tooltips.push(tooltip);
                        }
                    });
                } else {
                    // Remover atributos de tooltip quando expandido
                    sidebar.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                        el.removeAttribute('data-bs-toggle');
                        el.removeAttribute('data-bs-placement');
                        el.removeAttribute('title');
                    });
                }
            }

            // Restaurar estado do localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
            }
            // Inicializar tooltips baseado no estado
            updateTooltips(sidebarCollapsed);

            // Toggle ao clicar
            if (sidebarToggleBtn) {
                sidebarToggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    const isCollapsed = sidebar.classList.contains('collapsed');

                    // Salvar preferência
                    localStorage.setItem('sidebarCollapsed', isCollapsed);

                    // Atualizar tooltips
                    updateTooltips(isCollapsed);

                    // Fechar todos os submenus quando minimizar
                    if (isCollapsed) {
                        document.querySelectorAll('.sidebar .collapse.show').forEach(collapse => {
                            const bsCollapse = bootstrap.Collapse.getInstance(collapse);
                            if (bsCollapse) {
                                bsCollapse.hide();
                            }
                        });
                    }
                });
            }

        });
    </script>

    <script>
        window.dismissTip = function(tipId) {
            var el = document.getElementById(tipId);
            if (el) {
                el.style.animation = 'none';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                el.style.transition = 'all 0.2s ease';
                setTimeout(function() { el.style.display = 'none'; }, 200);
            }
            var dismissed = JSON.parse(localStorage.getItem('dismissedTips') || '[]');
            if (dismissed.indexOf(tipId) === -1) {
                dismissed.push(tipId);
                localStorage.setItem('dismissedTips', JSON.stringify(dismissed));
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            var dismissed = JSON.parse(localStorage.getItem('dismissedTips') || '[]');
            document.querySelectorAll('.tip-card').forEach(function(el) {
                if (dismissed.indexOf(el.id) === -1) {
                    el.style.display = 'flex';
                }
            });
        });
    </script>

    @include('layouts.partials.theme-toggle-script')

    @stack('scripts')

    <!-- Global Tooltips & Popovers Initialization -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize all popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                html: true,
                sanitize: false
            });
        });
    });
    </script>

    @can('assistente.usar')
        <x-assistente-widget />
    @endcan

    @auth
        @unless(auth()->user()->hasRole('aluno') || auth()->user()->hasRole('encarregado'))
            @include('partials.glossario-notas')
            @include('layouts.partials.staff-notificacoes-offcanvas')
        @endunless
        @include('layouts.partials.bloqueio-inactividade')
    @endauth

    <!-- App JS (pre-compiled, Alpine.js) — depois do widget para registar assistenteFacilita -->
    <script type="module" src="{{ asset('js/app-compiled.js') }}"></script>
    @auth
        @include('layouts.partials.pwa-scripts')
        <script src="{{ asset('js/facilita-core.js') }}" defer></script>
    @endauth
</body>
</html>
