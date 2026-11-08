<style>
    .cargo-filtros .form-label { font-size: 0.8rem; margin-bottom: 0.25rem; color: var(--text-muted); }
    .cargo-list-header { gap: 0.75rem; }
    .cargo-legenda { font-size: 0.78rem; color: var(--text-muted); }
    .cargo-search-wrap { width: 100%; max-width: 320px; }
    @media (min-width: 768px) {
        .cargo-search-wrap { width: 280px; }
    }

    .cargo-card {
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 1rem 1.15rem;
        background: var(--bg-card);
        transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .cargo-card:hover {
        box-shadow: 0 6px 18px rgba(79, 70, 229, 0.08);
        border-color: rgba(79, 70, 229, 0.18);
    }
    .cargo-card--inactivo { border-left: 4px solid #94a3b8; }
    .cargo-card--parcial { border-left: 4px solid #f59e0b; }

    .cargo-professor {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-width: 0;
    }
    .cargo-professor .avatar-circle { width: 42px; height: 42px; font-size: 1rem; flex-shrink: 0; }
    .cargo-professor__nome {
        font-weight: 600;
        font-size: 0.95rem;
        line-height: 1.35;
        word-break: break-word;
    }
    .cargo-professor__sub { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem; }

    .cargo-meta { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }

    .cargo-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.6rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .cargo-chip--activo {
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
        border-color: rgba(16, 185, 129, 0.22);
    }
    .cargo-chip--inactivo {
        background: #f1f5f9;
        color: #64748b;
        border-color: #e2e8f0;
    }

    /* Cartão simples (coordenador) */
    .cargo-card--single .cargo-single__head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }
    .cargo-single__curso {
        margin-top: 0.85rem;
        padding: 0.65rem 0.75rem;
        border-radius: 10px;
        background: rgba(79, 70, 229, 0.06);
        border: 1px solid rgba(79, 70, 229, 0.1);
        font-size: 0.88rem;
    }
    .cargo-single__curso a { font-weight: 600; text-decoration: none; color: var(--primary-color); }
    .cargo-single__curso a:hover { text-decoration: underline; }
    .cargo-single__acoes {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.85rem;
        padding-top: 0.85rem;
        border-top: 1px solid var(--border-color);
    }
    .cargo-single__acoes .btn { flex: 1 1 auto; min-width: 0; }
    @media (min-width: 576px) {
        .cargo-single__acoes .btn { flex: 0 1 auto; }
    }

    /* Cartão agrupado (director / associação) */
    .cargo-card__top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }
    .cargo-card__body {
        margin-top: 0.85rem;
        padding-top: 0.85rem;
        border-top: 1px dashed var(--border-color);
    }
    .cargo-item-row {
        display: grid;
        grid-template-columns: minmax(72px, 88px) minmax(0, 1fr) auto;
        gap: 0.5rem 0.75rem;
        align-items: center;
        padding: 0.65rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    .cargo-item-row:last-child { border-bottom: none; padding-bottom: 0; }
    .cargo-item-row__turma {
        font-weight: 700;
        font-size: 0.88rem;
        color: var(--primary-color);
        text-decoration: none;
    }
    .cargo-item-row__turma:hover { text-decoration: underline; }
    .cargo-item-row__curso {
        font-size: 0.8rem;
        color: var(--text-muted);
        line-height: 1.3;
        min-width: 0;
        word-break: break-word;
    }
    .cargo-item-row__status { justify-self: start; }
    .cargo-item-row__acoes {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        padding-top: 0.35rem;
    }
    @media (min-width: 768px) {
        .cargo-item-row {
            grid-template-columns: 88px minmax(0, 1fr) auto auto;
        }
        .cargo-item-row__acoes {
            grid-column: auto;
            justify-self: end;
            padding-top: 0;
        }
        .cargo-item-row__status { justify-self: center; }
    }

    .cargo-acoes-compact .btn { padding: 0.25rem 0.5rem; }
</style>
