@php
    $urlPortalAluno = auth()->check() && auth()->user()->hasRole('aluno')
        ? route('portal.aluno.dashboard')
        : route('login');
@endphp

@if($portalAlunoActivo ?? true)
<section class="aluno-zone" id="portal-aluno">
    <div class="container">
        <div class="aluno-panel reveal">
            <div class="row g-3 g-lg-4 align-items-center">
                <div class="col-lg-5 aluno-panel__intro">
                    <span class="sec-tag sec-tag--aluno">Para ti, aluno</span>
                    <h2 class="aluno-panel__title">{{ $paginaPublica['alunos_titulo'] }}</h2>
                    <p class="aluno-panel__sub">{{ $paginaPublica['alunos_subtitulo'] }}</p>
                    @auth
                        @if(auth()->user()->hasRole('aluno'))
                            <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-aluno-portal">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Ir para o meu portal
                            </a>
                        @else
                            <p class="aluno-zone__hint mb-0">Esta área destina-se a contas de aluno.</p>
                        @endif
                    @else
                        <a href="{{ $urlPortalAluno }}" class="btn btn-aluno-portal">
                            <i class="bi bi-mortarboard me-2"></i>Entrar no Portal do Aluno
                        </a>
                        <p class="aluno-zone__hint">Utiliza as credenciais fornecidas pela tua escola.</p>
                    @endauth
                </div>
                <div class="col-lg-7">
                    <div class="aluno-benefits-row">
                        <div class="aluno-benefit aluno-benefit--compact">
                            <div class="aluno-benefit__ico" style="background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent);">
                                <i class="bi bi-journal-check"></i>
                            </div>
                            <div>
                                <h4>Notas e médias</h4>
                                <p>MAC, PP, PT e médias trimestrais.</p>
                            </div>
                        </div>
                        <div class="aluno-benefit aluno-benefit--compact">
                            <div class="aluno-benefit__ico" style="background: color-mix(in srgb, var(--primary) 14%, transparent); color: var(--primary);">
                                <i class="bi bi-file-earmark-text-fill"></i>
                            </div>
                            <div>
                                <h4>Boletim em PDF</h4>
                                <p>Descarrega o boletim trimestral ou anual.</p>
                            </div>
                        </div>
                        <div class="aluno-benefit aluno-benefit--compact">
                            <div class="aluno-benefit__ico" style="background: rgba(16,185,129,.14); color: #10b981;">
                                <i class="bi bi-calendar-week-fill"></i>
                            </div>
                            <div>
                                <h4>Horário e provas</h4>
                                <p>Aulas e calendário de avaliações.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

<section class="escola-strip">
    <div class="container">
        <a href="#funcionalidades" class="escola-strip__link reveal">
            <span class="escola-strip__ico"><i class="bi bi-building"></i></span>
            <span class="escola-strip__text">
                <strong>Sou da escola</strong>
                <small>Docentes e gestão — ver ferramentas de gestão académica</small>
            </span>
            <i class="bi bi-chevron-down escola-strip__arrow"></i>
        </a>
    </div>
</section>
