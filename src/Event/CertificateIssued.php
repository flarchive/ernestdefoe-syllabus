<?php

namespace Ernestdefoe\Syllabus\Event;

use Ernestdefoe\Syllabus\Certificate;
use Ernestdefoe\Syllabus\Course;
use Flarum\User\User;

/**
 * A certificate was issued for a completed course: once per member and
 * course, right after CourseCompleted. $certificate->skills holds the skills
 * it was issued with; $certificate->code is its verification code.
 * Certificates are never revoked, so there is no revocation event.
 *
 * Part of Syllabus's public contract (see the README): the class name and its
 * properties are stable.
 */
final class CertificateIssued
{
    public function __construct(public readonly User $user, public readonly Course $course, public readonly Certificate $certificate)
    {
    }
}
