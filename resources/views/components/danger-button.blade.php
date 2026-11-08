<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-danger d-inline-flex align-items-center']) }}>
    {{ $slot }}
</button>
