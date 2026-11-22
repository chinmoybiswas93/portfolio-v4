@php use App\Support\Seo\Seo; @endphp
{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ Seo::absolute('/blog') }}</link>
        <description>{{ $description }}</description>
        <language>en-gb</language>
        <atom:link href="{{ Seo::absolute('/feed.xml') }}" rel="self" type="application/rss+xml" />
@if ($posts->isNotEmpty())
        <lastBuildDate>{{ $posts->max('updated_at')?->toRssString() }}</lastBuildDate>
@endif
@foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ Seo::absolute(route('posts.show', $post, false)) }}</link>
            <guid isPermaLink="true">{{ Seo::absolute(route('posts.show', $post, false)) }}</guid>
            <pubDate>{{ $post->published_at?->toRssString() }}</pubDate>
@if ($post->category)
            <category>{{ $post->category->name }}</category>
@endif
            <description>{{ $post->excerpt }}</description>
            <content:encoded><![CDATA[{!! str_replace(']]>', ']]]]><![CDATA[>', (string) $post->body_html) !!}]]></content:encoded>
        </item>
@endforeach
    </channel>
</rss>
