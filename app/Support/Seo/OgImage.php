<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Support\Brand;
use App\Support\BrandAssets;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Social share cards (1200×630 PNG, docs/ARCHITECTURE.md §6), drawn with GD from a label and a title.
 * The file name is a hash of what is drawn, so a changed title gets a new image and old URLs never go stale.
 * Images are made on first use and kept on the public disk.
 */
final class OgImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const VERSION = 1;

    public static function url(string $label, string $title): string
    {
        $path = self::path($label, $title);
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $disk->put($path, self::render($label, $title));
        }

        return $disk->url($path);
    }

    public static function path(string $label, string $title): string
    {
        return 'og/'.substr(hash('sha256', self::VERSION."\n".$label."\n".$title), 0, 32).'.png';
    }

    public static function render(string $label, string $title): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('GD could not create the image.');
        }
        imageantialias($image, true);

        $bold = resource_path('og/DejaVuSans-Bold.ttf');
        $regular = resource_path('og/DejaVuSans.ttf');
        $navy = self::colour($image, 0x0F, 0x17, 0x2A);
        $teal = self::colour($image, 0x2D, 0xD4, 0xBF);
        $ink = self::colour($image, 0xE2, 0xE8, 0xF0);
        $muted = self::colour($image, 0x94, 0xA3, 0xB8);

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $navy);
        imagefilledrectangle($image, 0, 0, 12, self::HEIGHT, $teal);

        $logo = @imagecreatefrompng(BrandAssets::ogLogoPath());
        if ($logo instanceof GdImage) {
            // Fit the logo into an 80 px tall box so any upload size keeps the layout.
            $height = 80;
            $width = max(1, (int) round(imagesx($logo) * $height / max(1, imagesy($logo))));
            imagealphablending($image, true);
            imagecopyresampled($image, $logo, 80, 64, 0, 0, $width, $height, imagesx($logo), imagesy($logo));
        }
        imagettftext($image, 26, 0, 172, 116, $ink, $bold, Brand::name());

        imagettftext($image, 22, 0, 80, 262, $teal, $bold, mb_strtoupper($label));

        $size = 54;
        $lines = self::wrap($title, $bold, $size, self::WIDTH - 160);
        if (count($lines) > 3) {
            $size = 44;
            $lines = self::wrap($title, $bold, $size, self::WIDTH - 160);
        }
        $lines = array_slice($lines, 0, 4);
        $y = 340;
        foreach ($lines as $line) {
            imagettftext($image, $size, 0, 80, $y, $ink, $bold, $line);
            $y += (int) round($size * 1.35);
        }

        imagettftext($image, 22, 0, 80, self::HEIGHT - 56, $muted, $regular, Brand::domain());

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $candidate) ?: [0, 0, 0, 0, 0];
            if ($line !== '' && $maxWidth < $box[2] - $box[0]) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private static function colour(GdImage $image, int $r, int $g, int $b): int
    {
        return (int) imagecolorallocate($image, $r, $g, $b);
    }
}
