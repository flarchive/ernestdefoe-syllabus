<?php

namespace Ernestdefoe\Syllabus\Event;

use Ernestdefoe\Syllabus\Course;
use Flarum\User\User;

/**
 * A member enrolled in a course: by enrolling themselves, or by buying it
 * through Kiosk. Fired once per enrollment, when the enrollment is created,
 * never when the course or a lesson is visited. A member who leaves and
 * enrolls again is a new enrollment.
 *
 * Part of Syllabus's public contract (see the README): the class name and its
 * properties are stable.
 */
final class CourseEnrolled
{
    public function __construct(public readonly User $user, public readonly Course $course)
    {
    }
}
