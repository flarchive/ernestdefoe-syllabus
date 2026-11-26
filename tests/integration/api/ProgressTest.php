<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

class ProgressTest extends SyllabusTestCase
{
    #[Test]
    public function lessons_unlock_in_order_when_the_course_says_so(): void
    {
        $course = $this->course(['sequential' => true]);
        $one = $this->lesson($course['id'], ['title' => 'One']);
        $two = $this->lesson($course['id'], ['title' => 'Two', 'body' => 'SECOND-BODY']);
        $this->enrol($course['id']);

        [, $locked, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$two['id'], 2);
        $this->assertSame('order', $locked['data']['locked']);
        $this->assertStringNotContainsString('SECOND-BODY', $raw);
        [$status] = $this->json('POST', '/api/syllabus/lessons/'.$two['id'].'/complete', 2);
        $this->assertSame(403, $status, 'a locked lesson cannot be ticked off');

        $done = $this->complete($one['id']);
        $this->assertSame(['done' => 1, 'total' => 2, 'percent' => 50], $done['progress']);
        $this->assertTrue($done['nextUnlocked']);

        [, $open, $raw] = $this->json('GET', '/api/syllabus/lessons/'.$two['id'], 2);
        $this->assertNull($open['data']['locked']);
        $this->assertStringContainsString('SECOND-BODY', $raw);
        $this->assertSame('One', $open['data']['prev']['title']);
    }

    #[Test]
    public function without_ordering_every_lesson_is_open_to_an_enrolled_member(): void
    {
        $course = $this->course();
        $this->lesson($course['id']);
        $two = $this->lesson($course['id'], ['title' => 'Two']);
        $this->enrol($course['id']);

        [, $open] = $this->json('GET', '/api/syllabus/lessons/'.$two['id'], 2);
        $this->assertNull($open['data']['locked']);
    }

    #[Test]
    public function completing_every_lesson_completes_the_course_once_and_issues_a_verifiable_certificate(): void
    {
        $course = $this->course(['title' => 'Bread <b>101</b>']);
        $one = $this->lesson($course['id']);
        $two = $this->lesson($course['id'], ['type' => 'video', 'videoUrl' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->enrol($course['id']);

        $this->complete($one['id']);
        $last = $this->complete($two['id']);
        $this->assertTrue($last['courseCompleted']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $last['certificateCode']);

        // A second tick changes nothing and issues nothing.
        $again = $this->complete($two['id']);
        $this->assertNull($again['certificateCode']);
        $this->assertSame(1, $this->database()->table('syllabus_certificates')->count());

        [, $show] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertSame(100, $show['data']['me']['percent']);
        $this->assertNotNull($show['data']['me']['completedAt']);
        $this->assertSame($last['certificateCode'], $show['data']['certificateCode']);

        // Anyone with the link can verify it — signed out included.
        [$status, $cert] = $this->json('GET', '/api/syllabus/certificates/'.$last['certificateCode']);
        $this->assertSame(200, $status);
        $this->assertSame('normal', $cert['data']['userName']);
        $this->assertSame('Bread <b>101</b>', $cert['data']['courseTitle']);
        $this->assertFalse($cert['data']['isOwner']);
        [, $mine] = $this->json('GET', '/api/syllabus/certificates/'.$last['certificateCode'], 2);
        $this->assertTrue($mine['data']['isOwner']);

        [$status] = $this->json('GET', '/api/syllabus/certificates/'.str_repeat('0', 32));
        $this->assertSame(404, $status);

        $this->assertSame(1, $this->database()->table('notifications')->where('user_id', 2)->where('type', 'syllabusCourseCompleted')->count());

        // A lesson added later moves the percentage, never the completion.
        $this->lesson($course['id'], ['title' => 'Bonus']);
        [, $show] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertSame(66, $show['data']['me']['percent']);
        $this->assertNotNull($show['data']['me']['completedAt']);

        // Deleting the course leaves the certificate valid.
        $this->send($this->request('DELETE', '/api/syllabus/courses/'.$course['id'], ['authenticatedAs' => 1]));
        [$status, $cert] = $this->json('GET', '/api/syllabus/certificates/'.$last['certificateCode']);
        $this->assertSame(200, $status);
        $this->assertNull($cert['data']['course']);
    }

    #[Test]
    public function assignments_and_quizzes_cannot_be_ticked_off_by_hand(): void
    {
        $course = $this->course();
        $task = $this->lesson($course['id'], ['type' => 'assignment', 'title' => 'Task']);
        $this->enrol($course['id']);

        [$status] = $this->json('POST', '/api/syllabus/lessons/'.$task['id'].'/complete', 2);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function leaving_a_course_keeps_what_was_done(): void
    {
        $course = $this->course();
        $one = $this->lesson($course['id']);
        $this->lesson($course['id']);
        $this->enrol($course['id']);
        $this->complete($one['id']);

        $this->send($this->request('DELETE', '/api/syllabus/courses/'.$course['id'].'/enrolment', ['authenticatedAs' => 2]));
        [, $show] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertNull($show['data']['me']);
        $this->assertSame(0, $show['data']['enrolmentCount']);

        $again = $this->enrol($course['id']);
        $this->assertSame(50, $again['me']['percent']);
    }

    #[Test]
    public function my_courses_and_the_profile_show_progress_with_the_members_privacy_choice(): void
    {
        $course = $this->course();
        $one = $this->lesson($course['id']);
        $this->lesson($course['id']);
        $this->course(['title' => 'Not taken']);
        $this->enrol($course['id']);
        $this->complete($one['id']);

        [, $mine, $raw] = $this->json('GET', '/api/syllabus/courses', 2, null, ['filter' => ['mine' => '1']]);
        $this->assertIsArray($mine['data'] ?? null, $raw);
        $this->assertCount(1, $mine['data']);
        $this->assertSame(50, $mine['data'][0]['me']['percent']);
        $this->assertSame($one['id'], $mine['data'][0]['me']['lastLessonId']);

        [, $profile] = $this->json('GET', '/api/syllabus/users/2/courses', 3);
        $this->assertSame(50, $profile['data'][0]['me']['percent'], 'the profile owner\'s progress, not the viewer\'s');

        $this->database()->table('users')->where('id', 2)->update(['preferences' => json_encode(['syllabusShowCourses' => false])]);
        [, $hidden] = $this->json('GET', '/api/syllabus/users/2/courses', 3);
        $this->assertTrue($hidden['meta']['hidden']);
        $this->assertSame([], $hidden['data']);
        [, $own] = $this->json('GET', '/api/syllabus/users/2/courses', 2);
        $this->assertCount(1, $own['data']);
    }
}
