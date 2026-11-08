@php
    use App\Support\DeviceDetect;
    $pwaMobile = DeviceDetect::suportaPwa();
@endphp
@if($pwaMobile)
    <link rel="manifest" href="{{ $manifestUrl ?? route('pwa.manifest.staff') }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Facilita">
@endif
