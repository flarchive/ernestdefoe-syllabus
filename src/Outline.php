<?php

namespace Ernestdefoe\Syllabus;

use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * 🚨 The one place that decides which lessons a member may read.
 *
 * The course page, the lesson page, "mark complete", the video and file
 * downloads and next/previous all ask {@see build()}. A lesson counts — towards
 * progress and as a step in a "lessons unlock in order" course — when it is
 * published, and, for a quiz lesson, while Brainstorm is enabled.
 */
class Outline
{
    public function __construct(private Brainstorm $brainstorm, private ConnectionInterface $db)
    {
    }

    /** @return Builder<Lesson> */
    public function countable(): Builder
    {
        $query = Lesson::query()->where('status', 'published');

        if (! $this->brainstorm->enabled()) {
            $query->where('type', '!=', 'quiz');
        }

        return $query;
    }

    public function counts(Lesson $lesson): bool
    {
        return $lesson->isPublished() && ($lesson->type !== 'quiz' || $this->brainstorm->enabled());
    }

    /**
     * Countable lessons per course, for a whole list in one query.
     *
     * @param list<int> $courseIds
     * @return array<int, int>
     */
    public function totals(array $courseIds): array
    {
        if (! $courseIds) {
            return [];
        }

        return $this->countable()->whereIn('course_id', $courseIds)
            ->groupBy('course_id')
            ->selectRaw('course_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Countable lessons a member completed per course, in one query.
     *
     * @param list<int> $courseIds
     * @return array<int, int>
     */
    public function done(array $courseIds, int $userId): array
    {
        if (! $courseIds) {
            return [];
        }

        $countable = $this->countable()->whereIn('course_id', $courseIds)->select('id');

        return $this->db->table('syllabus_progress')
            ->where('user_id', $userId)
            ->whereIn('course_id', $courseIds)
            ->whereIn('lesson_id', $countable->getQuery())
            ->groupBy('course_id')
            ->selectRaw('course_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * The whole course as this actor sees it. Four small queries: the
     * enrolment, the lessons, the sections and the actor's progress.
     */
    public function build(Course $course, User $actor, ?Enrolment $enrolment = null, bool $withEnrolment = true): CourseState
    {
        $editor = Access::canEdit($actor, $course);

        if ($withEnrolment && ! $enrolment && ! $actor->isGuest()) {
            $enrolment = Enrolment::query()->where('course_id', $course->id)->where('user_id', $actor->id)->first();
        }

        $all = $course->relationLoaded('lessons') ? $course->lessons : $course->lessons()->get();
        $lessons = $all->filter(fn (Lesson $l) => $editor || $this->counts($l))->values();
        $sections = $course->relationLoaded('sections') ? $course->sections : $course->sections()->get();

        $completed = [];
        if (! $actor->isGuest() && $lessons->isNotEmpty()) {
            foreach ($this->db->table('syllabus_progress')->where('user_id', $actor->id)->where('course_id', $course->id)->pluck('lesson_id') as $id) {
                $completed[(int) $id] = true;
            }
        }

        $meetsRule = Access::meetsRule($actor, $course);
        $canEnrol = ! $enrolment && Access::canEnrol($actor, $course);

        $locks = $previews = [];
        $index = 0;
        $allBefore = true;
        $total = $done = 0;

        foreach ($lessons as $lesson) {
            if (! $this->counts($lesson)) {
                // A draft (or a quiz lesson with Brainstorm off): editors only.
                $locks[$lesson->id] = $editor ? null : 'access';
                continue;
            }

            $total++;
            $isDone = isset($completed[$lesson->id]);
            $done += $isDone ? 1 : 0;

            $preview = $index < $course->preview_lessons;
            if ($preview) {
                $previews[$lesson->id] = true;
            }

            $locks[$lesson->id] = match (true) {
                $editor, $preview => null,
                $actor->isGuest() => 'login',
                ! $enrolment => $canEnrol ? 'enrol' : 'access',
                ! $meetsRule => 'access',
                $course->sequential && ! $allBefore => 'order',
                default => null,
            };

            $allBefore = $allBefore && $isDone;
            $index++;
        }

        return new CourseState($course, $editor, $enrolment, $meetsRule, $canEnrol, $lessons, $sections, $completed, $locks, $previews, $total, $done);
    }
}
