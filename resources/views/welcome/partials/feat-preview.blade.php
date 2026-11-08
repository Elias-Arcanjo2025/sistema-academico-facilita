@php
    $tipo = $tipo ?? 'default';
@endphp

<div class="feat-preview feat-preview--{{ $tipo }}" aria-hidden="true">
    @switch($tipo)
        @case('notas')
            <div class="feat-mock feat-mock--window">
                <div class="feat-mock__bar">
                    <span></span><span></span><span></span>
                </div>
                <div class="feat-mock__body">
                    <div class="feat-mock-table">
                        <div class="feat-mock-table__head">
                            <span>Aluno</span><span>MAC</span><span>PP</span><span>PT</span><span>MT</span>
                        </div>
                        @foreach([['Ana M.', '14', '15', '16', '15'], ['João K.', '12', '13', '14', '13'], ['Maria S.', '16', '17', '15', '16']] as $row)
                        <div class="feat-mock-table__row">
                            <span>{{ $row[0] }}</span><span>{{ $row[1] }}</span><span>{{ $row[2] }}</span><span>{{ $row[3] }}</span><strong>{{ $row[4] }}</strong>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @break

        @case('turmas')
            <div class="feat-mock feat-mock--window">
                <div class="feat-mock__bar"><span></span><span></span><span></span></div>
                <div class="feat-mock__body feat-mock__body--list">
                    @foreach([['11ª A — Informática', '32 alunos'], ['12ª B — Electrónica', '28 alunos'], ['10ª C — Gestão', '35 alunos']] as $turma)
                    <div class="feat-mock-list__item">
                        <i class="bi bi-people-fill"></i>
                        <div><strong>{{ $turma[0] }}</strong><small>{{ $turma[1] }}</small></div>
                    </div>
                    @endforeach
                </div>
            </div>
            @break

        @case('boletins')
            <div class="feat-mock feat-mock--doc">
                <div class="feat-mock-doc__header"></div>
                <div class="feat-mock-doc__line w-80"></div>
                <div class="feat-mock-doc__line w-100"></div>
                <div class="feat-mock-doc__line w-60"></div>
                <div class="feat-mock-doc__grid">
                    <span></span><span></span><span></span><span></span>
                </div>
                <div class="feat-mock-doc__badge"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</div>
            </div>
            @break

        @case('stats')
            <div class="feat-mock feat-mock--chart">
                <div class="feat-mock-chart__bars">
                    @foreach([45, 72, 58, 88, 65] as $h)
                    <span style="--h: {{ $h }}%"></span>
                    @endforeach
                </div>
                <div class="feat-mock-chart__legend">
                    <span><i></i> Aprovados</span>
                    <span><i></i> Média geral</span>
                </div>
            </div>
            @break

        @case('seguranca')
            <div class="feat-mock feat-mock--security">
                <div class="feat-mock-shield"><i class="bi bi-shield-lock-fill"></i></div>
                <div class="feat-mock-log">
                    <div><span class="ok"></span> Login — Admin</div>
                    <div><span class="ok"></span> Nota actualizada</div>
                    <div><span class="warn"></span> Exportação PDF</div>
                </div>
            </div>
            @break

        @case('mensagens')
            <div class="feat-mock feat-mock--chat">
                <div class="feat-mock-bubble feat-mock-bubble--in">Bom dia! Como está o desempenho da Ana?</div>
                <div class="feat-mock-bubble feat-mock-bubble--out">Bom dia! A Ana melhorou a Matemática: 15 valores no 2º trimestre.</div>
                <div class="feat-mock-bubble feat-mock-bubble--in">Óptima notícia, obrigado professor!</div>
            </div>
            @break

        @case('secretaria')
            <div class="feat-mock feat-mock--window">
                <div class="feat-mock__bar"><span></span><span></span><span></span></div>
                <div class="feat-mock__body feat-mock__body--list">
                    <div class="feat-mock-list__item">
                        <i class="bi bi-file-earmark-text"></i>
                        <div><strong>Declaração de frequência</strong><small>Pedido do portal — Ana M.</small></div>
                        <span class="feat-mock-badge feat-mock-badge--ok">Pronto</span>
                    </div>
                    <div class="feat-mock-list__item">
                        <i class="bi bi-file-earmark-text"></i>
                        <div><strong>Declaração de matrícula</strong><small>Pedido do portal — João K.</small></div>
                        <span class="feat-mock-badge feat-mock-badge--warn">Em preparação</span>
                    </div>
                    <div class="feat-mock-list__item">
                        <i class="bi bi-person-plus"></i>
                        <div><strong>Matrícula nova</strong><small>10ª classe — Informática</small></div>
                        <span class="feat-mock-badge">Recebido</span>
                    </div>
                </div>
            </div>
            @break

        @case('presencas')
            <div class="feat-mock feat-mock--window">
                <div class="feat-mock__bar"><span></span><span></span><span></span></div>
                <div class="feat-mock__body feat-mock__body--list">
                    @foreach([['Ana M.', 'presente'], ['João K.', 'presente'], ['Maria S.', 'falta']] as $p)
                    <div class="feat-mock-list__item">
                        @if($p[1] === 'presente')
                            <i class="bi bi-check-circle-fill" style="color:#10b981;"></i>
                        @else
                            <i class="bi bi-x-circle-fill" style="color:#ef4444;"></i>
                        @endif
                        <div><strong>{{ $p[0] }}</strong><small>{{ $p[1] === 'presente' ? 'Presente' : 'Falta — encarregado notificado' }}</small></div>
                    </div>
                    @endforeach
                </div>
            </div>
            @break

        @case('transicao')
            <div class="feat-mock feat-mock--transition">
                <div class="feat-mock-year">2024-2025</div>
                <div class="feat-mock-arrow"><i class="bi bi-arrow-right-circle-fill"></i></div>
                <div class="feat-mock-year feat-mock-year--new">2025-2026</div>
                <div class="feat-mock-transfer">
                    <span><i class="bi bi-check2"></i> Turmas</span>
                    <span><i class="bi bi-check2"></i> Alunos</span>
                    <span><i class="bi bi-check2"></i> Associações</span>
                </div>
            </div>
            @break

        @default
            <div class="feat-mock feat-mock--default">
                <i class="bi bi-grid-1x2-fill"></i>
            </div>
    @endswitch
</div>
