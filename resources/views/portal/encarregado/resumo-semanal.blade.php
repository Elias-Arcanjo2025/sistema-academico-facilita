@extends('layouts.portal-encarregado')

@section('title', 'Esta semana')

@section('content')
@php
    $periodo = $resumo['periodo'];
    $filhos = $resumo['filhos'];
    $totais = $resumo['totais'];
@endphp

<div class="enc-dash">
    <a href="{{ route('portal.encarregado.dashboard') }}" class="enc-back"><i class="bi bi-arrow-left"></i> Início</a>

    <div class="enc-hero mb-3">
        <div class="enc-hero__eyebrow">Resumo semanal</div>
        <h1 class="enc-hero__title">Esta semana</h1>
        <p class="enc-hero__sub mb-0">{{ $periodo['rotulo'] }} — faltas, ocorrências e mensagens dos seus educandos.</p>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="enc-kpi">
                <span class="enc-kpi__label">Faltas</span>
                <strong class="enc-kpi__value">{{ $totais['faltas'] }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="enc-kpi">
                <span class="enc-kpi__label">Atrasos</span>
                <strong class="enc-kpi__value">{{ $totais['atrasos'] }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="enc-kpi {{ $totais['ocorrencias'] > 0 ? 'enc-kpi--warn' : '' }}">
                <span class="enc-kpi__label">Ocorrências</span>
                <strong class="enc-kpi__value">{{ $totais['ocorrencias'] }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="enc-kpi">
                <span class="enc-kpi__label">Mensagens</span>
                <strong class="enc-kpi__value">{{ $totais['mensagens'] }}</strong>
            </div>
        </div>
    </div>

    @if($filhos->isEmpty())
        <div class="enc-panel text-muted">Ainda não há educandos associados.</div>
    @else
        <h2 class="enc-section-title">Por educando</h2>
        <div class="row g-2">
            @foreach($filhos as $filho)
                <div class="col-12">
                    <div class="enc-panel" style="border-left: 4px solid {{ $filho['cor'] }}">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
                            <div>
                                <strong>{{ $filho['nome'] }}</strong>
                                <div class="small text-muted">{{ $filho['turma'] }} · score {{ $filho['score'] }} · {{ $filho['score_label'] }}</div>
                            </div>
                            <a href="{{ $filho['url'] }}" class="btn btn-sm btn-outline-secondary">Ver filho</a>
                        </div>
                        <div class="row g-2 small mb-2">
                            <div class="col-4 col-md-2">
                                <span class="text-muted d-block">Faltas injust.</span>
                                <strong>{{ $filho['faltas_injustificadas'] }}</strong>
                            </div>
                            <div class="col-4 col-md-2">
                                <span class="text-muted d-block">Faltas just.</span>
                                <strong>{{ $filho['faltas_justificadas'] }}</strong>
                            </div>
                            <div class="col-4 col-md-2">
                                <span class="text-muted d-block">Atrasos</span>
                                <strong>{{ $filho['atrasos'] }}</strong>
                            </div>
                            <div class="col-4 col-md-2">
                                <span class="text-muted d-block">Ocorrências</span>
                                <strong>{{ $filho['ocorrencias'] }}</strong>
                            </div>
                            <div class="col-4 col-md-2">
                                <span class="text-muted d-block">Mensagens</span>
                                <strong>{{ $filho['mensagens'] }}</strong>
                            </div>
                            <div class="col-4 col-md-2">
                                <span class="text-muted d-block">Média MT</span>
                                <strong>{{ $filho['pode_notas'] && $filho['media_mt'] !== null ? number_format($filho['media_mt'], 1) : '—' }}</strong>
                            </div>
                        </div>
                        <p class="small text-muted mb-2">{{ $filho['tendencia_faltas'] }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ $filho['url_assiduidade'] }}" class="btn btn-sm btn-outline-primary">Assiduidade</a>
                            <a href="{{ $filho['url_ocorrencias'] }}" class="btn btn-sm btn-outline-primary">Ocorrências</a>
                            <a href="{{ $filho['url_mensagens'] }}" class="btn btn-sm btn-outline-primary">Mensagens</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
