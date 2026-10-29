<?php

namespace Ernestdefoe\Syllabus;

use Flarum\User\User;

/**
 * Who may do what to a course. Every controller asks here, so the rules live
 * in one place; what a member may read inside a course is decided by
 * {@see Outline}, which asks here too.
 */
class Access
{
    public static function canView(User $actor, Course $course): bool
    {
        return $actor->hasPermission('syllabus.view')
            && ($course->isPublished() || self::canEdit($actor, $course));
    }

    public static function canCreate(User $actor): bool
    {
        return ! $actor->isGuest() && ($actor->hasPermission('syllabus.create') || self::isManager($actor));
    }

    public static function isManager(User $actor): bool
    {
        return ! $actor->isGuest() && $actor->hasPermission('syllabus.manage');
    }

    /** The owner or a co-instructor the owner added. */
    public static function isInstructor(User $actor, Course $course): bool
    {
        if ($actor->isGuest()) {
            return false;
        }

        if ($course->user_id === $actor->id) {
            return true;
        }

        return $course->relationLoaded('instructors')
            ? $course->instructors->contains('id', $actor->id)
            : $course->instructors()->where('users.id', $actor->id)->exists();
    }

    /**
     * Instructors while they may still teach, and managers. The same rule
     * reviews the course's assignments and sees its students.
     */
    public static function canEdit(User $actor, Course $course): bool
    {
        if ($actor->isGuest()) {
            return false;
        }

        return self::isManager($actor)
            || ($actor->hasPermission('syllabus.create') && self::isInstructor($actor, $course));
    }

    /** Only the owner (or a manager) chooses who teaches alongside them. */
    public static function canChooseInstructors(User $actor, Course $course): bool
    {
        return self::isManager($actor) || ($course->user_id === $actor->id && $actor->hasPermission('syllabus.create'));
    }

    /**
     * The course's access rule: everyone who may enrol, or only members of the
     * chosen groups — which is how paid courses work. Anything that adds a
     * member to a group on payment unlocks the course; removing them locks it
     * again.
     */
    public static function meetsRule(User $actor, Course $course): bool
    {
        if ($actor->isGuest() || ! $actor->hasPermission('syllabus.enrol')) {
            return false;
        }

        if ($course->access !== 'groups') {
            return true;
        }

        $allowed = $course->relationLoaded('groups')
            ? $course->groups->pluck('id')->all()
            : $course->groups()->pluck('groups.id')->all();

        return (bool) array_intersect(array_map('intval', $allowed), array_map('intval', $actor->permissionGroupIds()));
    }

    public static function canEnrol(User $actor, Course $course): bool
    {
        return $course->isPublished() && self::canView($actor, $course) && self::meetsRule($actor, $course);
    }
}
