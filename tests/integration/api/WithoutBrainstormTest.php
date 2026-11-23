<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Brainstorm installed but disabled: no quiz lessons, and nothing errors. */
class WithoutBrainstormTest extends SyllabusTestCase
{
    #[Test]
    public function there_is_no_quiz_lesson_type(): void
    {
        $course = $this->course();

        [$status] = $this->json('POST', "/api/syllabus/courses/{$course['id']}/lessons", 1, ['data' => ['title' => 'Q', 'type' => 'quiz', 'quizId' => 1]]);
        $this->assertSame(422, $status);

        [, $meta] = $this->json('GET', '/api/syllabus/editor', 1);
        $this->assertFalse($meta['data']['quizzesEnabled']);
        $this->assertSame([], $meta['data']['quizzes']);
    }

    #[Test]
    public function quiz_lessons_left_behind_neither_show_nor_count(): void
    {
        $course = $this->course();
        $text = $this->lesson($course['id']);
        // As if Brainstorm had been switched off after this lesson was written.
        $this->database()->table('syllabus_lessons')->insert([
            'course_id' => $course['id'], 'title' => 'Orphan quiz', 'slug' => 'orphan-quiz', 'type' => 'quiz', 'quiz_id' => 99,
            'status' => 'published', 'position' => 9, 'published_at' => date('Y-m-d H:i:s'),
        ]);
        $orphan = (int) $this->database()->table('syllabus_lessons')->where('slug', 'orphan-quiz')->value('id');

        $this->enrol($course['id']);
        [, $show, $raw] = $this->json('GET', '/api/syllabus/courses/'.$course['id'], 2);
        $this->assertSame(200, $show ? 200 : 0, $raw);
        $this->assertStringNotContainsString('Orphan quiz', $raw);
        $this->assertSame(1, $show['data']['lessonCount']);
        [$status] = $this->json('GET', '/api/syllabus/lessons/'.$orphan, 2);
        $this->assertSame(404, $status);

        $done = $this->complete($text['id']);
        $this->assertTrue($done['courseCompleted']);
    }
}
