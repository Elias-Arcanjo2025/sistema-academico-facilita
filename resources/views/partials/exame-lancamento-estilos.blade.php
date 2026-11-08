<style>
    .exame-lancamento-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color, #e2e8f0);
        background: var(--bg-light, #f8fafc);
    }
    .exame-lancamento-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .exame-lancamento-stat {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.65rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .exame-lancamento-stat--total { background: rgba(79, 70, 229, 0.08); color: var(--primary-color, #4f46e5); }
    .exame-lancamento-stat--ok { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .exame-lancamento-stat--warn { background: rgba(245, 158, 11, 0.12); color: #b45309; }
    .exame-lancamento-filtros .btn { font-size: 0.8rem; padding: 0.2rem 0.65rem; }
    .exame-lancamento-table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted, #64748b);
        white-space: nowrap;
    }
    .exame-lancamento-table tbody td { vertical-align: middle; }
    .exame-lancamento-row--fora {
        background: rgba(245, 158, 11, 0.05);
        box-shadow: inset 3px 0 0 #f59e0b;
    }
    .exame-lancamento-row--elegivel {
        box-shadow: inset 3px 0 0 #10b981;
    }
    .exame-aluno-celula { min-width: 11rem; max-width: 16rem; }
    .exame-aluno-num {
        width: 2rem;
        height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(79, 70, 229, 0.1);
        color: var(--primary-color, #4f46e5);
        font-weight: 700;
        font-size: 0.8rem;
    }
    .exame-aluno-nome {
        font-weight: 500;
        line-height: 1.35;
        font-size: 0.9rem;
    }
    .exame-disc-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: stretch;
        padding: 0.15rem 0;
    }
    .exame-disc-chip {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        width: 8.25rem;
        flex-shrink: 0;
        padding: 0.45rem 0.5rem;
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 0.5rem;
        background: var(--bg-card, #fff);
    }
    .exame-disc-chip__codigo {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        color: var(--text-muted, #64748b);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .exame-disc-chip__fluxo {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .exame-disc-chip__ref {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 2rem;
        line-height: 1.1;
    }
    .exame-disc-chip__ref-label {
        font-size: 0.6rem;
        color: var(--text-muted, #94a3b8);
        text-transform: uppercase;
    }
    .exame-disc-chip__ref-valor { font-size: 0.85rem; font-weight: 700; }
    .exame-disc-chip__arrow { font-size: 0.65rem; color: var(--text-muted, #94a3b8); flex-shrink: 0; }
    .exame-disc-chip__input {
        width: 2.75rem;
        min-width: 2.75rem;
        padding-left: 0.2rem;
        padding-right: 0.2rem;
        text-align: center;
        font-weight: 700;
    }
    .exame-estado-celula { width: 8.5rem; }
    .exame-regras-collapse summary {
        cursor: pointer;
        list-style: none;
        font-size: 0.85rem;
        color: var(--primary-color, #4f46e5);
        font-weight: 500;
    }
    .exame-regras-collapse summary::-webkit-details-marker { display: none; }
    .exame-regras-collapse summary .bi { transition: transform 0.2s; }
    .exame-regras-collapse[open] summary .bi-chevron-down { transform: rotate(180deg); }
    @media (max-width: 767.98px) {
        .exame-lancamento-table .exame-aluno-celula { max-width: none; }
        .exame-estado-celula { width: auto; }
    }
</style>
