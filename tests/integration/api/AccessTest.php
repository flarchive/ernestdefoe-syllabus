<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

class AccessTest extends SyllabusTestCase
{
    #[Test]
    public function guests_browse_the_catalogue_and_outline_but_never_lesson_content(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course['id']);

        [$status, $list] = $this->json('GET', '/api/syllabus/courses');
        $this->assertSame(200, $status);
        $this->assertSame('Baking Bread', $list['data'][0]['title']);

        [$status, $show, $raw] = $this->json('GET', '/api/syllabus/courses/'.$course['id']);
        $this->assertSame(200, $status, $raw);
        $this->assertSame('A lesson', $show['data']['outline'][0]['title']);
        $this->assertSame('login', $show['data']['outline'][0]['locked']);
        $this->assertStringNotContainsString('SECRET-BODY', $raw);

        [$status, $one, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id']);
        $this->assertSame(200, $status, $raw);
        $this->assertSame('login', $one['data']['locked']);
        $this->assertArrayNotHasKey('bodyHtml', $one['data']);
        $this->assertStringNotContainsString('SECRET-BODY', $raw);
    }

    #[Test]
    public function preview_lessons_are_open_to_anyone_who_can_see_the_course(): void
    {
        $course = $this->course(['previewLessons' => 1]);
        $first = $this->lesson($course['id'], ['title' => 'Free', 'body' => 'FREE-BODY']);
        $second = $this->lesson($course['id'], ['title' => 'Paid', 'body' => 'SECRET-BODY']);

        [, $one, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$first['id']);
        $this->assertNull($one['data']['locked']);
        $this->assertTrue($one['data']['preview']);
        $this->assertStringContainsString('FREE-BODY', $raw);

        [, $two, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$second['id'], 2);
        $this->assertSame('enrol', $two['data']['locked']);
        $this->assertStringNotContainsString('SECRET-BODY', $raw);
    }

    #[Test]
    public function members_read_lessons_once_enrolled(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course['id']);

