@props(['title' => null, 'description' => null, 'type' => 'website', 'image' => null, 'published' => null, 'modified' => null, 'jsonLd' => []])
@php
    use App\Support\Seo\Seo;
    use App\Support\Seo\OgImage;
    $profile = app(\App\Settings\ProfileSettings::class);
    $seo = app(\App\Settings\SeoSettings::class);
    $pageTitle = $title ? $title.' · '.$seo->site_title : $seo->site_title;
    $metaDescription = \Illuminate\Support\Str::limit(trim((string) ($description ?: $seo->default_description)), 160);
    $canonical = Seo::canonical(request());
    $shareImage = $image ?? ($seo->default_og_image_path
        ? Seo::absolute(\Illuminate\Support\Facades\Storage::disk('public')->url($seo->default_og_image_path))
        : Seo::absolute(OgImage::url($profile->title, $profile->tagline ?: $seo->site_title)));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonical }}">
    @unless (Seo::indexable())
        <meta name="robots" content="noindex, nofollow">
    @endunless
    <meta property="og:site_name" content="{{ $seo->site_title }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title ?: $seo->site_title }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="en_GB">
    <meta name="twitter:card" content="summary_large_image">
    @if ($published)
        <meta property="article:published_time" content="{{ $published }}">
    @endif
    @if ($modified)
        <meta property="article:modified_time" content="{{ $modified }}">
    @endif
    <link rel="alternate" type="application/rss+xml" title="{{ $seo->site_title }} — Writing" href="{{ Seo::absolute('/feed.xml') }}">
    @if ($jsonLd !== [])
        <script type="application/ld+json">{!! \App\Support\Seo\JsonLd::encode($jsonLd) !!}</script>
    @endif
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
