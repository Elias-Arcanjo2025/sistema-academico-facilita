@props([
    'viewUrl' => null,
    'editUrl' => null,
    'deleteRoute' => null,
    'deleteId' => null,
    'deleteName' => 'este registo',
    'canView' => true,
    'canEdit' => true,
    'canDelete' => true,
    'size' => 'sm',
    'tooltips' => true
])

<div class="btn-group" role="group">
    @if($viewUrl && $canView)
        <a href="{{ $viewUrl }}"
           class="btn btn-{{ $size }} btn-outline-info"
           title="Ver detalhes"
           @if($tooltips) data-bs-toggle="tooltip" @endif>
            <i class="bi bi-eye"></i>
        </a>
    @endif

    {{ $slot }}

    @if($editUrl && $canEdit)
        <a href="{{ $editUrl }}"
           class="btn btn-{{ $size }} btn-outline-primary"
           title="Editar"
           @if($tooltips) data-bs-toggle="tooltip" @endif>
            <i class="bi bi-pencil"></i>
        </a>
    @endif

    @if($deleteRoute && $deleteId && $canDelete)
        <form action="{{ route($deleteRoute, $deleteId) }}"
              method="POST"
              class="d-inline"
              onsubmit="event.preventDefault(); handleDeleteConfirmation(this, '{{ $deleteName }}');">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="btn btn-{{ $size }} btn-outline-danger"
                    title="Eliminar"
                    @if($tooltips) data-bs-toggle="tooltip" @endif>
                <i class="bi bi-trash"></i>
            </button>
        </form>
    @endif
</div>

@once
@push('scripts')
<script>
/**
 * Action Buttons Component - Delete Confirmation Handler
 * Usa SweetAlert2 para confirmações visuais de exclusão
 */
async function handleDeleteConfirmation(form, itemName) {
    const confirmed = await confirmDelete(itemName);
    if (confirmed) {
        showLoading('Eliminando...');
        form.submit();
    }
}
</script>
@endpush
@endonce
