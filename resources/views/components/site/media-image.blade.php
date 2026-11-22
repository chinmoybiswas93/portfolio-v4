@props(['media', 'conversion' => 'card', 'sizes' => '100vw', 'width' => 320, 'height' => 200, 'loading' => 'lazy'])
@php
    /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media $media */
    $src = $media->hasGeneratedConversion($conversion) ? $media->getUrl($conversion) : $media->getUrl();
    $srcset = $media->hasGeneratedConversion($conversion) ? $media->getSrcset($conversion) : '';
@endphp
<img src="{{ $src }}" @if ($srcset !== '') srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
     alt="{{ $media->getCustomProperty('alt', '') }}" width="{{ $width }}" height="{{ $height }}"
     loading="{{ $loading }}" decoding="async" {{ $attributes }}>
