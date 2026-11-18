<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Certificates\Design;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class CertificateDesignTest extends SyllabusTestCase
{
    #[Test]
    public function a_course_falls_back_to_the_forum_default_part_by_part(): void
    {
        $course = $this->course([], 4);

        [$status] = $this->json('PATCH', '/api/syllabus/certificate-design', 1, ['data' => ['template' => 'modern', 'signerName' => 'Ada Lovelace', 'signerTitle' => 'Head of school']]);
        $this->assertSame(200, $status);
        $this->multipart('/api/syllabus/certificate-design/logo', 1, [], ['image' => $this->file($this->png(), 'logo.png', 'image/png')]);

        // Nothing set on the course: every part is the forum's.
        $resolved = $this->resolve($course['id']);
        $this->assertSame('modern', $resolved['template']);
        $this->assertSame('Ada Lovelace', $resolved['signerName']);
        $this->assertSame('Head of school', $resolved['signerTitle']);
        $this->assertNotNull($resolved['logo']);

        // The course overrides the template and its signer's name; the title and logo stay the forum's.
        [$status, $body, $raw] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 4, ['data' => ['certificate' => ['template' => 'minimal', 'signerName' => 'Dave the Baker', 'signerTitle' => '']]]);
        $this->assertSame(200, $status, $raw);
        $this->assertSame('minimal', $body['data']['edit']['certificate']['template']);
        $this->assertSame('', $body['data']['edit']['certificate']['signerTitle']);
        $resolved = $this->resolve($course['id']);
        $this->assertSame('minimal', $resolved['template']);
        $this->assertSame('Dave the Baker', $resolved['signerName']);
        $this->assertSame('Head of school', $resolved['signerTitle']);
        $this->assertSame('modern', $this->resolve(null, true)['template']);

        // Clearing an override returns that part to the forum's; an unknown template is dropped.
        $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 4, ['data' => ['certificate' => ['template' => 'comic-sans', 'signerName' => '']]]);
        $resolved = $this->resolve($course['id']);
        $this->assertSame('modern', $resolved['template']);
        $this->assertSame('Ada Lovelace', $resolved['signerName']);
    }

    #[Test]
    public function with_no_design_at_all_the_forum_itself_signs_and_the_old_signature_setting_still_counts(): void
    {
        $course = $this->course();
        $resolved = $this->resolve($course['id']);
        $this->assertSame('classic', $resolved['template']);
        $this->assertSame($this->option('forum_title'), $resolved['signerName']);

        $this->option('ernestdefoe-syllabus.certificate_signature', 'The Registrar');
        $this->assertSame('The Registrar', $this->resolve($course['id'])['signerName']);
    }

    #[Test]
    public function uploads_must_be_real_images_and_are_re_encoded(): void
    {
        $course = $this->course([], 4);
        $path = '/api/syllabus/courses/'.$course['id'].'/certificate/';

        // An SVG with a script, a script named .png, and a file over the cap are refused.
        [$status] = $this->multipart($path.'logo', 4, [], ['image' => $this->file('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg', 'image/svg+xml')]);
        $this->assertSame(422, $status);
        [$status] = $this->multipart($path.'signature', 4, [], ['image' => $this->file('<?php echo 1;', 'sig.png', 'image/png')]);
        $this->assertSame(422, $status);
        [$status] = $this->multipart($path.'background', 4, [], ['image' => $this->file(str_repeat("\xFF", 9 * 1024 * 1024), 'big.jpg', 'image/jpeg')]);
        $this->assertSame(422, $status);
        [$status] = $this->multipart($path.'wallpaper', 4, [], ['image' => $this->file($this->png(), 'x.png', 'image/png')]);
        $this->assertSame(404, $status);

        // A PNG with extra bytes after its end is kept as pixels only.
        [$status, $body, $raw] = $this->multipart($path.'signature', 4, [], ['image' => $this->file($this->png().'<script>alert(1)</script>', 'sig.png', 'image/png')]);
        $this->assertSame(200, $status, $raw);
        $this->assertTrue($body['data']['hasSignature']);

        $stored = json_decode((string) Course::query()->find($course['id'])->certificate_design, true);
        $this->assertMatchesRegularExpression('~^signature/[A-Za-z0-9]{32}\.png$~', $stored['signature']);
        $bytes = $this->disk()->get($stored['signature']);
        $this->assertStringStartsWith("\x89PNG", $bytes);
        $this->assertStringNotContainsString('<script>', $bytes);

        // Replacing it removes the old file; removing it clears the part.
        $this->multipart($path.'signature', 4, [], ['image' => $this->file($this->png(), 'sig2.png', 'image/png')]);
        $this->assertFalse($this->disk()->exists($stored['signature']));
        [$status, $body] = $this->json('DELETE', $path.'signature', 4);
        $this->assertSame(200, $status);
        $this->assertFalse($body['data']['hasSignature']);
    }

    #[Test]
    public function only_the_course_instructors_and_managers_set_a_course_design_and_only_admins_the_forum_default(): void
    {
        $course = $this->course([], 4);
        $image = fn () => ['image' => $this->file($this->png(), 'x.png', 'image/png')];
        $path = '/api/syllabus/courses/'.$course['id'].'/certificate/';

        // A member, another instructor: no.
        $this->assertSame(403, $this->multipart($path.'logo', 2, [], $image())[0]);
        $this->assertSame(403, $this->multipart($path.'logo', 5, [], $image())[0]);
        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 5, ['data' => ['certificate' => ['template' => 'modern']]]);
        $this->assertSame(403, $status);
        $this->assertSame(403, $this->send($this->request('GET', $path.'preview', ['authenticatedAs' => 5]))->getStatusCode());

        // The instructor, a co-instructor, a manager (moderator), an admin: yes.
        $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 4, ['data' => ['instructorIds' => [5]]]);
        foreach ([4, 5, 6, 1] as $actor) {
            [$status, , $raw] = $this->multipart($path.'logo', $actor, [], $image());
            $this->assertSame(200, $status, "user $actor: $raw");
        }

        // The forum default is admins' alone.
        foreach ([2, 4, 6] as $actor) {
            [$status] = $this->json('PATCH', '/api/syllabus/certificate-design', $actor, ['data' => ['template' => 'modern']]);
            $this->assertSame(403, $status, "user $actor");
            $this->assertSame(403, $this->multipart('/api/syllabus/certificate-design/logo', $actor, [], $image())[0]);
        }
        [$status] = $this->json('GET', '/api/syllabus/certificate-design', null);
        $this->assertSame(403, $status);
        [$status] = $this->json('PATCH', '/api/syllabus/certificate-design', 1, ['data' => ['template' => 'modern']]);
        $this->assertSame(200, $status);
    }

    #[Test]
    public function the_preview_and_the_certificate_are_drawn_as_jpegs_and_redrawn_when_the_design_changes(): void
    {
        $course = $this->course([], 4);
        $lesson = $this->lesson($course['id'], [], 4);

        $preview = $this->send($this->request('GET', '/api/syllabus/courses/'.$course['id'].'/certificate/preview', ['authenticatedAs' => 4])->withQueryParams(['template' => 'academic', 'signerName' => 'Dave']));
        $this->assertSame(200, $preview->getStatusCode());
        $this->assertSame('image/jpeg', $preview->getHeaderLine('Content-Type'));
        $this->assertSame([1200, 849], array_slice(getimagesizefromstring((string) $preview->getBody()) ?: [], 0, 2));

        $this->enrol($course['id']);
        $this->complete($lesson['id']);
        $code = (string) $this->database()->table('syllabus_certificates')->value('code');

        // Anyone with the link sees it, a guest too; the page carries it as the share image.
        $first = $this->image($code);
        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame('image/jpeg', $first->getHeaderLine('Content-Type'));
        $this->assertSame([2400, 1697], array_slice(getimagesizefromstring((string) $first->getBody()) ?: [], 0, 2));
        [, $body] = $this->json('GET', '/api/syllabus/certificates/'.$code, null);
        $this->assertMatchesRegularExpression('~/syllabus/certificates/'.$code.'/image\?v=[0-9a-f]{16}$~', $body['data']['imageUrl']);
        $this->assertStringEndsWith('?v='.trim($first->getHeaderLine('ETag'), '"'), $body['data']['imageUrl']);
        $this->assertArrayNotHasKey('signature', $body['data']);

        // Unchanged: the same drawing (a 304 for a browser that has it).
        $etag = $first->getHeaderLine('ETag');
        $this->assertSame($etag, $this->image($code)->getHeaderLine('ETag'));
        $this->assertSame(304, $this->send($this->request('GET', "/api/syllabus/certificates/$code/image")->withHeader('If-None-Match', $etag))->getStatusCode());

        // A new design: a new drawing, and only one kept.
        $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 4, ['data' => ['certificate' => ['template' => 'modern']]]);
        $this->assertNotSame($etag, $this->image($code)->getHeaderLine('ETag'));
        $this->assertCount(1, $this->disk()->files('rendered/'.$code));

        $this->assertSame(404, $this->image(str_repeat('a', 32))->getStatusCode());
    }

    private function image(string $code): ResponseInterface
    {
        return $this->send($this->request('GET', "/api/syllabus/certificates/$code/image"));
    }

    /** @return array<string, mixed> */
    private function resolve(?int $courseId, bool $forum = false): array
    {
        $course = $courseId ? Course::query()->find($courseId) : null;

        return $this->app()->getContainer()->make(Design::class)->resolve($course, [], $forum ? 'forum' : 'course');
    }

    private function option(string $key, ?string $value = null): ?string
    {
        $settings = $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class);
        if ($value !== null) {
            $settings->set($key, $value);
        }

        return $settings->get($key);
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->app()->getContainer()->make(\Illuminate\Contracts\Filesystem\Factory::class)->disk('syllabus-certificates');
    }

    /** A real 40 × 20 PNG. */
    private function png(): string
    {
        $image = imagecreatetruecolor(40, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 40, 120));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
