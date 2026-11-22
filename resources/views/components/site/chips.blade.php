@props(['tags', 'label' => 'Skills'])
@if ($tags->isNotEmpty())
    <ul class="chips" aria-label="{{ $label }}">
        @foreach ($tags as $tag)
            <li class="chip">{{ $tag->name }}</li>
        @endforeach
    </ul>
@endif
