@php
    $rotaActual = request()->route()?->getName() ?? '';
@endphp
<nav class="portal-bottom-nav" aria-label="Navegação do portal">
    <a href="{{ route('portal.aluno.dashboard') }}" class="{{ str_starts_with($rotaActual, 'portal.aluno.dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door"></i>
        <span>Início</span>
    </a>
    <a href="{{ route('portal.aluno.notas') }}" class="{{ str_starts_with($rotaActual, 'portal.aluno.notas') ? 'active' : '' }}">
        <i class="bi bi-journal-text"></i>
        <span>Notas</span>
    </a>
    <a href="{{ route('portal.aluno.calendario') }}" class="{{ str_starts_with($rotaActual, 'portal.aluno.calendario') || str_starts_with($rotaActual, 'portal.aluno.provas') ? 'active' : '' }}">
        <i class="bi bi-calendar3"></i>
        <span>Agenda</span>
    </a>
    <a href="{{ route('portal.aluno.materiais') }}" class="{{ str_starts_with($rotaActual, 'portal.aluno.materiais') ? 'active' : '' }}">
        <i class="bi bi-folder2-open"></i>
        <span>Materiais</span>
    </a>
    <a href="{{ route('portal.aluno.assiduidade') }}" class="{{ str_starts_with($rotaActual, 'portal.aluno.assiduidade') || str_starts_with($rotaActual, 'portal.aluno.ocorrencias') ? 'active' : '' }}">
        <i class="bi bi-person-check"></i>
        <span>Faltas</span>
    </a>
</nav>
