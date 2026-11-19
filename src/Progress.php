<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Ernestdefoe\Syllabus\Notification\CourseCompletedBlueprint;
use Flarum\Extension\ExtensionManager;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;

/**
 * Completing lessons and courses. A course is completed — once, as a dated
 * fact — when an enrolled member has completed every countable lesson; that
 * moment issues the certificate, sends the notification and awards the
 * course's badge.
 */
class Progress
{
    public function __construct(
        private ConnectionInterface $db,
        private Outline $outline,
        private NotificationSyncer $notifications,
        private ExtensionManager $extensions,
        private Container $container
    ) {
    }

    public function isComplete(Lesson $lesson, int $userId): bool
    {
        return $this->db->table('syllabus_progress')->where('lesson_id', $lesson->id)->where('user_id', $userId)->exists();
    }

    /** Record a completed lesson; returns the certificate if that finished the course. */
    public function complete(Lesson $lesson, User $user): ?Certificate
    {
        $this->db->table('syllabus_progress')->insertOrIgnore([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'course_id' => $lesson->course_id,
            'completed_at' => Carbon::now(),
        ]);

        return $this->checkCourse($lesson->course, $user);
    }

    public function uncomplete(Lesson $lesson, User $user): void
    {
        $this->db->table('syllabus_progress')->where('lesson_id', $lesson->id)->where('user_id', $user->id)->delete();
    }

    /** Remember where the member got to, for "continue where you left off". */
    public function touch(Enrolment $enrolment, Lesson $lesson): void
    {
        $enrolment->last_lesson_id = $lesson->id;
        $enrolment->last_seen_at = Carbon::now();
        $enrolment->save();
    }

    public function checkCourse(Course $course, User $user): ?Certificate
    {
        $enrolment = Enrolment::query()->where('course_id', $course->id)->where('user_id', $user->id)->first();
        if (! $enrolment || $enrolment->completed_at) {
            return null;
        }

        $total = $this->outline->totals([$course->id])[$course->id] ?? 0;
        $done = $this->outline->done([$course->id], $user->id)[$course->id] ?? 0;

        if ($total === 0 || $done < $total) {
            return null;
        }

        // Set only if still unset: two lessons finished at once issue one certificate.
        $claimed = Enrolment::query()->whereKey($enrolment->id)->whereNull('completed_at')->update(['completed_at' => Carbon::now()]);
        if (! $claimed) {
            return null;
        }

        $certificate = Certificate::query()->where('course_id', $course->id)->where('user_id', $user->id)->first();
        if (! $certificate) {
            $certificate = new Certificate();
            $certificate->course_id = $course->id;
            $certificate->user_id = $user->id;
            $certificate->code = Certificate::newCode();
            $certificate->course_title = $course->title;
            $certificate->user_name = $user->display_name;
            $certificate->issued_at = Carbon::now();
            $certificate->save();
        }

        $this->notifications->sync(new CourseCompletedBlueprint($course, $certificate), [$user]);
        $this->awardBadge($course, $user);

        return $certificate;
    }

    /**
     * The course's fof/badges badge, if it has one and that extension is on.
     * Never to the course's own instructors: they wrote it.
     */
    private function awardBadge(Course $course, User $user): void
    {
        if (! $course->badge_id || ! $this->extensions->isEnabled('fof-badges') || ! class_exists('FoF\Badges\Badge')
            || Access::isInstructor($user, $course)) {
            return;
        }

        $badge = \FoF\Badges\Badge::query()->find($course->badge_id);
        if ($badge) {
            $this->container->make('FoF\Badges\BadgeAwarder')->award($user, $badge, \FoF\Badges\UserBadge::GRANTED_BY_SYSTEM);
        }
    }
}
