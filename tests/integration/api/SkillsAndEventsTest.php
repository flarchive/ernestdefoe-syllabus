<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Event\CertificateIssued;
use Ernestdefoe\Syllabus\Event\CourseCompleted;
use Ernestdefoe\Syllabus\Event\CourseEnrolled;
use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class SkillsAndEventsTest extends SyllabusTestCase
{
    /** @var list<array{0: string, 1: int, 2: int}> */
    private array $fired = [];

    private function record(): void
    {
        $events = $this->app()->getContainer()->make(Dispatcher::class);
        foreach ([CourseEnrolled::class, CourseCompleted::class, CertificateIssued::class] as $class) {
            $events->listen($class, function ($e) use ($class) {
                $this->fired[] = [substr($class, strrpos($class, '\\') + 1), $e->user->id, $e->course->id];
                if ($e instanceof CertificateIssued) {
                    $this->assertSame($e->course->id, $e->certificate->course_id);
                }
            });
        }
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_column($this->fired, 0);
    }

    #[Test]
    public function editors_set_skills_which_show_on_the_course_page(): void
    {
        $course = $this->course(['skills' => 'Photogrammetry, 3D Reconstruction , photogrammetry,, RealityCapture'], 4);
        $this->assertSame(['Photogrammetry', '3D Reconstruction', 'RealityCapture'], $course['skills']);

        [$status, $body] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 4, ['data' => ['skills' => ['Meshing', ' ']]]);
        $this->assertSame(200, $status);
        $this->assertSame(['Meshing'], $body['data']['skills']);
        $this->assertSame(['Meshing'], $body['data']['edit']['skills']);

        [, $page] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertSame(['Meshing'], $page['data']['skills']);

        // Too many, and only the course's editors.
        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 4, ['data' => ['skills' => array_map(fn ($i) => "Skill $i", range(1, 21))]]);
        $this->assertSame(422, $status);
        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 5, ['data' => ['skills' => ['Hijacked']]]);
        $this->assertSame(403, $status);

        // Clearing them.
        [, $body] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 4, ['data' => ['skills' => []]]);
        $this->assertSame([], $body['data']['skills']);
    }

    #[Test]
    public function the_events_fire_once_for_confirmed_facts_and_never_for_a_visit(): void
    {
        $course = $this->course(['skills' => ['Baking', 'Proofing']]);
        $a = $this->lesson($course['id']);
        $b = $this->lesson($course['id']);
        $this->record();

        // Visiting is not enrolling.
        $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertSame([], $this->fired);

        $this->enrol($course['id']);
        $this->enrol($course['id']);
        $this->assertSame([['CourseEnrolled', 2, $course['id']]], $this->fired);

        // Partial progress and revisits fire nothing.
        $this->complete($a['id']);
        $this->json('GET', '/api/syllabus/lessons/'.$a['id'], 2);
        $this->complete($a['id']);
        $this->assertSame(['CourseEnrolled'], $this->names());

        $this->complete($b['id']);
        $this->assertSame(['CourseEnrolled', 'CourseCompleted', 'CertificateIssued'], $this->names());

        // Completing lessons again, revisiting, and leaving and coming back: the
        // enrollment is new, the completion and the certificate are not.
        $this->json('DELETE', '/api/syllabus/lessons/'.$b['id'].'/complete', 2);
        $this->complete($b['id']);
        $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->json('DELETE', '/api/syllabus/courses/'.$course['id'].'/enrolment', 2);
        $this->enrol($course['id']);
        $this->assertSame(['CourseEnrolled', 'CourseCompleted', 'CertificateIssued', 'CourseEnrolled'], $this->names());
        $this->assertSame(1, $this->database()->table('syllabus_certificates')->count());
    }

    #[Test]
    public function a_certificate_keeps_the_skills_it_was_issued_with(): void
    {
        $course = $this->course(['skills' => ['Baking', 'Proofing']]);
        $lesson = $this->lesson($course['id']);
        $this->enrol($course['id']);
        $this->complete($lesson['id']);

        $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 1, ['data' => ['skills' => ['Something else']]]);

        [$status, $body] = $this->json('GET', '/api/syllabus/users/2/achievements', 2);
        $this->assertSame(200, $status);
        $certificate = $body['data']['certificates'][0];
        $this->assertSame('Baking Bread', $certificate['courseTitle']);
        $this->assertSame(['Baking', 'Proofing'], $certificate['skills']);
        $this->assertNotEmpty($certificate['issuer']);
        $this->assertStringEndsWith('/certificates/'.$certificate['code'], $certificate['verifyUrl']);
        $this->assertSame(['id' => $course['id'], 'slug' => 'baking-bread'], $certificate['course']);
        $this->assertSame(
            [['name' => 'Baking', 'courseTitle' => 'Baking Bread', 'certificateCode' => $certificate['code']], ['name' => 'Proofing', 'courseTitle' => 'Baking Bread', 'certificateCode' => $certificate['code']]],
            $body['data']['skills']
        );
    }

    #[Test]
    public function achievements_follow_the_courses_tab_privacy(): void
    {
        $course = $this->course(['skills' => ['Baking']]);
        $lesson = $this->lesson($course['id']);
        $this->enrol($course['id']);
        $this->complete($lesson['id']);

        // Shown: guest, another member, the owner, an admin.
        foreach ([null, 3, 2, 1] as $actor) {
            [$status, $body] = $this->json('GET', '/api/syllabus/users/2/achievements', $actor);
            $this->assertSame(200, $status, 'actor '.var_export($actor, true));
            $this->assertCount(1, $body['data']['certificates']);
        }

        // Kept private: only the owner and course managers (moderator, admin) see them.
        $this->database()->table('users')->where('id', 2)->update(['preferences' => json_encode(['syllabusShowCourses' => false])]);
        foreach ([null => true, 3 => true, 2 => false, 6 => false, 1 => false] as $actor => $hidden) {
            [$status, $body] = $this->json('GET', '/api/syllabus/users/2/achievements', $actor ?: null);
            $this->assertSame(200, $status);
            $this->assertSame($hidden, $body['meta']['hidden'], 'actor '.$actor);
            $this->assertSame($hidden, $body['data'] === null);
        }

        // A course hidden from the viewer hides its certificate from them too (not from the owner or a manager).
        $this->database()->table('users')->where('id', 2)->update(['preferences' => null]);
        $this->json('PATCH', '/api/syllabus/courses/'.$course['id'], 1, ['data' => ['status' => 'draft']]);
        foreach ([null => 0, 3 => 0, 2 => 1, 6 => 1, 1 => 1] as $actor => $count) {
            [, $body] = $this->json('GET', '/api/syllabus/users/2/achievements', $actor ?: null);
            $this->assertCount($count, $body['data']['certificates'], 'actor '.$actor);
            $this->assertCount($count, $body['data']['skills'], 'actor '.$actor);
        }

        [$status] = $this->json('GET', '/api/syllabus/users/999/achievements', 1);
        $this->assertSame(404, $status);
    }
}
