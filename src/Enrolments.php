<?php

namespace Ernestdefoe\Syllabus;

use Flarum\User\User;
use Illuminate\Database\Query\Builder;

/**
 * Who is enrolled where. Part of Syllabus's public contract (see the README):
 * other extensions may resolve this class and call its methods, and their
 * signatures and results are stable.
 */
class Enrolments
{
    /**
     * The ids of the courses a member is enrolled in, as a query (one column,
     * `course_id`) to use as a subquery, so a caller's own visibility check
     * stays one query:
     *
     *     $query->whereIn('syllabus_course_id', resolve(Enrolments::class)->courseIdsFor($actor));
     *
     * A guest is enrolled nowhere: the query matches no rows. Enrollment is
     * the fact itself; whether the member may still open the course (paid or
     * group access) is the course's own rule, not part of this answer.
     */
    public function courseIdsFor(User $user): Builder
    {
        $query = Enrolment::query()->toBase()->select('course_id');

        return $user->isGuest() ? $query->whereRaw('1 = 0') : $query->where('user_id', $user->id);
    }
}
