@push('styles')
<style>
    .cfg-estado-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .cfg-ano-pill {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: #fff;
        border: none;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
    }

    .cfg-trim-card {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        transition: box-shadow 0.2s ease;
    }

    .cfg-trim-card--actual {
        border-color: #818cf8;
        background: linear-gradient(180deg, #eef2ff 0%, #fff 100%);
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.12);
    }

    .cfg-trim-card--inactivo {
        background: #f8fafc;
        opacity: 0.85;
    }

    .cfg-stat-card {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        border-left-width: 4px;
    }

    .cfg-stat-card--nota { border-left-color: #6366f1; }
    .cfg-stat-card--reprov { border-left-color: #f59e0b; }
    .cfg-stat-card--desist { border-left-color: #64748b; }
    .cfg-stat-card--media { border-left-color: #0ea5e9; }

    .cfg-stat-value {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .cfg-stat-value--indigo { color: #4f46e5; }
    .cfg-stat-value--amber { color: #d97706; }
    .cfg-stat-value--slate { color: #475569; }

    .cfg-ano-row--actual {
        background: linear-gradient(90deg, rgba(16, 185, 129, 0.08) 0%, transparent 100%) !important;
    }

    .cfg-badge-actual {
        background: #059669;
        color: #fff;
    }

    .cfg-badge-novo {
        background: #0ea5e9;
        color: #fff;
    }

    .cfg-badge-anterior {
        background: #94a3b8;
        color: #fff;
    }

    .cfg-btn-activar {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        border: none;
        color: #fff;
    }

    .cfg-btn-activar:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        color: #fff;
    }

    .cfg-btn-novo-ano {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        border: none;
        color: #fff;
    }

    .cfg-btn-novo-ano:hover {
        background: linear-gradient(135deg, #047857 0%, #065f46 100%);
        color: #fff;
    }
</style>
@endpush
