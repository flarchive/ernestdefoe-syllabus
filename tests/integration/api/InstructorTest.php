<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

class InstructorTest extends SyllabusTestCase
{
    #[Test]
    public function members_cannot_create_courses(): void
    {
        [$status] = $this->json('POST', '/api/syllabus/courses', 2, ['data' => ['title' => 'Mine']]);
        $this->assertSame(403, $status);
        [$status] = $this->json('POST', '/api/syllabus/courses', null, ['data' => ['title' => 'Mine']]);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function an_instructor_edits_only_their_own_courses_and_those_they_were_added_to(): void
    {
        $daves = $this->course(['status' => 'draft'], 4);

        // erin cannot see dave's draft, let alone edit it.
        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 5, ['data' => ['title' => 'Hijacked']]);
        $this->assertSame(404, $status);

        // Published, she can see it but not edit it, add lessons or see students.
        $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 4, ['data' => ['status' => 'published']]);
        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 5, ['data' => ['title' => 'Hijacked']]);
        $this->assertSame(403, $status);
        [$status] = $this->json('POST', '/api/syllabus/courses/'.$daves['id'].'/lessons', 5, ['data' => ['title' => 'Mine now']]);
        $this->assertSame(403, $status);
        [$status] = $this->json('GET', '/api/syllabus/courses/'.$daves['id'].'/students', 5);
        $this->assertSame(403, $status);
        [$status] = $this->json('DELETE', '/api/syllabus/courses/'.$daves['id'], 5);
        $this->assertSame(403, $status);

        $lesson = $this->lesson($daves['id'], [], 4);
        [$status] = $this->json('PATCH', '/api/syllabus/lessons/'.$lesson['id'], 5, ['data' => ['title' => 'Hijacked']]);
        $this->assertSame(403, $status);

        // A plain member cannot be made co-instructor; erin can.
        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 4, ['data' => ['instructorIds' => [3]]]);
        $this->assertSame(422, $status);
        [$status, $body] = $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 4, ['data' => ['instructorIds' => [5]]]);
        $this->assertSame(200, $status);
        $this->assertSame('erin', $body['data']['edit']['instructors'][0]['username']);

        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 5, ['data' => ['title' => 'Co-written']]);
        $this->assertSame(200, $status);
        // A co-instructor does not choose who else teaches.
        $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 5, ['data' => ['instructorIds' => []]]);
        $this->assertSame(1, $this->database()->table('syllabus_instructors')->count());

        // A moderator manages every course.
        [$status] = $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 6, ['data' => ['featured' => true]]);
        $this->assertSame(200, $status);
        // Instructors cannot feature their own.
        $this->json('PATCH', '/api/syllabus/courses/'.$daves['id'], 4, ['data' => ['featured' => false]]);
        $this->assertTrue((bool) $this->database()->table('syllabus_courses')->where('id', $daves['id'])->value('featured'));
    }

    #[Test]
    public function the_teaching_panel_lists_only_what_the_instructor_teaches(): void
    {
        $this->course(['title' => 'Daves'], 4);
        $this->course(['title' => 'Erins', 'status' => 'draft'], 5);

        [, $dave] = $this->json('GET', '/api/syllabus/teaching', 4);
        $this->assertSame(['Daves'], array_column($dave['data'], 'title'));
        [, $erin] = $this->json('GET', '/api/syllabus/teaching', 5);
        $this->assertSame(['Erins'], array_column($erin['data'], 'title'));
        [, $mod] = $this->json('GET', '/api/syllabus/teaching', 6);
        $this->assertCount(2, $mod['data']);
        [$status] = $this->json('GET', '/api/syllabus/teaching', 2);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function the_outline_is_reordered_into_sections_by_drag_and_drop(): void
    {
        $course = $this->course([], 4);
        $a = $this->lesson($course['id'], ['title' => 'A'], 4);
        $b = $this->lesson($course['id'], ['title' => 'B'], 4);
        $c = $this->lesson($course['id'], ['title' => 'C'], 4);

        [$status, $body, $raw] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'].'/outline', 4, ['data' => ['items' => [
            ['kind' => 'section', 'title' => 'Basics'],
            ['kind' => 'lesson', 'id' => $c['id']],
            ['kind' => 'section', 'title' => 'More'],
            ['kind' => 'lesson', 'id' => $a['id']],
        ]]]);
        $this->assertSame(200, $status, $raw);
        $this->assertSame(['Basics', 'C', 'More', 'A', 'B'], array_column($body['data']['outline'], 'title'));

        // Rename one section, drop the other: its lessons stay.
        $basics = $body['data']['outline'][0]['id'];
        [, $body] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'].'/outline', 4, ['data' => ['items' => [
            ['kind' => 'lesson', 'id' => $b['id']],
            ['kind' => 'section', 'id' => $basics, 'title' => 'Start here'],
            ['kind' => 'lesson', 'id' => $a['id']],
            ['kind' => 'lesson', 'id' => $c['id']],
        ]]]);
        $this->assertSame(['B', 'Start here', 'A', 'C'], array_column($body['data']['outline'], 'title'));
        $this->assertSame(1, $this->database()->table('syllabus_sections')->count());

        // Lessons from another course are ignored.
        $other = $this->course([], 5);
        $foreign = $this->lesson($other['id'], ['title' => 'Foreign'], 5);
        [, $body] = $this->json('PATCH', '/api/syllabus/courses/'.$course['id'].'/outline', 4, ['data' => ['items' => [['kind' => 'lesson', 'id' => $foreign['id']]]]]);
        $this->assertNotContains('Foreign', array_column($body['data']['outline'], 'title'));
    }

    #[Test]
    public function managers_order_categories(): void
    {
        [$status] = $this->json('POST', '/api/syllabus/categories', 4, ['data' => ['name' => 'Nope']]);
        $this->assertSame(403, $status);

        $this->json('POST', '/api/syllabus/categories', 1, ['data' => ['name' => 'Cooking']]);
        [, $body] = $this->json('POST', '/api/syllabus/categories', 1, ['data' => ['name' => 'Music']]);
        $ids = array_column($body['data'], 'id');

        [, $body] = $this->json('POST', '/api/syllabus/categories/order', 1, ['data' => ['ids' => array_reverse($ids)]]);
        $this->assertSame(['Music', 'Cooking'], array_column($body['data'], 'name'));

        $this->course(['categoryId' => $ids[0]]);
        [, $list] = $this->json('GET', '/api/syllabus/categories');
        $this->assertSame(1, $list['data'][1]['courseCount']);
        [, $filtered] = $this->json('GET', '/api/syllabus/courses', null, null, ['filter' => ['category' => (string) $ids[1]]]);
        $this->assertCount(0, $filtered['data']);
    }

    #[Test]
    public function students_and_their_progress_for_the_instructor(): void
    {
        $course = $this->course([], 4);
        $one = $this->lesson($course['id'], [], 4);
        $this->lesson($course['id'], [], 4);
        $this->enrol($course['id'], 2);
        $this->enrol($course['id'], 3);
        $this->complete($one['id'], 2);

        [$status, $body] = $this->json('GET', '/api/syllabus/courses/'.$course['id'].'/students', 4);
        $this->assertSame(200, $status);
        $byName = array_column(array_map(fn ($r) => ['n' => $r['user']['username'], 'p' => $r['percent']], $body['data']), 'p', 'n');
        ksort($byName);
        $this->assertSame(['carol' => 0, 'normal' => 50], $byName);

        // New lessons notify enrolled members, not the instructor.
        $this->lesson($course['id'], ['title' => 'Fresh'], 4);
        $this->assertSame(2, $this->database()->table('notifications')->where('type', 'syllabusNewLesson')->count());
    }
}
