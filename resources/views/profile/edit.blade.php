@php
    $isHub = config('control.mode') === 'hub';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-person-circle me-2"></i>Meu Perfil
            </h2>
            <a href="{{ $isHub ? route('control.hub.index') : url()->previous() }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-2">
        <div class="container-fluid px-0">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-person-badge me-2"></i>Informações do Perfil
                            </h5>
                        </div>
                        <div class="card-body">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>

                    @unless($isHub)
                        <div class="card shadow-sm mb-4 border-info">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-journal-text me-2"></i>Regras de Avaliação
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    Consulte os critérios de aprovação, fórmulas de médias, regras de transição,
                                    recuperação e recurso aplicados no sistema.
                                </p>
                                <a href="{{ route('regras-avaliacao') }}" class="btn btn-outline-info">
                                    <i class="bi bi-book me-1"></i> Ver regras completas
                                </a>
                            </div>
                        </div>
                    @endunless

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-lock me-2"></i>Alterar Password
                            </h5>
                        </div>
                        <div class="card-body">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-display me-2"></i>Sessões activas</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Veja e termine sessões abertas noutros dispositivos.</p>
                            <a href="{{ route('profile.sessoes') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-shield-check me-1"></i>Gerir sessões
                            </a>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>Zona de Perigo
                            </h5>
                        </div>
                        <div class="card-body">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