        [, $before, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertSame('enrol', $before['data']['locked']);
        $this->assertStringNotContainsString('SECRET-BODY', $raw);

        $enrolled = $this->enrol($course['id']);
        $this->assertTrue($enrolled['me']['enrolled']);
        $this->assertSame(1, $enrolled['enrolmentCount']);

        [, $after, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertNull($after['data']['locked']);
        $this->assertStringContainsString('SECRET-BODY', $raw);

        // Guests cannot enrol at all.
        [$status] = $this->json('POST', '/api/syllabus/courses/'.$course['id'].'/enrolment');
        $this->assertSame(401, $status);
    }

    #[Test]
    public function group_restricted_courses_open_only_to_their_groups(): void
    {
        $course = $this->course(['access' => 'groups', 'groupIds' => [self::SUPPORTERS]]);
        $lesson = $this->lesson($course['id']);

        // Not in the group: may see the course, may not enrol or read.
        [, $show] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertFalse($show['data']['canEnrol']);
        $this->assertSame('access', $show['data']['outline'][0]['locked']);
        [$status] = $this->json('POST', '/api/syllabus/courses/'.$course['id'].'/enrolment', 2);
        $this->assertSame(403, $status);

        // In the group (pat): enrols and reads.
        $this->enrol($course['id'], 7);
        [, $one, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 7);
        $this->assertNull($one['data']['locked']);
        $this->assertStringContainsString('SECRET-BODY', $raw);

        // Leaves the group (a lapsed payment): locked again, enrolment or not.
        $this->database()->table('group_user')->where('user_id', 7)->delete();
        [, $one, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 7);
        $this->assertSame('access', $one['data']['locked']);
        $this->assertStringNotContainsString('SECRET-BODY', $raw);
    }

    #[Test]
    public function a_groups_course_needs_at_least_one_group(): void
    {
        [$status, , $raw] = $this->json('POST', '/api/syllabus/courses', 1, ['data' => ['title' => 'X', 'access' => 'groups', 'groupIds' => []]]);
        $this->assertSame(422, $status, $raw);
    }

    #[Test]
    public function a_paid_course_stays_locked_without_kiosk(): void
    {
        // Kiosk isn't enabled here: "paid" must fail closed, never open.
        $course = $this->course(['access' => 'paid']);
        $this->assertSame('paid', $course['access']);
        $this->lesson($course['id']);

        [, $show] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertFalse($show['data']['canEnrol']);
        $this->assertSame('access', $show['data']['outline'][0]['locked']);
        [$status] = $this->json('POST', '/api/syllabus/courses/'.$course['id'].'/enrolment', 2);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function drafts_are_invisible_to_members(): void
    {
        $draft = $this->course(['status' => 'draft']);
        $course = $this->course();
        $hidden = $this->lesson($course['id'], ['status' => 'draft', 'title' => 'Hidden draft']);

        [$status] = $this->json('GET', '/api/syllabus/courses/'.$draft['id'], 2);
        $this->assertSame(404, $status);
        [, $list] = $this->json('GET', '/api/syllabus/courses', 2);
        $this->assertCount(1, $list['data']);

        [, $show, $raw] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertStringNotContainsString('Hidden draft', $raw);
        [$status] = $this->json('GET', '/api/syllabus/lessons/'.$hidden['id'], 2);
        $this->assertSame(404, $status);
    }

    #[Test]
    public function video_addresses_and_files_stay_behind_the_lock(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course['id'], ['type' => 'video', 'videoUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        [, $locked, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertArrayNotHasKey('video', $locked['data']);
        $this->assertStringNotContainsString('dQw4w9WgXcQ', $raw);

        // An uploaded file, too.
        [$status, $up, $raw] = $this->multipart('/api/syllabus/lessons/'.$lesson['id'].'/video', 1, [], ['video' => $this->file($this->tinyMp4(), 'clip.mp4', 'video/mp4')]);
        $this->assertSame(200, $status, $raw);
        $fileId = $up['data']['videoFile']['id'];

        [$status] = $this->json('GET', '/api/syllabus/files/'.$fileId, 2);
        $this->assertSame(404, $status);

        $this->enrol($course['id']);
        [, $open] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertSame('file', $open['data']['video']['provider']);

        $response = $this->send($this->request('GET', '/api/syllabus/files/'.$fileId, ['authenticatedAs' => 2]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('attachment;', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));

        // With the file gone the embed is the fallback.
        $this->json('PATCH', '/api/syllabus/lessons/'.$lesson['id'], 1, ['data' => ['title' => 'Still a video']]);
        $this->send($this->request('DELETE', '/api/syllabus/lessons/'.$lesson['id'].'/video', ['authenticatedAs' => 1]));
        [, $open] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', $open['data']['video']['embedUrl']);
    }

    #[Test]
    public function only_youtube_and_vimeo_addresses_are_accepted(): void
    {
        $course = $this->course();

        foreach (['javascript:alert(1)', 'https://evil.example/watch?v=dQw4w9WgXcQ', "java\nscript:alert(1)", 'data:text/html,hi'] as $bad) {
            [$status] = $this->json('POST', "/api/syllabus/courses/{$course['id']}/lessons", 1, ['data' => ['title' => 'V', 'type' => 'video', 'videoUrl' => $bad]]);
            $this->assertSame(422, $status, $bad);
        }

        $lesson = $this->lesson($course['id'], ['type' => 'video', 'videoUrl' => 'https://vimeo.com/76979871']);
        [, $open] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 1);
        $this->assertSame('https://player.vimeo.com/video/76979871?dnt=1', $open['data']['video']['embedUrl']);
    }

    #[Test]
    public function cover_urls_must_be_https(): void
    {
        [$status] = $this->json('POST', '/api/syllabus/courses', 1, ['data' => ['title' => 'X', 'coverUrl' => 'javascript:alert(1)']]);
        $this->assertSame(422, $status);
        [$status] = $this->json('POST', '/api/syllabus/courses', 1, ['data' => ['title' => 'X', 'coverUrl' => 'http://example.com/a.png']]);
        $this->assertSame(422, $status);
        $course = $this->course(['coverUrl' => 'https://example.com/a.png']);
        $this->assertSame('https://example.com/a.png', $course['coverUrl']);
    }

    #[Test]
    public function an_uploaded_cover_is_re_encoded_and_an_svg_is_refused(): void
    {
        $course = $this->course();

        [$status] = $this->multipart('/api/syllabus/courses/'.$course['id'].'/cover', 1, [], ['cover' => $this->file('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg', 'image/svg+xml')]);
        $this->assertSame(422, $status);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        [$status, $body, $raw] = $this->multipart('/api/syllabus/courses/'.$course['id'].'/cover', 1, [], ['cover' => $this->file($png, 'x.png', 'image/png')]);
        $this->assertSame(200, $status, $raw);
        $this->assertMatchesRegularExpression('~/assets/syllabus/[A-Za-z0-9]{32}\.webp$~', $body['data']['coverUrl']);
    }

    /** Enough of an MP4 header for finfo to call it video/mp4. */
    private function tinyMp4(): string
    {
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 64);
    }
}
