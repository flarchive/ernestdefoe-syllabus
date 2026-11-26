<?php

namespace Ernestdefoe\Syllabus\Event;

use Ernestdefoe\Syllabus\Course;
use Flarum\User\User;

/**
 * A member completed a course: every counted lesson is done. Fired once per
 * member and course, ever: completing it again after leaving and re-enrolling
 * doesn't fire it again. CertificateIssued follows straight after.
 *
 * Part of Syllabus's public contract (see the README): the class name and its
 * properties are stable.
 */
final class CourseCompleted
{
    public function __construct(public readonly User $user, public readonly Course $course)
    {
    }
}
