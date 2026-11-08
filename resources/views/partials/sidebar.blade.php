<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="#">
                    <i class="bi bi-speedometer2"></i>
                    Painel de Controlo
                </a>
            </li>

            {{-- Menu para Professores --}}
            @if(true) {{-- Substituir por @can('ver-notas') --}}
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-journal-text"></i>
                    Lançar Notas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-people"></i>
                    Minhas Turmas
                </a>
            </li>
            @endif

            {{-- Menu para Directores de Turma --}}
            @if(true) {{-- Substituir por @can('ver-turma') --}}
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    Pautas da Turma
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-person-lines-fill"></i>
                    Alunos da Turma
                </a>
            </li>
            @endif

            {{-- Menu para Coordenadores --}}
            @if(true) {{-- Substituir por @can('ver-curso') --}}
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-diagram-3"></i>
                    Gestão do Curso
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    Relatórios do Curso
                </a>
            </li>
            @endif

            {{-- Menu para Administradores --}}
            @if(true) {{-- Substituir por @can('administrar') --}}
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 text-muted">
                    <span>Administração</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-people-fill"></i>
                    Utilizadores
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-building"></i>
                    Cursos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-book"></i>
                    Disciplinas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-collection"></i>
                    Turmas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-mortarboard"></i>
                    Alunos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-clock-history"></i>
                    Auditoria
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-gear"></i>
                    Configurações
                </a>
            </li>
            @endif
        </ul>
    </div>
</nav>
