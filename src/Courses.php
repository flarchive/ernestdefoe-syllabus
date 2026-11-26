<?php

namespace Ernestdefoe\Syllabus;

use Flarum\User\User;

/**
 * Courses as other extensions see them. Part of Syllabus's public contract
 * (see the README): other extensions may resolve this class and call its
 * methods, and their signatures and results are stable.
 */
class Courses
{
    public const ATTACHABLE_LIMIT = 100;

    /**
     * The published courses a user may attach something to (a quiz, a
     * resource): the ones they teach and may still edit (owner or
     * co-instructor, with "Create and teach courses"), or every published
     * course for course managers and admins. Ordered by title, at most
     * $limit (1–100). A guest may attach to none.
     *
     * @return list<array{id: int, title: string}>
     */
    public function attachableBy(User $user, int $limit = 50): array
    {
        if ($user->isGuest()) {
            return [];
        }

        $query = Course::query()->where('status', 'published');
        if (! Access::isManager($user)) {
            if (! $user->hasPermission('syllabus.create')) {
                return [];
            }
            $query->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereIn('id', fn ($sub) => $sub->select('course_id')->from('syllabus_instructors')->where('user_id', $user->id)));
        }

        return $query->orderBy('title')->orderBy('id')->limit(max(1, min(self::ATTACHABLE_LIMIT, $limit)))->get(['id', 'title'])
            ->map(fn (Course $course) => ['id' => (int) $course->id, 'title' => (string) $course->title])
            ->values()->all();
    }
}
