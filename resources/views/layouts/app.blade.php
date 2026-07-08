<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ku' ? 'rtl' : 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cwt Academy')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&family=Noto+Kufi+Arabic:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght@100..700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&family=Noto+Kufi+Arabic:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    @php
        $cspNonce = request()->attributes->get('csp_nonce') ?? '';

        if (app()->environment('production') && is_file(public_path('hot'))) {
            unlink(public_path('hot'));
        }
    @endphp
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if($cspNonce)
    <meta name="csp-nonce" content="{{ $cspNonce }}">
    @endif
</head>
<body class="bg-bg-base text-text-primary font-sans antialiased min-h-screen flex flex-col selection:bg-gold-400 selection:text-text-on-gold">
    @include('components.top-nav')
    @include('components.flash-messages')

    <main class="flex-grow">
        @yield('content')
    </main>
</body>
</html>
