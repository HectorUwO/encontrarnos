<?php

namespace App\Services\Photos;

use App\Enums\PhotoSize;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Genera y guarda versiones reducidas de fotografías (las de las fichas y las
 * de las solicitudes). Cada una se crea la primera vez que se pide y se
 * reutiliza después.
 */
class PhotoThumbnails
{
    private const JPEG_QUALITY = 82;

    /**
     * Bytes que GD necesita por píxel para decodificar, girar y escalar.
     */
    private const BYTES_PER_PIXEL = 5;

    /**
     * Ruta, en el disco de miniaturas, de la fotografía guardada en `$path` de
     * `$disk` a ese tamaño. Devuelve null si no se pudo generar (archivo
     * ilegible, imagen demasiado grande para la memoria); entonces conviene
     * servir la original.
     *
     * Las fotografías se guardan con un nombre que nunca se reutiliza (su
     * huella o un identificador único), por eso la miniatura no caduca.
     */
    public function pathFor(string $disk, string $path, PhotoSize $size): ?string
    {
        $target = $size->value.'/'.sha1("{$disk}:{$path}").'.jpg';
        $thumbnails = Storage::disk('record_thumbnails');

        if ($thumbnails->exists($target)) {
            return $target;
        }

        $original = Storage::disk($disk)->get($path);
        $resized = is_string($original) ? $this->resize($original, $size->width()) : null;

        return $resized !== null && $thumbnails->put($target, $resized) ? $target : null;
    }

    private function resize(string $contents, int $maxWidth): ?string
    {
        $info = @getimagesizefromstring($contents);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        $orientation = $this->orientation($contents);

        // Un JPEG que ya cabe y no necesita girarse se guarda tal cual: volver
        // a comprimirlo solo le quitaría calidad y a veces lo haría más pesado.
        if ($info[2] === IMAGETYPE_JPEG && $info[0] <= $maxWidth && $orientation === 1) {
            return $contents;
        }

        if (! $this->fitsInMemory($info[0], $info[1])) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        $source = $this->orient($source, $orientation);
        $width = min($maxWidth, imagesx($source));
        $height = max(1, (int) round(imagesy($source) * $width / imagesx($source)));

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imageinterlace($canvas, true);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();

        return $jpeg === '' ? null : $jpeg;
    }

    private function fitsInMemory(int $width, int $height): bool
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return true;
        }

        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return memory_get_usage(true) + $width * $height * self::BYTES_PER_PIXEL < $bytes;
    }

    /**
     * Orientación EXIF (1 a 8) con la que la cámara guardó la foto; los
     * navegadores la aplican al mostrar el original, pero GD no.
     */
    private function orientation(string $contents): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        } ?: $image;

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }
}
