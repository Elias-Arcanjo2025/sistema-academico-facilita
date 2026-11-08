<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">
                    <i class="bi bi-journal-text me-2"></i>Regras de Avaliação e Aprovação
                </h2>
                <small class="text-muted">
                    Critérios, fórmulas e cálculos aplicados no sistema Facilita
                </small>
            </div>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="card shadow-soft">
                <div class="card-body p-4">
                    @include('regras-avaliacao._conteudo')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
