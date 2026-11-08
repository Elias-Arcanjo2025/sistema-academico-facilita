<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-primary d-inline-flex align-items-center']) }}>
    {{ $slot }}
</button>
