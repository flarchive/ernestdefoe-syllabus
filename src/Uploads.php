<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Every file Syllabus accepts.
 *
 * Covers are images only: the bytes are sniffed, decoded and re-encoded to
 * WebP by Intervention Image (so nothing but pixels survives — no SVG, no
 * script, no metadata), capped in size and stored under a random name with
 * the forum's public assets.
 *
 * Lesson videos and assignment files are kept outside the public folder under
 * random names and only ever served by {@see response()}, after the caller has
 * checked access, as attachments with nosniff: an uploaded HTML or SVG file
 * can never render on the forum's origin.
 */
class Uploads
{
    public const COVER_MAX_BYTES = 5 * 1024 * 1024;
    public const COVER_WIDTH = 1600;
    public const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv'];
    public const DEFAULT_FILE_TYPES = 'pdf,txt,md,zip,png,jpg,jpeg,gif,webp,doc,docx,odt,rtf,ppt,pptx,odp,xls,xlsx,ods,csv';

    /** Never accepted, whatever the extension says: these could run in a browser. */
    private const DANGEROUS = ['html', 'xhtml', 'svg', 'xml', 'javascript', 'ecmascript', 'x-php', 'x-httpd', 'x-sh', 'x-msdownload', 'x-executable'];

    public function __construct(
        private Factory $filesystems,
        private ImageManager $images,
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator
    ) {
    }

    public function disk(): Filesystem
    {
        return $this->filesystems->disk('syllabus-files');
    }

    public function coverUrl(Course $course): ?string
    {
        if ($course->cover_path) {
            return $this->filesystems->disk('flarum-assets')->url($course->cover_path);
        }

        return $course->cover_url;
    }

    /** Store a cover image; returns its path on the assets disk. */
    public function storeCover(UploadedFileInterface $file): string
    {
        $tmp = $this->accept($file, 'cover', self::COVER_MAX_BYTES);

        $mime = $this->sniff($tmp);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $this->fail('cover', 'not_an_image');
        }

        try {
            $image = $this->images->read($tmp);
            $image->scaleDown(width: self::COVER_WIDTH);
            $encoded = (string) $image->toWebp(82);
        } catch (\Throwable) {
            $this->fail('cover', 'not_an_image');
        }

        $path = 'syllabus/'.Str::random(32).'.webp';
        $this->filesystems->disk('flarum-assets')->put($path, $encoded);

