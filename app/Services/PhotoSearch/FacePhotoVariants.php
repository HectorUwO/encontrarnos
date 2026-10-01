<?php

namespace App\Services\PhotoSearch;

use GdImage;
use Generator;

class FacePhotoVariants
{
    /** @return Generator<string, string> */
    public function make(string $contents): Generator
    {
        $info = @getimagesizefromstring($contents);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 4_000_000) {
            return;
        }
        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return;
        }
        $scale = 640 / max($info[0], $info[1]);
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        unset($source);
        yield 'scaled_640' => $this->jpeg($canvas);
        foreach ([90, 180, 270] as $angle) {
            $rotated = imagerotate($canvas, $angle, 0);
            if ($rotated !== false) {
                yield 'rotated_'.$angle => $this->jpeg($rotated);
                unset($rotated);
            }
        }
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 95);

        return (string) ob_get_clean();
    }
}
