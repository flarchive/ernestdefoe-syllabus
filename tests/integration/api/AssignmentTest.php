<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

class AssignmentTest extends SyllabusTestCase
{
    /** dave's course with one assignment; normal (2) and carol (3) enrolled. */
    private function setUpCourse(): array
    {
        $course = $this->course([], 4);
        $task = $this->lesson($course['id'], ['type' => 'assignment', 'title' => 'Bake a loaf', 'body' => 'Photograph it.'], 4);
        $this->enrol($course['id'], 2);
        $this->enrol($course['id'], 3);

        return [$course, $task];
    }

    #[Test]
    public function a_student_hands_in_work_and_an_instructor_passes_it(): void
    {
        [$course, $task] = $this->setUpCourse();

        [$status, $sub, $raw] = $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => "My loaf <script>x</script>\nwas good"], [
            'files' => [$this->file('%PDF-1.4 fake pdf', 'loaf.pdf', 'application/pdf')],
        ]);
        $this->assertSame(201, $status, $raw);
        $this->assertSame('pending', $sub['data']['status']);
        $this->assertSame("My loaf <script>x</script>\nwas good", $sub['data']['body'], 'kept as text, shown as text');
        $this->assertCount(1, $sub['data']['files']);
        $fileId = $sub['data']['files'][0]['id'];

        // The instructor was told.
        $this->assertSame(1, $this->database()->table('notifications')->where('user_id', 4)->where('type', 'syllabusSubmissionReceived')->count());

        // One waiting at a time.
        [$status] = $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => 'again'], []);
        $this->assertSame(422, $status);

        // The student and the instructor may download it; another student, another instructor and a guest may not.
        foreach ([2 => 200, 4 => 200, 1 => 200, 3 => 404, 5 => 404] as $actor => $expected) {
            $response = $this->send($this->request('GET', '/api/syllabus/files/'.$fileId, ['authenticatedAs' => $actor]));
            $this->assertSame($expected, $response->getStatusCode(), "user $actor");
            if ($expected === 200) {
                $this->assertSame('application/octet-stream', $response->getHeaderLine('Content-Type'));
                $this->assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));
            }
        }
        $this->assertSame(404, $this->send($this->request('GET', '/api/syllabus/files/'.$fileId)->withAttribute('bypassCsrfToken', true))->getStatusCode());

        // The queue: dave sees it, erin (another instructor) does not, a student may not ask.
        [, $queue] = $this->json('GET', '/api/syllabus/submissions', 4);
        $this->assertCount(1, $queue['data']);
        [, $other] = $this->json('GET', '/api/syllabus/submissions', 5);
        $this->assertCount(0, $other['data']);
        [$status] = $this->json('GET', '/api/syllabus/submissions', 3);
        $this->assertSame(403, $status);

        // Only the course's instructors (and managers) may review.
        [$status] = $this->json('PATCH', '/api/syllabus/submissions/'.$sub['data']['id'], 5, ['data' => ['status' => 'passed']]);
        $this->assertSame(404, $status);
        [$status] = $this->json('PATCH', '/api/syllabus/submissions/'.$sub['data']['id'], 3, ['data' => ['status' => 'passed']]);
        $this->assertSame(403, $status);

        [$status, $reviewed] = $this->json('PATCH', '/api/syllabus/submissions/'.$sub['data']['id'], 4, ['data' => ['status' => 'passed', 'feedback' => 'Lovely crumb.']]);
        $this->assertSame(200, $status);
        $this->assertSame('passed', $reviewed['data']['status']);
        $this->assertSame('dave', $reviewed['data']['reviewer']['username']);
        $this->assertSame(1, $this->database()->table('notifications')->where('user_id', 2)->where('type', 'syllabusAssignmentReviewed')->count());

        // Passing completed the lesson — and with it, this one-lesson course.
        [, $show] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertSame(100, $show['data']['me']['percent']);
        $this->assertNotNull($show['data']['certificateCode']);

        // No more submissions after a pass.
        [$status] = $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => 'more'], []);
        $this->assertSame(422, $status);
    }

    #[Test]
    public function students_never_see_each_others_work(): void
    {
        [, $task] = $this->setUpCourse();
        $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => 'NORMALS-WORK'], []);

        [, $carol, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$task['id'], 3);
        $this->assertSame([], $carol['data']['submissions']);
        $this->assertTrue($carol['data']['canSubmit']);
        $this->assertStringNotContainsString('NORMALS-WORK', $raw);

        [, $own] = $this->json('GET', '/api/syllabus/lessons/'.$task['id'], 2);
        $this->assertCount(1, $own['data']['submissions']);
        $this->assertFalse($own['data']['canSubmit']);
    }

    #[Test]
    public function needs_work_lets_the_student_try_again(): void
    {
        [, $task] = $this->setUpCourse();
        [, $sub] = $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => 'try one'], []);
        $this->json('PATCH', '/api/syllabus/submissions/'.$sub['data']['id'], 4, ['data' => ['status' => 'needs_work', 'feedback' => 'Longer proof.']]);

        [, $lesson] = $this->json('GET', '/api/syllabus/lessons/'.$task['id'], 2);
        $this->assertTrue($lesson['data']['canSubmit']);
        $this->assertFalse($lesson['data']['completed']);
        $this->assertSame('Longer proof.', $lesson['data']['submissions'][0]['feedback']);

        [$status] = $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => 'try two'], []);
        $this->assertSame(201, $status);
    }

    #[Test]
    public function unsafe_files_are_refused(): void
    {
        [, $task] = $this->setUpCourse();

        $cases = [
            ['<html><script>alert(1)</script></html>', 'page.html'],
            ['<svg xmlns="http://www.w3.org/2000/svg"/>', 'pic.svg'],
            // An allowed extension doesn't help HTML in disguise.
            ["<!DOCTYPE html>\n<html><body><script>alert(1)</script></body></html>", 'notes.txt'],
            ['<?php echo 1;', 'shell.php'],
        ];
        foreach ($cases as [$contents, $name]) {
            [$status, , $raw] = $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => 'x'], ['files' => [$this->file($contents, $name)]]);
            $this->assertSame(422, $status, $name.': '.$raw);
        }
        $this->assertSame(0, $this->database()->table('syllabus_submissions')->count(), 'a refused file leaves no submission behind');
        $this->assertSame(0, $this->database()->table('syllabus_files')->count());
    }

    #[Test]
    public function submissions_are_limited_per_hour(): void
    {
        $this->setting('ernestdefoe-syllabus.submissions_per_hour', 2);
        $course = $this->course([], 4);
        $this->enrol($course['id']);
        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $tasks[] = $this->lesson($course['id'], ['type' => 'assignment', 'title' => "Task $i"], 4);
        }

        $this->assertSame(201, $this->multipart('/api/syllabus/lessons/'.$tasks[0]['id'].'/submissions', 2, ['body' => 'a'], [])[0]);
        $this->assertSame(201, $this->multipart('/api/syllabus/lessons/'.$tasks[1]['id'].'/submissions', 2, ['body' => 'b'], [])[0]);
        $this->assertSame(422, $this->multipart('/api/syllabus/lessons/'.$tasks[2]['id'].'/submissions', 2, ['body' => 'c'], [])[0]);
    }

    #[Test]
    public function only_enrolled_members_with_the_lesson_open_may_hand_in(): void
    {
        $course = $this->course([], 4);
        $task = $this->lesson($course['id'], ['type' => 'assignment'], 4);

        [$status] = $this->multipart('/api/syllabus/lessons/'.$task['id'].'/submissions', 2, ['body' => 'x'], []);
        $this->assertSame(403, $status);
    }
}
