<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Quiz lessons with Brainstorm enabled. */
class BrainstormTest extends SyllabusTestCase
{
    protected function extensions(): array
    {
        return ['ernestdefoe-brainstorm', 'ernestdefoe-syllabus'];
    }

    /** A published one-question Brainstorm quiz by $author. */
    private function quiz(int $author = 1, string $title = 'Bread quiz'): int
    {
        [$status, $body, $raw] = $this->json('POST', '/api/quizzes', $author, ['data' => [
            'title' => $title,
            'status' => 'published',
            'passMark' => 100,
            'questions' => [['type' => 'boolean', 'body' => 'Bread needs flour.', 'options' => [['id' => 'true', 'correct' => true]]]],
        ]]);
        $this->assertSame(201, $status, $raw);

        return $body['data']['id'];
    }

    private function take(int $quizId, string $answer, int $actor = 2): bool
    {
        [, $paper, $raw] = $this->json('POST', "/api/quizzes/$quizId/attempts", $actor);
        $this->assertArrayHasKey('data', $paper, $raw);
        $question = (string) $paper['data']['questions'][0]['id'];
        [$status, $result, $raw] = $this->json('POST', '/api/quiz-attempts/'.$paper['data']['id'].'/submit', $actor, ['data' => ['answers' => [$question => $answer]]]);
        $this->assertSame(200, $status, $raw);

        return $result['data']['passed'];
    }

    #[Test]
    public function passing_the_quiz_completes_the_lesson_and_unlocks_the_next(): void
    {
        $quizId = $this->quiz();
        $course = $this->course(['sequential' => true]);
        $lesson = $this->lesson($course['id'], ['type' => 'quiz', 'quizId' => $quizId, 'title' => 'Check yourself']);
        $after = $this->lesson($course['id'], ['title' => 'After the quiz']);
        $this->enrol($course['id']);

        [, $open] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertSame($quizId, $open['data']['quiz']['id']);
        $this->assertFalse($open['data']['canComplete'], 'a quiz is passed, not ticked off');
        [, $next] = $this->json('GET', '/api/syllabus/lessons/'.$after['id'], 2);
        $this->assertSame('order', $next['data']['locked']);

        $this->assertFalse($this->take($quizId, 'false'));
        [, $still] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertFalse($still['data']['completed']);

        $this->assertTrue($this->take($quizId, 'true'));
        [, $done] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertTrue($done['data']['completed']);
        [, $next] = $this->json('GET', '/api/syllabus/lessons/'.$after['id'], 2);
        $this->assertNull($next['data']['locked']);
    }

    #[Test]
    public function a_quiz_passed_before_enrolling_counts_when_the_lesson_is_opened(): void
    {
        $quizId = $this->quiz();
        $this->assertTrue($this->take($quizId, 'true'));

        $course = $this->course();
        $lesson = $this->lesson($course['id'], ['type' => 'quiz', 'quizId' => $quizId]);
        $this->enrol($course['id']);

        [, $open] = $this->json('GET', '/api/syllabus/lessons/'.$lesson['id'], 2);
        $this->assertTrue($open['data']['completed']);
    }

    #[Test]
    public function instructors_pick_only_quizzes_they_may_edit(): void
    {
        $adminsQuiz = $this->quiz(1, 'Admins quiz');
        $course = $this->course([], 4);

        // dave writes no quizzes (no Brainstorm permission): the admin's is not his to embed.
        [$status, , $raw] = $this->json('POST', "/api/syllabus/courses/{$course['id']}/lessons", 4, ['data' => ['title' => 'Q', 'type' => 'quiz', 'quizId' => $adminsQuiz]]);
        $this->assertSame(422, $status, $raw);

        [, $meta] = $this->json('GET', '/api/syllabus/editor', 4);
        $this->assertTrue($meta['data']['quizzesEnabled']);
        $this->assertSame([], $meta['data']['quizzes']);

        [, $meta] = $this->json('GET', '/api/syllabus/editor', 1);
        $this->assertSame(['Admins quiz'], array_column($meta['data']['quizzes'], 'title'));
    }

    #[Test]
    public function quiz_results_show_in_the_students_view(): void
    {
        $quizId = $this->quiz();
        $course = $this->course();
        $this->lesson($course['id'], ['type' => 'quiz', 'quizId' => $quizId]);
        $this->enrol($course['id']);
        $this->take($quizId, 'true');

        [, $students] = $this->json('GET', '/api/syllabus/courses/'.$course['id'].'/students', 1);
        $this->assertSame(1, $students['meta']['quizzes'][0]['passed']);
        $this->assertSame(100, $students['data'][0]['percent']);
    }
}