        return $path;
    }

    public function deleteCover(?string $path): void
    {
        if ($path && str_starts_with($path, 'syllabus/')) {
            $this->filesystems->disk('flarum-assets')->delete($path);
        }
    }

    /** A lesson video: MP4, WebM or Ogg only, up to the admin's cap. */
    public function storeVideo(UploadedFileInterface $file, Lesson $lesson, int $userId): Upload
    {
        $max = $this->mb('max_video_mb', 200);
        if ($max <= 0) {
            $this->fail('video', 'video_off');
        }

        $tmp = $this->accept($file, 'video', $max * 1024 * 1024);
        $mime = $this->sniff($tmp);
        if (! isset(self::VIDEO_TYPES[$mime])) {
            $this->fail('video', 'video_type');
        }

        return $this->keep($tmp, $file, 'video', $mime, $lesson->course_id, $lesson->id, null, $userId);
    }

    /** An assignment file: an allow-listed extension, up to the admin's cap, never anything a browser would run. */
    public function storeSubmissionFile(UploadedFileInterface $file, Submission $submission): Upload
    {
        $tmp = $this->accept($file, 'files', $this->mb('max_file_mb', 10) * 1024 * 1024);

        $name = $this->cleanName((string) $file->getClientFilename());
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || ! in_array($ext, $this->fileTypes(), true)) {
            $this->fail('files', 'file_type', ['{types}' => implode(', ', $this->fileTypes())]);
        }

        $mime = $this->sniff($tmp);
        foreach (self::DANGEROUS as $bad) {
            if (str_contains($mime, $bad)) {
                $this->fail('files', 'file_type', ['{types}' => implode(', ', $this->fileTypes())]);
            }
        }

        return $this->keep($tmp, $file, 'submission', $mime, $submission->course_id, $submission->lesson_id, $submission->id, $submission->user_id);
    }

    /** @return list<string> */
    public function fileTypes(): array
    {
        $raw = (string) ($this->settings->get('ernestdefoe-syllabus.file_types') ?: self::DEFAULT_FILE_TYPES);
        $types = array_filter(array_map(fn ($t) => strtolower(trim($t, " .\t\n")), explode(',', $raw)), fn ($t) => preg_match('/^[a-z0-9]{1,10}$/', $t));

        // These are never allowed, even if an admin lists them.
        return array_values(array_diff(array_unique($types), ['html', 'htm', 'xhtml', 'svg', 'svgz', 'xml', 'js', 'mjs', 'php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'msi', 'jar', 'hta']));
    }

    public function mb(string $key, int $default): int
    {
        $value = $this->settings->get('ernestdefoe-syllabus.'.$key);

        return max(0, min(4096, is_numeric($value) ? (int) $value : $default));
    }

    public function delete(Upload $upload): void
    {
        $this->disk()->delete($upload->path);
        $upload->delete();
    }

    /**
     * The file as a download. Videos stream with byte ranges (Safari will not
     * play a video without them); everything else is sent as
     * application/octet-stream so a browser never interprets it.
     */
    public function response(Upload $upload, ServerRequestInterface $request): ResponseInterface
    {
        $disk = $this->disk();
        if (! $disk->exists($upload->path)) {
            return new Response\EmptyResponse(404);
        }

        $size = (int) $disk->size($upload->path);
        $type = $upload->kind === 'video' && isset(self::VIDEO_TYPES[$upload->mime]) ? $upload->mime : 'application/octet-stream';
        $ascii = (string) preg_replace('/[^A-Za-z0-9._ -]/', '_', $upload->name);
        $headers = [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($upload->name),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ];

        if ($upload->kind !== 'video') {
            $stream = $disk->readStream($upload->path);

            return new Response(new Stream($stream), 200, $headers + ['Content-Length' => (string) $size]);
        }

        [$start, $end] = [0, $size - 1];
        $status = 200;
        if (preg_match('/^bytes=(\d*)-(\d*)$/', $request->getHeaderLine('Range'), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
            }
            if ($start > $end || $start >= $size) {
                return new Response\EmptyResponse(416, ['Content-Range' => 'bytes */'.$size]);
            }
            $status = 206;
            $headers['Content-Range'] = "bytes $start-$end/$size";
        }

        $length = $end - $start + 1;
        $path = $upload->path;

        // Streamed in pieces, never read into memory whole.
        $body = new CallbackStream(function () use ($disk, $path, $start, $length) {
            $handle = $disk->readStream($path);
            if (! $handle) {
                return '';
            }
            fseek($handle, $start);
            $left = $length;
            while ($left > 0 && ! feof($handle)) {
                $chunk = (string) fread($handle, min(1048576, $left));
                $left -= strlen($chunk);
                echo $chunk;
                flush();
            }
            fclose($handle);

            return '';
        });

        return new Response($body, $status, $headers + ['Content-Length' => (string) $length]);
    }

    private function keep(string $tmp, UploadedFileInterface $file, string $kind, string $mime, int $courseId, ?int $lessonId, ?int $submissionId, ?int $userId): Upload
    {
        $path = $kind.'/'.Str::random(40);
        $handle = fopen($tmp, 'rb');
        $this->disk()->writeStream($path, $handle);
        if (is_resource($handle)) {
            fclose($handle);
        }

        $upload = new Upload();
        $upload->user_id = $userId;
        $upload->course_id = $courseId;
        $upload->lesson_id = $lessonId;
        $upload->submission_id = $submissionId;
        $upload->kind = $kind;
        $upload->path = $path;
        $upload->name = $this->cleanName((string) $file->getClientFilename());
        $upload->mime = mb_substr($mime, 0, 100);
        $upload->size = (int) filesize($tmp);
        $upload->created_at = Carbon::now();
        $upload->save();

        return $upload;
    }

    /** The upload's temporary path, after the error and size checks. */
    private function accept(UploadedFileInterface $file, string $field, int $maxBytes): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $this->fail($field, in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'too_big' : 'upload_failed', ['{mb}' => (string) max(1, intdiv($maxBytes, 1048576))]);
        }

        if ((int) $file->getSize() > $maxBytes || (int) $file->getSize() === 0) {
            $this->fail($field, (int) $file->getSize() === 0 ? 'upload_failed' : 'too_big', ['{mb}' => (string) max(1, intdiv($maxBytes, 1048576))]);
        }

        $tmp = $file->getStream()->getMetadata('uri');
        if (! is_string($tmp) || ! is_file($tmp)) {
            $this->fail($field, 'upload_failed');
        }

        return $tmp;
    }

    /** What the bytes are, whatever the name or the browser claimed. */
    private function sniff(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mime) ? strtolower($mime) : 'application/octet-stream';
    }

    private function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F"]+/u', '', $name));

        return mb_substr($name !== '' ? $name : 'file', 0, 200);
    }

    /** @param array<string, string> $params */
    private function fail(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => $this->translator->trans('ernestdefoe-syllabus.api.'.$key, $params)]);
    }
}
