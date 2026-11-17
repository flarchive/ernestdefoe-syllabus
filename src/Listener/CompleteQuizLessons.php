<?php

namespace Ernestdefoe\Syllabus\Listener;

use ErnestDefoe\Brainstorm\Event\AttemptFinished;
use Ernestdefoe\Syllabus\Enrolment;
use Ernestdefoe\Syllabus\Lesson;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Progress;

/**
 * Passing a Brainstorm quiz completes every quiz lesson built on it in the
 * member's enrolled courses — where that lesson is open to them. Practice
 * attempts (an editor trying their own quiz) never count. Registered only
 * while Brainstorm is enabled.
 */
class CompleteQuizLessons
{
    public function __construct(private Outline $outline, private Progress $progress)
    {
    }

    public function handle(AttemptFinished $event): void
    {
        $attempt = $event->attempt;
        if (! $attempt->passed || $attempt->practice) {
            return;
        }

        $lessons = Lesson::query()
            ->where('type', 'quiz')
            ->where('quiz_id', $attempt->quiz_id)
            ->where('status', 'published')
            ->whereIn('course_id', Enrolment::query()->where('user_id', $event->user->id)->select('course_id'))
            ->with(['course.instructors', 'course.groups'])
            ->get();

        foreach ($lessons as $lesson) {
            $state = $this->outline->build($lesson->course, $event->user);
            $open = $state->lesson($lesson->id);

            if ($open && $state->unlocked($open)) {
                $open->setRelation('course', $lesson->course);
                $this->progress->complete($open, $event->user);
            }
        }
    }
}
