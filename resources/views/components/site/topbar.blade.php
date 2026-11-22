@props(['current' => null])
<header class="topbar">
    <a class="topbar-name" href="{{ route('home') }}">{{ app(\App\Settings\ProfileSettings::class)->name }}</a>
    <nav class="topbar-nav" aria-label="Main">
        <a href="{{ route('projects.index') }}" @if ($current === 'projects') aria-current="page" @endif>Projects</a>
        <a href="{{ route('posts.index') }}" @if ($current === 'writing') aria-current="page" @endif>Writing</a>
        <x-site.theme-toggle />
    </nav>
</header>
