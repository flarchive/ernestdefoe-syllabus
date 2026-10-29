<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The catalogue and a course page cost the same number of queries for one item or many. */
class QueryCountTest extends SyllabusTestCase
{
    protected function extensions(): array
    {
        return ['flarum-mentions', 'ernestdefoe-syllabus'];
    }

    /** @var list<string> */
    private array $log = [];

    private bool $listening = false;

    private function queries(string $path, array $query = [], int $actor = 2): int
    {
        $this->log = [];
        $this->listening || $this->database()->listen(function ($event) {
            // Core refreshes a member's "last seen" once in a while, on
            // whichever request happens to come due: not this page's cost.
            if (! preg_match('/^update .*users.* set .*last_seen_at/i', $event->sql)) {
                $this->log[] = $event->sql;
            }
        });
        $this->listening = true;
        $request = $this->request('GET', $path, ['authenticatedAs' => $actor])->withQueryParams($query);
        $this->send($request); // warm
        $this->log = [];
        $response = $this->send($request);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return count($this->log);
    }

    private function assertQueries(int $expected, string $path, array $query = [], int $actor = 2): void
    {
        $this->assertSame($expected, $this->queries($path, $query, $actor), $path."\n".implode("\n", $this->log));
    }

    #[Test]
    public function the_catalogue_and_my_courses_do_not_grow_with_the_course_count(): void
    {
        [, $cats] = $this->json('POST', '/api/syllabus/categories', 1, ['data' => ['name' => 'Cooking']]);
        $category = $cats['data'][0]['id'];
        $first = $this->course(['description' => 'Thanks @"normal"#2', 'categoryId' => $category], 4);
        $this->lesson($first['id'], [], 4);
        $this->enrol($first['id']);

        $one = $this->queries('/api/syllabus/courses');
        $mineOne = $this->queries('/api/syllabus/courses', ['filter' => ['mine' => '1']]);
        $teachOne = $this->queries('/api/syllabus/teaching', [], 4);

        for ($i = 0; $i < 6; $i++) {
            $course = $this->course(['title' => "Course $i", 'categoryId' => $category, 'description' => "Hi @\"admin\"#1 $i"], $i % 2 ? 4 : 5);
            $this->lesson($course['id'], [], $i % 2 ? 4 : 5);
            $this->enrol($course['id']);
        }

        $this->assertQueries($one, '/api/syllabus/courses');
        $this->assertQueries($mineOne, '/api/syllabus/courses', ['filter' => ['mine' => '1']]);
        $this->assertQueries($teachOne, '/api/syllabus/teaching', [], 4);
    }

    #[Test]
    public function a_course_page_does_not_grow_with_the_lesson_count(): void
    {
        $course = $this->course();
        $first = $this->lesson($course['id']);
        $this->enrol($course['id']);
        $this->complete($first['id']);

        $one = $this->queries('/api/syllabus/courses/'.$course['id']);
        $lessonOne = $this->queries('/api/syllabus/lessons/'.$first['id']);

        for ($i = 0; $i < 10; $i++) {
            $lesson = $this->lesson($course['id'], ['title' => "Lesson $i"]);
            $this->complete($lesson['id']);
        }
        $this->json('PATCH', '/api/syllabus/courses/'.$course['id'].'/outline', 1, ['data' => ['items' => [['kind' => 'section', 'title' => 'Part one']]]]);

        $this->assertQueries($one, '/api/syllabus/courses/'.$course['id']);
        $this->assertQueries($lessonOne, '/api/syllabus/lessons/'.$first['id']);
    }
}
