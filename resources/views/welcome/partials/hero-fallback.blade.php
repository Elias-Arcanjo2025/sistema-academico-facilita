<div class="hero-fallback">
    @if(($paginaPublica['usar_logo_instituicao'] ?? false) && !empty($instituicao['logo']))
        <div class="hero-fallback__logo">
            <img src="{{ \App\Support\PublicStorage::url($instituicao['logo']) }}"
                 alt="{{ $instituicao['nome'] ?? config('app.name') }}">
        </div>
    @elseif(!empty($instituicao['nome']))
        <p class="hero-fallback__instituicao">{{ $instituicao['nome'] }}</p>
    @endif

    <div class="hero-highlights">
        @if($portalAlunoActivo ?? true)
        <div class="hero-highlight">
            <i class="bi bi-journal-check"></i>
            <span>Notas e boletim</span>
        </div>
        <div class="hero-highlight">
            <i class="bi bi-calendar-week"></i>
            <span>Horário e provas</span>
        </div>
        @endif
        <div class="hero-highlight">
            <i class="bi bi-file-earmark-spreadsheet"></i>
            <span>Pautas e relatórios</span>
        </div>
        <div class="hero-highlight">
            <i class="bi bi-shield-check"></i>
            <span>Acesso seguro</span>
        </div>
    </div>

    @if(($paginaPublica['mostrar_contactos'] ?? false) && (!empty($instituicao['telefone']) || !empty($instituicao['email'])))
        <div class="hero-fallback__contactos">
            @if(!empty($instituicao['telefone']))
                <span><i class="bi bi-telephone"></i>{{ $instituicao['telefone'] }}</span>
            @endif
            @if(!empty($instituicao['email']))
                <span><i class="bi bi-envelope"></i>{{ $instituicao['email'] }}</span>
            @endif
        </div>
    @endif
</div>
