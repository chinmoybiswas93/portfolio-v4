<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use Illuminate\Support\Str;

/**
 * Adds ids to the h2/h3 headings of rendered post HTML and lists them for the "On this page" contents.
 * The HTML comes from our own CommonMark renderer (raw HTML escaped), so headings are plain <h2>/<h3>.
 */
final class TableOfContents
{
    /**
     * @return array{html: string, items: list<array{level: int, id: string, text: string}>}
     */
    public static function build(string $html): array
    {
        $items = [];
        $used = [];

        $html = (string) preg_replace_callback('#<h([23])>(.*?)</h\1>#s', function (array $m) use (&$items, &$used): string {
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $base = Str::slug($text) ?: 'section';
            $id = $base;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = $base.'-'.$n;
            }
            $used[$id] = true;
            $items[] = ['level' => (int) $m[1], 'id' => $id, 'text' => $text];

            return '<h'.$m[1].' id="'.e($id).'">'.$m[2].'</h'.$m[1].'>';
        }, $html);

        return ['html' => $html, 'items' => $items];
    }
}
