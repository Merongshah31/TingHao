<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Ting Hao' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite('resources/js/app.js')
    <link rel="stylesheet" href="{{ asset('css/tinghao.css') }}?v={{ filemtime(public_path('css/tinghao.css')) }}">
</head>
<body>
    <div class="language-switcher" aria-label="{{ __('messages.language') }}">
        <a href="{{ route('language.switch', 'en') }}" @class(['active' => app()->getLocale() === 'en'])>EN</a>
        <span>|</span>
        <a href="{{ route('language.switch', 'zh_CN') }}" @class(['active' => app()->getLocale() === 'zh_CN'])>中文</a>
    </div>
    @yield('content')
</body>
</html>
