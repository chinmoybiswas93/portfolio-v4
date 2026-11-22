@props(['title' => null, 'description' => null])
@php
    $profile = app(\App\Settings\ProfileSettings::class);
    $seo = app(\App\Settings\SeoSettings::class);
    $pageTitle = $title ? $title.' · '.$seo->site_title : $seo->site_title;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description ?? $seo->default_description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#0F172A">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script src="{{ asset('theme-init.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#content">Skip to content</a>
    <div class="spotlight" aria-hidden="true"></div>
    {{ $slot }}
</body>
</html>
