<?php

namespace Ernestdefoe\Syllabus\Certificates;

use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Certificate logos, backgrounds and signatures, and the drawn certificates.
 *
 * Uploads are images only: the bytes are sniffed (never the name or the
 * browser's claim), their size is checked before decoding, and they are
 * decoded and re-encoded by Intervention Image, so nothing but pixels is kept
 * (no SVG, no script, no metadata). They live on a private disk outside the
 * public folder and are never served on their own: Renderer draws them into
 * the certificate, which is served as a JPEG.
 */
class Images
{
    public const DISK = 'syllabus-certificates';
    public const MAX_BYTES = ['logo' => 5 * 1024 * 1024, 'background' => 8 * 1024 * 1024, 'signature' => 5 * 1024 * 1024];
    /** Largest size kept: big enough for a 2400 px certificate, small enough to draw quickly. */
    public const BOX = ['logo' => [1200, 600], 'background' => [2400, 1700], 'signature' => [1200, 500]];
    /** Refuse anything that would need more memory than this to decode. */
    public const MAX_PIXELS = 40_000_000;

    private const TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private Factory $filesystems, private ImageManager $images, private TranslatorInterface $translator, private LoggerInterface $log)
    {
    }

    public function disk(): Filesystem
    {
        return $this->filesystems->disk(self::DISK);
    }

    /** Stores an uploaded image of the given kind; returns its path on the certificates disk. */
    public function store(UploadedFileInterface $file, string $kind): string
    {
        $max = self::MAX_BYTES[$kind];
        $mb = ['{mb}' => (string) intdiv($max, 1048576)];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $this->fail($kind, in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'too_big' : 'upload_failed', $mb);
        }
        $size = (int) $file->getSize();
        if ($size === 0 || $size > $max) {
            $this->fail($kind, $size === 0 ? 'upload_failed' : 'too_big', $mb);
        }
        $tmp = $file->getStream()->getMetadata('uri');
        if (! is_string($tmp) || ! is_file($tmp)) {
            $this->fail($kind, 'upload_failed');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $dimensions = @getimagesize($tmp);
        if (! in_array($mime, self::TYPES, true) || ! $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1) {
            $this->fail($kind, 'not_an_image');
        }
        if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
            $this->fail($kind, 'image_too_large');
        }

        [$width, $height] = self::BOX[$kind];
        try {
            $image = $this->images->read($tmp);
            $image->scaleDown($width, $height);
            // A background is a photo: JPEG. Logos and signatures keep their transparency: PNG.
            $encoded = $kind === 'background' ? (string) $image->toJpeg(88) : (string) $image->toPng();
        } catch (\Throwable) {
            $this->fail($kind, 'not_an_image');
        }

        $path = $kind.'/'.Str::random(32).($kind === 'background' ? '.jpg' : '.png');
        $this->disk()->put($path, $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        foreach (Design::IMAGES as $kind) {
            if ($path && self::validPath($path, $kind)) {
                $this->disk()->delete($path);
            }
        }
    }

    /** The bytes of an image Renderer may draw, or null. */
    public function read(?array $ref): ?string
    {
        if (! $ref) {
            return null;
        }

        try {
            $disk = $this->filesystems->disk($ref['disk']);

            return $disk->exists($ref['path']) ? $disk->get($ref['path']) : null;
        } catch (\Throwable $e) {
            // A missing or unreadable image leaves its place on the certificate empty.
            $this->log->warning('Syllabus: could not read certificate image '.$ref['path'].': '.$e->getMessage());

            return null;
        }
    }

    /** Only paths this class wrote: kind/32 letters and digits.png|jpg. */
    public static function validPath(string $path, string $kind): bool
    {
        return (bool) preg_match('~^'.preg_quote($kind, '~').'/[A-Za-z0-9]{32}\.(png|jpg)$~', $path);
    }

    /** Whether a forum logo can be drawn: GD reads these, not SVG. */
    public function isRaster(string $path): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|gif|webp)$/i', $path);
    }

    /** @param array<string, string> $params */
    private function fail(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => $this->translator->trans('ernestdefoe-syllabus.api.'.$key, $params)]);
    }
}
