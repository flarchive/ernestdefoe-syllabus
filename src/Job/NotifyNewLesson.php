<?php

namespace Ernestdefoe\Syllabus\Job;

use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Enrolment;
use Ernestdefoe\Syllabus\Lesson;
use Ernestdefoe\Syllabus\Notification\NewLessonBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;

/**
 * Tells a course's students about a newly published lesson, off the request
 * that published it: a course can have thousands of students. One sync for
 * everyone, because the syncer hides this notification from anyone left out
 * of the list.
 */
class NotifyNewLesson extends AbstractJob
{
    public function __construct(private int $lessonId, private int $actorId)
    {
    }

    public function handle(NotificationSyncer $notifications): void
    {
        $lesson = Lesson::query()->find($this->lessonId);
        $course = $lesson ? Course::query()->find($lesson->course_id) : null;
        $actor = User::query()->find($this->actorId);
        if (! $lesson || ! $course || ! $actor || ! $lesson->isPublished() || ! $course->isPublished()) {
            return;
        }

        $users = User::query()
            ->whereIn('id', Enrolment::query()->where('course_id', $course->id)->where('user_id', '!=', $actor->id)->select('user_id'))
            ->get()->all();

        if ($users) {
            $notifications->sync(new NewLessonBlueprint($lesson, $course, $actor), $users);
        }
    }
}
